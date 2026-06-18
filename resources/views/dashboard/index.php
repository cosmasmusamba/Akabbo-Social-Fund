<?php
use App\Helpers\Format;
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';

// Safe defaults for all dashboard variables
$dashStats          = $dashStats ?? [];
$chartData          = $chartData ?? ['months' => [], 'savings' => [], 'loans' => [], 'portfolio' => []];
$pendingLoans       = $pendingLoans ?? [];
$overdueLoans       = $overdueLoans ?? [];
$recentTransactions = $recentTransactions ?? [];
$overdueCount       = $overdueCount ?? 0;
$user               = $user ?? ['first_name' => 'Admin'];
?>
<style>
.gradient-green { background: linear-gradient(135deg, #136b55 0%, #0a4033 100%); }
.gradient-gold  { background: linear-gradient(135deg, #c9963a 0%, #8a6220 100%); }
.gradient-blue  { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); }
.gradient-rose  { background: linear-gradient(135deg, #e11d48 0%, #9f1239 100%); }
.kpi-icon { width: 48px; height: 48px; border-radius: 12px; display:flex; align-items:center; justify-content:center; }
.trend-up   { color: #10b981; } .trend-down { color: #ef4444; }
.quick-action { display:flex; flex-direction:column; align-items:center; gap:8px; padding:18px 14px;
background:#fff; border:1.5px solid #e8edf2; border-radius:14px; cursor:pointer;
transition:all 0.2s; text-decoration:none; text-align:center; }
.quick-action:hover { border-color: var(--green-bright); box-shadow:0 6px 20px rgba(26,143,111,0.12); transform:translateY(-2px); }
.quick-action .qa-icon { width:46px; height:46px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; }
.overdue-badge { background:#fef2f2; border:1px solid #fecaca; color:#dc2626; }
</style>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">
            Good <?= date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening') ?>,
            <span style="color:var(--green-mid)"><?= htmlspecialchars($user['first_name'] ?? 'Admin') ?></span> 👋
        </h1>
        <p class="text-slate-400 text-sm mt-0.5"><?= date('l, d F Y') ?> &nbsp;·&nbsp; <?= htmlspecialchars($settings['org_name'] ?? 'Akabbo Social Fund') ?></p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <?php if ($auth->can('members.create')): ?>
            <a href="<?= APP_URL ?>/members/create" class="btn btn-primary btn-sm"><i class="fa-solid fa-user-plus"></i> Add Member</a>
        <?php endif; ?>
        <?php if ($auth->can('loans.create')): ?>
            <a href="<?= APP_URL ?>/loans/create" class="btn btn-secondary btn-sm"><i class="fa-solid fa-file-circle-plus"></i> New Loan</a>
        <?php endif; ?>
        <?php if ($auth->can('savings.deposit')): ?>
            <a href="<?= APP_URL ?>/savings/deposit" class="btn btn-gold btn-sm"><i class="fa-solid fa-arrow-down-to-bracket"></i> Deposit</a>
        <?php endif; ?>
    </div>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="stat-card">
        <div class="flex items-start justify-between mb-3">
            <div>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Savings</p>
                <h3 class="text-xl sm:text-2xl font-bold text-slate-800 mt-1"><?= Format::currencyCompact((float)($dashStats['total_savings'] ?? 0)) ?></h3>
            </div>
            <div class="kpi-icon gradient-green text-white text-lg"><i class="fa-solid fa-piggy-bank"></i></div>
        </div>
        <div class="flex items-center gap-1.5 text-xs">
            <span class="trend-up font-semibold"><i class="fa-solid fa-arrow-trend-up"></i> +<?= number_format($dashStats['savings_growth'] ?? 0, 1) ?>%</span>
            <span class="text-slate-400">vs last month</span>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="flex items-start justify-between mb-3">
            <div>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Loan Portfolio</p>
                <h3 class="text-xl sm:text-2xl font-bold text-slate-800 mt-1"><?= Format::currencyCompact((float)($dashStats['outstanding_balance'] ?? 0)) ?></h3>
            </div>
            <div class="kpi-icon gradient-gold text-white text-lg"><i class="fa-solid fa-hand-holding-dollar"></i></div>
        </div>
        <div class="flex items-center gap-1.5 text-xs">
            <span class="font-semibold text-amber-600"><?= number_format($dashStats['active_loans'] ?? 0) ?> active</span>
            <span class="text-slate-400">of <?= number_format($dashStats['total_loans'] ?? 0) ?> loans</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="flex items-start justify-between mb-3">
            <div>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Active Members</p>
                <h3 class="text-xl sm:text-2xl font-bold text-slate-800 mt-1"><?= number_format($dashStats['active_members'] ?? 0) ?></h3>
            </div>
            <div class="kpi-icon gradient-blue text-white text-lg"><i class="fa-solid fa-users"></i></div>
        </div>
        <div class="flex items-center gap-1.5 text-xs">
            <span class="trend-up font-semibold"><i class="fa-solid fa-user-plus"></i> +<?= $dashStats['new_this_month'] ?? 0 ?></span>
            <span class="text-slate-400">new this month</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="flex items-start justify-between mb-3">
            <div>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Today's Collections</p>
                <h3 class="text-xl sm:text-2xl font-bold text-slate-800 mt-1"><?= Format::currencyCompact((float)($dashStats['collections_today'] ?? 0)) ?></h3>
            </div>
            <div class="kpi-icon gradient-rose text-white text-lg"><i class="fa-solid fa-money-bill-trend-up"></i></div>
        </div>
        <div class="flex items-center gap-1.5 text-xs">
            <span class="font-semibold text-slate-600"><?= $dashStats['txns_today'] ?? 0 ?> transactions</span>
            <span class="text-slate-400">today</span>
        </div>
    </div>
</div>

<!-- Row 2: Charts + Pending -->
<div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-5">
    <div class="card xl:col-span-2">
        <div class="card-header">
            <div>
                <h3 class="section-title">Financial Overview</h3>
                <p class="text-xs text-slate-400 mt-0.5">Savings vs Loan disbursements — last 6 months</p>
            </div>
        </div>
        <div class="card-body">
            <canvas id="overviewChart" height="200"></canvas>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h3 class="section-title">Loan Portfolio</h3></div>
        <div class="card-body">
            <canvas id="portfolioChart" height="190"></canvas>
            <div class="mt-4 space-y-2" id="portfolioLegend"></div>
        </div>
    </div>
</div>

<!-- Row 3: Quick actions + Pending loans + Activity -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
    <div class="card">
        <div class="card-header"><h3 class="section-title">Quick Actions</h3></div>
        <div class="card-body">
            <div class="grid grid-cols-2 gap-3">
                <?php if ($auth->can('savings.deposit')): ?>
                    <a href="<?= APP_URL ?>/savings/deposit" class="quick-action">
                        <div class="qa-icon" style="background:#ecfdf5;color:#059669"><i class="fa-solid fa-arrow-down-to-bracket"></i></div>
                        <span class="text-xs font-semibold text-slate-700">Deposit</span>
                    </a>
                <?php endif; ?>
                <?php if ($auth->can('savings.withdraw')): ?>
                    <a href="<?= APP_URL ?>/savings/withdraw" class="quick-action">
                        <div class="qa-icon" style="background:#fff7ed;color:#ea580c"><i class="fa-solid fa-arrow-up-from-bracket"></i></div>
                        <span class="text-xs font-semibold text-slate-700">Withdraw</span>
                    </a>
                <?php endif; ?>
                <?php if ($auth->can('loans.create')): ?>
                    <a href="<?= APP_URL ?>/loans/create" class="quick-action">
                        <div class="qa-icon" style="background:#eff6ff;color:#2563eb"><i class="fa-solid fa-file-contract"></i></div>
                        <span class="text-xs font-semibold text-slate-700">New Loan</span>
                    </a>
                <?php endif; ?>
                <?php if ($auth->can('savings.deposit')): ?>
                    <a href="<?= APP_URL ?>/loans/repayment" class="quick-action">
                        <div class="qa-icon" style="background:#fdf4ff;color:#9333ea"><i class="fa-solid fa-circle-dollar-to-slot"></i></div>
                        <span class="text-xs font-semibold text-slate-700">Repayment</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="section-title">Pending Approvals</h3>
            <?php if ($auth->can('loans.view')): ?>
                <a href="<?= APP_URL ?>/loans?status=pending" class="text-xs font-semibold" style="color:var(--green-mid)">View all</a>
            <?php endif; ?>
        </div>
        <div class="divide-y divide-slate-50">
            <?php if (empty($pendingLoans)): ?>
                <div class="px-5 py-8 text-center">
                    <i class="fa-regular fa-circle-check text-3xl text-slate-200 mb-2 block"></i>
                    <p class="text-sm text-slate-400">No pending approvals</p>
                </div>
            <?php else: ?>
                <?php foreach (array_slice($pendingLoans, 0, 5) as $loan): ?>
                <div class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50 transition-colors">
                    <div class="avatar-circle w-9 h-9 text-xs flex-shrink-0"><?= Format::initials($loan['member_name'] ?? 'Unknown') ?></div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold text-slate-800 truncate"><?= htmlspecialchars($loan['member_name'] ?? '') ?></div>
                        <div class="text-xs text-slate-400"><?= htmlspecialchars($loan['loan_no'] ?? '') ?> · <?= htmlspecialchars($loan['product_name'] ?? '') ?></div>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <div class="text-sm font-bold" style="color:var(--green-mid)"><?= Format::currencyCompact((float)($loan['principal_amount'] ?? 0)) ?></div>
                        <?php if ($auth->can('loans.approve')): ?>
                            <a href="<?= APP_URL ?>/loans/<?= $loan['id'] ?>/approve" class="text-[10px] font-semibold px-2 py-0.5 rounded-full" style="background:#dcfce7;color:#166534">Review</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="section-title">Recent Activity</h3>
            <?php if ($auth->can('transactions.view')): ?>
                <a href="<?= APP_URL ?>/transactions" class="text-xs font-semibold" style="color:var(--green-mid)">All transactions</a>
            <?php endif; ?>
        </div>
        <div class="divide-y divide-slate-50">
            <?php if (empty($recentTransactions)): ?>
                <div class="px-5 py-8 text-center">
                    <i class="fa-regular fa-clock text-3xl text-slate-200 mb-2 block"></i>
                    <p class="text-sm text-slate-400">No recent activity</p>
                </div>
            <?php else: ?>
                <?php
                $txnIcons = [
                    'deposit'          => ['bg'=>'#dcfce7','color'=>'#16a34a','icon'=>'fa-arrow-down'],
                    'withdrawal'       => ['bg'=>'#fff7ed','color'=>'#ea580c','icon'=>'fa-arrow-up'],
                    'loan_disbursement'=> ['bg'=>'#eff6ff','color'=>'#2563eb','icon'=>'fa-hand-holding-dollar'],
                    'loan_repayment'   => ['bg'=>'#fdf4ff','color'=>'#9333ea','icon'=>'fa-circle-dollar-to-slot'],
                ];
                foreach (array_slice($recentTransactions, 0, 6) as $txn):
                    $ic = $txnIcons[$txn['txn_type']] ?? ['bg'=>'#f1f5f9','color'=>'#64748b','icon'=>'fa-arrow-right-arrow-left'];
                ?>
                <div class="flex items-center gap-3 px-5 py-3">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 text-sm" style="background:<?= $ic['bg'] ?>;color:<?= $ic['color'] ?>">
                        <i class="fa-solid <?= $ic['icon'] ?>"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-semibold text-slate-700 truncate"><?= htmlspecialchars($txn['member_name'] ?? '—') ?></div>
                        <div class="text-[10px] text-slate-400"><?= Format::timeAgo($txn['created_at'] ?? date('Y-m-d H:i:s')) ?></div>
                    </div>
                    <div class="text-xs font-bold <?= str_contains($txn['txn_type'] ?? '', 'deposit') || str_contains($txn['txn_type'] ?? '', 'repayment') ? 'text-emerald-600' : 'text-slate-700' ?>">
                        <?= Format::currencyCompact((float)($txn['amount'] ?? 0)) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Chart scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function() {
    const months  = <?= json_encode($chartData['months'] ?? []) ?>;
    const savings = <?= json_encode($chartData['savings'] ?? []) ?>;
    const loans   = <?= json_encode($chartData['loans'] ?? []) ?>;
    
    new Chart(document.getElementById('overviewChart'), {
        type: 'bar',
        data: {
            labels: months,
            datasets: [
                { label: 'Savings', data: savings, backgroundColor: 'rgba(19,107,85,0.85)', borderRadius: 6, borderSkipped: false },
                { label: 'Loans Disbursed', data: loans, backgroundColor: 'rgba(201,150,58,0.75)', borderRadius: 6, borderSkipped: false }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: true,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ' USh ' + ctx.raw.toLocaleString() } } },
            scales: {
                x: { grid: { display: false }, border: { display: false } },
                y: { grid: { color: '#f1f5f9' }, border: { display: false }, ticks: { callback: v => v >= 1e6 ? (v/1e6).toFixed(1)+'M' : v >= 1e3 ? (v/1e3).toFixed(0)+'K' : v, font: { size: 11 } } }
            }
        }
    });

    const portfolioData   = <?= json_encode($chartData['portfolio'] ?? []) ?>;
    const portfolioColors = ['#136b55','#10b981','#f59e0b','#ef4444'];
    const portfolioLabels = Object.keys(portfolioData);
    const portfolioValues = Object.values(portfolioData);
    
    new Chart(document.getElementById('portfolioChart'), {
        type: 'doughnut',
        data: {
            labels: portfolioLabels,
            datasets: [{ data: portfolioValues, backgroundColor: portfolioColors, borderWidth: 3, borderColor: '#fff', hoverOffset: 8 }]
        },
        options: { responsive: true, cutout: '68%', plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.raw} loans` } } } }
    });

    const legend = document.getElementById('portfolioLegend');
    const total = portfolioValues.reduce((a,b)=>a+b,0) || 1;
    portfolioLabels.forEach((l, i) => {
        const pct = Math.round(portfolioValues[i]/total*100);
        legend.innerHTML += `<div class="flex items-center justify-between text-xs"><span class="flex items-center gap-2"><span class="w-3 h-3 rounded-sm inline-block" style="background:${portfolioColors[i]}"></span><span class="text-slate-600 font-medium">${l}</span></span><span class="font-bold text-slate-800">${portfolioValues[i]} <span class="text-slate-400 font-normal">(${pct}%)</span></span></div>`;
    });
})();
</script>