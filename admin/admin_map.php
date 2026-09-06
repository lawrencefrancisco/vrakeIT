<?php 
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
startSecureSession();
requireAdminLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>VrakeIT - Admin Live Map</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <style>
    body, html { margin: 0; padding: 0; height: 100%; font-family: 'Poppins', sans-serif; overflow: hidden; }
    
    /* Full screen map wrapper */
    #adminMap { height: 100vh; width: 100%; z-index: 1; }
    
    /* Floating Map Panel */
    .map-overlay {
      position: absolute;
      top: 20px;
      left: 60px; /* Adjusted to accommodate an admin sidebar if needed */
      z-index: 1000;
      background: rgba(255, 255, 255, 0.95);
      padding: 15px 25px;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }
    
  </style>
</head>
<body>

  <!-- Floating Legend -->
  <div class="map-overlay">
    <h4 style="margin:0; font-weight: 700; color: #007ED2;">Live Incident Map</h4>
    <p style="margin: 0; font-size: 13px; color: #666;">Valenzuela Boundaries</p>
    <div style="margin-top: 10px; font-size: 12px; font-weight: 500;">
      <div><span style="color: red; font-size: 18px; vertical-align: middle;">●</span> Pending</div>
      <div><span style="color: orange; font-size: 18px; vertical-align: middle;">●</span> Reviewing</div>
      <div><span style="color: green; font-size: 18px; vertical-align: middle;">●</span> Closed</div>
    </div>
  </div>

  <div id="adminMap"></div>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    // 1. Initialize Map — locked to Valenzuela City, Philippines
    const VALENZUELA_CENTER = [14.7050, 120.9850];

    // Valenzuela City boundary — accurate vertices (clockwise)
    // Exact city bounds: N 14.7700 | S 14.6400 | E 121.0500 | W 120.9300
    const VALENZUELA_POLY = [
      // ─ Wawang Pulo westward protrusion (westernmost tip) ───────
      [14.7250, 120.9380],  // Wawang Pulo SW approach
      [14.7290, 120.9310],  // Wawang Pulo far-west tip
      [14.7350, 120.9305],  // Wawang Pulo west mid
      [14.7410, 120.9345],  // Wawang Pulo NW shoulder
      [14.7445, 120.9435],  // Wawang Pulo north / Coloong join
      [14.7465, 120.9530],  // Coloong west edge
      // ─ Northern boundary (W → E) ───────────────────────────────
      [14.7505, 120.9580],  // Coloong / Tagalag N
      [14.7550, 120.9660],  // Malanday north
      [14.7595, 120.9755],  // Lingunan NW
      [14.7640, 120.9870],  // Lingunan / Punturin N
      [14.7670, 120.9990],  // Punturin north apex
      [14.7695, 121.0110],  // Bignay west
      [14.7700, 121.0230],  // Bignay north (northernmost)
      [14.7675, 121.0370],  // Bignay NE / Lawang Bato N
      [14.7620, 121.0450],  // Lawang Bato north
      // ─ Eastern boundary (N → S) ────────────────────────────────
      [14.7510, 121.0490],  // Lawang Bato east
      [14.7400, 121.0500],  // Canumay East (easternmost)
      [14.7300, 121.0475],  // Canumay East mid
      [14.7210, 121.0420],  // Bagbaguin east
      [14.7100, 121.0340],  // Ugong east
      [14.7000, 121.0250],  // Mapulang Lupa / Ugong transition
      [14.6945, 121.0165],  // Paso de Blas east
      // ─ Southern boundary (E → W) ───────────────────────────────
      [14.6905, 121.0070],  // Gen. T. De Leon SE
      [14.6870, 120.9990],  // Gen. T. De Leon south
      [14.6845, 120.9910],  // Parada / Marulas E
      [14.6820, 120.9845],  // Marulas south (southernmost)
      [14.6825, 120.9750],  // Karuhatan south (Tullahan River)
      [14.6845, 120.9640],  // Malinta south (Tullahan River)
      [14.6870, 120.9565],  // Malinta / Rincon SW
      // ─ Western boundary (S → N) ────────────────────────────────
      [14.6935, 120.9530],  // Rincon west
      [14.7020, 120.9505],  // Polo west
      [14.7115, 120.9480],  // Balangkas west
      [14.7200, 120.9455],  // Malanday SW
      [14.7235, 120.9420],  // south approach to Wawang Pulo
    ];

    // Bounds matching exact city extents (with tiny padding)
    const VALENZUELA_BOUNDS = L.latLngBounds(
      L.latLng(14.6380, 120.9270), // SW — slightly beyond S/W city edge
      L.latLng(14.7720, 121.0530)  // NE — slightly beyond N/E city edge
    );
    const map = L.map('adminMap', {
      center: VALENZUELA_CENTER,
      zoom: 13,
      minZoom: 13,
      maxZoom: 19,
      maxBounds: VALENZUELA_BOUNDS,
      maxBoundsViscosity: 1.0
    });
    map.setMaxBounds(VALENZUELA_BOUNDS);

    // ── BASE TILE LAYER (full color) ─────────────────────────
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // ── BORDER HIGHLIGHT ──────────────────────────────────────
    L.polygon(VALENZUELA_POLY, {
      color: '#007ED2',
      weight: 3,
      dashArray: '8 5',
      fill: false,
      interactive: false
    }).addTo(map);

    // ── DARK MASK: inverted donut polygon — outside = dark, inside = city ──
    // fillRule 'evenodd' is REQUIRED for the hole to render correctly in Leaflet
    const WORLD_OUTER = [
      [90, -180], [90, 180], [-90, 180], [-90, -180]
    ];
    L.polygon([WORLD_OUTER, VALENZUELA_POLY], {
      color: 'transparent',
      weight: 0,
      fillColor: '#0d0d1a',
      fillOpacity: 0.85,
      fillRule: 'evenodd',
      interactive: false
    }).addTo(map);

    // Group to hold markers so we can clear them easily on refresh
    let markerGroup = L.layerGroup().addTo(map);

    // 2. Custom Icon Generator based on Status
   function getMarkerColor(status) {
  if (status === 'pending') return '#f87171';   // Red
  if (status === 'reviewing') return '#fbbf24'; // Orange
  if (status === 'closed') return '#4ade80';    // Green
  return '#60b4ff';                             // Default Blue
}

    // 3. Fetch Data and Populate Map
    async function loadAdminMap() {
      try {
        // Send POST request matching the admin_actions.php requirement
        const formData = new FormData();
        formData.append('action', 'get_map_reports');

        // Adjust path if your admin_map is in a different directory relative to api/admin_actions.php
        const response = await fetch('../api/admin_action.php', {
          method: 'POST',
          body: formData
        });
        
        const data = await response.json();

        if (data.success && data.reports) {
          markerGroup.clearLayers(); // Clear existing markers to prevent duplicates on polling

          data.reports.forEach(report => {
            if (report.latitude && report.longitude) {
              const markerColor = getMarkerColor(report.status);
          
          const marker = L.circleMarker([report.latitude, report.longitude], {
            radius: 12,                // Size of the circle
            color: markerColor,        // Border color
            weight: 2,                 // Border thickness
            fillColor: markerColor,    // Inside color
            fillOpacity: 0.35          // Transparency level (0.0 to 1.0)
          }).addTo(markerGroup);

              const isInjuredText = parseInt(report.is_injured) === 1 ? '<span style="color:red; font-weight:bold;">Yes</span>' : 'No';

              // Build Popup Content
              const popupHtml = `
                <div style="font-family: 'Poppins', sans-serif; min-width: 180px;">
                  <strong style="color: #E90101; font-size: 14px;">Ref: ${report.reference_number}</strong><br>
                  <span style="font-size: 12px; color: #444;">Type: <strong>${report.flow_type.replace('_', ' ').toUpperCase()}</strong></span><br>
                  <span style="font-size: 12px; color: #444;">Status: <strong>${report.status.toUpperCase()}</strong></span><br>
                  <span style="font-size: 12px; color: #444;">Injuries: ${isInjuredText}</span><br>
                  <hr style="margin: 8px 0;">
                  <span style="font-size: 11px; color: #666;">${report.location_address}</span><br>
                  <a href="view_report.php?id=${report.id}" style="display:inline-block; margin-top:8px; padding: 4px 8px; background: #007ED2; color: #fff; text-decoration: none; border-radius: 4px; font-size: 11px;">View Full Report</a>
                </div>
              `;
              marker.bindPopup(popupHtml);
            }
          });
        }
      } catch (error) {
        console.error("Failed to load map data:", error);
      }
    }

    // Load reports initially and poll every 30 seconds for live updates
    loadAdminMap();
    setInterval(loadAdminMap, 30000);
  </script>
</body>
<!-- Test update -->
</html>