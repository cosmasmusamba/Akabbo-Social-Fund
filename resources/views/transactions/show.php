<?php use App\Helpers\Format; ?>
<div class="max-w-2xl mx-auto">
  <div class="flex items-center justify-between mb-5">
    <div class="breadcrumb"><a href="<?= APP_URL ?>/transactions">Transactions</a><span class="sep">/</span><span class="current"><?= htmlspecialchars($txn['txn_ref']) ?></span></div>
    <a href="<?= APP_URL ?>/transactions" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
  </div>
  <div class="card">
    <div class="card-header">
      <div><div class="text-xs text-slate-400 mb-1">Transaction Reference</div><h2 class="font-mono font-bold text-slate-800 text-lg"><?= htmlspecialchars($txn['txn_ref']) ?></h2></div>
      <?= Format::statusPill($txn['status']) ?>
    </div>
    <div class="card-body">
      <div class="text-center mb-6 p-6 rounded-xl" style="background:linear-gradient(135deg,#f0fdf9,#ecfdf5)">
        <div class="text-xs font-semibold text-slate-400 mb-1 uppercase tracking-wider"><?= ucwords(str_replace('_',' ',$txn['txn_type'])) ?></div>
        <div class="text-4xl font-black text-emerald-700"><?= Format::currency((float)$txn['amount']) ?></div>
        <div class="text-sm text-slate-500 mt-1"><?= Format::date($txn['transaction_date']) ?></div>
      </div>
      <?php $fields=[['Member',$txn['member_name']??'—'],['Member No',$txn['member_no']??'—'],['Payment Method',ucwords(str_replace('_',' ',$txn['payment_method']??'—'))],['External Reference',$txn['external_ref']??'—'],['Balance Before',$txn['balance_before']!==null?Format::currency((float)$txn['balance_before']):'—'],['Balance After',$txn['balance_after']!==null?Format::currency((float)$txn['balance_after']):'—'],['Description',$txn['description']??'—'],['Created At',Format::datetime($txn['created_at'])]];
      foreach($fields as [$l,$v]): ?>
      <div class="flex justify-between py-2.5 border-b border-slate-50 last:border-0">
        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide"><?= $l ?></span>
        <span class="text-sm font-semibold text-slate-700 text-right max-w-xs"><?= htmlspecialchars($v) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if($auth->can('transactions.approve')&&$txn['status']==='pending'): ?>
    <div class="px-6 pb-5 flex gap-3 justify-end">
      <button onclick="approveTxn()" class="btn btn-primary btn-sm"><i class="fa-solid fa-check"></i> Approve</button>
    </div>
    <?php endif; ?>
  </div>
</div>
<script>
async function approveTxn(){
  const fd=new FormData();fd.append('csrf_token',document.querySelector('meta[name="csrf-token"]').content);
  const r=await fetch('<?= APP_URL ?>/transactions/<?= $txn['id'] ?>/approve',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd});
  const d=await r.json();if(d.success){window.showToast('success',d.message);setTimeout(()=>location.reload(),900);}else window.showToast('error',d.message);
}
</script>
