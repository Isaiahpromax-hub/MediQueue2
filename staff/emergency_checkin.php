<?php
$pageTitle = 'Triage & Emergency Check-in';
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
    "SELECT p.id, p.patient_code, p.first_name, p.last_name, p.phone, p.blood_group, p.allergies, u.email, u.is_active
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
$symptomLists = triageSymptomLists();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $patientId = intval($_POST['patient_id'] ?? 0);
        $serviceId = intval($_POST['service_id'] ?? 0);
        $doctorId = intval($_POST['doctor_id'] ?? 0);
        $symptoms = array_map('trim', (array)($_POST['symptoms'] ?? []));

        $patient = db()->fetch(
            "SELECT p.*, u.is_active FROM patients p JOIN users u ON p.user_id = u.id WHERE p.id = ?",
            [$patientId]
        );
        $service = db()->fetch("SELECT * FROM services WHERE id = ? AND is_active = 1", [$serviceId]);

        if (!$patient || !$patient['is_active']) {
            $error = 'Please select a valid patient.';
        } elseif (!$service) {
            $error = 'Please select a service.';
        } elseif ($activeEntry = getActiveQueueEntry($patient['id'])) {
            $patientName = $patient['first_name'] . ' ' . $patient['last_name'];
            logAudit(getUserId(), 'triage_checkin_denied', "Blocked duplicate triage check-in for {$patient['patient_code']} — already active as {$activeEntry['queue_number']}");
            $error = "$patientName is already in today's queue as {$activeEntry['queue_number']} (Status: {$activeEntry['status']}) — no duplicate was created.";
        } else {
            $vitals = [];
            foreach (['bp', 'hr', 'temp', 'spo2', 'notes'] as $k) {
                $v = trim($_POST['vitals_' . $k] ?? '');
                if ($v !== '') $vitals[$k] = $v;
            }

            $assessment = assessTriage($symptoms, $vitals);
            $priority = $assessment['priority'];
            $reasons = $assessment['reasons'];

            $queueNumber = nextQueueNumber($priority !== 'Normal' ? priorityQueuePrefix($priority) : null);
            $maxPos = db()->fetch("SELECT MAX(position) as max_pos FROM queue_entries WHERE queue_date = CURDATE() AND service_id = ? AND status = 'Waiting'", [$serviceId]);
            $position = ($maxPos['max_pos'] ?? 0) + 1;

            $record = array_merge(['symptoms' => array_values($symptoms)], $vitals);
            $entryId = db()->insert(
                "INSERT INTO queue_entries (queue_number, patient_id, service_id, doctor_id, queue_date, status, position, priority, vitals_json) VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?)",
                [$queueNumber, $patient['id'], $serviceId, $doctorId ?: null, ($priority === 'Emergency' ? 'Called' : 'Waiting'), $position, $priority, json_encode($record)]
            );

            if ($priority === 'Emergency') {
                db()->update("UPDATE queue_entries SET called_at = NOW() WHERE id = ?", [$entryId]);
                createNotification($patient['user_id'], 'You Are Being Called (Emergency)', "EMERGENCY: Your number $queueNumber has been called. Please proceed to the ER immediately.", 'queue', $entryId, 'queue');
                notifyEmergencyProviders($serviceId, $queueNumber, $patient['first_name'] . ' ' . $patient['last_name']);
                sendSMS($patient['phone'], "MediQueue EMERGENCY: Your number $queueNumber is being called. Present to the front desk / ER immediately.");
            } else {
                createNotification($patient['user_id'], 'Joined Queue', "You have joined the queue. Your number is $queueNumber (" . strtolower($priority) . " priority).", 'queue', $entryId, 'queue');
                sendSMS($patient['phone'], "MediQueue: You have joined the queue for {$service['name']}. Your number is $queueNumber.");
            }

            logAudit(getUserId(), 'triage_checkin', "Triage {$patient['patient_code']} -> $priority as $queueNumber for {$service['name']}. Evidence: " . implode('; ', $reasons));
            $highlight = $priority === 'Emergency' ? 'danger' : ($priority === 'Urgent' ? 'warning' : 'success');
            setFlash($highlight, "TRIAGE RESULT: <strong>$priority</strong> — {$patient['first_name']} {$patient['last_name']} checked in as <strong>$queueNumber</strong> for {$service['name']}.<br><small style='opacity:0.85;'>" . escape(implode(' • ', $reasons)) . "</small>");
            redirect('staff/queue.php?service_id=' . $serviceId);
        }
    }
}
?>

<div class="page-content">
    <div class="d-flex justify-between align-center mb-20">
        <div>
            <div class="page-title">&#9873; Triage &amp; Emergency Check-in</div>
            <div class="page-subtitle">Score symptoms + vitals to verify whether this patient needs emergency fast-tracking or belongs in the normal queue.</div>
        </div>
        <a href="queue.php" class="btn btn-outline">&larr; Back to Queue</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo escape($error); ?></div>
    <?php endif; ?>

    <div class="card" style="max-width: 760px;">
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <div class="form-group">
                <label for="patient_id">Patient *</label>
                <select id="patient_id" name="patient_id" class="form-control" required>
                    <option value="">Select a patient</option>
                    <?php foreach ($patients as $p): ?>
                        <option value="<?php echo $p['id']; ?>">
                            <?php echo escape($p['first_name'] . ' ' . $p['last_name']); ?>
                            (<?php echo escape($p['patient_code']); ?><?php echo $p['blood_group'] ? ' - ' . escape($p['blood_group']) : ''; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <small style="color:var(--gray);">New walk-in? <a href="add_patient.php">Register them first</a>, then triage.</small>
            </div>
            <div class="form-group">
                <label for="service_id">Target Service *</label>
                <select id="service_id" name="service_id" class="form-control" required data-filter-doctors="1">
                    <option value="">Select a service / department</option>
                    <?php foreach ($services as $s): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo escape($s['name']); ?> - <?php echo escape($s['department']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="doctor_id">Route To Provider (Optional)</label>
                <select id="doctor_id" name="doctor_id" class="form-control">
                    <option value="">First available provider</option>
                    <option value="" data-nodoctor="1" style="display:none;">No providers for this service yet</option>
                    <?php foreach ($doctors as $d): ?>
                        <option value="<?php echo $d['id']; ?>" data-services="<?php echo escape($d['service_ids']); ?>"><?php echo escape(doctorDisplayName($d)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php $lists = $symptomLists; ?>
            <div style="border:2px solid var(--danger);border-radius:var(--radius-sm);padding:16px;margin-bottom:18px;">
                <div style="font-weight:700;color:var(--danger);margin-bottom:4px;">&#9873; Red-Flag Symptoms</div>
                <div style="font-size:0.85rem;color:var(--gray);margin-bottom:10px;">Any one of these = a genuine emergency. Select all that apply.</div>
                <div class="form-row">
                    <?php foreach ($lists['critical'] as $key => $label): ?>
                        <label class="checkbox-inline" style="flex:1 1 45%;min-width:240px;">
                            <input type="checkbox" name="symptoms[]" value="<?php echo $key; ?>" onchange="reassess()"> <?php echo escape($label); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="border:1px solid var(--gray-light);border-radius:var(--radius-sm);padding:16px;margin-bottom:18px;">
                <div style="font-weight:700;color:#856404;margin-bottom:4px;">&#9888; Other Concerns</div>
                <div style="font-size:0.85rem;color:var(--gray);margin-bottom:10px;">Urgent but not necessarily critical. Select any that apply.</div>
                <div class="form-row">
                    <?php foreach ($lists['urgent'] as $key => $label): ?>
                        <label class="checkbox-inline" style="flex:1 1 45%;min-width:240px;">
                            <input type="checkbox" name="symptoms[]" value="<?php echo $key; ?>" onchange="reassess()"> <?php echo escape($label); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="border:1px solid var(--gray-light);border-radius:var(--radius-sm);padding:16px;margin-bottom:18px;">
                <div style="font-weight:700;margin-bottom:10px;">Vitals (optional but recommended)</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Blood Pressure</label>
                        <input type="text" name="vitals_bp" class="form-control" placeholder="e.g. 140/90" oninput="reassess()">
                    </div>
                    <div class="form-group">
                        <label>Heart Rate (BPM)</label>
                        <input type="number" name="vitals_hr" class="form-control" min="0" placeholder="e.g. 96" oninput="reassess()">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Temperature (&deg;C)</label>
                        <input type="number" name="vitals_temp" class="form-control" step="0.1" min="30" max="45" placeholder="e.g. 38.5" oninput="reassess()">
                    </div>
                    <div class="form-group">
                        <label>SpO2 (%)</label>
                        <input type="number" name="vitals_spo2" class="form-control" min="0" max="100" placeholder="e.g. 92" oninput="reassess()">
                    </div>
                </div>
                <div class="form-group">
                    <label>Condition Notes</label>
                    <textarea name="vitals_notes" class="form-control" rows="2" placeholder="Additional observations..."></textarea>
                </div>
            </div>

            <div id="triageVerdict" class="alert" style="display:none;margin-bottom:18px;">
                <div style="font-weight:800;font-size:1.05rem;" id="triageVerdictTitle"></div>
                <ul id="triageReasons" style="margin:6px 0 0 18px;font-size:0.9rem;"></ul>
            </div>

            <div class="d-flex gap-10">
                <button type="submit" class="btn btn-primary btn-lg" style="flex:1;font-weight:700;" id="submitBtn">&#9873; Check In Patient</button>
                <a href="queue.php" class="btn btn-outline btn-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
var CRITICAL_KEYS = <?php echo json_encode(array_keys($symptomLists['critical'])); ?>;
var URGENT_KEYS = <?php echo json_encode(array_keys($symptomLists['urgent'])); ?>;
var CRITICAL_LABELS = <?php echo json_encode($symptomLists['critical']); ?>;
var URGENT_LABELS = <?php echo json_encode($symptomLists['urgent']); ?>;

function collectSymptoms() {
    return Array.prototype.map.call(
        document.querySelectorAll('input[name="symptoms[]"]:checked'),
        function (el) { return el.value; }
    );
}

function reassess() {
    var symptoms = collectSymptoms();
    var reasons = [];
    var emergency = false, urgent = false;

    symptoms.forEach(function (s) {
        if (CRITICAL_KEYS.indexOf(s) !== -1) { emergency = true; reasons.push('Red-flag: ' + CRITICAL_LABELS[s]); }
        else if (URGENT_KEYS.indexOf(s) !== -1) { urgent = true; reasons.push('Urgent: ' + URGENT_LABELS[s]); }
    });

    var bp = document.querySelector('input[name="vitals_bp"]').value.replace(/[^0-9\/]/g, '');
    var hr = parseInt(document.querySelector('input[name="vitals_hr"]').value, 10) || 0;
    var temp = parseFloat(document.querySelector('input[name="vitals_temp"]').value) || 0;
    var spo2 = parseInt(document.querySelector('input[name="vitals_spo2"]').value, 10) || 0;

    if (bp) {
        var parts = bp.split('/');
        var sys = parseInt(parts[0], 10) || 0, dia = parseInt(parts[1], 10) || 0;
        if (sys >= 180 || dia >= 110) { emergency = true; reasons.push('Critical BP ' + sys + '/' + dia); }
        else if (sys >= 140 || dia >= 90) { urgent = true; reasons.push('Elevated BP ' + sys + '/' + dia); }
    }
    if (hr) {
        if (hr >= 120 || hr <= 50) { emergency = true; reasons.push('Abnormal HR ' + hr + ' bpm'); }
        else if (hr >= 100) { urgent = true; reasons.push('Elevated HR ' + hr + ' bpm'); }
    }
    if (spo2) {
        if (spo2 <= 90) { emergency = true; reasons.push('Low SpO2 ' + spo2 + '%'); }
        else if (spo2 <= 93) { urgent = true; reasons.push('Borderline SpO2 ' + spo2 + '%'); }
    }
    if (temp) {
        if (temp >= 39.5) { emergency = true; reasons.push('Very high temp ' + temp + '°C'); }
        else if (temp >= 38.0) { urgent = true; reasons.push('Raised temp ' + temp + '°C'); }
    }

    var verdict = document.getElementById('triageVerdict');
    var title = document.getElementById('triageVerdictTitle');
    var list = document.getElementById('triageReasons');
    var btn = document.getElementById('submitBtn');

    if (!emergency && !urgent) {
        if (!reasons.length) reasons.push('No symptoms or vitals recorded');
        reasons.push('No red-flag symptoms or abnormal vitals — patient stays in the normal queue');
    }

    var cls, label, icon, btnLabel;
    if (emergency) { cls = 'alert-danger'; label = 'EMERGENCY'; icon = '\u26A0'; btnLabel = '\u26A0 Confirm EMERGENCY Fast-track'; }
    else if (urgent) { cls = 'alert-warning'; label = 'URGENT'; icon = '\u26A0'; btnLabel = '\u26A0 Fast-track as URGENT'; }
    else { cls = 'alert-success'; label = 'NORMAL — can stay in queue'; icon = '\u2714'; btnLabel = '\u2714 Add to NORMAL Queue'; }

    verdict.className = 'alert ' + cls;
    verdict.style.display = 'block';
    title.innerHTML = icon + ' Triage verdict: <strong>' + label + '</strong>';
    list.innerHTML = reasons.map(function (r) { return '<li>' + escapeHtml(r) + '</li>'; }).join('');
    btn.innerHTML = btnLabel;
}

document.addEventListener('DOMContentLoaded', function () {
    reassess();
    var svc = document.getElementById('service_id');
    if (svc) svc.addEventListener('change', reassess);
});
</script>

<?php require_once '../includes/footer.php'; ?>