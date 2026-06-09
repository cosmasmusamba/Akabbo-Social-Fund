<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — Akabbo Social Fund</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,700;1,9..144,300&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root {
      --green-deep:  #0a4033;
      --green-mid:   #136b55;
      --green-light: #1a8f6f;
      --gold:        #c9963a;
      --gold-light:  #e8b85a;
      --cream:       #faf7f2;
      --surface:     #ffffff;
    }
    * { box-sizing: border-box; }
    body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--cream); }
    .brand-font { font-family: 'Fraunces', serif; }

    /* Animated gradient orbs background */
    .bg-canvas {
      position: fixed; inset: 0; overflow: hidden; z-index: 0;
      background: linear-gradient(135deg, #0a2218 0%, #0d3529 40%, #102c1e 100%);
    }
    .orb {
      position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0.35;
      animation: drift 12s ease-in-out infinite;
    }
    .orb-1 { width: 500px; height: 500px; background: radial-gradient(circle, #1a8f6f 0%, transparent 70%); top: -100px; left: -100px; animation-delay: 0s; }
    .orb-2 { width: 400px; height: 400px; background: radial-gradient(circle, #c9963a 0%, transparent 70%); bottom: -80px; right: -80px; animation-delay: -4s; }
    .orb-3 { width: 300px; height: 300px; background: radial-gradient(circle, #136b55 0%, transparent 70%); top: 50%; left: 50%; transform: translate(-50%, -50%); animation-delay: -8s; }

    @keyframes drift {
      0%, 100% { transform: translate(0, 0) scale(1); }
      33%       { transform: translate(30px, -20px) scale(1.05); }
      66%       { transform: translate(-20px, 15px) scale(0.97); }
    }

    /* Card */
    .login-card {
      background: rgba(255,255,255,0.97);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255,255,255,0.3);
      box-shadow: 0 32px 80px rgba(0,0,0,0.35), 0 0 0 1px rgba(255,255,255,0.05);
    }

    /* Logo mark */
    .logo-mark {
      background: linear-gradient(135deg, var(--green-mid) 0%, var(--green-light) 100%);
      box-shadow: 0 8px 24px rgba(26,143,111,0.4);
    }
    .logo-ring {
      border: 2px solid rgba(201,150,58,0.5);
      box-shadow: 0 0 0 4px rgba(201,150,58,0.15), inset 0 0 0 1px rgba(201,150,58,0.2);
    }

    /* Input fields */
    .form-input {
      width: 100%; padding: 12px 16px 12px 46px;
      border: 1.5px solid #e2e8f0; border-radius: 10px;
      background: #f8fafc; color: #1e293b; font-size: 0.9rem;
      transition: all 0.2s;
      font-family: 'Plus Jakarta Sans', sans-serif;
    }
    .form-input:focus {
      outline: none;
      border-color: var(--green-light);
      background: #fff;
      box-shadow: 0 0 0 3px rgba(26,143,111,0.12);
    }
    .form-input.error {
      border-color: #ef4444;
      background: #fff8f8;
      box-shadow: 0 0 0 3px rgba(239,68,68,0.1);
    }

    /* Submit button */
    .btn-login {
      width: 100%;
      padding: 13px;
      background: linear-gradient(135deg, var(--green-mid) 0%, var(--green-deep) 100%);
      color: #fff; font-weight: 700; font-size: 0.95rem; letter-spacing: 0.01em;
      border: none; border-radius: 10px; cursor: pointer;
      transition: all 0.25s; position: relative; overflow: hidden;
      font-family: 'Plus Jakarta Sans', sans-serif;
    }
    .btn-login::after {
      content: '';
      position: absolute; inset: 0;
      background: linear-gradient(135deg, rgba(201,150,58,0.3) 0%, transparent 60%);
      opacity: 0; transition: opacity 0.25s;
    }
    .btn-login:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(10,64,51,0.4); }
    .btn-login:hover::after { opacity: 1; }
    .btn-login:active { transform: translateY(0); }
    .btn-login:disabled { opacity: 0.65; cursor: not-allowed; transform: none; }

    /* Alert */
    .alert-error {
      background: #fff2f2; border: 1px solid #fecaca; border-radius: 10px;
      padding: 12px 16px; color: #b91c1c; font-size: 0.875rem;
      display: flex; align-items: flex-start; gap: 10px;
    }
    .alert-success {
      background: #f0fdf9; border: 1px solid #a7f3d0; border-radius: 10px;
      padding: 12px 16px; color: #065f46; font-size: 0.875rem;
      display: flex; align-items: flex-start; gap: 10px;
    }

    /* Stats strip */
    .stat-chip {
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.15);
      border-radius: 100px; padding: 6px 14px;
      color: rgba(255,255,255,0.85); font-size: 0.78rem;
      display: flex; align-items: center; gap: 6px;
    }
    .stat-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--gold); }

    /* Password toggle */
    .pw-toggle { position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
      color: #94a3b8; cursor: pointer; transition: color 0.2s; background: none; border: none; padding: 4px; }
    .pw-toggle:hover { color: var(--green-mid); }

    /* Checkbox */
    input[type="checkbox"] { accent-color: var(--green-mid); width: 16px; height: 16px; }

    /* Loading spinner */
    .spin { animation: spin 1s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Slide in */
    @keyframes slideUp {
      from { opacity: 0; transform: translateY(20px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .animate-slide-up { animation: slideUp 0.5s ease forwards; }
    .delay-1 { animation-delay: 0.1s; opacity: 0; }
    .delay-2 { animation-delay: 0.2s; opacity: 0; }
    .delay-3 { animation-delay: 0.3s; opacity: 0; }
  </style>
</head>

<body class="h-full flex">
  <!-- Background -->
  <div class="bg-canvas">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>
  </div>

  <!-- Left panel (desktop only) -->
  <div class="hidden lg:flex lg:w-[44%] xl:w-[48%] flex-col justify-between p-12 relative z-10">
    <!-- Brand -->
    <div class="animate-slide-up">
      <div class="flex items-center gap-3 mb-3">
        <div class="logo-mark logo-ring w-11 h-11 rounded-xl flex items-center justify-center">
          <i class="fa-solid fa-coins text-white text-lg"></i>
        </div>
        <div>
          <div class="brand-font text-white text-xl font-bold leading-tight">Akabbo</div>
          <div class="text-xs text-green-300 font-medium tracking-wider uppercase">Social Fund</div>
        </div>
      </div>
    </div>

    <!-- Hero text -->
    <div class="animate-slide-up delay-1">
      <h1 class="brand-font text-white text-5xl xl:text-6xl font-light leading-tight mb-6">
        Growing<br>
        <em class="not-italic font-bold" style="color: var(--gold-light);">together</em>,<br>
        prospering<br>together.
      </h1>
      <p class="text-green-200 text-base leading-relaxed max-w-md" style="opacity:0.85;">
        A complete digital platform for managing savings, loans, and member contributions — built for cooperatives and SACCOs across Uganda.
      </p>
    </div>

    <!-- Stats chips -->
    <div class="animate-slide-up delay-2 flex flex-wrap gap-3">
      <div class="stat-chip"><span class="stat-dot"></span> Real-time Dashboards</div>
      <div class="stat-chip"><span class="stat-dot"></span> Loan Lifecycle Management</div>
      <div class="stat-chip"><span class="stat-dot"></span> Role-Based Access</div>
      <div class="stat-chip"><span class="stat-dot"></span> Audit Compliance</div>
      <div class="stat-chip"><span class="stat-dot"></span> Mobile-First Design</div>
    </div>
  </div>

  <!-- Right panel / Login form -->
  <div class="flex-1 flex items-center justify-center p-5 sm:p-8 relative z-10">
    <div class="w-full max-w-[420px]">

      <!-- Mobile logo -->
      <div class="lg:hidden flex justify-center mb-8 animate-slide-up">
        <div class="text-center">
          <div class="logo-mark logo-ring w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-3">
            <i class="fa-solid fa-coins text-white text-2xl"></i>
          </div>
          <div class="brand-font text-white text-2xl font-bold">Akabbo Social Fund</div>
          <div class="text-green-300 text-xs tracking-wider mt-1">ENTERPRISE FUND MANAGEMENT</div>
        </div>
      </div>

      <!-- Card -->
      <div class="login-card rounded-2xl p-8 animate-slide-up delay-1">
        <div class="mb-7">
          <h2 class="text-2xl font-bold text-slate-800 mb-1">Welcome back</h2>
          <p class="text-slate-500 text-sm">Sign in to your account to continue</p>
        </div>

        <!-- Flash/error message -->
        <div id="alertBox" class="hidden mb-5"></div>

        <!-- Login form -->
        <form id="loginForm" novalidate>
          <?= $csrfField ?? '' ?>

          <!-- Email -->
          <div class="mb-4">
            <label class="block text-sm font-semibold text-slate-700 mb-1.5" for="email">
              Email Address
            </label>
            <div class="relative">
              <i class="fa-regular fa-envelope absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
              <input
                type="email" id="email" name="email"
                class="form-input" placeholder="you@example.com"
                autocomplete="email" autofocus required
              >
            </div>
          </div>

          <!-- Password -->
          <div class="mb-5">
            <div class="flex justify-between items-center mb-1.5">
              <label class="block text-sm font-semibold text-slate-700" for="password">Password</label>
              <a href="<?= APP_URL ?>/auth/forgot-password" class="text-xs font-medium hover:underline" style="color:var(--green-mid);">
                Forgot password?
              </a>
            </div>
            <div class="relative">
              <i class="fa-solid fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
              <input
                type="password" id="password" name="password"
                class="form-input" placeholder="Enter your password"
                autocomplete="current-password" required
              >
              <button type="button" class="pw-toggle" id="pwToggle" title="Show/hide password">
                <i class="fa-regular fa-eye text-sm" id="pwIcon"></i>
              </button>
            </div>
          </div>

          <!-- Remember me -->
          <div class="flex items-center gap-2.5 mb-6">
            <input type="checkbox" id="remember" name="remember" value="1">
            <label for="remember" class="text-sm text-slate-600 cursor-pointer select-none">
              Keep me signed in for 7 days
            </label>
          </div>

          <!-- Submit -->
          <button type="submit" class="btn-login" id="loginBtn">
            <span id="btnText" class="flex items-center justify-center gap-2">
              <i class="fa-solid fa-arrow-right-to-bracket"></i>
              Sign In to Dashboard
            </span>
            <span id="btnLoading" class="hidden items-center justify-center gap-2">
              <svg class="spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
              </svg>
              Authenticating…
            </span>
          </button>
        </form>

        <!-- Divider -->
        <div class="mt-6 pt-5 border-t border-slate-100 text-center">
          <p class="text-xs text-slate-400">
            Secure access powered by enterprise-grade encryption.<br>
            Unauthorized access is strictly prohibited.
          </p>
        </div>
      </div>

      <!-- Footer -->
      <p class="text-center text-xs mt-5 animate-slide-up delay-3" style="color: rgba(255,255,255,0.4);">
        © <?= date('Y') ?> Akabbo Social Fund &nbsp;·&nbsp; v<?= APP_VERSION ?>
      </p>
    </div>
  </div>

  <script nonce="<?= $_SERVER['CSP_NONCE'] ?? '' ?>">
    // ─── Password toggle ──────────────────────────────────────────────
    document.getElementById('pwToggle').addEventListener('click', () => {
      const pw   = document.getElementById('password');
      const icon = document.getElementById('pwIcon');
      const show = pw.type === 'password';
      pw.type    = show ? 'text' : 'password';
      icon.className = show ? 'fa-regular fa-eye-slash text-sm' : 'fa-regular fa-eye text-sm';
    });

    // ─── Show alert ───────────────────────────────────────────────────
    function showAlert(type, message) {
      const box  = document.getElementById('alertBox');
      const icon = type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check';
      box.className = type === 'error' ? 'alert-error mb-5' : 'alert-success mb-5';
      box.innerHTML = `<i class="fa-solid ${icon} mt-0.5 shrink-0"></i><span>${message}</span>`;
      box.classList.remove('hidden');
    }

    // ─── Form submission via AJAX ─────────────────────────────────────
    document.getElementById('loginForm').addEventListener('submit', async (e) => {
      e.preventDefault();

      const email    = document.getElementById('email').value.trim();
      const password = document.getElementById('password').value;
      const btn      = document.getElementById('loginBtn');
      const btnText  = document.getElementById('btnText');
      const loading  = document.getElementById('btnLoading');

      // Basic client-side validation
      if (!email || !password) {
        showAlert('error', 'Please enter your email address and password.');
        return;
      }

      // Set loading state
      btn.disabled       = true;
      btnText.classList.add('hidden');
      loading.classList.remove('hidden');
      loading.style.display = 'flex';

      try {
        const formData = new FormData(e.target);
        const response = await fetch('<?= APP_URL ?>/auth/login', {
          method:  'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': document.querySelector('[name="csrf_token"]')?.value || ''
          },
          body: formData
        });

        const result = await response.json();

        if (result.success) {
          showAlert('success', result.message || 'Login successful. Redirecting…');
          // Brief delay for UX feedback then redirect
          setTimeout(() => {
            window.location.href = result.data?.redirect || '<?= APP_URL ?>/dashboard';
          }, 600);
        } else {
          showAlert('error', result.message || 'Login failed. Please check your credentials.');
          // Shake animation on card
          document.querySelector('.login-card').animate(
            [{ transform: 'translateX(0)' }, { transform: 'translateX(-8px)' }, { transform: 'translateX(8px)' }, { transform: 'translateX(0)' }],
            { duration: 300, iterations: 2 }
          );
        }
      } catch (err) {
        showAlert('error', 'A network error occurred. Please check your connection and try again.');
        console.error('Login error:', err);
      } finally {
        btn.disabled      = false;
        btnText.classList.remove('hidden');
        loading.classList.add('hidden');
        loading.style.display = 'none';
      }
    });

    // ─── Input error clearing ─────────────────────────────────────────
    ['email', 'password'].forEach(id => {
      document.getElementById(id).addEventListener('input', () => {
        document.getElementById(id).classList.remove('error');
      });
    });
  </script>
</body>
</html>
