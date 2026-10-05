<?php
$pageTitle = 'Reschedule Appointment';
require_once '../includes/header.php';
requireStaff();

$apptId = intval($_GET['id'] ?? 0);
$appt = $apptId ? db()->fetch(
    "SELECT a.*, p.id AS patient_pk, p.user_id, p.phone, p.first_name, p.last_name, p.patient_code,
            s.name AS service_name, d.first_name AS doc_first, d.last_name AS doc_last
     FROM appointments a
     JOIN patients p ON a.patient_id = p.id
     JOIN services s ON a.service_id = s.id
     JOIN doctors d ON a.doctor_id = d.id
     WHERE a.id = ?",
    [$apptId]
) : null;

if (!$appt || !in_array($appt['status'], ['Pending', 'Confirmed'])) {
    setFlash('error', 'Appointment not found or cannot be rescheduled.');
    redirect('staff/appointments.php');
}

$docFirst = (stripos($appt['doc_first'], 'Dr. ') === 0) ? substr($appt['doc_first'], 4) : $appt['doc_first'];
$docName = 'Dr. ' . $docFirst . ' ' . $appt['doc_last'];

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $newDate = $_POST['appointment_date'] ?? '';
        $newTime = $_POST['appointment_time'] ?? '';

        if (!$newDate || !$newTime) {
            $error = 'Please choose a new date and time.';
        } elseif ($newDate < date('Y-m-d')) {
            $error = 'Cannot schedule appointments in the past.';
        } else {
            $existing = db()->fetch(
                "SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status NOT IN ('Cancelled','No Show') AND id <> ?",
                [$appt['doctor_id'], $newDate, $newTime, $apptId]
            );
            if ($existing) {
                $error = 'This time slot is already booked. Please choose another.';
            } else {
                db()->update(
                    "UPDATE appointments SET appointment_date = ?, appointment_time = ?, status = 'Pending', updated_at = NOW() WHERE id = ?",
                    [$newDate, $newTime, $apptId]
                );
                createNotification(
                    $appt['user_id'],
                    'Appointment Rescheduled',
                    "Your appointment ({$appt['appointment_id']}) has been moved to $newDate at $newTime with $docName.",
                    'appointment'
                );
                sendSMS($appt['phone'], "MediQueue: Your appointment {$appt['appointment_id']} has been moved to $newDate at $newTime with $docName.");
                logAudit(getUserId(), 'appointment_rescheduled', "Rescheduled appointment {$appt['appointment_id']} for {$appt['patient_code']} to $newDate at $newTime");
                setFlash('success', "Appointment {$appt['appointment_id']} rescheduled to $newDate at $newTime.");
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
            <div class="page-title">Reschedule Appointment</div>
            <div class="page-subtitle"><?php echo escape($appt['first_name'] . ' ' . $appt['last_name']); ?> (<?php echo escape($appt['patient_code']); ?>)</div>
        </div>
        <a href="appointments.php" class="btn btn-outline">&larr; Back to Appointments</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo escape($error); ?></div>
    <?php endif; ?>

    <div class="card" style="max-width: 620px;">
        <div class="card-header">
            <div class="card-title">Current Schedule</div>
        </div>
        <div class="mb-20" style="padding: 0 20px;">
            <table class="table">
                <tr><th style="width:140px;">Appointment</th><td><?php echo escape($appt['appointment_id']); ?></td></tr>
                <tr><th>Service</th><td><?php echo escape($appt['service_name']); ?></td></tr>
                <tr><th>Provider</th><td><?php echo escape($docName); ?></td></tr>
                <tr><th>Current</th><td><?php echo date('M d, Y', strtotime($appt['appointment_date'])) . ' at ' . date('g:i A', strtotime($appt['appointment_time'])); ?></td></tr>
            </table>
        </div>

        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="appointment_date">New Date *</label>
                    <input type="date" id="appointment_date" name="appointment_date" class="form-control" min="<?php echo $minDate; ?>" max="<?php echo $maxDate; ?>" value="<?php echo $appt['appointment_date']; ?>" required>
                </div>
                <div class="form-group">
                    <label for="appointment_time">New Time *</label>
                    <input type="time" id="appointment_time" name="appointment_time" class="form-control" min="08:00" max="17:00" value="<?php echo $appt['appointment_time']; ?>" required>
                </div>
            </div>
            <p style="color:var(--gray);font-size:13px;">The appointment will return to <strong>Pending</strong> status and the patient will be notified.</p>
            <div class="d-flex gap-10">
                <button type="submit" class="btn btn-primary">Save Reschedule</button>
                <a href="appointments.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>