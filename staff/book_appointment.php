<?php
$pageTitle = 'Book Appointment';
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
    "SELECT p.id, p.patient_code, p.first_name, p.last_name, p.phone
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
        $apptDate = $_POST['appointment_date'] ?? '';
        $apptTime = $_POST['appointment_time'] ?? '';
        $reason = trim($_POST['reason'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        $patient = db()->fetch(
            "SELECT p.*, u.is_active FROM patients p JOIN users u ON p.user_id = u.id WHERE p.id = ?",
            [$patientId]
        );

        if (!$patient || !$patient['is_active']) {
            $error = 'Please select a valid patient.';
        } elseif (!$serviceId || !$doctorId || !$apptDate || !$apptTime) {
            $error = 'Please fill in all required fields.';
        } elseif ($apptDate < date('Y-m-d')) {
            $error = 'Cannot book appointments in the past.';
        } else {
            $existing = db()->fetch(
                "SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status NOT IN ('Cancelled','No Show')",
                [$doctorId, $apptDate, $apptTime]
            );
            if ($existing) {
                $error = 'This time slot is already booked. Please choose another.';
            } else {
                $maxId = db()->fetch(
                    "SELECT MAX(CAST(SUBSTRING(appointment_id, CHAR_LENGTH('APT') + 1) AS UNSIGNED)) AS m FROM appointments WHERE appointment_id LIKE 'APT%'"
                );
                $apptId = 'APT' . str_pad(($maxId['m'] ?? 0) + 1, 5, '0', STR_PAD_LEFT);

                db()->insert(
                    "INSERT INTO appointments (appointment_id, patient_id, doctor_id, service_id, appointment_date, appointment_time, reason, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')",
                    [$apptId, $patient['id'], $doctorId, $serviceId, $apptDate, $apptTime, $reason, $notes]
                );

                $doctor = db()->fetch("SELECT first_name, last_name FROM doctors WHERE id = ?", [$doctorId]);
                $docFirst = (stripos($doctor['first_name'], 'Dr. ') === 0) ? substr($doctor['first_name'], 4) : $doctor['first_name'];
                $docName = 'Dr. ' . trim($docFirst . ' ' . $doctor['last_name']);
                createNotification($patient['user_id'], 'Appointment Booked', "Your appointment ($apptId) with $docName on $apptDate at $apptTime has been booked by reception.", 'appointment');
                notifyStaffInbox(
                    'Appointment Booked',
                    "Appointment $apptId booked for {$patient['first_name']} {$patient['last_name']} on $apptDate at $apptTime with $docName.",
                    'appointment'
                );
                sendSMS($patient['phone'], "MediQueue: Appointment $apptId with $docName on $apptDate at $apptTime has been booked.");
                logAudit(getUserId(), 'appointment_booked', "Booked appointment $apptId for patient {$patient['patient_code']}");
                setFlash('success', "Appointment $apptId booked for {$patient['first_name']} {$patient['last_name']}.");
                redirect('staff/appointments.php');
            }
        }
    }
}

$minDate = date('Y-m-d');
$maxDate = date('Y-m-d', strtotime('+60 days'));
?>

<div class="page-content">
    <div class="d-flex justify-between align-center mb-20">
        <div>
            <div class="page-title">Book Appointment</div>
            <div class="page-subtitle">Schedule an appointment on behalf of a patient.</div>
        </div>
        <a href="appointments.php" class="btn btn-outline">&larr; Back to Appointments</a>
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
                <label for="service_id">Service / Department *</label>
                <select id="service_id" name="service_id" class="form-control" required data-filter-doctors="1">
                    <option value="">Select a service</option>
                    <?php foreach ($services as $s): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo escape($s['name']); ?> - <?php echo escape($s['department']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="doctor_id">Doctor / Provider *</label>
                <select id="doctor_id" name="doctor_id" class="form-control" required data-auto-select="1">
                    <option value="">Select a provider</option>
                    <option value="" data-nodoctor="1" style="display:none;">No providers available for this service yet</option>
                    <?php foreach ($doctors as $d): ?>
                        <option value="<?php echo $d['id']; ?>" data-services="<?php echo escape($d['service_ids']); ?>"><?php echo escape(doctorDisplayName($d)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="appointment_date">Date *</label>
                    <input type="date" id="appointment_date" name="appointment_date" class="form-control" min="<?php echo $minDate; ?>" max="<?php echo $maxDate; ?>" required>
                </div>
                <div class="form-group">
                    <label for="appointment_time">Time *</label>
                    <input type="time" id="appointment_time" name="appointment_time" class="form-control" min="08:00" max="17:00" required>
                </div>
            </div>
            <div class="form-group">
                <label for="reason">Reason for Visit *</label>
                <textarea id="reason" name="reason" class="form-control" rows="3" required placeholder="Describe the reason for the visit..."><?php echo escape($reason ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label for="notes">Additional Notes</label>
                <textarea id="notes" name="notes" class="form-control" rows="2" placeholder="Any additional information..."><?php echo escape($notes ?? ''); ?></textarea>
            </div>
            <div class="d-flex gap-10">
                <button type="submit" class="btn btn-primary btn-lg">Book Appointment</button>
                <a href="appointments.php" class="btn btn-outline btn-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>