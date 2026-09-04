<?php
require_once dirname(__FILE__) . '/../config/config.php';
require_once dirname(__FILE__) . '/../includes/db.php';

$sql = file_get_contents(dirname(__FILE__) . '/ocr_verification_migration.sql');
$db  = getDB();

$statements = array_filter(array_map('trim', explode(';', $sql)));
$ok  = 0;
$err = 0;

foreach ($statements as $q) {
    if (!$q) continue;
    try {
        $db->exec($q);
        echo "OK:  " . substr(preg_replace('/\s+/', ' ', $q), 0, 80) . "\n";
        $ok++;
    } catch (Exception $e) {
        echo "ERR: " . $e->getMessage() . "\n";
        echo "  -> " . substr(preg_replace('/\s+/', ' ', $q), 0, 80) . "\n";
        $err++;
    }
}

echo "\n======================\n";
echo "Migration complete! OK: $ok  Errors: $err\n";
