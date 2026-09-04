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

// Fetch all announcements
$announcements = $db->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll();

adminHead('Announcements');
?>
<body>
  <?php adminNav('announcements', $admin); ?>
  
  <div class="main">
    <?php adminTopbar('Announcement Management'); ?>
    
    <div class="page-body">
      <!-- Header Actions -->
      <div class="section-card mb-4">
        <div class="section-header" style="flex-wrap:wrap;gap:10px;">
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button class="btn-admin btn-primary-admin" onclick="openAnnouncementModal()">
              <i class="bi bi-plus-lg me-1"></i> New Announcement
            </button>
          </div>
        </div>
      </div>

      <!-- Announcements Table -->
      <div class="section-card">
        <div class="section-header">
          <span class="section-title-text"><i class="bi bi-megaphone-fill me-2"></i>Active & Past Announcements (<?= count($announcements) ?>)</span>
        </div>
        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead>
              <tr>
                <th>Title</th>
                <th>Content Preview</th>
                <th>Status</th>
                <th>Date Posted</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($announcements) > 0): ?>
                <?php foreach ($announcements as $ann): ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($ann['title']) ?></strong></td>
                    <td style="color:rgba(255,255,255,0.6);font-size:12px;max-width:300px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                      <?= htmlspecialchars($ann['content']) ?>
                    </td>
                    <td>
                      <?php if ($ann['is_active']): ?>
                        <span class="badge-status bs-approved">Active</span>
                      <?php else: ?>
                        <span class="badge-status bs-closed">Inactive</span>
                      <?php endif; ?>
                    </td>
                    <td style="font-size:12px;color:rgba(255,255,255,0.4);">
                      <?= date('M d, Y', strtotime($ann['created_at'])) ?>
                    </td>
                    <td style="display:flex;gap:6px;">
                      <button class="btn-admin btn-review" onclick='editAnnouncement(<?= json_encode($ann) ?>)'>
                        <i class="bi bi-pencil"></i>
                      </button>
                      <button class="btn-admin btn-danger-admin" onclick="deleteAnnouncement(<?= $ann['id'] ?>)">
                        <i class="bi bi-trash"></i>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5" style="text-align:center;padding:40px;color:rgba(255,255,255,0.4);">
                    <i class="bi bi-megaphone" style="font-size:32px;display:block;margin-bottom:10px;color:rgba(255,255,255,0.2);"></i>
                    No announcements published yet.
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Announcement Modal -->
  <div class="modal fade modal-dark" id="announcementModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content p-2">
        <div class="modal-header">
          <h6 class="modal-title" id="annModalTitle">New Announcement</h6>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="annId">
          <div class="mb-3">
            <label class="form-lbl">Title</label>
            <input type="text" id="annTitle" class="form-dark" placeholder="e.g. System Maintenance">
          </div>
          <div class="mb-3">
            <label class="form-lbl">Content / Message</label>
            <textarea id="annContent" class="form-dark" rows="4" placeholder="Write your announcement here..."></textarea>
          </div>
          <div class="mb-3">
            <label class="form-lbl">Status</label>
            <select id="annStatus" class="form-dark" style="padding:10px 14px;">
              <option value="1">Active (Visible)</option>
              <option value="0">Inactive (Hidden)</option>
            </select>
          </div>
          <div id="annResult" style="font-size:13px;display:none;" class="mt-2"></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button class="btn-admin btn-primary-admin" onclick="saveAnnouncement()">
            <i class="bi bi-save"></i> Save Announcement
          </button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const modal = new bootstrap.Modal(document.getElementById('announcementModal'));

    function openAnnouncementModal() {
      document.getElementById('annModalTitle').textContent = 'New Announcement';
      document.getElementById('annId').value = '';
      document.getElementById('annTitle').value = '';
      document.getElementById('annContent').value = '';
      document.getElementById('annStatus').value = '1';
      document.getElementById('annResult').style.display = 'none';
      modal.show();
    }

    function editAnnouncement(ann) {
      document.getElementById('annModalTitle').textContent = 'Edit Announcement';
      document.getElementById('annId').value = ann.id;
      document.getElementById('annTitle').value = ann.title;
      document.getElementById('annContent').value = ann.content;
      document.getElementById('annStatus').value = ann.is_active;
      document.getElementById('annResult').style.display = 'none';
      modal.show();
    }

    async function saveAnnouncement() {
      const id = document.getElementById('annId').value;
      const title = document.getElementById('annTitle').value;
      const content = document.getElementById('annContent').value;
      const status = document.getElementById('annStatus').value;
      const res_div = document.getElementById('annResult');

      if (!title || !content) {
        res_div.style.display = 'block';
        res_div.innerHTML = `<span style="color:#f87171">Title and content are required.</span>`;
        return;
      }

      const fd = new FormData();
      fd.append('action', 'save_announcement');
      fd.append('id', id);
      fd.append('title', title);
      fd.append('content', content);
      fd.append('is_active', status);

      const res = await fetch('api/admin_action.php', {method: 'POST', body: fd});
      const data = await res.json();

      res_div.style.display = 'block';
      res_div.innerHTML = data.success 
        ? `<span style="color:#4ade80"><i class="bi bi-check-circle me-1"></i>${data.message}</span>` 
        : `<span style="color:#f87171">${data.message}</span>`;

      if (data.success) setTimeout(() => location.reload(), 1000);
    }

    async function deleteAnnouncement(id) {
      if (!confirm('Are you sure you want to delete this announcement?')) return;

      const fd = new FormData();
      fd.append('action', 'delete_announcement');
      fd.append('id', id);

      const res = await fetch('api/admin_action.php', {method: 'POST', body: fd});
      const data = await res.json();

      if (data.success) location.reload();
      else alert(data.message);
    }
  </script>
</body>
</html>