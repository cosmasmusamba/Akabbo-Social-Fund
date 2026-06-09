<?php
namespace App\Controllers;

use App\Models\Loan;
use App\Models\Member;
use App\Helpers\Format;
use Database;

/**
 * AKABBO SOCIAL FUND
 * Loan Controller
 *
 * Handles the full loan lifecycle: application, approval/rejection,
 * disbursement, repayment recording, and schedule management.
 */
class LoanController extends BaseController
{
    private Loan     $model;
    private Member   $memberModel;
    private Database $db;

    public function __construct()
    {
        parent::__construct();
        $this->auth->requireAuth();
        $this->model       = new Loan();
        $this->memberModel = new Member();
        $this->db          = Database::getInstance();
    }

    /** Paginated loan list with filters. */
    public function index(): void
    {
        $this->auth->requirePermission('loans.view');

        ['page' => $page, 'limit' => $limit] = $this->getPaginationParams();

        $filters = [
            'status'     => $this->getQuery('status', ''),
            'member_id'  => $this->getQuery('member', ''),
            'product_id' => $this->getQuery('product', ''),
            'search'     => $this->getQuery('search', ''),
            'date_from'  => $this->getQuery('from', ''),
            'date_to'    => $this->getQuery('to', ''),
        ];

        $result       = $this->model->getList($page, $limit, $filters);
        $loanStats    = $this->model->getStats();
        $loanProducts = $this->db->fetchAll("SELECT id, name FROM loan_products WHERE status='active' ORDER BY name");
        $settings     = $this->getSettings();
        $unreadNotifications = $this->getUnreadCount();

        $pageTitle  = 'Loans';
        $activePage = 'loans';
        $breadcrumbs = ['Loans' => null];

        $this->view('loans/index', compact('result','loanStats','loanProducts','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'));
    }

    /** Show the loan application form. */
    public function create(): void
    {
        $this->auth->requirePermission('loans.create');

        $loanProducts = $this->db->fetchAll("SELECT * FROM loan_products WHERE status='active' ORDER BY name");
        $settings     = $this->getSettings();
        $unreadNotifications = $this->getUnreadCount();

        $pageTitle  = 'New Loan Application';
        $activePage = 'loans';
        $breadcrumbs = ['Loans' => APP_URL.'/loans', 'New Application' => null];

        $this->view('loans/create', compact('loanProducts','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'));
    }

    /** Store a new loan application. */
    public function store(): void
    {
        $this->auth->requirePermission('loans.create');
        $this->verifyCsrf();

        $data    = $this->getPost();
        $missing = $this->validateRequired($data, ['member_id','loan_product_id','principal_amount','term_months','first_repayment_date','disbursement_method']);
        if ($missing) $this->jsonError('Please complete all required fields.', array_fill_keys($missing, 'Required.'), 422);

        $member  = $this->memberModel->find((int)$data['member_id']);
        if (!$member || $member['status'] !== 'active') $this->jsonError('Invalid or inactive member.', null, 404);

        $product = $this->db->fetchOne("SELECT * FROM loan_products WHERE id = ? AND status='active'", [(int)$data['loan_product_id']]);
        if (!$product) $this->jsonError('Invalid loan product.', null, 404);

        $principal = (float)$data['principal_amount'];
        $term      = (int)$data['term_months'];

        // Validate amount range
        if ($principal < $product['min_amount'] || $principal > $product['max_amount']) {
            $this->jsonError("Loan amount must be between {$product['min_amount']} and {$product['max_amount']}.", null, 422);
        }

        // Validate term range
        if ($term < $product['min_term_months'] || $term > $product['max_term_months']) {
            $this->jsonError("Loan term must be between {$product['min_term_months']} and {$product['max_term_months']} months.", null, 422);
        }

        // Calculate loan financials
        $calc = $this->model->calculateLoan($principal, (float)$product['interest_rate'], $term, $product['interest_type']);

        $processingFee = $principal * ((float)$product['processing_fee_pct'] / 100);
        $insuranceFee  = $principal * ((float)$product['insurance_fee_pct'] / 100);

        $firstRepayDate    = $data['first_repayment_date'];
        $expectedMaturity  = date('Y-m-d', strtotime($firstRepayDate . ' +' . ($term - 1) . ' months'));

        $this->db->beginTransaction();
        try {
            $loanNo = $this->model->generateLoanNo();
            $status = ($data['status'] ?? '') === 'draft' ? 'draft' : 'pending';

            $loanId = $this->model->create([
                'loan_no'               => $loanNo,
                'member_id'             => (int)$data['member_id'],
                'loan_product_id'       => (int)$data['loan_product_id'],
                'principal_amount'      => $principal,
                'interest_rate'         => (float)$product['interest_rate'],
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
            ]);

            // Add guarantors
            if (!empty($data['guarantor_id']) && is_array($data['guarantor_id'])) {
                foreach ($data['guarantor_id'] as $gId) {
                    if (!$gId) continue;
                    $this->db->execute("
                        INSERT INTO loan_guarantors (loan_id, guarantor_member_id, status)
                        VALUES (?, ?, 'pending')
                    ", [$loanId, (int)$gId]);
                }
            }

            // Notify finance managers
            $this->notifyManagers($loanId, $loanNo, $member, $principal, $status);

            $this->auth->logAudit($_SESSION['user_id'], 'loan_applied', 'loans', $loanId, 'Loan',
                "Loan {$loanNo} applied for member {$member['member_no']}");

            $this->db->commit();

            if ($this->isAjax()) {
                $this->jsonSuccess(['loan_id' => $loanId, 'redirect' => APP_URL . '/loans/' . $loanId],
                    $status === 'draft' ? 'Loan saved as draft.' : 'Loan application submitted successfully!');
            }
            $this->flash('success', "Loan application {$loanNo} submitted for approval.");
            $this->redirect('/loans/' . $loanId);

        } catch (\Exception $e) {
            $this->db->rollback();
            error_log('[LOAN STORE ERROR] ' . $e->getMessage());
            $this->jsonError('Failed to submit loan application. Please try again.', null, 500);
        }
    }

    /** Show loan detail page. */
    public function show(int $id): void
    {
        $this->auth->requirePermission('loans.view');

        $loan = $this->model->getDetail($id);
        if (!$loan) {
            $this->flash('error', 'Loan not found.');
            $this->redirect('/loans');
        }

        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnreadCount();

        $pageTitle  = 'Loan — ' . $loan['loan_no'];
        $activePage = 'loans';
        $breadcrumbs = ['Loans' => APP_URL.'/loans', $loan['loan_no'] => null];

        $this->view('loans/show', compact('loan','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'));
    }

    /** Approve a pending loan. */
    public function approve(int $id): void
    {
        $this->auth->requirePermission('loans.approve');
        $this->verifyCsrf();

        $loan = $this->model->find($id);
        if (!$loan) $this->jsonError('Loan not found.', null, 404);
        if ($loan['status'] !== 'pending') $this->jsonError('Only pending loans can be approved.', null, 409);

        $this->model->update($id, [
            'status'       => 'approved',
            'approved_by'  => $_SESSION['user_id'],
            'approval_date'=> date('Y-m-d'),
        ]);

        $this->notifyMemberLoanStatus($loan, 'approved');
        $this->auth->logAudit($_SESSION['user_id'], 'loan_approved', 'loans', $id, 'Loan', "Loan {$loan['loan_no']} approved");

        $this->jsonSuccess(null, "Loan {$loan['loan_no']} approved successfully.");
    }

    /** Reject a pending loan. */
    public function reject(int $id): void
    {
        $this->auth->requirePermission('loans.approve');
        $this->verifyCsrf();

        $loan = $this->model->find($id);
        if (!$loan) $this->jsonError('Loan not found.', null, 404);
        if (!in_array($loan['status'], ['pending','approved'], true)) $this->jsonError('Cannot reject this loan.', null, 409);

        $reason = $this->getPost()['rejection_reason'] ?? '';
        if (!$reason) $this->jsonError('Please provide a rejection reason.', null, 422);

        $this->model->update($id, [
            'status'           => 'rejected',
            'rejected_by'      => $_SESSION['user_id'],
            'rejection_reason' => $reason,
        ]);

        $this->notifyMemberLoanStatus($loan, 'rejected', $reason);
        $this->auth->logAudit($_SESSION['user_id'], 'loan_rejected', 'loans', $id, 'Loan', "Loan {$loan['loan_no']} rejected: {$reason}");

        $this->jsonSuccess(null, "Loan {$loan['loan_no']} rejected.");
    }

    /** Disburse an approved loan. */
    public function disburse(int $id): void
    {
        $this->auth->requirePermission('loans.disburse');
        $this->verifyCsrf();

        $loan = $this->model->find($id);
        if (!$loan) $this->jsonError('Loan not found.', null, 404);
        if ($loan['status'] !== 'approved') $this->jsonError('Only approved loans can be disbursed.', null, 409);

        $data = $this->getPost();
        $disbDate = $data['disbursement_date'] ?? date('Y-m-d');

        $this->db->beginTransaction();
        try {
            $firstRepay = $loan['first_repayment_date'] ?? date('Y-m-d', strtotime($disbDate . ' +1 month'));

            $this->model->update($id, [
                'status'             => 'active',
                'disbursement_date'  => $disbDate,
                'first_repayment_date' => $firstRepay,
                'disbursed_by'       => $_SESSION['user_id'],
            ]);

            // Generate repayment schedule
            $this->model->generateRepaymentSchedule(
                $id,
                (float)$loan['principal_amount'],
                (float)$loan['interest_rate'],
                (int)$loan['term_months'],
                $loan['interest_type'],
                $firstRepay
            );

            // Record disbursement transaction
            $this->db->execute("
                INSERT INTO transactions
                    (txn_ref, txn_type, amount, member_id, loan_id, payment_method, description,
                     transaction_date, status, created_by)
                VALUES (?, 'loan_disbursement', ?, ?, ?, ?, ?, ?, 'completed', ?)
            ", [
                'TXN-' . strtoupper(uniqid()),
                $loan['principal_amount'],
                $loan['member_id'],
                $id,
                $loan['disbursement_method'] ?? 'cash',
                "Disbursement for loan {$loan['loan_no']}",
                $disbDate,
                $_SESSION['user_id'],
            ]);

            $this->notifyMemberLoanStatus($loan, 'disbursed');
            $this->auth->logAudit($_SESSION['user_id'], 'loan_disbursed', 'loans', $id, 'Loan',
                "Loan {$loan['loan_no']} disbursed on {$disbDate}");

            $this->db->commit();
            $this->jsonSuccess(null, "Loan {$loan['loan_no']} disbursed successfully!");

        } catch (\Exception $e) {
            $this->db->rollback();
            error_log('[LOAN DISBURSE ERROR] ' . $e->getMessage());
            $this->jsonError('Disbursement failed. Please try again.', null, 500);
        }
    }

    /** Record a loan repayment. */
    public function recordRepayment(): void
    {
        $this->auth->requirePermission('savings.deposit');
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

        $this->db->beginTransaction();
        try {
            $newBalance = (float)$loan['balance_outstanding'] - $amount;

            // Update loan balance
            $newStatus = $newBalance <= 0 ? 'completed' : 'active';
            $this->model->update($loanId, [
                'amount_paid'        => (float)$loan['amount_paid'] + $amount,
                'balance_outstanding'=> max(0, $newBalance),
                'status'             => $newStatus,
                'actual_maturity_date' => $newBalance <= 0 ? $data['transaction_date'] : null,
            ]);

            // Allocate repayment to schedule installments (oldest first)
            $remaining = $amount;
            $installments = $this->db->fetchAll("
                SELECT * FROM loan_repayment_schedules
                WHERE loan_id = ? AND status IN ('upcoming','due','overdue','partially_paid')
                ORDER BY installment_no ASC
            ", [$loanId]);

            foreach ($installments as $inst) {
                if ($remaining <= 0) break;
                $due  = ($inst['total_due'] + $inst['penalty_due']) - ($inst['total_paid'] + $inst['penalty_paid']);
                $pay  = min($remaining, $due);
                $remaining -= $pay;

                $isPaid = ($inst['total_paid'] + $pay) >= $inst['total_due'];
                $this->db->execute("
                    UPDATE loan_repayment_schedules
                    SET total_paid = total_paid + ?, principal_paid = principal_paid + ?,
                        interest_paid = interest_paid + ?,
                        status = ?, paid_date = ?
                    WHERE id = ?
                ", [
                    $pay,
                    min($pay, $inst['principal_due'] - $inst['principal_paid']),
                    max(0, $pay - max(0, $inst['principal_due'] - $inst['principal_paid'])),
                    $isPaid ? 'paid' : 'partially_paid',
                    $data['transaction_date'],
                    $inst['id'],
                ]);
            }

            // Save transaction record
            $this->db->execute("
                INSERT INTO transactions
                    (txn_ref, txn_type, amount, member_id, loan_id, payment_method, external_ref,
                     description, transaction_date, status, balance_before, balance_after, created_by)
                VALUES (?, 'loan_repayment', ?, ?, ?, ?, ?, ?, ?, 'completed', ?, ?, ?)
            ", [
                'TXN-' . strtoupper(uniqid()),
                $amount, $loan['member_id'], $loanId,
                $data['payment_method'], $data['external_ref'] ?? null,
                "Loan repayment for {$loan['loan_no']}",
                $data['transaction_date'],
                $loan['balance_outstanding'], $newBalance,
                $_SESSION['user_id'],
            ]);

            $this->auth->logAudit($_SESSION['user_id'], 'loan_repayment', 'loans', $loanId, 'Loan',
                "Repayment of " . Format::currency($amount) . " on loan {$loan['loan_no']}");

            $this->db->commit();

            $msg = $newBalance <= 0
                ? "Loan {$loan['loan_no']} fully repaid and completed!"
                : "Repayment of " . Format::currency($amount) . " recorded. Balance: " . Format::currency($newBalance);

            $this->jsonSuccess(['new_balance' => $newBalance, 'status' => $newStatus], $msg);

        } catch (\Exception $e) {
            $this->db->rollback();
            error_log('[REPAYMENT ERROR] ' . $e->getMessage());
            $this->jsonError('Failed to record repayment. Please try again.', null, 500);
        }
    }

    /** AJAX: Calculate loan repayment details. */
    public function calculate(): void
    {
        $this->auth->requireAuth();

        $principal = (float)$this->getQuery('principal', 0);
        $rate      = (float)$this->getQuery('rate', 0);
        $term      = (int)$this->getQuery('term', 0);
        $type      = $this->getQuery('type', 'reducing_balance');

        if (!$principal || !$rate || !$term) $this->jsonError('Invalid parameters.', null, 422);

        $calc = $this->model->calculateLoan($principal, $rate, $term, $type);
        $this->jsonSuccess($calc);
    }

    /** Get repayment schedule for a loan. */
    public function schedule(int $id): void
    {
        $this->auth->requirePermission('loans.view');
        $schedule = $this->model->getSchedule($id);
        $this->jsonSuccess($schedule);
    }

    /** Soft-delete a loan. */
    public function delete(int $id): void
    {
        $this->auth->requirePermission('loans.delete');
        $this->verifyCsrf();

        $loan = $this->model->find($id);
        if (!$loan) $this->jsonError('Loan not found.', null, 404);
        if (in_array($loan['status'], ['active','disbursed'], true)) $this->jsonError('Cannot delete an active loan.', null, 409);

        $this->model->delete($id);
        $this->auth->logAudit($_SESSION['user_id'], 'loan_deleted', 'loans', $id, 'Loan', "Loan {$loan['loan_no']} deleted");

        if ($this->isAjax()) $this->jsonSuccess(null, 'Loan deleted.');
        $this->flash('success', 'Loan deleted.');
        $this->redirect('/loans');
    }

    // ── Private helpers ──────────────────────────────────────────

    private function notifyManagers(int $loanId, string $loanNo, array $member, float $amount, string $status): void
    {
        if ($status === 'draft') return;
        $memberName = $member['first_name'] . ' ' . $member['last_name'];
        $managers   = $this->db->fetchAll("SELECT id FROM users WHERE role_id IN (1,2,3,4) AND status='active'");
        foreach ($managers as $mgr) {
            $this->db->execute("
                INSERT INTO notifications (user_id, type, title, message, channel)
                VALUES (?, 'loan_application', ?, ?, 'system')
            ", [
                $mgr['id'],
                'New Loan Application',
                "Loan {$loanNo} submitted by {$memberName} for " . Format::currency($amount) . " — awaiting approval.",
            ]);
        }
    }

    private function notifyMemberLoanStatus(array $loan, string $status, string $reason = ''): void
    {
        $messages = [
            'approved'  => "Your loan application {$loan['loan_no']} has been approved.",
            'rejected'  => "Your loan application {$loan['loan_no']} was rejected. Reason: {$reason}",
            'disbursed' => "Your loan {$loan['loan_no']} of " . Format::currency($loan['principal_amount']) . " has been disbursed.",
        ];
        $msg = $messages[$status] ?? '';
        if (!$msg) return;

        // In a full system, this would also send SMS/email based on settings
        $userId = $this->db->fetchColumn("SELECT user_id FROM members WHERE id = ? AND user_id IS NOT NULL", [$loan['member_id']]);
        if ($userId) {
            $this->db->execute("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'loan_status', 'Loan Update', ?)", [$userId, $msg]);
        }
    }

    private function getSettings(): array
    {
        $rows = $this->db->fetchAll("SELECT `key`, `value` FROM settings");
        return array_column($rows, 'value', 'key');
    }

    /** Show loan edit form (only for draft/pending loans) */
    public function edit(int $id): void
    {
        $this->auth->requirePermission('loans.edit');
        $loan = $this->model->find($id);
        if (!$loan) { $this->flash('error','Loan not found.'); $this->redirect('/loans'); }
        if (!in_array($loan['status'], ['draft','pending'])) {
            $this->flash('error','Only draft or pending loans can be edited.');
            $this->redirect('/loans/' . $id);
        }
        $loanProducts = $this->db->fetchAll("SELECT * FROM loan_products WHERE status='active'");
        $member       = $this->memberModel->find($loan['member_id']);
        $settings     = $this->getSettings();
        $unreadNotifications = $this->getUnreadCount();
        $pageTitle   = 'Edit Loan ' . $loan['loan_no'];
        $activePage  = 'loans';
        $breadcrumbs = ['Loans' => APP_URL.'/loans', $loan['loan_no'] => APP_URL.'/loans/'.$id, 'Edit' => null];
        $this->view('loans/edit', compact('loan','loanProducts','member','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'));
    }

    /** Process loan update (draft/pending only) */
    public function update(int $id): void
    {
        $this->auth->requirePermission('loans.edit');
        $this->verifyCsrf();
        $loan = $this->model->find($id);
        if (!$loan || !in_array($loan['status'], ['draft','pending'])) {
            $this->jsonError('Loan cannot be edited in its current state.', null, 400);
        }

        $data    = $this->getPost();
        $missing = $this->validateRequired($data, ['principal_amount','term_months','first_repayment_date']);
        if ($missing) { $this->jsonError('Required fields missing.', array_fill_keys($missing,'Required'), 422); }

        $principal = (float)$data['principal_amount'];
        $term      = (int)$data['term_months'];
        $product   = $this->db->fetchOne("SELECT * FROM loan_products WHERE id=?", [$loan['loan_product_id']]);

        $calc          = $this->model->calculateLoan($principal, $product['interest_rate'], $term, $product['interest_type']);
        $processingFee = $principal * $product['processing_fee_pct'] / 100;

        $this->db->beginTransaction();
        try {
            $this->model->update($id, [
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
            ]);

            // Regenerate schedule
            $this->model->generateRepaymentSchedule($id, $principal, $product['interest_rate'], $term, $product['interest_type'], $data['first_repayment_date']);

            $this->auth->logAudit($_SESSION['user_id'], 'loan_updated', 'loans', $id, 'Loan', "Loan {$loan['loan_no']} updated");
            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL.'/loans/'.$id], 'Loan application updated.');
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->jsonError('Update failed: ' . $e->getMessage(), null, 500);
        }
    }

    private function getUnreadCount(): int
    {
        return (int)$this->db->fetchColumn("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0", [$_SESSION['user_id']]);
    }
}
