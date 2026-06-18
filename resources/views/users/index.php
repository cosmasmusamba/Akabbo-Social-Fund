<?php
use App\Helpers\Avatar;
use App\Helpers\Format;
$pageTitle   = 'System Users';
$activePage  = 'users';
$breadcrumbs = ['Users' => null];
$result      = $result ?? ['data' => [], 'total' => 0, 'page' => 1, 'last_page' => 1, 'from' => 0, 'to' => 0];
?>
<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-xl font-bold text-slate-800">System Users</h1>
        <p class="text-sm text-slate-400 mt-0.5"><?= number_format($result['total'] ?? 0) ?> system accounts</p>
    </div>
    <?php if ($auth->can('users.create')): ?>
        <a href="<?= APP_URL ?>/users/create" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-user-plus"></i> Add User
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th class="hidden md:table-cell">Role</th>
                    <th class="hidden md:table-cell">Last Login</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($result['data'])): ?>
                    <tr>
                        <td colspan="6" class="text-center py-12">
                            <i class="fa-solid fa-users text-5xl text-slate-200 block mb-3"></i>
                            <p class="text-slate-400">No users found</p>
                        </td>
                    </tr>
                <?php else: foreach ($result['data'] as $u): 
                    // SAFEGUARD: Prevent actions on the currently logged-in user
                    $isSelf = ($u['id'] ?? 0) === ($_SESSION['user_id'] ?? 0);
                ?>
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                <div class="avatar-circle w-9 h-9 text-xs overflow-hidden">
                                    <?= Avatar::small($u) ?>
                                </div>
                                <div>
                                    <div class="font-semibold text-sm"><?= htmlspecialchars(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?></div>
                                    <?php if (!empty($u['phone'])): ?>
                                        <div class="text-xs text-slate-400"><?= htmlspecialchars($u['phone']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="text-sm"><?= htmlspecialchars($u['email'] ?? '') ?></td>
                        <td class="hidden md:table-cell">
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600">
                                <?= htmlspecialchars($u['role_name'] ?? '—') ?>
                            </span>
                        </td>
                        <td class="hidden md:table-cell text-xs text-slate-400">
                            <?= !empty($u['last_login']) ? Format::timeAgo($u['last_login']) : 'Never' ?>
                        </td>
                        <td><?= Format::statusPill($u['status'] ?? 'active') ?></td>
                        <td>
                            <div class="flex gap-1">
                                <a href="<?= APP_URL ?>/users/<?= $u['id'] ?? 0 ?>" class="btn btn-secondary btn-sm py-1 px-2" title="View Profile">
                                    <i class="fa-solid fa-eye text-xs"></i>
                                </a>
                                
                                <?php if ($auth->can('users.edit') && ($u['id'] ?? 0) !== ($_SESSION['user_id'] ?? 0)): ?>
                                    <a href="<?= APP_URL ?>/users/<?= $u['id'] ?? 0 ?>/edit" class="btn btn-secondary btn-sm py-1 px-2" title="Edit User">
                                        <i class="fa-solid fa-pen text-xs"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($auth->can('users.edit') && ($u['id'] ?? 0) !== ($_SESSION['user_id'] ?? 0)): ?>
                                    <button onclick="resetPwd(<?= $u['id'] ?>, '<?= addslashes(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?>')" 
                                            class="btn btn-secondary btn-sm py-1 px-2" title="Reset password">
                                        <i class="fa-solid fa-key text-xs"></i>
                                    </button>
                                    
                                    <!-- Dynamic Suspend / Activate Button -->
                                    <?php if (($u['status'] ?? '') === 'active'): ?>
                                        <button onclick="disableUser(<?= $u['id'] ?>, '<?= addslashes(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?>')" 
                                                class="btn btn-sm py-1 px-2" style="background:#fef3c7;color:#92400e;border:1px solid #fcd34d;" title="Suspend User">
                                            <i class="fa-solid fa-user-slash text-xs"></i>
                                        </button>
                                    <?php else: ?>
                                        <button onclick="activateUser(<?= $u['id'] ?>, '<?= addslashes(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?>')" 
                                                class="btn btn-sm py-1 px-2" style="background:#dcfce7;color:#166534;border:1px solid #a7f3d0;" title="Activate User">
                                            <i class="fa-solid fa-user-check text-xs"></i>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <!-- Deactivate / Delete Action -->
                                <?php if ($auth->can('users.delete') && ($u['id'] ?? 0) !== ($_SESSION['user_id'] ?? 0)): ?>
                                    <button onclick="deleteUser(<?= $u['id'] ?>, '<?= addslashes(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?>')" 
                                            class="btn btn-sm py-1 px-2" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;" title="Deactivate User">
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if (!empty($result['data'])): 
        $page = $result['page'] ?? 1;
        $lastPage = $result['last_page'] ?? 1;
        $qs = http_build_query(array_diff_key($_GET, ['page' => '']));
        $base = APP_URL . '/users?' . ($qs ? $qs . '&' : ''); 
    ?>
        <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between">
            <p class="text-xs text-slate-400">
                Showing <strong><?= $result['from'] ?? 1 ?></strong>–<strong><?= $result['to'] ?? count($result['data']) ?></strong> 
                of <strong><?= number_format($result['total']) ?></strong>
            </p>
            <div class="flex gap-1">
                <a href="<?= $base ?>page=<?= max(1, $page - 1) ?>" class="pager-btn <?= $page <= 1 ? 'opacity-40 pointer-events-none' : '' ?>">
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                </a>
                <?php for ($p = max(1, $page - 2); $p <= min($lastPage, $page + 2); $p++): ?>
                    <a href="<?= $base ?>page=<?= $p ?>" class="pager-btn <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <a href="<?= $base ?>page=<?= min($lastPage, $page + 1) ?>" class="pager-btn <?= $page >= $lastPage ? 'opacity-40 pointer-events-none' : '' ?>">
                    <i class="fa-solid fa-chevron-right text-xs"></i>
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// COMPLIANCE: All actions use custom modals instead of native browser prompts/confirms

async function resetPwd(id, name) {
    window.confirmAction({
        title: 'Reset Password',
        message: `Generate a new temporary password for <strong>${name}</strong>?`,
        confirmText: 'Reset',
        type: 'warning',
        onConfirm: async () => {
            const fd = new FormData();
            fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
            try {
                const r = await fetch(`<?= APP_URL ?>/users/${id}/reset-password`, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                });
                const d = await r.json();
                if (d.success) {
                    window.showToast('success', `Temp password: ${d.data?.temp_password}`);
                } else {
                    window.showToast('error', d.message);
                }
            } catch (e) {
                window.showToast('error', 'Request failed.');
            }
        }
    });
}

function disableUser(id, name) {
    window.confirmAction({
        title: 'Suspend User',
        message: `Are you sure you want to suspend <strong>${name}</strong>? They will lose access to the system until reactivated.`,
        confirmText: 'Suspend',
        type: 'warning',
        onConfirm: async () => {
            const fd = new FormData();
            fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
            const r = await fetch(`<?= APP_URL ?>/users/${id}/disable`, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
            const d = await r.json();
            if (d.success) { window.showToast('success', d.message); setTimeout(() => location.reload(), 900); }
            else window.showToast('error', d.message);
        }
    });
}

function activateUser(id, name) {
    window.confirmAction({
        title: 'Activate User',
        message: `Are you sure you want to reactivate <strong>${name}</strong>? They will regain full system access.`,
        confirmText: 'Activate',
        type: 'primary',
        onConfirm: async () => {
            const fd = new FormData();
            fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
            const r = await fetch(`<?= APP_URL ?>/users/${id}/activate`, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
            const d = await r.json();
            if (d.success) { window.showToast('success', d.message); setTimeout(() => location.reload(), 900); }
            else window.showToast('error', d.message);
        }
    });
}

function deleteUser(id, name) {
    window.confirmAction({
        title: 'Deactivate User',
        message: `Are you sure you want to deactivate <strong>${name}</strong>? This will revoke their system access.`,
        confirmText: 'Deactivate',
        type: 'danger',
        onConfirm: async () => {
            const fd = new FormData();
            fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
            const r = await fetch(`<?= APP_URL ?>/users/${id}/delete`, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
            const d = await r.json();
            if (d.success) { window.showToast('success', d.message); setTimeout(() => location.reload(), 900); }
            else window.showToast('error', d.message);
        }
    });
}
</script>