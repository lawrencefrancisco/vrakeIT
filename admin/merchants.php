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
if ($filter === 'deactivated') $where .= " AND status='deactivated'";

$merchants = $db->query("SELECT * FROM merchants $where ORDER BY created_at DESC")->fetchAll();

adminHead('Merchant Oversight');
?>

<body>
  <?php adminNav('merchants', $admin); ?>
  <div class="main">
    <?php adminTopbar('Merchant Oversight'); ?>
    <div class="page-body">

      <div class="section-card mb-4">
        <div class="section-header" style="justify-content: flex-start; gap: 8px; flex-wrap: wrap;">
          <?php foreach (['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'deactivated' => 'Deactivated'] as $k => $v): ?>
            <a href="?filter=<?= $k ?>" class="btn-admin <?= $filter === $k ? 'btn-primary-admin' : 'btn-review' ?> m-0"><?= $v ?></a>
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
                  <td style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($m['business_type']) ?></td>
                  <td style="font-size:12px;"><?= htmlspecialchars($m['contact_number']) ?></td>
                  <td style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($m['email']) ?></td>
                  <td><span class="badge-status bs-<?= $m['status'] ?>"><?= ucfirst($m['status']) ?></span></td>
                  <td style="font-size:12px;color:var(--muted);"><?= date('M d, Y', strtotime($m['created_at'])) ?></td>
                  <td style="display:flex;gap:6px;flex-wrap:wrap;">
                    <?php if ($m['status'] !== 'approved'): ?>
                      <button class="btn-admin btn-approve" onclick="merchantAction(<?= $m['id'] ?>,'approved')"><i class="bi bi-check2"></i> Approve</button>
                    <?php endif; ?>
                    <?php if ($m['status'] !== 'rejected'): ?>
                      <button class="btn-admin btn-reject" onclick="merchantAction(<?= $m['id'] ?>,'rejected')"><i class="bi bi-x"></i> Reject</button>
                    <?php endif; ?>
                    <?php if ($m['status'] === 'approved'): ?>
                      <button class="btn-admin btn-danger-admin" onclick="merchantAction(<?= $m['id'] ?>, 'deactivated')">
                        <i class="bi bi-pause"></i> Deactivate
                      </button>
                    <?php endif; ?>
                    <?php if (!empty($m['permit']) || !empty($m['logo'])): ?>
                      <button class="btn-admin btn-review" onclick="viewMerchantDocs('<?= htmlspecialchars($m['permit']) ?>', '<?= htmlspecialchars($m['logo']) ?>', '<?= addslashes(htmlspecialchars($m['business_name'])) ?>')" title="View Documents"><i class="bi bi-file-earmark-text"></i></button>
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

    <div class="modal fade" id="docsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.1);">
        <div class="modal-header" style="background: rgba(0, 126, 210, 0.05); border-bottom: 1px solid rgba(0,0,0,0.05);">
          <h5 class="modal-title" style="font-weight: 700; color: #0f172a;"><i class="bi bi-folder2-open me-2 text-primary"></i> <span id="docsBusinessName"></span> Documents</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" style="background: #f8fafc; padding: 24px;">
          <div class="row g-4">
            <div class="col-md-6">
              <h6 style="font-weight: 700; color: #0f172a; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;"><i class="bi bi-file-earmark-text" style="color: #f59e0b;"></i> Business Permit</h6>
              <div id="permitViewer" style="background: #fff; border-radius: 12px; padding: 8px; border: 1px solid rgba(0,0,0,0.05); min-height: 250px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0,0,0,0.02);">
              </div>
            </div>
            <div class="col-md-6">
              <h6 style="font-weight: 700; color: #0f172a; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;"><i class="bi bi-image" style="color: #10b981;"></i> Business Logo</h6>
              <div id="logoViewer" style="background: #fff; border-radius: 12px; padding: 8px; border: 1px solid rgba(0,0,0,0.05); min-height: 250px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0,0,0,0.02);">
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer" style="border-top: 1px solid rgba(0,0,0,0.05); background: #fff;">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 12px; font-size: 14px; padding: 8px 16px; background: rgba(0,0,0,0.05); color: #0f172a; border: none; font-weight: 600;">Close</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
        <div class="modal-body text-center" style="padding: 32px 24px;">
          <i class="bi bi-question-circle text-primary" style="font-size: 3rem; margin-bottom: 16px; display: block;"></i>
          <h5 style="font-weight: 700; color: #0f172a; margin-bottom: 8px;">Confirm Action</h5>
          <p style="color: rgba(0,0,0,0.6); font-size: 14px; margin-bottom: 24px;" id="confirmMessage"></p>
          <div style="display: flex; gap: 8px; justify-content: center;">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 10px; font-size: 13px; font-weight: 600; padding: 8px 20px; background: rgba(0,0,0,0.05); color: #0f172a; border: none;">Cancel</button>
            <button type="button" class="btn btn-primary" id="confirmActionBtn" style="border-radius: 10px; font-size: 13px; font-weight: 600; padding: 8px 20px; border: none;">Confirm</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function viewMerchantDocs(permit, logo, businessName) {
      document.getElementById('docsBusinessName').textContent = businessName;
      
      const permitViewer = document.getElementById('permitViewer');
      if (permit) {
        const pExt = permit.split('.').pop().toLowerCase();
        if (pExt === 'pdf') {
          permitViewer.innerHTML = `<embed src="../assets/uploads/merchants/${permit}" type="application/pdf" width="100%" height="300px" style="border-radius: 8px;" />`;
        } else {
          permitViewer.innerHTML = `<a href="../assets/uploads/merchants/${permit}" target="_blank" style="display:block;"><img src="../assets/uploads/merchants/${permit}" style="max-width: 100%; max-height: 300px; border-radius: 8px; object-fit: contain;" /></a>`;
        }
      } else {
        permitViewer.innerHTML = '<span style="color: rgba(0,0,0,0.4); font-size: 14px;">No permit uploaded</span>';
      }

      const logoViewer = document.getElementById('logoViewer');
      if (logo) {
        logoViewer.innerHTML = `<a href="../assets/uploads/merchants/${logo}" target="_blank" style="display:block;"><img src="../assets/uploads/merchants/${logo}" style="max-width: 100%; max-height: 300px; border-radius: 8px; object-fit: contain;" /></a>`;
      } else {
        logoViewer.innerHTML = '<span style="color: rgba(0,0,0,0.4); font-size: 14px;">No logo uploaded</span>';
      }

      new bootstrap.Modal(document.getElementById('docsModal')).show();
    }

    let currentAction = null;
    let currentMid = null;

    function merchantAction(mid, status) {
      const labels = {
        approved: 'approve',
        rejected: 'reject',
        deactivated: 'deactivate'
      };
      currentAction = status;
      currentMid = mid;
      
      document.getElementById('confirmMessage').innerHTML = `Are you sure you want to <strong>${labels[status]}</strong> this merchant?`;
      
      const btn = document.getElementById('confirmActionBtn');
      if (status === 'approved') {
        btn.style.background = '#10b981';
        btn.textContent = 'Yes, Approve';
      } else if (status === 'rejected') {
        btn.style.background = '#ef4444';
        btn.textContent = 'Yes, Reject';
      } else {
        btn.style.background = '#f59e0b';
        btn.textContent = 'Yes, Deactivate';
      }

      new bootstrap.Modal(document.getElementById('confirmModal')).show();
    }

    document.getElementById('confirmActionBtn').addEventListener('click', async () => {
      const btn = document.getElementById('confirmActionBtn');
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
      
      const fd = new FormData();
      fd.append('action', 'merchant_status');
      fd.append('merchant_id', currentMid);
      fd.append('status', currentAction);
      try {
        const res = await fetch('api/admin_action.php', {
          method: 'POST',
          body: fd
        });
        const data = await res.json();
        if (data.success) location.reload();
        else alert(data.message);
      } catch (err) {
        alert('Connection error');
        btn.disabled = false;
        btn.innerHTML = 'Retry';
      }
    });
  </script>
</body>
</html>
