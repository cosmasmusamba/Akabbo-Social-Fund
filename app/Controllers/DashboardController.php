<?php
namespace App\Controllers;

use App\Models\Member;
use App\Models\Loan;
use App\Helpers\Format;
use Database;

/**
 * AKABBO SOCIAL FUND
 * Dashboard Controller
 *
 * Aggregates all KPI metrics, chart data, pending approvals,
 * overdue loans, and recent transactions for the main dashboard.
 */
class DashboardController extends BaseController
{
    private Database $db;
    private Member   $memberModel;
    private Loan     $loanModel;

    public function __construct()
    {
        parent::__construct();
        $this->auth->requireAuth();
        $this->db          = Database::getInstance();
        $this->memberModel = new Member();
        $this->loanModel   = new Loan();
    }

    /**
     * Render the main dashboard view with all aggregated stats.
     */
    public function index(): void
    {
        $dashStats = $this->buildDashboardStats();
        $chartData = $this->buildChartData();

        $pendingLoans = $this->db->fetchAll("
            SELECT l.id, l.loan_no, l.principal_amount,
                   CONCAT(m.first_name,' ',m.last_name) AS member_name,
                   lp.name AS product_name
            FROM loans l
            JOIN members m ON m.id = l.member_id
            JOIN loan_products lp ON lp.id = l.loan_product_id
            WHERE l.status = 'pending' AND l.deleted_at IS NULL
            ORDER BY l.created_at ASC
            LIMIT 6
        ");

        $overdueLoans = $this->db->fetchAll("
            SELECT
                m.id AS member_id, m.member_no,
                CONCAT(m.first_name,' ',m.last_name) AS full_name,
                m.phone,
                SUM(lrs.total_due - lrs.total_paid) AS overdue_amount,
                MAX(DATEDIFF(CURDATE(), lrs.due_date)) AS days_overdue
            FROM loan_repayment_schedules lrs
            JOIN loans l ON l.id = lrs.loan_id
            JOIN members m ON m.id = l.member_id
            WHERE lrs.status = 'overdue'
            GROUP BY m.id
            ORDER BY overdue_amount DESC
            LIMIT 8
        ");

        $recentTransactions = $this->db->fetchAll("
            SELECT t.*, CONCAT(m.first_name,' ',m.last_name) AS member_name
            FROM transactions t
            LEFT JOIN members m ON m.id = t.member_id
            WHERE t.status IN ('completed','approved')
            ORDER BY t.created_at DESC
            LIMIT 8
        ");

        $overdueCount = $this->db->fetchColumn(
            "SELECT COUNT(DISTINCT l.member_id)
             FROM loans l
             JOIN loan_repayment_schedules lrs ON lrs.loan_id = l.id
             WHERE lrs.status = 'overdue'"
        );

        $settings = $this->getSettings();

        $unreadNotifications = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0",
            [$_SESSION['user_id']]
        );

        $this->view('dashboard/index', compact(
            'dashStats', 'chartData', 'pendingLoans', 'overdueLoans',
            'recentTransactions', 'overdueCount', 'settings', 'unreadNotifications'
        ));
    }

    /**
     * Build the main KPI statistics array for the dashboard.
     */
    private function buildDashboardStats(): array
    {
        // Savings totals
        $savings = $this->db->fetchOne("
            SELECT
                COALESCE(SUM(balance), 0) AS total_savings,
                COUNT(*) AS savings_accounts
            FROM savings_accounts
            WHERE status = 'active'
        ");

        // Loan portfolio stats
        $loans = $this->loanModel->getStats();

        // Member stats
        $memberStats = $this->memberModel->getStats();

        // Today's collections
        $today = $this->db->fetchOne("
            SELECT
                COALESCE(SUM(CASE WHEN txn_type IN ('deposit','loan_repayment') THEN amount ELSE 0 END), 0) AS collections_today,
                COUNT(*) AS txns_today
            FROM transactions
            WHERE DATE(created_at) = CURDATE()
              AND status IN ('completed','approved')
        ");

        // Savings growth vs last month
        $thisMonth  = $this->db->fetchColumn(
            "SELECT COALESCE(SUM(amount),0) FROM transactions WHERE txn_type='deposit' AND status='completed' AND MONTH(transaction_date)=MONTH(NOW()) AND YEAR(transaction_date)=YEAR(NOW())"
        );
        $lastMonth  = $this->db->fetchColumn(
            "SELECT COALESCE(SUM(amount),0) FROM transactions WHERE txn_type='deposit' AND status='completed' AND MONTH(transaction_date)=MONTH(NOW()-INTERVAL 1 MONTH) AND YEAR(transaction_date)=YEAR(NOW()-INTERVAL 1 MONTH)"
        );
        $savingsGrowth = $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1) : 0;

        return [
            'total_savings'       => (float)$savings['total_savings'],
            'savings_accounts'    => (int)$savings['savings_accounts'],
            'savings_growth'      => $savingsGrowth,
            'outstanding_balance' => (float)$loans['outstanding_balance'],
            'total_loans'         => (int)$loans['total_loans'],
            'active_loans'        => (int)($loans['active_loans'] + $loans['disbursed_loans']),
            'pending_loans'       => (int)$loans['pending_loans'],
            'total_repaid'        => (float)$loans['total_repaid'],
            'active_members'      => (int)$memberStats['active_members'],
            'total_members'       => (int)$memberStats['total_members'],
            'new_this_month'      => (int)$memberStats['new_this_month'],
            'collections_today'   => (float)$today['collections_today'],
            'txns_today'          => (int)$today['txns_today'],
            'member_stats'        => $memberStats,
        ];
    }

    /**
     * Build chart data for the last 6 months.
     */
    private function buildChartData(): array
    {
        $months   = [];
        $savings  = [];
        $loansArr = [];

        for ($i = 5; $i >= 0; $i--) {
            $date      = new \DateTime("first day of -{$i} month");
            $months[]  = $date->format('M Y');
            $yearMonth = $date->format('Y-m');

            $savAmt = $this->db->fetchColumn(
                "SELECT COALESCE(SUM(amount),0) FROM transactions
                 WHERE txn_type = 'deposit' AND status = 'completed'
                   AND DATE_FORMAT(transaction_date,'%Y-%m') = ?",
                [$yearMonth]
            );

            $lnAmt = $this->db->fetchColumn(
                "SELECT COALESCE(SUM(principal_amount),0) FROM loans
                 WHERE status IN ('disbursed','active','completed')
                   AND DATE_FORMAT(disbursement_date,'%Y-%m') = ?",
                [$yearMonth]
            );

            $savings[]  = (float)$savAmt;
            $loansArr[] = (float)$lnAmt;
        }

        // Loan portfolio breakdown for donut chart
        $portfolio = $this->db->fetchAll("
            SELECT status, COUNT(*) AS cnt
            FROM loans
            WHERE deleted_at IS NULL AND status NOT IN ('draft','cancelled','written_off')
            GROUP BY status
        ");

        $portfolioMap = [];
        foreach ($portfolio as $p) {
            $label = ucfirst($p['status']);
            $portfolioMap[$label] = (int)$p['cnt'];
        }

        return [
            'months'    => $months,
            'savings'   => $savings,
            'loans'     => $loansArr,
            'portfolio' => $portfolioMap,
        ];
    }

    /**
     * Get system settings as a key-value array.
     */
    private function getSettings(): array
    {
        $rows = $this->db->fetchAll("SELECT `key`, `value` FROM settings");
        $out  = [];
        foreach ($rows as $r) {
            $out[$r['key']] = $r['value'];
        }
        return $out;
    }

    /**
     * AJAX: Get mini dashboard stats for header update.
     */
    public function miniStats(): void
    {
        $this->auth->requireAuth();

        $pending = $this->db->fetchColumn("SELECT COUNT(*) FROM loans WHERE status = 'pending' AND deleted_at IS NULL");
        $unread  = $this->db->fetchColumn("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0", [$_SESSION['user_id']]);

        $this->jsonSuccess([
            'pending_approvals'   => (int)$pending,
            'unread_notifications'=> (int)$unread,
        ]);
    }
}
