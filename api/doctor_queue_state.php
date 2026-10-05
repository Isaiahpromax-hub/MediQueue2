<?php
require_once __DIR__ . '/../includes/auth.php';

$role = $_SESSION['role'] ?? null;
if ($role === 'nurse') {
    requireNurse();
} else {
    requireDoctor();
}

header('Content-Type: application/json');

$doctorId = getDoctorIdForUser();
if (!$doctorId) {
    echo json_encode(['error' => 'Provider profile not found']);
    exit;
}

$serviceIds = getDoctorServiceIds($doctorId);
if (!$serviceIds) {
    echo json_encode(['error' => 'No services assigned']);
    exit;
}

$forwardTargets = [];
foreach ($serviceIds as $sid) {
    $targets = db()->fetchAll(
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
         WHERE d.id <> ? AND u.role IN ('doctor','nurse') AND u.is_active = 1
         ORDER BY workload ASC, d.type, d.first_name",
        [$sid, $sid, $sid, $doctorId]
    );
    $forwardTargets[$sid] = array_map(function ($t) {
        $title = $t['type'] === 'nurse' ? 'Nurse' : 'Dr.';
        $first = preg_replace('/^Dr\.\s*/i', '', $t['first_name']);
        $label = $title . ' ' . $first . ' ' . $t['last_name'];
        if (!empty($t['specialization']) && $t['type'] !== 'nurse') {
            $label .= ' (' . $t['specialization'] . ')';
        }
        $workload = (int) $t['workload'];
        $waiting = (int) $t['waiting'];
        $label .= $workload > 0
            ? ' — ' . $waiting . ' waiting · ' . ($workload - $waiting) . ' active'
            : ' — free';
        return ['id' => (int) $t['id'], 'name' => $label, 'type' => $t['type'], 'waiting' => $waiting, 'workload' => $workload];
    }, $targets);
}

$all = isset($_GET['all']) ? intval($_GET['all']) : 0;
$serviceId = 0;

if ($all) {
    $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
    $whereService = "q.service_id IN ($placeholders)";
    $params = $serviceIds;
} else {
    $serviceId = intval($_GET['service_id'] ?? 0);
    if (!$serviceId || !in_array($serviceId, $serviceIds)) {
        echo json_encode(['error' => 'Invalid service']);
        exit;
    }
    $whereService = 'q.service_id = ?';
    $params = [$serviceId];
}

$baseSelect =
    "SELECT q.id, q.queue_number, q.status, q.service_id, q.doctor_id, q.priority, q.vitals_json, s.name AS service_name,
            p.first_name, p.last_name,
            CASE WHEN d.type = 'nurse' THEN CONCAT('Nurse ', CASE WHEN d.first_name LIKE 'Dr. %' THEN SUBSTRING(d.first_name, 5) ELSE d.first_name END, ' ', d.last_name)
                 ELSE CONCAT('Dr. ', CASE WHEN d.first_name LIKE 'Dr. %' THEN SUBSTRING(d.first_name, 5) ELSE d.first_name END, ' ', d.last_name) END AS doctor_name
     FROM queue_entries q
     JOIN patients p ON q.patient_id = p.id
     JOIN services s ON q.service_id = s.id
     LEFT JOIN doctors d ON q.doctor_id = d.id
     WHERE $whereService AND q.queue_date = CURDATE()";

$active = db()->fetchAll(
    $baseSelect . " AND q.status IN ('Called','Serving')
     ORDER BY FIELD(q.priority, 'Emergency', 'Urgent', 'Normal'), FIELD(q.status, 'Serving', 'Called'), q.position ASC LIMIT 50",
    $params
);

$waiting = db()->fetchAll(
    $baseSelect . " AND q.status = 'Waiting'
     ORDER BY FIELD(q.priority, 'Emergency', 'Urgent', 'Normal'), q.position ASC LIMIT 50",
    $params
);

$myCurrent = null;
$myNext = null;

foreach ($active as &$a) {
    $a['is_mine'] = ($a['doctor_id'] == $doctorId);
    $a['can_claim'] = ($a['doctor_id'] === null && $a['status'] === 'Called');
    if (!$myCurrent && $a['is_mine']) {
        $myCurrent = $a;
    }
}
unset($a);

foreach ($waiting as &$w) {
    $w['is_mine'] = ($w['doctor_id'] == $doctorId);
    if (!$myNext && ($w['doctor_id'] == $doctorId || $w['doctor_id'] === null)) {
        $myNext = $w;
    }
}
unset($w);

$servedCount = db()->fetch(
    "SELECT COUNT(*) as c FROM queue_entries q WHERE $whereService AND q.queue_date = CURDATE() AND q.status = 'Served'",
    $params
)['c'];

$inQueueCount = db()->fetch(
    "SELECT COUNT(*) as c FROM queue_entries q WHERE $whereService AND q.queue_date = CURDATE() AND q.status IN ('Waiting','Called','Serving')",
    $params
)['c'];

$waitingOnlyCount = db()->fetch(
    "SELECT COUNT(*) as c FROM queue_entries q WHERE $whereService AND q.queue_date = CURDATE() AND q.status = 'Waiting'",
    $params
)['c'];

$myWaitingCount = db()->fetch(
    "SELECT COUNT(*) as c FROM queue_entries q WHERE $whereService AND q.queue_date = CURDATE() AND q.status = 'Waiting' AND (q.doctor_id = ? OR q.doctor_id IS NULL)",
    array_merge($params, [$doctorId])
)['c'];

$primaryServiceId = $myNext ? $myNext['service_id'] : ($all ? $serviceIds[0] : $serviceId);

echo json_encode([
    'service_id' => $serviceId,
    'doctor_id' => $doctorId,
    'primary_service_id' => $primaryServiceId,
    'served_count' => $servedCount,
    'in_queue_count' => $inQueueCount,
    'waiting_count' => $waitingOnlyCount,
    'my_waiting_count' => $myWaitingCount,
    'forward_targets' => $forwardTargets,
    'my_current' => $myCurrent,
    'my_next' => $myNext,
    'active' => $active,
    'waiting' => $waiting,
]);