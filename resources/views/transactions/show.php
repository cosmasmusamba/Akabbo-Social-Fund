<?php
use App\Helpers\Format;

$pageTitle   = 'Transaction ' . ($txn['txn_ref'] ?? 'Details');
$activePage  = 'transactions';
$breadcrumbs = ['Transactions' => APP_URL . '/transactions', ($txn['txn_ref'] ?? 'Details') => null];
?>

<div class="max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-5">
        <div class="breadcrumb">
            <a href="<?= APP_URL ?>/transactions"><i class="fa-solid fa-house-chimney text-xs"></i></a>
            <span class="sep">/</span>
            <a href="<?= APP_URL ?>/transactions">Transactions</a>
            <span class="sep">/</span>
            <span class="current"><?= htmlspecialchars($txn['txn_ref'] ?? '') ?></span>
        </div>
        <a href="<?= APP_URL ?>/transactions" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>

    <div class="card">
        <div class="card-header flex items-center justify-between">
            <div>
                <div class="text-xs text-slate-400 mb-1">Transaction Reference</div>
                <h2 class="font-mono font-bold text-slate-800 text-lg"><?= htmlspecialchars($txn['txn_ref'] ?? '') ?></h2>
            </div>
            <?= Format::statusPill($txn['status'] ?? 'pending') ?>
        </div>
        
        <div class="card-body">
            <!-- Amount Hero -->
            <div class="text-center mb-6 p-6 rounded-xl" style="background:linear-gradient(135deg,#f0fdf9,#ecfdf5)">
                <div class="text-xs font-semibold text-slate-400 mb-1 uppercase tracking-wider">
                    <?= ucwords(str_replace('_', ' ', $txn['txn_type'] ?? '')) ?>
                </div>
                <div class="text-4xl font-black text-emerald-700">
                    <?= Format::currency((float)($txn['amount'] ?? 0)) ?>
                </div>
                <div class="text-sm text-slate-500 mt-1">
                    <?= Format::date($txn['transaction_date'] ?? '') ?>
                </div>
            </div>

            <!-- Details Grid -->
            <?php 
            $fields = [
                ['Member', $txn['member_name'] ?? '—'],
                ['Member No', $txn['member_no'] ?? '—'],
                ['Payment Method', ucwords(str_replace('_', ' ', $txn['payment_method'] ?? '—'))],
                ['External Reference', $txn['external_ref'] ?? '—'],
                ['Balance Before', $txn['balance_before'] !== null ? Format::currency((float)$txn['balance_before']) : '—'],
                ['Balance After', $txn['balance_after'] !== null ? Format::currency((float)$txn['balance_after']) : '—'],
                ['Description', $txn['description'] ?? '—'],
                ['Created At', Format::datetime($txn['created_at'] ?? '')],
                ['Approved By', $txn['approved_by_name'] ?? '—'],
                ['Approved At', Format::datetime($txn['approved_at'] ?? '')],
            ];
            foreach ($fields as [$label, $value]): 
            ?>
            <div class="flex justify-between py-2.5 border-b border-slate-50 last:border-0">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide"><?= $label ?></span>
                <span class="text-sm font-semibold text-slate-700 text-right max-w-xs break-words"><?= htmlspecialchars($value) ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Action Buttons -->
        <?php if ($auth->can('transactions.approve') && ($txn['status'] ?? '') === 'pending'): ?>
        <div class="px-6 pb-5 flex gap-3 justify-end">
            <button onclick="approveTxn(<?= (int)$txn['id'] ?>, '<?= addslashes($txn['txn_ref'] ?? '') ?>')" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-check"></i> Approve
            </button>
            <button onclick="rejectTxn(<?= (int)$txn['id'] ?>, '<?= addslashes($txn['txn_ref'] ?? '') ?>')" class="btn btn-danger btn-sm">
                <i class="fa-solid fa-xmark"></i> Reject
            </button>
        </div>
        <?php endif; ?>

        <?php if ($auth->can('transactions.reverse') && in_array($txn['status'] ?? '', ['completed', 'approved'])): ?>
        <div class="px-6 pb-5 flex gap-3 justify-end">
            <button onclick="reverseTxn(<?= (int)$txn['id'] ?>, '<?= addslashes($txn['txn_ref'] ?? '') ?>')" class="btn btn-danger btn-sm">
                <i class="fa-solid fa-rotate-left"></i> Reverse Transaction
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Unified Notes Modal for Transaction Actions
function openTxnNotesModal(action, id, txnRef) {
    const isApprove = action === 'approve';
    const isReject = action === 'reject';
    const isReverse = action === 'reverse';
    
    let title, label, fieldName, btnClass, btnIcon, btnText, headerIcon;
    
    if (isApprove) {
        title = `Approve Transaction ${txnRef}`;
        label = 'Approval Notes';
        fieldName = 'approval_notes';
        btnClass = 'btn-primary';
        btnIcon = 'fa-check';
        btnText = 'Approve';
        headerIcon = 'fa-circle-check text-emerald-500';
    } else if (isReject) {
        title = `Reject Transaction ${txnRef}`;
        label = 'Rejection Notes';
        fieldName = 'rejection_notes';
        btnClass = 'btn-danger';
        btnIcon = 'fa-xmark';
        btnText = 'Reject';
        headerIcon = 'fa-circle-xmark text-red-500';
    } else {
        title = `Reverse Transaction ${txnRef}`;
        label = 'Reversal Reason';
        fieldName = 'reversal_notes';
        btnClass = 'btn-danger';
        btnIcon = 'fa-rotate-left';
        btnText = 'Reverse';
        headerIcon = 'fa-circle-xmark text-red-500';
    }

    // Remove existing modal if any
    const existingModal = document.getElementById('txnNotesModal');
    if (existingModal) existingModal.remove();

    const modalHtml = `
        <div id="txnNotesModal" class="modal-overlay" style="z-index: 9999;">
            <div class="modal" style="max-width: 450px;">
                <div class="modal-header">
                    <h3 class="font-bold flex items-center gap-2">
                        <i class="fa-solid ${headerIcon}"></i> ${title}
                    </h3>
                    <button onclick="document.getElementById('txnNotesModal').remove()" class="text-slate-400 hover:text-slate-700">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <label class="form-label required">${label} (Minimum 10 characters)</label>
                    <textarea id="txnNotesModalInput" rows="3" class="form-control" placeholder="Enter your reason here..."></textarea>
                    <div class="text-xs text-slate-400 mt-1">
                        <span id="txnNotesModalCount" class="text-red-500 font-bold">0</span>/10 characters minimum
                    </div>
                </div>
                <div class="modal-footer">
                    <button onclick="document.getElementById('txnNotesModal').remove()" class="btn btn-secondary">Cancel</button>
                    <button id="txnNotesModalSubmit" class="btn ${btnClass}" disabled>
                        <i class="fa-solid ${btnIcon}"></i> ${btnText}
                    </button>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);

    const textarea = document.getElementById('txnNotesModalInput');
    const countSpan = document.getElementById('txnNotesModalCount');
    const submitBtn = document.getElementById('txnNotesModalSubmit');

    // Real-time validation
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

    // Handle submission
    submitBtn.addEventListener('click', async () => {
        const notes = textarea.value.trim();
        if (notes.length < 10) return;

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';

        const fd = new FormData();
        fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
        fd.append(fieldName, notes);

        const url = `<?= APP_URL ?>/transactions/${id}/${action}`;
        try {
            const r = await fetch(url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
            const d = await r.json();
            if (d.success) {
                window.showToast('success', d.message);
                document.getElementById('txnNotesModal').remove();
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

// Wrapper functions for the action buttons
function approveTxn(id, txnRef) {
    openTxnNotesModal('approve', id, txnRef);
}

function rejectTxn(id, txnRef) {
    openTxnNotesModal('reject', id, txnRef);
}

function reverseTxn(id, txnRef) {
    openTxnNotesModal('reverse', id, txnRef);
}
</script>