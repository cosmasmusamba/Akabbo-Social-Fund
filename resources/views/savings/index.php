<?php
use App\Helpers\Format;
use App\Helpers\Avatar;

$pageTitle   = 'Savings Accounts';
$activePage  = 'savings';
$breadcrumbs = ['Savings' => null];
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Savings Accounts</h1>
        <p class="text-sm text-slate-400 mt-0.5"><?= number_format($result['total'] ?? 0) ?> total accounts managed</p>
    </div>
    <div class="flex gap-2 flex-wrap">
        <?php if ($auth->can('savings.deposit')): ?>
            <a href="<?= APP_URL ?>/savings/deposit" class="btn btn-gold btn-sm"><i class="fa-solid fa-arrow-down-to-bracket"></i> Deposit</a>
            <a href="<?= APP_URL ?>/savings/withdraw" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-up-from-bracket"></i> Withdraw</a>
            <a href="<?= APP_URL ?>/savings/transfer" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-right-arrow-left"></i> Transfer</a>
        <?php endif; ?>
        <?php if ($auth->can('savings.edit')): ?>
            <button onclick="postInterest()" class="btn btn-secondary btn-sm" title="Post monthly interest to all active accounts">
                <i class="fa-solid fa-percent"></i> Post Interest
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Stats cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <?php
    $stats = [
        ['Total Savings', Format::currencyCompact((float)($summaryStats['total_savings'] ?? 0)), 'text-emerald-700', 'bg-emerald-50', 'fa-piggy-bank'],
        ['Total Accounts', number_format($summaryStats['total_accounts'] ?? 0), 'text-blue-700', 'bg-blue-50', 'fa-wallet'],
        ['Active', number_format($summaryStats['active_accounts'] ?? 0), 'text-green-600', 'bg-green-50', 'fa-circle-check'],
        ['Dormant', number_format($summaryStats['dormant_accounts'] ?? 0), 'text-amber-600', 'bg-amber-50', 'fa-moon']
    ];
    foreach ($stats as [$label, $val, $textColor, $bgColor, $icon]): ?>
        <div class="stat-card flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl <?= $bgColor ?> flex items-center justify-center flex-shrink-0">
                <i class="fa-solid <?= $icon ?> text-xl <?= $textColor ?>"></i>
            </div>
            <div>
                <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider"><?= $label ?></div>
                <div class="text-xl font-black text-slate-800 mt-0.5"><?= $val ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Filter bar -->
<div class="card mb-5">
    <div class="card-body py-3">
        <form method="GET" class="flex flex-wrap gap-3 items-center">
            <div class="relative flex-1 min-w-[200px]">
                <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Search account, member name, or number…" class="form-control pl-9 py-2 text-sm">
            </div>
            <select name="status" class="form-control form-select py-2 text-sm w-auto" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach (['active', 'dormant', 'frozen', 'closed'] as $s): ?>
                    <option value="<?= $s ?>" <?= ($_GET['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
            <?php if (!empty($_GET['search']) || !empty($_GET['status'])): ?>
                <a href="<?= APP_URL ?>/savings" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i> Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Savings accounts table -->
<div class="card">
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
            <tr>
                <th>Member</th>
                <th>Account No</th>
                <th class="hidden md:table-cell">Phone</th>
                <th>Type</th>
                <th class="text-right">Balance</th>
                <th>Status</th>
                <th class="hidden lg:table-cell">Opened</th>
                <th class="text-right">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($result['data'])): ?>
                <tr>
                    <td colspan="8" class="text-center py-16">
                        <i class="fa-solid fa-piggy-bank text-5xl text-slate-200 block mb-3"></i>
                        <p class="text-slate-400 font-semibold">No savings accounts found</p>
                    </td>
                </tr>
            <?php else: foreach ($result['data'] as $a): ?>
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <?= Avatar::small($a) ?>
                            <div>
                                <div class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($a['member_name'] ?? '') ?></div>
                                <div class="text-xs text-slate-400"><?= htmlspecialchars($a['member_no'] ?? '') ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="font-mono text-xs bg-slate-100 px-2 py-1 rounded border border-slate-200 text-slate-700">
                            <?= htmlspecialchars($a['account_no'] ?? '') ?>
                        </span>
                    </td>
                    <td class="hidden md:table-cell text-sm text-slate-600"><?= htmlspecialchars($a['phone'] ?? '') ?></td>
                    <td class="text-xs font-medium capitalize text-slate-600"><?= str_replace('_', ' ', $a['account_type'] ?? 'regular') ?></td>
                    <td class="text-right font-bold text-emerald-700"><?= Format::currency((float)($a['balance'] ?? 0)) ?></td>
                    <td><?= Format::statusPill($a['status'] ?? 'active') ?></td>
                    <td class="hidden lg:table-cell text-xs text-slate-500"><?= Format::date($a['opened_at'] ?? '') ?></td>
                    <td class="text-right">
                        <div class="flex gap-1 justify-end">
                            <a href="<?= APP_URL ?>/savings/<?= $a['id'] ?? 0 ?>" class="btn btn-secondary btn-sm py-1 px-2" title="View Details"><i class="fa-solid fa-eye text-xs"></i></a>
                            <a href="<?= APP_URL ?>/members/<?= $a['member_id'] ?? 0 ?>/statement" class="btn btn-secondary btn-sm py-1 px-2" target="_blank" title="Statement"><i class="fa-solid fa-file-pdf text-xs text-red-500"></i></a>
                            <?php if ($auth->can('savings.deposit')): ?>
                                <a href="<?= APP_URL ?>/savings/deposit?account=<?= $a['id'] ?? 0 ?>" class="btn btn-sm py-1 px-2" style="background:#dcfce7;color:#166534;border:1px solid #a7f3d0;font-size:0.7rem" title="Quick Deposit">
                                    <i class="fa-solid fa-plus text-xs"></i>
                                </a>
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
        $page = $result['page'] ?? 1;
        $lastPage = $result['last_page'] ?? 1;
        $qs = http_build_query(array_diff_key($_GET, ['page' => '']));
        $base = APP_URL . '/savings?' . ($qs ? $qs . '&' : '');
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
async function postInterest() {
    if (!confirm('Post monthly interest to all active savings accounts?\n\nThis will calculate and add interest based on current balances and create immutable ledger entries.')) return;
    
    const btn = event.target.closest('button');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';

    const fd = new FormData();
    fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '');

    try {
        const r = await fetch('<?= APP_URL ?>/savings/post-interest', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
        const d = await r.json();
        if (d.success) { 
            window.showToast('success', d.message); 
            setTimeout(() => location.reload(), 1000); 
        } else {
            window.showToast('error', d.message);
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    } catch (e) { 
        window.showToast('error', 'Request failed.'); 
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
}
</script>