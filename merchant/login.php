<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/merchant_auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
startSecureSession();
requireMerchantGuest();
$timeout = isset($_GET['timeout']) ? 'Your session expired. Please log in again.' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VrakeIT — Merchant Login</title>
  <meta name="description" content="VrakeIT Merchant Portal — Log in to manage your rewards and advertisements.">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets/css/style.css" rel="stylesheet">
  <style>
    :root { --m-green: #16a34a; --m-green-light: #22c55e; --m-dark: #052e16; }
    .auth-bg {
      min-height: 100vh; display: flex; align-items: center; justify-content: center;
      background: radial-gradient(circle at top left, rgba(16, 185, 129, 0.18), transparent 45%), radial-gradient(circle at bottom right, rgba(245, 158, 11, 0.15), transparent 45%); background-color: #f8fafc;
      padding: 20px;
    }
    .auth-card {
      background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px);
      border: 1px solid rgba(255, 255, 255, 0.8); border-radius: 24px;
      padding: 40px 32px; width: 100%; max-width: 420px;
      box-shadow: 0 15px 35px rgba(0,0,0,0.08);
    }
    .merchant-badge {
      display: inline-flex; align-items: center; gap: 8px;
      background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3);
      color: #059669; border-radius: 50px; padding: 6px 16px;
      font-size: 12px; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 20px;
    }
    .auth-logo .logo-img { width: 72px; height: 72px; object-fit: contain; margin-bottom: 10px; display: block; }
    .auth-logo h1 { font-size: 2rem; font-weight: 800; margin: 0; color: #fff; }
    .auth-logo h1 span.v { color: #10b981; }
    .auth-logo h1 span.r { color: #f59e0b; }
    .auth-logo p { color: rgba(0,0,0,0.6); font-size: 13px; margin: 6px 0 28px; }
    .form-control {
      background: #ffffff; border: 1px solid rgba(0,0,0,0.1);
      color: #0f172a; border-radius: 12px; padding: 14px 16px; font-size: 14px;
      transition: all 0.2s;
    }
    .form-control::placeholder { color: transparent; }
    .form-control:focus { background: #ffffff; border-color: #10b981; color: #0f172a; box-shadow: 0 0 0 0.25rem rgba(16, 185, 129, 0.15); }
    .form-floating label { color: rgba(0,0,0,0.6); font-size: 13px; }
    .form-floating > .form-control:focus ~ label,
    .form-floating > .form-control:not(:placeholder-shown) ~ label { color: rgba(0,0,0,0.6); }
    .form-floating > .form-control:focus ~ label::after,
.form-floating > .form-control:not(:placeholder-shown) ~ label::after {
  background-color: transparent !important;
}
    .btn-merchant {
      width: 100%; background: linear-gradient(135deg, #16a34a, #15803d);
      color: #fff; border: none; border-radius: 12px; padding: 14px;
      font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.2s;
    }
    .btn-merchant:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(22,163,74,0.4); }
    .btn-merchant:disabled { opacity: 0.6; transform: none; cursor: not-allowed; }
    .btn-outline-merchant {
      width: 100%; background: rgba(16, 185, 129, 0.05);
      border: 1px solid rgba(16, 185, 129, 0.3); color: #10b981;
      border-radius: 12px; padding: 13px; font-size: 14px; font-weight: 600;
      cursor: pointer; transition: all 0.2s; text-decoration: none; display: block; text-align: center;
    }
    .btn-outline-merchant:hover { background: rgba(16, 185, 129, 0.1); color: #059669; border-color: rgba(16, 185, 129, 0.4); }
    .divider { border: none; border-top: 1px solid rgba(0,0,0,0.1); margin: 20px 0; }
    .pw-wrap { position: relative; }
    .pw-toggle { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #10b981; font-size: 16px; cursor: pointer; z-index: 5; }
    .alert-box { border-radius: 10px; font-size: 13px; }
    .loading-spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; vertical-align: middle; margin-right: 6px; }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>
  <div class="auth-bg">
    <div class="auth-card">
      <div class="text-center">
        <div class="merchant-badge"><i class="bi bi-shop"></i> Merchant Portal</div>
        <div class="auth-logo">
          <img src="../assets/img/system_logo.png" alt="VrakeIT Logo" class="logo-img">
          <h1><span class="v">Vrake</span><span class="r">IT</span></h1>
          <p>Partner Business Login</p>
        </div>
      </div>

      <?php if ($timeout): ?>
        <div class="alert alert-warning alert-box py-2 mb-3"><i class="bi bi-clock me-1"></i><?= $timeout ?></div>
      <?php endif; ?>

      <div id="alertBox" class="alert alert-box py-2 mb-3 d-none"></div>

      <form id="loginForm" novalidate>
        <div class="form-floating mb-3">
          <input type="email" class="form-control" id="email" name="email" placeholder="Business Email" required>
          <label for="email"><i class="bi bi-envelope me-1"></i>Business Email</label>
        </div>
        <div class="pw-wrap mb-3">
          <div class="form-floating">
            <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
            <label for="password"><i class="bi bi-lock me-1"></i>Password</label>
          </div>
          <button type="button" class="pw-toggle" id="togglePass"><i class="bi bi-eye" id="eyeIcon"></i></button>
        </div>

        <button type="submit" class="btn-merchant mb-3" id="loginBtn">
          <i class="bi bi-box-arrow-in-right me-1"></i> Log In
        </button>
        <hr class="divider">
        <a href="register.php" class="btn-outline-merchant">
          <i class="bi bi-person-plus me-1"></i> Register Your Business
        </a>
        <div class="text-center mt-3">
          <a href="../index.php" style="color:rgba(0,0,0,0.6);font-size:13px;text-decoration:none;transition:color 0.2s;" onmouseover="this.style.color='#10b981'" onmouseout="this.style.color='rgba(0,0,0,0.6)'">
            <i class="bi bi-arrow-left me-1"></i> Back to Driver Login
          </a>
        </div>
      </form>
    </div>
  </div>
  <script>
    document.getElementById('togglePass').addEventListener('click', () => {
      const p = document.getElementById('password');
      const e = document.getElementById('eyeIcon');
      p.type = p.type === 'password' ? 'text' : 'password';
      e.className = p.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
    });

    function showAlert(msg, type = 'danger') {
      const box = document.getElementById('alertBox');
      box.className = `alert alert-${type} alert-box py-2 mb-3`;
      box.innerHTML = `<i class="bi bi-exclamation-circle me-1"></i>${msg}`;
    }

    document.getElementById('loginForm').addEventListener('submit', async e => {
      e.preventDefault();
      const btn = document.getElementById('loginBtn');
      btn.disabled = true;
      btn.innerHTML = '<span class="loading-spinner"></span> Logging in...';
      document.getElementById('alertBox').classList.add('d-none');
      try {
        const res = await fetch('api/login.php', { method: 'POST', body: new FormData(e.target) });
        const data = await res.json();
        if (data.success) {
          btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Success!';
          window.location.href = data.redirect;
        } else {
          showAlert(data.message);
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-box-arrow-in-right me-1"></i> Log In';
        }
      } catch {
        showAlert('Connection error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-box-arrow-in-right me-1"></i> Log In';
      }
    });
  </script>
</body>
</html>
