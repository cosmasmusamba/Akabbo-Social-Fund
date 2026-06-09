<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Forgot Password — Akabbo Social Fund</title><script src="https://cdn.tailwindcss.com"></script><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"><style>body{font-family:'Plus Jakarta Sans',sans-serif;background:linear-gradient(135deg,#0a2218,#0d3529);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}</style></head>
<body><div class="w-full max-w-sm"><div class="bg-white rounded-2xl p-8 shadow-2xl">
  <div class="text-center mb-6"><div style="width:56px;height:56px;background:linear-gradient(135deg,#136b55,#1a8f6f);border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px" class="shadow-lg"><i class="fa-solid fa-lock text-white text-xl"></i></div><h1 class="text-xl font-bold text-slate-800">Reset Password</h1><p class="text-sm text-slate-500 mt-1">Enter your email to receive a reset link</p></div>
  <div id="alertBox" class="hidden mb-4"></div>
  <form id="forgotForm">
    <?= $csrfField ?>
    <div class="mb-4"><label class="block text-sm font-semibold text-slate-700 mb-1.5">Email Address</label><div class="relative"><i class="fa-regular fa-envelope absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i><input type="email" name="email" class="w-full pl-10 pr-4 py-2.5 border-1.5 border-slate-200 rounded-lg text-sm focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-100" placeholder="your@email.com" required style="border:1.5px solid #e2e8f0"></div></div>
    <button type="submit" class="w-full py-2.5 rounded-lg text-white font-bold text-sm" style="background:linear-gradient(135deg,#136b55,#0a4033)">Send Reset Link</button>
  </form>
  <div class="text-center mt-5"><a href="<?= defined('APP_URL')?APP_URL:'' ?>/login" class="text-sm font-semibold" style="color:#136b55">← Back to login</a></div>
</div></div>
<script>
document.getElementById('forgotForm').addEventListener('submit',async(e)=>{
  e.preventDefault();const b=e.target.querySelector('[type=submit]');b.disabled=true;b.textContent='Sending…';
  try{const fd=new FormData(e.target);const r=await fetch('<?= defined('APP_URL')?APP_URL:'' ?>/auth/forgot-password',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd});const d=await r.json();const box=document.getElementById('alertBox');box.className='mb-4 p-3 rounded-lg text-sm '+(d.success?'bg-green-50 text-green-800 border border-green-200':'bg-red-50 text-red-800 border border-red-200');box.textContent=d.message;box.classList.remove('hidden');}catch(e){console.error(e);}finally{b.disabled=false;b.textContent='Send Reset Link';}
});
</script></body></html>
