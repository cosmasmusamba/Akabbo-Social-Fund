<?php
$pageTitle   = 'Create System User';
$activePage  = 'users';
$breadcrumbs = ['Users' => APP_URL . '/users', 'Create' => null];
$roles       = $roles ?? [];
?>
<div class="max-w-xl mx-auto">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Create System User</h1>
            <p class="text-sm text-slate-400 mt-0.5">Add a new user to the system</p>
        </div>
        <a href="<?= APP_URL ?>/users" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>
    
    <div class="card">
        <div class="card-body">
            <form id="createUserForm">
                <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="form-label required">First Name</label>
                        <input type="text" name="first_name" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label required">Last Name</label>
                        <input type="text" name="last_name" class="form-control" required>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="form-label required">Email Address</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-control" placeholder="+256 700 000000">
                    </div>
                    <div>
                        <label class="form-label required">Role</label>
                        <select name="role_id" class="form-control form-select" required>
                            <option value="">Select role…</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id'] ?? '' ?>"><?= htmlspecialchars($r['name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="form-label">National ID (NIN)</label>
                        <input type="text" name="nin" class="form-control" placeholder="e.g. CM99060105UKRG">
                        <p class="text-xs text-slate-400 mt-1"><i class="fa-solid fa-link mr-1"></i> Auto-links to member record</p>
                    </div>
                    <div>
                        <label class="form-label">Passport Number</label>
                        <input type="text" name="passport_number" class="form-control" placeholder="Fallback if NIN unavailable">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="form-label">Password <span class="text-slate-400 font-normal">(leave blank to auto-generate)</span></label>
                        <input type="password" name="password" class="form-control" placeholder="Leave blank to generate">
                    </div>
                </div>
                
                <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-sm text-amber-700 mb-5">
                    <i class="fa-solid fa-triangle-exclamation mr-1.5"></i>
                    The user will be required to change their password on first login. 
                    <strong>Tip:</strong> If a matching member is found via NIN, Passport, or Email, their system account will be automatically linked to their member profile.
                </div>
                
                <div class="flex gap-3 justify-end pt-4 border-t border-slate-100">
                    <a href="<?= APP_URL ?>/users" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-user-plus"></i> Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('createUserForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Creating…';
    
    const formData = new FormData(this);
    try {
        const response = await fetch('<?= APP_URL ?>/users/store', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            let msg = data.message;
            if (data.data?.temp_password) {
                msg += ` Temp password: ${data.data.temp_password}`;
            }
            window.showToast('success', msg);
            setTimeout(() => { 
                window.location.href = data.data?.redirect || '<?= APP_URL ?>/users'; 
            }, 2000);
        } else {
            window.showToast('error', data.message || 'Creation failed.');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    } catch (err) {
        window.showToast('error', 'Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});
</script>