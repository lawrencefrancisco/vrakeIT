<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
startSecureSession();

// Already logged-in redirect
if (!empty($_SESSION['user_id'])) {
    $db = getDB();
    $u  = $db->prepare("SELECT role FROM users WHERE id=?");
    $u->execute([$_SESSION['user_id']]);
    $row = $u->fetch();
    if ($row && in_array($row['role'], ['admin', 'moderator'])) {
        header('Location: dashboard.php'); exit;
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db    = getDB();
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    // Accept admin OR moderator roles
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND role IN ('admin', 'moderator')");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($pass, $admin['password'])) {
        // Block deactivated admin/moderator accounts
        if (isset($admin['is_active']) && (int)$admin['is_active'] === 0) {
            auditLog($admin['id'], 'admin_login_blocked_deactivated', 'Deactivated account attempted login: ' . $email);
            $error = 'This account has been deactivated. Please contact the system administrator.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id']       = $admin['id'];
            $_SESSION['role']          = $admin['role'];   // store role for gate checks
            $_SESSION['last_activity'] = time();
            auditLog($admin['id'], 'admin_login', ucfirst($admin['role']) . ' login from ' . ($_SERVER['REMOTE_ADDR'] ?? ''));
            header('Location: dashboard.php'); exit;
        }
    }

    $error = 'Invalid credentials or insufficient access level.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>VrakeIT Admin Login</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{
  --accent:#007ED2;
  --bg:#f8fafc;
  --card-bg:rgba(255, 255, 255, 0.75);
  --border-light:rgba(255,255,255,0.8);
  --text:#0f172a;
  --muted:#64748b;
  --shadow-soft: 0 10px 40px -10px rgba(0,0,0,0.08);
}
*{box-sizing:border-box;margin:0;padding:0}
body{
  font-family:'Poppins',sans-serif;
  background-color: var(--bg);
  background-image: 
    radial-gradient(circle at 15% 50%, rgba(0, 126, 210, 0.08), transparent 25%),
    radial-gradient(circle at 85% 30%, rgba(233, 1, 1, 0.06), transparent 25%);
  min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;
}
.card{
  position:relative;z-index:1;
  background:var(--card-bg);
  backdrop-filter:blur(24px); -webkit-backdrop-filter:blur(24px);
  border:1px solid var(--border-light);
  border-radius:28px;
  padding:40px;width:100%;max-width:420px;
  box-shadow:var(--shadow-soft);
}
.logo{display:flex;align-items:center;gap:10px;margin-bottom:32px;}
.logo-badge{width:56px;height:56px;border-radius:16px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#fff;box-shadow:0 4px 12px rgba(0,0,0,0.05);}
.logo-badge img{width:40px;height:40px;object-fit:contain;}
.logo-text{font-size:20px;font-weight:800;color:var(--text);letter-spacing:-0.5px;}
.logo-sub{font-size:11px;color:var(--muted);font-weight:600;margin-top:-2px;text-transform:uppercase;letter-spacing:1px;}
h2{font-size:24px;font-weight:800;color:var(--text);margin-bottom:6px;letter-spacing:-0.5px;}
.sub{font-size:13px;color:var(--muted);margin-bottom:28px;}
.field{margin-bottom:16px;}
label{display:block;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;}
input{
  width:100%;
  background:#ffffff;
  border:1px solid rgba(0,0,0,0.1);
  color:var(--text);
  border-radius:12px;padding:12px 14px;
  font-family:'Poppins',sans-serif;font-size:14px;font-weight:500;
  outline:none;transition:all .2s;
  box-shadow:inset 0 2px 4px rgba(0,0,0,0.02);
}
input:focus{border-color:var(--accent);box-shadow:0 0 0 4px rgba(0,126,210,0.1);}
input::placeholder{color:#94a3b8;font-weight:400;}
.btn{width:100%;background:linear-gradient(135deg,var(--accent),#005fa3);color:#fff;border:none;border-radius:12px;padding:14px;font-family:'Poppins',sans-serif;font-size:15px;font-weight:700;cursor:pointer;transition:all .2s;margin-top:8px;box-shadow:0 4px 12px rgba(0,126,210,0.3);}
.btn:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(0,126,210,0.4);opacity:.95;}
.err{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;border-radius:10px;padding:10px 14px;font-size:13px;margin-bottom:16px;font-weight:500;}
.shield{text-align:center;margin-top:20px;font-size:12px;color:var(--muted);font-weight:500;}
/* Role hint badges */
.role-hint{display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap;}
.role-badge{display:inline-flex;align-items:center;gap:5px;background:rgba(255,255,255,0.6);border:1px solid var(--border-light);border-radius:999px;padding:4px 12px;font-size:11px;font-weight:600;color:var(--muted);box-shadow:0 2px 6px rgba(0,0,0,0.02);}
</style>
</head>
<body>
<div class="bg-orbs"><div class="orb orb1"></div><div class="orb orb2"></div></div>
<div class="card">
  <div class="logo">
    <div class="logo-badge"><img src="../assets/img/system_logo.png" alt="VrakeIT Logo"></div>
    <div><div class="logo-text">VrakeIT</div><div class="logo-sub">Administration Portal</div></div>
  </div>
  <h2>Admin Sign In</h2>
  <div class="sub">Restricted access — authorized personnel only.</div>

  <!-- Role hint -->
  <div class="role-hint">
    <span class="role-badge"><i class="bi bi-stars" style="color:#fbbf24;"></i> Admin</span>
    <span class="role-badge"><i class="bi bi-person-badge-fill" style="color:#a78bfa;"></i> Moderator</span>
  </div>

  <?php if($error): ?><div class="err"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="POST">
    <div class="field"><label>Email Address</label><input type="email" name="email" placeholder="admin@vrakeit.com" required autofocus></div>
    <div class="field"><label>Password</label><input type="password" name="password" placeholder="••••••••" required></div>
    <button type="submit" class="btn"><i class="bi bi-shield-lock me-2"></i>Sign In to Admin Portal</button>
  </form>
  <div class="shield"><i class="bi bi-lock-fill me-1"></i>Secured connection • VrakeIT Admin v2.0</div>
</div>
</body>
</html>