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
    body { font-family: 'Poppins', sans-serif; background: #0a0f1e; color: #fff; margin: 0; min-height: 100vh; }

    /* ── Header ──────────────────────────────────────── */
    .m-header { background: rgba(255,255,255,0.05); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(255,255,255,0.08); padding: 14px 20px; display: flex; align-items: center; gap: 12px; position: sticky; top: 0; z-index: 100; }
    .m-header a { color: rgba(255,255,255,0.6); text-decoration: none; font-size: 20px; }
    .m-header h5 { margin: 0; font-size: 16px; font-weight: 700; flex: 1; }
    .btn-new-ad { background: linear-gradient(135deg, #ca8a04, #a16207); color: #fff; border: none; border-radius: 10px; padding: 8px 16px; font-size: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px; white-space: nowrap; transition: opacity 0.2s; }
    .btn-new-ad:hover { opacity: 0.88; }

    /* ── Page body ───────────────────────────────────── */
    .page-body { padding: 20px 16px 100px; max-width: 640px; margin: 0 auto; }
    .section-title { font-size: 11px; font-weight: 700; color: rgba(255,255,255,0.35); text-transform: uppercase; letter-spacing: 1px; margin: 24px 0 12px; }

    /* ── Ad cards ────────────────────────────────────── */
    .ad-card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.09); border-radius: 18px; overflow: hidden; margin-bottom: 14px; transition: border-color 0.2s; }
    .ad-card:hover { border-color: rgba(253,224,71,0.25); }
    .ad-card.inactive { opacity: 0.45; }
    .ad-img { width: 100%; height: 160px; object-fit: cover; display: block; }
    .ad-img-placeholder { width: 100%; height: 100px; background: rgba(255,255,255,0.04); display: flex; align-items: center; justify-content: center; font-size: 36px; color: rgba(255,255,255,0.15); }
    .ad-body { padding: 14px 16px 16px; }
    .ad-title { font-weight: 700; font-size: 14px; margin-bottom: 4px; }
    .ad-desc { font-size: 12px; color: rgba(255,255,255,0.45); margin-bottom: 12px; line-height: 1.5; }
    .ad-status-chip { display: inline-flex; align-items: center; gap: 4px; font-size: 10px; font-weight: 700; border-radius: 999px; padding: 2px 9px; margin-bottom: 12px; }
    .chip-active   { background: rgba(74,222,128,0.12);  color: #4ade80; border: 1px solid rgba(74,222,128,0.3); }
    .chip-inactive { background: rgba(248,113,113,0.10); color: #f87171; border: 1px solid rgba(248,113,113,0.3); }
    .ad-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .btn-sm { border: none; border-radius: 8px; padding: 6px 13px; font-size: 11px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: all 0.2s; font-family: 'Poppins', sans-serif; }
    .btn-edit     { background: rgba(251,191,36,0.12); color: #fbbf24; border: 1px solid rgba(251,191,36,0.3); }
    .btn-edit:hover     { background: rgba(251,191,36,0.22); }
    .btn-toggle-off { background: rgba(248,113,113,0.12); color: #f87171; border: 1px solid rgba(248,113,113,0.3); }
    .btn-toggle-off:hover { background: rgba(248,113,113,0.22); }
    .btn-toggle-on  { background: rgba(74,222,128,0.12);  color: #4ade80; border: 1px solid rgba(74,222,128,0.3); }
    .btn-toggle-on:hover  { background: rgba(74,222,128,0.22); }
    .btn-delete   { background: rgba(248,113,113,0.08); color: #f87171; border: 1px solid rgba(248,113,113,0.2); }
    .btn-delete:hover   { background: rgba(248,113,113,0.2); }

    /* ── Empty state ─────────────────────────────────── */
    .empty-state { text-align: center; padding: 48px 20px; color: rgba(255,255,255,0.3); }
    .empty-state i { font-size: 48px; display: block; margin-bottom: 12px; color: rgba(253,224,71,0.25); }

    /* ── Modals ──────────────────────────────────────── */
    .modal-content { background: #111827; border: 1px solid rgba(255,255,255,0.09); border-radius: 20px; color: #fff; }
    .modal-header  { border-bottom: 1px solid rgba(255,255,255,0.08); padding: 16px 20px; }
    .modal-footer  { border-top:    1px solid rgba(255,255,255,0.08); padding: 14px 20px; }
    .btn-close     { filter: invert(1); }
    .modal-title   { font-size: 15px; font-weight: 700; }

    /* ── Form controls ───────────────────────────────── */
    .form-label   { font-size: 11px; font-weight: 600; color: rgba(255,255,255,0.45); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; display: block; }
    .form-ctrl    { background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12); color: #fff; border-radius: 10px; padding: 10px 12px; font-size: 13px; font-family: 'Poppins',sans-serif; width: 100%; outline: none; transition: border-color 0.2s; }
    .form-ctrl::placeholder { color: rgba(255,255,255,0.3); }
    .form-ctrl:focus { border-color: #fde047; background: rgba(255,255,255,0.1); }
    .upload-zone { border: 2px dashed rgba(255,255,255,0.15); border-radius: 12px; padding: 16px; text-align: center; cursor: pointer; color: rgba(255,255,255,0.4); transition: all 0.2s; }
    .upload-zone:hover { border-color: #fde047; color: #fde047; background: rgba(253,224,71,0.04); }
    .upload-zone input { display: none; }
    .upload-fname { color: #fde047; font-size: 11px; margin-top: 5px; }
    .img-preview  { width: 100%; max-height: 140px; object-fit: cover; border-radius: 10px; margin-top: 8px; display: none; }

    /* ── Submit buttons ──────────────────────────────── */
    .btn-submit   { background: linear-gradient(135deg, #ca8a04, #a16207); color: #fff; border: none; border-radius: 10px; padding: 10px 20px; font-weight: 700; font-size: 13px; cursor: pointer; font-family: 'Poppins',sans-serif; transition: opacity 0.2s; }
    .btn-submit:hover { opacity: 0.9; }
    .btn-cancel   { background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.65); border: 1px solid rgba(255,255,255,0.12); border-radius: 10px; padding: 10px 18px; font-size: 13px; font-weight: 600; cursor: pointer; font-family: 'Poppins',sans-serif; }

    /* ── Flash result ────────────────────────────────── */
    .flash-result { font-size: 13px; padding: 0; margin-top: 8px; display: none; }
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
        <p style="margin:0;font-size:14px;">No ads yet — click <strong style="color:#fde047;"></strong> to publish your first one.</p>
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
          <h6 class="modal-title"><i class="bi bi-megaphone me-2" style="color:#fde047;"></i>Create New Ad</h6>
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
              <label class="form-label">Ad Image <span style="color:rgba(255,255,255,0.3);">(optional)</span></label>
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
          <h6 class="modal-title"><i class="bi bi-pencil me-2" style="color:#fbbf24;"></i>Edit Advertisement</h6>
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
              <label class="form-label">Replace Image <span style="color:rgba(255,255,255,0.3);">(leave blank to keep existing)</span></label>
              <div id="eExistingImgWrap" style="margin-bottom:8px;display:none;">
                <img id="eExistingImg" src="" alt="Current image" style="width:100%;max-height:120px;object-fit:cover;border-radius:10px;border:1px solid rgba(255,255,255,0.1);">
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
