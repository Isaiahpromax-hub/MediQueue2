<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
header('Content-Type: application/json');

$entries = db()->fetchAll(
    "SELECT q.id, q.queue_number, q.priority, q.vitals_json, q.status, q.joined_at,
            TIMESTAMPDIFF(MINUTE, q.joined_at, NOW()) AS queued_minutes,
            p.first_name, p.last_name, p.blood_group, p.allergies, p.emergency_contact, p.emergency_phone,
            s.name AS service_name
     FROM queue_entries q
     JOIN patients p ON q.patient_id = p.id
     JOIN services s ON q.service_id = s.id
     WHERE q.queue_date = CURDATE() AND q.priority IN ('Urgent','Emergency') AND q.status IN ('Waiting','Called','Serving')
     ORDER BY FIELD(q.priority, 'Emergency', 'Urgent'), q.joined_at ASC LIMIT 20"
);

foreach ($entries as &$e) {
    $e['queued_minutes'] = (int) ($e['queued_minutes'] ?? 0);
    $e['vitals'] = $e['vitals_json'] ? json_decode($e['vitals_json'], true) : null;
    unset($e['vitals_json']);
}
unset($e);

echo json_encode(['emergencies' => $entries]);