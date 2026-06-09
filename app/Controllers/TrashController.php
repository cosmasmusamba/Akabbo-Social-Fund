<?php
namespace App\Controllers;
use Database;

/**
 * AKABBO SOCIAL FUND - Trash Controller
 * Soft-delete recovery and permanent deletion.
 */
class TrashController extends BaseController
{
    private Database $db;
    public function __construct() {
        parent::__construct();
        $this->auth->requireAuth();
        $this->auth->requirePermission('users.delete');
        $this->db = Database::getInstance();
    }

    public function index(): void
    {
        ['page'=>$page,'limit'=>$limit] = $this->getPaginationParams();
        $type   = $this->getQuery('type','');
        $where  = ['restored_at IS NULL','permanently_deleted_at IS NULL'];
        $params = [];
        if ($type) { $where[]='record_type=?'; $params[]=$type; }
        $ws = 'WHERE '.implode(' AND ',$where);
        $offset = ($page-1)*$limit;
        $total  = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM trash {$ws}",$params);
        $data   = $this->db->fetchAll("SELECT t.*, CONCAT(u.first_name,' ',u.last_name) AS deleted_by_name FROM trash t LEFT JOIN users u ON u.id=t.deleted_by {$ws} ORDER BY t.deleted_at DESC LIMIT {$limit} OFFSET {$offset}",$params);
        $result = ['data'=>$data,'total'=>$total,'page'=>$page,'per_page'=>$limit,'last_page'=>(int)ceil($total/$limit)];
        $settings=array_column($this->db->fetchAll("SELECT `key`,`value` FROM settings"),'value','key');
        $unreadNotifications=(int)$this->db->fetchColumn("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0",[$_SESSION['user_id']]);
        $pageTitle='Trash'; $activePage='trash'; $breadcrumbs=['Trash'=>null];
        $this->view('trash/index',compact('result','type','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'));
    }

    public function restore(string $type, int $id): void
    {
        $this->verifyCsrf();
        $item = $this->db->fetchOne("SELECT * FROM trash WHERE record_type=? AND record_id=? AND restored_at IS NULL",[$type,$id]);
        if (!$item) $this->jsonError('Record not found in trash.',null,404);

        $tableMap = ['member'=>'members','loan'=>'loans','transaction'=>'transactions'];
        $table    = $tableMap[$type] ?? null;
        if (!$table) $this->jsonError('Unknown record type.',null,400);

        $this->db->execute("UPDATE `{$table}` SET deleted_at=NULL, updated_at=NOW() WHERE id=?",[$id]);
        $this->db->execute("UPDATE trash SET restored_at=NOW(), restored_by=? WHERE id=?",[$_SESSION['user_id'],$item['id']]);
        $this->auth->logAudit($_SESSION['user_id'],'record_restored','trash',$id,ucfirst($type),"Restored {$type} ID {$id}");
        $this->jsonSuccess(null,ucfirst($type).' restored successfully.');
    }

    public function destroy(string $type, int $id): void
    {
        $this->verifyCsrf();
        $item = $this->db->fetchOne("SELECT * FROM trash WHERE record_type=? AND record_id=?",[$type,$id]);
        if (!$item) $this->jsonError('Record not found.',null,404);

        $tableMap = ['member'=>'members','loan'=>'loans','transaction'=>'transactions'];
        $table    = $tableMap[$type] ?? null;
        if ($table) $this->db->execute("DELETE FROM `{$table}` WHERE id=?",[$id]);
        $this->db->execute("UPDATE trash SET permanently_deleted_at=NOW() WHERE id=?",[$item['id']]);
        $this->auth->logAudit($_SESSION['user_id'],'record_permanently_deleted','trash',$id,ucfirst($type),"Permanently deleted {$type} ID {$id}");
        $this->jsonSuccess(null,ucfirst($type).' permanently deleted.');
    }
}
