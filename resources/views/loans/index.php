<?php 
use App\Helpers\Format; 
$pageTitle   = 'Loans'; 
$activePage  = 'loans'; 
$breadcrumbs = ['Loans' => null]; 
$result      = $result ?? ['data' => [], 'total' => 0, 'page' => 1, 'last_page' => 1, 'from' => 0, 'to' => 0];
$loanStats   = $loanStats ?? [];
$loanProducts= $loanProducts ?? [];
?>
<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Loan Portfolio</h1>
        <p class="text-sm text-slate-400 mt-0.5"><?= number_format($result['total'] ?? 0) ?> total loans</p>
    </div>
    <div class="flex gap-2">
        <?php if ($auth->can('loans.create')): ?>
            <a href="<?= APP_URL ?>/loans/create" class="btn btn-primary btn-sm"><i class="fa-solid fa-file-circle-plus"></i> New Loan</a>
        <?php endif; ?>
    </div>
</div>

<!-- Stats row -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
    <?php 
    $sc = [
        ['label' => 'Active', 'val' => ($loanStats['active_loans'] ?? 0) + ($loanStats['disbursed_loans'] ?? 0), 'color' => 'text-emerald-700'],
        ['label' => 'Pending', 'val' => $loanStats['pending_loans'] ?? 0, 'color' => 'text-amber-600'],
        ['label' => 'Completed', 'val' => $loanStats['completed_loans'] ?? 0, 'color' => 'text-blue-600'],
        ['label' => 'Defaulted', 'val' => $loanStats['defaulted_loans'] ?? 0, 'color' => 'text-red-600']
    ];
    foreach ($sc as $s): 
    ?>
    <div class="stat-card text-center py-5">
        <div class="text-2xl font-black <?= $s['color'] ?>"><?= number_format($s['val']) ?></div>
        <div class="text-xs font-semibold text-slate-400 mt-1"><?= $s['label'] ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filter bar -->
<div class="card mb-5">
    <div class="card-body py-3">
        <form method="GET" class="flex flex-wrap gap-3">
            <div class="relative flex-1 min-w-[180px]">
                <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Search loan no, member…" class="form-control pl-9 py-2 text-sm">
            </div>
            <select name="status" class="form-control form-select py-2 text-sm w-auto min-w-[130px]" onchange="this.form.submit()">
                <option value="">All Status</option>
                <?php foreach (['pending', 'approved', 'active', 'completed', 'defaulted', 'rejected'] as $s): ?>
                    <option value="<?= $s ?>" <?= ($_GET['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="product" class="form-control form-select py-2 text-sm w-auto" onchange="this.form.submit()">
                <option value="">All Products</option>
                <?php foreach ($loanProducts as $lp): ?>
                    <option value="<?= $lp['id'] ?>" <?= ($_GET['product'] ?? '') == $lp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($lp['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="from" value="<?= htmlspecialchars($_GET['from'] ?? '') ?>" class="form-control py-2 text-sm w-auto">
            <input type="date" name="to" value="<?= htmlspecialchars($_GET['to'] ?? '') ?>" class="form-control py-2 text-sm w-auto">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
            <?php if (!empty($_GET['search']) || !empty($_GET['status']) || !empty($_GET['product']) || !empty($_GET['from']) || !empty($_GET['to'])): ?>
                <a href="<?= APP_URL ?>/loans" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i> Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Loan No</th><th>Member</th><th class="hidden md:table-cell">Product</th><th>Amount</th>
                    <th class="hidden lg:table-cell">Repaid</th><th class="hidden lg:table-cell">Outstanding</th>
                    <th>Status</th><th class="hidden md:table-cell">Applied</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($result['data'])): ?>
                    <tr><td colspan="9" class="text-center py-12"><i class="fa-solid fa-file-contract text-5xl text-slate-200 block mb-3"></i><p class="text-slate-400">No loans found</p></td></tr>
                <?php else: foreach ($result['data'] as $l): ?>
                <tr>
                    <td><span class="font-mono text-xs bg-slate-100 px-2 py-0.5 rounded"><?= htmlspecialchars($l['loan_no'] ?? '') ?></span></td>
                    <td>
                        <div class="font-semibold text-slate-800 text-sm"><?= htmlspecialchars($l['member_name'] ?? '') ?></div>
                        <div class="text-xs text-slate-400"><?= htmlspecialchars($l['member_no'] ?? '') ?></div>
                    </td>
                    <td class="hidden md:table-cell text-sm"><?= htmlspecialchars($l['product_name'] ?? '') ?></td>
                    <td class="font-bold text-slate-800"><?= Format::currency((float)($l['principal_amount'] ?? 0)) ?></td>
                    <td class="hidden lg:table-cell text-emerald-700 font-semibold"><?= Format::currency((float)($l['amount_paid'] ?? 0)) ?></td>
                    <td class="hidden lg:table-cell font-bold text-amber-700"><?= Format::currency((float)($l['balance_outstanding'] ?? 0)) ?></td>
                    <td><?= Format::statusPill($l['status'] ?? '') ?></td>
                    <td class="hidden md:table-cell text-xs text-slate-400"><?= Format::date($l['application_date'] ?? '') ?></td>
                    <td>
                        <div class="flex gap-1">
                            <a href="<?= APP_URL ?>/loans/<?= $l['id'] ?>" class="btn btn-secondary btn-sm py-1 px-2" title="View">
                                <i class="fa-solid fa-eye text-xs"></i>
                            </a>
                            
                            <?php if (in_array($l['status'] ?? '', ['draft', 'pending']) && $auth->can('loans.edit')): ?>
                                <a href="<?= APP_URL ?>/loans/<?= $l['id'] ?>/edit" class="btn btn-secondary btn-sm py-1 px-2" title="Edit">
                                    <i class="fa-solid fa-pen text-xs"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if (($l['status'] ?? '') === 'pending' && $auth->can('loans.approve')): ?>
                                <button onclick="approveLoan(<?= (int)$l['id'] ?>, '<?= addslashes($l['loan_no'] ?? '') ?>')" class="btn btn-sm py-1 px-2" style="background:#dcfce7;color:#166534;border:1px solid #a7f3d0;font-size:0.7rem;" title="Approve">
                                    <i class="fa-solid fa-check"></i>
                                </button>
                                <button onclick="rejectLoan(<?= (int)$l['id'] ?>, '<?= addslashes($l['loan_no'] ?? '') ?>')" class="btn btn-sm py-1 px-2" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;font-size:0.7rem;" title="Reject">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($result['data'])): 
        $page = $result['page'] ?? 1;
        $lastPage = $result['last_page'] ?? 1;
        $qs = http_build_query(array_diff_key($_GET, ['page' => '']));
        $base = APP_URL . '/loans?' . ($qs ? $qs . '&' : ''); 
    ?>
    <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between">
        <p class="text-xs text-slate-400">Showing <strong><?= $result['from'] ?? 1 ?></strong>–<strong><?= $result['to'] ?? count($result['data']) ?></strong> of <strong><?= number_format($result['total']) ?></strong></p>
        <div class="flex gap-1">
            <a href="<?= $base ?>page=<?= max(1, $page - 1) ?>" class="pager-btn <?= $page <= 1 ? 'opacity-40 pointer-events-none' : '' ?>"><i class="fa-solid fa-chevron-left text-xs"></i></a>
            <?php for ($p = max(1, $page - 2); $p <= min($lastPage, $page + 2); $p++): ?>
                <a href="<?= $base ?>page=<?= $p ?>" class="pager-btn <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <a href="<?= $base ?>page=<?= min($lastPage, $page + 1) ?>" class="pager-btn <?= $page >= $lastPage ? 'opacity-40 pointer-events-none' : '' ?>"><i class="fa-solid fa-chevron-right text-xs"></i></a>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
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

    // Remove existing modal if any
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