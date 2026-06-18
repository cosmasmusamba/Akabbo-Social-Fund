<?php
$pageTitle = 'Sign In';
$settings  = $settings ?? [];
$flash     = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars($settings['org_name'] ?? 'Akabbo Social Fund') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --green-deep: #0a4033;
            --green-mid:  #136b55;
            --green-light:#1a8f6f;
            --gold:       #c9963a;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #eef2f5 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .login-card {
            background: white;
            border-radius: 1.5rem;
            box-shadow: 0 20px 35px -12px rgba(0,0,0,0.1);
            border: 1px solid rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        .brand-circle {
            background: linear-gradient(135deg, var(--green-mid) 0%, var(--green-deep) 100%);
            width: 70px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 20px;
            margin: 0 auto 1rem;
            box-shadow: 0 10px 20px -5px rgba(19,107,85,0.3);
        }
        .form-input {
            width: 100%;
            padding: 0.7rem 1rem 0.7rem 2.75rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.75rem;
            background: #fafcff;
            transition: all 0.2s;
            font-size: 0.9rem;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--green-mid);
            box-shadow: 0 0 0 3px rgba(19,107,85,0.1);
            background: white;
        }
        .btn-login {
            background: linear-gradient(135deg, var(--green-mid), var(--green-deep));
            color: white;
            font-weight: 600;
            padding: 0.75rem;
            border-radius: 0.75rem;
            width: 100%;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(10,64,51,0.2);
        }
        .btn-login:disabled {
            opacity: 0.7;
            transform: none;
        }
        .alert-error, .alert-success {
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }
        .alert-error {
            background: #fff5f5;
            border: 1px solid #fed7d7;
            color: #c53030;
        }
        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }
        .pw-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
        }
        .pw-toggle:hover { color: var(--green-mid); }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .spin { animation: spin 0.8s linear infinite; }
    </style>
</head>
<body>

<div class="w-full max-w-md">
    <!-- Branding (centered, simple) -->
    <div class="text-center mb-6">
        <div class="brand-circle">
            <i class="fa-solid fa-coins text-white text-2xl"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-800"><?= htmlspecialchars($settings['org_name'] ?? 'Akabbo Social Fund') ?></h1>
        <p class="text-slate-500 text-sm mt-1"><?= htmlspecialchars($settings['org_tagline'] ?? 'Growing Together, Prospering Together') ?></p>
    </div>

    <!-- Login Card -->
    <div class="login-card p-6 sm:p-8">
        <div class="mb-6">
            <h2 class="text-xl font-bold text-slate-800">Welcome back</h2>
            <p class="text-slate-500 text-sm mt-1">Sign in to your account to continue</p>
        </div>

        <!-- Dynamic alert box (for AJAX errors / flash) -->
        <?php if ($flash): ?>
            <div class="mb-4 <?= $flash['type'] === 'error' ? 'alert-error' : 'alert-success' ?>">
                <i class="fa-solid <?= $flash['type'] === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check' ?> mt-0.5"></i>
                <span><?= htmlspecialchars($flash['message']) ?></span>
            </div>
        <?php endif; ?>
        <div id="alertBox" class="hidden mb-4"></div>

        <form id="loginForm" novalidate>
            <?= $csrfField ?? '' ?>

            <div class="space-y-4">
                <!-- Email -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Email Address</label>
                    <div class="relative">
                        <i class="fa-regular fa-envelope absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                               class="form-input" placeholder="name@example.com" autofocus required>
                    </div>
                </div>

                <!-- Password -->
                <div>
                    <div class="flex justify-between items-center mb-1.5">
                        <label class="block text-sm font-semibold text-slate-700">Password</label>
                        <a href="<?= APP_URL ?>/auth/forgot-password" class="text-xs font-medium text-emerald-600 hover:underline">Forgot password?</a>
                    </div>
                    <div class="relative">
                        <i class="fa-solid fa-lock absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" required>
                        <button type="button" class="pw-toggle" id="pwToggle" aria-label="Show password">
                            <i class="fa-regular fa-eye" id="pwIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Remember me -->
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="remember" name="remember" value="1" class="w-4 h-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500/20">
                    <label for="remember" class="text-sm text-slate-600 cursor-pointer">Keep me signed in for 7 days</label>
                </div>
            </div>

            <button type="submit" class="btn-login mt-6 flex items-center justify-center gap-2" id="loginBtn">
                <span id="btnText">Sign In to Dashboard <i class="fa-solid fa-arrow-right"></i></span>
                <span id="btnLoading" class="hidden items-center gap-2">
                    <svg class="spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Authenticating…
                </span>
            </button>
        </form>

        <div class="mt-6 pt-4 border-t border-slate-100 text-center">
            <p class="text-xs text-slate-400">
                <i class="fa-solid fa-shield-halved mr-1"></i>
                Secure access · Enterprise-grade encryption
            </p>
        </div>
    </div>

    <div class="text-center mt-5 text-xs text-slate-400">
        &copy; <?= date('Y') ?> <?= htmlspecialchars($settings['org_name'] ?? 'Akabbo Social Fund') ?> &nbsp;·&nbsp; v<?= $settings['app_version'] ?? '1.0.0' ?>
    </div>
</div>

<script>
// Password toggle
const pwToggle = document.getElementById('pwToggle');
const pwInput = document.getElementById('password');
const pwIcon = document.getElementById('pwIcon');
pwToggle.addEventListener('click', () => {
    const show = pwInput.type === 'password';
    pwInput.type = show ? 'text' : 'password';
    pwIcon.className = show ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
});

// Show alert helper
function showAlert(type, message) {
    const box = document.getElementById('alertBox');
    box.className = type === 'error' ? 'alert-error mb-4' : 'alert-success mb-4';
    box.innerHTML = `<i class="fa-solid ${type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check'} mt-0.5"></i><span>${escapeHtml(message)}</span>`;
    box.classList.remove('hidden');
    setTimeout(() => box.classList.add('hidden'), 5000);
}

function escapeHtml(str) {
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

// AJAX form submission
const form = document.getElementById('loginForm');
form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;

    if (!email || !password) {
        showAlert('error', 'Please enter both email and password.');
        return;
    }

    const btn = document.getElementById('loginBtn');
    const btnText = document.getElementById('btnText');
    const btnLoading = document.getElementById('btnLoading');

    btn.disabled = true;
    btnText.classList.add('hidden');
    btnLoading.classList.remove('hidden');
    btnLoading.style.display = 'flex';

    try {
        const formData = new FormData(form);
        const response = await fetch('<?= APP_URL ?>/auth/login', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': document.querySelector('[name="csrf_token"]')?.value || ''
            },
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            showAlert('success', result.message || 'Login successful. Redirecting…');
            setTimeout(() => {
                window.location.href = result.data?.redirect || '<?= APP_URL ?>/dashboard';
            }, 600);
        } else {
            showAlert('error', result.message || 'Invalid credentials. Please try again.');
            // Shake effect
            document.querySelector('.login-card').animate([
                { transform: 'translateX(0)' },
                { transform: 'translateX(-6px)' },
                { transform: 'translateX(6px)' },
                { transform: 'translateX(0)' }
            ], { duration: 250, iterations: 2 });
        }
    } catch (err) {
        showAlert('error', 'Network error. Please check your connection.');
    } finally {
        btn.disabled = false;
        btnText.classList.remove('hidden');
        btnLoading.classList.add('hidden');
        btnLoading.style.display = 'none';
    }
});
</script>
</body>
</html>