<?php
use App\Helpers\Format;

$pageTitle   = 'Transfer ' . ($transfer['transfer_ref'] ?? 'Details');
$activePage  = 'transfers';
$breadcrumbs = ['Transfers' => APP_URL.'/transfers', ($transfer['transfer_ref'] ?? 'Details') => null];
?>

<div class="max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-5">
        <div class="breadcrumb">
            <a href="<?= APP_URL ?>/transfers" class="text-slate-500 hover:text-slate-700">Transfers</a>
            <span class="sep mx-2 text-slate-300">/</span>
            <span class="current font-semibold text-slate-800"><?= htmlspecialchars($transfer['transfer_ref'] ?? '') ?></span>
        </div>
        <a href="<?= APP_URL ?>/transfers" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>

    <div class="card">
        <div class="card-header flex items-center justify-between">
            <div>
                <div class="text-xs text-slate-400 mb-1 uppercase tracking-wider">Transfer Reference</div>
                <h2 class="font-mono font-bold text-slate-800 text-lg"><?= htmlspecialchars($transfer['transfer_ref'] ?? '') ?></h2>
            </div>
            <?= Format::statusPill($transfer['status'] ?? 'pending') ?>
        </div>

        <div class="card-body">
            <!-- Hero Amount -->
            <div class="text-center mb-8 p-6 rounded-xl" style="background:linear-gradient(135deg,#f0fdf9,#ecfdf5)">
                <div class="text-xs font-semibold text-slate-400 mb-1 uppercase tracking-wider">Transfer Amount</div>
                <div class="text-4xl font-black text-emerald-700"><?= Format::currency((float)($transfer['amount'] ?? 0)) ?></div>
                <div class="text-sm text-slate-500 mt-1"><?= Format::date($transfer['transfer_date'] ?? '') ?></div>
            </div>

            <!-- Details Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 mb-6">
                <?php 
                $fields = [
                    ['From Member', ($transfer['from_name'] ?? '—') . ' (' . ($transfer['from_no'] ?? '') . ')'],
                    ['To Member',   ($transfer['to_name'] ?? '—') . ' (' . ($transfer['to_no'] ?? '') . ')'],
                    ['Description', $transfer['description'] ?? '—'],
                    ['Created By',  $transfer['created_by_name'] ?? '—'],
                    ['Approved By', $transfer['approved_by_name'] ?? '—'],
                    ['Created At',  Format::datetime($transfer['created_at'] ?? '')],
                ];
                foreach ($fields as [$label, $value]): 
                ?>
                <div class="flex justify-between py-2.5 border-b border-slate-50 last:border-0">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide"><?= $label ?></span>
                    <span class="text-sm font-semibold text-slate-700 text-right max-w-xs break-words"><?= htmlspecialchars($value) ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Approval Notes (if processed) -->
            <?php if (!empty($transfer['approval_notes']) || !empty($transfer['rejection_notes'])): ?>
            <div class="p-4 rounded-xl mb-6 <?= ($transfer['status'] ?? '') === 'rejected' ? 'bg-red-50 border border-red-200' : 'bg-emerald-50 border border-emerald-200' ?>">
                <div class="text-xs font-bold uppercase tracking-wider mb-1 <?= ($transfer['status'] ?? '') === 'rejected' ? 'text-red-700' : 'text-emerald-700' ?>">
                    <?= ucfirst($transfer['status'] ?? '') ?> Notes
                </div>
                <div class="text-sm text-slate-700 italic">
                    "<?= htmlspecialchars($transfer['approval_notes'] ?? $transfer['rejection_notes'] ?? '') ?>"
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Action Buttons -->
        <div class="px-6 pb-5 flex gap-3 justify-end border-t border-slate-100 pt-4">
            <?php if (($transfer['status'] ?? '') === 'completed' && $auth->can('approvals.process')): ?>
                <button onclick="reverseTransfer()" class="btn btn-danger btn-sm">
                    <i class="fa-solid fa-rotate-left"></i> Reverse Transfer
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
async function reverseTransfer() {
    const notes = prompt('Reversal notes are mandatory. Please state your reason for reversing this transfer:');
    if (!notes || notes.trim().length < 10) {
        window.showToast('error', 'Reversal notes must be at least 10 characters long.');
        return;
    }

    const fd = new FormData();
    fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
    fd.append('reversal_notes', notes.trim());

    try {
        const r = await fetch('<?= APP_URL ?>/transfers/<?= $transfer['id'] ?? 0 ?>/reverse', {
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
        window.showToast('error', 'Network error. Please try again.');
    }
}
</script>