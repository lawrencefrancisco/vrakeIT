

function showCustomConfirm(title, text, iconHtml, confirmText, confirmClass, onConfirm) {
  document.getElementById('confirmModalTitle').innerHTML = title;
  document.getElementById('confirmModalText').innerHTML = text;
  document.getElementById('confirmModalIcon').innerHTML = iconHtml;
  
  const btn = document.getElementById('confirmModalBtn');
  btn.innerHTML = confirmText;
  btn.className = 'btn-admin ' + confirmClass;
  
  // Set the shadow color based on the button class
  if (confirmClass.includes('btn-approve') || confirmClass.includes('btn-primary-admin')) {
    btn.style.boxShadow = '0 4px 15px rgba(56,189,248,0.2)';
  } else if (confirmClass.includes('btn-reject')) {
    btn.style.boxShadow = '0 4px 15px rgba(248,113,113,0.2)';
  } else {
    btn.style.boxShadow = 'none';
  }
  
  const modalEl = document.getElementById('actionConfirmModal');
  const modal = new bootstrap.Modal(modalEl);
  
  // Remove old event listeners by cloning
  const newBtn = btn.cloneNode(true);
  btn.parentNode.replaceChild(newBtn, btn);
  
  newBtn.addEventListener('click', () => {
    modal.hide();
    onConfirm();
  });
  
  modal.show();
}

function showCustomAlert(title, text, iconHtml, isSuccess, onClose) {
  document.getElementById('alertModalTitle').innerHTML = title;
  document.getElementById('alertModalText').innerHTML = text;
  document.getElementById('alertModalIcon').innerHTML = iconHtml;
  
  const btn = document.getElementById('alertModalBtn');
  if(isSuccess) {
    document.getElementById('alertModalIcon').style.color = '#10b981';
    btn.style.boxShadow = '0 4px 15px rgba(16,185,129,0.2)';
    btn.className = 'btn-admin btn-approve';
  } else {
    document.getElementById('alertModalIcon').style.color = '#f87171';
    btn.style.boxShadow = '0 4px 15px rgba(248,113,113,0.2)';
    btn.className = 'btn-admin btn-reject';
  }
  
  const modalEl = document.getElementById('alertModal');
  const modal = new bootstrap.Modal(modalEl);
  
  const newBtn = btn.cloneNode(true);
  btn.parentNode.replaceChild(newBtn, btn);
  
  // Also handle modal close event (clicking outside)
  modalEl.addEventListener('hidden.bs.modal', function handler() {
    modalEl.removeEventListener('hidden.bs.modal', handler);
    if(onClose) onClose();
  });
  
  newBtn.addEventListener('click', () => {
    modal.hide(); // this triggers hidden.bs.modal
  });
  
  modal.show();
}

const enforcersList = null;

function getAdminStatusBadge(s) {
  const cfg = {
    pending:   { cls: 'bs-pending',   icon: '<i class="bi bi-clock"></i>',                     label: 'Pending'                       },
    ongoing:   { cls: 'bs-reviewing', icon: '<i class="bi bi-arrow-repeat"></i>',               label: 'Ongoing'                       },
    escalated: { cls: 'bs-rejected',  icon: '<i class="bi bi-exclamation-octagon-fill"></i>',   label: 'Escalated to Higher Department' },
    closed:    { cls: 'bs-closed',    icon: '<i class="bi bi-lock-fill"></i>',                  label: 'Closed'                        },
  };
  const c = cfg[s] || { cls: 'bs-pending', icon: '<i class="bi bi-clock"></i>', label: s || 'N/A' };
  return `<span class="badge-status ${c.cls}" style="font-size:12px;">${c.icon} ${c.label}</span>`;
}

async function adminPost(data) {
  const fd = new FormData();
  for(const k in data) fd.append(k, data[k]);
  const res = await fetch('api/admin_action.php',{method:'POST',body:fd});
  return await res.json();
}

function approveGC(rid, uid) {
  const points = null;
  const msg = `This will:<br>
  <div style="text-align:left; display:inline-block; margin-top:8px;">
    • Grant <span style="color:#fbbf24; font-weight:bold;">+${points} points</span> to the reporter<br>
    • Set status to <span style="color:#10b981;"><i class="bi bi-check-circle-fill"></i> Verified</span>
  </div>`;
  
  showCustomConfirm('Approve Report?', msg, '<i class="bi bi-star-fill" style="color:#fbbf24;"></i>', '<i class="bi bi-check2"></i> Approve', 'btn-approve', async () => {
    const d = await adminPost({action:'approve_gc', report_id:rid, user_id:uid});
    if (d.success) {
      showCustomAlert('Approved!', d.message, '<i class="bi bi-check-circle-fill"></i>', true, () => location.reload());
    } else {
      showCustomAlert('Error', d.message, '<i class="bi bi-x-circle-fill"></i>', false);
    }
  });
}

function updateStatus(rid, status) {
  showCustomConfirm('Update Status?', 'Are you sure you want to change the status?', '<i class="bi bi-arrow-repeat" style="color:#38bdf8;"></i>', 'Update Status', 'btn-primary-admin', async () => {
      const d = await adminPost({action:'update_report_status', report_id:rid, status});
      if (d.success) {
        showCustomAlert('Updated!', 'Status has been updated successfully.', '<i class="bi bi-check-circle-fill"></i>', true, () => location.reload());
      } else {
        showCustomAlert('Error', d.message, '<i class="bi bi-x-circle-fill"></i>', false);
      }
  });
}

function denyReport(rid) {
  showCustomConfirm('Close Report?', 'Are you sure you want to mark this report as closed?', '<i class="bi bi-x-circle-fill" style="color:#f87171;"></i>', '<i class="bi bi-x"></i> Close Report', 'btn-reject', async () => {
    const d = await adminPost({action:'update_report_status',report_id:rid,status:'closed'});
    if(d.success) {
        showCustomAlert('Closed', 'Report has been closed successfully.', '<i class="bi bi-check-circle-fill"></i>', true, () => location.reload());
    } else {
        showCustomAlert('Error', d.message, '<i class="bi bi-x-circle-fill"></i>', false);
    }
  });
}

async function saveAdminManagement(rid) {
  const status = document.getElementById('adminManageStatus').value;
  const enforcerId = document.getElementById('adminManageEnforcer').value;
  const notes = document.getElementById('adminManageNotes').value;
  
  const btn = document.getElementById('btnSaveManage');
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving...';
  btn.disabled = true;
  
  const d = await adminPost({
    action: 'manage_report',
    report_id: rid,
    status: status,
    assigned_enforcer_id: enforcerId,
    admin_notes: notes
  });
  
  if(d.success) {
    alert('<i class="bi bi-check-circle"></i> ' + d.message);
    location.reload();
  } else {
    showCustomAlert('Error', d.message, '<i class="bi bi-x-circle-fill"></i>', false);
    btn.innerHTML = '<i class="bi bi-save"></i> Save Changes';
    btn.disabled = false;
  }
}

async function generateAISummary(rid) {
  const btn = document.getElementById('btnAiSummarize');
  const textDiv = document.getElementById('aiSummaryText');
  
  btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Thinking...';
  btn.disabled = true;
  textDiv.innerHTML = '<span style="color:var(--muted);">Analyzing incident details...</span>';
  
  const d = await adminPost({ action: 'summarize_incident', report_id: rid });
  
  btn.innerHTML = '<i class="bi bi-magic"></i> Summarize';
  btn.disabled = false;
  
  if(d.success) {
    textDiv.innerHTML = `<span style="color:var(--text);">${d.summary}</span>`;
  } else {
    textDiv.innerHTML = `<span style="color:#f87171;">Error: ${d.message}</span>`;
  }
}

let reportModalInstance = null;
async function viewReport(rid) {
  if (!reportModalInstance) {
    reportModalInstance = new bootstrap.Modal(document.getElementById('reportModal'));
  }
  
  const modalBody = document.getElementById('reportModalBody');
  modalBody.innerHTML = '<div class="text-center mt-5"><div class="spinner-border text-primary" role="status"></div></div>';
  reportModalInstance.show();

  const d = await adminPost({action:'get_report', report_id:rid});
  
  // 1. SAFELY GRAB THE REPORT: Handles both common jsonResponse formats
  const r = d.report || (d.data && d.data.report);

  if (!d.success || !r) {
    modalBody.innerHTML = `<div class="p-4 text-center" style="color:#ef4444; font-weight:600;"><i class="bi bi-exclamation-triangle-fill fs-3 d-block mb-2"></i> Failed to load report details.</div>`;
    return  // PREPARE VARIABLES
  // 1. Media
  let mediaHtml = '<span class="text-muted fst-italic">No media provided</span>';
  if (r.media && r.media.length > 0) {
    mediaHtml = '<div style="display:flex; gap:12px; flex-wrap:wrap;">';
    r.media.forEach(m => {
      const imgPath = m.file_path.includes('assets/') ? `../${m.file_path}` : `../assets/uploads/${m.file_path}`;
      if (m.file_type === 'image') {
        mediaHtml += `<a href="${imgPath}" target="_blank" style="display:block; border-radius:8px; overflow:hidden; border:1px solid #e2e8f0; transition:transform 0.2s; box-shadow:0 1px 3px rgba(0,0,0,0.05);"><img src="${imgPath}" style="height:120px; width:120px; object-fit:cover;" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'"></a>`;
      } else if (m.file_type === 'video') {
        mediaHtml += `<video src="${imgPath}" controls style="height:120px; border-radius:8px; border:1px solid #e2e8f0; box-shadow:0 1px 3px rgba(0,0,0,0.05);"></video>`;
      }
    });
    mediaHtml += '</div>';
  }

  // 2. Vehicles
  let vehiclesHtml = '<span class="text-muted fst-italic">N/A</span>';
  if (r.vehicles && r.vehicles.length > 0) {
    vehiclesHtml = '<div style="display:flex; gap:8px; flex-wrap:wrap;">';
    r.vehicles.forEach(v => {
      vehiclesHtml += `<div style="background:#f1f5f9; border:1px solid #e2e8f0; padding:6px 12px; border-radius:6px; display:flex; align-items:center; gap:8px; box-shadow:0 1px 2px rgba(0,0,0,0.02);">
        <span style="color:#334155; font-weight:600; font-size:13px;">${v.vehicle_type}</span>
        <span style="color:#cbd5e1;">|</span>
        <span style="font-family:'Courier New', monospace; font-weight:700; color:#0ea5e9; font-size:13px;">${v.plate_number || 'NO PLATE'}</span>
        ${v.vehicle_count > 1 ? `<span style="background:#e0f2fe; color:#0284c7; font-size:11px; font-weight:700; padding:2px 6px; border-radius:4px;">x${v.vehicle_count}</span>` : ''}
      </div>`;
    });
    vehiclesHtml += '</div>';
  }

  // 3. Emergency Services
  let emergencySvcs = '<span class="text-muted fst-italic">N/A</span>';
  if (r.emergency_services && r.emergency_services !== 'null') {
     try {
         let parsed = JSON.parse(r.emergency_services);
         if (Array.isArray(parsed)) emergencySvcs = parsed.map(s => `<span style="background:#fee2e2; border:1px solid #fca5a5; color:#ef4444; padding:3px 8px; border-radius:6px; font-size:12px; font-weight:600; display:inline-flex; align-items:center; gap:4px;"><i class="bi bi-truck-front-fill"></i> ${s.toUpperCase()}</span>`).join(' ');
     } catch(e) { 
         emergencySvcs = `<span style="color:#334155; font-weight:600;">${r.emergency_services}</span>`; 
     }
  }

  // 4. Details
  const descText = r.event_details || r.damage_category || '';
  const descHtml = descText ? descText.replace(/\n/g, '<br>') : '<span class="text-muted fst-italic">No details provided</span>';

  // 5. Badges
  const roleBadge = r.reporter_role === 'citizen'
    ? `<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:6px;background:#ede9fe;border:1px solid #ddd6fe;color:#8b5cf6;font-size:11px;font-weight:600;"><i class='bi bi-eye-fill'></i> Citizen/Witness</span>`
    : `<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:6px;background:#e0f2fe;border:1px solid #bae6fd;color:#0ea5e9;font-size:11px;font-weight:600;"><i class='bi bi-car-front-fill'></i> Driver</span>`;

  const flowBadge = `<span style="display:inline-block; padding:3px 8px; border-radius:6px; background:#f1f5f9; border:1px solid #e2e8f0; color:#475569; font-size:11px; font-weight:700; letter-spacing:0.5px;">${(r.flow_type || '').replace('_', ' ').toUpperCase()}</span>`;

  // BUILD HTML
  modalBody.innerHTML = `
    <div class="row g-4 pb-2" style="color: #334155;">
      
      <!-- AI Summary Banner -->
      <div class="col-12">
        <div id="aiSummaryContainer" style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:12px; padding:16px; display:flex; align-items:center; gap:16px; box-shadow:0 2px 8px rgba(14,165,233,0.05);">
          <div style="flex-shrink:0; font-size:28px; color:#0ea5e9; background:#ffffff; width:56px; height:56px; display:flex; align-items:center; justify-content:center; border-radius:12px; border:1px solid #bae6fd; box-shadow:0 2px 4px rgba(0,0,0,0.02);"><i class="bi bi-robot"></i></div>
          <div style="flex-grow:1;">
            <div style="font-size:11px; font-weight:800; color:#0284c7; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px;">AI Incident Summary</div>
            <div id="aiSummaryText" style="font-size:13px; color:#475569; line-height:1.4; font-weight:500;">Click the button to generate an intelligent 1-sentence summary of this report.</div>
          </div>
          <div>
            <button id="btnAiSummarize" class="btn btn-sm btn-primary" onclick="generateAISummary(${r.id})" style="white-space:nowrap; border-radius:8px; padding:8px 16px; font-weight:600; box-shadow:0 2px 4px rgba(14,165,233,0.2);">
              <i class="bi bi-stars me-1"></i> Summarize
            </button>
          </div>
        </div>
      </div>

      <!-- Reporter Card -->
      <div class="col-12">
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px; display:flex; align-items:center; gap:16px;">
          <div style="position:relative; flex-shrink:0;">
            <img src="${r.avatar_url}" alt="${r.reporter_name}"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"
                 style="width:56px; height:56px; border-radius:10px; object-fit:cover; border:1px solid #cbd5e1; box-shadow:0 2px 5px rgba(0,0,0,0.05);">
            <div style="display:none; width:56px; height:56px; border-radius:10px; background:#e2e8f0; border:1px solid #cbd5e1; align-items:center; justify-content:center; font-weight:800; font-size:18px; color:#475569;">
              ${(r.reporter_name||'?').split(' ').map(n=>n[0]).join('').slice(0,2).toUpperCase()}
            </div>
          </div>
          <div style="flex-grow:1;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px;">
              <div>
                <p style="margin:0 0 2px 0; color:#64748b; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.5px;">Reported By</p>
                <h5 style="margin:0 0 4px 0; color:#0f172a; font-weight:700; display:flex; align-items:center; gap:8px;">
                  ${r.reporter_name}
                  ${r.account_verified == 1 ? '<span style="background:#dcfce7;color:#16a34a;border:1px solid #bbf7d0;padding:2px 6px;border-radius:12px;font-size:10px;font-weight:700;"><i class="bi bi-shield-check"></i> Verified</span>' : ''}
                  ${r.assigned_enforcer_id !== null ? '<span style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;padding:2px 6px;border-radius:12px;font-size:10px;font-weight:700;"><i class="bi bi-person-badge"></i> Enforcer</span>' : ''}
                </h5>
                <div style="font-size:12px; color:#64748b; font-weight:500;">
                  <i class="bi bi-envelope me-1"></i> ${r.email}
                  ${r.phone ? '<span style="margin:0 6px;color:#cbd5e1;">|</span><i class="bi bi-telephone me-1"></i> ' + r.phone : ''}
                </div>
              </div>
              <div style="text-align:right;">
                <p style="margin:0 0 4px 0; color:#64748b; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.5px;">Ref #</p>
                <div style="font-family:'Courier New', monospace; font-weight:700; color:#0ea5e9; font-size:14px; background:#f0f9ff; padding:2px 8px; border-radius:6px; border:1px solid #bae6fd;">${r.reference_number}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Incident Details Header -->
      <div class="col-12">
        <div style="display:flex; align-items:center; gap:12px; border-bottom:1px solid #e2e8f0; padding-bottom:8px; margin-top:8px;">
          <h6 style="color:#0f172a; font-weight:700; margin:0;"><i class="bi bi-journal-text me-2 text-primary"></i>Incident Details</h6>
          <div style="display:flex; gap:6px;">
            ${getAdminStatusBadge(r.status)}
            ${flowBadge}
            ${roleBadge}
          </div>
        </div>
      </div>

      <!-- Detail Grid -->
      <div class="col-md-6">
        <p style="margin:0 0 2px 0; color:#64748b; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Date & Time</p>
        <div style="color:#0f172a; font-weight:600; font-size:13px; display:flex; align-items:center; gap:6px;">
          <i class="bi bi-calendar-event text-primary"></i>
          ${r.incident_date ? r.incident_date + ' ' + (r.incident_time||'') : '<span class="text-muted fst-italic">Not provided</span>'}
          <span style="font-size:11px; color:#94a3b8; font-weight:500; margin-left:4px;">(Reported: ${r.created_at_fmt})</span>
        </div>
      </div>
      
      <div class="col-md-6">
        <p style="margin:0 0 2px 0; color:#64748b; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Location</p>
        <div style="color:#0f172a; font-weight:600; font-size:13px; display:flex; align-items:start; gap:6px; line-height:1.4;">
          <i class="bi bi-geo-alt-fill text-danger mt-1"></i>
          <div>${r.location_address || '<span class="text-muted fst-italic">Not provided</span>'}</div>
        </div>
      </div>

      <div class="col-md-3 col-6">
        <p style="margin:0 0 2px 0; color:#64748b; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Weather</p>
        <div style="color:#0f172a; font-weight:600; font-size:13px;">${r.weather_condition || 'N/A'}</div>
      </div>
      <div class="col-md-3 col-6">
        <p style="margin:0 0 2px 0; color:#64748b; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Road Cond.</p>
        <div style="color:#0f172a; font-weight:600; font-size:13px;">${r.road_condition || 'N/A'}</div>
      </div>
      <div class="col-md-3 col-6">
        <p style="margin:0 0 2px 0; color:#64748b; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Insurance</p>
        <div style="color:#0f172a; font-weight:600; font-size:13px;">${r.insurance_type ? r.insurance_type.toUpperCase() : 'N/A'}</div>
      </div>
      <div class="col-md-3 col-6">
        <p style="margin:0 0 2px 0; color:#64748b; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Other Parties</p>
        <div style="color:#0f172a; font-weight:600; font-size:13px;">
          ${r.has_other_parties == 1 
            ? '<span style="color:#ef4444; font-weight:700;">Yes</span>' 
            : '<span style="color:#64748b;">No</span>'}
        </div>
      </div>

      <!-- Injury Row -->
      <div class="col-md-4">
        <p style="margin:0 0 2px 0; color:#64748b; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Injuries?</p>
        ${r.is_injured == 1 
          ? '<span style="background:#fee2e2; border:1px solid #fca5a5; color:#ef4444; padding:2px 8px; border-radius:6px; font-size:12px; font-weight:700;"><i class="bi bi-exclamation-circle-fill me-1"></i> YES</span>' 
          : '<span style="background:#f1f5f9; border:1px solid #e2e8f0; color:#64748b; padding:2px 8px; border-radius:6px; font-size:12px; font-weight:600;">NO</span>'}
      </div>
      
      ${r.injury_severity ? `
      <div class="col-md-4">
        <p style="margin:0 0 2px 0; color:#64748b; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Severity</p>
        ${r.injury_severity === 'minor'
          ? '<span style="background:#fef3c7; border:1px solid #fde68a; color:#d97706; padding:2px 8px; border-radius:6px; font-size:12px; font-weight:700;"><i class="bi bi-bandaid-fill me-1"></i> Minor</span>'
          : '<span style="background:#fee2e2; border:1px solid #fca5a5; color:#ef4444; padding:2px 8px; border-radius:6px; font-size:12px; font-weight:700;"><i class="bi bi-heartbreak-fill me-1"></i> Major</span>'}
      </div>` : ''}

      ${r.has_deceased !== null && r.has_deceased !== undefined && r.injury_severity ? `
      <div class="col-md-4">
        <p style="margin:0 0 2px 0; color:#64748b; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Fatalities</p>
        ${r.has_deceased == 1
          ? '<span style="background:#fee2e2; border:1px solid #fca5a5; color:#ef4444; padding:2px 8px; border-radius:6px; font-size:12px; font-weight:700;"><i class="bi bi-x-octagon-fill me-1"></i> Deceased</span>'
          : '<span style="background:#dcfce7; border:1px solid #bbf7d0; color:#16a34a; padding:2px 8px; border-radius:6px; font-size:12px; font-weight:700;"><i class="bi bi-check-circle-fill me-1"></i> Alive</span>'}
      </div>` : ''}

      <!-- Enforcer & Emergency -->
      <div class="col-md-6 mt-3">
        <p style="margin:0 0 2px 0; color:#64748b; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Law Enforcer / Auth</p>
        <div style="color:#0f172a; font-weight:600; font-size:13px;">
          ${r.enforcer_type ? `<span style="background:#e0f2fe; border:1px solid #bae6fd; color:#0284c7; padding:2px 8px; border-radius:6px; font-size:12px; font-weight:700;"><i class="bi bi-shield-shaded me-1"></i> ${r.enforcer_type.toUpperCase()}</span>` : '<span class="text-muted fst-italic">N/A</span>'}
        </div>
      </div>
      <div class="col-md-6 mt-3">
        <p style="margin:0 0 2px 0; color:#64748b; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Emergency Services</p>
        <div>${emergencySvcs}</div>
      </div>

      <!-- Long Text Content (White Cards) -->
      <div class="col-12 mt-4">
        <p style="margin:0 0 6px 0; color:#64748b; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;"><i class="bi bi-car-front-fill me-1"></i> Vehicles Involved</p>
        <div style="background:#ffffff; padding:12px; border-radius:8px; border:1px solid #e2e8f0; box-shadow:0 1px 2px rgba(0,0,0,0.02);">
          ${vehiclesHtml}
        </div>
      </div>

      <div class="col-12 mt-3">
        <p style="margin:0 0 6px 0; color:#64748b; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;"><i class="bi bi-card-text me-1"></i> Event Details</p>
        <div style="background:#ffffff; padding:16px; border-radius:8px; border:1px solid #e2e8f0; font-size:13px; line-height:1.6; color:#1e293b; font-weight:500; box-shadow:0 1px 2px rgba(0,0,0,0.02);">
          ${descHtml}
        </div>
      </div>

      <div class="col-12 mt-3">
        <p style="margin:0 0 6px 0; color:#64748b; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;"><i class="bi bi-images me-1"></i> Media / Evidence</p>
        <div style="background:#ffffff; padding:16px; border-radius:8px; border:1px solid #e2e8f0; box-shadow:0 1px 2px rgba(0,0,0,0.02);">
          ${mediaHtml}
        </div>
      </div>
    </div>
  `;
}
