<?php use App\Helpers\Format; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<div class="flex items-center justify-between mb-5">
  <div class="breadcrumb"><a href="<?= APP_URL ?>/savings">Savings</a><span class="sep">/</span><span class="current"><?= htmlspecialchars($account['account_no']) ?></span></div>
  <div class="flex gap-2">
    <?php if($auth->can('savings.deposit')): ?>
    <a href="<?= APP_URL ?>/savings/deposit?member=<?= $account['member_id'] ?>&account=<?= $account['id'] ?>" class="btn btn-gold btn-sm"><i class="fa-solid fa-arrow-down-to-bracket"></i> Deposit</a>
    <a href="<?= APP_URL ?>/savings/withdraw?member=<?= $account['member_id'] ?>&account=<?= $account['id'] ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-up-from-bracket"></i> Withdraw</a>
    <a href="<?= APP_URL ?>/savings/statement/<?= $account['id'] ?>" class="btn btn-secondary btn-sm" target="_blank"><i class="fa-solid fa-file-pdf"></i> Statement</a>
    <?php endif; ?>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
  <!-- Left column: Account details + Goal -->
  <div>
    <div class="card mb-5">
      <div class="card-body text-center py-6" style="background:linear-gradient(135deg,#f0fdf9,#ecfdf5);border-radius:14px;">
        <div class="text-xs text-slate-400 mb-1 uppercase tracking-wider font-semibold">Account Balance</div>
        <div class="text-4xl font-black text-emerald-700"><?= Format::currency((float)$account['balance']) ?></div>
        <div class="text-sm text-slate-500 mt-1"><?= Format::titleCase($account['account_type']) ?> Account</div>
        <?= Format::statusPill($account['status']) ?>
      </div>
    </div>

    <div class="card mb-5">
      <div class="card-header"><h3 class="section-title">Account Info</h3></div>
      <div class="card-body">
        <?php $f=[['Account No',$account['account_no']],['Member',$account['member_name']],['Member No',$account['member_no']],['Phone',$account['phone']],['Type',Format::titleCase($account['account_type'])],['Interest Rate',Format::percentage((float)$account['interest_rate'])],['Opened',Format::date($account['opened_at'])]];
        foreach($f as [$l,$v]): ?>
        <div class="flex justify-between py-2 border-b border-slate-50 last:border-0 text-sm"><span class="text-slate-400 text-xs font-semibold"><?= $l ?></span><span class="font-semibold text-slate-700"><?= htmlspecialchars($v) ?></span></div>
        <?php endforeach; ?>
        <a href="<?= APP_URL ?>/members/<?= $account['member_id'] ?>" class="btn btn-secondary btn-sm mt-4 w-full justify-center"><i class="fa-solid fa-user"></i> View Member</a>
      </div>
    </div>

    <!-- Savings Goal Card -->
    <div class="card">
      <div class="card-header"><h3 class="section-title"><i class="fa-solid fa-bullseye"></i> Savings Goal</h3></div>
      <div class="card-body">
        <form id="goalForm" class="space-y-3">
          <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
          <div>
            <label class="form-label">Target Amount</label>
            <div class="relative"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-500"><?= $settings['currency_symbol']??'USh' ?></span><input type="number" name="target_amount" value="<?= $account['target_amount'] ?? '' ?>" class="form-control pl-12" step="10000" placeholder="Optional"></div>
          </div>
          <div>
            <label class="form-label">Target Date</label>
            <input type="date" name="target_date" value="<?= $account['target_date'] ?? '' ?>" class="form-control">
          </div>
          <button type="submit" class="btn btn-primary btn-sm w-full"><i class="fa-solid fa-save"></i> Update Goal</button>
        </form>
        <?php if (!empty($account['target_amount']) && $account['target_amount'] > 0): ?>
        <div class="mt-4 pt-4 border-t border-slate-100">
          <div class="flex justify-between text-sm mb-1"><span>Progress</span><span><?= Format::currency((float)$account['balance']) ?> / <?= Format::currency((float)$account['target_amount']) ?></span></div>
          <div class="w-full bg-slate-200 rounded-full h-2.5"><div class="bg-emerald-600 h-2.5 rounded-full" style="width: <?= min(100, ($account['balance'] / $account['target_amount']) * 100) ?>%"></div></div>
          <?php if (!empty($account['target_date'])): ?>
          <p class="text-xs text-slate-400 mt-2">Target date: <?= Format::date($account['target_date']) ?></p>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Right column: Chart + Transactions -->
  <div class="lg:col-span-2">
    <!-- Chart -->
    <div class="card mb-5">
      <div class="card-header"><h3 class="section-title">Balance History (Last 12 months)</h3></div>
      <div class="card-body">
        <canvas id="balanceChart" height="200"></canvas>
      </div>
    </div>

    <!-- Transactions table -->
    <div class="card">
      <div class="card-header"><h3 class="section-title">Transaction History</h3></div>
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Ref</th><th>Type</th><th>Amount</th><th class="hidden md:table-cell">Balance After</th><th class="hidden md:table-cell">Method</th><th>Date</th></tr></thead>
          <tbody>
            <?php if(empty($transactions)): ?>
            <tr><td colspan="6" class="text-center py-8 text-slate-400">No transactions found</td></tr>
            <?php else: foreach($transactions as $t): ?>
            <tr>
              <td><span class="font-mono text-xs"><?= htmlspecialchars($t['txn_ref']) ?></span></td>
              <td class="text-xs capitalize"><?= str_replace('_',' ',$t['txn_type']) ?></td>
              <td class="font-bold <?= in_array($t['txn_type'],['deposit','transfer_in','interest'])?'text-emerald-700':'text-orange-700' ?>"><?= Format::currency((float)$t['amount']) ?></td>
              <td class="hidden md:table-cell text-sm text-slate-600"><?= $t['balance_after']!==null?Format::currency((float)$t['balance_after']):'—' ?></td>
              <td class="hidden md:table-cell text-xs capitalize"><?= str_replace('_',' ',$t['payment_method']??'—') ?></td>
              <td class="text-xs text-slate-400"><?= Format::date($t['transaction_date']) ?></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
// Update goal via AJAX
document.getElementById('goalForm')?.addEventListener('submit', async function(e){
  e.preventDefault();
  const fd = new FormData(this);
  fd.append('_method', 'POST'); // or use POST directly
  const r = await fetch('<?= APP_URL ?>/savings/<?= $account['id'] ?>/update-goal', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: fd
  });
  const d = await r.json();
  if(d.success) window.showToast('success', d.message);
  else window.showToast('error', d.message);
});

// Chart.js
const ctx = document.getElementById('balanceChart')?.getContext('2d');
if(ctx) {
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: <?= json_encode($months ?? []) ?>,
      datasets: [{
        label: 'Balance (UGX)',
        data: <?= json_encode($balances ?? []) ?>,
        borderColor: '#136b55',
        backgroundColor: 'rgba(19,107,85,0.1)',
        fill: true,
        tension: 0.3
      }]
    },
    options: { responsive: true, maintainAspectRatio: true }
  });
}
</script>