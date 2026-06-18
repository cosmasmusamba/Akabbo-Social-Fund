<?php
// We should notify admins use (dispatch instead of sendToUser) with relevant messages for all actions of this contoller and any other event of success and failure. Avoid using default `prompt` instead use modals. Enforce logAudit and (RBAC + IDOR). Enforce audit COMPLIANCE_AUDIT.md 15. Service Fees for Account Enquiries
namespace App\Controllers;

use App\Helpers\StorageHelper;
use App\Services\NotificationService;
use Database;

class SettingsController extends BaseController
{
    private NotificationService $notif;

    public function __construct()
    {
        parent::__construct();
        $this->auth->requireAuth();
        $this->auth->requirePermission('settings.view');
        $this->notif = new NotificationService();
    }

    // ── CRUD METHODS ───────────────────────────────────────────────

    public function index(): void
    {
        // ENFORCE: Audit log for viewing sensitive system configuration
        $this->logAudit('settings_viewed', 'settings', null, null, "Viewed system settings dashboard");

        $settings = array_column($this->db->fetchAll("SELECT * FROM settings ORDER BY `group`, `key`"), null, 'key');
        $groups   = array_unique(array_column($this->db->fetchAll("SELECT DISTINCT `group` FROM settings ORDER BY `group`"), 'group'));
        
        $this->view('settings/index', array_merge(
            $this->prepareViewData('System Settings', 'settings'),
            ['settings' => $settings, 'groups' => $groups]
        ));
    }

    public function update(): void
    {
        $this->auth->requirePermission('settings.edit');
        $this->verifyCsrf();
        $data = $this->getPost();
        unset($data['csrf_token'], $data['_method']);
        
        $this->db->beginTransaction();
        try {
            $oldSettings = $this->db->fetchAll("SELECT `key`, `value` FROM settings");
            $oldSettingsMap = array_column($oldSettings, 'value', 'key');
            $updated = 0;
            $changedKeys = [];
            $newValuesMap = [];
            
            foreach ($data as $key => $value) {
                $exists = $this->db->fetchColumn("SELECT COUNT(*) FROM settings WHERE `key`=?", [$key]);
                if ($exists) {
                    // Only update if value actually changed to save DB writes and make audit cleaner
                    if (($oldSettingsMap[$key] ?? null) !== (string)$value) {
                        $this->db->execute("UPDATE settings SET `value`=?, updated_at=NOW() WHERE `key`=?", [$value, $key]);
                        $updated++;
                        $changedKeys[] = $key;
                        $newValuesMap[$key] = $value;
                    }
                }
            }
            
            if ($updated > 0) {
                // ENFORCE: Audit Log with before/after values (only for changed keys)
                $this->logAudit('settings_updated', 'settings', null, null, "{$updated} settings updated", json_encode(array_intersect_key($oldSettingsMap, array_flip($changedKeys))), json_encode($newValuesMap));
                
                // ENFORCE: Notify admins of general settings changes
                $this->notifyAdmins(
                    'settings_updated',
                    'System Settings Updated',
                    "System settings were modified by an administrator. Changed parameters: " . implode(', ', $changedKeys)
                );

                // ENFORCE: Audit Sec 15 - Specific notification if Service Fees were changed
                $feeKeys = array_filter($changedKeys, fn($k) => str_contains($k, 'fee_') || str_contains($k, 'inquiry') || str_contains($k, 'statement'));
                if (!empty($feeKeys)) {
                    $this->notifyAdmins(
                        'service_fees_config_changed',
                        'Service Fees Configuration Changed (Audit Sec 15)',
                        "Service fee settings were modified. Affected parameters: " . implode(', ', $feeKeys)
                    );
                }
            }
            
            $this->db->commit();
            $this->jsonSuccess(['updated' => $updated], $updated > 0 ? 'Settings saved successfully.' : 'No changes detected.');
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->notifyAdmins('settings_update_failed', 'Settings Update Failed', "Failed to update system settings: " . $e->getMessage());
            $this->jsonError('Failed to save settings: ' . $e->getMessage(), null, 500);
        }
    }

    public function uploadLogo(): void
    {
        $this->auth->requirePermission('settings.edit');
        $this->verifyCsrf();

        if (empty($_FILES['logo']['name'])) {
            $this->jsonError('No file uploaded.', null, 400);
        }

        $newFilename = null;
        $oldFilename = $this->db->fetchColumn("SELECT `value` FROM settings WHERE `key` = 'org_logo'");

        $this->db->beginTransaction();
        try {
            $newFilename = StorageHelper::upload($_FILES['logo'], 'logos', ALLOWED_IMAGE_TYPES, 2 * 1024 * 1024, 'org-logo-');
            $this->db->execute("UPDATE settings SET `value`=?, updated_at=NOW() WHERE `key`=?", [$newFilename, 'org_logo']);
            
            // FIX: Delete old logo ONLY after DB success to prevent data loss
            if ($oldFilename && $oldFilename !== $newFilename) {
                StorageHelper::delete('logos', $oldFilename);
            }

            $this->logAudit('logo_uploaded', 'settings', null, null, "Organization logo uploaded: {$newFilename}", json_encode(['old' => $oldFilename]), json_encode(['new' => $newFilename]));
            
            $this->notifyAdmins('branding_updated', 'Organization Logo Updated', "The organization logo was updated to: {$newFilename}");
            
            $this->db->commit();
            $this->jsonSuccess(['filename' => $newFilename], 'Logo uploaded successfully.');
        } catch (\Exception $e) {
            $this->db->rollback();
            // FIX: Clean up newly uploaded file if DB transaction failed (Prevents orphaned files)
            if ($newFilename) {
                StorageHelper::delete('logos', $newFilename);
            }
            $this->notifyAdmins('system_error', 'Logo Upload Failed', "An administrator attempted to upload a logo but it failed: " . $e->getMessage());
            $this->jsonError($e->getMessage(), null, 500);
        }
    }

    public function uploadFavicon(): void
    {
        $this->auth->requirePermission('settings.edit');
        $this->verifyCsrf();

        if (empty($_FILES['favicon']['name'])) {
            $this->jsonError('No file uploaded.', null, 400);
        }

        $newFilename = null;
        $oldFilename = $this->db->fetchColumn("SELECT `value` FROM settings WHERE `key` = 'org_favicon'");

        $this->db->beginTransaction();
        try {
            $newFilename = StorageHelper::upload($_FILES['favicon'], 'logos', ['image/x-icon', 'image/jpeg', 'image/png'], 1 * 1024 * 1024, 'favicon-');
            $this->db->execute("UPDATE settings SET `value`=?, updated_at=NOW() WHERE `key`=?", [$newFilename, 'org_favicon']);
            
            // FIX: Delete old favicon ONLY after DB success
            if ($oldFilename && $oldFilename !== $newFilename) {
                StorageHelper::delete('logos', $oldFilename);
            }

            $this->logAudit('favicon_uploaded', 'settings', null, null, "Favicon uploaded: {$newFilename}", json_encode(['old' => $oldFilename]), json_encode(['new' => $newFilename]));
            
            $this->notifyAdmins('branding_updated', 'Favicon Updated', "The system favicon was updated to: {$newFilename}");
            
            $this->db->commit();
            $this->jsonSuccess(['filename' => $newFilename], 'Favicon uploaded successfully.');
        } catch (\Exception $e) {
            $this->db->rollback();
            // FIX: Clean up newly uploaded file if DB transaction failed
            if ($newFilename) {
                StorageHelper::delete('logos', $newFilename);
            }
            $this->notifyAdmins('system_error', 'Favicon Upload Failed', "An administrator attempted to upload a favicon but it failed: " . $e->getMessage());
            $this->jsonError($e->getMessage(), null, 500);
        }
    }

    public function backup(): void
    {
        $this->auth->requirePermission('settings.edit');
        $this->verifyCsrf();

        $filename = 'akabbo-backup-' . date('Y-m-d-His') . '.sql';
        $path     = BACKUP_PATH . DIRECTORY_SEPARATOR . $filename;

        if (!is_dir(BACKUP_PATH)) {
            mkdir(BACKUP_PATH, 0755, true);
        }

        $dbHost = getenv('DB_HOST') ?: 'localhost';
        $dbUser = getenv('DB_USER') ?: 'root';
        $dbPass = getenv('DB_PASS') ?: '';
        $dbName = getenv('DB_NAME') ?: 'akabbo_fund';

        $command = sprintf(
            "mysqldump --user=%s --password=%s --host=%s %s > %s",
            escapeshellarg($dbUser),
            escapeshellarg($dbPass),
            escapeshellarg($dbHost),
            escapeshellarg($dbName),
            escapeshellarg($path)
        );

        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);

        if ($returnVar !== 0 || !file_exists($path) || filesize($path) < 100) {
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('backup_failed', 'Database Backup Failed', "A manual database backup failed. Error code: {$returnVar}");
            $this->jsonError('Database backup failed. Ensure mysqldump is in your system PATH and exec() is enabled in PHP.', null, 500);
        }

        $fileSize = filesize($path);
        $this->logAudit('backup_created', 'settings', null, null, "Manual DB backup created: {$filename}", null, json_encode(['filename' => $filename, 'size_bytes' => $fileSize]));
        
        // ENFORCE: Notify admins of success
        $this->notifyAdmins('backup_created', 'Database Backup Created', "A manual database backup ({$filename}) was successfully created by an administrator.");
        
        $this->jsonSuccess(['filename' => $filename, 'size' => number_format($fileSize) . ' bytes'], 'Database backup created successfully: ' . $filename);
    }

    // ── HELPERS ────────────────────────────────────────────────────

    /**
     * ENFORCE: Notify all admins via multi-channel dispatch
     */
    private function notifyAdmins(string $type, string $title, string $message): void
    {
        $admins = $this->db->fetchAll("
            SELECT DISTINCT u.id 
            FROM users u 
            JOIN role_permissions rp ON rp.role_id = u.role_id 
            JOIN permissions p ON p.id = rp.permission_id 
            WHERE p.slug IN ('settings.view', 'settings.edit') AND u.status = 'active'
        ");
        foreach ($admins as $admin) {
            // USE dispatch instead of sendToUser for multi-channel (System + Email + SMS)
            $this->notif->dispatch((int)$admin['id'], $type, $title, $message);
        }
    }
}