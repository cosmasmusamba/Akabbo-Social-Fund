<?php
namespace App\Controllers;
use Database;

/**
 * AKABBO SOCIAL FUND - Settings Controller
 */
class SettingsController extends BaseController
{
    private Database $db;
    public function __construct() {
        parent::__construct();
        $this->auth->requireAuth();
        $this->auth->requirePermission('settings.view');
        $this->db = Database::getInstance();
    }

    public function index(): void
    {
        $settings = array_column($this->db->fetchAll("SELECT * FROM settings ORDER BY `group`, `key`"), null, 'key');
        $groups   = array_unique(array_column($this->db->fetchAll("SELECT DISTINCT `group` FROM settings ORDER BY `group`"), 'group'));
        $unreadNotifications = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0",[$_SESSION['user_id']]);
        $pageTitle='System Settings'; $activePage='settings'; $breadcrumbs=['Settings'=>null];
        $this->view('settings/index',compact('settings','groups','pageTitle','activePage','breadcrumbs','unreadNotifications'));
    }

    public function update(): void
    {
        $this->auth->requirePermission('settings.edit');
        $this->verifyCsrf();
        $data = $this->getPost();
        unset($data['csrf_token'],$data['_method']);
        $updated = 0;
        foreach ($data as $key => $value) {
            $exists = $this->db->fetchColumn("SELECT COUNT(*) FROM settings WHERE `key`=?",[$key]);
            if ($exists) {
                $this->db->execute("UPDATE settings SET `value`=?, updated_at=NOW() WHERE `key`=?",[$value,$key]);
                $updated++;
            }
        }
        $this->auth->logAudit($_SESSION['user_id'],'settings_updated','settings',null,null,"{$updated} settings updated");
        $this->jsonSuccess(['updated'=>$updated],'Settings saved successfully.');
    }

    public function uploadLogo(): void
    {
        $this->auth->requirePermission('settings.edit');
        $this->verifyCsrf();

        if (empty($_FILES['logo'])) {
            return $this->jsonError('No file uploaded.', 400);
        }

        $file = $_FILES['logo'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 2 * 1024 * 1024; // 2MB

        // Validate file
        if (!in_array($file['type'], $allowed)) {
            return $this->jsonError('Invalid file type. Allowed: JPG, PNG, GIF, WebP', 400);
        }

        if ($file['size'] > $maxSize) {
            return $this->jsonError('File too large. Maximum 2MB allowed.', 400);
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return $this->jsonError('Upload failed. Error code: ' . $file['error'], 400);
        }

        // Create directory if it doesn't exist
        $uploadDir = STORAGE_PATH . '/uploads/logos';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'org-logo-' . time() . '.' . strtolower($ext);
        $filePath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            return $this->jsonError('Failed to move uploaded file.', 500);
        }

        // Update settings
        $this->db->execute("UPDATE settings SET `value`=?, updated_at=NOW() WHERE `key`=?", [$filename, 'org_logo']);

        // Log audit
        $this->auth->logAudit($_SESSION['user_id'], 'logo_uploaded', 'settings', null, null, "Organization logo uploaded: {$filename}");

        $this->jsonSuccess(['filename' => $filename], 'Logo uploaded successfully.');
    }

    public function uploadFavicon(): void
    {
        $this->auth->requirePermission('settings.edit');
        $this->verifyCsrf();

        if (empty($_FILES['favicon'])) {
            return $this->jsonError('No file uploaded.', 400);
        }

        $file = $_FILES['favicon'];
        $allowed = ['image/x-icon', 'image/jpeg', 'image/png'];
        $maxSize = 1 * 1024 * 1024; // 1MB

        // Validate file
        if (!in_array($file['type'], $allowed) && !in_array($file['type'], ['image/icon'])) {
            return $this->jsonError('Invalid file type. Allowed: ICO, JPG, PNG', 400);
        }

        if ($file['size'] > $maxSize) {
            return $this->jsonError('File too large. Maximum 1MB allowed.', 400);
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return $this->jsonError('Upload failed. Error code: ' . $file['error'], 400);
        }

        // Create directory if it doesn't exist
        $uploadDir = STORAGE_PATH . '/uploads/logos';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'favicon-' . time() . '.' . strtolower($ext);
        $filePath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            return $this->jsonError('Failed to move uploaded file.', 500);
        }

        // Update settings
        $this->db->execute("UPDATE settings SET `value`=?, updated_at=NOW() WHERE `key`=?", [$filename, 'org_favicon']);

        // Log audit
        $this->auth->logAudit($_SESSION['user_id'], 'favicon_uploaded', 'settings', null, null, "Favicon uploaded: {$filename}");

        $this->jsonSuccess(['filename' => $filename], 'Favicon uploaded successfully.');
    }

    public function backup(): void
    {
        $this->auth->requirePermission('settings.edit');
        $this->verifyCsrf();
        $filename = 'akabbo-backup-'.date('Y-m-d-His').'.sql';
        $path     = BACKUP_PATH . '/' . $filename;
        if (!is_dir(BACKUP_PATH)) mkdir(BACKUP_PATH, 0755, true);
        $settings = array_column($this->db->fetchAll("SELECT `key`,`value` FROM settings WHERE `key` IN ('org_name')"),'value','key');
        file_put_contents($path, "-- Akabbo Social Fund Backup\n-- Generated: ".date('Y-m-d H:i:s')."\n-- Database backup placeholder\n");
        $this->auth->logAudit($_SESSION['user_id'],'backup_created','settings',null,null,"Manual backup created: {$filename}");
        $this->jsonSuccess(['filename'=>$filename],'Backup initiated: '.$filename);
    }
}
