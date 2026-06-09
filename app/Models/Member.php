<?php
namespace App\Models;

/**
 * AKABBO SOCIAL FUND
 * Member Model
 *
 * Handles all database operations for the members table,
 * including savings summary aggregations and status management.
 */
class Member extends BaseModel
{
    protected string $table = 'members';
    protected bool $useSoftDelete = true;

    /**
     * Generate the next sequential member number (e.g. AKB-00345).
     */
    public function generateMemberNo(): string
    {
        $last = $this->db->fetchColumn(
            "SELECT member_no FROM members ORDER BY id DESC LIMIT 1"
        );

        $seq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = (int)$m[1] + 1;
        }

        return 'AKB-' . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Get full member profile including savings and loan totals.
     */
    public function getProfile(int $memberId): ?array
    {
        $sql = "
            SELECT
                m.*,
                COALESCE(SUM(sa.balance), 0)       AS total_savings,
                COUNT(DISTINCT sa.id)               AS savings_accounts,
                COUNT(DISTINCT l.id)                AS total_loans,
                COALESCE(SUM(CASE WHEN l.status IN ('active','disbursed') THEN l.balance_outstanding ELSE 0 END), 0) AS active_loan_balance
            FROM members m
            LEFT JOIN savings_accounts sa ON sa.member_id = m.id AND sa.status = 'active'
            LEFT JOIN loans l ON l.member_id = m.id AND l.deleted_at IS NULL
            WHERE m.id = ? AND m.deleted_at IS NULL
            GROUP BY m.id
        ";
        return $this->db->fetchOne($sql, [$memberId]);
    }

    /**
     * Search members by name, phone, email, or member number.
     */
    public function search(string $term, int $limit = 20): array
    {
        $like = "%{$term}%";
        $sql = "
            SELECT id, member_no, first_name, last_name, phone, email, status
            FROM members
            WHERE deleted_at IS NULL
              AND (
                    CONCAT(first_name, ' ', last_name) LIKE ?
                 OR member_no LIKE ?
                 OR phone LIKE ?
                 OR email LIKE ?
              )
            ORDER BY first_name ASC
            LIMIT ?
        ";
        return $this->db->fetchAll($sql, [$like, $like, $like, $like, $limit]);
    }

    /**
     * Get paginated member list with savings and loan summaries.
     */
    public function getList(int $page = 1, int $limit = 25, array $filters = []): array
    {
        $where  = ['m.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = 'm.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $like     = '%' . $filters['search'] . '%';
            $where[]  = "(CONCAT(m.first_name,' ',m.last_name) LIKE ? OR m.member_no LIKE ? OR m.phone LIKE ?)";
            $params   = array_merge($params, [$like, $like, $like]);
        }
        if (!empty($filters['group_id'])) {
            $where[]  = 'm.group_id = ?';
            $params[] = $filters['group_id'];
        }
        if (!empty($filters['kyc_verified'])) {
            $where[]  = 'm.kyc_verified = ?';
            $params[] = (int)$filters['kyc_verified'];
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $offset   = ($page - 1) * $limit;

        $countSql = "SELECT COUNT(*) FROM members m {$whereSql}";
        $total    = (int)$this->db->fetchColumn($countSql, $params);

        $dataSql = "
            SELECT
                m.id, m.member_no, m.first_name, m.last_name, m.gender,
                m.phone, m.email, m.district, m.membership_date,
                m.status, m.kyc_verified, m.avatar,
                COALESCE(SUM(sa.balance), 0) AS total_savings,
                COUNT(DISTINCT CASE WHEN l.status IN ('active','disbursed') THEN l.id END) AS active_loans
            FROM members m
            LEFT JOIN savings_accounts sa ON sa.member_id = m.id AND sa.status = 'active'
            LEFT JOIN loans l ON l.member_id = m.id AND l.deleted_at IS NULL
            {$whereSql}
            GROUP BY m.id
            ORDER BY m.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        return [
            'data'      => $this->db->fetchAll($dataSql, $params),
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $limit,
            'last_page' => (int)ceil($total / $limit),
        ];
    }

    /**
     * Get member statistics for dashboard.
     */
    public function getStats(): array
    {
        return $this->db->fetchOne("
            SELECT
                COUNT(*)                                            AS total_members,
                SUM(status = 'active')                             AS active_members,
                SUM(status = 'inactive')                           AS inactive_members,
                SUM(status = 'suspended')                          AS suspended_members,
                SUM(kyc_verified = 1)                              AS kyc_verified,
                SUM(kyc_verified = 0 AND status = 'active')        AS kyc_pending,
                SUM(MONTH(membership_date) = MONTH(NOW()) AND YEAR(membership_date) = YEAR(NOW())) AS new_this_month
            FROM members WHERE deleted_at IS NULL
        ");
    }

    /**
     * Get members with overdue loan repayments.
     */
    public function getWithOverdueLoans(): array
    {
        return $this->db->fetchAll("
            SELECT DISTINCT
                m.id, m.member_no, CONCAT(m.first_name,' ',m.last_name) AS full_name,
                m.phone,
                COUNT(DISTINCT lrs.id)  AS overdue_installments,
                SUM(lrs.total_due - lrs.total_paid) AS overdue_amount
            FROM members m
            JOIN loans l ON l.member_id = m.id AND l.status = 'active'
            JOIN loan_repayment_schedules lrs ON lrs.loan_id = l.id AND lrs.status = 'overdue'
            WHERE m.deleted_at IS NULL
            GROUP BY m.id
            ORDER BY overdue_amount DESC
        ");
    }
}
