<?php
use App\Helpers\Format;

$pageTitle   = 'Share Analytics';
$activePage  = 'shares';
$breadcrumbs = ['Shares' => APP_URL.'/shares', 'Analytics' => null];

$stats   = $stats ?? [];
$monthly = $monthly ?? [];
$config  = $config ?? ['par_value' => 1000, 'loan_rate_discount' => 2, 'loan_multiplier_bonus' => 1, 'dividend_rate' => 5];
$txns    = $txns ?? ['data' => [], 'total' => 0, 'page' => 1, 'last_page' => 1, 'from' => 0, 'to' => 0];
?>

<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Share Analytics</h1>
        <p class="text-sm text-slate-400 mt-0.5">Share capital distribution and monthly activity</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= APP_URL ?>/shares" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
        <?php if ($auth->can('shares.manage')): ?>
            <button onclick="openConfigModal()" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-sliders"></i> Configure
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <?php 
    $kpis = [
        ['Active Shareholders', number_format($stats['active_shareholders'] ?? 0), 'fa-users', 'text-blue-700', 'bg-blue-50'],
        ['Total Shares Issued', number_format($stats['total_shares_issued'] ?? 0), 'fa-chart-pie', 'text-emerald-700', 'bg-emerald-50'],
        ['Share Capital', Format::currencyCompact((float)($stats['total_share_capital'] ?? 0)), 'fa-coins', 'text-amber-700', 'bg-amber-50'],
        ['Avg per Member', number_format((float)($stats['avg_shares_per_member'] ?? 0), 1) . ' shares', 'fa-calculator', 'text-purple-700', 'bg-purple-50'],
    ];
    foreach ($kpis as [$l, $v, $ic, $cl, $bg]): 
    ?>
        <div class="stat-card text-center py-5 <?= $bg ?>">
            <i class="fa-solid <?= $ic ?> text-2xl <?= $cl ?> mb-2 block"></i>
            <div class="text-xl font-black <?= $cl ?>"><?= $v ?></div>
            <div class="text-xs text-slate-400 mt-1"><?= $l ?></div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Share config summary -->
<div class="card mb-5">
    <div class="card-header">
        <h3 class="section-title"><i class="fa-solid fa-gear mr-2" style="color:var(--green-mid)"></i>Current Share Configuration</h3>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <?php 
            $cfgItems = [
                ['Par Value per Share', Format::currency((float)$config['par_value']), 'text-slate-800'],
                ['Loan Rate Discount', '-' . Format::percentage((float)$config['loan_rate_discount']), 'text-emerald-700'],
                ['Multiplier Bonus', '+' . ((int)$config['loan_multiplier_bonus']) . '× loan limit', 'text-blue-700'],
                ['Annual Dividend Rate', Format::percentage((float)$config['dividend_rate']), 'text-amber-700'],
                ['Max Shares per Member', number_format((int)$config['max_shares_per_member']), 'text-slate-700'],
                ['Min Shares to Purchase', number_format((int)$config['min_shares']), 'text-slate-700'],
                ['≥100 shares extra discount', '-1.00% additional', 'text-purple-700'],
                ['Transferable', ((int)$config['is_transferable']) ? 'Yes' : 'No', 'text-slate-700'],
            ];
            foreach ($cfgItems as [$l, $v, $cl]): 
            ?>
                <div>
                    <div class="text-xs text-slate-400 font-semibold"><?= $l ?></div>
                    <div class="font-bold <?= $cl ?> mt-0.5"><?= $v ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Monthly chart + transaction list -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
    <!-- Monthly trend chart -->
    <div class="card lg:col-span-2">
        <div class="card-header">
            <h3 class="section-title">Monthly Share Activity</h3>
            <span class="text-xs text-slate-400">Last 12 months</span>
        </div>
        <div class="card-body">
            <canvas id="shareChart" height="220"></canvas>
        </div>
    </div>

    <!-- Summary stats -->
    <div class="card">
        <div class="card-header"><h3 class="section-title">12-Month Summary</h3></div>
        <div class="card-body">
            <?php
            $totalCapital   = array_sum(array_column($monthly, 'capital_raised'));
            $totalPurchases = array_sum(array_column($monthly, 'shares_bought'));
            $totalSales     = array_sum(array_column($monthly, 'shares_sold'));
            $totalDividends = array_sum(array_column($monthly, 'dividends_paid'));
            
            $summaryRows = [
                ['Capital Raised', Format::currency((float)$totalCapital), 'text-emerald-700'],
                ['Shares Purchased', number_format((int)$totalPurchases), 'text-blue-700'],
                ['Shares Sold', number_format((int)$totalSales), 'text-red-600'],
                ['Dividends Paid', Format::currency((float)$totalDividends), 'text-amber-700'],
                ['Net Shares Issued', number_format((int)($totalPurchases - $totalSales)), 'text-slate-800'],
            ];
            foreach ($summaryRows as [$l, $v, $cl]): 
            ?>
                <div class="flex justify-between py-2.5 border-b border-slate-50 last:border-0">
                    <span class="text-xs text-slate-500"><?= $l ?></span>
                    <span class="font-bold <?= $cl ?> text-sm"><?= $v ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Recent transactions -->
<div class="card">
    <div class="card-header">
        <h3 class="section-title">Recent Share Transactions</h3>
        <div class="flex gap-2">
            <?php foreach (['' => 'All', 'pending' => 'Pending', 'completed' => 'Completed'] as $v => $l): ?>
                <a href="?status=<?= $v ?>" class="btn btn-sm <?= ($_GET['status'] ?? '') === $v ? 'btn-primary' : 'btn-secondary' ?>">
                    <?= $l ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Member</th>
                    <th>Type</th>
                    <th class="hidden md:table-cell">Shares</th>
                    <th>Amount</th>
                    <th class="hidden md:table-cell">Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($txns['data'])): ?>
                    <tr><td colspan="7" class="text-center py-8 text-slate-400">No transactions found</td></tr>
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
                        <td>
                            <a href="<?= APP_URL ?>/shares/member/<?= $t['member_id'] ?? 0 ?>" class="font-semibold text-sm hover:text-green-700 transition-colors">
                                <?= htmlspecialchars($t['member_name'] ?? 'Unknown') ?>
                            </a>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars($t['member_no'] ?? '') ?></div>
                        </td>
                        <td><span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $tc ?>"><?= ucwords(str_replace('_', ' ', $t['txn_type'] ?? '')) ?></span></td>
                        <td class="hidden md:table-cell font-black text-blue-700"><?= number_format($t['shares_qty'] ?? 0) ?></td>
                        <td class="font-semibold"><?= Format::currency((float)($t['total_amount'] ?? 0)) ?></td>
                        <td class="hidden md:table-cell text-xs text-slate-400"><?= Format::date($t['transaction_date'] ?? '') ?></td>
                        <td><?= Format::statusPill($t['status'] ?? 'pending') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Config modal (reused from index) -->
<?php if ($auth->can('shares.manage')): ?>
<div id="configModal" class="hidden modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3 class="font-bold text-slate-800"><i class="fa-solid fa-sliders mr-2"></i>Share Configuration</h3>
            <button onclick="closeConfigModal()" class="text-slate-400 hover:text-slate-700"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <div class="modal-body">
            <form id="configForm">
                <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="form-label required">Par Value (<?= $settings['currency_symbol'] ?? 'USh' ?>)</label><input type="number" name="par_value" value="<?= $config['par_value'] ?? 1000 ?>" class="form-control" required min="100" step="100"></div>
                    <div><label class="form-label required">Rate Discount (%)</label><input type="number" name="loan_rate_discount" value="<?= $config['loan_rate_discount'] ?? 2 ?>" class="form-control" required step="0.5" min="0" max="20"></div>
                    <div><label class="form-label required">Multiplier Bonus</label><input type="number" name="loan_multiplier_bonus" value="<?= $config['loan_multiplier_bonus'] ?? 1 ?>" class="form-control" required min="0" max="5"></div>
                    <div><label class="form-label required">Dividend Rate (%)</label><input type="number" name="dividend_rate" value="<?= $config['dividend_rate'] ?? 5 ?>" class="form-control" required step="0.5" min="0" max="50"></div>
                    <div><label class="form-label">Min Shares</label><input type="number" name="min_shares" value="<?= $config['min_shares'] ?? 1 ?>" class="form-control" min="1"></div>
                    <div><label class="form-label">Max Shares per Member</label><input type="number" name="max_shares_per_member" value="<?= $config['max_shares_per_member'] ?? 1000 ?>" class="form-control" min="1"></div>
                </div>
                <div class="flex items-center gap-2 mt-3">
                    <input type="checkbox" name="is_transferable" value="1" id="isTransferable" <?= ($config['is_transferable'] ?? 0) ? 'checked' : '' ?> style="accent-color:var(--green-mid);width:16px;height:16px">
                    <label for="isTransferable" class="text-sm text-slate-700">Allow share transfers between members</label>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button onclick="closeConfigModal()" class="btn btn-secondary">Cancel</button>
            <button onclick="saveConfig()" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Config</button>
        </div>
    </div>
</div>

<script>
function openConfigModal()  { document.getElementById('configModal').classList.remove('hidden'); }
function closeConfigModal() { document.getElementById('configModal').classList.add('hidden'); }
async function saveConfig() {
    const fd = new FormData(document.getElementById('configForm'));
    try {
        const r = await fetch('<?= APP_URL ?>/shares/config', { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'}, body: fd });
        const d = await r.json();
        if (d.success) { window.showToast('success', d.message); closeConfigModal(); setTimeout(() => location.reload(), 900); }
        else window.showToast('error', d.message);
    } catch(e) { window.showToast('error', 'Request failed.'); }
}

// Chart.js
const ctx = document.getElementById('shareChart')?.getContext('2d');
if (ctx) {
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($monthly, 'period')) ?: '[]' ?>,
            datasets: [
                { label: 'Capital Raised', data: <?= json_encode(array_map(fn($m) => (float)$m['capital_raised'], $monthly)) ?: '[]' ?>, backgroundColor: 'rgba(16,185,129,0.8)', borderRadius: 5 },
                { label: 'Dividends Paid', data: <?= json_encode(array_map(fn($m) => (float)$m['dividends_paid'], $monthly)) ?: '[]' ?>, backgroundColor: 'rgba(245,158,11,0.75)', borderRadius: 5 }
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
}
</script>
<?php endif; ?>