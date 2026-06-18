<?php
use App\Helpers\Format;

$pageTitle   = 'Transactions';
$activePage  = 'transactions';
$breadcrumbs = ['Transactions' => null];
$typeColors  = [
    'deposit'           => 'bg-emerald-100 text-emerald-700',
    'withdrawal'        => 'bg-orange-100 text-orange-700',
    'loan_disbursement' => 'bg-blue-100 text-blue-700',
    'loan_repayment'    => 'bg-purple-100 text-purple-700',
    'membership_fee'    => 'bg-teal-100 text-teal-700',
    'penalty'           => 'bg-red-100 text-red-700',
    'transfer_in'       => 'bg-indigo-100 text-indigo-700',
    'transfer_out'      => 'bg-amber-100 text-amber-700',
    'interest'          => 'bg-sky-100 text-sky-700',
];
?>

<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Transactions</h1>
        <p class="text-sm text-slate-400 mt-0.5"><?= number_format($result['total'] ?? 0) ?> records</p>
    </div>
    <div class="flex gap-2">
        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-file-csv text-green-600"></i> Export
        </a>
    </div>
</div>

<!-- Type totals pills -->
<div class="flex flex-wrap gap-2 mb-5">
    <?php foreach ($typeTotals ?? [] as $t): 
        $cls = $typeColors[$t['txn_type']] ?? 'bg-slate-100 text-slate-600'; 
    ?>
    <div class="flex items-center gap-2 px-3 py-2 rounded-xl <?= $cls ?> border border-current/20">
        <span class="text-xs font-bold capitalize"><?= str_replace('_', ' ', $t['txn_type']) ?></span>
        <span class="text-xs opacity-70"><?= number_format($t['cnt']) ?> · <?= Format::currencyCompact((float)$t['total']) ?></span>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="card mb-5">
    <div class="card-body py-3">
        <form method="GET" class="flex flex-wrap gap-3">
            <div class="relative flex-1 min-w-[160px]">
                <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Ref, member…" class="form-control pl-9 py-2 text-sm">
            </div>
            <select name="type" class="form-control form-select py-2 text-sm w-auto" onchange="this.form.submit()">
                <option value="">All Types</option>
                <?php foreach (['deposit', 'withdrawal', 'loan_disbursement', 'loan_repayment', 'membership_fee', 'penalty', 'transfer_in', 'transfer_out', 'interest'] as $t): ?>
                    <option value="<?= $t ?>" <?= ($filters['type'] ?? '') === $t ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $t)) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="from" value="<?= htmlspecialchars($filters['from'] ?? date('Y-m-01')) ?>" class="form-control py-2 text-sm w-auto">
            <input type="date" name="to" value="<?= htmlspecialchars($filters['to'] ?? date('Y-m-d')) ?>" class="form-control py-2 text-sm w-auto">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
            <a href="<?= APP_URL ?>/transactions" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i></a>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Type</th>
                    <th>Member</th>
                    <th>Amount</th>
                    <th class="hidden md:table-cell">Method</th>
                    <th class="hidden lg:table-cell">Balance After</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($result['data'])): ?>
                    <tr>
                        <td colspan="9" class="text-center py-12">
                            <i class="fa-solid fa-arrow-right-arrow-left text-5xl text-slate-200 block mb-3"></i>
                            <p class="text-slate-400">No transactions found</p>
                        </td>
                    </tr>
                <?php else: foreach ($result['data'] as $t): 
                    $tc = $typeColors[$t['txn_type']] ?? 'bg-slate-100 text-slate-600'; 
                ?>
                <tr>
                    <td><span class="font-mono text-xs text-slate-600"><?= htmlspecialchars($t['txn_ref'] ?? '') ?></span></td>
                    <td><span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $tc ?>"><?= ucwords(str_replace('_', ' ', $t['txn_type'] ?? '')) ?></span></td>
                    <td>
                        <?php if (!empty($t['member_name'])): ?>
                            <div class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($t['member_name']) ?></div>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars($t['member_no'] ?? '') ?></div>
                        <?php else: ?>
                            <span class="text-slate-300 text-xs">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="font-bold <?= in_array($t['txn_type'] ?? '', ['deposit', 'loan_repayment', 'membership_fee', 'transfer_in', 'interest']) ? 'text-emerald-700' : 'text-slate-700' ?>">
                        <?= Format::currency((float)($t['amount'] ?? 0)) ?>
                    </td>
                    <td class="hidden md:table-cell text-xs capitalize"><?= str_replace('_', ' ', $t['payment_method'] ?? '—') ?></td>
                    <td class="hidden lg:table-cell text-xs text-slate-500">
                        <?= $t['balance_after'] !== null ? Format::currency((float)$t['balance_after']) : '—' ?>
                    </td>
                    <td class="text-xs text-slate-500"><?= Format::date($t['transaction_date'] ?? '') ?></td>
                    <td><?= Format::statusPill($t['status'] ?? 'pending') ?></td>
                    <td>
                        <div class="flex gap-1">
                            <a href="<?= APP_URL ?>/transactions/<?= $t['id'] ?>" class="btn btn-secondary btn-sm py-1 px-2" title="View details">
                                <i class="fa-solid fa-eye text-xs"></i>
                            </a>
                            
                            <?php if (($t['status'] ?? '') === 'pending' && $auth->can('transactions.approve')): ?>
                                <button onclick="approveTxn(<?= (int)$t['id'] ?>)" class="btn btn-sm py-1 px-2" style="background:#dcfce7;color:#166534;border:1px solid #a7f3d0;font-size:0.7rem;" title="Approve">
                                    <i class="fa-solid fa-check"></i>
                                </button>
                            <?php endif; ?>
                            
                            <?php if (in_array($t['status'] ?? '', ['completed', 'approved']) && $auth->can('transactions.reverse')): ?>
                                <button onclick="reverseTxn(<?= (int)$t['id'] ?>)" class="btn btn-sm py-1 px-2" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;font-size:0.7rem;" title="Reverse">
                                    <i class="fa-solid fa-rotate-left"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if (!empty($result['data'])): 
        $page     = $result['page'] ?? 1;
        $lastPage = $result['last_page'] ?? 1;
        $qs       = http_build_query(array_diff_key($_GET, ['page' => '']));
        $base     = APP_URL . '/transactions?' . ($qs ? $qs . '&' : ''); 
    ?>
    <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between">
        <p class="text-xs text-slate-400">
            Showing <strong><?= $result['from'] ?? 1 ?></strong>–<strong><?= $result['to'] ?? count($result['data']) ?></strong> of <strong><?= number_format($result['total']) ?></strong>
        </p>
        <div class="flex gap-1">
            <a href="<?= $base ?>page=<?= max(1, $page - 1) ?>" class="pager-btn <?= $page <= 1 ? 'opacity-40 pointer-events-none' : '' ?>">
                <i class="fa-solid fa-chevron-left text-xs"></i>
            </a>
            <?php for ($p = max(1, $page - 2); $p <= min($lastPage, $page + 2); $p++): ?>
                <a href="<?= $base ?>page=<?= $p ?>" class="pager-btn <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <a href="<?= $base ?>page=<?= min($lastPage, $page + 1) ?>" class="pager-btn <?= $page >= $lastPage ? 'opacity-40 pointer-events-none' : '' ?>">
                <i class="fa-solid fa-chevron-right text-xs"></i>
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Unified Notes Modal for Approving and Reversing Transactions
function openTxnNotesModal(action, id) {
    const isApprove = action === 'approve';
    const title = isApprove ? 'Approve Transaction' : 'Request Reversal';
    const label = isApprove ? 'Approval Notes' : 'Reversal Reason';
    const fieldName = isApprove ? 'approval_notes' : 'reversal_notes';
    const btnClass = isApprove ? 'btn-primary' : 'btn-danger';
    const btnIcon = isApprove ? 'fa-check' : 'fa-rotate-left';
    const btnText = isApprove ? 'Approve' : 'Request Reversal';
    const headerIcon = isApprove ? 'fa-circle-check text-emerald-500' : 'fa-circle-xmark text-red-500';

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

// Wrapper functions for the table buttons
function approveTxn(id) {
    openTxnNotesModal('approve', id);
}

function reverseTxn(id) {
    openTxnNotesModal('reverse', id);
}
</script>