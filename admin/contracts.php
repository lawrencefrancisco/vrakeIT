<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once __DIR__ . '/layout.php';
startSecureSession();
requireAdminLogin();
$admin = getAdminUser();
$db    = getDB();

// ── Fetch contracts with linked report + both user accounts ──────
$contracts = $db->query("
    SELECT
        c.*,
        CONCAT(u1.first_name,' ',u1.last_name)  AS submitted_by,
        u1.email                                  AS p1_account_email,
        u1.verification_status                    AS p1_id_verified,
        CONCAT(u2.first_name,' ',u2.last_name)   AS p2_account_name,
        u2.email                                  AS p2_account_email,
        u2.verification_status                    AS p2_id_verified,
        r.reference_number                        AS report_ref,
        r.flow_type                               AS report_flow_type,
        r.status                                  AS report_status
    FROM contracts c
    LEFT JOIN users u1 ON c.party1_user_id = u1.id
    LEFT JOIN users u2 ON c.party2_user_id = u2.id
    LEFT JOIN reports r ON c.report_id = r.id
    ORDER BY c.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// ── Summary counts ───────────────────────────────────────────────
$counts = [
    'total'    => count($contracts),
    'draft'    => 0, 'waiting' => 0, 'signed' => 0,
    'settled'  => 0, 'disputed' => 0,
];
foreach ($contracts as $c) {
    $s = $c['status'] ?? 'draft';
    if (isset($counts[$s])) $counts[$s]++;
}

adminHead('Settlement Contracts');
?>

<body>
<?php adminNav('contracts', $admin); ?>
<div class="main">
    <?php adminTopbar('Settlement Contracts'); ?>
    <div class="page-body">

        <!-- ── Summary Strip ───────────────────────────────── -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:16px;margin-bottom:28px;">
            <?php
            $tiles = [
                ['Total',          $counts['total'],   'bi-file-earmark-ruled',      '#6366f1'],
                ['Drafts',         $counts['draft'],   'bi-pencil-square',           '#94a3b8'],
                ['Awaiting P2',    $counts['waiting'], 'bi-hourglass-split',         '#f59e0b'],
                ['Signed',         $counts['signed'],  'bi-pen-fill',                '#0ea5e9'],
                ['Settled',        $counts['settled'], 'bi-check-circle-fill',       '#10b981'],
                ['Disputed',       $counts['disputed'],'bi-exclamation-triangle-fill','#ef4444'],
            ];
            foreach ($tiles as [$label, $count, $icon, $color]): ?>
            <div class="stat-card" style="padding:18px;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                    <i class="bi <?= $icon ?>" style="font-size:1.15rem;color:<?= $color ?>;"></i>
                    <span style="font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.04em;"><?= $label ?></span>
                </div>
                <div style="font-size:1.9rem;font-weight:800;color:var(--text);line-height:1;"><?= $count ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ── Filter Bar ──────────────────────────────────── -->
        <div style="display:flex;gap:10px;margin-bottom:18px;flex-wrap:wrap;align-items:center;">
            <div style="position:relative;flex:1;min-width:220px;">
                <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:13px;"></i>
                <input type="text" id="searchInput" placeholder="Search reference, party, email…" oninput="filterTable()"
                    class="search-input" style="width:100%;padding-left:34px;">
            </div>
            <select id="statusFilter" onchange="filterTable()" class="form-dark" style="width:auto;min-width:160px;">
                <option value="all">All Statuses</option>
                <option value="draft">Draft</option>
                <option value="waiting">Awaiting Party 2</option>
                <option value="signed">Signed</option>
                <option value="settled">Settled</option>
                <option value="disputed">Disputed</option>
            </select>
            <select id="verifiedFilter" onchange="filterTable()" class="form-dark" style="width:auto;min-width:160px;">
                <option value="all">All ID Status</option>
                <option value="both">Both Verified</option>
                <option value="p1">P1 Verified Only</option>
                <option value="p2">P2 Verified Only</option>
                <option value="none">None Verified</option>
            </select>
        </div>

        <!-- ── Table ───────────────────────────────────────── -->
        <div class="section-card">
            <div class="section-header">
                <span class="section-title-text">
                    <i class="bi bi-file-earmark-ruled"></i>
                    Contracts &nbsp;<span style="font-size:13px;font-weight:500;color:var(--muted);">(<span id="visibleCount"><?= $counts['total'] ?></span> shown)</span>
                </span>
                <div style="display:flex;gap:8px;">
                    <button class="btn-admin btn-primary-admin" onclick="exportCSV()">
                        <i class="bi bi-download"></i> Export CSV
                    </button>
                </div>
            </div>
            <div style="overflow-x:auto;">
                <table class="data-table" id="contractsTable">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Incident Type</th>
                            <th>Party 1</th>
                            <th>Party 2</th>
                            <th>Fault</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>ID Verified</th>
                            <th>Linked Report</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="contractsTbody">
                    <?php foreach ($contracts as $c):
                        $status     = $c['status'] ?? 'draft';
                        $badgeClass = match ($status) {
                            'settled'  => 'bs-approved',
                            'signed'   => 'bs-verified',
                            'waiting'  => 'bs-pending',
                            'disputed' => 'bs-rejected',
                            'draft'    => 'bs-closed',
                            default    => 'bs-reviewing',
                        };
                        $badgeLabel = match ($status) {
                            'settled'  => 'Settled',
                            'signed'   => 'Signed',
                            'waiting'  => 'Awaiting P2',
                            'disputed' => 'Disputed',
                            'draft'    => 'Draft',
                            default    => ucfirst($status),
                        };
                        $amount      = is_numeric($c['amount']) && $c['amount'] > 0
                            ? '₱' . number_format((float)$c['amount'], 2) : '—';
                        $p1Verified  = ($c['p1_id_verified'] ?? '') === 'verified';
                        $p2Verified  = ($c['p2_id_verified'] ?? '') === 'verified';
                        $verifiedKey = $p1Verified && $p2Verified ? 'both' : ($p1Verified ? 'p1' : ($p2Verified ? 'p2' : 'none'));
                        $incType     = ucfirst(str_replace('-', ' ', $c['incident_type'] ?? ($c['report_flow_type'] ?? '')));
                        $fault       = ['party1'=>'P1','party2'=>'P2','shared'=>'Shared','undetermined'=>'Undecided'][$c['fault']??''] ?? '—';
                        $searchStr   = strtolower(
                            ($c['reference_number'] ?? '') . ' ' .
                            ($c['party1_name'] ?? '') . ' ' . ($c['p1_account_email'] ?? '') . ' ' .
                            ($c['party2_name'] ?? '') . ' ' . ($c['p2_account_email'] ?? '') . ' ' .
                            ($c['submitted_by'] ?? '') . ' ' . ($c['report_ref'] ?? '')
                        );
                    ?>
                    <tr data-status="<?= $status ?>"
                        data-verified="<?= $verifiedKey ?>"
                        data-search="<?= htmlspecialchars($searchStr) ?>"
                        data-json='<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>'>
                        <td>
                            <div style="font-weight:700;font-size:12px;color:var(--text);letter-spacing:0.3px;"><?= htmlspecialchars($c['reference_number']) ?></div>
                            <div style="font-size:11px;color:var(--muted);"><?= date('M d, Y', strtotime($c['created_at'])) ?></div>
                        </td>
                        <td style="font-size:12px;">
                            <?= $incType ?: '<span style="color:var(--muted);">—</span>' ?>
                        </td>
                        <td>
                            <div style="font-weight:600;font-size:12px;"><?= htmlspecialchars($c['party1_name'] ?? '—') ?></div>
                            <div style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($c['p1_account_email'] ?? '') ?></div>
                            <?php if ($p1Verified): ?><span style="font-size:10px;color:#0ea5e9;font-weight:700;">✓ Verified</span><?php endif; ?>
                        </td>
                        <td>
                            <?php if ($c['party2_name']): ?>
                            <div style="font-weight:600;font-size:12px;"><?= htmlspecialchars($c['party2_name']) ?></div>
                            <div style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($c['p2_account_email'] ?? '') ?></div>
                            <?php if ($p2Verified): ?><span style="font-size:10px;color:#0ea5e9;font-weight:700;">✓ Verified</span><?php endif; ?>
                            <?php else: ?><span style="color:var(--muted);font-size:11px;">Not invited yet</span><?php endif; ?>
                        </td>
                        <td style="font-size:12px;font-weight:600;"><?= $fault ?></td>
                        <td style="color:#f59e0b;font-weight:700;font-size:13px;"><?= $amount ?></td>
                        <td><span class="badge-status <?= $badgeClass ?>"><?= $badgeLabel ?></span></td>
                        <td>
                            <?php if ($p1Verified && $p2Verified): ?>
                                <span class="badge-status bs-verified">Both ✓</span>
                            <?php elseif ($p1Verified): ?>
                                <span class="badge-status bs-pending">P1 Only</span>
                            <?php elseif ($p2Verified): ?>
                                <span class="badge-status bs-pending">P2 Only</span>
                            <?php else: ?>
                                <span class="badge-status bs-closed">None</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($c['report_ref']): ?>
                            <a href="incidents.php?ref=<?= urlencode($c['report_ref']) ?>"
                               style="font-size:11px;color:var(--accent);font-weight:700;text-decoration:underline;">
                               <?= htmlspecialchars($c['report_ref']) ?>
                            </a>
                            <?php else: ?>
                            <span style="font-size:11px;color:var(--muted);">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12px;color:var(--muted);"><?= date('M d, Y', strtotime($c['created_at'])) ?></td>
                        <td>
                            <button onclick='openDetail(this.closest("tr").dataset.json)'
                                class="btn-admin btn-review">
                                <i class="bi bi-eye me-1"></i>View
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div id="emptyMsg" style="display:none;text-align:center;padding:64px;color:var(--muted);">
                    <i class="bi bi-inbox" style="font-size:40px;display:block;margin-bottom:12px;opacity:.4;"></i>
                    No contracts match your filters.
                </div>
            </div>
        </div>

    </div><!-- /page-body -->
</div><!-- /main -->

<!-- ══════════════════════════════════════════════════════════════
     DETAIL SLIDE-OVER
═══════════════════════════════════════════════════════════════ -->
<div id="detailOverlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.4);backdrop-filter:blur(6px);z-index:900;" onclick="closeDetail()"></div>

<div id="detailPanel" style="
    position:fixed;top:0;right:0;height:100vh;width:540px;max-width:100vw;
    background:var(--card-bg);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);
    border-left:1px solid var(--border-light);box-shadow:-10px 0 40px rgba(0,0,0,.12);
    z-index:901;display:flex;flex-direction:column;
    transform:translateX(100%);transition:transform .3s cubic-bezier(.4,0,.2,1);overflow:hidden;">

    <!-- Panel Header -->
    <div id="panelHeader" style="background:linear-gradient(135deg,#6366f1,#4f46e5);padding:24px 24px 20px;flex-shrink:0;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:36px;height:36px;background:rgba(255,255,255,.2);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-file-earmark-ruled" style="font-size:18px;color:#fff;"></i>
                </div>
                <div>
                    <div style="font-size:16px;font-weight:800;color:#fff;" id="panelTitle">Contract Details</div>
                    <div style="font-size:11px;color:rgba(255,255,255,.75);" id="panelSubtitle"></div>
                </div>
            </div>
            <button onclick="closeDetail()" style="width:32px;height:32px;border-radius:8px;background:rgba(255,255,255,.15);border:none;color:#fff;font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;">&times;</button>
        </div>
        <div id="panelBadge" style="margin-top:4px;"></div>
    </div>

    <!-- Panel Body (scrollable) -->
    <div id="panelBody" style="flex:1;overflow-y:auto;padding:20px 24px;"></div>

    <!-- Panel Footer -->
    <div id="panelFooter" style="padding:16px 24px;border-top:1px solid var(--border);background:rgba(255,255,255,.5);flex-shrink:0;display:flex;gap:10px;flex-wrap:wrap;"></div>
</div>

<!-- Dispute Note Modal -->
<div class="modal fade" id="disputeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
        <div class="modal-content" style="border-radius:20px;border:none;background:var(--card-bg);color:var(--text);">
            <div class="modal-header" style="background:linear-gradient(135deg,#ef4444,#b91c1c);border-radius:20px 20px 0 0;border:none;">
                <h5 class="modal-title" style="font-weight:700;color:#fff;"><i class="bi bi-exclamation-triangle-fill me-2"></i>Log Dispute Note</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:24px;">
                <label class="form-lbl">Admin Dispute Note</label>
                <textarea id="disputeNoteInput" class="form-dark" rows="4" placeholder="Describe the dispute reason or resolution attempt…" style="resize:none;"></textarea>
            </div>
            <div class="modal-footer" style="border:none;padding:0 24px 24px;gap:10px;">
                <button class="btn-admin btn-reject" style="flex:1;padding:12px;" onclick="saveDisputeNote()">
                    <i class="bi bi-exclamation-triangle me-1"></i>Mark Disputed
                </button>
                <button class="btn-admin" style="flex:1;padding:12px;" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── State ────────────────────────────────────────────────────────
let currentContract = null;
const disputeModal  = new bootstrap.Modal(document.getElementById('disputeModal'));

// ── Filter + Search ──────────────────────────────────────────────
function filterTable() {
    const term     = document.getElementById('searchInput').value.toLowerCase();
    const status   = document.getElementById('statusFilter').value;
    const verified = document.getElementById('verifiedFilter').value;
    const rows     = document.querySelectorAll('#contractsTbody tr');
    let   vis      = 0;
    rows.forEach(r => {
        const ms = status   === 'all' || r.dataset.status   === status;
        const mv = verified === 'all' || r.dataset.verified === verified;
        const mt = r.dataset.search.includes(term);
        const ok = ms && mv && mt;
        r.style.display = ok ? '' : 'none';
        if (ok) vis++;
    });
    document.getElementById('visibleCount').textContent = vis;
    document.getElementById('emptyMsg').style.display   = vis === 0 ? 'block' : 'none';
}

// ── Status helpers ───────────────────────────────────────────────
const STATUS_LABELS = {
    draft:'Draft', waiting:'Awaiting Party 2', signed:'Signed',
    settled:'Settled', fulfilled:'Fulfilled',
    not_settled:'Pending', disputed:'Disputed',
};
const STATUS_COLORS = {
    draft:'#94a3b8', waiting:'#f59e0b', signed:'#0ea5e9',
    settled:'#10b981', fulfilled:'#10b981',
    not_settled:'#f59e0b', disputed:'#ef4444',
};
const STATUS_BG = {
    draft:'rgba(148,163,184,.15)', waiting:'rgba(245,158,11,.15)',
    signed:'rgba(14,165,233,.15)', settled:'rgba(16,185,129,.15)',
    fulfilled:'rgba(16,185,129,.15)', not_settled:'rgba(245,158,11,.15)',
    disputed:'rgba(239,68,68,.15)',
};

function statusChip(s) {
    const label = STATUS_LABELS[s] ?? s;
    const color = STATUS_COLORS[s] ?? '#6b7280';
    const bg    = STATUS_BG[s]    ?? 'rgba(107,114,128,.12)';
    return `<span style="display:inline-block;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;color:${color};background:${bg};">${label}</span>`;
}

function htmlEsc(str) {
    if (!str) return '—';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Detail panel ─────────────────────────────────────────────────
function openDetail(jsonStr) {
    const c = typeof jsonStr === 'string' ? JSON.parse(jsonStr) : jsonStr;
    currentContract = c;
    const s = c.status ?? 'draft';

    // Header
    document.getElementById('panelTitle').textContent   = htmlEsc(c.reference_number) || 'Contract';
    document.getElementById('panelSubtitle').textContent = c.incident_type
        ? c.incident_type.replace(/-/g,' ').replace(/\b\w/g,l=>l.toUpperCase())
        : (c.report_flow_type ? 'Flow: ' + c.report_flow_type.replace(/_/g,' ').replace(/\b\w/g,l=>l.toUpperCase()) : 'Settlement Contract');
    document.getElementById('panelBadge').innerHTML = statusChip(s);

    // Body
    const row = (l, v) => `
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding:9px 0;border-bottom:1px solid rgba(0,0,0,.05);">
          <span style="font-size:11px;color:var(--muted);font-weight:600;white-space:nowrap;">${l}</span>
          <span style="font-size:13px;font-weight:600;text-align:right;word-break:break-word;max-width:60%;">${v}</span>
        </div>`;
    const section = (title, content) => `
        <div style="margin-bottom:18px;">
          <div style="font-size:10px;font-weight:800;color:#6366f1;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:8px;padding-bottom:5px;border-bottom:2px solid rgba(99,102,241,.15);">${title}</div>
          ${content}
        </div>`;

    const amount    = (c.amount && parseFloat(c.amount) > 0) ? '₱' + parseFloat(c.amount).toLocaleString('en-PH',{minimumFractionDigits:2}) : 'Not specified';
    const deadline  = c.payment_deadline ? new Date(c.payment_deadline+'T00:00:00').toLocaleDateString('en-PH',{year:'numeric',month:'long',day:'numeric'}) : '—';
    const p1Signed  = c.p1_signed_at ? new Date(c.p1_signed_at).toLocaleString('en-PH') : '—';
    const p2Signed  = c.p2_signed_at ? new Date(c.p2_signed_at).toLocaleString('en-PH') : '—';
    const created   = new Date(c.created_at).toLocaleString('en-PH',{year:'numeric',month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'});
    const faultMap  = {party1:'Party 1',party2:'Party 2',shared:'Shared',undetermined:'Not Determined'};
    const resMap    = {
        pay_repair:'Pay Repair Costs', shoulder_shop:'Shoulder at Shop',
        cash:'Cash Payment', split:'Split Costs',
        insurance:'Insurance', no_comp:'No Compensation'
    };
    const payMap    = {cash:'Cash',gcash:'GCash',maya:'Maya',bank_transfer:'Bank Transfer'};
    const schMap    = {lump_sum:'Lump Sum',installments:'Installments'};

    const verBadge = v => (v === 'verified')
        ? '<span style="color:#0ea5e9;font-size:11px;font-weight:700;">✓ ID Verified</span>'
        : '<span style="color:#94a3b8;font-size:11px;">Not Verified</span>';

    let html = section('Contract Info',
        row('Reference',     htmlEsc(c.reference_number)) +
        row('Status',        statusChip(s)) +
        row('Date Created',  created) +
        row('Incident Type', c.incident_type ? c.incident_type.replace(/-/g,' ').replace(/\b\w/g,l=>l.toUpperCase()) : '—') +
        row('Fault',         faultMap[c.fault] ?? '—') +
        (c.report_ref ? row('Linked Report', `<a href="incidents.php?ref=${encodeURIComponent(c.report_ref)}" style="color:var(--accent);font-weight:700;text-decoration:underline;">${htmlEsc(c.report_ref)}</a>`) : '')
    );

    html += section('Party 1',
        row('Name',    htmlEsc(c.party1_name)    || '—') +
        row('Email',   htmlEsc(c.p1_account_email) || '—') +
        row('Contact', htmlEsc(c.party1_contact) || '—') +
        row('Address', htmlEsc(c.party1_address) || '—') +
        row('Vehicle', [c.party1_vehicle_type, c.party1_plate].filter(Boolean).join(' · ') || '—') +
        row('License', htmlEsc(c.party1_license) || '—') +
        row('Insurance', htmlEsc(c.party1_insurance) || '—') +
        row('ID Status', verBadge(c.p1_id_verified)) +
        row('Signed At', p1Signed)
    );

    html += section('Party 2',
        row('Name',    htmlEsc(c.party2_name)    || '—') +
        row('Email',   htmlEsc(c.p2_account_email) || '—') +
        row('Contact', htmlEsc(c.party2_contact) || '—') +
        row('Address', htmlEsc(c.party2_address) || '—') +
        row('Vehicle', [c.party2_vehicle_type, c.party2_plate].filter(Boolean).join(' · ') || '—') +
        row('License', htmlEsc(c.party2_license) || '—') +
        row('Insurance', htmlEsc(c.party2_insurance) || '—') +
        row('ID Status', verBadge(c.p2_id_verified)) +
        row('Confirmed At', p2Signed)
    );

    if (c.witness_name) {
        html += section('Witness',
            row('Name',    htmlEsc(c.witness_name)) +
            row('Contact', htmlEsc(c.witness_contact) || '—')
        );
    }

    html += section('Damage',
        row('P1 Damage',    htmlEsc(c.damage_desc_p1) || '—') +
        row('P1 Est. Cost', c.damage_cost_p1 ? '₱'+Number(c.damage_cost_p1).toLocaleString('en-PH',{minimumFractionDigits:2}) : '—') +
        row('P2 Damage',    htmlEsc(c.damage_desc_p2) || '—') +
        row('P2 Est. Cost', c.damage_cost_p2 ? '₱'+Number(c.damage_cost_p2).toLocaleString('en-PH',{minimumFractionDigits:2}) : '—')
    );

    html += section('Agreement',
        row('Resolution',   resMap[c.resolution_type] ?? '—') +
        row('Who Pays',     c.who_pays ? c.who_pays.replace(/_/g,' ').replace(/\b\w/g,l=>l.toUpperCase()) : '—') +
        row('Amount',       `<span style="color:#f59e0b;font-weight:800;">${amount}</span>`) +
        row('Payment',      payMap[c.payment_method] ?? '—') +
        row('Schedule',     schMap[c.payment_schedule] ?? '—') +
        row('Deadline',     deadline)
    );

    if (c.terms) {
        html += `<div style="margin-bottom:18px;">
          <div style="font-size:10px;font-weight:800;color:#6366f1;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:8px;padding-bottom:5px;border-bottom:2px solid rgba(99,102,241,.15);">Written Terms</div>
          <div style="background:rgba(0,126,210,.06);border-left:3px solid var(--accent);border-radius:6px;padding:12px;font-size:12.5px;line-height:1.7;white-space:pre-wrap;">${htmlEsc(c.terms)}</div>
        </div>`;
    }
    if (c.escalation_clause) {
        html += `<div style="margin-bottom:18px;">
          <div style="font-size:10px;font-weight:800;color:#ef4444;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:8px;">Escalation Clause</div>
          <div style="background:rgba(239,68,68,.06);border-left:3px solid #ef4444;border-radius:6px;padding:12px;font-size:12.5px;line-height:1.7;white-space:pre-wrap;">${htmlEsc(c.escalation_clause)}</div>
        </div>`;
    }

    if (c.admin_notes) {
        html += `<div style="margin-bottom:18px;">
          <div style="font-size:10px;font-weight:800;color:#f59e0b;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:8px;">Admin Notes</div>
          <div style="background:rgba(245,158,11,.08);border-left:3px solid #f59e0b;border-radius:6px;padding:12px;font-size:12.5px;line-height:1.7;">${htmlEsc(c.admin_notes)}</div>
        </div>`;
    }

    document.getElementById('panelBody').innerHTML = html;

    // Footer actions
    buildFooter(s, c);

    // Open panel
    document.getElementById('detailOverlay').style.display = 'block';
    requestAnimationFrame(() => {
        document.getElementById('detailPanel').style.transform = 'translateX(0)';
    });
}

function buildFooter(s, c) {
    const footer  = document.getElementById('panelFooter');
    const refEnc  = encodeURIComponent(c.reference_number);
    let   btns    = '';

    if (['signed','settled','fulfilled'].includes(s)) {
        btns += `<a href="../api/get_contract_pdf.php?ref=${refEnc}" target="_blank"
                    style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:11px;border-radius:12px;background:linear-gradient(135deg,#007ED2,#005fa3);color:#fff;font-weight:700;font-size:13px;text-decoration:none;">
                    <i class="bi bi-file-earmark-pdf-fill"></i> Download PDF
                 </a>`;
    }

    if (s !== 'settled' && s !== 'fulfilled' && s !== 'disputed') {
        btns += `<button onclick="adminMarkSettled('${c.reference_number}')"
                    style="flex:1;padding:11px;border-radius:12px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;border:none;font-family:Poppins,sans-serif;font-weight:700;font-size:13px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;">
                    <i class="bi bi-check2-circle"></i> Mark Settled
                 </button>`;
    }

    if (s !== 'disputed') {
        btns += `<button onclick="openDisputeModal('${c.reference_number}')"
                    style="flex:1;padding:11px;border-radius:12px;background:linear-gradient(135deg,#ef4444,#b91c1c);color:#fff;border:none;font-family:Poppins,sans-serif;font-weight:700;font-size:13px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;">
                    <i class="bi bi-exclamation-triangle"></i> Log Dispute
                 </button>`;
    }

    if (!btns) {
        btns = `<div style="text-align:center;width:100%;font-size:13px;color:var(--muted);padding:4px;">No further actions available.</div>`;
    }

    footer.innerHTML = `<div style="display:flex;gap:8px;width:100%;flex-wrap:wrap;">${btns}</div>`;
}

function closeDetail() {
    document.getElementById('detailPanel').style.transform = 'translateX(100%)';
    setTimeout(() => { document.getElementById('detailOverlay').style.display = 'none'; }, 300);
}

// ── Admin actions ────────────────────────────────────────────────
async function adminMarkSettled(ref) {
    if (!confirm(`Mark contract ${ref} as SETTLED?`)) return;
    const fd = new FormData();
    fd.append('ref', ref);
    fd.append('action', 'settled');
    await adminUpdateStatus(fd);
}

function openDisputeModal(ref) {
    currentContract._activeRef = ref;
    document.getElementById('disputeNoteInput').value = '';
    disputeModal.show();
}

async function saveDisputeNote() {
    const note = document.getElementById('disputeNoteInput').value.trim();
    const ref  = currentContract._activeRef || currentContract.reference_number;
    if (!note) { alert('Please enter a dispute note.'); return; }
    const fd = new FormData();
    fd.append('ref', ref);
    fd.append('action', 'disputed');
    fd.append('note', note);
    disputeModal.hide();
    await adminUpdateStatus(fd);
}

async function adminUpdateStatus(fd) {
    try {
        const res  = await fetch('../api/admin_update_contract.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            // Update row data-status attribute + badge
            const ref = fd.get('ref');
            const tr  = [...document.querySelectorAll('#contractsTbody tr')]
                .find(r => JSON.parse(r.dataset.json).reference_number === ref);
            if (tr) {
                const newStatus = fd.get('action');
                const parsed    = JSON.parse(tr.dataset.json);
                parsed.status   = newStatus;
                if (fd.get('note')) parsed.admin_notes = fd.get('note');
                tr.dataset.json   = JSON.stringify(parsed);
                tr.dataset.status = newStatus;
                // Update visible badge cell
                const badgeCell = tr.querySelector('.badge-status');
                if (badgeCell) {
                    const map = {settled:'bs-approved',disputed:'bs-rejected'};
                    const lbl = {settled:'Settled',disputed:'Disputed'};
                    badgeCell.className = 'badge-status ' + (map[newStatus] || 'bs-reviewing');
                    badgeCell.textContent = lbl[newStatus] || newStatus;
                }
                currentContract = parsed;
            }
            closeDetail();
            showAdminToast(data.message || 'Updated successfully!', 'success');
        } else {
            showAdminToast(data.message || 'Update failed.', 'error');
        }
    } catch(e) {
        showAdminToast('Network error. Try again.', 'error');
    }
}

// ── Export CSV ───────────────────────────────────────────────────
function exportCSV() {
    const rows = [...document.querySelectorAll('#contractsTbody tr')]
        .filter(r => r.style.display !== 'none')
        .map(r => {
            const c = JSON.parse(r.dataset.json);
            return [
                c.reference_number, c.status, c.incident_type ?? '', c.fault ?? '',
                c.party1_name ?? '', c.p1_account_email ?? '',
                c.party2_name ?? '', c.p2_account_email ?? '',
                c.amount ?? '', c.resolution_type ?? '', c.payment_method ?? '',
                c.payment_deadline ?? '', c.report_ref ?? '',
                c.p1_signed_at ?? '', c.p2_signed_at ?? '',
                new Date(c.created_at).toLocaleDateString('en-PH'),
            ].map(v => `"${String(v).replace(/"/g,'""')}"`).join(',');
        });
    const header = ['Reference','Status','Incident Type','Fault',
        'Party 1 Name','Party 1 Email','Party 2 Name','Party 2 Email',
        'Amount','Resolution','Payment Method','Payment Deadline',
        'Linked Report','P1 Signed','P2 Signed','Created Date'].join(',');
    const csv  = [header, ...rows].join('\n');
    const blob = new Blob([csv], {type:'text/csv'});
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `vrakeit-contracts-${Date.now()}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

// ── Toast ────────────────────────────────────────────────────────
function showAdminToast(msg, type='success') {
    const t = document.createElement('div');
    const bg = type === 'success' ? 'linear-gradient(135deg,#10b981,#059669)' : 'linear-gradient(135deg,#ef4444,#b91c1c)';
    t.style.cssText = `position:fixed;bottom:24px;right:24px;z-index:9999;background:${bg};color:#fff;padding:14px 20px;border-radius:14px;font-family:Poppins,sans-serif;font-size:13px;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.15);display:flex;align-items:center;gap:8px;max-width:360px;animation:slideUp .3s ease;`;
    t.innerHTML = `<i class="bi bi-${type==='success'?'check-circle-fill':'exclamation-circle-fill'}"></i> ${msg}`;
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity='0';t.style.transition='opacity .4s'; setTimeout(()=>t.remove(),400); }, 3500);
}

// Close on Escape key
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDetail(); });
</script>

<style>
@keyframes slideUp {
    from { transform:translateY(20px); opacity:0; }
    to   { transform:translateY(0);    opacity:1; }
}
</style>

</body>
</html>
