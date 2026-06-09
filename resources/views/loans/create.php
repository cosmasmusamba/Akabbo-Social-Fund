<?php
use App\Helpers\Format;
$pageTitle   = 'New Loan Application';
$activePage  = 'loans';
$breadcrumbs = ['Loans' => APP_URL.'/loans', 'New Application' => null];
?>

<style>
  .calc-result {
    background: linear-gradient(135deg,#f0fdf9 0%,#ecfdf5 100%);
    border: 1px solid #a7f3d0; border-radius:14px; padding:20px;
  }
  .calc-row { display:flex; justify-content:space-between; align-items:center;
    padding:8px 0; border-bottom:1px solid rgba(0,0,0,0.05); }
  .calc-row:last-child { border-bottom:none; padding-top:12px; margin-top:4px;
    border-top:2px solid #a7f3d0; }
  .calc-label { font-size:0.82rem; color:#374151; }
  .calc-value { font-size:0.9rem; font-weight:700; color:#1e293b; }
  .calc-total .calc-value { font-size:1.1rem; color:var(--green-deep); }
  .schedule-tbl th { font-size:0.7rem; padding:8px 10px; }
  .schedule-tbl td { font-size:0.78rem; padding:7px 10px; }
  .member-search-result {
    position:absolute; top:calc(100% + 4px); left:0; right:0;
    background:#fff; border:1px solid #e2e8f0; border-radius:12px;
    box-shadow:0 8px 30px rgba(0,0,0,0.12); z-index:50; max-height:260px; overflow-y:auto;
  }
  .member-option { display:flex; align-items:center; gap:10px; padding:10px 14px;
    cursor:pointer; transition:background 0.15s; }
  .member-option:hover { background:#f8fafc; }
  .member-option:first-child { border-radius:12px 12px 0 0; }
  .member-option:last-child  { border-radius:0 0 12px 12px; }
  .section-tab { padding:8px 16px; border-radius:8px; font-size:0.82rem; font-weight:600;
    cursor:pointer; border:none; background:transparent; color:#94a3b8; transition:all 0.2s; }
  .section-tab.active { background:var(--green-mid); color:#fff; }
</style>

<div class="max-w-5xl mx-auto">

  <div class="flex items-center justify-between mb-5">
    <div>
      <h1 class="text-xl font-bold text-slate-800">New Loan Application</h1>
      <p class="text-sm text-slate-400 mt-0.5">Fill in applicant details and loan parameters</p>
    </div>
    <a href="<?= APP_URL ?>/loans" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-arrow-left"></i> Back
    </a>
  </div>

  <form id="loanForm" method="POST" action="<?= APP_URL ?>/loans/store" novalidate>
    <?= $csrfField ?>

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-5">

      <!-- ── Left: Form ───────────────────────────────────────── -->
      <div class="xl:col-span-3 space-y-5">

        <!-- Member search -->
        <div class="card">
          <div class="card-header">
            <h3 class="section-title"><i class="fa-solid fa-user-tie" style="color:var(--green-mid)"></i> Applicant</h3>
          </div>
          <div class="card-body">
            <div class="relative" id="memberSearchWrap">
              <label class="form-label required" for="memberSearch">Search Member</label>
              <div class="relative">
                <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" id="memberSearch" autocomplete="off"
                  class="form-control pl-9" placeholder="Type name, phone or member number…"
                  oninput="searchMember(this.value)">
                <div id="memberResults" class="member-search-result hidden"></div>
              </div>
              <input type="hidden" id="member_id" name="member_id" required>
            </div>

            <!-- Member card (shown after selection) -->
            <div id="memberCard" class="hidden mt-4 p-4 rounded-xl border border-slate-200 bg-slate-50">
              <div class="flex items-center gap-3">
                <div class="avatar-circle w-12 h-12" id="mcAvatar">?</div>
                <div class="flex-1">
                  <div class="font-bold text-slate-800" id="mcName">—</div>
                  <div class="text-xs text-slate-500 flex gap-3 mt-0.5">
                    <span id="mcNo"></span>
                    <span id="mcPhone"></span>
                  </div>
                </div>
                <button type="button" onclick="clearMember()" class="text-slate-400 hover:text-red-500 transition-colors">
                  <i class="fa-solid fa-xmark"></i>
                </button>
              </div>
              <div class="grid grid-cols-3 gap-3 mt-3 pt-3 border-t border-slate-200">
                <div class="text-center">
                  <div class="text-xs text-slate-400">Total Savings</div>
                  <div class="font-bold text-emerald-700 text-sm" id="mcSavings">—</div>
                </div>
                <div class="text-center">
                  <div class="text-xs text-slate-400">Max Eligible</div>
                  <div class="font-bold text-blue-700 text-sm" id="mcMaxLoan">—</div>
                </div>
                <div class="text-center">
                  <div class="text-xs text-slate-400">Active Loans</div>
                  <div class="font-bold text-slate-700 text-sm" id="mcActiveLoans">—</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Loan product & parameters -->
        <div class="card">
          <div class="card-header">
            <h3 class="section-title"><i class="fa-solid fa-sliders" style="color:var(--green-mid)"></i> Loan Parameters</h3>
          </div>
          <div class="card-body space-y-4">

            <!-- Product -->
            <div>
              <label class="form-label required" for="loan_product_id">Loan Product</label>
              <select id="loan_product_id" name="loan_product_id" class="form-control form-select" required onchange="onProductChange(this)">
                <option value="">— Select loan product —</option>
                <?php foreach ($loanProducts ?? [] as $lp): ?>
                  <option value="<?= $lp['id'] ?>"
                    data-min="<?= $lp['min_amount'] ?>"
                    data-max="<?= $lp['max_amount'] ?>"
                    data-rate="<?= $lp['interest_rate'] ?>"
                    data-type="<?= $lp['interest_type'] ?>"
                    data-min-term="<?= $lp['min_term_months'] ?>"
                    data-max-term="<?= $lp['max_term_months'] ?>"
                    data-fee="<?= $lp['processing_fee_pct'] ?>"
                    data-guarantor="<?= $lp['requires_guarantor'] ?>"
                    <?= ($_POST['loan_product_id'] ?? '') == $lp['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($lp['name']) ?> — <?= Format::percentage((float)$lp['interest_rate']) ?> p.a.
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Product info banner -->
            <div id="productInfo" class="hidden p-3 rounded-xl text-xs" style="background:#f0fdf9;border:1px solid #a7f3d0;color:#065f46;">
              <div class="grid grid-cols-3 gap-2 text-center">
                <div><div class="opacity-60">Min Amount</div><div class="font-bold" id="piMin">—</div></div>
                <div><div class="opacity-60">Max Amount</div><div class="font-bold" id="piMax">—</div></div>
                <div><div class="opacity-60">Interest</div><div class="font-bold" id="piRate">—</div></div>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <!-- Principal -->
              <div>
                <label class="form-label required" for="principal_amount">Loan Amount (<?= $settings['currency_symbol'] ?? 'USh' ?>)</label>
                <input type="number" id="principal_amount" name="principal_amount"
                  value="<?= htmlspecialchars($_POST['principal_amount'] ?? '') ?>"
                  class="form-control" placeholder="0" min="0" step="1000" required
                  oninput="triggerCalculation()">
                <p class="field-hint" id="amountRange">Enter the requested amount</p>
              </div>

              <!-- Term -->
              <div>
                <label class="form-label required" for="term_months">Loan Term (Months)</label>
                <input type="number" id="term_months" name="term_months"
                  value="<?= htmlspecialchars($_POST['term_months'] ?? '') ?>"
                  class="form-control" placeholder="e.g. 12" min="1" max="36" required
                  oninput="triggerCalculation()">
                <p class="field-hint" id="termRange">Number of repayment months</p>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <!-- Interest rate (read-only, from product) -->
              <div>
                <label class="form-label" for="interest_rate">Interest Rate (% p.a.)</label>
                <input type="number" id="interest_rate" name="interest_rate"
                  value="<?= htmlspecialchars($_POST['interest_rate'] ?? '') ?>"
                  class="form-control bg-slate-50" step="0.01" min="0" readonly>
                <p class="field-hint">Set by loan product</p>
              </div>

              <!-- Interest type -->
              <div>
                <label class="form-label" for="interest_type">Interest Type</label>
                <input type="text" id="interest_type_display" class="form-control bg-slate-50" readonly placeholder="—">
                <input type="hidden" id="interest_type" name="interest_type" value="">
              </div>
            </div>

            <!-- First repayment date -->
            <div>
              <label class="form-label required" for="first_repayment_date">First Repayment Date</label>
              <input type="date" id="first_repayment_date" name="first_repayment_date"
                value="<?= htmlspecialchars($_POST['first_repayment_date'] ?? date('Y-m-d', strtotime('+1 month'))) ?>"
                min="<?= date('Y-m-d', strtotime('+7 days')) ?>"
                class="form-control" required>
            </div>

            <!-- Purpose -->
            <div>
              <label class="form-label" for="purpose">Loan Purpose</label>
              <textarea id="purpose" name="purpose" rows="2"
                class="form-control" placeholder="Describe what the loan will be used for…"><?= htmlspecialchars($_POST['purpose'] ?? '') ?></textarea>
            </div>

            <!-- Disbursement method -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="form-label required" for="disbursement_method">Disbursement Method</label>
                <select id="disbursement_method" name="disbursement_method" class="form-control form-select" required onchange="toggleDisbAccount(this.value)">
                  <option value="cash">Cash</option>
                  <option value="mobile_money">Mobile Money</option>
                  <option value="bank_transfer">Bank Transfer</option>
                </select>
              </div>
              <div id="disbAccountRow">
                <label class="form-label" for="disbursement_account">Account / Mobile Number</label>
                <input type="text" id="disbursement_account" name="disbursement_account"
                  class="form-control" placeholder="e.g. 0700 000000">
              </div>
            </div>

          </div>
        </div>

        <!-- Guarantors section (if required) -->
        <div class="card" id="guarantorSection" style="display:none;">
          <div class="card-header">
            <h3 class="section-title"><i class="fa-solid fa-handshake" style="color:var(--green-mid)"></i> Guarantors</h3>
            <button type="button" onclick="addGuarantor()" class="btn btn-secondary btn-sm">
              <i class="fa-solid fa-plus"></i> Add Guarantor
            </button>
          </div>
          <div class="card-body">
            <div id="guarantorsList" class="space-y-3">
              <!-- Guarantor rows added dynamically -->
            </div>
            <p class="text-xs text-slate-400 mt-2">At least one guarantor is required for this loan product.</p>
          </div>
        </div>

        <!-- Notes -->
        <div class="card">
          <div class="card-header">
            <h3 class="section-title"><i class="fa-solid fa-note-sticky" style="color:var(--green-mid)"></i> Officer Notes</h3>
          </div>
          <div class="card-body">
            <textarea name="officer_notes" rows="2" class="form-control"
              placeholder="Internal notes visible to officers only (optional)…"></textarea>
          </div>
        </div>

        <!-- Submit -->
        <div class="flex gap-3 justify-end">
          <button type="button" onclick="saveDraft()" class="btn btn-secondary">
            <i class="fa-regular fa-floppy-disk"></i> Save as Draft
          </button>
          <button type="submit" class="btn btn-primary" id="submitLoanBtn">
            <i class="fa-solid fa-paper-plane"></i> Submit Application
          </button>
        </div>
      </div>

      <!-- ── Right: Calculator ──────────────────────────────────── -->
      <div class="xl:col-span-2">
        <div class="sticky top-20 space-y-4">

          <!-- Live calculator -->
          <div class="card">
            <div class="card-header">
              <h3 class="section-title"><i class="fa-solid fa-calculator" style="color:var(--green-mid)"></i> Loan Calculator</h3>
            </div>
            <div class="card-body">
              <div id="calcEmpty" class="text-center py-6 text-slate-400 text-sm">
                <i class="fa-solid fa-calculator text-3xl text-slate-200 mb-2 block"></i>
                Select a product and enter amount to calculate
              </div>
              <div id="calcResults" class="hidden">
                <div class="calc-result mb-4">
                  <div class="calc-row"><span class="calc-label">Principal</span><span class="calc-value" id="cr_principal">—</span></div>
                  <div class="calc-row"><span class="calc-label">Processing Fee</span><span class="calc-value" id="cr_fee">—</span></div>
                  <div class="calc-row"><span class="calc-label">Total Interest</span><span class="calc-value text-amber-600" id="cr_interest">—</span></div>
                  <div class="calc-row calc-total">
                    <span class="calc-label font-bold text-slate-800">Total Payable</span>
                    <span class="calc-value" id="cr_total">—</span>
                  </div>
                </div>
                <div class="flex items-center justify-between mb-3">
                  <div class="text-center">
                    <div class="text-xs text-slate-400">Monthly Installment</div>
                    <div class="text-xl font-bold" style="color:var(--green-mid)" id="cr_monthly">—</div>
                  </div>
                  <div class="text-center">
                    <div class="text-xs text-slate-400">Term</div>
                    <div class="text-xl font-bold text-slate-800" id="cr_term">—</div>
                  </div>
                  <div class="text-center">
                    <div class="text-xs text-slate-400">Int. Rate</div>
                    <div class="text-xl font-bold text-amber-600" id="cr_rate">—</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Repayment schedule preview -->
          <div class="card" id="scheduleCard" style="display:none;">
            <div class="card-header">
              <h3 class="section-title text-sm"><i class="fa-solid fa-calendar-days" style="color:var(--green-mid)"></i> Repayment Schedule</h3>
              <span class="text-xs text-slate-400" id="scheduleCount"></span>
            </div>
            <div class="overflow-x-auto max-h-72 overflow-y-auto">
              <table class="data-table schedule-tbl w-full text-xs">
                <thead class="sticky top-0">
                  <tr>
                    <th>#</th>
                    <th>Due Date</th>
                    <th>Principal</th>
                    <th>Interest</th>
                    <th>Total</th>
                  </tr>
                </thead>
                <tbody id="scheduleBody"></tbody>
              </table>
            </div>
          </div>

        </div>
      </div>

    </div>
  </form>
</div>

<script>
const CURRENCY = '<?= $settings['currency_symbol'] ?? 'USh' ?>';
let calcDebounce;
let selectedMember = null;

// ── Format number ────────────────────────────────────────────────
function fmt(n) {
  return CURRENCY + ' ' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

// ── Product selection ────────────────────────────────────────────
function onProductChange(sel) {
  const opt = sel.options[sel.selectedIndex];
  if (!opt.value) {
    document.getElementById('productInfo').classList.add('hidden');
    document.getElementById('interest_rate').value = '';
    document.getElementById('interest_type').value = '';
    document.getElementById('interest_type_display').value = '';
    return;
  }

  document.getElementById('productInfo').classList.remove('hidden');
  document.getElementById('piMin').textContent = fmt(opt.dataset.min);
  document.getElementById('piMax').textContent = fmt(opt.dataset.max);
  document.getElementById('piRate').textContent = opt.dataset.rate + '%';
  document.getElementById('amountRange').textContent = `Min: ${fmt(opt.dataset.min)} — Max: ${fmt(opt.dataset.max)}`;
  document.getElementById('termRange').textContent = `${opt.dataset.minTerm}–${opt.dataset.maxTerm} months`;
  document.getElementById('interest_rate').value = opt.dataset.rate;
  document.getElementById('interest_type').value = opt.dataset.type;
  document.getElementById('interest_type_display').value = opt.dataset.type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());

  // Principal constraints
  document.getElementById('principal_amount').min = opt.dataset.min;
  document.getElementById('principal_amount').max = opt.dataset.max;
  document.getElementById('term_months').min = opt.dataset.minTerm;
  document.getElementById('term_months').max = opt.dataset.maxTerm;

  // Show guarantor if required
  document.getElementById('guarantorSection').style.display = opt.dataset.guarantor === '1' ? 'block' : 'none';

  triggerCalculation();
}

// ── Calculation trigger with debounce ────────────────────────────
function triggerCalculation() {
  clearTimeout(calcDebounce);
  calcDebounce = setTimeout(calculate, 350);
}

// ── Loan calculation ─────────────────────────────────────────────
function calculate() {
  const principal = parseFloat(document.getElementById('principal_amount').value) || 0;
  const rate      = parseFloat(document.getElementById('interest_rate').value) || 0;
  const term      = parseInt(document.getElementById('term_months').value) || 0;
  const type      = document.getElementById('interest_type').value;
  const firstDate = document.getElementById('first_repayment_date').value;
  const prodSel   = document.getElementById('loan_product_id');
  const opt       = prodSel.options[prodSel.selectedIndex];
  const feePct    = opt && opt.dataset.fee ? parseFloat(opt.dataset.fee) : 0;

  if (!principal || !rate || !term || !type) {
    document.getElementById('calcEmpty').classList.remove('hidden');
    document.getElementById('calcResults').classList.add('hidden');
    document.getElementById('scheduleCard').style.display = 'none';
    return;
  }

  const monthlyRate = rate / 100 / 12;
  let totalInterest, totalPayable, monthly;

  if (type === 'flat') {
    totalInterest = principal * (rate / 100) * (term / 12);
    totalPayable  = principal + totalInterest;
    monthly       = totalPayable / term;
  } else if (type === 'reducing_balance') {
    if (monthlyRate === 0) {
      monthly = principal / term;
    } else {
      const f = Math.pow(1 + monthlyRate, term);
      monthly = principal * (monthlyRate * f) / (f - 1);
    }
    totalPayable  = monthly * term;
    totalInterest = totalPayable - principal;
  } else {
    totalPayable  = principal * Math.pow(1 + monthlyRate, term);
    totalInterest = totalPayable - principal;
    monthly       = totalPayable / term;
  }

  const processingFee = principal * feePct / 100;

  // Update calculator UI
  document.getElementById('calcEmpty').classList.add('hidden');
  document.getElementById('calcResults').classList.remove('hidden');
  document.getElementById('cr_principal').textContent = fmt(principal);
  document.getElementById('cr_fee').textContent       = fmt(processingFee);
  document.getElementById('cr_interest').textContent  = fmt(totalInterest);
  document.getElementById('cr_total').textContent     = fmt(totalPayable + processingFee);
  document.getElementById('cr_monthly').textContent   = fmt(monthly);
  document.getElementById('cr_term').textContent      = term + ' mo';
  document.getElementById('cr_rate').textContent      = rate + '%';

  // Generate schedule
  generateSchedule(principal, rate, term, type, monthly, firstDate);
}

// ── Schedule generation ──────────────────────────────────────────
function generateSchedule(principal, rate, term, type, monthly, firstDate) {
  const monthlyRate = rate / 100 / 12;
  let balance = principal;
  let rows = '';
  let d = firstDate ? new Date(firstDate) : new Date();

  for (let i = 1; i <= term; i++) {
    let interest, princ;
    if (type === 'flat') {
      interest = principal * (rate / 100) / 12;
      princ    = principal / term;
    } else {
      interest = balance * monthlyRate;
      princ    = monthly - interest;
      if (i === term) princ = balance;
    }

    balance -= princ;
    const total = princ + interest;
    const dueDate = d.toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'2-digit' });

    rows += `<tr>
      <td class="text-slate-500">${i}</td>
      <td class="font-medium">${dueDate}</td>
      <td>${fmt(Math.max(0, princ))}</td>
      <td class="text-amber-600">${fmt(Math.max(0, interest))}</td>
      <td class="font-bold">${fmt(total)}</td>
    </tr>`;

    d.setMonth(d.getMonth() + 1);
  }

  document.getElementById('scheduleBody').innerHTML = rows;
  document.getElementById('scheduleCount').textContent = term + ' installments';
  document.getElementById('scheduleCard').style.display = 'block';
}

// ── Member search ────────────────────────────────────────────────
let searchDebounce;
function searchMember(val) {
  clearTimeout(searchDebounce);
  if (val.length < 2) {
    document.getElementById('memberResults').classList.add('hidden');
    return;
  }
  searchDebounce = setTimeout(async () => {
    try {
      const res  = await fetch(`<?= APP_URL ?>/members/search?q=${encodeURIComponent(val)}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const data = await res.json();
      renderMemberResults(data.data || []);
    } catch(e) {}
  }, 300);
}

function renderMemberResults(members) {
  const box = document.getElementById('memberResults');
  if (!members.length) {
    box.innerHTML = '<div class="px-4 py-3 text-sm text-slate-400">No members found</div>';
  } else {
    box.innerHTML = members.map(m => `
      <div class="member-option" onclick="selectMember(${JSON.stringify(m).replace(/"/g,'&quot;')})">
        <div class="avatar-circle w-8 h-8 text-xs">${m.initials}</div>
        <div class="flex-1">
          <div class="text-sm font-semibold text-slate-800">${m.full_name}</div>
          <div class="text-xs text-slate-400">${m.member_no} · ${m.phone}</div>
        </div>
        <span class="text-xs font-bold text-emerald-700">${m.total_savings_fmt}</span>
      </div>
    `).join('');
  }
  box.classList.remove('hidden');
}

function selectMember(m) {
  selectedMember = m;
  document.getElementById('member_id').value = m.id;
  document.getElementById('memberSearch').value = m.full_name;
  document.getElementById('memberResults').classList.add('hidden');

  const mc = document.getElementById('memberCard');
  document.getElementById('mcAvatar').textContent    = m.initials;
  document.getElementById('mcName').textContent      = m.full_name;
  document.getElementById('mcNo').textContent        = m.member_no;
  document.getElementById('mcPhone').textContent     = m.phone;
  document.getElementById('mcSavings').textContent   = m.total_savings_fmt;
  document.getElementById('mcMaxLoan').textContent   = m.max_loan_fmt;
  document.getElementById('mcActiveLoans').textContent = m.active_loans + ' active';
  mc.classList.remove('hidden');
}

function clearMember() {
  selectedMember = null;
  document.getElementById('member_id').value = '';
  document.getElementById('memberSearch').value = '';
  document.getElementById('memberCard').classList.add('hidden');
}

document.addEventListener('click', e => {
  if (!document.getElementById('memberSearchWrap').contains(e.target)) {
    document.getElementById('memberResults').classList.add('hidden');
  }
});

// ── Disbursement account toggle ───────────────────────────────────
function toggleDisbAccount(val) {
  document.getElementById('disbAccountRow').style.display = val === 'cash' ? 'none' : 'block';
}

// ── Guarantor rows ────────────────────────────────────────────────
let gCount = 0;
function addGuarantor() {
  gCount++;
  const row = document.createElement('div');
  row.className = 'flex items-center gap-2';
  row.id = 'g_' + gCount;
  row.innerHTML = `
    <div class="flex-1 relative">
      <input type="text" name="guarantor_name[]" placeholder="Guarantor member name or number"
        class="form-control text-sm" autocomplete="off">
      <input type="hidden" name="guarantor_id[]">
    </div>
    <button type="button" onclick="this.closest('div').remove()" class="btn btn-danger btn-sm py-2 px-3">
      <i class="fa-solid fa-trash text-xs"></i>
    </button>`;
  document.getElementById('guarantorsList').appendChild(row);
}

// ── Save draft ────────────────────────────────────────────────────
function saveDraft() {
  const hidden = document.createElement('input');
  hidden.type  = 'hidden'; hidden.name = 'status'; hidden.value = 'draft';
  document.getElementById('loanForm').appendChild(hidden);
  document.getElementById('loanForm').submit();
}

// ── Submit ────────────────────────────────────────────────────────
document.getElementById('loanForm').addEventListener('submit', function(e) {
  if (!document.getElementById('member_id').value) {
    e.preventDefault();
    window.showToast('error', 'Please select a member before submitting.');
    return;
  }
  const btn = document.getElementById('submitLoanBtn');
  btn.disabled = true;
  btn.innerHTML = '<svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Submitting…';
});
</script>
