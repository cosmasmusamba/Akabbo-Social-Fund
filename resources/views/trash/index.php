<?php use App\Helpers\Format; $pageTitle='Trash'; $activePage='trash'; $breadcrumbs=['Trash'=>null]; ?>
<div class="flex items-center justify-between mb-5">
  <div><h1 class="text-xl font-bold text-slate-800">Trash</h1><p class="text-sm text-slate-400 mt-0.5">Deleted records — recoverable until permanently removed</p></div>
  <div class="flex gap-2">
    <?php foreach(['','member','loan','transaction'] as $t): ?>
    <a href="?type=<?= $t ?>" class="btn <?= ($type??'')===$t?'btn-primary':'btn-secondary' ?> btn-sm"><?= $t?ucfirst($t).'s':'All' ?></a>
    <?php endforeach; ?>
  </div>
</div>
<div class="card">
  <div class="overflow-x-auto">
    <table class="data-table">
      <thead><tr><th>Type</th><th>Record ID</th><th class="hidden md:table-cell">Deleted By</th><th class="hidden md:table-cell">Deleted At</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if(empty($result['data'])): ?>
        <tr><td colspan="5" class="text-center py-12"><i class="fa-solid fa-trash-can text-5xl text-slate-200 block mb-3"></i><p class="text-slate-400">Trash is empty</p></td></tr>
        <?php else: foreach($result['data'] as $item):
          $data=json_decode($item['record_data']??'{}',true);
          $label=$item['record_type']==='member'?($data['first_name']??'').' '.($data['last_name']??''):($item['record_type']==='loan'?($data['loan_no']??''):($data['txn_ref']??''));
        ?>
        <tr>
          <td><span class="px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700"><?= ucfirst($item['record_type']) ?></span></td>
          <td><div class="font-semibold text-sm"><?= htmlspecialchars(trim($label)?:('#'.$item['record_id'])) ?></div><div class="text-xs text-slate-400">ID: <?= $item['record_id'] ?></div></td>
          <td class="hidden md:table-cell text-sm"><?= htmlspecialchars($item['deleted_by_name']??'—') ?></td>
          <td class="hidden md:table-cell text-xs text-slate-400"><?= Format::datetime($item['deleted_at']) ?></td>
          <td>
            <div class="flex gap-2">
              <button onclick="restoreRecord('<?= $item['record_type'] ?>',<?= $item['record_id'] ?>,'<?= addslashes(trim($label)) ?>')" class="btn btn-sm" style="background:#dcfce7;color:#166534;border:1px solid #a7f3d0;font-size:0.78rem;padding:5px 12px"><i class="fa-solid fa-rotate-left"></i> Restore</button>
              <button onclick="destroyRecord('<?= $item['record_type'] ?>',<?= $item['record_id'] ?>,'<?= addslashes(trim($label)) ?>')" class="btn btn-danger btn-sm py-1 px-3" style="font-size:0.78rem"><i class="fa-solid fa-trash"></i></button>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<script>
async function restoreRecord(type,id,label){
  window.confirmAction({title:'Restore Record',message:`Restore "${label||type+' #'+id}"?`,confirmText:'Restore',type:'primary',onConfirm:async()=>{
    const fd=new FormData();fd.append('csrf_token',document.querySelector('meta[name="csrf-token"]').content);
    const r=await fetch(`<?= APP_URL ?>/trash/${type}/${id}/restore`,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd});
    const d=await r.json();if(d.success){window.showToast('success',d.message);setTimeout(()=>location.reload(),900);}else window.showToast('error',d.message);
  }});
}
async function destroyRecord(type,id,label){
  window.confirmAction({title:'Permanently Delete',message:`PERMANENTLY delete "${label||type+' #'+id}"? This cannot be undone.`,confirmText:'Delete Forever',type:'danger',onConfirm:async()=>{
    const fd=new FormData();fd.append('csrf_token',document.querySelector('meta[name="csrf-token"]').content);
    const r=await fetch(`<?= APP_URL ?>/trash/${type}/${id}/destroy`,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd});
    const d=await r.json();if(d.success){window.showToast('success',d.message);setTimeout(()=>location.reload(),900);}else window.showToast('error',d.message);
  }});
}
</script>
