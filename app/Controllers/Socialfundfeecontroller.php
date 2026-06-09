<?php
namespace App\Controllers;

use App\Models\SocialFundFee;
use App\Helpers\Format;
use Database;

/**
 * AKABBO SOCIAL FUND — Social Fund Fee Controller
 * Manage, configure and collect periodic member fees.
 */
class SocialFundFeeController extends BaseController
{
    private SocialFundFee $model;
    private Database      $db;

    public function __construct()
    {
        parent::__construct();
        $this->auth->requireAuth();
        $this->model = new SocialFundFee();
        $this->db    = Database::getInstance();
    }

    public function index(): void
    {
        $this->auth->requirePermission('social_fund.view');

        $fees        = $this->model->getAll();
        $activeFee   = $this->model->getActive();
        $settings    = $this->getSettings();
        $unreadNotifications = $this->getUnread();

        // Current month summary
        $summary = null;
        if ($activeFee) {
            $summary = $this->model->getPaymentSummary($activeFee['id'], (int)date('n'), (int)date('Y'));
        }

        $pageTitle   = 'Social Fund Fees';
        $activePage  = 'social-fund';
        $breadcrumbs = ['Social Fund Fees' => null];

        $this->view('social-fund/index', compact(
            'fees','activeFee','summary','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'
        ));
    }

    public function create(): void
    {
        $this->auth->requirePermission('social_fund.manage');
        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnread();

        $pageTitle   = 'New Fee Configuration';
        $activePage  = 'social-fund';
        $breadcrumbs = ['Social Fund Fees' => APP_URL.'/social-fund', 'New' => null];

        $this->view('social-fund/create', compact(
            'settings','pageTitle','activePage','breadcrumbs','unreadNotifications'
        ));
    }

    public function store(): void
    {
        $this->auth->requirePermission('social_fund.manage');
        $this->verifyCsrf();

        $data    = $this->getPost();
        $missing = $this->validateRequired($data, ['name','amount','frequency','due_day','effective_from']);
        if ($missing) {
            $this->jsonError('Required fields missing.', array_fill_keys($missing,'Required'), 422);
        }

        if ((float)$data['amount'] <= 0) {
            $this->jsonError('Fee amount must be greater than zero.', null, 422);
        }

        $id = $this->model->create([
            'name'            => $data['name'],
            'amount'          => (float)$data['amount'],
            'frequency'       => $data['frequency'],
            'due_day'         => (int)$data['due_day'],
            'grace_days'      => (int)($data['grace_days'] ?? 5),
            'penalty_amount'  => (float)($data['penalty_amount'] ?? 0),
            'is_mandatory'    => isset($data['is_mandatory']) ? 1 : 0,
            'applies_to'      => $data['applies_to'] ?? 'all',
            'status'          => $data['status'] ?? 'active',
            'effective_from'  => $data['effective_from'],
            'effective_to'    => !empty($data['effective_to']) ? $data['effective_to'] : null,
            'created_by'      => $_SESSION['user_id'],
        ]);

        // If activating new fee, deactivate old ones
        if (($data['status'] ?? '') === 'active') {
            $this->db->execute(
                "UPDATE social_fund_fees SET status='inactive' WHERE id != ? AND status='active'", [$id]
            );
        }

        $this->auth->logAudit($_SESSION['user_id'], 'social_fund_fee_created', 'social_fund', (int)$id,
            'SocialFundFee', "Fee '{$data['name']}' created: " . Format::currency((float)$data['amount']));

        $this->jsonSuccess(
            ['redirect' => APP_URL.'/social-fund'],
            "Fee '{$data['name']}' configured successfully."
        );
    }

    public function update(int $id): void
    {
        $this->auth->requirePermission('social_fund.manage');
        $this->verifyCsrf();

        $data = $this->getPost();
        $this->model->update($id, [
            'name'           => $data['name'],
            'amount'         => (float)$data['amount'],
            'frequency'      => $data['frequency'],
            'due_day'        => (int)$data['due_day'],
            'grace_days'     => (int)($data['grace_days'] ?? 5),
            'penalty_amount' => (float)($data['penalty_amount'] ?? 0),
            'is_mandatory'   => isset($data['is_mandatory']) ? 1 : 0,
            'applies_to'     => $data['applies_to'] ?? 'all',
            'status'         => $data['status'] ?? 'active',
            'effective_to'   => !empty($data['effective_to']) ? $data['effective_to'] : null,
        ]);

        $this->auth->logAudit($_SESSION['user_id'], 'social_fund_fee_updated', 'social_fund', $id,
            'SocialFundFee', "Fee ID {$id} updated");
        $this->jsonSuccess(['redirect' => APP_URL.'/social-fund'], 'Fee configuration updated.');
    }

    // ── Generate fee records for a period ─────────────────────────
    public function generatePeriod(): void
    {
        $this->auth->requirePermission('social_fund.manage');
        $this->verifyCsrf();

        $data    = $this->getPost();
        $feeId   = (int)($data['fee_id'] ?? 0);
        $month   = (int)($data['month'] ?? date('n'));
        $year    = (int)($data['year']  ?? date('Y'));

        if (!$feeId || $month < 1 || $month > 12 || $year < 2000) {
            $this->jsonError('Invalid fee, month or year.', null, 422);
        }

        try {
            $result = $this->model->generateMonthlyFees($feeId, $month, $year, $_SESSION['user_id']);
            $this->auth->logAudit($_SESSION['user_id'], 'fee_period_generated', 'social_fund', $feeId,
                'SocialFundFee', "Generated {$result['inserted']} fee records for {$year}-{$month}");

            $this->jsonSuccess($result,
                "{$result['inserted']} fee record(s) generated for " . date('F Y', mktime(0,0,0,$month,1,$year))
                . ($result['skipped'] ? ". {$result['skipped']} already existed." : '.')
            );
        } catch (\Exception $e) {
            $this->jsonError('Generation failed: ' . $e->getMessage(), null, 500);
        }
    }

    // ── Record a payment ─────────────────────────────────────────
    public function recordPayment(): void
    {
        $this->auth->requirePermission('social_fund.manage');
        $this->verifyCsrf();

        $data    = $this->getPost();
        $missing = $this->validateRequired($data, ['payment_id','amount_paid','payment_method']);
        if ($missing) {
            $this->jsonError('Required fields missing.', array_fill_keys($missing,'Required'), 422);
        }

        try {
            $this->model->recordPayment(
                (int)$data['payment_id'],
                (float)$data['amount_paid'],
                (float)($data['penalty_paid'] ?? 0),
                $data['payment_method'],
                $_SESSION['user_id']
            );

            // Record financial transaction
            $payment = $this->db->fetchOne(
                "SELECT sfp.*, sff.name AS fee_name, m.id AS member_id
                 FROM social_fund_fee_payments sfp
                 JOIN social_fund_fees sff ON sff.id=sfp.fee_id
                 JOIN members m ON m.id=sfp.member_id
                 WHERE sfp.id=?", [(int)$data['payment_id']]
            );

            if ($payment) {
                $this->db->execute("
                    INSERT INTO transactions
                        (txn_ref, txn_type, amount, member_id, payment_method, description,
                         transaction_date, status, created_by)
                    VALUES (?, 'membership_fee', ?, ?, ?, ?, CURDATE(), 'completed', ?)
                ", [
                    'TXN-' . strtoupper(uniqid()),
                    (float)$data['amount_paid'],
                    $payment['member_id'],
                    $data['payment_method'],
                    "Social fund fee: {$payment['fee_name']} — " .
                        date('F Y', mktime(0,0,0,$payment['period_month'],1,$payment['period_year'])),
                    $_SESSION['user_id'],
                ]);
            }

            $this->jsonSuccess(null, 'Payment recorded successfully.');
        } catch (\Exception $e) {
            $this->jsonError('Payment failed: ' . $e->getMessage(), null, 500);
        }
    }

    // ── Waive a fee ───────────────────────────────────────────────
    public function waive(): void
    {
        $this->auth->requirePermission('social_fund.manage');
        $this->verifyCsrf();

        $paymentId = (int)$this->getPost()['payment_id'];
        $reason    = trim($this->getPost()['reason'] ?? '');

        if (empty($reason)) {
            $this->jsonError('A reason is required to waive a fee.', null, 422);
        }

        $this->db->execute("
            UPDATE social_fund_fee_payments
            SET status='waived', waived_by=?, waived_reason=?, updated_at=NOW()
            WHERE id=?
        ", [$_SESSION['user_id'], $reason, $paymentId]);

        $this->jsonSuccess(null, 'Fee waived successfully.');
    }

    // ── Payments list for a period ────────────────────────────────
    public function periodPayments(): void
    {
        $this->auth->requirePermission('social_fund.view');

        $feeId   = (int)$this->getQuery('fee', 0);
        $month   = (int)$this->getQuery('month', (int)date('n'));
        $year    = (int)$this->getQuery('year',  (int)date('Y'));

        $fee     = $feeId ? $this->model->find($feeId) : $this->model->getActive();
        if (!$fee) { $this->flash('error','No active fee configuration found.'); $this->redirect('/social-fund'); }

        $summary  = $this->model->getPaymentSummary($fee['id'], $month, $year);
        $arrears  = $this->model->getMemberArrears($fee['id']);
        $payments = $this->db->fetchAll("
            SELECT sfp.*, CONCAT(m.first_name,' ',m.last_name) AS member_name, m.member_no, m.phone
            FROM social_fund_fee_payments sfp
            JOIN members m ON m.id = sfp.member_id
            WHERE sfp.fee_id=? AND sfp.period_month=? AND sfp.period_year=?
            ORDER BY sfp.status ASC, m.first_name ASC
        ", [$fee['id'], $month, $year]);

        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnread();
        $fees     = $this->model->getAll();

        $pageTitle   = 'Fee Payments — ' . date('F Y', mktime(0,0,0,$month,1,$year));
        $activePage  = 'social-fund';
        $breadcrumbs = ['Social Fund Fees' => APP_URL.'/social-fund', date('F Y', mktime(0,0,0,$month,1,$year)) => null];

        $this->view('social-fund/payments', compact(
            'fee','fees','payments','summary','arrears','month','year',
            'settings','pageTitle','activePage','breadcrumbs','unreadNotifications'
        ));
    }

    // ── Mark overdue (cron/manual) ───────────────────────────────
    public function markOverdue(): void
    {
        $this->auth->requirePermission('social_fund.manage');
        $count = $this->model->markOverdue();
        $this->jsonSuccess(['marked' => $count], "{$count} record(s) marked overdue.");
    }

    private function getSettings(): array
    {
        return array_column($this->db->fetchAll("SELECT `key`,`value` FROM settings"), 'value', 'key');
    }
    private function getUnread(): int
    {
        return (int)$this->db->fetchColumn("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0", [$_SESSION['user_id']]);
    }
}