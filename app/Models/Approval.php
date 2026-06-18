<?php
namespace App\Models;

/**
 * AKABBO SOCIAL FUND — Approval Model
 *
 * Workflow engine for all financial activities requiring
 * authorised sign-off: loans, waivers, withdrawals, transfers, disbursements,
 * share transactions and expenses.
 */
class Approval extends BaseModel
{
    protected string $table = 'approvals';

    // ── Create an approval request ───────────────────────────────
    /**
     * Lodge a new approval request and return its ID.
     *
     * @param string $referenceType  e.g. 'withdrawal', 'transfer', 'disbursement'
     * @param int    $referenceId    PK of the record being approved
     * @param string $referenceRef   Human-readable ref (e.g. TXN-XXXXXX)
     * @param float  $amount
     * @param int    $requestedBy    User ID of initiator
     * @param string $notes          Mandatory context notes
     * @param int|null $assignedTo   Specific approver (null = any eligible)
     */
    public function request(
        string  $referenceType,
        int     $referenceId,
        string  $referenceRef,
        float   $amount,
        int     $requestedBy,
        string  $notes,
        ?int    $assignedTo = null
    ): int {
        // SLA: 24 hours from now
        $due = (new \DateTime())->modify('+24 hours')->format('Y-m-d H:i:s');

        return (int) $this->db->insert("
            INSERT INTO approvals
                (reference_type, reference_id, reference_ref, amount,
                 requested_by, assigned_to, status, approval_notes, due_by)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)
        ", [$referenceType, $referenceId, $referenceRef, $amount,
            $requestedBy, $assignedTo, $notes, $due]);
    }

    // ── Approve ──────────────────────────────────────────────────
    public function approve(int $approvalId, int $reviewerId, string $notes): bool
    {
        if (empty(trim($notes))) {
            throw new \InvalidArgumentException('Approval notes are mandatory.');
        }

        $rows = $this->db->execute("
            UPDATE approvals
            SET status         = 'approved',
                reviewed_by    = ?,
                reviewed_at    = NOW(),
                approval_notes = ?,
                updated_at     = NOW()
            WHERE id = ? AND status = 'pending'
        ", [$reviewerId, $notes, $approvalId]);

        return $rows > 0;
    }

    // ── Reject ───────────────────────────────────────────────────
    public function reject(int $approvalId, int $reviewerId, string $notes): bool
    {
        if (empty(trim($notes))) {
            throw new \InvalidArgumentException('Rejection notes are mandatory.');
        }

        $rows = $this->db->execute("
            UPDATE approvals
            SET status          = 'rejected',
                reviewed_by     = ?,
                reviewed_at     = NOW(),
                rejection_notes = ?,
                updated_at      = NOW()
            WHERE id = ? AND status = 'pending'
        ", [$reviewerId, $notes, $approvalId]);

        return $rows > 0;
    }

    // ── Cancel ───────────────────────────────────────────────────
    public function cancel(int $approvalId): bool
    {
        return $this->db->execute(
            "UPDATE approvals SET status='cancelled', updated_at=NOW() WHERE id=? AND status='pending'",
            [$approvalId]
        ) > 0;
    }

    // ── Get pending count for dashboard badge ────────────────────
    public function getPendingCount(?int $assignedTo = null): int
    {
        if ($assignedTo) {
            return (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM approvals WHERE status='pending' AND (assigned_to=? OR assigned_to IS NULL)",
                [$assignedTo]
            );
        }
        return (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM approvals WHERE status='pending'"
        );
    }

    // ── Paginated approval queue ─────────────────────────────────
    public function getQueue(int $page = 1, int $limit = 25, array $filters = []): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = 'a.status = ?';
            $params[] = $filters['status'];
        } else {
            $where[]  = "a.status = 'pending'";
        }

        if (!empty($filters['reference_type'])) {
            $where[]  = 'a.reference_type = ?';
            $params[] = $filters['reference_type'];
        }

        if (!empty($filters['assigned_to'])) {
            $where[]  = '(a.assigned_to = ? OR a.assigned_to IS NULL)';
            $params[] = $filters['assigned_to'];
        }

        $ws     = 'WHERE ' . implode(' AND ', $where);
        $offset = ($page - 1) * $limit;
        $total  = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM approvals a $ws", $params);

        $data = $this->db->fetchAll("
            SELECT a.*,
                   CONCAT(u.first_name,' ',u.last_name)  AS requested_by_name,
                   u.email                               AS requested_by_email,
                   CONCAT(ur.first_name,' ',ur.last_name) AS reviewed_by_name,
                   TIMESTAMPDIFF(HOUR, a.requested_at, NOW()) AS hours_waiting,
                   CASE WHEN a.due_by < NOW() AND a.status='pending' THEN 1 ELSE 0 END AS is_overdue
            FROM approvals a
            JOIN users u   ON u.id  = a.requested_by
            LEFT JOIN users ur ON ur.id = a.reviewed_by
            $ws ORDER BY a.requested_at ASC
            LIMIT $limit OFFSET $offset
        ", $params);

        return ['data' => $data, 'total' => $total, 'page' => $page,
                'per_page' => $limit, 'last_page' => (int)ceil($total / $limit),
                'from' => $total > 0 ? $offset + 1 : 0, 'to' => min($offset + $limit, $total)];
    }

    // ── Get approval for a specific record ───────────────────────
    public function getForRecord(string $type, int $referenceId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM approvals WHERE reference_type=? AND reference_id=? ORDER BY id DESC LIMIT 1",
            [$type, $referenceId]
        );
    }

    // ── Stats ────────────────────────────────────────────────────
    public function getStats(): array
    {
        return $this->db->fetchOne("
            SELECT
                SUM(status='pending')   AS pending,
                SUM(status='approved')  AS approved,
                SUM(status='rejected')  AS rejected,
                SUM(status='cancelled') AS cancelled,
                SUM(status='pending' AND due_by < NOW()) AS overdue,
                AVG(CASE WHEN status != 'pending'
                    THEN TIMESTAMPDIFF(HOUR, requested_at, reviewed_at) END) AS avg_hours_to_review
            FROM approvals
        ") ?? [];
    }
}