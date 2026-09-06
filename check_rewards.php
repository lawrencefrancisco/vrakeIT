<?php
require 'includes/db.php';
$db = getDB();
$stmt = $db->query("SELECT * FROM merchant_rewards");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
