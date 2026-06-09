<?php 
use App\Helpers\Format;
use App\Helpers\Avatar;
?>
<style>
.profile-hero{background:linear-gradient(135deg,#0a4033 0%,#136b55 60%,#1a8f6f 100%);border-radius:16px;padding:28px;color:#fff;position:relative;overflow:hidden;}
.profile-hero::after{content:'';position:absolute;right:-40px;top:-40px;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,0.06);}
.profile-avatar{width:80px;height:80px;border-radius:50%;border:3px solid rgba(255,255,255,0.4);display:flex;align-items:center;justify-content:center;font-size:1.8rem;font-weight:700;background:rgba(255,255,255,0.15);color:#fff;flex-shrink:0;}
.profile-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
.info-row{display:flex;align-items:flex-start;gap:12px;padding:10px 0;border-bottom:1px solid #f1f5f9;}
.info-row:last-child{border-bottom:none;}
.info-label{font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#94a3b8;width:130px;flex-shrink:0;padding-top:2px;}
.info-value{font-size:0.875rem;color:#1e293b;font-weight:500;flex:1;}
.kyc-doc-card{display:flex;align-items:center;gap:12px;padding:8px 12px;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;}
.kyc-doc-card img{width:48px;height:48px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0;cursor:pointer;}
.kyc-doc-card i{font-size:2rem;color:#64748b;}
.tab-btn{padding:9px 18px;border-radius:8px;font-size:0.85rem;font-weight:600;cursor:pointer;border:1.5px solid transparent;transition:all 0.18s;}
.tab-btn.active{background:var(--green-mid);color:#fff;border-color:var(--green-mid);}
.tab-btn:not(.active){background:#fff;color:#64748b;border-color:#e2e8f0;}
.tab-btn:not(.active):hover{border-color:#94a3b8;color:#374151;}
.tab-panel{display:none;}.tab-panel.active{display:block;}
</style>

<!-- Header Actions -->
<div class="flex items-center justify-between mb-5">
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard"><i class="fa-solid fa-house-chimney text-xs"></i></a>
    <span class="sep">/</span><a href="<?= APP_URL ?>/members">Members</a>
    <span class="sep">/</span><span class="current"><?= htmlspecialchars($member['first_name'].' '.$member['last_name']) ?></span>
  </div>
  <div class="flex gap-2 flex-wrap">
    <a href="<?= APP_URL ?>/members/<?= $member['id'] ?>/statement" class="btn btn-secondary btn-sm" target="_blank">
      <i class="fa-solid fa-file-pdf text-red-500"></i> Statement
    </a>
    <?php if($auth->can('savings.deposit')): ?>
    <a href="<?= APP_URL ?>/savings/deposit?member=<?= $member['id'] ?>" class="btn btn-gold btn-sm">
      <i class="fa-solid fa-arrow-down-to-bracket"></i> Deposit
    </a>
    <?php endif; ?>
    <?php if($auth->can('loans.create')): ?>
    <a href="<?= APP_URL ?>/loans/create?member=<?= $member['id'] ?>" class="btn btn-primary btn-sm">
      <i class="fa-solid fa-file-contract"></i> New Loan
    </a>
    <?php endif; ?>
    <?php if($auth->can('members.edit')): ?>
    <a href="<?= APP_URL ?>/members/<?= $member['id'] ?>/edit" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-pen"></i> Edit
    </a>
    <?php endif; ?>
  </div>
</div>

<!-- Profile Hero -->
<div class="profile-hero mb-5">
  <div class="flex items-center gap-5">
    <div class="profile-avatar">
        <?= Avatar::medium($member) ?>
    </div>
    <div class="flex-1 min-w-0">
      <h1 class="text-2xl font-bold mb-1"><?= htmlspecialchars($member['first_name'].' '.$member['middle_name'].' '.$member['last_name']) ?></h1>
      <div class="flex flex-wrap gap-3 text-sm text-green-200 mb-3">
        <span><i class="fa-solid fa-id-badge mr-1"></i><?= htmlspecialchars($member['member_no']) ?></span>
        <span><i class="fa-solid fa-phone mr-1"></i><?= htmlspecialchars($member['phone']) ?></span>
        <?php if($member['email']): ?><span><i class="fa-solid fa-envelope mr-1"></i><?= htmlspecialchars($member['email']) ?></span><?php endif; ?>
        <span><i class="fa-solid fa-calendar mr-1"></i>Joined <?= Format::date($member['membership_date']) ?></span>
      </div>
      <div class="flex gap-2 flex-wrap">
        <?= Format::statusPill($member['status']) ?>
        <?php if($member['kyc_verified']): ?>
          <span class="badge" style="background:rgba(16,185,129,0.2);color:#6ee7b7;border-color:rgba(16,185,129,0.4)"><i class="fa-solid fa-shield-check mr-1"></i>KYC Verified</span>
        <?php else: ?>
          <span class="badge" style="background:rgba(245,158,11,0.2);color:#fcd34d;border-color:rgba(245,158,11,0.3)"><i class="fa-solid fa-clock mr-1"></i>KYC Pending</span>
          <?php if($auth->can('members.edit')): ?>
          <button onclick="verifyKyc(<?= $member['id'] ?>)" class="badge cursor-pointer" style="background:rgba(59,130,246,0.2);color:#93c5fd;border-color:rgba(59,130,246,0.3)">
            <i class="fa-solid fa-check mr-1"></i>Verify KYC
          </button>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <!-- Financial KPIs -->
  <div class="grid grid-cols-3 gap-4 mt-5 pt-5 border-t border-white/10">
    <div class="text-center">
      <div class="text-2xl font-black text-white"><?= Format::currencyCompact((float)$member['total_savings']) ?></div>
      <div class="text-xs text-green-300 mt-0.5">Total Savings</div>
    </div>
    <div class="text-center">
      <div class="text-2xl font-black text-white"><?= $member['total_loans'] ?></div>
      <div class="text-xs text-green-300 mt-0.5">Total Loans</div>
    </div>
    <div class="text-center">
      <div class="text-2xl font-black" style="color:#fcd34d"><?= Format::currencyCompact((float)$member['active_loan_balance']) ?></div>
      <div class="text-xs text-green-300 mt-0.5">Loan Balance</div>
    </div>
  </div>
</div>

<!-- Tab navigation -->
<div class="flex gap-2 mb-5 flex-wrap">
  <button class="tab-btn active" onclick="switchTab('personal',this)">Personal Info</button>
  <button class="tab-btn" onclick="switchTab('savings',this)">Savings (<?= count($savingsAccounts) ?>)</button>
  <button class="tab-btn" onclick="switchTab('loans',this)">Loans (<?= count($loans) ?>)</button>
  <button class="tab-btn" onclick="switchTab('transactions',this)">Transactions</button>
</div>

<!-- Personal Info Tab -->
<div id="tab-personal" class="tab-panel active">
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    <!-- Personal Details Card -->
    <div class="card">
      <div class="card-header"><h3 class="section-title"><i class="fa-solid fa-user mr-2" style="color:var(--green-mid)"></i>Personal Details</h3></div>
      <div class="card-body">
        <?php 
        $personalFields = [
            ['Gender', ucfirst($member['gender'] ?? '—')],
            ['Date of Birth', Format::date($member['date_of_birth'])],
            ['National ID', $member['national_id'] ?? '—'],
            ['Passport Number', $member['passport_no'] ?? '—'],
            ['Occupation', $member['occupation'] ?? '—'],
            ['Employer', $member['employer'] ?? '—'],
            ['District', $member['district'] ?? '—'],
            ['Address', $member['address'] ?? '—']
        ];
        foreach($personalFields as [$label, $value]): ?>
        <div class="info-row"><div class="info-label"><?= $label ?></div><div class="info-value"><?= htmlspecialchars($value) ?></div></div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Next of Kin Card -->
    <div class="card">
      <div class="card-header"><h3 class="section-title"><i class="fa-solid fa-people-group mr-2" style="color:var(--green-mid)"></i>Next of Kin</h3></div>
      <div class="card-body">
        <?php 
        $kinFields = [
            ['Name', $member['next_of_kin_name'] ?? '—'],
            ['Phone', $member['next_of_kin_phone'] ?? '—'],
            ['Relationship', $member['next_of_kin_relationship'] ?? '—']
        ];
        foreach($kinFields as [$label, $value]): ?>
        <div class="info-row"><div class="info-label"><?= $label ?></div><div class="info-value"><?= htmlspecialchars($value) ?></div></div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- KYC Documents Card (spans full width if needed) -->
    <div class="card lg:col-span-2">
      <div class="card-header"><h3 class="section-title"><i class="fa-solid fa-id-card mr-2" style="color:var(--green-mid)"></i>KYC Documents</h3></div>
      <div class="card-body">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- ID Front -->
          <div>
            <div class="info-label mb-2">National ID – Front</div>
            <?php if(!empty($member['id_front'])): ?>
              <div class="kyc-doc-card">
                <?php 
                  $frontExt = strtolower(pathinfo($member['id_front'], PATHINFO_EXTENSION));
                  if(in_array($frontExt, ['jpg','jpeg','png','webp'])): ?>
                  <img src="<?= APP_URL ?>/storage/uploads/kyc/<?= htmlspecialchars($member['id_front']) ?>" 
                       onclick="window.open(this.src)" class="cursor-pointer">
                <?php else: ?>
                  <i class="fa-regular fa-file-pdf"></i>
                <?php endif; ?>
                <div class="flex-1">
                  <div class="text-sm font-medium truncate"><?= htmlspecialchars(basename($member['id_front'])) ?></div>
                  <a href="<?= APP_URL ?>/storage/uploads/kyc/<?= htmlspecialchars($member['id_front']) ?>" target="_blank" class="text-xs text-green-600">View full</a>
                </div>
              </div>
            <?php else: ?>
              <div class="text-slate-400 text-sm bg-slate-50 rounded-lg p-3 text-center">Not uploaded</div>
            <?php endif; ?>
          </div>
          <!-- ID Back -->
          <div>
            <div class="info-label mb-2">National ID – Back</div>
            <?php if(!empty($member['id_back'])): ?>
              <div class="kyc-doc-card">
                <?php 
                  $backExt = strtolower(pathinfo($member['id_back'], PATHINFO_EXTENSION));
                  if(in_array($backExt, ['jpg','jpeg','png','webp'])): ?>
                  <img src="<?= APP_URL ?>/storage/uploads/kyc/<?= htmlspecialchars($member['id_back']) ?>" 
                       onclick="window.open(this.src)" class="cursor-pointer">
                <?php else: ?>
                  <i class="fa-regular fa-file-pdf"></i>
                <?php endif; ?>
                <div class="flex-1">
                  <div class="text-sm font-medium truncate"><?= htmlspecialchars(basename($member['id_back'])) ?></div>
                  <a href="<?= APP_URL ?>/storage/uploads/kyc/<?= htmlspecialchars($member['id_back']) ?>" target="_blank" class="text-xs text-green-600">View full</a>
                </div>
              </div>
            <?php else: ?>
              <div class="text-slate-400 text-sm bg-slate-50 rounded-lg p-3 text-center">Not uploaded</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Savings Tab (unchanged) -->
<div id="tab-savings" class="tab-panel">
  <div class="card">
    <div class="card-header">
      <h3 class="section-title">Savings Accounts</h3>
      <?php if($auth->can('savings.deposit')): ?>
      <a href="<?= APP_URL ?>/savings/deposit?member=<?= $member['id'] ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Deposit</a>
      <?php endif; ?>
    </div>
    <div class="overflow-x-auto">
      <table class="data-table">
        <thead><tr><th>Account No</th><th>Type</th><th>Balance</th><th>Status</th><th>Opened</th><th>Action</th></tr</thead>
        <tbody>
          <?php foreach($savingsAccounts as $sa): ?>
          <tr>
            <td><span class="font-mono text-xs bg-slate-100 px-2 py-0.5 rounded"><?= htmlspecialchars($sa['account_no']) ?></span></td>
            <td><?= Format::titleCase($sa['account_type']) ?></td>
            <td class="font-bold text-emerald-700"><?= Format::currency((float)$sa['balance']) ?></td>
            <td><?= Format::statusPill($sa['status']) ?></td>
            <td class="text-xs text-slate-400"><?= Format::date($sa['opened_at']) ?></td>
            <td><a href="<?= APP_URL ?>/savings/<?= $sa['id'] ?>" class="btn btn-secondary btn-sm py-1 px-2"><i class="fa-solid fa-eye text-xs"></i></a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Loans Tab (unchanged) -->
<div id="tab-loans" class="tab-panel">
  <div class="card">
    <div class="card-header">
      <h3 class="section-title">Loan History</h3>
      <?php if($auth->can('loans.create')): ?>
      <a href="<?= APP_URL ?>/loans/create?member=<?= $member['id'] ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> New Loan</a>
      <?php endif; ?>
    </div>
    <div class="overflow-x-auto">
      <table class="data-table">
        <thead><tr><th>Loan No</th><th>Product</th><th>Principal</th><th>Outstanding</th><th>Status</th><th>Applied</th><th>Action</th></tr</thead>
        <tbody>
          <?php if(empty($loans)): ?>
          <tr><td colspan="7" class="text-center py-8 text-slate-400"><i class="fa-solid fa-file-contract text-3xl block mb-2 text-slate-200"></i>No loans on record</td></tr>
          <?php else: foreach($loans as $l): ?>
          <tr>
            <td><span class="font-mono text-xs bg-slate-100 px-2 py-0.5 rounded"><?= htmlspecialchars($l['loan_no']) ?></span></td>
            <td class="text-sm"><?= htmlspecialchars($l['product_name']) ?></td>
            <td class="font-semibold"><?= Format::currency((float)$l['principal_amount']) ?></td>
            <td class="font-bold <?= $l['balance_outstanding']>0?'text-amber-600':'text-emerald-600' ?>"><?= Format::currency((float)$l['balance_outstanding']) ?></td>
            <td><?= Format::statusPill($l['status']) ?></td>
            <td class="text-xs text-slate-400"><?= Format::date($l['application_date']) ?></td>
            <td><a href="<?= APP_URL ?>/loans/<?= $l['id'] ?>" class="btn btn-secondary btn-sm py-1 px-2"><i class="fa-solid fa-eye text-xs"></i></a></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Transactions Tab (unchanged) -->
<div id="tab-transactions" class="tab-panel">
  <div class="card">
    <div class="card-header"><h3 class="section-title">Recent Transactions</h3></div>
    <div class="overflow-x-auto">
      <table class="data-table">
        <thead><tr><th>Ref</th><th>Type</th><th>Amount</th><th>Method</th><th>Date</th><th>Status</th></tr</thead>
        <tbody>
          <?php if(empty($transactions)): ?>
          <tr><td colspan="6" class="text-center py-8 text-slate-400">No transactions found</td></tr>
          <?php else: foreach($transactions as $t): ?>
          <tr>
            <td><span class="font-mono text-xs"><?= htmlspecialchars($t['txn_ref']) ?></span></td>
            <td class="text-xs capitalize"><?= str_replace('_',' ',$t['txn_type']) ?></td>
            <td class="font-bold <?= in_array($t['txn_type'],['deposit','loan_repayment'])?'text-emerald-700':'text-slate-700' ?>"><?= Format::currency((float)$t['amount']) ?></td>
            <td class="text-xs capitalize"><?= str_replace('_',' ',$t['payment_method']) ?></td>
            <td class="text-xs text-slate-400"><?= Format::date($t['transaction_date']) ?></td>
            <td><?= Format::statusPill($t['status']) ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function switchTab(name, btn) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  btn.classList.add('active');
}

async function verifyKyc(id) {
  window.confirmAction({title:'Verify KYC',message:'Confirm KYC verification for this member?',confirmText:'Verify',type:'primary',onConfirm:async()=>{
    try {
      const fd=new FormData(); fd.append('csrf_token',document.querySelector('meta[name="csrf-token"]').content);
      const r=await fetch(`<?= APP_URL ?>/members/${id}/kyc-verify`,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd});
      const d=await r.json();
      if(d.success){window.showToast('success',d.message);setTimeout(()=>location.reload(),800);}
      else window.showToast('error',d.message);
    } catch(e){window.showToast('error','Verification failed.');}
  }});
}
</script>