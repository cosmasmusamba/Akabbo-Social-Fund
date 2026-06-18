<?php
namespace App\Models;

/**
 * AKABBO SOCIAL FUND — Expense Model
 *
 * Tracks all SACCO operational expenditure with category
 * classification, approval workflow, and period reporting.
 */
class Expense extends BaseModel
{
    protected string $table = 'expenses';
    protected bool $useSoftDelete = true;

    /**
     * Generate a unique, sequential expense reference (e.g., EXP-2026-0001).
     */
    public function generateRef(): string
    {
        $year = date('Y');
        $last = $this->db->fetchColumn(
            "SELECT expense_ref FROM expenses WHERE expense_ref LIKE ? ORDER BY id DESC LIMIT 1",
            ["EXP-{$year}-%"]
        );
        
        $seq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = (int)$m[1] + 1;
        }
        
        return "EXP-{$year}-" . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get paginated list of expenses with filters.
     */
    public function getList(int $page = 1, int $limit = 25, array $filters = []): array
    {
        $where  = ['e.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['status'])) { 
            $where[] = 'e.status = ?'; 
            $params[] = $filters['status']; 
        }
        if (!empty($filters['category_id'])) { 
            $where[] = 'e.category_id = ?'; 
            $params[] = $filters['category_id']; 
        }
        if (!empty($filters['from'])) { 
            $where[] = 'e.expense_date >= ?'; 
            $params[] = $filters['from']; 
        }
        if (!empty($filters['to'])) { 
            $where[] = 'e.expense_date <= ?'; 
            $params[] = $filters['to']; 
        }
        if (!empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $where[] = '(e.title LIKE ? OR e.expense_ref LIKE ? OR e.payee_name LIKE ?)';
            $params = array_merge($params, [$like, $like, $like]);
        }

        $ws = 'WHERE ' . implode(' AND ', $where);
        $offset = ($page - 1) * $limit;
        
        $total = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM expenses e {$ws}", $params);
        
        $data = $this->db->fetchAll("
            SELECT e.*, 
                   ec.name AS category_name,
                   CONCAT(u.first_name,' ',u.last_name) AS created_by_name,
                   CONCAT(ua.first_name,' ',ua.last_name) AS approved_by_name
            FROM expenses e
            JOIN expense_categories ec ON ec.id = e.category_id
            JOIN users u ON u.id = e.created_by
            LEFT JOIN users ua ON ua.id = e.approved_by
            {$ws} 
            ORDER BY e.expense_date DESC, e.id DESC
            LIMIT {$limit} OFFSET {$offset}
        ", $params);

        return [
            'data' => $data, 
            'total' => $total, 
            'page' => $page,
            'per_page' => $limit, 
            'last_page' => (int)ceil($total / $limit),
            'from' => $total > 0 ? $offset + 1 : 0, 
            'to' => min($offset + $limit, $total)
        ];
    }

    /**
     * Get detailed expense record with related user names.
     */
    public function getDetail(int $id): ?array
    {
        return $this->db->fetchOne("
            SELECT e.*, 
                   ec.name AS category_name,
                   CONCAT(u.first_name,' ',u.last_name) AS created_by_name,
                   CONCAT(ua.first_name,' ',ua.last_name) AS approved_by_name,
                   CONCAT(up.first_name,' ',up.last_name) AS paid_by_name
            FROM expenses e
            JOIN expense_categories ec ON ec.id = e.category_id
            JOIN users u ON u.id = e.created_by
            LEFT JOIN users ua ON ua.id = e.approved_by
            LEFT JOIN users up ON up.id = e.paid_by
            WHERE e.id = ? AND e.deleted_at IS NULL
        ", [$id]);
    }

    /**
     * Get summary statistics for a given date range.
     * (FIXED: Removed duplicate SQL fragment and syntax error)
     */
    public function getSummaryStats(?string $from = null, ?string $to = null): array
    {
        $from = $from ?? date('Y-01-01');
        $to   = $to ?? date('Y-m-d');
        
        return $this->db->fetchOne("
            SELECT 
                COUNT(*) AS total_expenses, 
                COALESCE(SUM(amount), 0) AS total_amount,
                COALESCE(SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END), 0) AS approved_amount,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) AS paid_amount,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) AS pending_amount,
                SUM(status = 'pending') AS pending_count, 
                SUM(status = 'approved') AS approved_count,
                SUM(status = 'paid') AS paid_count, 
                SUM(status = 'rejected') AS rejected_count
            FROM expenses 
            WHERE deleted_at IS NULL AND expense_date BETWEEN ? AND ?
        ", [$from, $to]);
    }

    /**
     * Get expense breakdown by category.
     */
    public function getByCategory(?string $from = null, ?string $to = null): array
    {
        $from = $from ?? date('Y-01-01');
        $to   = $to ?? date('Y-m-d');
        
        return $this->db->fetchAll("
            SELECT 
                ec.name AS category, 
                COUNT(e.id) AS expense_count,
                COALESCE(SUM(e.amount), 0) AS total_amount, 
                COALESCE(AVG(e.amount), 0) AS avg_amount
            FROM expense_categories ec
            LEFT JOIN expenses e ON e.category_id = ec.id 
                AND e.deleted_at IS NULL
                AND e.expense_date BETWEEN ? AND ? 
                AND e.status IN ('approved', 'paid')
            GROUP BY ec.id 
            ORDER BY total_amount DESC
        ", [$from, $to]);
    }

    /**
     * Get monthly expense trend for the last N months.
     */
    public function getMonthlyTrend(int $months = 12): array
    {
        return $this->db->fetchAll("
            SELECT 
                DATE_FORMAT(expense_date, '%Y-%m') AS period, 
                COUNT(*) AS count, 
                COALESCE(SUM(amount), 0) AS total
            FROM expenses 
            WHERE deleted_at IS NULL 
              AND status IN ('approved', 'paid')
              AND expense_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
            GROUP BY DATE_FORMAT(expense_date, '%Y-%m') 
            ORDER BY period ASC
        ", [$months]);
    }
}