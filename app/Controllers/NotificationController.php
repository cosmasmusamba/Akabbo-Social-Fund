<?php
namespace App\Controllers;

use App\Models\Notification;
use App\Services\NotificationService;

/**
 * AKABBO SOCIAL FUND - Notification Controller
 * Handles notification inbox, read states, and broadcasting.
 */
class NotificationController extends BaseController
{
    private Notification $model;
    private NotificationService $notif;

    public function __construct()
    {
        parent::__construct();
        $this->auth->requireAuth();
        $this->model = new Notification();
        $this->notif = new NotificationService();
    }

    // ── INBOX & VIEWING ────────────────────────────────────────────

    /**
     * Display the user's permission-filtered notification inbox.
     */
    public function index(): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        // Notifications are inherently personal, but global admins can view all system broadcasts.
        $perms = ['global' => 'notifications.view_all', 'group' => 'groups.view_members', 'personal' => 'notifications.view'];
        $this->requireScopeAccess($perms, 'You do not have permission to view notifications.');
        
        $scope = $this->resolveDataScope($perms);
        
        ['page' => $page, 'limit' => $limit] = $this->getPaginationParams();
        $uid    = $_SESSION['user_id'];
        $offset = ($page - 1) * $limit;

        if ($scope['type'] === 'none') {
            $result = ['data' => [], 'total' => 0, 'page' => $page, 'per_page' => $limit, 'last_page' => 0, 'from' => 0, 'to' => 0];
        } else {
            // If global scope, pass null to model (assuming model handles null as "all users")
            // If personal/group scope, pass the specific user ID.
            $targetUid = ($scope['type'] === 'global') ? null : $uid;

            // Model handles the complex permission-filtered SQL
            $total = $this->model->getTotalCount($targetUid);
            $data  = $this->model->getForUser($targetUid, $limit, $offset);

            $result = [
                'data'      => $data,
                'total'     => $total,
                'page'      => $page,
                'per_page'  => $limit,
                'last_page' => (int)ceil($total / $limit),
                'from'      => $total > 0 ? $offset + 1 : 0,
                'to'        => min($offset + $limit, $total)
            ];
        }

        // ENFORCE: Audit log for viewing inbox
        $this->logAudit('notifications_viewed', 'notifications', null, 'Notification', "Viewed notification inbox (Page {$page})");

        $this->view('notifications/index', array_merge(
            $this->prepareViewData('Notifications', 'notifications'),
            ['result' => $result]
        ));
    }

    // ── STATE MUTATIONS ────────────────────────────────────────────

    /**
     * Mark a single notification as read.
     */
    public function markRead(): void
    {
        $this->verifyCsrf();
        $id = (int)($this->getPost()['id'] ?? 0);
        
        if ($id <= 0) {
            $this->jsonError('Invalid notification ID.', null, 400);
        }

        // ENFORCE: IDOR Guard
        $notification = $this->db->fetchOne("SELECT user_id FROM notifications WHERE id = ?", [$id]);
        if (!$notification) {
            $this->jsonError('Notification not found.', null, 404);
        }

        $isOwner = (int)$notification['user_id'] === (int)$_SESSION['user_id'];
        $hasGlobal = $this->auth->can('notifications.view_all');

        if (!$isOwner && !$hasGlobal) {
            $this->jsonError('You do not have permission to modify this notification.', null, 403);
        }

        try {
            // Delegate to model
            $this->model->markAsRead($id, $_SESSION['user_id']);
            
            $this->logAudit('notification_marked_read', 'notifications', $id, 'Notification', "Notification {$id} marked as read");
            
            // ENFORCE: Notify relevant parties
            if (!$isOwner && $hasGlobal) {
                // Notify the user that an admin touched their notification
                $this->notif->dispatch((int)$notification['user_id'], 'notification_marked_read_by_admin', 'Notification Marked Read', 
                    "A system administrator has marked one of your notifications as read.");
                
                // Notify admins of the action
                $this->notifyAdmins('notification_marked_read_by_admin', 'Notification Marked Read by Admin', 
                    "Admin marked notification {$id} as read on behalf of user ID {$notification['user_id']}.");
            }

            $this->jsonSuccess(null, 'Notification marked as read.');
        } catch (\Exception $e) {
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('notification_mark_read_failed', 'Notification Update Failed', 
                "Failed to mark notification {$id} as read: " . $e->getMessage());
            $this->jsonError('Failed to mark notification as read.', null, 500);
        }
    }

    /**
     * Mark all notifications as read for the current user.
     */
    public function markAllRead(): void
    {
        $this->verifyCsrf();
        
        try {
            // Delegate to model
            $this->model->markAllAsRead($_SESSION['user_id']);
            
            $this->logAudit('all_notifications_marked_read', 'notifications', null, 'Notification', "All notifications marked as read for user ID {$_SESSION['user_id']}");
            
            $this->jsonSuccess(null, 'All notifications marked as read.');
        } catch (\Exception $e) {
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('notification_mark_all_read_failed', 'Notification Update Failed', 
                "Failed to mark all notifications as read for user ID {$_SESSION['user_id']}: " . $e->getMessage());
            $this->jsonError('Failed to mark all notifications as read.', null, 500);
        }
    }

    // ── BROADCASTING ───────────────────────────────────────────────

    /**
     * Broadcast a system notification to all users or a specific role.
     */
    public function send(): void
    {
        // ENFORCE: RBAC (Using a dedicated broadcast permission for better security)
        $this->auth->requirePermission('notifications.broadcast');
        $this->verifyCsrf();
        
        $data = $this->getPost(['title', 'message', 'target']);
        if (empty($data['title']) || empty($data['message'])) {
            $this->jsonError('Title and message required.', null, 422);
        }

        $target = $data['target'] ?? 'all';
        
        $this->db->beginTransaction();
        try {
            // Delegate the user fetching and bulk insertion to the model
            $sentCount = $this->model->broadcast($data['title'], $data['message'], $target);

            $this->logAudit('broadcast_sent', 'notifications', null, 'Notification', "Broadcast sent to {$sentCount} user(s) targeting: {$target}");
            
            // ENFORCE: Notify admins of successful broadcast
            $this->notifyAdmins('broadcast_sent', 'System Broadcast Sent', 
                "A system broadcast titled '{$data['title']}' was sent to {$sentCount} user(s) targeting '{$target}' by an administrator.");
            
            $this->db->commit();
            $this->jsonSuccess(['sent' => $sentCount], "Notification sent to {$sentCount} user(s).");
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('broadcast_failed', 'Broadcast Failed', 
                "Failed to send broadcast '{$data['title']}': " . $e->getMessage());
            $this->jsonError('Failed to send broadcast: ' . $e->getMessage(), null, 500);
        }
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
            WHERE p.slug IN ('notifications.view_all', 'notifications.broadcast') AND u.status = 'active'
        ");
        foreach ($admins as $admin) {
            $this->notif->dispatch((int)$admin['id'], $type, $title, $message);
        }
    }
}