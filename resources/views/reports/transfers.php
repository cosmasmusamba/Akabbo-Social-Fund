<?php
use App\Helpers\Format;
$pageTitle   = 'Transfers Report';
$activePage  = 'reports';
$breadcrumbs = ['Reports' => APP_URL.'/reports', 'Transfers' => null];
$data    = $data ?? [];
$totals  = $totals ?? [];
$filters = $filters ?? ['from' => date('Y-m-01'), 'to' => date('Y-m-d')];
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Transfers Report</h1>
        <p class="text-sm text-slate-400 mt-0.5">Member-to-member fund transfers</p>
    </div>
    <?php if (!empty($data)): ?>
    <a href="<?= APP_URL ?>/reports/transfers?export=1&from=<?= $filters['from'] ?>&to=<?= $filters['to'] ?>" class="btn btn-secondary btn-sm">
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
            <a href="<?= APP_URL ?>/reports/transfers" class="btn btn-secondary btn-sm">Reset</a>
        </form>
    </div>
</div>

<!-- KPIs -->
<div class="grid grid-cols-2 md:grid-cols-2 gap-4 mb-5">
    <div class="stat-card border-l-4 border-blue-500">
        <div class="text-xs font-semibold text-slate-400 uppercase">Total Transferred</div>
        <div class="text-2xl font-black text-blue-600 mt-1"><?= Format::currency($totals['total_transferred'] ?? 0) ?></div>
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
                    <th>From Member</th>
                    <th>To Member</th>
                    <th class="text-right">Amount</th>
                    <th>Description</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data)): ?>
                    <tr><td colspan="7" class="text-center py-10 text-slate-400">No transfers found for this period.</td></tr>
                <?php else: foreach ($data as $row): ?>
                    <tr>
                        <td class="text-sm"><?= Format::date($row['transfer_date'] ?? '') ?></td>
                        <td><span class="font-mono text-xs bg-slate-100 px-2 py-1 rounded"><?= htmlspecialchars($row['transfer_ref'] ?? '') ?></span></td>
                        <td>
                            <div class="text-sm font-semibold"><?= htmlspecialchars($row['from_name'] ?? '') ?></div>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars($row['from_no'] ?? '') ?></div>
                        </td>
                        <td>
                            <div class="text-sm font-semibold"><?= htmlspecialchars($row['to_name'] ?? '') ?></div>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars($row['to_no'] ?? '') ?></div>
                        </td>
                        <td class="text-right font-bold text-slate-800"><?= Format::currency($row['amount'] ?? 0) ?></td>
                        <td class="text-sm text-slate-500 max-w-xs truncate" title="<?= htmlspecialchars($row['description'] ?? '') ?>"><?= htmlspecialchars(Format::truncate($row['description'] ?? '', 40)) ?></td>
                        <td><?= Format::statusPill($row['status'] ?? 'pending') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>