<?php $pageTitle='Create User'; $activePage='users'; $breadcrumbs=['Users'=>APP_URL.'/users','Create'=>null]; ?>
<div class="max-w-xl mx-auto">
  <div class="flex items-center justify-between mb-5"><div><h1 class="text-xl font-bold text-slate-800">Create System User</h1></div><a href="<?= APP_URL ?>/users" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a></div>
  <div class="card"><div class="card-body">
    <form id="createUserForm" data-ajax="true">
      <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
      <div class="grid grid-cols-2 gap-4 mb-4">
        <div><label class="form-label required">First Name</label><input type="text" name="first_name" class="form-control" required></div>
        <div><label class="form-label required">Last Name</label><input type="text" name="last_name" class="form-control" required></div>
        <div class="col-span-2"><label class="form-label required">Email Address</label><input type="email" name="email" class="form-control" required></div>
        <div><label class="form-label">Phone</label><input type="tel" name="phone" class="form-control" placeholder="+256 700 000000"></div>
        <div><label class="form-label required">Role</label><select name="role_id" class="form-control form-select" required><option value="">Select role…</option><?php foreach($roles??[] as $r): ?><option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-span-2"><label class="form-label">Password <span class="text-slate-400 font-normal">(leave blank to auto-generate)</span></label><input type="password" name="password" class="form-control" placeholder="Leave blank to generate"></div>
      </div>
      <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-sm text-amber-700 mb-4"><i class="fa-solid fa-triangle-exclamation mr-1.5"></i>The user will be required to change their password on first login.</div>
      <div class="flex gap-3 justify-end pt-4 border-t border-slate-100">
        <a href="<?= APP_URL ?>/users" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Create User</button>
      </div>
    </form>
  </div></div>
</div>
<script>
document.getElementById('createUserForm').addEventListener('submit',async function(e){
  e.preventDefault();
  await window.submitForm(this,{loadingText:'Creating…',onSuccess:r=>{window.showToast('success',r.message+(r.data?.temp_password?` Temp password: ${r.data.temp_password}`:''));setTimeout(()=>{window.location.href=r.data?.redirect||'<?= APP_URL ?>/users';},2000);}});
});
</script>
