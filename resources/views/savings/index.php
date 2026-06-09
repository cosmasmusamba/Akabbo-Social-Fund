<?php use App\Helpers\Format; $pageTitle='Savings'; $activePage='savings'; $breadcrumbs=['Savings'=>null]; ?>
<div class="flex items-center justify-between mb-5">
  <div><h1 class="text-xl font-bold text-slate-800">Savings Accounts</h1><p class="text-sm text-slate-400 mt-0.5"><?= number_format($result['total']??0) ?> accounts</p></div>
  <div class="flex gap-2">
    <?php if($auth->can('savings.deposit')): ?>
    <a href="<?= APP_URL ?>/savings/deposit" class="btn btn-gold btn-sm"><i class="fa-solid fa-arrow-down-to-bracket"></i> Deposit</a>
    <a href="<?= APP_URL ?>/savings/withdraw" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-up-from-bracket"></i> Withdraw</a>
    <?php endif; ?>
  </div>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
  <?php $sc=[['Total Savings',Format::currencyCompact((float)($summaryStats['total_savings']??0)),'text-emerald-700','fa-piggy-bank'],['Accounts',number_format($summaryStats['total_accounts']??0),'text-blue-700','fa-wallet'],['Active',number_format($summaryStats['active_accounts']??0),'text-green-600','fa-circle-check'],['Dormant',number_format($summaryStats['dormant_accounts']??0),'text-amber-600','fa-moon']];
  foreach($sc as [$label,$val,$color,$icon]): ?>
  <div class="stat-card text-center py-5">
    <i class="fa-solid <?= $icon ?> text-2xl <?= $color ?> mb-2 block"></i>
    <div class="text-xl font-black <?= $color ?>"><?= $val ?></div>
    <div class="text-xs text-slate-400 mt-1"><?= $label ?></div>
  </div>
  <?php endforeach; ?>
</div>

<div class="card mb-5"><div class="card-body py-3">
  <form method="GET" class="flex flex-wrap gap-3">
    <div class="relative flex-1 min-w-[180px]"><i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i><input type="text" name="search" value="<?= htmlspecialchars($_GET['search']??'') ?>" placeholder="Search account, member…" class="form-control pl-9 py-2 text-sm"></div>
    <select name="status" class="form-control form-select py-2 text-sm w-auto" onchange="this.form.submit()">
      <option value="">All Status</option>
      <?php foreach(['active','dormant','frozen'] as $s): ?><option value="<?= $s ?>" <?= ($_GET['status']??'')===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
    <?php if(!empty($_GET['search'])||!empty($_GET['status'])): ?><a href="<?= APP_URL ?>/savings" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i> Clear</a><?php endif; ?>
  </form>
</div></div>

<div class="card">
  <div class="overflow-x-auto">
    <table class="data-table">
      <thead><tr><th>Account No</th><th>Member</th><th class="hidden md:table-cell">Phone</th><th>Type</th><th>Balance</th><th>Status</th><th class="hidden md:table-cell">Opened</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if(empty($result['data'])): ?>
        <tr><td colspan="8" class="text-center py-12"><i class="fa-solid fa-piggy-bank text-5xl text-slate-200 block mb-3"></i><p class="text-slate-400">No savings accounts found</p></td></tr>
        <?php else: foreach($result['data'] as $a): ?>
        <tr>
          <td><span class="font-mono text-xs bg-slate-100 px-2 py-0.5 rounded"><?= htmlspecialchars($a['account_no']) ?></span></td>
          <td><div class="flex items-center gap-2"><div class="avatar-circle w-8 h-8 text-xs"><?= Format::initials($a['member_name']) ?></div><div><div class="text-sm font-semibold"><?= htmlspecialchars($a['member_name']) ?></div><div class="text-xs text-slate-400"><?= htmlspecialchars($a['member_no']) ?></div></div></div></td>
          <td class="hidden md:table-cell text-sm"><?= htmlspecialchars($a['phone']) ?></td>
          <td class="text-xs capitalize"><?= str_replace('_',' ',$a['account_type']) ?></td>
          <td class="font-bold text-emerald-700"><?= Format::currency((float)$a['balance']) ?></td>
          <td><?= Format::statusPill($a['status']) ?></td>
          <td class="hidden md:table-cell text-xs text-slate-400"><?= Format::date($a['opened_at']) ?></td>
          <td>
            <div class="flex gap-1">
              <a href="<?= APP_URL ?>/savings/<?= $a['id'] ?>" class="btn btn-secondary btn-sm py-1 px-2"><i class="fa-solid fa-eye text-xs"></i></a>
              <?php if($auth->can('savings.deposit')): ?>
              <a href="<?= APP_URL ?>/savings/deposit?member=<?= $a['member_id'] ?>&account=<?= $a['id'] ?>" class="btn btn-sm py-1 px-2" style="background:#dcfce7;color:#166534;border:1px solid #a7f3d0;font-size:0.7rem">Dep.</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if(!empty($result['data'])): $page=$result['page']??1;$lastPage=$result['last_page']??1;$qs=http_build_query(array_diff_key($_GET,['page'=>'']));$base=APP_URL.'/savings?'.($qs?$qs.'&':''); ?>
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
