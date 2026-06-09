<?php $pageTitle='Settings'; $activePage='settings'; $breadcrumbs=['Settings'=>null]; ?>
<div class="flex items-center justify-between mb-5">
  <div><h1 class="text-xl font-bold text-slate-800">System Settings</h1><p class="text-sm text-slate-400 mt-0.5">Configure application preferences</p></div>
  <div class="flex gap-2">
    <?php if($auth->can('settings.edit')): ?>
    <button onclick="createBackup()" class="btn btn-secondary btn-sm"><i class="fa-solid fa-database"></i> Backup Now</button>
    <?php endif; ?>
  </div>
</div>
<form id="settingsForm" data-ajax="true">
  <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
  <?php $readonly=!$auth->can('settings.edit'); ?>
  <?php $groupLabels=['general'=>'General','finance'=>'Financial Settings','security'=>'Security','notifications'=>'Notifications','backup'=>'Backup & Recovery','system'=>'System'];
  foreach(($groups??[]) as $group): ?>
  <div class="card mb-5">
    <div class="card-header"><h3 class="section-title"><i class="fa-solid fa-sliders mr-2" style="color:var(--green-mid)"></i><?= $groupLabels[$group]??ucfirst($group) ?></h3></div>
    <div class="card-body space-y-4">
      <?php foreach(($settings??[]) as $key=>$s): if($s['group']!==$group) continue; ?>
      <div class="flex flex-col sm:flex-row sm:items-center gap-2">
        <div class="sm:w-64 flex-shrink-0">
          <label class="form-label mb-0" for="s_<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($s['description']??$key) ?></label>
          <div class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($key) ?></div>
        </div>
        <div class="flex-1">
          <?php if($s['type']==='boolean'): ?>
          <select name="<?= htmlspecialchars($key) ?>" id="s_<?= htmlspecialchars($key) ?>" class="form-control form-select py-2 text-sm w-auto" <?= $readonly?'disabled':'' ?>>
            <option value="true" <?= $s['value']==='true'?'selected':'' ?>>Enabled</option>
            <option value="false" <?= $s['value']==='false'?'selected':'' ?>>Disabled</option>
          </select>
          <?php elseif($s['type']==='integer'): ?>
          <input type="number" name="<?= htmlspecialchars($key) ?>" id="s_<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($s['value']??'') ?>" class="form-control py-2 text-sm" style="max-width:200px" <?= $readonly?'readonly':'' ?>>
          <?php else: ?>
          <input type="text" name="<?= htmlspecialchars($key) ?>" id="s_<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($s['value']??'') ?>" class="form-control py-2 text-sm" <?= $readonly?'readonly':'' ?>>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if($auth->can('settings.edit')): ?>
  <div class="flex justify-end"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Settings</button></div>
  <?php endif; ?>
</form>
<script>
document.getElementById('settingsForm').addEventListener('submit',async function(e){
  e.preventDefault();
  await window.submitForm(this,{loadingText:'Saving…',onSuccess:r=>window.showToast('success',r.message)});
});
async function createBackup(){
  window.confirmAction({title:'Create Backup',message:'Create a system backup now?',confirmText:'Backup',type:'primary',onConfirm:async()=>{
    const fd=new FormData();fd.append('csrf_token',document.querySelector('meta[name="csrf-token"]').content);
    const r=await fetch('<?= APP_URL ?>/settings/backup',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd});
    const d=await r.json();window.showToast(d.success?'success':'error',d.message);
  }});
}
</script>
