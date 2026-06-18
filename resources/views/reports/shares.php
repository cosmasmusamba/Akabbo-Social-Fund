<?php
use App\Helpers\Format;
$pageTitle   = 'Shares Report';
$activePage  = 'reports';
$breadcrumbs = ['Reports' => APP_URL.'/reports', 'Shares' => null];
$data    = $data ?? [];
$totals  = $totals ?? [];
$filters = $filters ?? ['from' => date('Y-m-01'), 'to' => date('Y-m-d')];
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Shares Report</h1>
        <p class="text-sm text-slate-400 mt-0.5">Share purchases and redemptions history</p>
    </div>
    <?php if (!empty($data)): ?>
    <a href="<?= APP_URL ?>/reports/shares?export=1&from=<?= $filters['from'] ?>&to=<?= $filters['to'] ?>" class="btn btn-secondary btn-sm">
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
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
            <a href="<?= APP_URL ?>/reports/shares" class="btn btn-secondary btn-sm">Reset</a>
        </form>
    </div>
</div>

<!-- KPIs -->
<div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-5">
    <div class="stat-card border-l-4 border-purple-500">
        <div class="text-xs font-semibold text-slate-400 uppercase">Total Purchased</div>
        <div class="text-2xl font-black text-purple-600 mt-1"><?= Format::currency($totals['total_purchased'] ?? 0) ?></div>
    </div>
    <div class="stat-card border-l-4 border-blue-500">
        <div class="text-xs font-semibold text-slate-400 uppercase">Total Shares</div>
        <div class="text-2xl font-black text-blue-600 mt-1"><?= number_format($totals['total_shares'] ?? 0) ?></div>
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
                    <th>Member</th>
                    <th>Type</th>
                    <th class="text-right">Shares Qty</th>
                    <th class="text-right">Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data)): ?>
                    <tr><td colspan="8" class="text-center py-10 text-slate-400">No share transactions found for this period.</td></tr>
                <?php else: foreach ($data as $row): ?>
                    <tr>
                        <td class="text-sm"><?= Format::date($row['transaction_date'] ?? '') ?></td>
                        <td><span class="font-mono text-xs bg-slate-100 px-2 py-1 rounded"><?= htmlspecialchars($row['txn_ref'] ?? '') ?></span></td>
                        <td>
                            <div class="text-sm font-semibold"><?= htmlspecialchars($row['member_name'] ?? '') ?></div>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars($row['member_no'] ?? '') ?></div>
                        </td>
                        <td><span class="badge <?= ($row['txn_type'] ?? '') === 'purchase' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' ?>"><?= ucfirst($row['txn_type'] ?? '') ?></span></td>
                        <td class="text-right font-bold"><?= number_format($row['shares_qty'] ?? 0) ?></td>
                        <td class="text-right font-bold text-slate-800"><?= Format::currency($row['total_amount'] ?? 0) ?></td>
                        <td class="text-xs capitalize"><?= str_replace('_', ' ', $row['payment_method'] ?? '') ?></td>
                        <td><?= Format::statusPill($row['status'] ?? 'pending') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>