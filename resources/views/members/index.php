<?php
use App\Helpers\Format;
$pageTitle   = 'Members';
$activePage  = 'members';
$breadcrumbs = ['Members' => null];
?>

<!-- ── Page header ──────────────────────────────────────────────── -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
  <div>
    <h1 class="text-xl font-bold text-slate-800">Members</h1>
    <p class="text-sm text-slate-400 mt-0.5">
      <?= number_format($result['total'] ?? 0) ?> total members registered
    </p>
  </div>
  <div class="flex items-center gap-2 flex-wrap">
    <a href="<?= APP_URL ?>/members/import" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-file-import"></i> Import
    </a>
    <button onclick="exportMembers()" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-file-export"></i> Export
    </button>
    <?php if ($auth->can('members.create')): ?>
    <a href="<?= APP_URL ?>/members/create" class="btn btn-primary btn-sm">
      <i class="fa-solid fa-user-plus"></i> Add Member
    </a>
    <?php endif; ?>
  </div>
</div>

<!-- ── Filters bar ───────────────────────────────────────────────── -->
<div class="card mb-5">
  <div class="card-body py-4">
    <form method="GET" class="flex flex-col sm:flex-row gap-3 flex-wrap" id="filterForm">
      <!-- Search -->
      <div class="relative flex-1 min-w-[200px]">
        <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
        <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
          placeholder="Search name, phone, member no…"
          class="form-control pl-9 py-2 text-sm">
      </div>

      <!-- Status filter -->
      <select name="status" class="form-control form-select py-2 text-sm w-full sm:w-auto min-w-[140px]"
              onchange="this.closest('form').submit()">
        <option value="">All Statuses</option>
        <?php foreach(['active','inactive','suspended','exited'] as $s): ?>
          <option value="<?= $s ?>" <?= ($_GET['status'] ?? '') === $s ? 'selected' : '' ?>>
            <?= ucfirst($s) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <!-- KYC filter -->
      <select name="kyc" class="form-control form-select py-2 text-sm w-full sm:w-auto min-w-[140px]"
              onchange="this.closest('form').submit()">
        <option value="">All KYC</option>
        <option value="1" <?= ($_GET['kyc'] ?? '') === '1' ? 'selected' : '' ?>>KYC Verified</option>
        <option value="0" <?= ($_GET['kyc'] ?? '') === '0' ? 'selected' : '' ?>>KYC Pending</option>
      </select>

      <button type="submit" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-filter"></i> Filter
      </button>
      <?php if (!empty($_GET['search']) || !empty($_GET['status']) || isset($_GET['kyc'])): ?>
        <a href="<?= APP_URL ?>/members" class="btn btn-secondary btn-sm">
          <i class="fa-solid fa-xmark"></i> Clear
        </a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- ── Status Summary pills ──────────────────────────────────────── -->
<div class="flex flex-wrap gap-2 mb-4">
  <?php
  $filterPills = [
    ['label'=>'All',       'val'=>'', 'count'=>$memberStats['total_members']??0,    'color'=>'bg-slate-100 text-slate-700 border-slate-200'],
    ['label'=>'Active',    'val'=>'active',    'count'=>$memberStats['active_members']??0,    'color'=>'bg-emerald-50 text-emerald-700 border-emerald-200'],
    ['label'=>'Inactive',  'val'=>'inactive',  'count'=>$memberStats['inactive_members']??0,  'color'=>'bg-slate-100 text-slate-600 border-slate-200'],
    ['label'=>'Suspended', 'val'=>'suspended', 'count'=>$memberStats['suspended_members']??0, 'color'=>'bg-amber-50 text-amber-700 border-amber-200'],
  ];
  $currentStatus = $_GET['status'] ?? '';
  foreach ($filterPills as $pill):
  ?>
  <a href="?status=<?= $pill['val'] ?><?= !empty($_GET['search']) ? '&search='.urlencode($_GET['search']) : '' ?>"
     class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border transition-all
            <?= $currentStatus === $pill['val'] ? 'ring-2 ring-offset-1 ring-current shadow-sm' : 'opacity-70 hover:opacity-100' ?>
            <?= $pill['color'] ?>">
    <?= $pill['label'] ?>
    <span class="font-bold"><?= number_format($pill['count']) ?></span>
  </a>
  <?php endforeach; ?>
</div>

<!-- ── Members Table ─────────────────────────────────────────────── -->
<div class="card">
  <div class="overflow-x-auto">
    <table class="data-table">
      <thead>
        <tr>
          <th><input type="checkbox" id="selectAll" title="Select all"></th>
          <th>Member</th>
          <th class="hidden sm:table-cell">Member No.</th>
          <th class="hidden md:table-cell">Phone</th>
          <th class="hidden lg:table-cell">Total Savings</th>
          <th class="hidden lg:table-cell">Active Loans</th>
          <th>Status</th>
          <th class="hidden md:table-cell">KYC</th>
          <th class="hidden md:table-cell">Joined</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="membersTableBody">
        <?php if (empty($result['data'])): ?>
          <tr>
            <td colspan="10" class="text-center py-12">
              <div class="inline-flex flex-col items-center">
                <i class="fa-regular fa-users text-5xl text-slate-200 mb-3"></i>
                <p class="text-slate-500 font-semibold mb-1">No members found</p>
                <p class="text-slate-400 text-sm mb-4">
                  <?= !empty($_GET['search']) ? 'Try a different search term' : 'Register your first member to get started' ?>
                </p>
                <?php if ($auth->can('members.create')): ?>
                  <a href="<?= APP_URL ?>/members/create" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-user-plus"></i> Add First Member
                  </a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($result['data'] as $m): ?>
          <tr class="member-row" data-id="<?= $m['id'] ?>">
            <td><input type="checkbox" class="row-check" value="<?= $m['id'] ?>"></td>
            <td>
              <div class="flex items-center gap-3">
                <?php if ($m['avatar']): ?>
                  <img src="<?= APP_URL ?>/storage/uploads/avatars/<?= htmlspecialchars($m['avatar']) ?>"
                       class="w-9 h-9 rounded-full object-cover border-2 border-slate-100" alt="">
                <?php else: ?>
                  <div class="avatar-circle w-9 h-9 text-xs">
                    <?= Format::initials($m['first_name'].' '.$m['last_name']) ?>
                  </div>
                <?php endif; ?>
                <div>
                  <a href="<?= APP_URL ?>/members/<?= $m['id'] ?>"
                     class="text-sm font-semibold text-slate-800 hover:text-green-700 transition-colors">
                    <?= htmlspecialchars($m['first_name'].' '.$m['last_name']) ?>
                  </a>
                  <div class="text-xs text-slate-400"><?= htmlspecialchars($m['district'] ?? '') ?></div>
                </div>
              </div>
            </td>
            <td class="hidden sm:table-cell">
              <span class="font-mono text-xs text-slate-600 bg-slate-100 px-2 py-0.5 rounded">
                <?= htmlspecialchars($m['member_no']) ?>
              </span>
            </td>
            <td class="hidden md:table-cell text-sm"><?= htmlspecialchars($m['phone']) ?></td>
            <td class="hidden lg:table-cell">
              <span class="text-sm font-semibold text-emerald-700">
                <?= Format::currency((float)($m['total_savings'] ?? 0)) ?>
              </span>
            </td>
            <td class="hidden lg:table-cell text-center">
              <?php if ($m['active_loans'] > 0): ?>
                <span class="badge" style="background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe;">
                  <?= $m['active_loans'] ?> loan<?= $m['active_loans']>1?'s':'' ?>
                </span>
              <?php else: ?>
                <span class="text-xs text-slate-300">—</span>
              <?php endif; ?>
            </td>
            <td><?= Format::statusPill($m['status']) ?></td>
            <td class="hidden md:table-cell">
              <?php if ($m['kyc_verified']): ?>
                <span title="KYC Verified" class="text-emerald-500"><i class="fa-solid fa-circle-check"></i></span>
              <?php else: ?>
                <span title="KYC Pending" class="text-amber-400"><i class="fa-solid fa-clock"></i></span>
              <?php endif; ?>
            </td>
            <td class="hidden md:table-cell text-xs text-slate-400">
              <?= Format::date($m['membership_date']) ?>
            </td>
            <td>
              <div class="flex items-center gap-1">
                <a href="<?= APP_URL ?>/members/<?= $m['id'] ?>" class="btn btn-secondary btn-sm py-1 px-2.5" title="View profile">
                  <i class="fa-solid fa-eye text-xs"></i>
                </a>
                <?php if ($auth->can('members.edit')): ?>
                <a href="<?= APP_URL ?>/members/<?= $m['id'] ?>/edit" class="btn btn-secondary btn-sm py-1 px-2.5" title="Edit">
                  <i class="fa-solid fa-pen text-xs"></i>
                </a>
                <?php endif; ?>
                <div class="relative" data-dropdown>
                  <button class="btn btn-secondary btn-sm py-1 px-2.5" onclick="toggleRowMenu(this)" title="More">
                    <i class="fa-solid fa-ellipsis-vertical text-xs"></i>
                  </button>
                  <div class="row-menu hidden absolute right-0 top-full mt-1 w-44 bg-white rounded-xl shadow-lg border border-slate-200 py-1 z-10">
                    <a href="<?= APP_URL ?>/savings?member=<?= $m['id'] ?>" class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-slate-700 hover:bg-slate-50">
                      <i class="fa-solid fa-piggy-bank w-4 text-slate-400"></i> View Savings
                    </a>
                    <a href="<?= APP_URL ?>/loans?member=<?= $m['id'] ?>" class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-slate-700 hover:bg-slate-50">
                      <i class="fa-solid fa-file-contract w-4 text-slate-400"></i> View Loans
                    </a>
                    <a href="<?= APP_URL ?>/members/<?= $m['id'] ?>/statement" class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-slate-700 hover:bg-slate-50">
                      <i class="fa-solid fa-file-pdf w-4 text-slate-400"></i> Statement
                    </a>
                    <?php if ($auth->can('members.delete')): ?>
                    <div class="border-t border-slate-100 mt-1 pt-1">
                      <button onclick="confirmDelete(<?= $m['id'] ?>,'<?= addslashes($m['first_name'].' '.$m['last_name']) ?>')"
                        class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-red-600 hover:bg-red-50 w-full">
                        <i class="fa-solid fa-trash w-4"></i> Delete
                      </button>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination & bulk actions -->
  <?php if (!empty($result['data'])): ?>
  <div class="px-5 py-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3">
    <!-- Showing X–Y of Z -->
    <p class="text-xs text-slate-400">
      Showing <strong class="text-slate-600"><?= $result['from'] ?? 1 ?></strong>–<strong class="text-slate-600"><?= $result['to'] ?? count($result['data']) ?></strong>
      of <strong class="text-slate-600"><?= number_format($result['total']) ?></strong> members
    </p>

    <!-- Pagination buttons -->
    <div class="flex items-center gap-1.5" id="paginationContainer">
      <?php
      $page     = $result['page'] ?? 1;
      $lastPage = $result['last_page'] ?? 1;
      $qs       = http_build_query(array_diff_key($_GET, ['page'=>'']));
      $base     = APP_URL . '/members?' . ($qs ? $qs.'&' : '');
      ?>
      <a href="<?= $base ?>page=<?= max(1,$page-1) ?>"
         class="pager-btn <?= $page <= 1 ? 'opacity-40 pointer-events-none' : '' ?>">
        <i class="fa-solid fa-chevron-left text-xs"></i>
      </a>
      <?php
      $range = range(max(1,$page-2), min($lastPage,$page+2));
      if (!in_array(1,$range)) { echo "<a href='{$base}page=1' class='pager-btn'>1</a>"; if (min($range)>2) echo "<span class='text-slate-400 text-xs px-1'>…</span>"; }
      foreach ($range as $p): ?>
        <a href="<?= $base ?>page=<?= $p ?>" class="pager-btn <?= $p==$page ? 'active' : '' ?>"><?= $p ?></a>
      <?php endforeach;
      if (!in_array($lastPage,$range) && $lastPage>1) { if (max($range)<$lastPage-1) echo "<span class='text-slate-400 text-xs px-1'>…</span>"; echo "<a href='{$base}page={$lastPage}' class='pager-btn'>{$lastPage}</a>"; }
      ?>
      <a href="<?= $base ?>page=<?= min($lastPage,$page+1) ?>"
         class="pager-btn <?= $page >= $lastPage ? 'opacity-40 pointer-events-none' : '' ?>">
        <i class="fa-solid fa-chevron-right text-xs"></i>
      </a>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- ── Delete confirm modal ──────────────────────────────────────── -->
<div id="deleteModal" class="hidden modal-overlay">
  <div class="modal">
    <div class="modal-header">
      <h3 class="font-bold text-slate-800 flex items-center gap-2">
        <i class="fa-solid fa-trash text-red-500"></i> Delete Member
      </h3>
      <button onclick="closeDeleteModal()" class="text-slate-400 hover:text-slate-700">
        <i class="fa-solid fa-xmark text-lg"></i>
      </button>
    </div>
    <div class="modal-body">
      <p class="text-slate-600 text-sm">
        Are you sure you want to delete <strong id="deleteTarget" class="text-slate-800"></strong>?
        This action will move the member to Trash and can be recovered.
      </p>
    </div>
    <div class="modal-footer">
      <button onclick="closeDeleteModal()" class="btn btn-secondary">Cancel</button>
      <form id="deleteForm" method="POST" style="display:inline">
        <?= $csrfField ?>
        <input type="hidden" name="_method" value="DELETE">
        <button type="submit" class="btn btn-danger">
          <i class="fa-solid fa-trash"></i> Yes, Delete
        </button>
      </form>
    </div>
  </div>
</div>

<script>
// Select all checkbox
document.getElementById('selectAll').addEventListener('change', function() {
  document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
});

// Row dropdown menus
function toggleRowMenu(btn) {
  const menu = btn.nextElementSibling;
  document.querySelectorAll('.row-menu').forEach(m => { if(m!==menu) m.classList.add('hidden'); });
  menu.classList.toggle('hidden');
  event.stopPropagation();
}
document.addEventListener('click', () => document.querySelectorAll('.row-menu').forEach(m => m.classList.add('hidden')));

// Delete modal
function confirmDelete(id, name) {
  document.getElementById('deleteTarget').textContent = name;
  document.getElementById('deleteForm').action = `<?= APP_URL ?>/members/${id}/delete`;
  document.getElementById('deleteModal').classList.remove('hidden');
}
function closeDeleteModal() {
  document.getElementById('deleteModal').classList.add('hidden');
}

// Export
function exportMembers() {
  const params = new URLSearchParams(window.location.search);
  params.set('export', 'csv');
  window.location.href = '<?= APP_URL ?>/members/export?' + params.toString();
}
</script>
