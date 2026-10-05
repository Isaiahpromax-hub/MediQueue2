<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$serviceId = intval($_GET['service_id'] ?? 0);
if (!$serviceId) {
    echo json_encode(['error' => 'No service ID']);
    exit;
}

$doctorNameExpr = "CASE WHEN q.doctor_id IS NULL THEN NULL WHEN d.type = 'nurse' THEN CONCAT('Nurse ', CASE WHEN d.first_name LIKE 'Dr. %' THEN SUBSTRING(d.first_name, 5) ELSE d.first_name END, ' ', d.last_name) ELSE CONCAT('Dr. ', CASE WHEN d.first_name LIKE 'Dr. %' THEN SUBSTRING(d.first_name, 5) ELSE d.first_name END, ' ', d.last_name) END";
$doctorJoin = "LEFT JOIN doctors d ON q.doctor_id = d.id";

$active = db()->fetchAll(
    "SELECT q.queue_number, q.id, q.status, q.service_id, q.doctor_id, q.priority, $doctorNameExpr AS doctor_name,
            p.first_name, p.last_name
     FROM queue_entries q
     JOIN patients p ON q.patient_id = p.id
     $doctorJoin
     WHERE q.service_id = ? AND q.queue_date = CURDATE() AND q.status IN ('Called','Serving')
     ORDER BY FIELD(q.priority, 'Emergency', 'Urgent', 'Normal'), FIELD(q.status, 'Serving', 'Called'), q.position ASC LIMIT 20",
    [$serviceId]
);

$nextPatient = db()->fetch(
    "SELECT q.queue_number, q.priority, p.first_name, p.last_name
     FROM queue_entries q JOIN patients p ON q.patient_id = p.id
     WHERE q.service_id = ? AND q.queue_date = CURDATE() AND q.status = 'Waiting'
     ORDER BY FIELD(q.priority, 'Emergency', 'Urgent', 'Normal'), q.position ASC LIMIT 1",
    [$serviceId]
);

$waiting = db()->fetchAll(
    "SELECT q.queue_number, q.id, q.doctor_id, q.priority, q.status AS queue_status, $doctorNameExpr AS doctor_name,
            p.first_name, p.last_name, s.name as service_name
     FROM queue_entries q
     JOIN patients p ON q.patient_id = p.id
     JOIN services s ON q.service_id = s.id
     $doctorJoin
     WHERE q.service_id = ? AND q.queue_date = CURDATE() AND q.status = 'Waiting'
     ORDER BY FIELD(q.priority, 'Emergency', 'Urgent', 'Normal'), q.position ASC LIMIT 20",
    [$serviceId]
);

$role = $_SESSION['role'];
$duration = 0;
$svc = db()->fetch("SELECT estimated_duration FROM services WHERE id = ?", [$serviceId]);
if ($svc) {
    $duration = intval($svc['estimated_duration']);
}
foreach ($active as &$a) {
    $a['can_manage'] = canManageQueueEntry($a);
}
unset($a);
$i = 0;
foreach ($waiting as &$w) {
    $i++;
    $w['can_manage'] = canManageQueueEntry($w);
    $w['est_minutes'] = $duration > 0 ? ($duration * $i) : 0;
}
unset($w);

$forwardTargets = db()->fetchAll(
    "SELECT d.id, d.first_name, d.last_name, d.specialization, d.type,
            (SELECT COUNT(*) FROM queue_entries q
             WHERE q.doctor_id = d.id AND q.service_id = ? AND q.queue_date = CURDATE()
               AND q.status IN ('Waiting','Called','Serving')) AS workload,
            (SELECT COUNT(*) FROM queue_entries q
             WHERE q.doctor_id = d.id AND q.service_id = ? AND q.queue_date = CURDATE()
               AND q.status = 'Waiting') AS waiting
     FROM doctors d
     JOIN users u ON d.user_id = u.id
     JOIN doctor_services ds ON ds.doctor_id = d.id AND ds.service_id = ?
     WHERE u.role IN ('doctor','nurse') AND u.is_active = 1 AND d.is_available = 1
     ORDER BY workload ASC, d.type, d.first_name",
    [$serviceId, $serviceId, $serviceId]
);
foreach ($forwardTargets as &$ft) {
    $label = providerDisplayName($ft);
    $wl = (int) $ft['workload'];
    $label .= $wl > 0 ? ' — ' . (int) $ft['waiting'] . ' waiting · ' . ($wl - (int) $ft['waiting']) . ' active' : ' — free';
    $ft['label'] = $label;
}
unset($ft);

$current = !empty($active) ? $active[0] : null;

$servingCount = db()->fetch("SELECT COUNT(*) as c FROM queue_entries WHERE service_id = ? AND queue_date = CURDATE() AND status IN ('Called','Serving')", [$serviceId])['c'];
$servedCount = db()->fetch("SELECT COUNT(*) as c FROM queue_entries WHERE service_id = ? AND queue_date = CURDATE() AND status = 'Served'", [$serviceId])['c'];

echo json_encode([
    'forward_targets' => $forwardTargets,
    'currently_serving' => $current ? $current['queue_number'] : null,
    'current_id' => $current ? $current['id'] : null,
    'current_status' => $current ? $current['status'] : null,
    'next_patient' => $nextPatient ? $nextPatient['queue_number'] : null,
    'active' => $active,
    'waiting' => $waiting,
    'serving_count' => $servingCount,
    'served_count' => $servedCount,
]);