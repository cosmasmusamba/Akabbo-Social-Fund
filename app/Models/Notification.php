<?php
namespace App\Models;

/**
 * AKABBO SOCIAL FUND - Notification Model
 * 
 * Handles permission-filtered notification retrieval, read states, and broadcasting.
 * Uses session-based permission checks for blazing-fast, zero-DB-hit evaluation.
 */
class Notification extends BaseModel
{
    protected string $table = 'notifications';

    /**
     * CENTRAL MAPPING: Maps notification 'type' to the required permission 'slug'.
     * 
     * - If value is NULL, it is visible to ALL authenticated users (e.g., broadcasts).
     * - If value is a string, the user MUST have that permission to see it.
     * 
     * ⚠️ NOTE: Ensure the keys (left side) exactly match the 'type' strings 
     * inserted into the database by your NotificationService or controllers.
     */
    private const TYPE_PERMISSION_MAP = [
        // Universal
        'broadcast' => null, 

        // Expense Workflow
        'expense_approval_request' => 'expenses.approve',
        'expense_approved'         => 'expenses.view',
        'expense_rejected'         => 'expenses.view',
        'expense_paid'             => 'expenses.view',

        // Fund Transfer Workflow
        'transfer_approval_request' => 'transfers.view', 
        'transfer_completed'        => 'transfers.view',
        'transfer_reversed'         => 'transfers.view',

        // Withdrawal Workflow (Savings)
        'withdrawal_approval_request' => 'savings.withdraw', 
        'withdrawal_approved'         => 'savings.view',
        'withdrawal_rejected'         => 'savings.view',

        // Loan / Disbursement Workflow
        'loan_approval_request'      => 'loans.approve',
        'disbursement_approval_request' => 'loans.approve',
        'loan_approved'              => 'loans.view',
        'loan_rejected'              => 'loans.view',
        'loan_disbursed'             => 'loans.view',
        'loan_repayment'             => 'loans.view',

        // Share Transaction Workflow
        'share_approval_request'     => 'shares.approve',
        'share_approved'             => 'shares.view',
        'share_rejected'             => 'shares.view',

        // Social Fund Workflow
        'social_fund_waiver_request' => 'approvals.process',
        'social_fund_overdue'        => 'social_fund.view',

        // General Transaction Workflow
        'transaction_approval_request' => 'transactions.approve',
        'transaction_reversed'         => 'transactions.reverse',

        // Reminder Types
        'kyc_reminder_personal'          => null, 
        'overdue_loan_reminder_personal' => null,
        'inactivity_reminder_personal'   => null,
        
        // Admin reminders require specific permissions to view
        'kyc_reminder_admin'             => 'members.view',
        'overdue_loan_reminder_admin'    => 'loans.view',

        // Member & User Lifecycle
        'kyc_verified'             => null,
        'member_profile_updated'   => null,
        'member_terminated'        => null,
        'user_profile_updated'     => null,
        'user_terminated'          => null,
        'added_to_group'           => null,

        // Transaction Success/Failure
        'deposit_success'          => null,
        'withdrawal_approved'      => 'savings.view',
        'withdrawal_rejected'      => 'savings.view',
        'transfer_approved'        => 'transfers.view',
        'transfer_rejected'        => 'transfers.view',
        'share_approved'           => 'shares.view',
        'share_rejected'           => 'shares.view',

        // Group Lifecycle
        'added_to_group'       => null,
        'removed_from_group'   => null,
    ];

    // ── Permission Evaluation ───────────────────────────────────────

    /**
     * Get the list of notification types the current user is allowed to see.
     * Uses the session permissions array for blazing-fast, zero-DB-check evaluation.
     */
    public function getAllowedTypes(): array
    {
        // Super Admins see everything
        if (!empty($_SESSION['user']['is_super_admin'])) {
            return array_keys(self::TYPE_PERMISSION_MAP);
        }

        $userPerms = $_SESSION['permissions'] ?? [];
        $allowedTypes = [];

        foreach (self::TYPE_PERMISSION_MAP as $type => $requiredPerm) {
            // If no permission is required (null), or the user has the permission
            if ($requiredPerm === null || in_array($requiredPerm, $userPerms, true)) {
                $allowedTypes[] = $type;
            }
        }

        return $allowedTypes;
    }

    // ── Read States & Counts (Permission-Filtered) ──────────────────

    /**
     * Get the unread count for the Topbar Bell (Permission-filtered)
     */
    public function getUnreadCount(int $userId): int
    {
        $allowedTypes = $this->getAllowedTypes();
        if (empty($allowedTypes)) return 0;

        $placeholders = implode(',', array_fill(0, count($allowedTypes), '?'));
        
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM notifications 
             WHERE user_id = ? AND is_read = 0 AND type IN ($placeholders)",
            array_merge([$userId], $allowedTypes)
        );
    }

    /**
     * Get total count for pagination (Permission-filtered)
     */
    public function getTotalCount(int $userId): int
    {
        $allowedTypes = $this->getAllowedTypes();
        if (empty($allowedTypes)) return 0;

        $placeholders = implode(',', array_fill(0, count($allowedTypes), '?'));
        
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM notifications 
             WHERE user_id = ? AND type IN ($placeholders)",
            array_merge([$userId], $allowedTypes)
        );
    }

    /**
     * Get paginated notifications for the Inbox (Permission-filtered)
     */
    public function getForUser(int $userId, int $limit, int $offset): array
    {
        $allowedTypes = $this->getAllowedTypes();
        if (empty($allowedTypes)) return [];

        $placeholders = implode(',', array_fill(0, count($allowedTypes), '?'));
        $params = array_merge([$userId], $allowedTypes, [$limit, $offset]);

        return $this->db->fetchAll(
            "SELECT * FROM notifications 
             WHERE user_id = ? AND type IN ($placeholders) 
             ORDER BY created_at DESC 
             LIMIT ? OFFSET ?",
            $params
        );
    }

    // ── DRY Database Operations ─────────────────────────────────────

    /**
     * Mark a specific notification as read for a user.
     */
    public function markAsRead(int $id, int $userId): void
    {
        $this->db->execute(
            "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?", 
            [$id, $userId]
        );
    }

    /**
     * Mark all unread notifications as read for a user.
     */
    public function markAllAsRead(int $userId): void
    {
        $this->db->execute(
            "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0", 
            [$userId]
        );
    }

    /**
     * Send a broadcast notification to all active users or a specific role.
     * Returns the number of users who received the notification.
     */
    public function broadcast(string $title, string $message, string $target = 'all'): int
    {
        if ($target === 'all') {
            $users = $this->db->fetchAll("SELECT id FROM users WHERE status = 'active'");
        } else {
            $users = $this->db->fetchAll(
                "SELECT u.id FROM users u 
                 JOIN roles r ON r.id = u.role_id 
                 WHERE r.slug = ? AND u.status = 'active'", 
                [$target]
            );
        }

        $count = 0;
        foreach ($users as $u) {
            $this->db->execute(
                "INSERT INTO notifications (user_id, type, title, message, channel, send_status, sent_at, created_at) 
                 VALUES (?, 'broadcast', ?, ?, 'system', 'sent', NOW(), NOW())",
                [$u['id'], $title, $message]
            );
            $count++;
        }
        
        return $count;
    }
}