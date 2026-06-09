<?php use App\Helpers\Format; $pageTitle='Social Fund Fees'; $activePage='social-fund'; $breadcrumbs=['Social Fund Fees'=>null]; ?>
<div class="flex items-center justify-between mb-5">
  <div><h1 class="text-xl font-bold text-slate-800">Social Fund Fees</h1>
  <p class="text-sm text-slate-400">Monthly member fee management</p></div>
  <div class="flex gap-2">
    <a href="<?= APP_URL ?>/social-fund/payments" class="btn btn-secondary btn-sm"><i class="fa-solid fa-list-check"></i> This Month</a>
    <?php if($auth->can('social_fund.manage')): ?>
    <a href="<?= APP_URL ?>/social-fund/create" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> New Fee Config</a>
    <?php endif; ?>
  </div>
</div>

<?php if($activeFee&&$summary): ?>
<!-- Current month summary -->
<div class="card mb-5" style="background:linear-gradient(135deg,#0a4033,#136b55);border:none">
  <div class="card-body text-white">
    <div class="flex items-start justify-between mb-4">
      <div>
        <div class="text-xs text-green-300 font-semibold uppercase tracking-wider mb-1">Active Fee — <?= date('F Y') ?></div>
        <div class="text-2xl font-black"><?= htmlspecialchars($activeFee['name']) ?></div>
        <div class="text-green-200 text-sm mt-0.5"><?= Format::currency((float)$activeFee['amount']) ?> per member · Due day <?= $activeFee['due_day'] ?></div>
      </div>
      <div class="text-right">
        <div class="text-3xl font-black" style="color:#a7f3d0"><?= Format::currency((float)($summary['total_collected']??0)) ?></div>
        <div class="text-green-300 text-xs mt-0.5">collected of <?= Format::currency((float)($summary['total_due']??0)) ?></div>
      </div>
    </div>
    <div class="grid grid-cols-5 gap-3">
      <?php $sc=[['Paid',$summary['paid_count']??0,'#a7f3d0'],['Partial',$summary['partial_count']??0,'#fde68a'],['Overdue',$summary['overdue_count']??0,'#fca5a5'],['Pending',$summary['pending_count']??0,'rgba(255,255,255,0.5)'],['Waived',$summary['waived_count']??0,'#c4b5fd']];
      foreach($sc as [$l,$v,$cl]): ?>
      <div class="text-center p-3 rounded-xl" style="background:rgba(0,0,0,0.2)">
        <div class="text-xl font-black" style="color:<?= $cl ?>"><?= number_format($v) ?></div>
        <div class="text-xs text-green-300 mt-0.5"><?= $l ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if(($summary['total_arrears']??0)>0): ?>
    <div class="mt-3 p-2 rounded-xl text-sm" style="background:rgba(239,68,68,0.2);color:#fca5a5">
      <i class="fa-solid fa-triangle-exclamation mr-1"></i>Total arrears: <strong><?= Format::currency((float)$summary['total_arrears']) ?></strong>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Fee configurations list -->
<div class="card">
  <div class="card-header"><h3 class="section-title">Fee Configurations</h3></div>
  <div class="overflow-x-auto">
    <table class="data-table">
      <thead><tr><th>Name</th><th>Amount</th><th>Frequency</th><th class="hidden md:table-cell">Due Day</th><th class="hidden md:table-cell">Applies To</th><th class="hidden lg:table-cell">Effective From</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if(empty($fees)): ?>
        <tr><td colspan="8" class="text-center py-10 text-slate-400">No fee configurations yet</td></tr>
        <?php else: foreach($fees as $f): ?>
        <tr>
          <td><div class="font-semibold text-slate-800"><?= htmlspecialchars($f['name']) ?></div></td>
          <td class="font-bold text-emerald-700"><?= Format::currency((float)$f['amount']) ?></td>
          <td class="text-sm capitalize"><?= $f['frequency'] ?></td>
          <td class="hidden md:table-cell text-sm"><?= $f['due_day'] ?>th + <?= $f['grace_days'] ?> days grace</td>
          <td class="hidden md:table-cell text-xs capitalize"><?= str_replace('_',' ',$f['applies_to']) ?></td>
          <td class="hidden lg:table-cell text-xs text-slate-400"><?= Format::date($f['effective_from']) ?></td>
          <td><?= Format::statusPill($f['status']) ?></td>
          <td>
            <div class="flex gap-1">
              <?php if($auth->can('social_fund.manage')): ?>
              <a href="<?= APP_URL ?>/social-fund/<?= $f['id'] ?>/edit" class="btn btn-secondary btn-sm py-1 px-2.5"><i class="fa-solid fa-pen text-xs"></i></a>
              <button onclick="generatePeriod(<?= $f['id'] ?>,'<?= htmlspecialchars($f['name']) ?>')" class="btn btn-sm py-1 px-2.5" style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;font-size:.75rem"><i class="fa-solid fa-calendar-plus"></i></button>
              <?php endif; ?>
              <a href="<?= APP_URL ?>/social-fund/payments?fee=<?= $f['id'] ?>" class="btn btn-secondary btn-sm py-1 px-2.5"><i class="fa-solid fa-eye text-xs"></i></a>
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
    <div class="modal-header"><h3 class="font-bold text-slate-800"><i class="fa-solid fa-calendar-plus text-blue-500 mr-2"></i>Generate Fee Records</h3><button onclick="document.getElementById('genModal').classList.add('hidden')" class="text-slate-400"><i class="fa-solid fa-xmark text-lg"></i></button></div>
    <div class="modal-body space-y-3">
      <p class="text-sm text-slate-600">Generate fee records for <strong id="genFeeName"></strong></p>
      <input type="hidden" id="genFeeId">
      <div class="grid grid-cols-2 gap-3">
        <div><label class="form-label required">Month</label>
          <select id="genMonth" class="form-control form-select">
            <?php for($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= $m==(int)date('n')?'selected':'' ?>><?= date('F',mktime(0,0,0,$m,1)) ?></option><?php endfor; ?>
          </select>
        </div>
        <div><label class="form-label required">Year</label>
          <input type="number" id="genYear" class="form-control" value="<?= date('Y') ?>" min="2020" max="<?= date('Y')+1 ?>">
        </div>
      </div>
      <div class="p-3 rounded-xl" style="background:#eff6ff;border:1px solid #bfdbfe"><p class="text-xs text-blue-700">This creates pending fee records for all applicable members. Existing records are skipped.</p></div>
    </div>
    <div class="modal-footer"><button onclick="document.getElementById('genModal').classList.add('hidden')" class="btn btn-secondary">Cancel</button><button onclick="submitGenerate()" class="btn btn-primary"><i class="fa-solid fa-gear"></i> Generate</button></div>
  </div>
</div>
<script>
function generatePeriod(id,name){document.getElementById('genFeeId').value=id;document.getElementById('genFeeName').textContent=name;document.getElementById('genModal').classList.remove('hidden');}
async function submitGenerate(){
  const fd=new FormData();
  fd.append('csrf_token',document.querySelector('meta[name="csrf-token"]').content);
  fd.append('fee_id',document.getElementById('genFeeId').value);
  fd.append('month',document.getElementById('genMonth').value);
  fd.append('year',document.getElementById('genYear').value);
  const r=await fetch('<?= APP_URL ?>/social-fund/generate-period',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd});
  const d=await r.json();
  document.getElementById('genModal').classList.add('hidden');
  if(d.success){window.showToast('success',d.message);}else window.showToast('error',d.message);
}
</script>
