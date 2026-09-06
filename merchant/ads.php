<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/merchant_auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
startSecureSession();
requireMerchantLogin();
$merchant = getLoggedInMerchant();
$db = getDB();
$merchant_id = $merchant['id'];

$stmt = $db->prepare("SELECT * FROM merchant_ads WHERE merchant_id = ? ORDER BY created_at DESC");
$stmt->execute([$merchant_id]);
$ads = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VrakeIT — Manage Ads</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    * { box-sizing: border-box; }
    body { font-family: 'Poppins', sans-serif; background: #f8fafc; color: #0f172a; margin: 0; min-height: 100vh; }

    /* ── Header ──────────────────────────────────────── */
    .m-header { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(0, 0, 0, 0.05); padding: 14px 20px; display: flex; align-items: center; gap: 12px; position: sticky; top: 0; z-index: 100; box-shadow: 0 4px 20px rgba(0,0,0,0.02); }
    .m-header a { color: #0f172a; text-decoration: none; font-size: 20px; transition: color 0.2s; }
    .m-header a:hover { color: #f59e0b; }
    .m-header h5 { margin: 0; font-size: 16px; font-weight: 700; flex: 1; color: #0f172a; }
    .btn-new-ad { background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; border: none; border-radius: 10px; padding: 10px 18px; font-size: 13px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px; white-space: nowrap; transition: opacity 0.2s; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2); }
    .btn-new-ad:hover { opacity: 0.9; }

    /* ── Page body ───────────────────────────────────── */
    .page-body { padding: 24px 16px 100px; max-width: 640px; margin: 0 auto; }
    .section-title { font-size: 11px; font-weight: 700; color: rgba(0,0,0,0.4); text-transform: uppercase; letter-spacing: 1px; margin: 24px 0 16px; }

    /* ── Ad cards ────────────────────────────────────── */
    .ad-card { background: #fff; border: 1px solid rgba(0,0,0,0.05); border-radius: 20px; overflow: hidden; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); transition: all 0.2s; }
    .ad-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.05); border-color: rgba(245, 158, 11, 0.2); }
    .ad-card.inactive { opacity: 0.6; background: #f8fafc; }
    .ad-img { width: 100%; height: 180px; object-fit: cover; display: block; border-bottom: 1px solid rgba(0,0,0,0.03); }
    .ad-img-placeholder { width: 100%; height: 140px; background: rgba(0,0,0,0.02); display: flex; align-items: center; justify-content: center; font-size: 40px; color: rgba(0,0,0,0.1); border-bottom: 1px solid rgba(0,0,0,0.03); }
    .ad-body { padding: 20px; }
    .ad-title { font-weight: 700; font-size: 15px; margin-bottom: 6px; color: #0f172a; }
    .ad-desc { font-size: 13px; color: rgba(0,0,0,0.6); margin-bottom: 16px; line-height: 1.5; }
    
    .ad-status-chip { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 700; border-radius: 999px; padding: 4px 12px; margin-bottom: 16px; }
    .chip-active   { background: rgba(16, 185, 129, 0.1);  color: #059669; }
    .chip-inactive { background: rgba(239, 68, 68, 0.1); color: #dc2626; }
    
    .ad-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .btn-sm { border: none; border-radius: 10px; padding: 8px 16px; font-size: 12px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: all 0.2s; font-family: 'Poppins', sans-serif; }
    .btn-edit     { background: rgba(245, 158, 11, 0.1); color: #d97706; }
    .btn-edit:hover     { background: rgba(245, 158, 11, 0.15); }
    .btn-toggle-off { background: rgba(239, 68, 68, 0.1); color: #dc2626; }
    .btn-toggle-off:hover { background: rgba(239, 68, 68, 0.15); }
    .btn-toggle-on  { background: rgba(16, 185, 129, 0.1);  color: #059669; }
    .btn-toggle-on:hover  { background: rgba(16, 185, 129, 0.15); }
    .btn-delete   { background: #f8fafc; color: #ef4444; border: 1px solid rgba(0,0,0,0.05); }
    .btn-delete:hover   { background: #fee2e2; }

    /* ── Empty state ─────────────────────────────────── */
    .empty-state { text-align: center; padding: 48px 20px; color: rgba(0,0,0,0.4); }
    .empty-state i { font-size: 48px; display: block; margin-bottom: 12px; color: rgba(0,0,0,0.1); }

    /* ── Modals ──────────────────────────────────────── */
    .modal-content { background: #fff; border: none; border-radius: 20px; color: #0f172a; box-shadow: 0 20px 40px rgba(0,0,0,0.1); overflow: hidden; }
    .modal-header  { border-bottom: 1px solid rgba(0,0,0,0.05); padding: 16px 20px; background: rgba(0,0,0,0.01); }
    .modal-footer  { border-top:    1px solid rgba(0,0,0,0.05); padding: 16px 20px; background: rgba(0,0,0,0.01); }
    .btn-close     { opacity: 0.5; }
    .btn-close:hover { opacity: 1; }
    .modal-title   { font-size: 15px; font-weight: 700; color: #0f172a; }

    /* ── Form controls ───────────────────────────────── */
    .form-label   { font-size: 11px; font-weight: 700; color: rgba(0,0,0,0.5); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; display: block; }
    .form-ctrl    { background: #f8fafc; border: 1px solid rgba(0,0,0,0.1); color: #0f172a; border-radius: 10px; padding: 10px 12px; font-size: 13px; font-family: 'Poppins',sans-serif; width: 100%; outline: none; transition: border-color 0.2s; }
    .form-ctrl::placeholder { color: rgba(0,0,0,0.3); }
    .form-ctrl:focus { border-color: #f59e0b; background: #fff; box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1); }
    
    .upload-zone { border: 2px dashed rgba(0,0,0,0.15); border-radius: 12px; padding: 20px; text-align: center; cursor: pointer; color: rgba(0,0,0,0.4); transition: all 0.2s; background: #f8fafc; }
    .upload-zone:hover { border-color: #f59e0b; color: #d97706; background: #fffbeb; }
    .upload-zone input { display: none; }
    .upload-fname { color: #d97706; font-size: 12px; font-weight: 600; margin-top: 8px; }
    .img-preview  { width: 100%; max-height: 160px; object-fit: cover; border-radius: 10px; margin-top: 12px; display: none; border: 1px solid rgba(0,0,0,0.05); }

    /* ── Submit buttons ──────────────────────────────── */
    .btn-submit   { background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; border: none; border-radius: 10px; padding: 10px 20px; font-weight: 700; font-size: 13px; cursor: pointer; font-family: 'Poppins',sans-serif; transition: opacity 0.2s; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.2); }
    .btn-submit:hover { opacity: 0.9; }
    .btn-cancel   { background: transparent; color: rgba(0,0,0,0.6); border: none; border-radius: 10px; padding: 10px 18px; font-size: 13px; font-weight: 600; cursor: pointer; font-family: 'Poppins',sans-serif; transition: background 0.2s; }
    .btn-cancel:hover { background: rgba(0,0,0,0.05); }

    /* ── Flash result ────────────────────────────────── */
    .flash-result { font-size: 13px; font-weight: 600; padding: 0; margin-top: 12px; display: none; }
  </style>
</head>
<body>

  <div class="m-header">
    <a href="home.php"><i class="bi bi-arrow-left"></i></a>
    <h5>Manage Advertisements</h5>
    <button class="btn-new-ad" onclick="openCreate()">
      <i class="bi bi-plus-circle"></i> New Ad
    </button>
  </div>

  <div class="page-body">

    <div class="section-title">Your Ads (<?= count($ads) ?>)</div>

    <?php if (empty($ads)): ?>
      <div class="empty-state">
        <i class="bi bi-megaphone"></i>
        <p style="margin:0;font-size:14px;">No ads yet — click <strong style="color:#f59e0b;"></strong> to publish your first one.</p>
      </div>
    <?php else: ?>
      <?php foreach ($ads as $ad): ?>
      <div class="ad-card <?= $ad['is_active'] ? '' : 'inactive' ?>" id="ad-<?= $ad['id'] ?>">
        <?php if ($ad['image_path']): ?>
          <img class="ad-img" src="../assets/uploads/merchant_ads/<?= htmlspecialchars($ad['image_path']) ?>" alt="Ad image">
        <?php else: ?>
          <div class="ad-img-placeholder"><i class="bi bi-image"></i></div>
        <?php endif; ?>
        <div class="ad-body">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:4px;">
            <div class="ad-title"><?= sanitize($ad['title']) ?></div>
            <span class="ad-status-chip <?= $ad['is_active'] ? 'chip-active' : 'chip-inactive' ?>">
              <?= $ad['is_active'] ? '● Active' : '○ Inactive' ?>
            </span>
          </div>
          <?php if ($ad['description']): ?>
            <div class="ad-desc"><?= sanitize($ad['description']) ?></div>
          <?php endif; ?>
          <div class="ad-actions">
            <button class="btn-sm btn-edit" onclick="openEdit(<?= $ad['id'] ?>, '<?= addslashes(htmlspecialchars($ad['title'])) ?>', '<?= addslashes(htmlspecialchars($ad['description'] ?? '')) ?>', '<?= htmlspecialchars($ad['image_path'] ?? '') ?>')">
              <i class="bi bi-pencil"></i> Edit
            </button>
            <?php if ($ad['is_active']): ?>
              <button class="btn-sm btn-toggle-off" onclick="toggleAd(<?= $ad['id'] ?>, 'deactivate', this)">
                <i class="bi bi-pause-circle"></i> Deactivate
              </button>
            <?php else: ?>
              <button class="btn-sm btn-toggle-on" onclick="toggleAd(<?= $ad['id'] ?>, 'activate', this)">
                <i class="bi bi-play-circle"></i> Activate
              </button>
            <?php endif; ?>
            <button class="btn-sm btn-delete" onclick="deleteAd(<?= $ad['id'] ?>, '<?= addslashes(htmlspecialchars($ad['title'])) ?>')">
              <i class="bi bi-trash3"></i> Delete
            </button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>

  </div>

  <!-- ═══ CREATE AD MODAL ═══ -->
  <div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content p-1">
        <div class="modal-header">
          <h6 class="modal-title"><i class="bi bi-megaphone me-2" style="color:#f59e0b;"></i>Create New Ad</h6>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="createForm" enctype="multipart/form-data">
          <div class="modal-body p-3">
            <div class="mb-3">
              <label class="form-label">Ad Title *</label>
              <input type="text" class="form-ctrl" name="title" id="c-title" placeholder="e.g. Grand Opening — 50% Off!" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea class="form-ctrl" name="description" id="c-desc" rows="2" placeholder="What should drivers know?"></textarea>
            </div>
            <div class="mb-2">
              <label class="form-label">Ad Image <span style="color:rgba(0,0,0,0.4);">(optional)</span></label>
              <label class="upload-zone" id="cImgZone">
                <input type="file" name="image" id="cImgFile" accept="image/*">
                <i class="bi bi-image" style="font-size:26px;display:block;margin-bottom:6px;"></i>
                <span id="cImgLabel" style="font-size:13px;">Click to upload</span>
                <small style="display:block;margin-top:3px;opacity:0.55;font-size:11px;">JPG, PNG or WebP — max 5MB</small>
              </label>
              <div class="upload-fname" id="cImgName"></div>
              <img class="img-preview" id="cImgPreview" alt="Preview">
            </div>
            <div class="flash-result" id="createResult"></div>
          </div>
          <div class="modal-footer" style="justify-content:flex-end;gap:10px;">
            <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn-submit" id="createBtn"><i class="bi bi-upload me-1"></i>Publish Ad</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ═══ EDIT AD MODAL ═══ -->
  <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content p-1">
        <div class="modal-header">
          <h6 class="modal-title"><i class="bi bi-pencil me-2" style="color:#d97706;"></i>Edit Advertisement</h6>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="editForm" enctype="multipart/form-data">
          <input type="hidden" name="ad_id" id="e-id">
          <div class="modal-body p-3">
            <div class="mb-3">
              <label class="form-label">Ad Title *</label>
              <input type="text" class="form-ctrl" name="title" id="e-title" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea class="form-ctrl" name="description" id="e-desc" rows="2"></textarea>
            </div>
            <div class="mb-2">
              <label class="form-label">Replace Image <span style="color:rgba(0,0,0,0.4);">(leave blank to keep existing)</span></label>
              <div id="eExistingImgWrap" style="margin-bottom:8px;display:none;">
                <img id="eExistingImg" src="" alt="Current image" style="width:100%;max-height:120px;object-fit:cover;border-radius:10px;border:1px solid rgba(0,0,0,0.05);">
              </div>
              <label class="upload-zone" id="eImgZone">
                <input type="file" name="image" id="eImgFile" accept="image/*">
                <i class="bi bi-arrow-repeat" style="font-size:22px;display:block;margin-bottom:5px;"></i>
                <span id="eImgLabel" style="font-size:13px;">Click to change image</span>
                <small style="display:block;margin-top:3px;opacity:0.55;font-size:11px;">JPG, PNG or WebP — max 5MB</small>
              </label>
              <div class="upload-fname" id="eImgName"></div>
              <img class="img-preview" id="eImgPreview" alt="New preview">
            </div>
            <div class="flash-result" id="editResult"></div>
          </div>
          <div class="modal-footer" style="justify-content:flex-end;gap:10px;">
            <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn-submit" id="editBtn" style="background:linear-gradient(135deg,#b45309,#92400e);">
              <i class="bi bi-save me-1"></i>Save Changes
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    /* ── Modal instances ──────────────────────────────── */
    const createModalEl = document.getElementById('createModal');
    const editModalEl   = document.getElementById('editModal');
    let createModal, editModal;
    document.addEventListener('DOMContentLoaded', () => {
      createModal = new bootstrap.Modal(createModalEl);
      editModal   = new bootstrap.Modal(editModalEl);
    });

    function openCreate() { createModal.show(); }

    /* ── Image preview helper ─────────────────────────── */
    function bindImagePreview(fileInputId, labelId, nameId, previewId) {
      document.getElementById(fileInputId).addEventListener('change', function () {
        if (!this.files[0]) return;
        document.getElementById(labelId).textContent = 'Click to change image';
        document.getElementById(nameId).textContent  = '✓ ' + this.files[0].name;
        const reader = new FileReader();
        reader.onload = e => {
          const img = document.getElementById(previewId);
          img.src = e.target.result;
          img.style.display = 'block';
        };
        reader.readAsDataURL(this.files[0]);
      });
    }

    bindImagePreview('cImgFile', 'cImgLabel', 'cImgName', 'cImgPreview');
    bindImagePreview('eImgFile', 'eImgLabel', 'eImgName', 'eImgPreview');

    /* ── AJAX helper ─────────────────────────────────── */
    async function apiPost(url, formData) {
      const res = await fetch(url, { method: 'POST', body: formData });
      return res.json();
    }

    function showResult(elId, success, msg) {
      const el = document.getElementById(elId);
      el.style.display = 'block';
      el.innerHTML = `<span style="color:${success ? '#4ade80' : '#f87171'}">
        <i class="bi bi-${success ? 'check-circle' : 'x-circle'} me-1"></i>${msg}</span>`;
    }

    /* ── CREATE ──────────────────────────────────────── */
    document.getElementById('createForm').addEventListener('submit', async e => {
      e.preventDefault();
      const btn = document.getElementById('createBtn');
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Publishing…';
      try {
        const data = await apiPost('api/add_ad.php', new FormData(e.target));
        showResult('createResult', data.success, data.message);
        if (data.success) { setTimeout(() => location.reload(), 1100); }
        else { btn.disabled = false; btn.innerHTML = '<i class="bi bi-upload me-1"></i>Publish Ad'; }
      } catch {
        showResult('createResult', false, 'Connection error.');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-upload me-1"></i>Publish Ad';
      }
    });

    /* ── EDIT ────────────────────────────────────────── */
    function openEdit(id, title, desc, imagePath) {
      document.getElementById('e-id').value    = id;
      document.getElementById('e-title').value = title;
      document.getElementById('e-desc').value  = desc;
      document.getElementById('editResult').style.display = 'none';
      document.getElementById('eImgPreview').style.display = 'none';
      document.getElementById('eImgName').textContent = '';
      document.getElementById('eImgLabel').textContent = 'Click to change image';
      document.getElementById('eImgFile').value = '';

      const wrap   = document.getElementById('eExistingImgWrap');
      const imgTag = document.getElementById('eExistingImg');
      if (imagePath) {
        imgTag.src = `../assets/uploads/merchant_ads/${imagePath}`;
        wrap.style.display = 'block';
      } else {
        wrap.style.display = 'none';
      }
      editModal.show();
    }

    document.getElementById('editForm').addEventListener('submit', async e => {
      e.preventDefault();
      const btn = document.getElementById('editBtn');
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Saving…';
      try {
        const data = await apiPost('api/edit_ad.php', new FormData(e.target));
        showResult('editResult', data.success, data.message);
        if (data.success) { setTimeout(() => location.reload(), 1000); }
        else { btn.disabled = false; btn.innerHTML = '<i class="bi bi-save me-1"></i>Save Changes'; }
      } catch {
        showResult('editResult', false, 'Connection error.');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-save me-1"></i>Save Changes';
      }
    });

    /* ── TOGGLE ──────────────────────────────────────── */
    async function toggleAd(id, action, btn) {
      btn.disabled = true;
      const fd = new FormData();
      fd.append('ad_id', id);
      fd.append('action', action);
      try {
        const data = await apiPost('api/toggle_ad.php', fd);
        if (data.success) { location.reload(); }
        else { alert(data.message); btn.disabled = false; }
      } catch { alert('Connection error.'); btn.disabled = false; }
    }

    /* ── DELETE ──────────────────────────────────────── */
    async function deleteAd(id, title) {
      if (!confirm(`Delete ad "${title}"? This cannot be undone.`)) return;
      const fd = new FormData();
      fd.append('ad_id', id);
      try {
        const data = await apiPost('api/delete_ad.php', fd);
        if (data.success) {
          const card = document.getElementById('ad-' + id);
          card.style.transition = 'opacity 0.3s, transform 0.3s';
          card.style.opacity = '0';
          card.style.transform = 'translateY(-6px)';
          setTimeout(() => card.remove(), 320);
        } else { alert(data.message); }
      } catch { alert('Connection error.'); }
    }
  </script>
</body>
</html>
