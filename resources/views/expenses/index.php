<?php
use App\Helpers\Format;
$pageTitle   = 'Expenses';
$activePage  = 'expenses';
$breadcrumbs = ['Expenses' => null];
?>

<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Operational Expenses</h1>
        <p class="text-sm text-slate-400 mt-0.5">Track and manage SACCO expenditures</p>
    </div>
    <?php if ($auth->can('expenses.create')): ?>
        <a href="<?= APP_URL ?>/expenses/create" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus"></i> New Expense
        </a>
    <?php endif; ?>
</div>

<!-- Summary Stats -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
    <?php 
    $statsMap = [
        ['label' => 'Total Expenses', 'val' => Format::currency((float)($stats['total_amount'] ?? 0)), 'color' => 'text-slate-800', 'icon' => 'fa-receipt'],
        ['label' => 'Approved', 'val' => Format::currency((float)($stats['approved_amount'] ?? 0)), 'color' => 'text-blue-700', 'icon' => 'fa-circle-check'],
        ['label' => 'Paid', 'val' => Format::currency((float)($stats['paid_amount'] ?? 0)), 'color' => 'text-emerald-700', 'icon' => 'fa-money-bill-wave'],
        ['label' => 'Pending', 'val' => Format::currency((float)($stats['pending_amount'] ?? 0)), 'color' => 'text-amber-700', 'icon' => 'fa-clock'],
    ];
    foreach ($statsMap as $s): 
    ?>
    <div class="stat-card text-center py-5">
        <i class="fa-solid <?= $s['icon'] ?> text-2xl <?= $s['color'] ?> mb-2 block"></i>
        <div class="text-xl font-black <?= $s['color'] ?>"><?= $s['val'] ?></div>
        <div class="text-xs text-slate-400 mt-1"><?= $s['label'] ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="card mb-5">
    <div class="card-body py-3">
        <form method="GET" class="flex flex-wrap gap-3">
            <div class="relative flex-1 min-w-[180px]">
                <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" placeholder="Search title, payee, ref…" class="form-control pl-9 py-2 text-sm">
            </div>
            <select name="status" class="form-control form-select py-2 text-sm w-auto" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach (['draft', 'pending', 'approved', 'paid', 'rejected'] as $s): ?>
                    <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="category" class="form-control form-select py-2 text-sm w-auto" onchange="this.form.submit()">
                <option value="">All Categories</option>
                <?php foreach ($categories ?? [] as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($filters['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="from" value="<?= htmlspecialchars($filters['from'] ?? date('Y-m-01')) ?>" class="form-control py-2 text-sm w-auto">
            <input type="date" name="to" value="<?= htmlspecialchars($filters['to'] ?? date('Y-m-d')) ?>" class="form-control py-2 text-sm w-auto">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
            <?php if (!empty($filters['search']) || !empty($filters['status']) || !empty($filters['category_id'])): ?>
                <a href="<?= APP_URL ?>/expenses" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i> Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Data Table -->
<div class="card">
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Title / Category</th>
                    <th>Amount</th>
                    <th class="hidden md:table-cell">Payee</th>
                    <th class="hidden md:table-cell">Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($result['data'])): ?>
                    <tr>
                        <td colspan="7" class="text-center py-12">
                            <i class="fa-solid fa-receipt text-5xl text-slate-200 block mb-3"></i>
                            <p class="text-slate-400">No expenses found for this period</p>
                        </td>
                    </tr>
                <?php else: foreach ($result['data'] as $e): ?>
                    <tr>
                        <td><span class="font-mono text-xs text-slate-600 bg-slate-100 px-2 py-0.5 rounded"><?= htmlspecialchars($e['expense_ref'] ?? '') ?></span></td>
                        <td>
                            <div class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($e['title'] ?? '') ?></div>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars($e['category_name'] ?? '') ?></div>
                        </td>
                        <td class="font-bold text-slate-800"><?= Format::currency((float)($e['amount'] ?? 0)) ?></td>
                        <td class="hidden md:table-cell text-sm"><?= htmlspecialchars($e['payee_name'] ?? '—') ?></td>
                        <td class="hidden md:table-cell text-xs text-slate-500"><?= Format::date($e['expense_date'] ?? '') ?></td>
                        <td><?= Format::statusPill($e['status'] ?? 'draft') ?></td>
                        <td>
                            <div class="flex gap-1">
                                <a href="<?= APP_URL ?>/expenses/<?= $e['id'] ?>" class="btn btn-secondary btn-sm py-1 px-2" title="View details">
                                    <i class="fa-solid fa-eye text-xs"></i>
                                </a>
                                <?php if (in_array($e['status'], ['draft', 'pending']) && $auth->can('expenses.create')): ?>
                                    <a href="<?= APP_URL ?>/expenses/<?= $e['id'] ?>/edit" class="btn btn-secondary btn-sm py-1 px-2" title="Edit">
                                        <i class="fa-solid fa-pen text-xs"></i>
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
        $base = APP_URL . '/expenses?' . ($qs ? $qs . '&' : '');
    ?>
        <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between">
            <p class="text-xs text-slate-400">
                Showing <strong><?= $result['from'] ?? 1 ?></strong>–<strong><?= $result['to'] ?? count($result['data']) ?></strong> of <strong><?= number_format($result['total']) ?></strong>
            </p>
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