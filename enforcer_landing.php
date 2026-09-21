<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
startSecureSession();
requireLogin();
$user = getLoggedInUser();

$role = $user['role'] ?? 'user';

// If they are an enforcer, let them stay.
if ($role === 'enforcer') {
    // Do nothing, they belong here.
} 
// If they are a standard user, send them to their dashboard.
elseif ($role === 'user') {
    header('Location: landing.php');
    exit;
} 
// If they are anything else (like an 'admin'), send them back to login for now.
else {
    header('Location: index.php');
    exit;
}

$db   = getDB();
$announcements = $db->query("SELECT * FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 5")->fetchAll();
$totalReports  = $db->prepare("SELECT COUNT(*) FROM reports WHERE user_id = ?");
$totalReports->execute([$user['id']]);
$reportCount = $totalReports->fetchColumn();

// Get live incident count for notification badge (new unread incidents)
$lastViewed = $user['last_incident_viewed_at'] ?? '2000-01-01 00:00:00';
$liveReportsStmt = $db->prepare("SELECT COUNT(*) FROM reports WHERE status IN ('pending', 'reviewing') AND created_at > ?");
$liveReportsStmt->execute([$lastViewed]);
$liveReportCount = $liveReportsStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VrakeIT – Enforcer Home</title>
  <meta name="description" content="VrakeIT dashboard — File and track road incidents across the Philippines.">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/style.css?v=<?= time() ?>" rel="stylesheet">
  
</head>
<body>

  <!-- Sidebar Overlay -->
  <div class="sidebar-overlay" id="overlay" onclick="closeSidebar()"></div>

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <img src="<?= getAvatarUrl($user['avatar']) ?>" class="s-avatar" alt="Avatar">
      <h4><?= sanitize($user['first_name'] . ' ' . $user['last_name']) ?></h4>
      <p><?= sanitize($user['email']) ?></p>
    </div>
    <nav class="sidebar-nav">
      <a href="enforcer_landing.php" class="active"><i class="bi bi-house-fill"></i> Home</a>
      <a href="enforcer_portal.php"><i class="bi bi-exclamation-triangle-fill"></i> File Victim Report</a>
      <a href="track.php"><i class="bi bi-list-check"></i> Track Reports</a>
      <hr class="divider">
      <a href="profile.php"><i class="bi bi-person-circle"></i> My Profile</a>
      <a href="#" id="logoutBtn"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>
  </aside>

  <!-- Header -->
  <header class="app-header" style="display:flex; align-items:center; justify-content:space-between; width:100%; box-sizing:border-box; overflow:hidden; padding:12px 16px;">
    <button onclick="openSidebar()" style="background:none;border:none;color:#1e293b;font-size:24px;padding:0;flex-shrink:0;"><i class="bi bi-list"></i></button>
    <span class="header-logo" style="display:flex;align-items:center;gap:6px; flex-shrink:1; overflow:hidden;">
      <img src="assets/img/system_logo.png" alt="VrakeIT" style="width:26px;height:26px;object-fit:contain;vertical-align:middle; flex-shrink:0;">
      <span style="font-weight:800; font-size:18px;"><span style="color:var(--blue);">Vrake</span><span style="color:var(--red);">IT</span></span>
    </span>
    <div class="header-right" style="display:flex; align-items:center; gap:8px; flex-shrink:0;">
      <button onclick="manualEnablePush()" style="width:34px; height:34px; flex-shrink:0; border-radius:50%; background:var(--blue-light); border:none; color:var(--blue); display:flex; align-items:center; justify-content:center; cursor:pointer; box-shadow:0 2px 8px rgba(0,126,210,0.2);" title="Enable Push Alerts">
        <i class="bi bi-bell-fill"></i>
      </button>
      <div class="points-badge" style="background: rgba(0,126,210,0.1); color: #007ED2; display:flex; align-items:center; gap:4px; flex-shrink:0;">
        <i class="bi bi-shield-lock-fill"></i>
        <span class="d-none d-sm-inline">Enforcer</span>
      </div>
      <img src="<?= getAvatarUrl($user['avatar']) ?>" class="user-avatar-sm" alt="Profile" onclick="window.location='profile.php'" style="width:34px; height:34px; flex-shrink:0;">
    </div>
  </header>

  <!-- ═══════════════ PREMIUM SAAS HERO ═══════════════ -->
  <div class="saas-hero">
    <!-- Background layers -->
    <div class="hero-grid-overlay"></div>
    <div class="speed-lines">
      <div class="speed-line"></div>
      <div class="speed-line"></div>
      <div class="speed-line"></div>
      <div class="speed-line"></div>
      <div class="speed-line"></div>
    </div>

    <!-- Content -->
    <div class="saas-hero-content" style="padding-top:56px;">
      <div class="saas-status-chip">
        <span class="pulse-dot" style="<?= ($user['is_on_duty'] ?? 1) ? '' : 'background: #ff4d4d; box-shadow: 0 0 12px #ff4d4d;' ?>"></span>
        Enforcer Network Active
      </div>
      <h2 class="saas-hero-heading">
        Enforcer Dashboard,
        <span class="name-glow"><?= sanitize($user['first_name']) ?></span>
      </h2>
      <p class="saas-hero-sub">
        Manage reports and ensure public safety on the road.
      </p>

      <!-- On Duty Toggle -->
      <style>
        .duty-toggle-wrap {
          display: inline-flex;
          align-items: center;
          gap: 12px;
          margin-top: 20px;
          margin-bottom: 30px; /* Gives space so it doesn't clip the bottom edge */
          padding: 8px 18px;
          background: rgba(255, 255, 255, 0.1);
          backdrop-filter: blur(12px);
          -webkit-backdrop-filter: blur(12px);
          border: 1px solid rgba(255, 255, 255, 0.2);
          border-radius: 40px;
          box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .form-switch .form-check-input.duty-switch {
          background-color: rgba(255, 255, 255, 0.3);
          border-color: rgba(255, 255, 255, 0.1);
          background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='%23fff'/%3e%3c/svg%3e");
        }
        .form-switch .form-check-input.duty-switch:checked {
          background-color: #4ade80;
          border-color: #4ade80;
        }
      </style>
      <div class="duty-toggle-wrap">
        <span style="color: #fff; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Receive Live Alerts</span>
        <div class="form-check form-switch" style="margin: 0; display:flex; align-items:center;">
          <input class="form-check-input duty-switch" type="checkbox" id="dutyToggle" onchange="toggleDutyStatus(this)" <?= (!isset($user['is_on_duty']) || $user['is_on_duty'] == 1) ? 'checked' : '' ?> style="width: 44px; height: 24px; cursor: pointer; box-shadow: none; margin: 0;">
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════════════ ROAD SAFETY FLOATING TIPS ═══════════════ -->
<div class="floating-safety-tips">
  <div class="tip-orb"></div>
  <p id="safetyTipText">Stay calm — arriving safely matters more than arriving first.</p>
</div>



<script>
async function toggleDutyStatus(checkbox) {
  const isOnDuty = checkbox.checked ? '1' : '0';
  const dutyPulse = document.querySelector('.pulse-dot');
  
  try {
    const fd = new FormData();
    fd.append('is_on_duty', isOnDuty);
    
    const res = await fetch('api/toggle_duty.php', { method: 'POST', body: fd });
    const data = await res.json();
    
    if (data.success) {
      if (isOnDuty === '1') {
        dutyPulse.style.background = '#4ade80';
        dutyPulse.style.boxShadow = '0 0 12px #4ade80';
      } else {
        dutyPulse.style.background = '#ff4d4d';
        dutyPulse.style.boxShadow = '0 0 12px #ff4d4d';
      }
    } else {
      alert("Failed to update status");
      checkbox.checked = !checkbox.checked;
    }
  } catch (e) {
    alert("Network error");
    checkbox.checked = !checkbox.checked;
  }
}

/* Road Safety Assurance Tips */
/* SMOOTH PREMIUM PHRASE TRANSITION — replace your current rotateSafetyTip() script */

const safetyTips = [
  "Stay calm — arriving safely matters more than arriving first.",
  "A deep breath can prevent a dangerous reaction on the road.",
  "Give space, stay patient, and let safety lead.",
  "Your family would rather wait than worry.",
  "Road rage fades — consequences can last forever.",
  "Drive smart, stay kind, and protect every life around you.",
  "Slow down when emotions speed up.",
  "Every safe choice makes the road better for everyone."
];

let currentSafetyTip = 0;
const safetyTipText = document.getElementById("safetyTipText");

/* Prevent repeating same phrase twice */
function getNextSafetyTip() {
  let next;
  do {
    next = Math.floor(Math.random() * safetyTips.length);
  } while (next === currentSafetyTip);
  currentSafetyTip = next;
  return safetyTips[next];
}

/* Initial style for buttery animation */
safetyTipText.style.transition = "opacity 0.7s ease, transform 0.7s ease, filter 0.7s ease";

function rotateSafetyTip() {
  /* Fade out + slight upward blur */
  safetyTipText.style.opacity = "0";
  safetyTipText.style.transform = "translateY(-8px) scale(0.985)";
  safetyTipText.style.filter = "blur(6px)";

  setTimeout(() => {
    /* Change text only when invisible */
    safetyTipText.textContent = getNextSafetyTip();

    /* Reset below */
    safetyTipText.style.transform = "translateY(8px) scale(1.015)";
    safetyTipText.style.filter = "blur(6px)";

    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        /* Smooth reveal */
        safetyTipText.style.opacity = "1";
        safetyTipText.style.transform = "translateY(0) scale(1)";
        safetyTipText.style.filter = "blur(0)";
      });
    });
  }, 700);
}

/* Smooth cycle timing */
setInterval(rotateSafetyTip, 5000);
</script>

  <!-- ═══════════════ METRIC STRIP ═══════════════ -->
  <div class="metric-strip">
    <div class="metric-pill">
      <span class="m-val"><?= number_format($reportCount) ?></span>
      <span class="m-lbl">Filed Reports</span>
    </div>
    <div class="metric-pill">
      <span class="m-val"><span style="color:#007ED2;font-size:14px;" class="bi bi-shield-fill-check"></span></span>
      <span class="m-lbl">Active</span>
    </div>
    <div class="metric-pill">
      <span class="m-val"><?= $user['account_verified'] ? '<span style="color:#4ade80;font-size:14px;" class="bi bi-patch-check-fill"></span>' : '<span style="color:#FFB74D;font-size:14px;" class="bi bi-shield-exclamation"></span>' ?></span>
      <span class="m-lbl"><?= $user['account_verified'] ? 'Verified' : 'Unverified' ?></span>
    </div>
  </div>

  <!-- ═══════════════ CTA GRID ═══════════════ -->
  <div class="saas-cta-grid">
    <a href="enforcer_portal.php" class="saas-cta-btn red-cta" id="btn-file-report">
      <div class="saas-cta-icon-wrap"><i class="bi bi-shield-fill-plus"></i></div>
      <div class="cta-text-wrap">
        <span class="cta-label">File Victim Report</span>
        <span class="cta-sub">File on behalf of victim</span>
      </div>
    </a>
    <a href="enforcer_live.php" class="saas-cta-btn blue-cta" id="btn-live-incidents" style="position:relative;">
      <?php if ($liveReportCount > 0): ?>
        <span style="position:absolute; top:8px; right:8px; background:#ef4444; color:white; border-radius:50%; min-width:24px; height:24px; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:bold; box-shadow:0 2px 8px rgba(239, 68, 68, 0.4); padding: 0 6px; z-index:10; animation: pulse-red 2s infinite; border: 2px solid white;"><?= $liveReportCount ?></span>
      <?php endif; ?>
      <div class="saas-cta-icon-wrap" style="color:#007ED2; background:rgba(0,126,210,0.1);"><i class="bi bi-geo-alt-fill"></i></div>
      <div class="cta-text-wrap">
        <span class="cta-label" style="color:#007ED2;">Live Incidents</span>
        <span class="cta-sub" style="color:rgba(0,126,210,0.7);">View active emergency reports</span>
      </div>
    </a>
    <a href="track.php" class="saas-cta-btn green-cta" id="btn-track-report" style="grid-column: span 2;">
      <div class="saas-cta-icon-wrap"><i class="bi bi-list-check"></i></div>
      <div class="cta-text-wrap">
        <span class="cta-label green-cta-label">My Tracked Reports</span>
        <span class="cta-sub">View reports you have filed</span>
      </div>
    </a>
  </div>

  <!-- ═══════════════ VERIFICATION BANNER ═══════════════ -->
  <?php if (!$user['account_verified']): ?>
  <div class="glass-verify-banner">
    <div class="vb-icon"><i class="bi bi-shield-exclamation"></i></div>
    <div class="vb-text">
      <h6>Verification Required</h6>
      <p>Unlock all platform features by verifying your identity.</p>
    </div>
    <a href="verify.php" class="vb-arrow"><i class="bi bi-arrow-right"></i></a>
  </div>
  <?php endif; ?>

  <!-- ═══════════════ HOW TO FILE A REPORT ═══════════════ -->
  <div class="glass-section" id="section-how-to-file">
    <div class="glass-section-title">
      <div class="gs-icon-wrap red-icon"><i class="bi bi-shield-check"></i></div>
      <h3>How to File a Victim Report</h3>
    </div>
    <div class="glass-steps">
      <div class="glass-step">
        <div class="glass-step-num">1</div>
        <div class="glass-step-content">
          <h6>Open Enforcer Portal</h6>
          <p>Tap the red "File Victim Report" button on the dashboard.</p>
          <div class="step-loader"><div class="step-loader-fill" style="animation-delay:0s;"></div></div>
        </div>
      </div>
      <div class="glass-step">
        <div class="glass-step-num">2</div>
        <div class="glass-step-content">
          <h6>Answer Guided Questions</h6>
          <p>Follow the step-by-step wizard tailored to your situation.</p>
          <div class="step-loader"><div class="step-loader-fill" style="animation-delay:0.5s;"></div></div>
        </div>
      </div>
      <div class="glass-step">
        <div class="glass-step-num">3</div>
        <div class="glass-step-content">
          <h6>Provide Location &amp; Details</h6>
          <p>Auto-detect your GPS location and upload scene photos.</p>
          <div class="step-loader"><div class="step-loader-fill" style="animation-delay:1s;"></div></div>
        </div>
      </div>
      <div class="glass-step">
        <div class="glass-step-num">4</div>
        <div class="glass-step-content">
          <h6>Submit &amp; Track</h6>
          <p>Submit your report and monitor its status anytime.</p>
          <div class="step-loader"><div class="step-loader-fill" style="animation-delay:1.5s;"></div></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════════════ HOW IT WORKS ═══════════════ -->
  <div class="glass-section" id="section-how-it-works">
    <div class="glass-section-title">
      <div class="gs-icon-wrap blue-icon"><i class="bi bi-info-circle-fill"></i></div>
      <h3>How VrakeIT Works</h3>
    </div>
    <div class="hiw-chips">
      <div class="hiw-chip">
        <div class="hc-icon" style="background:linear-gradient(135deg,rgba(233,1,1,0.25),rgba(180,0,0,0.15));color:#ff6b6b;">
          <i class="bi bi-broadcast-pin"></i>
        </div>
        <div>
          <p class="hc-title">Community-Driven Reporting</p>
          <p class="hc-desc">Citizens like you file real-time road incident reports that feed directly into the safety database.</p>
        </div>
      </div>
      <div class="hiw-chip">
        <div class="hc-icon" style="background:linear-gradient(135deg,rgba(0,126,210,0.25),rgba(0,90,160,0.15));color:#60b4ff;">
          <i class="bi bi-shield-lock-fill"></i>
        </div>
        <div>
          <p class="hc-title">Authority Review</p>
          <p class="hc-desc">Every report is reviewed by authorities before being added to the official road safety record.</p>
        </div>
      </div>
      <div class="hiw-chip">
        <div class="hc-icon" style="background:linear-gradient(135deg,rgba(255,200,0,0.22),rgba(200,140,0,0.12));color:#FFD700;">
          <i class="bi bi-star-fill"></i>
        </div>
        <div>
          <p class="hc-title">Good Citizen Rewards</p>
          <p class="hc-desc">Earn <strong style="color:#FFD700;">Good Citizen Points</strong> for every accepted report — redeemable at partner establishments.</p>
        </div>
      </div>
      <div class="hiw-chip">
        <div class="hc-icon" style="background:linear-gradient(135deg,rgba(74,222,128,0.2),rgba(0,170,80,0.12));color:#4ade80;">
          <i class="bi bi-graph-up-arrow"></i>
        </div>
        <div>
          <p class="hc-title">Live Status Tracking</p>
          <p class="hc-desc">Track your report status in real time — from submission to resolution.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════════════ ANNOUNCEMENTS ═══════════════ -->
  <div class="glass-section" id="section-announcements">
    <div class="glass-section-title">
      <div class="gs-icon-wrap gold-icon"><i class="bi bi-megaphone-fill"></i></div>
      <h3>Announcements</h3>
    </div>

    <?php if (!empty($announcements)): ?>
      <?php foreach ($announcements as $ann): ?>
        <div class="glass-announce">
          <div class="ann-icon"><i class="bi bi-megaphone-fill"></i></div>
          <div>
            <p class="ann-title"><?= sanitize($ann['title']) ?></p>
            <p class="ann-body"><?= sanitize($ann['content']) ?></p>
            <p class="ann-date"><i class="bi bi-clock me-1"></i><?= date('M d, Y', strtotime($ann['created_at'])) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="glass-empty">
        <i class="bi bi-bell-slash-fill"></i>
        <p>No announcements yet. Check back soon.</p>
      </div>
    <?php endif; ?>
  </div>

  <div style="height:32px;"></div>

  <script>
    function openSidebar() {
      document.getElementById('sidebar').classList.add('open');
      document.getElementById('overlay').classList.add('open');
    }
    function closeSidebar() {
      document.getElementById('sidebar').classList.remove('open');
      document.getElementById('overlay').classList.remove('open');
    }
    document.getElementById('logoutBtn').addEventListener('click', async e => {
      e.preventDefault();
      await fetch('api/logout.php', { method: 'POST' });
      window.location.href = 'index.php';
    });

    // Stagger-in glass sections on scroll
    const sections = document.querySelectorAll('.glass-section, .metric-pill, .saas-cta-btn, .glass-verify-banner');
    const io = new IntersectionObserver(entries => {
      entries.forEach((entry, i) => {
        if (entry.isIntersecting) {
          entry.target.style.transition = `opacity 0.5s ease ${i * 0.08}s, transform 0.5s ease ${i * 0.08}s`;
          entry.target.style.opacity = '1';
          entry.target.style.transform = 'translateY(0)';
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1 });
    sections.forEach(el => {
      el.style.opacity = '0';
      el.style.transform = 'translateY(20px)';
      io.observe(el);
    });
    
    document.addEventListener('DOMContentLoaded', initWebPush);
  </script>
  
  
<!-- ── WEB PUSH NOTIFICATION SYSTEM ────────────────────────────── -->
<div id="push-permission-banner" style="
  display:none;
  position:fixed; bottom:80px; left:50%; transform:translateX(-50%);
  z-index:9999; max-width:380px; width:calc(100% - 32px);
  background: linear-gradient(135deg, #0f172a, #1e3a5f);
  border: 1px solid rgba(0, 126, 210, 0.4);
  border-radius:20px; padding:20px 20px 16px;
  box-shadow: 0 20px 50px rgba(0,0,0,0.4);
  animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
">
  <div style="display:flex; align-items:flex-start; gap:14px; margin-bottom:14px;">
    <div style="
      width:48px; height:48px; min-width:48px; border-radius:14px;
      background: linear-gradient(135deg, #007ED2, #0056a3);
      display:flex; align-items:center; justify-content:center;
      font-size:22px; box-shadow: 0 6px 20px rgba(0,126,210,0.35);
    "><i class="bi bi-bell-fill" style="color:#fff;"></i></div>
    <div>
      <div style="font-weight:700; font-size:14px; color:#f1f5f9; margin-bottom:4px;">Stay Notified On-Duty</div>
      <div style="font-size:12px; color:#94a3b8; line-height:1.4;">Enable push notifications to receive instant OS-level alerts when new incident reports are submitted — even when your browser is in the background.</div>
    </div>
  </div>
  <div style="display:flex; gap:8px;">
    <button onclick="enablePushNotifications()" style="
      flex:1; background: linear-gradient(135deg, #007ED2, #0056a3);
      color:#fff; border:none; border-radius:12px; padding:11px;
      font-family:'DM Sans',sans-serif; font-weight:700; font-size:13px;
      cursor:pointer; box-shadow:0 4px 15px rgba(0,126,210,0.3);
    "><i class="bi bi-bell-fill"></i> Enable Alerts</button>
    <button onclick="document.getElementById('push-permission-banner').style.display='none'; localStorage.setItem('pushDismissed','1');" style="
      background:rgba(255,255,255,0.07); color:#94a3b8; border:1px solid rgba(255,255,255,0.1);
      border-radius:12px; padding:11px 16px;
      font-family:'DM Sans',sans-serif; font-weight:600; font-size:13px; cursor:pointer;
    ">Later</button>
  </div>
</div>

<style>
@keyframes slideUp {
  from { opacity:0; transform:translateX(-50%) translateY(30px); }
  to   { opacity:1; transform:translateX(-50%) translateY(0); }
}
@keyframes slideDown {
  from { opacity:1; transform:translateX(-50%) translateY(0); }
  to   { opacity:0; transform:translateX(-50%) translateY(30px); }
}
</style>

<script>
const VAPID_PUBLIC_KEY = '<?= VAPID_PUBLIC_KEY ?>';

/**
 * Convert a URL-safe base64 string to a Uint8Array.
 */
function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4);
  const base64  = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const raw     = atob(base64);
  return Uint8Array.from([...raw].map(c => c.charCodeAt(0)));
}

/**
 * Initialise the push notification system.
 * Registers the service worker and shows the permission banner if needed.
 */
async function initWebPush() {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
    console.log('[WebPush] Not supported in this browser.');
    return;
  }

  // Show the permission banner if the user hasn't dismissed it and hasn't granted yet
  if (Notification.permission === 'default' && !localStorage.getItem('pushDismissed')) {
    setTimeout(() => {
      document.getElementById('push-permission-banner').style.display = 'block';
    }, 2000); // Small delay so it doesn't feel instant / intrusive
  }

  // If already granted, auto-subscribe silently
  if (Notification.permission === 'granted') {
    await subscribeEnforcer();
  }
}

/**
 * Called when the enforcer clicks "Enable Alerts".
 */
async function enablePushNotifications() {
  document.getElementById('push-permission-banner').style.display = 'none';

  const permission = await Notification.requestPermission();
  if (permission === 'granted') {
    await subscribeEnforcer();
    showPushToast('Notifications enabled! You\'ll now receive instant report alerts.', 'success');
  } else {
    showPushToast('Notifications blocked. You can enable them in browser settings.', 'warn');
    localStorage.setItem('pushDismissed', '1');
  }
}

/**
 * Registers the service worker and subscribes to push notifications.
 */
async function subscribeEnforcer() {
  // Dynamically build base path from the current URL so it works on
  // any domain (localhost, Cloudflare tunnel, production, etc.)
  const basePath = window.location.pathname.substring(
    0, window.location.pathname.lastIndexOf('/') + 1
  );
  // If we're in /vrakeit/enforcer_landing.php, basePath = /vrakeit/
  // If we're at root /enforcer_landing.php, basePath = /
  const swPath  = basePath + 'sw.js';
  const apiPath = basePath + 'api/save_push_subscription.php';

  console.log('[WebPush] SW path:', swPath);
  console.log('[WebPush] API path:', apiPath);
  console.log('[WebPush] Protocol:', window.location.protocol);

  try {
    console.log('[WebPush] Registering service worker...');
    const reg = await navigator.serviceWorker.register(swPath, { scope: basePath });
    console.log('[WebPush] SW registered, scope:', reg.scope);

    // Wait for the SW to be ready
    const readyReg = await navigator.serviceWorker.ready;
    console.log('[WebPush] SW ready:', readyReg.scope);

    // Check if already subscribed
    let subscription = await readyReg.pushManager.getSubscription();
    console.log('[WebPush] Existing subscription:', subscription ? 'YES' : 'NONE');

    if (!subscription) {
      console.log('[WebPush] Subscribing with VAPID key...');
      subscription = await readyReg.pushManager.subscribe({
        userVisibleOnly:      true,
        applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
      });
      console.log('[WebPush] Subscribed! Endpoint:', subscription.endpoint.substring(0, 50) + '...');
    }

    // Save subscription to server
    console.log('[WebPush] Saving to server at:', apiPath);
    const res  = await fetch(apiPath, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(subscription.toJSON()),
    });
    const json = await res.json();
    console.log('[WebPush] Server response:', json);

    if (json.success) {
      console.log('[WebPush] ✅ Subscription saved! Ready to receive push notifications.');
    } else {
      console.error('[WebPush] ❌ Server failed to save subscription:', json.message);
    }
  } catch (err) {
    console.error('[WebPush] ❌ Subscription failed:', err.name, err.message, err);
    // Show a visible error toast for debugging on mobile
    showPushToast('Push setup failed: ' + err.message, 'warn');
  }
}

/**
 * Show a small toast notification on-screen.
 */
function showPushToast(message, type = 'success') {
  const toast = document.createElement('div');
  const bg    = type === 'success' ? '#10b981' : '#f59e0b';
  const icon  = type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill';
  toast.style.cssText = `
    position:fixed; bottom:30px; left:50%; transform:translateX(-50%);
    background:${bg}; color:#fff; padding:12px 24px; border-radius:50px;
    font-family:'Poppins', sans-serif; font-weight:600; font-size:14px;
    display:flex; align-items:center; gap:8px; white-space:nowrap;
    z-index:10000; box-shadow:0 8px 30px rgba(0,0,0,0.25);
    animation: slideUp 0.3s ease forwards;
  `;
  toast.innerHTML = `<i class="bi bi-${icon}" style="font-size:1.15rem;"></i> <span>${message}</span>`;
  document.body.appendChild(toast);
  setTimeout(() => {
    toast.style.animation = 'slideDown 0.3s ease forwards';
    setTimeout(() => toast.remove(), 300);
  }, 4000);
}

/**
 * Triggered by the bell icon in the header.
 */
function manualEnablePush() {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
    // If they are on their cellphone accessing it via local IP instead of HTTPS
    if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost') {
      alert("⚠️ Push notifications require a secure connection (HTTPS).\\n\\nYou are currently accessing this via an unsecured local IP (" + window.location.protocol + "//" + window.location.hostname + "). Please use the secure Cloudflare tunnel link (https://...) to enable alerts.");
    } else {
      alert("⚠️ Your browser does not support Web Push notifications. Please use a modern browser like Chrome or Safari.");
    }
    return;
  }
  
  if (Notification.permission === 'granted') {
    showPushToast('Notifications are already enabled!', 'success');
  } else {
    // Show the banner if it was dismissed
    document.getElementById('push-permission-banner').style.display = 'block';
  }
}
</script>

</body>
</html>