<?php
namespace App\Models;

/**
 * AKABBO SOCIAL FUND — Group Model
 */
class Group extends BaseModel
{
    protected string $table = 'savings_groups';

    public function generateCode(): string
    {
        $last = $this->db->fetchColumn("SELECT group_code FROM `savings_groups` ORDER BY id DESC LIMIT 1");
        $seq  = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = (int)$m[1] + 1;
        }
        return 'GRP-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    public function getWithStats(): array
    {
        return $this->db->fetchAll("
            SELECT g.*,
                   COUNT(DISTINCT m.id)                          AS member_count,
                   COALESCE(SUM(sa.balance),0)                   AS total_savings,
                   COUNT(DISTINCT CASE WHEN l.status IN ('active','disbursed') THEN l.id END) AS active_loans
            FROM `savings_groups` g
            LEFT JOIN members m ON m.group_id = g.id AND m.deleted_at IS NULL AND m.status='active'
            LEFT JOIN savings_accounts sa ON sa.member_id = m.id AND sa.status='active'
            LEFT JOIN loans l ON l.member_id = m.id AND l.deleted_at IS NULL
            GROUP BY g.id
            ORDER BY g.name ASC
        ");
    }

    public function getMembers(int $groupId): array
    {
        return $this->db->fetchAll("
            SELECT m.*, COALESCE(SUM(sa.balance),0) AS total_savings
            FROM members m
            LEFT JOIN savings_accounts sa ON sa.member_id = m.id AND sa.status='active'
            WHERE m.group_id = ? AND m.deleted_at IS NULL
            GROUP BY m.id
            ORDER BY m.first_name
        ", [$groupId]);
    }
}
