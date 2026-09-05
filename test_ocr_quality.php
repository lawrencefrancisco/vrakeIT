<?php
// ======================================================================
// test_ocr_quality.php  — VrakeIT OCR Diagnostic Tool
// Run at: http://localhost/vrakeit/test_ocr_quality.php
// DELETE THIS FILE AFTER TESTING (security risk in production)
// ======================================================================

// No auth — for local testing only
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    die('Forbidden — local access only.');
}

require_once __DIR__ . '/includes/TesseractProvider.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VrakeIT — OCR Diagnostic</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #0f1117; color: #e2e8f0; min-height: 100vh; padding: 2rem; }
        h1 { font-size: 1.6rem; margin-bottom: .4rem; color: #7c3aed; }
        h2 { font-size: 1.1rem; margin: 1.5rem 0 .5rem; color: #a78bfa; border-bottom: 1px solid #2d2d3f; padding-bottom: .3rem; }
        .card { background: #1a1b2e; border: 1px solid #2d2d3f; border-radius: 10px; padding: 1.2rem; margin-bottom: 1rem; }
        .badge { display: inline-block; padding: .15rem .6rem; border-radius: 999px; font-size: .75rem; font-weight: 600; margin-right: .4rem; }
        .ok   { background: #166534; color: #bbf7d0; }
        .warn { background: #854d0e; color: #fef3c7; }
        .fail { background: #7f1d1d; color: #fecaca; }
        table { width: 100%; border-collapse: collapse; font-size: .88rem; }
        td, th { padding: .5rem .7rem; border: 1px solid #2d2d3f; }
        th { background: #1e1f35; color: #a78bfa; text-align: left; }
        pre { background: #0d0e1a; padding: 1rem; border-radius: 8px; font-size: .78rem;
              white-space: pre-wrap; word-break: break-word; max-height: 300px; overflow-y: auto;
              border: 1px solid #2d2d3f; color: #94a3b8; }
        .bar-wrap { background: #2d2d3f; border-radius: 999px; height: 8px; width: 200px; display: inline-block; vertical-align: middle; }
        .bar { height: 8px; border-radius: 999px; }
        form { margin-top: 1rem; }
        input[type=file] { color: #e2e8f0; }
        button { background: #7c3aed; color: #fff; border: none; padding: .5rem 1.2rem;
                 border-radius: 6px; cursor: pointer; font-size: .9rem; margin-top: .5rem; }
        button:hover { background: #6d28d9; }
        .sub { color: #64748b; font-size: .82rem; }
    </style>
</head>
<body>
<h1>🔬 VrakeIT OCR Diagnostic</h1>
<p class="sub">Tests Tesseract OCR quality and system setup. Local access only.</p>

<?php
// ── System Checks
$tessBin   = TesseractProvider::findBinary();
$magick    = 'C:\\Program Files\\ImageMagick-7.1.2-Q16-HDRI\\magick.exe';
$magickOk  = @is_executable($magick);

// Dynamic magick search
if (!$magickOk) {
    $out = []; exec('where magick 2>NUL', $out);
    if (!empty($out[0]) && @is_executable(trim($out[0]))) { $magick = trim($out[0]); $magickOk = true; }
}

$tessVersion = '';
if ($tessBin) {
    exec(escapeshellarg($tessBin) . ' --version 2>&1', $vLines);
    $tessVersion = $vLines[0] ?? '';
}

$langs = [];
if ($tessBin) {
    exec(escapeshellarg($tessBin) . ' --list-langs 2>&1', $lLines);
    foreach ($lLines as $l) { if (trim($l) && !str_contains($l, 'List of')) $langs[] = trim($l); }
}
$hasEng = in_array('eng', $langs);
$hasFil = in_array('fil', $langs);
?>

<div class="card">
    <h2>⚙️ System Status</h2>
    <table>
        <tr><th>Component</th><th>Status</th><th>Detail</th></tr>
        <tr>
            <td>Tesseract Binary</td>
            <td><?= $tessBin ? '<span class="badge ok">✓ Found</span>' : '<span class="badge fail">✗ Missing</span>' ?></td>
            <td><?= htmlspecialchars($tessBin ?? 'Not found') ?></td>
        </tr>
        <tr>
            <td>Tesseract Version</td>
            <td><?= $tessVersion ? '<span class="badge ok">✓ OK</span>' : '<span class="badge warn">?</span>' ?></td>
            <td><?= htmlspecialchars($tessVersion ?: 'Unknown') ?></td>
        </tr>
        <tr>
            <td>Language: <code>eng</code> (English — LSTM Best)</td>
            <td><?= $hasEng ? '<span class="badge ok">✓ Installed</span>' : '<span class="badge fail">✗ Missing</span>' ?></td>
            <td><?php
                $engFile = 'C:\\Program Files\\Tesseract-OCR\\tessdata\\eng.traineddata';
                echo file_exists($engFile) ? round(filesize($engFile)/1024/1024, 1) . ' MB' : 'Not found';
            ?></td>
        </tr>
        <tr>
            <td>Language: <code>fil</code> (Filipino)</td>
            <td><?= $hasFil ? '<span class="badge ok">✓ Installed</span>' : '<span class="badge warn">✗ Missing</span>' ?></td>
            <td><?php
                $filFile = 'C:\\Program Files\\Tesseract-OCR\\tessdata\\fil.traineddata';
                echo file_exists($filFile) ? round(filesize($filFile)/1024/1024, 1) . ' MB' : 'Not found';
            ?></td>
        </tr>
        <tr>
            <td>ImageMagick (pre-processing)</td>
            <td><?= $magickOk ? '<span class="badge ok">✓ Installed</span>' : '<span class="badge warn">✗ Not found</span>' ?></td>
            <td><?php
                if ($magickOk) {
                    $mv = []; exec(escapeshellarg($magick) . ' --version 2>&1', $mv);
                    echo htmlspecialchars($mv[0] ?? $magick);
                } else echo 'ImageMagick not found — pre-processing disabled';
            ?></td>
        </tr>
        <tr>
            <td>OEM Mode</td>
            <td><span class="badge ok">✓ LSTM Only</span></td>
            <td><code>--oem 1</code> (most accurate)</td>
        </tr>
        <tr>
            <td>PSM Strategy</td>
            <td><span class="badge ok">✓ Dual Pass</span></td>
            <td><code>--psm 6</code> + <code>--psm 3</code> — best result wins</td>
        </tr>
    </table>
</div>

<?php if (!empty($_FILES['id_test']['tmp_name'])): ?>
<?php
// ── Run OCR on uploaded image
$tmpPath = $_FILES['id_test']['tmp_name'];
$ext = pathinfo($_FILES['id_test']['name'], PATHINFO_EXTENSION);
$testImg = sys_get_temp_dir() . '/ocr_test_' . time() . '.' . $ext;
move_uploaded_file($tmpPath, $testImg);

$start  = microtime(true);
$result = TesseractProvider::extractFromImage($testImg);
$elapsed = round((microtime(true) - $start) * 1000);

@unlink($testImg);

$conf    = $result['confidence'] ?? 0;
$confPct = round($conf * 100);
$barColor = $confPct >= 70 ? '#22c55e' : ($confPct >= 45 ? '#f59e0b' : '#ef4444');
?>
<div class="card">
    <h2>📊 OCR Results — <?= htmlspecialchars($_FILES['id_test']['name']) ?></h2>
    <table>
        <tr><th>Field</th><th>Extracted Value</th><th>Status</th></tr>
        <tr>
            <td>Document Type</td>
            <td><?= htmlspecialchars($result['doc_type'] ?: '—') ?></td>
            <td><?= $result['doc_type'] ? '<span class="badge ok">✓</span>' : '<span class="badge warn">Not detected</span>' ?></td>
        </tr>
        <tr>
            <td>Is Government ID?</td>
            <td><?= $result['is_gov_id'] ? 'Yes' : 'No' ?></td>
            <td><?= $result['is_gov_id'] ? '<span class="badge ok">✓</span>' : '<span class="badge fail">✗</span>' ?></td>
        </tr>
        <tr>
            <td>Full Name</td>
            <td><?= htmlspecialchars($result['name'] ?: '—') ?></td>
            <td><?= $result['name'] ? '<span class="badge ok">✓</span>' : '<span class="badge fail">✗ Not found</span>' ?></td>
        </tr>
        <tr>
            <td>Date of Birth</td>
            <td><?= htmlspecialchars($result['dob'] ?: '—') ?></td>
            <td><?= $result['dob'] ? '<span class="badge ok">✓</span>' : '<span class="badge fail">✗ Not found</span>' ?></td>
        </tr>
        <tr>
            <td>Document Number</td>
            <td><?= htmlspecialchars($result['doc_number'] ?: '—') ?></td>
            <td><?= $result['doc_number'] ? '<span class="badge ok">✓</span>' : '<span class="badge warn">Not found</span>' ?></td>
        </tr>
        <tr>
            <td>Expiry Date</td>
            <td><?= htmlspecialchars($result['expiry'] ?: '—') ?></td>
            <td><?= $result['expiry'] ? '<span class="badge ok">✓</span>' : '<span class="badge warn">N/A or not found</span>' ?></td>
        </tr>
        <tr>
            <td>Confidence Score</td>
            <td>
                <?= $confPct ?>%
                &nbsp;<span class="bar-wrap"><span class="bar" style="width:<?= $confPct ?>%;background:<?= $barColor ?>"></span></span>
            </td>
            <td><?= $confPct >= 70 ? '<span class="badge ok">High</span>' : ($confPct >= 45 ? '<span class="badge warn">Medium</span>' : '<span class="badge fail">Low</span>') ?></td>
        </tr>
        <tr>
            <td>Image Readable?</td>
            <td><?= $result['readable'] ? 'Yes' : 'No' ?></td>
            <td><?= $result['readable'] ? '<span class="badge ok">✓</span>' : '<span class="badge fail">✗</span>' ?></td>
        </tr>
        <tr>
            <td>Processing Time</td>
            <td><?= $elapsed ?> ms</td>
            <td><span class="badge <?= $elapsed < 5000 ? 'ok' : 'warn' ?>"><?= $elapsed < 5000 ? 'Fast' : 'Slow' ?></span></td>
        </tr>
        <?php if ($result['error']): ?>
        <tr>
            <td>Error</td>
            <td colspan="2"><span class="badge fail"><?= htmlspecialchars($result['error']) ?></span></td>
        </tr>
        <?php endif; ?>
    </table>

    <h2>📄 Raw Tesseract Text Output</h2>
    <pre><?= htmlspecialchars($result['raw_text'] ?: '(empty)') ?></pre>
</div>
<?php endif; ?>

<div class="card">
    <h2>🧪 Test Your ID Image</h2>
    <p class="sub" style="margin-bottom:.8rem">Upload any Philippine government-issued ID (JPG/PNG) to test OCR accuracy. The image is processed and immediately deleted — not stored.</p>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="id_test" accept="image/jpeg,image/png,image/webp" required><br>
        <button type="submit">Run OCR Test</button>
    </form>
</div>

<p class="sub" style="margin-top:1.5rem">⚠️ Delete <code>test_ocr_quality.php</code> from your server when done testing.</p>
</body>
</html>
