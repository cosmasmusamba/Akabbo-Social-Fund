<?php use App\Helpers\Format; $pageTitle='Reports'; $activePage='reports'; $breadcrumbs=['Reports'=>null]; ?>
<div class="mb-6"><h1 class="text-xl font-bold text-slate-800">Reports & Analytics</h1><p class="text-sm text-slate-400 mt-0.5">Generate and export financial reports</p></div>

<!-- Summary KPIs -->
<div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-8">
  <?php $kpis=[['Total Savings',Format::currencyCompact((float)($summary['total_savings']??0)),'fa-piggy-bank','gradient-green','text-white'],['Outstanding Loans',Format::currencyCompact((float)($summary['outstanding_loans']??0)),'fa-hand-holding-dollar','gradient-gold','text-white'],['Deposits This Month',Format::currencyCompact((float)($summary['deposits_this_month']??0)),'fa-arrow-down','bg-emerald-500','text-white'],['Repayments This Month',Format::currencyCompact((float)($summary['repayments_this_month']??0)),'fa-circle-dollar-to-slot','bg-purple-600','text-white'],['Active Members',number_format($summary['active_members']??0),'fa-users','bg-blue-600','text-white'],['Active Loans',number_format($summary['active_loans']??0),'fa-file-contract','bg-amber-500','text-white']];
  foreach($kpis as [$label,$val,$icon,$bg,$tc]): ?>
  <div class="stat-card flex items-center gap-4 py-5">
    <div class="kpi-icon <?= $bg ?> <?= $tc ?>"><i class="fa-solid <?= $icon ?>"></i></div>
    <div><div class="text-xl font-black text-slate-800"><?= $val ?></div><div class="text-xs text-slate-400 font-semibold"><?= $label ?></div></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Report cards -->
<h2 class="text-base font-bold text-slate-700 mb-4">Available Reports</h2>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
  <?php $reports=[
    ['Savings Report','View all savings accounts, deposits, and withdrawals by date range.','fa-piggy-bank','bg-emerald-50 border-emerald-200 hover:border-emerald-400','/reports/savings','text-emerald-700'],
    ['Loans Report','Complete loan portfolio analysis by product, status, and member.','fa-file-contract','bg-blue-50 border-blue-200 hover:border-blue-400','/reports/loans','text-blue-700'],
    ['Transactions Report','All financial transactions with filter by type and date.','fa-arrow-right-arrow-left','bg-purple-50 border-purple-200 hover:border-purple-400','/reports/transactions','text-purple-700'],
    ['Members Report','Member enrollment, savings, and loan statistics.','fa-users','bg-indigo-50 border-indigo-200 hover:border-indigo-400','/reports/members','text-indigo-700'],
    ['Cash Flow Report','Monthly inflow vs outflow analysis for the fiscal year.','fa-chart-line','bg-amber-50 border-amber-200 hover:border-amber-400','/reports/cash-flow','text-amber-700'],
    ['Audit Report','System activity logs and user action history.','fa-shield-halved','bg-slate-50 border-slate-200 hover:border-slate-400','/audit','text-slate-700'],
  ];
  foreach($reports as [$title,$desc,$icon,$cardCls,$url,$iconCls]): ?>
  <a href="<?= APP_URL.$url ?>" class="card border-2 p-6 transition-all <?= $cardCls ?>" style="cursor:pointer;text-decoration:none">
    <i class="fa-solid <?= $icon ?> text-3xl <?= $iconCls ?> mb-3 block"></i>
    <h3 class="font-bold text-slate-800 mb-1"><?= $title ?></h3>
    <p class="text-sm text-slate-500"><?= $desc ?></p>
    <div class="mt-4 flex items-center gap-1 text-xs font-semibold <?= $iconCls ?>">Generate Report <i class="fa-solid fa-arrow-right ml-1"></i></div>
  </a>
  <?php endforeach; ?>
</div>
