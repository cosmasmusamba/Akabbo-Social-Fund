<?php
/* 
*Enforces logAudit and (RBAC + IDOR).
*Ensures to use ApprovalController for DRY and consistence.
*Respective notification recipients are notified via a centralised dispatch
*On member registration, Always create user account with default member role if account does not exist already for user.
*NIN and Passport numbers are masked only showing last 5 characters if logged in user is not owner. 
*Determines the user's data scope (Global, Group, Personal, or None)
*. Prevent overdraft if so, processes it a loan with users concent to terms with other loan mechanisms applicable.
*/
namespace App\Controllers;

use App\Models\Member;
use App\Helpers\Format;
use App\Models\Share;
use App\Helpers\StorageHelper;
use App\Services\NotificationService;
use Database;

class MemberController extends BaseController
{
    private Member $model;
    private Share $shareModel;
    private NotificationService $notif;

    public function __construct()
    {
        parent::__construct();
        $this->auth->requireAuth();
        $this->model = new Member();
        $this->shareModel = new Share();
        $this->notif = new NotificationService();
    }

    private function maskMemberSensitiveFields(array $member): array {
        $user = $this->auth->user();
        $isOwner = !empty($user['member_id']) && (int)$user['member_id'] === (int)($member['id'] ?? 0);
        
        // Allow admins/KYC officers to see full data
        $isAdmin = $this->auth->can('members.kyc-verify') || $this->auth->can('members.view_all_unmasked'); 
        
        if (!$isOwner && !$isAdmin) {
            if (!empty($member['national_id'])) {
                $len = strlen($member['national_id']);
                $member['national_id'] = $len > 5 ? str_repeat('*', $len - 5) . substr($member['national_id'], -5) : str_repeat('*', $len);
            }
            if (!empty($member['passport_no'])) {
                $len = strlen($member['passport_no']);
                $member['passport_no'] = $len > 5 ? str_repeat('*', $len - 5) . substr($member['passport_no'], -5) : str_repeat('*', $len);
            }
        }
        return $member;
    }

    // ── CRUD METHODS ─────────────────────────────────────────────────
    public function index(): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'members.view', 'group' => 'groups.view_members', 'personal' => 'members.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to view members.');
        
        ['page' => $page, 'limit' => $limit] = $this->getPaginationParams();

        $filters = [
            'status'       => $this->getQuery('status', ''),
            'search'       => $this->getQuery('search', ''),
            'group_id'     => $this->getQuery('group', ''),
            'kyc_verified' => $this->getQuery('kyc', ''),
        ];

        // ENFORCE: Apply data scope to model filters
        $scope = $this->resolveDataScope($perms);
        if ($scope['type'] === 'personal') {
            $filters['member_id'] = $scope['member_id'];
        } elseif ($scope['type'] === 'group') {
            $filters['group_id'] = $scope['group_id'];
        } elseif ($scope['type'] === 'none') {
            $filters['member_id'] = -1; // Force empty result
        }

        $result = $this->model->getList($page, $limit, $filters);
        
        // ENFORCE: Mask sensitive fields in the list
        if (!empty($result['data'])) {
            $result['data'] = array_map([$this, 'maskMemberSensitiveFields'], $result['data']);
        }
        
        $stats  = $this->model->getStats();

        $this->view('members/index', array_merge(
            $this->prepareViewData('Members', 'members'),
            ['result' => $result, 'stats' => $stats]
        ));
    }

    public function create(): void
    {
        $this->auth->requirePermission('members.create');
        $savings_groups = $this->db->fetchAll("SELECT id, name FROM savings_groups WHERE status='active' ORDER BY name");
        
        $this->view('members/create', array_merge(
            $this->prepareViewData('Register Member', 'members', ['Register' => null]),
            ['savings_groups' => $savings_groups]
        ));
    }

    public function store(): void
    {
        $this->auth->requirePermission('members.create');
        $this->verifyCsrf();
        $data = $this->getPost();
        $missing = $this->validateRequired($data, ['first_name', 'last_name', 'phone', 'membership_date']);
        if ($missing) $this->jsonError('Please complete all required fields.', array_fill_keys($missing, 'Required.'), 422);

        $this->db->beginTransaction();
        try {
            $memberNo = \App\Helpers\MemberSequence::nextFormatted($data['membership_date']);

            $memberData = [
                'member_no'               => $memberNo,
                'first_name'              => $data['first_name'],
                'last_name'               => $data['last_name'],
                'middle_name'             => $data['middle_name'] ?? null,
                'gender'                  => $data['gender'] ?? null,
                'date_of_birth'           => !empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
                'phone'                   => $data['phone'],
                'phone_alt'               => $data['phone_alt'] ?? null,
                'email'                   => !empty($data['email']) ? strtolower($data['email']) : null,
                'address'                 => $data['address'] ?? null,
                'district'                => $data['district'] ?? null,
                'occupation'              => $data['occupation'] ?? null,
                'employer'                => $data['employer'] ?? null,
                'national_id'             => !empty($data['national_id']) ? $data['national_id'] : null,
                'passport_no'             => !empty($data['passport_no']) ? $data['passport_no'] : null,
                'next_of_kin_name'        => $data['next_of_kin_name'] ?? null,
                'next_of_kin_phone'       => $data['next_of_kin_phone'] ?? null,
                'next_of_kin_relationship'=> $data['next_of_kin_relationship'] ?? null,
                'group_id'                => !empty($data['group_id']) ? (int)$data['group_id'] : null,
                'membership_date'         => $data['membership_date'],
                'status'                  => 'active',
                'created_by'              => $_SESSION['user_id'],
            ];

            $memberId = $this->model->create($memberData);

            if (!empty($_FILES['avatar']['name'])) {
                $memberData['avatar'] = StorageHelper::upload($_FILES['avatar'], 'avatars', ALLOWED_IMAGE_TYPES, 2 * 1024 * 1024, 'avatar_');
                $this->model->update($memberId, ['avatar' => $memberData['avatar']]);
            }
            if (!empty($_FILES['id_front']['name'])) {
                $memberData['id_front'] = StorageHelper::upload($_FILES['id_front'], 'kyc', ALLOWED_DOC_TYPES, 2 * 1024 * 1024, 'id_front_');
                $this->model->update($memberId, ['id_front' => $memberData['id_front']]);
            }
            if (!empty($_FILES['id_back']['name'])) {
                $memberData['id_back'] = StorageHelper::upload($_FILES['id_back'], 'kyc', ALLOWED_DOC_TYPES, 2 * 1024 * 1024, 'id_back_');
                $this->model->update($memberId, ['id_back' => $memberData['id_back']]);
            }

            $accountNo = \App\Helpers\SavingsAccountSequence::nextFormatted($data['membership_date']);

            $this->db->execute(
                "INSERT INTO savings_accounts (account_no, member_id, account_type, balance, status, opened_at, created_by, created_at, updated_at)
                 VALUES (?, ?, 'regular', 0, 'active', ?, ?, NOW(), NOW())",
                [$accountNo, $memberId, $data['membership_date'], $_SESSION['user_id']]
            );

            // NEW: Auto-create or link User Account
            $nin = $data['national_id'] ?? null;
            $passport = $data['passport_no'] ?? null;
            $email = $data['email'] ?? null;

            $existingUserId = null;
            if ($nin) $existingUserId = $this->db->fetchColumn("SELECT id FROM users WHERE nin = ? LIMIT 1", [$nin]);
            if (!$existingUserId && $passport) $existingUserId = $this->db->fetchColumn("SELECT id FROM users WHERE passport_number = ? LIMIT 1", [$passport]);
            if (!$existingUserId && $email) $existingUserId = $this->db->fetchColumn("SELECT id FROM users WHERE email = ? LIMIT 1", [strtolower($email)]);

            if ($existingUserId) {
                // Check if user is already linked to a different member
                $currentMemberLink = $this->db->fetchColumn("SELECT member_id FROM users WHERE id = ?", [$existingUserId]);
                if ($currentMemberLink && (int)$currentMemberLink !== (int)$memberId) {
                    throw new \Exception("User account (ID: {$existingUserId}) is already linked to another member (ID: {$currentMemberLink}). Cannot proceed.");
                }
                // Link existing user to this member
                $this->db->execute("UPDATE users SET member_id = ? WHERE id = ?", [$memberId, $existingUserId]);
                $this->db->execute("UPDATE members SET user_id = ? WHERE id = ?", [$existingUserId, $memberId]);
                $this->logAudit('user_linked_to_member', 'users', $existingUserId, 'User', "User ID {$existingUserId} linked to new member {$memberNo}");
            } else {
                // Create new user account with default member role (role_id = 9)
                $tempPass = \App\Helpers\Security::generateToken(6);
                $userEmail = $email ? strtolower($email) : strtolower($memberNo) . '@akabbo.local'; // Fallback for NOT NULL constraint
                
                $newUserId = (int)$this->db->insert(
                    "INSERT INTO users (first_name, last_name, email, phone, nin, passport_number, role_id, password_hash, status, must_change_password, member_id, created_by) 
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
                    [
                        $data['first_name'], $data['last_name'], $userEmail, $data['phone'], 
                        $nin ?? '', $passport, 9, // 9 is the 'member' role ID
                        \App\Helpers\Security::hashPassword($tempPass), 'active', 1, $memberId, $_SESSION['user_id']
                    ]
                );
                
                $this->db->execute("UPDATE members SET user_id = ? WHERE id = ?", [$newUserId, $memberId]);
                
                $this->notif->dispatch(
                    $newUserId,
                    'account_created',
                    'Welcome to Akabbo Social Fund',
                    "Dear {$data['first_name']}, your member account has been created. Your temporary password is: {$tempPass}. Please log in and change your password immediately."
                );
                
                $this->logAudit('user_created_for_member', 'users', $newUserId, 'User', "User account created and linked to member {$memberNo}");
            }

            $this->logAudit('member_created', 'members', $memberId, 'Member', "Member {$memberNo} created", null, json_encode($memberData));

            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/members/' . $memberId], 'Member registered successfully!');
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->jsonError('Failed to register member: ' . $e->getMessage(), null, 500);
        }
    }

    public function show(int $id): void
    {
        if (!$this->canAccessMemberRecord($id)) {
            $this->flash('error', 'You do not have permission to view this member profile.');
            $this->redirect('/dashboard');
        }

        $member = $this->model->getProfile($id);
        if (!$member) { 
            $this->flash('error', 'Member not found.'); 
            $this->redirect('/members'); 
        }
        
        // ENFORCE: Mask sensitive fields if not owner
        $member = $this->maskMemberSensitiveFields($member);
        
        $user = $this->auth->user();
        $userMemberId = $user['member_id'] ?? null;
        
        if ($userMemberId == $id && $this->auth->can('members.view_own')) {
            $activePage = 'my-profile';
        } elseif ($this->auth->can('members.view')) {
            $activePage = 'members-show';
        } else {
            $activePage = '';
        }
        
        $savingsAccounts = $this->db->fetchAll("SELECT * FROM savings_accounts WHERE member_id = ? AND status != 'closed' ORDER BY balance DESC", [$id]);
        $loans = $this->db->fetchAll("SELECT l.*, lp.name AS product_name FROM loans l JOIN loan_products lp ON lp.id = l.loan_product_id WHERE l.member_id = ? AND l.deleted_at IS NULL ORDER BY l.application_date DESC", [$id]);
        $transactions = $this->db->fetchAll("SELECT * FROM transactions WHERE member_id = ? ORDER BY transaction_date DESC, id DESC LIMIT 50", [$id]);
        $shares = $this->shareModel->getByMember($id); 
        $shareTxns = $this->shareModel->getTransactions(1, 20, ['member_id' => $id])['data'] ?? [];
        
        $baseRate = (float)($this->getSettings()['loan_interest_rate'] ?? 10);
        $baseMultiplier = (int)($this->getSettings()['max_loan_multiplier'] ?? 3);
        $sharePrivileges = $this->shareModel->getLoanPrivileges($id, $baseRate, $baseMultiplier);

        $this->view('members/show', array_merge(
            $this->prepareViewData($member['first_name'] . ' ' . $member['last_name'], $activePage, ['Members' => APP_URL.'/members', 'Profile' => null]),
            [
                'member'          => $member, 
                'savingsAccounts' => $savingsAccounts,
                'loans'           => $loans,
                'transactions'    => $transactions,
                'shares'          => $shares,
                'shareTxns'       => $shareTxns,
                'privileges'      => $sharePrivileges, 
            ]
        ));
    }

    public function edit(int $id): void
    {
        // REMOVE THIS LINE: $this->auth->requirePermission('members.edit');
        
        $member = $this->model->find($id);
        if (!$member) { $this->flash('error', 'Member not found.'); $this->redirect('/members'); }

        $user = $this->auth->user();
        $isOwnProfile = !empty($user['member_id']) && (int)$user['member_id'] === $id;

        // This check correctly handles both admins and own-profile edits
        if (!$this->auth->can('members.edit') && !$isOwnProfile) {
            $this->flash('error', 'You do not have permission to edit this member.');
            $this->redirect('/members/' . $id);
        }
        
        $savings_groups = $this->db->fetchAll("SELECT id, name FROM savings_groups WHERE status='active' ORDER BY name");

        $this->view('members/edit', array_merge(
            $this->prepareViewData('Edit Member', 'members', ['Members' => APP_URL.'/members', 'Edit' => null]),
            ['member' => $member, 'savings_groups' => $savings_groups]
        ));
    }

    public function update(int $id): void
    {
        $user = $this->auth->user();
        $isOwnProfile = !empty($user['member_id']) && (int)$user['member_id'] === $id;

        // ENFORCE: Centralized Scope Gatekeeper & Resolver
        $perms = ['global' => 'members.edit', 'group' => 'groups.view_members', 'personal' => 'members.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to update this record.');
        
        $scope = $this->resolveDataScope($perms);
        
        if ($scope['type'] === 'none' || ($scope['type'] === 'personal' && !$isOwnProfile)) {
            $this->jsonError('You do not have permission to update this record.', null, 403);
        }
        if ($scope['type'] === 'group') {
            $memberGroupId = $this->db->fetchColumn("SELECT group_id FROM members WHERE id = ?", [$id]);
            if (!$memberGroupId || (int)$memberGroupId !== (int)$scope['group_id']) {
                $this->jsonError('You do not have permission to update members outside your group.', null, 403);
            }
        }

        $this->verifyCsrf();
        $oldData = $this->model->getOldValues($id);
        if (!$oldData) $this->jsonError('Member not found.', null, 404);

        $data = $this->getPost();

        // ENFORCE: Field-level RBAC (Members can only edit basic info)
        if ($isOwnProfile && !$this->auth->can('members.edit')) {
            $updateData = [
                'first_name'         => $data['first_name'] ?? $oldData['first_name'],
                'last_name'          => $data['last_name'] ?? $oldData['last_name'],
                'phone'              => $data['phone'] ?? $oldData['phone'],
                'email'              => !empty($data['email']) ? strtolower($data['email']) : $oldData['email'],
                'address'            => $data['address'] ?? $oldData['address'],
                'next_of_kin_name'   => $data['next_of_kin_name'] ?? $oldData['next_of_kin_name'],
                'next_of_kin_phone'  => $data['next_of_kin_phone'] ?? $oldData['next_of_kin_phone'],
            ];
        } else {
            $updateData = [
                'first_name'               => $data['first_name'],
                'last_name'                => $data['last_name'],
                'middle_name'              => $data['middle_name'] ?? $oldData['middle_name'],
                'gender'                   => $data['gender'],
                'date_of_birth'            => !empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
                'phone'                    => $data['phone'],
                'phone_alt'                => $data['phone_alt'] ?? null,
                'email'                    => !empty($data['email']) ? strtolower($data['email']) : null,
                'address'                  => $data['address'] ?? null,
                'district'                 => $data['district'] ?? null,
                'occupation'               => $data['occupation'] ?? null,
                'employer'                 => $data['employer'] ?? null,
                'national_id'              => !empty($data['national_id']) ? $data['national_id'] : $oldData['national_id'],
                'passport_no'              => !empty($data['passport_no']) ? $data['passport_no'] : $oldData['passport_no'],
                'next_of_kin_name'         => $data['next_of_kin_name'] ?? $oldData['next_of_kin_name'],
                'next_of_kin_phone'        => $data['next_of_kin_phone'] ?? $oldData['next_of_kin_phone'],
                'next_of_kin_relationship' => $data['next_of_kin_relationship'] ?? $oldData['next_of_kin_relationship'],
                'group_id'                 => !empty($data['group_id']) ? (int)$data['group_id'] : null,
                'status'                   => $data['status'] ?? $oldData['status'],
            ];
        }

        $this->db->beginTransaction();
        $newFiles = []; // Track new files to clean up if transaction fails
        
        try {
            // 1. Upload new files (Store paths temporarily)
            if (!empty($_FILES['avatar']['name'])) {
                $newFiles['avatar'] = StorageHelper::upload($_FILES['avatar'], 'avatars', ALLOWED_IMAGE_TYPES, 2 * 1024 * 1024, 'avatar_');
                $updateData['avatar'] = $newFiles['avatar'];
            }
            if (!empty($_FILES['id_front']['name'])) {
                $newFiles['id_front'] = StorageHelper::upload($_FILES['id_front'], 'kyc', ALLOWED_DOC_TYPES, 2 * 1024 * 1024, 'id_front_');
                $updateData['id_front'] = $newFiles['id_front'];
            }
            if (!empty($_FILES['id_back']['name'])) {
                $newFiles['id_back'] = StorageHelper::upload($_FILES['id_back'], 'kyc', ALLOWED_DOC_TYPES, 2 * 1024 * 1024, 'id_back_');
                $updateData['id_back'] = $newFiles['id_back'];
            }

            // 2. Update Member Record
            $this->model->update($id, $updateData);

            // 3. ENHANCEMENT: Sync critical fields to the linked User account
            if (!empty($oldData['user_id'])) {
                $userSyncData = [];
                if (isset($updateData['first_name'])) $userSyncData['first_name'] = $updateData['first_name'];
                if (isset($updateData['last_name']))  $userSyncData['last_name']  = $updateData['last_name'];
                if (isset($updateData['phone']))      $userSyncData['phone']      = $updateData['phone'];
                if (isset($updateData['email']))      $userSyncData['email']      = $updateData['email'];
                
                // Only admins can update NIN/Passport, so sync them if changed
                if (!$isOwnProfile || $this->auth->can('members.edit')) {
                    if (isset($updateData['national_id'])) $userSyncData['nin'] = $updateData['national_id'];
                    if (isset($updateData['passport_no'])) $userSyncData['passport_number'] = $updateData['passport_no'];
                }

                if (!empty($userSyncData)) {
                    $setClauses = [];
                    $params = [];
                    foreach ($userSyncData as $col => $val) {
                        $setClauses[] = "{$col} = ?";
                        $params[] = $val;
                    }
                    $params[] = $oldData['user_id'];
                    $this->db->execute("UPDATE users SET " . implode(', ', $setClauses) . " WHERE id = ?", $params);
                }
            }

            // 4. FIX: Storage Leak - Delete old files ONLY after DB success
            if (isset($newFiles['avatar']) && !empty($oldData['avatar'])) StorageHelper::delete('avatars', $oldData['avatar']);
            if (isset($newFiles['id_front']) && !empty($oldData['id_front'])) StorageHelper::delete('kyc', $oldData['id_front']);
            if (isset($newFiles['id_back']) && !empty($oldData['id_back'])) StorageHelper::delete('kyc', $oldData['id_back']);

            // 5. ENFORCE: Notify member if an admin updated their profile
            if (!$isOwnProfile && !empty($oldData['user_id']) && (int)$oldData['user_id'] !== (int)$_SESSION['user_id']) {
                $this->notif->dispatch(
                    (int)$oldData['user_id'],
                    'member_profile_updated',
                    'Profile Information Updated',
                    "Dear {$oldData['first_name']}, your member profile information has been updated by an administrator. If you did not authorize this change, please contact support immediately."
                );
            }

            $this->logAudit('member_updated', 'members', $id, 'Member', "Member {$oldData['member_no']} updated", json_encode($oldData), json_encode($updateData));

            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/members/' . $id], 'Member updated successfully!');
            
        } catch (\Exception $e) {
            $this->db->rollback();
            
            // FIX: Clean up newly uploaded files if DB transaction failed (Prevents orphaned files)
            foreach ($newFiles as $type => $filename) {
                $folder = ($type === 'avatar') ? 'avatars' : 'kyc';
                StorageHelper::delete($folder, $filename);
            }
            
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('member_update_failed', 'Member Update Failed', "Failed to update member ID {$id} ({$oldData['member_no']}): " . $e->getMessage());
            
            $this->jsonError('Failed to update member: ' . $e->getMessage(), null, 500);
        }
    }

    public function delete(int $id): void
    {
        $this->auth->requirePermission('members.delete');
        $this->verifyCsrf();

        $member = $this->model->find($id);
        if (!$member) $this->jsonError('Member not found.', null, 404);

        $activeLoans = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM loans WHERE member_id = ? AND status IN ('active','disbursed','pending')", [$id]);
        if ($activeLoans > 0) {
            $this->jsonError("Cannot delete member with {$activeLoans} active loan(s). Close loans first.", null, 409);
        }

        $this->model->delete($id, $_SESSION['user_id']);

        if (!empty($member['user_id'])) {
            $this->notif->dispatch(
                (int)$member['user_id'],
                'member_terminated',
                'Membership Terminated',
                "Dear {$member['first_name']}, your membership has been terminated. Please contact administration for further details regarding your account."
            );
        }

        $this->logAudit('member_deleted', 'members', $id, 'Member', "Member {$member['member_no']} moved to trash", json_encode($member), null);
        $this->jsonSuccess(['redirect' => APP_URL . '/members'], 'Member moved to trash.');
    }

    public function disable(int $id): void
    {
        $this->auth->requirePermission('members.edit');
        $this->verifyCsrf();

        $member = $this->model->find($id);
        if (!$member) $this->jsonError('Member not found.', null, 404);
        if (in_array($member['status'], ['inactive', 'suspended'])) {
            $this->jsonError('Member is already disabled.', null, 400);
        }

        $oldData = $this->model->getOldValues($id);
        $this->model->update($id, ['status' => 'inactive']);

        if (!empty($member['user_id'])) {
            $this->notif->dispatch(
                (int)$member['user_id'],
                'member_disabled',
                'Account Disabled',
                "Dear {$member['first_name']}, your membership has been temporarily disabled. Please contact administration for further details."
            );
        }

        $this->logAudit('member_disabled', 'members', $id, 'Member', "Member {$member['member_no']} disabled", json_encode($oldData), json_encode(['status' => 'inactive']));
        $this->jsonSuccess(['redirect' => APP_URL . '/members/' . $id], 'Member disabled successfully.');
    }

    public function activate(int $id): void
    {
        $this->auth->requirePermission('members.edit');
        $this->verifyCsrf();

        $member = $this->model->find($id);
        if (!$member) $this->jsonError('Member not found.', null, 404);
        if ($member['status'] === 'active') {
            $this->jsonError('Member is already active.', null, 400);
        }

        $oldData = $this->model->getOldValues($id);
        $this->model->update($id, ['status' => 'active']);

        if (!empty($member['user_id'])) {
            $this->notif->dispatch(
                (int)$member['user_id'],
                'member_activated',
                'Account Reactivated',
                "Dear {$member['first_name']}, your membership has been successfully reactivated. You now have full access to all services."
            );
        }

        $this->logAudit('member_activated', 'members', $id, 'Member', "Member {$member['member_no']} activated", json_encode($oldData), json_encode(['status' => 'active']));
        $this->jsonSuccess(['redirect' => APP_URL . '/members/' . $id], 'Member activated successfully.');
    }

    public function search(): void
    {
        $this->auth->requireAuth();
        $query = $this->getQuery('q', '');
        
        if (strlen($query) < 2) {
            $this->jsonSuccess([]);
            return;
        }

        // ENFORCE: Centralized Scope Gatekeeper & Resolver
        $perms = ['global' => 'members.view', 'group' => 'groups.view_members', 'personal' => 'members.view_own'];
        $this->requireScopeAccess($perms);
        $scope = $this->resolveDataScope($perms);

        $scopeFilters = [];
        if ($scope['type'] === 'personal') {
            $scopeFilters['member_id'] = $scope['member_id'];
        } elseif ($scope['type'] === 'group') {
            $scopeFilters['group_id'] = $scope['group_id'];
        } elseif ($scope['type'] === 'none') {
            $this->jsonSuccess([]); // Force empty result
            return;
        }

        $members = $this->model->search($query, 15, $scopeFilters);
        if (empty($members)) {
            $this->jsonSuccess([]);
            return;
        }

        $ids = array_column($members, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        $savingsMap = [];
        $savingsData = $this->db->fetchAll(
            "SELECT member_id, COALESCE(SUM(balance), 0) AS total_savings 
             FROM savings_accounts 
             WHERE member_id IN ($placeholders) AND status='active' 
             GROUP BY member_id", 
            $ids
        );
        foreach ($savingsData as $row) {
            $savingsMap[$row['member_id']] = (float)$row['total_savings'];
        }

        $loansMap = [];
        $loansData = $this->db->fetchAll(
            "SELECT member_id, COUNT(*) AS active_loans, COALESCE(SUM(balance_outstanding), 0) AS active_loan_balance 
             FROM loans 
             WHERE member_id IN ($placeholders) AND status IN ('active','disbursed') 
             GROUP BY member_id", 
            $ids
        );
        foreach ($loansData as $row) {
            $loansMap[$row['member_id']] = [
                'active_loans' => (int)$row['active_loans'],
                'active_loan_balance' => (float)$row['active_loan_balance']
            ];
        }

        $sharesMap = [];
        $sharesData = $this->db->fetchAll(
            "SELECT member_id, shares_held 
             FROM member_shares 
             WHERE member_id IN ($placeholders) AND status='active'", 
            $ids
        );
        foreach ($sharesData as $row) {
            $sharesMap[$row['member_id']] = (int)$row['shares_held'];
        }

        $multiplier = (int)($this->getSettings()['max_loan_multiplier'] ?? 3);
        
        $result = array_map(function ($m) use ($multiplier, $savingsMap, $loansMap, $sharesMap) {
            // ENFORCE: Mask sensitive fields in search results
            $m = $this->maskMemberSensitiveFields($m);
            
            $name = trim($m['first_name'] . ' ' . $m['last_name']);
            $id = (int)$m['id'];
            
            $savings = $savingsMap[$id] ?? 0.0;
            $loanData = $loansMap[$id] ?? ['active_loans' => 0, 'active_loan_balance' => 0.0];
            $sharesHeld = $sharesMap[$id] ?? 0;

            return [
                'id'                  => $id,
                'full_name'           => $name,
                'member_no'           => $m['member_no'],
                'phone'               => $m['phone'],
                'status'              => $m['status'],
                'group_id'            => (int)($m['group_id'] ?? 0),
                'initials'            => Format::initials($name),
                'total_savings'       => $savings,
                'total_savings_fmt'   => Format::currency($savings),
                'max_loan_fmt'        => Format::currency($savings * $multiplier),
                'active_loans'        => $loanData['active_loans'],
                'active_loan_balance' => $loanData['active_loan_balance'],
                'active_loan_bal_fmt' => Format::currency($loanData['active_loan_balance']),
                'shares_held'         => $sharesHeld,
                'is_shareholder'      => $sharesHeld > 0,
            ];
        }, $members);
        
        $this->jsonSuccess($result);
    }

    public function export(): void
    {
        $this->auth->requirePermission('members.export');
        
        // ENFORCE: Centralized Scope Gatekeeper & Resolver
        $perms = ['global' => 'members.export', 'group' => 'groups.view_members', 'personal' => 'members.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to export members.');
        $scope = $this->resolveDataScope($perms);

        $filters = ['search' => $this->getQuery('search', ''), 'status' => $this->getQuery('status', '')];
        
        if ($scope['type'] === 'personal') {
            $filters['member_id'] = $scope['member_id'];
        } elseif ($scope['type'] === 'group') {
            $filters['group_id'] = $scope['group_id'];
        } elseif ($scope['type'] === 'none') {
            $filters['member_id'] = -1; // Force empty result
        }

        $result  = $this->model->getList(1, 10000, $filters);
        $members = $result['data'];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="akabbo-members-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Member No', 'First Name', 'Last Name', 'Gender', 'Phone', 'Email', 'District', 'Total Savings', 'Status', 'KYC', 'Membership Date']);

        foreach ($members as $m) {
            fputcsv($out, [
                $m['member_no'], $m['first_name'], $m['last_name'], $m['gender'], $m['phone'], 
                $m['email'] ?? '', $m['district'] ?? '', number_format($m['total_savings'] ?? 0, 2),
                $m['status'], $m['kyc_verified'] ? 'Yes' : 'No', Format::date($m['membership_date']),
            ]);
        }
        fclose($out);
        
        $this->logAudit('members_exported', 'members', null, null, count($members) . ' members exported', null, json_encode($filters));
        exit;
    }

    public function verifyKyc(int $id): void
    {
        $this->auth->requirePermission('members.kyc-verify'); 
        $this->verifyCsrf();
        
        $member = $this->model->find($id);
        if (!$member) $this->jsonError('Member not found.', null, 404);
        if ($member['kyc_verified']) $this->jsonError('Member is already KYC verified.', null, 400);

        $this->db->execute("UPDATE members SET kyc_verified = 1, kyc_verified_by = ?, kyc_verified_at = NOW() WHERE id = ?", [$_SESSION['user_id'], $id]);

        if (!empty($member['user_id'])) {
            $this->notif->dispatch(
                (int)$member['user_id'],
                'kyc_verified',
                'KYC Verification Successful',
                "Dear {$member['first_name']}, your KYC documents have been successfully verified by our team. You now have full access to all member services."
            );
        }

        $this->logAudit('kyc_verified', 'members', $id, 'Member', "KYC verified for {$member['member_no']} by User ID {$_SESSION['user_id']}");
        $this->jsonSuccess(['redirect' => APP_URL . '/members/' . $id], 'Member KYC verified successfully.');
    }

    public function sendKycReminder(int $id): void
    {
        $this->auth->requirePermission('members.edit');
        $this->verifyCsrf();

        $member = $this->model->find($id);
        if (!$member) $this->jsonError('Member not found.', null, 404);
        if ($member['kyc_verified']) $this->jsonError('Member is already KYC verified.', null, 400);

        $reminderService = new \App\Services\ReminderService();
        if (!empty($member['user_id'])) {
            $reminderService->sendCustomReminder([$member['user_id']], 'kyc_reminder_personal', 'Action Required: Complete Your KYC', "Dear {$member['first_name']}, please complete your KYC verification to activate full services.");
        }

        $this->logAudit('kyc_reminder_sent', 'members', $id, 'Member', "Manual KYC reminder sent to {$member['member_no']}");
        $this->jsonSuccess(null, 'KYC reminder sent successfully.');
    }

    public function statement(int $id): void
    {
        if (!$this->canAccessMemberRecord($id)) {
            $this->flash('error', 'You do not have permission to view this statement.');
            $this->redirect('/dashboard');
        }

        $member = $this->model->getProfile($id);
        if (!$member) {
            $this->flash('error', 'Member not found.');
            $this->redirect('/members');
        }

        $user = $this->auth->user();
        $isOwnAccount = !empty($user['member_id']) && (int)$user['member_id'] === $id;

        if ($isOwnAccount) {
            $feeService = new \App\Services\ServiceFeeService();
            $result = $feeService->processFee($id, 'statement_request', $_SESSION['user_id']);
            
            if (!$result['success']) {
                $this->flash('error', $result['message'] ?? 'Failed to process statement request fee.');
                $this->redirect('/members/' . $id);
            }
        }

        $from = $this->getQuery('from', date('Y-01-01'));
        $to   = $this->getQuery('to', date('Y-m-d'));

        $savingsAccounts = $this->db->fetchAll("SELECT * FROM savings_accounts WHERE member_id = ? AND status != 'closed'", [$id]);
        $loans = $this->db->fetchAll("SELECT l.*, lp.name AS product_name FROM loans l JOIN loan_products lp ON lp.id = l.loan_product_id WHERE l.member_id = ? AND l.deleted_at IS NULL ORDER BY l.application_date DESC", [$id]);
        $transactions = $this->db->fetchAll("SELECT * FROM transactions WHERE member_id = ? AND transaction_date BETWEEN ? AND ? ORDER BY transaction_date ASC, id ASC", [$id, $from, $to]);
        $pendingDebits = $this->db->fetchAll("SELECT * FROM pending_debits WHERE member_id = ? AND settled_at IS NULL ORDER BY created_at ASC", [$id]);

        $this->view('members/statement', [
            'member' => $member, 'savingsAccounts' => $savingsAccounts, 'loans' => $loans,
            'transactions' => $transactions, 'pendingDebits' => $pendingDebits, 'from' => $from, 'to' => $to,
        ], null);
    }
}