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
  <header class="app-header">
    <button onclick="openSidebar()" style="background:none;border:none;color:#fff;font-size:22px;padding:0;"><i class="bi bi-list"></i></button>
    <span class="header-logo" style="display:flex;align-items:center;gap:8px;">
      <img src="assets/img/system_logo.png" alt="VrakeIT" style="width:28px;height:28px;object-fit:contain;vertical-align:middle;">
      <span><span style="color:var(--blue);">Vrake</span><span style="color:var(--red);">IT</span></span>
    </span>
    <div class="header-right">
      <div class="points-badge" style="background: rgba(0,126,210,0.1); color: #007ED2;">
        <i class="bi bi-shield-lock-fill"></i>
        <span>Enforcer</span>
      </div>
      <img src="<?= getAvatarUrl($user['avatar']) ?>" class="user-avatar-sm" alt="Profile" onclick="window.location='profile.php'">
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
        <span class="pulse-dot"></span>
        Enforcer Network Active
      </div>
      <h2 class="saas-hero-heading">
        Enforcer Dashboard,
        <span class="name-glow"><?= sanitize($user['first_name']) ?></span>
      </h2>
      <p class="saas-hero-sub">
        Manage reports and ensure public safety on the road.
      </p>
    </div>
  </div>

  <!-- ═══════════════ ROAD SAFETY FLOATING TIPS ═══════════════ -->
<div class="floating-safety-tips">
  <div class="tip-orb"></div>
  <p id="safetyTipText">Stay calm — arriving safely matters more than arriving first.</p>
</div>



<script>
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
    <a href="track.php" class="saas-cta-btn blue-cta" id="btn-track-report">
      <div class="saas-cta-icon-wrap"><i class="bi bi-list-check"></i></div>
      <div class="cta-text-wrap">
        <span class="cta-label">Track Reports</span>
        <span class="cta-sub">View filed reports</span>
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
  </script>
</body>
</html>