<?php
namespace App\Controllers;
use App\Helpers\Security;
use App\Helpers\Format;
use Database;

/**
 * AKABBO SOCIAL FUND - User Controller
 * System user management and profile operations.
 */
class UserController extends BaseController
{
    private Database $db;
    public function __construct() {
        parent::__construct();
        $this->auth->requireAuth();
        $this->db = Database::getInstance();
    }

    public function index(): void
    {
        $this->auth->requirePermission('users.view');
        ['page'=>$page,'limit'=>$limit] = $this->getPaginationParams();
        $search = $this->getQuery('search','');
        $where  = ['1=1']; $params = [];
        if ($search) { $like="%{$search}%"; $where[]="(CONCAT(first_name,' ',last_name) LIKE ? OR email LIKE ?)"; $params=[$like,$like]; }
        $ws = 'WHERE '.implode(' AND ',$where);
        $offset = ($page-1)*$limit;
        $total  = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM users {$ws}",$params);
        $data   = $this->db->fetchAll("SELECT u.*,r.name AS role_name FROM users u JOIN roles r ON r.id=u.role_id {$ws} ORDER BY u.created_at DESC LIMIT {$limit} OFFSET {$offset}",$params);
        $result = ['data'=>$data,'total'=>$total,'page'=>$page,'per_page'=>$limit,'last_page'=>(int)ceil($total/$limit),'from'=>$total>0?$offset+1:0,'to'=>min($offset+$limit,$total)];
        $roles  = $this->db->fetchAll("SELECT * FROM roles ORDER BY id");
        $settings=array_column($this->db->fetchAll("SELECT `key`,`value` FROM settings"),'value','key');
        $unreadNotifications=(int)$this->db->fetchColumn("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0",[$_SESSION['user_id']]);
        $pageTitle='System Users'; $activePage='users'; $breadcrumbs=['Users'=>null];
        $this->view('users/index',compact('result','roles','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'));
    }

    public function create(): void
    {
        $this->auth->requirePermission('users.create');
        $roles=$this->db->fetchAll("SELECT * FROM roles ORDER BY id");
        $settings=array_column($this->db->fetchAll("SELECT `key`,`value` FROM settings"),'value','key');
        $unreadNotifications=(int)$this->db->fetchColumn("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0",[$_SESSION['user_id']]);
        $pageTitle='Create User'; $activePage='users'; $breadcrumbs=['Users'=>APP_URL.'/users','Create'=>null];
        $this->view('users/create',compact('roles','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'));
    }

    public function store(): void
    {
        $this->auth->requirePermission('users.create');
        $this->verifyCsrf();
        $data    = $this->getPost();
        $missing = $this->validateRequired($data,['first_name','last_name','email','role_id']);
        if ($missing) $this->jsonError('Required fields missing.',array_fill_keys($missing,'Required.'),422);
        if ($this->db->fetchColumn("SELECT COUNT(*) FROM users WHERE email=?",[$data['email']])) $this->jsonError('Email already in use.',null,409);
        $tempPass = $_POST['password'] ?? Security::generateToken(6);
        $id = $this->db->insert("INSERT INTO users (first_name,last_name,email,phone,role_id,password_hash,status,must_change_password,created_by) VALUES (?,?,?,?,?,?,?,?,?)",
            [$data['first_name'],$data['last_name'],strtolower($data['email']),$data['phone']??null,(int)$data['role_id'],Security::hashPassword($tempPass),'active',1,$_SESSION['user_id']]);
        $this->auth->logAudit($_SESSION['user_id'],'user_created','users',(int)$id,'User',"User {$data['email']} created");
        $this->jsonSuccess(['user_id'=>$id,'temp_password'=>$tempPass,'redirect'=>APP_URL.'/users'],'User created. Temporary password: '.$tempPass);
    }

    public function show(int $id): void
    {
        $this->auth->requirePermission('users.view');
        $user = $this->db->fetchOne("SELECT u.*,r.name AS role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?",[$id]);
        if (!$user) { $this->flash('error','User not found.'); $this->redirect('/users'); }
        $sessions=$this->db->fetchAll("SELECT * FROM login_sessions WHERE user_id=? ORDER BY login_at DESC LIMIT 10",[$id]);
        $settings=array_column($this->db->fetchAll("SELECT `key`,`value` FROM settings"),'value','key');
        $unreadNotifications=(int)$this->db->fetchColumn("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0",[$_SESSION['user_id']]);
        $pageTitle=$user['first_name'].' '.$user['last_name']; $activePage='users';
        $breadcrumbs=['Users'=>APP_URL.'/users',$pageTitle=>null];
        $this->view('users/show',compact('user','sessions','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'));
    }

    public function edit(int $id): void { $this->auth->requirePermission('users.edit'); $this->show($id); }

    public function update(int $id): void
    {
        $this->auth->requirePermission('users.edit');
        $this->verifyCsrf();
        $data=$this->getPost();
        $this->db->execute("UPDATE users SET first_name=?,last_name=?,phone=?,role_id=?,status=?,updated_at=NOW() WHERE id=?",
            [$data['first_name'],$data['last_name'],$data['phone']??null,(int)$data['role_id'],$data['status']??'active',$id]);
        $this->jsonSuccess(null,'User updated successfully.');
    }

    public function delete(int $id): void
    {
        $this->auth->requirePermission('users.delete');
        $this->verifyCsrf();
        if ($id === $_SESSION['user_id']) $this->jsonError('You cannot delete your own account.',null,409);
        $this->db->execute("UPDATE users SET status='inactive' WHERE id=?",[$id]);
        $this->auth->logAudit($_SESSION['user_id'],'user_deleted','users',$id,'User',"User ID {$id} deactivated");
        $this->jsonSuccess(null,'User deactivated.');
    }

    public function resetPassword(int $id): void
    {
        $this->auth->requirePermission('users.edit');
        $this->verifyCsrf();
        $newPass = Security::generateToken(6);
        $this->db->execute("UPDATE users SET password_hash=?,must_change_password=1 WHERE id=?",
            [Security::hashPassword($newPass),$id]);
        $this->jsonSuccess(['temp_password'=>$newPass],'Password reset. New temporary password: '.$newPass);
    }

    public function profile(): void
    {
        $user=$this->db->fetchOne("SELECT u.*,r.name AS role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?",[$_SESSION['user_id']]);
        $sessions=$this->db->fetchAll("SELECT * FROM login_sessions WHERE user_id=? ORDER BY login_at DESC LIMIT 5",[$_SESSION['user_id']]);
        $settings=array_column($this->db->fetchAll("SELECT `key`,`value` FROM settings"),'value','key');
        $unreadNotifications=(int)$this->db->fetchColumn("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0",[$_SESSION['user_id']]);
        $pageTitle='My Profile'; $activePage='profile'; $breadcrumbs=['Profile'=>null];
        $this->view('users/profile',compact('user','sessions','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'));
    }

    public function updateProfile(): void
    {
        $this->verifyCsrf();
        $data=$this->getPost(['first_name','last_name','phone']);
        $this->db->execute("UPDATE users SET first_name=?,last_name=?,phone=?,updated_at=NOW() WHERE id=?",
            [$data['first_name'],$data['last_name'],$data['phone']??null,$_SESSION['user_id']]);
        $_SESSION['user']['name']=$data['first_name'].' '.$data['last_name'];
        $_SESSION['user']['first_name']=$data['first_name'];
        $this->jsonSuccess(null,'Profile updated successfully.');
    }

    public function showChangePassword(): void
    {
        $settings=array_column($this->db->fetchAll("SELECT `key`,`value` FROM settings"),'value','key');
        $unreadNotifications=(int)$this->db->fetchColumn("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0",[$_SESSION['user_id']]);
        $pageTitle='Change Password'; $activePage='profile'; $breadcrumbs=['Profile'=>APP_URL.'/profile','Change Password'=>null];
        $this->view('users/change-password',compact('settings','pageTitle','activePage','breadcrumbs','unreadNotifications'));
    }

    public function changePassword(): void
    {
        $this->verifyCsrf();
        $data=$this->getPost(['current_password','new_password','confirm_password']);
        $user=$this->db->fetchOne("SELECT password_hash FROM users WHERE id=?",[$_SESSION['user_id']]);
        if (!Security::verifyPassword($_POST['current_password']??'',$user['password_hash'])) $this->jsonError('Current password is incorrect.',null,401);
        if (($data['new_password']??'')!==($data['confirm_password']??'')) $this->jsonError('New passwords do not match.',null,422);
        if (strlen($data['new_password']??'')<8) $this->jsonError('Password must be at least 8 characters.',null,422);
        $this->db->execute("UPDATE users SET password_hash=?,must_change_password=0,updated_at=NOW() WHERE id=?",
            [Security::hashPassword($data['new_password']),$_SESSION['user_id']]);
        $this->auth->logAudit($_SESSION['user_id'],'password_changed','users',$_SESSION['user_id'],'User','Password changed');
        $this->jsonSuccess(['redirect'=>APP_URL.'/profile'],'Password changed successfully!');
    }
}
