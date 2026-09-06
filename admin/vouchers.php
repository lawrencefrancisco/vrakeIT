<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once __DIR__ . '/layout.php';
startSecureSession();
requireAdminLogin();

if (($_SESSION['role'] ?? '') === 'moderator') {
    header('Location: admin_dashboard.php?error=access_denied');
    exit;
}

$admin = getAdminUser();
$db = getDB();

$vouchers = $db->query("SELECT v.*, u.first_name, u.last_name, m.business_name, mr.reward_name, mr.points_required FROM vouchers v JOIN users u ON v.user_id=u.id JOIN merchants m ON v.merchant_id=m.id JOIN merchant_rewards mr ON v.reward_id=mr.id ORDER BY v.created_at DESC LIMIT 100")->fetchAll();
adminHead('Vouchers');
?>
<body>
<?php adminNav('vouchers', $admin); ?>
<div class="main">
<?php adminTopbar('Issued Vouchers'); ?>
<div class="page-body">
<div class="section-card">
  <div class="section-header"><span class="section-title-text"><i class="bi bi-ticket-perforated me-2"></i>Vouchers (<?= count($vouchers) ?>)</span></div>
  <div style="overflow-x:auto;">
  <table class="data-table">
    <thead><tr><th>Code</th><th>User</th><th>Merchant</th><th>Reward</th><th>Points Cost</th><th>Status</th><th>Expires</th><th>Issued</th></tr></thead>
    <tbody>
    <?php foreach($vouchers as $v): ?>
    <tr>
      <td><code style="color:#60b4ff;background:rgba(96,180,255,0.1);padding:3px 8px;border-radius:6px;font-size:12px;"><?= htmlspecialchars($v['voucher_code']) ?></code></td>
      <td><?= htmlspecialchars($v['first_name'].' '.$v['last_name']) ?></td>
      <td><?= htmlspecialchars($v['business_name']) ?></td>
      <td><?= htmlspecialchars($v['reward_name']) ?></td>
      <td style="color:#fbbf24;font-weight:700;"><?= number_format($v['points_required']) ?></td>
      <td><span class="badge-status bs-<?= $v['status']==='active'?'approved':($v['status']==='redeemed'?'closed':'rejected') ?>"><?= ucfirst($v['status']) ?></span></td>
      <td style="font-size:12px;color:var(--muted);"><?= date('M d, Y', strtotime($v['expires_at'])) ?></td>
      <td style="font-size:12px;color:var(--muted);"><?= date('M d, Y', strtotime($v['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
</div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
