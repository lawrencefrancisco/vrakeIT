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
  <title>VrakeIT - Enforcer Login</title>
  <meta name="description" content="Login to VrakeIT - The Philippines' road incident reporting system.">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  
</head>
<body style="background-color: #020b18;">
  <div class="auth-bg" style="background-image: url('assets/img/background.png'); background-size: cover; background-position: center; position: relative; min-height: 100vh; overflow: hidden; display: flex; align-items: center; justify-content: center;">
    
    <!-- Reuse animated elements from Enforcer Landing for consistency -->
    <div class="hero-grid-overlay"></div>
    <div class="speed-lines">
      <div class="speed-line"></div>
      <div class="speed-line"></div>
      <div class="speed-line"></div>
      <div class="speed-line"></div>
      <div class="speed-line"></div>
    </div>

    <div class="auth-card enforcer-glass-card" style="position: relative; z-index: 10; width: 100%; max-width: 420px; padding: 40px 30px;">
      
      <!-- Network Active Status Chip -->
      <div class="text-center mb-4 mt-2">
        <div class="saas-status-chip" style="background: rgba(233,1,1,0.15); border: 1px solid rgba(233,1,1,0.3); color: #ff4d4d; font-size: 11px;">
          <span class="pulse-dot" style="background: #E90101; box-shadow: 0 0 0 0 rgba(233, 1, 1, 0.7);"></span>
          Enforcer Network Active
        </div>
      </div>

      <div class="auth-logo mb-4">
        <div class="logo-icon" style="background:transparent;border:none;box-shadow:none;">
            <img src="assets/img/system_logo.png" alt="VrakeIT Logo" style="width:80px;height:80px;object-fit:contain;">
        </div>
        <h1 style="font-weight: 800; letter-spacing: -0.5px; margin-bottom: 5px;">
          <span style="color: #ffffff;">Vrake</span><span style="color: #E90101;">IT</span>
        </h1>
        <p style="color: rgba(255,255,255,0.7); font-weight: 500; font-size: 14px;">Authorized Personnel Only</p>
      </div>

      <?php if ($timeout): ?>
        <div class="alert alert-warning py-2 mb-3" style="font-size:13px; border-radius:10px; background: rgba(255,193,7,0.1); border: 1px solid rgba(255,193,7,0.3); color: #ffc107;">
          <i class="bi bi-clock me-1"></i><?= $timeout ?>
        </div>
      <?php endif; ?>
      <div id="alertBox" class="alert py-2 mb-3 d-none" style="font-size:13px; border-radius:10px;"></div>

      <form id="loginForm" novalidate>
        <input type="hidden" name="login_type" value="enforcer">
        
        <div class="form-floating mb-3">
          <input type="email" class="form-control dark-input" id="email" name="email" placeholder="Email" required>
          <label for="email" style="color: rgba(255,255,255,0.6);"><i class="bi bi-envelope me-1"></i>Email Address</label>
        </div>
        
        <div class="form-floating mb-3" style="position:relative;">
          <input type="password" class="form-control dark-input" id="password" name="password" placeholder="Password" required>
          <label for="password" style="color: rgba(255,255,255,0.6);"><i class="bi bi-lock me-1"></i>Password</label>
          <button type="button" class="btn btn-sm" id="togglePass" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; color:#E90101; z-index:10;">
            <i class="bi bi-eye" id="eyeIcon"></i>
          </button>
        </div>
        
        <div class="text-end mb-4">
          <a href="#" style="font-size:13px; color: rgba(255,255,255,0.6); text-decoration:none; transition: color 0.3s;" onmouseover="this.style.color='#E90101'" onmouseout="this.style.color='rgba(255,255,255,0.6)'">Forgot Password?</a>
        </div>
        
        <button type="submit" class="btn-primary-vr enforcer-btn mb-3" id="loginBtn">
          <i class="bi bi-shield-check me-2"></i> Authenticate
        </button>
        
        
        <!-- <a href="enforcer_register.php" class="btn-outline-vr d-block text-center text-decoration-none mt-3" style="padding:13px; border-color: rgba(255,255,255,0.2); color: #ffffff; background: rgba(255,255,255,0.05);">
          <i class="bi bi-person-badge me-1"></i> Request Access
        </a> -->
      </form>
    </div>

    <!-- Standard User Login Link -->
    <div style="position: absolute; bottom: 30px; width: 100%; text-align: center; z-index: 10;">
      <a href="index.php" class="user-access-btn" style="opacity: 0.7; transition: opacity 0.3s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.7'">
        <span class="user-icon">
          <i class="bi bi-person-circle"></i>
        </span>
        <span class="user-text">Standard User Login</span>
        <span class="user-arrow">
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
      loginBtn.innerHTML = '<span class="loading-spinner"></span> Authenticating...';
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
          loginBtn.innerHTML = '<i class="bi bi-shield-check me-2"></i> Authenticate';
        }
      } catch {
        showAlert('Connection error. Please try again.');
        loginBtn.disabled = false;
        loginBtn.innerHTML = '<i class="bi bi-shield-check me-2"></i> Authenticate';
      }
    });
  </script>
</body>
</html>