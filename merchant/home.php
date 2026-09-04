<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/merchant_auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
startSecureSession();
requireMerchantLogin();
$merchant = getLoggedInMerchant();
$db = getDB();
$merchant_id = $merchant['id'];

// Analytics
$stats = $db->prepare("SELECT COUNT(v.id) as total_val, COALESCE(SUM(r.points_required),0) as gcp_val FROM vouchers v JOIN merchant_rewards r ON v.reward_id = r.id WHERE v.merchant_id = ? AND v.status = 'redeemed'");
$stats->execute([$merchant_id]);
$analytics = $stats->fetch();

$active_rewards = $db->prepare("SELECT COUNT(*) FROM merchant_rewards WHERE merchant_id = ? AND is_active = 1");
$active_rewards->execute([$merchant_id]);
$active_count = $active_rewards->fetchColumn();

// Recent validations
$recent = $db->prepare("SELECT v.voucher_code, v.redeemed_at, u.first_name, u.last_name, r.reward_name FROM vouchers v JOIN users u ON v.user_id = u.id JOIN merchant_rewards r ON v.reward_id = r.id WHERE v.merchant_id = ? AND v.status = 'redeemed' ORDER BY v.redeemed_at DESC LIMIT 5");
$recent->execute([$merchant_id]);
$recent_validations = $recent->fetchAll();

$msg   = $_GET['msg']   ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VrakeIT — Merchant Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    * { box-sizing: border-box; }
    body { font-family: 'Poppins', sans-serif; background: #0a0f1e; color: #fff; margin: 0; min-height: 100vh; }
    .m-header { background: rgba(255,255,255,0.05); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(255,255,255,0.08); padding: 14px 20px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
    .m-header-brand { display: flex; align-items: center; gap: 10px; }
    .m-logo-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: transparent; flex-shrink: 0; }
    .m-logo-icon img.sys-logo { width: 40px; height: 40px; object-fit: contain; }
    .m-biz-name { font-weight: 700; font-size: 15px; color: #fff; }
    .m-biz-badge { font-size: 10px; color: #4ade80; background: rgba(74,222,128,0.12); border: 1px solid rgba(74,222,128,0.25); border-radius: 50px; padding: 2px 8px; font-weight: 600; }
    .btn-logout { background: rgba(248,113,113,0.12); border: 1px solid rgba(248,113,113,0.3); color: #f87171; border-radius: 8px; padding: 7px 14px; font-size: 12px; font-weight: 600; cursor: pointer; text-decoration: none; }
    .btn-logout:hover { background: rgba(248,113,113,0.2); color: #fca5a5; }
    .page-body { padding: 20px 16px 80px; max-width: 600px; margin: 0 auto; }
    .stat-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 24px; }
    .stat-card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 16px 12px; text-align: center; }
    .stat-val { font-size: 1.8rem; font-weight: 800; color: #fff; line-height: 1; }
    .stat-lbl { font-size: 10px; color: rgba(255,255,255,0.45); font-weight: 600; text-transform: uppercase; margin-top: 4px; }
    .stat-card.green .stat-val { color: #4ade80; }
    .stat-card.gold .stat-val { color: #fde047; }
    .stat-card.blue .stat-val { color: #60b4ff; }
    .section-title { font-size: 12px; font-weight: 700; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 1px; margin: 24px 0 12px; }
    .action-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
    .action-card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 20px 16px; text-align: center; text-decoration: none; color: #fff; transition: all 0.2s; }
    .action-card:hover { background: rgba(255,255,255,0.09); transform: translateY(-2px); color: #fff; }
    .action-card i { font-size: 28px; display: block; margin-bottom: 8px; }
    .action-card span { font-size: 13px; font-weight: 600; display: block; }
    .action-card small { font-size: 11px; color: rgba(255,255,255,0.45); }
    .action-card.green-card i { color: #4ade80; }
    .action-card.blue-card i { color: #60b4ff; }
    .action-card.gold-card i { color: #fde047; }
    .action-card.red-card i { color: #f87171; }
    .validate-card { background: linear-gradient(135deg, rgba(22,163,74,0.18), rgba(21,128,61,0.12)); border: 1px solid rgba(22,163,74,0.3); border-radius: 20px; padding: 24px 20px; margin-bottom: 20px; }
    .validate-card h5 { font-size: 15px; font-weight: 700; color: #4ade80; margin-bottom: 6px; }
    .validate-card p { font-size: 12px; color: rgba(255,255,255,0.5); margin-bottom: 16px; }
    .validate-input-row { display: flex; gap: 8px; }
    .validate-input-row input { flex: 1; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); color: #fff; border-radius: 10px; padding: 12px; font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; font-family: 'Poppins', sans-serif; }
    .validate-input-row input:focus { outline: none; border-color: #4ade80; }
    .validate-input-row input::placeholder { color: rgba(255,255,255,0.3); letter-spacing: 0; font-weight: 400; text-transform: none; }
    .btn-validate { background: linear-gradient(135deg,#16a34a,#15803d); color: #fff; border: none; border-radius: 10px; padding: 12px 20px; font-weight: 700; font-size: 14px; cursor: pointer; white-space: nowrap; transition: all 0.2s; }
    .btn-validate:hover { opacity: 0.9; }
    .validate-result { margin-top: 12px; padding: 12px 16px; border-radius: 10px; font-size: 13px; font-weight: 600; display: none; }
    .validate-result.success { background: rgba(74,222,128,0.15); border: 1px solid rgba(74,222,128,0.4); color: #4ade80; }
    .validate-result.error   { background: rgba(248,113,113,0.15); border: 1px solid rgba(248,113,113,0.4); color: #f87171; }
    .val-table { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; overflow: hidden; }
    .val-table-row { display: flex; justify-content: space-between; align-items: center; padding: 14px 16px; border-bottom: 1px solid rgba(255,255,255,0.06); font-size: 13px; }
    .val-table-row:last-child { border-bottom: none; }
    .val-code { font-weight: 700; color: #4ade80; font-size: 12px; }
    .val-name { color: rgba(255,255,255,0.7); }
    .val-date { font-size: 11px; color: rgba(255,255,255,0.35); }
    .empty-state { text-align: center; padding: 32px 16px; color: rgba(255,255,255,0.3); }
    .empty-state i { font-size: 40px; display: block; margin-bottom: 10px; }
    .empty-state p { font-size: 13px; }
    .alert-flash { border-radius: 12px; font-size: 13px; margin-bottom: 16px; }
  </style>
</head>
<body>
  <div class="m-header">
    <div class="m-header-brand">
      <div class="m-logo-icon"><img src="../assets/img/system_logo.png" alt="VrakeIT" class="sys-logo"></div>
      <div>
        <div class="m-biz-name"><?= sanitize($merchant['business_name']) ?></div>
        <div class="m-biz-badge">Merchant Partner</div>
      </div>
    </div>
    <a href="logout.php" class="btn-logout"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
  </div>

  <div class="page-body">
    <?php if($msg): ?><div class="alert alert-success alert-flash"><i class="bi bi-check-circle me-2"></i><?= sanitize($msg) ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-danger alert-flash"><i class="bi bi-exclamation-circle me-2"></i><?= sanitize($error) ?></div><?php endif; ?>

    <div class="section-title">Your Impact</div>
    <div class="stat-row">
      <div class="stat-card green"><div class="stat-val"><?= number_format($analytics['total_val']) ?></div><div class="stat-lbl">Validations</div></div>
      <div class="stat-card gold"><div class="stat-val"><?= number_format($analytics['gcp_val']) ?></div><div class="stat-lbl">Points Given</div></div>
      <div class="stat-card blue"><div class="stat-val"><?= $active_count ?></div><div class="stat-lbl">Active Rewards</div></div>
    </div>

    <!-- Validate Voucher -->
    <div class="validate-card">
      <h5><i class="bi bi-upc-scan me-2"></i>Validate a Voucher</h5>
      <p>Enter the voucher code presented by the driver to confirm their reward.</p>
      <div class="validate-input-row">
        <input type="text" id="voucherInput" placeholder="VRK-XXXXXX-XXXX" maxlength="15">
        <button class="btn-validate" id="validateBtn">Validate</button>
      </div>
      <div class="validate-result" id="validateResult"></div>
    </div>

    <div class="section-title">Manage</div>
    <div class="action-grid">
      <a href="rewards.php" class="action-card green-card">
        <i class="bi bi-tags-fill"></i>
        <span>Rewards</span>
        <small>Create & manage offers</small>
      </a>
      <a href="ads.php" class="action-card gold-card">
        <i class="bi bi-megaphone-fill"></i>
        <span>Advertisements</span>
        <small>Promote your business</small>
      </a>
    </div>

    <div class="section-title">Recent Validations</div>
    <?php if(count($recent_validations) > 0): ?>
    <div class="val-table">
      <?php foreach($recent_validations as $v): ?>
      <div class="val-table-row">
        <div>
          <div class="val-code"><?= sanitize($v['voucher_code']) ?></div>
          <div class="val-name"><?= sanitize($v['reward_name']) ?></div>
          <div class="val-date"><?= sanitize($v['first_name'] . ' ' . $v['last_name']) ?></div>
        </div>
        <div class="val-date"><?= date('M d, h:i A', strtotime($v['redeemed_at'])) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state"><i class="bi bi-clock-history"></i><p>No validations yet.</p></div>
    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.getElementById('validateBtn').addEventListener('click', async () => {
      const code = document.getElementById('voucherInput').value.trim().toUpperCase();
      const resultBox = document.getElementById('validateResult');
      if (!code) { resultBox.className = 'validate-result error'; resultBox.style.display = 'block'; resultBox.innerHTML = '<i class="bi bi-exclamation-circle me-2"></i>Please enter a voucher code.'; return; }
      document.getElementById('validateBtn').disabled = true;
      document.getElementById('validateBtn').textContent = '...';
      try {
        const fd = new FormData();
        fd.append('voucher_code', code);
        fd.append('merchant_id', '<?= $merchant_id ?>');
        const res = await fetch('api/validate_voucher.php', { method: 'POST', body: fd });
        const data = await res.json();
        resultBox.style.display = 'block';
        if (data.success) {
          resultBox.className = 'validate-result success';
          resultBox.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i>' + data.message;
          document.getElementById('voucherInput').value = '';
          setTimeout(() => location.reload(), 2000);
        } else {
          resultBox.className = 'validate-result error';
          resultBox.innerHTML = '<i class="bi bi-x-circle-fill me-2"></i>' + data.message;
        }
      } catch { resultBox.className = 'validate-result error'; resultBox.style.display='block'; resultBox.innerHTML = '<i class="bi bi-wifi-off me-2"></i>Connection error.'; }
      document.getElementById('validateBtn').disabled = false;
      document.getElementById('validateBtn').textContent = 'Validate';
    });
  </script>
</body>
</html>
