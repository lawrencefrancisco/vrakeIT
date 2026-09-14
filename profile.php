<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
startSecureSession();
requireLogin();
$user = getLoggedInUser();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VrakeIT – My Profile</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
  <header class="app-header">
    <?php $backLink = (($user['role'] ?? 'user') === 'enforcer') ? 'enforcer_landing.php' : 'landing.php'; ?>
    <a href="<?= $backLink ?>" class="back-btn" style="font-size:22px;"><i class="bi bi-arrow-left"></i></a>
    <span class="header-logo">My Profile</span>
    <span style="width:32px;"></span>
  </header>

  <div style="padding:24px 16px;">
    <div id="alertBox" class="alert py-2 mb-3 d-none" style="font-size:13px;border-radius:10px;"></div>

    <!-- Avatar -->
    <div style="text-align:center;margin-bottom:24px;">
      <div class="avatar-wrap">
        <img src="<?= getAvatarUrl($user['avatar']) ?>" alt="Avatar" id="avatarPreview">
        <button class="avatar-edit-btn" onclick="document.getElementById('avatarInput').click()" title="Change Photo">
          <i class="bi bi-camera-fill"></i>
        </button>
      </div>
      <input type="file" id="avatarInput" accept="image/*" style="display:none;" onchange="previewAvatar(this)">
      <h5 style="font-weight:700; margin-bottom:2px; color:#ffffff;">
        <?= sanitize($user['first_name'] . ' ' . $user['last_name']) ?>
      </h5>
      <div style="font-size:13px;color:var(--semi);"><?= sanitize($user['email']) ?></div>
      <?php if (($user['role'] ?? 'user') !== 'enforcer'): ?>
      <div style="display:inline-flex;align-items:center;gap:6px;background:#e8f4ff;color:var(--blue);border-radius:20px;padding:5px 14px;font-size:13px;font-weight:600;margin-top:8px;">
        <i class="bi bi-star-fill" style="color:#FFD700;"></i>
        <?= number_format($user['points']) ?> Good Citizen Points
      </div>
      <?php else: ?>
      <div style="display:inline-flex;align-items:center;gap:6px;background:rgba(0,126,210,0.1);color:#007ED2;border-radius:20px;padding:5px 14px;font-size:13px;font-weight:600;margin-top:8px;">
        <i class="bi bi-shield-lock-fill"></i> Enforcer Account
      </div>
      <?php endif; ?>
      <?php if ($user['account_verified']): ?>
        <div style="display:inline-flex;align-items:center;gap:6px;background:#d1e7dd;color:#0a3622;border-radius:20px;padding:5px 14px;font-size:13px;font-weight:600;margin-top:6px;margin-left:6px;">
          <i class="bi bi-patch-check-fill"></i> Verified
        </div>
      <?php endif; ?>
    </div>

    <!-- Form -->
    <form id="profileForm">
      <div class="section-card" style="margin:0 0 16px;">
        <div class="section-title"><i class="bi bi-person-fill"></i> Personal Info</div>
        <div class="row g-2 mb-2">
          <div class="col-6">
            <div class="form-floating">
              <input type="text" class="form-control" id="first_name" name="first_name" placeholder="First Name" value="<?= sanitize($user['first_name']) ?>" required>
              <label for="first_name">First Name</label>
            </div>
          </div>
          <div class="col-6">
            <div class="form-floating">
              <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Last Name" value="<?= sanitize($user['last_name']) ?>" required>
              <label for="last_name">Last Name</label>
            </div>
          </div>
        </div>
        <div class="form-floating mb-2">
          <input type="tel" class="form-control" id="phone" name="phone" placeholder="Phone" value="<?= sanitize($user['phone']) ?>" required>
          <label for="phone"><i class="bi bi-phone me-1"></i>Mobile Number</label>
        </div>
        <div class="form-floating">
          <input type="email" class="form-control" id="email" name="email" placeholder="Email" value="<?= sanitize($user['email']) ?>" required>
          <label for="email"><i class="bi bi-envelope me-1"></i>Email Address</label>
        </div>
      </div>

      <button type="submit" class="btn-primary-vr" id="saveBtn">
        <i class="bi bi-check-circle me-1"></i> Save Changes
      </button>
    </form>

    <!-- Danger zone -->
    <div style="margin-top:20px;text-align:center;">
      <button id="logoutBtnProfile" style="background:none;border:none;color:var(--red);font-family:Poppins,sans-serif;font-size:14px;cursor:pointer;">
        <i class="bi bi-box-arrow-right me-1"></i> Logout
      </button>
    </div>
  </div>

  <script>
    let newAvatarFile = null;

    function previewAvatar(input) {
      newAvatarFile = input.files[0];
      if (!newAvatarFile) return;
      const reader = new FileReader();
      reader.onload = e => document.getElementById('avatarPreview').src = e.target.result;
      reader.readAsDataURL(newAvatarFile);
    }

    function showAlert(msg, type = 'danger') {
      const box = document.getElementById('alertBox');
      box.className = `alert alert-${type} py-2 mb-3`;
      box.style = 'font-size:13px;border-radius:10px;';
      box.innerHTML = `<i class="bi bi-${type==='success'?'check':'exclamation'}-circle me-1"></i>${msg}`;
    }

    document.getElementById('profileForm').addEventListener('submit', async e => {
      e.preventDefault();
      const btn = document.getElementById('saveBtn');
      btn.disabled = true;
      btn.innerHTML = '<span class="loading-spinner"></span> Saving...';
      const fd = new FormData(e.target);
      if (newAvatarFile) fd.append('avatar', newAvatarFile);
      try {
        const res = await fetch('api/update_profile.php', {
          method: 'POST',
          body: fd
        });
        const data = await res.json();
        showAlert(data.message, data.success ? 'success' : 'danger');
        if (data.success && data.avatar_url) document.getElementById('avatarPreview').src = data.avatar_url;
      } catch {
        showAlert('Connection error.');
      }
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Save Changes';
    });

    document.getElementById('logoutBtnProfile').addEventListener('click', async () => {
      await fetch('api/logout.php', {
        method: 'POST'
      });
      window.location.href = 'index.php';
    });
  </script>
</body>

</html>