<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];
$count = db()->fetch("SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0", [$userId])['c'] ?? 0;

echo json_encode(['count' => (int) $count]);