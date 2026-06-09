<?php
namespace App\Controllers;
use Database;

/**
 * AKABBO SOCIAL FUND - Notification Controller
 */
class NotificationController extends BaseController
{
    private Database $db;
    public function __construct() {
        parent::__construct();
        $this->auth->requireAuth();
        $this->db = Database::getInstance();
    }

    public function index(): void
    {
        ['page'=>$page,'limit'=>$limit] = $this->getPaginationParams();
        $uid    = $_SESSION['user_id'];
        $offset = ($page-1)*$limit;
        $total  = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM notifications WHERE user_id=?",[$uid]);
        $data   = $this->db->fetchAll("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}",[$uid]);
        $result = ['data'=>$data,'total'=>$total,'page'=>$page,'per_page'=>$limit,'last_page'=>(int)ceil($total/$limit)];
        $unreadNotifications = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0",[$uid]);
        $settings = array_column($this->db->fetchAll("SELECT `key`,`value` FROM settings"),'value','key');
        $pageTitle='Notifications'; $activePage='notifications'; $breadcrumbs=['Notifications'=>null];
        $this->view('notifications/index', compact('result','unreadNotifications','settings','pageTitle','activePage','breadcrumbs'));
    }

    public function markRead(): void
    {
        $this->verifyCsrf();
        $id = (int)($this->getPost()['id'] ?? 0);
        $this->db->execute("UPDATE notifications SET is_read=1, read_at=NOW() WHERE id=? AND user_id=?",[$id,$_SESSION['user_id']]);
        $this->jsonSuccess(null,'Notification marked as read.');
    }

    public function markAllRead(): void
    {
        $this->verifyCsrf();
        $this->db->execute("UPDATE notifications SET is_read=1, read_at=NOW() WHERE user_id=? AND is_read=0",[$_SESSION['user_id']]);
        $this->jsonSuccess(null,'All notifications marked as read.');
    }

    public function send(): void
    {
        $this->auth->requirePermission('users.edit');
        $this->verifyCsrf();
        $data = $this->getPost(['title','message','target']);
        if (empty($data['title'])||empty($data['message'])) $this->jsonError('Title and message required.',null,422);
        $target = $data['target'] ?? 'all';
        $users  = $target === 'all'
            ? $this->db->fetchAll("SELECT id FROM users WHERE status='active'")
            : $this->db->fetchAll("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug=? AND u.status='active'",[$target]);
        foreach ($users as $u) {
            $this->db->execute("INSERT INTO notifications (user_id,type,title,message,channel) VALUES (?,'broadcast',?,?,'system')",
                [$u['id'],$data['title'],$data['message']]);
        }
        $this->jsonSuccess(['sent'=>count($users)],'Notification sent to '.count($users).' user(s).');
    }
}
