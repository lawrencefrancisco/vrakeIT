<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once __DIR__ . '/layout.php';
startSecureSession();
requireAdminLogin();
$admin = getAdminUser();
$db = getDB();

$verifs = $db->query("SELECT v.*, u.first_name, u.last_name, u.email FROM id_verifications v JOIN users u ON v.user_id=u.id ORDER BY FIELD(v.status,'pending','approved','rejected'), v.created_at DESC LIMIT 100")->fetchAll();

adminHead('ID Verifications');
?>
<body>
<?php adminNav('verifications', $admin); ?>
<div class="main">
<?php adminTopbar('ID Verification Review'); ?>
<div class="page-body">
<div class="section-card">
  <div class="section-header"><span class="section-title-text"><i class="bi bi-shield-check me-2"></i>ID Verification Requests (<?= count($verifs) ?>)</span></div>
  <div style="overflow-x:auto;">
  <table class="data-table">
   <thead><tr><th>User</th><th>ID Type</th><th>Full Name</th><th>Birthdate</th><th>Status</th><th>Submitted</th><th>ID File</th><th>Selfie</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($verifs as $v): ?>
    <tr>
      <td>
        <div style="font-weight:600;"><?= htmlspecialchars($v['first_name'].' '.$v['last_name']) ?></div>
        <div style="font-size:11px;color:rgba(255,255,255,0.35);"><?= htmlspecialchars($v['email']) ?></div>
      </td>
      <td><?= htmlspecialchars($v['id_type']) ?></td>
      <td><?= htmlspecialchars($v['full_name']) ?></td>
      <td style="font-size:12px;"><?= date('M d, Y', strtotime($v['birthdate'])) ?></td>
      <td><span class="badge-status bs-<?= $v['status'] ?>"><?= ucfirst($v['status']) ?></span></td>
      <td style="font-size:12px;color:rgba(255,255,255,0.4);"><?= date('M d, Y', strtotime($v['created_at'])) ?></td>
      <td>
        <!-- FIXED: changed file_path to id_file -->
        <a href="../assets/uploads/<?= htmlspecialchars($v['id_file']) ?>" target="_blank" class="btn-admin btn-review"><i class="bi bi-eye"></i> View ID</a>
      </td>
      <td>
        <!-- FIXED: changed selfie_path to selfie_file -->
        <?php if (!empty($v['selfie_file'])): ?>
            <a href="../assets/uploads/<?= htmlspecialchars($v['selfie_file']) ?>" target="_blank" class="btn-admin btn-review"><i class="bi bi-person-bounding-box"></i> View Selfie</a>
        <?php else: ?>
            <span style="font-size:12px;color:rgba(255,255,255,0.3);">No selfie</span>
        <?php endif; ?>
      </td>
      <td style="display:flex;gap:6px;">
        <?php if($v['status']==='pending'): ?>
        <button class="btn-admin btn-approve" onclick="verifyAction(<?= $v['id'] ?>,'approved')"><i class="bi bi-check2"></i> Approve</button>
        <button class="btn-admin btn-reject" onclick="verifyAction(<?= $v['id'] ?>,'rejected')"><i class="bi bi-x"></i> Reject</button>
        <?php else: ?>
        <span style="font-size:12px;color:rgba(255,255,255,0.3);">—</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
</div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
async function verifyAction(vid, status) {
  if (!confirm('Confirm: ' + status + ' this verification?')) return;
  const fd = new FormData();
  fd.append('action','verify_id'); fd.append('verif_id',vid); fd.append('status',status);
  const res = await fetch('api/admin_action.php',{method:'POST',body:fd});
  const data = await res.json();
  if(data.success) location.reload(); else alert(data.message);
}
</script>
</body></html>
