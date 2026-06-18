<?php
use App\Helpers\Format;

$pageTitle   = 'Edit User: ' . ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '');
$activePage  = 'users';
$breadcrumbs = ['Users' => APP_URL.'/users', ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '') => APP_URL.'/users/'.($user['id'] ?? 0), 'Edit' => null];
$user        = $user ?? [];
$roles       = $roles ?? [];
?>

<style>
.user-edit-hero {
    background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
    border-radius: 16px;
    padding: 24px;
    color: #fff;
    position: relative;
    overflow: hidden;
}
.user-edit-hero::after {
    content: '';
    position: absolute;
    right: -40px;
    top: -40px;
    width: 200px;
    height: 200px;
    border-radius: 50%;
    background: rgba(255,255,255,0.08);
}
.user-edit-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    border: 3px solid rgba(255,255,255,0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    font-weight: 700;
    background: rgba(255,255,255,0.15);
    color: #fff;
    flex-shrink: 0;
    overflow: hidden;
}
.user-edit-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.status-indicator {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.status-active { background: rgba(16,185,129,0.2); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.4); }
.status-inactive { background: rgba(100,116,139,0.2); color: #cbd5e1; border: 1px solid rgba(100,116,139,0.4); }
.status-suspended { background: rgba(245,158,11,0.2); color: #fcd34d; border: 1px solid rgba(245,158,11,0.4); }
.status-locked { background: rgba(239,68,68,0.2); color: #fca5a5; border: 1px solid rgba(239,68,68,0.4); }
.info-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.75rem;
    background: rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.9);
}
</style>

<!-- Header -->
<div class="flex items-center justify-between mb-5">
    <div class="breadcrumb">
        <a href="<?= APP_URL ?>/dashboard"><i class="fa-solid fa-house-chimney text-xs"></i></a>
        <span class="sep">/</span>
        <a href="<?= APP_URL ?>/users">Users</a>
        <span class="sep">/</span>
        <a href="<?= APP_URL ?>/users/<?= $user['id'] ?? 0 ?>"><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></a>
        <span class="sep">/</span>
        <span class="current">Edit</span>
    </div>
    <a href="<?= APP_URL ?>/users/<?= $user['id'] ?? 0 ?>" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-arrow-left"></i> Back to Profile
    </a>
</div>

<!-- User Hero Card -->
<div class="user-edit-hero mb-5">
    <div class="flex items-center gap-5">
        <div class="user-edit-avatar">
            <?php if (!empty($user['avatar'])): ?>
                <img src="<?= APP_URL ?>/storage/uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>" alt="">
            <?php else: ?>
                <?= Format::initials(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?>
            <?php endif; ?>
        </div>
        <div class="flex-1 min-w-0">
            <h1 class="text-2xl font-bold mb-1"><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></h1>
            <div class="flex flex-wrap gap-2 mb-2">
                <span class="info-pill"><i class="fa-solid fa-envelope"></i> <?= htmlspecialchars($user['email'] ?? '') ?></span>
                <?php if (!empty($user['phone'])): ?>
                    <span class="info-pill"><i class="fa-solid fa-phone"></i> <?= htmlspecialchars($user['phone']) ?></span>
                <?php endif; ?>
                <?php if (!empty($user['employee_id'])): ?>
                    <span class="info-pill"><i class="fa-solid fa-id-badge"></i> <?= htmlspecialchars($user['employee_id']) ?></span>
                <?php endif; ?>
            </div>
            <div class="flex gap-2 flex-wrap items-center">
                <span class="status-indicator status-<?= $user['status'] ?? 'active' ?>">
                    <i class="fa-solid fa-circle text-[8px]"></i>
                    <?= ucfirst($user['status'] ?? 'active') ?>
                </span>
                <span class="info-pill"><i class="fa-solid fa-user-shield"></i> <?= htmlspecialchars($user['role_name'] ?? 'Unknown Role') ?></span>
                <?php if (!empty($user['must_change_password'])): ?>
                    <span class="info-pill" style="background:rgba(245,158,11,0.2);color:#fcd34d">
                        <i class="fa-solid fa-key"></i> Must Change Password
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <!-- Left: Edit Form -->
    <div class="lg:col-span-2">
        <form id="editUserForm" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
            
            <!-- Personal Information -->
            <div class="card">
                <div class="card-header">
                    <h3 class="section-title"><i class="fa-solid fa-user mr-2" style="color:var(--green-mid)"></i>Personal Information</h3>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label required">First Name</label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" class="form-control" required>
                        </div>
                        <div>
                            <label class="form-label required">Last Name</label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" class="form-control" required>
                        </div>
                        <div>
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" class="form-control" placeholder="+256 700 000000">
                        </div>
                        <div>
                            <label class="form-label">Email Address</label>
                            <input type="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" class="form-control bg-slate-50" readonly disabled>
                            <p class="text-xs text-slate-400 mt-1"><i class="fa-solid fa-lock text-xs mr-1"></i>Email cannot be changed for security reasons</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Role & Access -->
            <div class="card">
                <div class="card-header">
                    <h3 class="section-title"><i class="fa-solid fa-user-shield mr-2" style="color:var(--green-mid)"></i>Role & Access</h3>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label required">Role</label>
                            <select name="role_id" class="form-control form-select" required>
                                <option value="">Select role…</option>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>" <?= ($user['role_id'] ?? '') == $r['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($r['name']) ?>
                                        <?php if (!empty($r['is_system'])): ?>(System)<?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-slate-400 mt-1">Determines what this user can see and do in the system</p>
                        </div>
                        <div>
                            <label class="form-label required">Account Status</label>
                            <select name="status" class="form-control form-select" required>
                                <option value="active" <?= ($user['status'] ?? '') === 'active' ? 'selected' : '' ?>>✅ Active</option>
                                <option value="inactive" <?= ($user['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>⏸️ Inactive</option>
                                <option value="suspended" <?= ($user['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>⚠️ Suspended</option>
                                <option value="locked" <?= ($user['status'] ?? '') === 'locked' ? 'selected' : '' ?>>🔒 Locked</option>
                            </select>
                            <p class="text-xs text-slate-400 mt-1">Suspended users cannot log in; Locked users are blocked due to security</p>
                        </div>
                    </div>

                    <!-- Linked Member Info -->
                    <?php if (!empty($user['member_id'])): ?>
                        <div class="mt-4 p-4 rounded-xl bg-emerald-50 border border-emerald-200">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center">
                                    <i class="fa-solid fa-link text-emerald-600"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="text-sm font-semibold text-emerald-900">Linked to Member Profile</div>
                                    <a href="<?= APP_URL ?>/members/<?= $user['member_id'] ?>" class="text-xs text-emerald-700 hover:underline">
                                        View Member Profile <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="mt-4 p-4 rounded-xl bg-amber-50 border border-amber-200">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center">
                                    <i class="fa-solid fa-link-slash text-amber-600"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="text-sm font-semibold text-amber-900">No Linked Member Profile</div>
                                    <div class="text-xs text-amber-700">This user account is not linked to any member record</div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Security Options -->
            <div class="card">
                <div class="card-header">
                    <h3 class="section-title"><i class="fa-solid fa-shield-halved mr-2" style="color:var(--green-mid)"></i>Security Options</h3>
                </div>
                <div class="card-body">
                    <div class="space-y-3">
                        <label class="flex items-start gap-3 p-3 rounded-lg hover:bg-slate-50 cursor-pointer transition-colors">
                            <input type="checkbox" name="must_change_password" value="1" 
                                   <?= !empty($user['must_change_password']) ? 'checked' : '' ?>
                                   class="mt-1 w-4 h-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <div>
                                <div class="text-sm font-semibold text-slate-800">Require Password Change on Next Login</div>
                                <div class="text-xs text-slate-500">User will be forced to create a new password the next time they sign in</div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex flex-col sm:flex-row gap-3 justify-between">
                <div>
                    <button type="button" onclick="showResetPasswordModal()" class="btn btn-secondary btn-sm">
                        <i class="fa-solid fa-key"></i> Reset Password
                    </button>
                    <?php if (($user['status'] ?? '') !== 'inactive' && ($user['id'] ?? 0) !== ($_SESSION['user_id'] ?? 0)): ?>
                        <button type="button" onclick="showDeactivateModal()" class="btn btn-sm" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca">
                            <i class="fa-solid fa-user-slash"></i> Deactivate
                        </button>
                    <?php endif; ?>
                </div>
                <div class="flex gap-3">
                    <a href="<?= APP_URL ?>/users/<?= $user['id'] ?? 0 ?>" class="btn btn-secondary">Cancel</a>
                    <button type="submit" id="saveBtn" class="btn btn-primary">
                        <i class="fa-solid fa-save"></i> Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Right: Sidebar Info -->
    <div class="space-y-5">
        <!-- Account Activity -->
        <div class="card">
            <div class="card-header">
                <h3 class="section-title text-sm"><i class="fa-solid fa-clock-rotate-left mr-2" style="color:var(--green-mid)"></i>Account Activity</h3>
            </div>
            <div class="card-body space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-slate-500">Last Login:</span>
                    <span class="font-semibold text-slate-800">
                        <?= !empty($user['last_login']) ? Format::timeAgo($user['last_login']) : 'Never' ?>
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Last IP:</span>
                    <span class="font-mono text-xs text-slate-700"><?= htmlspecialchars($user['last_login_ip'] ?? '—') ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Created:</span>
                    <span class="text-slate-700"><?= Format::date($user['created_at'] ?? '') ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Last Updated:</span>
                    <span class="text-slate-700"><?= Format::date($user['updated_at'] ?? '') ?></span>
                </div>
                <?php if (!empty($user['failed_login_attempts']) && $user['failed_login_attempts'] > 0): ?>
                    <div class="mt-3 p-3 rounded-lg bg-red-50 border border-red-200">
                        <div class="flex items-center gap-2 text-red-700">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <span class="text-xs font-semibold"><?= (int)$user['failed_login_attempts'] ?> failed login attempts</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Tips -->
        <div class="card">
            <div class="card-header">
                <h3 class="section-title text-sm"><i class="fa-solid fa-lightbulb mr-2 text-amber-500"></i>Quick Tips</h3>
            </div>
            <div class="card-body text-xs text-slate-600 space-y-2">
                <div class="flex gap-2">
                    <i class="fa-solid fa-circle-info text-blue-500 mt-0.5 flex-shrink-0"></i>
                    <span>Changing a user's role will immediately affect their permissions across the system.</span>
                </div>
                <div class="flex gap-2">
                    <i class="fa-solid fa-circle-info text-blue-500 mt-0.5 flex-shrink-0"></i>
                    <span>Suspending a user will terminate their current session and prevent future logins.</span>
                </div>
                <div class="flex gap-2">
                    <i class="fa-solid fa-circle-info text-blue-500 mt-0.5 flex-shrink-0"></i>
                    <span>All changes are logged in the audit trail for compliance purposes.</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" class="hidden modal-overlay">
    <div class="modal" style="max-width:440px">
        <div class="modal-header">
            <h3 class="font-bold flex items-center gap-2">
                <i class="fa-solid fa-key text-amber-500"></i> Reset Password
            </h3>
            <button onclick="closeResetPasswordModal()" class="text-slate-400 hover:text-slate-700">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <div class="modal-body">
            <p class="text-sm text-slate-600 mb-4">
                Generate a new temporary password for <strong><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></strong>?
            </p>
            <div class="p-3 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-800">
                <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                The user will be required to change this password on their next login.
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="closeResetPasswordModal()" class="btn btn-secondary">Cancel</button>
            <button onclick="resetPassword()" class="btn btn-primary">
                <i class="fa-solid fa-key"></i> Generate New Password
            </button>
        </div>
    </div>
</div>

<!-- Deactivate Modal -->
<div id="deactivateModal" class="hidden modal-overlay">
    <div class="modal" style="max-width:440px">
        <div class="modal-header">
            <h3 class="font-bold flex items-center gap-2">
                <i class="fa-solid fa-user-slash text-red-500"></i> Deactivate User
            </h3>
            <button onclick="closeDeactivateModal()" class="text-slate-400 hover:text-slate-700">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <div class="modal-body">
            <p class="text-sm text-slate-600 mb-3">
                Are you sure you want to deactivate <strong><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></strong>?
            </p>
            <div class="p-3 rounded-lg bg-red-50 border border-red-200 text-xs text-red-800">
                <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                This will immediately terminate their current session and prevent future logins. The user can be reactivated later.
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="closeDeactivateModal()" class="btn btn-secondary">Cancel</button>
            <button onclick="deactivateUser()" class="btn btn-danger">
                <i class="fa-solid fa-user-slash"></i> Yes, Deactivate
            </button>
        </div>
    </div>
</div>

<script>
// Form Submission
document.getElementById('editUserForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('saveBtn');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';
    
    const formData = new FormData(this);
    
    // Handle unchecked checkbox (must_change_password)
    if (!this.querySelector('[name="must_change_password"]').checked) {
        formData.set('must_change_password', '0');
    }
    
    try {
        const response = await fetch('<?= APP_URL ?>/users/<?= $user['id'] ?? 0 ?>/update', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            window.showToast('success', data.message);
            setTimeout(() => {
                window.location.href = '<?= APP_URL ?>/users/<?= $user['id'] ?? 0 ?>';
            }, 1000);
        } else {
            window.showToast('error', data.message || 'Update failed.');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    } catch (err) {
        console.error(err);
        window.showToast('error', 'Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
});

// Reset Password Modal
function showResetPasswordModal() {
    document.getElementById('resetPasswordModal').classList.remove('hidden');
}
function closeResetPasswordModal() {
    document.getElementById('resetPasswordModal').classList.add('hidden');
}
async function resetPassword() {
    const fd = new FormData();
    fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
    
    try {
        const r = await fetch('<?= APP_URL ?>/users/<?= $user['id'] ?? 0 ?>/reset-password', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        });
        const d = await r.json();
        
        if (d.success) {
            closeResetPasswordModal();
            window.showToast('success', `Password reset! Temporary password: ${d.data?.temp_password}`);
            setTimeout(() => location.reload(), 1500);
        } else {
            window.showToast('error', d.message);
        }
    } catch (e) {
        window.showToast('error', 'Request failed.');
    }
}

// Deactivate Modal
function showDeactivateModal() {
    document.getElementById('deactivateModal').classList.remove('hidden');
}
function closeDeactivateModal() {
    document.getElementById('deactivateModal').classList.add('hidden');
}
async function deactivateUser() {
    const fd = new FormData();
    fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
    fd.append('status', 'inactive');
    fd.append('first_name', '<?= addslashes($user['first_name'] ?? '') ?>');
    fd.append('last_name', '<?= addslashes($user['last_name'] ?? '') ?>');
    fd.append('phone', '<?= addslashes($user['phone'] ?? '') ?>');
    fd.append('role_id', '<?= $user['role_id'] ?? '' ?>');
    
    try {
        const r = await fetch('<?= APP_URL ?>/users/<?= $user['id'] ?? 0 ?>/update', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        });
        const d = await r.json();
        
        if (d.success) {
            closeDeactivateModal();
            window.showToast('success', d.message);
            setTimeout(() => {
                window.location.href = '<?= APP_URL ?>/users';
            }, 1000);
        } else {
            window.showToast('error', d.message);
        }
    } catch (e) {
        window.showToast('error', 'Request failed.');
    }
}
</script>