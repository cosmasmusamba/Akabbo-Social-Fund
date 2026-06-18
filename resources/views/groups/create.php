<?php
$pageTitle   = $pageTitle ?? 'Create Group';
$activePage  = $activePage ?? 'groups';
$breadcrumbs = $breadcrumbs ?? ['Groups' => APP_URL . '/groups', 'Create' => null];
$members     = $members ?? [];
?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Create Savings Group</h1>
            <p class="text-sm text-slate-400 mt-0.5">Set up a new group, SACCO or investment club</p>
        </div>
        <a href="<?= APP_URL ?>/groups" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form id="createGroupForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
                
                <div class="mb-5">
                    <div class="form-section-label mb-3" style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--green-mid, #136b55)">
                        <i class="fa-solid fa-info-circle mr-1"></i> Group Details
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="form-label required" for="name">Group Name</label>
                            <input type="text" id="name" name="name" class="form-control" placeholder="e.g. Kampala Women's SACCO" required>
                        </div>
                        <div>
                            <label class="form-label" for="description">Description</label>
                            <textarea id="description" name="description" rows="2" class="form-control" placeholder="Brief description of the group's purpose and objectives…"></textarea>
                        </div>
                        <div>
                            <label class="form-label" for="meeting_schedule">Meeting Schedule</label>
                            <input type="text" id="meeting_schedule" name="meeting_schedule" class="form-control" placeholder="e.g. Every Saturday at 10:00 AM">
                            <p class="text-xs text-slate-400 mt-1">When and where the group meets regularly</p>
                        </div>
                    </div>
                </div>

                <div class="mb-5">
                    <div class="mb-3" style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--green-mid, #136b55)">
                        <i class="fa-solid fa-crown mr-1"></i> Group Leadership
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="form-label" for="chairperson_id">Chairperson</label>
                            <select id="chairperson_id" name="chairperson_id" class="form-control form-select">
                                <option value="">— Select member —</option>
                                <?php foreach ($members as $m): ?>
                                    <option value="<?= $m['id'] ?? '' ?>"><?= htmlspecialchars(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?> (<?= htmlspecialchars($m['member_no'] ?? '') ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="treasurer_id">Treasurer</label>
                            <select id="treasurer_id" name="treasurer_id" class="form-control form-select">
                                <option value="">— Select member —</option>
                                <?php foreach ($members as $m): ?>
                                    <option value="<?= $m['id'] ?? '' ?>"><?= htmlspecialchars(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="secretary_id">Secretary</label>
                            <select id="secretary_id" name="secretary_id" class="form-control form-select">
                                <option value="">— Select member —</option>
                                <?php foreach ($members as $m): ?>
                                    <option value="<?= $m['id'] ?? '' ?>"><?= htmlspecialchars(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <p class="text-xs text-slate-400 mt-2">Leadership positions are optional and can be assigned later.</p>
                </div>

                <div class="mb-5">
                    <div class="mb-3" style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--green-mid, #136b55)">
                        <i class="fa-solid fa-image"></i> Group Logo / Avatar
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="w-20 h-20 rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 flex items-center justify-center overflow-hidden" id="avatarPreviewContainer">
                            <i class="fa-solid fa-building text-3xl text-slate-300" id="avatarPlaceholderIcon"></i>
                            <img id="avatarPreview" src="" class="hidden w-full h-full object-cover">
                        </div>
                        <div>
                            <input type="file" id="avatarInput" name="avatar" accept="image/jpeg,image/png,image/webp" class="hidden">
                            <button type="button" onclick="document.getElementById('avatarInput').click()" class="btn btn-secondary btn-sm">
                                <i class="fa-solid fa-upload"></i> Upload Logo
                            </button>
                            <p class="text-xs text-slate-400 mt-1">JPG, PNG, WebP · Max 2MB</p>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 justify-end pt-4 border-t border-slate-100">
                    <a href="<?= APP_URL ?>/groups" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fa-solid fa-people-group"></i> Create Group
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('avatarInput').addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) {
        window.showToast('error', 'File too large, max 2MB.');
        this.value = '';
        return;
    }
    const reader = new FileReader();
    reader.onload = function(e) {
        const img = document.getElementById('avatarPreview');
        img.src = e.target.result;
        img.classList.remove('hidden');
        document.getElementById('avatarPlaceholderIcon').classList.add('hidden');
    };
    reader.readAsDataURL(file);
});

document.getElementById('createGroupForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Creating...';
    
    const formData = new FormData(this);
    try {
        const response = await fetch('<?= APP_URL ?>/groups/store', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            window.showToast('success', data.message);
            setTimeout(() => { window.location.href = data.data?.redirect || '<?= APP_URL ?>/groups'; }, 800);
        } else {
            window.showToast('error', data.message || 'Creation failed.');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    } catch (err) {
        window.showToast('error', 'Network error. Check console.');
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});
</script>