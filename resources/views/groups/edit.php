<?php
$pageTitle   = 'Edit: ' . $group['name'];
$activePage  = 'groups';
$breadcrumbs = ['Groups' => APP_URL.'/groups', $group['name'] => APP_URL.'/groups/'.$group['id'], 'Edit' => null];
?>
<div class="max-w-2xl mx-auto">
  <div class="flex items-center justify-between mb-5">
    <div>
      <h1 class="text-xl font-bold text-slate-800">Edit Group</h1>
      <p class="text-sm text-slate-400 mt-0.5"><?= htmlspecialchars($group['group_code'] ?? '') ?></p>
    </div>
    <a href="<?= APP_URL ?>/groups/<?= $group['id'] ?>" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-arrow-left"></i> Back
    </a>
  </div>

  <div class="card">
    <div class="card-body">
      <form id="editGroupForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">

        <div class="space-y-4 mb-5">
          <div>
            <label class="form-label required">Group Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($group['name']) ?>"
                   class="form-control" required>
          </div>
          <div>
            <label class="form-label">Description</label>
            <textarea name="description" rows="2" class="form-control"><?= htmlspecialchars($group['description'] ?? '') ?></textarea>
          </div>
          <div>
            <label class="form-label">Meeting Schedule</label>
            <input type="text" name="meeting_schedule"
                   value="<?= htmlspecialchars($group['meeting_schedule'] ?? '') ?>"
                   class="form-control" placeholder="e.g. Every Saturday at 10:00 AM">
          </div>
        </div>

        <div class="mb-5">
          <div class="mb-3 text-xs font-bold text-slate-400 uppercase tracking-wider">Leadership</div>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label class="form-label">Chairperson</label>
              <select name="chairperson_id" class="form-control form-select">
                <option value="">— None —</option>
                <?php foreach ($members ?? [] as $m): ?>
                <option value="<?= $m['id'] ?>" <?= ($group['chairperson_id'] ?? '') == $m['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="form-label">Treasurer</label>
              <select name="treasurer_id" class="form-control form-select">
                <option value="">— None —</option>
                <?php foreach ($members ?? [] as $m): ?>
                <option value="<?= $m['id'] ?>" <?= ($group['treasurer_id'] ?? '') == $m['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="form-label">Secretary</label>
              <select name="secretary_id" class="form-control form-select">
                <option value="">— None —</option>
                <?php foreach ($members ?? [] as $m): ?>
                <option value="<?= $m['id'] ?>" <?= ($group['secretary_id'] ?? '') == $m['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <div class="mb-5">
            <div class="mb-3 text-xs font-bold text-slate-400 uppercase tracking-wider">Group Logo</div>
            <div class="flex items-center gap-4">
                <div class="w-20 h-20 rounded-xl border-2 border-slate-200 bg-slate-50 flex items-center justify-center overflow-hidden">
                    <?php if (!empty($group['avatar'])): ?>
                        <img src="<?= APP_URL ?>/storage/uploads/avatars/<?= htmlspecialchars($group['avatar']) ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <i class="fa-solid fa-building text-3xl text-slate-300"></i>
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
            <option value="<?= $s ?>" <?= $group['status'] === $s ? 'selected' : '' ?>>
              <?= ucfirst($s) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="flex gap-3 justify-end pt-4 border-t border-slate-100">
          <a href="<?= APP_URL ?>/groups/<?= $group['id'] ?>" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-save"></i> Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Avatar preview for edit form
document.getElementById('avatarInput')?.addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) {
        window.showToast('error', 'File too large, max 2MB.');
        this.value = '';
        return;
    }
    const reader = new FileReader();
    reader.onload = function(e) {
        // Find the preview container (the div showing current avatar)
        const previewDiv = document.querySelector('.w-20.h-20.rounded-xl.border-2');
        if (previewDiv) {
            // Replace content with new image
            previewDiv.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">`;
        }
    };
    reader.readAsDataURL(file);
});

document.getElementById('editGroupForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    try {
        const r = await fetch('<?= APP_URL ?>/groups/<?= $group['id'] ?>/update', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        });
        const d = await r.json();
        if (d.success) {
            window.showToast('success', d.message);
            setTimeout(() => { window.location.href = d.data?.redirect || '<?= APP_URL ?>/groups'; }, 800);
        } else {
            window.showToast('error', d.message);
        }
    } catch(e) { window.showToast('error', 'Request failed.'); }
});
</script>