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

<body>
  <div class="auth-bg" style="background-image: url('assets/img/background.png'); background-size: cover; background-position: center; position: relative;">

    <!-- Merchant Portal Mini Button -->
    <div style="position: absolute; top: 20px; right: 20px; z-index: 100;">
      <!-- Updated href to point to the correct merchant login directory -->
      <a href="merchant/login.php" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.15); color: #ffffff; border-radius: 30px; backdrop-filter: blur(10px); padding: 8px 16px; font-size: 12px; font-weight: 500; text-decoration: none; display: flex; align-items: center; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0,0,0,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.15)'; this.style.borderColor='rgba(255,200,0,0.5)'; this.style.color='#FFD700'; this.style.transform='translateY(-2px)';" onmouseout="this.style.background='rgba(255,255,255,0.05)'; this.style.borderColor='rgba(255,255,255,0.15)'; this.style.color='#ffffff'; this.style.transform='translateY(0)';">
        <i class="bi bi-shop-window me-2"></i> Merchant Login
      </a>
    </div>

    <div class="auth-card">
      <div class="auth-logo">
        <div class="logo-icon"><img src="assets/img/system_logo.png" alt="VrakeIT Logo"></div>
        <h1>
          <span style="color: #007ED2;">Vrake</span><span style="color: #E90101;">IT</span>
        </h1>
        <p class="text-white">Road Incident Reporting System</p>
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
          <a href="#" style="font-size:13px;color: whitesmoke;text-decoration:none;">Forgot Password?</a>
        </div>
        <button type="submit" class="btn-primary-vr mb-3" id="loginBtn">
          <i class="bi bi-box-arrow-in-right me-1"></i> Login
        </button>
        <div class="divider-text" style="color: whitesmoke;">or</div>
        <a href="register.php" class="btn-outline-vr d-block text-center text-decoration-none mt-3" style="padding:13px;">
          <i class="bi bi-person-plus me-1"></i> Create Account
        </a>
      </form>
    </div>

    <!-- Enforcer Portal Link -->
    <div style="position: absolute; bottom: 20px; width: 100%; text-align: center;">
      <a href="enforcer_login.php" class="enforcer-access-btn">
        <span class="enforcer-icon">
          <i class="bi bi-shield-lock-fill"></i>
        </span>
        <span class="enforcer-text">Enforcer Portal</span>
        <span class="enforcer-arrow">
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