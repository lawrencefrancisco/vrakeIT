<?php
require_once '../includes/auth.php';
require_once '../includes/admin_auth.php';
require_once '../includes/functions.php';
require_once 'layout.php';

startSecureSession();
requireAdminLogin();

// Prevent moderator access
if ($_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$admin = getAdminUser();

adminHead('Personnel Management', 'personnel');
adminNav('personnel', $admin);
?>

<div class="main">
    <?php adminTopbar('Personnel Management'); ?>

    <div class="page-body">
        <div style="max-width: 720px; margin: 0 auto;">

            <!-- ── Role Tabs ───────────────────────────────────────── -->
            <div style="display:flex; gap:8px; margin-bottom:20px;">
                <button id="tabEnforcer" onclick="switchTab('enforcer')"
                    style="flex:1; padding:10px 0; border-radius:10px; border:1.5px solid var(--muted);
                           background:var(--muted); color:var(--text); font-size:13px; font-weight:600;
                           cursor:pointer; transition:all .2s; display:flex; align-items:center; justify-content:center; gap:8px;">
                    <i class="bi bi-shield-fill-check" style="color:#60a5fa;"></i> Enforcer
                </button>
                <button id="tabModerator" onclick="switchTab('moderator')"
                    style="flex:1; padding:10px 0; border-radius:10px; border:1.5px solid var(--muted);
                           background:transparent; color:var(--muted); font-size:13px; font-weight:600;
                           cursor:pointer; transition:all .2s; display:flex; align-items:center; justify-content:center; gap:8px;">
                    <i class="bi bi-person-badge-fill" style="color:#a78bfa;"></i> Moderator
                </button>
                <button id="tabAdmin" onclick="switchTab('admin')"
                    style="flex:1; padding:10px 0; border-radius:10px; border:1.5px solid var(--muted);
                           background:transparent; color:var(--muted); font-size:13px; font-weight:600;
                           cursor:pointer; transition:all .2s; display:flex; align-items:center; justify-content:center; gap:8px;">
                    <i class="bi bi-stars" style="color:#fbbf24;"></i> Admin
                </button>
            </div>

            <!-- ══════════════════════════════════════════════════════
                 PANEL: ENFORCER
            ═══════════════════════════════════════════════════════════ -->
            <div id="panelEnforcer">

                <!-- Create Enforcer Form -->
                <div class="section-card" style="margin-bottom:20px;">
                    <div class="section-header">
                        <div class="section-title-text">
                            <i class="bi bi-shield-plus me-2" style="color:#60a5fa;"></i>Create Enforcer Account
                        </div>
                    </div>
                    <div style="padding:24px;">
                        <div id="alertBoxEnforcer" class="alert d-none mb-4" style="font-size:13px; border-radius:10px;"></div>
                        <form id="createEnforcerForm">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-lbl">First Name</label>
                                    <input type="text" class="form-dark" name="first_name" placeholder="Juan" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-lbl">Last Name</label>
                                    <input type="text" class="form-dark" name="last_name" placeholder="Dela Cruz" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-lbl">Email Address</label>
                                <input type="email" class="form-dark" name="email" placeholder="enforcer@vrakeit.com" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-lbl">Mobile Number</label>
                                <input type="tel" class="form-dark" name="phone" placeholder="09XXXXXXXXX" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-lbl">Temporary Password</label>
                                <input type="password" class="form-dark" name="password" placeholder="••••••••" required>
                                <small style="color:var(--muted); font-size:11px; margin-top:4px; display:block;">
                                    Provide this password to the enforcer.
                                </small>
                            </div>
                            <button type="submit" class="btn-primary-admin w-100 py-2" id="submitEnforcerBtn"
                                style="border-radius:10px; font-weight:600;">
                                <i class="bi bi-person-plus-fill me-2"></i> Create Enforcer Account
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Enforcer List -->
                <div class="section-card">
                    <div class="section-header">
                        <div class="section-title-text">
                            <i class="bi bi-shield-fill-check me-2" style="color:#60a5fa;"></i>Enforcer Accounts
                        </div>
                        <span id="enforcerCount" style="font-size:11px; color:var(--muted); background:var(--muted); padding:3px 10px; border-radius:999px;">
                            Loading…
                        </span>
                    </div>
                    <div id="enforcerListWrap" style="overflow-x:auto;">
                        <div style="padding:32px; text-align:center; color:var(--muted); font-size:13px;">
                            <span class="spinner-border spinner-border-sm me-2" role="status"></span>Loading enforcers…
                        </div>
                    </div>
                </div>
            </div><!-- /panelEnforcer -->

            <!-- ══════════════════════════════════════════════════════
                 PANEL: MODERATOR
            ═══════════════════════════════════════════════════════════ -->
            <div id="panelModerator" style="display:none;">

                <!-- Create Moderator Form -->
                <div class="section-card" style="margin-bottom:20px;">
                    <div class="section-header">
                        <div class="section-title-text">
                            <i class="bi bi-person-badge me-2" style="color:#a78bfa;"></i>Create Moderator Account
                        </div>
                    </div>
                    <div style="padding:24px;">
                        <div style="background:rgba(167,139,250,0.08); border:1.5px solid rgba(167,139,250,0.22);
                                    border-radius:10px; padding:14px 16px; margin-bottom:20px; font-size:12px;
                                    color:var(--muted); line-height:1.6;">
                            <div style="font-weight:700; color:#a78bfa; margin-bottom:6px; font-size:13px;">
                                <i class="bi bi-lock-fill me-2"></i>Restricted Access Role
                            </div>
                            Moderators can review reports, manage incident statuses, and view user profiles.
                            They <strong style="color:var(--muted);">cannot</strong> access:
                            <ul style="margin:8px 0 0 4px; padding-left:16px;">
                                <li>Personnel Management</li>
                                <li>User Management</li>
                                <li>Merchant Oversight</li>
                                <li>Reward Controls &amp; Voucher Records</li>
                                <li>System Announcements</li>
                            </ul>
                        </div>
                        <div id="alertBoxModerator" class="alert d-none mb-4" style="font-size:13px; border-radius:10px;"></div>
                        <form id="createModeratorForm">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-lbl">First Name</label>
                                    <input type="text" class="form-dark" name="first_name" placeholder="Maria" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-lbl">Last Name</label>
                                    <input type="text" class="form-dark" name="last_name" placeholder="Santos" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-lbl">Email Address</label>
                                <input type="email" class="form-dark" name="email" placeholder="moderator@vrakeit.com" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-lbl">Mobile Number</label>
                                <input type="tel" class="form-dark" name="phone" placeholder="09XXXXXXXXX" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-lbl">Temporary Password</label>
                                <input type="password" class="form-dark" name="password" placeholder="••••••••" required>
                                <small style="color:var(--muted); font-size:11px; margin-top:4px; display:block;">
                                    Provide this password to the moderator.
                                </small>
                            </div>
                            <button type="submit" class="btn-primary-admin w-100 py-2" id="submitModeratorBtn"
                                style="border-radius:10px; font-weight:600; background:linear-gradient(135deg,#7c3aed,#a78bfa);">
                                <i class="bi bi-person-plus-fill me-2"></i> Create Moderator Account
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Moderator List -->
                <div class="section-card">
                    <div class="section-header">
                        <div class="section-title-text">
                            <i class="bi bi-person-badge-fill me-2" style="color:#a78bfa;"></i>Moderator Accounts
                        </div>
                        <span id="moderatorCount" style="font-size:11px; color:var(--muted); background:var(--muted); padding:3px 10px; border-radius:999px;">
                            Loading…
                        </span>
                    </div>
                    <div id="moderatorListWrap" style="overflow-x:auto;">
                        <div style="padding:32px; text-align:center; color:var(--muted); font-size:13px;">
                            <span class="spinner-border spinner-border-sm me-2" role="status"></span>Loading moderators…
                        </div>
                    </div>
                </div>
            </div><!-- /panelModerator -->

            <!-- ══════════════════════════════════════════════════════
                 PANEL: ADMIN
            ═══════════════════════════════════════════════════════════ -->
            <div id="panelAdmin" style="display:none;">
                <div class="section-card">
                    <div class="section-header">
                        <div class="section-title-text">
                            <i class="bi bi-stars me-2" style="color:#fbbf24;"></i>Admin Accounts
                        </div>
                        <span id="adminCount" style="font-size:11px; color:var(--muted); background:var(--muted); padding:3px 10px; border-radius:999px;">
                            Loading…
                        </span>
                    </div>
                    <div id="adminListWrap" style="overflow-x:auto;">
                        <div style="padding:32px; text-align:center; color:var(--muted); font-size:13px;">
                            <span class="spinner-border spinner-border-sm me-2" role="status"></span>Loading admins…
                        </div>
                    </div>
                </div>
            </div><!-- /panelAdmin -->

        </div><!-- /max-width wrapper -->
    </div><!-- /page-body -->
</div><!-- /main -->

<script>
// ── Tab switcher ─────────────────────────────────────────────────────────────
const tabStyles = {
    enforcer:  { bg: 'var(--muted)',       color: '#fff',      border: 'var(--muted)' },
    moderator: { bg: 'rgba(167,139,250,0.12)',        color: '#c4b5fd',   border: 'rgba(167,139,250,0.35)' },
    admin:     { bg: 'rgba(251,191,36,0.10)',          color: '#fbbf24',   border: 'rgba(251,191,36,0.35)' },
};
const tabInactive = { bg: 'transparent', color: 'var(--muted)', border: 'var(--muted)' };

function applyTabStyle(btnId, style) {
    const btn = document.getElementById(btnId);
    btn.style.background   = style.bg;
    btn.style.color        = style.color;
    btn.style.borderColor  = style.border;
}

function switchTab(role) {
    ['enforcer','moderator','admin'].forEach(r => {
        document.getElementById('panel' + r.charAt(0).toUpperCase() + r.slice(1)).style.display = (r === role) ? '' : 'none';
        applyTabStyle('tab' + r.charAt(0).toUpperCase() + r.slice(1), r === role ? tabStyles[r] : tabInactive);
    });
}

// ── Render a personnel table ─────────────────────────────────────────────────
function renderTable(rows, accentColor) {
    if (!rows.length) {
        return `<div style="padding:32px; text-align:center; color:var(--muted); font-size:13px;">
                    <i class="bi bi-inbox" style="font-size:28px; display:block; margin-bottom:8px;"></i>No accounts found.
                </div>`;
    }

    const ths = `
        <tr>
            <th>#</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Joined</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>`;

    const tds = rows.map((u, i) => {
        const initials = (u.first_name.charAt(0) + u.last_name.charAt(0)).toUpperCase();
        const joined   = u.created_at ? new Date(u.created_at).toLocaleDateString('en-PH', {year:'numeric',month:'short',day:'numeric'}) : '—';
        const active   = u.is_active == 1;
        const statusBadge = active
            ? `<span class="badge-status bs-approved"><i class="bi bi-circle-fill" style="font-size:7px;margin-right:5px;"></i>Active</span>`
            : `<span class="badge-status" style="background:rgba(248,113,113,0.15);color:#f87171;border:1px solid rgba(248,113,113,0.3);"><i class="bi bi-slash-circle me-1"></i>Inactive</span>`;

        return `<tr>
            <td style="color:var(--muted); font-size:12px;">${i + 1}</td>
            <td>
                <div style="display:flex; align-items:center; gap:10px;">
                    <div style="width:32px; height:32px; border-radius:50%; background:${accentColor}22;
                                border:1.5px solid ${accentColor}44; display:flex; align-items:center;
                                justify-content:center; font-size:12px; font-weight:700; color:${accentColor}; flex-shrink:0;">
                        ${initials}
                    </div>
                    <span style="font-weight:600; font-size:13px;">${escHtml(u.first_name)} ${escHtml(u.last_name)}</span>
                </div>
            </td>
            <td style="font-size:12px; color:var(--muted);">${escHtml(u.email)}</td>
            <td style="font-size:12px; color:var(--muted);">${escHtml(u.phone || '—')}</td>
            <td style="font-size:12px; color:var(--muted);">${joined}</td>
            <td>${statusBadge}</td>
            <td>
                <button onclick="confirmPersonnelDeactivate(${u.id}, ${u.is_active == 1 ? 1 : 0}, '${escHtml(u.first_name)} ${escHtml(u.last_name)}')"
                    style="${active
                        ? 'background:rgba(248,113,113,0.15);color:#f87171;border:1px solid rgba(248,113,113,0.3);'
                        : 'background:rgba(74,222,128,0.15);color:#4ade80;border:1px solid rgba(74,222,128,0.3);'}
                    padding:5px 12px; border-radius:7px; font-size:12px; font-weight:600; cursor:pointer; white-space:nowrap;">
                    <i class="bi ${active ? 'bi-person-slash' : 'bi-person-check'}"></i>
                    ${active ? 'Deactivate' : 'Reactivate'}
                </button>
            </td>
        </tr>`;
    }).join('');

    return `<table class="data-table"><thead>${ths}</thead><tbody>${tds}</tbody></table>`;
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Fetch & display personnel ─────────────────────────────────────────────────
async function loadPersonnel(role) {
    const wrapId  = role + 'ListWrap';
    const countId = role + 'Count';
    const accent  = role === 'enforcer' ? '#60a5fa' : role === 'moderator' ? '#a78bfa' : '#fbbf24';

    const wrap  = document.getElementById(wrapId);
    const count = document.getElementById(countId);

    wrap.innerHTML = `<div style="padding:32px; text-align:center; color:var(--muted); font-size:13px;">
        <span class="spinner-border spinner-border-sm me-2" role="status"></span>Loading…
    </div>`;

    try {
        const fd = new FormData();
        fd.append('action', 'get_personnel');
        fd.append('role', role);

        const res  = await fetch('api/admin_action.php', { method: 'POST', body: fd });
        const text = await res.text(); // Read as text first to debug bad responses
        let data;
        try {
            data = JSON.parse(text);
        } catch {
            console.error('Non-JSON response for role=' + role + ':', text);
            throw new Error('Server returned non-JSON response');
        }

        if (data.success) {
            // Handle both data.personnel and data.data.personnel shapes
            const rows = data.personnel ?? data.data?.personnel ?? [];
            count.textContent = rows.length + ' account' + (rows.length !== 1 ? 's' : '');
            wrap.innerHTML    = renderTable(rows, accent);
        } else {
            wrap.innerHTML = `<div style="padding:24px; color:#f87171; font-size:13px;">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>${escHtml(data.message)}
            </div>`;
            count.textContent = '—';
        }
    } catch (err) {
        console.error('loadPersonnel error for role=' + role + ':', err);
        wrap.innerHTML = `<div style="padding:24px; color:#f87171; font-size:13px;">
            <i class="bi bi-wifi-off me-2"></i>Connection error. Check console for details.
        </div>`;
        count.textContent = '—';
    }
}

// ── Generic form submit helper ───────────────────────────────────────────────
function submitPersonnelForm({ formId, btnId, alertBoxId, action, btnLabel, listRole }) {
    const form     = document.getElementById(formId);
    const btn      = document.getElementById(btnId);
    const alertBox = document.getElementById(alertBoxId);

    form.addEventListener('submit', async e => {
        e.preventDefault();
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Creating...';
        alertBox.className = 'alert d-none mb-4';
        alertBox.removeAttribute('style');

        const fd = new FormData(e.target);
        fd.append('action', action);

        try {
            const res  = await fetch('api/admin_action.php', { method: 'POST', body: fd });
            const data = await res.json();

            alertBox.classList.remove('d-none');

            if (data.success) {
                alertBox.classList.add('alert-success');
                alertBox.style.cssText = 'font-size:13px;border-radius:10px;background:rgba(74,222,128,0.15);color:#4ade80;border:1px solid rgba(74,222,128,0.3);';
                alertBox.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i>' + data.message;
                e.target.reset();
                // Refresh the list for this role
                loadPersonnel(listRole);
            } else {
                alertBox.classList.add('alert-danger');
                alertBox.style.cssText = 'font-size:13px;border-radius:10px;background:rgba(248,113,113,0.15);color:#f87171;border:1px solid rgba(248,113,113,0.3);';
                alertBox.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i>' + data.message;
            }
        } catch (err) {
            alertBox.classList.remove('d-none');
            alertBox.classList.add('alert-danger');
            alertBox.style.cssText = 'font-size:13px;border-radius:10px;background:rgba(248,113,113,0.15);color:#f87171;border:1px solid rgba(248,113,113,0.3);';
            alertBox.innerHTML = '<i class="bi bi-wifi-off me-2"></i>Connection error. Please try again.';
        }

        btn.disabled = false;
        btn.innerHTML = btnLabel;
    });
}

// ── Wire up forms ────────────────────────────────────────────────────────────
submitPersonnelForm({
    formId:     'createEnforcerForm',
    btnId:      'submitEnforcerBtn',
    alertBoxId: 'alertBoxEnforcer',
    action:     'create_enforcer',
    listRole:   'enforcer',
    btnLabel:   '<i class="bi bi-person-plus-fill me-2"></i> Create Enforcer Account',
});

submitPersonnelForm({
    formId:     'createModeratorForm',
    btnId:      'submitModeratorBtn',
    alertBoxId: 'alertBoxModerator',
    action:     'create_moderator',
    listRole:   'moderator',
    btnLabel:   '<i class="bi bi-person-plus-fill me-2"></i> Create Moderator Account',
});

// ── Initial load ─────────────────────────────────────────────────────────────
loadPersonnel('enforcer');
loadPersonnel('moderator');
loadPersonnel('admin');

// ── Personnel Deactivate / Reactivate ────────────────────────────────────────
let _personnelTarget = null;

function confirmPersonnelDeactivate(uid, currentActive, name) {
    _personnelTarget = { uid, currentActive };
    const isDeactivating = currentActive === 1;
    document.getElementById('personnelDeactivateTitle').textContent = isDeactivating ? 'Deactivate Account' : 'Reactivate Account';
    document.getElementById('personnelDeactivateBody').innerHTML = isDeactivating
        ? `<p style="font-size:14px;margin-bottom:0;">Are you sure you want to <strong style="color:#f87171;">deactivate</strong> <strong>${name}</strong>?<br>
           <small style="color:var(--muted);font-size:12px;">This account will be disabled and the user will not be able to log in.</small></p>`
        : `<p style="font-size:14px;margin-bottom:0;">Are you sure you want to <strong style="color:#4ade80;">reactivate</strong> <strong>${name}</strong>?<br>
           <small style="color:var(--muted);font-size:12px;">This account will be restored and the user can log in again.</small></p>`;
    const btn = document.getElementById('personnelDeactivateConfirmBtn');
    btn.style.cssText = isDeactivating
        ? 'background:rgba(248,113,113,0.2);color:#f87171;border:1.5px solid rgba(248,113,113,0.4);padding:7px 20px;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;'
        : 'background:rgba(74,222,128,0.2);color:#4ade80;border:1.5px solid rgba(74,222,128,0.4);padding:7px 20px;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;';
    btn.innerHTML = isDeactivating
        ? '<i class="bi bi-person-slash me-1"></i> Yes, Deactivate'
        : '<i class="bi bi-person-check me-1"></i> Yes, Reactivate';
    new bootstrap.Modal(document.getElementById('personnelDeactivateModal')).show();
}

async function doPersonnelDeactivate() {
    if (!_personnelTarget) return;
    const { uid, currentActive } = _personnelTarget;
    const newActive = currentActive === 1 ? 0 : 1;
    const fd = new FormData();
    fd.append('action', 'deactivate_user');
    fd.append('user_id', uid);
    fd.append('active', newActive);
    const res  = await fetch('api/admin_action.php', { method: 'POST', body: fd });
    const data = await res.json();
    bootstrap.Modal.getInstance(document.getElementById('personnelDeactivateModal')).hide();
    if (data.success) {
        // Reload all panels to reflect the change
        loadPersonnel('enforcer');
        loadPersonnel('moderator');
        loadPersonnel('admin');
    } else {
        alert(data.message);
    }
}
</script>

<!-- Personnel Deactivate / Reactivate Modal -->
<div class="modal fade modal-dark" id="personnelDeactivateModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-2">
      <div class="modal-header" style="border-bottom:1px solid var(--muted);">
        <h6 class="modal-title" id="personnelDeactivateTitle">Deactivate Account</h6>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="personnelDeactivateBody" style="padding:20px 16px;"></div>
      <div class="modal-footer" style="border-top:1px solid var(--muted);gap:8px;">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button id="personnelDeactivateConfirmBtn" onclick="doPersonnelDeactivate()"></button>
      </div>
    </div>
  </div>
</div>

</body>
</html>
