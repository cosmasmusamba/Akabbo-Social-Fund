<?php use App\Helpers\Format; $pageTitle='System Users'; $activePage='users'; $breadcrumbs=['Users'=>null]; ?>
<div class="flex items-center justify-between mb-5">
  <div><h1 class="text-xl font-bold text-slate-800">System Users</h1><p class="text-sm text-slate-400 mt-0.5"><?= number_format($result['total']??0) ?> system accounts</p></div>
  <?php if($auth->can('users.create')): ?><a href="<?= APP_URL ?>/users/create" class="btn btn-primary btn-sm"><i class="fa-solid fa-user-plus"></i> Add User</a><?php endif; ?>
</div>
<div class="card">
  <div class="overflow-x-auto">
    <table class="data-table">
      <thead><tr><th>User</th><th>Email</th><th class="hidden md:table-cell">Role</th><th class="hidden md:table-cell">Last Login</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($result['data']??[] as $u): ?>
        <tr>
          <td><div class="flex items-center gap-3"><div class="avatar-circle w-9 h-9 text-xs"><?= Format::initials($u['first_name'].' '.$u['last_name']) ?></div><div><div class="font-semibold text-sm"><?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?></div><?php if($u['phone']): ?><div class="text-xs text-slate-400"><?= htmlspecialchars($u['phone']) ?></div><?php endif; ?></div></div></td>
          <td class="text-sm"><?= htmlspecialchars($u['email']) ?></td>
          <td class="hidden md:table-cell"><span class="px-2 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600"><?= htmlspecialchars($u['role_name']) ?></span></td>
          <td class="hidden md:table-cell text-xs text-slate-400"><?= $u['last_login']?Format::timeAgo($u['last_login']):'Never' ?></td>
          <td><?= Format::statusPill($u['status']) ?></td>
          <td>
            <div class="flex gap-1">
              <a href="<?= APP_URL ?>/users/<?= $u['id'] ?>" class="btn btn-secondary btn-sm py-1 px-2"><i class="fa-solid fa-eye text-xs"></i></a>
              <?php if($auth->can('users.edit')&&$u['id']!==$_SESSION['user_id']): ?>
              <button onclick="resetPwd(<?= $u['id'] ?>, '<?= addslashes($u['first_name']) ?>')" class="btn btn-secondary btn-sm py-1 px-2" title="Reset password"><i class="fa-solid fa-key text-xs"></i></button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<script>
async function resetPwd(id, name){
  window.confirmAction({title:'Reset Password',message:`Reset password for ${name}? A temporary password will be generated.`,confirmText:'Reset',type:'warning',onConfirm:async()=>{
    const fd=new FormData();fd.append('csrf_token',document.querySelector('meta[name="csrf-token"]').content);
    const r=await fetch(`<?= APP_URL ?>/users/${id}/reset-password`,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd});
    const d=await r.json();
    if(d.success){window.showToast('success',`Temp password: ${d.data.temp_password}`);}else window.showToast('error',d.message);
  }});
}
</script>
