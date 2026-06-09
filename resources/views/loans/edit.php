<?php
use App\Helpers\Format;
$pageTitle   = 'Edit ' . $loan['loan_no'];
$activePage  = 'loans';
?>
<style>
.calc-box{background:linear-gradient(135deg,#f0fdf9,#ecfdf5);border:1px solid #a7f3d0;border-radius:14px;padding:18px;}
.calc-row{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(0,0,0,0.05);}
.calc-row:last-child{border:none;padding-top:10px;border-top:2px solid #a7f3d0;margin-top:4px;}
</style>

<div class="max-w-4xl mx-auto">
  <div class="flex items-center justify-between mb-5">
    <div>
      <h1 class="text-xl font-bold text-slate-800">Edit Loan Application</h1>
      <p class="text-sm text-slate-400 mt-0.5">
        <?= htmlspecialchars($loan['loan_no']) ?> &nbsp;·&nbsp;
        <?= Format::statusPill($loan['status']) ?>
        <span class="ml-2 text-amber-600 text-xs font-semibold">
          <i class="fa-solid fa-triangle-exclamation"></i>
          Only pending/draft loans can be edited
        </span>
      </p>
    </div>
    <a href="<?= APP_URL ?>/loans/<?= $loan['id'] ?>" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-arrow-left"></i> Back
    </a>
  </div>

  <div class="grid grid-cols-1 xl:grid-cols-5 gap-5">

    <!-- Form -->
    <div class="xl:col-span-3">
      <form id="editLoanForm">
        <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">

        <!-- Member (read-only) -->
        <div class="card mb-4">
          <div class="card-header">
            <h3 class="section-title"><i class="fa-solid fa-user-tie mr-2" style="color:var(--green-mid)"></i>Applicant</h3>
          </div>
          <div class="card-body">
            <div class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 border border-slate-200">
              <div class="avatar-circle w-10 h-10">
                <?= Format::initials($member['first_name'] . ' ' . $member['last_name']) ?>
              </div>
              <div class="flex-1">
                <div class="font-bold text-slate-800">
                  <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?>
                </div>
                <div class="text-xs text-slate-400">
                  <?= htmlspecialchars($member['member_no']) ?> ·
                  <?= htmlspecialchars($member['phone']) ?>
                </div>
              </div>
              <span class="text-xs text-slate-400 italic">Cannot be changed</span>
            </div>
          </div>
        </div>

        <!-- Loan Parameters -->
        <div class="card mb-4">
          <div class="card-header">
            <h3 class="section-title"><i class="fa-solid fa-sliders mr-2" style="color:var(--green-mid)"></i>Loan Parameters</h3>
          </div>
          <div class="card-body space-y-4">

            <!-- Product (read-only) -->
            <div>
              <label class="form-label">Loan Product</label>
              <input type="text" class="form-control bg-slate-50"
                     value="<?= htmlspecialchars(array_column($loanProducts, 'name', 'id')[$loan['loan_product_id']] ?? '—') ?>"
                     readonly>
              <p class="text-xs text-slate-400 mt-1">Product cannot be changed — delete and reapply if needed</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="form-label required">Principal Amount
                  (<?= $settings['currency_symbol'] ?? 'USh' ?>)
                </label>
                <?php
                $prod = null;
                foreach ($loanProducts as $lp) {
                    if ($lp['id'] == $loan['loan_product_id']) { $prod = $lp; break; }
                }
                ?>
                <input type="number" name="principal_amount" id="principal"
                       value="<?= $loan['principal_amount'] ?>"
                       min="<?= $prod['min_amount'] ?? 0 ?>"
                       max="<?= $prod['max_amount'] ?? 999999999 ?>"
                       step="1000" class="form-control" required
                       oninput="recalculate()">
                <?php if ($prod): ?>
                <p class="text-xs text-slate-400 mt-1">
                  Min: <?= Format::currency((float)$prod['min_amount']) ?> ·
                  Max: <?= Format::currency((float)$prod['max_amount']) ?>
                </p>
                <?php endif; ?>
              </div>

              <div>
                <label class="form-label required">Term (months)</label>
                <input type="number" name="term_months" id="term"
                       value="<?= $loan['term_months'] ?>"
                       min="<?= $prod['min_term_months'] ?? 1 ?>"
                       max="<?= $prod['max_term_months'] ?? 60 ?>"
                       class="form-control" required
                       oninput="recalculate()">
                <?php if ($prod): ?>
                <p class="text-xs text-slate-400 mt-1">
                  <?= $prod['min_term_months'] ?>–<?= $prod['max_term_months'] ?> months
                </p>
                <?php endif; ?>
              </div>
            </div>

            <div>
              <label class="form-label required">First Repayment Date</label>
              <input type="date" name="first_repayment_date"
                     value="<?= htmlspecialchars($loan['first_repayment_date'] ?? '') ?>"
                     min="<?= date('Y-m-d', strtotime('+7 days')) ?>"
                     class="form-control" required>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="form-label required">Disbursement Method</label>
                <select name="disbursement_method" class="form-control form-select"
                        onchange="toggleDisbAccount(this.value)" required>
                  <?php foreach (['cash' => 'Cash', 'mobile_money' => 'Mobile Money', 'bank_transfer' => 'Bank Transfer'] as $v => $l): ?>
                  <option value="<?= $v ?>" <?= ($loan['disbursement_method'] ?? '') === $v ? 'selected' : '' ?>>
                    <?= $l ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div id="disbAccountRow" style="<?= ($loan['disbursement_method'] ?? 'cash') === 'cash' ? 'display:none' : '' ?>">
                <label class="form-label">Account / Phone No.</label>
                <input type="text" name="disbursement_account"
                       value="<?= htmlspecialchars($loan['disbursement_account'] ?? '') ?>"
                       class="form-control" placeholder="e.g. 0700 000000">
              </div>
            </div>

            <div>
              <label class="form-label">Purpose</label>
              <textarea name="purpose" rows="2" class="form-control"
                        placeholder="What will this loan be used for?"><?= htmlspecialchars($loan['purpose'] ?? '') ?></textarea>
            </div>
          </div>
        </div>

        <!-- Warning -->
        <div class="p-4 rounded-xl mb-4" style="background:#fffbeb;border:1px solid #fde68a">
          <p class="text-sm text-amber-700">
            <i class="fa-solid fa-triangle-exclamation mr-1.5"></i>
            Saving will regenerate the repayment schedule based on the new parameters.
            Previous schedule will be replaced.
          </p>
        </div>

        <div class="flex gap-3 justify-end">
          <a href="<?= APP_URL ?>/loans/<?= $loan['id'] ?>" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn btn-primary" id="saveBtn">
            <i class="fa-solid fa-save"></i> Save Changes
          </button>
        </div>
      </form>
    </div>

    <!-- Calculator panel -->
    <div class="xl:col-span-2">
      <div class="sticky top-20">
        <div class="card">
          <div class="card-header">
            <h3 class="section-title text-sm">
              <i class="fa-solid fa-calculator mr-1.5" style="color:var(--green-mid)"></i>
              Updated Calculation
            </h3>
          </div>
          <div class="card-body">
            <div class="calc-box mb-4" id="calcResult">
              <p class="text-sm text-slate-400 text-center py-4">
                Adjust the amount or term to see updated figures
              </p>
            </div>

            <!-- Current vs new comparison -->
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
              Current vs Updated
            </div>
            <div class="space-y-2">
              <?php
              $comparisons = [
                ['Principal',   Format::currency((float)$loan['principal_amount']), 'cr_principal'],
                ['Total Interest', Format::currency((float)$loan['total_interest']), 'cr_interest'],
                ['Monthly',     Format::currency((float)$loan['monthly_installment']), 'cr_monthly'],
                ['Total Payable', Format::currency((float)$loan['total_payable']), 'cr_total'],
              ];
              foreach ($comparisons as [$label, $current, $newId]):
              ?>
              <div class="flex justify-between items-center text-sm py-1.5 border-b border-slate-50">
                <span class="text-slate-500"><?= $label ?></span>
                <div class="flex items-center gap-2">
                  <span class="text-slate-400 line-through text-xs"><?= $current ?></span>
                  <span class="font-bold text-slate-800" id="<?= $newId ?>">—</span>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
const RATE  = <?= (float)($prod['interest_rate'] ?? $loan['interest_rate']) ?>;
const TYPE  = '<?= $prod['interest_type'] ?? $loan['interest_type'] ?? 'reducing_balance' ?>';
const SYM   = '<?= $settings['currency_symbol'] ?? 'USh' ?>';

function fmt(n) {
  return SYM + ' ' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function recalculate() {
  const principal   = parseFloat(document.getElementById('principal').value) || 0;
  const term        = parseInt(document.getElementById('term').value) || 0;
  if (!principal || !term) return;

  const monthlyRate = RATE / 100 / 12;
  let totalInterest, totalPayable, monthly;

  if (TYPE === 'flat') {
    totalInterest = principal * (RATE / 100) * (term / 12);
    totalPayable  = principal + totalInterest;
    monthly       = totalPayable / term;
  } else {
    const f   = Math.pow(1 + monthlyRate, term);
    monthly   = monthlyRate === 0 ? principal / term : principal * (monthlyRate * f) / (f - 1);
    totalPayable  = monthly * term;
    totalInterest = totalPayable - principal;
  }

  const fee = principal * <?= (float)($prod['processing_fee_pct'] ?? 0) ?> / 100;

  document.getElementById('calcResult').innerHTML = `
    <div class="calc-row"><span class="text-xs text-slate-600">Principal</span><span class="text-sm font-bold text-slate-800">${fmt(principal)}</span></div>
    <div class="calc-row"><span class="text-xs text-slate-600">Processing Fee</span><span class="text-sm font-semibold text-amber-600">${fmt(fee)}</span></div>
    <div class="calc-row"><span class="text-xs text-slate-600">Total Interest</span><span class="text-sm font-semibold text-amber-600">${fmt(totalInterest)}</span></div>
    <div class="calc-row"><span class="text-xs font-bold text-slate-800">Total Payable</span><span class="text-base font-black" style="color:var(--green-deep)">${fmt(totalPayable + fee)}</span></div>
  `;

  document.getElementById('cr_principal').textContent = fmt(principal);
  document.getElementById('cr_interest').textContent  = fmt(totalInterest);
  document.getElementById('cr_monthly').textContent   = fmt(monthly);
  document.getElementById('cr_total').textContent     = fmt(totalPayable);
}

function toggleDisbAccount(val) {
  document.getElementById('disbAccountRow').style.display = val === 'cash' ? 'none' : 'block';
}

document.getElementById('editLoanForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  await window.submitForm(this, {
    loadingText: 'Saving…',
    onSuccess: r => {
      window.showToast('success', r.message);
      setTimeout(() => { window.location.href = r.data?.redirect || '<?= APP_URL ?>/loans/<?= $loan['id'] ?>'; }, 900);
    }
  });
});

// Init calculation on load
recalculate();
</script>
