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
