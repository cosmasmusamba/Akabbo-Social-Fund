<?php use App\Helpers\Format; ?>
<div class="max-w-2xl mx-auto">
  <div class="flex items-center justify-between mb-5">
    <div class="breadcrumb"><a href="<?= APP_URL ?>/audit">Audit Log</a><span class="sep">/</span><span class="current">Entry #<?= $log['id'] ?></span></div>
    <a href="<?= APP_URL ?>/audit" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
  </div>
  <div class="card">
    <div class="card-header"><h2 class="font-bold text-slate-800">Audit Entry #<?= $log['id'] ?></h2><?php $sc=['info'=>'bg-slate-100 text-slate-600','warning'=>'bg-amber-100 text-amber-700','critical'=>'bg-red-100 text-red-700']; ?><span class="px-2 py-0.5 rounded-full text-xs font-bold <?= $sc[$log['severity']??'info'] ?>"><?= ucfirst($log['severity']??'info') ?></span></div>
    <div class="card-body space-y-3">
      <?php $f=[['Action',str_replace('_',' ',$log['action'])],['Module',ucfirst($log['module'])],['User',$log['user_name']??'System'],['IP Address',$log['ip_address']??'—'],['Session ID',substr($log['session_id']??'—',0,20).'…'],['Description',$log['description']??'—'],['Record Type',$log['record_type']??'—'],['Record ID',$log['record_id']??'—'],['Timestamp',Format::datetime($log['created_at'])]];
      foreach($f as [$l,$v]): ?>
      <div class="flex gap-4 py-2 border-b border-slate-50 last:border-0">
        <div class="text-xs font-bold text-slate-400 uppercase tracking-wide w-32 flex-shrink-0 pt-0.5"><?= $l ?></div>
        <div class="text-sm text-slate-700 font-medium flex-1 break-all"><?= htmlspecialchars($v) ?></div>
      </div>
      <?php endforeach; ?>
      <?php if($log['old_values']): ?>
      <div class="border-t border-slate-100 pt-3">
        <div class="text-xs font-bold text-slate-400 mb-2">PREVIOUS VALUES</div>
        <pre class="text-xs bg-red-50 p-3 rounded-lg overflow-x-auto text-red-800"><?= json_encode(json_decode($log['old_values']),JSON_PRETTY_PRINT) ?></pre>
      </div>
      <?php endif; ?>
      <?php if($log['new_values']): ?>
      <div>
        <div class="text-xs font-bold text-slate-400 mb-2">NEW VALUES</div>
        <pre class="text-xs bg-green-50 p-3 rounded-lg overflow-x-auto text-green-800"><?= json_encode(json_decode($log['new_values']),JSON_PRETTY_PRINT) ?></pre>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
