<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/merchant_auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
startSecureSession();
requireMerchantLogin();
$merchant = getLoggedInMerchant();
$db = getDB();
$merchant_id = $merchant['id'];

$stmt = $db->prepare("SELECT * FROM merchant_rewards WHERE merchant_id = ? ORDER BY created_at DESC");
$stmt->execute([$merchant_id]);
$rewards = $stmt->fetchAll();
$msg   = $_GET['msg']   ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VrakeIT — Manage Rewards</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    * { box-sizing: border-box; }
    body { font-family: 'Poppins', sans-serif; background: #0a0f1e; color: #fff; margin: 0; }
    .m-header { background: rgba(255,255,255,0.05); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(255,255,255,0.08); padding: 14px 20px; display: flex; align-items: center; gap: 12px; position: sticky; top: 0; z-index: 100; }
    .m-header a { color: rgba(255,255,255,0.6); text-decoration: none; font-size: 20px; }
    .m-header h5 { margin: 0; font-size: 16px; font-weight: 700; }
    .page-body { padding: 20px 16px 80px; max-width: 600px; margin: 0 auto; }
    .section-title { font-size: 12px; font-weight: 700; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 1px; margin: 20px 0 12px; }
    .add-form { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 20px; margin-bottom: 24px; }
    .add-form h6 { font-weight: 700; color: #4ade80; margin-bottom: 16px; }
    .form-label { font-size: 11px; font-weight: 600; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
    .form-control { background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12); color: #fff; border-radius: 10px; padding: 10px 12px; font-size: 13px; font-family: 'Poppins',sans-serif; }
    .form-control::placeholder { color: rgba(255,255,255,0.3); }
    .form-control:focus { background: rgba(255,255,255,0.1); border-color: #4ade80; color: #fff; box-shadow: 0 0 0 3px rgba(74,222,128,0.12); }
    .btn-add { background: linear-gradient(135deg,#16a34a,#15803d); color: #fff; border: none; border-radius: 10px; padding: 11px 20px; font-weight: 700; font-size: 13px; cursor: pointer; width: 100%; transition: all 0.2s; }
    .btn-add:hover { opacity: 0.9; }
    .reward-card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 16px; margin-bottom: 12px; }
    .reward-card.inactive { opacity: 0.5; }
    .reward-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
    .reward-name { font-weight: 700; font-size: 14px; }
    .reward-pts { background: rgba(74,222,128,0.15); border: 1px solid rgba(74,222,128,0.3); color: #4ade80; font-size: 11px; font-weight: 700; border-radius: 50px; padding: 3px 10px; white-space: nowrap; }
    .reward-desc { font-size: 12px; color: rgba(255,255,255,0.5); margin-bottom: 10px; }
    .reward-meta { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
    .reward-chip { font-size: 11px; background: rgba(255,255,255,0.08); border-radius: 50px; padding: 3px 10px; color: rgba(255,255,255,0.6); }
    .reward-actions { display: flex; gap: 8px; }
    .btn-sm-action { border: none; border-radius: 8px; padding: 7px 14px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
    .btn-replenish { background: rgba(96,180,255,0.15); color: #60b4ff; border: 1px solid rgba(96,180,255,0.3); }
    .btn-deactivate { background: rgba(248,113,113,0.12); color: #f87171; border: 1px solid rgba(248,113,113,0.3); }
    .btn-activate { background: rgba(74,222,128,0.12); color: #4ade80; border: 1px solid rgba(74,222,128,0.3); }
    .empty-state { text-align: center; padding: 32px; color: rgba(255,255,255,0.3); }
    .empty-state i { font-size: 40px; display: block; margin-bottom: 10px; }
    .alert-flash { border-radius: 12px; font-size: 13px; margin-bottom: 16px; }
    /* Modal */
    .modal-content { background: #111827; border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; color: #fff; }
    .modal-header { border-bottom: 1px solid rgba(255,255,255,0.08); }
    .modal-footer { border-top: 1px solid rgba(255,255,255,0.08); }
    .btn-close { filter: invert(1); }
  </style>
</head>
<body>
  <div class="m-header">
    <a href="home.php"><i class="bi bi-arrow-left"></i></a>
    <h5>Manage Rewards</h5>
  </div>

  <div class="page-body">
    <?php if($msg): ?><div class="alert alert-success alert-flash"><i class="bi bi-check-circle me-2"></i><?= sanitize($msg) ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-danger alert-flash"><i class="bi bi-x-circle me-2"></i><?= sanitize($error) ?></div><?php endif; ?>

    <div class="add-form">
      <h6><i class="bi bi-plus-circle me-2"></i>Create New Reward</h6>
      <form id="addRewardForm">
        <input type="hidden" name="merchant_id" value="<?= $merchant_id ?>">
        <div class="mb-3">
          <label class="form-label">Reward Name</label>
          <input type="text" class="form-control" name="reward_name" placeholder="e.g. 10% Off on All Items" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Description / Terms</label>
          <textarea class="form-control" name="description" rows="2" placeholder="Any conditions or details..."></textarea>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-4">
            <label class="form-label">Points Required</label>
            <input type="number" class="form-control" name="points_required" placeholder="50" min="10" required>
          </div>
          <div class="col-4">
            <label class="form-label">Stock</label>
            <input type="number" class="form-control" name="quantity" placeholder="100" min="1" required>
          </div>
          <div class="col-4">
            <label class="form-label">Valid (Days)</label>
            <input type="number" class="form-control" name="duration_days" placeholder="30" min="1" required>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Category</label>
          <select class="form-control" name="category">
            <option>Food &amp; Beverage</option>
            <option>Fuel &amp; Transport</option>
            <option>Health &amp; Wellness</option>
            <option>Shopping</option>
            <option>General</option>
          </select>
        </div>
        <div id="addRewardResult" class="mb-2" style="font-size:13px;display:none;"></div>
        <button type="submit" class="btn-add" id="addRewardBtn"><i class="bi bi-plus me-1"></i>Add Reward</button>
      </form>
    </div>

    <div class="section-title">Your Rewards (<?= count($rewards) ?>)</div>

    <?php if(count($rewards) > 0): ?>
      <?php foreach($rewards as $r): ?>
      <div class="reward-card <?= $r['is_active'] ? '' : 'inactive' ?>">
        <div class="reward-top">
          <div class="reward-name"><?= sanitize($r['reward_name']) ?></div>
          <div class="reward-pts"><?= $r['points_required'] ?> pts</div>
        </div>
        <?php if($r['description']): ?><div class="reward-desc"><?= sanitize($r['description']) ?></div><?php endif; ?>
        <div class="reward-meta">
          <span class="reward-chip"><i class="bi bi-box me-1"></i>Stock: <?= $r['quantity'] ?? '∞' ?></span>
          <span class="reward-chip"><i class="bi bi-arrow-repeat me-1"></i>Redeemed: <?= $r['redeemed_count'] ?></span>
          <?php if($r['expires_at']): ?>
          <span class="reward-chip <?= strtotime($r['expires_at']) < time() ? 'text-danger' : '' ?>">
            <i class="bi bi-clock me-1"></i><?= date('M d, Y', strtotime($r['expires_at'])) ?>
          </span>
          <?php endif; ?>
          <span class="reward-chip" style="<?= $r['is_active'] ? 'color:#4ade80;' : 'color:#f87171;' ?>">
            <?= $r['is_active'] ? '● Active' : '○ Inactive' ?>
          </span>
        </div>
        <div class="reward-actions" style="flex-wrap:wrap;gap:6px;">
          <button class="btn-sm-action btn-replenish" onclick="openReplenish(<?= $r['id'] ?>, '<?= addslashes($r['reward_name']) ?>')">
            <i class="bi bi-arrow-up-circle me-1"></i>Replenish
          </button>
          <button class="btn-sm-action" style="background:rgba(251,191,36,0.12);color:#f59e0b;border:1px solid rgba(251,191,36,0.3);" onclick="openEdit(<?= $r['id'] ?>, '<?= addslashes($r['reward_name']) ?>', '<?= addslashes($r['description'] ?? '') ?>', <?= $r['points_required'] ?>, <?= $r['quantity'] ?>, '<?= $r['category'] ?? 'General' ?>')">
            <i class="bi bi-pencil me-1"></i>Edit
          </button>
          <?php if($r['is_active']): ?>
          <form action="api/toggle_reward.php" method="POST" style="margin:0;">
            <input type="hidden" name="reward_id" value="<?= $r['id'] ?>">
            <input type="hidden" name="action" value="deactivate">
            <button type="submit" class="btn-sm-action btn-deactivate"><i class="bi bi-pause-circle me-1"></i>Deactivate</button>
          </form>
          <?php else: ?>
          <form action="api/toggle_reward.php" method="POST" style="margin:0;">
            <input type="hidden" name="reward_id" value="<?= $r['id'] ?>">
            <input type="hidden" name="action" value="activate">
            <button type="submit" class="btn-sm-action btn-activate"><i class="bi bi-play-circle me-1"></i>Activate</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="empty-state"><i class="bi bi-tags"></i><p>No rewards yet. Create your first one above.</p></div>
    <?php endif; ?>
  </div>

  <!-- Replenish Modal -->
  <div class="modal fade" id="replenishModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content p-2">
        <div class="modal-header">
          <h6 class="modal-title">Replenish: <span id="replenishName" class="text-success"></span></h6>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form action="api/replenish_reward.php" method="POST">
          <div class="modal-body">
            <input type="hidden" name="reward_id" id="replenishId">
            <div class="mb-3">
              <label class="form-label">Add Quantity</label>
              <input type="number" class="form-control" name="add_quantity" placeholder="e.g. 50" min="0" value="0">
            </div>
            <div class="mb-3">
              <label class="form-label">Extend Expiry (Days from now)</label>
              <input type="number" class="form-control" name="add_duration" placeholder="e.g. 30" min="0" value="0">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success btn-sm">Replenish</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit Reward Modal -->
  <div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content p-2">
        <div class="modal-header">
          <h6 class="modal-title text-warning"><i class="bi bi-pencil me-2"></i>Edit Reward</h6>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="editRewardForm">
          <div class="modal-body">
            <input type="hidden" name="reward_id" id="editRewardId">
            <div class="mb-3">
              <label class="form-label">Reward Name</label>
              <input type="text" class="form-control" name="reward_name" id="editName" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Description / Terms</label>
              <textarea class="form-control" name="description" id="editDesc" rows="2"></textarea>
            </div>
            <div class="row g-2 mb-3">
              <div class="col-4">
                <label class="form-label">Points</label>
                <input type="number" class="form-control" name="points_required" id="editPts" min="10" required>
              </div>
              <div class="col-4">
                <label class="form-label">Stock</label>
                <input type="number" class="form-control" name="quantity" id="editQty" min="1" required>
              </div>
              <div class="col-4">
                <label class="form-label">Valid (Days)</label>
                <input type="number" class="form-control" name="duration_days" id="editDays" min="1" value="30" required>
              </div>
            </div>
            <div class="mb-2">
              <label class="form-label">Category</label>
              <select class="form-control" name="category" id="editCat">
                <option>Food & Beverage</option>
                <option>Fuel & Transport</option>
                <option>Health & Wellness</option>
                <option>Shopping</option>
                <option>General</option>
              </select>
            </div>
            <div id="editResult" style="font-size:13px;display:none;" class="mt-2"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning btn-sm text-dark fw-bold" id="editSaveBtn">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function openReplenish(id, name) {
      document.getElementById('replenishId').value = id;
      document.getElementById('replenishName').textContent = name;
      new bootstrap.Modal(document.getElementById('replenishModal')).show();
    }

    function openEdit(id, name, desc, pts, qty, cat) {
      document.getElementById('editRewardId').value = id;
      document.getElementById('editName').value = name;
      document.getElementById('editDesc').value = desc;
      document.getElementById('editPts').value = pts;
      document.getElementById('editQty').value = qty;
      document.getElementById('editCat').value = cat;
      document.getElementById('editResult').style.display = 'none';
      new bootstrap.Modal(document.getElementById('editModal')).show();
    }

    document.getElementById('editRewardForm').addEventListener('submit', async e => {
      e.preventDefault();
      const btn = document.getElementById('editSaveBtn');
      const res_div = document.getElementById('editResult');
      btn.disabled = true; btn.textContent = 'Saving...';
      try {
        const res = await fetch('api/edit_reward.php', { method:'POST', body: new FormData(e.target) });
        const data = await res.json();
        res_div.style.display = 'block';
        if (data.success) {
          res_div.innerHTML = '<span style="color:#4ade80"><i class="bi bi-check-circle me-1"></i>' + data.message + '</span>';
          setTimeout(() => location.reload(), 1000);
        } else {
          res_div.innerHTML = '<span style="color:#f87171"><i class="bi bi-x-circle me-1"></i>' + data.message + '</span>';
          btn.disabled = false; btn.textContent = 'Save Changes';
        }
      } catch { res_div.style.display='block'; res_div.innerHTML='<span style="color:#f87171">Connection error.</span>'; btn.disabled=false; btn.textContent='Save Changes'; }
    });

    document.getElementById('addRewardForm').addEventListener('submit', async e => {
      e.preventDefault();
      const btn = document.getElementById('addRewardBtn');
      const res_div = document.getElementById('addRewardResult');
      btn.disabled = true; btn.textContent = 'Adding...';
      try {
        const res = await fetch('api/add_reward.php', { method:'POST', body: new FormData(e.target) });
        const data = await res.json();
        res_div.style.display = 'block';
        if (data.success) {
          res_div.innerHTML = '<span style="color:#4ade80"><i class="bi bi-check-circle me-1"></i>' + data.message + '</span>';
          setTimeout(() => location.reload(), 1200);
        } else {
          res_div.innerHTML = '<span style="color:#f87171"><i class="bi bi-x-circle me-1"></i>' + data.message + '</span>';
          btn.disabled = false; btn.innerHTML = '<i class="bi bi-plus me-1"></i>Add Reward';
        }
      } catch { res_div.style.display='block'; res_div.innerHTML='<span style="color:#f87171">Connection error.</span>'; btn.disabled=false; btn.innerHTML='<i class="bi bi-plus me-1"></i>Add Reward'; }
    });
  </script>
</body>
</html>
