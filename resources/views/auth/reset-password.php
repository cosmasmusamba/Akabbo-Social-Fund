<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Reset Password — Akabbo Social Fund</title><script src="https://cdn.tailwindcss.com"></script><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"><style>body{font-family:'Plus Jakarta Sans',sans-serif;background:linear-gradient(135deg,#0a2218,#0d3529);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}</style></head>
<body><div class="w-full max-w-sm"><div class="bg-white rounded-2xl p-8 shadow-2xl">
  <div class="text-center mb-6"><div style="width:56px;height:56px;background:linear-gradient(135deg,#136b55,#1a8f6f);border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px" class="shadow-lg"><i class="fa-solid fa-key text-white text-xl"></i></div><h1 class="text-xl font-bold text-slate-800">Set New Password</h1></div>
  <div id="alertBox" class="hidden mb-4"></div>
  <form id="resetForm">
    <?= $csrfField ?>
    <input type="hidden" name="token" value="<?= htmlspecialchars($token??'') ?>">
    <div class="mb-4"><label class="block text-sm font-semibold text-slate-700 mb-1.5">New Password</label><input type="password" name="password" class="w-full px-3 py-2.5 rounded-lg text-sm" style="border:1.5px solid #e2e8f0" minlength="8" required></div>
    <div class="mb-5"><label class="block text-sm font-semibold text-slate-700 mb-1.5">Confirm Password</label><input type="password" name="password_confirm" class="w-full px-3 py-2.5 rounded-lg text-sm" style="border:1.5px solid #e2e8f0" required></div>
    <button type="submit" class="w-full py-2.5 rounded-lg text-white font-bold text-sm" style="background:linear-gradient(135deg,#136b55,#0a4033)">Set Password</button>
  </form>
</div></div>
<script>
document.getElementById('resetForm').addEventListener('submit',async(e)=>{
  e.preventDefault();const b=e.target.querySelector('[type=submit]');b.disabled=true;b.textContent='Saving…';
  try{const fd=new FormData(e.target);const r=await fetch('<?= defined('APP_URL')?APP_URL:'' ?>/auth/reset-password',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd});const d=await r.json();const box=document.getElementById('alertBox');box.className='mb-4 p-3 rounded-lg text-sm '+(d.success?'bg-green-50 text-green-800 border border-green-200':'bg-red-50 text-red-800 border border-red-200');box.textContent=d.message;box.classList.remove('hidden');if(d.success)setTimeout(()=>{window.location.href=d.data?.redirect||'<?= defined('APP_URL')?APP_URL:'' ?>/login'},1500);}catch(e){console.error(e);}finally{b.disabled=false;b.textContent='Set Password';}
});
</script></body></html>
