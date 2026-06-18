<?php
use App\Helpers\Format;
$pageTitle   = $pageTitle ?? 'Approval Details';
$activePage  = $activePage ?? 'approvals';
$breadcrumbs = $breadcrumbs ?? ['Approvals' => APP_URL.'/approvals', 'Details' => null];
$approval    = $approval ?? [];
$refData     = $refData ?? [];
$financialContext = $financialContext ?? null;
$isPending   = ($approval['status'] ?? '') === 'pending';
?>
<div class="flex items-center justify-between mb-5">
    <div class="breadcrumb">
        <a href="<?= APP_URL ?>/approvals"><i class="fa-solid fa-house-chimney text-xs"></i></a>
        <span class="sep">/</span><a href="<?= APP_URL ?>/approvals">Approvals</a>
        <span class="sep">/</span><span class="current">#<?= htmlspecialchars($approval['id'] ?? '') ?></span>
    </div>
    <a href="<?= APP_URL ?>/approvals" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back to Queue</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <!-- Left: Request Details -->
    <div class="lg:col-span-2 space-y-5">
        <!-- Header Card -->
        <div class="card">
            <div class="card-body">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">
                            <?= ucwords(str_replace('_', ' ', $approval['reference_type'] ?? '')) ?> Request
                        </div>
                        <h2 class="text-2xl font-black text-slate-800">
                            <?= htmlspecialchars($approval['reference_ref'] ?? 'Unknown Reference') ?>
                        </h2>
                    </div>
                    <?= Format::statusPill($approval['status'] ?? 'pending') ?>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 pt-4 border-t border-slate-100">
                    <div>
                        <div class="text-xs text-slate-400">Requested By</div>
                        <div class="font-semibold text-sm"><?= htmlspecialchars($approval['requested_by_name'] ?? '—') ?></div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-400">Requested At</div>
                        <div class="font-semibold text-sm"><?= Format::date($approval['requested_at'] ?? '', 'd M Y, H:i') ?></div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-400">Amount</div>
                        <div class="font-bold text-lg <?= ($approval['amount'] ?? 0) > 0 ? 'text-emerald-700' : 'text-slate-700' ?>">
                            <?= !empty($approval['amount']) ? Format::currency((float)$approval['amount']) : '—' ?>
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-400">SLA Due By</div>
                        <div class="font-semibold text-sm <?= !empty($approval['is_overdue']) && $isPending ? 'text-red-600' : '' ?>">
                            <?= Format::date($approval['due_by'] ?? '', 'd M Y, H:i') ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (is_array($financialContext) && $isPending): ?>
            <div class="card border-l-4 <?= $financialContext['is_sufficient'] ? 'border-l-emerald-500' : 'border-l-red-500' ?>">
                <div class="card-header">
                    <h3 class="section-title flex items-center gap-2">
                        <i class="fa-solid fa-shield-halved <?= $financialContext['is_sufficient'] ? 'text-emerald-600' : 'text-red-600' ?>"></i>
                        Financial Health Check
                    </h3>
                    <?php if (!$financialContext['is_sufficient']): ?>
                        <span class="badge bg-red-100 text-red-700 border-red-200">Insufficient Funds</span>
                    <?php else: ?>
                        <span class="badge bg-emerald-100 text-emerald-700 border-emerald-200">Funds Available</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (!$financialContext['is_sufficient']): ?>
                        <div class="p-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm mb-4 flex items-start gap-2">
                            <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
                            <div>
                                <strong>Warning:</strong> The member's current available balance is lower than the requested amount. 
                                If you approve this request, the system will automatically reject it during execution to prevent an overdraft.
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="p-3 rounded-lg bg-slate-50 border border-slate-100">
                            <div class="text-xs text-slate-400 mb-1">Current Balance</div>
                            <div class="font-bold text-slate-800"><?= Format::currency($financialContext['current_balance']) ?></div>
                        </div>
                        <div class="p-3 rounded-lg bg-slate-50 border border-slate-100">
                            <div class="text-xs text-slate-400 mb-1">Loan Collateral</div>
                            <div class="font-bold text-amber-600"><?= Format::currency($financialContext['loan_collateral']) ?></div>
                        </div>
                        <div class="p-3 rounded-lg bg-slate-50 border border-slate-100">
                            <div class="text-xs text-slate-400 mb-1">Min. Savings</div>
                            <div class="font-bold text-slate-600"><?= Format::currency($financialContext['min_savings']) ?></div>
                        </div>
                        <div class="p-3 rounded-lg <?= $financialContext['is_sufficient'] ? 'bg-emerald-50 border-emerald-200' : 'bg-red-50 border-red-200' ?>">
                            <div class="text-xs <?= $financialContext['is_sufficient'] ? 'text-emerald-600' : 'text-red-600' ?> mb-1">Available to Withdraw</div>
                            <div class="font-bold <?= $financialContext['is_sufficient'] ? 'text-emerald-800' : 'text-red-800' ?>"><?= Format::currency($financialContext['available_balance']) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Referenced Record Details -->
        <div class="card">
            <div class="card-header"><h3 class="section-title"><i class="fa-solid fa-file-lines mr-2" style="color:var(--green-mid)"></i>Request Details</h3></div>
            <div class="card-body">
                <?php if (empty($refData) || !is_array($refData)): ?>
                    <p class="text-sm text-slate-400 italic">No additional details available for this record type.</p>
                <?php else:
                $type = $approval['reference_type'] ?? '';
                if ($type === 'withdrawal' || $type === 'reversal'): ?>
                    <div class="grid grid-cols-2 gap-x-8 gap-y-3">
                        <div class="flex justify-between py-2 border-b border-slate-50"><span class="text-xs text-slate-400">Member</span><span class="text-sm font-semibold"><?= htmlspecialchars($refData['member_name'] ?? '—') ?> (<?= htmlspecialchars($refData['member_no'] ?? '') ?>)</span></div>
                        <div class="flex justify-between py-2 border-b border-slate-50"><span class="text-xs text-slate-400">Type</span><span class="text-sm font-semibold capitalize"><?= str_replace('_', ' ', $refData['txn_type'] ?? '') ?></span></div>
                        <div class="flex justify-between py-2 border-b border-slate-50"><span class="text-xs text-slate-400">Method</span><span class="text-sm font-semibold capitalize"><?= str_replace('_', ' ', $refData['payment_method'] ?? '') ?></span></div>
                        <div class="flex justify-between py-2 border-b border-slate-50 col-span-2"><span class="text-xs text-slate-400">Description</span><span class="text-sm font-semibold"><?= htmlspecialchars($refData['description'] ?? '—') ?></span></div>
                    </div>
                <?php elseif ($type === 'transfer'): ?>
                    <div class="grid grid-cols-2 gap-x-8 gap-y-3">
                        <div class="flex justify-between py-2 border-b border-slate-50"><span class="text-xs text-slate-400">From</span><span class="text-sm font-semibold"><?= htmlspecialchars($refData['from_name'] ?? '—') ?> (<?= htmlspecialchars($refData['from_no'] ?? '') ?>)</span></div>
                        <div class="flex justify-between py-2 border-b border-slate-50"><span class="text-xs text-slate-400">To</span><span class="text-sm font-semibold"><?= htmlspecialchars($refData['to_name'] ?? '—') ?> (<?= htmlspecialchars($refData['to_no'] ?? '') ?>)</span></div>
                        <div class="flex justify-between py-2 border-b border-slate-50 col-span-2"><span class="text-xs text-slate-400">Description</span><span class="text-sm font-semibold"><?= htmlspecialchars($refData['description'] ?? '—') ?></span></div>
                    </div>
                <?php elseif ($type === 'disbursement'): ?>
                    <div class="grid grid-cols-2 gap-x-8 gap-y-3">
                        <div class="flex justify-between py-2 border-b border-slate-50"><span class="text-xs text-slate-400">Member</span><span class="text-sm font-semibold"><?= htmlspecialchars($refData['member_name'] ?? '—') ?> (<?= htmlspecialchars($refData['member_no'] ?? '') ?>)</span></div>
                        <div class="flex justify-between py-2 border-b border-slate-50"><span class="text-xs text-slate-400">Product</span><span class="text-sm font-semibold"><?= htmlspecialchars($refData['product_name'] ?? '—') ?></span></div>
                        <div class="flex justify-between py-2 border-b border-slate-50"><span class="text-xs text-slate-400">Term</span><span class="text-sm font-semibold"><?= ($refData['term_months'] ?? 0) ?> months</span></div>
                        <div class="flex justify-between py-2 border-b border-slate-50"><span class="text-xs text-slate-400">Rate</span><span class="text-sm font-semibold"><?= ($refData['interest_rate'] ?? 0) ?>% p.a.</span></div>
                    </div>
                <?php elseif ($type === 'expense'): ?>
                    <div class="grid grid-cols-2 gap-x-8 gap-y-3">
                        <div class="flex justify-between py-2 border-b border-slate-50"><span class="text-xs text-slate-400">Category</span><span class="text-sm font-semibold"><?= htmlspecialchars($refData['category_name'] ?? '—') ?></span></div>
                        <div class="flex justify-between py-2 border-b border-slate-50"><span class="text-xs text-slate-400">Payee</span><span class="text-sm font-semibold"><?= htmlspecialchars($refData['payee_name'] ?? '—') ?></span></div>
                        <div class="flex justify-between py-2 border-b border-slate-50 col-span-2"><span class="text-xs text-slate-400">Title</span><span class="text-sm font-semibold"><?= htmlspecialchars($refData['title'] ?? '—') ?></span></div>
                    </div>
                <?php elseif ($type === 'share_transaction'): ?>
                    <div class="grid grid-cols-2 gap-x-8 gap-y-3">
                        <div class="flex justify-between py-2 border-b border-slate-50"><span class="text-xs text-slate-400">Member</span><span class="text-sm font-semibold"><?= htmlspecialchars($refData['member_name'] ?? '—') ?> (<?= htmlspecialchars($refData['member_no'] ?? '') ?>)</span></div>
                        <div class="flex justify-between py-2 border-b border-slate-50"><span class="text-xs text-slate-400">Type</span><span class="text-sm font-semibold capitalize"><?= str_replace('_', ' ', $refData['txn_type'] ?? '') ?></span></div>
                        <div class="flex justify-between py-2 border-b border-slate-50"><span class="text-xs text-slate-400">Quantity</span><span class="text-sm font-semibold"><?= number_format($refData['shares_qty'] ?? 0) ?> shares</span></div>
                    </div>
                <?php else: ?>
                    <pre class="text-xs bg-slate-50 p-3 rounded"><?= htmlspecialchars(json_encode($refData, JSON_PRETTY_PRINT)) ?></pre>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Reviewer Notes (if processed) -->
        <?php if (!$isPending): ?>
            <div class="card">
                <div class="card-header"><h3 class="section-title"><i class="fa-solid fa-comment-dots mr-2" style="color:var(--green-mid)"></i>Reviewer Notes</h3></div>
                <div class="card-body">
                    <div class="p-4 rounded-xl <?= ($approval['status'] ?? '') === 'approved' ? 'bg-emerald-50 border border-emerald-200' : 'bg-red-50 border border-red-200' ?>">
                        <div class="flex items-center gap-2 mb-2">
                            <i class="fa-solid <?= ($approval['status'] ?? '') === 'approved' ? 'fa-circle-check text-emerald-600' : 'fa-circle-xmark text-red-600' ?>"></i>
                            <span class="font-bold <?= ($approval['status'] ?? '') === 'approved' ? 'text-emerald-800' : 'text-red-800' ?>">
                                <?= ucfirst($approval['status'] ?? '') ?> by <?= htmlspecialchars($approval['reviewed_by_name'] ?? 'Unknown') ?>
                            </span>
                        </div>
                        <div class="text-sm text-slate-700 italic">
                            "<?= htmlspecialchars($approval['approval_notes'] ?? $approval['rejection_notes'] ?? 'No notes provided') ?>"
                        </div>
                        <div class="text-xs text-slate-400 mt-2 pt-2 border-t border-current/10">
                            Reviewed at: <?= Format::date($approval['reviewed_at'] ?? '', 'd M Y, H:i') ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right: Action Panel -->
    <div>
        <?php if ($isPending && $auth->can('approvals.process')): ?>
            <div class="card sticky top-20">
                <div class="card-header"><h3 class="section-title">Process Request</h3></div>
                <div class="card-body space-y-4">
                    <div class="p-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-xs flex items-start gap-2">
                        <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
                        <div>
                            <strong>Note:</strong> You cannot approve or reject your own requests. Mandatory notes (min 10 characters) are required.
                        </div>
                    </div>

                    <!-- Approve Form -->
                    <form id="approveForm" class="space-y-3">
                        <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
                        <div>
                            <label class="form-label required">Approval Notes</label>
                            <textarea name="approval_notes" id="approveNotes" rows="3" class="form-control" placeholder="State your reason for approving..." required minlength="10"></textarea>
                            <div class="text-xs text-slate-400 mt-1"><span id="approveCharCount" class="text-red-500">0</span>/10 characters minimum</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-full justify-center" <?= (is_array($financialContext) && !$financialContext['is_sufficient']) ? 'title="Warning: Insufficient funds"' : '' ?>>
                            <i class="fa-solid fa-check"></i> Approve Request
                        </button>
                    </form>

                    <div class="relative flex py-2 items-center">
                        <div class="flex-grow border-t border-slate-200"></div>
                        <span class="flex-shrink-0 mx-4 text-slate-400 text-xs">OR</span>
                        <div class="flex-grow border-t border-slate-200"></div>
                    </div>

                    <!-- Reject Form -->
                    <form id="rejectForm" class="space-y-3">
                        <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
                        <div>
                            <label class="form-label required">Rejection Notes</label>
                            <textarea name="rejection_notes" id="rejectNotes" rows="3" class="form-control" placeholder="State your reason for rejecting..." required minlength="10"></textarea>
                            <div class="text-xs text-slate-400 mt-1"><span id="rejectCharCount" class="text-red-500">0</span>/10 characters minimum</div>
                        </div>
                        <button type="submit" class="btn btn-danger w-full justify-center">
                            <i class="fa-solid fa-xmark"></i> Reject Request
                        </button>
                    </form>
                </div>
            </div>
        <?php elseif (!$isPending): ?>
            <div class="card sticky top-20 text-center py-8">
                <i class="fa-solid fa-clipboard-check text-4xl text-slate-300 mb-3 block"></i>
                <p class="text-sm text-slate-500">This request has already been processed.</p>
            </div>
        <?php else: ?>
            <div class="card sticky top-20 text-center py-8">
                <i class="fa-solid fa-lock text-4xl text-slate-300 mb-3 block"></i>
                <p class="text-sm text-slate-500">You do not have permission to process this request.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Character counters for note validation
['approveNotes', 'rejectNotes'].forEach(id => {
    const textarea = document.getElementById(id);
    const counter = document.getElementById(id.replace('Notes', 'CharCount'));
    if (textarea && counter) {
        textarea.addEventListener('input', () => {
            const len = textarea.value.trim().length;
            counter.textContent = len;
            if (len < 10) {
                counter.classList.add('text-red-500');
                counter.classList.remove('text-emerald-500');
            } else {
                counter.classList.remove('text-red-500');
                counter.classList.add('text-emerald-500');
            }
        });
    }
});

// Handle Approve
document.getElementById('approveForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const notes = document.getElementById('approveNotes').value.trim();
    if (notes.length < 10) {
        window.showToast('error', 'Approval notes must be at least 10 characters long.');
        return;
    }
    
    <?php if (is_array($financialContext) && !$financialContext['is_sufficient']): ?>
    if (!confirm('WARNING: The member currently has insufficient available balance. The system will auto-reject this during execution to prevent an overdraft. Do you still want to proceed?')) {
        return;
    }
    <?php endif; ?>

    const btn = this.querySelector('button[type="submit"]');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
    const formData = new FormData(this);
    try {
        const r = await fetch('<?= APP_URL ?>/approvals/<?= $approval['id'] ?>/approve', {
            method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData
        });
        const d = await r.json();
        if (d.success) { window.showToast('success', d.message); setTimeout(() => location.reload(), 1000); }
        else { window.showToast('error', d.message || 'Approval failed.'); btn.disabled = false; btn.innerHTML = originalHtml; }
    } catch (err) { window.showToast('error', 'Network error.'); btn.disabled = false; btn.innerHTML = originalHtml; }
});

// Handle Reject
document.getElementById('rejectForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const notes = document.getElementById('rejectNotes').value.trim();
    if (notes.length < 10) {
        window.showToast('error', 'Rejection notes must be at least 10 characters long.');
        return;
    }
    const btn = this.querySelector('button[type="submit"]');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
    const formData = new FormData(this);
    try {
        const r = await fetch('<?= APP_URL ?>/approvals/<?= $approval['id'] ?>/reject', {
            method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData
        });
        const d = await r.json();
        if (d.success) { window.showToast('success', d.message); setTimeout(() => location.reload(), 1000); }
        else { window.showToast('error', d.message || 'Rejection failed.'); btn.disabled = false; btn.innerHTML = originalHtml; }
    } catch (err) { window.showToast('error', 'Network error.'); btn.disabled = false; btn.innerHTML = originalHtml; }
});
</script>