<?php
use App\Helpers\Format;
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
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
  .chart-bar { border-radius: 6px 6px 0 0; transition: opacity 0.2s; cursor:pointer; }
  .chart-bar:hover { opacity: 0.8; }
</style>

<!-- ── Page header ──────────────────────────────────────────────── -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-bold text-slate-800">
      Good <?= date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening') ?>,
      <span style="color:var(--green-mid)"><?= htmlspecialchars($user['first_name'] ?? 'Admin') ?></span> 👋
    </h1>
    <p class="text-slate-400 text-sm mt-0.5"><?= date('l, d F Y') ?> &nbsp;·&nbsp; <?= htmlspecialchars($settings['org_name'] ?? 'Akabbo Social Fund') ?></p>
  </div>
  <div class="flex items-center gap-2 flex-wrap">
    <a href="<?= APP_URL ?>/members/create" class="btn btn-primary btn-sm">
      <i class="fa-solid fa-user-plus"></i> Add Member
    </a>
    <a href="<?= APP_URL ?>/loans/create" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-file-circle-plus"></i> New Loan
    </a>
    <a href="<?= APP_URL ?>/savings/deposit" class="btn btn-gold btn-sm">
      <i class="fa-solid fa-arrow-down-to-bracket"></i> Deposit
    </a>
  </div>
</div>

<!-- ── KPI Cards ─────────────────────────────────────────────────── -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

  <!-- Total Savings -->
  <div class="stat-card">
    <div class="flex items-start justify-between mb-3">
      <div>
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Savings</p>
        <h3 class="text-xl sm:text-2xl font-bold text-slate-800 mt-1">
          <?= Format::currencyCompact($dashStats['total_savings'] ?? 0) ?>
        </h3>
      </div>
      <div class="kpi-icon gradient-green text-white text-lg"><i class="fa-solid fa-piggy-bank"></i></div>
    </div>
    <div class="flex items-center gap-1.5 text-xs">
      <span class="trend-up font-semibold"><i class="fa-solid fa-arrow-trend-up"></i> +<?= $dashStats['savings_growth'] ?? '0' ?>%</span>
      <span class="text-slate-400">vs last month</span>
    </div>
  </div>

  <!-- Loans Outstanding -->
  <div class="stat-card">
    <div class="flex items-start justify-between mb-3">
      <div>
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Loan Portfolio</p>
        <h3 class="text-xl sm:text-2xl font-bold text-slate-800 mt-1">
          <?= Format::currencyCompact($dashStats['outstanding_balance'] ?? 0) ?>
        </h3>
      </div>
      <div class="kpi-icon gradient-gold text-white text-lg"><i class="fa-solid fa-hand-holding-dollar"></i></div>
    </div>
    <div class="flex items-center gap-1.5 text-xs">
      <span class="font-semibold text-amber-600"><?= number_format($dashStats['active_loans'] ?? 0) ?> active</span>
      <span class="text-slate-400">of <?= number_format($dashStats['total_loans'] ?? 0) ?> loans</span>
    </div>
  </div>

  <!-- Active Members -->
  <div class="stat-card">
    <div class="flex items-start justify-between mb-3">
      <div>
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Active Members</p>
        <h3 class="text-xl sm:text-2xl font-bold text-slate-800 mt-1">
          <?= number_format($dashStats['active_members'] ?? 0) ?>
        </h3>
      </div>
      <div class="kpi-icon gradient-blue text-white text-lg"><i class="fa-solid fa-users"></i></div>
    </div>
    <div class="flex items-center gap-1.5 text-xs">
      <span class="trend-up font-semibold"><i class="fa-solid fa-user-plus"></i> +<?= $dashStats['new_this_month'] ?? 0 ?></span>
      <span class="text-slate-400">new this month</span>
    </div>
  </div>

  <!-- Collections Today -->
  <div class="stat-card">
    <div class="flex items-start justify-between mb-3">
      <div>
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Today's Collections</p>
        <h3 class="text-xl sm:text-2xl font-bold text-slate-800 mt-1">
          <?= Format::currencyCompact($dashStats['collections_today'] ?? 0) ?>
        </h3>
      </div>
      <div class="kpi-icon gradient-rose text-white text-lg"><i class="fa-solid fa-money-bill-trend-up"></i></div>
    </div>
    <div class="flex items-center gap-1.5 text-xs">
      <span class="font-semibold text-slate-600"><?= $dashStats['txns_today'] ?? 0 ?> transactions</span>
      <span class="text-slate-400">today</span>
    </div>
  </div>
</div>

<!-- ── Row 2: Charts + Pending ────────────────────────────────────── -->
<div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-5">

  <!-- Monthly savings chart -->
  <div class="card xl:col-span-2">
    <div class="card-header">
      <div>
        <h3 class="section-title">Financial Overview</h3>
        <p class="text-xs text-slate-400 mt-0.5">Savings vs Loan disbursements — last 6 months</p>
      </div>
      <div class="flex items-center gap-2">
        <span class="flex items-center gap-1.5 text-xs text-slate-500">
          <span class="w-3 h-3 rounded-sm inline-block" style="background:var(--green-mid)"></span> Savings
        </span>
        <span class="flex items-center gap-1.5 text-xs text-slate-500">
          <span class="w-3 h-3 rounded-sm inline-block" style="background:var(--gold)"></span> Loans
        </span>
      </div>
    </div>
    <div class="card-body">
      <canvas id="overviewChart" height="200"></canvas>
    </div>
  </div>

  <!-- Portfolio donut -->
  <div class="card">
    <div class="card-header">
      <h3 class="section-title">Loan Portfolio</h3>
    </div>
    <div class="card-body">
      <canvas id="portfolioChart" height="190"></canvas>
      <div class="mt-4 space-y-2" id="portfolioLegend"></div>
    </div>
  </div>
</div>

<!-- ── Row 3: Quick actions + Pending loans + Activity ───────────── -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">

  <!-- Quick actions -->
  <div class="card">
    <div class="card-header"><h3 class="section-title">Quick Actions</h3></div>
    <div class="card-body">
      <div class="grid grid-cols-2 gap-3">
        <a href="<?= APP_URL ?>/savings/deposit" class="quick-action">
          <div class="qa-icon" style="background:#ecfdf5;color:#059669"><i class="fa-solid fa-arrow-down-to-bracket"></i></div>
          <span class="text-xs font-semibold text-slate-700">Deposit</span>
        </a>
        <a href="<?= APP_URL ?>/savings/withdraw" class="quick-action">
          <div class="qa-icon" style="background:#fff7ed;color:#ea580c"><i class="fa-solid fa-arrow-up-from-bracket"></i></div>
          <span class="text-xs font-semibold text-slate-700">Withdraw</span>
        </a>
        <a href="<?= APP_URL ?>/loans/create" class="quick-action">
          <div class="qa-icon" style="background:#eff6ff;color:#2563eb"><i class="fa-solid fa-file-contract"></i></div>
          <span class="text-xs font-semibold text-slate-700">New Loan</span>
        </a>
        <a href="<?= APP_URL ?>/loans/repayment" class="quick-action">
          <div class="qa-icon" style="background:#fdf4ff;color:#9333ea"><i class="fa-solid fa-circle-dollar-to-slot"></i></div>
          <span class="text-xs font-semibold text-slate-700">Repayment</span>
        </a>
        <a href="<?= APP_URL ?>/members/create" class="quick-action">
          <div class="qa-icon" style="background:#f0fdf4;color:#16a34a"><i class="fa-solid fa-user-plus"></i></div>
          <span class="text-xs font-semibold text-slate-700">Register</span>
        </a>
        <a href="<?= APP_URL ?>/reports" class="quick-action">
          <div class="qa-icon" style="background:#fef9c3;color:#ca8a04"><i class="fa-solid fa-chart-pie"></i></div>
          <span class="text-xs font-semibold text-slate-700">Reports</span>
        </a>
      </div>
    </div>
  </div>

  <!-- Pending loan approvals -->
  <div class="card">
    <div class="card-header">
      <h3 class="section-title">Pending Approvals</h3>
      <a href="<?= APP_URL ?>/loans?status=pending" class="text-xs font-semibold" style="color:var(--green-mid)">View all</a>
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
          <div class="avatar-circle w-9 h-9 text-xs flex-shrink-0">
            <?= Format::initials($loan['member_name']) ?>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-semibold text-slate-800 truncate"><?= htmlspecialchars($loan['member_name']) ?></div>
            <div class="text-xs text-slate-400"><?= $loan['loan_no'] ?> · <?= $loan['product_name'] ?></div>
          </div>
          <div class="text-right flex-shrink-0">
            <div class="text-sm font-bold" style="color:var(--green-mid)"><?= Format::currencyCompact((float)$loan['principal_amount']) ?></div>
            <a href="<?= APP_URL ?>/loans/<?= $loan['id'] ?>/approve"
               class="text-[10px] font-semibold px-2 py-0.5 rounded-full" style="background:#dcfce7;color:#166534">
              Review
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recent activity -->
  <div class="card">
    <div class="card-header">
      <h3 class="section-title">Recent Activity</h3>
      <a href="<?= APP_URL ?>/transactions" class="text-xs font-semibold" style="color:var(--green-mid)">All transactions</a>
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
          <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 text-sm"
               style="background:<?= $ic['bg'] ?>;color:<?= $ic['color'] ?>">
            <i class="fa-solid <?= $ic['icon'] ?>"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-xs font-semibold text-slate-700 truncate"><?= htmlspecialchars($txn['member_name'] ?? '—') ?></div>
            <div class="text-[10px] text-slate-400"><?= Format::timeAgo($txn['created_at']) ?></div>
          </div>
          <div class="text-xs font-bold <?= str_contains($txn['txn_type'],'deposit') || str_contains($txn['txn_type'],'repayment') ? 'text-emerald-600' : 'text-slate-700' ?>">
            <?= Format::currencyCompact((float)$txn['amount']) ?>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ── Row 4: Overdue loans + Member status ───────────────────────── -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

  <!-- Overdue loans -->
  <div class="card">
    <div class="card-header">
      <div class="flex items-center gap-2">
        <h3 class="section-title">Overdue Loans</h3>
        <?php if (!empty($overdueCount) && $overdueCount > 0): ?>
          <span class="badge overdue-badge"><?= $overdueCount ?> overdue</span>
        <?php endif; ?>
      </div>
      <a href="<?= APP_URL ?>/loans?status=defaulted" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-triangle-exclamation text-amber-500"></i> View All
      </a>
    </div>
    <div class="overflow-x-auto">
      <table class="data-table">
        <thead>
          <tr>
            <th>Member</th>
            <th>Overdue Amount</th>
            <th>Days</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($overdueLoans)): ?>
            <tr><td colspan="4" class="text-center py-8 text-slate-400 text-sm">
              <i class="fa-solid fa-circle-check text-emerald-300 text-2xl block mb-2"></i>
              No overdue loans — great work!
            </td></tr>
          <?php else: ?>
            <?php foreach (array_slice($overdueLoans, 0, 6) as $ol): ?>
            <tr>
              <td>
                <div class="font-semibold text-slate-800 text-sm"><?= htmlspecialchars($ol['full_name']) ?></div>
                <div class="text-xs text-slate-400"><?= $ol['member_no'] ?></div>
              </td>
              <td class="font-bold text-red-600"><?= Format::currency((float)$ol['overdue_amount']) ?></td>
              <td>
                <span class="badge overdue-badge"><?= $ol['days_overdue'] ?? '—' ?>d</span>
              </td>
              <td>
                <a href="<?= APP_URL ?>/members/<?= $ol['member_id'] ?>" class="btn btn-secondary btn-sm">
                  <i class="fa-solid fa-eye"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Member status breakdown -->
  <div class="card">
    <div class="card-header">
      <h3 class="section-title">Member Overview</h3>
      <a href="<?= APP_URL ?>/members" class="text-xs font-semibold" style="color:var(--green-mid)">Manage</a>
    </div>
    <div class="card-body">
      <?php
      $memberStats = $dashStats['member_stats'] ?? [];
      $total       = max(1, (int)($memberStats['total_members'] ?? 1));
      $bars = [
        ['label'=>'Active',    'val'=>$memberStats['active_members']    ?? 0, 'color'=>'#10b981'],
        ['label'=>'Inactive',  'val'=>$memberStats['inactive_members']  ?? 0, 'color'=>'#94a3b8'],
        ['label'=>'Suspended', 'val'=>$memberStats['suspended_members'] ?? 0, 'color'=>'#f59e0b'],
        ['label'=>'KYC Pending','val'=>$memberStats['kyc_pending']      ?? 0, 'color'=>'#3b82f6'],
      ];
      ?>
      <div class="space-y-4 mb-6">
        <?php foreach ($bars as $b): ?>
        <div>
          <div class="flex justify-between text-sm mb-1.5">
            <span class="font-medium text-slate-700"><?= $b['label'] ?></span>
            <span class="font-bold text-slate-800"><?= number_format($b['val']) ?></span>
          </div>
          <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full rounded-full transition-all duration-700"
                 style="width:<?= min(100, round($b['val']/$total*100)) ?>%;background:<?= $b['color'] ?>"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- KYC alert -->
      <?php if (!empty($memberStats['kyc_pending']) && $memberStats['kyc_pending'] > 0): ?>
      <div class="flex items-center gap-3 p-3 rounded-xl" style="background:#eff6ff;border:1px solid #bfdbfe">
        <i class="fa-solid fa-id-card text-blue-500"></i>
        <div class="flex-1">
          <span class="text-sm font-semibold text-blue-800"><?= $memberStats['kyc_pending'] ?> members awaiting KYC verification</span>
        </div>
        <a href="<?= APP_URL ?>/members?kyc=0" class="text-xs font-bold text-blue-600 hover:underline">Review →</a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ── Chart scripts ──────────────────────────────────────────────── -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function() {
  // Monthly Overview Chart
  const months  = <?= json_encode($chartData['months']  ?? ['Jan','Feb','Mar','Apr','May','Jun']) ?>;
  const savings = <?= json_encode($chartData['savings'] ?? [0,0,0,0,0,0]) ?>;
  const loans   = <?= json_encode($chartData['loans']   ?? [0,0,0,0,0,0]) ?>;

  new Chart(document.getElementById('overviewChart'), {
    type: 'bar',
    data: {
      labels: months,
      datasets: [
        {
          label: 'Savings',
          data: savings,
          backgroundColor: 'rgba(19,107,85,0.85)',
          borderRadius: 6,
          borderSkipped: false,
        },
        {
          label: 'Loans Disbursed',
          data: loans,
          backgroundColor: 'rgba(201,150,58,0.75)',
          borderRadius: 6,
          borderSkipped: false,
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: ctx => ' USh ' + ctx.raw.toLocaleString()
          }
        }
      },
      scales: {
        x: { grid: { display: false }, border: { display: false } },
        y: {
          grid: { color: '#f1f5f9' },
          border: { display: false },
          ticks: {
            callback: v => v >= 1e6 ? (v/1e6).toFixed(1)+'M' : v >= 1e3 ? (v/1e3).toFixed(0)+'K' : v,
            font: { size: 11 }
          }
        }
      }
    }
  });

  // Portfolio Donut
  const portfolioData   = <?= json_encode($chartData['portfolio'] ?? ['Active'=>0,'Completed'=>0,'Pending'=>0,'Defaulted'=>0]) ?>;
  const portfolioColors = ['#136b55','#10b981','#f59e0b','#ef4444'];
  const portfolioLabels = Object.keys(portfolioData);
  const portfolioValues = Object.values(portfolioData);

  new Chart(document.getElementById('portfolioChart'), {
    type: 'doughnut',
    data: {
      labels: portfolioLabels,
      datasets: [{
        data: portfolioValues,
        backgroundColor: portfolioColors,
        borderWidth: 3,
        borderColor: '#fff',
        hoverOffset: 8,
      }]
    },
    options: {
      responsive: true,
      cutout: '68%',
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.raw} loans` } } }
    }
  });

  // Legend
  const legend = document.getElementById('portfolioLegend');
  portfolioLabels.forEach((l, i) => {
    const total = portfolioValues.reduce((a,b)=>a+b,0) || 1;
    const pct   = Math.round(portfolioValues[i]/total*100);
    legend.innerHTML += `
      <div class="flex items-center justify-between text-xs">
        <span class="flex items-center gap-2">
          <span class="w-3 h-3 rounded-sm inline-block" style="background:${portfolioColors[i]}"></span>
          <span class="text-slate-600 font-medium">${l}</span>
        </span>
        <span class="font-bold text-slate-800">${portfolioValues[i]} <span class="text-slate-400 font-normal">(${pct}%)</span></span>
      </div>`;
  });
})();
</script>
