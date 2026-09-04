<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once __DIR__ . '/layout.php';
startSecureSession();
requireAdminLogin();
$admin = getAdminUser();
$db = getDB();

$filter = $_GET['filter'] ?? 'all';
$where = "WHERE 1=1";
if ($filter === 'pending')     $where .= " AND status='pending'";
if ($filter === 'approved')    $where .= " AND status='approved'";
if ($filter === 'rejected')    $where .= " AND status='rejected'";

$merchants = $db->query("SELECT * FROM merchants $where ORDER BY created_at DESC")->fetchAll();

adminHead('Merchant Oversight');
?>

<body>
  <?php adminNav('merchants', $admin); ?>
  <div class="main">
    <?php adminTopbar('Merchant Oversight'); ?>
    <div class="page-body">

      <div class="section-card mb-4">
        <div class="section-header">
          <?php foreach (['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $k => $v): ?>
            <a href="?filter=<?= $k ?>" class="btn-admin <?= $filter === $k ? 'btn-primary-admin' : 'btn-review' ?> me-2"><?= $v ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="section-card">
        <div class="section-header"><span class="section-title-text"><i class="bi bi-shop me-2"></i>Merchants (<?= count($merchants) ?>)</span></div>
        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead>
              <tr>
                <th>Business</th>
                <th>Type</th>
                <th>Contact</th>
                <th>Email</th>
                <th>Status</th>
                <th>Applied</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($merchants as $m): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($m['business_name']) ?></strong></td>
                  <td style="font-size:12px;color:rgba(255,255,255,0.5);"><?= htmlspecialchars($m['business_type']) ?></td>
                  <td style="font-size:12px;"><?= htmlspecialchars($m['contact_number']) ?></td>
                  <td style="font-size:12px;color:rgba(255,255,255,0.5);"><?= htmlspecialchars($m['email']) ?></td>
                  <td><span class="badge-status bs-<?= $m['status'] ?>"><?= ucfirst($m['status']) ?></span></td>
                  <td style="font-size:12px;color:rgba(255,255,255,0.4);"><?= date('M d, Y', strtotime($m['created_at'])) ?></td>
                  <td style="display:flex;gap:6px;flex-wrap:wrap;">
                    <?php if ($m['status'] !== 'approved'): ?>
                      <button class="btn-admin btn-approve" onclick="merchantAction(<?= $m['id'] ?>,'approved')"><i class="bi bi-check2"></i> Approve</button>
                    <?php endif; ?>
                    <?php if ($m['status'] !== 'rejected'): ?>
                      <button class="btn-admin btn-reject" onclick="merchantAction(<?= $m['id'] ?>,'rejected')"><i class="bi bi-x"></i> Reject</button>
                    <?php endif; ?>
                    <?php if ($m['status'] === 'approved'): ?>
                      <button class="btn-admin btn-danger-admin" onclick="merchantAction(1, 'deactivated')">
                        <i class="bi bi-pause"></i> Deactivate
                      </button>
                    <?php endif; ?>
                    <?php if (!empty($m['permit'])): ?>
                      <a href="../assets/uploads/merchants/<?= htmlspecialchars($m['permit']) ?>" target="_blank" class="btn-admin btn-review"><i class="bi bi-file-pdf"></i></a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    async function merchantAction(mid, status) {
      const labels = {
        approved: 'approve',
        rejected: 'reject',
        deactivated: 'deactivate'
      };
      if (!confirm('Confirm: ' + labels[status] + ' this merchant?')) return;
      const fd = new FormData();
      fd.append('action', 'merchant_status');
      fd.append('merchant_id', mid);
      fd.append('status', status);
      const res = await fetch('api/admin_action.php', {
        method: 'POST',
        body: fd
      });
      const data = await res.json();
      if (data.success) location.reload();
      else alert(data.message);
    }
  </script>
</body>

</html>