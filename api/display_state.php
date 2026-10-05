<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

$services = db()->fetchAll("SELECT id, name, department, estimated_duration FROM services WHERE is_active = 1 ORDER BY id");
$result = [];

foreach ($services as $s) {
    $current = db()->fetch(
        "SELECT q.queue_number, q.priority, q.status, p.first_name, p.last_name
         FROM queue_entries q JOIN patients p ON q.patient_id = p.id
         WHERE q.service_id = ? AND q.queue_date = CURDATE() AND q.status IN ('Called','Serving')
         ORDER BY FIELD(q.status, 'Serving', 'Called'), q.position ASC LIMIT 1",
        [$s['id']]
    );
    $next = db()->fetch(
        "SELECT q.queue_number, q.priority, p.first_name, p.last_name
         FROM queue_entries q JOIN patients p ON q.patient_id = p.id
         WHERE q.service_id = ? AND q.queue_date = CURDATE() AND q.status = 'Waiting'
         ORDER BY FIELD(q.priority, 'Emergency', 'Urgent', 'Normal'), q.position ASC LIMIT 1",
        [$s['id']]
    );
    $emergencyWaiting = db()->fetch(
        "SELECT COUNT(*) AS c FROM queue_entries WHERE service_id = ? AND queue_date = CURDATE() AND status = 'Waiting' AND priority = 'Emergency'",
        [$s['id']]
    )['c'];
    $waitingCount = db()->fetch(
        "SELECT COUNT(*) AS c FROM queue_entries WHERE service_id = ? AND queue_date = CURDATE() AND status = 'Waiting'",
        [$s['id']]
    )['c'];
    $waitingList = db()->fetchAll(
        "SELECT q.queue_number, q.priority, p.first_name, p.last_name
         FROM queue_entries q JOIN patients p ON q.patient_id = p.id
         WHERE q.service_id = ? AND q.queue_date = CURDATE() AND q.status = 'Waiting'
         ORDER BY FIELD(q.priority, 'Emergency', 'Urgent', 'Normal'), q.position ASC",
        [$s['id']]
    );

    if (!$current && !$next && $waitingCount === 0) {
        continue;
    }

    $result[] = [
        'service_id' => (int) $s['id'],
        'service_name' => $s['name'],
        'department' => $s['department'],
        'estimated_duration' => (int) ($s['estimated_duration'] ?? 0),
        'currently_serving' => $current ? [
            'queue_number' => $current['queue_number'],
            'patient_name' => $current['first_name'] . ' ' . $current['last_name'],
            'priority' => $current['priority'],
            'status' => $current['status'],
        ] : null,
        'next_patient' => $next ? [
            'queue_number' => $next['queue_number'],
            'patient_name' => $next['first_name'] . ' ' . $next['last_name'],
            'priority' => $next['priority'],
        ] : null,
        'emergency_waiting' => (int) $emergencyWaiting,
        'waiting_count' => (int) $waitingCount,
        'waiting' => array_map(function ($w) {
            return [
                'queue_number' => $w['queue_number'],
                'patient_name' => $w['first_name'] . ' ' . $w['last_name'],
                'priority' => $w['priority'],
            ];
        }, $waitingList),
    ];
}

echo json_encode(['updated_at' => date('H:i:s'), 'services' => $result]);