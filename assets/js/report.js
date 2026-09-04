// ─── VrakeIT Report Wizard JS ───────────────────────────────────────────────
// State object
const reportData = {
  flow_type: '',
  is_injured: 0,
  enforcer_type: '',
  enforcer_documented: '',
  emergency_services: [],
  is_safe: null,
  incident_date: '',
  incident_time: '',
  location_lat: null,
  location_lng: null,
  location_address: '',
  has_other_parties: 0,
  other_parties_present: null,
  vehicle_types: [],
  vehicle_counts: [],
  plate_numbers: [],
  weather_condition: '',
  road_condition: '',
  insurance_type: '',
  event_details: '',
  damage_category: '',
  gc_description: '',
};

const stepHistory = ['step-s1'];

const steps = {
  total: { 'step-s1': 1, 'step-a1': 2, 'step-a2': 3, 'step-a3': 4, 'step-b-reassure': 2, 'step-b1': 3, 'step-b-notsafe': 3, 'step-b2': 4, 'step-b3': 5, 'step-b4': 6, 'step-gc1': 7, 'step-gc2': 8, 'step-gc3': 9, 'step-gc-overview': 10, 'step-b5': 7, 'step-b6': 8, 'step-b7': 9, 'step-b8': 10, 'step-b9': 11, 'step-b10': 12, 'step-b11': 13, 'step-overview': 14 },
  max: 14,
};

function showStep(id) {
  document.querySelectorAll('.wizard-step').forEach(s => s.classList.remove('active'));
  const el = document.getElementById(id);
  if (el) { el.classList.add('active'); el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
  const num = steps.total[id] || 1;
  document.getElementById('progressBar').style.width = Math.round((num / steps.max) * 100) + '%';
  document.getElementById('stepLabel').textContent = `Step ${num} of ${steps.max}`;
}

function goToStep(id) {
  stepHistory.push(id);
  showStep(id);
  if (id === 'step-b3') initMap();
}

function goBack() {
  if (stepHistory.length > 1) {
    stepHistory.pop();
    showStep(stepHistory[stepHistory.length - 1]);
  }
}

// ─── STEP S1: Injured? ───────────────────────────────────────────────────────
function chooseInjured(injured) {
  reportData.is_injured = injured ? 1 : 0;
  if (injured) {
    reportData.flow_type = 'first';
    // Show emergency hotline prompt before proceeding
    showEmergencyHotlineModal(() => {
      goToStep('step-a1');
      document.getElementById('flowLabel').textContent = 'First Flow';
    });
  } else {
    reportData.flow_type = 'second';
    goToStep('step-b-reassure');
    document.getElementById('flowLabel').textContent = 'Second Flow';
  }
}

// ─── EMERGENCY HOTLINE MODAL ─────────────────────────────────────────────────
function showEmergencyHotlineModal(onContinue) {
  const existing = document.getElementById('emergencyHotlineModal');
  if (existing) existing.remove();
  
  document.body.insertAdjacentHTML('beforeend', `
  <div id="emergencyHotlineModal" style="position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:1200;display:flex;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(6px);">
    <div style="background:#fff;border-radius:24px;padding:32px 24px;max-width:380px;width:100%;text-align:center;animation:slideUp .3s ease;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
      <div style="width:80px;height:80px;background:linear-gradient(135deg,#E90101,#b30000);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:38px;animation:pulse-red 1.5s infinite;">🚨</div>
      <h3 style="font-size:20px;font-weight:800;margin-bottom:6px;color:#1f2937;">Do you need emergency services?</h3>
      <p style="font-size:13px;color:#6b7280;margin-bottom:20px;line-height:1.5;">If there is immediate danger or someone requires urgent medical attention, please call emergency services <strong>right now</strong> before filing your report.</p>
      
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;">
        <a href="tel:911" style="display:flex;flex-direction:column;align-items:center;gap:6px;background:linear-gradient(135deg,#E90101,#b30000);color:#fff;border-radius:16px;padding:16px 12px;text-decoration:none;font-weight:700;font-size:13px;box-shadow:0 4px 15px rgba(233,1,1,0.35);">
          <span style="font-size:24px;">📞</span>
          <span>Call 911</span>
          <span style="font-size:10px;opacity:0.85;font-weight:500;">Emergency Hotline</span>
        </a>
        <a href="tel:117" style="display:flex;flex-direction:column;align-items:center;gap:6px;background:linear-gradient(135deg,#007ED2,#005fa3);color:#fff;border-radius:16px;padding:16px 12px;text-decoration:none;font-weight:700;font-size:13px;box-shadow:0 4px 15px rgba(0,126,210,0.35);">
          <span style="font-size:24px;">🚑</span>
          <span>Call 117</span>
          <span style="font-size:10px;opacity:0.85;font-weight:500;">Philippine Red Cross</span>
        </a>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px;">
        <a href="tel:0284264925" style="display:flex;flex-direction:column;align-items:center;gap:6px;background:#f59e0b;color:#fff;border-radius:16px;padding:12px;text-decoration:none;font-weight:700;font-size:12px;box-shadow:0 4px 12px rgba(245,158,11,0.3);">
          <span style="font-size:20px;">🚒</span>
          <span>BFP Hotline</span>
        </a>
        <a href="tel:722-0650" style="display:flex;flex-direction:column;align-items:center;gap:6px;background:#10b981;color:#fff;border-radius:16px;padding:12px;text-decoration:none;font-weight:700;font-size:12px;box-shadow:0 4px 12px rgba(16,185,129,0.3);">
          <span style="font-size:20px;">🚔</span>
          <span>PNP Hotline</span>
        </a>
      </div>
      
      <button onclick="dismissEmergencyModal()" style="width:100%;background:#f3f4f6;color:#374151;border:none;border-radius:14px;padding:14px;font-family:Poppins,sans-serif;font-size:14px;font-weight:600;cursor:pointer;transition:background 0.2s;">
        I'm okay — Continue Filing Report
      </button>
      <p style="font-size:11px;color:#9ca3af;margin-top:10px;margin-bottom:0;">Your report is still important. Please stay safe.</p>
    </div>
  </div>
  <style>
    @keyframes slideUp { from { opacity:0; transform:translateY(30px); } to { opacity:1; transform:translateY(0); } }
    @keyframes pulse-red { 0%,100% { box-shadow:0 0 0 0 rgba(233,1,1,0.4); } 70% { box-shadow:0 0 0 15px rgba(233,1,1,0); } }
  </style>`);
  
  window._emergencyOnContinue = onContinue;
}

function dismissEmergencyModal() {
  const modal = document.getElementById('emergencyHotlineModal');
  if (modal) {
    modal.style.opacity = '0';
    modal.style.transition = 'opacity 0.2s';
    setTimeout(() => { modal.remove(); }, 200);
  }
  if (typeof window._emergencyOnContinue === 'function') {
    window._emergencyOnContinue();
    window._emergencyOnContinue = null;
  }
}

// ─── FIRST FLOW ──────────────────────────────────────────────────────────────
function selectEnforcer(val, label, btn) {
  document.querySelectorAll('#step-a1 .choice-btn').forEach(b => b.classList.remove('selected'));
  btn.classList.add('selected');
  reportData.enforcer_type = val;
  setTimeout(() => goToStep('step-a2'), 250);
}

function setEnforcerDoc(val, btn) {
  document.querySelectorAll('#step-a2 .choice-btn').forEach(b => b.classList.remove('selected'));
  btn.classList.add('selected');
  reportData.enforcer_documented = val;
  setTimeout(() => goToStep('step-a3'), 250);
}

function toggleEmergency(btn) {
  btn.classList.toggle('active');
  const svc = btn.dataset.service;
  if (btn.classList.contains('active')) {
    if (!reportData.emergency_services.includes(svc)) reportData.emergency_services.push(svc);
  } else {
    reportData.emergency_services = reportData.emergency_services.filter(s => s !== svc);
  }
}

// ─── SECOND FLOW ─────────────────────────────────────────────────────────────
function chooseSafe(safe) {
  reportData.is_safe = safe ? 1 : 0;
  goToStep(safe ? 'step-b2' : 'step-b-notsafe');
}

function saveDatetime() {
  const d = document.getElementById('incidentDate').value;
  const t = document.getElementById('incidentTime').value;
  if (!d || !t) { alert('Please set both date and time.'); return; }
  reportData.incident_date = d;
  reportData.incident_time = t;
  goToStep('step-b3');
}

function saveLocation() {
  if (!reportData.location_lat) { alert('Please wait for location detection or allow GPS access.'); return; }
  goToStep('step-b4');
}

function chooseOtherParties(has) {
  reportData.has_other_parties = has ? 1 : 0;
  if (has) {
    goToStep('step-b5');
  } else {
    reportData.flow_type = 'good_citizen';
    goToStep('step-gc1');
    document.getElementById('flowLabel').textContent = 'Good Citizen';
  }
}

function saveVehicleTypes() {
  const selected = [...document.querySelectorAll('.vehicle-chip.active')].map(c => c.dataset.vehicle);
  if (!selected.length) { alert('Please select at least one vehicle type.'); return; }
  reportData.vehicle_types = selected;
  buildVehicleCountForms(selected);
  goToStep('step-b7');
}

function buildVehicleCountForms(types) {
  const container = document.getElementById('vehicleCountForms');
  container.innerHTML = types.map((t, i) => `
    <div style="background:#f8f8f8;border-radius:12px;padding:14px;margin-bottom:12px;">
      <div style="font-size:14px;font-weight:600;margin-bottom:10px;">${t}</div>
      <div class="row g-2">
        <div class="col-5">
          <label style="font-size:12px;color:var(--muted);">Count</label>
          <input type="number" class="form-control" id="vc_count_${i}" min="1" max="20" value="1" style="border-radius:10px;border:2px solid #e0e0e0;">
        </div>
        <div class="col-7">
          <label style="font-size:12px;color:var(--muted);">Plate Number(s)</label>
          <input type="text" class="form-control" id="vc_plate_${i}" placeholder="e.g. ABC 1234" style="border-radius:10px;border:2px solid #e0e0e0;">
        </div>
      </div>
    </div>
  `).join('');
}

function saveVehicleDetails() {
  reportData.vehicle_counts = [];
  reportData.plate_numbers = [];
  reportData.vehicle_types.forEach((_, i) => {
    reportData.vehicle_counts.push(document.getElementById(`vc_count_${i}`)?.value || '1');
    reportData.plate_numbers.push(document.getElementById(`vc_plate_${i}`)?.value || '');
  });
  goToStep('step-b8');
}

function toggleVehicle(btn) {
  btn.classList.toggle('active');
}

function saveWeather() {
  const w = document.getElementById('weatherCond').value;
  const r = document.getElementById('roadCond').value;
  if (!w || !r) { alert('Please select weather and road condition.'); return; }
  reportData.weather_condition = w;
  reportData.road_condition = r;
  goToStep('step-b9');
}

function selectInsurance(val, btn) {
  document.querySelectorAll('#step-b9 .choice-btn').forEach(b => b.classList.remove('selected'));
  btn.classList.add('selected');
  reportData.insurance_type = val;
  setTimeout(() => goToStep('step-b10'), 250);
}



function selectGCCategory(val, label, btn) {
  document.querySelectorAll('#step-gc1 .choice-btn').forEach(b => b.classList.remove('selected'));
  btn.classList.add('selected');
  reportData.damage_category = val;
  setTimeout(() => goToStep('step-gc2'), 250);
}

// --- GOOD CITIZEN MEDIA ---
function previewGCMedia(input) {
    if (!input.files) return;
    
    for (let i = 0; i < input.files.length; i++) {
        gcFiles.push(input.files[i]);
    }
    
    const container = document.getElementById('gcMediaPreview');
    const ovGcPhotos = document.getElementById('ov-gc-photos'); // Overview container
    container.innerHTML = '';
    ovGcPhotos.innerHTML = '';

    gcFiles.forEach((file, index) => {
        const url = URL.createObjectURL(file);
        
        // Build preview for Step 2
        const img = document.createElement('img');
        img.src = url;
        img.style.width = '60px';
        img.style.height = '60px';
        img.style.objectFit = 'cover';
        img.style.borderRadius = '8px';
        img.style.cursor = 'pointer';
        img.onclick = function() { viewFullImage(this.src); }; // Enlarge on click
        container.appendChild(img);

        // Build preview for Overview Step
        const ovImg = document.createElement('img');
        ovImg.src = url;
        ovImg.style.width = '45px';
        ovImg.style.height = '45px';
        ovImg.style.objectFit = 'cover';
        ovImg.style.borderRadius = '6px';
        ovImg.style.cursor = 'pointer';
        ovImg.onclick = function() { viewFullImage(this.src); }; // Enlarge on click
        ovGcPhotos.appendChild(ovImg);
    });
}

// IMPORTANT: When submitting your form data via fetch(), 
// you must append 'standardFiles' or 'gcFiles' to your FormData instead of the input element directly.
// Example: standardFiles.forEach(file => formData.append('media[]', file));

function goToGCOverview() {
  const desc = document.getElementById('gcDescription').value.trim();
  if (!desc) { alert('Please describe the event.'); return; }
  reportData.gc_description = desc;
  document.getElementById('ov-gc-cat').textContent = reportData.damage_category.replace(/_/g, ' ');
  document.getElementById('ov-gc-desc').textContent = desc.substring(0, 80) + (desc.length > 80 ? '…' : '');
  goToStep('step-gc-overview');

  const gcPreview = document.getElementById('gcMediaPreview');
const ovGcPhotos = document.getElementById('ov-gc-photos');

if (gcPreview && gcPreview.children.length > 0) {
    ovGcPhotos.innerHTML = gcPreview.innerHTML;
    ovGcPhotos.querySelectorAll('img').forEach(img => {
        img.style.width = '45px';
        img.style.height = '45px';
        img.style.objectFit = 'cover';
        img.style.borderRadius = '6px';
        img.style.cursor = 'pointer'; // Make it look clickable
        
        // Add the click event to enlarge
        img.onclick = function() { viewFullImage(this.src); };
    });
} else {
    ovGcPhotos.textContent = 'None';
}
}

async function submitGoodCitizen() {
  const btn = document.getElementById('submitGCBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="loading-spinner"></span> Submitting...';
  
  const fd = buildFormData('good_citizen');
 // --- THE FIX ---
  // Map the Good Citizen description to 'event_details' so PHP catches it!
  // We use fd.set() to overwrite the empty value created by buildFormData()
  fd.set('event_details', reportData.gc_description);
  
  try {
    const res = await fetch('api/submit_report.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      showSuccessModal(data.reference_number, data.points_earned || 0, data.total_points || 0);
      
      // Wipe the memory clean
      standardFiles = [];
      gcFiles = [];
      
      if (document.getElementById('mediaPreview')) document.getElementById('mediaPreview').innerHTML = '';
      if (document.getElementById('gcMediaPreview')) document.getElementById('gcMediaPreview').innerHTML = '';

    } else {
      alert('Error: ' + data.message);
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-send-fill me-1"></i> Submit Report';
    }
  } catch { 
    alert('Connection error.'); 
    btn.disabled = false; 
    btn.innerHTML = '<i class="bi bi-send-fill me-1"></i> Submit Report'; 
  }
}

// ─── OVERVIEW ────────────────────────────────────────────────────────────────
function goToOverview(flow) {
  if (flow === 'second') {
    reportData.event_details = document.getElementById('eventDetails').value.trim();
    if (!reportData.event_details) { alert('Please describe the event.'); return; }
  }
  document.getElementById('ov-type').textContent = flow === 'first' ? 'With Injury / Law Enforcer' : 'No Injury — Full Report';
  document.getElementById('ov-injured').textContent = reportData.is_injured ? 'Yes' : 'No';
  document.getElementById('ov-datetime').textContent = reportData.incident_date
    ? `${reportData.incident_date} ${reportData.incident_time}` : '—';
  document.getElementById('ov-location').textContent = reportData.location_address || '—';
  document.getElementById('ov-parties').textContent = reportData.has_other_parties ? 'Yes' : 'No';
  document.getElementById('ov-weather').textContent = reportData.weather_condition || '—';
  document.getElementById('ov-road').textContent = reportData.road_condition || '—';
  document.getElementById('ov-details').textContent = reportData.event_details || '—';
  document.getElementById('ov-insurance').textContent = reportData.insurance_type || '—';
  goToStep('step-overview');

  const mediaPreview = document.getElementById('mediaPreview');
const ovPhotos = document.getElementById('ov-photos');

if (mediaPreview && mediaPreview.children.length > 0) {
    ovPhotos.innerHTML = mediaPreview.innerHTML;
    ovPhotos.querySelectorAll('img, video').forEach(media => {
        media.style.width = '45px';
        media.style.height = '45px';
        media.style.objectFit = 'cover';
        media.style.borderRadius = '6px';
        media.style.cursor = 'pointer'; // Make it look clickable
        
        // Add the click event to enlarge
        if(media.tagName.toLowerCase() === 'img') {
            media.onclick = function() { viewFullImage(this.src); };
        }
    });
} else {
    ovPhotos.textContent = 'None';
}
}

// Arrays to hold files cumulatively
let standardFiles = [];
let gcFiles = [];

// --- STANDARD REPORT MEDIA ---
function previewMedia(input) {
    if (!input.files) return;
    
    // Add new files to our array
    for (let i = 0; i < input.files.length; i++) {
        standardFiles.push(input.files[i]);
    }
    
    const container = document.getElementById('mediaPreview');
    const ovPhotos = document.getElementById('ov-photos'); // Overview container
    container.innerHTML = '';
    ovPhotos.innerHTML = '';

    standardFiles.forEach((file, index) => {
        const url = URL.createObjectURL(file);
        
        // Build preview for Step 10
        const img = document.createElement('img');
        img.src = url;
        img.style.width = '60px';
        img.style.height = '60px';
        img.style.objectFit = 'cover';
        img.style.borderRadius = '8px';
        img.style.cursor = 'pointer';
        img.onclick = function() { viewFullImage(this.src); }; // Enlarge on click
        container.appendChild(img);

        // Build preview for Overview Step
        const ovImg = document.createElement('img');
        ovImg.src = url;
        ovImg.style.width = '45px';
        ovImg.style.height = '45px';
        ovImg.style.objectFit = 'cover';
        ovImg.style.borderRadius = '6px';
        ovImg.style.cursor = 'pointer';
        ovImg.onclick = function() { viewFullImage(this.src); }; // Enlarge on click
        ovPhotos.appendChild(ovImg);
    });
}

function buildFormData(flowOverride = null) {
  const fd = new FormData();
  const currentFlow = flowOverride || reportData.flow_type; // Capture the current flow
  
  fd.append('flow_type', currentFlow);
  fd.append('is_injured', reportData.is_injured);
  fd.append('enforcer_type', reportData.enforcer_type);
  fd.append('enforcer_documented', reportData.enforcer_documented);
  reportData.emergency_services.forEach(s => fd.append('emergency_services[]', s));
  if (reportData.is_safe !== null) fd.append('is_safe', reportData.is_safe);
  fd.append('incident_date', reportData.incident_date);
  fd.append('incident_time', reportData.incident_time);
  fd.append('location_lat', reportData.location_lat || '');
  fd.append('location_lng', reportData.location_lng || '');
  fd.append('location_address', reportData.location_address);
  fd.append('has_other_parties', reportData.has_other_parties);
  if (reportData.other_parties_present !== null) fd.append('other_parties_present', reportData.other_parties_present);
  reportData.vehicle_types.forEach((v, i) => {
    fd.append('vehicle_types[]', v);
    fd.append('vehicle_counts[]', reportData.vehicle_counts[i] || 1);
    fd.append('plate_numbers[]', reportData.plate_numbers[i] || '');
  });
  fd.append('weather_condition', reportData.weather_condition);
  fd.append('road_condition', reportData.road_condition);
  fd.append('insurance_type', reportData.insurance_type);
  fd.append('event_details', reportData.event_details);
  fd.append('damage_category', reportData.damage_category);

  // --- THE FIX: ONLY ADD PHOTOS FOR THE CURRENT FLOW ---
  
  if (currentFlow === 'good_citizen') {
      // 1. If it's a Good Citizen report, ONLY append gcFiles
      if (typeof gcFiles !== 'undefined' && gcFiles.length > 0) {
          gcFiles.forEach((file) => {
              fd.append('media[]', file); 
          });
      }
  } else {
      // 2. Otherwise, ONLY append standardFiles
      if (typeof standardFiles !== 'undefined' && standardFiles.length > 0) {
          standardFiles.forEach((file) => {
              fd.append('media[]', file); 
          });
      }
  }

  return fd;
}

async function submitReport() {
  const btn = document.getElementById('submitReportBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="loading-spinner"></span> Submitting...';
  
  // buildFormData() already attaches standardFiles and gcFiles
  const fd = buildFormData(); 
  
  try {
    const res = await fetch('api/submit_report.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      showSuccessModal(data.reference_number, 0, 0);
    } else {
      alert('Error: ' + data.message);
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-send-fill me-1"></i> Submit Report';
    }
  } catch { 
    alert('Connection error.'); 
    btn.disabled = false; 
    btn.innerHTML = '<i class="bi bi-send-fill me-1"></i> Submit Report'; 
  }
}

function showSuccessModal(refNum, ptsEarned, totalPts) {
  const isGC = ptsEarned > 0;
  document.body.insertAdjacentHTML('beforeend', `
  <div style="position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:999;display:flex;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:24px;padding:32px 24px;max-width:360px;width:100%;text-align:center;animation:fadeIn .3s ease;">
      <div style="width:80px;height:80px;background:linear-gradient(135deg,#00c853,#009624);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:40px;">✅</div>
      <h3 style="font-size:22px;font-weight:800;margin-bottom:8px;">Report Submitted!</h3>
      <div style="background:#f5f5f5;border-radius:12px;padding:12px;margin-bottom:14px;">
        <div style="font-size:12px;color:#888;">Reference Number</div>
        <div style="font-size:18px;font-weight:700;color:var(--red);">${refNum}</div>
      </div>
      ${isGC ? `<div style="background:#e8f4ff;border-radius:12px;padding:12px;margin-bottom:14px;">
        <i class="bi bi-star-fill" style="color:#007ED2;font-size:22px;"></i>
        <div style="font-size:18px;font-weight:800;color:#007ED2;margin:4px 0;">You earned ${ptsEarned} pts!</div>
        <div style="font-size:13px;color:#555;">Total: ${totalPts} points</div>
      </div>` : ''}
      <div style="display:flex;gap:10px;">
        <a href="track.php" style="flex:1;background:#f0f0f0;color:#333;border-radius:12px;padding:12px;text-decoration:none;font-weight:600;font-size:14px;">Track Report</a>
        ${isGC ? `<a href="good_citizen.php" style="flex:1;background:linear-gradient(135deg,#007ED2,#005fa3);color:#fff;border-radius:12px;padding:12px;text-decoration:none;font-weight:600;font-size:14px;">Redeem Points</a>` : `<a href="landing.php" style="flex:1;background:linear-gradient(135deg,var(--red),#c20000);color:#fff;border-radius:12px;padding:12px;text-decoration:none;font-weight:600;font-size:14px;">Go Home</a>`}
      </div>
    </div>
  </div>`);
}

// ─── MAP ──────────────────────────────────────────────────────────────────────
let map, marker;

function initMap() {
  if (map) return;
  map = L.map('mapContainer').setView([14.5995, 120.9842], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
  }).addTo(map);
  marker = L.marker([14.5995, 120.9842], { draggable: true }).addTo(map);
  marker.on('dragend', e => reverseGeocode(e.target.getLatLng()));
  detectLocation();
}

function detectLocation() {
  document.getElementById('addressDisplay').textContent = 'Detecting location...';
  if (!navigator.geolocation) {
    document.getElementById('addressDisplay').textContent = 'Geolocation not supported.';
    return;
  }
  navigator.geolocation.getCurrentPosition(pos => {
    const { latitude: lat, longitude: lng } = pos.coords;
    map.setView([lat, lng], 16);
    marker.setLatLng([lat, lng]);
    reverseGeocode({ lat, lng });
  }, () => {
    document.getElementById('addressDisplay').textContent = 'Unable to detect location. Please drag the pin manually.';
  });
}

async function reverseGeocode({ lat, lng }) {
  reportData.location_lat = lat;
  reportData.location_lng = lng;
  try {
    const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`);
    const data = await res.json();
    const addr = data.display_name || `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
    reportData.location_address = addr;
    document.getElementById('addressDisplay').textContent = addr;
    document.getElementById('locLat').value = lat;
    document.getElementById('locLng').value = lng;
    document.getElementById('locAddress').value = addr;
  } catch {
    reportData.location_address = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
    document.getElementById('addressDisplay').textContent = reportData.location_address;
  }
}

// Set default date/time
document.addEventListener('DOMContentLoaded', () => {
  const now = new Date();
  const d = now.toISOString().split('T')[0];
  const t = now.toTimeString().slice(0, 5);
  if (document.getElementById('incidentDate')) document.getElementById('incidentDate').value = d;
  if (document.getElementById('incidentTime')) document.getElementById('incidentTime').value = t;
});
