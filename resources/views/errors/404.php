<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Page Not Found — Akabbo Social Fund</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Fraunces:ital,opsz,wght@0,9..144,300;1,9..144,300&display=swap" rel="stylesheet">
  <style>body{font-family:'Plus Jakarta Sans',sans-serif;background:#f4f6f9;}.brand-font{font-family:'Fraunces',serif;}</style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
  <div class="text-center max-w-md">
    <div class="w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-6" style="background:linear-gradient(135deg,#136b55,#1a8f6f)">
      <span class="text-white text-4xl font-black">404</span>
    </div>
    <h1 class="brand-font text-3xl font-bold text-slate-800 mb-3">Page Not Found</h1>
    <p class="text-slate-500 mb-8">The page you're looking for doesn't exist or has been moved.</p>
    <div class="flex gap-3 justify-center">
      <a href="<?= defined('APP_URL') ? APP_URL : '' ?>/dashboard"
         class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-white font-semibold text-sm"
         style="background:linear-gradient(135deg,#136b55,#0a4033)">
        ← Go to Dashboard
      </a>
      <button onclick="history.back()" class="px-5 py-2.5 rounded-xl font-semibold text-sm border border-slate-200 bg-white text-slate-700 hover:bg-slate-50">
        Go Back
      </button>
    </div>
  </div>
</body>
</html>
