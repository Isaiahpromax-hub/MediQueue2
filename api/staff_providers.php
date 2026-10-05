<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if (!isLoggedIn() || !in_array(getRole(), ['receptionist', 'admin', 'doctor', 'nurse'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$doctors = db()->fetchAll(
    "SELECT d.id, d.user_id, d.first_name, d.last_name, d.type, d.is_available,
            (SELECT GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ')
             FROM doctor_services ds JOIN services s ON s.id = ds.service_id
             WHERE ds.doctor_id = d.id) AS services
     FROM doctors d
     JOIN users u ON u.id = d.user_id AND u.is_active = 1
     WHERE EXISTS (SELECT 1 FROM doctor_services ds WHERE ds.doctor_id = d.id)
     ORDER BY d.type DESC, d.first_name ASC"
);

$providers = [];
foreach ($doctors as $d) {
    $serving = db()->fetchAll(
        "SELECT q.queue_number, q.status, p.first_name, p.last_name
         FROM queue_entries q JOIN patients p ON p.id = q.patient_id
         WHERE q.queue_date = CURDATE() AND q.doctor_id = ? AND q.status IN ('Called','Serving')
         ORDER BY FIELD(q.status,'Serving','Called'), FIELD(q.priority,'Emergency','Urgent','Normal'), q.position ASC",
        [$d['id']]
    );

    $waiting = db()->fetchAll(
        "SELECT q.queue_number, p.first_name, p.last_name
         FROM queue_entries q JOIN patients p ON p.id = q.patient_id
         WHERE q.queue_date = CURDATE() AND q.doctor_id = ? AND q.status = 'Waiting'
         ORDER BY FIELD(q.priority,'Emergency','Urgent','Normal'), q.position ASC",
        [$d['id']]
    );

    $served = db()->fetch(
        "SELECT COUNT(*) AS c FROM queue_entries WHERE queue_date = CURDATE() AND status = 'Served' AND doctor_id = ?",
        [$d['id']]
    )['c'];

    $skipped = db()->fetch(
        "SELECT COUNT(*) AS c FROM queue_entries WHERE queue_date = CURDATE() AND status = 'Skipped' AND doctor_id = ?",
        [$d['id']]
    )['c'];

    $providers[] = [
        'id' => (int) $d['id'],
        'name' => providerDisplayName($d),
        'type' => $d['type'],
        'is_available' => (bool) $d['is_available'],
        'services' => $d['services'] ?: '—',
        'serving' => array_map(function ($r) {
            return $r['queue_number'] . ' · ' . $r['first_name'] . ' ' . $r['last_name'];
        }, $serving),
        'waiting' => array_map(function ($r) {
            return $r['queue_number'] . ' · ' . $r['first_name'] . ' ' . $r['last_name'];
        }, $waiting),
        'served_count' => (int) $served,
        'skipped_count' => (int) $skipped,
    ];
}

echo json_encode(['ok' => true, 'providers' => $providers]);