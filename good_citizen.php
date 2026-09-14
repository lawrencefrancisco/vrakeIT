<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
startSecureSession();
requireLogin();
$user = getLoggedInUser();
if (($user['role'] ?? 'user') !== 'user') { header('Location: enforcer_landing.php'); exit; }

// Prevent unverified users from accessing Good Citizen page
if (empty($user['account_verified'])) {
    echo "<script>alert('You must be verified to access Good Citizen rewards.'); window.location.href='verify.php';</script>";
    exit;
}
$db = getDB();

// Pull BOTH legacy rewards AND active merchant_rewards
$rewards = $db->query("
    SELECT r.id, r.business_name, r.reward_name, r.description, r.points_required, r.category,
           NULL as expires_at, NULL as merchant_id, NULL as logo,
           -1 as quantity, 0 as redeemed_count
    FROM rewards r WHERE r.is_active = 1
    UNION ALL
    SELECT mr.id+10000 as id, m.business_name, mr.reward_name, mr.description, mr.points_required,
           COALESCE(mr.category,'General') as category, mr.expires_at, mr.merchant_id, m.logo,
           COALESCE(mr.quantity,-1) as quantity, COALESCE(mr.redeemed_count,0) as redeemed_count
    FROM merchant_rewards mr
    JOIN merchants m ON mr.merchant_id = m.id
    WHERE mr.is_active = 1 AND m.status = 'approved'
      AND (mr.expires_at IS NULL OR mr.expires_at > NOW())
      AND (mr.quantity IS NULL OR mr.quantity > mr.redeemed_count)
    ORDER BY points_required ASC
")->fetchAll();

$txns = $db->prepare("SELECT * FROM good_citizen_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
$txns->execute([$user['id']]);
$transactions = $txns->fetchAll();

// User's active vouchers
$vouchers = $db->prepare("SELECT v.*, mr.reward_name, m.business_name FROM vouchers v
    JOIN merchant_rewards mr ON v.reward_id = mr.id
    JOIN merchants m ON v.merchant_id = m.id
    WHERE v.user_id = ? AND v.status = 'active' ORDER BY v.created_at DESC LIMIT 10");
$vouchers->execute([$user['id']]);
$myVouchers = $vouchers->fetchAll();

$categories = array_unique(array_column($rewards, 'category'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>VrakeIT – Good Citizen</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
<style>

  
  :root {
  --hero-bg-start: #0f172a;
  --hero-bg-mid: #1e293b;
  --hero-bg-end: #334155;
  --accent-glow: rgba(0, 126, 210, 0.15);
  --glass-bg: rgba(255, 255, 255, 0.1);
  --glass-border: rgba(255, 255, 255, 0.2);
}

body::before { display: none !important; }
body { 
  background: #f8fafc !important; 
  font-family: 'Poppins', sans-serif;
}

/* Premium App Header */
.app-header {
  background: rgba(255,255,255,0.9) !important;
  backdrop-filter: blur(10px);
  border-bottom: 1px solid rgba(0,0,0,0.05);
  color: #0f172a !important;
}
.app-header a, .app-header i { color: #0f172a !important; }
.header-logo { color: #0f172a !important; font-weight: 700; }


/* Hero */
.gc-hero {
  background: linear-gradient(135deg, var(--hero-bg-start), var(--hero-bg-mid), var(--hero-bg-end));
  background-size: 200% 200%;
  color: #ffffff;
  padding: 48px 20px; 
  text-align: center;
  margin-bottom: 0; 
  position: relative; 
  overflow: hidden;
  animation: bgShift 12s ease infinite;
}

/* Background Ambient Orbs */
.gc-hero::before,
.gc-hero::after {
  content: ''; 
  position: absolute; 
  border-radius: 50%;
  background: radial-gradient(circle, var(--accent-glow), transparent 70%);
  pointer-events: none; 
  will-change: transform;
}

.gc-hero::before {
  top: -80px; 
  right: -80px;
  width: 250px; 
  height: 250px;
  animation: driftSlow 8s ease-in-out infinite alternate;
}

.gc-hero::after {
  bottom: -60px; 
  left: -60px;
  width: 200px; 
  height: 200px;
  animation: driftSlow 10s ease-in-out infinite alternate-reverse;
}

/* Premium Glassmorphism Circle */
.gc-hero .pts-circle {
  width: 120px; 
  height: 120px;
  margin: 16px auto;
  border: 1px solid var(--glass-border);
  border-radius: 50%;
  display: flex; 
  flex-direction: column;
  align-items: center; 
  justify-content: center;
  background: var(--glass-bg);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  box-shadow: 
    0 8px 32px rgba(0, 0, 0, 0.25), 
    inset 0 1px 1px rgba(255, 255, 255, 0.2),
    0 0 20px rgba(255, 255, 255, 0.03);
  animation: float 6s ease-in-out infinite;
  transition: transform 0.3s ease, box-shadow 0.3s ease;
  cursor: pointer; /* Optional: if it triggers a modal/details */
}

.gc-hero .pts-circle:hover {
  transform: translateY(-4px) scale(1.02);
  box-shadow: 
    0 12px 40px rgba(0, 0, 0, 0.35), 
    inset 0 1px 1px rgba(255, 255, 255, 0.3),
    0 0 30px rgba(255, 255, 255, 0.08);
}

/* Text Styling */
.gc-hero .pts-circle .pts-num { 
  font-size: 34px; 
  font-weight: 800; 
  line-height: 1; 
  background: linear-gradient(180deg, #ffffff 0%, #ffc107 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  margin-bottom: 4px;
}

.gc-hero .pts-circle .pts-lbl { 
  font-size: 11px; 
  opacity: 0.85; 
  letter-spacing: 1.5px; 
  text-transform: uppercase; 
  font-weight: 600;
}

/* Keyframes */
@keyframes bgShift {
  0% { background-position: 0% 50%; }
  50% { background-position: 100% 50%; }
  100% { background-position: 0% 50%; }
}

@keyframes float {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-8px); }
}

@keyframes driftSlow {
  0% { transform: translate(0, 0); }
  100% { transform: translate(15px, 20px); }
}

/* Segmented Control Tabs */
.gc-tabs {
  display: flex; gap: 8px; padding: 16px;
  background: transparent;
  position: sticky; top: 56px; z-index: 50;
  border-bottom: none;
  box-shadow: none;
}
.tab-btn {
  flex: 1; border: none; 
  background: #e2e8f0; padding: 12px 4px;
  font-size: 13px; font-weight: 600; cursor: pointer;
  transition: all .3s cubic-bezier(0.4, 0, 0.2, 1);
  color: #64748b; border-radius: 12px;
  display: flex; align-items: center; justify-content: center; gap: 6px;
}
.tab-btn.active { 
  background: #ffffff; color: #0f172a; 
  box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}
.tab-btn:hover:not(.active) { background: #cbd5e1; color: #334155; }


/* Category chips */
.cat-chips { display: flex; gap: 10px; overflow-x: auto; scrollbar-width: none; padding: 0 16px 16px; }
.vehicle-chip {
  flex-shrink: 0; border: 1px solid rgba(0,0,0,0.08); background: #ffffff;
  border-radius: 20px; padding: 8px 18px; font-size: 12px;
  font-weight: 600; cursor: pointer; transition: all .3s ease;
  color: #64748b; white-space: nowrap;
  box-shadow: 0 2px 5px rgba(0,0,0,0.02);
}
.vehicle-chip.active { 
  background: #0f172a; border-color: #0f172a; color: #ffffff; 
  box-shadow: 0 4px 12px rgba(15,23,42,0.2); 
}


/* Premium Reward Card */
.reward-card {
  background: #ffffff; border: 1px solid rgba(0,0,0,0.05);
  border-radius: 20px; padding: 0;
  overflow: hidden; transition: all .3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 4px 20px rgba(0,0,0,0.03);
}
.reward-card:hover { 
  border-color: rgba(0, 126, 210, 0.2); 
  box-shadow: 0 12px 30px rgba(0,126,210,0.08); 
  transform: translateY(-4px); 
}

.rc-header {
  display: flex; align-items: center; gap: 16px;
  padding: 16px 20px 12px;
  border-bottom: none;
}
.rc-logo {
  width: 60px; height: 60px; min-width: 60px;
  border-radius: 16px; object-fit: cover;
  border: 1px solid rgba(0,0,0,0.05); background: #f8fafc;
  flex-shrink: 0;
  box-shadow: 0 4px 10px rgba(0,0,0,0.02);
}
.rc-icon-fallback {
  width: 60px; height: 60px; min-width: 60px;
  border-radius: 16px; background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
  display: flex; align-items: center; justify-content: center; font-size: 28px;
  border: 1px solid rgba(0,0,0,0.05);
  box-shadow: 0 4px 10px rgba(0,0,0,0.02);
}
.rc-brand { font-size: 12px; color: #64748b; font-weight: 600; margin-bottom: 2px; text-transform: uppercase; letter-spacing: 0.5px; }
.rc-name { font-size: 16px; font-weight: 800; color: #0f172a; line-height: 1.2; }

.rc-body { padding: 0 20px 20px; }
.rc-desc { font-size: 13px; color: #475569; line-height: 1.5; margin-bottom: 16px; }
.rc-footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding-top: 12px; border-top: 1px dashed rgba(0,0,0,0.06); }

.reward-pts {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(0, 126, 210, 0.08);
  color: #007ED2; border-radius: 999px;
  padding: 6px 14px; font-size: 13px; font-weight: 800;
  border: 1px solid rgba(0, 126, 210, 0.15);
}
.rc-need { font-size: 12px; color: #94a3b8; font-weight: 600; }

.rc-btn {
  background: linear-gradient(135deg, #0f172a, #1e293b);
  color: #ffffff; border: none; border-radius: 12px;
  padding: 10px 20px; font-family: 'Poppins', sans-serif;
  font-size: 13px; font-weight: 700; cursor: pointer;
  transition: all .3s ease; white-space: nowrap;
  box-shadow: 0 4px 15px rgba(15,23,42,0.15);
}
.rc-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(15,23,42,0.25); }
.rc-btn:active { transform: translateY(0); }


/* Voucher card */
.voucher-card {
  background: linear-gradient(135deg, #0f172a, #1e293b);
  border-radius: 20px; padding: 24px; margin-bottom: 16px;
  color: #ffffff; position: relative; overflow: hidden;
  box-shadow: 0 8px 30px rgba(15,23,42,0.2);
}
.voucher-card::before {
  content:''; position: absolute; right: -40px; top: -40px;
  width: 150px; height: 150px;
  background: rgba(255,255,255,0.05); border-radius: 50%;
}
.voucher-card::after {
  content:''; position: absolute; left: -20px; bottom: -20px;
  width: 100px; height: 100px;
  background: rgba(255,255,255,0.03); border-radius: 50%;
}
.voucher-code {
  font-size: 24px; font-weight: 800; letter-spacing: 4px;
  font-family: 'Courier New', monospace; background: rgba(255,255,255,0.1);
  border: 1px solid rgba(255,255,255,0.2);
  border-radius: 12px; padding: 12px 20px;
  display: inline-block; margin: 16px 0; position: relative; z-index: 1;
}
.voucher-exp { font-size: 12px; opacity: 0.8; position: relative; z-index: 1; font-weight: 500; }

/* History rows */
.txn-row {
  display:flex; align-items:center; gap:12px;
  padding:12px 0; border-bottom:1px solid #f0f4f8;
}
.txn-row:last-child { border-bottom:none; }
.txn-icon {
  width:36px; height:36px; min-width:36px;
  border-radius:10px; display:flex; align-items:center; justify-content:center;
  font-size:16px;
}
.txn-icon.earned { background:#e8fef2; color:#00a854; }
.txn-icon.redeemed { background:#fff0f0; color:#E90101; }

/* Modal base */
.gc-modal-backdrop {
  display:none; position:fixed; inset:0;
  background:rgba(15,25,50,0.65);
  backdrop-filter:blur(4px);
  z-index:999; align-items:flex-end; justify-content:center;
  padding:0;
}
.gc-modal-backdrop.open { display:flex; }
.gc-modal-sheet {
  background:#fff; border-radius:28px 28px 0 0;
  padding:28px 24px 36px; width:100%; max-width:480px;
  text-align:center; animation:slideUp .3s cubic-bezier(0.22,1,0.36,1);
}
@keyframes slideUp { from{transform:translateY(100%);opacity:0;} to{transform:translateY(0);opacity:1;} }
.gc-modal-handle {
  width:40px; height:4px; background:#e0e5ee;
  border-radius:4px; margin:0 auto 20px;
}
.modal-icon-ring {
  width:72px; height:72px; border-radius:50%;
  display:flex; align-items:center; justify-content:center;
  margin:0 auto 16px; font-size:34px;
}
.gc-modal-btn {
  border:none; border-radius:14px; padding:13px;
  font-family:Poppins,sans-serif; font-weight:700;
  font-size:14px; cursor:pointer; flex:1; transition:all .2s;
}
.gc-modal-btn.secondary { background:#f0f4f8; color:#444; }
.gc-modal-btn.primary { background:linear-gradient(135deg,#007ED2,#005fa3); color:#fff; box-shadow:0 4px 14px rgba(0,126,210,0.3); }
.gc-modal-btn.primary:hover { opacity:.9; transform:translateY(-1px); }

/* Success modal */
.success-badge {
  width:72px; height:72px;
  background:linear-gradient(135deg,#00c853,#009624);
  border-radius:50%; display:flex; align-items:center;
  justify-content:center; margin:0 auto 16px;
  font-size:34px; box-shadow:0 8px 24px rgba(0,200,83,0.3);
}

@keyframes fadeIn { from{opacity:0;transform:translateY(8px);} to{opacity:1;transform:translateY(0);} }
</style>
</head>
<body>
<header class="app-header">
  <a href="landing.php" class="back-btn" style="font-size:22px;"><i class="bi bi-arrow-left"></i></a>
  <span class="header-logo">Good Citizen</span>
  <span style="width:32px;"></span>
</header>

<!-- Points Hero -->
<div class="gc-hero">
  <div style="font-size:13px;opacity:0.85;margin-bottom:4px;">Your Points Balance</div>
  <div class="pts-circle">
    <span class="pts-num" id="livePoints"><?= number_format($user['points']) ?></span>
    <span class="pts-lbl">POINTS</span>
  </div>
  <!-- <div style="font-size:14px;font-weight:600;opacity:0.9;">Earn more by filing reports!</div> -->
  <!-- <a href="report.php" style="display:inline-block;background:#E90101;border:2px solid rgba(255,255,255,0.5);color:#fff;border-radius:20px;padding:8px 20px;font-size:13px;font-weight:600;text-decoration:none;margin-top:12px;">
    <i class="bi bi-plus-circle me-1"></i> File a Report
  </a> -->
</div>

<!-- Tabs -->
<div class="gc-tabs">
  <button class="tab-btn active" onclick="switchTab('rewards',this)" id="tabRewards"><i class="bi bi-gift-fill"></i> Rewards</button>
  <button class="tab-btn" onclick="switchTab('vouchers',this)" id="tabVouchers"><i class="bi bi-ticket-perforated-fill"></i> Vouchers<?php if(count($myVouchers)): ?> <span style="background:#E90101;color:#fff;border-radius:99px;padding:1px 7px;font-size:10px;"><?= count($myVouchers) ?></span><?php endif; ?></button>
  <button class="tab-btn" onclick="switchTab('history',this)" id="tabHistory"><i class="bi bi-clock-history"></i> History</button>
</div>

<!-- REWARDS TAB -->
<div id="tabContentRewards" style="padding:16px;">
  <div class="cat-chips">
    <button class="vehicle-chip active" onclick="filterRewards('all',this)">All</button>
    <?php foreach($categories as $cat): ?>
    <button class="vehicle-chip" onclick="filterRewards('<?= htmlspecialchars($cat) ?>',this)"><?= htmlspecialchars($cat) ?></button>
    <?php endforeach; ?>
  </div>

  <?php if(empty($rewards)): ?>
  <div style="text-align:center;padding:40px;color:var(--muted);">
    <i class="bi bi-gift" style="font-size:48px;display:block;margin-bottom:8px;color:#ddd;"></i>
    No rewards available yet.
  </div>
  <?php else: ?>
  <div class="d-flex flex-column gap-3" id="rewardsGrid">
    <?php foreach($rewards as $r):
      $icons = ['Food & Beverage'=>'🍔','Fuel & Transport'=>'⛽','Health & Wellness'=>'💊','Shopping'=>'🛍️','General'=>'🎁'];
      $icon = $icons[$r['category']] ?? '🎁';
      $isMerchant = ($r['merchant_id'] !== null);
      $redeemId   = $isMerchant ? ($r['id'] - 10000) : null;
      $logoUrl = null;
      if ($isMerchant && !empty($r['logo'])) {
          $logoUrl = 'assets/uploads/merchants/' . htmlspecialchars($r['logo']);
      }
      $canRedeem = $user['points'] >= $r['points_required'];
    ?>
    <div class="reward-item" data-category="<?= htmlspecialchars($r['category']) ?>">
      <div class="reward-card">
        <div class="rc-header">
          <?php if ($logoUrl): ?>
            <img src="<?= $logoUrl ?>" alt="Logo" class="rc-logo"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
            <div class="rc-icon-fallback" style="display:none;"><?= $icon ?></div>
          <?php else: ?>
            <div class="rc-icon-fallback"><?= $icon ?></div>
          <?php endif; ?>
          <div style="min-width:0;">
            <div class="rc-brand"><?= sanitize($r['business_name']) ?></div>
            <div class="rc-name"><?= sanitize($r['reward_name']) ?></div>
          </div>
        </div>
        <div class="rc-body">
          <?php if (!empty($r['description'])): ?>
          <div class="rc-desc"><?= sanitize($r['description']) ?></div>
          <?php endif; ?>
          <div class="rc-footer">
            <div class="reward-pts"><i class="bi bi-star-fill" style="color:#FFD700;"></i> <?= number_format($r['points_required']) ?> pts</div>
            <?php if($canRedeem): ?>
              <?php if($isMerchant && $redeemId): ?>
              <?php
                $stock = ($r['quantity'] == -1) ? 'null' : (int)($r['quantity'] - $r['redeemed_count']);
              ?>
              <button class="rc-btn" onclick="confirmRedeem(<?= $redeemId ?>, '<?= addslashes($r['reward_name']) ?>', '<?= addslashes($r['business_name']) ?>', <?= $r['points_required'] ?>, <?= $stock ?>)">Redeem &amp; Voucher</button>
              <?php else: ?>
              <button class="rc-btn" onclick="showRedeemInfo('<?= addslashes($r['reward_name']) ?>','<?= addslashes($r['business_name']) ?>',<?= $r['points_required'] ?>)">Redeem</button>
              <?php endif; ?>
            <?php else: ?>
              <span class="rc-need">Need <?= number_format($r['points_required'] - $user['points']) ?> more</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- VOUCHERS TAB -->
<div id="tabContentVouchers" style="padding:16px;display:none;">
  <?php if(empty($myVouchers)): ?>
  <div style="text-align:center;padding:40px;color:var(--muted);">
    <i class="bi bi-ticket-perforated" style="font-size:48px;display:block;margin-bottom:8px;color:#ddd;"></i>
    <p>No active vouchers. Redeem a merchant reward to get one!</p>
  </div>
  <?php else: ?>
  <?php foreach($myVouchers as $v): ?>
  <div class="voucher-card">
    <div style="font-size:12px;opacity:0.8;"><?= sanitize($v['business_name']) ?></div>
    <div style="font-size:15px;font-weight:700;margin:4px 0;"><?= sanitize($v['reward_name']) ?></div>
    <div class="voucher-code"><?= htmlspecialchars($v['voucher_code']) ?></div>
    <div class="voucher-exp"><i class="bi bi-clock me-1"></i>Expires: <?= date('M d, Y', strtotime($v['expires_at'])) ?></div>
    <button onclick="copyCode('<?= $v['voucher_code'] ?>')" style="margin-top:10px;background:rgba(255,255,255,0.2);border:1px solid rgba(255,255,255,0.4);color:#fff;border-radius:8px;padding:6px 14px;font-size:12px;font-family:Poppins,sans-serif;cursor:pointer;">
      <i class="bi bi-clipboard me-1"></i>Copy Code
    </button>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- HISTORY TAB -->
<div id="tabContentHistory" style="padding:16px;display:none;">
  <div style="background:#fff;border-radius:18px;padding:4px 16px;box-shadow:0 2px 8px rgba(0,0,0,0.04);">
    <?php if(empty($transactions)): ?>
    <div style="text-align:center;padding:36px 0;color:#aab4c0;">
      <i class="bi bi-clock-history" style="font-size:40px;display:block;margin-bottom:8px;"></i>
      <p style="font-size:13px;margin:0;">No transactions yet.</p>
    </div>
    <?php else: ?>
    <?php foreach($transactions as $t):
      $isEarned = $t['type']==='earned';
    ?>
    <div class="txn-row">
      <div class="txn-icon <?= $isEarned?'earned':'redeemed' ?>"><?= $isEarned?'<i class="bi bi-star-fill"></i>':'<i class="bi bi-ticket-perforated-fill"></i>' ?></div>
      <div style="flex:1;min-width:0;">
        <div style="font-size:13px;font-weight:600;color:#1a2b45;"><?= sanitize($t['description']) ?></div>
        <div style="font-size:11px;color:#aab4c0;margin-top:2px;"><?= date('M d, Y', strtotime($t['created_at'])) ?></div>
      </div>
      <div style="font-size:15px;font-weight:800;color:<?= $isEarned?'#00a854':'#E90101' ?>; white-space:nowrap;"><?= $isEarned?'+':'-' ?><?= number_format($t['points']) ?> pts</div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Redeem Info Modal -->
<div id="redeemModal" class="gc-modal-backdrop">
  <div class="gc-modal-sheet">
    <div class="gc-modal-handle"></div>
    <div class="modal-icon-ring" style="background:#e8f4ff; color:#007ED2;"><i class="bi bi-gift-fill"></i></div>
    <h4 style="font-weight:800;margin-bottom:4px;font-size:17px;" id="rm-name"></h4>
    <div style="color:#8a9bb0;font-size:13px;margin-bottom:16px;" id="rm-business"></div>
    <div style="background:#f0f7ff;border-radius:14px;padding:14px;margin-bottom:16px;text-align:left;">
      <div style="font-size:13px;color:#445;line-height:1.55;"><i class="bi bi-info-circle-fill me-1" style="color:#007ED2;"></i>Visit the partner business and show your VrakeIT account to redeem this reward.</div>
    </div>
    <div style="font-size:14px;font-weight:700;color:#005fa3;margin-bottom:20px;"><i class="bi bi-star-fill" style="color:#FFD700;"></i> <span id="rm-pts"></span> points</div>
    <div style="display:flex;gap:10px;">
      <button onclick="closeRedeem()" class="gc-modal-btn secondary">Close</button>
      <button onclick="closeRedeem()" class="gc-modal-btn primary">Got it!</button>
    </div>
  </div>
</div>

<!-- Merchant Redeem Confirm Modal -->
<div id="confirmRedeemModal" class="gc-modal-backdrop">
  <div class="gc-modal-sheet">
    <div class="gc-modal-handle"></div>
    <div class="modal-icon-ring" style="background:#fff8e1; color:#f59e0b;"><i class="bi bi-ticket-perforated-fill"></i></div>
    <h4 style="font-weight:800;margin-bottom:4px;font-size:17px;" id="cr-name"></h4>
    <div style="color:#8a9bb0;font-size:13px;margin-bottom:10px;" id="cr-business"></div>
    <div id="cr-stock-wrap" style="margin-bottom:12px;display:none;">
      <span id="cr-stock-badge" style="display:inline-flex;align-items:center;gap:5px;border-radius:999px;padding:5px 14px;font-size:12px;font-weight:700;">
        <i class="bi bi-boxes"></i> <span id="cr-stock-num"></span> left in stock
      </span>
    </div>
    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:14px;padding:14px;margin-bottom:20px;font-size:13px;color:#78350f;text-align:left;">
      <i class="bi bi-exclamation-triangle-fill me-1" style="color:#f59e0b;"></i>This will deduct <strong><span id="cr-pts"></span> points</strong> from your balance and generate a voucher code.
    </div>
    <div style="display:flex;gap:10px;">
      <button onclick="closeConfirm()" class="gc-modal-btn secondary">Cancel</button>
      <button id="confirmRedeemBtn" onclick="executeRedeem()" class="gc-modal-btn primary">Confirm &amp; Redeem</button>
    </div>
  </div>
</div>

<!-- Voucher Success Modal -->
<div id="voucherSuccessModal" style="display:none;position:fixed;inset:0;background:rgba(15,25,50,0.7);z-index:1200;align-items:flex-end;justify-content:center;backdrop-filter:blur(4px);">
  <div style="background:#fff;border-radius:28px 28px 0 0;padding:32px 24px 40px;width:100%;max-width:480px;text-align:center;animation:slideUp .3s cubic-bezier(0.22,1,0.36,1);">
    <div style="width:40px;height:4px;background:#e0e5ee;border-radius:4px;margin:0 auto 24px;"></div>
    <div class="success-badge"><i class="bi bi-check-lg" style="color:#fff;"></i></div>
    <h3 style="font-weight:800;margin-bottom:4px;color:#1a2b45;">Voucher Generated!</h3>
    <div style="color:#8a9bb0;font-size:13px;margin-bottom:20px;" id="vs-business"></div>
    <div style="background:linear-gradient(135deg,#005fa3,#007ED2,#00a0e9);border-radius:18px;padding:22px;margin-bottom:16px;color:#fff;">
      <div style="font-size:11px;opacity:0.8;margin-bottom:8px;text-transform:uppercase;letter-spacing:1px;">Your Voucher Code</div>
      <div id="vs-code" style="font-size:24px;font-weight:800;letter-spacing:4px;font-family:monospace;"></div>
      <div id="vs-exp" style="font-size:11px;opacity:0.75;margin-top:8px;"></div>
    </div>
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:14px;padding:12px;margin-bottom:20px;font-size:12px;color:#166534;text-align:left;">
      <i class="bi bi-info-circle-fill me-1"></i>Show this code at the partner business to claim your reward. Screenshot it now!
    </div>
    <div style="display:flex;gap:10px;">
      <button onclick="copyCode(document.getElementById('vs-code').textContent)" class="gc-modal-btn secondary"><i class="bi bi-clipboard me-1"></i>Copy Code</button>
      <button onclick="document.getElementById('voucherSuccessModal').style.display='none';location.reload();" class="gc-modal-btn primary">Done</button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
let pendingRedeemId = null;

function switchTab(tab, btn) {
  ['Rewards','Vouchers','History'].forEach(t => {
    document.getElementById('tabContent'+t).style.display = 'none';
    document.getElementById('tab'+t).classList.remove('active');
  });
  document.getElementById('tabContent'+tab.charAt(0).toUpperCase()+tab.slice(1)).style.display = '';
  btn.classList.add('active');
}

function filterRewards(cat, btn) {
  document.querySelectorAll('.vehicle-chip').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.reward-item').forEach(el => {
    el.style.display = (cat === 'all' || el.dataset.category === cat) ? '' : 'none';
  });
}

function showRedeemInfo(name, business, pts) {
  document.getElementById('rm-name').textContent = name;
  document.getElementById('rm-business').textContent = business;
  document.getElementById('rm-pts').textContent = pts.toLocaleString();
  document.getElementById('redeemModal').classList.add('open');
}
function closeRedeem() { document.getElementById('redeemModal').classList.remove('open'); }

function confirmRedeem(id, name, business, pts, stock) {
  pendingRedeemId = id;
  document.getElementById('cr-name').textContent = name;
  document.getElementById('cr-business').textContent = business;
  document.getElementById('cr-pts').textContent = pts.toLocaleString();
  const stockWrap  = document.getElementById('cr-stock-wrap');
  const stockBadge = document.getElementById('cr-stock-badge');
  const stockNum   = document.getElementById('cr-stock-num');
  if (stock !== null && stock !== undefined && stock >= 0) {
    const low = stock <= 5;
    stockBadge.style.cssText = 'display:inline-flex;align-items:center;gap:5px;border-radius:999px;padding:5px 14px;font-size:12px;font-weight:700;' +
      (low ? 'background:#fff1f2;border:1px solid #fecdd3;color:#be123c;' : 'background:#f0f9ff;border:1px solid #bae6fd;color:#0369a1;');
    stockNum.textContent = stock;
    stockWrap.style.display = 'block';
  } else {
    stockWrap.style.display = 'none';
  }
  document.getElementById('confirmRedeemModal').classList.add('open');
}
function closeConfirm() { document.getElementById('confirmRedeemModal').classList.remove('open'); }

async function executeRedeem() {
  if (!pendingRedeemId) return;
  const btn = document.getElementById('confirmRedeemBtn');
  btn.disabled = true; btn.textContent = 'Processing...';
  const fd = new FormData();
  fd.append('reward_id', pendingRedeemId);
  try {
    const res = await fetch('api/redeem_reward.php', { method:'POST', body:fd });
    const data = await res.json();
    closeConfirm();
    if (data.success) {
      document.getElementById('vs-code').textContent = data.voucher_code;
      document.getElementById('vs-business').textContent = data.reward_name + ' @ ' + data.business_name;
      document.getElementById('vs-exp').textContent = 'Expires: ' + new Date(data.expires_at).toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'});
      document.getElementById('livePoints').textContent = data.remaining_pts.toLocaleString();
      document.getElementById('voucherSuccessModal').style.display = 'flex';
    } else {
      alert('Error: ' + data.message);
    }
  } catch(e) { alert('Connection error. Please try again.'); }
  btn.disabled = false; btn.textContent = 'Confirm & Redeem';
}

function copyCode(code) {
  navigator.clipboard.writeText(code).then(() => {
    const t = document.createElement('div');
    t.textContent = '✓ Copied!';
    Object.assign(t.style,{position:'fixed',bottom:'80px',left:'50%',transform:'translateX(-50%)',background:'#1f2937',color:'#fff',padding:'8px 20px',borderRadius:'20px',fontSize:'13px',fontWeight:'600',zIndex:'9999',animation:'fadeIn .2s'});
    document.body.appendChild(t);
    setTimeout(()=>t.remove(),2000);
  });
}
</script>
</body>
</html>