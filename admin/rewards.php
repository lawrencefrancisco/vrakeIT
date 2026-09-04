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

$legacyRewards = $db->query("SELECT r.*, NULL as merchant_name, NULL as merchant_id, 'legacy' as source FROM rewards r ORDER BY r.points_required ASC")->fetchAll();
$merchantRewards = $db->query("SELECT mr.*, m.business_name as merchant_name, mr.merchant_id, 'merchant' as source FROM merchant_rewards mr JOIN merchants m ON mr.merchant_id=m.id ORDER BY mr.created_at DESC")->fetchAll();
$all = array_merge($legacyRewards, $merchantRewards);

adminHead('Reward Controls');
?>
<body>
<?php adminNav('rewards', $admin); ?>
<div class="main">
<?php adminTopbar('Reward Controls'); ?>
<div class="page-body">
<div class="section-card">
  <div class="section-header"><span class="section-title-text"><i class="bi bi-gift me-2"></i>All Rewards (<?= count($all) ?>)</span></div>
  <div style="overflow-x:auto;">
  <table class="data-table">
    <thead><tr><th>Reward</th><th>Business</th><th>Source</th><th>Points</th><th>Stock</th><th>Redeemed</th><th>Active</th><th>Expires</th></tr></thead>
    <tbody>
    <?php foreach($all as $r): ?>
    <tr>
      <td><strong><?= htmlspecialchars($r['reward_name']) ?></strong>
        <?php if($r['description']): ?><div style="font-size:11px;color:rgba(255,255,255,0.35);"><?= htmlspecialchars(substr($r['description'],0,50)) ?></div><?php endif; ?>
      </td>
      <td><?= htmlspecialchars($r['source']==='merchant' ? $r['merchant_name'] : $r['business_name']) ?></td>
      <td><span class="badge-status <?= $r['source']==='merchant'?'bs-approved':'bs-reviewing' ?>"><?= $r['source']==='merchant'?'Merchant':'Legacy' ?></span></td>
      <td style="color:#fbbf24;font-weight:700;"><?= number_format($r['points_required']) ?></td>
      <td><?= $r['source']==='merchant' ? ($r['quantity'] ?? '∞') : '∞' ?></td>
      <td><?= $r['source']==='merchant' ? ($r['redeemed_count'] ?? 0) : '—' ?></td>
      <td><?php if($r['is_active']): ?><span class="badge-status bs-approved">Active</span><?php else: ?><span class="badge-status bs-closed">Inactive</span><?php endif; ?></td>
      <td style="font-size:12px;color:rgba(255,255,255,0.4);"><?= ($r['source']==='merchant' && $r['expires_at']) ? date('M d, Y', strtotime($r['expires_at'])) : '—' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
</div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
