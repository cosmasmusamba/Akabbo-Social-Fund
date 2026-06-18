<?php
namespace App\Services;

use App\Services\Channels\EmailChannel;
use App\Services\Channels\SmsChannel;
use Database;

class NotificationService
{
    private Database $db;

    private const APPROVAL_MAP = [
        'expense' => [
            'request_type' => 'expense_approval_request', 'approve_type' => 'expense_approved',
            'reject_type'  => 'expense_rejected', 'perm' => 'expenses.approve', 'label' => 'Expense'
        ],
        'transfer' => [
            'request_type' => 'transfer_approval_request', 'approve_type' => 'transfer_completed',
            'reject_type'  => 'transfer_rejected', 'perm' => 'transfers.view', 'label' => 'Fund Transfer'
        ],
        'withdrawal' => [
            'request_type' => 'withdrawal_approval_request', 'approve_type' => 'withdrawal_approved',
            'reject_type'  => 'withdrawal_rejected', 'perm' => 'savings.withdraw', 'label' => 'Withdrawal'
        ],
        'disbursement' => [
            'request_type' => 'loan_approval_request', 'approve_type' => 'loan_approved',
            'reject_type'  => 'loan_rejected', 'perm' => 'loans.approve', 'label' => 'Loan Disbursement'
        ],
        'share_transaction' => [
            'request_type' => 'share_approval_request', 'approve_type' => 'share_approved',
            'reject_type'  => 'share_rejected', 'perm' => 'shares.approve', 'label' => 'Share Transaction'
        ],
        'social_fund_waiver' => [
            'request_type' => 'social_fund_waiver_request', 'approve_type' => 'social_fund_waiver_approved',
            'reject_type'  => 'social_fund_waiver_rejected', 'perm' => 'approvals.process', 'label' => 'Social Fund Waiver'
        ],
        'loan_application' => [
            'request_type' => 'loan_approval_request', 'approve_type' => 'loan_approved',
            'reject_type'  => 'loan_rejected', 'perm' => 'loans.approve', 'label' => 'Loan Application'
        ]
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function notifyApprovers(string $referenceType, string $referenceRef, float $amount, string $notes): void
    {
        $config = self::APPROVAL_MAP[$referenceType] ?? null;
        if (!$config) return;

        $title = "New {$config['label']} Approval Request";
        $message = "A new {$config['label']} request ({$referenceRef}) for " . number_format($amount, 2) . " requires your approval.\nNotes: {$notes}";

        $users = $this->db->fetchAll(
            "SELECT DISTINCT u.id FROM users u 
             JOIN role_permissions rp ON rp.role_id = u.role_id 
             JOIN permissions p ON p.id = rp.permission_id 
             WHERE p.slug = ? AND u.status = 'active'",
            [$config['perm']]
        );

        foreach ($users as $user) {
            // FIX: Use dispatch to trigger System + Email + SMS
            $this->dispatch($user['id'], $config['request_type'], $title, $message);
        }
    }

    public function notifyRequester(int $requesterId, string $referenceType, string $referenceRef, string $action, string $notes): void
    {
        $config = self::APPROVAL_MAP[$referenceType] ?? null;
        if (!$config) return;

        $type = $action === 'approved' ? $config['approve_type'] : $config['reject_type'];
        $title = ucfirst($config['label']) . " " . ucfirst($action);
        $message = "Your {$config['label']} request ({$referenceRef}) has been {$action}.\nReviewer Notes: {$notes}";

        // FIX: Use dispatch to trigger System + Email + SMS
        $this->dispatch($requesterId, $type, $title, $message);
    }

    /**
     * Core DB-only insertion. Use this ONLY if you explicitly want to bypass Email/SMS.
     */
    public function sendToUser(int $userId, string $type, string $title, string $message, string $channel = 'system'): void
    {
        $this->db->execute(
            "INSERT INTO notifications (user_id, type, title, message, channel, send_status, sent_at, created_at)
             VALUES (?, ?, ?, ?, ?, 'sent', NOW(), NOW())",
            [$userId, $type, $title, $message, $channel]
        );
    }

    public function broadcast(string $title, string $message, string $channel = 'system'): int
    {
        $users = $this->db->fetchAll("SELECT id FROM users WHERE status = 'active'");
        $count = 0;
        foreach ($users as $user) {
            // FIX: Use dispatch for broadcasts so users get SMS/Email alerts
            $this->dispatch($user['id'], 'broadcast', $title, $message);
            $count++;
        }
        return $count;
    }

    /**
     * MULTI-CHANNEL DISPATCH: Saves to DB, then triggers Email/SMS in the background.
     */
    public function dispatch(int $userId, string $type, string $title, string $message): void
    {
        // 1. Always save to the in-system inbox (Database)
        $this->sendToUser($userId, $type, $title, $message, 'system');

        // 2. Dispatch to external channels (Failures here shouldn't break the app)
        try {
            $settings = $this->db->fetchAll("SELECT `key`, `value` FROM settings WHERE `key` IN ('enable_email_notifications', 'enable_sms_notifications')");
            $settingsMap = array_column($settings, 'value', 'key');
            
            $enableEmail = ($settingsMap['enable_email_notifications'] ?? 'false') === 'true';
            $enableSms   = ($settingsMap['enable_sms_notifications'] ?? 'false') === 'true';

            if ($enableEmail && class_exists(EmailChannel::class)) {
                (new EmailChannel())->send($userId, $title, $message);
            }
            
            if ($enableSms && class_exists(SmsChannel::class)) {
                $smsMessage = \App\Helpers\Format::truncate($message, 140, '...'); 
                (new SmsChannel())->send($userId, $title, $smsMessage);
            }
        } catch (\Exception $e) {
            error_log("[AKABBO NOTIF DISPATCH ERROR] " . $e->getMessage());
        }
    }
}