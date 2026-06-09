<?php
use App\Helpers\Format;
$pageTitle   = 'Issue Shares';
$activePage  = 'shares';
$breadcrumbs = ['Shares' => APP_URL.'/shares', 'Issue Shares' => null];
?>
<div class="max-w-2xl mx-auto">
  <div class="flex items-center justify-between mb-5">
    <div>
      <h1 class="text-xl font-bold text-slate-800">Issue Shares to Member</h1>
      <p class="text-sm text-slate-400 mt-0.5">Par value: <?= Format::currency((float)($config['par_value'] ?? 1000)) ?> per share</p>
    </div>
    <a href="<?= APP_URL ?>/shares" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
  </div>

  <div class="card">
    <div class="card-body">
      <form id="issueSharesForm">
        <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">

        <!-- Member search -->
        <div class="mb-5">
          <label class="form-label required">Member</label>
          <div class="relative" id="shareMemberWrap">
            <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
            <input type="text" id="shareMemberSearch" autocomplete="off"
                   class="form-control pl-9" placeholder="Search member by name, phone or number…"
                   oninput="searchMember(this.value)">
            <div id="shareMemberResults"
                 class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-slate-200
                        rounded-xl shadow-lg z-10 max-h-60 overflow-y-auto"></div>
          </div>
          <input type="hidden" id="share_member_id" name="member_id" required>
        </div>

        <!-- Member info panel -->
        <div id="shareMemberCard" class="hidden mb-5 p-4 rounded-xl border border-emerald-200 bg-emerald-50">
          <div class="flex items-center gap-3 mb-3">
            <div class="avatar-circle w-10 h-10" id="smAvatar">?</div>
            <div>
              <div class="font-bold text-slate-800" id="smName">—</div>
              <div class="text-xs text-slate-500" id="smNo">—</div>
            </div>
          </div>
          <div class="grid grid-cols-3 gap-3 pt-3 border-t border-emerald-200 text-center text-xs">
            <div><div class="text-slate-400 font-semibold">Current Shares</div><div class="font-bold text-slate-800 mt-0.5" id="smShares">—</div></div>
            <div><div class="text-slate-400 font-semibold">Savings Balance</div><div class="font-bold text-emerald-700 mt-0.5" id="smSavings">—</div></div>
            <div><div class="text-slate-400 font-semibold">Shareholder?</div><div class="font-bold mt-0.5" id="smHolder">—</div></div>
          </div>
        </div>

        <!-- Share details -->
        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="form-label required">Number of Shares</label>
              <input type="number" name="shares_qty" id="sharesQty" class="form-control"
                     min="<?= $config['min_shares'] ?? 1 ?>"
                     max="<?= $config['max_shares_per_member'] ?? 1000 ?>"
                     placeholder="e.g. 10" required oninput="calcTotal()">
              <p class="text-xs text-slate-400 mt-1">
                Min: <?= number_format($config['min_shares'] ?? 1) ?> ·
                Max: <?= number_format($config['max_shares_per_member'] ?? 1000) ?> per member
              </p>
            </div>
            <div>
              <label class="form-label">Total Cost</label>
              <div class="form-control bg-slate-50 font-bold text-emerald-700 flex items-center" id="totalCost">
                —
              </div>
              <p class="text-xs text-slate-400 mt-1">
                @ <?= Format::currency((float)($config['par_value'] ?? 1000)) ?> per share
              </p>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="form-label required">Payment Method</label>
              <select name="payment_method" class="form-control form-select" required>
                <option value="cash">Cash</option>
                <option value="mobile_money">Mobile Money</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="deduction">Deduct from Savings</option>
              </select>
            </div>
            <div>
              <label class="form-label required">Transaction Date</label>
              <input type="date" name="transaction_date" class="form-control"
                     value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
            </div>
          </div>

          <div>
            <label class="form-label required">Notes / Reason <span class="text-red-500">*</span></label>
            <textarea name="notes" rows="3" class="form-control" required
                      placeholder="Mandatory: describe the purpose of this share issuance, board resolution reference, etc."></textarea>
            <p class="text-xs text-amber-600 mt-1">
              <i class="fa-solid fa-triangle-exclamation mr-1"></i>
              Notes are mandatory for all share transactions. This request will be sent for approval.
            </p>
          </div>
        </div>

        <!-- Privilege preview -->
        <div id="privilegePreview" class="hidden mt-5 p-4 rounded-xl border border-amber-200 bg-amber-50">
          <h4 class="font-semibold text-amber-800 text-sm mb-2">
            <i class="fa-solid fa-star mr-1.5 text-amber-500"></i>
            Loan Privileges After Share Issuance
          </h4>
          <div class="grid grid-cols-2 gap-3 text-sm">
            <div class="flex justify-between">
              <span class="text-slate-500">Interest Rate:</span>
              <span class="font-bold text-emerald-700">
                Standard − <?= Format::percentage((float)($config['loan_rate_discount'] ?? 2)) ?>
              </span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-500">Loan Multiplier:</span>
              <span class="font-bold text-blue-700">
                Standard + <?= $config['loan_multiplier_bonus'] ?? 1 ?>×
              </span>
            </div>
          </div>
        </div>

        <div class="flex gap-3 justify-end pt-5 border-t border-slate-100 mt-5">
          <a href="<?= APP_URL ?>/shares" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn btn-primary" id="issueBtn" disabled>
            <i class="fa-solid fa-certificate"></i> Issue Shares (Pending Approval)
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const PAR_VALUE = <?= (float)($config['par_value'] ?? 1000) ?>;
const SYM       = '<?= $settings['currency_symbol'] ?? 'USh' ?>';

function fmt(n) {
  return SYM + ' ' + Number(n).toLocaleString('en-US', {minimumFractionDigits:0,maximumFractionDigits:0});
}

function calcTotal() {
  const qty = parseInt(document.getElementById('sharesQty').value) || 0;
  document.getElementById('totalCost').textContent = qty > 0 ? fmt(qty * PAR_VALUE) : '—';
  if (qty > 0) document.getElementById('privilegePreview').classList.remove('hidden');
}

let srDebounce;
function searchMember(v) {
  clearTimeout(srDebounce);
  if (v.length < 2) { document.getElementById('shareMemberResults').classList.add('hidden'); return; }
  srDebounce = setTimeout(async () => {
    const r = await fetch(`<?= APP_URL ?>/members/search?q=${encodeURIComponent(v)}`, {headers:{'X-Requested-With':'XMLHttpRequest'}});
    const d = await r.json();
    const box = document.getElementById('shareMemberResults');
    if (!d.data?.length) {
      box.innerHTML = '<div class="px-4 py-3 text-sm text-slate-400">No members found</div>';
    } else {
      box.innerHTML = d.data.map(m => `
        <div class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 cursor-pointer"
             onclick='selectMember(${JSON.stringify(m).replace(/"/g,"&quot;")})'>
          <div class="avatar-circle w-8 h-8 text-xs">${m.initials}</div>
          <div>
            <div class="text-sm font-semibold">${m.full_name}</div>
            <div class="text-xs text-slate-400">${m.member_no} · ${m.total_savings_fmt}</div>
          </div>
          ${m.is_shareholder ? '<span class="ml-auto text-xs font-bold text-amber-600"><i class="fa-solid fa-star"></i> Shareholder</span>' : ''}
        </div>`).join('');
    }
    box.classList.remove('hidden');
  }, 300);
}

function selectMember(m) {
  document.getElementById('share_member_id').value = m.id;
  document.getElementById('shareMemberSearch').value = m.full_name;
  document.getElementById('shareMemberResults').classList.add('hidden');
  document.getElementById('smAvatar').textContent   = m.initials;
  document.getElementById('smName').textContent     = m.full_name;
  document.getElementById('smNo').textContent       = m.member_no;
  document.getElementById('smSavings').textContent  = m.total_savings_fmt;
  document.getElementById('smShares').textContent   = m.shares_held ?? '0';
  document.getElementById('smHolder').innerHTML     = m.is_shareholder
    ? '<span class="text-amber-600"><i class="fa-solid fa-star"></i> Yes</span>'
    : '<span class="text-slate-400">No</span>';
  document.getElementById('shareMemberCard').classList.remove('hidden');
  document.getElementById('issueBtn').disabled = false;
}

document.addEventListener('click', e => {
  if (!document.getElementById('shareMemberWrap').contains(e.target))
    document.getElementById('shareMemberResults').classList.add('hidden');
});

document.getElementById('issueSharesForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  if (!document.getElementById('share_member_id').value) {
    window.showToast('error', 'Please select a member first.'); return;
  }
  await window.submitForm(this, {
    loadingText: 'Submitting…',
    onSuccess: r => {
      window.showToast('success', r.message);
      setTimeout(() => { window.location.href = r.data?.redirect || '<?= APP_URL ?>/shares'; }, 900);
    }
  });
});
</script>