<?php use App\Helpers\Format;
$pageTitle='Loans'; $activePage='loans'; $breadcrumbs=['Loans'=>null]; ?>
<div class="flex items-center justify-between mb-5">
  <div>
    <h1 class="text-xl font-bold text-slate-800">Loan Portfolio</h1>
    <p class="text-sm text-slate-400 mt-0.5"><?= number_format($result['total']??0) ?> total loans</p>
  </div>
  <div class="flex gap-2">
    <?php if($auth->can('loans.create')): ?>
    <a href="<?= APP_URL ?>/loans/create" class="btn btn-primary btn-sm"><i class="fa-solid fa-file-circle-plus"></i> New Loan</a>
    <?php endif; ?>
  </div>
</div>
<!-- Stats row -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
  <?php $sc=[['label'=>'Active','val'=>($loanStats['active_loans']??0)+($loanStats['disbursed_loans']??0),'color'=>'text-emerald-700'],['label'=>'Pending','val'=>$loanStats['pending_loans']??0,'color'=>'text-amber-600'],['label'=>'Completed','val'=>$loanStats['completed_loans']??0,'color'=>'text-blue-600'],['label'=>'Defaulted','val'=>$loanStats['defaulted_loans']??0,'color'=>'text-red-600']];
  foreach($sc as $s): ?>
  <div class="stat-card text-center py-5">
    <div class="text-2xl font-black <?= $s['color'] ?>"><?= number_format($s['val']) ?></div>
    <div class="text-xs font-semibold text-slate-400 mt-1"><?= $s['label'] ?></div>
  </div>
  <?php endforeach; ?>
</div>
<!-- Filter bar -->
<div class="card mb-5">
  <div class="card-body py-3">
    <form method="GET" class="flex flex-wrap gap-3">
      <div class="relative flex-1 min-w-[180px]">
        <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
        <input type="text" name="search" value="<?= htmlspecialchars($_GET['search']??'') ?>" placeholder="Search loan no, member…" class="form-control pl-9 py-2 text-sm">
      </div>
      <select name="status" class="form-control form-select py-2 text-sm w-auto min-w-[130px]" onchange="this.form.submit()">
        <option value="">All Status</option>
        <?php foreach(['pending','approved','active','completed','defaulted','rejected'] as $s): ?>
          <option value="<?= $s ?>" <?= ($_GET['status']??'')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="product" class="form-control form-select py-2 text-sm w-auto" onchange="this.form.submit()">
        <option value="">All Products</option>
        <?php foreach($loanProducts??[] as $lp): ?>
          <option value="<?= $lp['id'] ?>" <?= ($_GET['product']??'')==$lp['id']?'selected':'' ?>><?= htmlspecialchars($lp['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="date" name="from" value="<?= htmlspecialchars($_GET['from']??'') ?>" class="form-control py-2 text-sm w-auto">
      <input type="date" name="to" value="<?= htmlspecialchars($_GET['to']??'') ?>" class="form-control py-2 text-sm w-auto">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
      <?php if(!empty($_GET['search'])||!empty($_GET['status'])||!empty($_GET['product'])): ?>
        <a href="<?= APP_URL ?>/loans" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i> Clear</a>
      <?php endif; ?>
    </form>
  </div>
</div>
<!-- Table -->
<div class="card">
  <div class="overflow-x-auto">
    <table class="data-table">
      <thead><tr><th>Loan No</th><th>Member</th><th class="hidden md:table-cell">Product</th><th>Amount</th><th class="hidden lg:table-cell">Repaid</th><th class="hidden lg:table-cell">Outstanding</th><th>Status</th><th class="hidden md:table-cell">Applied</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if(empty($result['data'])): ?>
          <tr><td colspan="9" class="text-center py-12"><i class="fa-solid fa-file-contract text-5xl text-slate-200 block mb-3"></i><p class="text-slate-400">No loans found</p></td></tr>
        <?php else: foreach($result['data'] as $l): ?>
        <tr>
          <td><span class="font-mono text-xs bg-slate-100 px-2 py-0.5 rounded"><?= htmlspecialchars($l['loan_no']) ?></span></td>
          <td>
            <div class="font-semibold text-slate-800 text-sm"><?= htmlspecialchars($l['member_name']) ?></div>
            <div class="text-xs text-slate-400"><?= htmlspecialchars($l['member_no']) ?></div>
          </td>
          <td class="hidden md:table-cell text-sm"><?= htmlspecialchars($l['product_name']) ?></td>
          <td class="font-bold text-slate-800"><?= Format::currency((float)$l['principal_amount']) ?></td>
          <td class="hidden lg:table-cell text-emerald-700 font-semibold"><?= Format::currency((float)$l['amount_paid']) ?></td>
          <td class="hidden lg:table-cell font-bold text-amber-700"><?= Format::currency((float)$l['balance_outstanding']) ?></td>
          <td><?= Format::statusPill($l['status']) ?></td>
          <td class="hidden md:table-cell text-xs text-slate-400"><?= Format::date($l['application_date']) ?></td>
          <td>
            <div class="flex gap-1">
              <a href="<?= APP_URL ?>/loans/<?= $l['id'] ?>" class="btn btn-secondary btn-sm py-1 px-2"><i class="fa-solid fa-eye text-xs"></i></a>
              <?php if($l['status']==='pending'&&$auth->can('loans.approve')): ?>
              <button onclick="approveLoan(<?= $l['id'] ?>,'<?= addslashes($l['loan_no']) ?>')" class="btn btn-sm py-1 px-2" style="background:#dcfce7;color:#166534;border:1px solid #a7f3d0;font-size:0.7rem;"><i class="fa-solid fa-check"></i></button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if(!empty($result['data'])): $page=$result['page']??1;$lastPage=$result['last_page']??1;$qs=http_build_query(array_diff_key($_GET,['page'=>'']));$base=APP_URL.'/loans?'.($qs?$qs.'&':''); ?>
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
<script>
function approveLoan(id, no) {
  window.confirmAction({title:'Approve Loan',message:`Approve loan ${no}?`,confirmText:'Approve',type:'primary',onConfirm:async()=>{
    try {
      const fd=new FormData(); fd.append('csrf_token',document.querySelector('meta[name="csrf-token"]').content);
      const r=await fetch(`<?= APP_URL ?>/loans/${id}/approve`,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd});
      const d=await r.json();
      if(d.success){window.showToast('success',d.message);setTimeout(()=>location.reload(),1000);}
      else window.showToast('error',d.message);
    }catch(e){window.showToast('error','Failed to approve loan.');}
  }});
}
</script>
