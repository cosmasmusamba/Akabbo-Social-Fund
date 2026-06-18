<?php
use App\Helpers\Format;

$pageTitle    = 'Shares — ' . ($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '');
$activePage   = 'shares';
$breadcrumbs  = ['Shares' => APP_URL.'/shares', ($member['first_name'] ?? 'Member') => null];

$holding      = $holding ?? null;
$txns         = $txns ?? ['data' => [], 'total' => 0];
$config       = $config ?? ['par_value' => 1000, 'loan_rate_discount' => 2, 'loan_multiplier_bonus' => 1, 'dividend_rate' => 5];
$privileges   = $privileges ?? ['is_shareholder' => false, 'interest_rate' => 10, 'multiplier' => 3, 'discount' => 0, 'shares_held' => 0];
?>

<div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-5">
        <div class="breadcrumb">
            <a href="<?= APP_URL ?>/shares" class="text-slate-500 hover:text-slate-700">Shares</a>
            <span class="sep">/</span>
            <span class="current"><?= htmlspecialchars(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')) ?></span>
        </div>
        <?php if ($auth->can('shares.manage')): ?>
            <a href="<?= APP_URL ?>/shares/create" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-plus"></i> Issue More Shares
            </a>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <!-- Left: Profile & Holdings -->
        <div class="space-y-5">
            <div class="card">
                <div class="card-body text-center py-6" style="background:linear-gradient(135deg,#f0fdf9,#ecfdf5);border-radius:14px;">
                    <div class="avatar-circle w-16 h-16 text-xl mx-auto mb-3" style="border:3px solid rgba(255,255,255,0.3)">
                        <?= Format::initials(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')) ?>
                    </div>
                    <div class="text-xl font-bold text-slate-800 mb-0.5"><?= htmlspecialchars(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')) ?></div>
                    <div class="text-sm font-mono text-slate-500 mb-3"><?= htmlspecialchars($member['member_no'] ?? '') ?></div>
                    
                    <?php if ($holding && ($holding['status'] ?? '') === 'active'): ?>
                        <div class="mt-4 pt-4 border-t border-emerald-200">
                            <div class="text-3xl font-black text-emerald-700"><?= number_format($holding['shares_held'] ?? 0) ?></div>
                            <div class="text-xs text-slate-500 uppercase tracking-wider font-semibold mt-0.5">Shares Held</div>
                            <div class="text-sm font-bold text-slate-700 mt-2"><?= Format::currency((float)($holding['total_invested'] ?? 0)) ?> Invested</div>
                        </div>
                    <?php else: ?>
                        <div class="mt-4 px-3 py-2 rounded-xl text-sm" style="background:rgba(0,0,0,0.05);color:#64748b">
                            Not yet a shareholder
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Loan Privileges -->
            <div class="card">
                <div class="card-header">
                    <h3 class="section-title"><i class="fa-solid fa-star mr-1.5 text-amber-500"></i> Loan Privileges</h3>
                </div>
                <div class="card-body">
                    <?php if ($privileges['is_shareholder']): ?>
                        <div class="space-y-3">
                            <div class="flex justify-between py-2 border-b border-slate-50">
                                <span class="text-sm text-slate-500">Effective Rate</span>
                                <span class="font-bold text-emerald-700"><?= Format::percentage((float)$privileges['interest_rate']) ?> p.a.</span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-slate-50">
                                <span class="text-sm text-slate-500">Rate Discount</span>
                                <span class="font-bold text-amber-600">-<?= Format::percentage((float)$privileges['discount']) ?></span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-slate-50">
                                <span class="text-sm text-slate-500">Loan Multiplier</span>
                                <span class="font-bold text-blue-700"><?= (int)$privileges['multiplier'] ?>×</span>
                            </div>
                            <div class="flex justify-between py-2">
                                <span class="text-sm text-slate-500">Shares Held</span>
                                <span class="font-bold"><?= number_format((int)$privileges['shares_held']) ?></span>
                            </div>
                            <div class="p-3 rounded-xl text-xs" style="background:#f0fdf9;border:1px solid #a7f3d0;color:#065f46">
                                <i class="fa-solid fa-shield-check mr-1"></i> Shareholder privileges active — enhanced loan terms apply
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-6">
                            <i class="fa-regular fa-star text-4xl text-slate-200 block mb-2"></i>
                            <p class="text-sm text-slate-400">No shareholder privileges</p>
                            <p class="text-xs text-slate-400 mt-1">Issue shares to unlock enhanced loan terms</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: Transaction History -->
        <div class="lg:col-span-2">
            <div class="card">
                <div class="card-header">
                    <h3 class="section-title">Share Transaction History</h3>
                    <span class="text-xs text-slate-400"><?= number_format($txns['total'] ?? 0) ?> transactions</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Type</th>
                                <th class="hidden md:table-cell">Shares</th>
                                <th>Amount</th>
                                <th class="hidden md:table-cell">Date</th>
                                <th>Status</th>
                                <?php if ($auth->can('shares.approve')): ?>
                                    <th>Action</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($txns['data'])): ?>
                                <tr>
                                    <td colspan="<?= $auth->can('shares.approve') ? 7 : 6 ?>" class="text-center py-10">
                                        <i class="fa-solid fa-file-invoice text-4xl text-slate-200 block mb-2"></i>
                                        <p class="text-slate-400 text-sm">No share transactions yet</p>
                                    </td>
                                </tr>
                            <?php else: foreach ($txns['data'] as $t): 
                                $typeColors = [
                                    'purchase' => 'bg-emerald-100 text-emerald-700',
                                    'sale' => 'bg-red-100 text-red-700',
                                    'transfer_in' => 'bg-blue-100 text-blue-700',
                                    'transfer_out' => 'bg-orange-100 text-orange-700',
                                    'dividend' => 'bg-amber-100 text-amber-700',
                                ];
                                $tc = $typeColors[$t['txn_type']] ?? 'bg-slate-100 text-slate-600';
                            ?>
                                <tr>
                                    <td><span class="font-mono text-xs"><?= htmlspecialchars($t['txn_ref'] ?? '') ?></span></td>
                                    <td><span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $tc ?>"><?= ucwords(str_replace('_', ' ', $t['txn_type'] ?? '')) ?></span></td>
                                    <td class="hidden md:table-cell font-black text-blue-700"><?= number_format($t['shares_qty'] ?? 0) ?></td>
                                    <td class="font-semibold"><?= Format::currency((float)($t['total_amount'] ?? 0)) ?></td>
                                    <td class="hidden md:table-cell text-xs text-slate-400"><?= Format::date($t['transaction_date'] ?? '') ?></td>
                                    <td><?= Format::statusPill($t['status'] ?? 'pending') ?></td>
                                    <?php if ($auth->can('shares.approve') && ($t['status'] ?? '') === 'pending'): ?>
                                        <td>
                                            <div class="flex gap-1">
                                                <button onclick="approveShare(<?= (int)$t['id'] ?>, '<?= addslashes($t['txn_ref'] ?? '') ?>')" class="btn btn-sm py-1 px-2.5" style="background:#dcfce7;color:#166534;border:1px solid #a7f3d0;font-size:.75rem"><i class="fa-solid fa-check"></i></button>
                                                <button onclick="rejectShare(<?= (int)$t['id'] ?>, '<?= addslashes($t['txn_ref'] ?? '') ?>')" class="btn btn-danger btn-sm py-1 px-2.5" style="font-size:.75rem"><i class="fa-solid fa-xmark"></i></button>
                                            </div>
                                        </td>
                                    <?php else: ?>
                                        <td><span class="text-xs text-slate-300">—</span></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($auth->can('shares.approve')): ?>
<script>
async function approveShare(id, ref) {
    const notes = prompt('Approval notes (mandatory, min 10 chars):');
    if (!notes || notes.trim().length < 10) {
        window.showToast('error', 'Approval notes must be at least 10 characters.');
        return;
    }
    const fd = new FormData();
    fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
    fd.append('approval_notes', notes.trim());
    
    try {
        const r = await fetch(`<?= APP_URL ?>/shares/${id}/approve`, { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'}, body: fd });
        const d = await r.json();
        if (d.success) { window.showToast('success', d.message); setTimeout(() => location.reload(), 900); }
        else window.showToast('error', d.message);
    } catch(e) { window.showToast('error', 'Request failed.'); }
}

async function rejectShare(id, ref) {
    const notes = prompt('Rejection notes (mandatory):');
    if (!notes || notes.trim().length < 10) {
        window.showToast('error', 'Rejection notes must be at least 10 characters.');
        return;
    }
    const fd = new FormData();
    fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
    fd.append('rejection_notes', notes.trim());
    
    try {
        const r = await fetch(`<?= APP_URL ?>/shares/${id}/reject`, { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'}, body: fd });
        const d = await r.json();
        if (d.success) { window.showToast('success', d.message); setTimeout(() => location.reload(), 900); }
        else window.showToast('error', d.message);
    } catch(e) { window.showToast('error', 'Request failed.'); }
}
</script>
<?php endif; ?>