<?php use App\Helpers\Format; $pageTitle="Cash Flow — {$year}"; $activePage='reports'; $breadcrumbs=['Reports'=>APP_URL.'/reports','Cash Flow'=>null]; ?>
<div class="flex items-center justify-between mb-5">
  <div><h1 class="text-xl font-bold text-slate-800">Cash Flow — <?= $year ?></h1><p class="text-sm text-slate-400">Annual inflow vs outflow analysis</p></div>
  <div class="flex gap-2 items-center">
    <?php $prevY=$year-1; $nextY=$year+1; ?>
    <a href="?year=<?= $prevY ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-chevron-left"></i> <?= $prevY ?></a>
    <span class="font-bold text-slate-700"><?= $year ?></span>
    <?php if($nextY<=date('Y')): ?><a href="?year=<?= $nextY ?>" class="btn btn-secondary btn-sm"><?= $nextY ?> <i class="fa-solid fa-chevron-right"></i></a><?php endif; ?>
  </div>
</div>
<!-- Annual Summary -->
<div class="grid grid-cols-3 gap-4 mb-6">
  <div class="stat-card text-center py-5"><div class="text-xl font-black text-emerald-700"><?= Format::currency((float)$yearTotal['inflow']) ?></div><div class="text-xs text-slate-400 mt-1">Total Inflow</div></div>
  <div class="stat-card text-center py-5"><div class="text-xl font-black text-orange-700"><?= Format::currency((float)$yearTotal['outflow']) ?></div><div class="text-xs text-slate-400 mt-1">Total Outflow</div></div>
  <div class="stat-card text-center py-5"><div class="text-xl font-black <?= $yearTotal['net']>=0?'text-blue-700':'text-red-700' ?>"><?= Format::currency((float)$yearTotal['net']) ?></div><div class="text-xs text-slate-400 mt-1">Net Cash Flow</div></div>
</div>
<!-- Chart -->
<div class="card mb-5"><div class="card-header"><h3 class="section-title">Monthly Cash Flow</h3></div>
  <div class="card-body"><canvas id="cashFlowChart" height="200"></canvas></div>
</div>
<!-- Table -->
<div class="card"><div class="overflow-x-auto"><table class="data-table">
  <thead><tr><th>Month</th><th>Inflow (Deposits + Repayments)</th><th>Outflow (Withdrawals + Disbursements)</th><th>Net</th></tr></thead>
  <tbody>
    <?php foreach($months as $m): ?>
    <tr>
      <td class="font-semibold"><?= $m['month'] ?> <?= $m['year'] ?></td>
      <td class="text-emerald-700 font-semibold"><?= Format::currency((float)$m['inflow']) ?></td>
      <td class="text-orange-700 font-semibold"><?= Format::currency((float)$m['outflow']) ?></td>
      <td class="font-bold <?= $m['net']>=0?'text-blue-700':'text-red-700' ?>"><?= Format::currency((float)$m['net']) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot><tr class="bg-slate-50 font-bold">
    <td>Annual Total</td>
    <td class="text-emerald-700"><?= Format::currency((float)$yearTotal['inflow']) ?></td>
    <td class="text-orange-700"><?= Format::currency((float)$yearTotal['outflow']) ?></td>
    <td class="<?= $yearTotal['net']>=0?'text-blue-700':'text-red-700' ?>"><?= Format::currency((float)$yearTotal['net']) ?></td>
  </tr></tfoot>
</table></div></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('cashFlowChart'),{type:'bar',data:{
  labels:<?= json_encode(array_column($months,'month')) ?>,
  datasets:[{label:'Inflow',data:<?= json_encode(array_column($months,'inflow')) ?>,backgroundColor:'rgba(16,185,129,0.8)',borderRadius:5},
            {label:'Outflow',data:<?= json_encode(array_column($months,'outflow')) ?>,backgroundColor:'rgba(234,88,12,0.75)',borderRadius:5},
            {label:'Net',data:<?= json_encode(array_column($months,'net')) ?>,type:'line',borderColor:'#3b82f6',backgroundColor:'rgba(59,130,246,0.1)',borderWidth:2,tension:0.4,fill:true}]},
  options:{responsive:true,plugins:{legend:{position:'top'},tooltip:{callbacks:{label:c=>` USh ${c.raw.toLocaleString()}`}}},
  scales:{x:{grid:{display:false}},y:{grid:{color:'#f1f5f9'},ticks:{callback:v=>v>=1e6?(v/1e6).toFixed(1)+'M':v>=1e3?(v/1e3).toFixed(0)+'K':v}}}}});
</script>
