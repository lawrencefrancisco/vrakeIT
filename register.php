<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
startSecureSession();
requireGuest();

// If a stale pending registration exists (user backed out of OTP), clear it
// so they cannot accidentally re-use an old OTP or have a ghost account state.
if (!empty($_SESSION['pending_reg'])) {
    unset($_SESSION['pending_reg']);
}

// Prevent back-forward cache from restoring this page after form submission
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VrakeIT – Create Account</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>

<body style="background-color: #f8fafc;">
  <div class="auth-bg" style="background: radial-gradient(circle at top left, rgba(0, 126, 210, 0.22), transparent 45%), radial-gradient(circle at bottom right, rgba(233, 1, 1, 0.18), transparent 45%); background-color: #f8fafc; position: relative; min-height: 100vh; overflow: hidden;">
    <div class="auth-card" style="max-width:460px; background: rgba(255,255,255,0.85); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.5); box-shadow: 0 20px 40px rgba(0,0,0,0.08);">
      <div class="auth-logo">
        <div class="logo-icon"><img src="assets/img/system_logo.png" alt="VrakeIT Logo"></div>
        <h1>
          <span style="color: #007ED2;">Vrake</span><span style="color: #E90101;">IT</span>
        </h1>
        <p style="color: rgba(0,0,0,0.6); font-weight: 500; font-size: 14px;">Create your account</p>
      </div>

      <div id="alertBox" class="alert py-2 mb-3 d-none" style="font-size:13px;border-radius:10px;"></div>

      <form id="regForm" novalidate>
        <input type="hidden" name="role" value="user">
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
        <div class="form-floating mb-2">
          <input type="date" class="form-control" id="birthdate" name="birthdate"
                 max="<?= date('Y-m-d', strtotime('-16 years')) ?>" required
                 placeholder="Date of Birth">
          <label for="birthdate"><i class="bi bi-calendar3 me-1"></i>Date of Birth</label>
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
        <p class="mb-2" style="font-size:13px;font-weight:600;color: #0f172a;">Send OTP via:</p>
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
          <label class="form-check-label" for="agree_tos" style="font-size:13px; color:#0f172a">
            I agree to the <a href="#" style="color:var(--red);">Terms and Conditions</a>
          </label>
        </div>

        <button type="submit" class="btn-primary-vr mb-3" id="regBtn" style="background: linear-gradient(135deg, #007ED2, #005A9E); border: none; box-shadow: 0 4px 15px rgba(0, 126, 210, 0.3);">
          <i class="bi bi-arrow-right-circle me-1"></i> Continue
        </button>
        <a href="index.php" class="btn-outline-vr d-block text-center text-decoration-none" style="padding:13px; border: 1px solid rgba(0,0,0,0.1); color: #0f172a; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.02); transition: all 0.3s ease;" onmouseover="this.style.background='#f8fafc'; this.style.borderColor='rgba(0, 126, 210, 0.3)'; this.style.color='#007ED2';" onmouseout="this.style.background='#fff'; this.style.borderColor='rgba(0,0,0,0.1)'; this.style.color='#0f172a';">
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
          // Replace the current history entry so the Back button skips this page,
          // preventing the form from being re-submitted via browser back navigation.
          window.location.replace(data.redirect);
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

    // Guard against bfcache: if this page is restored from browser cache
    // after a successful submission, force a fresh reload so requireGuest()
    // on the server runs and redirects logged-in users away.
    window.addEventListener('pageshow', (event) => {
      if (event.persisted) {
        window.location.reload();
      }
    });

    
  </script>
</body>

</html>