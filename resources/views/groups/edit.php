<?php
$pageTitle   = $pageTitle ?? 'Edit: ' . ($group['name'] ?? 'Group');
$activePage  = $activePage ?? 'groups';
$breadcrumbs = $breadcrumbs ?? ['Groups' => APP_URL . '/groups', $group['name'] ?? '' => APP_URL . '/groups/' . ($group['id'] ?? 0), 'Edit' => null];
$group       = $group ?? [];
$members     = $members ?? [];
?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Edit Group</h1>
            <p class="text-sm text-slate-400 mt-0.5"><?= htmlspecialchars($group['group_code'] ?? '') ?></p>
        </div>
        <a href="<?= APP_URL ?>/groups/<?= $group['id'] ?? 0 ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form id="editGroupForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
                
                <div class="mb-5">
                    <div class="form-section-label mb-3" style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--green-mid, #136b55)">
                        <i class="fa-solid fa-info-circle mr-1"></i> Group Details
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="form-label required" for="name">Group Name</label>
                            <input type="text" id="name" name="name" value="<?= htmlspecialchars($group['name'] ?? '') ?>" class="form-control" required>
                        </div>
                        <div>
                            <label class="form-label" for="description">Description</label>
                            <textarea id="description" name="description" rows="2" class="form-control"><?= htmlspecialchars($group['description'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="form-label" for="meeting_schedule">Meeting Schedule</label>
                            <input type="text" id="meeting_schedule" name="meeting_schedule" value="<?= htmlspecialchars($group['meeting_schedule'] ?? '') ?>" class="form-control" placeholder="e.g. Every Saturday at 10:00 AM">
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
                                <option value="">— None —</option>
                                <?php foreach ($members as $m): ?>
                                    <option value="<?= $m['id'] ?? '' ?>" <?= ($group['chairperson_id'] ?? '') == ($m['id'] ?? '') ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="treasurer_id">Treasurer</label>
                            <select id="treasurer_id" name="treasurer_id" class="form-control form-select">
                                <option value="">— None —</option>
                                <?php foreach ($members as $m): ?>
                                    <option value="<?= $m['id'] ?? '' ?>" <?= ($group['treasurer_id'] ?? '') == ($m['id'] ?? '') ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="secretary_id">Secretary</label>
                            <select id="secretary_id" name="secretary_id" class="form-control form-select">
                                <option value="">— None —</option>
                                <?php foreach ($members as $m): ?>
                                    <option value="<?= $m['id'] ?? '' ?>" <?= ($group['secretary_id'] ?? '') == ($m['id'] ?? '') ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mb-5">
                    <div class="mb-3" style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--green-mid, #136b55)">
                        <i class="fa-solid fa-image"></i> Group Logo / Avatar
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="w-20 h-20 rounded-xl border-2 border-slate-200 bg-slate-50 flex items-center justify-center overflow-hidden" id="avatarPreviewContainer">
                            <?php if (!empty($group['avatar'])): ?>
                                <img id="avatarPreview" src="<?= APP_URL ?>/storage/uploads/avatars/<?= htmlspecialchars($group['avatar']) ?>" class="w-full h-full object-cover">
                                <i id="avatarPlaceholderIcon" class="fa-solid fa-building text-3xl text-slate-300 hidden"></i>
                            <?php else: ?>
                                <img id="avatarPreview" src="" class="hidden w-full h-full object-cover">
                                <i id="avatarPlaceholderIcon" class="fa-solid fa-building text-3xl text-slate-300"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <input type="file" id="avatarInput" name="avatar" accept="image/jpeg,image/png,image/webp" class="hidden">
                            <button type="button" onclick="document.getElementById('avatarInput').click()" class="btn btn-secondary btn-sm">
                                <i class="fa-solid fa-upload"></i> Change Logo
                            </button>
                            <p class="text-xs text-slate-400 mt-1">Leave empty to keep current</p>
                        </div>
                    </div>
                </div>

                <div class="mb-5">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control form-select" style="max-width:200px">
                        <?php foreach (['active', 'inactive', 'closed'] as $s): ?>
                            <option value="<?= $s ?>" <?= ($group['status'] ?? 'active') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex gap-3 justify-end pt-4 border-t border-slate-100">
                    <a href="<?= APP_URL ?>/groups/<?= $group['id'] ?? 0 ?>" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fa-solid fa-save"></i> Save Changes
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

document.getElementById('editGroupForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
    
    const formData = new FormData(this);
    try {
        const response = await fetch('<?= APP_URL ?>/groups/<?= $group['id'] ?? 0 ?>/update', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            window.showToast('success', data.message);
            setTimeout(() => { window.location.href = data.data?.redirect || '<?= APP_URL ?>/groups/<?= $group['id'] ?? 0 ?>'; }, 800);
        } else {
            window.showToast('error', data.message || 'Update failed.');
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