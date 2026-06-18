<?php
// We should notify members use (dispatch instead of sendToUser) with relevant messages for all actions of this contoller and any other event of success and failure. Avoid using default `prompt` instead use modals. Enforce logAudit and (RBAC + IDOR). Determine the user's data scope (Global, Group, Personal, or None)
namespace App\Controllers;

use App\Helpers\StorageHelper;
use App\Models\Group;
use App\Services\NotificationService;

class GroupController extends BaseController
{
    private Group $model;
    private NotificationService $notif;

    public function __construct()
    {
        parent::__construct();
        $this->auth->requireAuth();
        $this->model = new Group();
        $this->notif = new NotificationService();
    }

    // ── CRUD METHODS ───────────────────────────────────────────────

    public function index(): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'groups.view', 'group' => 'groups.view_members'];
        $this->requireScopeAccess($perms, 'You do not have permission to view groups.');
        $scope = $this->resolveDataScope($perms);

        // ENFORCE: Pass scope to model to filter data at the database level
        $groups = $this->model->getWithStats($scope);
        
        // ENFORCE: Audit log for viewing
        $this->logAudit('groups_viewed', 'savings_groups', null, null, "Viewed groups list");

        $this->view('groups/index', array_merge(
            $this->prepareViewData('Savings Groups', 'groups'),
            ['groups' => $groups]
        ));
    }

    public function create(): void
    {
        $this->auth->requirePermission('groups.create');
        
        // ENFORCE: Scope the member dropdown so group managers only see their own members
        $perms = ['global' => 'groups.view', 'group' => 'groups.view_members'];
        $scope = $this->resolveDataScope($perms);
        $scopeCondition = $this->buildScopeCondition($scope, 'id');

        $members = $this->db->fetchAll("SELECT id, member_no, first_name, last_name FROM members WHERE status='active' AND deleted_at IS NULL {$scopeCondition} ORDER BY first_name, last_name");
        
        $this->view('groups/create', array_merge(
            $this->prepareViewData('Create Group', 'groups', ['Groups' => APP_URL.'/groups', 'Create' => null]),
            ['members' => $members]
        ));
    }

    public function store(): void
    {
        $this->auth->requirePermission('groups.create');
        $this->verifyCsrf();
        $data = $this->getPost();
        if (empty($data['name'])) $this->jsonError('Group name is required.', null, 422);
        
        $this->db->beginTransaction();
        $newFileName = null;
        try {
            $groupData = [
                'group_code'       => $this->model->generateCode(),
                'name'             => $data['name'],
                'description'      => $data['description'] ?? null,
                'meeting_schedule' => $data['meeting_schedule'] ?? null,
                'chairperson_id'   => !empty($data['chairperson_id']) ? (int)$data['chairperson_id'] : null,
                'treasurer_id'     => !empty($data['treasurer_id']) ? (int)$data['treasurer_id'] : null,
                'secretary_id'     => !empty($data['secretary_id']) ? (int)$data['secretary_id'] : null,
                'status'           => 'active',
                'created_by'       => $_SESSION['user_id'],
            ];
            $groupId = $this->model->create($groupData);
            
            if (!empty($_FILES['avatar']['name'])) {
                $newFileName = StorageHelper::upload($_FILES['avatar'], 'avatars', ALLOWED_IMAGE_TYPES, 2 * 1024 * 1024, 'group_');
                $this->model->update($groupId, ['avatar' => $newFileName]);
            }

            // ENFORCE: Notify assigned leaders via multi-channel dispatch
            $roles = ['chairperson_id' => 'Chairperson', 'treasurer_id' => 'Treasurer', 'secretary_id' => 'Secretary'];
            foreach ($roles as $field => $roleName) {
                if (!empty($groupData[$field])) {
                    $leader = $this->db->fetchOne("SELECT first_name, user_id FROM members WHERE id = ?", [$groupData[$field]]);
                    if ($leader && !empty($leader['user_id'])) {
                        $this->notif->dispatch(
                            (int)$leader['user_id'],
                            'assigned_group_role',
                            "Assigned as {$roleName}",
                            "Dear {$leader['first_name']}, you have been assigned as the {$roleName} of the new savings group: '{$groupData['name']}'."
                        );
                    }
                }
            }
            
            $this->logAudit('group_created', 'savings_groups', $groupId, 'Group', "Group {$data['name']} created", null, json_encode($groupData));
            
            // ENFORCE: Notify admins of success
            $this->notifyAdmins('group_created', 'New Savings Group Created', "A new savings group '{$data['name']}' has been created by an administrator.");
            
            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/groups/' . $groupId], 'Group created successfully!');
        } catch (\Exception $e) {
            $this->db->rollback();
            // FIX: Clean up uploaded file if DB transaction failed
            if ($newFileName) StorageHelper::delete('avatars', $newFileName);
            
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('group_creation_failed', 'Group Creation Failed', "Failed to create group '{$data['name']}': " . $e->getMessage());
            $this->jsonError('Database error: ' . $e->getMessage(), null, 500);
        }
    }

    public function show(int $id): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'groups.view', 'group' => 'groups.view_members'];
        $this->requireScopeAccess($perms, 'You do not have permission to view groups.');
        $scope = $this->resolveDataScope($perms);

        $group = $this->model->find($id);
        if (!$group) { $this->flash('error', 'Group not found.'); $this->redirect('/groups'); }
        
        // ENFORCE: IDOR / Scope Guard (Prevents group managers from viewing other groups)
        if ($scope['type'] === 'group' && (int)$group['id'] !== (int)$scope['group_id']) {
            $this->flash('error', 'You do not have permission to view this group.');
            $this->redirect('/groups');
        }
        
        $members = $this->model->getMembers($id);
        
        // ENFORCE: Audit log for viewing sensitive group data
        $this->logAudit('group_viewed', 'savings_groups', $id, 'Group', "Viewed group {$group['name']}");

        $this->view('groups/show', array_merge(
            $this->prepareViewData($group['name'], 'groups', ['Groups' => APP_URL.'/groups', $group['name'] => null]),
            ['group' => $group, 'members' => $members]
        ));
    }

    public function edit(int $id): void
    {
        $this->auth->requirePermission('groups.edit');
        
        // ENFORCE: Scope the member dropdown
        $perms = ['global' => 'groups.view', 'group' => 'groups.view_members'];
        $scope = $this->resolveDataScope($perms);
        $scopeCondition = $this->buildScopeCondition($scope, 'id');

        $group = $this->model->find($id);
        if (!$group) { $this->flash('error', 'Group not found.'); $this->redirect('/groups'); }
        
        // ENFORCE: IDOR / Scope Guard
        if ($scope['type'] === 'group' && (int)$group['id'] !== (int)$scope['group_id']) {
            $this->flash('error', 'You do not have permission to edit this group.');
            $this->redirect('/groups');
        }
        
        $members = $this->db->fetchAll("SELECT id, member_no, first_name, last_name FROM members WHERE status='active' AND deleted_at IS NULL {$scopeCondition} ORDER BY first_name, last_name");
        
        $this->view('groups/edit', array_merge(
            $this->prepareViewData('Edit: ' . $group['name'], 'groups', ['Groups' => APP_URL.'/groups', $group['name'] => APP_URL.'/groups/'.$id, 'Edit' => null]),
            ['group' => $group, 'members' => $members]
        ));
    }

    public function update(int $id): void
    {
        $this->auth->requirePermission('groups.edit');
        $this->verifyCsrf();
        
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'groups.view', 'group' => 'groups.view_members'];
        $scope = $this->resolveDataScope($perms);

        $oldData = $this->model->getOldValues($id);
        if (!$oldData) $this->jsonError('Group not found.', null, 404);
        
        // ENFORCE: IDOR / Scope Guard
        if ($scope['type'] === 'group' && (int)$oldData['id'] !== (int)$scope['group_id']) {
            $this->jsonError('You do not have permission to edit this group.', null, 403);
        }
        
        $data = $this->getPost();
        if (empty($data['name'])) $this->jsonError('Group name is required.', null, 422);
        
        $updateData = [
            'name'             => $data['name'],
            'description'      => $data['description'] ?? $oldData['description'],
            'meeting_schedule' => $data['meeting_schedule'] ?? $oldData['meeting_schedule'],
            'chairperson_id'   => !empty($data['chairperson_id']) ? (int)$data['chairperson_id'] : null,
            'treasurer_id'     => !empty($data['treasurer_id']) ? (int)$data['treasurer_id'] : null,
            'secretary_id'     => !empty($data['secretary_id']) ? (int)$data['secretary_id'] : null,
            'status'           => $data['status'] ?? $oldData['status'],
        ];
        
        $newFileName = null;
        $this->db->beginTransaction();
        try {
            if (!empty($_FILES['avatar']['name'])) {
                $newFileName = StorageHelper::upload($_FILES['avatar'], 'avatars', ALLOWED_IMAGE_TYPES, 2 * 1024 * 1024, 'group_');
                $updateData['avatar'] = $newFileName;
            }
            
            $this->model->update($id, $updateData);

            // FIX: Delete old avatar ONLY after DB success to prevent data loss
            if ($newFileName && !empty($oldData['avatar'])) {
                StorageHelper::delete('avatars', $oldData['avatar']);
            }

            // ENFORCE: Notify newly assigned leaders if they changed
            $roles = ['chairperson_id' => 'Chairperson', 'treasurer_id' => 'Treasurer', 'secretary_id' => 'Secretary'];
            foreach ($roles as $field => $roleName) {
                $oldLeaderId = $oldData[$field] ?? null;
                $newLeaderId = $updateData[$field] ?? null;
                
                if ($newLeaderId && $newLeaderId !== $oldLeaderId) {
                    $leader = $this->db->fetchOne("SELECT first_name, user_id FROM members WHERE id = ?", [$newLeaderId]);
                    if ($leader && !empty($leader['user_id'])) {
                        $this->notif->dispatch(
                            (int)$leader['user_id'],
                            'assigned_group_role',
                            "Assigned as {$roleName}",
                            "Dear {$leader['first_name']}, you have been assigned as the {$roleName} of the savings group: '{$updateData['name']}'."
                        );
                    }
                }
            }

            $this->logAudit('group_updated', 'savings_groups', $id, 'Group', "Group {$data['name']} updated", json_encode($oldData), json_encode($updateData));
            
            // ENFORCE: Notify admins of success
            $this->notifyAdmins('group_updated', 'Savings Group Updated', "The savings group '{$data['name']}' has been updated by an administrator.");
            
            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/groups/' . $id], 'Group updated successfully.');
        } catch (\Exception $e) {
            $this->db->rollback();
            // FIX: Clean up uploaded file if DB transaction failed
            if ($newFileName) StorageHelper::delete('avatars', $newFileName);
            
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('group_update_failed', 'Group Update Failed', "Failed to update group '{$data['name']}': " . $e->getMessage());
            $this->jsonError('Update failed: ' . $e->getMessage(), null, 500);
        }
    }

    public function delete(int $id): void
    {
        $this->auth->requirePermission('groups.delete');
        $this->verifyCsrf();
        
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'groups.view', 'group' => 'groups.view_members'];
        $scope = $this->resolveDataScope($perms);

        $group = $this->model->find($id);
        if (!$group) $this->jsonError('Group not found.', null, 404);
        
        // ENFORCE: IDOR / Scope Guard
        if ($scope['type'] === 'group' && (int)$group['id'] !== (int)$scope['group_id']) {
            $this->jsonError('You do not have permission to delete this group.', null, 403);
        }
        
        $memberCount = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM members WHERE group_id = ? AND deleted_at IS NULL", [$id]);
        if ($memberCount > 0) {
            $this->jsonError("Cannot delete group: {$memberCount} member(s) still assigned.", null, 409);
        }
        
        $this->db->beginTransaction();
        try {
            $oldData = $this->model->getOldValues($id);
            $this->model->delete($id, $_SESSION['user_id']);
            
            $this->logAudit('group_deleted', 'savings_groups', $id, 'Group', "Group {$group['name']} moved to trash", json_encode($oldData), null);
            
            // ENFORCE: Notify admins of success
            $this->notifyAdmins('group_deleted', 'Savings Group Deleted', "The savings group '{$group['name']}' has been moved to trash by an administrator.");
            
            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/groups'], 'Group moved to trash.');
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('group_delete_failed', 'Group Deletion Failed', "Failed to delete group '{$group['name']}': " . $e->getMessage());
            $this->jsonError('Failed to delete group: ' . $e->getMessage(), null, 500);
        }
    }

    // ── MEMBER MANAGEMENT ──────────────────────────────────────────

    /**
     * Add a member to a group and notify them
     */
    public function addMember(int $groupId): void
    {
        $this->auth->requirePermission('groups.edit');
        $this->verifyCsrf();
        
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'groups.view', 'group' => 'groups.view_members'];
        $scope = $this->resolveDataScope($perms);

        $group = $this->model->find($groupId);
        if (!$group) $this->jsonError('Group not found.', null, 404);

        // ENFORCE: IDOR / Scope Guard
        if ($scope['type'] === 'group' && (int)$group['id'] !== (int)$scope['group_id']) {
            $this->jsonError('You do not have permission to modify this group.', null, 403);
        }
        
        $data = $this->getPost(['member_id']);
        $memberId = (int)$data['member_id'];
        
        $member = $this->db->fetchOne("SELECT * FROM members WHERE id = ?", [$memberId]);
        if (!$member) $this->jsonError('Member not found.', null, 404);

        $this->db->beginTransaction();
        try {
            $this->db->execute("UPDATE members SET group_id = ? WHERE id = ?", [$groupId, $memberId]);
            
            // ENFORCE: Notify member of group assignment using multi-channel dispatch
            if (!empty($member['user_id'])) {
                $this->notif->dispatch(
                    (int)$member['user_id'],
                    'added_to_group',
                    'Assigned to New Group',
                    "Dear {$member['first_name']}, you have been successfully added to the savings group: '{$group['name']}'."
                );
            }

            $this->logAudit('member_added_to_group', 'savings_groups', $groupId, 'Group', "Member {$member['member_no']} added to {$group['name']}", null, json_encode(['member_id' => $memberId, 'group_name' => $group['name']]));
            
            // ENFORCE: Notify admins of success
            $this->notifyAdmins('member_added_to_group', 'Member Added to Group', "Member {$member['first_name']} {$member['last_name']} was added to the group '{$group['name']}'.");
            
            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/groups/' . $groupId], 'Member added to group successfully.');
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('add_member_failed', 'Add Member Failed', "Failed to add member to group '{$group['name']}': " . $e->getMessage());
            $this->jsonError('Failed to add member: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Remove a member from a group and notify them
     */
    public function removeMember(int $groupId): void
    {
        $this->auth->requirePermission('groups.edit');
        $this->verifyCsrf();
        
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'groups.view', 'group' => 'groups.view_members'];
        $scope = $this->resolveDataScope($perms);

        $group = $this->model->find($groupId);
        if (!$group) $this->jsonError('Group not found.', null, 404);

        // ENFORCE: IDOR / Scope Guard
        if ($scope['type'] === 'group' && (int)$group['id'] !== (int)$scope['group_id']) {
            $this->jsonError('You do not have permission to modify this group.', null, 403);
        }
        
        $data = $this->getPost(['member_id']);
        $memberId = (int)$data['member_id'];
        
        // Ensure member is actually in this group before removing
        $member = $this->db->fetchOne("SELECT * FROM members WHERE id = ? AND group_id = ?", [$memberId, $groupId]);
        if (!$member) $this->jsonError('Member not found in this group.', null, 404);

        $this->db->beginTransaction();
        try {
            $this->db->execute("UPDATE members SET group_id = NULL WHERE id = ?", [$memberId]);
            
            // ENFORCE: Notify member of group removal using multi-channel dispatch
            if (!empty($member['user_id'])) {
                $this->notif->dispatch(
                    (int)$member['user_id'],
                    'removed_from_group',
                    'Removed from Group',
                    "Dear {$member['first_name']}, you have been removed from the savings group: '{$group['name']}'."
                );
            }

            $this->logAudit('member_removed_from_group', 'savings_groups', $groupId, 'Group', "Member {$member['member_no']} removed from {$group['name']}", json_encode(['member_id' => $memberId, 'group_name' => $group['name']]), null);
            
            // ENFORCE: Notify admins of success
            $this->notifyAdmins('member_removed_from_group', 'Member Removed from Group', "Member {$member['first_name']} {$member['last_name']} was removed from the group '{$group['name']}'.");
            
            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/groups/' . $groupId], 'Member removed from group successfully.');
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('remove_member_failed', 'Remove Member Failed', "Failed to remove member from group '{$group['name']}': " . $e->getMessage());
            $this->jsonError('Failed to remove member: ' . $e->getMessage(), null, 500);
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
            WHERE p.slug IN ('groups.view', 'groups.create', 'groups.edit', 'groups.delete') AND u.status = 'active'
        ");
        foreach ($admins as $admin) {
            // USE dispatch instead of sendToUser for multi-channel (System + Email + SMS)
            $this->notif->dispatch((int)$admin['id'], $type, $title, $message);
        }
    }
}