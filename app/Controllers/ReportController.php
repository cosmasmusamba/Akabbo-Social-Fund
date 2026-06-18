<?php
// this controller must mind to generate reports for all respective tables in the database at any time reuired. Enforce logAudit and (RBAC + IDOR). Determine the user's data scope (Global, Group, Personal, or None)
namespace App\Controllers;

use App\Helpers\Format;
use App\Services\NotificationService;
use Database;

class ReportController extends BaseController
{
    private NotificationService $notif;

    public function __construct()
    {
        parent::__construct();
        $this->auth->requireAuth();
        $this->notif = new NotificationService();
    }

    // ── DASHBOARD SUMMARY ──────────────────────────────────────────
    public function index(): void
    {
        $perms = ['global' => 'reports.view', 'group' => 'groups.view_members', 'personal' => 'reports.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to view reports.');
        $scope = $this->resolveDataScope($perms);
        
        $summary = [
            'total_savings'      => (float)$this->db->fetchColumn("SELECT COALESCE(SUM(balance),0) FROM savings_accounts WHERE status='active' " . $this->buildScopeCondition($scope, 'member_id')),
            'total_loans'        => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM loans WHERE deleted_at IS NULL " . $this->buildScopeCondition($scope, 'member_id')),
            'active_members'     => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM members WHERE status='active' AND deleted_at IS NULL " . $this->buildScopeCondition($scope, 'id')),
            'total_transactions' => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM transactions WHERE status='completed' " . $this->buildScopeCondition($scope, 'member_id')),
        ];

        $this->logAudit('reports_dashboard_viewed', 'reports', null, null, "Viewed reports dashboard");

        $this->view('reports/index', array_merge(
            $this->prepareViewData('Reports', 'reports'),
            ['summary' => $summary]
        ));
    }

    // ── SAVINGS REPORT ─────────────────────────────────────────────
    public function savings(): void
    {
        $perms = ['global' => 'reports.view', 'group' => 'groups.view_members', 'personal' => 'reports.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to view savings reports.');
        $scope = $this->resolveDataScope($perms);
        $scopeCondition = $this->buildScopeCondition($scope, 'sa.member_id');

        ['from' => $from, 'to' => $to] = $this->getDateRange();

        $data = $this->db->fetchAll("
            SELECT sa.account_no, sa.account_type, sa.balance, sa.status, sa.opened_at,
                   m.member_no, CONCAT(m.first_name,' ',m.last_name) AS member_name, m.phone,
                   COALESCE(SUM(CASE WHEN t.txn_type='deposit' AND t.transaction_date BETWEEN ? AND ? THEN t.amount ELSE 0 END),0) AS total_deposits,
                   COALESCE(SUM(CASE WHEN t.txn_type='withdrawal' AND t.transaction_date BETWEEN ? AND ? THEN t.amount ELSE 0 END),0) AS total_withdrawals
            FROM savings_accounts sa
            JOIN members m ON m.id = sa.member_id
            LEFT JOIN transactions t ON t.savings_account_id = sa.id AND t.status='completed'
            WHERE sa.status != 'closed' AND m.deleted_at IS NULL {$scopeCondition}
            GROUP BY sa.id, sa.account_no, sa.account_type, sa.balance, sa.status, sa.opened_at, m.member_no, m.first_name, m.last_name, m.phone
            ORDER BY sa.balance DESC
        ", [$from, $to, $from, $to]);

        $totals = [
            'total_savings'     => array_sum(array_column($data, 'balance')),
            'total_deposits'    => array_sum(array_column($data, 'total_deposits')),
            'total_withdrawals' => array_sum(array_column($data, 'total_withdrawals')),
            'accounts'          => count($data),
        ];

        if ($this->getQuery('export')) $this->exportCsv($data, $from, $to, 'savings');

        $this->logAudit('savings_report_viewed', 'reports', null, null, "Viewed savings report");

        $this->view('reports/savings', array_merge(
            $this->prepareViewData('Savings Report', 'reports', ['Reports' => APP_URL.'/reports', 'Savings' => null]),
            ['data' => $data, 'totals' => $totals, 'filters' => ['from' => $from, 'to' => $to]]
        ));
    }

    // ── LOANS REPORT ───────────────────────────────────────────────
    public function loans(): void
    {
        $perms = ['global' => 'reports.view', 'group' => 'groups.view_members', 'personal' => 'reports.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to view loan reports.');
        $scope = $this->resolveDataScope($perms);
        $scopeCondition = $this->buildScopeCondition($scope, 'l.member_id');

        ['from' => $from, 'to' => $to] = $this->getDateRange();
        $status = $this->getQuery('status', '');

        $whereExtra = $status ? "AND l.status = ?" : '';
        $params = [$from, $to];
        if ($status) $params[] = $status;

        $data = $this->db->fetchAll("
            SELECT l.loan_no, CONCAT(m.first_name,' ',m.last_name) AS member_name, m.member_no, m.phone, 
                   lp.name AS product_name, l.principal_amount, l.interest_rate, l.term_months,
                   l.total_payable, l.amount_paid, l.balance_outstanding, l.status, 
                   l.application_date, l.disbursement_date, l.expected_maturity_date
            FROM loans l
            JOIN members m ON m.id = l.member_id
            JOIN loan_products lp ON lp.id = l.loan_product_id
            WHERE l.deleted_at IS NULL AND l.application_date BETWEEN ? AND ? {$scopeCondition} {$whereExtra}
            ORDER BY l.application_date DESC
        ", $params);

        $totals = [
            'total_principal' => array_sum(array_column($data, 'principal_amount')),
            'total_payable'   => array_sum(array_column($data, 'total_payable')),
            'total_collected' => array_sum(array_column($data, 'amount_paid')),
            'outstanding'     => array_sum(array_column($data, 'balance_outstanding')),
            'loan_count'      => count($data),
        ];

        if ($this->getQuery('export')) $this->exportCsv($data, $from, $to, 'loans');

        $this->logAudit('loans_report_viewed', 'reports', null, null, "Viewed loans report");

        $this->view('reports/loans', array_merge(
            $this->prepareViewData('Loans Report', 'reports', ['Reports' => APP_URL.'/reports', 'Loans' => null]),
            ['data' => $data, 'totals' => $totals, 'filters' => ['from' => $from, 'to' => $to, 'status' => $status]]
        ));
    }

    // ── TRANSACTIONS REPORT ────────────────────────────────────────
    public function transactions(): void
    {
        $perms = ['global' => 'reports.view', 'group' => 'groups.view_members', 'personal' => 'reports.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to view transaction reports.');
        $scope = $this->resolveDataScope($perms);
        $scopeCondition = $this->buildScopeCondition($scope, 't.member_id');

        ['from' => $from, 'to' => $to] = $this->getDateRange();
        $type = $this->getQuery('type', '');
        ['page' => $page, 'limit' => $limit] = $this->getPaginationParams();
        $offset = ($page - 1) * $limit;

        $whereExtra = $type ? "AND t.txn_type = ?" : '';
        $params = [$from, $to];
        if ($type) $params[] = $type;

        $total = (int)$this->db->fetchColumn("
            SELECT COUNT(*) FROM transactions t
            WHERE t.transaction_date BETWEEN ? AND ? AND t.status='completed' {$scopeCondition} {$whereExtra}
        ", $params);

        $data = $this->db->fetchAll("
            SELECT t.*, CONCAT(m.first_name,' ',m.last_name) AS member_name, m.member_no,
                   CONCAT(u.first_name,' ',u.last_name) AS created_by_name
            FROM transactions t
            LEFT JOIN members m ON m.id = t.member_id
            LEFT JOIN users u ON u.id = t.created_by
            WHERE t.transaction_date BETWEEN ? AND ? AND t.status='completed' {$scopeCondition} {$whereExtra}
            ORDER BY t.transaction_date DESC, t.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ", $params);

        $summary = $this->db->fetchOne("
            SELECT COALESCE(SUM(CASE WHEN txn_type='deposit' THEN amount ELSE 0 END),0) AS total_deposits,
                   COALESCE(SUM(CASE WHEN txn_type='withdrawal' THEN amount ELSE 0 END),0) AS total_withdrawals,
                   COALESCE(SUM(CASE WHEN txn_type='loan_disbursement' THEN amount ELSE 0 END),0) AS total_disbursements,
                   COALESCE(SUM(CASE WHEN txn_type='loan_repayment' THEN amount ELSE 0 END),0) AS total_repayments,
                   COUNT(*) AS txn_count
            FROM transactions
            WHERE transaction_date BETWEEN ? AND ? AND status='completed' {$scopeCondition} {$whereExtra}
        ", $params);

        if ($this->getQuery('export')) $this->exportCsv($data, $from, $to, 'transactions');

        $this->logAudit('transactions_report_viewed', 'reports', null, null, "Viewed transactions report");

        $this->view('reports/transactions', array_merge(
            $this->prepareViewData('Transactions Report', 'reports', ['Reports' => APP_URL.'/reports', 'Transactions' => null]),
            ['result' => ['data' => $data, 'total' => $total, 'page' => $page, 'per_page' => $limit, 'last_page' => (int)ceil($total/$limit)], 
             'summary' => $summary, 'filters' => ['from' => $from, 'to' => $to, 'type' => $type]]
        ));
    }

    // ── MEMBERS REPORT ───────────────────────────────────────────────
    public function members(): void
    {
        $perms = ['global' => 'reports.view', 'group' => 'groups.view_members', 'personal' => 'reports.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to view member reports.');
        $scope = $this->resolveDataScope($perms);
        $scopeCondition = $this->buildScopeCondition($scope, 'm.id');

        ['from' => $from, 'to' => $to] = $this->getDateRange();

        $data = $this->db->fetchAll("
            SELECT m.id, m.member_no, CONCAT(m.first_name, ' ', m.last_name) AS full_name, 
                   m.gender, m.phone, m.email, m.district, m.occupation, m.status, m.kyc_verified,
                   m.membership_date, g.name AS group_name,
                   COALESCE(SUM(sa.balance), 0) AS total_savings,
                   COALESCE(SUM(CASE WHEN l.status IN ('active', 'disbursed') THEN l.balance_outstanding ELSE 0 END), 0) AS total_loan_balance
            FROM members m
            LEFT JOIN savings_groups g ON g.id = m.group_id
            LEFT JOIN savings_accounts sa ON sa.member_id = m.id AND sa.status != 'closed'
            LEFT JOIN loans l ON l.member_id = m.id AND l.deleted_at IS NULL
            WHERE m.deleted_at IS NULL AND m.membership_date BETWEEN ? AND ? {$scopeCondition}
            GROUP BY m.id
            ORDER BY m.membership_date DESC
        ", [$from, $to]);

        $totals = [
            'total_members' => count($data),
            'active_members' => count(array_filter($data, fn($m) => $m['status'] === 'active')),
            'kyc_verified' => count(array_filter($data, fn($m) => $m['kyc_verified'] == 1)),
            'total_savings' => array_sum(array_column($data, 'total_savings')),
        ];

        if ($this->getQuery('export')) $this->exportCsv($data, $from, $to, 'members');

        $this->logAudit('members_report_viewed', 'reports', null, null, "Viewed members report");

        $this->view('reports/members', array_merge(
            $this->prepareViewData('Members Report', 'reports', ['Reports' => APP_URL.'/reports', 'Members' => null]),
            ['data' => $data, 'totals' => $totals, 'filters' => ['from' => $from, 'to' => $to]]
        ));
    }

    // ── SHARES REPORT ────────────────────────────────────────────────
    public function shares(): void
    {
        $perms = ['global' => 'reports.view', 'group' => 'groups.view_members', 'personal' => 'reports.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to view share reports.');
        $scope = $this->resolveDataScope($perms);
        $scopeCondition = $this->buildScopeCondition($scope, 'st.member_id');

        ['from' => $from, 'to' => $to] = $this->getDateRange();

        $data = $this->db->fetchAll("
            SELECT st.txn_ref, CONCAT(m.first_name, ' ', m.last_name) AS member_name, m.member_no,
                   st.txn_type, st.shares_qty, st.total_amount, st.payment_method, st.transaction_date, st.status
            FROM share_transactions st
            JOIN members m ON m.id = st.member_id
            WHERE st.transaction_date BETWEEN ? AND ? {$scopeCondition}
            ORDER BY st.transaction_date DESC
        ", [$from, $to]);

        $totals = [
            'total_purchased' => array_sum(array_map(fn($r) => $r['txn_type'] === 'purchase' ? $r['total_amount'] : 0, $data)),
            'total_shares' => array_sum(array_map(fn($r) => $r['txn_type'] === 'purchase' ? $r['shares_qty'] : 0, $data)),
            'txn_count' => count($data),
        ];

        if ($this->getQuery('export')) $this->exportCsv($data, $from, $to, 'shares');

        $this->logAudit('shares_report_viewed', 'reports', null, null, "Viewed shares report");

        $this->view('reports/shares', array_merge(
            $this->prepareViewData('Shares Report', 'reports', ['Reports' => APP_URL.'/reports', 'Shares' => null]),
            ['data' => $data, 'totals' => $totals, 'filters' => ['from' => $from, 'to' => $to]]
        ));
    }

    // ── EXPENSES REPORT ──────────────────────────────────────────────
    public function expenses(): void
    {
        // Expenses are typically global. If you need group scoping, ensure expenses table has group_id.
        $this->auth->requirePermission('reports.view'); 
        
        ['from' => $from, 'to' => $to] = $this->getDateRange();
        $status = $this->getQuery('status', '');
        $whereExtra = $status ? "AND e.status = ?" : '';
        $params = [$from, $to];
        if ($status) $params[] = $status;

        $data = $this->db->fetchAll("
            SELECT e.expense_ref, ec.name AS category_name, e.title, e.amount, e.payment_method, 
                   e.payee_name, e.expense_date, e.status
            FROM expenses e
            JOIN expense_categories ec ON ec.id = e.category_id
            WHERE e.deleted_at IS NULL AND e.expense_date BETWEEN ? AND ? {$whereExtra}
            ORDER BY e.expense_date DESC
        ", $params);

        $totals = [
            'total_expenses' => array_sum(array_column($data, 'amount')),
            'txn_count' => count($data),
        ];

        if ($this->getQuery('export')) $this->exportCsv($data, $from, $to, 'expenses');

        $this->logAudit('expenses_report_viewed', 'reports', null, null, "Viewed expenses report");

        $this->view('reports/expenses', array_merge(
            $this->prepareViewData('Expenses Report', 'reports', ['Reports' => APP_URL.'/reports', 'Expenses' => null]),
            ['data' => $data, 'totals' => $totals, 'filters' => ['from' => $from, 'to' => $to, 'status' => $status]]
        ));
    }

    // ── TRANSFERS REPORT ─────────────────────────────────────────────
    public function transfers(): void
    {
        $perms = ['global' => 'reports.view', 'group' => 'groups.view_members', 'personal' => 'reports.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to view transfer reports.');
        $scope = $this->resolveDataScope($perms);
        
        // Custom scope condition for transfers (checks both sender and receiver)
        $scopeCondition = '';
        if ($scope['type'] === 'personal') {
            $mid = (int)$scope['member_id'];
            $scopeCondition = "AND (ft.from_member_id = {$mid} OR ft.to_member_id = {$mid})";
        } elseif ($scope['type'] === 'group') {
            $gid = (int)$scope['group_id'];
            $scopeCondition = "AND (ft.from_member_id IN (SELECT id FROM members WHERE group_id = {$gid} AND deleted_at IS NULL) OR ft.to_member_id IN (SELECT id FROM members WHERE group_id = {$gid} AND deleted_at IS NULL))";
        } elseif ($scope['type'] === 'none') {
            $scopeCondition = "AND 1=0";
        }

        ['from' => $from, 'to' => $to] = $this->getDateRange();

        $data = $this->db->fetchAll("
            SELECT ft.transfer_ref, 
                   CONCAT(mf.first_name, ' ', mf.last_name) AS from_name, mf.member_no AS from_no,
                   CONCAT(mt.first_name, ' ', mt.last_name) AS to_name, mt.member_no AS to_no,
                   ft.amount, ft.description, ft.transfer_date, ft.status
            FROM fund_transfers ft
            JOIN members mf ON mf.id = ft.from_member_id
            JOIN members mt ON mt.id = ft.to_member_id
            WHERE ft.transfer_date BETWEEN ? AND ? {$scopeCondition}
            ORDER BY ft.transfer_date DESC
        ", [$from, $to]);

        $totals = [
            'total_transferred' => array_sum(array_column($data, 'amount')),
            'txn_count' => count($data),
        ];

        if ($this->getQuery('export')) $this->exportCsv($data, $from, $to, 'transfers');

        $this->logAudit('transfers_report_viewed', 'reports', null, null, "Viewed transfers report");

        $this->view('reports/transfers', array_merge(
            $this->prepareViewData('Transfers Report', 'reports', ['Reports' => APP_URL.'/reports', 'Transfers' => null]),
            ['data' => $data, 'totals' => $totals, 'filters' => ['from' => $from, 'to' => $to]]
        ));
    }

    // ── SOCIAL FUND REPORT ───────────────────────────────────────────
    public function socialFund(): void
    {
        $perms = ['global' => 'reports.view', 'group' => 'groups.view_members', 'personal' => 'reports.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to view social fund reports.');
        $scope = $this->resolveDataScope($perms);
        $scopeCondition = $this->buildScopeCondition($scope, 'sfp.member_id');

        ['from' => $from, 'to' => $to] = $this->getDateRange();

        $data = $this->db->fetchAll("
            SELECT sfp.id, CONCAT(m.first_name, ' ', m.last_name) AS member_name, m.member_no,
                   sff.name AS fee_name, sfp.amount_due, sfp.amount_paid, sfp.penalty_paid, 
                   sfp.period_month, sfp.period_year, sfp.status
            FROM social_fund_fee_payments sfp
            JOIN members m ON m.id = sfp.member_id
            JOIN social_fund_fees sff ON sff.id = sfp.fee_id
            WHERE sfp.created_at BETWEEN ? AND ? {$scopeCondition}
            ORDER BY sfp.created_at DESC
        ", [$from . ' 00:00:00', $to . ' 23:59:59']);

        $totals = [
            'total_due' => array_sum(array_column($data, 'amount_due')),
            'total_paid' => array_sum(array_column($data, 'amount_paid')),
            'total_penalty' => array_sum(array_column($data, 'penalty_paid')),
            'txn_count' => count($data),
        ];

        if ($this->getQuery('export')) $this->exportCsv($data, $from, $to, 'social_fund');

        $this->logAudit('social_fund_report_viewed', 'reports', null, null, "Viewed social fund report");

        $this->view('reports/social-fund', array_merge(
            $this->prepareViewData('Social Fund Report', 'reports', ['Reports' => APP_URL.'/reports', 'Social Fund' => null]),
            ['data' => $data, 'totals' => $totals, 'filters' => ['from' => $from, 'to' => $to]]
        ));
    }

    // ── CASH FLOW REPORT ───────────────────────────────────────────
    public function cashFlow(): void
    {
        $perms = ['global' => 'reports.view', 'group' => 'groups.view_members', 'personal' => 'reports.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to view cash flow reports.');
        $scope = $this->resolveDataScope($perms);
        $scopeCondition = $this->buildScopeCondition($scope, 'member_id');

        $year = (int)$this->getQuery('year', date('Y'));
        $monthly = [];

        for ($m = 1; $m <= 12; $m++) {
            $ym = sprintf('%04d-%02d', $year, $m);
            $row = $this->db->fetchOne("
                SELECT COALESCE(SUM(CASE WHEN txn_type='deposit' THEN amount ELSE 0 END),0) AS inflow_savings,
                       COALESCE(SUM(CASE WHEN txn_type='loan_repayment' THEN amount ELSE 0 END),0) AS inflow_repayments,
                       COALESCE(SUM(CASE WHEN txn_type='withdrawal' THEN amount ELSE 0 END),0) AS outflow_withdrawals,
                       COALESCE(SUM(CASE WHEN txn_type='loan_disbursement' THEN amount ELSE 0 END),0) AS outflow_loans
                FROM transactions WHERE DATE_FORMAT(transaction_date,'%Y-%m') = ? AND status='completed' {$scopeCondition}
            ", [$ym]);

            $inflow  = (float)$row['inflow_savings'] + (float)$row['inflow_repayments'];
            $outflow = (float)$row['outflow_withdrawals'] + (float)$row['outflow_loans'];

            $monthly[] = [
                'month' => date('M', mktime(0,0,0,$m,1,$year)), 'month_num' => $m,
                'inflow_savings' => (float)$row['inflow_savings'], 'inflow_repayments' => (float)$row['inflow_repayments'],
                'outflow_withdrawals' => (float)$row['outflow_withdrawals'], 'outflow_loans' => (float)$row['outflow_loans'],
                'total_inflow' => $inflow, 'total_outflow' => $outflow, 'net_flow' => $inflow - $outflow,
            ];
        }

        $this->logAudit('cash_flow_report_viewed', 'reports', null, null, "Viewed cash flow report for {$year}");

        $this->view('reports/cash-flow', array_merge(
            $this->prepareViewData('Cash Flow Report — ' . $year, 'reports', ['Reports' => APP_URL.'/reports', 'Cash Flow' => null]),
            ['monthly' => $monthly, 'filters' => ['year' => $year]]
        ));
    }

    // ── EXPORT ENGINE ──────────────────────────────────────────────
    public function export(): void
    {
        $this->auth->requirePermission('reports.export');
        $type = $this->getQuery('type', 'transactions');
        
        // SECURITY: Whitelist allowed export types to prevent arbitrary method execution
        $allowedTypes = ['transactions', 'savings', 'loans', 'members', 'shares', 'expenses', 'transfers', 'social_fund', 'service_fees'];
        if (!in_array($type, $allowedTypes, true)) {
            $this->flash('error', 'Invalid report type for export.');
            $this->redirect('/reports');
        }

        $method = ($type === 'social_fund') ? 'socialFund' : $type;
        
        $_GET['export'] = '1'; // Force export flag for the target method
        
        $this->logAudit('report_export_initiated', 'reports', null, null, "Initiated export for {$type} report");
        
        $this->{$method}();
    }

    // ── SERVICE FEES ENGINE ──────────────────────────────────────────────
    public function serviceFees(): void
    {
        $perms = ['global' => 'reports.view', 'group' => 'groups.view_members', 'personal' => 'reports.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to view service fee reports.');
        $scope = $this->resolveDataScope($perms);
        
        $tScope = $this->buildScopeCondition($scope, 't.member_id');
        $pdScope = $this->buildScopeCondition($scope, 'pd.member_id');

        ['from' => $from, 'to' => $to] = $this->getDateRange();

        $collected = $this->db->fetchOne("
            SELECT 
                COALESCE(SUM(amount), 0) AS total_collected,
                COUNT(*) AS collected_count
            FROM transactions t
            WHERE t.transaction_date BETWEEN ? AND ? 
              AND t.txn_type IN ('balance_inquiry_fee', 'statement_request_fee') 
              AND t.status = 'completed' {$tScope}
        ", [$from, $to]);

        $pending = $this->db->fetchOne("
            SELECT 
                COALESCE(SUM(amount), 0) AS total_pending,
                COUNT(*) AS pending_count
            FROM pending_debits pd
            WHERE pd.settled_at IS NULL {$pdScope}
        ");

        $collectedFees = $this->db->fetchAll("
            SELECT t.*, CONCAT(m.first_name, ' ', m.last_name) AS member_name, m.member_no
            FROM transactions t
            JOIN members m ON m.id = t.member_id
            WHERE t.transaction_date BETWEEN ? AND ? 
              AND t.txn_type IN ('balance_inquiry_fee', 'statement_request_fee') 
              AND t.status = 'completed' {$tScope}
            ORDER BY t.transaction_date DESC, t.id DESC
            LIMIT 200
        ", [$from, $to]);

        $pendingDebits = $this->db->fetchAll("
            SELECT pd.*, CONCAT(m.first_name, ' ', m.last_name) AS member_name, m.member_no
            FROM pending_debits pd
            JOIN members m ON m.id = pd.member_id
            WHERE pd.settled_at IS NULL {$pdScope}
            ORDER BY pd.created_at DESC
            LIMIT 200
        ");

        if ($this->getQuery('export')) {
            $this->exportCsv($collectedFees, $from, $to, 'service_fees');
        }

        $summary = [
            'total_collected' => (float)($collected['total_collected'] ?? 0),
            'collected_count' => (int)($collected['collected_count'] ?? 0),
            'total_pending'   => (float)($pending['total_pending'] ?? 0),
            'pending_count'   => (int)($pending['pending_count'] ?? 0),
        ];

        $this->logAudit('service_fees_report_viewed', 'reports', null, null, "Viewed service fees report");

        $this->view('reports/service-fees', array_merge(
            $this->prepareViewData('Service Fees Report', 'reports', ['Reports' => APP_URL.'/reports', 'Service Fees' => null]),
            [
                'summary' => $summary,
                'collectedFees' => $collectedFees,
                'pendingDebits' => $pendingDebits,
                'filters' => ['from' => $from, 'to' => $to]
            ]
        ));
    }

    // ── HELPERS ────────────────────────────────────────────────────
    private function getDateRange(): array
    {
        $from = $this->getQuery('from', date('Y-m-01'));
        $to   = $this->getQuery('to', date('Y-m-t'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-t');
        return compact('from', 'to');
    }

    private function exportCsv(array $data, string $from, string $to, string $type): void
    {
        $this->logAudit('report_exported', 'reports', null, null, 
            "{$type} report exported", null, json_encode(['type' => $type, 'from' => $from, 'to' => $to]));

        $this->notifyAdmins('report_exported', 'Report Exported', 
            "A {$type} report ({$from} to {$to}) was exported by " . ($this->auth->user()['first_name'] ?? 'Unknown') . ".");

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="akabbo-' . $type . '-' . $from . '-to-' . $to . '.csv"');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel compatibility

        if (!empty($data)) {
            fputcsv($out, array_keys($data[0]));
            foreach ($data as $row) fputcsv($out, array_values($row));
        } else {
            fputcsv($out, ['No data found for the selected period.']);
        }
        fclose($out);
        exit;
    }

    // ── HELPER: Notify all admins via multi-channel dispatch ────────
    private function notifyAdmins(string $type, string $title, string $message): void
    {
        $admins = $this->db->fetchAll("
            SELECT DISTINCT u.id 
            FROM users u 
            JOIN role_permissions rp ON rp.role_id = u.role_id 
            JOIN permissions p ON p.id = rp.permission_id 
            WHERE p.slug IN ('reports.view', 'reports.export') AND u.status = 'active'
        ");
        foreach ($admins as $admin) {
            $this->notif->dispatch((int)$admin['id'], $type, $title, $message);
        }
    }
}