<?php
/**
 * fix_reports_table.php
 * One-time migration: adds columns that exist in the schema but are missing
 * from the live `reports` table.  Safe to run multiple times (uses IF NOT EXISTS).
 */
require 'includes/db.php';
$db = getDB();

$columns = [
    "ALTER TABLE `reports` ADD COLUMN IF NOT EXISTS `flow_type`          VARCHAR(50)   DEFAULT NULL",
    "ALTER TABLE `reports` ADD COLUMN IF NOT EXISTS `is_injured`         TINYINT(1)    DEFAULT NULL",
    "ALTER TABLE `reports` ADD COLUMN IF NOT EXISTS `location_address`   TEXT          DEFAULT NULL",
    "ALTER TABLE `reports` ADD COLUMN IF NOT EXISTS `incident_date`      DATE          DEFAULT NULL",
    "ALTER TABLE `reports` ADD COLUMN IF NOT EXISTS `incident_time`      TIME          DEFAULT NULL",
    "ALTER TABLE `reports` ADD COLUMN IF NOT EXISTS `has_other_parties`  TINYINT(1)    DEFAULT NULL",
    "ALTER TABLE `reports` ADD COLUMN IF NOT EXISTS `weather_condition`  VARCHAR(50)   DEFAULT NULL",
    "ALTER TABLE `reports` ADD COLUMN IF NOT EXISTS `road_condition`     VARCHAR(50)   DEFAULT NULL",
    "ALTER TABLE `reports` ADD COLUMN IF NOT EXISTS `insurance_type`     VARCHAR(50)   DEFAULT NULL",
    "ALTER TABLE `reports` ADD COLUMN IF NOT EXISTS `media_urls`         TEXT          DEFAULT NULL",
    "ALTER TABLE `reports` ADD COLUMN IF NOT EXISTS `event_details`      TEXT          DEFAULT NULL",
    "ALTER TABLE `reports` ADD COLUMN IF NOT EXISTS `enforcer_type`      VARCHAR(50)   DEFAULT NULL",
    "ALTER TABLE `reports` ADD COLUMN IF NOT EXISTS `enforcer_documented` VARCHAR(20)  DEFAULT NULL",
    "ALTER TABLE `reports` ADD COLUMN IF NOT EXISTS `emergency_services` TEXT          DEFAULT NULL",
    "ALTER TABLE `reports` ADD COLUMN IF NOT EXISTS `is_safe`            TINYINT(1)    DEFAULT NULL",
    "ALTER TABLE `reports` ADD COLUMN IF NOT EXISTS `location_lat`       DECIMAL(10,8) DEFAULT NULL",
    "ALTER TABLE `reports` ADD COLUMN IF NOT EXISTS `location_lng`       DECIMAL(11,8) DEFAULT NULL",
    "ALTER TABLE `reports` ADD COLUMN IF NOT EXISTS `other_parties_present` TINYINT(1) DEFAULT NULL",
    "ALTER TABLE `reports` ADD COLUMN IF NOT EXISTS `damage_category`    VARCHAR(100)  DEFAULT NULL",
    "ALTER TABLE `reports` ADD COLUMN IF NOT EXISTS `admin_notes`        TEXT          DEFAULT NULL",
    "ALTER TABLE `reports` ADD COLUMN IF NOT EXISTS `assigned_enforcer_id` INT(11)     DEFAULT NULL",
    "ALTER TABLE `reports` ADD COLUMN IF NOT EXISTS `resolution_status`  VARCHAR(50)   DEFAULT 'unresolved'",
    "ALTER TABLE `reports` ADD COLUMN IF NOT EXISTS `parties`            VARCHAR(20)   DEFAULT NULL",
];

$errors = [];
$ok     = [];

foreach ($columns as $sql) {
    try {
        $db->exec($sql);
        // extract column name for a nicer output
        preg_match('/ADD COLUMN IF NOT EXISTS `(\w+)`/', $sql, $m);
        $ok[] = $m[1] ?? $sql;
    } catch (PDOException $e) {
        $errors[] = $e->getMessage();
    }
}

echo "<pre style='font-family:monospace;font-size:14px;'>";
echo "<b>✅ Processed columns (" . count($ok) . "):</b>\n";
foreach ($ok as $col) echo "  + $col\n";

if ($errors) {
    echo "\n<b style='color:red'>❌ Errors (" . count($errors) . "):</b>\n";
    foreach ($errors as $err) echo "  - $err\n";
} else {
    echo "\n<b style='color:green'>All done — no errors! You can now delete this file.</b>\n";
}
echo "</pre>";
?>
