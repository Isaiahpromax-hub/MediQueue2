<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$entryId = intval($_GET['id'] ?? 0);
$userId = $_SESSION['user_id'];

$patient = db()->fetch("SELECT id FROM patients WHERE user_id = ?", [$userId]);
$entry = $patient ? db()->fetch("SELECT * FROM queue_entries WHERE id = ? AND patient_id = ?", [$entryId, $patient['id']]) : null;
if (!$entry) {
    echo json_encode(['error' => 'Not found']);
    exit;
}

$serviceId = $entry['service_id'];
$serviceEstimate = db()->fetch("SELECT estimated_duration FROM services WHERE id = ?", [$serviceId])['estimated_duration'] ?? 15;

$currentlyServing = db()->fetch(
    "SELECT queue_number, position FROM queue_entries WHERE service_id = ? AND queue_date = CURDATE() AND status IN ('Called','Serving') ORDER BY position ASC LIMIT 1",
    [$serviceId]
);

$peopleAhead = 0;
if ($entry['status'] === 'Waiting') {
    $ahead = db()->fetch(
        "SELECT COUNT(*) as count FROM queue_entries WHERE service_id = ? AND queue_date = CURDATE() AND status = 'Waiting' AND position < ?",
        [$serviceId, $entry['position']]
    );
    $peopleAhead = $ahead['count'] ?? 0;
}

$waitingCount = db()->fetch("SELECT COUNT(*) as c FROM queue_entries WHERE service_id = ? AND queue_date = CURDATE() AND status = 'Waiting'", [$serviceId])['c'];
$servedCount = db()->fetch("SELECT COUNT(*) as c FROM queue_entries WHERE service_id = ? AND queue_date = CURDATE() AND status = 'Served'", [$serviceId])['c'];

echo json_encode([
    'my_status' => $entry['status'],
    'currently_serving' => $currentlyServing['queue_number'] ?? '---',
    'people_ahead' => $peopleAhead,
    'estimated_wait' => $peopleAhead * $serviceEstimate,
    'waiting_count' => $waitingCount,
    'served_count' => $servedCount,
]);
