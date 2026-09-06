<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/merchant_auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
startSecureSession();
requireMerchantGuest();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VrakeIT — Register Your Business</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets/css/style.css" rel="stylesheet">
  <style>
    body { background: radial-gradient(circle at top left, rgba(16, 185, 129, 0.18), transparent 45%), radial-gradient(circle at bottom right, rgba(245, 158, 11, 0.15), transparent 45%); background-color: #f8fafc; min-height: 100vh; font-family: 'Poppins', sans-serif; }
    .reg-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px; }
    .reg-card {
      background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px);
      border: 1px solid rgba(255, 255, 255, 0.8); border-radius: 24px;
      padding: 36px 28px; width: 100%; max-width: 520px;
      box-shadow: 0 15px 35px rgba(0,0,0,0.08);
    }
    .merchant-badge { display: inline-flex; align-items: center; gap: 8px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #059669; border-radius: 50px; padding: 6px 16px; font-size: 12px; font-weight: 600; margin-bottom: 16px; }
    .reg-title { font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; }
    .reg-sub { color: rgba(0,0,0,0.6); font-size: 13px; margin-bottom: 24px; }
    .form-label { display: block; color: rgba(0,0,0,0.7); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
    .form-control, .form-select {
      background: #ffffff; border: 1px solid rgba(0,0,0,0.1);
      color: #0f172a; border-radius: 12px; padding: 12px 14px; font-size: 14px;
      font-family: 'Poppins', sans-serif;
    }
    .form-control::placeholder { color: rgba(0,0,0,0.4); }
    .form-control:focus, .form-select:focus { background: #ffffff; border-color: #10b981; color: #0f172a; box-shadow: 0 0 0 0.25rem rgba(16, 185, 129, 0.15); }
    .form-select option { background: #ffffff; color: #0f172a; }
    .upload-zone {
      display: block; width: 100%; border: 2px dashed rgba(0,0,0,0.15); border-radius: 12px; padding: 20px; background: rgba(255,255,255,0.5);
      text-align: center; cursor: pointer; transition: all 0.2s; color: rgba(0,0,0,0.6);
    }
    .upload-zone:hover { border-color: #10b981; color: #10b981; background: rgba(16, 185, 129, 0.05); }
    .upload-zone input { display: none; }
    .upload-zone i { font-size: 28px; display: block; margin-bottom: 6px; }
    .upload-zone small { font-size: 11px; display: block; margin-top: 4px; opacity: 0.6; }
    .upload-filename { color: #10b981; font-size: 12px; margin-top: 6px; display: none; }
    .btn-merchant { width: 100%; background: linear-gradient(135deg, #16a34a, #15803d); color: #fff; border: none; border-radius: 12px; padding: 14px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
    .btn-merchant:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(22,163,74,0.4); }
    .btn-merchant:disabled { opacity: 0.6; transform: none; cursor: not-allowed; }
    .divider { border: none; border-top: 1px solid rgba(0,0,0,0.1); margin: 20px 0; }
    .pw-wrap { position: relative; }
    .pw-toggle { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #4ade80; font-size: 16px; cursor: pointer; z-index: 5; }
    .section-label { font-size: 11px; font-weight: 800; color: rgba(0,0,0,0.5); text-transform: uppercase; letter-spacing: 1px; margin: 24px 0 16px; display: flex; align-items: center; gap: 8px; }
    
    .section-label::after { content: ''; flex: 1; height: 1px; background: rgba(255,255,255,0.1); }
    .pending-notice { background: rgba(234,179,8,0.12); border: 1px solid rgba(234,179,8,0.3); border-radius: 12px; padding: 14px 16px; color: #fde047; font-size: 13px; display: none; margin-bottom: 16px; }
    .alert-box { border-radius: 10px; font-size: 13px; }
    .reg-logo-img { width: 64px; height: 64px; object-fit: contain; display: block; margin: 0 auto 12px; }
    .loading-spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; vertical-align: middle; margin-right: 6px; }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>
  <div class="reg-wrap">
    <div class="reg-card">
      <img src="../assets/img/system_logo.png" alt="VrakeIT Logo" class="reg-logo-img">
      <div class="merchant-badge"><i class="bi bi-shop"></i> Merchant Registration</div>
      <h1 class="reg-title">Register Your Business</h1>
      <p class="reg-sub">Partner with VrakeIT — reward drivers and grow your community presence.</p>

      <div id="alertBox" class="alert alert-box py-2 mb-3 d-none"></div>
      <div class="pending-notice" id="pendingNotice">
        <i class="bi bi-hourglass-split me-2"></i>
        <strong>Registration received!</strong> Your application is under review. An administrator will approve your account soon. You will be able to log in once approved.
      </div>

      <form id="regForm" novalidate enctype="multipart/form-data">
        <div class="section-label">Business Information</div>

        <div class="mb-3">
          <label class="form-label">Business Name</label>
          <input type="text" class="form-control" name="business_name" placeholder="e.g. Mang Juan's Carwash" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Business Type</label>
          <select class="form-select" name="business_type" required>
            <option value="" disabled selected>Select a category</option>
            <option>Food & Beverage</option>
            <option>Fuel & Transport</option>
            <option>Automotive</option>
            <option>Health & Wellness</option>
            <option>Shopping & Retail</option>
            <option>Services</option>
            <option>Other</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Business Address</label>
          <input type="text" class="form-control" name="business_address" placeholder="Street, Barangay, City" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Contact Number</label>
          <input type="tel" class="form-control" name="contact_number" placeholder="09XXXXXXXXX" required>
        </div>

        <div class="section-label">Business Documents</div>

        <div class="mb-3">
          <label class="form-label">Business Permit <span style="color:#f59e0b;">*</span></label>
          <label class="upload-zone" id="permitZone">
            <input type="file" name="permit" id="permitFile" accept="image/*,.pdf" required>
            <i class="bi bi-file-earmark-text"></i>
            <span id="permitLabel">Click to upload Business Permit</span>
            <small>JPG, PNG, or PDF — max 10MB</small>
          </label>
          <p class="upload-filename" id="permitName"></p>
        </div>
        <div class="mb-3">
          <label class="form-label">Business Logo <span style="color:rgba(0,0,0,0.4);">(optional)</span></label>
          <label class="upload-zone" id="logoZone">
            <input type="file" name="logo" id="logoFile" accept="image/*">
            <i class="bi bi-image"></i>
            <span id="logoLabel">Click to upload Logo</span>
            <small>JPG or PNG — max 5MB</small>
          </label>
          <p class="upload-filename" id="logoName"></p>
        </div>

        <div class="section-label">Account Credentials</div>

        <div class="mb-3">
          <label class="form-label">Business Email</label>
          <input type="email" class="form-control" name="email" placeholder="you@yourbusiness.com" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <div class="pw-wrap">
            <input type="password" class="form-control" name="password" id="password" placeholder="Minimum 8 characters" required>
            <button type="button" class="pw-toggle" id="togglePass"><i class="bi bi-eye" id="eyeIcon"></i></button>
          </div>
        </div>

        <button type="submit" class="btn-merchant mb-3" id="regBtn">
          <i class="bi bi-send me-1"></i> Submit for Approval
        </button>
        <hr class="divider">
        <a href="login.php" style="color:rgba(0,0,0,0.6);font-size:13px;text-decoration:none;display:block;text-align:center;transition:color 0.2s;" onmouseover="this.style.color='#10b981'" onmouseout="this.style.color='rgba(0,0,0,0.6)'">
          <i class="bi bi-arrow-left me-1"></i> Back to Merchant Login
        </a>
      </form>
    </div>
  </div>

  <script>
    // File upload labels
    function bindUpload(inputId, labelId, nameId) {
      document.getElementById(inputId).addEventListener('change', function() {
        if (this.files[0]) {
          document.getElementById(nameId).style.display = 'block';
          document.getElementById(nameId).textContent = '✓ ' + this.files[0].name;
          document.getElementById(labelId).textContent = 'Click to change file';
        }
      });
    }
    bindUpload('permitFile', 'permitLabel', 'permitName');
    bindUpload('logoFile', 'logoLabel', 'logoName');

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

    document.getElementById('regForm').addEventListener('submit', async e => {
      e.preventDefault();
      const btn = document.getElementById('regBtn');
      btn.disabled = true;
      btn.innerHTML = '<span class="loading-spinner"></span> Submitting...';
      document.getElementById('alertBox').classList.add('d-none');

      try {
        const res = await fetch('api/register.php', { method: 'POST', body: new FormData(e.target) });
        const data = await res.json();
        if (data.success) {
          document.getElementById('regForm').style.display = 'none';
          document.getElementById('pendingNotice').style.display = 'block';
          btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Submitted!';
        } else {
          showAlert(data.message);
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-send me-1"></i> Submit for Approval';
        }
      } catch (error) {
        console.error("Fetch Error:", error);
        showAlert('Connection error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send me-1"></i> Submit for Approval';
      }
    });
  </script>
</body>
</html>