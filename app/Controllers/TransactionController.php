<?php
namespace App\Controllers;
use App\Helpers\Format;
use Database;

/**
 * AKABBO SOCIAL FUND - Transaction Controller
 */
class TransactionController extends BaseController
{
    private Database $db;
    public function __construct() {
        parent::__construct();
        $this->auth->requireAuth();
        $this->auth->requirePermission('transactions.view');
        $this->db = Database::getInstance();
    }

    public function index(): void
    {
        ['page'=>$page,'limit'=>$limit] = $this->getPaginationParams();
        $search=$this->getQuery('search',''); $type=$this->getQuery('type',''); $from=$this->getQuery('from',date('Y-m-01')); $to=$this->getQuery('to',date('Y-m-d'));
        $where=["t.transaction_date BETWEEN ? AND ?"]; $params=[$from,$to];
        if ($search){$like="%{$search}%";$where[]="(t.txn_ref LIKE ? OR CONCAT(m.first_name,' ',m.last_name) LIKE ? OR m.member_no LIKE ?)";$params=array_merge($params,[$like,$like,$like]);}
        if ($type){$where[]='t.txn_type=?';$params[]=$type;}
        $ws='WHERE '.implode(' AND ',$where);
        $offset=($page-1)*$limit;
        $total=(int)$this->db->fetchColumn("SELECT COUNT(*) FROM transactions t LEFT JOIN members m ON m.id=t.member_id {$ws}",$params);
        $data=$this->db->fetchAll("SELECT t.*,CONCAT(m.first_name,' ',m.last_name) AS member_name,m.member_no FROM transactions t LEFT JOIN members m ON m.id=t.member_id {$ws} ORDER BY t.created_at DESC LIMIT {$limit} OFFSET {$offset}",$params);
        $result=['data'=>$data,'total'=>$total,'page'=>$page,'per_page'=>$limit,'last_page'=>(int)ceil($total/$limit),'from'=>$total>0?$offset+1:0,'to'=>min($offset+$limit,$total)];
        $typeTotals=$this->db->fetchAll("SELECT txn_type,COUNT(*) AS cnt,COALESCE(SUM(amount),0) AS total FROM transactions t LEFT JOIN members m ON m.id=t.member_id {$ws} GROUP BY txn_type",$params);
        $settings=array_column($this->db->fetchAll("SELECT `key`,`value` FROM settings"),'value','key');
        $unreadNotifications=(int)$this->db->fetchColumn("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0",[$_SESSION['user_id']]);
        $pageTitle='Transactions'; $activePage='transactions'; $breadcrumbs=['Transactions'=>null];
        $filters=compact('from','to','search','type');
        $this->view('transactions/index',compact('result','typeTotals','filters','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'));
    }

    public function show(int $id): void
    {
        $txn=$this->db->fetchOne("SELECT t.*,CONCAT(m.first_name,' ',m.last_name) AS member_name,m.member_no FROM transactions t LEFT JOIN members m ON m.id=t.member_id WHERE t.id=?",[$id]);
        if (!$txn){$this->flash('error','Transaction not found.');$this->redirect('/transactions');}
        $settings=array_column($this->db->fetchAll("SELECT `key`,`value` FROM settings"),'value','key');
        $unreadNotifications=(int)$this->db->fetchColumn("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0",[$_SESSION['user_id']]);
        $pageTitle='Transaction '.$txn['txn_ref']; $activePage='transactions';
        $breadcrumbs=['Transactions'=>APP_URL.'/transactions',$txn['txn_ref']=>null];
        $this->view('transactions/show',compact('txn','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'));
    }

    public function approve(int $id): void
    {
        $this->auth->requirePermission('transactions.approve');
        $this->verifyCsrf();
        $txn=$this->db->fetchOne("SELECT * FROM transactions WHERE id=? AND status='pending'",[$id]);
        if (!$txn) $this->jsonError('Transaction not found or cannot be approved.',null,400);
        $this->db->execute("UPDATE transactions SET status='completed',approved_by=?,approved_at=NOW() WHERE id=?",[$_SESSION['user_id'],$id]);
        $this->auth->logAudit($_SESSION['user_id'],'transaction_approved','transactions',$id,'Transaction',"Transaction {$txn['txn_ref']} approved");
        $this->jsonSuccess(null,'Transaction approved.');
    }

    public function reverse(int $id): void
    {
        $this->auth->requirePermission('transactions.reverse');
        $this->verifyCsrf();
        $txn=$this->db->fetchOne("SELECT * FROM transactions WHERE id=? AND status='completed' AND deleted_at IS NULL",[$id]);
        if (!$txn) $this->jsonError('Transaction cannot be reversed.',null,400);
        $this->db->execute("UPDATE transactions SET status='reversed',updated_at=NOW() WHERE id=?",[$id]);
        $this->auth->logAudit($_SESSION['user_id'],'transaction_reversed','transactions',$id,'Transaction',"Transaction {$txn['txn_ref']} reversed");
        $this->jsonSuccess(null,'Transaction reversed.');
    }
}
