<?php
namespace App\Controllers;

use App\Models\Member;
use App\Helpers\Security;
use App\Helpers\Format;
use Database;

/**
 * AKABBO SOCIAL FUND
 * Member Controller
 *
 * Manages all member CRUD operations, KYC verification,
 * AJAX member search, statement generation, and CSV export.
 */
class MemberController extends BaseController
{
    private Member   $model;
    private Database $db;

    public function __construct()
    {
        parent::__construct();
        $this->auth->requireAuth();
        $this->model = new Member();
        $this->db    = Database::getInstance();
    }

    /**
     * Display paginated member list with filters.
     */
    public function index(): void
    {
        $this->auth->requirePermission('members.view');

        ['page' => $page, 'limit' => $limit] = $this->getPaginationParams();

        $filters = [
            'search'   => $this->getQuery('search', ''),
            'status'   => $this->getQuery('status', ''),
            'kyc'      => isset($_GET['kyc']) ? (int)$_GET['kyc'] : null,
            'group_id' => $this->getQuery('group_id'),
        ];

        $result      = $this->model->getList($page, $limit, $filters);
        $memberStats = $this->model->getStats();
        $savings_groups      = $this->db->fetchAll("SELECT id, name FROM savings_groups WHERE status='active' ORDER BY name");
        $settings    = $this->getSettings();
        $unreadNotifications = $this->getUnreadCount();

        $this->view('members/index', compact('result','memberStats','savings_groups','settings','unreadNotifications'));
    }

    /**
     * Show the member registration form.
     */
    public function create(): void
    {
        $this->auth->requirePermission('members.create');

        $savings_groups   = $this->db->fetchAll("SELECT id, name FROM savings_groups WHERE status='active' ORDER BY name");
        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnreadCount();

        $this->view('members/create', compact('savings_groups','settings','unreadNotifications'));
    }

    /**
     * Process member registration form submission.
     */
    public function store(): void
    {
        $this->auth->requirePermission('members.create');
        $this->verifyCsrf();

        $data    = $this->getPost();
        $missing = $this->validateRequired($data, ['first_name','last_name','gender','phone','next_of_kin_name','next_of_kin_phone','next_of_kin_relationship','membership_date']);

        if ($missing) {
            if ($this->isAjax()) {
                $this->jsonError('Please fill all required fields.', array_fill_keys($missing, 'This field is required.'), 422);
            }
            $this->flash('error', 'Please fill all required fields.');
            $_SESSION['form_data'] = $data;
            $this->redirect('/members/create');
        }

        // ---- UNIQUENESS CHECKS ----
        if ($this->model->exists('phone', $data['phone'])) {
            $this->jsonError('A member with this phone number already exists.', null, 409);
        }
        if (!empty($data['email']) && $this->model->exists('email', $data['email'])) {
            $this->jsonError('A member with this email address already exists.', null, 409);
        }
        if (!empty($data['national_id']) && $this->model->exists('national_id', $data['national_id'])) {
            $this->jsonError('A member with this National ID already exists.', null, 409);
        }
        if (!empty($data['passport_no']) && $this->model->exists('passport_no', $data['passport_no'])) {
            $this->jsonError('A member with this Passport Number already exists.', null, 409);
        }

        $this->db->beginTransaction();
        try {
            // Handle avatar upload
            $avatarFile = null;
            if (!empty($_FILES['avatar']['name'])) {
                $avatarFile = $this->handleAvatarUpload($_FILES['avatar']);
            }

            // Handle ID front upload
            $idFrontFile = null;
            if (!empty($_FILES['id_front']['name'])) {
                $idFrontFile = $this->handleDocumentUpload($_FILES['id_front'], 'id_front');
            }

            // Handle ID back upload
            $idBackFile = null;
            if (!empty($_FILES['id_back']['name'])) {
                $idBackFile = $this->handleDocumentUpload($_FILES['id_back'], 'id_back');
            }

            // Build member record
            $memberData = [
                'member_no'                 => \App\Helpers\MemberSequence::nextFormatted(),
                'first_name'                => $data['first_name'],
                'last_name'                 => $data['last_name'],
                'middle_name'               => $data['middle_name'] ?? null,
                'gender'                    => $data['gender'],
                'date_of_birth'             => !empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
                'national_id'               => !empty($data['national_id']) ? $data['national_id'] : null,
                'passport_no'               => !empty($data['passport_no']) ? $data['passport_no'] : null,
                'email'                     => !empty($data['email']) ? strtolower($data['email']) : null,
                'phone'                     => $data['phone'],
                'phone_alt'                 => $data['phone_alt'] ?? null,
                'address'                   => $data['address'] ?? null,
                'district'                  => $data['district'] ?? null,
                'occupation'                => $data['occupation'] ?? null,
                'employer'                  => $data['employer'] ?? null,
                'next_of_kin_name'          => $data['next_of_kin_name'],
                'next_of_kin_phone'         => $data['next_of_kin_phone'],
                'next_of_kin_relationship'  => $data['next_of_kin_relationship'],
                'membership_date'           => $data['membership_date'],
                'membership_fee_paid'       => isset($data['membership_fee_paid']) ? 1 : 0,
                'membership_fee_amount'     => !empty($data['membership_fee_amount']) ? (float)$data['membership_fee_amount'] : 0,
                'group_id'                  => !empty($data['group_id']) ? (int)$data['group_id'] : null,
                'avatar'                    => $avatarFile,
                'id_front'                  => $idFrontFile,
                'id_back'                   => $idBackFile,
                'status'                    => 'active',
                'kyc_verified'              => 0,
                'created_by'                => $_SESSION['user_id'],
            ];

            $memberId = $this->model->create($memberData);

            // Auto-create a default savings account
            $this->db->execute("
                INSERT INTO savings_accounts (account_no, member_id, account_type, balance, status, opened_at, created_by)
                VALUES (?, ?, 'regular', 0, 'active', ?, ?)
            ", [
                'SAV-' . str_pad($memberId, 6, '0', STR_PAD_LEFT),
                $memberId,
                $data['membership_date'],
                $_SESSION['user_id'],
            ]);

            // Record membership fee transaction if paid
            if ($memberData['membership_fee_paid'] && $memberData['membership_fee_amount'] > 0) {
                $this->db->execute("
                    INSERT INTO transactions (txn_ref, txn_type, amount, member_id, payment_method, description,
                                             transaction_date, status, created_by)
                    VALUES (?, 'membership_fee', ?, ?, 'cash', 'Membership registration fee', ?, 'completed', ?)
                ", [
                    'TXN-' . strtoupper(uniqid()),
                    $memberData['membership_fee_amount'],
                    $memberId,
                    $data['membership_date'],
                    $_SESSION['user_id'],
                ]);
            }

            // Audit log
            $this->auth->logAudit($_SESSION['user_id'], 'member_created', 'members', $memberId, 'Member', "Member {$memberData['member_no']} registered");

            $this->db->commit();

            if ($this->isAjax()) {
                $this->jsonSuccess(['member_id' => $memberId, 'redirect' => APP_URL . '/members/' . $memberId], 'Member registered successfully!');
            }

            $this->flash('success', "Member {$memberData['member_no']} — {$memberData['first_name']} {$memberData['last_name']} registered successfully!");
            $this->redirect('/members/' . $memberId);

        } catch (\Exception $e) {
            $this->db->rollback();
            error_log('[MEMBER STORE ERROR] ' . $e->getMessage());

            if ($this->isAjax()) {
                $this->jsonError('Registration failed. Please try again.', null, 500);
            }
            $this->flash('error', 'Registration failed. Please try again.');
            $this->redirect('/members/create');
        }
    }

    /**
     * Show a member's full profile.
     */
    public function show(int $id): void
    {
        $this->auth->requirePermission('members.view');

        $member = $this->model->getProfile($id);
        if (!$member) {
            $this->flash('error', 'Member not found.');
            $this->redirect('/members');
        }

        $loans = $this->db->fetchAll("
            SELECT l.*, lp.name AS product_name
            FROM loans l
            JOIN loan_products lp ON lp.id = l.loan_product_id
            WHERE l.member_id = ? AND l.deleted_at IS NULL
            ORDER BY l.created_at DESC
        ", [$id]);

        $transactions = $this->db->fetchAll("
            SELECT * FROM transactions WHERE member_id = ?
            ORDER BY created_at DESC LIMIT 20
        ", [$id]);

        $savingsAccounts = $this->db->fetchAll("
            SELECT * FROM savings_accounts WHERE member_id = ? AND status != 'closed'
        ", [$id]);

        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnreadCount();

        $pageTitle   = $member['first_name'] . ' ' . $member['last_name'];
        $activePage  = 'members';
        $breadcrumbs = ['Members' => APP_URL.'/members', $pageTitle => null];

        $this->view('members/show', compact('member','loans','transactions','savingsAccounts','settings','pageTitle','breadcrumbs','activePage','unreadNotifications'));
    }

    /**
     * Show the member edit form.
     */
    public function edit(int $id): void
    {
        $this->auth->requirePermission('members.edit');

        $member = $this->model->find($id);
        if (!$member) {
            $this->flash('error', 'Member not found.');
            $this->redirect('/members');
        }

        $savings_groups   = $this->db->fetchAll("SELECT id, name FROM savings_groups WHERE status='active' ORDER BY name");
        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnreadCount();

        $pageTitle   = 'Edit: ' . $member['first_name'] . ' ' . $member['last_name'];
        $activePage  = 'members';
        $breadcrumbs = ['Members' => APP_URL.'/members', 'Edit Member' => null];

        $this->view('members/edit', compact('member','savings_groups','settings','pageTitle','breadcrumbs','activePage','unreadNotifications'));
    }

    /**
     * Process member update.
     */
    public function update(int $id): void
    {
        $this->auth->requirePermission('members.edit');
        $this->verifyCsrf();

        $member = $this->model->find($id);
        if (!$member) $this->jsonError('Member not found.', null, 404);

        $data    = $this->getPost();
        $missing = $this->validateRequired($data, ['first_name','last_name','gender','phone']);
        if ($missing) $this->jsonError('Required fields missing.', array_fill_keys($missing, 'Required.'), 422);

        // ---- UNIQUENESS CHECKS (excluding current member) ----
        if ($this->model->exists('phone', $data['phone'], $id)) {
            $this->jsonError('Phone number already in use by another member.', null, 409);
        }
        if (!empty($data['email']) && $this->model->exists('email', $data['email'], $id)) {
            $this->jsonError('Email address already in use by another member.', null, 409);
        }
        if (!empty($data['national_id']) && $this->model->exists('national_id', $data['national_id'], $id)) {
            $this->jsonError('National ID already in use by another member.', null, 409);
        }
        if (!empty($data['passport_no']) && $this->model->exists('passport_no', $data['passport_no'], $id)) {
            $this->jsonError('Passport number already in use by another member.', null, 409);
        }

        // ----- Handle avatar upload -----
        $avatarFile = $member['avatar']; // keep existing by default
        if (!empty($_FILES['avatar']['name'])) {
            try {
                $avatarFile = $this->handleAvatarUpload($_FILES['avatar']);
                // Delete old avatar if it exists
                if (!empty($member['avatar']) && file_exists(UPLOADS_PATH . '/avatars/' . $member['avatar'])) {
                    unlink(UPLOADS_PATH . '/avatars/' . $member['avatar']);
                }
            } catch (\RuntimeException $e) {
                $this->jsonError($e->getMessage(), null, 400);
            }
        }

        // ----- Handle ID front -----
        if (!empty($_FILES['id_front']['name'])) {
            try {
                $idFrontFile = $this->handleDocumentUpload($_FILES['id_front'], 'id_front');
                if (!empty($member['id_front']) && file_exists(UPLOADS_PATH . '/kyc/' . $member['id_front'])) {
                    unlink(UPLOADS_PATH . '/kyc/' . $member['id_front']);
                }
                $data['id_front'] = $idFrontFile;
            } catch (\RuntimeException $e) {
                $this->jsonError("ID Front: " . $e->getMessage(), null, 400);
            }
        } elseif (!empty($data['delete_id_front'])) {
            if (!empty($member['id_front']) && file_exists(UPLOADS_PATH . '/kyc/' . $member['id_front'])) {
                unlink(UPLOADS_PATH . '/kyc/' . $member['id_front']);
            }
            $data['id_front'] = null;
        } else {
            $data['id_front'] = $member['id_front'] ?? null;
        }

        // ----- Handle ID back -----
        if (!empty($_FILES['id_back']['name'])) {
            try {
                $idBackFile = $this->handleDocumentUpload($_FILES['id_back'], 'id_back');
                if (!empty($member['id_back']) && file_exists(UPLOADS_PATH . '/kyc/' . $member['id_back'])) {
                    unlink(UPLOADS_PATH . '/kyc/' . $member['id_back']);
                }
                $data['id_back'] = $idBackFile;
            } catch (\RuntimeException $e) {
                $this->jsonError("ID Back: " . $e->getMessage(), null, 400);
            }
        } elseif (!empty($data['delete_id_back'])) {
            if (!empty($member['id_back']) && file_exists(UPLOADS_PATH . '/kyc/' . $member['id_back'])) {
                unlink(UPLOADS_PATH . '/kyc/' . $member['id_back']);
            }
            $data['id_back'] = null;
        } else {
            $data['id_back'] = $member['id_back'] ?? null;
        }

        // Build update array
        $updateData = [
            'first_name'               => $data['first_name'],
            'last_name'                => $data['last_name'],
            'middle_name'              => $data['middle_name'] ?? $member['middle_name'],
            'gender'                   => $data['gender'],
            'date_of_birth'            => !empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
            'phone'                    => $data['phone'],
            'phone_alt'                => $data['phone_alt'] ?? null,
            'email'                    => !empty($data['email']) ? strtolower($data['email']) : null,
            'address'                  => $data['address'] ?? null,
            'district'                 => $data['district'] ?? null,
            'occupation'               => $data['occupation'] ?? null,
            'employer'                 => $data['employer'] ?? null,
            'national_id'              => !empty($data['national_id']) ? $data['national_id'] : $member['national_id'],
            'passport_no'              => !empty($data['passport_no']) ? $data['passport_no'] : $member['passport_no'],
            'next_of_kin_name'         => $data['next_of_kin_name'] ?? $member['next_of_kin_name'],
            'next_of_kin_phone'        => $data['next_of_kin_phone'] ?? $member['next_of_kin_phone'],
            'next_of_kin_relationship' => $data['next_of_kin_relationship'] ?? $member['next_of_kin_relationship'],
            'group_id'                 => !empty($data['group_id']) ? (int)$data['group_id'] : null,
            'status'                   => $data['status'] ?? $member['status'],
            'avatar'                   => $avatarFile,
            'id_front'                 => $data['id_front'] ?? null,
            'id_back'                  => $data['id_back'] ?? null,
        ];

        $this->model->update($id, $updateData);
        $this->auth->logAudit($_SESSION['user_id'], 'member_updated', 'members', $id, 'Member', "Member ID {$id} updated");

        if ($this->isAjax()) {
            $this->jsonSuccess(['member_id' => $id, 'redirect' => APP_URL . '/members/' . $id], 'Member updated successfully!');
        }
        $this->flash('success', 'Member updated successfully.');
        $this->redirect('/members/' . $id);
    }

    /**
     * Soft-delete a member.
     */
    public function delete(int $id): void
    {
        $this->auth->requirePermission('members.delete');
        $this->verifyCsrf();

        $member = $this->model->find($id);
        if (!$member) $this->jsonError('Member not found.', null, 404);

        // Check for active loans
        $activeLoans = $this->db->fetchColumn("SELECT COUNT(*) FROM loans WHERE member_id = ? AND status IN ('active','disbursed','pending')", [$id]);
        if ($activeLoans > 0) {
            $this->jsonError("Cannot delete member with {$activeLoans} active loan(s). Close loans first.", null, 409);
        }

        // Move to trash
        $this->db->execute("INSERT INTO trash (record_type, record_id, record_data, deleted_by) VALUES (?, ?, ?, ?)", [
            'member', $id, json_encode($member), $_SESSION['user_id']
        ]);

        $this->model->delete($id);
        $this->auth->logAudit($_SESSION['user_id'], 'member_deleted', 'members', $id, 'Member', "Member {$member['member_no']} deleted");

        if ($this->isAjax()) {
            $this->jsonSuccess(null, 'Member moved to trash.');
        }
        $this->flash('success', "Member {$member['first_name']} {$member['last_name']} deleted.");
        $this->redirect('/members');
    }

    /**
     * AJAX: Search members for live search and loan applicant lookup.
     */
    public function search(): void
    {
        $this->auth->requireAuth();

        $query   = $this->getQuery('q', '');
        $members = $this->model->search($query, 15);

        $result = array_map(function ($m) {
            $name      = trim($m['first_name'] . ' ' . $m['last_name']);
            $savings   = $this->db->fetchColumn("SELECT COALESCE(SUM(balance),0) FROM savings_accounts WHERE member_id = ? AND status='active'", [$m['id']]);
            $settings  = $this->getSettings();
            $multiplier = (int)($settings['max_loan_multiplier'] ?? 3);

            return [
                'id'               => $m['id'],
                'full_name'        => $name,
                'member_no'        => $m['member_no'],
                'phone'            => $m['phone'],
                'status'           => $m['status'],
                'initials'         => Format::initials($name),
                'total_savings'    => (float)$savings,
                'total_savings_fmt'=> Format::currency((float)$savings),
                'max_loan_fmt'     => Format::currency((float)$savings * $multiplier),
                'active_loans'     => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM loans WHERE member_id = ? AND status IN ('active','disbursed')", [$m['id']]),
            ];
        }, $members);

        $this->jsonSuccess($result);
    }

    /**
     * Export members as CSV.
     */
    public function export(): void
    {
        $this->auth->requirePermission('members.export');

        $filters = [
            'search' => $this->getQuery('search', ''),
            'status' => $this->getQuery('status', ''),
        ];

        $result  = $this->model->getList(1, 10000, $filters);
        $members = $result['data'];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="akabbo-members-' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');

        // UTF-8 BOM for Excel compatibility
        fputs($out, "\xEF\xBB\xBF");

        fputcsv($out, ['Member No', 'First Name', 'Last Name', 'Gender', 'Phone', 'Email', 'District', 'Total Savings (UGX)', 'Status', 'KYC Verified', 'Membership Date']);

        foreach ($members as $m) {
            fputcsv($out, [
                $m['member_no'], $m['first_name'], $m['last_name'],
                $m['gender'], $m['phone'], $m['email'] ?? '',
                $m['district'] ?? '', number_format($m['total_savings'] ?? 0, 2),
                $m['status'], $m['kyc_verified'] ? 'Yes' : 'No',
                Format::date($m['membership_date']),
            ]);
        }

        fclose($out);
        $this->auth->logAudit($_SESSION['user_id'], 'members_exported', 'members', null, null, count($members) . ' members exported');
        exit;
    }

    /**
     * Verify KYC for a member.
     */
    public function verifyKyc(int $id): void
    {
        $this->auth->requirePermission('members.edit');
        $this->verifyCsrf();

        $member = $this->model->find($id);
        if (!$member) $this->jsonError('Member not found.', null, 404);

        $this->model->update($id, [
            'kyc_verified'    => 1,
            'kyc_verified_by' => $_SESSION['user_id'],
            'kyc_verified_at' => date('Y-m-d H:i:s'),
        ]);

        $this->auth->logAudit($_SESSION['user_id'], 'kyc_verified', 'members', $id, 'Member', "KYC verified for member ID {$id}");
        $this->jsonSuccess(null, 'KYC verified successfully.');
    }

    // ── Private helpers ──────────────────────────────────────────

    /** Handle avatar file upload and return stored filename. */
    private function handleAvatarUpload(array $file): ?string
    {
        $validation = Security::validateUpload($file, ['image/jpeg','image/png','image/webp'], 2 * 1024 * 1024);
        if (!$validation['valid']) {
            throw new \RuntimeException($validation['error']);
        }

        $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
        $name = 'avatar_' . uniqid() . '.' . strtolower($ext);
        $dest = UPLOADS_PATH . '/avatars/' . $name;

        if (!is_dir(UPLOADS_PATH . '/avatars')) {
            mkdir(UPLOADS_PATH . '/avatars', 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new \RuntimeException('Failed to save avatar file.');
        }

        return $name;
    }

    /**
     * Handle KYC document upload (ID front/back)
     */
    private function handleDocumentUpload(array $file, string $type): string
    {
        $validation = Security::validateUpload($file, ['image/jpeg','image/png','image/webp','application/pdf'], 2 * 1024 * 1024);
        if (!$validation['valid']) {
            throw new \RuntimeException($validation['error']);
        }

        $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
        $name = $type . '_' . uniqid() . '.' . strtolower($ext);
        $dest = UPLOADS_PATH . '/kyc/' . $name;

        if (!is_dir(UPLOADS_PATH . '/kyc')) {
            mkdir(UPLOADS_PATH . '/kyc', 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new \RuntimeException('Failed to save document.');
        }

        return $name;
    }

    /** Generate a printable member account statement. */
    public function statement(int $id): void
    {
        $this->auth->requirePermission('members.view');

        $member = $this->model->getProfile($id);
        if (!$member) { $this->flash('error','Member not found.'); $this->redirect('/members'); }

        $from = $this->getQuery('from', date('Y-01-01'));
        $to   = $this->getQuery('to',   date('Y-m-d'));

        $transactions = $this->db->fetchAll("
            SELECT t.*, sa.account_no
            FROM transactions t
            LEFT JOIN savings_accounts sa ON sa.id = t.savings_account_id
            WHERE t.member_id = ?
              AND t.transaction_date BETWEEN ? AND ?
              AND t.status IN ('completed','approved')
            ORDER BY t.transaction_date ASC, t.id ASC
        ", [$id, $from, $to]);

        $loans = $this->db->fetchAll("
            SELECT l.*, lp.name AS product_name
            FROM loans l JOIN loan_products lp ON lp.id = l.loan_product_id
            WHERE l.member_id = ? AND l.deleted_at IS NULL
            ORDER BY l.created_at DESC
        ", [$id]);

        $savingsAccounts = $this->db->fetchAll(
            "SELECT * FROM savings_accounts WHERE member_id = ?", [$id]
        );

        $settings = $this->getSettings();
        $pageTitle = 'Statement — ' . $member['first_name'] . ' ' . $member['last_name'];

        // Render without the main layout (printable page)
        $this->view('members/statement',
            compact('member','transactions','loans','savingsAccounts','settings','from','to','pageTitle'),
            null
        );
    }

    private function getSettings(): array
    {
        $rows = $this->db->fetchAll("SELECT `key`, `value` FROM settings");
        return array_column($rows, 'value', 'key');
    }

    private function getUnreadCount(): int
    {
        return (int)$this->db->fetchColumn("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0", [$_SESSION['user_id']]);
    }
}