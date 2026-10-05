<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

$phone = preg_replace('/[^0-9]/', '', $_GET['phone'] ?? '');
$queueNo = trim($_GET['queue'] ?? '');

if ($phone === '' && $queueNo === '') {
    echo json_encode(['found' => false, 'message' => 'Enter a phone number or queue number.']);
    exit;
}

$patient = null;
if ($phone !== '') {
    $patient = db()->fetch(
        "SELECT id, patient_code, first_name, last_name, phone FROM patients WHERE REPLACE(REPLACE(phone, ' ', ''), '-', '') = ?",
        [$phone]
    );
}

$entry = null;
if ($queueNo !== '') {
    $entry = db()->fetch(
        "SELECT q.*, s.name AS service_name, s.estimated_duration, p.id AS patient_pk, p.first_name, p.last_name
         FROM queue_entries q
         JOIN services s ON q.service_id = s.id
         JOIN patients p ON q.patient_id = p.id
         WHERE q.queue_number = ?",
        [$queueNo]
    );
}

if (!$patient && !$entry) {
    echo json_encode(['found' => false, 'message' => 'No matching record found. Check the number and try again.']);
    exit;
}

$queue = [];
$appointmentsToday = [];

$useId = $patient ? $patient['id'] : ($entry ? $entry['patient_pk'] : 0);
$estDuration = 0;

foreach (db()->fetchAll(
    "SELECT q.queue_number, q.status, q.position, s.name AS service_name, s.estimated_duration
     FROM queue_entries q JOIN services s ON q.service_id = s.id
     WHERE q.patient_id = ? AND q.queue_date = CURDATE()
     ORDER BY FIELD(q.status, 'Serving', 'Called', 'Waiting'), q.position ASC LIMIT 5",
    [$useId]
) as $q) {
    $d = intval($q['estimated_duration'] ?? 0);
    $estDuration = $d ?: $estDuration;
    $queue[] = [
        'queue_number' => $q['queue_number'],
        'service_name' => $q['service_name'],
        'status' => $q['status'],
        'est_minutes' => $d > 0 && $q['status'] === 'Waiting' ? (max(0, intval($q['position']) - 1) * $d) : 0,
    ];
}

foreach (db()->fetchAll(
    "SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.status, s.name AS service_name
     FROM appointments a JOIN services s ON a.service_id = s.id
     WHERE a.patient_id = ? AND a.appointment_date >= CURDATE() AND a.status NOT IN ('Cancelled', 'No Show', 'Completed')
     ORDER BY a.appointment_date, a.appointment_time LIMIT 5",
    [$useId]
) as $a) {
    $appointmentsToday[] = [
        'appointment_id' => $a['appointment_id'],
        'service_name' => $a['service_name'],
        'date' => $a['appointment_date'],
        'time' => $a['appointment_time'],
        'status' => $a['status'],
    ];
}

echo json_encode([
    'found' => true,
    'patient' => $patient ? [
        'code' => $patient['patient_code'],
        'name' => $patient['first_name'] . ' ' . $patient['last_name'],
    ] : ($entry ? ['code' => '', 'name' => $entry['first_name'] . ' ' . $entry['last_name']] : null),
    'queue' => $queue,
    'appointments' => $appointmentsToday,
]);