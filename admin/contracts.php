<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once __DIR__ . '/layout.php';
startSecureSession();
requireAdminLogin();
$admin = getAdminUser();
$db = getDB();

$contracts = $db->query("
    SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as submitted_by
    FROM contracts c
    LEFT JOIN users u ON c.user_id = u.id
    ORDER BY c.created_at DESC
")->fetchAll();

// Summary counts
$total      = count($contracts);
$settled    = count(array_filter($contracts, fn($c) => ($c['status'] ?? '') === 'settled'));
$disputed   = count(array_filter($contracts, fn($c) => ($c['status'] ?? '') === 'disputed'));
$notSettled = $total - $settled - $disputed;

adminHead('Settlement Contracts');
?>

<body>
    <?php adminNav('contracts', $admin); ?>
    <div class="main">
        <?php adminTopbar('Settlement Contracts'); ?>
        <div class="page-body">

            <!-- Summary Cards -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:24px;">
                <?php foreach (
                    [
                        ['Total',       $total,      'bi-file-earmark-ruled', '#6366f1'],
                        ['Pending', $notSettled, 'bi-hourglass-split',    '#fbbf24'],
                        ['Settled',     $settled,    'bi-check-circle-fill',  '#10b981'],
                        ['Disputed',    $disputed,   'bi-exclamation-triangle-fill', '#ef4444'],
                    ] as [$label, $count, $icon, $color]
                ): ?>
                    <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:18px 20px;">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                            <i class="bi <?= $icon ?>" style="font-size:1.2rem;color:<?= $color ?>;"></i>
                            <span style="font-size:12px;color:rgba(255,255,255,0.45);font-weight:600;text-transform:uppercase;letter-spacing:.04em;"><?= $label ?></span>
                        </div>
                        <div style="font-size:1.75rem;font-weight:800;color:#fff;"><?= $count ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Search + Filter -->
            <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
                <div style="position:relative;flex:1;min-width:200px;">
                    <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,0.3);font-size:13px;"></i>
                    <input type="text" id="searchInput" placeholder="Search reference, party, or user..." oninput="filterTable()"
                        style="width:100%;padding:9px 12px 9px 34px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:#fff;font-family:inherit;font-size:13px;outline:none;">
                </div>
                <select id="statusFilter" onchange="filterTable()"
                    style="padding:9px 12px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:#fff;font-family:inherit;font-size:13px;outline:none;">
                    <option value="all">All Status</option>
                    <option value="not_settled">Pending</option>
                    <option value="settled">Settled</option>
                    <option value="disputed">Disputed</option>
                </select>
            </div>

            <div class="section-card">
                <div class="section-header">
                    <span class="section-title-text"><i class="bi bi-file-earmark-ruled me-2"></i>All Contracts (<span id="visibleCount"><?= $total ?></span>)</span>
                </div>
                <div style="overflow-x:auto;">
                    <table class="data-table" id="contractsTable">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Submitted By</th>
                                <th>Party 1</th>
                                <th>Party 2</th>
                                <th>Parties</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="contractsTbody">
                            <?php foreach ($contracts as $c):
                                $status     = $c['status'] ?? 'not_settled';
                                $badgeClass = match ($status) {
                                    'settled'    => 'bs-approved',
                                    'disputed'   => 'bs-closed',
                                    default      => 'bs-reviewing',
                                };
                                $badgeLabel = match ($status) {
                                    'settled'    => 'Settled',
                                    'disputed'   => 'Disputed',
                                    default      => 'Pending',
                                };
                                $amount = is_numeric($c['amount']) && $c['amount'] > 0
                                    ? '₱' . number_format($c['amount'], 2)
                                    : '—';
                            ?>
                                <tr data-status="<?= $status ?>"
                                    data-search="<?= strtolower(htmlspecialchars($c['reference_number'] . ' ' . $c['party1_name'] . ' ' . $c['party2_name'] . ' ' . ($c['submitted_by'] ?? ''))) ?>">
                                    <td><strong style="color:#e2e8f0;"><?= htmlspecialchars($c['reference_number']) ?></strong></td>
                                    <td style="font-size:12px;color:rgba(255,255,255,0.55);"><?= htmlspecialchars($c['submitted_by'] ?? '—') ?></td>
                                    <td>
                                        <div style="font-weight:600;"><?= htmlspecialchars($c['party1_name'] ?? '—') ?></div>
                                        <?php if ($c['party1_contact']): ?>
                                            <div style="font-size:11px;color:rgba(255,255,255,0.35);"><?= htmlspecialchars($c['party1_contact']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight:600;"><?= htmlspecialchars($c['party2_name'] ?? '—') ?></div>
                                        <?php if ($c['party2_contact']): ?>
                                            <div style="font-size:11px;color:rgba(255,255,255,0.35);"><?= htmlspecialchars($c['party2_contact']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-transform:capitalize;font-size:12px;color:rgba(255,255,255,0.55);"><?= htmlspecialchars($c['parties'] ?? '—') ?></td>
                                    <td style="color:#fbbf24;font-weight:700;"><?= $amount ?></td>
                                    <td><span class="badge-status <?= $badgeClass ?>"><?= $badgeLabel ?></span></td>
                                    <td style="font-size:12px;color:rgba(255,255,255,0.4);"><?= date('M d, Y', strtotime($c['created_at'])) ?></td>
                                    <td>
                                        <button onclick='openDetail(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)'
                                            style="background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.12);border-radius:8px;padding:5px 12px;color:#fff;font-family:inherit;font-size:12px;font-weight:600;cursor:pointer;transition:background .2s;"
                                            onmouseover="this.style.background='rgba(255,255,255,0.14)'"
                                            onmouseout="this.style.background='rgba(255,255,255,0.07)'">
                                            <i class="bi bi-eye me-1"></i>View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div id="emptyMsg" style="display:none;text-align:center;padding:48px;color:rgba(255,255,255,0.3);">
                        <i class="bi bi-inbox" style="font-size:40px;display:block;margin-bottom:10px;"></i>No contracts match your search.
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ── Detail Modal ─────────────────────────────────────── -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-scrollable" style="margin:auto;max-width:500px;">
            <div class="modal-content" style="border-radius:20px;border:none;background:#1e2433;color:#fff;">
                <div class="modal-header" style="background:linear-gradient(135deg,#6366f1,#4f46e5);border-radius:20px 20px 0 0;border:none;">
                    <h5 class="modal-title" style="font-weight:700;"><i class="bi bi-file-earmark-ruled me-2"></i>Contract Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody" style="font-size:14px;padding:20px;"></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const detailModal = new bootstrap.Modal(document.getElementById('detailModal'));

        // ── Search + filter ───────────────────────────────────────
        function filterTable() {
            const term = document.getElementById('searchInput').value.toLowerCase();
            const status = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('#contractsTbody tr');
            let visible = 0;

            rows.forEach(row => {
                const matchSearch = row.dataset.search.includes(term);
                const matchStatus = status === 'all' || row.dataset.status === status;
                const show = matchSearch && matchStatus;
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            document.getElementById('visibleCount').textContent = visible;
            document.getElementById('emptyMsg').style.display = visible === 0 ? 'block' : 'none';
        }

        // ── Detail modal ──────────────────────────────────────────
        function openDetail(c) {
            const statusLabels = {
                settled: 'Settled',
                not_settled: 'Pending',
                disputed: 'Disputed'
            };
            const statusColors = {
                settled: '#10b981',
                not_settled: '#fbbf24',
                disputed: '#ef4444'
            };
            const s = c.status ?? 'not_settled';

            const amount = (c.amount && parseFloat(c.amount) > 0) ?
                '₱' + parseFloat(c.amount).toLocaleString() :
                'Not specified';

            const row = (label, value) => `
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.07);">
      <span style="font-size:12px;color:rgba(255,255,255,0.4);flex-shrink:0;">${label}</span>
      <span style="font-size:13px;font-weight:600;text-align:right;word-break:break-word;max-width:65%;">${value}</span>
    </div>`;

            document.getElementById('modalBody').innerHTML = `
    <div style="background:rgba(255,255,255,0.04);border-radius:12px;padding:4px 12px;margin-bottom:4px;">
      ${row('Reference',     htmlEsc(c.reference_number))}
      ${row('Status',        `<span style="color:${statusColors[s]};font-weight:700;">${statusLabels[s] ?? s}</span>`)}
      ${row('Submitted By',  htmlEsc(c.submitted_by ?? '—'))}
      ${row('Submitted On',  c.created_at ? new Date(c.created_at).toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'numeric'}) : '—')}
      ${row('Party 1',       htmlEsc(c.party1_name ?? '—') + (c.party1_contact ? `<br><small style="color:rgba(255,255,255,0.35);">${htmlEsc(c.party1_contact)}</small>` : ''))}
      ${row('Party 2',       htmlEsc(c.party2_name ?? '—') + (c.party2_contact ? `<br><small style="color:rgba(255,255,255,0.35);">${htmlEsc(c.party2_contact)}</small>` : ''))}
      ${row('Parties',       htmlEsc(c.parties ?? '—'))}
      ${row('Agreed Amount', `<span style="color:#fbbf24;font-weight:700;">${amount}</span>`)}
    </div>
    ${c.description ? `
    <div style="margin-top:14px;">
      <div style="font-size:11px;color:rgba(255,255,255,0.35);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;">Incident Description</div>
      <div style="background:rgba(255,255,255,0.04);border-radius:10px;padding:12px;font-size:13px;line-height:1.6;color:rgba(255,255,255,0.75);">${htmlEsc(c.description)}</div>
    </div>` : ''}
    ${c.terms ? `
    <div style="margin-top:14px;">
      <div style="font-size:11px;color:rgba(255,255,255,0.35);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;">Agreed Terms</div>
      <div style="background:rgba(255,255,255,0.04);border-radius:10px;padding:12px;font-size:13px;line-height:1.6;color:rgba(255,255,255,0.75);">${htmlEsc(c.terms)}</div>
    </div>` : ''}
  `;

            detailModal.show();
        }

        function htmlEsc(str) {
            if (!str) return '—';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
    </script>
</body>

</html>