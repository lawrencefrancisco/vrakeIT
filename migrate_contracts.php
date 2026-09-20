<?php
/**
 * migrate_contracts.php
 * Adds all new columns needed for the reworked settlement contract system.
 * Safe to run multiple times (uses IF NOT EXISTS).
 */
require 'includes/db.php';
$db = getDB();

$columns = [
    // Link to incident report
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `report_id`           INT(11)        DEFAULT NULL",
    // What happened
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `incident_type`       VARCHAR(60)    DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `versions_agree`      TINYINT(1)     DEFAULT NULL COMMENT '1=both agree, 0=each writes own version'",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `version_p1`          TEXT           DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `version_p2`          TEXT           DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `fault`               VARCHAR(30)    DEFAULT NULL COMMENT 'party1|party2|shared|undetermined'",
    // Party 1 extended info
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `party1_user_id`      INT(11)        DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `party1_address`      VARCHAR(255)   DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `party1_license`      VARCHAR(60)    DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `party1_plate`        VARCHAR(30)    DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `party1_vehicle_type` VARCHAR(60)    DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `party1_insurance`    VARCHAR(120)   DEFAULT NULL",
    // Party 2 extended info
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `party2_user_id`      INT(11)        DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `party2_email`        VARCHAR(120)   DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `party2_address`      VARCHAR(255)   DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `party2_license`      VARCHAR(60)    DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `party2_plate`        VARCHAR(30)    DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `party2_vehicle_type` VARCHAR(60)    DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `party2_insurance`    VARCHAR(120)   DEFAULT NULL",
    // Witness
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `witness_name`        VARCHAR(150)   DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `witness_contact`     VARCHAR(60)    DEFAULT NULL",
    // Damage evidence
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `damage_desc_p1`      TEXT           DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `damage_cost_p1`      DECIMAL(12,2)  DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `damage_desc_p2`      TEXT           DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `damage_cost_p2`      DECIMAL(12,2)  DEFAULT NULL",
    // Agreement terms
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `resolution_type`     VARCHAR(60)    DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `who_pays`            VARCHAR(30)    DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `payment_method`      VARCHAR(30)    DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `payment_schedule`    VARCHAR(20)    DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `payment_deadline`    DATE           DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `escalation_clause`   TEXT           DEFAULT NULL",
    // Consent and signing
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `consent_voluntary`      TINYINT(1) DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `consent_hidden_damage`  TINYINT(1) DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `p1_signed_at`        DATETIME       DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `p2_signed_at`        DATETIME       DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `p2_ip_address`       VARCHAR(45)    DEFAULT NULL",
    // Invite flow
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `invite_token`        VARCHAR(64)    DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `invite_sent_at`      DATETIME       DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `invite_expires_at`   DATETIME       DEFAULT NULL",
    // Auto-save
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `draft_data`          LONGTEXT       DEFAULT NULL",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `type`                VARCHAR(30)    DEFAULT 'contract'",
    "ALTER TABLE `contracts` ADD COLUMN IF NOT EXISTS `updated_at`          TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    // Status extension (settled|not_settled|disputed → also: draft|waiting|signed|fulfilled)
];

$errors = [];
$ok     = [];

foreach ($columns as $sql) {
    try {
        $db->exec($sql);
        preg_match('/ADD COLUMN IF NOT EXISTS `(\w+)`/', $sql, $m);
        $ok[] = $m[1] ?? substr($sql, 0, 60);
    } catch (PDOException $e) {
        $errors[] = $e->getMessage();
    }
}

// Update status ENUM to include new values — use MODIFY instead of IF NOT EXISTS
try {
    $db->exec("ALTER TABLE `contracts` MODIFY COLUMN `status` VARCHAR(30) DEFAULT 'draft'");
    $ok[] = 'status column updated to VARCHAR(30) default draft';
} catch (PDOException $e) {
    $errors[] = 'status: ' . $e->getMessage();
}

// Unique index on invite_token
try {
    $db->exec("ALTER TABLE `contracts` ADD UNIQUE INDEX IF NOT EXISTS `idx_invite_token` (`invite_token`)");
    $ok[] = 'idx_invite_token unique index';
} catch (PDOException $e) {
    // MariaDB may not support IF NOT EXISTS on index
    try {
        $db->exec("CREATE UNIQUE INDEX `idx_invite_token` ON `contracts`(`invite_token`)");
        $ok[] = 'idx_invite_token created';
    } catch (PDOException $e2) {
        // Already exists
    }
}

echo "<pre style='font-family:monospace;font-size:13px;padding:20px;'>";
echo "<b style='color:green'>✅ Processed (" . count($ok) . "):</b>\n";
foreach ($ok as $col) echo "  + $col\n";
if ($errors) {
    echo "\n<b style='color:red'>❌ Errors (" . count($errors) . "):</b>\n";
    foreach ($errors as $err) echo "  - $err\n";
} else {
    echo "\n<b style='color:green'>All done — no errors.</b>\n";
}
echo "</pre>";
