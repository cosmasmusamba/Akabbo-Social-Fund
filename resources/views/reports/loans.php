<?php use App\Helpers\Format; $pageTitle='Loans Report'; $activePage='reports'; $breadcrumbs=['Reports'=>APP_URL.'/reports','Loans'=>null]; ?>
<div class="flex items-center justify-between mb-5">
  <div><h1 class="text-xl font-bold text-slate-800">Loans Report</h1><p class="text-sm text-slate-400">Period: <?= Format::date($filters['from']) ?> — <?= Format::date($filters['to']) ?></p></div>
  <a href="?<?= http_build_query(array_merge($_GET,['export'=>'1'])) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-file-csv text-green-600"></i> Export CSV</a>
</div>
<div class="card mb-5"><div class="card-body py-3"><form method="GET" class="flex flex-wrap gap-3">
  <input type="date" name="from" value="<?= htmlspecialchars($filters['from']) ?>" class="form-control py-2 text-sm w-auto">
  <input type="date" name="to" value="<?= htmlspecialchars($filters['to']) ?>" class="form-control py-2 text-sm w-auto">
  <select name="status" class="form-control form-select py-2 text-sm w-auto" onchange="this.form.submit()">
    <option value="">All Status</option>
    <?php foreach(['active','completed','defaulted','pending','approved'] as $s): ?><option value="<?= $s ?>" <?= ($status??'')===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Apply</button>
</form></div></div>
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
  <?php $st=[['Total Principal',Format::currency((float)($totals['total_principal']??0)),'text-slate-800'],['Total Payable',Format::currency((float)($totals['total_payable']??0)),'text-blue-700'],['Total Repaid',Format::currency((float)($totals['total_repaid']??0)),'text-emerald-700'],['Outstanding',Format::currency((float)($totals['total_outstanding']??0)),'text-amber-700']];
  foreach($st as [$label,$val,$color]): ?>
  <div class="stat-card text-center py-5"><div class="text-lg font-black <?= $color ?>"><?= $val ?></div><div class="text-xs text-slate-400 mt-1"><?= $label ?></div></div>
  <?php endforeach; ?>
</div>
<div class="card"><div class="overflow-x-auto"><table class="data-table">
  <thead><tr><th>Loan No</th><th>Member</th><th class="hidden md:table-cell">Product</th><th>Principal</th><th class="hidden lg:table-cell">Repaid</th><th class="hidden lg:table-cell">Outstanding</th><th>Status</th><th class="hidden md:table-cell">Applied</th></tr></thead>
  <tbody>
    <?php if(empty($data)): ?><tr><td colspan="8" class="text-center py-10 text-slate-400">No loans for this period</td></tr>
    <?php else: foreach($data as $row): ?>
    <tr>
      <td><span class="font-mono text-xs bg-slate-100 px-2 py-0.5 rounded"><?= htmlspecialchars($row['loan_no']) ?></span></td>
      <td><div class="font-semibold text-sm"><?= htmlspecialchars($row['member_name']) ?></div><div class="text-xs text-slate-400"><?= htmlspecialchars($row['member_no']) ?></div></td>
      <td class="hidden md:table-cell text-sm"><?= htmlspecialchars($row['product_name']) ?></td>
      <td class="font-bold"><?= Format::currency((float)$row['principal_amount']) ?></td>
      <td class="hidden lg:table-cell text-emerald-700 font-semibold"><?= Format::currency((float)$row['amount_paid']) ?></td>
      <td class="hidden lg:table-cell text-amber-700 font-bold"><?= Format::currency((float)$row['balance_outstanding']) ?></td>
      <td><?= Format::statusPill($row['status']) ?></td>
      <td class="hidden md:table-cell text-xs text-slate-400"><?= Format::date($row['application_date']) ?></td>
    </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table></div></div>
