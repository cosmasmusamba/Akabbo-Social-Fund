<?php use App\Helpers\Format; $pageTitle='My Profile'; $activePage='profile'; $breadcrumbs=['Profile'=>null]; ?>
<div class="max-w-3xl mx-auto">
  <div class="card mb-5">
    <div class="card-body">
      <div class="flex items-center gap-5 mb-6">
        <div class="avatar-circle w-16 h-16 text-xl"><?= Format::initials($user['first_name'].' '.$user['last_name']) ?></div>
        <div><h2 class="text-xl font-bold text-slate-800"><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></h2><div class="text-sm text-slate-400"><?= htmlspecialchars($user['email']) ?></div><span class="text-xs font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-700 mt-1 inline-block"><?= htmlspecialchars($user['role_name']) ?></span></div>
      </div>
      <form id="profileForm" data-ajax="true">
        <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
        <div class="grid grid-cols-2 gap-4 mb-4">
          <div><label class="form-label required">First Name</label><input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" class="form-control" required></div>
          <div><label class="form-label required">Last Name</label><input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" class="form-control" required></div>
          <div class="col-span-2"><label class="form-label">Phone</label><input type="tel" name="phone" value="<?= htmlspecialchars($user['phone']??'') ?>" class="form-control"></div>
        </div>
        <div class="flex justify-between items-center pt-4 border-t border-slate-100">
          <a href="<?= APP_URL ?>/profile/change-password" class="text-sm font-semibold" style="color:var(--green-mid)"><i class="fa-solid fa-key mr-1"></i>Change Password</a>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Changes</button>
        </div>
      </form>
    </div>
  </div>
  <!-- Recent sessions -->
  <div class="card"><div class="card-header"><h3 class="section-title">Recent Login Sessions</h3></div>
    <div class="divide-y divide-slate-100">
      <?php foreach($sessions??[] as $s): ?>
      <div class="px-5 py-3 flex items-center justify-between">
        <div><div class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($s['ip_address']??'Unknown') ?></div><div class="text-xs text-slate-400"><?= Format::datetime($s['login_at']) ?></div></div>
        <span class="px-2 py-0.5 rounded-full text-xs font-bold <?= $s['status']==='active'?'bg-emerald-100 text-emerald-700':'bg-slate-100 text-slate-500' ?>"><?= ucfirst($s['status']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<script>
document.getElementById('profileForm').addEventListener('submit',async function(e){e.preventDefault();await window.submitForm(this,{onSuccess:r=>window.showToast('success',r.message)});});
</script>
