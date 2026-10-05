<?php
$pageTitle = 'Book Appointment';
require_once '../includes/header.php';
requirePatient();

function doctorDisplayName($row) {
    return providerDisplayName($row);
}

function nextAppointmentId() {
    $max = (int) db()->fetch(
        "SELECT MAX(CAST(SUBSTRING(appointment_id, 4) AS UNSIGNED)) AS m FROM appointments WHERE appointment_id LIKE 'APT%'"
    )['m'];
    do {
        $max++;
        $code = 'APT' . str_pad($max, 5, '0', STR_PAD_LEFT);
    } while (db()->fetch("SELECT id FROM appointments WHERE appointment_id = ?", [$code]));
    return $code;
}

$patient = db()->fetch("SELECT * FROM patients WHERE user_id = ?", [$_SESSION['user_id']]);
$services = patientServices();
$doctors = db()->fetchAll(
    "SELECT d.*, u.is_active AS user_active,
            GROUP_CONCAT(DISTINCT ds.service_id) AS service_ids,
            GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') AS service_names
     FROM doctors d
     JOIN users u ON d.user_id = u.id
     LEFT JOIN doctor_services ds ON d.id = ds.doctor_id
     LEFT JOIN services s ON ds.service_id = s.id
     WHERE d.is_available = 1 AND u.is_active = 1 AND u.role IN ('doctor','nurse')
       AND EXISTS (SELECT 1 FROM doctor_services ds2 WHERE ds2.doctor_id = d.id)
     GROUP BY d.id
     ORDER BY d.type DESC, d.first_name"
);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $serviceId = intval($_POST['service_id'] ?? 0);
        $doctorId = intval($_POST['doctor_id'] ?? 0);
        $apptDate = $_POST['appointment_date'] ?? '';
        $apptTime = $_POST['appointment_time'] ?? '';
        $reason = trim($_POST['reason'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (!$serviceId || !$doctorId || !$apptDate || !$apptTime) {
            $error = 'Please fill in all required fields.';
        } elseif ($apptDate < date('Y-m-d')) {
            $error = 'Cannot book appointments in the past.';
        } elseif (!doctorServesService($doctorId, $serviceId)) {
            $error = 'The selected doctor does not handle this service.';
        } else {
            $existing = db()->fetch(
                "SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status NOT IN ('Cancelled','No Show')",
                [$doctorId, $apptDate, $apptTime]
            );
            if ($existing) {
                $error = 'This time slot is already booked. Please choose another.';
            } else {
                $apptId = nextAppointmentId();

                db()->insert(
                    "INSERT INTO appointments (appointment_id, patient_id, doctor_id, service_id, appointment_date, appointment_time, reason, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')",
                    [$apptId, $patient['id'], $doctorId, $serviceId, $apptDate, $apptTime, $reason, $notes]
                );

                $doctor = db()->fetch("SELECT * FROM doctors WHERE id = ?", [$doctorId]);
                $patientName = $patient['first_name'] . ' ' . $patient['last_name'];
                $svcName = db()->fetch("SELECT name FROM services WHERE id = ?", [$serviceId])['name'] ?? '';
                createNotification($_SESSION['user_id'], 'Appointment Booked', "Your appointment ($apptId) with " . providerDisplayName($doctor) . " on $apptDate at $apptTime has been booked.", 'appointment');
                createNotification(
                    $doctor['user_id'],
                    'New Appointment',
                    "New appointment ($apptId): $patientName on $apptDate at $apptTime ($svcName).",
                    'appointment', null, 'appointment'
                );
                notifyStaffInbox(
                    'New Appointment Request',
                    "New appointment request ($apptId): $patientName on $apptDate at $apptTime ($svcName).",
                    'appointment'
                );
                logAudit($_SESSION['user_id'], 'appointment_created', "Appointment $apptId booked with provider {$doctor['id']}");

                setFlash('success', "Appointment booked successfully! Your appointment ID is $apptId.");
                redirect('patient/appointments.php');
            }
        }
    }
}

$minDate = date('Y-m-d');
$maxDate = date('Y-m-d', strtotime('+60 days'));
?>

<div class="page-content">
    <div class="page-title">Book an Appointment</div>
    <div class="page-subtitle">Schedule a visit with one of our healthcare providers.</div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo escape($error); ?></div>
    <?php endif; ?>

    <div class="card" style="max-width: 700px;">
        <form method="POST" action="">
            <?php echo csrfField(); ?>
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
                <label for="doctor_id">Doctor *</label>
                <select id="doctor_id" name="doctor_id" class="form-control" required data-auto-select="1">
                    <option value="">Select a doctor</option>
                    <option value="" data-nodoctor="1" style="display:none;">No doctors available for this service yet</option>
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
                <textarea id="reason" name="reason" class="form-control" rows="3" required placeholder="Describe the reason for your visit..."><?php echo escape($reason ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label for="notes">Additional Notes</label>
                <textarea id="notes" name="notes" class="form-control" rows="2" placeholder="Any additional information..."><?php echo escape($notes ?? ''); ?></textarea>
            </div>
            <div class="d-flex gap-10">
                <button type="submit" class="btn btn-primary btn-lg">Book Appointment</button>
                <a href="dashboard.php" class="btn btn-outline btn-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
