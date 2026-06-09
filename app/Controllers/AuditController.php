<?php
namespace App\Controllers;
use App\Helpers\Format;
use Database;

/**
 * AKABBO SOCIAL FUND - Audit Controller
 */
class AuditController extends BaseController
{
    private Database $db;
    public function __construct() {
        parent::__construct();
        $this->auth->requireAuth();
        $this->auth->requirePermission('audit.view');
        $this->db = Database::getInstance();
    }

    public function index(): void
    {
        ['page'=>$page,'limit'=>$limit] = $this->getPaginationParams();
        $search = $this->getQuery('search','');
        $module = $this->getQuery('module','');
        $from   = $this->getQuery('from', date('Y-m-01'));
        $to     = $this->getQuery('to', date('Y-m-d'));

        $where=['created_at BETWEEN ? AND ?']; $params=[$from.' 00:00:00',$to.' 23:59:59'];
        if ($search) { $where[]='(user_name LIKE ? OR description LIKE ? OR ip_address LIKE ?)'; $like="%{$search}%"; $params=array_merge($params,[$like,$like,$like]); }
        if ($module) { $where[]='module=?'; $params[]=$module; }
        $ws = 'WHERE '.implode(' AND ',$where);
        $offset=($page-1)*$limit;
        $total=(int)$this->db->fetchColumn("SELECT COUNT(*) FROM audit_logs {$ws}",$params);
        $data=$this->db->fetchAll("SELECT * FROM audit_logs {$ws} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}",$params);
        $modules=$this->db->fetchAll("SELECT DISTINCT module FROM audit_logs ORDER BY module");
        $result=['data'=>$data,'total'=>$total,'page'=>$page,'per_page'=>$limit,'last_page'=>(int)ceil($total/$limit),'from'=>$total>0?$offset+1:0,'to'=>min($offset+$limit,$total)];
        $settings=array_column($this->db->fetchAll("SELECT `key`,`value` FROM settings"),'value','key');
        $unreadNotifications=(int)$this->db->fetchColumn("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0",[$_SESSION['user_id']]);
        $pageTitle='Audit Log'; $activePage='audit'; $breadcrumbs=['Audit Log'=>null];
        $filters=compact('from','to','search','module');
        $this->view('audit/index',compact('result','modules','filters','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'));
    }

    public function show(int $id): void
    {
        $log=$this->db->fetchOne("SELECT * FROM audit_logs WHERE id=?",[$id]);
        if (!$log) { $this->flash('error','Audit log not found.'); $this->redirect('/audit'); }
        $settings=array_column($this->db->fetchAll("SELECT `key`,`value` FROM settings"),'value','key');
        $unreadNotifications=(int)$this->db->fetchColumn("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0",[$_SESSION['user_id']]);
        $pageTitle='Audit Entry #'.$id; $activePage='audit';
        $breadcrumbs=['Audit Log'=>APP_URL.'/audit','Entry #'.$id=>null];
        $this->view('audit/show',compact('log','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'));
    }
}
