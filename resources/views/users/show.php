<?php use App\Helpers\Format; ?>
<div class="max-w-2xl mx-auto">
  <div class="flex items-center justify-between mb-5">
    <div class="breadcrumb"><a href="<?= APP_URL ?>/users">Users</a><span class="sep">/</span><span class="current"><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></span></div>
    <div class="flex gap-2">
      <?php if($auth->can('users.edit')&&$user['id']!=$_SESSION['user_id']): ?>
      <button onclick="resetPwd()" class="btn btn-secondary btn-sm"><i class="fa-solid fa-key"></i> Reset Password</button>
      <?php endif; ?>
    </div>
  </div>
  <div class="card mb-5">
    <div class="card-body">
      <div class="flex items-center gap-4 mb-5">
        <div class="avatar-circle w-16 h-16 text-xl"><?= Format::initials($user['first_name'].' '.$user['last_name']) ?></div>
        <div><h2 class="text-xl font-bold text-slate-800"><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></h2><div class="text-sm text-slate-400"><?= htmlspecialchars($user['email']) ?></div><div class="flex gap-2 mt-1"><?= Format::statusPill($user['status']) ?><span class="px-2 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600"><?= htmlspecialchars($user['role_name']) ?></span></div></div>
      </div>
      <?php if($auth->can('users.edit')): ?>
      <form id="editUserForm" data-ajax="true">
        <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
        <div class="grid grid-cols-2 gap-4 mb-4">
          <div><label class="form-label">First Name</label><input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" class="form-control"></div>
          <div><label class="form-label">Last Name</label><input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" class="form-control"></div>
          <div><label class="form-label">Phone</label><input type="tel" name="phone" value="<?= htmlspecialchars($user['phone']??'') ?>" class="form-control"></div>
          <div><label class="form-label">Status</label><select name="status" class="form-control form-select"><option value="active" <?= $user['status']==='active'?'selected':'' ?>>Active</option><option value="inactive" <?= $user['status']==='inactive'?'selected':'' ?>>Inactive</option><option value="suspended" <?= $user['status']==='suspended'?'selected':'' ?>>Suspended</option></select></div>
          <div><label class="form-label">Role</label><select name="role_id" class="form-control form-select"><?php foreach($roles??[] as $r): ?><option value="<?= $r['id'] ?>" <?= $user['role_id']==$r['id']?'selected':'' ?>><?= htmlspecialchars($r['name']) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="flex justify-end"><button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-save"></i> Save Changes</button></div>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <!-- Login sessions -->
  <div class="card"><div class="card-header"><h3 class="section-title">Login Sessions</h3></div>
    <div class="divide-y divide-slate-100">
      <?php foreach($sessions??[] as $s): ?>
      <div class="px-5 py-3 flex items-center justify-between"><div><div class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($s['ip_address']??'Unknown') ?></div><div class="text-xs text-slate-400"><?= Format::datetime($s['login_at']) ?></div></div><span class="px-2 py-0.5 rounded-full text-xs font-bold <?= $s['status']==='active'?'bg-emerald-100 text-emerald-700':'bg-slate-100 text-slate-500' ?>"><?= ucfirst($s['status']) ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<script>
<?php if($auth->can('users.edit')): ?>
document.getElementById('editUserForm')?.addEventListener('submit',async function(e){e.preventDefault();await window.submitForm(this,{loadingText:'Saving…',onSuccess:r=>window.showToast('success',r.message)});});
<?php endif; ?>
async function resetPwd(){
  window.confirmAction({title:'Reset Password',message:'Generate a new temporary password for this user?',confirmText:'Reset',type:'warning',onConfirm:async()=>{
    const fd=new FormData();fd.append('csrf_token',document.querySelector('meta[name="csrf-token"]').content);
    const r=await fetch('<?= APP_URL ?>/users/<?= $user['id'] ?>/reset-password',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd});
    const d=await r.json();if(d.success)window.showToast('success',`Temp password: ${d.data?.temp_password}`);else window.showToast('error',d.message);
  }});
}
</script>
