<?php
require_once __DIR__ . '/includes/db.php';

$token = $_GET['token'] ?? '';
$isValid = false;
$userId = null;

if ($token) {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires_at > NOW()");
    $stmt->execute([$token]);
    if ($row = $stmt->fetch()) {
        $isValid = true;
        $userId = $row['id'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>VrakeIT - Set New Password</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
  <div class="auth-bg" style="background-image: url('assets/img/background.png'); background-size: cover; background-position: center;">
    <div class="auth-card">
      <div class="auth-logo">
        <h1>New Password</h1>
      </div>

      <?php if (!$isValid): ?>
        <div class="alert alert-danger py-2 mb-3" style="font-size:13px;border-radius:10px;">
          Invalid or expired token. Please request a new link.
        </div>
        <a href="forgot_password.php" class="btn-primary-vr d-block text-center text-decoration-none">Request New Link</a>
      <?php else: ?>
        <div id="alertBox" class="alert py-2 mb-3 d-none" style="font-size:13px;border-radius:10px;"></div>
        <form id="newPasswordForm">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
          <div class="form-floating mb-3">
            <input type="password" class="form-control" id="password" name="password" placeholder="New Password" required minlength="8">
            <label for="password">New Password</label>
          </div>
          <button type="submit" class="btn-primary-vr mb-3" id="saveBtn">Save Password</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <script>
    const form = document.getElementById('newPasswordForm');
    if (form) {
        form.addEventListener('submit', async e => {
          e.preventDefault();
          const btn = document.getElementById('saveBtn');
          const alertBox = document.getElementById('alertBox');
          btn.disabled = true;
          
          const fd = new FormData(e.target);
          try {
            const res = await fetch('api/update_password.php', { method: 'POST', body: fd });
            const data = await res.json();
            
            alertBox.className = `alert alert-${data.success ? 'success' : 'danger'} py-2 mb-3`;
            alertBox.textContent = data.message;
            alertBox.classList.remove('d-none');
            
            if(data.success) {
                setTimeout(() => window.location.href = 'index.php', 2000);
            } else {
                btn.disabled = false;
            }
          } catch {
            alertBox.className = `alert alert-danger py-2 mb-3`;
            alertBox.textContent = 'Connection error.';
            alertBox.classList.remove('d-none');
            btn.disabled = false;
          }
        });
    }
  </script>
</body>
</html>