<?php
use App\Helpers\Format;
$pageTitle   = 'Expenses Report';
$activePage  = 'reports';
$breadcrumbs = ['Reports' => APP_URL.'/reports', 'Expenses' => null];
$data    = $data ?? [];
$totals  = $totals ?? [];
$filters = $filters ?? ['from' => date('Y-m-01'), 'to' => date('Y-m-d'), 'status' => ''];
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Expenses Report</h1>
        <p class="text-sm text-slate-400 mt-0.5">Organizational expenditure tracking</p>
    </div>
    <?php if (!empty($data)): ?>
    <a href="<?= APP_URL ?>/reports/expenses?export=1&from=<?= $filters['from'] ?>&to=<?= $filters['to'] ?>&status=<?= $filters['status'] ?>" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-file-csv text-green-600"></i> Export CSV
    </a>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="card mb-5">
    <div class="card-body py-3">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="text-xs font-semibold text-slate-500 mb-1 block">From</label>
                <input type="date" name="from" value="<?= htmlspecialchars($filters['from']) ?>" class="form-control py-2 text-sm w-40">
            </div>
            <div>
                <label class="text-xs font-semibold text-slate-500 mb-1 block">To</label>
                <input type="date" name="to" value="<?= htmlspecialchars($filters['to']) ?>" class="form-control py-2 text-sm w-40">
            </div>
            <div>
                <label class="text-xs font-semibold text-slate-500 mb-1 block">Status</label>
                <select name="status" class="form-control form-select py-2 text-sm w-40">
                    <option value="">All Statuses</option>
                    <?php foreach (['pending', 'approved', 'paid', 'rejected'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
            <a href="<?= APP_URL ?>/reports/expenses" class="btn btn-secondary btn-sm">Reset</a>
        </form>
    </div>
</div>

<!-- KPIs -->
<div class="grid grid-cols-2 md:grid-cols-2 gap-4 mb-5">
    <div class="stat-card border-l-4 border-red-500">
        <div class="text-xs font-semibold text-slate-400 uppercase">Total Expenses</div>
        <div class="text-2xl font-black text-red-600 mt-1"><?= Format::currency($totals['total_expenses'] ?? 0) ?></div>
    </div>
    <div class="stat-card border-l-4 border-slate-400">
        <div class="text-xs font-semibold text-slate-400 uppercase">Transactions</div>
        <div class="text-2xl font-black text-slate-600 mt-1"><?= number_format($totals['txn_count'] ?? 0) ?></div>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Reference</th>
                    <th>Category</th>
                    <th>Title / Payee</th>
                    <th class="text-right">Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data)): ?>
                    <tr><td colspan="7" class="text-center py-10 text-slate-400">No expenses found for this period.</td></tr>
                <?php else: foreach ($data as $row): ?>
                    <tr>
                        <td class="text-sm"><?= Format::date($row['expense_date'] ?? '') ?></td>
                        <td><span class="font-mono text-xs bg-slate-100 px-2 py-1 rounded"><?= htmlspecialchars($row['expense_ref'] ?? '') ?></span></td>
                        <td><span class="badge bg-slate-100 text-slate-700"><?= htmlspecialchars($row['category_name'] ?? '') ?></span></td>
                        <td>
                            <div class="text-sm font-semibold"><?= htmlspecialchars($row['title'] ?? '') ?></div>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars($row['payee_name'] ?? 'No payee specified') ?></div>
                        </td>
                        <td class="text-right font-bold text-slate-800"><?= Format::currency($row['amount'] ?? 0) ?></td>
                        <td class="text-xs capitalize"><?= str_replace('_', ' ', $row['payment_method'] ?? '') ?></td>
                        <td><?= Format::statusPill($row['status'] ?? 'pending') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>