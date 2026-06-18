<?php
// Fallback for APP_URL if not defined
$appUrl = defined('APP_URL') ? APP_URL : '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found — Akabbo Social Fund</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .gradient-green { background: linear-gradient(135deg, #136b55 0%, #0a4033 100%); }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full text-center">
        <!-- Logo / Brand -->
        <div class="mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl gradient-green text-white text-2xl font-black mb-4 shadow-lg">
                A
            </div>
            <h1 class="text-2xl font-bold text-slate-800">Akabbo Social Fund</h1>
            <p class="text-sm text-slate-500 mt-1">Growing Together, Prospering Together</p>
        </div>

        <!-- 404 Illustration / Icon -->
        <div class="mb-6">
            <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-amber-50 text-amber-500 mb-4">
                <i class="fa-solid fa-map-location-dot text-5xl"></i>
            </div>
            <h2 class="text-6xl font-black text-slate-800 mb-2">404</h2>
            <h3 class="text-xl font-bold text-slate-700 mb-2">Page Not Found</h3>
            <p class="text-slate-500 text-sm leading-relaxed">
                The page you're looking for doesn't exist, has been moved, or you don't have permission to view it.
            </p>
        </div>

        <!-- Actions -->
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="<?= htmlspecialchars($appUrl) ?>/dashboard" class="btn inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-lg font-semibold text-sm text-white transition-all gradient-green hover:opacity-90 shadow-sm">
                <i class="fa-solid fa-house-chimney"></i> Go to Dashboard
            </a>
            <button onclick="window.history.back()" class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-lg font-semibold text-sm text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 transition-all shadow-sm">
                <i class="fa-solid fa-arrow-left"></i> Go Back
            </button>
        </div>

        <!-- Footer -->
        <div class="mt-12 text-xs text-slate-400">
            &copy; <?= date('Y') ?> Akabbo Social Fund &nbsp;·&nbsp; v1.0.0
       9</div>
    </div>

</body>
</html>