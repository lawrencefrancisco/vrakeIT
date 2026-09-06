<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
startSecureSession();
requireGuest();

$timeout = isset($_GET['timeout']) ? 'Your session expired. Please log in again.' : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VrakeIT - Login</title>
  <meta name="description" content="Login to VrakeIT - The Philippines' road incident reporting system.">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>

<body style="background-color: #f8fafc;">
  <div class="auth-bg" style="background: radial-gradient(circle at top left, rgba(0, 126, 210, 0.22), transparent 45%), radial-gradient(circle at bottom right, rgba(233, 1, 1, 0.18), transparent 45%); background-color: #f8fafc; position: relative; min-height: 100vh; overflow: hidden;">

    <!-- Merchant Portal Mini Button -->
    <div style="position: absolute; top: 20px; right: 20px; z-index: 100;">
      <!-- Updated href to point to the correct merchant login directory -->
      <a href="merchant/login.php" style="background: rgba(255,255,255,0.7); border: 1px solid rgba(0,0,0,0.1); color: #0f172a; border-radius: 30px; backdrop-filter: blur(10px); padding: 8px 16px; font-size: 12px; font-weight: 600; text-decoration: none; display: flex; align-items: center; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0,0,0,0.05);" onmouseover="this.style.background='#ffffff'; this.style.borderColor='rgba(0,0,0,0.15)'; this.style.color='#007ED2'; this.style.transform='translateY(-2px)';" onmouseout="this.style.background='rgba(255,255,255,0.7)'; this.style.borderColor='rgba(0,0,0,0.1)'; this.style.color='#0f172a'; this.style.transform='translateY(0)';">
        <i class="bi bi-shop-window me-2"></i> Merchant Login
      </a>
    </div>

    <div class="auth-card">
      <div class="auth-logo">
        <div class="logo-icon"><img src="assets/img/system_logo.png" alt="VrakeIT Logo"></div>
        <h1>
          <span style="color: #007ED2;">Vrake</span><span style="color: #E90101;">IT</span>
        </h1>
        <p style="color: rgba(0,0,0,0.6); font-weight: 500; font-size: 14px;">Road Incident Reporting System</p>
      </div>

      <?php if ($timeout): ?>
        <div class="alert alert-warning py-2 mb-3" style="font-size:13px;border-radius:10px;">
          <i class="bi bi-clock me-1"></i><?= $timeout ?>
        </div>
      <?php endif; ?>

      <div id="alertBox" class="alert py-2 mb-3 d-none" style="font-size:13px;border-radius:10px;"></div>

      <form id="loginForm" novalidate>
        <input type="hidden" name="login_type" value="user">
        <div class="form-floating mb-3">
          <input type="email" class="form-control" id="email" name="email" placeholder="Email" required>
          <label for="email"><i class="bi bi-envelope me-1"></i>Email Address</label>
        </div>
        <div class="form-floating mb-3" style="position:relative;">
          <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
          <label for="password"><i class="bi bi-lock me-1"></i>Password</label>
          <button type="button" class="btn btn-sm" id="togglePass" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:#E90101;z-index:10;">
            <i class="bi bi-eye" id="eyeIcon"></i>
          </button>
        </div>
        <div class="text-end mb-3">
          <a href="#" style="font-size:13px;color: rgba(0,0,0,0.6);text-decoration:none;transition: color 0.3s;" onmouseover="this.style.color='#007ED2'" onmouseout="this.style.color='rgba(0,0,0,0.6)'">Forgot Password?</a>
        </div>
        <button type="submit" class="btn-primary-vr mb-3" id="loginBtn">
          <i class="bi bi-box-arrow-in-right me-1"></i> Login
        </button>
        <div class="divider-text" style="color: rgba(0,0,0,0.4); font-weight: 600;">or</div>
        <a href="register.php" class="btn-outline-vr d-block text-center text-decoration-none mt-3" style="padding:13px; color: #007ED2; border-color: rgba(0, 126, 210, 0.3); background: rgba(0, 126, 210, 0.05);" onmouseover="this.style.background='rgba(0, 126, 210, 0.1)';" onmouseout="this.style.background='rgba(0, 126, 210, 0.05)';">
          <i class="bi bi-person-plus me-1"></i> Create Account
        </a>
      </form>
    </div>

    <!-- Enforcer Portal Link -->
    <div style="position: absolute; bottom: 20px; width: 100%; text-align: center;">
      <a href="enforcer_login.php" class="enforcer-access-btn">
        <span class="enforcer-icon" style="background: rgba(233,1,1,0.1); color: #E90101;">
          <i class="bi bi-shield-lock-fill"></i>
        </span>
        <span class="enforcer-text" style="color: #0f172a; font-weight:600;">Enforcer Portal</span>
        <span class="enforcer-arrow" style="color: #0f172a;">
          <i class="bi bi-arrow-right-short"></i>
        </span>
      </a>
    </div>
  </div>

  <script>
    const form = document.getElementById('loginForm');
    const alertBox = document.getElementById('alertBox');
    const loginBtn = document.getElementById('loginBtn');

    document.getElementById('togglePass').addEventListener('click', () => {
      const p = document.getElementById('password');
      const e = document.getElementById('eyeIcon');
      if (p.type === 'password') {
        p.type = 'text';
        e.className = 'bi bi-eye-slash';
      } else {
        p.type = 'password';
        e.className = 'bi bi-eye';
      }
    });

    function showAlert(msg, type = 'danger') {
      alertBox.className = `alert alert-${type} py-2 mb-3`;
      alertBox.style.borderRadius = '10px';
      alertBox.style.fontSize = '13px';
      alertBox.innerHTML = `<i class="bi bi-exclamation-circle me-1"></i>${msg}`;
      alertBox.classList.remove('d-none');
    }

    form.addEventListener('submit', async e => {
      e.preventDefault();
      loginBtn.disabled = true;
      loginBtn.innerHTML = '<span class="loading-spinner"></span> Logging in...';
      alertBox.classList.add('d-none');
      const fd = new FormData(form);

      try {
        const res = await fetch('api/login.php', {
          method: 'POST',
          body: fd
        });
        const data = await res.json();
        if (data.success) {
          loginBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Success!';
          window.location.href = data.redirect;
        } else {
          showAlert(data.message);
          loginBtn.disabled = false;
          loginBtn.innerHTML = '<i class="bi bi-box-arrow-in-right me-1"></i> Login';
        }
      } catch {
        showAlert('Connection error. Please try again.');
        loginBtn.disabled = false;
        loginBtn.innerHTML = '<i class="bi bi-box-arrow-in-right me-1"></i> Login';
      }
    });
  </script>
</body>

</html>