<?php use App\Helpers\Format; $pageTitle='Process Withdrawal'; $activePage='savings'; $breadcrumbs=['Savings'=>APP_URL.'/savings','Withdraw'=>null]; ?>
<div class="max-w-2xl mx-auto">
  <div class="flex items-center justify-between mb-5"><div><h1 class="text-xl font-bold text-slate-800">Process Withdrawal</h1></div><a href="<?= APP_URL ?>/savings" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a></div>
  <div class="card"><div class="card-body">
    <form id="withdrawForm" data-ajax="true">
      <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
      <input type="hidden" name="savings_account_id" id="withdraw_account_id" value="<?= htmlspecialchars($_GET['account']??'') ?>">
      <div class="mb-5">
        <label class="form-label required">Member</label>
        <div class="relative" id="wdMemberWrap">
          <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
          <input type="text" id="wdMemberSearch" autocomplete="off" class="form-control pl-9" placeholder="Search member…" oninput="wdSearch(this.value)" value="<?= $member?htmlspecialchars($member['first_name'].' '.$member['last_name']):'' ?>">
          <div id="wdMemberResults" class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-lg z-10 max-h-60 overflow-y-auto"></div>
        </div>
        <input type="hidden" id="wd_member_id" name="member_id" value="<?= $member?$member['id']:'' ?>" required>
      </div>
      <div id="wdMemberInfo" class="<?= $member?'':'hidden' ?> mb-5 p-4 rounded-xl bg-orange-50 border border-orange-200">
        <div class="flex items-center gap-3">
          <div class="avatar-circle w-10 h-10" id="wdAvatar"><?= $member?Format::initials($member['first_name'].' '.$member['last_name']):'?' ?></div>
          <div><div class="font-bold" id="wdName"><?= $member?htmlspecialchars($member['first_name'].' '.$member['last_name']):'' ?></div><div class="text-sm text-slate-500" id="wdNo"><?= $member?htmlspecialchars($member['member_no']):'' ?></div></div>
        </div>
        <div class="mt-3 pt-3 border-t border-orange-200">
          <div class="text-xs text-slate-500">Available Balance</div><div class="font-bold text-emerald-700 text-lg" id="wdBalance">—</div>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4 mb-4">
        <div class="col-span-2"><label class="form-label required">Amount (<?= $settings['currency_symbol']??'USh' ?>)</label><input type="number" name="amount" class="form-control text-lg font-bold" placeholder="0" min="1000" step="500" required></div>
        <div><label class="form-label required">Payment Method</label><select name="payment_method" class="form-control form-select" required><option value="cash">Cash</option><option value="mobile_money">Mobile Money</option><option value="bank_transfer">Bank Transfer</option></select></div>
        <div><label class="form-label required">Date</label><input type="date" name="transaction_date" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required></div>
        <div class="col-span-2"><label class="form-label">Description</label><input type="text" name="description" class="form-control" value="Member savings withdrawal"></div>
      </div>
      <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-sm text-amber-700 mb-4"><i class="fa-solid fa-triangle-exclamation mr-1.5"></i>Minimum savings balance of <?= Format::currency((float)($settings['min_savings']??10000)) ?> must be maintained. Savings locked against active loans cannot be withdrawn.</div>
      <div class="flex gap-3 justify-end pt-4 border-t border-slate-100">
        <a href="<?= APP_URL ?>/savings" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn" style="background:linear-gradient(135deg,#ea580c,#c2410c);color:#fff"><i class="fa-solid fa-arrow-up-from-bracket"></i> Process Withdrawal</button>
      </div>
    </form>
  </div></div>
</div>
<script>
let wdDebounce;
function wdSearch(v){
  clearTimeout(wdDebounce);if(v.length<2){document.getElementById('wdMemberResults').classList.add('hidden');return;}
  wdDebounce=setTimeout(async()=>{
    const r=await fetch(`<?= APP_URL ?>/members/search?q=${encodeURIComponent(v)}`,{headers:{'X-Requested-With':'XMLHttpRequest'}});
    const d=await r.json();const box=document.getElementById('wdMemberResults');
    if(!d.data?.length){box.innerHTML='<div class="px-4 py-3 text-sm text-slate-400">No members found</div>';box.classList.remove('hidden');return;}
    box.innerHTML=d.data.map(m=>`<div class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 cursor-pointer" onclick='selectWdMember(${JSON.stringify(m).replace(/"/g,"&quot;")})'><div class="avatar-circle w-8 h-8 text-xs">${m.initials}</div><div><div class="font-semibold text-sm">${m.full_name}</div><div class="text-xs text-slate-400">${m.member_no} · ${m.total_savings_fmt}</div></div></div>`).join('');
    box.classList.remove('hidden');
  },300);
}
function selectWdMember(m){
  document.getElementById('wd_member_id').value=m.id;document.getElementById('wdMemberSearch').value=m.full_name;
  document.getElementById('wdMemberResults').classList.add('hidden');document.getElementById('wdAvatar').textContent=m.initials;
  document.getElementById('wdName').textContent=m.full_name;document.getElementById('wdNo').textContent=m.member_no;
  document.getElementById('wdBalance').textContent=m.total_savings_fmt;document.getElementById('wdMemberInfo').classList.remove('hidden');
}
document.addEventListener('click',e=>{if(!document.getElementById('wdMemberWrap').contains(e.target))document.getElementById('wdMemberResults').classList.add('hidden');});
document.getElementById('withdrawForm').addEventListener('submit',async function(e){
  e.preventDefault();await window.submitForm(this,{loadingText:'Processing…',onSuccess:r=>{window.showToast('success',r.message);setTimeout(()=>location.reload(),1000);}});
});
</script>
