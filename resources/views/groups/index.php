<?php
use App\Helpers\Format;
$pageTitle   = 'Groups';
$activePage  = 'groups';
$breadcrumbs = ['Groups' => null];
?>
<div class="flex items-center justify-between mb-5">
  <div>
    <h1 class="text-xl font-bold text-slate-800">Savings Groups</h1>
    <p class="text-sm text-slate-400 mt-0.5"><?= count($groups) ?> groups registered</p>
  </div>
  <?php if ($auth->can('members.create')): ?>
  <a href="<?= APP_URL ?>/groups/create" class="btn btn-primary btn-sm">
    <i class="fa-solid fa-plus"></i> New Group
  </a>
  <?php endif; ?>
</div>

<?php if (empty($groups)): ?>
<div class="card">
  <div class="card-body text-center py-16">
    <i class="fa-solid fa-people-group text-6xl text-slate-200 block mb-4"></i>
    <p class="text-slate-500 font-semibold text-lg mb-1">No groups yet</p>
    <p class="text-slate-400 text-sm mb-5">Create savings groups to organise your members</p>
    <?php if ($auth->can('members.create')): ?>
    <a href="<?= APP_URL ?>/groups/create" class="btn btn-primary">
      <i class="fa-solid fa-plus"></i> Create First Group
    </a>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
  <?php foreach ($groups as $g): ?>
  <div class="card hover:shadow-md transition-all">
    <div class="card-body">
      <!-- Card header -->
      <div class="flex items-start justify-between mb-3">
        <div class="flex items-center gap-3">
            <?php if (!empty($g['avatar'])): ?>
                <img src="<?= APP_URL ?>/storage/uploads/avatars/<?= htmlspecialchars($g['avatar']) ?>" 
                     class="w-11 h-11 rounded-xl object-cover">
            <?php else: ?>
                <div class="w-11 h-11 rounded-xl flex items-center justify-center text-white font-bold text-base flex-shrink-0"
                     style="background:linear-gradient(135deg,#136b55,#1a8f6f)">
                    <?= strtoupper(substr($g['name'], 0, 2)) ?>
                </div>
            <?php endif; ?>
            <div>
                <div class="font-bold text-slate-800"><?= htmlspecialchars($g['name']) ?></div>
                <div class="text-xs text-slate-400 font-mono"><?= htmlspecialchars($g['group_code'] ?? '') ?></div>
            </div>
        </div>
        <?= Format::statusPill($g['status']) ?>
      </div>

      <?php if (!empty($g['description'])): ?>
      <p class="text-sm text-slate-500 mb-3 leading-relaxed">
        <?= htmlspecialchars(Format::truncate($g['description'], 80)) ?>
      </p>
      <?php endif; ?>

      <!-- Stats row -->
      <div class="grid grid-cols-3 gap-2 py-3 border-t border-b border-slate-100 mb-3 text-center">
        <div>
          <div class="text-lg font-black text-slate-800"><?= number_format($g['member_count'] ?? 0) ?></div>
          <div class="text-[10px] text-slate-400 font-semibold uppercase tracking-wide">Members</div>
        </div>
        <div>
          <div class="text-lg font-black text-emerald-700"><?= Format::currencyCompact((float)($g['total_savings'] ?? 0)) ?></div>
          <div class="text-[10px] text-slate-400 font-semibold uppercase tracking-wide">Savings</div>
        </div>
        <div>
          <div class="text-lg font-black text-blue-700"><?= number_format($g['active_loans'] ?? 0) ?></div>
          <div class="text-[10px] text-slate-400 font-semibold uppercase tracking-wide">Loans</div>
        </div>
      </div>

      <?php if (!empty($g['meeting_schedule'])): ?>
      <div class="flex items-center gap-1.5 text-xs text-slate-500 mb-3">
        <i class="fa-regular fa-calendar text-slate-400"></i>
        <?= htmlspecialchars($g['meeting_schedule']) ?>
      </div>
      <?php endif; ?>

      <!-- Actions -->
      <div class="flex gap-2">
        <a href="<?= APP_URL ?>/groups/<?= $g['id'] ?>"
           class="btn btn-secondary btn-sm flex-1 justify-center">
          <i class="fa-solid fa-eye text-xs"></i> View
        </a>
        <?php if ($auth->can('members.edit')): ?>
        <a href="<?= APP_URL ?>/groups/<?= $g['id'] ?>/edit"
           class="btn btn-secondary btn-sm px-3" title="Edit group">
          <i class="fa-solid fa-pen text-xs"></i>
        </a>
        <?php endif; ?>
        <?php if ($auth->can('members.delete') && ($g['member_count'] ?? 0) == 0): ?>
        <button onclick="deleteGroup(<?= $g['id'] ?>,'<?= addslashes($g['name']) ?>')"
                class="btn btn-danger btn-sm px-3" title="Delete group">
          <i class="fa-solid fa-trash text-xs"></i>
        </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
async function deleteGroup(id, name) {
  window.confirmAction({
    title: 'Delete Group',
    message: `Delete group "${name}"? This cannot be undone.`,
    confirmText: 'Delete',
    type: 'danger',
    onConfirm: async () => {
      const fd = new FormData();
      fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
      try {
        const r = await fetch(`<?= APP_URL ?>/groups/${id}/delete`, {
          method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: fd
        });
        const d = await r.json();
        if (d.success) { window.showToast('success', d.message); setTimeout(() => location.reload(), 800); }
        else window.showToast('error', d.message);
      } catch(e) { window.showToast('error', 'Request failed.'); }
    }
  });
}
</script>