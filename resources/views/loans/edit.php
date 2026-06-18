<?php
use App\Helpers\Format;
$pageTitle   = 'Edit: ' . ($loan['loan_no'] ?? 'Loan');
$activePage  = 'loans';
$breadcrumbs = ['Loans' => APP_URL.'/loans', $loan['loan_no'] ?? 'Edit' => null];

// Fetch existing guarantors for pre-filling
$existingGuarantors = $loan['guarantors'] ?? [];
?>
<style>
.calc-result { background: linear-gradient(135deg,#f0fdf9 0%,#ecfdf5 100%); border: 1px solid #a7f3d0; border-radius:14px; padding:20px; }
.calc-row { display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid rgba(0,0,0,0.05); }
.calc-row:last-child { border-bottom:none; padding-top:12px; margin-top:4px; border-top:2px solid #a7f3d0; }
.calc-label { font-size:0.82rem; color:#374151; }
.calc-value { font-size:0.9rem; font-weight:700; color:#1e293b; }
.calc-total .calc-value { font-size:1.1rem; color:var(--green-deep); }
.schedule-tbl th { font-size:0.7rem; padding:8px 10px; }
.schedule-tbl td { font-size:0.78rem; padding:7px 10px; }
.member-card { display:flex; align-items:center; gap:10px; padding:12px; background:#f8fafc; border-radius:12px; border:1px solid #e2e8f0; }
</style>

<div class="max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Edit Loan Application</h1>
            <p class="text-sm text-slate-400 mt-0.5"><?= htmlspecialchars($loan['loan_no'] ?? '') ?> &nbsp;·&nbsp; <?= Format::statusPill($loan['status'] ?? 'draft') ?></p>
        </div>
        <a href="<?= APP_URL ?>/loans/<?= $loan['id'] ?? 0 ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
    </div>

    <form id="loanForm" method="POST" action="<?= APP_URL ?>/loans/<?= $loan['id'] ?? 0 ?>/update" novalidate>
        <?= $csrfField ?>
        <div class="grid grid-cols-1 xl:grid-cols-5 gap-5">
            <!-- ── Left: Form ───────────────────────────────────────── -->
            <div class="xl:col-span-3 space-y-5">
                <!-- Member (Readonly) -->
                <div class="card">
                    <div class="card-header"><h3 class="section-title"><i class="fa-solid fa-user-tie" style="color:var(--green-mid)"></i> Applicant</h3></div>
                    <div class="card-body">
                        <div class="member-card">
                            <div class="avatar-circle w-12 h-12"><?= Format::initials(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')) ?></div>
                            <div class="flex-1">
                                <div class="font-bold text-slate-800"><?= htmlspecialchars(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')) ?></div>
                                <div class="text-xs text-slate-500"><?= htmlspecialchars($member['member_no'] ?? '—') ?> · <?= htmlspecialchars($member['phone'] ?? '—') ?></div>
                            </div>
                            <span class="text-xs text-slate-400 italic">Cannot be changed</span>
                        </div>
                    </div>
                </div>

                <!-- Loan Parameters -->
                <div class="card">
                    <div class="card-header"><h3 class="section-title"><i class="fa-solid fa-sliders" style="color:var(--green-mid)"></i> Loan Parameters</h3></div>
                    <div class="card-body space-y-4">
                        <!-- Product (Readonly) -->
                        <div>
                            <label class="form-label">Loan Product</label>
                            <input type="text" class="form-control bg-slate-50" value="<?= htmlspecialchars($loan['product_name'] ?? '—') ?>" readonly>
                            <input type="hidden" name="loan_product_id" value="<?= (int)($loan['loan_product_id'] ?? 0) ?>">
                            <p class="text-xs text-slate-400 mt-1">Product cannot be changed. Delete and reapply if needed.</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="form-label required" for="principal_amount">Loan Amount (<?= $settings['currency_symbol'] ?? 'USh' ?>)</label>
                                <input type="number" id="principal_amount" name="principal_amount" value="<?= (float)($loan['principal_amount'] ?? 0) ?>" class="form-control" placeholder="0" min="0" step="1000" required oninput="triggerCalculation()">
                            </div>
                            <div>
                                <label class="form-label required" for="term_months">Loan Term (Months)</label>
                                <input type="number" id="term_months" name="term_months" value="<?= (int)($loan['term_months'] ?? 0) ?>" class="form-control" placeholder="e.g. 12" min="1" max="36" required oninput="triggerCalculation()">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">Interest Rate (% p.a.)</label>
                                <input type="number" id="interest_rate" name="interest_rate" value="<?= (float)($loan['interest_rate'] ?? 0) ?>" class="form-control bg-slate-50" step="0.01" min="0" readonly>
                            </div>
                            <div>
                                <label class="form-label">Interest Type</label>
                                <input type="text" id="interest_type_display" class="form-control bg-slate-50" value="<?= Format::titleCase($loan['interest_type'] ?? '') ?>" readonly>
                                <input type="hidden" id="interest_type" name="interest_type" value="<?= htmlspecialchars($loan['interest_type'] ?? '') ?>">
                            </div>
                        </div>

                        <div>
                            <label class="form-label required" for="first_repayment_date">First Repayment Date</label>
                            <input type="date" id="first_repayment_date" name="first_repayment_date" value="<?= htmlspecialchars($loan['first_repayment_date'] ?? '') ?>" min="<?= date('Y-m-d', strtotime('+7 days')) ?>" class="form-control" required>
                        </div>

                        <div>
                            <label class="form-label" for="purpose">Loan Purpose</label>
                            <textarea id="purpose" name="purpose" rows="2" class="form-control" placeholder="Describe what the loan will be used for…"><?= htmlspecialchars($loan['purpose'] ?? '') ?></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="form-label required" for="disbursement_method">Disbursement Method</label>
                                <select id="disbursement_method" name="disbursement_method" class="form-control form-select" required onchange="toggleDisbAccount(this.value)">
                                    <?php foreach (['cash' => 'Cash', 'mobile_money' => 'Mobile Money', 'bank_transfer' => 'Bank Transfer'] as $v => $l): ?>
                                        <option value="<?= $v ?>" <?= ($loan['disbursement_method'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div id="disbAccountRow" style="<?= ($loan['disbursement_method'] ?? 'cash') === 'cash' ? 'display:none' : '' ?>">
                                <label class="form-label" for="disbursement_account">Account / Mobile Number</label>
                                <input type="text" id="disbursement_account" name="disbursement_account" value="<?= htmlspecialchars($loan['disbursement_account'] ?? '') ?>" class="form-control" placeholder="e.g. 0700 000000">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Guarantors section -->
                <div class="card" id="guarantorSection">
                    <div class="card-header">
                        <h3 class="section-title"><i class="fa-solid fa-handshake" style="color:var(--green-mid)"></i> Guarantors</h3>
                        <button type="button" onclick="addGuarantor()" class="btn btn-secondary btn-sm"><i class="fa-solid fa-plus"></i> Add Guarantor</button>
                    </div>
                    <div class="card-body">
                        <div id="guarantorsList" class="space-y-3">
                            <?php if (!empty($existingGuarantors)): ?>
                                <?php foreach ($existingGuarantors as $g): ?>
                                    <div class="flex items-center gap-2 guarantor-row">
                                        <div class="flex-1 relative">
                                            <input type="text" name="guarantor_name[]" value="<?= htmlspecialchars($g['guarantor_name'] ?? '') ?>" placeholder="Guarantor member name or number" class="form-control text-sm" autocomplete="off">
                                            <input type="hidden" name="guarantor_id[]" value="<?= (int)($g['guarantor_member_id'] ?? 0) ?>">
                                        </div>
                                        <button type="button" onclick="this.closest('.guarantor-row').remove()" class="btn btn-danger btn-sm py-2 px-3"><i class="fa-solid fa-trash text-xs"></i></button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-slate-400 mt-2">Guarantors for this loan application.</p>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex gap-3 justify-end">
                    <a href="<?= APP_URL ?>/loans/<?= $loan['id'] ?? 0 ?>" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary" id="submitLoanBtn"><i class="fa-solid fa-save"></i> Save Changes</button>
                </div>
            </div>

            <!-- ── Right: Calculator ──────────────────────────────────── -->
            <div class="xl:col-span-2">
                <div class="sticky top-20 space-y-4">
                    <div class="card">
                        <div class="card-header"><h3 class="section-title"><i class="fa-solid fa-calculator" style="color:var(--green-mid)"></i> Updated Calculation</h3></div>
                        <div class="card-body">
                            <div id="calcResults">
                                <div class="calc-result mb-4">
                                    <div class="calc-row"><span class="calc-label">Principal</span><span class="calc-value" id="cr_principal">—</span></div>
                                    <div class="calc-row"><span class="calc-label">Total Interest</span><span class="calc-value text-amber-600" id="cr_interest">—</span></div>
                                    <div class="calc-row calc-total"><span class="calc-label font-bold text-slate-800">Total Payable</span><span class="calc-value" id="cr_total">—</span></div>
                                </div>
                                <div class="flex items-center justify-between mb-3">
                                    <div class="text-center"><div class="text-xs text-slate-400">Monthly</div><div class="text-xl font-bold" style="color:var(--green-mid)" id="cr_monthly">—</div></div>
                                    <div class="text-center"><div class="text-xs text-slate-400">Term</div><div class="text-xl font-bold text-slate-800" id="cr_term">—</div></div>
                                    <div class="text-center"><div class="text-xs text-slate-400">Rate</div><div class="text-xl font-bold text-amber-600" id="cr_rate">—</div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card" id="scheduleCard">
                        <div class="card-header">
                            <h3 class="section-title text-sm"><i class="fa-solid fa-calendar-days" style="color:var(--green-mid)"></i> Repayment Schedule</h3>
                            <span class="text-xs text-slate-400" id="scheduleCount"></span>
                        </div>
                        <div class="overflow-x-auto max-h-72 overflow-y-auto">
                            <table class="data-table schedule-tbl w-full text-xs">
                                <thead class="sticky top-0"><tr><th>#</th><th>Due Date</th><th>Principal</th><th>Interest</th><th>Total</th></tr></thead>
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

function fmt(n) { return CURRENCY + ' ' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }); }
function triggerCalculation() { clearTimeout(calcDebounce); calcDebounce = setTimeout(calculate, 350); }

function calculate() {
    const principal = parseFloat(document.getElementById('principal_amount').value) || 0;
    const rate      = parseFloat(document.getElementById('interest_rate').value) || 0;
    const term      = parseInt(document.getElementById('term_months').value) || 0;
    const type      = document.getElementById('interest_type').value;
    const firstDate = document.getElementById('first_repayment_date').value;

    if (!principal || !rate || !term || !type) return;

    const monthlyRate = rate / 100 / 12;
    let totalInterest, totalPayable, monthly;

    if (type === 'flat') {
        totalInterest = principal * (rate / 100) * (term / 12);
        totalPayable  = principal + totalInterest;
        monthly       = totalPayable / term;
    } else if (type === 'reducing_balance') {
        if (monthlyRate === 0) { monthly = principal / term; }
        else {
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

    document.getElementById('cr_principal').textContent = fmt(principal);
    document.getElementById('cr_interest').textContent  = fmt(totalInterest);
    document.getElementById('cr_total').textContent     = fmt(totalPayable);
    document.getElementById('cr_monthly').textContent   = fmt(monthly);
    document.getElementById('cr_term').textContent      = term + ' mo';
    document.getElementById('cr_rate').textContent      = rate + '%';

    generateSchedule(principal, rate, term, type, monthly, firstDate);
}

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
        rows += `<tr><td class="text-slate-500">${i}</td><td class="font-medium">${dueDate}</td><td>${fmt(Math.max(0, princ))}</td><td class="text-amber-600">${fmt(Math.max(0, interest))}</td><td class="font-bold">${fmt(total)}</td></tr>`;
        d.setMonth(d.getMonth() + 1);
    }
    document.getElementById('scheduleBody').innerHTML = rows;
    document.getElementById('scheduleCount').textContent = term + ' installments';
}

function toggleDisbAccount(val) {
    document.getElementById('disbAccountRow').style.display = val === 'cash' ? 'none' : 'block';
}

function addGuarantor() {
    const row = document.createElement('div');
    row.className = 'flex items-center gap-2 guarantor-row';
    row.innerHTML = `
        <div class="flex-1 relative">
            <input type="text" name="guarantor_name[]" placeholder="Guarantor member name or number" class="form-control text-sm" autocomplete="off">
            <input type="hidden" name="guarantor_id[]" value="">
        </div>
        <button type="button" onclick="this.closest('.guarantor-row').remove()" class="btn btn-danger btn-sm py-2 px-3"><i class="fa-solid fa-trash text-xs"></i></button>`;
    document.getElementById('guarantorsList').appendChild(row);
}

// Initialize calculator on load
document.addEventListener('DOMContentLoaded', calculate);

// Form submission
document.getElementById('loanForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('submitLoanBtn');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<svg class="animate-spin w-4 h-4 inline mr-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Saving…';

    try {
        const formData = new FormData(this);
        const response = await fetch(this.action, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
        const data = await response.json();
        if (data.success) {
            window.showToast('success', data.message);
            setTimeout(() => { window.location.href = data.data?.redirect || '<?= APP_URL ?>/loans/<?= $loan['id'] ?? 0 ?>'; }, 800);
        } else {
            window.showToast('error', data.message);
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    } catch (err) {
        window.showToast('error', 'Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
});
</script>