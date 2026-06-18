<?php
use App\Helpers\Format;

$pageTitle   = 'New Loan Product';
$activePage  = 'loan-products';
$breadcrumbs = ['Loan Products' => APP_URL . '/loan-products', 'New' => null];
$currency    = $settings['currency_symbol'] ?? 'USh';
?>

<div class="max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800">New Loan Product</h1>
            <p class="text-sm text-slate-400 mt-0.5">Configure a new loan type for members</p>
        </div>
        <a href="<?= APP_URL ?>/loan-products" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>

    <form id="productForm">
        <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <!-- Basic Info -->
            <div class="card">
                <div class="card-header">
                    <h3 class="section-title"><i class="fa-solid fa-tag mr-2" style="color:var(--green-mid)"></i>Basic Information</h3>
                </div>
                <div class="card-body space-y-4">
                    <div>
                        <label class="form-label required">Product Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Emergency Loan, Business Loan" required>
                    </div>
                    <div>
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="3" class="form-control" placeholder="Brief description shown to officers when creating loans…"></textarea>
                    </div>
                </div>
            </div>

            <!-- Interest & Terms -->
            <div class="card">
                <div class="card-header">
                    <h3 class="section-title"><i class="fa-solid fa-percent mr-2" style="color:var(--green-mid)"></i>Interest & Terms</h3>
                </div>
                <div class="card-body space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label required">Interest Rate (% p.a.)</label>
                            <input type="number" name="interest_rate" class="form-control" step="0.01" min="0" max="100" placeholder="10.00" required oninput="updatePreview()">
                        </div>
                        <div>
                            <label class="form-label required">Interest Type</label>
                            <select name="interest_type" class="form-control form-select" required onchange="updatePreview()">
                                <option value="flat">Flat Rate</option>
                                <option value="reducing_balance" selected>Reducing Balance</option>
                                <option value="compound">Compound</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label required">Min Term (months)</label>
                            <input type="number" name="min_term_months" class="form-control" value="1" min="1" max="60" required>
                        </div>
                        <div>
                            <label class="form-label required">Max Term (months)</label>
                            <input type="number" name="max_term_months" class="form-control" value="12" min="1" max="60" required>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Max Loan Multiplier</label>
                        <input type="number" name="max_loan_multiplier" class="form-control" value="3" min="1" max="10" step="1">
                        <p class="text-xs text-slate-400 mt-1">Max loan = multiplier × member's savings balance</p>
                    </div>
                </div>
            </div>

            <!-- Amount Limits -->
            <div class="card">
                <div class="card-header">
                    <h3 class="section-title"><i class="fa-solid fa-scale-balanced mr-2" style="color:var(--green-mid)"></i>Amount Limits</h3>
                </div>
                <div class="card-body space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label required">Minimum Amount (<?= htmlspecialchars($currency) ?>)</label>
                            <input type="number" name="min_amount" class="form-control" min="0" step="1000" placeholder="50,000" required oninput="updatePreview()">
                        </div>
                        <div>
                            <label class="form-label required">Maximum Amount (<?= htmlspecialchars($currency) ?>)</label>
                            <input type="number" name="max_amount" class="form-control" min="0" step="1000" placeholder="5,000,000" required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fees & Requirements -->
            <div class="card">
                <div class="card-header">
                    <h3 class="section-title"><i class="fa-solid fa-coins mr-2" style="color:var(--green-mid)"></i>Fees & Requirements</h3>
                </div>
                <div class="card-body space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Processing Fee (%)</label>
                            <input type="number" name="processing_fee_pct" class="form-control" value="0" step="0.01" min="0" max="20" placeholder="1.00">
                        </div>
                        <div>
                            <label class="form-label">Insurance Fee (%)</label>
                            <input type="number" name="insurance_fee_pct" class="form-control" value="0" step="0.01" min="0" max="10" placeholder="0.00">
                        </div>
                    </div>
                    <div class="space-y-3 pt-2">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="requires_guarantor" value="1" class="w-4 h-4 rounded" style="accent-color:var(--green-mid)">
                            <div>
                                <div class="text-sm font-semibold text-slate-700">Requires Guarantor</div>
                                <div class="text-xs text-slate-400">Applicant must provide at least one guarantor</div>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="requires_collateral" value="1" class="w-4 h-4 rounded" style="accent-color:var(--green-mid)">
                            <div>
                                <div class="text-sm font-semibold text-slate-700">Requires Collateral</div>
                                <div class="text-xs text-slate-400">Physical asset must be pledged as security</div>
                            </div>
        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick preview -->
        <div id="previewCard" class="card mt-4 hidden">
            <div class="card-header">
                <h3 class="section-title"><i class="fa-solid fa-calculator mr-2" style="color:var(--green-mid)"></i>Sample Calculation Preview</h3>
                <span class="text-xs text-slate-400">Based on minimum amount, minimum term</span>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4" id="previewContent"></div>
            </div>
        </div>

        <div class="flex gap-3 justify-end mt-5">
            <a href="<?= APP_URL ?>/loan-products" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-save"></i> Create Product
            </button>
        </div>
    </form>
</div>

<script>
const CURRENCY = '<?= htmlspecialchars($currency) ?>';
const FORM_ACTION = '<?= APP_URL ?>/loan-products/store';

function fmt(n) {
    return CURRENCY + ' ' + Number(n).toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0});
}

function updatePreview() {
    const principal = parseFloat(document.querySelector('[name=min_amount]').value) || 0;
    const rate      = parseFloat(document.querySelector('[name=interest_rate]').value) || 0;
    const term      = parseInt(document.querySelector('[name=min_term_months]').value) || 1;
    const type      = document.querySelector('[name=interest_type]').value;
    const feePct    = parseFloat(document.querySelector('[name=processing_fee_pct]').value) || 0;

    if (!principal || !rate) {
        document.getElementById('previewCard').classList.add('hidden');
        return;
    }

    const monthlyRate = rate / 100 / 12;
    let totalInterest, totalPayable, monthly;

    if (type === 'flat') {
        totalInterest = principal * (rate / 100) * (term / 12);
        totalPayable  = principal + totalInterest;
        monthly       = totalPayable / term;
    } else {
        const f       = Math.pow(1 + monthlyRate, term);
        monthly       = monthlyRate === 0 ? principal / term : principal * (monthlyRate * f) / (f - 1);
        totalPayable  = monthly * term;
        totalInterest = totalPayable - principal;
    }

    const processingFee = principal * feePct / 100;

    document.getElementById('previewCard').classList.remove('hidden');
    document.getElementById('previewContent').innerHTML = `
        <div class="text-center"><div class="text-lg font-black text-slate-800">${fmt(principal)}</div><div class="text-xs text-slate-400 mt-0.5">Principal</div></div>
        <div class="text-center"><div class="text-lg font-black text-amber-600">${fmt(totalInterest)}</div><div class="text-xs text-slate-400 mt-0.5">Total Interest</div></div>
        <div class="text-center"><div class="text-lg font-black" style="color:var(--green-mid)">${fmt(monthly)}</div><div class="text-xs text-slate-400 mt-0.5">Monthly Payment</div></div>
        <div class="text-center"><div class="text-lg font-black text-slate-800">${fmt(totalPayable + processingFee)}</div><div class="text-xs text-slate-400 mt-0.5">Total Payable + Fee</div></div>
    `;
}

document.getElementById('productForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';
    
    const formData = new FormData(this);
    try {
        const response = await fetch(FORM_ACTION, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            window.showToast('success', data.message);
            setTimeout(() => { window.location.href = data.data?.redirect || '<?= APP_URL ?>/loan-products'; }, 800);
        } else {
            window.showToast('error', data.message || 'Creation failed.');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    } catch (err) {
        window.showToast('error', 'Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});
</script>