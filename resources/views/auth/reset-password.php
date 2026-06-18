<?php
$pageTitle = 'Set New Password';
$settings  = $settings ?? [];
$flash     = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$token     = $token ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars($settings['org_name'] ?? 'Akabbo Social Fund') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --green-mid: #136b55; --green-bright: #10b981; }
        .btn-primary { background: var(--green-mid); color: #fff; }
        .btn-primary:hover { background: #0f5644; }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; } </style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md">
        <!-- Back Link -->
        <a href="<?= APP_URL ?>/login" class="inline-flex items-center gap-2 text-sm text-slate-500 hover:text-emerald-600 mb-6 transition-colors">
            <i class="fa-solid fa-arrow-left"></i> Back to login
        </a>

        <!-- Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8">
            <div class="mb-6">
                <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl mb-4">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <h2 class="text-xl font-bold text-slate-800">Set New Password</h2>
                <p class="text-sm text-slate-500 mt-1">Create a strong, secure password for your account.</p>
            </div>

            <!-- Flash Messages -->
            <?php if ($flash): ?>
                <div class="mb-4 p-3 rounded-lg text-sm flex items-start gap-2 <?= $flash['type'] === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' ?>">
                    <i class="fa-solid <?= $flash['type'] === 'error' ? 'fa-circle-xmark' : 'fa-circle-check' ?> mt-0.5"></i>
                    <span><?= htmlspecialchars($flash['message']) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= APP_URL ?>/auth/reset-password" id="resetForm">
                <?= $csrfField ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                
                <div class="space-y-4">
                    <!-- New Password -->
                    <div>
                        <label for="password" class="block text-sm font-semibold text-slate-700 mb-1.5">New Password</label>
                        <div class="relative">
                            <i class="fa-solid fa-lock absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="password" id="password" name="password" 
                                   class="form-control w-full pl-10 py-2.5 rounded-lg border border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none transition-all" 
                                   placeholder="Minimum 8 characters" required minlength="8" autofocus>
                        </div>
                        <p class="text-xs text-slate-400 mt-1.5"><i class="fa-solid fa-circle-info mr-1"></i>Must be at least 8 characters long.</p>
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label for="password_confirm" class="block text-sm font-semibold text-slate-700 mb-1.5">Confirm Password</label>
                        <div class="relative">
                            <i class="fa-solid fa-lock absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="password" id="password_confirm" name="password_confirm" 
                                   class="form-control w-full pl-10 py-2.5 rounded-lg border border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none transition-all" 
                                   placeholder="Re-enter your new password" required minlength="8">
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full mt-6 btn-primary py-2.5 rounded-lg font-semibold text-sm transition-all flex items-center justify-center gap-2" id="submitBtn">
                    <span>Set Password</span>
                    <i class="fa-solid fa-check"></i>
                </button>
            </form>
        </div>

        <div class="text-center mt-6 text-xs text-slate-400">
            &copy; <?= date('Y') ?> <?= htmlspecialchars($settings['org_name'] ?? 'Akabbo Social Fund') ?>
        </div>
    </div>

    <script>
    document.getElementById('resetForm').addEventListener('submit', function(e) {
        const p1 = document.getElementById('password').value;
        const p2 = document.getElementById('password_confirm').value;
        if (p1 !== p2) {
            e.preventDefault();
            alert('Passwords do not match. Please try again.');
            return;
        }
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Updating…';
    });
    </script>
</body>
</html>