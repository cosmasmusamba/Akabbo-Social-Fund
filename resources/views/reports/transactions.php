<?php use App\Helpers\Format; $pageTitle='Transactions Report'; $activePage='reports'; $breadcrumbs=['Reports'=>APP_URL.'/reports','Transactions'=>null]; ?>
<div class="flex items-center justify-between mb-5">
  <div><h1 class="text-xl font-bold text-slate-800">Transactions Report</h1><p class="text-sm text-slate-400">Period: <?= Format::date($filters['from']) ?> — <?= Format::date($filters['to']) ?></p></div>
  <a href="?<?= http_build_query(array_merge($_GET,['export'=>'1'])) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-file-csv text-green-600"></i> Export</a>
</div>
<div class="card mb-5"><div class="card-body py-3"><form method="GET" class="flex flex-wrap gap-3">
  <input type="date" name="from" value="<?= htmlspecialchars($filters['from']) ?>" class="form-control py-2 text-sm w-auto">
  <input type="date" name="to" value="<?= htmlspecialchars($filters['to']) ?>" class="form-control py-2 text-sm w-auto">
  <select name="type" class="form-control form-select py-2 text-sm w-auto" onchange="this.form.submit()">
    <option value="">All Types</option>
    <?php foreach(['deposit','withdrawal','loan_disbursement','loan_repayment'] as $t): ?><option value="<?= $t ?>" <?= ($type??'')===$t?'selected':'' ?>><?= ucwords(str_replace('_',' ',$t)) ?></option><?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Apply</button>
</form></div></div>
<!-- Type breakdown -->
<?php if(!empty($typeTotals)): ?>
<div class="flex flex-wrap gap-3 mb-5">
  <?php $tc=['deposit'=>'bg-emerald-100 text-emerald-700','withdrawal'=>'bg-orange-100 text-orange-700','loan_disbursement'=>'bg-blue-100 text-blue-700','loan_repayment'=>'bg-purple-100 text-purple-700'];
  foreach($typeTotals as $t): $cls=$tc[$t['txn_type']]??'bg-slate-100 text-slate-600'; ?>
  <div class="px-4 py-2.5 rounded-xl border border-current/20 <?= $cls ?>">
    <div class="text-xs font-bold capitalize"><?= str_replace('_',' ',$t['txn_type']) ?></div>
    <div class="font-black"><?= Format::currency((float)$t['total']) ?></div>
    <div class="text-xs opacity-70"><?= number_format($t['cnt']) ?> transactions</div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<div class="card"><div class="overflow-x-auto"><table class="data-table">
  <thead><tr><th>Reference</th><th>Type</th><th>Member</th><th>Amount</th><th class="hidden md:table-cell">Method</th><th>Date</th></tr></thead>
  <tbody>
    <?php if(empty($result['data'])): ?><tr><td colspan="6" class="text-center py-10 text-slate-400">No transactions found</td></tr>
    <?php else: foreach($result['data'] as $t): ?>
    <tr>
      <td><span class="font-mono text-xs"><?= htmlspecialchars($t['txn_ref']) ?></span></td>
      <td><span class="text-xs capitalize"><?= str_replace('_',' ',$t['txn_type']) ?></span></td>
      <td class="text-sm"><?= htmlspecialchars($t['member_name']??'—') ?></td>
      <td class="font-bold <?= in_array($t['txn_type'],['deposit','loan_repayment'])?'text-emerald-700':'text-slate-700' ?>"><?= Format::currency((float)$t['amount']) ?></td>
      <td class="hidden md:table-cell text-xs capitalize"><?= str_replace('_',' ',$t['payment_method']??'—') ?></td>
      <td class="text-xs text-slate-400"><?= Format::date($t['transaction_date']) ?></td>
    </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table></div>
<?php if(!empty($result['data'])): $page=$result['page']??1;$lastPage=$result['last_page']??1;$qs=http_build_query(array_diff_key($_GET,['page'=>'']));$base=APP_URL.'/reports/transactions?'.($qs?$qs.'&':''); ?>
<div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between">
  <p class="text-xs text-slate-400">Showing <?= $result['from']??1 ?>–<?= $result['to']??count($result['data']) ?> of <?= number_format($result['total']) ?></p>
  <div class="flex gap-1">
    <a href="<?= $base ?>page=<?= max(1,$page-1) ?>" class="pager-btn <?= $page<=1?'opacity-40 pointer-events-none':'' ?>"><i class="fa-solid fa-chevron-left text-xs"></i></a>
    <?php for($p=max(1,$page-2);$p<=min($lastPage,$page+2);$p++): ?><a href="<?= $base ?>page=<?= $p ?>" class="pager-btn <?= $p==$page?'active':'' ?>"><?= $p ?></a><?php endfor; ?>
    <a href="<?= $base ?>page=<?= min($lastPage,$page+1) ?>" class="pager-btn <?= $page>=$lastPage?'opacity-40 pointer-events-none':'' ?>"><i class="fa-solid fa-chevron-right text-xs"></i></a>
  </div>
</div>
<?php endif; ?></div>
