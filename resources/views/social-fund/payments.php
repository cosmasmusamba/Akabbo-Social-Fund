<?php
use App\Helpers\Format;
$monthName = date('F Y', mktime(0, 0, 0, $month, 1, $year));
?>
<div class="flex items-center justify-between mb-5">
  <div>
    <h1 class="text-xl font-bold text-slate-800">Fee Payments — <?= $monthName ?></h1>
    <p class="text-sm text-slate-400 mt-0.5"><?= htmlspecialchars($fee['name'] ?? '') ?></p>
  </div>
  <div class="flex gap-2 items-center">
    <!-- Period navigation -->
    <?php
    $prev = new DateTime("{$year}-{$month}-01");
    $prev->modify('-1 month');
    $next = new DateTime("{$year}-{$month}-01");
    $next->modify('+1 month');
    $qs   = '?fee=' . $fee['id'];
    ?>
    <a href="<?= APP_URL ?>/social-fund/payments<?= $qs ?>&month=<?= $prev->format('n') ?>&year=<?= $prev->format('Y') ?>"
       class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-chevron-left text-xs"></i>
    </a>
    <span class="text-sm font-semibold text-slate-700 px-2"><?= $monthName ?></span>
    <?php if ($next <= new DateTime()): ?>
    <a href="<?= APP_URL ?>/social-fund/payments<?= $qs ?>&month=<?= $next->format('n') ?>&year=<?= $next->format('Y') ?>"
       class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-chevron-right text-xs"></i>
    </a>
    <?php endif; ?>

    <!-- Fee selector -->
    <select onchange="location.href='<?= APP_URL ?>/social-fund/payments?fee='+this.value+'&month=<?= $month ?>&year=<?= $year ?>'"
            class="form-control form-select py-1.5 text-sm w-auto ml-2">
      <?php foreach ($fees as $f): ?>
      <option value="<?= $f['id'] ?>" <?= $f['id'] === $fee['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($f['name']) ?>
      </option>
      <?php endforeach; ?>
    </select>

    <?php if ($auth->can('social_fund.manage')): ?>
    <button onclick="generateNow()" class="btn btn-primary btn-sm">
      <i class="fa-solid fa-gear"></i> Generate
    </button>
    <?php endif; ?>
  </div>
</div>

<!-- Summary cards -->
<?php if ($summary): ?>
<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-5">
  <?php $sc = [
    ['Due',       Format::currency((float)($summary['total_due']       ?? 0)), 'text-slate-800'],
    ['Collected', Format::currency((float)($summary['total_collected']  ?? 0)), 'text-emerald-700'],
    ['Arrears',   Format::currency((float)($summary['total_arrears']    ?? 0)), 'text-red-600'],
    ['Penalties', Format::currency((float)($summary['total_penalties']  ?? 0)), 'text-amber-700'],
    ['Members',   number_format($summary['total_members'] ?? 0),                'text-blue-700'],
  ];
  foreach ($sc as [$l, $v, $cl]): ?>
  <div class="stat-card text-center py-4">
    <div class="text-lg font-black <?= $cl ?>"><?= $v ?></div>
    <div class="text-xs text-slate-400 mt-1"><?= $l ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Status filter tabs -->
<div class="flex gap-2 mb-4 flex-wrap">
  <?php foreach (['all' => 'All', 'paid' => 'Paid', 'partial' => 'Partial', 'overdue' => 'Overdue', 'pending' => 'Pending', 'waived' => 'Waived'] as $val => $label): ?>
  <button onclick="filterPayments('<?= $val ?>')"
          class="btn btn-sm status-filter-btn <?= $val === 'all' ? 'btn-primary' : 'btn-secondary' ?>"
          data-status="<?= $val ?>">
    <?= $label ?>
  </button>
  <?php endforeach; ?>
</div>

<!-- Payments table -->
<div class="card">
  <div class="overflow-x-auto">
    <table class="data-table" id="paymentsTable">
      <thead>
        <tr>
          <th>Member</th>
          <th>Amount Due</th>
          <th class="hidden md:table-cell">Amount Paid</th>
          <th class="hidden lg:table-cell">Penalty Paid</th>
          <th class="hidden md:table-cell">Paid Date</th>
          <th>Status</th>
          <?php if ($auth->can('social_fund.manage')): ?>
          <th>Actions</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($payments)): ?>
        <tr>
          <td colspan="7" class="text-center py-12">
            <i class="fa-solid fa-coins text-5xl text-slate-200 block mb-3"></i>
            <p class="text-slate-500 font-semibold mb-1">No fee records for this period</p>
            <?php if ($auth->can('social_fund.manage')): ?>
            <button onclick="generateNow()" class="btn btn-primary btn-sm mt-2">
              <i class="fa-solid fa-gear"></i> Generate Records Now
            </button>
            <?php endif; ?>
          </td>
        </tr>
        <?php else: foreach ($payments as $p): ?>
        <tr class="payment-row" data-status="<?= $p['status'] ?>">
          <td>
            <div class="flex items-center gap-2.5">
              <div class="avatar-circle w-8 h-8 text-xs">
                <?= Format::initials($p['member_name']) ?>
              </div>
              <div>
                <div class="font-semibold text-sm text-slate-800"><?= htmlspecialchars($p['member_name']) ?></div>
                <div class="text-xs text-slate-400"><?= htmlspecialchars($p['member_no']) ?> · <?= htmlspecialchars($p['phone']) ?></div>
              </div>
            </div>
          </td>
          <td class="font-semibold"><?= Format::currency((float)$p['amount_due']) ?></td>
          <td class="hidden md:table-cell text-emerald-700 font-semibold"><?= Format::currency((float)$p['amount_paid']) ?></td>
          <td class="hidden lg:table-cell text-amber-600"><?= $p['penalty_paid'] > 0 ? Format::currency((float)$p['penalty_paid']) : '—' ?></td>
          <td class="hidden md:table-cell text-xs text-slate-400"><?= $p['paid_date'] ? Format::date($p['paid_date']) : '—' ?></td>
          <td><?= Format::statusPill($p['status']) ?></td>
          <?php if ($auth->can('social_fund.manage')): ?>
          <td>
            <?php if (in_array($p['status'], ['pending','partial','overdue'])): ?>
            <div class="flex gap-1">
              <button onclick="recordPayment(<?= $p['id'] ?>, <?= $p['amount_due'] ?>, <?= $p['amount_paid'] ?>, '<?= addslashes($p['member_name']) ?>')"
                      class="btn btn-sm py-1 px-2.5" style="background:#dcfce7;color:#166534;border:1px solid #a7f3d0;font-size:.75rem">
                <i class="fa-solid fa-check"></i> Pay
              </button>
              <button onclick="waiveFee(<?= $p['id'] ?>, '<?= addslashes($p['member_name']) ?>')"
                      class="btn btn-secondary btn-sm py-1 px-2.5" title="Waive this fee">
                <i class="fa-solid fa-hand text-xs"></i>
              </button>
            </div>
            <?php else: ?>
            <span class="text-xs text-slate-300">—</span>
            <?php endif; ?>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Record payment modal -->
<div id="payModal" class="hidden modal-overlay">
  <div class="modal" style="max-width:460px">
    <div class="modal-header">
      <h3 class="font-bold text-slate-800"><i class="fa-solid fa-circle-dollar-to-slot text-emerald-500 mr-2"></i>Record Fee Payment</h3>
      <button onclick="closePayModal()" class="text-slate-400 hover:text-slate-700"><i class="fa-solid fa-xmark text-lg"></i></button>
    </div>
    <div class="modal-body space-y-3">
      <p class="text-sm text-slate-600">Recording payment for: <strong id="payMember"></strong></p>
      <input type="hidden" id="payId">
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="form-label required">Amount Paid</label>
          <input type="number" id="payAmount" class="form-control" min="0" step="100">
        </div>
        <div>
          <label class="form-label">Penalty Paid</label>
          <input type="number" id="payPenalty" class="form-control" min="0" step="100" value="0">
        </div>
        <div class="col-span-2">
          <label class="form-label required">Payment Method</label>
          <select id="payMethod" class="form-control form-select">
            <option value="cash">Cash</option>
            <option value="mobile_money">Mobile Money</option>
            <option value="bank_transfer">Bank Transfer</option>
            <option value="deduction">Savings Deduction</option>
          </select>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button onclick="closePayModal()" class="btn btn-secondary">Cancel</button>
      <button onclick="submitPayment()" class="btn btn-primary"><i class="fa-solid fa-check"></i> Record Payment</button>
    </div>
  </div>
</div>

<!-- Waive fee modal -->
<div id="waiveModal" class="hidden modal-overlay">
  <div class="modal" style="max-width:440px">
    <div class="modal-header">
      <h3 class="font-bold text-slate-800"><i class="fa-solid fa-hand text-purple-500 mr-2"></i>Waive Fee</h3>
      <button onclick="closeWaiveModal()" class="text-slate-400 hover:text-slate-700"><i class="fa-solid fa-xmark text-lg"></i></button>
    </div>
    <div class="modal-body space-y-3">
      <p class="text-sm text-slate-600">Waive fee for: <strong id="waiveMember"></strong></p>
      <input type="hidden" id="waiveId">
      <div>
        <label class="form-label required">Reason for Waiver (mandatory)</label>
        <textarea id="waiveReason" rows="3" class="form-control"
                  placeholder="State the reason for waiving this fee…"></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button onclick="closeWaiveModal()" class="btn btn-secondary">Cancel</button>
      <button onclick="submitWaive()" class="btn" style="background:#7c3aed;color:#fff"><i class="fa-solid fa-hand"></i> Waive Fee</button>
    </div>
  </div>
</div>

<script>
// Filter by status
function filterPayments(status) {
  document.querySelectorAll('.status-filter-btn').forEach(b => {
    b.className = b.dataset.status === status ? 'btn btn-sm btn-primary status-filter-btn' : 'btn btn-sm btn-secondary status-filter-btn';
    b.dataset.status = b.dataset.status;
  });
  document.querySelectorAll('.payment-row').forEach(row => {
    row.style.display = (status === 'all' || row.dataset.status === status) ? '' : 'none';
  });
}

// Record payment
function recordPayment(id, due, paid, member) {
  document.getElementById('payId').value     = id;
  document.getElementById('payMember').textContent = member;
  document.getElementById('payAmount').value = (due - paid).toFixed(0);
  document.getElementById('payPenalty').value = '0';
  document.getElementById('payModal').classList.remove('hidden');
}
function closePayModal() { document.getElementById('payModal').classList.add('hidden'); }

async function submitPayment() {
  const fd = new FormData();
  fd.append('csrf_token',     document.querySelector('meta[name="csrf-token"]').content);
  fd.append('payment_id',     document.getElementById('payId').value);
  fd.append('amount_paid',    document.getElementById('payAmount').value);
  fd.append('penalty_paid',   document.getElementById('payPenalty').value);
  fd.append('payment_method', document.getElementById('payMethod').value);
  try {
    const r = await fetch('<?= APP_URL ?>/social-fund/record-payment', {
      method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd
    });
    const d = await r.json();
    closePayModal();
    if (d.success) { window.showToast('success', d.message); setTimeout(() => location.reload(), 800); }
    else window.showToast('error', d.message);
  } catch(e) { window.showToast('error', 'Request failed.'); }
}

// Waive fee
function waiveFee(id, member) {
  document.getElementById('waiveId').value     = id;
  document.getElementById('waiveMember').textContent = member;
  document.getElementById('waiveReason').value = '';
  document.getElementById('waiveModal').classList.remove('hidden');
}
function closeWaiveModal() { document.getElementById('waiveModal').classList.add('hidden'); }

async function submitWaive() {
  const reason = document.getElementById('waiveReason').value.trim();
  if (!reason) { window.showToast('error', 'A reason is required to waive a fee.'); return; }
  const fd = new FormData();
  fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
  fd.append('payment_id', document.getElementById('waiveId').value);
  fd.append('reason',     reason);
  try {
    const r = await fetch('<?= APP_URL ?>/social-fund/waive', {
      method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd
    });
    const d = await r.json();
    closeWaiveModal();
    if (d.success) { window.showToast('success', d.message); setTimeout(() => location.reload(), 800); }
    else window.showToast('error', d.message);
  } catch(e) { window.showToast('error', 'Request failed.'); }
}

// Generate now
async function generateNow() {
  if (!confirm('Generate fee records for <?= $monthName ?>?')) return;
  const fd = new FormData();
  fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
  fd.append('fee_id', '<?= $fee['id'] ?>');
  fd.append('month',  '<?= $month ?>');
  fd.append('year',   '<?= $year ?>');
  const r = await fetch('<?= APP_URL ?>/social-fund/generate-period', {
    method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd
  });
  const d = await r.json();
  if (d.success) { window.showToast('success', d.message); setTimeout(() => location.reload(), 900); }
  else window.showToast('error', d.message);
}
</script>