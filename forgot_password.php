<?php require_once __DIR__ . '/includes/auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>VrakeIT - Forgot Password</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
  <div class="auth-bg" style="background-image: url('assets/img/background.png'); background-size: cover; background-position: center;">
    <div class="auth-card">
      <div class="auth-logo">
        <div class="logo-icon"><i class="bi bi-shield-lock-fill"></i></div>
        <h1>Reset Password</h1>
        <p class="text-white">Enter your email to receive a reset link</p>
      </div>
      <div id="alertBox" class="alert py-2 mb-3 d-none" style="font-size:13px;border-radius:10px;"></div>
      
      <form id="forgotForm">
        <div class="form-floating mb-3">
          <input type="email" class="form-control" id="email" name="email" placeholder="Email" required>
          <label for="email"><i class="bi bi-envelope me-1"></i>Email Address</label>
        </div>
        <button type="submit" class="btn-primary-vr mb-3" id="resetBtn">
          <i class="bi bi-send me-1"></i> Send Reset Link
        </button>
        <a href="index.php" class="btn-outline-vr d-block text-center text-decoration-none mt-3" style="padding:13px;">
          <i class="bi bi-arrow-left me-1"></i> Back to Login
        </a>
      </form>
    </div>
  </div>

  <script>
    document.getElementById('forgotForm').addEventListener('submit', async e => {
      e.preventDefault();
      const btn = document.getElementById('resetBtn');
      const alertBox = document.getElementById('alertBox');
      
      btn.disabled = true;
      btn.innerHTML = '<span class="loading-spinner"></span> Sending...';
      alertBox.classList.add('d-none');
      
      const fd = new FormData(e.target);
      try {
        const res = await fetch('api/send_reset.php', { method: 'POST', body: fd });
        const data = await res.json();
        
        alertBox.className = `alert alert-${data.success ? 'success' : 'danger'} py-2 mb-3`;
        alertBox.innerHTML = `<i class="bi bi-info-circle me-1"></i>${data.message}`;
        alertBox.classList.remove('d-none');
        
        if(data.success) e.target.reset();
      } catch {
        alertBox.className = `alert alert-danger py-2 mb-3`;
        alertBox.innerHTML = `<i class="bi bi-exclamation-circle me-1"></i>Connection error.`;
        alertBox.classList.remove('d-none');
      }
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-send me-1"></i> Send Reset Link';
    });
  </script>
</body>
</html>