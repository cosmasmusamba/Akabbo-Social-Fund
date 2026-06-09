<?php use App\Helpers\Format; $pageTitle='Transactions'; $activePage='transactions'; $breadcrumbs=['Transactions'=>null];
$typeColors=['deposit'=>'bg-emerald-100 text-emerald-700','withdrawal'=>'bg-orange-100 text-orange-700','loan_disbursement'=>'bg-blue-100 text-blue-700','loan_repayment'=>'bg-purple-100 text-purple-700','membership_fee'=>'bg-teal-100 text-teal-700','penalty'=>'bg-red-100 text-red-700'];
?>
<div class="flex items-center justify-between mb-5">
  <div><h1 class="text-xl font-bold text-slate-800">Transactions</h1><p class="text-sm text-slate-400 mt-0.5"><?= number_format($result['total']??0) ?> records</p></div>
  <div class="flex gap-2">
    <a href="?<?= http_build_query(array_merge($_GET,['export'=>'csv'])) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-file-csv text-green-600"></i> Export</a>
  </div>
</div>

<!-- Type totals pills -->
<div class="flex flex-wrap gap-2 mb-5">
  <?php foreach($typeTotals??[] as $t): $cls=$typeColors[$t['txn_type']]??'bg-slate-100 text-slate-600'; ?>
  <div class="flex items-center gap-2 px-3 py-2 rounded-xl <?= $cls ?> border border-current/20">
    <span class="text-xs font-bold capitalize"><?= str_replace('_',' ',$t['txn_type']) ?></span>
    <span class="text-xs opacity-70"><?= $t['cnt'] ?> · <?= Format::currencyCompact((float)$t['total']) ?></span>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="card mb-5"><div class="card-body py-3">
  <form method="GET" class="flex flex-wrap gap-3">
    <div class="relative flex-1 min-w-[160px]"><i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i><input type="text" name="search" value="<?= htmlspecialchars($_GET['search']??'') ?>" placeholder="Ref, member…" class="form-control pl-9 py-2 text-sm"></div>
    <select name="type" class="form-control form-select py-2 text-sm w-auto" onchange="this.form.submit()">
      <option value="">All Types</option>
      <?php foreach(['deposit','withdrawal','loan_disbursement','loan_repayment','membership_fee','penalty'] as $t): ?><option value="<?= $t ?>" <?= ($filters['type']??'')===$t?'selected':'' ?>><?= ucwords(str_replace('_',' ',$t)) ?></option><?php endforeach; ?>
    </select>
    <input type="date" name="from" value="<?= htmlspecialchars($filters['from']??date('Y-m-01')) ?>" class="form-control py-2 text-sm w-auto">
    <input type="date" name="to" value="<?= htmlspecialchars($filters['to']??date('Y-m-d')) ?>" class="form-control py-2 text-sm w-auto">
    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
    <a href="<?= APP_URL ?>/transactions" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i></a>
  </form>
</div></div>

<div class="card">
  <div class="overflow-x-auto">
    <table class="data-table">
      <thead><tr><th>Reference</th><th>Type</th><th>Member</th><th>Amount</th><th class="hidden md:table-cell">Method</th><th class="hidden lg:table-cell">Balance After</th><th>Date</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
        <?php if(empty($result['data'])): ?>
        <tr><td colspan="9" class="text-center py-12"><i class="fa-solid fa-arrow-right-arrow-left text-5xl text-slate-200 block mb-3"></i><p class="text-slate-400">No transactions found</p></td></tr>
        <?php else: foreach($result['data'] as $t): $tc=$typeColors[$t['txn_type']]??'bg-slate-100 text-slate-600'; ?>
        <tr>
          <td><span class="font-mono text-xs text-slate-600"><?= htmlspecialchars($t['txn_ref']) ?></span></td>
          <td><span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $tc ?>"><?= ucwords(str_replace('_',' ',$t['txn_type'])) ?></span></td>
          <td>
            <?php if($t['member_name']): ?>
            <div class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($t['member_name']) ?></div>
            <div class="text-xs text-slate-400"><?= htmlspecialchars($t['member_no']??'') ?></div>
            <?php else: ?><span class="text-slate-300 text-xs">—</span><?php endif; ?>
          </td>
          <td class="font-bold <?= in_array($t['txn_type'],['deposit','loan_repayment','membership_fee'])?'text-emerald-700':'text-slate-700' ?>"><?= Format::currency((float)$t['amount']) ?></td>
          <td class="hidden md:table-cell text-xs capitalize"><?= str_replace('_',' ',$t['payment_method']??'—') ?></td>
          <td class="hidden lg:table-cell text-xs text-slate-500"><?= $t['balance_after']!==null?Format::currency((float)$t['balance_after']):'—' ?></td>
          <td class="text-xs text-slate-500"><?= Format::date($t['transaction_date']) ?></td>
          <td><?= Format::statusPill($t['status']) ?></td>
          <td><a href="<?= APP_URL ?>/transactions/<?= $t['id'] ?>" class="btn btn-secondary btn-sm py-1 px-2"><i class="fa-solid fa-eye text-xs"></i></a></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if(!empty($result['data'])): $page=$result['page']??1;$lastPage=$result['last_page']??1;$qs=http_build_query(array_diff_key($_GET,['page'=>'']));$base=APP_URL.'/transactions?'.($qs?$qs.'&':''); ?>
  <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between">
    <p class="text-xs text-slate-400">Showing <strong><?= $result['from']??1 ?></strong>–<strong><?= $result['to']??count($result['data']) ?></strong> of <strong><?= number_format($result['total']) ?></strong></p>
    <div class="flex gap-1">
      <a href="<?= $base ?>page=<?= max(1,$page-1) ?>" class="pager-btn <?= $page<=1?'opacity-40 pointer-events-none':'' ?>"><i class="fa-solid fa-chevron-left text-xs"></i></a>
      <?php for($p=max(1,$page-2);$p<=min($lastPage,$page+2);$p++): ?><a href="<?= $base ?>page=<?= $p ?>" class="pager-btn <?= $p==$page?'active':'' ?>"><?= $p ?></a><?php endfor; ?>
      <a href="<?= $base ?>page=<?= min($lastPage,$page+1) ?>" class="pager-btn <?= $page>=$lastPage?'opacity-40 pointer-events-none':'' ?>"><i class="fa-solid fa-chevron-right text-xs"></i></a>
    </div>
  </div>
  <?php endif; ?>
</div>
