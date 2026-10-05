<?php
$pageTitle = 'Add Patient to Queue';
require_once '../includes/header.php';
requireStaff();

function doctorDisplayName($row) {
    $first = preg_replace('/^Dr\.\s*/i', '', $row['first_name'] ?? '');
    $out = 'Dr. ' . $first . ' ' . ($row['last_name'] ?? '');
    if (!empty($row['specialization'])) {
        $out .= ' (' . $row['specialization'] . ')';
    }
    return $out;
}

$patients = db()->fetchAll(
    "SELECT p.id, p.patient_code, p.first_name, p.last_name, p.phone, u.email, u.is_active
     FROM patients p JOIN users u ON p.user_id = u.id
     ORDER BY p.last_name, p.first_name"
);
$services = db()->fetchAll("SELECT * FROM services WHERE is_active = 1 ORDER BY name");
$doctors = db()->fetchAll(
    "SELECT d.*, u.is_active AS user_active,
            GROUP_CONCAT(DISTINCT ds.service_id) AS service_ids
     FROM doctors d
     JOIN users u ON d.user_id = u.id
     LEFT JOIN doctor_services ds ON d.id = ds.doctor_id
     WHERE d.is_available = 1 AND u.is_active = 1 AND u.role IN ('doctor','nurse')
       AND EXISTS (SELECT 1 FROM doctor_services ds2 WHERE ds2.doctor_id = d.id)
     GROUP BY d.id
     ORDER BY d.type, d.first_name"
);

$presetPatientId = intval($_GET['patient_id'] ?? 0);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $patientId = intval($_POST['patient_id'] ?? 0);
        $serviceId = intval($_POST['service_id'] ?? 0);
        $doctorId = intval($_POST['doctor_id'] ?? 0);
        $priority = in_array($_POST['priority'] ?? 'Normal', ['Normal', 'Urgent', 'Emergency']) ? $_POST['priority'] : 'Normal';

        $patient = db()->fetch(
            "SELECT p.*, u.is_active FROM patients p JOIN users u ON p.user_id = u.id WHERE p.id = ?",
            [$patientId]
        );
        $service = db()->fetch("SELECT * FROM services WHERE id = ? AND is_active = 1", [$serviceId]);

        if (!$patient || !$patient['is_active']) {
            $error = 'Please select a valid patient.';
        } elseif (!$service) {
            $error = 'Please select a valid service.';
        } elseif ($activeEntry = getActiveQueueEntry($patient['id'])) {
            $patientName = $patient['first_name'] . ' ' . $patient['last_name'];
            logAudit(getUserId(), 'queue_add_denied', "Blocked duplicate queue add for {$patient['patient_code']} — already active as {$activeEntry['queue_number']}");
            $error = "$patientName is already in today's queue as {$activeEntry['queue_number']} (Status: {$activeEntry['status']}) — no duplicate was created.";
        } else {
            $vitals = [];
            if ($priority !== 'Normal') {
                foreach (['bp', 'hr', 'temp', 'spo2', 'notes'] as $k) {
                    $v = trim($_POST['vitals_' . $k] ?? '');
                    if ($v !== '') $vitals[$k] = $v;
                }
            }

            $queueNumber = nextQueueNumber($priority !== 'Normal' ? priorityQueuePrefix($priority) : null);
            $maxPos = db()->fetch("SELECT MAX(position) as max_pos FROM queue_entries WHERE queue_date = CURDATE() AND service_id = ? AND status = 'Waiting'", [$serviceId]);
            $position = ($maxPos['max_pos'] ?? 0) + 1;

            $entryId = db()->insert(
                "INSERT INTO queue_entries (queue_number, patient_id, service_id, doctor_id, queue_date, status, position, priority, vitals_json) VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?)",
                [$queueNumber, $patient['id'], $serviceId, $doctorId ?: null, ($priority === 'Emergency' ? 'Called' : 'Waiting'), $position, $priority, $vitals ? json_encode($vitals) : null]
            );

            if ($priority === 'Emergency') {
                db()->update("UPDATE queue_entries SET called_at = NOW() WHERE id = ?", [$entryId]);
                createNotification($patient['user_id'], 'You Are Being Called (Emergency)', "EMERGENCY: Your number $queueNumber has been called. Please proceed to the ER/treatment room immediately.", 'queue', $entryId, 'queue');
                notifyStaffInbox(
                    'Emergency Patient Added to Queue',
                    "{$patient['first_name']} {$patient['last_name']} fast-tracked as $queueNumber (EMERGENCY) for {$service['name']} — auto-called.",
                    'queue', $entryId, 'queue'
                );
                notifyEmergencyProviders($serviceId, $queueNumber, $patient['first_name'] . ' ' . $patient['last_name']);
                sendSMS($patient['phone'], "MediQueue EMERGENCY: Your number $queueNumber is being called. Present to the front desk / ER immediately.");
            } else {
                createNotification($patient['user_id'], 'Joined Queue', "You have joined the queue. Your number is $queueNumber.", 'queue', $entryId, 'queue');
                notifyStaffInbox(
                    'Patient Added to Queue',
                    "{$patient['first_name']} {$patient['last_name']} joined the queue as $queueNumber for {$service['name']}.",
                    'queue', $entryId, 'queue'
                );
                sendSMS($patient['phone'], "MediQueue: You have joined the queue for {$service['name']}. Your number is $queueNumber.");
                if ($priority !== 'Normal') {
                    notifyEmergencyProviders($serviceId, $queueNumber, $patient['first_name'] . ' ' . $patient['last_name']);
                    sendSMS($patient['phone'], "MediQueue " . strtoupper($priority) . ": Please present to the front desk / ER. Your priority number is $queueNumber.");
                }
            }
            logAudit(getUserId(), 'queue_added', "Added patient {$patient['patient_code']} to queue as $queueNumber ($priority)");
            setFlash('success', "{$patient['first_name']} {$patient['last_name']} joined the queue as $queueNumber for {$service['name']}." . ($priority !== 'Normal' ? " Marked as $priority." : ''));
            redirect('staff/queue.php?service_id=' . $serviceId);
        }
    }
}
?>

<div class="page-content">
    <div class="d-flex justify-between align-center mb-20">
        <div>
            <div class="page-title">Add Patient to Queue</div>
            <div class="page-subtitle">Help a patient join the queue (walk-ins without a phone).</div>
        </div>
        <a href="queue.php" class="btn btn-outline">&larr; Back to Queue</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo escape($error); ?></div>
    <?php endif; ?>

    <div class="card" style="max-width: 700px;">
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <div class="form-group">
                <label for="patient_id">Patient *</label>
                <select id="patient_id" name="patient_id" class="form-control" required>
                    <option value="">Select a patient</option>
                    <?php foreach ($patients as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $p['id'] === $presetPatientId ? 'selected' : ''; ?>>
                            <?php echo escape($p['first_name'] . ' ' . $p['last_name']); ?>
                            (<?php echo escape($p['patient_code']); ?><?php echo $p['phone'] ? ' - ' . escape($p['phone']) : ''; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <small style="color:var(--gray);">Need to register a new walk-in patient first? <a href="add_patient.php">Register Patient</a></small>
            </div>
            <div class="form-group">
                <label for="service_id">Service *</label>
                <select id="service_id" name="service_id" class="form-control" required data-filter-doctors="1">
                    <option value="">Select a service</option>
                    <?php foreach ($services as $s): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo escape($s['name']); ?> - <?php echo escape($s['department']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="doctor_id">Preferred Provider (Optional)</label>
                <select id="doctor_id" name="doctor_id" class="form-control">
                    <option value="">No preference</option>
                    <option value="" data-nodoctor="1" style="display:none;">No providers available for this service yet</option>
                    <?php foreach ($doctors as $d): ?>
                        <option value="<?php echo $d['id']; ?>" data-services="<?php echo escape($d['service_ids']); ?>"><?php echo escape(doctorDisplayName($d)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="priority">Priority / Triage Level</label>
                <select id="priority" name="priority" class="form-control" onchange="toggleVitals()">
                    <option value="Normal" selected>Normal</option>
                    <option value="Urgent">Urgent</option>
                    <option value="Emergency">Emergency</option>
                </select>
                <small style="color:var(--gray);">Emergency patients get an ER number, skip to the front of the queue, and alert all on-duty providers.</small>
            </div>
            <div id="vitalsSection" style="display:none;border:1px solid var(--danger);border-radius:var(--radius-sm);padding:14px;margin-bottom:16px;">
                <div style="font-weight:700;color:var(--danger);margin-bottom:8px;">&#9888; Quick Vitals Capture</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Blood Pressure (e.g. 140/90)</label>
                        <input type="text" name="vitals_bp" class="form-control" placeholder="Systolic/Diastolic">
                    </div>
                    <div class="form-group">
                        <label>Heart Rate (BPM)</label>
                        <input type="number" name="vitals_hr" class="form-control" min="0" placeholder="e.g. 96">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Temperature (&deg;C)</label>
                        <input type="number" name="vitals_temp" class="form-control" step="0.1" min="30" max="45" placeholder="e.g. 38.5">
                    </div>
                    <div class="form-group">
                        <label>SpO2 (%)</label>
                        <input type="number" name="vitals_spo2" class="form-control" min="0" max="100" placeholder="e.g. 92">
                    </div>
                </div>
                <div class="form-group">
                    <label>Vitals / Condition Notes</label>
                    <textarea name="vitals_notes" class="form-control" rows="2" placeholder="e.g. chest pain, shortness of breath, unresponsive..."></textarea>
                </div>
            </div>
            <div class="d-flex gap-10">
                <button type="submit" class="btn btn-primary btn-lg">Add to Queue</button>
                <a href="queue.php" class="btn btn-outline btn-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function toggleVitals() {
    const v = document.getElementById('priority').value;
    document.getElementById('vitalsSection').style.display = v === 'Normal' ? 'none' : 'block';
}
document.addEventListener('DOMContentLoaded', toggleVitals);
</script>

<?php require_once '../includes/footer.php'; ?>