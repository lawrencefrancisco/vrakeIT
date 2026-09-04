<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
startSecureSession();
if (empty($_SESSION['pending_reg'])) {
    header('Location: ' . BASE_URL . '/register.php');
    exit;
}
$method = $_SESSION['pending_reg']['delivery_method'] ?? 'sms';

// Prevent back-forward cache from restoring this page
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>VrakeIT – Verify OTP</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-bg">
  <div class="auth-card" style="text-align:center;">
    <div class="auth-logo">
      <div class="logo-icon" style="background:linear-gradient(135deg,#007ED2,#005fa3);">
        <i class="bi bi-shield-lock-fill"></i>
      </div>
      <h1 style="color:var(--blue);">Verify OTP</h1>
    </div>

    <div id="methodBadge" style="display:inline-flex;align-items:center;gap:6px;background:#e8f4ff;color:var(--blue);border-radius:20px;padding:6px 16px;font-size:13px;font-weight:600;margin-bottom:8px;">
      <i class="bi bi-<?= $method === 'sms' ? 'chat-dots' : 'envelope' ?>"></i>
      Code sent via <?= strtoupper($method) ?>
    </div>
    <p id="targetDisplay" style="font-size:13px;color:var(--muted);margin-bottom:20px;"></p>

    <div id="alertBox" class="alert py-2 mb-3 d-none" style="font-size:13px;border-radius:10px;text-align:left;"></div>

    <form id="otpForm">
      <div class="otp-inputs mb-4" id="otpInputs">
        <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]">
        <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]">
        <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]">
        <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]">
        <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]">
        <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]">
      </div>

      <div class="mb-3" style="font-size:13px;color:var(--muted);">
        Code expires in: <strong id="countdown" style="color:var(--red);">5:00</strong>
      </div>

      <button type="submit" class="btn-blue-vr mb-3" id="verifyBtn">
        <i class="bi bi-check-circle me-1"></i> Verify OTP
      </button>
    </form>

    <p style="font-size:13px;color:var(--muted);">
      Didn't receive it?
      <button id="resendBtn" style="background:none;border:none;color:var(--red);font-weight:600;cursor:pointer;font-family:Poppins,sans-serif;font-size:13px;" disabled>
        Resend OTP (<span id="resendCountdown">60</span>s)
      </button>
    </p>

    <a href="register.php" style="display:block;margin-top:12px;font-size:13px;color:var(--muted);text-decoration:none;">
      <i class="bi bi-arrow-left me-1"></i> Back to Register
    </a>
  </div>
</div>

<script>
const inputs   = document.querySelectorAll('.otp-input');
const alertBox = document.getElementById('alertBox');
const verifyBtn= document.getElementById('verifyBtn');
const resendBtn= document.getElementById('resendBtn');

// Restore masked target from sessionStorage
const target = sessionStorage.getItem('otp_target') || '';
document.getElementById('targetDisplay').textContent = target ? `Sent to: ${target}` : '';

// OTP input navigation
inputs.forEach((input, i) => {
  input.addEventListener('input', () => {
    input.value = input.value.replace(/\D/g, '');
    if (input.value && i < inputs.length - 1) inputs[i+1].focus();
  });
  input.addEventListener('keydown', e => {
    if (e.key === 'Backspace' && !input.value && i > 0) inputs[i-1].focus();
  });
  input.addEventListener('paste', e => {
    e.preventDefault();
    const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
    [...text].forEach((c, j) => { if (inputs[j]) inputs[j].value = c; });
    if (inputs[Math.min(text.length, 5)]) inputs[Math.min(text.length, 5)].focus();
  });
});

// Countdown timer (5 min)
let timeLeft = 300;
const countEl = document.getElementById('countdown');
const timer = setInterval(() => {
  timeLeft--;
  const m = Math.floor(timeLeft/60), s = timeLeft%60;
  countEl.textContent = `${m}:${s.toString().padStart(2,'0')}`;
  if (timeLeft <= 0) { clearInterval(timer); countEl.textContent = 'Expired'; countEl.style.color='#dc3545'; }
}, 1000);

// Resend countdown (60s)
let resendLeft = 60;
const resendEl = document.getElementById('resendCountdown');
const resendTimer = setInterval(() => {
  resendLeft--;
  resendEl.textContent = resendLeft;
  if (resendLeft <= 0) {
    clearInterval(resendTimer);
    resendBtn.disabled = false;
    resendBtn.innerHTML = 'Resend OTP';
  }
}, 1000);

function showAlert(msg, type='danger') {
  alertBox.className = `alert alert-${type} py-2 mb-3`;
  alertBox.style = 'font-size:13px;border-radius:10px;text-align:left;';
  alertBox.innerHTML = `<i class="bi bi-exclamation-circle me-1"></i>${msg}`;
}

document.getElementById('otpForm').addEventListener('submit', async e => {
  e.preventDefault();
  const otp = [...inputs].map(i => i.value).join('');
  if (otp.length < 6) { showAlert('Please enter all 6 digits.'); return; }
  verifyBtn.disabled = true;
  verifyBtn.innerHTML = '<span class="loading-spinner"></span> Verifying...';
  alertBox.classList.add('d-none');
  const fd = new FormData();
  fd.append('otp', otp);
  try {
    const res = await fetch('api/verify_otp.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      verifyBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Verified!';
      if (sessionStorage.getItem('is_enforcer') === '1') {
        sessionStorage.removeItem('is_enforcer');
        window.location.href = 'enforcer_landing.php';
      } else {
        window.location.href = data.redirect;
      }
    } else {
      showAlert(data.message);
      verifyBtn.disabled = false;
      verifyBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Verify OTP';
    }
  } catch { showAlert('Connection error.'); verifyBtn.disabled = false; verifyBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Verify OTP'; }
});

resendBtn.addEventListener('click', async () => {
  resendBtn.disabled = true;
  resendBtn.textContent = 'Sending...';
  try {
    const res = await fetch('api/resend_otp.php', { method: 'POST' });
    const data = await res.json();
    if (data.success) {
      showAlert('OTP resent successfully!', 'success');
      timeLeft = 300;
    } else {
      showAlert(data.message);
      resendBtn.disabled = false;
      resendBtn.textContent = 'Resend OTP';
    }
  } catch { showAlert('Connection error.'); resendBtn.disabled = false; resendBtn.textContent = 'Resend OTP'; }
});

// Guard against bfcache: if this page is restored from browser cache
// after a successful OTP submission (user is now logged in), the server
// will see no pending_reg and redirect back to register.php. Force a
// fresh reload here so that redirect actually happens.
window.addEventListener('pageshow', (event) => {
  if (event.persisted) {
    window.location.reload();
  }
});
</script>
</body>
</html>
