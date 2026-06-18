<?php
// We should notify both approver and applicant use (dispatch instead of sendToUser) with relevant messages for all actions of this contoller and any other event of success and failure. Avoid using default `prompt` instead use modals. Ensure to use ApprovalController for DRY and consistence. Enforce logAudit and (RBAC + IDOR), Enforce audit COMPLIANCE_AUDIT.md (4. Loan Management). Embrace canAccessMemberRecord for all methods that fetch records. Determine the user's data scope (Global, Group, Personal, or None)
namespace App\Controllers;

use App\Models\Loan;
use App\Models\Member;
use App\Models\Approval;
use App\Helpers\Format;
use App\Helpers\LoanSequence;
use App\Services\NotificationService;
use Database;

class LoanController extends BaseController
{
    private Loan $model;
    private Member $memberModel;
    private Approval $approval;
    private NotificationService $notif;

    public function __construct()
    {
        parent::__construct();
        $this->auth->requireAuth();
        $this->model       = new Loan();
        $this->memberModel = new Member();
        $this->approval    = new Approval(); 
        $this->notif       = new NotificationService();
    }

    // ── CRUD METHODS ───────────────────────────────────────────────

    public function index(): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'loans.view', 'group' => 'groups.view_members', 'personal' => 'loans.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to view loans.');
        $scope = $this->resolveDataScope($perms);

        ['page' => $page, 'limit' => $limit] = $this->getPaginationParams();

        $filters = [
            'status'     => $this->getQuery('status', ''),
            'member_id'  => $this->getQuery('member', ''),
            'product_id' => $this->getQuery('product', ''),
            'search'     => $this->getQuery('search', ''),
            'date_from'  => $this->getQuery('from', ''),
            'date_to'    => $this->getQuery('to', ''),
        ];

        // ENFORCE: Pass scope to model to filter data at the database level
        $result       = $this->model->getList($page, $limit, $filters, $scope);
        $loanStats    = $this->model->getStats($scope);
        $loanProducts = $this->db->fetchAll("SELECT id, name FROM loan_products WHERE status='active' ORDER BY name");

        // ENFORCE: Audit log for viewing
        $this->logAudit('loans_viewed', 'loans', null, null, "Viewed loans list");

        $this->view('loans/index', array_merge(
            $this->prepareViewData('Loans', 'loans'),
            ['result' => $result, 'loanStats' => $loanStats, 'loanProducts' => $loanProducts]
        ));
    }

    public function create(): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'loans.create', 'group' => 'groups.view_members', 'personal' => 'loans.create_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to create loans.');
        
        $loanProducts = $this->db->fetchAll("SELECT * FROM loan_products WHERE status='active' ORDER BY name");
        
        $prefillMember = null;
        $memberId = $this->getQuery('member');
        if ($memberId) {
            $prefillMember = $this->memberModel->find((int)$memberId);
            // ENFORCE: IDOR Guard for prefill member
            if ($prefillMember && !$this->canAccessMemberRecord((int)$prefillMember['id'])) {
                $prefillMember = null;
            }
        }

        $this->view('loans/create', array_merge(
            $this->prepareViewData('New Loan Application', 'loans', ['Loans' => APP_URL.'/loans', 'New' => null]),
            ['loanProducts' => $loanProducts, 'prefillMember' => $prefillMember]
        ));
    }

    public function store(): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'loans.create', 'group' => 'groups.view_members', 'personal' => 'loans.create_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to create loans.');
        $this->verifyCsrf();
        
        $data = $this->getPost();
        $missing = $this->validateRequired($data, ['member_id','loan_product_id','principal_amount','term_months','first_repayment_date','disbursement_method']);
        if ($missing) $this->jsonError('Please complete all required fields.', array_fill_keys($missing, 'Required.'), 422);
        
        $member  = $this->memberModel->find((int)$data['member_id']);
        if (!$member || $member['status'] !== 'active') $this->jsonError('Invalid or inactive member.', null, 404);
        
        // ENFORCE: IDOR Guard
        if (!$this->canAccessMemberRecord((int)$data['member_id'])) {
            $this->jsonError('You do not have permission to apply for a loan for this member.', null, 403);
        }
        
        $product = $this->db->fetchOne("SELECT * FROM loan_products WHERE id = ? AND status='active'", [(int)$data['loan_product_id']]);
        if (!$product) $this->jsonError('Invalid loan product.', null, 404);
        
        $principal = (float)$data['principal_amount'];
        $term      = (int)$data['term_months'];
        
        if ($principal < $product['min_amount'] || $principal > $product['max_amount']) {
            $this->jsonError("Loan amount must be between {$product['min_amount']} and {$product['max_amount']}.", null, 422);
        }
        if ($term < $product['min_term_months'] || $term > $product['max_term_months']) {
            $this->jsonError("Loan term must be between {$product['min_term_months']} and {$product['max_term_months']} months.", null, 422);
        }

        // COMPLIANCE: Active Loan Limits (Audit Sec 4.1)
        $settings = $this->getSettings();
        $maxActiveLoans = (int)($settings['max_active_loans_per_member'] ?? 1); 
        
        $activeLoansCount = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM loans WHERE member_id = ? AND status IN ('pending', 'approved', 'disbursed', 'active')",
            [(int)$data['member_id']]
        );

        if ($activeLoansCount >= $maxActiveLoans) {
            // ENFORCE: Notify applicant via multi-channel dispatch
            if (!empty($member['user_id'])) {
                $this->notif->dispatch(
                    (int)$member['user_id'],
                    'loan_rejected',
                    'Loan Application Rejected',
                    "Dear {$member['first_name']}, your loan application was automatically rejected because you have reached the maximum allowed active loans ({$maxActiveLoans})."
                );
            }
            
            $this->logAudit('loan_auto_rejected_limit', 'loans', 0, 'Loan', 
                "Auto-rejected loan application for member {$member['member_no']}: Active loan limit ({$maxActiveLoans}) exceeded. Current active: {$activeLoansCount}."
            );
            
            $this->jsonError("Application rejected: Member has reached the maximum allowed active loans ({$maxActiveLoans}).", null, 409);
        }

        $isShareholder = (bool)$member['is_shareholder'];
        $sharesHeld    = (int)($member['shares_held'] ?? 0);
        $calc = $this->model->calculateLoan($principal, (float)$product['interest_rate'], $term, $product['interest_type'], $isShareholder, $sharesHeld);
        
        $processingFee = $principal * ((float)$product['processing_fee_pct'] / 100);
        $insuranceFee  = $principal * ((float)$product['insurance_fee_pct'] / 100);
        $firstRepayDate    = $data['first_repayment_date'];
        $expectedMaturity  = date('Y-m-d', strtotime($firstRepayDate . ' +' . ($term - 1) . ' months'));
        
        $this->db->beginTransaction();
        try {
            $loanNo = LoanSequence::nextFormatted();
            $status = ($data['status'] ?? '') === 'draft' ? 'draft' : 'pending';
            
            $loanData = [
                'loan_no'               => $loanNo,
                'member_id'             => (int)$data['member_id'],
                'loan_product_id'       => (int)$data['loan_product_id'],
                'principal_amount'      => $principal,
                'interest_rate'         => $calc['interest_rate'],
                'interest_type'         => $product['interest_type'],
                'term_months'           => $term,
                'processing_fee'        => round($processingFee, 2),
                'insurance_fee'         => round($insuranceFee, 2),
                'total_interest'        => $calc['total_interest'],
                'total_payable'         => $calc['total_payable'],
                'monthly_installment'   => $calc['monthly_installment'],
                'balance_outstanding'   => $calc['total_payable'],
                'purpose'               => $data['purpose'] ?? null,
                'disbursement_method'   => $data['disbursement_method'],
                'disbursement_account'  => $data['disbursement_account'] ?? null,
                'application_date'      => date('Y-m-d'),
                'first_repayment_date'  => $firstRepayDate,
                'expected_maturity_date'=> $expectedMaturity,
                'status'                => $status,
                'created_by'            => $_SESSION['user_id'],
            ];
            
            $loanId = $this->model->create($loanData);
            
            if (!empty($data['guarantor_id']) && is_array($data['guarantor_id'])) {
                foreach ($data['guarantor_id'] as $gId) {
                    if (!$gId) continue;
                    $this->db->execute("INSERT INTO loan_guarantors (loan_id, guarantor_member_id, status) VALUES (?, ?, 'pending')", [$loanId, (int)$gId]);
                }
            }

            // ENFORCE: Notify applicant via multi-channel dispatch
            if (!empty($member['user_id'])) {
                $this->notif->dispatch(
                    (int)$member['user_id'],
                    'loan_applied',
                    'Loan Application Submitted',
                    "Dear {$member['first_name']}, your loan application {$loanNo} for " . Format::currency($principal) . " has been submitted and is pending approval."
                );
            }

            if ($status === 'pending') {
                // DRY: Route through centralized approval engine
                $this->approval->request('loan_application', $loanId, $loanNo, $principal, $_SESSION['user_id'], "Loan application for {$loanNo}");
                
                // ENFORCE: Notify approvers
                $this->notif->notifyApprovers('loan_application', $loanNo, $principal, $data['purpose'] ?? 'No purpose specified');
            }
            
            $this->logAudit('loan_applied', 'loans', $loanId, 'Loan', "Loan {$loanNo} applied for member {$member['member_no']}", null, json_encode($loanData));
                
            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/loans/' . $loanId], $status === 'draft' ? 'Loan saved as draft.' : 'Loan application submitted successfully!');
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('loan_application_failed', 'Loan Application Failed', "Failed to submit loan application for member {$member['member_no']}: " . $e->getMessage());
            $this->jsonError('Failed to submit loan application. Please try again.', null, 500);
        }
    }

    public function show(int $id): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'loans.view', 'group' => 'groups.view_members', 'personal' => 'loans.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to view loans.');
        
        $loan = $this->model->getDetail($id);
        if (!$loan) {
            $this->flash('error', 'Loan not found.');
            $this->redirect('/loans');
        }
        
        // ENFORCE: IDOR Guard
        if (!$this->canAccessMemberRecord($loan['member_id'])) {
            $this->flash('error', 'You do not have permission to view this loan.');
            $this->redirect('/dashboard');
        }

        $repayments = $this->db->fetchAll("
            SELECT t.*, CONCAT(u.first_name, ' ', u.last_name) AS recorded_by_name 
            FROM transactions t 
            LEFT JOIN users u ON u.id = t.created_by 
            WHERE t.loan_id = ? AND t.txn_type = 'loan_repayment' AND t.status = 'completed'
            ORDER BY t.transaction_date DESC, t.id DESC
        ", [$id]);

        // ENFORCE: Audit log for viewing sensitive financial data
        $this->logAudit('loan_viewed', 'loans', $id, 'Loan', "Viewed loan {$loan['loan_no']}");

        $this->view('loans/show', array_merge(
            $this->prepareViewData('Loan — ' . $loan['loan_no'], 'loans', ['Loans' => APP_URL.'/loans', $loan['loan_no'] => null]),
            ['loan' => $loan, 'repayments' => $repayments]
        ));
    }

    public function approve(int $id): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'loans.approve', 'group' => 'groups.view_members', 'personal' => 'loans.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to approve loans.');
        $this->verifyCsrf();
        
        $loan = $this->model->getDetail($id);
        if (!$loan) $this->jsonError('Loan not found.', null, 404);

        // ENFORCE: IDOR Guard
        if (!$this->canAccessMemberRecord($loan['member_id'])) {
            $this->jsonError('You do not have permission to approve this loan.', null, 403);
        }

        $approval = $this->db->fetchOne("SELECT id FROM approvals WHERE reference_type = 'loan_application' AND reference_id = ? AND status = 'pending'", [$id]);
        if (!$approval) {
            $this->jsonError('No pending approval found for this loan. It may have already been processed.', null, 404);
        }

        $this->db->beginTransaction();
        try {
            // DRY: Use the Approval model to handle core logic
            $this->approval->approve($approval['id'], $_SESSION['user_id'], $_POST['rejection_notes'] ?? '');
            
            // ENFORCE: Notify Applicant (Member)
            if (!empty($loan['user_id'])) {
                $this->notif->dispatch((int)$loan['user_id'], 'loan_approved', 'Loan Approved', 
                    "Dear {$loan['first_name']}, your loan application {$loan['loan_no']} for " . Format::currency((float)$loan['principal_amount']) . " has been approved.");
            }
            
            // ENFORCE: Notify the approver (current user)
            $this->notif->dispatch((int)$_SESSION['user_id'], 'loan_approved_by_you', 'Loan Approved', 
                "You have successfully approved loan {$loan['loan_no']}.");

            $this->logAudit('loan_approved', 'loans', $id, 'Loan', "Loan {$loan['loan_no']} approved");
            
            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/loans'], 'Loan approved successfully.');
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('loan_approval_failed', 'Loan Approval Failed', "Failed to approve loan {$loan['loan_no']}: " . $e->getMessage());
            $this->jsonError('Failed to approve loan: ' . $e->getMessage(), null, 500);
        }
    }

    public function reject(int $id): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'loans.approve', 'group' => 'groups.view_members', 'personal' => 'loans.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to reject loans.');
        $this->verifyCsrf();
        
        $loan = $this->model->getDetail($id);
        if (!$loan) $this->jsonError('Loan not found.', null, 404);

        // ENFORCE: IDOR Guard
        if (!$this->canAccessMemberRecord($loan['member_id'])) {
            $this->jsonError('You do not have permission to reject this loan.', null, 403);
        }

        $approval = $this->db->fetchOne("SELECT id FROM approvals WHERE reference_type = 'loan_application' AND reference_id = ? AND status = 'pending'", [$id]);
        if (!$approval) {
            $this->jsonError('No pending approval found for this loan.', null, 404);
        }

        $this->db->beginTransaction();
        try {
            $rejectionNotes = $_POST['rejection_notes'] ?? ($_POST['rejection_reason'] ?? 'No reason provided.');
            
            // DRY: Use the Approval model to handle core logic
            $this->approval->reject($approval['id'], $_SESSION['user_id'], $rejectionNotes);
            
            // ENFORCE: Notify Applicant (Member)
            if (!empty($loan['user_id'])) {
                $this->notif->dispatch((int)$loan['user_id'], 'loan_rejected', 'Loan Rejected', 
                    "Dear {$loan['first_name']}, your loan application {$loan['loan_no']} has been rejected. Reason: {$rejectionNotes}");
            }
            
            // ENFORCE: Notify the approver (current user)
            $this->notif->dispatch((int)$_SESSION['user_id'], 'loan_rejected_by_you', 'Loan Rejected', 
                "You have rejected loan {$loan['loan_no']}.");

            $this->logAudit('loan_rejected', 'loans', $id, 'Loan', "Loan {$loan['loan_no']} rejected. Reason: {$rejectionNotes}");
            
            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/loans'], 'Loan rejected successfully.');
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('loan_rejection_failed', 'Loan Rejection Failed', "Failed to reject loan {$loan['loan_no']}: " . $e->getMessage());
            $this->jsonError('Failed to reject loan: ' . $e->getMessage(), null, 500);
        }
    }

    public function requestDisbursement(int $id): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'loans.disburse', 'group' => 'groups.view_members', 'personal' => 'loans.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to disburse loans.');
        $this->verifyCsrf();
        
        $loan = $this->model->find($id);
        if (!$loan) $this->jsonError('Loan not found.', null, 404);
        if ($loan['status'] !== 'approved') $this->jsonError('Only approved loans can be queued for disbursement.', null, 409);

        // ENFORCE: IDOR Guard
        if (!$this->canAccessMemberRecord($loan['member_id'])) {
            $this->jsonError('You do not have permission to disburse this loan.', null, 403);
        }

        $this->db->beginTransaction();
        try {
            // DRY: Route through centralized approval engine
            $this->approval->request('disbursement', $id, $loan['loan_no'], (float)$loan['principal_amount'], $_SESSION['user_id'], "Disbursement request for loan {$loan['loan_no']}");
            
            // ENFORCE: Notify approvers
            $this->notif->notifyApprovers('disbursement', $loan['loan_no'], (float)$loan['principal_amount'], "Disbursement for {$loan['loan_no']}");

            // ENFORCE: Notify applicant
            $member = $this->memberModel->find($loan['member_id']);
            if (!empty($member['user_id'])) {
                $this->notif->dispatch((int)$member['user_id'], 'disbursement_requested', 'Disbursement Requested', 
                    "Dear {$member['first_name']}, your loan {$loan['loan_no']} has been queued for disbursement and is pending final approval.");
            }

            $this->logAudit('disbursement_requested', 'loans', $id, 'Loan', "Disbursement requested for loan {$loan['loan_no']}");
            
            $this->db->commit();
            $this->jsonSuccess(null, 'Disbursement request submitted for approval.');
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('disbursement_request_failed', 'Disbursement Request Failed', "Failed to request disbursement for loan {$loan['loan_no']}: " . $e->getMessage());
            $this->jsonError('Failed to request disbursement: ' . $e->getMessage(), null, 500);
        }
    }

    public function recordRepayment(): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'savings.deposit', 'group' => 'groups.view_members', 'personal' => 'loans.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to record repayments.');
        $this->verifyCsrf();
        
        $data = $this->getPost();
        $missing = $this->validateRequired($data, ['loan_id','amount','payment_method','transaction_date']);
        if ($missing) $this->jsonError('Please fill all required fields.', array_fill_keys($missing, 'Required.'), 422);
        
        $loanId = (int)$data['loan_id'];
        $amount = (float)$data['amount'];
        $loan = $this->model->find($loanId);
        
        if (!$loan) $this->jsonError('Loan not found.', null, 404);
        if (!in_array($loan['status'], ['active','disbursed'], true)) $this->jsonError('This loan is not active.', null, 409);
        if ($amount <= 0) $this->jsonError('Repayment amount must be greater than zero.', null, 422);
        if ($amount > $loan['balance_outstanding']) $this->jsonError('Amount exceeds outstanding balance.', null, 422);
        
        // ENFORCE: IDOR Guard
        if (!$this->canAccessMemberRecord($loan['member_id'])) {
            $this->jsonError('You do not have permission to record repayment for this loan.', null, 403);
        }
        
        $this->db->beginTransaction();
        try {
            $newBalance = max(0, (float)$loan['balance_outstanding'] - $amount);
            $newStatus = $newBalance <= 0 ? 'completed' : 'active';
            $oldData = $this->model->getOldValues($loanId);
            
            $newData = [
                'amount_paid'        => (float)$loan['amount_paid'] + $amount,
                'balance_outstanding'=> $newBalance,
                'status'             => $newStatus,
                'actual_maturity_date' => $newBalance <= 0 ? $data['transaction_date'] : null,
            ];
            $this->model->update($loanId, $newData);
            
            // COMPLIANCE: STRICT ALLOCATION PRIORITY (Audit Sec 4.3)
            $remaining = $amount;
            $installments = $this->db->fetchAll(
                "SELECT * FROM loan_repayment_schedules WHERE loan_id = ? AND status IN ('upcoming','due','overdue','partially_paid') ORDER BY installment_no ASC", 
                [$loanId]
            );

            foreach ($installments as $inst) {
                if ($remaining <= 0) break;

                $principalDue = max(0, (float)$inst['principal_due'] - (float)$inst['principal_paid']);
                $interestDue  = max(0, (float)$inst['interest_due'] - (float)$inst['interest_paid']);
                $penaltyDue   = max(0, (float)$inst['penalty_due'] - (float)$inst['penalty_paid']);

                $allocPrincipal = 0;
                $allocInterest  = 0;
                $allocPenalty   = 0;

                // 1. Allocate to Principal first
                if ($remaining > 0 && $principalDue > 0) {
                    $allocPrincipal = min($remaining, $principalDue);
                    $remaining -= $allocPrincipal;
                }

                // 2. Allocate to Interest second
                if ($remaining > 0 && $interestDue > 0) {
                    $allocInterest = min($remaining, $interestDue);
                    $remaining -= $allocInterest;
                }

                // 3. Allocate to Penalties third
                if ($remaining > 0 && $penaltyDue > 0) {
                    $allocPenalty = min($remaining, $penaltyDue);
                    $remaining -= $allocPenalty;
                }

                $totalAllocated = $allocPrincipal + $allocInterest + $allocPenalty;
                
                if ($totalAllocated > 0) {
                    $newTotalPaid = (float)$inst['total_paid'] + $totalAllocated;
                    $totalOwed = (float)$inst['total_due'] + (float)$inst['penalty_due'];
                    $isFullyPaid = ($newTotalPaid >= $totalOwed);
                    
                    $this->db->execute("
                        UPDATE loan_repayment_schedules 
                        SET principal_paid = principal_paid + ?, 
                            interest_paid = interest_paid + ?, 
                            penalty_paid = penalty_paid + ?, 
                            total_paid = total_paid + ?,
                            status = ?, 
                            paid_date = ? 
                        WHERE id = ?
                    ", [
                        $allocPrincipal, 
                        $allocInterest, 
                        $allocPenalty, 
                        $totalAllocated,
                        $isFullyPaid ? 'paid' : 'partially_paid', 
                        $data['transaction_date'], 
                        $inst['id']
                    ]);
                }
            }
            
            // Create immutable loan repayment transaction
            $this->db->execute("INSERT INTO transactions (txn_ref, txn_type, amount, member_id, loan_id, payment_method, external_ref, description, transaction_date, status, balance_before, balance_after, created_by) VALUES (?, 'loan_repayment', ?, ?, ?, ?, ?, ?, ?, 'completed', ?, ?, ?)", [
                'TXN-' . strtoupper(uniqid()), $amount, $loan['member_id'], $loanId, $data['payment_method'], $data['external_ref'] ?? null, "Loan repayment for {$loan['loan_no']}", $data['transaction_date'], $loan['balance_outstanding'], $newBalance, $_SESSION['user_id']
            ]);
            
            $this->logAudit('loan_repayment', 'loans', $loanId, 'Loan', "Repayment of " . Format::currency($amount) . " on loan {$loan['loan_no']}", json_encode($oldData), json_encode($newData));
            
            // ENFORCE: Notify applicant
            $member = $this->memberModel->find($loan['member_id']);
            if (!empty($member['user_id'])) {
                $this->notif->dispatch((int)$member['user_id'], 'loan_repayment_recorded', 'Loan Repayment Recorded', 
                    "Dear {$member['first_name']}, a repayment of " . Format::currency($amount) . " has been recorded for your loan {$loan['loan_no']}. New balance: " . Format::currency($newBalance));
            }

            // COMPLIANCE: Release guarantors upon full loan settlement (Audit Sec 14)
            if ($newBalance <= 0) {
                $this->releaseGuarantors($loanId);
            }

            $this->db->commit();
            
            $msg = $newBalance <= 0 ? "Loan {$loan['loan_no']} fully repaid and completed!" : "Repayment of " . Format::currency($amount) . " recorded. Balance: " . Format::currency($newBalance);
            $this->jsonSuccess(['new_balance' => $newBalance, 'status' => $newStatus], $msg);
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('loan_repayment_failed', 'Loan Repayment Failed', "Failed to record repayment for loan {$loan['loan_no']}: " . $e->getMessage());
            $this->jsonError('Failed to record repayment. Please try again.', null, 500);
        }
    }

    public function calculate(): void
    {
        $this->auth->requireAuth();
        $principal = (float)$this->getQuery('principal', 0);
        $rate      = (float)$this->getQuery('rate', 0);
        $term      = (int)$this->getQuery('term', 0);
        $type      = $this->getQuery('type', 'reducing_balance');
        if (!$principal || !$rate || !$term) $this->jsonError('Invalid parameters.', null, 422);
        
        $this->jsonSuccess($this->model->calculateLoan($principal, $rate, $term, $type));
    }

    public function schedule(int $id): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'loans.view', 'group' => 'groups.view_members', 'personal' => 'loans.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to view loan schedules.');
        
        $loan = $this->model->find($id);
        if (!$loan) $this->jsonError('Loan not found.', null, 404);

        // ENFORCE: IDOR Guard
        if (!$this->canAccessMemberRecord($loan['member_id'])) {
            $this->jsonError('You do not have permission to view this loan schedule.', null, 403);
        }

        $this->jsonSuccess($this->model->getSchedule($id));
    }

    public function delete(int $id): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'loans.delete', 'group' => 'groups.view_members', 'personal' => 'loans.delete_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to delete loans.');
        $this->verifyCsrf();
        
        $loan = $this->model->find($id);
        if (!$loan) $this->jsonError('Loan not found.', null, 404);
        if (in_array($loan['status'], ['active','disbursed'], true)) $this->jsonError('Cannot delete an active loan.', null, 409);

        // ENFORCE: IDOR Guard
        if (!$this->canAccessMemberRecord($loan['member_id'])) {
            $this->jsonError('You do not have permission to delete this loan.', null, 403);
        }

        $this->db->beginTransaction();
        try {
            $oldData = $this->model->getOldValues($id);
            $this->model->delete($id, $_SESSION['user_id']);
            
            // ENFORCE: Notify applicant
            $member = $this->memberModel->find($loan['member_id']);
            if (!empty($member['user_id'])) {
                $this->notif->dispatch((int)$member['user_id'], 'loan_deleted', 'Loan Application Deleted', 
                    "Dear {$member['first_name']}, your loan application {$loan['loan_no']} has been deleted by an administrator.");
            }

            $this->logAudit('loan_deleted', 'loans', $id, 'Loan', "Loan {$loan['loan_no']} moved to trash", json_encode($oldData), null);
            
            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/loans'], 'Loan moved to trash.');
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('loan_delete_failed', 'Loan Deletion Failed', "Failed to delete loan {$loan['loan_no']}: " . $e->getMessage());
            $this->jsonError('Failed to delete loan: ' . $e->getMessage(), null, 500);
        }
    }

    public function edit(int $id): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'loans.edit', 'group' => 'groups.view_members', 'personal' => 'loans.edit_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to edit loans.');
        
        $loan = $this->model->find($id);
        if (!$loan || !in_array($loan['status'], ['draft','pending'])) {
            $this->flash('error', 'Only draft or pending loans can be edited.');
            $this->redirect('/loans/' . $id);
        }

        // ENFORCE: IDOR Guard
        if (!$this->canAccessMemberRecord($loan['member_id'])) {
            $this->flash('error', 'You do not have permission to edit this loan.');
            $this->redirect('/dashboard');
        }
    
        $loanProducts = $this->db->fetchAll("SELECT * FROM loan_products WHERE status='active'");
        $member       = $this->memberModel->find($loan['member_id']);
        
        $this->view('loans/edit', array_merge(
            $this->prepareViewData('Edit Loan ' . $loan['loan_no'], 'loans', ['Loans' => APP_URL.'/loans', $loan['loan_no'] => APP_URL.'/loans/'.$id, 'Edit' => null]),
            ['loan' => $loan, 'loanProducts' => $loanProducts, 'member' => $member]
        ));
    }

    public function update(int $id): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'loans.edit', 'group' => 'groups.view_members', 'personal' => 'loans.edit_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to edit loans.');
        $this->verifyCsrf();
        
        $loan = $this->model->find($id);
        if (!$loan || !in_array($loan['status'], ['draft','pending'])) $this->jsonError('Loan cannot be edited in its current state.', null, 400);

        // ENFORCE: IDOR Guard
        if (!$this->canAccessMemberRecord($loan['member_id'])) {
            $this->jsonError('You do not have permission to edit this loan.', null, 403);
        }

        $data = $this->getPost();
        $missing = $this->validateRequired($data, ['principal_amount','term_months','first_repayment_date']);
        if ($missing) $this->jsonError('Required fields missing.', array_fill_keys($missing,'Required'), 422);

        $principal = (float)$data['principal_amount'];
        $term      = (int)$data['term_months'];
        $product   = $this->db->fetchOne("SELECT * FROM loan_products WHERE id=?", [$loan['loan_product_id']]);
        $calc      = $this->model->calculateLoan($principal, $product['interest_rate'], $term, $product['interest_type']);
        $processingFee = $principal * $product['processing_fee_pct'] / 100;

        $this->db->beginTransaction();
        try {
            $oldData = $this->model->getOldValues($id);
            $newData = [
                'principal_amount'    => $principal,
                'term_months'         => $term,
                'processing_fee'      => $processingFee,
                'total_interest'      => $calc['total_interest'],
                'total_payable'       => $calc['total_payable'],
                'monthly_installment' => $calc['monthly_installment'],
                'balance_outstanding' => $calc['total_payable'],
                'first_repayment_date'=> $data['first_repayment_date'],
                'purpose'             => $data['purpose'] ?? $loan['purpose'],
                'disbursement_method' => $data['disbursement_method'] ?? $loan['disbursement_method'],
                'disbursement_account'=> $data['disbursement_account'] ?? null,
            ];
            
            $this->model->update($id, $newData);
            $this->model->generateRepaymentSchedule($id, $principal, $product['interest_rate'], $term, $product['interest_type'], $data['first_repayment_date']);
            
            // ENFORCE: Notify applicant
            $member = $this->memberModel->find($loan['member_id']);
            if (!empty($member['user_id'])) {
                $this->notif->dispatch((int)$member['user_id'], 'loan_updated', 'Loan Application Updated', 
                    "Dear {$member['first_name']}, your loan application {$loan['loan_no']} has been updated by an administrator.");
            }

            $this->logAudit('loan_updated', 'loans', $id, 'Loan', "Loan {$loan['loan_no']} updated", json_encode($oldData), json_encode($newData));
            
            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL.'/loans/'.$id], 'Loan application updated.');
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('loan_update_failed', 'Loan Update Failed', "Failed to update loan {$loan['loan_no']}: " . $e->getMessage());
            $this->jsonError('Update failed: ' . $e->getMessage(), null, 500);
        }
    }

    // ── HELPERS ────────────────────────────────────────────────────

    /**
     * COMPLIANCE: Release guarantors upon full loan settlement (Audit Sec 14)
     */
    private function releaseGuarantors(int $loanId): void
    {
        $guarantors = $this->db->fetchAll("SELECT * FROM loan_guarantors WHERE loan_id = ? AND status = 'confirmed'", [$loanId]);
        
        foreach ($guarantors as $guarantor) {
            $gMemberId = (int)$guarantor['guarantor_member_id'];
            
            $activeGuaranteedLoans = (int)$this->db->fetchColumn("
                SELECT COUNT(*) FROM loan_guarantors lg
                JOIN loans l ON l.id = lg.loan_id
                WHERE lg.guarantor_member_id = ? AND l.status IN ('active', 'disbursed', 'pending') AND lg.status = 'confirmed'
            ", [$gMemberId]);

            if ($activeGuaranteedLoans === 0) {
                $this->db->execute("UPDATE loan_guarantors SET status = 'released' WHERE id = ?", [$guarantor['id']]);
                
                $gMember = $this->db->fetchOne("SELECT first_name, last_name, user_id FROM members WHERE id = ?", [$gMemberId]);
                if ($gMember) {
                    $this->logAudit('guarantor_restriction_released', 'loans', $loanId, 'LoanGuarantor', 
                        "Guarantor restrictions released for {$gMember['first_name']} {$gMember['last_name']} as loan {$loanId} settled and no other active guarantees exist."
                    );
                    
                    // ENFORCE: Notify guarantor via multi-channel dispatch
                    if (!empty($gMember['user_id'])) {
                        $this->notif->dispatch(
                            (int)$gMember['user_id'],
                            'guarantor_released',
                            'Guarantor Obligation Released',
                            "Dear {$gMember['first_name']}, your guarantee obligation for a settled loan has been fully released. Your account restrictions have been lifted."
                        );
                    }
                }
            }
        }
    }

    /**
     * ENFORCE: Notify all admins via multi-channel dispatch
     */
    private function notifyAdmins(string $type, string $title, string $message): void
    {
        $admins = $this->db->fetchAll("
            SELECT DISTINCT u.id 
            FROM users u 
            JOIN role_permissions rp ON rp.role_id = u.role_id 
            JOIN permissions p ON p.id = rp.permission_id 
            WHERE p.slug IN ('loans.view', 'loans.create', 'loans.edit', 'loans.approve', 'loans.disburse', 'loans.delete') AND u.status = 'active'
        ");
        foreach ($admins as $admin) {
            $this->notif->dispatch((int)$admin['id'], $type, $title, $message);
        }
    }
}