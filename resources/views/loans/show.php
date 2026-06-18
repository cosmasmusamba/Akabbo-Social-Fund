<?php
use App\Helpers\Format;

$pageTitle   = 'Loan — ' . ($loan['loan_no'] ?? 'Unknown');
$activePage  = 'loans';
$breadcrumbs = ['Loans' => APP_URL.'/loans', $loan['loan_no'] ?? 'Details' => null];
?>

<style>
.loan-hero { background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%); border-radius: 16px; padding: 24px; color: #fff; margin-bottom: 20px; }
.timeline-item { display: flex; gap: 12px; position: relative; }
.timeline-item::before { content: ''; position: absolute; left: 15px; top: 32px; bottom: -8px; width: 2px; background: #f1f5f9; }
.timeline-item:last-child::before { display: none; }
.timeline-dot { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.75rem; }
.sched-paid { background: #f0fdf4; } 
.sched-overdue { background: #fef2f2; } 
.sched-upcoming { background: #f8fafc; }
</style>

<!-- Actions bar -->
<div class="flex items-center justify-between mb-5">
    <div class="breadcrumb">
        <a href="<?= APP_URL ?>/dashboard"><i class="fa-solid fa-house-chimney text-xs"></i></a>
        <span class="sep">/</span><a href="<?= APP_URL ?>/loans">Loans</a>
        <span class="sep">/</span><span class="current"><?= htmlspecialchars($loan['loan_no'] ?? '') ?></span>
    </div>
    <div class="flex gap-2 flex-wrap">
        <?php if (in_array($loan['status'] ?? '', ['draft', 'pending']) && $auth->can('loans.edit')): ?>
            <a href="<?= APP_URL ?>/loans/<?= $loan['id'] ?>/edit" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-pen"></i> Edit
            </a>
        <?php endif; ?>
        
        <?php if (($loan['status'] ?? '') === 'pending' && $auth->can('loans.approve')): ?>
            <button onclick="approveLoan(<?= (int)$loan['id'] ?>, '<?= addslashes($loan['loan_no'] ?? '') ?>')" class="btn btn-sm" style="background:#dcfce7;color:#166534;border:1px solid #a7f3d0;font-weight:700">
                <i class="fa-solid fa-check"></i> Approve
            </button>
            <button onclick="rejectLoan(<?= (int)$loan['id'] ?>, '<?= addslashes($loan['loan_no'] ?? '') ?>')" class="btn btn-danger btn-sm">
                <i class="fa-solid fa-xmark"></i> Reject
            </button>
        <?php endif; ?>
        
        <?php if (($loan['status'] ?? '') === 'approved' && $auth->can('loans.disburse')): ?>
            <button onclick="disburseLoan(<?= (int)$loan['id'] ?>)" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-paper-plane"></i> Disburse
            </button>
        <?php endif; ?>
        
        <?php if (in_array($loan['status'] ?? '', ['active', 'disbursed']) && $auth->can('savings.deposit')): ?>
            <button onclick="openRepaymentModal()" class="btn btn-gold btn-sm">
                <i class="fa-solid fa-circle-dollar-to-slot"></i> Record Repayment
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Hero -->
<div class="loan-hero">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="text-blue-200 text-xs font-semibold uppercase tracking-wider mb-1">
                <?= htmlspecialchars($loan['product_name'] ?? 'Unknown Product') ?>
            </div>
            <div class="text-3xl font-black"><?= htmlspecialchars($loan['loan_no'] ?? '') ?></div>
            <div class="text-blue-200 mt-1">
                <?= htmlspecialchars($loan['member_name'] ?? 'Unknown') ?> · <?= htmlspecialchars($loan['member_no'] ?? '') ?>
            </div>
        </div>
        <div class="text-right">
            <?= Format::statusPill($loan['status'] ?? 'unknown') ?>
            <div class="text-3xl font-black mt-2"><?= Format::currency((float)($loan['principal_amount'] ?? 0)) ?></div>
            <div class="text-blue-200 text-sm">
                <?= (float)($loan['interest_rate'] ?? 0) ?>% p.a. · <?= (int)($loan['term_months'] ?? 0) ?> months
            </div>
        </div>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-5 pt-5 border-t border-white/15">
        <div class="text-center">
            <div class="text-xl font-black"><?= Format::currency((float)($loan['total_payable'] ?? 0)) ?></div>
            <div class="text-blue-200 text-xs mt-0.5">Total Payable</div>
        </div>
        <div class="text-center">
            <div class="text-xl font-black text-green-300"><?= Format::currency((float)($loan['amount_paid'] ?? 0)) ?></div>
            <div class="text-blue-200 text-xs mt-0.5">Total Repaid</div>
        </div>
        <div class="text-center">
            <div class="text-xl font-black text-yellow-300"><?= Format::currency((float)($loan['balance_outstanding'] ?? 0)) ?></div>
            <div class="text-blue-200 text-xs mt-0.5">Outstanding</div>
        </div>
        <div class="text-center">
            <?php 
            $totalPayable = (float)($loan['total_payable'] ?? 1);
            $amountPaid = (float)($loan['amount_paid'] ?? 0);
            $pct = $totalPayable > 0 ? min(100, round(($amountPaid / $totalPayable) * 100)) : 0; 
            ?>
            <div class="text-xl font-black"><?= $pct ?>%</div>
            <div class="text-blue-200 text-xs mt-0.5">Repaid</div>
        </div>
    </div>
    <!-- Progress bar -->
    <div class="mt-4 h-2.5 bg-blue-900/50 rounded-full overflow-hidden">
        <div class="h-full rounded-full bg-gradient-to-r from-green-400 to-emerald-300 transition-all" style="width: <?= $pct ?>%"></div>
    </div>
</div>

<!-- Main grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <!-- Left: Details & Timeline -->
    <div class="lg:col-span-2 space-y-5">
        <!-- Loan Details -->
        <div class="card">
            <div class="card-header"><h3 class="section-title"><i class="fa-solid fa-info-circle mr-2" style="color:var(--green-mid)"></i>Loan Details</h3></div>
            <div class="card-body">
                <div class="grid grid-cols-2 gap-x-8">
                    <?php 
                    $fields = [
                        ['Principal', Format::currency((float)($loan['principal_amount'] ?? 0))],
                        ['Interest Rate', (float)($loan['interest_rate'] ?? 0) . '% p.a.'],
                        ['Interest Type', Format::titleCase($loan['interest_type'] ?? '')],
                        ['Term', (int)($loan['term_months'] ?? 0) . ' months'],
                        ['Monthly Installment', Format::currency((float)($loan['monthly_installment'] ?? 0))],
                        ['Processing Fee', Format::currency((float)($loan['processing_fee'] ?? 0))],
                        ['Total Interest', Format::currency((float)($loan['total_interest'] ?? 0))],
                        ['Total Payable', Format::currency((float)($loan['total_payable'] ?? 0))],
                        ['Purpose', $loan['purpose'] ?? '—'],
                        ['Disbursement', Format::titleCase($loan['disbursement_method'] ?? '—')],
                        ['Applied', Format::date($loan['application_date'] ?? '')],
                        ['Approved', Format::date($loan['approval_date'] ?? '')],
                        ['Disbursed', Format::date($loan['disbursement_date'] ?? '')],
                        ['Maturity', Format::date($loan['expected_maturity_date'] ?? '')],
                        ['Approved By', $loan['approved_by_name'] ?? '—'],
                        ['Created By', $loan['created_by_name'] ?? '—']
                    ];
                    foreach ($fields as [$label, $value]): 
                    ?>
                    <div class="flex justify-between py-2.5 border-b border-slate-50">
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide"><?= $label ?></span>
                        <span class="text-sm font-semibold text-slate-800 text-right"><?= htmlspecialchars($value) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Repayment history -->
        <div class="card">
            <div class="card-header"><h3 class="section-title"><i class="fa-solid fa-receipt mr-2" style="color:var(--green-mid)"></i>Payment History</h3></div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Ref</th><th>Amount</th><th>Method</th><th>Date</th><th>Recorded By</th></tr></thead>
                    <tbody>
                        <?php if (empty($repayments ?? [])): ?>
                            <tr><td colspan="5" class="text-center py-8 text-slate-400">No repayments recorded yet</td></tr>
                        <?php else: foreach ($repayments as $r): ?>
                        <tr>
                            <td><span class="font-mono text-xs"><?= htmlspecialchars($r['txn_ref'] ?? '') ?></span></td>
                            <td class="font-bold text-emerald-700"><?= Format::currency((float)($r['amount'] ?? 0)) ?></td>
                            <td class="text-xs capitalize"><?= htmlspecialchars(str_replace('_', ' ', $r['payment_method'] ?? '')) ?></td>
                            <td class="text-xs text-slate-500"><?= Format::date($r['transaction_date'] ?? '') ?></td>
                            <td class="text-xs text-slate-500"><?= htmlspecialchars($r['recorded_by_name'] ?? '—') ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Guarantors -->
        <?php if (!empty($loan['guarantors'])): ?>
        <div class="card">
            <div class="card-header"><h3 class="section-title"><i class="fa-solid fa-handshake mr-2" style="color:var(--green-mid)"></i>Guarantors</h3></div>
            <div class="divide-y divide-slate-100">
                <?php foreach ($loan['guarantors'] as $g): ?>
                <div class="flex items-center gap-3 px-5 py-3">
                    <div class="avatar-circle w-9 h-9 text-xs"><?= Format::initials($g['guarantor_name'] ?? 'U') ?></div>
                    <div class="flex-1">
                        <div class="font-semibold text-sm"><?= htmlspecialchars($g['guarantor_name'] ?? '') ?></div>
                        <div class="text-xs text-slate-400"><?= htmlspecialchars($g['member_no'] ?? '') ?> · <?= htmlspecialchars($g['phone'] ?? '') ?></div>
                    </div>
                    <?= Format::statusPill($g['status'] ?? 'pending') ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right: Repayment Schedule -->
    <div>
        <div class="card sticky top-20">
            <div class="card-header"><h3 class="section-title text-sm"><i class="fa-solid fa-calendar-days mr-2" style="color:var(--green-mid)"></i>Repayment Schedule</h3></div>
            <div class="max-h-96 overflow-y-auto">
                <?php if (empty($loan['schedule'] ?? [])): ?>
                    <div class="p-4 text-center text-slate-400 text-sm">No schedule generated yet.</div>
                <?php else: foreach ($loan['schedule'] as $s): 
                    $cls = $s['status'] === 'paid' ? 'sched-paid' : ($s['status'] === 'overdue' ? 'sched-overdue' : 'sched-upcoming');
                    $ic = $s['status'] === 'paid' ? 'fa-check text-emerald-500' : ($s['status'] === 'overdue' ? 'fa-exclamation text-red-500' : 'fa-clock text-slate-400');
                ?>
                <div class="<?= $cls ?> px-4 py-2.5 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full border border-current flex items-center justify-center flex-shrink-0" style="font-size:0.6rem">
                            <i class="fa-solid <?= $ic ?>"></i>
                        </span>
                        <div>
                            <div class="text-xs font-bold text-slate-700">#<?= (int)$s['installment_no'] ?> · <?= Format::date($s['due_date'] ?? '') ?></div>
                            <div class="text-[10px] text-slate-400">Principal: <?= Format::currency((float)($s['principal_due'] ?? 0)) ?></div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-bold <?= $s['status'] === 'overdue' ? 'text-red-600' : ($s['status'] === 'paid' ? 'text-emerald-600' : 'text-slate-700') ?>">
                            <?= Format::currency((float)($s['total_due'] ?? 0)) ?>
                        </div>
                        <?php if ((float)($s['total_paid'] ?? 0) > 0 && $s['status'] !== 'paid'): ?>
                            <div class="text-[10px] text-blue-500">Paid: <?= Format::currency((float)($s['total_paid'] ?? 0)) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Repayment Modal -->
<div id="repaymentModal" class="hidden modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3 class="font-bold flex items-center gap-2"><i class="fa-solid fa-circle-dollar-to-slot text-green-500"></i> Record Repayment</h3>
            <button onclick="closeRepaymentModal()" class="text-slate-400 hover:text-slate-700"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <div class="modal-body">
            <div class="p-3 rounded-xl mb-4 text-sm" style="background:#f0fdf9;border:1px solid #a7f3d0">
                <div class="flex justify-between">
                    <span class="text-slate-500">Outstanding Balance:</span>
                    <strong class="text-emerald-700"><?= Format::currency((float)($loan['balance_outstanding'] ?? 0)) ?></strong>
                </div>
                <div class="flex justify-between mt-1">
                    <span class="text-slate-500">Monthly Installment:</span>
                    <strong class="text-slate-800"><?= Format::currency((float)($loan['monthly_installment'] ?? 0)) ?></strong>
                </div>
            </div>
            <form id="repaymentForm" data-ajax="true">
                <input type="hidden" name="loan_id" value="<?= (int)$loan['id'] ?>">
                <div class="mb-4">
                    <label class="form-label required">Amount (<?= $settings['currency_symbol'] ?? 'USh' ?>)</label>
                    <input type="number" name="amount" class="form-control" placeholder="0" min="0" step="1000"
                           value="<?= number_format((float)($loan['monthly_installment'] ?? 0), 0, '', '') ?>" required>
                    <button type="button" onclick="document.querySelector('[name=amount]').value='<?= number_format((float)($loan['balance_outstanding'] ?? 0), 0, '', '') ?>'"
                            class="text-xs text-blue-600 mt-1 hover:underline">Pay full balance</button>
                </div>
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div>
                        <label class="form-label required">Payment Method</label>
                        <select name="payment_method" class="form-control form-select" required>
                            <option value="cash">Cash</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label required">Payment Date</label>
                        <input type="date" name="transaction_date" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">External Reference</label>
                    <input type="text" name="external_ref" class="form-control" placeholder="Mobile money or bank ref (optional)">
                </div>
                <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
            </form>
        </div>
        <div class="modal-footer">
            <button onclick="closeRepaymentModal()" class="btn btn-secondary">Cancel</button>
            <button onclick="submitRepayment()" class="btn btn-primary"><i class="fa-solid fa-circle-check"></i> Record Payment</button>
        </div>
    </div>
</div>

<script>
function openRepaymentModal() { document.getElementById('repaymentModal').classList.remove('hidden'); }
function closeRepaymentModal() { document.getElementById('repaymentModal').classList.add('hidden'); }

async function submitRepayment() {
    const form = document.getElementById('repaymentForm');
    try {
        await window.submitForm(form, {
            onSuccess: r => { window.showToast('success', r.message); closeRepaymentModal(); setTimeout(() => location.reload(), 1200); },
            onError: e => window.showToast('error', e.message)
        });
    } catch (e) {}
}

async function disburseLoan(id) {
    window.confirmAction({
        title: 'Disburse Loan', message: 'Disburse this loan? This cannot be undone.', confirmText: 'Disburse', type: 'primary',
        onConfirm: async () => {
            const fd = new FormData(); fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
            const r = await fetch(`<?= APP_URL ?>/loans/${id}/disburse`, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
            const d = await r.json();
            if (d.success) { window.showToast('success', d.message); setTimeout(() => location.reload(), 1000); }
            else window.showToast('error', d.message);
        }
    });
}

// --- UNIFIED NOTES MODAL FOR APPROVE/REJECT ---
function openNotesModal(action, id, loanNo) {
    const isApprove = action === 'approve';
    const title = isApprove ? `Approve Loan ${loanNo}` : `Reject Loan ${loanNo}`;
    const label = isApprove ? 'Approval Notes' : 'Rejection Reason';
    const fieldName = isApprove ? 'approval_notes' : 'rejection_reason';
    const btnClass = isApprove ? 'btn-primary' : 'btn-danger';
    const btnIcon = isApprove ? 'fa-check' : 'fa-xmark';
    const btnText = isApprove ? 'Approve' : 'Reject';
    const headerIcon = isApprove ? 'fa-circle-check text-emerald-500' : 'fa-circle-xmark text-red-500';

    const existingModal = document.getElementById('notesModal');
    if (existingModal) existingModal.remove();

    const modalHtml = `
        <div id="notesModal" class="modal-overlay" style="z-index: 9999;">
            <div class="modal" style="max-width: 450px;">
                <div class="modal-header">
                    <h3 class="font-bold flex items-center gap-2">
                        <i class="fa-solid ${headerIcon}"></i> ${title}
                    </h3>
                    <button onclick="document.getElementById('notesModal').remove()" class="text-slate-400 hover:text-slate-700">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <label class="form-label required">${label} (Minimum 10 characters)</label>
                    <textarea id="notesModalInput" rows="3" class="form-control" placeholder="Enter your reason here..."></textarea>
                    <div class="text-xs text-slate-400 mt-1">
                        <span id="notesModalCount" class="text-red-500 font-bold">0</span>/10 characters minimum
                    </div>
                </div>
                <div class="modal-footer">
                    <button onclick="document.getElementById('notesModal').remove()" class="btn btn-secondary">Cancel</button>
                    <button id="notesModalSubmit" class="btn ${btnClass}" disabled>
                        <i class="fa-solid ${btnIcon}"></i> ${btnText}
                    </button>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);

    const textarea = document.getElementById('notesModalInput');
    const countSpan = document.getElementById('notesModalCount');
    const submitBtn = document.getElementById('notesModalSubmit');

    textarea.addEventListener('input', () => {
        const len = textarea.value.trim().length;
        countSpan.textContent = len;
        submitBtn.disabled = len < 10;
        if (len >= 10) {
            countSpan.classList.remove('text-red-500');
            countSpan.classList.add('text-emerald-500');
        } else {
            countSpan.classList.add('text-red-500');
            countSpan.classList.remove('text-emerald-500');
        }
    });

    submitBtn.addEventListener('click', async () => {
        const notes = textarea.value.trim();
        if (notes.length < 10) return;

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';

        const fd = new FormData();
        fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
        fd.append(fieldName, notes);

        const url = `<?= APP_URL ?>/loans/${id}/${action}`;
        try {
            const r = await fetch(url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
            const d = await r.json();
            if (d.success) {
                window.showToast('success', d.message);
                document.getElementById('notesModal').remove();
                setTimeout(() => location.reload(), 900);
            } else {
                window.showToast('error', d.message || 'Action failed.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = `<i class="fa-solid ${btnIcon}"></i> ${btnText}`;
            }
        } catch (e) {
            window.showToast('error', 'Network error.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = `<i class="fa-solid ${btnIcon}"></i> ${btnText}`;
        }
    });

    setTimeout(() => textarea.focus(), 100);
}

// Wrapper functions
function approveLoan(id, no) {
    openNotesModal('approve', id, no);
}

function rejectLoan(id, no) {
    openNotesModal('reject', id, no || 'this loan');
}
</script>