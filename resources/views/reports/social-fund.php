<?php
use App\Helpers\Format;
$pageTitle   = 'Social Fund Report';
$activePage  = 'reports';
$breadcrumbs = ['Reports' => APP_URL.'/reports', 'Social Fund' => null];
$data    = $data ?? [];
$totals  = $totals ?? [];
$filters = $filters ?? ['from' => date('Y-m-01'), 'to' => date('Y-m-d')];
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Social Fund Report</h1>
        <p class="text-sm text-slate-400 mt-0.5">Welfare and social fee collections</p>
    </div>
    <?php if (!empty($data)): ?>
    <a href="<?= APP_URL ?>/reports/export?type=social_fund&from=<?= $filters['from'] ?>&to=<?= $filters['to'] ?>" class="btn btn-secondary btn-sm">
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
            <a href="<?= APP_URL ?>/reports/social-fund" class="btn btn-secondary btn-sm">Reset</a>
        </form>
    </div>
</div>

<!-- KPIs -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
    <div class="stat-card border-l-4 border-amber-500">
        <div class="text-xs font-semibold text-slate-400 uppercase">Total Due</div>
        <div class="text-2xl font-black text-amber-600 mt-1"><?= Format::currency($totals['total_due'] ?? 0) ?></div>
    </div>
    <div class="stat-card border-l-4 border-emerald-500">
        <div class="text-xs font-semibold text-slate-400 uppercase">Total Paid</div>
        <div class="text-2xl font-black text-emerald-600 mt-1"><?= Format::currency($totals['total_paid'] ?? 0) ?></div>
    </div>
    <div class="stat-card border-l-4 border-red-500">
        <div class="text-xs font-semibold text-slate-400 uppercase">Total Penalties</div>
        <div class="text-2xl font-black text-red-600 mt-1"><?= Format::currency($totals['total_penalty'] ?? 0) ?></div>
    </div>
    <div class="stat-card border-l-4 border-slate-400">
        <div class="text-xs font-semibold text-slate-400 uppercase">Records</div>
        <div class="text-2xl font-black text-slate-600 mt-1"><?= number_format($totals['txn_count'] ?? 0) ?></div>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Period</th>
                    <th>Member</th>
                    <th>Fee Name</th>
                    <th class="text-right">Amount Due</th>
                    <th class="text-right">Amount Paid</th>
                    <th class="text-right">Penalty</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data)): ?>
                    <tr><td colspan="7" class="text-center py-10 text-slate-400">No social fund records found for this period.</td></tr>
                <?php else: foreach ($data as $row): ?>
                    <tr>
                        <td class="text-sm font-semibold"><?= date('M Y', mktime(0, 0, 0, $row['period_month'], 1, $row['period_year'])) ?></td>
                        <td>
                            <div class="text-sm font-semibold"><?= htmlspecialchars($row['member_name'] ?? '') ?></div>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars($row['member_no'] ?? '') ?></div>
                        </td>
                        <td class="text-sm"><?= htmlspecialchars($row['fee_name'] ?? '') ?></td>
                        <td class="text-right font-bold text-slate-800"><?= Format::currency($row['amount_due'] ?? 0) ?></td>
                        <td class="text-right text-emerald-600 font-semibold"><?= Format::currency($row['amount_paid'] ?? 0) ?></td>
                        <td class="text-right text-red-500 text-sm"><?= ($row['penalty_paid'] ?? 0) > 0 ? Format::currency($row['penalty_paid']) : '—' ?></td>
                        <td><?= Format::statusPill($row['status'] ?? 'pending') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>