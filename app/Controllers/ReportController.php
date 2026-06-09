<?php
namespace App\Controllers;

use App\Helpers\Format;
use Database;

/**
 * AKABBO SOCIAL FUND
 * Report Controller
 *
 * Generates financial reports: savings, loans, transactions,
 * member statements, and cash flow — with PDF/Excel/CSV export.
 */
class ReportController extends BaseController
{
    private Database $db;

    public function __construct()
    {
        parent::__construct();
        $this->auth->requireAuth();
        $this->db = Database::getInstance();
    }

    /** Reports dashboard index. */
    public function index(): void
    {
        $this->auth->requirePermission('reports.view');
        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnreadCount();

        // Quick summary stats for the reports landing
        $summary = [
            'total_savings'      => (float)$this->db->fetchColumn("SELECT COALESCE(SUM(balance),0) FROM savings_accounts WHERE status='active'"),
            'total_loans'        => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM loans WHERE deleted_at IS NULL"),
            'active_members'     => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM members WHERE status='active' AND deleted_at IS NULL"),
            'total_transactions' => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM transactions WHERE status='completed'"),
        ];

        $pageTitle   = 'Reports';
        $activePage  = 'reports';
        $breadcrumbs = ['Reports' => null];

        $this->view('reports/index', compact('summary','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'));
    }

    /** Savings report with date range filter. */
    public function savings(): void
    {
        $this->auth->requirePermission('reports.view');

        ['from' => $from, 'to' => $to] = $this->getDateRange();

        $data = $this->db->fetchAll("
            SELECT
                m.member_no, CONCAT(m.first_name,' ',m.last_name) AS member_name,
                m.phone, sa.account_no, sa.account_type,
                sa.balance, sa.status, sa.opened_at,
                COALESCE(SUM(CASE WHEN t.txn_type='deposit' AND t.transaction_date BETWEEN ? AND ? THEN t.amount ELSE 0 END),0) AS period_deposits,
                COALESCE(SUM(CASE WHEN t.txn_type='withdrawal' AND t.transaction_date BETWEEN ? AND ? THEN t.amount ELSE 0 END),0) AS period_withdrawals
            FROM savings_accounts sa
            JOIN members m ON m.id = sa.member_id
            LEFT JOIN transactions t ON t.savings_account_id = sa.id AND t.status='completed'
            WHERE sa.status != 'closed' AND m.deleted_at IS NULL
            GROUP BY sa.id
            ORDER BY sa.balance DESC
        ", [$from, $to, $from, $to]);

        $totals = [
            'total_balance'      => array_sum(array_column($data, 'balance')),
            'period_deposits'    => array_sum(array_column($data, 'period_deposits')),
            'period_withdrawals' => array_sum(array_column($data, 'period_withdrawals')),
            'account_count'      => count($data),
        ];

        if ($this->getQuery('export')) {
            $this->exportCsv($data, $from, $to, 'savings');
        }

        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnreadCount();
        $pageTitle  = 'Savings Report';
        $activePage = 'reports';
        $breadcrumbs = ['Reports' => APP_URL.'/reports', 'Savings' => null];

        $this->view('reports/savings', compact('data','totals','from','to','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'));
    }

    /** Loans report. */
    public function loans(): void
    {
        $this->auth->requirePermission('reports.view');

        ['from' => $from, 'to' => $to] = $this->getDateRange();
        $status = $this->getQuery('status', '');

        $whereExtra = $status ? "AND l.status = '{$status}'" : '';

        $data = $this->db->fetchAll("
            SELECT
                l.loan_no, CONCAT(m.first_name,' ',m.last_name) AS member_name,
                m.member_no, m.phone, lp.name AS product_name,
                l.principal_amount, l.interest_rate, l.term_months,
                l.total_payable, l.amount_paid, l.balance_outstanding,
                l.status, l.application_date, l.disbursement_date, l.expected_maturity_date
            FROM loans l
            JOIN members m ON m.id = l.member_id
            JOIN loan_products lp ON lp.id = l.loan_product_id
            WHERE l.deleted_at IS NULL
              AND l.application_date BETWEEN ? AND ?
              {$whereExtra}
            ORDER BY l.application_date DESC
        ", [$from, $to]);

        $totals = [
            'total_principal'  => array_sum(array_column($data, 'principal_amount')),
            'total_payable'    => array_sum(array_column($data, 'total_payable')),
            'total_collected'  => array_sum(array_column($data, 'amount_paid')),
            'outstanding'      => array_sum(array_column($data, 'balance_outstanding')),
            'loan_count'       => count($data),
        ];

        if ($this->getQuery('export')) $this->exportCsv($data, $from, $to, 'loans');

        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnreadCount();
        $pageTitle  = 'Loans Report';
        $activePage = 'reports';
        $breadcrumbs = ['Reports' => APP_URL.'/reports', 'Loans' => null];

        $this->view('reports/loans', compact('data','totals','from','to','status','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'));
    }

    /** Transactions report. */
    public function transactions(): void
    {
        $this->auth->requirePermission('reports.view');

        ['from' => $from, 'to' => $to] = $this->getDateRange();
        $type = $this->getQuery('type', '');

        $whereExtra = $type ? "AND t.txn_type = '{$type}'" : '';

        ['page' => $page, 'limit' => $limit] = $this->getPaginationParams();
        $offset = ($page - 1) * $limit;

        $total = (int)$this->db->fetchColumn("
            SELECT COUNT(*) FROM transactions t
            WHERE t.transaction_date BETWEEN ? AND ? AND t.status='completed' {$whereExtra}
        ", [$from, $to]);

        $data = $this->db->fetchAll("
            SELECT t.*, CONCAT(m.first_name,' ',m.last_name) AS member_name, m.member_no,
                   CONCAT(u.first_name,' ',u.last_name) AS created_by_name
            FROM transactions t
            LEFT JOIN members m ON m.id = t.member_id
            LEFT JOIN users u ON u.id = t.created_by
            WHERE t.transaction_date BETWEEN ? AND ? AND t.status='completed' {$whereExtra}
            ORDER BY t.transaction_date DESC, t.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ", [$from, $to]);

        $summary = $this->db->fetchOne("
            SELECT
                COALESCE(SUM(CASE WHEN txn_type='deposit' THEN amount ELSE 0 END),0) AS total_deposits,
                COALESCE(SUM(CASE WHEN txn_type='withdrawal' THEN amount ELSE 0 END),0) AS total_withdrawals,
                COALESCE(SUM(CASE WHEN txn_type='loan_disbursement' THEN amount ELSE 0 END),0) AS total_disbursements,
                COALESCE(SUM(CASE WHEN txn_type='loan_repayment' THEN amount ELSE 0 END),0) AS total_repayments,
                COUNT(*) AS txn_count
            FROM transactions
            WHERE transaction_date BETWEEN ? AND ? AND status='completed' {$whereExtra}
        ", [$from, $to]);

        if ($this->getQuery('export')) $this->exportCsv($data, $from, $to, 'transactions');

        $result      = ['data'=>$data,'total'=>$total,'page'=>$page,'per_page'=>$limit,'last_page'=>(int)ceil($total/$limit)];
        $settings    = $this->getSettings();
        $unreadNotifications = $this->getUnreadCount();
        $pageTitle   = 'Transactions Report';
        $activePage  = 'reports';
        $breadcrumbs = ['Reports' => APP_URL.'/reports', 'Transactions' => null];

        $this->view('reports/transactions', compact('result','summary','from','to','type','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'));
    }

    /** Cash flow report. */
    public function cashFlow(): void
    {
        $this->auth->requirePermission('reports.view');

        $year = (int)$this->getQuery('year', date('Y'));

        $monthly = [];
        for ($m = 1; $m <= 12; $m++) {
            $ym    = sprintf('%04d-%02d', $year, $m);
            $row   = $this->db->fetchOne("
                SELECT
                    COALESCE(SUM(CASE WHEN txn_type='deposit' THEN amount ELSE 0 END),0)          AS inflow_savings,
                    COALESCE(SUM(CASE WHEN txn_type='loan_repayment' THEN amount ELSE 0 END),0)    AS inflow_repayments,
                    COALESCE(SUM(CASE WHEN txn_type='withdrawal' THEN amount ELSE 0 END),0)        AS outflow_withdrawals,
                    COALESCE(SUM(CASE WHEN txn_type='loan_disbursement' THEN amount ELSE 0 END),0) AS outflow_loans
                FROM transactions
                WHERE DATE_FORMAT(transaction_date,'%Y-%m') = ? AND status='completed'
            ", [$ym]);

            $inflow  = (float)$row['inflow_savings'] + (float)$row['inflow_repayments'];
            $outflow = (float)$row['outflow_withdrawals'] + (float)$row['outflow_loans'];

            $monthly[] = [
                'month'               => date('M', mktime(0,0,0,$m,1,$year)),
                'month_num'           => $m,
                'inflow_savings'      => (float)$row['inflow_savings'],
                'inflow_repayments'   => (float)$row['inflow_repayments'],
                'outflow_withdrawals' => (float)$row['outflow_withdrawals'],
                'outflow_loans'       => (float)$row['outflow_loans'],
                'total_inflow'        => $inflow,
                'total_outflow'       => $outflow,
                'net_flow'            => $inflow - $outflow,
            ];
        }

        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnreadCount();
        $pageTitle  = 'Cash Flow Report — ' . $year;
        $activePage = 'reports';
        $breadcrumbs = ['Reports' => APP_URL.'/reports', 'Cash Flow' => null];

        $this->view('reports/cash-flow', compact('monthly','year','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'));
    }

    /** Export data as CSV. */
    public function export(): void
    {
        $this->auth->requirePermission('reports.export');
        $type = $this->getQuery('type', 'transactions');
        ['from' => $from, 'to' => $to] = $this->getDateRange();

        // Delegate to the typed export method
        $this->{$type}();
    }

    // ── Private helpers ──────────────────────────────────────────

    private function getDateRange(): array
    {
        $from = $this->getQuery('from', date('Y-m-01'));
        $to   = $this->getQuery('to',   date('Y-m-t'));

        // Validate formats
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-t');

        return compact('from', 'to');
    }

    private function exportCsv(array $data, string $from, string $to, string $type): void
    {
        $this->auth->logAudit($_SESSION['user_id'], 'report_exported', 'reports', null, null, "{$type} report exported ({$from} to {$to})");

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="akabbo-' . $type . '-' . $from . '-to-' . $to . '.csv"');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM

        if (!empty($data)) {
            fputcsv($out, array_keys($data[0]));
            foreach ($data as $row) {
                fputcsv($out, array_values($row));
            }
        } else {
            fputcsv($out, ['No data found for the selected period.']);
        }

        fclose($out);
        exit;
    }

    private function getSettings(): array
    {
        $rows = $this->db->fetchAll("SELECT `key`, `value` FROM settings");
        return array_column($rows, 'value', 'key');
    }

    private function getUnreadCount(): int
    {
        return (int)$this->db->fetchColumn("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0", [$_SESSION['user_id']]);
    }
}
