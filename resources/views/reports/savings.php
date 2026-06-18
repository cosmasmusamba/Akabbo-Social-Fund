<?php 
use App\Helpers\Format; 
$pageTitle = 'Savings Report'; 
$activePage = 'reports'; 
$breadcrumbs = ['Reports' => APP_URL.'/reports', 'Savings' => null]; 
$filters = $filters ?? ['from' => date('Y-m-01'), 'to' => date('Y-m-d')];
?>
<div class="flex items-center justify-between mb-5">
  <div>
    <h1 class="text-xl font-bold text-slate-800">Savings Report</h1>
    <p class="text-sm text-slate-400">Period: <?= Format::date($filters['from'] ?? '') ?> — <?= Format::date($filters['to'] ?? '') ?></p>
  </div>
  <div class="flex gap-2">
    <a href="?<?= http_build_query(array_merge($_GET, ['export' => '1'])) ?>" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-file-csv text-green-600"></i> Export CSV
    </a>
  </div>
</div>

<!-- Filter -->
<div class="card mb-5">
  <div class="card-body py-3">
    <form method="GET" class="flex flex-wrap gap-3">
      <input type="date" name="from" value="<?= htmlspecialchars($filters['from'] ?? '') ?>" class="form-control py-2 text-sm w-auto">
      <input type="date" name="to" value="<?= htmlspecialchars($filters['to'] ?? '') ?>" class="form-control py-2 text-sm w-auto">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Apply</button>
    </form>
  </div>
</div>

<!-- Totals -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
  <?php 
  $st = [
    ['Total Savings Balance', Format::currency((float)($totals['total_savings'] ?? 0)), 'text-emerald-700'],
    ['Total Deposits', Format::currency((float)($totals['total_deposits'] ?? 0)), 'text-blue-700'],
    ['Total Withdrawals', Format::currency((float)($totals['total_withdrawals'] ?? 0)), 'text-orange-700'],
    ['Accounts', number_format($totals['accounts'] ?? 0), 'text-slate-700']
  ];
  foreach ($st as [$label, $val, $color]): 
  ?>
  <div class="stat-card text-center py-5">
    <div class="text-xl font-black <?= $color ?>"><?= $val ?></div>
    <div class="text-xs text-slate-400 mt-1"><?= $label ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Table -->
<div class="card">
  <div class="overflow-x-auto">
    <table class="data-table">
      <thead>
        <tr>
          <th>Account</th>
          <th>Member</th>
          <th class="hidden md:table-cell">Type</th>
          <th>Balance</th>
          <th class="hidden lg:table-cell">Deposits</th>
          <th class="hidden lg:table-cell">Withdrawals</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($data)): ?>
          <tr><td colspan="7" class="text-center py-10 text-slate-400">No data for this period</td></tr>
        <?php else: foreach ($data as $row): ?>
        <tr>
          <td><span class="font-mono text-xs bg-slate-100 px-2 py-0.5 rounded"><?= htmlspecialchars($row['account_no'] ?? '') ?></span></td>
          <td>
            <div class="font-semibold text-sm"><?= htmlspecialchars($row['member_name'] ?? '') ?></div>
            <div class="text-xs text-slate-400"><?= htmlspecialchars($row['member_no'] ?? '') ?></div>
          </td>
          <td class="hidden md:table-cell text-xs capitalize"><?= str_replace('_', ' ', $row['account_type'] ?? '') ?></td>
          <td class="font-bold text-emerald-700"><?= Format::currency((float)($row['balance'] ?? 0)) ?></td>
          <td class="hidden lg:table-cell text-blue-700"><?= Format::currency((float)($row['total_deposits'] ?? 0)) ?></td>
          <td class="hidden lg:table-cell text-orange-700"><?= Format::currency((float)($row['total_withdrawals'] ?? 0)) ?></td>
          <td><?= Format::statusPill($row['status'] ?? '') ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>