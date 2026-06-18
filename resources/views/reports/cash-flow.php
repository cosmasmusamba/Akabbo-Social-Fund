<?php 
use App\Helpers\Format; 
$pageTitle   = "Cash Flow — {$year}"; 
$activePage  = 'reports'; 
$breadcrumbs = ['Reports' => APP_URL.'/reports', 'Cash Flow' => null]; 
$filters     = $filters ?? ['year' => $year];
$monthly     = $monthly ?? [];

// Calculate annual totals safely from monthly data
$yearTotal = [
    'inflow'  => array_sum(array_column($monthly, 'total_inflow')),
    'outflow' => array_sum(array_column($monthly, 'total_outflow')),
];
$yearTotal['net'] = $yearTotal['inflow'] - $yearTotal['outflow'];
?>
<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Cash Flow — <?= (int)$year ?></h1>
        <p class="text-sm text-slate-400">Annual inflow vs outflow analysis</p>
    </div>
    <div class="flex gap-2 items-center">
        <?php $prevY = (int)$year - 1; $nextY = (int)$year + 1; ?>
        <a href="?year=<?= $prevY ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-chevron-left"></i> <?= $prevY ?></a>
        <span class="font-bold text-slate-700"><?= (int)$year ?></span>
        <?php if ($nextY <= (int)date('Y')): ?>
            <a href="?year=<?= $nextY ?>" class="btn btn-secondary btn-sm"><?= $nextY ?> <i class="fa-solid fa-chevron-right"></i></a>
        <?php endif; ?>
    </div>
</div>

<!-- Annual Summary -->
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="stat-card text-center py-5">
        <div class="text-xl font-black text-emerald-700"><?= Format::currency((float)$yearTotal['inflow']) ?></div>
        <div class="text-xs text-slate-400 mt-1">Total Inflow</div>
    </div>
    <div class="stat-card text-center py-5">
        <div class="text-xl font-black text-orange-700"><?= Format::currency((float)$yearTotal['outflow']) ?></div>
        <div class="text-xs text-slate-400 mt-1">Total Outflow</div>
    </div>
    <div class="stat-card text-center py-5">
        <div class="text-xl font-black <?= $yearTotal['net'] >= 0 ? 'text-blue-700' : 'text-red-700' ?>">
            <?= Format::currency((float)$yearTotal['net']) ?>
        </div>
        <div class="text-xs text-slate-400 mt-1">Net Cash Flow</div>
    </div>
</div>

<!-- Chart -->
<div class="card mb-5">
    <div class="card-header"><h3 class="section-title">Monthly Cash Flow</h3></div>
    <div class="card-body"><canvas id="cashFlowChart" height="200"></canvas></div>
</div>

<!-- Table -->
<div class="card">
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Inflow (Deposits + Repayments)</th>
                    <th>Outflow (Withdrawals + Disbursements)</th>
                    <th>Net</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($monthly)): ?>
                    <tr><td colspan="4" class="text-center py-10 text-slate-400">No data available for this year</td></tr>
                <?php else: foreach ($monthly as $m): ?>
                <tr>
                    <td class="font-semibold"><?= htmlspecialchars($m['month'] ?? '') ?> <?= (int)$year ?></td>
                    <td class="text-emerald-700 font-semibold"><?= Format::currency((float)($m['total_inflow'] ?? 0)) ?></td>
                    <td class="text-orange-700 font-semibold"><?= Format::currency((float)($m['total_outflow'] ?? 0)) ?></td>
                    <td class="font-bold <?= ($m['net_flow'] ?? 0) >= 0 ? 'text-blue-700' : 'text-red-700' ?>">
                        <?= Format::currency((float)($m['net_flow'] ?? 0)) ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
            <tfoot>
                <tr class="bg-slate-50 font-bold">
                    <td>Annual Total</td>
                    <td class="text-emerald-700"><?= Format::currency((float)$yearTotal['inflow']) ?></td>
                    <td class="text-orange-700"><?= Format::currency((float)$yearTotal['outflow']) ?></td>
                    <td class="<?= $yearTotal['net'] >= 0 ? 'text-blue-700' : 'text-red-700' ?>">
                        <?= Format::currency((float)$yearTotal['net']) ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('cashFlowChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($monthly, 'month')) ?>,
        datasets: [
            { label: 'Inflow', data: <?= json_encode(array_column($monthly, 'total_inflow')) ?>, backgroundColor: 'rgba(16,185,129,0.8)', borderRadius: 5 },
            { label: 'Outflow', data: <?= json_encode(array_column($monthly, 'total_outflow')) ?>, backgroundColor: 'rgba(234,88,12,0.75)', borderRadius: 5 },
            { label: 'Net', data: <?= json_encode(array_column($monthly, 'net_flow')) ?>, type: 'line', borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.1)', borderWidth: 2, tension: 0.4, fill: true }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' }, tooltip: { callbacks: { label: c => ` USh ${c.raw.toLocaleString()}` } } },
        scales: {
            x: { grid: { display: false } },
            y: { grid: { color: '#f1f5f9' }, ticks: { callback: v => v >= 1e6 ? (v/1e6).toFixed(1)+'M' : v >= 1e3 ? (v/1e3).toFixed(0)+'K' : v } }
        }
    }
});
</script>