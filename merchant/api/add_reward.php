<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/merchant_auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
startSecureSession();
requireMerchantLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Method not allowed.');

$merchant_id     = (int)$_SESSION['merchant_id'];
$reward_name     = trim($_POST['reward_name']     ?? '');
$description     = trim($_POST['description']     ?? '');
$points_required = (int)($_POST['points_required'] ?? 0);
$quantity        = (int)($_POST['quantity']         ?? 0);
$duration_days   = (int)($_POST['duration_days']   ?? 0);

if (empty($reward_name))       jsonResponse(false, 'Reward name is required.');
if ($points_required < 10)     jsonResponse(false, 'Points required must be at least 10.');
if ($quantity < 1)             jsonResponse(false, 'Stock quantity must be at least 1.');
if ($duration_days < 1)        jsonResponse(false, 'Valid duration must be at least 1 day.');

$category = trim($_POST['category'] ?? 'General');
$expires_at = date('Y-m-d H:i:s', strtotime("+{$duration_days} days"));

$db   = getDB();
$stmt = $db->prepare("INSERT INTO merchant_rewards (merchant_id, reward_name, description, points_required, quantity, expires_at, category) VALUES (?,?,?,?,?,?,?)");
$stmt->execute([$merchant_id, $reward_name, $description, $points_required, $quantity, $expires_at, $category]);

auditLog(null, 'merchant_reward_added', "Reward: {$reward_name} by merchant #{$merchant_id}");
jsonResponse(true, 'Reward added successfully!');
