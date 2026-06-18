<?php
use App\Helpers\Format;
$pageTitle = 'Service Fees Report';
$activePage = 'reports';
$breadcrumbs = ['Reports' => APP_URL.'/reports', 'Service Fees' => null];
$summary = $summary ?? [];
$collectedFees = $collectedFees ?? [];
$pendingDebits = $pendingDebits ?? [];
$filters = $filters ?? ['from' => date('Y-m-01'), 'to' => date('Y-m-d')];
?>

<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Service Fees Report</h1>
        <p class="text-sm text-slate-400">Period: <?= Format::date($filters['from']) ?> — <?= Format::date($filters['to']) ?></p>
    </div>
    <a href="?<?= http_build_query(array_merge($_GET, ['export' => '1'])) ?>" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-file-csv text-green-600"></i> Export Collected
    </a>
</div>

<!-- Filters -->
<div class="card mb-5">
    <div class="card-body py-3">
        <form method="GET" class="flex flex-wrap gap-3">
            <input type="date" name="from" value="<?= htmlspecialchars($filters['from']) ?>" class="form-control py-2 text-sm w-auto">
            <input type="date" name="to" value="<?= htmlspecialchars($filters['to']) ?>" class="form-control py-2 text-sm w-auto">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Apply</button>
            <a href="<?= APP_URL ?>/reports/service-fees" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i> Clear</a>
        </form>
    </div>
</div>

<!-- KPIs -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="stat-card border-l-4 border-emerald-500">
        <div class="text-xs font-semibold text-slate-400 uppercase">Total Collected</div>
        <div class="text-2xl font-black text-emerald-600 mt-1"><?= Format::currency($summary['total_collected'] ?? 0) ?></div>
        <div class="text-xs text-slate-400 mt-1"><?= number_format($summary['collected_count'] ?? 0) ?> transactions</div>
    </div>
    <div class="stat-card border-l-4 border-amber-500">
        <div class="text-xs font-semibold text-slate-400 uppercase">Total Pending</div>
        <div class="text-2xl font-black text-amber-600 mt-1"><?= Format::currency($summary['total_pending'] ?? 0) ?></div>
        <div class="text-xs text-slate-400 mt-1"><?= number_format($summary['pending_count'] ?? 0) ?> members</div>
    </div>
</div>

<!-- Collected Fees Table -->
<div class="card mb-6">
    <div class="card-header">
        <h3 class="section-title"><i class="fa-solid fa-circle-check text-emerald-500 mr-2"></i> Collected Fees (<?= Format::date($filters['from']) ?> to <?= Format::date($filters['to']) ?>)</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Reference</th>
                    <th>Member</th>
                    <th>Fee Type</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($collectedFees)): ?>
                    <tr><td colspan="5" class="text-center py-8 text-slate-400">No fees collected in this period.</td></tr>
                <?php else: foreach ($collectedFees as $fee): ?>
                    <tr>
                        <td class="text-sm text-slate-500"><?= Format::date($fee['transaction_date']) ?></td>
                        <td><span class="font-mono text-xs bg-slate-100 px-2 py-0.5 rounded"><?= htmlspecialchars($fee['txn_ref']) ?></span></td>
                        <td>
                            <div class="text-sm font-semibold"><?= htmlspecialchars($fee['member_name']) ?></div>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars($fee['member_no']) ?></div>
                        </td>
                        <td class="text-xs capitalize"><?= str_replace('_', ' ', str_replace('_fee', '', $fee['txn_type'])) ?></td>
                        <td class="text-right font-bold text-emerald-700"><?= Format::currency((float)$fee['amount']) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pending Debits Table -->
<div class="card">
    <div class="card-header">
        <h3 class="section-title"><i class="fa-solid fa-clock text-amber-500 mr-2"></i> Pending Debits (Unsettled)</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date Incurred</th>
                    <th>Member</th>
                    <th>Reason</th>
                    <th class="text-right">Amount Due</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pendingDebits)): ?>
                    <tr><td colspan="4" class="text-center py-8 text-slate-400">No pending debits. All members are up to date!</td></tr>
                <?php else: foreach ($pendingDebits as $debit): ?>
                    <tr>
                        <td class="text-sm text-slate-500"><?= Format::date($debit['created_at']) ?></td>
                        <td>
                            <div class="text-sm font-semibold"><?= htmlspecialchars($debit['member_name']) ?></div>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars($debit['member_no']) ?></div>
                        </td>
                        <td class="text-xs capitalize"><?= str_replace('_', ' ', str_replace('_fee', '', $debit['reason'])) ?></td>
                        <td class="text-right font-bold text-amber-700"><?= Format::currency((float)$debit['amount']) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>