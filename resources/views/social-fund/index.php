<?php
use App\Helpers\Format;
$pageTitle   = 'Social Fund Fees';
$activePage  = 'social-fund';
$breadcrumbs = ['Social Fund Fees' => null];
?>
<div class="flex items-center justify-between mb-5">
  <div>
    <h1 class="text-xl font-bold text-slate-800">Social Fund Fees</h1>
    <p class="text-sm text-slate-400 mt-0.5">Monthly member fee management</p>
  </div>
  <div class="flex gap-2">
    <a href="<?= APP_URL ?>/social-fund/payments" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-list-check"></i> This Month
    </a>
    <?php if ($auth->can('social_fund.manage')): ?>
    <a href="<?= APP_URL ?>/social-fund/create" class="btn btn-primary btn-sm">
      <i class="fa-solid fa-plus"></i> New Fee Config
    </a>
    <?php endif; ?>
  </div>
</div>

<?php if ($activeFee && $summary): ?>
<!-- Current month hero -->
<div class="card mb-5" style="background:linear-gradient(135deg,#0a4033,#136b55);border:none;color:#fff">
  <div class="card-body">
    <div class="flex items-start justify-between mb-4">
      <div>
        <div class="text-xs font-semibold uppercase tracking-wider mb-1" style="color:#a7f3d0">
          Active Fee — <?= date('F Y') ?>
        </div>
        <div class="text-2xl font-black"><?= htmlspecialchars($activeFee['name']) ?></div>
        <div class="text-sm mt-0.5" style="color:#a7f3d0">
          <?= Format::currency((float)$activeFee['amount']) ?> per member ·
          Due by <?= $activeFee['due_day'] ?><?= in_array($activeFee['due_day'],[1,21,31])?'st':(in_array($activeFee['due_day'],[2,22])?'nd':(in_array($activeFee['due_day'],[3,23])?'rd':'th')) ?>
          + <?= $activeFee['grace_days'] ?> days grace
        </div>
      </div>
      <div class="text-right">
        <div class="text-3xl font-black" style="color:#a7f3d0">
          <?= Format::currency((float)($summary['total_collected'] ?? 0)) ?>
        </div>
        <div class="text-sm mt-0.5" style="color:rgba(167,243,208,.7)">
          collected of <?= Format::currency((float)($summary['total_due'] ?? 0)) ?>
        </div>
        <!-- Collection rate bar -->
        <?php $rate = ($summary['total_due'] ?? 0) > 0
            ? min(100, round((float)$summary['total_collected'] / (float)$summary['total_due'] * 100))
            : 0; ?>
        <div class="mt-2 h-2 rounded-full" style="background:rgba(0,0,0,0.3);width:160px;margin-left:auto">
          <div class="h-full rounded-full" style="width:<?= $rate ?>%;background:#a7f3d0"></div>
        </div>
        <div class="text-xs mt-1" style="color:rgba(167,243,208,.7)"><?= $rate ?>% collected</div>
      </div>
    </div>
    <!-- Stats row -->
    <div class="grid grid-cols-5 gap-3">
      <?php $sc = [
        ['Paid',    $summary['paid_count']    ?? 0, '#a7f3d0'],
        ['Partial', $summary['partial_count'] ?? 0, '#fde68a'],
        ['Overdue', $summary['overdue_count'] ?? 0, '#fca5a5'],
        ['Pending', $summary['pending_count'] ?? 0, 'rgba(255,255,255,0.6)'],
        ['Waived',  $summary['waived_count']  ?? 0, '#c4b5fd'],
      ];
      foreach ($sc as [$l, $v, $cl]): ?>
      <div class="text-center py-3 rounded-xl" style="background:rgba(0,0,0,0.2)">
        <div class="text-xl font-black" style="color:<?= $cl ?>"><?= number_format($v) ?></div>
        <div class="text-xs mt-0.5" style="color:rgba(167,243,208,.7)"><?= $l ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if (($summary['total_arrears'] ?? 0) > 0): ?>
    <div class="mt-3 px-3 py-2 rounded-xl text-sm" style="background:rgba(239,68,68,.2);color:#fca5a5">
      <i class="fa-solid fa-triangle-exclamation mr-1.5"></i>
      Total arrears: <strong><?= Format::currency((float)$summary['total_arrears']) ?></strong>
      <a href="<?= APP_URL ?>/social-fund/payments" class="ml-2 underline text-xs">View →</a>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Fee configurations table -->
<div class="card">
  <div class="card-header">
    <h3 class="section-title">Fee Configurations</h3>
  </div>
  <div class="overflow-x-auto">
    <table class="data-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Amount</th>
          <th class="hidden md:table-cell">Frequency</th>
          <th class="hidden md:table-cell">Due / Grace</th>
          <th class="hidden lg:table-cell">Applies To</th>
          <th class="hidden lg:table-cell">Effective From</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($fees)): ?>
        <tr>
          <td colspan="8" class="text-center py-12">
            <i class="fa-solid fa-coins text-5xl text-slate-200 block mb-3"></i>
            <p class="text-slate-400">No fee configurations yet</p>
            <?php if ($auth->can('social_fund.manage')): ?>
            <a href="<?= APP_URL ?>/social-fund/create" class="btn btn-primary btn-sm mt-3">
              <i class="fa-solid fa-plus"></i> Create First Fee
            </a>
            <?php endif; ?>
          </td>
        </tr>
        <?php else: foreach ($fees as $f): ?>
        <tr>
          <td>
            <div class="font-semibold text-slate-800"><?= htmlspecialchars($f['name']) ?></div>
            <?php if ($f['penalty_amount'] > 0): ?>
            <div class="text-xs text-red-500 mt-0.5">
              <i class="fa-solid fa-exclamation-circle"></i>
              Late penalty: <?= Format::currency((float)$f['penalty_amount']) ?>
            </div>
            <?php endif; ?>
          </td>
          <td class="font-bold text-emerald-700"><?= Format::currency((float)$f['amount']) ?></td>
          <td class="hidden md:table-cell text-sm capitalize"><?= $f['frequency'] ?></td>
          <td class="hidden md:table-cell text-xs text-slate-500">
            Day <?= $f['due_day'] ?> + <?= $f['grace_days'] ?> days
          </td>
          <td class="hidden lg:table-cell text-xs capitalize"><?= str_replace('_', ' ', $f['applies_to']) ?></td>
          <td class="hidden lg:table-cell text-xs text-slate-400"><?= Format::date($f['effective_from']) ?></td>
          <td><?= Format::statusPill($f['status']) ?></td>
          <td>
            <div class="flex gap-1">
              <a href="<?= APP_URL ?>/social-fund/payments?fee=<?= $f['id'] ?>"
                 class="btn btn-secondary btn-sm py-1 px-2.5" title="View payments">
                <i class="fa-solid fa-eye text-xs"></i>
              </a>
              <?php if ($auth->can('social_fund.manage')): ?>
              <a href="<?= APP_URL ?>/social-fund/<?= $f['id'] ?>/edit"
                 class="btn btn-secondary btn-sm py-1 px-2.5" title="Edit">
                <i class="fa-solid fa-pen text-xs"></i>
              </a>
              <button onclick="generatePeriod(<?= $f['id'] ?>, '<?= addslashes($f['name']) ?>')"
                      class="btn btn-sm py-1 px-2.5" title="Generate fee records"
                      style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;font-size:.75rem">
                <i class="fa-solid fa-calendar-plus"></i>
              </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Generate period modal -->
<div id="genModal" class="hidden modal-overlay">
  <div class="modal" style="max-width:440px">
    <div class="modal-header">
      <h3 class="font-bold text-slate-800">
        <i class="fa-solid fa-calendar-plus text-blue-500 mr-2"></i>Generate Fee Records
      </h3>
      <button onclick="closeGenModal()" class="text-slate-400 hover:text-slate-700">
        <i class="fa-solid fa-xmark text-lg"></i>
      </button>
    </div>
    <div class="modal-body space-y-4">
      <p class="text-sm text-slate-600">
        Generate pending fee records for all applicable members for:
        <strong id="genFeeName"></strong>
      </p>
      <input type="hidden" id="genFeeId">
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="form-label required">Month</label>
          <select id="genMonth" class="form-control form-select">
            <?php for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?= $m ?>" <?= $m === (int)date('n') ? 'selected' : '' ?>>
              <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
            </option>
            <?php endfor; ?>
          </select>
        </div>
        <div>
          <label class="form-label required">Year</label>
          <input type="number" id="genYear" class="form-control"
                 value="<?= date('Y') ?>" min="2020" max="<?= date('Y') + 1 ?>">
        </div>
      </div>
      <div class="p-3 rounded-xl text-xs" style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af">
        <i class="fa-solid fa-circle-info mr-1"></i>
        This creates pending fee records for all applicable active members.
        Existing records for the same period are skipped automatically.
      </div>
    </div>
    <div class="modal-footer">
      <button onclick="closeGenModal()" class="btn btn-secondary">Cancel</button>
      <button onclick="submitGenerate()" class="btn btn-primary">
        <i class="fa-solid fa-gear"></i> Generate Records
      </button>
    </div>
  </div>
</div>

<script>
function generatePeriod(id, name) {
  document.getElementById('genFeeId').value  = id;
  document.getElementById('genFeeName').textContent = name;
  document.getElementById('genModal').classList.remove('hidden');
}
function closeGenModal() { document.getElementById('genModal').classList.add('hidden'); }

async function submitGenerate() {
  const fd = new FormData();
  fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
  fd.append('fee_id', document.getElementById('genFeeId').value);
  fd.append('month',  document.getElementById('genMonth').value);
  fd.append('year',   document.getElementById('genYear').value);

  try {
    const r = await fetch('<?= APP_URL ?>/social-fund/generate-period', {
      method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd
    });
    const d = await r.json();
    closeGenModal();
    if (d.success) window.showToast('success', d.message);
    else           window.showToast('error', d.message);
  } catch(e) { window.showToast('error', 'Request failed.'); }
}
</script>