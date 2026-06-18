<?php
namespace App\Services;

use App\Models\Member;
use App\Models\Loan;
use Database;

/**
 * Centralized Reminder Service
 * Handles logic for finding users/members who need reminders and formats the payloads.
 */
class ReminderService
{
    private Database $db;
    private NotificationService $notif;
    private Member $memberModel;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->notif = new NotificationService();
        $this->memberModel = new Member();
    }

    /**
     * 1. KYC Pending Reminders
     * Notifies the member (if they have a user account) AND admins with 'members.view' permission.
     */
    public function sendKycReminders(int $daysSinceRegistration = 7): array
    {
        $members = $this->memberModel->getKycPendingMembers($daysSinceRegistration);
        $adminUsers = $this->getUsersWithPermission('members.view');
        $sentCount = 0;

        foreach ($members as $member) {
            $fullName = trim($member['first_name'] . ' ' . $member['last_name']);
            
            // Notify the member directly (if they have a linked user account)
            if (!empty($member['user_id'])) {
                $this->notif->sendToUser(
                    (int)$member['user_id'],
                    'kyc_reminder_personal',
                    'Action Required: Complete Your KYC',
                    "Dear {$fullName}, your account registration is pending KYC verification. Please visit our office or update your documents in the portal to activate full services."
                );
                $sentCount++;
            }

            // Notify admins
            foreach ($adminUsers as $admin) {
                $this->notif->sendToUser(
                    (int)$admin['id'],
                    'kyc_reminder_admin',
                    "KYC Pending: {$fullName} ({$member['member_no']})",
                    "Member {$fullName} has been registered for over {$daysSinceRegistration} days without KYC verification. Please follow up."
                );
                $sentCount++;
            }
        }
        return ['type' => 'kyc_reminder', 'sent' => $sentCount];
    }

    /**
     * 2. Overdue Loan Reminders
     */
    public function sendOverdueLoanReminders(): array
    {
        $members = $this->memberModel->getWithOverdueLoans();
        $adminUsers = $this->getUsersWithPermission('loans.view');
        $sentCount = 0;

        foreach ($members as $member) {
            $fullName = trim($member['full_name']);
            $overdueAmount = number_format((float)$member['overdue_amount'], 2);
            
            // Notify member
            if (!empty($member['user_id'])) {
                $this->notif->sendToUser(
                    (int)$member['user_id'],
                    'overdue_loan_reminder_personal',
                    'Payment Reminder: Overdue Loan Installment',
                    "Dear {$fullName}, you have {$member['overdue_installments']} overdue installment(s) totaling {$overdueAmount}. Please make a payment to avoid penalties."
                );
                $sentCount++;
            }

            // Notify loan officers/admins
            foreach ($adminUsers as $admin) {
                $this->notif->sendToUser(
                    (int)$admin['id'],
                    'overdue_loan_reminder_admin',
                    "Overdue Alert: {$fullName} ({$member['member_no']})",
                    "Member has {$member['overdue_installments']} overdue installment(s) totaling {$overdueAmount}. Follow up required."
                );
                $sentCount++;
            }
        }
        return ['type' => 'overdue_loan_reminder', 'sent' => $sentCount];
    }

    /**
     * 3. Account Inactivity Reminders
     */
    public function sendInactivityReminders(int $daysInactive = 30): array
    {
        $members = $this->memberModel->getInactiveMembers($daysInactive);
        $sentCount = 0;

        foreach ($members as $member) {
            $fullName = trim($member['first_name'] . ' ' . $member['last_name']);
            
            if (!empty($member['user_id'])) {
                $this->notif->sendToUser(
                    (int)$member['user_id'],
                    'inactivity_reminder_personal',
                    'We Miss You! Account Inactivity Alert',
                    "Dear {$fullName}, we noticed you haven't made any transactions in the last {$daysInactive} days. Visit us to learn about our latest savings and loan products!"
                );
                $sentCount++;
            }
        }
        return ['type' => 'inactivity_reminder', 'sent' => $sentCount];
    }

    /**
     * 4. Manual Custom Reminder (Triggered from UI)
     */
    public function sendCustomReminder(array $userIds, string $type, string $title, string $message): int
    {
        $sentCount = 0;
        foreach ($userIds as $userId) {
            $this->notif->sendToUser((int)$userId, $type, $title, $message);
            $sentCount++;
        }
        return $sentCount;
    }

    /**
     * Helper: Get all active users with a specific permission slug
     */
    private function getUsersWithPermission(string $permSlug): array
    {
        return $this->db->fetchAll(
            "SELECT DISTINCT u.id, u.first_name, u.last_name
            FROM users u
            JOIN role_permissions rp ON rp.role_id = u.role_id
            JOIN permissions p ON p.id = rp.permission_id
            WHERE p.slug = ? AND u.status = 'active'",
            [$permSlug]
        );
    }
}