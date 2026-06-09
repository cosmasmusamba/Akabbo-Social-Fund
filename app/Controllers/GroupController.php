<?php
namespace App\Controllers;

use Database;

/**
 * AKABBO SOCIAL FUND
 * Groups Controller
 *
 * Manages savings groups (SACCOs, investment clubs): creation, editing,
 * member assignment, leadership roles, and group statistics.
 */
class GroupController extends BaseController
{
    private Database $db;

    public function __construct()
    {
        parent::__construct();
        $this->auth->requireAuth();
        $this->db = Database::getInstance();
    }

    /**
     * List all groups with member counts and financial totals.
     */
    public function index(): void
    {
        $this->auth->requirePermission('groups.view');

        $groups = $this->db->fetchAll("
            SELECT g.*,
                   COUNT(DISTINCT m.id) AS member_count,
                   COALESCE(SUM(sa.balance), 0) AS total_savings,
                   (SELECT COUNT(*) FROM loans l WHERE l.member_id IN (SELECT id FROM members WHERE group_id = g.id) AND l.status IN ('active','disbursed')) AS active_loans
            FROM savings_groups g
            LEFT JOIN members m ON m.group_id = g.id AND m.deleted_at IS NULL
            LEFT JOIN savings_accounts sa ON sa.member_id = m.id AND sa.status = 'active'
            GROUP BY g.id
            ORDER BY g.name ASC
        ");

        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnreadCount();

        $pageTitle = 'Savings Groups';
        $activePage = 'groups';
        $breadcrumbs = ['Groups' => null];

        $this->view('groups/index', compact('groups', 'settings', 'pageTitle', 'activePage', 'breadcrumbs', 'unreadNotifications'));
    }

    /**
     * Show create group form.
     */
    public function create(): void
    {
        $this->auth->requirePermission('groups.create');

        // Fetch all active members for leadership dropdowns
        $members = $this->db->fetchAll("
            SELECT id, member_no, first_name, last_name
            FROM members
            WHERE status = 'active' AND deleted_at IS NULL
            ORDER BY first_name, last_name
        ");

        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnreadCount();

        $pageTitle = 'Create Group';
        $activePage = 'groups';
        $breadcrumbs = ['Groups' => APP_URL . '/groups', 'Create' => null];

        $this->view('groups/create', compact('members', 'settings', 'pageTitle', 'activePage', 'breadcrumbs', 'unreadNotifications'));
    }

    /**
     * Store a new group.
     */
    public function store(): void
    {
        $this->auth->requirePermission('groups.create');
        $this->verifyCsrf();

        $data = $this->getPost();
        $missing = $this->validateRequired($data, ['name']);
        if ($missing) {
            $this->jsonError('Group name is required.', null, 422);
            return;
        }

        $groupCode = $this->generateGroupCode();

        // Handle avatar upload
        $avatarFile = null;
        if (!empty($_FILES['avatar']['name'])) {
            try {
                $avatarFile = $this->handleAvatarUpload($_FILES['avatar']);
            } catch (\RuntimeException $e) {
                if ($this->isAjax()) $this->jsonError($e->getMessage(), null, 400);
                $this->flash('error', $e->getMessage());
                $this->redirect('/groups/create');
            }
        }

        $this->db->beginTransaction();
        try {
            $groupId = $this->db->insert("
                INSERT INTO savings_groups
                    (group_code, name, description, meeting_schedule, chairperson_id, treasurer_id, secretary_id, avatar, status, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())
            ", [
                $groupCode,
                $data['name'],
                $data['description'] ?? null,
                $data['meeting_schedule'] ?? null,
                !empty($data['chairperson_id']) ? (int)$data['chairperson_id'] : null,
                !empty($data['treasurer_id']) ? (int)$data['treasurer_id'] : null,
                !empty($data['secretary_id']) ? (int)$data['secretary_id'] : null,
                $avatarFile,
                $_SESSION['user_id']
            ]);

            $this->auth->logAudit($_SESSION['user_id'], 'group_created', 'savings_groups', $groupId, 'Group', "Group {$data['name']} created");
            $this->db->commit();

            $this->jsonSuccess(['group_id' => $groupId, 'redirect' => APP_URL . '/groups/' . $groupId], 'Group created successfully!');

        } catch (\Exception $e) {
            $this->db->rollback();
            error_log('[GROUP STORE ERROR] ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            $this->jsonError('Database error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Handle group avatar upload.
     */
    private function handleAvatarUpload(array $file): ?string
    {
        $validation = \App\Helpers\Security::validateUpload($file, ['image/jpeg','image/png','image/webp'], 2 * 1024 * 1024);
        if (!$validation['valid']) {
            throw new \RuntimeException($validation['error']);
        }
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $name = 'group_' . uniqid() . '.' . strtolower($ext);
        $dest = UPLOADS_PATH . '/avatars/' . $name;
        if (!is_dir(UPLOADS_PATH . '/avatars')) {
            mkdir(UPLOADS_PATH . '/avatars', 0755, true);
        }
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new \RuntimeException('Failed to save group avatar.');
        }
        return $name;
    }

    /**
     * Show a single group with its members and statistics.
     */
    public function show(int $id): void
    {
        $this->auth->requirePermission('groups.view');

        $group = $this->db->fetchOne("
            SELECT * FROM savings_groups WHERE id = ?
        ", [$id]);

        if (!$group) {
            $this->flash('error', 'Group not found.');
            $this->redirect('/groups');
        }

        // Fetch members with their savings totals
        $members = $this->db->fetchAll("
            SELECT m.id, m.member_no, m.first_name, m.last_name, m.phone, m.status, m.avatar,
                   COALESCE(SUM(sa.balance), 0) AS total_savings
            FROM members m
            LEFT JOIN savings_accounts sa ON sa.member_id = m.id AND sa.status = 'active'
            WHERE m.group_id = ? AND m.deleted_at IS NULL
            GROUP BY m.id
            ORDER BY m.first_name
        ", [$id]);

        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnreadCount();

        $pageTitle = $group['name'];
        $activePage = 'groups';
        $breadcrumbs = ['Groups' => APP_URL . '/groups', $group['name'] => null];

        $this->view('groups/show', compact('group', 'members', 'settings', 'pageTitle', 'activePage', 'breadcrumbs', 'unreadNotifications'));
    }

    /**
     * Show edit group form.
     */
    public function edit(int $id): void
    {
        $this->auth->requirePermission('groups.edit');

        $group = $this->db->fetchOne("SELECT * FROM savings_groups WHERE id = ?", [$id]);
        if (!$group) {
            $this->flash('error', 'Group not found.');
            $this->redirect('/groups');
        }

        $members = $this->db->fetchAll("
            SELECT id, member_no, first_name, last_name
            FROM members
            WHERE status = 'active' AND deleted_at IS NULL
            ORDER BY first_name, last_name
        ");

        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnreadCount();

        $pageTitle = 'Edit: ' . $group['name'];
        $activePage = 'groups';
        $breadcrumbs = ['Groups' => APP_URL . '/groups', $group['name'] => APP_URL . '/groups/' . $id, 'Edit' => null];

        $this->view('groups/edit', compact('group', 'members', 'settings', 'pageTitle', 'activePage', 'breadcrumbs', 'unreadNotifications'));
    }

    /**
     * Update a group.
     */
    public function update(int $id): void
    {
        $this->auth->requirePermission('groups.edit');
        $this->verifyCsrf();

        $group = $this->db->fetchOne("SELECT * FROM savings_groups WHERE id = ?", [$id]);
        if (!$group) {
            if ($this->isAjax()) $this->jsonError('Group not found.', null, 404);
            $this->flash('error', 'Group not found.');
            $this->redirect('/groups');
        }

        // Handle avatar upload (replace)
        $avatarFile = $group['avatar'] ?? null; // keep existing by default
        if (!empty($_FILES['avatar']['name'])) {
            try {
                $avatarFile = $this->handleAvatarUpload($_FILES['avatar']);
                // Delete old avatar if exists
                if (!empty($group['avatar']) && file_exists(UPLOADS_PATH . '/avatars/' . $group['avatar'])) {
                    unlink(UPLOADS_PATH . '/avatars/' . $group['avatar']);
                }
            } catch (\RuntimeException $e) {
                if ($this->isAjax()) $this->jsonError($e->getMessage(), null, 400);
                $this->flash('error', $e->getMessage());
                $this->redirect('/groups/' . $id . '/edit');
            }
        }

        $data = $this->getPost();
        $missing = $this->validateRequired($data, ['name']);
        if ($missing) {
            if ($this->isAjax()) {
                $this->jsonError('Group name is required.', null, 422);
            }
            $this->flash('error', 'Group name is required.');
            $this->redirect('/groups/' . $id . '/edit');
        }

        $this->db->beginTransaction();
        try {
            $this->db->execute("
                UPDATE savings_groups
                SET name = ?,
                    description = ?,
                    meeting_schedule = ?,
                    chairperson_id = ?,
                    treasurer_id = ?,
                    secretary_id = ?,
                    avatar = ?,
                    status = ?,
                    updated_at = NOW()
                WHERE id = ?
            ", [
                $data['name'],
                $data['description'] ?? $group['description'],
                $data['meeting_schedule'] ?? $group['meeting_schedule'],
                !empty($data['chairperson_id']) ? (int)$data['chairperson_id'] : null,
                !empty($data['treasurer_id']) ? (int)$data['treasurer_id'] : null,
                !empty($data['secretary_id']) ? (int)$data['secretary_id'] : null,
                $avatarFile,
                $data['status'] ?? $group['status'],
                $id
            ]);

            $this->auth->logAudit($_SESSION['user_id'], 'group_updated', 'savings_groups', $id, 'Group', "Group {$data['name']} updated");
            $this->db->commit();

            if ($this->isAjax()) {
                $this->jsonSuccess(['redirect' => APP_URL . '/groups/' . $id], 'Group updated successfully.');
            }
            $this->flash('success', 'Group updated successfully.');
            $this->redirect('/groups/' . $id);

        } catch (\Exception $e) {
            $this->db->rollback();
            error_log('[GROUP UPDATE ERROR] ' . $e->getMessage());
            if ($this->isAjax()) {
                $this->jsonError('Update failed. Please try again.', null, 500);
            }
            $this->flash('error', 'Update failed.');
            $this->redirect('/groups/' . $id . '/edit');
        }
    }

    /**
     * Delete (soft-delete) a group – only if no members are assigned.
     */
    public function delete(int $id): void
    {
        $this->auth->requirePermission('groups.delete');
        $this->verifyCsrf();

        $group = $this->db->fetchOne("SELECT * FROM savings_groups WHERE id = ?", [$id]);
        if (!$group) {
            $this->jsonError('Group not found.', null, 404);
        }

        // Check if any members are assigned
        $memberCount = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM members WHERE group_id = ? AND deleted_at IS NULL", [$id]);
        if ($memberCount > 0) {
            $this->jsonError("Cannot delete group: {$memberCount} member(s) still assigned.", null, 409);
        }

        // Soft delete by setting status to 'closed'? Or you have deleted_at? I'll use status='closed' to match schema.
        // Schema doesn't have deleted_at in groups table, so set status='closed'.
        $this->db->execute("UPDATE savings_groups SET status = 'closed' WHERE id = ?", [$id]);

        $this->auth->logAudit($_SESSION['user_id'], 'group_deleted', 'savings_groups', $id, 'Group', "Group {$group['name']} closed/deleted");

        if ($this->isAjax()) {
            $this->jsonSuccess(null, 'Group closed successfully.');
        }
        $this->flash('success', 'Group closed.');
        $this->redirect('/groups');
    }

    // ── Private helpers ──────────────────────────────────────────

    /**
     * Generate a unique group code like GRP-0001.
     */
    private function generateGroupCode(): string
    {
        $prefix = 'GRP-';
        $last = $this->db->fetchColumn("SELECT group_code FROM savings_groups WHERE group_code LIKE '{$prefix}%' ORDER BY id DESC LIMIT 1");
        if ($last) {
            $num = (int)substr($last, strlen($prefix)) + 1;
        } else {
            $num = 1;
        }
        return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
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