<?php
use App\Helpers\Format;

$pageTitle   = 'Loan Products';
$activePage  = 'loan-products';
$breadcrumbs = ['Loan Products' => null];
$products    = $products ?? [];
?>

<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Loan Products</h1>
        <p class="text-sm text-slate-400 mt-0.5"><?= count($products) ?> products configured</p>
    </div>
    <?php if ($auth->can('settings.edit')): ?>
        <a href="<?= APP_URL ?>/loan-products/create" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus"></i> New Product
        </a>
    <?php endif; ?>
</div>

<?php if (empty($products)): ?>
    <div class="card">
        <div class="card-body text-center py-16">
            <i class="fa-solid fa-file-contract text-6xl text-slate-200 block mb-4"></i>
            <p class="text-slate-500 font-semibold text-lg mb-1">No loan products yet</p>
            <p class="text-slate-400 text-sm mb-5">Configure loan types, interest rates, and terms for your members</p>
            <?php if ($auth->can('settings.edit')): ?>
                <a href="<?= APP_URL ?>/loan-products/create" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> Create First Product
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <?php foreach ($products as $p): ?>
            <div class="card <?= ($p['status'] ?? 'active') === 'inactive' ? 'opacity-60' : '' ?>">
                <div class="card-body">
                    <!-- Header -->
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <div class="font-bold text-slate-800 text-base"><?= htmlspecialchars($p['name'] ?? '') ?></div>
                            <div class="text-xs font-mono text-slate-400 mt-0.5"><?= htmlspecialchars($p['code'] ?? '') ?></div>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <?= Format::statusPill($p['status'] ?? 'active') ?>
                            <?php if ($auth->can('settings.edit')): ?>
                                <button onclick="toggleStatus(<?= (int)$p['id'] ?>, '<?= htmlspecialchars($p['status'] ?? 'active') ?>')"
                                        class="w-7 h-7 rounded-lg flex items-center justify-center transition-colors <?= ($p['status'] ?? 'active') === 'active' ? 'bg-emerald-100 text-emerald-600 hover:bg-emerald-200' : 'bg-slate-100 text-slate-400 hover:bg-slate-200' ?>"
                                        title="<?= ($p['status'] ?? 'active') === 'active' ? 'Deactivate' : 'Activate' ?>">
                                    <i class="fa-solid <?= ($p['status'] ?? 'active') === 'active' ? 'fa-toggle-on' : 'fa-toggle-off' ?> text-sm"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($p['description'])): ?>
                        <p class="text-xs text-slate-500 mb-3 leading-relaxed">
                            <?= htmlspecialchars(Format::truncate($p['description'], 70)) ?>
                        </p>
                    <?php endif; ?>

                    <!-- Key terms grid -->
                    <div class="grid grid-cols-2 gap-x-4 gap-y-2 mb-3">
                        <?php 
                        $terms = [
                            ['Interest', Format::percentage((float)($p['interest_rate'] ?? 0)) . ' p.a.'],
                            ['Type', Format::titleCase($p['interest_type'] ?? '')],
                            ['Min Loan', Format::currencyCompact((float)($p['min_amount'] ?? 0))],
                            ['Max Loan', Format::currencyCompact((float)($p['max_amount'] ?? 0))],
                            ['Min Term', ($p['min_term_months'] ?? 0) . ' mo'],
                            ['Max Term', ($p['max_term_months'] ?? 0) . ' mo'],
                        ];
                        foreach ($terms as [$label, $val]): 
                        ?>
                            <div class="flex justify-between text-xs border-b border-slate-50 pb-1">
                                <span class="text-slate-400 font-semibold"><?= $label ?></span>
                                <span class="font-bold text-slate-700"><?= $val ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Flags -->
                    <div class="flex flex-wrap gap-1.5 mb-4">
                        <?php if (($p['processing_fee_pct'] ?? 0) > 0): ?>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                <?= Format::percentage((float)$p['processing_fee_pct']) ?> Processing Fee
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($p['requires_guarantor'])): ?>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                Guarantor Required
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($p['requires_collateral'])): ?>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-50 text-purple-700 border border-purple-200">
                                Collateral Required
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Stats row -->
                    <div class="grid grid-cols-3 gap-2 py-2.5 border-t border-slate-100 mb-4 text-center">
                        <div>
                            <div class="font-black text-slate-800"><?= number_format($p['total_loans'] ?? 0) ?></div>
                            <div class="text-[10px] text-slate-400 font-semibold">Total</div>
                        </div>
                        <div>
                            <div class="font-black text-emerald-700"><?= number_format($p['active_loans'] ?? 0) ?></div>
                            <div class="text-[10px] text-slate-400 font-semibold">Active</div>
                        </div>
                        <div>
                            <div class="font-black text-amber-700"><?= Format::currencyCompact((float)($p['outstanding'] ?? 0)) ?></div>
                            <div class="text-[10px] text-slate-400 font-semibold">Outstanding</div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <?php if ($auth->can('settings.edit')): ?>
                        <a href="<?= APP_URL ?>/loan-products/<?= (int)$p['id'] ?>/edit" class="btn btn-secondary btn-sm w-full justify-center">
                            <i class="fa-solid fa-pen text-xs"></i> Edit Product
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
async function toggleStatus(id, current) {
    const action = current === 'active' ? 'deactivate' : 'activate';
    window.confirmAction({
        title: `${action.charAt(0).toUpperCase() + action.slice(1)} Product`,
        message: `Are you sure you want to ${action} this loan product?`,
        confirmText: action.charAt(0).toUpperCase() + action.slice(1),
        type: current === 'active' ? 'warning' : 'primary',
        onConfirm: async () => {
            const fd = new FormData();
            fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
            try {
                const r = await fetch(`<?= APP_URL ?>/loan-products/${id}/toggle`, {
                    method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd
                });
                const d = await r.json();
                if (d.success) { window.showToast('success', d.message); setTimeout(() => location.reload(), 800); }
                else window.showToast('error', d.message);
            } catch(e) { window.showToast('error', 'Request failed.'); }
        }
    });
}
</script>