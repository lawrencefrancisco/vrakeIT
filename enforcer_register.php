<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
startSecureSession();
requireGuest();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VrakeIT – Enforcer Account Registration</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
  <div class="auth-bg" style="background-image: url('assets/img/background.png'); background-size: cover; background-position: center;">
    <div class="auth-card" style="max-width:460px;">
      <div class="auth-logo">
        <div class="logo-icon"><i class="bi bi-shield-fill-exclamation"></i></div>
        <h1>VrakeIT</h1>
        <p class="text-white" style="font-weight: 500;">Create enforcer account</p>
      </div>

      <div id="alertBox" class="alert py-2 mb-3 d-none" style="font-size:13px;border-radius:10px;"></div>

      <form id="regForm" novalidate>
        <input type="hidden" name="role" value="enforcer">
        <div class="row g-2 mb-2">
          <div class="col-6">
            <div class="form-floating">
              <input type="text" class="form-control" id="first_name" name="first_name" placeholder="First Name" required>
              <label for="first_name">First Name</label>
            </div>
          </div>
          <div class="col-6">
            <div class="form-floating">
              <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Last Name" required>
              <label for="last_name">Last Name</label>
            </div>
          </div>
        </div>
        <div class="form-floating mb-2">
          <input type="email" class="form-control" id="email" name="email" placeholder="Email" required>
          <label for="email"><i class="bi bi-envelope me-1"></i>Email Address</label>
        </div>
        <div class="form-floating mb-2">
          <input type="tel" class="form-control" id="phone" name="phone" placeholder="09XXXXXXXXX" required>
          <label for="phone"><i class="bi bi-phone me-1"></i>Mobile Number (09XXXXXXXXX)</label>
        </div>
        <div class="form-floating mb-2" style="position:relative;">
          <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
          <label for="password"><i class="bi bi-lock me-1"></i>Password (min 8 chars)</label>

          <!-- Changed color:#999; to color:#E90101; below -->
          <button type="button" id="togglePass" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:#E90101;z-index:10;">
            <i class="bi bi-eye" id="eyeIcon"></i>
          </button>
        </div>

        <!-- OTP Delivery Method -->
        <p class="mb-2" style="font-size:13px;font-weight:600;color: white;">Send OTP via:</p>
        <div class="d-flex gap-2 mb-3">
          <label class="flex-fill" style="cursor:pointer;">
            <input type="radio" name="delivery_method" value="sms" class="d-none" id="otpSms" checked>
            <div class="id-type-btn" id="smsBtnLabel">
              <i class="bi bi-chat-dots"></i>
              <div style="font-size:13px;font-weight:600;">SMS</div>
            </div>
          </label>
          <label class="flex-fill" style="cursor:pointer;">
            <input type="radio" name="delivery_method" value="email" class="d-none" id="otpEmail">
            <div class="id-type-btn" id="emailBtnLabel">
              <i class="bi bi-envelope"></i>
              <div style="font-size:13px;font-weight:600;">Email</div>
            </div>
          </label>
        </div>

        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="agree_tos" name="agree_tos" value="1" required>
          <label class="form-check-label" for="agree_tos" style="font-size:13px; color:whitesmoke">
            I agree to the <a href="#" style="color:var(--red);">Terms and Conditions</a>
          </label>
        </div>

        <button type="submit" class="btn-primary-vr mb-3" id="regBtn">
          <i class="bi bi-arrow-right-circle me-1"></i> Continue
        </button>
        <a href="enforcer_login.php" class="btn-outline-vr d-block text-center text-decoration-none" style="padding:13px;">
          <i class="bi bi-arrow-left me-1"></i> Back to Login
        </a>
      </form>
    </div>
  </div>

  <script>
    const alertBox = document.getElementById('alertBox');
    const regBtn = document.getElementById('regBtn');

    // OTP toggle visuals
    document.querySelectorAll('input[name="delivery_method"]').forEach(r => {
      r.addEventListener('change', () => {
        document.getElementById('smsBtnLabel').classList.toggle('active', document.getElementById('otpSms').checked);
        document.getElementById('emailBtnLabel').classList.toggle('active', document.getElementById('otpEmail').checked);
      });
    });
    document.getElementById('smsBtnLabel').classList.add('active');

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
      alertBox.style = 'font-size:13px;border-radius:10px;';
      alertBox.innerHTML = `<i class="bi bi-exclamation-circle me-1"></i>${msg}`;
    }

    document.getElementById('regForm').addEventListener('submit', async e => {
      e.preventDefault();
      alertBox.classList.add('d-none');
      regBtn.disabled = true;
      regBtn.innerHTML = '<span class="loading-spinner"></span> Sending OTP...';
      const fd = new FormData(e.target);
      try {
        const res = await fetch('api/register.php', {
          method: 'POST',
          body: fd
        });
        const data = await res.json();
        if (data.success) {
          sessionStorage.setItem('otp_target', data.masked_target);
          sessionStorage.setItem('otp_method', data.delivery_method);
          window.location.href = data.redirect;
        } else {
          showAlert(data.message);
          regBtn.disabled = false;
          regBtn.innerHTML = '<i class="bi bi-arrow-right-circle me-1"></i> Continue';
        }
      } catch {
        showAlert('Connection error.');
        regBtn.disabled = false;
        regBtn.innerHTML = '<i class="bi bi-arrow-right-circle me-1"></i> Continue';
      }
    });

    
  </script>
</body>

</html>