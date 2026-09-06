<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once __DIR__ . '/layout.php';
startSecureSession();
requireAdminLogin();
$admin = getAdminUser();

// Prevent moderator access
if ($_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$db = getDB();

$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? 'all';

$where = "WHERE u.role IN ('user','enforcer')";
$params = [];
if ($search) { $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)"; $s="%$search%"; $params=[$s,$s,$s]; }
if ($filter === 'verified') $where .= " AND u.account_verified=1";
if ($filter === 'unverified') $where .= " AND u.account_verified=0";
if ($filter === 'enforcer') $where .= " AND u.role='enforcer'";

$stmt = $db->prepare("SELECT u.*, u.is_active, (SELECT COUNT(*) FROM reports WHERE user_id=u.id) as report_count FROM users u $where ORDER BY u.created_at DESC LIMIT 100");
$stmt->execute($params);
$users = $stmt->fetchAll();

adminHead('User Management');
?>
<body>
<?php adminNav('users', $admin); ?>
<div class="main">
<?php adminTopbar('User Management'); ?>
<div class="page-body">

<!-- Filters & Search -->
<div class="section-card mb-4">
  <div class="section-header" style="flex-wrap:wrap;gap:10px;">
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <?php foreach(['all'=>'All','verified'=>'Verified','unverified'=>'Unverified','enforcer'=>'Enforcers'] as $k=>$v): ?>
      <a href="?filter=<?= $k ?><?= $search?"&search=$search":'' ?>" class="btn-admin <?= $filter===$k?'btn-primary-admin':'btn-review' ?>"><?= $v ?></a>
      <?php endforeach; ?>
    </div>
    <form method="GET" style="display:flex;gap:8px;">
      <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
      <input class="search-input" name="search" placeholder="Search name or email…" value="<?= htmlspecialchars($search) ?>">
      <button type="submit" class="btn-admin btn-primary-admin"><i class="bi bi-search"></i></button>
    </form>
  </div>
</div>

<div class="section-card">
  <div class="section-header">
    <span class="section-title-text"><i class="bi bi-people me-2"></i>Users (<?= count($users) ?>)</span>
  </div>
  <div style="overflow-x:auto;">
  <table class="data-table">
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Points</th><th>Reports</th><th>Verified</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($users as $u): ?>
    <tr>
      <td><strong><?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?></strong></td>
      <td style="color:var(--muted);font-size:12px;"><?= htmlspecialchars($u['email']) ?></td>
      <td><span class="badge-status <?= $u['role']==='enforcer'?'bs-reviewing':'bs-approved' ?>"><?= ucfirst($u['role']) ?></span></td>
      <td><span style="color:#fbbf24;font-weight:700;"><?= number_format($u['points']) ?></span></td>
      <td><?= $u['report_count'] ?></td>
      <td>
        <?php if($u['account_verified']): ?>
        <span class="badge-status bs-approved"><i class="bi bi-check-circle me-1"></i>Yes</span>
        <?php else: ?>
        <span class="badge-status bs-pending">No</span>
        <?php endif; ?>
      </td>
      <td>
        <?php if($u['is_active'] ?? 1): ?>
        <span class="badge-status bs-approved" style="white-space:nowrap;"><i class="bi bi-circle-fill me-1" style="font-size:7px;"></i>Active</span>
        <?php else: ?>
        <span class="badge-status" style="background:rgba(248,113,113,0.15);color:#f87171;border:1px solid rgba(248,113,113,0.3);white-space:nowrap;"><i class="bi bi-slash-circle me-1"></i>Inactive</span>
        <?php endif; ?>
      </td>
      <td style="color:var(--muted);font-size:12px;"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
      <td style="display:flex;gap:6px;flex-wrap:wrap;">
        <button class="btn-admin btn-review" onclick="openPointsModal(<?= $u['id'] ?>, '<?= addslashes($u['first_name'].' '.$u['last_name']) ?>', <?= $u['points'] ?>)">
          <i class="bi bi-star"></i> Points
        </button>
        <button class="btn-admin <?= $u['account_verified']?'btn-reject':'btn-approve' ?>" onclick="toggleVerify(<?= $u['id'] ?>, 'account_verified', this)">
          <?= $u['account_verified']?'Unverify':'Verify' ?>
        </button>
        <button class="btn-admin" 
                onclick="confirmDeactivate(<?= $u['id'] ?>, <?= (int)($u['is_active'] ?? 1) ?>, '<?= addslashes($u['first_name'].' '.$u['last_name']) ?>')"
                style="<?= ($u['is_active'] ?? 1) ? 'background:rgba(248,113,113,0.15);color:#f87171;border:1px solid rgba(248,113,113,0.3);' : 'background:rgba(74,222,128,0.15);color:#4ade80;border:1px solid rgba(74,222,128,0.3);' ?>">
          <i class="bi <?= ($u['is_active'] ?? 1) ? 'bi-person-slash' : 'bi-person-check' ?>"></i>
          <?= ($u['is_active'] ?? 1) ? 'Deactivate' : 'Reactivate' ?>
        </button>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

</div>
</div>

<!-- Deactivate / Reactivate Confirmation Modal -->
<div class="modal fade modal-dark" id="deactivateModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-2">
      <div class="modal-header" style="border-bottom:1px solid rgba(255,255,255,0.08);">
        <h6 class="modal-title" id="deactivateModalTitle">Deactivate Account</h6>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="deactivateModalBody" style="padding:20px 16px;">
      </div>
      <div class="modal-footer" style="border-top:1px solid rgba(255,255,255,0.08);gap:8px;">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button id="deactivateConfirmBtn" onclick="doDeactivate()"></button>
      </div>
    </div>
  </div>
</div>

<!-- Points Modal -->
<div class="modal fade modal-dark" id="pointsModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-2">
      <div class="modal-header">
        <h6 class="modal-title" id="pointsModalTitle">Adjust Points</h6>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p style="font-size:13px;color:var(--muted);">Current balance: <strong id="currentPts" style="color:#fbbf24;"></strong> pts</p>
        <input type="hidden" id="adjustUserId">
        <div class="mb-3">
          <label class="form-lbl">Points Adjustment (positive to add, negative to deduct)</label>
          <input type="number" id="adjustPts" class="form-dark" placeholder="e.g. 50 or -20">
        </div>
        <div class="mb-3">
          <label class="form-lbl">Reason / Description</label>
          <input type="text" id="adjustDesc" class="form-dark" placeholder="e.g. Admin bonus" value="Admin point adjustment">
        </div>
        <div id="adjustResult" style="font-size:13px;display:none;" class="mt-2"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button class="btn-admin btn-primary-admin" onclick="submitPointsAdjust()"><i class="bi bi-check2"></i> Apply</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openPointsModal(uid, name, pts) {
  document.getElementById('adjustUserId').value = uid;
  document.getElementById('pointsModalTitle').textContent = 'Adjust Points — ' + name;
  document.getElementById('currentPts').textContent = pts.toLocaleString();
  document.getElementById('adjustPts').value = '';
  document.getElementById('adjustResult').style.display = 'none';
  new bootstrap.Modal(document.getElementById('pointsModal')).show();
}

async function submitPointsAdjust() {
  const uid  = document.getElementById('adjustUserId').value;
  const pts  = document.getElementById('adjustPts').value;
  const desc = document.getElementById('adjustDesc').value;
  const res_div = document.getElementById('adjustResult');
  if (!pts) return;
  const fd = new FormData();
  fd.append('action','adjust_points'); fd.append('user_id',uid); fd.append('points',pts); fd.append('description',desc);
  const res = await fetch('api/admin_action.php',{method:'POST',body:fd});
  const data = await res.json();
  res_div.style.display='block';
  res_div.innerHTML = data.success
    ? `<span style="color:#4ade80"><i class="bi bi-check-circle me-1"></i>${data.message}</span>`
    : `<span style="color:#f87171">${data.message}</span>`;
  if (data.success) setTimeout(()=>location.reload(), 1200);
}

async function toggleVerify(uid, field, btn) {
  const fd = new FormData();
  fd.append('action','toggle_user'); fd.append('user_id',uid); fd.append('field',field);
  const res = await fetch('api/admin_action.php',{method:'POST',body:fd});
  const data = await res.json();
  if (data.success) location.reload();
  else alert(data.message);
}

// ── Deactivate / Reactivate ───────────────────────────────────────────────────
let _deactivateTarget = null;

function confirmDeactivate(uid, currentActive, name) {
  _deactivateTarget = { uid, currentActive };
  const isDeactivating = currentActive === 1;
  document.getElementById('deactivateModalTitle').textContent  = isDeactivating ? 'Deactivate Account' : 'Reactivate Account';
  document.getElementById('deactivateModalBody').innerHTML =
    isDeactivating
      ? `<p style="font-size:14px;margin-bottom:0;">Are you sure you want to <strong style="color:#f87171;">deactivate</strong> <strong>${name}</strong>?<br>
         <small style="color:var(--muted);font-size:12px;">The user will no longer be able to log in until reactivated.</small></p>`
      : `<p style="font-size:14px;margin-bottom:0;">Are you sure you want to <strong style="color:#4ade80;">reactivate</strong> <strong>${name}</strong>?<br>
         <small style="color:var(--muted);font-size:12px;">The user will regain access to their account.</small></p>`;
  const confirmBtn = document.getElementById('deactivateConfirmBtn');
  confirmBtn.style.cssText = isDeactivating
    ? 'background:rgba(248,113,113,0.2);color:#f87171;border:1.5px solid rgba(248,113,113,0.4);padding:7px 20px;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;'
    : 'background:rgba(74,222,128,0.2);color:#4ade80;border:1.5px solid rgba(74,222,128,0.4);padding:7px 20px;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;';
  confirmBtn.innerHTML = isDeactivating
    ? '<i class="bi bi-person-slash me-1"></i> Yes, Deactivate'
    : '<i class="bi bi-person-check me-1"></i> Yes, Reactivate';
  new bootstrap.Modal(document.getElementById('deactivateModal')).show();
}

async function doDeactivate() {
  if (!_deactivateTarget) return;
  const { uid, currentActive } = _deactivateTarget;
  const newActive = currentActive === 1 ? 0 : 1;
  const fd = new FormData();
  fd.append('action', 'deactivate_user');
  fd.append('user_id', uid);
  fd.append('active', newActive);
  const res  = await fetch('api/admin_action.php', { method: 'POST', body: fd });
  const data = await res.json();
  bootstrap.Modal.getInstance(document.getElementById('deactivateModal')).hide();
  if (data.success) location.reload();
  else alert(data.message);
}

</script>
</body></html>
