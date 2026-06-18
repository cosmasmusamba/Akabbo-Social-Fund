<?php
// Enforce RBAC + IDOR for search results. Determine the user's data scope (Global, Group, Personal, or None)
namespace App\Controllers;

use App\Helpers\Format;
use Database;

class SearchController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->auth->requireAuth();
    }

    // ── GLOBAL SEARCH METHOD ───────────────────────────────────────

    public function search(): void
    {
        $q = $this->getQuery('q', '');
        if (strlen($q) < 2) { 
            $this->jsonSuccess([
                'members' => [], 'loans' => [], 'transactions' => [], 'savings' => [], 
                'groups' => [], 'shares' => [], 'expenses' => [], 'users' => [], 'approvals' => []
            ]);
            return; 
        }

        // ENFORCE: Centralized Scope Gatekeeper & Resolver
        // We use 'reports.view' as the proxy for global staff access to search.
        $perms = [
            'global'   => 'reports.view', 
            'group'    => 'groups.view_members',
            'personal' => 'members.view_own'
        ];
        $this->requireScopeAccess($perms, 'You do not have permission to perform searches.');
        $scope = $this->resolveDataScope($perms);

        // ENFORCE: Generate safe SQL WHERE conditions based on the centralized scope
        // By applying these conditions directly in the SQL, we inherently satisfy 
        // the IDOR requirement at the database level, preventing unauthorized data leakage.
        $memberScope = $this->buildScopeCondition($scope, 'm.id');
        $like = "%{$q}%";

        // 1. Members (Scoped)
        $members = $this->db->fetchAll("
            SELECT m.id, m.member_no, m.first_name, m.last_name, m.phone, m.status, 
                   (SELECT COALESCE(SUM(balance),0) FROM savings_accounts WHERE member_id=m.id AND status='active') AS total_savings 
            FROM members m 
            WHERE m.deleted_at IS NULL 
              AND (CONCAT(m.first_name,' ',m.last_name) LIKE ? OR m.member_no LIKE ? OR m.phone LIKE ?) 
              {$memberScope} 
            LIMIT 5
        ", [$like, $like, $like]);

        // 2. Loans (Scoped)
        $loans = $this->db->fetchAll("
            SELECT l.id, l.loan_no, l.status, l.principal_amount, l.balance_outstanding, 
                   CONCAT(m.first_name,' ',m.last_name) AS member_name, lp.name AS product_name 
            FROM loans l 
            JOIN members m ON m.id=l.member_id 
            JOIN loan_products lp ON lp.id=l.loan_product_id 
            WHERE l.deleted_at IS NULL 
              AND (l.loan_no LIKE ? OR CONCAT(m.first_name,' ',m.last_name) LIKE ?) 
              {$memberScope} 
            LIMIT 5
        ", [$like, $like]);

        // 3. Transactions (Scoped)
        $transactions = $this->db->fetchAll("
            SELECT t.id, t.txn_ref, t.txn_type, t.amount, t.transaction_date, t.status, 
                   CONCAT(m.first_name,' ',m.last_name) AS member_name 
            FROM transactions t 
            LEFT JOIN members m ON m.id = t.member_id 
            WHERE t.status IN ('completed', 'approved') 
              AND (t.txn_ref LIKE ? OR CONCAT(m.first_name,' ',m.last_name) LIKE ?) 
              {$memberScope} 
            LIMIT 5
        ", [$like, $like]);

        // 4. Savings (Scoped)
        $savings = $this->db->fetchAll("
            SELECT sa.id, sa.account_no, sa.balance, sa.status, 
                   CONCAT(m.first_name,' ',m.last_name) AS member_name 
            FROM savings_accounts sa 
            JOIN members m ON m.id = sa.member_id 
            WHERE sa.status != 'closed' 
              AND (sa.account_no LIKE ? OR CONCAT(m.first_name,' ',m.last_name) LIKE ?) 
              {$memberScope} 
            LIMIT 5
        ", [$like, $like]);

        // 5. Groups (Scoped by Group/Personal)
        $groups = [];
        if ($this->auth->can('groups.view')) {
            $groupScopeCondition = '';
            if ($scope['type'] === 'group') {
                $groupScopeCondition = " AND g.id = " . (int)$scope['group_id'];
            } elseif ($scope['type'] === 'personal') {
                $mGroupId = $this->db->fetchColumn("SELECT group_id FROM members WHERE id = ?", [$scope['member_id']]);
                $groupScopeCondition = $mGroupId ? " AND g.id = " . (int)$mGroupId : " AND 1=0";
            } elseif ($scope['type'] === 'none') {
                $groupScopeCondition = " AND 1=0";
            }
            
            $groups = $this->db->fetchAll("
                SELECT g.id, g.name, g.created_at 
                FROM savings_groups g 
                WHERE g.name LIKE ? {$groupScopeCondition} 
                LIMIT 5
            ", [$like]);
        }

        // 6. Shares (Scoped)
        $shares = [];
        if ($this->auth->can('shares.view')) {
            $shares = $this->db->fetchAll("
                SELECT st.id, st.txn_ref, st.txn_type, st.shares_qty, st.total_amount, 
                       CONCAT(m.first_name,' ',m.last_name) AS member_name 
                FROM share_transactions st 
                JOIN members m ON m.id = st.member_id 
                WHERE st.status IN ('completed', 'approved') 
                  AND (st.txn_ref LIKE ? OR CONCAT(m.first_name,' ',m.last_name) LIKE ?) 
                  {$memberScope} 
                LIMIT 5
            ", [$like, $like]);
        }

        // 7. Expenses (Global/Group Staff Only - Organizational Data)
        $expenses = [];
        if ($this->auth->can('expenses.view') && in_array($scope['type'], ['global', 'group'])) {
            $expenses = $this->db->fetchAll("
                SELECT e.id, e.expense_ref, e.description, e.amount, e.expense_date, e.payee_name, c.name AS category_name 
                FROM expenses e 
                LEFT JOIN expense_categories c ON c.id = e.category_id 
                WHERE e.deleted_at IS NULL 
                  AND (e.expense_ref LIKE ? OR e.description LIKE ? OR e.payee_name LIKE ?) 
                LIMIT 5
            ", [$like, $like, $like]);
        }

        // 8. System Users (Admins Only - NOT scoped by member)
        $users = [];
        if ($this->auth->can('users.view')) {
            $users = $this->db->fetchAll("
                SELECT u.id, u.email, u.first_name, u.last_name, u.status, r.name AS role_name 
                FROM users u 
                JOIN roles r ON r.id = u.role_id 
                WHERE u.status != 'inactive' 
                  AND (u.email LIKE ? OR CONCAT(u.first_name,' ',u.last_name) LIKE ?) 
                LIMIT 5
            ", [$like, $like]);
        }

        // 9. Approvals (Approvers Only)
        $approvals = [];
        if ($this->auth->can('approvals.process') || $this->auth->can('approvals.view')) {
            // FIX: Changed 'a.notes' to 'a.approval_notes' to match the actual database schema.
            // We alias it back to 'notes' so the frontend rendering logic doesn't break.
            $approvals = $this->db->fetchAll("
                SELECT a.id, a.reference_type, a.reference_id, a.amount, a.status, a.approval_notes AS notes, a.created_at 
                FROM approvals a 
                WHERE a.status = 'pending' 
                  AND (a.reference_type LIKE ? OR a.approval_notes LIKE ?) 
                LIMIT 5
            ", [$like, $like]);
        }

        // ENFORCE: Audit log for search activity (Tracks what users are searching for)
        $this->logAudit('global_search_performed', 'search', null, null, "Performed global search for '{$q}'");

        // ── Null-Safe Formatting for Frontend Rendering ──────────────────
        
        $members = array_map(fn($m) => [
            'id' => $m['id'], 'member_no' => $m['member_no'], 
            'full_name' => trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')),
            'initials' => Format::initials(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))),
            'phone' => $m['phone'], 'status' => $m['status'],
            'total_savings_fmt' => Format::currency((float)($m['total_savings'] ?? 0))
        ], $members);

        $loans = array_map(fn($l) => [
            'id' => $l['id'], 'loan_no' => $l['loan_no'], 'status' => $l['status'],
            'member_name' => $l['member_name'], 'product_name' => $l['product_name'],
            'principal_fmt' => Format::currency((float)($l['principal_amount'] ?? 0)),
            'outstanding_fmt' => Format::currency((float)($l['balance_outstanding'] ?? 0))
        ], $loans);

        $transactions = array_map(fn($t) => [
            'id' => $t['id'], 'txn_ref' => $t['txn_ref'], 'status' => $t['status'],
            'member_name' => $t['member_name'],
            'amount_fmt' => Format::currency((float)($t['amount'] ?? 0)),
            'date_fmt' => Format::date($t['transaction_date'] ?? ''),
            'type_label' => ucwords(str_replace('_', ' ', $t['txn_type'] ?? ''))
        ], $transactions);

        $savings = array_map(fn($s) => [
            'id' => $s['id'], 'account_no' => $s['account_no'], 'status' => $s['status'],
            'member_name' => $s['member_name'],
            'balance_fmt' => Format::currency((float)($s['balance'] ?? 0))
        ], $savings);

        $groups = array_map(fn($g) => [
            'id' => $g['id'], 'name' => $g['name'],
            'date_fmt' => Format::date($g['created_at'] ?? '')
        ], $groups);

        $shares = array_map(fn($s) => [
            'id' => $s['id'], 'txn_ref' => $s['txn_ref'], 'shares_qty' => $s['shares_qty'],
            'member_name' => $s['member_name'],
            'amount_fmt' => Format::currency((float)($s['total_amount'] ?? 0)),
            'type_label' => ucwords(str_replace('_', ' ', $s['txn_type'] ?? ''))
        ], $shares);

        $expenses = array_map(fn($e) => [
            'id' => $e['id'], 'expense_ref' => $e['expense_ref'], 'description' => $e['description'],
            'category_name' => $e['category_name'],
            'amount_fmt' => Format::currency((float)($e['amount'] ?? 0)),
            'date_fmt' => Format::date($e['expense_date'] ?? '')
        ], $expenses);

        $users = array_map(fn($u) => [
            'id' => $u['id'], 'email' => $u['email'], 'status' => $u['status'],
            'full_name' => trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')),
            'initials' => Format::initials(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))),
            'role_name' => $u['role_name']
        ], $users);

        $approvals = array_map(fn($a) => [
            'id' => $a['id'], 'reference_type' => $a['reference_type'], 
            'reference_id' => $a['reference_id'], 'status' => $a['status'], 'notes' => $a['notes'],
            'amount_fmt' => Format::currency((float)($a['amount'] ?? 0)),
            'type_label' => ucwords(str_replace('_', ' ', $a['reference_type'] ?? '')),
            'date_fmt' => Format::date($a['created_at'] ?? '')
        ], $approvals);

        // Returns clean JSON for frontend AJAX rendering (No native prompts)
        $this->jsonSuccess(compact('members', 'loans', 'transactions', 'savings', 'groups', 'shares', 'expenses', 'users', 'approvals'));
    }
}