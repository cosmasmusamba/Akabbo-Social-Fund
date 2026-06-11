<?php
use App\Helpers\Format;
$pageTitle   = 'New Fund Transfer';
$activePage  = 'transfers';
$breadcrumbs = ['Transfers' => APP_URL . '/transfers', 'New Transfer' => null];
?>
<div class="max-w-2xl mx-auto">
  <div class="flex items-center justify-between mb-5">
    <div>
      <h1 class="text-xl font-bold text-slate-800">New Fund Transfer</h1>
      <p class="text-sm text-slate-400 mt-0.5">Transfer savings between member accounts</p>
    </div>
    <a href="<?= APP_URL ?>/transfers" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-arrow-left"></i> Back
    </a>
  </div>

  <!-- Info banner -->
  <div class="p-4 rounded-xl mb-5" style="background:#eff6ff;border:1px solid #bfdbfe">
    <div class="flex items-start gap-3">
      <i class="fa-solid fa-circle-info text-blue-500 mt-0.5 flex-shrink-0"></i>
      <div>
        <p class="text-sm font-semibold text-blue-800 mb-1">Approval Required</p>
        <p class="text-sm text-blue-700">
          All fund transfers require approval by an authorised officer before funds move.
          The transfer will remain pending until approved. A description/reason is mandatory.
        </p>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <form id="transferForm">
        <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">

        <!-- From member -->
        <div class="mb-5">
          <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">
            <i class="fa-solid fa-circle-arrow-up text-orange-500 mr-1"></i> From Member (Sender)
          </div>
          <div class="relative" id="fromMemberWrap">
            <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
            <input type="text" id="fromSearch" autocomplete="off" class="form-control pl-9"
                   placeholder="Search sender by name, phone or member number…"
                   oninput="searchMember(this.value, 'from')">
            <div id="fromResults"
                 class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-lg z-20 max-h-56 overflow-y-auto">
            </div>
          </div>
          <input type="hidden" name="from_member_id" id="from_member_id" required>
          <div id="fromCard" class="hidden mt-3 p-4 rounded-xl border border-orange-200 bg-orange-50">
            <div class="flex items-center gap-3">
              <div class="avatar-circle w-10 h-10" id="fromAvatar" style="background:linear-gradient(135deg,#ea580c,#c2410c)">?</div>
              <div class="flex-1">
                <div class="font-bold text-slate-800" id="fromName">—</div>
                <div class="text-xs text-slate-500" id="fromNo">—</div>
              </div>
              <button type="button" onclick="clearMember('from')"
                      class="text-slate-400 hover:text-red-500 transition-colors">
                <i class="fa-solid fa-xmark"></i>
              </button>
            </div>
            <div class="mt-3 pt-3 border-t border-orange-200 flex gap-6 text-center">
              <div>
                <div class="text-xs text-slate-500">Current Balance</div>
                <div class="font-black text-emerald-700 text-lg" id="fromBalance">—</div>
              </div>
              <div>
                <div class="text-xs text-slate-500">Available</div>
                <div class="font-black text-blue-700 text-lg" id="fromAvailable">—</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Transfer arrow indicator -->
        <div class="flex items-center gap-3 mb-5">
          <div class="flex-1 h-px bg-slate-200"></div>
          <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
               style="background:linear-gradient(135deg,#136b55,#1a8f6f)">
            <i class="fa-solid fa-arrow-down text-white text-sm"></i>
          </div>
          <div class="flex-1 h-px bg-slate-200"></div>
        </div>

        <!-- To member -->
        <div class="mb-5">
          <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">
            <i class="fa-solid fa-circle-arrow-down text-emerald-500 mr-1"></i> To Member (Recipient)
          </div>
          <div class="relative" id="toMemberWrap">
            <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
            <input type="text" id="toSearch" autocomplete="off" class="form-control pl-9"
                   placeholder="Search recipient by name, phone or member number…"
                   oninput="searchMember(this.value, 'to')">
            <div id="toResults"
                 class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-lg z-20 max-h-56 overflow-y-auto">
            </div>
          </div>
          <input type="hidden" name="to_member_id" id="to_member_id" required>
          <div id="toCard" class="hidden mt-3 p-4 rounded-xl border border-emerald-200 bg-emerald-50">
            <div class="flex items-center gap-3">
              <div class="avatar-circle w-10 h-10" id="toAvatar">?</div>
              <div class="flex-1">
                <div class="font-bold text-slate-800" id="toName">—</div>
                <div class="text-xs text-slate-500" id="toNo">—</div>
              </div>
              <button type="button" onclick="clearMember('to')"
                      class="text-slate-400 hover:text-red-500 transition-colors">
                <i class="fa-solid fa-xmark"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Amount + date -->
        <div class="grid grid-cols-2 gap-4 mb-4">
          <div>
            <label class="form-label required">Amount (<?= $settings['currency_symbol'] ?? 'USh' ?>)</label>
            <input type="number" name="amount" id="transferAmount" class="form-control text-lg font-bold"
                   min="1000" step="500" placeholder="0" required oninput="validateAmount()">
            <p class="text-xs text-slate-400 mt-1" id="amountHint">Enter the amount to transfer</p>
          </div>
          <div>
            <label class="form-label required">Transfer Date</label>
            <input type="date" name="transfer_date" class="form-control"
                   value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
          </div>
        </div>

        <!-- Description (mandatory) -->
        <div class="mb-5">
          <label class="form-label required">
            Description / Reason
            <span class="text-xs text-slate-400 font-normal ml-1">(mandatory)</span>
          </label>
          <textarea name="description" rows="3" class="form-control" required
                    placeholder="State the reason for this transfer clearly. This will be reviewed by the approving officer and recorded in the audit log…"></textarea>
          <p class="text-xs text-slate-400 mt-1">
            <i class="fa-solid fa-circle-info mr-1"></i>
            The description is shown to the approver and stored permanently in the audit trail.
          </p>
        </div>

        <div class="flex gap-3 justify-end pt-4 border-t border-slate-100">
          <a href="<?= APP_URL ?>/transfers" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn btn-primary" id="submitBtn">
            <i class="fa-solid fa-paper-plane"></i> Submit for Approval
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const SYM = '<?= $settings['currency_symbol'] ?? 'USh' ?>';
function fmt(n) { return SYM + ' ' + Number(n).toLocaleString('en-US', {minimumFractionDigits:0}); }

let memberData = { from: null, to: null };
let debounce   = {};

function searchMember(val, side) {
  clearTimeout(debounce[side]);
  const results = document.getElementById(side + 'Results');
  if (val.length < 2) { results.classList.add('hidden'); return; }

  debounce[side] = setTimeout(async () => {
    try {
      const r = await fetch(`<?= APP_URL ?>/members/search?q=${encodeURIComponent(val)}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const d = await r.json();
      if (!d.data?.length) {
        results.innerHTML = '<div class="px-4 py-3 text-sm text-slate-400">No members found</div>';
      } else {
        results.innerHTML = d.data.map(m => `
          <div class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 cursor-pointer border-b border-slate-100 last:border-0"
               onclick='selectMember(${JSON.stringify(m).replace(/"/g,"&quot;")}, "${side}")'>
            <div class="avatar-circle w-8 h-8 text-xs">${m.initials}</div>
            <div class="flex-1">
              <div class="text-sm font-semibold text-slate-800">${m.full_name}</div>
              <div class="text-xs text-slate-400">${m.member_no} · ${m.phone}</div>
            </div>
            <div class="text-xs font-bold text-emerald-700">${m.total_savings_fmt}</div>
          </div>`).join('');
      }
      results.classList.remove('hidden');
    } catch(e) {}
  }, 300);
}

function selectMember(m, side) {
  memberData[side] = m;
  document.getElementById(side + '_member_id').value = m.id;
  document.getElementById(side + 'Search').value     = m.full_name;
  document.getElementById(side + 'Results').classList.add('hidden');
  document.getElementById(side + 'Name').textContent = m.full_name;
  document.getElementById(side + 'No').textContent   = m.member_no + ' · ' + m.phone;
  document.getElementById(side + 'Avatar').textContent = m.initials;
  document.getElementById(side + 'Card').classList.remove('hidden');

  if (side === 'from') {
    document.getElementById('fromBalance').textContent   = m.total_savings_fmt;
    document.getElementById('fromAvailable').textContent = m.total_savings_fmt;
  }
  validateAmount();
}

function clearMember(side) {
  memberData[side] = null;
  document.getElementById(side + '_member_id').value = '';
  document.getElementById(side + 'Search').value     = '';
  document.getElementById(side + 'Card').classList.add('hidden');
}

function validateAmount() {
  const amount = parseFloat(document.getElementById('transferAmount').value) || 0;
  const hint   = document.getElementById('amountHint');
  if (memberData.from && amount > 0) {
    const bal = memberData.from.total_savings || 0;
    if (amount > bal) {
      hint.innerHTML = `<span class="text-red-500"><i class="fa-solid fa-exclamation-circle mr-1"></i>Exceeds sender balance of ${fmt(bal)}</span>`;
    } else {
      hint.innerHTML = `<span class="text-emerald-600"><i class="fa-solid fa-check-circle mr-1"></i>Remaining after transfer: ${fmt(bal - amount)}</span>`;
    }
  } else {
    hint.textContent = 'Enter the amount to transfer';
    hint.className   = 'text-xs text-slate-400 mt-1';
  }
}

// Close dropdowns on outside click
document.addEventListener('click', e => {
  ['from','to'].forEach(side => {
    if (!document.getElementById(side + 'MemberWrap')?.contains(e.target)) {
      document.getElementById(side + 'Results')?.classList.add('hidden');
    }
  });
});

document.getElementById('transferForm').addEventListener('submit', async function(e) {
  e.preventDefault();

  if (!document.getElementById('from_member_id').value) {
    window.showToast('error', 'Please select the sender member.'); return;
  }
  if (!document.getElementById('to_member_id').value) {
    window.showToast('error', 'Please select the recipient member.'); return;
  }
  if (document.getElementById('from_member_id').value === document.getElementById('to_member_id').value) {
    window.showToast('error', 'Sender and recipient cannot be the same member.'); return;
  }

  await window.submitForm(this, {
    loadingText: 'Submitting…',
    onSuccess: r => {
      window.showToast('success', r.message);
      setTimeout(() => { window.location.href = r.data?.redirect || '<?= APP_URL ?>/transfers'; }, 900);
    }
  });
});
</script>