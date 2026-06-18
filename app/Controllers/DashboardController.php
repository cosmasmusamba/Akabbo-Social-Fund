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
 * Aggregates KPI metrics with strict RBAC and IDOR data scoping.
 * Note: As a read-only controller, no multi-channel notifications are triggered here.
 */
class DashboardController extends BaseController
{
    private Member $memberModel;
    private Loan   $loanModel;

    public function __construct()
    {
        parent::__construct();
        
        $this->auth->requireAuth(); 
        
        $this->memberModel = new Member();
        $this->loanModel   = new Loan();
    }

    // ── MAIN DASHBOARD ─────────────────────────────────────────────

    /**
     * Render the main dashboard view with scoped stats.
     */
    public function index(): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = [
            'global'   => 'reports.view', 
            'group'    => 'groups.view_members',
            'personal' => 'members.view_own'
        ];
        $this->requireScopeAccess($perms, 'You do not have permission to view the dashboard.');
        $scope = $this->resolveDataScope($perms);
        
        // ENFORCE: Audit log for viewing dashboard
        $this->logAudit('dashboard_viewed', 'dashboard', null, null, "Viewed dashboard (Scope: {$scope['type']})");

        // Build scoped data
        $dashStats = $this->buildDashboardStats($scope);
        $chartData = $this->buildChartData($scope);

        // Fetch scoped lists (Pending, Overdue, Recent)
        $cLoan = $this->buildScopeCondition($scope, 'l.member_id');
        $pendingLoans = $this->db->fetchAll("
            SELECT l.id, l.loan_no, l.principal_amount,
            CONCAT(m.first_name,' ',m.last_name) AS member_name,
            lp.name AS product_name
            FROM loans l
            JOIN members m ON m.id = l.member_id
            JOIN loan_products lp ON lp.id = l.loan_product_id
            WHERE l.status = 'pending' AND l.deleted_at IS NULL
            {$cLoan}
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
            {$cLoan}
            GROUP BY m.id
            ORDER BY overdue_amount DESC
            LIMIT 8
        ");

        $cTxn = $this->buildScopeCondition($scope, 't.member_id');
        $recentTransactions = $this->db->fetchAll("
            SELECT t.*, CONCAT(m.first_name,' ',m.last_name) AS member_name
            FROM transactions t
            LEFT JOIN members m ON m.id = t.member_id
            WHERE t.status IN ('completed','approved')
            {$cTxn}
            ORDER BY t.created_at DESC
            LIMIT 8
        ");

        $overdueCount = $this->db->fetchColumn("
            SELECT COUNT(DISTINCT l.member_id)
            FROM loans l
            JOIN loan_repayment_schedules lrs ON lrs.loan_id = l.id
            WHERE lrs.status = 'overdue'
            " . $this->buildScopeCondition($scope, 'l.member_id')
        );

        // Inherited from BaseController
        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnreadCount();

        $this->view('dashboard/index', compact(
            'dashStats', 'chartData', 'pendingLoans', 'overdueLoans',
            'recentTransactions', 'overdueCount', 'settings', 'unreadNotifications', 'scope'
        ));
    }

    // ── DATA BUILDERS ──────────────────────────────────────────────

    private function buildDashboardStats(array $scope): array
    {
        $c = $this->buildScopeCondition($scope, 'member_id'); 
        
        // Savings totals
        $savings = $this->db->fetchOne("
            SELECT
            COALESCE(SUM(balance), 0) AS total_savings,
            COUNT(*) AS savings_accounts
            FROM savings_accounts
            WHERE status = 'active' {$c}
        ");

        // Loan portfolio stats
        $loans = $this->db->fetchOne("
            SELECT
            COUNT(*)                                    AS total_loans,
            SUM(status = 'active')                      AS active_loans,
            SUM(status = 'pending')                     AS pending_loans,
            SUM(status = 'completed')                   AS completed_loans,
            SUM(status = 'defaulted')                   AS defaulted_loans,
            SUM(status = 'disbursed')                   AS disbursed_loans,
            COALESCE(SUM(principal_amount), 0)          AS total_principal,
            COALESCE(SUM(CASE WHEN status IN ('active','disbursed') THEN balance_outstanding ELSE 0 END), 0) AS outstanding_balance,
            COALESCE(SUM(amount_paid), 0)               AS total_repaid
            FROM loans WHERE deleted_at IS NULL {$c}
        ");

        // Member stats
        if ($scope['type'] === 'global') {
            $memberStats = $this->memberModel->getStats();
        } else {
            $cMembers = $this->buildScopeCondition($scope, 'id'); 
            $memberStats = $this->db->fetchOne("
                SELECT
                COUNT(*) AS total_members,
                SUM(status = 'active') AS active_members,
                SUM(status = 'inactive') AS inactive_members,
                SUM(status = 'suspended') AS suspended_members,
                SUM(kyc_verified = 1) AS kyc_verified,
                SUM(kyc_verified = 0 AND status = 'active') AS kyc_pending,
                SUM(MONTH(membership_date) = MONTH(NOW()) AND YEAR(membership_date) = YEAR(NOW())) AS new_this_month
                FROM members WHERE deleted_at IS NULL {$cMembers}
            ");
        }

        // Today's collections
        $today = $this->db->fetchOne("
            SELECT
            COALESCE(SUM(CASE WHEN txn_type IN ('deposit','loan_repayment') THEN amount ELSE 0 END), 0) AS collections_today,
            COUNT(*) AS txns_today
            FROM transactions
            WHERE DATE(created_at) = CURDATE()
            AND status IN ('completed','approved')
            {$c}
        ");

        // Savings growth vs last month
        $thisMonth  = $this->db->fetchColumn(
            "SELECT COALESCE(SUM(amount),0) FROM transactions WHERE txn_type='deposit' AND status='completed' AND MONTH(transaction_date)=MONTH(NOW()) AND YEAR(transaction_date)=YEAR(NOW()) {$c}"
        );
        $lastMonth  = $this->db->fetchColumn(
            "SELECT COALESCE(SUM(amount),0) FROM transactions WHERE txn_type='deposit' AND status='completed' AND MONTH(transaction_date)=MONTH(NOW()-INTERVAL 1 MONTH) AND YEAR(transaction_date)=YEAR(NOW()-INTERVAL 1 MONTH) {$c}"
        );
        $savingsGrowth = $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1) : 0;

        // ENHANCEMENT: Pending Approvals Queue (Global count for users with permission)
        $pendingApprovals = 0;
        if ($this->auth->can('approvals.process')) {
            $pendingApprovals = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM approvals WHERE status = 'pending'");
        }

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
            'pending_approvals'   => $pendingApprovals, 
        ];
    }

    private function buildChartData(array $scope): array
    {
        $c = $this->buildScopeCondition($scope, 'member_id');

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
                AND DATE_FORMAT(transaction_date,'%Y-%m') = ?
                {$c}",
                [$yearMonth]
            );

            $lnAmt = $this->db->fetchColumn(
                "SELECT COALESCE(SUM(principal_amount),0) FROM loans
                WHERE status IN ('disbursed','active','completed')
                AND DATE_FORMAT(disbursement_date,'%Y-%m') = ?
                {$c}",
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
            {$c}
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

    // ── AJAX ENDPOINTS ─────────────────────────────────────────────

    /**
     * AJAX: Get mini dashboard stats for header update.
     */
    public function miniStats(): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = [
            'global'   => 'reports.view', 
            'group'    => 'groups.view_members',
            'personal' => 'members.view_own'
        ];
        $this->requireScopeAccess($perms);
        $scope = $this->resolveDataScope($perms);
        
        $c = $this->buildScopeCondition($scope, 'member_id');

        $pending = $this->db->fetchColumn("SELECT COUNT(*) FROM loans WHERE status = 'pending' AND deleted_at IS NULL {$c}");
        $unread  = $this->getUnreadCount();

        // Returns clean JSON for frontend AJAX modal/header updates (No native prompts)
        $this->jsonSuccess([
            'pending_approvals'   => (int)$pending,
            'unread_notifications'=> (int)$unread,
        ]);
    }
}