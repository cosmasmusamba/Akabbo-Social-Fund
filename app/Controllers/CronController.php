<?php
namespace App\Controllers;

use App\Services\ReminderService;
use App\Services\NotificationService;

/**
 * Cron Controller
 * Handles automated background tasks. 
 * SECURITY: Should only be accessible via CLI or protected by a secret token.
 * 
 * NOTE: Data scoping (Global/Group/Personal) does not apply here as cron jobs 
 * operate globally across the entire system by design.
 */
class CronController extends BaseController
{
    private ReminderService $reminderService;
    private NotificationService $notif;

    public function __construct()
    {
        parent::__construct();
        $this->reminderService = new ReminderService();
        $this->notif = new NotificationService();
        
        // ENFORCE: Secure the endpoint before any logic runs
        $this->secureCronRequest();
    }

    /**
     * Run all daily reminders
     * Usage: php public/index.php cron run-daily  OR  curl https://yoursite.com/cron/run-daily?secret=YOUR_CRON_SECRET
     */
    public function runDaily(): void
    {
        $startTime = microtime(true);
        $results = [];

        try {
            // 1. KYC Reminders (Members registered > 7 days ago without KYC)
            $results['kyc'] = $this->reminderService->sendKycReminders(7);

            // 2. Overdue Loan Reminders
            $results['overdue_loans'] = $this->reminderService->sendOverdueLoanReminders();

            // 3. Inactivity Reminders (No transactions in 30 days)
            $results['inactivity'] = $this->reminderService->sendInactivityReminders(30);

            $executionTime = round(microtime(true) - $startTime, 2);
            
            // ENFORCE: Audit Log for Success
            $this->logAudit('cron_daily_success', 'system', null, 'Cron', 
                "Daily cron executed successfully in {$executionTime}s", null, json_encode($results));

            // ENFORCE: Notify System Admins of Success via multi-channel dispatch
            $summaryParts = [];
            foreach ($results as $type => $res) {
                $sent = $res['sent'] ?? 0;
                $failed = $res['failed'] ?? 0;
                $summaryParts[] = ucfirst(str_replace('_', ' ', $type)) . ": {$sent} sent, {$failed} failed";
            }
            $summary = implode('. ', $summaryParts);
            
            $this->notifySystemAdmins('cron_daily_success', 'Daily Cron Executed Successfully', 
                "The daily automated reminders completed in {$executionTime} seconds. {$summary}");

            // Returns clean JSON (Frontend can use modals if triggered via UI)
            $this->jsonSuccess(['results' => $results, 'execution_time' => $executionTime], 'Daily cron reminders executed successfully.');
            
        } catch (\Exception $e) {
            $executionTime = round(microtime(true) - $startTime, 2);
            
            // ENFORCE: Audit Log for Failure
            $this->logAudit('cron_daily_failed', 'system', null, 'Cron', 
                "Daily cron failed after {$executionTime}s: " . $e->getMessage(), null, json_encode($results));

            // ENFORCE: Notify System Admins of Failure via multi-channel dispatch
            $this->notifySystemAdmins('cron_daily_failed', 'CRITICAL: Daily Cron Failed', 
                "The daily automated reminders failed after {$executionTime} seconds. Error: " . $e->getMessage());

            $this->jsonError('Cron execution failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Security check to prevent public web access to cron endpoints.
     * Uses hash_equals to prevent timing attacks.
     */
    private function secureCronRequest(): void
    {
        // 1. Allow if running from CLI
        if (php_sapi_name() === 'cli') {
            return;
        }

        // 2. Check for valid secret token in query string or header
        $secret = $_GET['secret'] ?? $_SERVER['HTTP_X_CRON_SECRET'] ?? '';
        $expectedSecret = getenv('CRON_SECRET') ?: 'your_super_secret_cron_key_change_this';

        // ENFORCE: Use hash_equals to prevent timing attacks
        if (empty($secret) || !hash_equals($expectedSecret, $secret)) {
            // ENFORCE: Audit log unauthorized access attempts
            $this->logAudit('cron_unauthorized_access', 'system', null, 'Cron', 
                'Unauthorized attempt to access cron endpoint from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Forbidden: Invalid cron secret.']);
            exit;
        }
    }

    /**
     * ENFORCE: Notify System Admins (Super Admins) via multi-channel dispatch
     */
    private function notifySystemAdmins(string $type, string $title, string $message): void
    {
        // Target super admins specifically for system-level cron alerts
        $admins = $this->db->fetchAll("
            SELECT DISTINCT u.id 
            FROM users u 
            JOIN roles r ON r.id = u.role_id 
            WHERE r.slug = 'super_admin' AND u.status = 'active'
        ");
        
        // Fallback to users with settings.edit if no super admins exist
        if (empty($admins)) {
            $admins = $this->db->fetchAll("
                SELECT DISTINCT u.id 
                FROM users u 
                JOIN role_permissions rp ON rp.role_id = u.role_id 
                JOIN permissions p ON p.id = rp.permission_id 
                WHERE p.slug = 'settings.edit' AND u.status = 'active'
            ");
        }

        foreach ($admins as $admin) {
            // USE dispatch instead of sendToUser for multi-channel (System + Email + SMS)
            $this->notif->dispatch((int)$admin['id'], $type, $title, $message);
        }
    }
}