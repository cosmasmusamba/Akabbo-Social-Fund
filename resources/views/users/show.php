<?php
use App\Helpers\Format;

$pageTitle   = $user['first_name'] . ' ' . $user['last_name'];
$activePage  = 'users';
$breadcrumbs = ['Users' => APP_URL . '/users', $pageTitle => null];
?>

<div class="max-w-6xl mx-auto">
    <!-- Header & Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div class="breadcrumb">
            <a href="<?= APP_URL ?>/dashboard"><i class="fa-solid fa-house-chimney text-xs"></i></a>
            <span class="sep">/</span>
            <a href="<?= APP_URL ?>/users">Users</a>
            <span class="sep">/</span>
            <span class="current"><?= htmlspecialchars($pageTitle) ?></span>
        </div>
        
        <div class="flex gap-2 flex-wrap">
            <?php if ($auth->can('users.edit') && $user['id'] != ($_SESSION['user_id'] ?? 0)): ?>
                <a href="<?= APP_URL ?>/users/<?= $user['id'] ?>/edit" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-pen"></i> Edit
                </a>
                <button onclick="resetPwd()" class="btn btn-secondary btn-sm" title="Reset Password">
                    <i class="fa-solid fa-key"></i> Reset Password
                </button>
                
                <?php if (($user['status'] ?? '') === 'active'): ?>
                    <button onclick="disableUser(<?= (int)$user['id'] ?>, '<?= addslashes($pageTitle) ?>')" 
                            class="btn btn-sm" style="background:#fef3c7;color:#92400e;border:1px solid #fcd34d;" title="Suspend User">
                        <i class="fa-solid fa-user-slash"></i> Suspend
                    </button>
                <?php else: ?>
                    <button onclick="activateUser(<?= (int)$user['id'] ?>, '<?= addslashes($pageTitle) ?>')" 
                            class="btn btn-sm" style="background:#dcfce7;color:#166534;border:1px solid #a7f3d0;" title="Activate User">
                        <i class="fa-solid fa-user-check"></i> Activate
                    </button>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($auth->can('users.delete') && $user['id'] != ($_SESSION['user_id'] ?? 0)): ?>
                <button onclick="deleteUser(<?= (int)$user['id'] ?>, '<?= addslashes($pageTitle) ?>')" 
                        class="btn btn-danger btn-sm" title="Deactivate User">
                    <i class="fa-solid fa-trash"></i> Deactivate
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Profile Hero Card -->
    <div class="card mb-6 overflow-hidden">
        <div class="h-24 bg-gradient-to-r from-blue-600 to-indigo-600"></div>
        <div class="px-6 pb-6">
            <div class="flex flex-col sm:flex-row sm:items-end gap-4 -mt-10">
                <div class="w-24 h-24 rounded-full overflow-hidden border-4 border-white shadow-md bg-white flex items-center justify-center text-3xl font-bold text-slate-400">
                    <?php if (!empty($user['avatar'])): ?>
                        <img src="<?= APP_URL ?>/storage/uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>" class="w-full h-full object-cover" alt="">
                    <?php else: ?>
                        <?= Format::initials($user['first_name'] . ' ' . $user['last_name']) ?>
                    <?php endif; ?>
                </div>
                <div class="flex-1 pb-2">
                    <div class="flex flex-wrap items-center gap-3 mb-1">
                        <h1 class="text-2xl font-bold text-slate-800"><?= htmlspecialchars($pageTitle) ?></h1>
                        <?= Format::statusPill($user['status'] ?? 'active') ?>
                    </div>
                    <div class="flex flex-wrap gap-4 text-sm text-slate-500">
                        <span><i class="fa-solid fa-envelope mr-1.5 text-slate-400"></i><?= htmlspecialchars($user['email']) ?></span>
                        <?php if (!empty($user['phone'])): ?>
                            <span><i class="fa-solid fa-phone mr-1.5 text-slate-400"></i><?= htmlspecialchars($user['phone']) ?></span>
                        <?php endif; ?>
                        <span><i class="fa-solid fa-user-shield mr-1.5 text-slate-400"></i><?= htmlspecialchars($user['role_name'] ?? 'Unknown Role') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column: Account & Linked Member -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Account Details -->
            <div class="card">
                <div class="card-header">
                    <h3 class="section-title"><i class="fa-solid fa-id-card mr-2 text-blue-600"></i>Account Details</h3>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                        <div>
                            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Email Address</div>
                            <div class="text-sm font-medium text-slate-800"><?= htmlspecialchars($user['email']) ?></div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Phone Number</div>
                            <div class="text-sm font-medium text-slate-800"><?= htmlspecialchars($user['phone'] ?? '—') ?></div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Employee ID</div>
                            <div class="text-sm font-medium text-slate-800 font-mono"><?= htmlspecialchars($user['employee_id'] ?? '—') ?></div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Role</div>
                            <div class="text-sm font-medium text-slate-800"><?= htmlspecialchars($user['role_name'] ?? '—') ?></div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">National ID (NIN)</div>
                            <div class="text-sm font-medium text-slate-800 font-mono"><?= htmlspecialchars($user['nin'] ?? '—') ?></div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Passport Number</div>
                            <div class="text-sm font-medium text-slate-800 font-mono"><?= htmlspecialchars($user['passport_number'] ?? '—') ?></div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Account Created</div>
                            <div class="text-sm font-medium text-slate-800"><?= Format::datetime($user['created_at']) ?></div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Last Updated</div>
                            <div class="text-sm font-medium text-slate-800"><?= Format::datetime($user['updated_at']) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Linked Member Profile -->
            <?php if ($member): ?>
            <div class="card">
                <div class="card-header flex items-center justify-between">
                    <h3 class="section-title"><i class="fa-solid fa-link mr-2 text-emerald-600"></i>Linked Member Profile</h3>
                    <a href="<?= APP_URL ?>/members/<?= $member['id'] ?>" class="btn btn-secondary btn-sm">
                        <i class="fa-solid fa-arrow-right"></i> View Full Profile
                    </a>
                </div>
                <div class="card-body">
                    <div class="flex items-center gap-4 mb-5 p-4 bg-emerald-50 rounded-xl border border-emerald-100">
                        <div class="w-14 h-14 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-lg font-bold flex-shrink-0">
                            <?= Format::initials($member['first_name'] . ' ' . $member['last_name']) ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold text-lg text-slate-800 truncate"><?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?></div>
                            <div class="text-sm text-slate-500"><?= htmlspecialchars($member['member_no']) ?> · <?= htmlspecialchars($member['phone']) ?></div>
                        </div>
                        <div class="flex flex-col items-end gap-1 flex-shrink-0">
                            <?= Format::statusPill($member['status'] ?? 'active') ?>
                            <?php if (!empty($member['kyc_verified'])): ?>
                                <span class="text-xs font-bold text-emerald-600"><i class="fa-solid fa-shield-check"></i> KYC Verified</span>
                            <?php else: ?>
                                <span class="text-xs font-bold text-amber-600"><i class="fa-solid fa-clock"></i> KYC Pending</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                        <div>
                            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Gender</div>
                            <div class="text-sm font-medium text-slate-800"><?= ucfirst($member['gender'] ?? '—') ?></div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Date of Birth</div>
                            <div class="text-sm font-medium text-slate-800"><?= Format::date($member['date_of_birth'] ?? '') ?></div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Email</div>
                            <div class="text-sm font-medium text-slate-800"><?= htmlspecialchars($member['email'] ?? '—') ?></div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">District</div>
                            <div class="text-sm font-medium text-slate-800"><?= htmlspecialchars($member['district'] ?? '—') ?></div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Occupation</div>
                            <div class="text-sm font-medium text-slate-800"><?= htmlspecialchars($member['occupation'] ?? '—') ?></div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Employer</div>
                            <div class="text-sm font-medium text-slate-800"><?= htmlspecialchars($member['employer'] ?? '—') ?></div>
                        </div>
                        <div class="md:col-span-2">
                            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Address</div>
                            <div class="text-sm font-medium text-slate-800"><?= htmlspecialchars($member['address'] ?? '—') ?></div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Joined</div>
                            <div class="text-sm font-medium text-slate-800"><?= Format::date($member['membership_date']) ?></div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Shareholder Status</div>
                            <div class="text-sm font-medium text-slate-800">
                                <?= !empty($member['is_shareholder']) ? '<span class="text-emerald-600 font-bold">Yes</span> (' . number_format($member['shares_held'] ?? 0) . ' shares)' : '<span class="text-slate-400">No</span>' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-10">
                    <i class="fa-solid fa-link-slash text-4xl text-slate-200 mb-3"></i>
                    <p class="text-slate-500 font-semibold">No Linked Member Profile</p>
                    <p class="text-sm text-slate-400 mt-1">This user account is not linked to any member record via NIN, Passport, or Email.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Column: Security & Sessions -->
        <div class="space-y-6">
            <!-- Security Status -->
            <div class="card">
                <div class="card-header">
                    <h3 class="section-title"><i class="fa-solid fa-shield-halved mr-2 text-amber-600"></i>Security Status</h3>
                </div>
                <div class="card-body space-y-3">
                    <div class="flex justify-between items-center p-3 rounded-lg bg-slate-50">
                        <span class="text-sm text-slate-600">Must Change Password</span>
                        <?php if (!empty($user['must_change_password'])): ?>
                            <span class="badge bg-amber-100 text-amber-700 border-amber-200">Yes</span>
                        <?php else: ?>
                            <span class="badge bg-emerald-100 text-emerald-700 border-emerald-200">No</span>
                        <?php endif; ?>
                    </div>
                    <div class="flex justify-between items-center p-3 rounded-lg bg-slate-50">
                        <span class="text-sm text-slate-600">Email Verified</span>
                        <?php if (!empty($user['email_verified'])): ?>
                            <span class="badge bg-emerald-100 text-emerald-700 border-emerald-200">Verified</span>
                        <?php else: ?>
                            <span class="badge bg-slate-100 text-slate-600 border-slate-200">Pending</span>
                        <?php endif; ?>
                    </div>
                    <div class="flex justify-between items-center p-3 rounded-lg bg-slate-50">
                        <span class="text-sm text-slate-600">Failed Login Attempts</span>
                        <span class="font-bold text-slate-800"><?= (int)($user['failed_login_attempts'] ?? 0) ?></span>
                    </div>
                    <?php if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()): ?>
                    <div class="flex justify-between items-center p-3 rounded-lg bg-red-50 border border-red-100">
                        <span class="text-sm text-red-700 font-semibold">Account Locked Until</span>
                        <span class="text-sm font-bold text-red-800"><?= Format::datetime($user['locked_until']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="flex justify-between items-center p-3 rounded-lg bg-slate-50">
                        <span class="text-sm text-slate-600">Last Login</span>
                        <span class="text-sm font-medium text-slate-800"><?= !empty($user['last_login']) ? Format::timeAgo($user['last_login']) : 'Never' ?></span>
                    </div>
                    <div class="flex justify-between items-center p-3 rounded-lg bg-slate-50">
                        <span class="text-sm text-slate-600">Last IP Address</span>
                        <span class="text-sm font-mono text-slate-800"><?= htmlspecialchars($user['last_login_ip'] ?? '—') ?></span>
                    </div>
                </div>
            </div>

            <!-- Recent Login Sessions -->
            <div class="card">
                <div class="card-header">
                    <h3 class="section-title"><i class="fa-solid fa-clock-rotate-left mr-2 text-indigo-600"></i>Recent Sessions</h3>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($sessions)): ?>
                        <div class="text-center py-8 text-slate-400 text-sm">No login sessions recorded.</div>
                    <?php else: ?>
                        <div class="divide-y divide-slate-100 max-h-80 overflow-y-auto">
                            <?php foreach ($sessions as $session): ?>
                                <div class="p-4 hover:bg-slate-50 transition-colors">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-xs font-bold text-slate-800"><?= Format::datetime($session['login_at']) ?></span>
                                        <?= Format::statusPill($session['status'] ?? 'active') ?>
                                    </div>
                                    <div class="text-xs text-slate-500 flex items-center gap-2 mb-1">
                                        <i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($session['ip_address'] ?? 'Unknown IP') ?>
                                    </div>
                                    <div class="text-xs text-slate-400 truncate" title="<?= htmlspecialchars($session['user_agent'] ?? '') ?>">
                                        <i class="fa-solid fa-desktop"></i> <?= htmlspecialchars(Format::truncate($session['user_agent'] ?? '', 50)) ?>
                                    </div>
                                    <?php if (!empty($session['logout_at'])): ?>
                                        <div class="text-xs text-slate-400 mt-1">
                                            <i class="fa-solid fa-right-from-bracket"></i> Logged out: <?= Format::datetime($session['logout_at']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Reset Password
async function resetPwd() {
    window.confirmAction({
        title: 'Reset Password',
        message: 'Generate a new temporary password for this user?',
        confirmText: 'Reset',
        type: 'warning',
        onConfirm: async () => {
            const fd = new FormData();
            fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
            try {
                const r = await fetch('<?= APP_URL ?>/users/<?= $user['id'] ?? 0 ?>/reset-password', {
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

// Lifecycle Management Functions
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