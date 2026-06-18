<?php
namespace App\Models;

/**
 * AKABBO SOCIAL FUND — Group Model
 *
 * Handles savings group operations, statistics, and member aggregations.
 */
class Group extends BaseModel
{
    protected string $table = 'savings_groups';

    /**
     * Generate a unique, sequential group code (e.g., GRP-0001).
     * 
     * @return string The new group code
     */
    public function generateCode(): string
    {
        $last = $this->db->fetchColumn(
            "SELECT group_code FROM `savings_groups` WHERE group_code LIKE 'GRP-%' ORDER BY id DESC LIMIT 1"
        );
        
        $seq = 1;
        if ($last && preg_match('/GRP-(\d+)$/', $last, $m)) {
            $seq = (int)$m[1] + 1;
        }
        
        return 'GRP-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get all groups with aggregated member counts, total savings, and active loans.
     * 
     * @return array List of groups with statistical aggregates
     */
    public function getWithStats(): array
    {
        return $this->db->fetchAll("
            SELECT g.*,
                   COUNT(DISTINCT m.id) AS member_count,
                   COALESCE(SUM(sa.balance), 0) AS total_savings,
                   COUNT(DISTINCT CASE WHEN l.status IN ('active','disbursed') THEN l.id END) AS active_loans
            FROM `savings_groups` g
            LEFT JOIN members m ON m.group_id = g.id AND m.deleted_at IS NULL AND m.status = 'active'
            LEFT JOIN savings_accounts sa ON sa.member_id = m.id AND sa.status = 'active'
            LEFT JOIN loans l ON l.member_id = m.id AND l.deleted_at IS NULL
            GROUP BY g.id
            ORDER BY g.name ASC
        ");
    }

    /**
     * Get all active members belonging to a specific group, with their total savings.
     * 
     * @param int $groupId The ID of the group
     * @return array List of members in the group
     */
    public function getMembers(int $groupId): array
    {
        return $this->db->fetchAll("
            SELECT m.*, COALESCE(SUM(sa.balance), 0) AS total_savings
            FROM members m
            LEFT JOIN savings_accounts sa ON sa.member_id = m.id AND sa.status = 'active'
            WHERE m.group_id = ? AND m.deleted_at IS NULL
            GROUP BY m.id
            ORDER BY m.first_name ASC, m.last_name ASC
        ", [$groupId]);
    }

    /**
     * Close a group (soft delete equivalent for groups).
     * Note: The controller should ensure no active members are assigned before calling this.
     * 
     * @param int $id The ID of the group to close
     * @return bool True if successful, false otherwise
     */
    public function close(int $id): bool
    {
        $result = $this->db->execute(
            "UPDATE `savings_groups` SET status = 'closed', updated_at = NOW() WHERE id = ?",
            [$id]
        );
        return $result > 0;
    }
}