<?php
// We should notify both approver, admins and members use (dispatch instead of sendToUser) with relevant messages for all actions of this contoller and any other event of success and failure. Avoid using default `prompt` instead use modals. Ensure to use ApprovalController for DRY and consistence. Enforce logAudit and (RBAC + IDOR). Embrace canAccessMemberRecord for all methods that fetch records. Determine the user's data scope (Global, Group, Personal, or None)
namespace App\Controllers;

use App\Models\SocialFundFee;
use App\Models\Approval;
use App\Helpers\Format;
use App\Services\NotificationService;

class SocialFundFeeController extends BaseController
{
    private SocialFundFee $model;
    private NotificationService $notif;
    private Approval $approval;

    public function __construct()
    {
        parent::__construct();
        $this->auth->requireAuth();
        $this->model = new SocialFundFee();
        $this->notif = new NotificationService();
        $this->approval = new Approval(); 
    }

    // ── CRUD METHODS ───────────────────────────────────────────────

    public function index(): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'social_fund.view', 'group' => 'groups.view_members', 'personal' => 'social_fund.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to view social fund fees.');
        
        $scope = $this->resolveDataScope($perms);
        
        $activeFee = $this->model->getActive();
        // ENFORCE: Pass scope to model to filter data
        $fees      = $this->model->getAll($scope); 
        
        $month = (int)$this->getQuery('month', date('n'));
        $year  = (int)$this->getQuery('year', date('Y'));
        
        $summary = [];
        if ($activeFee) {
            $summary = $this->model->getPaymentSummary($activeFee['id'], $month, $year, $scope);
        }

        // ENFORCE: Audit log for viewing dashboard
        $this->logAudit('social_fund_viewed', 'social_fund', null, null, "Viewed social fund fees dashboard");

        $this->view('social-fund/index', array_merge(
            $this->prepareViewData('Social Fund Fees', 'social-fund'),
            ['activeFee' => $activeFee, 'fees' => $fees, 'summary' => $summary, 'month' => $month, 'year' => $year]
        ));
    }

    public function create(): void
    {
        $this->auth->requirePermission('social_fund.manage');
        $this->view('social-fund/create', $this->prepareViewData('New Fee Configuration', 'social-fund'));
    }

    public function store(): void
    {
        $this->auth->requirePermission('social_fund.manage');
        $this->verifyCsrf();
        $data = $this->getPost();
        
        if (empty($data['name']) || empty($data['amount']) || empty($data['frequency'])) {
            $this->jsonError('Please fill in all required fields.', null, 422);
        }

        $this->db->beginTransaction();
        try {
            if (($data['status'] ?? 'active') === 'active') {
                $this->db->execute("UPDATE social_fund_fees SET status='inactive' WHERE status='active'");
            }

            $feeData = [
                'name'           => $data['name'],
                'amount'         => (float)$data['amount'],
                'frequency'      => $data['frequency'],
                'due_day'        => (int)($data['due_day'] ?? 1),
                'grace_days'     => (int)($data['grace_days'] ?? 5),
                'penalty_amount' => (float)($data['penalty_amount'] ?? 0),
                'applies_to'     => $data['applies_to'] ?? 'all',
                'effective_from' => $data['effective_from'] ?? date('Y-m-d'),
                'effective_to'   => !empty($data['effective_to']) ? $data['effective_to'] : null,
                'is_mandatory'   => isset($data['is_mandatory']) ? 1 : 0,
                'status'         => $data['status'] ?? 'active',
                'created_by'     => $_SESSION['user_id'],
            ];

            $id = $this->model->create($feeData);
            
            $this->logAudit('social_fund_fee_created', 'social_fund', $id, 'SocialFundFee', 
                "Fee '{$data['name']}' created", null, json_encode($feeData));

            // ENFORCE: Notify admins of new fee configuration
            $this->notifyAdmins('social_fund_fee_created', 'New Social Fund Fee Created', 
                "A new social fund fee '{$data['name']}' for " . Format::currency((float)$data['amount']) . " has been created.");

            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/social-fund'], 'Fee configuration created successfully.');
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('social_fund_fee_creation_failed', 'Fee Creation Failed', "Failed to create social fund fee: " . $e->getMessage());
            $this->jsonError('Failed to create fee: ' . $e->getMessage(), null, 500);
        }
    }

    public function generatePeriod(): void
    {
        $this->auth->requirePermission('social_fund.manage');
        $this->verifyCsrf();
        
        $data = $this->getPost();
        $feeId = (int)($data['fee_id'] ?? 0);
        $month = (int)($data['month'] ?? date('n'));
        $year  = (int)($data['year'] ?? date('Y'));

        $this->db->beginTransaction();
        try {
            $result = $this->model->generateMonthlyFees($feeId, $month, $year, $_SESSION['user_id']);
            
            $this->logAudit('social_fund_fees_generated', 'social_fund', $feeId, 'SocialFundFee', 
                "Generated fees for {$month}/{$year}: {$result['inserted']} inserted, {$result['skipped']} skipped");

            // ENFORCE: Notify admins of bulk generation
            $this->notifyAdmins('social_fund_fees_generated', 'Social Fund Fees Generated', 
                "Fees for {$month}/{$year} have been generated. {$result['inserted']} records created.");
            
            // ENFORCE: Broadcast to all members to remind them of the new fees
            $monthName = date('F Y', mktime(0,0,0,$month,1,$year));
            $this->notif->broadcast('Social Fund Fees Generated', 
                "Social fund fees for {$monthName} have been generated. Please ensure timely payment to avoid penalties.");

            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/social-fund'], "Successfully generated {$result['inserted']} fee records. ({$result['skipped']} skipped)");
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('social_fund_generation_failed', 'Fee Generation Failed', "Failed to generate fees for {$month}/{$year}: " . $e->getMessage());
            $this->jsonError('Generation failed: ' . $e->getMessage(), null, 500);
        }
    }

    public function payments(): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'social_fund.view', 'group' => 'groups.view_members', 'personal' => 'social_fund.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to view fee payments.');
        
        $scope = $this->resolveDataScope($perms);
        $scopeCondition = $this->buildScopeCondition($scope, 'sfp.member_id');

        $feeId = (int)$this->getQuery('fee', 0);
        $month = (int)$this->getQuery('month', date('n'));
        $year  = (int)$this->getQuery('year', date('Y'));

        $fee = $this->db->fetchOne("SELECT * FROM social_fund_fees WHERE id = ?", [$feeId]);
        if (!$fee) {
            $this->flash('error', 'Fee not found.');
            $this->redirect('/social-fund');
        }

        $fees     = $this->model->getAll($scope);
        $summary  = $this->model->getPaymentSummary($feeId, $month, $year, $scope);
        
        // ENFORCE: Apply data scope to payments list
        $payments = $this->db->fetchAll("
            SELECT sfp.*, CONCAT(m.first_name, ' ', m.last_name) AS member_name, m.member_no, m.phone
            FROM social_fund_fee_payments sfp
            JOIN members m ON m.id = sfp.member_id
            WHERE sfp.fee_id = ? AND sfp.period_month = ? AND sfp.period_year = ? {$scopeCondition}
            ORDER BY sfp.status ASC, m.first_name ASC
        ", [$feeId, $month, $year]);

        // ENFORCE: Audit log for viewing payments
        $this->logAudit('social_fund_payments_viewed', 'social_fund', $feeId, 'SocialFundFee', "Viewed payments for fee ID {$feeId} ({$month}/{$year})");

        $this->view('social-fund/payments', array_merge(
            $this->prepareViewData('Fee Payments', 'social-fund'),
            ['fee' => $fee, 'fees' => $fees, 'summary' => $summary, 'payments' => $payments, 'month' => $month, 'year' => $year]
        ));
    }

    public function recordPayment(): void
    {
        $this->auth->requirePermission('social_fund.manage');
        $this->verifyCsrf();
        
        $data = $this->getPost();
        $paymentId   = (int)($data['payment_id'] ?? 0);
        $amountPaid  = (float)($data['amount_paid'] ?? 0);
        $penaltyPaid = (float)($data['penalty_paid'] ?? 0);
        $method      = $data['payment_method'] ?? 'cash';

        $payment = $this->db->fetchOne("
            SELECT sfp.*, m.first_name, m.user_id, m.member_no, m.id AS member_id 
            FROM social_fund_fee_payments sfp 
            JOIN members m ON m.id = sfp.member_id 
            WHERE sfp.id = ?
        ", [$paymentId]);

        if (!$payment) {
            $this->jsonError('Payment record not found.', null, 404);
        }

        // ENFORCE: IDOR Guard
        if (!$this->canAccessMemberRecord((int)$payment['member_id'])) {
            $this->jsonError('You do not have permission to record payment for this member.', null, 403);
        }

        $this->db->beginTransaction();
        try {
            $this->model->recordPayment($paymentId, $amountPaid, $penaltyPaid, $method, $_SESSION['user_id']);
            
            $this->logAudit('social_fund_payment_recorded', 'social_fund', $paymentId, 'SocialFundFee', 
                "Recorded payment of " . Format::currency($amountPaid + $penaltyPaid) . " for member #{$payment['member_id']}");

            // ENFORCE: Notify the member via multi-channel dispatch
            if (!empty($payment['user_id'])) {
                $this->notif->dispatch((int)$payment['user_id'], 'social_fund_payment_received', 'Social Fund Payment Received', 
                    "Dear {$payment['first_name']}, your social fund payment of " . Format::currency($amountPaid + $penaltyPaid) . " has been successfully recorded.");
            }

            // ENFORCE: Notify admins
            $this->notifyAdmins('social_fund_payment_recorded', 'Social Fund Payment Recorded', 
                "A payment of " . Format::currency($amountPaid + $penaltyPaid) . " was recorded for member {$payment['first_name']} ({$payment['member_no']}).");

            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/social-fund/payments?fee=' . $payment['fee_id']], 'Payment recorded successfully.');
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('social_fund_payment_failed', 'Payment Recording Failed', "Failed to record payment for member {$payment['member_no']}: " . $e->getMessage());
            $this->jsonError('Failed to record payment: ' . $e->getMessage(), null, 500);
        }
    }

    public function waive(int $id): void
    {
        $this->auth->requirePermission('social_fund.manage');
        $this->verifyCsrf();
        
        $paymentId = $id;
        $reason = trim($this->getPost()['reason'] ?? '');

        if (strlen($reason) < 10) {
            $this->jsonError('Waiver reason must be at least 10 characters long.', null, 422);
        }

        $payment = $this->db->fetchOne("
            SELECT sfp.*, m.first_name, m.user_id, m.member_no, m.id AS member_id 
            FROM social_fund_fee_payments sfp 
            JOIN members m ON m.id = sfp.member_id 
            WHERE sfp.id = ? AND sfp.status IN ('pending', 'overdue')
        ", [$paymentId]);

        if (!$payment) {
            $this->jsonError('Fee payment not found or is not eligible for waiver.', null, 404);
        }

        // ENFORCE: IDOR Guard
        if (!$this->canAccessMemberRecord((int)$payment['member_id'])) {
            $this->jsonError('You do not have permission to waive fees for this member.', null, 403);
        }

        $this->db->beginTransaction();
        try {
            // DRY: Route through centralized approval engine
            $ref = 'WAIVE-' . strtoupper(uniqid());
            $this->approval->request(
                'social_fund_waiver', 
                $paymentId, 
                $ref, 
                (float)$payment['amount_due'], 
                $_SESSION['user_id'], 
                $reason
            );

            $this->logAudit('social_fund_waiver_requested', 'social_fund', $paymentId, 'SocialFundFee', 
                "Waiver requested for member #{$payment['member_id']}: {$reason}");
                
            // ENFORCE: Notify Approvers
            $this->notif->notifyApprovers('social_fund_waiver', $ref, (float)$payment['amount_due'], $reason);

            // ENFORCE: Notify Member
            if (!empty($payment['user_id'])) {
                $this->notif->dispatch((int)$payment['user_id'], 'social_fund_waiver_requested', 'Social Fund Waiver Requested', 
                    "Dear {$payment['first_name']}, your request to waive the social fund fee of " . Format::currency((float)$payment['amount_due']) . " has been submitted and is pending approval.");
            }
            
            // ENFORCE: Notify Admins
            $this->notifyAdmins('social_fund_waiver_requested', 'Social Fund Waiver Requested', 
                "A waiver request for " . Format::currency((float)$payment['amount_due']) . " was submitted for member {$payment['first_name']} ({$payment['member_no']}).");

            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/social-fund/payments?fee=' . $payment['fee_id']], 'Waiver request submitted for approval.');
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('social_fund_waiver_failed', 'Waiver Request Failed', "Failed to submit waiver request for member {$payment['member_no']}: " . $e->getMessage());
            $this->jsonError('Failed to request waiver: ' . $e->getMessage(), null, 500);
        }
    }

    public function periodPayments(): void
    {
        $this->payments();
    }

    public function edit(int $id): void
    {
        $this->auth->requirePermission('social_fund.manage');
        $fee = $this->model->find($id);
        if (!$fee) {
            $this->flash('error', 'Fee not found.');
            $this->redirect('/social-fund');
        }
        
        $this->view('social-fund/create', array_merge(
            $this->prepareViewData('Edit Fee Configuration', 'social-fund'),
            ['fee' => $fee] 
        ));
    }

    public function update(int $id): void
    {
        $this->auth->requirePermission('social_fund.manage');
        $this->verifyCsrf();
        
        $oldData = $this->model->find($id);
        if (!$oldData) {
            $this->jsonError('Fee not found.', null, 404);
        }

        $data = $this->getPost();
        $updateData = [
            'name'           => $data['name'],
            'amount'         => (float)$data['amount'],
            'frequency'      => $data['frequency'],
            'due_day'        => (int)($data['due_day'] ?? 1),
            'grace_days'     => (int)($data['grace_days'] ?? 5),
            'penalty_amount' => (float)($data['penalty_amount'] ?? 0),
            'applies_to'     => $data['applies_to'] ?? 'all',
            'effective_from' => $data['effective_from'] ?? $oldData['effective_from'],
            'effective_to'   => !empty($data['effective_to']) ? $data['effective_to'] : null,
            'is_mandatory'   => isset($data['is_mandatory']) ? 1 : 0,
            'status'         => $data['status'] ?? $oldData['status'],
        ];

        $this->db->beginTransaction();
        try {
            if ($updateData['status'] === 'active') {
                $this->db->execute("UPDATE social_fund_fees SET status='inactive' WHERE status='active' AND id != ?", [$id]);
            }

            $this->model->update($id, $updateData);
            
            $this->logAudit('social_fund_fee_updated', 'social_fund', $id, 'SocialFundFee', 
                "Fee '{$data['name']}' updated", json_encode($oldData), json_encode($updateData));

            // ENFORCE: Notify admins of configuration change
            $this->notifyAdmins('social_fund_fee_updated', 'Social Fund Fee Updated', 
                "The social fund fee '{$data['name']}' has been updated.");

            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/social-fund'], 'Fee configuration updated successfully.');
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('social_fund_update_failed', 'Fee Update Failed', "Failed to update fee '{$data['name']}': " . $e->getMessage());
            $this->jsonError('Failed to update fee: ' . $e->getMessage(), null, 500);
        }
    }

    // ── HELPERS ────────────────────────────────────────────────────

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
            WHERE p.slug IN ('social_fund.manage', 'social_fund.view') AND u.status = 'active'
        ");
        foreach ($admins as $admin) {
            $this->notif->dispatch((int)$admin['id'], $type, $title, $message);
        }
    }
}