<?php
// Shared admin layout helpers
function adminHead(string $title, string $activePage = ''): void {
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($title) ?> — VrakeIT Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>

:root{
  --sidebar-w:260px;
  --accent:#007ED2;
  --danger:#E90101;
  --bg:#f8fafc;
  --card-bg:rgba(255, 255, 255, 0.75);
  --border:rgba(0,0,0,0.06);
  --border-light:rgba(255,255,255,0.8);
  --text:#0f172a;
  --muted:#64748b;
  --shadow-soft: 0 10px 40px -10px rgba(0,0,0,0.08);
}
*{box-sizing:border-box;}

/* ── Body: Premium light mesh gradient ── */
body{
  font-family:'Poppins',sans-serif;
  background-color: var(--bg);
  background-image: 
    radial-gradient(circle at 15% 50%, rgba(0, 126, 210, 0.08), transparent 25%),
    radial-gradient(circle at 85% 30%, rgba(233, 1, 1, 0.06), transparent 25%);
  background-attachment:fixed;
  color:var(--text);
  margin:0;
  min-height:100vh;
}
a{color:inherit;text-decoration:none;}

/* ── Sidebar: Premium Glass ── */
.sidebar{
  position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;
  background:rgba(255,255,255,0.65);
  backdrop-filter:blur(24px); -webkit-backdrop-filter:blur(24px);
  border-right:1px solid var(--border-light);
  box-shadow: 1px 0 20px rgba(0,0,0,0.03);
  display:flex;flex-direction:column;z-index:100;overflow-y:auto;
}
.sidebar-logo{padding:28px 24px 20px;border-bottom:1px solid rgba(0,0,0,0.04);}
.sidebar-logo .badge-logo{width:48px;height:48px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;margin-bottom:12px;background:#fff;box-shadow:0 4px 12px rgba(0,0,0,0.05);}.sidebar-logo .badge-logo img{width:36px;height:36px;object-fit:contain;}
.sidebar-logo .brand{font-size:18px;font-weight:800;color:var(--text);letter-spacing:-0.5px;}
.sidebar-logo .brand-sub{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:1.5px;font-weight:600;}
.nav-section{padding:24px 16px 8px;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1.2px;}
.nav-item{display:flex;align-items:center;gap:12px;padding:12px 18px;border-radius:12px;margin:4px 12px;font-size:13px;font-weight:600;color:var(--muted);transition:all .25s cubic-bezier(0.4, 0, 0.2, 1);cursor:pointer;border:1px solid transparent;}
.nav-item:hover{background:rgba(255,255,255,0.9);color:var(--text);box-shadow:0 2px 8px rgba(0,0,0,0.02);}
.nav-item.active{background:#ffffff;color:var(--accent);border:1px solid var(--border-light);box-shadow:0 4px 15px rgba(0,126,210,0.1);}
.nav-item i{font-size:17px;width:20px;text-align:center;}
.sidebar-bottom{margin-top:auto;padding:20px;border-top:1px solid rgba(0,0,0,0.04);}

/* ── Main layout ── */
.main{margin-left:var(--sidebar-w);min-height:100vh;display:flex;flex-direction:column;}

/* ── Topbar: Premium Glass ── */
.topbar{
  background:rgba(255,255,255,0.6);
  backdrop-filter:blur(24px) saturate(150%);-webkit-backdrop-filter:blur(24px) saturate(150%);
  border-bottom:1px solid var(--border-light);
  padding:16px 32px;display:flex;align-items:center;justify-content:space-between;
  position:sticky;top:0;z-index:50;
  box-shadow: 0 4px 20px -10px rgba(0,0,0,0.05);
}
.topbar-title{font-size:20px;font-weight:800;color:var(--text);letter-spacing:-0.5px;}
.topbar-right{display:flex;align-items:center;gap:16px;}
.topbar-right span{color:var(--muted);font-weight:500;font-size:13px;}
.avatar-sm{width:38px;height:38px;background:linear-gradient(135deg,var(--accent),#005fa3);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;color:#fff;box-shadow:0 4px 12px rgba(0,126,210,0.3);border:2px solid #fff;}

/* ── Page body ── */
.page-body{padding:32px;flex:1;width:100%;}

/* ── Stat cards: Premium Glass ── */
.stat-card{
  background:var(--card-bg);
  backdrop-filter:blur(20px); -webkit-backdrop-filter:blur(20px);
  border:1px solid var(--border-light);
  border-radius:24px;
  padding:24px;
  transition:all .3s ease;
  box-shadow:var(--shadow-soft);
}
.stat-card:hover{transform:translateY(-4px);box-shadow:0 20px 40px -10px rgba(0,126,210,0.12);border-color:rgba(0,126,210,0.3);}
.stat-icon{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;margin-bottom:16px;background:#fff;box-shadow:0 4px 10px rgba(0,0,0,0.04);}
.stat-num{font-size:32px;font-weight:800;color:var(--text);line-height:1;letter-spacing:-1px;}
.stat-lbl{font-size:12px;font-weight:600;color:var(--muted);margin-top:6px;text-transform:uppercase;letter-spacing:0.5px;}
.stat-delta{font-size:12px;font-weight:600;margin-top:10px;}

/* ── Section card ── */
.section-card{
  background:var(--card-bg);
  backdrop-filter:blur(20px); -webkit-backdrop-filter:blur(20px);
  border:1px solid var(--border-light);
  border-radius:24px;
  overflow:hidden;
  box-shadow:var(--shadow-soft);
  margin-bottom: 24px;
}
.section-header{padding:20px 24px;border-bottom:1px solid rgba(0,0,0,0.04);display:flex;align-items:center;justify-content:space-between;background:rgba(255,255,255,0.4);}
.section-title-text{font-size:16px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:8px;}

/* ── Data table ── */
.data-table{width:100%;border-collapse:collapse;}
.data-table th{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;padding:16px 20px;border-bottom:1px solid rgba(0,0,0,0.06);white-space:nowrap;background:rgba(0,0,0,0.01);}
.data-table td{padding:16px 20px;border-bottom:1px solid rgba(0,0,0,0.03);font-size:13px;font-weight:500;vertical-align:middle;color:var(--text);transition:background 0.2s;}
.data-table tr:last-child td{border-bottom:none;}
.data-table tr:hover td{background:rgba(255,255,255,0.6);}

/* ── Badge status ── */
.badge-status{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:4px 12px;font-size:11px;font-weight:700;letter-spacing:0.3px;line-height:1;}
.bs-pending{background:rgba(245,158,11,0.15);color:#b45309;border:1px solid rgba(245,158,11,0.2);}
.bs-reviewing{background:rgba(0,126,210,0.12);color:#007ED2;border:1px solid rgba(0,126,210,0.2);}
.bs-approved{background:rgba(34,197,94,0.12);color:#15803d;border:1px solid rgba(34,197,94,0.2);}
.bs-verified{background:rgba(14,165,163,0.12);color:#0d9488;border:1px solid rgba(14,165,163,0.2);}
.bs-closed{background:rgba(100,116,139,0.12);color:#475569;border:1px solid rgba(100,116,139,0.2);}
.bs-rejected{background:rgba(239,68,68,0.12);color:#b91c1c;border:1px solid rgba(239,68,68,0.2);}
.bs-deactivated{background:rgba(100,116,139,0.12);color:#475569;border:1px solid rgba(100,116,139,0.2);}

/* ── Admin buttons ── */
.btn-admin{border:none;border-radius:10px;padding:8px 16px;font-family:'Poppins',sans-serif;font-size:12px;font-weight:700;cursor:pointer;transition:all .2s ease;display:inline-flex;align-items:center;gap:6px;box-shadow:0 2px 6px rgba(0,0,0,0.04);}
.btn-admin:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,0.08);}
.btn-approve{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;}
.btn-approve:hover{background:#dcfce7;border-color:#86efac;}
.btn-reject{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;}
.btn-reject:hover{background:#fee2e2;border-color:#fca5a5;}
.btn-review{background:#f0f9ff;color:#0369a1;border:1px solid #bae6fd;}
.btn-review:hover{background:#e0f2fe;border-color:#7dd3fc;}
.btn-danger-admin{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;}
.btn-primary-admin{background:linear-gradient(135deg,var(--accent),#005fa3);color:#fff;border:none;box-shadow:0 4px 12px rgba(0,126,210,0.3);}
.btn-primary-admin:hover{opacity:.95;box-shadow:0 6px 16px rgba(0,126,210,0.4);}

/* ── Search ── */
.search-input{background:#ffffff;border:1px solid rgba(0,0,0,0.1);color:var(--text);border-radius:12px;padding:10px 16px;font-size:13px;font-family:'Poppins',sans-serif;font-weight:500;outline:none;width:260px;box-shadow:inset 0 2px 4px rgba(0,0,0,0.02);transition:all 0.2s;}
.search-input::placeholder{color:var(--muted);font-weight:400;}
.search-input:focus{border-color:var(--accent);box-shadow:0 0 0 4px rgba(0,126,210,0.1);}

/* ── Forms ── */
.form-dark{background:#ffffff;border:1px solid rgba(0,0,0,0.1);color:var(--text);border-radius:12px;padding:12px 16px;font-family:'Poppins',sans-serif;font-size:13px;font-weight:500;width:100%;outline:none;box-shadow:inset 0 2px 4px rgba(0,0,0,0.02);transition:all 0.2s;}
.form-dark:focus{border-color:var(--accent);box-shadow:0 0 0 4px rgba(0,126,210,0.1);}
.form-dark::placeholder{color:var(--muted);font-weight:400;}
.form-lbl{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;display:block;}

</style>
<?php } // end adminHead

function adminNav(string $activePage, array $admin): void { ?>
<div class="sidebar">
  <div class="sidebar-logo">
    <div class="badge-logo"><img src="../assets/img/system_logo.png" alt="VrakeIT Logo"></div>
    <div class="brand">VrakeIT</div>
    <div class="brand-sub">Admin Portal</div>
  </div>
  <div class="nav-section">Overview</div>
  <a href="dashboard.php" class="nav-item <?= $activePage==='dashboard'?'active':'' ?>"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
  <div class="nav-section">Management</div>
<?php if ($admin['role'] === 'admin'): ?>
  <a href="admin_personnel.php" class="nav-item <?= $activePage==='personnel'?'active':'' ?>"><i class="bi bi-person-badge-fill"></i> Personnel</a>
  <a href="users.php" class="nav-item <?= $activePage==='users'?'active':'' ?>"><i class="bi bi-people-fill"></i> Users</a>
  <?php endif; ?>
  
  <a href="incidents.php" class="nav-item <?= $activePage==='incidents'?'active':'' ?>"><i class="bi bi-exclamation-triangle-fill"></i> Incidents</a>
  <a href="contracts.php" class="nav-item <?= $activePage==='contracts'?'active':'' ?>"><i class="bi bi-file-earmark-ruled"></i> Contracts</a>
  
  <?php if ($admin['role'] === 'admin'): ?>
  <a href="merchants.php" class="nav-item <?= $activePage==='merchants'?'active':'' ?>"><i class="bi bi-shop"></i> Merchants</a>
  <a href="rewards.php" class="nav-item <?= $activePage==='rewards'?'active':'' ?>"><i class="bi bi-gift-fill"></i> Rewards</a>
  <a href="announcements.php" class="nav-item <?= $activePage==='announcements'?'active':'' ?>"><i class="bi bi-megaphone-fill"></i> Announcements</a>
  <?php endif; ?>
  
  <div class="nav-section">Verification</div>
  <a href="verifications.php" class="nav-item <?= $activePage==='verifications'?'active':'' ?>"><i class="bi bi-shield-check"></i> ID Verifications</a>
  
  <?php if ($admin['role'] === 'admin'): ?>
  <a href="vouchers.php" class="nav-item <?= $activePage==='vouchers'?'active':'' ?>"><i class="bi bi-ticket-perforated"></i> Vouchers</a>
  <?php endif; ?>
  <div class="sidebar-bottom">
    <div class="nav-item" style="color:rgba(26,26,46,0.45);font-size:12px;cursor:default;">
      <i class="bi bi-person-badge"></i> <?= htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) ?>
    </div>
    <a href="logout.php" class="nav-item" style="color:#dc2626;"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
</div>
<?php } // end adminNav

function adminTopbar(string $title): void { ?>
<div class="topbar">
  <div class="topbar-title"><?= htmlspecialchars($title) ?></div>
  <div class="topbar-right">
    <span style="font-size:12px;color:var(--muted);"><?= date('M d, Y') ?></span>
    <div class="avatar-sm">A</div>
  </div>
</div>
<?php } // end adminTopbar
