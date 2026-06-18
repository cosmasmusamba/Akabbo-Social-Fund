<?php
$pageTitle   = 'Change Password';
$activePage  = 'profile';
$breadcrumbs = ['Profile' => APP_URL . '/profile', 'Change Password' => null];
?>
<div class="max-w-lg mx-auto">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Change Password</h1>
            <p class="text-sm text-slate-400 mt-0.5">Update your account password</p>
        </div>
        <a href="<?= APP_URL ?>/profile" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>
    
    <div class="card">
        <div class="card-body">
            <form id="changePwForm">
                <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
                
                <div class="mb-4">
                    <label class="form-label required">Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                
                <div class="mb-4">
                    <label class="form-label required">New Password</label>
                    <input type="password" name="new_password" class="form-control" minlength="8" required>
                    <p class="text-xs text-slate-400 mt-1">Minimum 8 characters</p>
                </div>
                
                <div class="mb-6">
                    <label class="form-label required">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                
                <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 mb-5 text-sm text-amber-700">
                    <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                    You will be logged out after changing your password.
                </div>
                
                <button type="submit" class="btn btn-primary w-full">
                    <i class="fa-solid fa-key"></i> Change Password
                </button>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('changePwForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Changing…';
    
    const formData = new FormData(this);
    try {
        const response = await fetch('<?= APP_URL ?>/profile/change-password', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            window.showToast('success', data.message);
            setTimeout(() => {
                window.location.href = data.data?.redirect || '<?= APP_URL ?>/login';
            }, 1200);
        } else {
            window.showToast('error', data.message);
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