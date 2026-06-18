<?php
use App\Helpers\Format;
$pageTitle   = $expense['expense_ref'] ?? 'Expense Details';
$activePage  = 'expenses';
$breadcrumbs = ['Expenses' => APP_URL . '/expenses', $pageTitle => null];
?>

<div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-5">
        <div class="breadcrumb">
            <a href="<?= APP_URL ?>/expenses"><i class="fa-solid fa-house-chimney text-xs"></i></a>
            <span class="sep">/</span><a href="<?= APP_URL ?>/expenses">Expenses</a>
            <span class="sep">/</span><span class="current"><?= htmlspecialchars($expense['expense_ref'] ?? '') ?></span>
        </div>
        <div class="flex gap-2">
            <a href="<?= APP_URL ?>/expenses" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
            <?php if (in_array($expense['status'] ?? '', ['draft', 'pending']) && $auth->can('expenses.create')): ?>
                <a href="<?= APP_URL ?>/expenses/<?= $expense['id'] ?>/edit" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pen"></i> Edit</a>
            <?php endif; ?>
            <?php if (($expense['status'] ?? '') === 'approved' && $auth->can('expenses.approve')): ?>
                <button onclick="markPaid()" class="btn btn-primary btn-sm"><i class="fa-solid fa-money-bill-wave"></i> Mark as Paid</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hero Section -->
    <div class="card mb-5">
        <div class="card-body text-center py-8" style="background:linear-gradient(135deg,#f0fdf9,#ecfdf5);border-radius:14px;">
            <div class="text-xs font-semibold text-slate-400 mb-1 uppercase tracking-wider"><?= htmlspecialchars($expense['category_name'] ?? 'Expense') ?></div>
            <h2 class="text-3xl font-black text-slate-800 mb-2"><?= Format::currency((float)($expense['amount'] ?? 0)) ?></h2>
            <div class="flex items-center justify-center gap-3">
                <?= Format::statusPill($expense['status'] ?? 'draft') ?>
                <span class="text-sm text-slate-500"><?= Format::date($expense['expense_date'] ?? '') ?></span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <!-- Left: Details -->
        <div class="lg:col-span-2 space-y-5">
            <div class="card">
                <div class="card-header"><h3 class="section-title"><i class="fa-solid fa-info-circle mr-2" style="color:var(--green-mid)"></i>Expense Details</h3></div>
                <div class="card-body">
                    <div class="grid grid-cols-2 gap-x-8">
                        <?php 
                        $fields = [
                            ['Reference', $expense['expense_ref'] ?? '—'],
                            ['Title', $expense['title'] ?? '—'],
                            ['Description', $expense['description'] ?? '—'],
                            ['Payee Name', $expense['payee_name'] ?? '—'],
                            ['Payee Contact', $expense['payee_contact'] ?? '—'],
                            ['Receipt No', $expense['receipt_no'] ?? '—'],
                            ['Payment Method', Format::titleCase($expense['payment_method'] ?? '—')],
                            ['Created By', $expense['created_by_name'] ?? '—'],
                            ['Approved By', $expense['approved_by_name'] ?? '—'],
                            ['Paid By', $expense['paid_by_name'] ?? '—'],
                        ];
                        foreach ($fields as [$label, $value]): 
                        ?>
                            <div class="flex justify-between py-2.5 border-b border-slate-50 last:border-0">
                                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide"><?= $label ?></span>
                                <span class="text-sm font-semibold text-slate-700 text-right"><?= htmlspecialchars($value) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Attachment -->
            <?php if (!empty($expense['attachment_path'])): ?>
                <div class="card">
                    <div class="card-header"><h3 class="section-title"><i class="fa-solid fa-paperclip mr-2" style="color:var(--green-mid)"></i>Attachment</h3></div>
                    <div class="card-body">
                        <?php 
                        $ext = strtolower(pathinfo($expense['attachment_path'], PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])): 
                        ?>
                            <a href="<?= APP_URL ?>/storage/uploads/receipts/<?= htmlspecialchars($expense['attachment_path']) ?>" target="_blank">
                                <img src="<?= APP_URL ?>/storage/uploads/receipts/<?= htmlspecialchars($expense['attachment_path']) ?>" class="max-h-64 rounded-lg border border-slate-200 hover:opacity-90 transition-opacity" alt="Receipt">
                            </a>
                        <?php else: ?>
                            <a href="<?= APP_URL ?>/storage/uploads/receipts/<?= htmlspecialchars($expense['attachment_path']) ?>" target="_blank" class="flex items-center gap-3 p-4 bg-slate-50 rounded-lg border border-slate-200 hover:bg-slate-100 transition-colors">
                                <i class="fa-regular fa-file-pdf text-red-500 text-3xl"></i>
                                <div>
                                    <div class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($expense['attachment_path']) ?></div>
                                    <div class="text-xs text-slate-400">Click to download PDF</div>
                                </div>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right: Approval Notes -->
        <div>
            <?php if (!empty($approval)): ?>
                <div class="card sticky top-20">
                    <div class="card-header"><h3 class="section-title"><i class="fa-solid fa-clipboard-check mr-2" style="color:var(--green-mid)"></i>Approval Trail</h3></div>
                    <div class="card-body space-y-4">
                        <div class="p-3 rounded-lg <?= $approval['status'] === 'approved' ? 'bg-emerald-50 border border-emerald-200' : ($approval['status'] === 'rejected' ? 'bg-red-50 border border-red-200' : 'bg-amber-50 border border-amber-200') ?>">
                            <div class="text-xs font-bold uppercase tracking-wider mb-1 <?= $approval['status'] === 'approved' ? 'text-emerald-700' : ($approval['status'] === 'rejected' ? 'text-red-700' : 'text-amber-700') ?>">
                                <?= ucfirst($approval['status']) ?>
                            </div>
                            <?php if (!empty($approval['approval_notes'])): ?>
                                <div class="text-sm text-slate-700 mt-2 italic">"<?= htmlspecialchars($approval['approval_notes']) ?>"</div>
                            <?php endif; ?>
                            <?php if (!empty($approval['rejection_notes'])): ?>
                                <div class="text-sm text-slate-700 mt-2 italic text-red-600">"<?= htmlspecialchars($approval['rejection_notes']) ?>"</div>
                            <?php endif; ?>
                            <div class="text-xs text-slate-400 mt-3 pt-2 border-t border-current/10">
                                Reviewed by: <?= htmlspecialchars($approval['reviewed_by_name'] ?? '—') ?><br>
                                Date: <?= Format::datetime($approval['reviewed_at'] ?? '') ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card sticky top-20">
                    <div class="card-body text-center py-8 text-slate-400">
                        <i class="fa-regular fa-clock text-3xl mb-2 block"></i>
                        <p class="text-sm">Awaiting approval</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Mark Paid Modal -->
<div id="paidModal" class="hidden modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3 class="font-bold flex items-center gap-2"><i class="fa-solid fa-money-bill-wave text-emerald-500"></i> Mark as Paid</h3>
            <button onclick="closePaidModal()" class="text-slate-400 hover:text-slate-700"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <div class="modal-body">
            <form id="paidForm">
                <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
                <div class="mb-4">
                    <label class="form-label required">Payment Notes (Mandatory)</label>
                    <textarea name="payment_notes" rows="3" class="form-control" placeholder="Provide details of how/when this was paid (min 10 characters)" required minlength="10"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button onclick="closePaidModal()" class="btn btn-secondary">Cancel</button>
            <button onclick="submitPaid()" class="btn btn-primary"><i class="fa-solid fa-check"></i> Confirm Payment</button>
        </div>
    </div>
</div>

<script>
function markPaid() { document.getElementById('paidModal').classList.remove('hidden'); }
function closePaidModal() { document.getElementById('paidModal').classList.add('hidden'); }

async function submitPaid() {
    const form = document.getElementById('paidForm');
    const notes = form.querySelector('[name="payment_notes"]').value;
    if (notes.length < 10) {
        window.showToast('error', 'Payment notes must be at least 10 characters.');
        return;
    }
    
    const fd = new FormData(form);
    try {
        const r = await fetch('<?= APP_URL ?>/expenses/<?= $expense['id'] ?>/paid', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        });
        const d = await r.json();
        if (d.success) {
            window.showToast('success', d.message);
            setTimeout(() => location.reload(), 900);
        } else {
            window.showToast('error', d.message);
        }
    } catch (e) {
        window.showToast('error', 'Network error.');
    }
}
</script>