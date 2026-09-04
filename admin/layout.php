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
:root{--sidebar-w:240px;--accent:#007ED2;--danger:#E90101;--bg:#060d1f;--card-bg:rgba(255,255,255,0.04);--border:rgba(255,255,255,0.08);}
*{box-sizing:border-box;}
body{font-family:'Poppins',sans-serif;background: linear-gradient(rgba(6, 13, 31, 0.85), rgba(6, 13, 31, 0.85)), linear-gradient(135deg, #E90101, #007ED2) !important;;color:#e2e8f0;margin:0;min-height:100vh;}
a{color:inherit;text-decoration:none;}
.sidebar{position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;background:rgba(255,255,255,0.03);border-right:1px solid var(--border);display:flex;flex-direction:column;z-index:100;overflow-y:auto;}
.sidebar-logo{padding:24px 20px 16px;border-bottom:1px solid var(--border);}
.sidebar-logo .badge-logo{width:44px;height:44px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;margin-bottom:8px;overflow:hidden;background:transparent;}.sidebar-logo .badge-logo img{width:44px;height:44px;object-fit:contain;}
.sidebar-logo .brand{font-size:16px;font-weight:800;color:#fff;}
.sidebar-logo .brand-sub{font-size:10px;color:rgba(255,255,255,0.35);text-transform:uppercase;letter-spacing:1px;}
.nav-section{padding:16px 12px 4px;font-size:10px;font-weight:700;color:rgba(255,255,255,0.25);text-transform:uppercase;letter-spacing:1px;}
.nav-item{display:flex;align-items:center;gap:10px;padding:10px 16px;border-radius:10px;margin:2px 8px;font-size:13px;font-weight:500;color:rgba(255,255,255,0.55);transition:all .2s;cursor:pointer;}
.nav-item:hover{background:rgba(255,255,255,0.06);color:#fff;}
.nav-item.active{background:rgba(0,126,210,0.2);color:#60b4ff;border:1px solid rgba(0,126,210,0.25);}
.nav-item i{font-size:16px;width:18px;text-align:center;}
.sidebar-bottom{margin-top:auto;padding:16px;border-top:1px solid var(--border);}
.main{margin-left:var(--sidebar-w);min-height:100vh;display:flex;flex-direction:column;}
.topbar{background:rgba(255,255,255,0.03);border-bottom:1px solid var(--border);padding:14px 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;}
.topbar-title{font-size:18px;font-weight:700;color:#fff;}
.topbar-right{display:flex;align-items:center;gap:16px;}
.avatar-sm{width:34px;height:34px;background:linear-gradient(135deg,var(--accent),#005fa3);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:#fff;}
.page-body{padding:28px;flex:1;}
.stat-card{background:var(--card-bg);border:1px solid var(--border);border-radius:20px;padding:22px;transition:border .2s;}
.stat-card:hover{border-color:rgba(255,255,255,0.18);}
.stat-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;margin-bottom:14px;}
.stat-num{font-size:28px;font-weight:800;color:#fff;line-height:1;}
.stat-lbl{font-size:12px;color:rgba(255,255,255,0.45);margin-top:4px;}
.stat-delta{font-size:11px;margin-top:8px;}
.data-table{width:100%;border-collapse:collapse;}
.data-table th{font-size:11px;font-weight:700;color:rgba(255,255,255,0.35);text-transform:uppercase;letter-spacing:.5px;padding:10px 14px;border-bottom:1px solid var(--border);white-space:nowrap;}
.data-table td{padding:12px 14px;border-bottom:1px solid rgba(255,255,255,0.04);font-size:13px;vertical-align:middle;}
.data-table tr:last-child td{border-bottom:none;}
.data-table tr:hover td{background:rgba(255,255,255,0.03);}
.badge-status{display:inline-flex;align-items:center;gap:4px;border-radius:999px;padding:3px 10px;font-size:11px;font-weight:600;}
.bs-pending{background:rgba(251,191,36,0.15);color:#fbbf24;}
.bs-reviewing{background:rgba(96,180,255,0.15);color:#60b4ff;}
.bs-approved{background:rgba(74,222,128,0.15);color:#4ade80;}
.bs-verified{background:rgba(20,210,180,0.15);color:#14d2b4;border:1px solid rgba(20,210,180,0.25);}
.bs-closed{background:rgba(156,163,175,0.12);color:#9ca3af;}
.bs-rejected{background:rgba(248,113,113,0.15);color:#f87171;}
.bs-deactivated{background:rgba(156,163,175,0.12);color:#9ca3af;}
.section-card{background:var(--card-bg);border:1px solid var(--border);border-radius:20px;overflow:hidden;}
.section-header{padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.section-title-text{font-size:15px;font-weight:700;color:#fff;}
.btn-admin{border:none;border-radius:8px;padding:7px 16px;font-family:'Poppins',sans-serif;font-size:12px;font-weight:600;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:6px;}
.btn-approve{background:rgba(74,222,128,0.15);color:#4ade80;border:1px solid rgba(74,222,128,0.3);}
.btn-approve:hover{background:rgba(74,222,128,0.25);}
.btn-reject{background:rgba(248,113,113,0.12);color:#f87171;border:1px solid rgba(248,113,113,0.3);}
.btn-reject:hover{background:rgba(248,113,113,0.22);}
.btn-review{background:rgba(96,180,255,0.12);color:#60b4ff;border:1px solid rgba(96,180,255,0.3);}
.btn-review:hover{background:rgba(96,180,255,0.22);}
.btn-danger-admin{background:rgba(248,113,113,0.12);color:#f87171;border:1px solid rgba(248,113,113,0.3);}
.btn-primary-admin{background:linear-gradient(135deg,var(--accent),#005fa3);color:#fff;border:none;}
.btn-primary-admin:hover{opacity:.9;transform:translateY(-1px);}
.search-input{background:rgba(255,255,255,0.06);border:1px solid var(--border);color:#fff;border-radius:10px;padding:8px 14px;font-size:13px;font-family:'Poppins',sans-serif;outline:none;width:220px;}
.search-input::placeholder{color:rgba(255,255,255,0.25);}
.search-input:focus{border-color:var(--accent);}
.modal-dark .modal-content{background:#0f172a;border:1px solid var(--border);border-radius:20px;color:#e2e8f0;}
.modal-dark .modal-header{border-bottom:1px solid var(--border);}
.modal-dark .modal-footer{border-top:1px solid var(--border);}
.modal-dark .btn-close{filter:invert(1);}
.form-dark{background:rgba(255,255,255,0.05);border:1px solid var(--border);color: var(--accent);border-radius:10px;padding:10px 14px;font-family:'Poppins',sans-serif;font-size:13px;width:100%;outline:none;}
.form-dark:focus{border-color:var(--accent);}
.form-dark::placeholder{color:rgba(255,255,255,0.25);}
.form-lbl{font-size:11px;font-weight:600;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;display:block;}
@media(max-width:768px){.sidebar{transform:translateX(-100%);}.main{margin-left:0;}}

/* ── Verification Badge ─────────────────────────────────────── */
.verified-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: rgba(74, 222, 128, 0.12);
  color: #4ade80;
  border: 1px solid rgba(74, 222, 128, 0.30);
  border-radius: 999px;
  padding: 2px 8px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.3px;
  white-space: nowrap;
  vertical-align: middle;
  line-height: 1;
  transition: background .2s;
}
.verified-badge i { font-size: 11px; }

.verified-badge-lg {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(74, 222, 128, 0.10);
  color: #4ade80;
  border: 1px solid rgba(74, 222, 128, 0.28);
  border-radius: 999px;
  padding: 4px 12px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.4px;
  margin-top: 6px;
  box-shadow: 0 0 8px rgba(74, 222, 128, 0.10);
}
.verified-badge-lg i { font-size: 13px; }
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
    <div class="nav-item" style="color:rgba(255,255,255,0.4);font-size:12px;cursor:default;">
      <i class="bi bi-person-badge"></i> <?= htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) ?>
    </div>
    <a href="logout.php" class="nav-item" style="color:#f87171;"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
</div>
<?php } // end adminNav

function adminTopbar(string $title): void { ?>
<div class="topbar">
  <div class="topbar-title"><?= htmlspecialchars($title) ?></div>
  <div class="topbar-right">
    <span style="font-size:12px;color:rgba(255,255,255,0.35);"><?= date('M d, Y') ?></span>
    <div class="avatar-sm">A</div>
  </div>
</div>
<?php } // end adminTopbar
