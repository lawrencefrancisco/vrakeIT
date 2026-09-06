<?php
// Simulate replenish request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SESSION = ['merchant_id' => 1];
$_POST = [
    'reward_id' => 1,
    'add_quantity' => 1,
    'add_duration' => 0
];

require 'includes/db.php';
$db = getDB();

$merchant_id  = 1;
$reward_id    = 1;
$add_quantity = 1;
$add_duration = 0;

$check = $db->prepare("SELECT id, expires_at FROM merchant_rewards WHERE id = ? AND merchant_id = ?");
$check->execute([$reward_id, $merchant_id]);
$reward = $check->fetch();
print_r($reward);

$updates = [];
$params  = [];
if ($add_quantity > 0) {
    $updates[] = "quantity = quantity + ?";
    $params[]  = $add_quantity;
}
if ($add_duration > 0) {
    $base       = ($reward['expires_at'] && strtotime($reward['expires_at']) > time()) ? $reward['expires_at'] : 'now';
    $new_expiry = date('Y-m-d H:i:s', strtotime("+{$add_duration} days", strtotime($base)));
    $updates[]  = "expires_at = ?";
    $params[]   = $new_expiry;
}

print_r($updates);
print_r($params);

$params[] = $reward_id;
$sql = "UPDATE merchant_rewards SET " . implode(', ', $updates) . " WHERE id = ?";
echo $sql . "\n";
$db->prepare($sql)->execute($params);

echo "Success\n";
