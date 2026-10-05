<?php
$pageTitle = 'Patient Details';
require_once '../includes/header.php';
requireStaff();

$patientId = intval($_GET['id'] ?? 0);
$patient = $patientId ? db()->fetch(
    "SELECT p.*, u.email, u.is_active FROM patients p JOIN users u ON p.user_id = u.id WHERE p.id = ?",
    [$patientId]
) : null;

if (!$patient) {
    setFlash('error', 'Patient not found.');
    redirect('staff/patients.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $note = trim($_POST['note'] ?? '');
        if ($note !== '') {
            db()->insert(
                "INSERT INTO patient_notes (patient_id, user_id, note) VALUES (?, ?, ?)",
                [$patientId, $_SESSION['user_id'], $note]
            );
            logAudit($_SESSION['user_id'], 'patient_note_added', 'Note added on patient ' . $patient['patient_code']);
            setFlash('success', 'Note added.');
        }
    }
    redirect('staff/patient_detail.php?id=' . $patientId);
}

$notes = db()->fetchAll(
    "SELECT n.*, u.role,
            COALESCE(NULLIF(CONCAT(p.first_name, ' ', p.last_name), ''),
                     NULLIF(CONCAT(d.first_name, ' ', d.last_name), ''),
                     NULLIF(CONCAT(s.first_name, ' ', s.last_name), ''),
                     u.email) AS author_name
     FROM patient_notes n
     JOIN users u ON n.user_id = u.id
     LEFT JOIN patients p ON p.user_id = u.id
     LEFT JOIN doctors d ON d.user_id = u.id
     LEFT JOIN staff s ON s.user_id = u.id
     WHERE n.patient_id = ?
     ORDER BY n.created_at DESC",
    [$patientId]
);

$age = '';
if (!empty($patient['date_of_birth'])) {
    $age = date_diff(date_create($patient['date_of_birth']), date_create('today'))->y;
}

$queueHistory = db()->fetchAll(
    "SELECT q.queue_number, q.queue_date, q.status, s.name AS service_name,
            CONCAT(CAST(d.first_name AS CHAR), ' ', d.last_name) AS provider_name
     FROM queue_entries q
     JOIN services s ON q.service_id = s.id
     LEFT JOIN doctors d ON q.doctor_id = d.id
     WHERE q.patient_id = ?
     ORDER BY q.id DESC LIMIT 10",
    [$patientId]
);

$appointments = db()->fetchAll(
    "SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.status, a.reason, a.notes,
            s.name AS service_name,
            CONCAT(CAST(d.first_name AS CHAR), ' ', d.last_name) AS provider_name
     FROM appointments a
     JOIN services s ON a.service_id = s.id
     LEFT JOIN doctors d ON a.doctor_id = d.id
     WHERE a.patient_id = ?
     ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT 10",
    [$patientId]
);

function patientStatusBadge($status)
{
    $map = [
        'Waiting' => 'badge-active',
        'Called' => 'badge-active',
        'Serving' => 'badge-active',
        'Served' => 'badge-success',
        'Pending' => 'badge-active',
        'Confirmed' => 'badge-active',
        'Completed' => 'badge-success',
        'Cancelled' => 'badge-inactive',
        'No Show' => 'badge-inactive',
    ];
    $class = $map[$status] ?? 'badge-inactive';
    return "<span class=\"badge $class\">" . escape($status) . "</span>";
}
?>
<div class="page-content">
    <div class="d-flex justify-between align-center mb-20">
        <div>
            <div class="page-title"><?php echo escape($patient['first_name'] . ' ' . $patient['last_name']); ?></div>
            <div class="page-subtitle"><?php echo escape($patient['patient_code']); ?></div>
        </div>
        <div class="d-flex gap-10">
            <a href="patients.php" class="btn btn-outline">&larr; Back</a>
            <a href="add_to_queue.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-outline">Add to Queue</a>
            <a href="book_appointment.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-primary">Book Appointment</a>
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <div class="card-header">
                <div class="card-title">Patient Information</div>
            </div>
            <table class="table">
                <tr><th style="width:140px;">Patient Code</th><td><?php echo escape($patient['patient_code']); ?></td></tr>
                <tr><th>Email</th><td><?php echo escape($patient['email']); ?></td></tr>
                <tr><th>Phone</th><td><?php echo escape($patient['phone'] ?? '--'); ?></td></tr>
                <tr><th>Gender</th><td><?php echo escape($patient['gender'] ?? '--'); ?></td></tr>
                <tr><th>Date of Birth</th><td><?php echo escape($patient['date_of_birth'] ?: '--'); ?><?php echo $age !== '' ? " <span style='opacity:0.7'>(age $age)</span>" : ''; ?></td></tr>
                <tr><th>Address</th><td><?php echo escape($patient['address'] ?? '--'); ?></td></tr>
                <tr><th>Emergency Contact</th><td><?php echo escape($patient['emergency_contact'] ?? '--'); ?></td></tr>
                <tr><th>Emergency Phone</th><td><?php echo escape($patient['emergency_phone'] ?? '--'); ?></td></tr>
                <tr><th>Blood Group</th><td><?php echo escape($patient['blood_group'] ?? '--'); ?></td></tr>
                <tr><th>Allergies</th><td><?php echo escape($patient['allergies'] ?? '--'); ?></td></tr>
                <tr><th>Status</th><td>
                    <span class="badge <?php echo $patient['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                        <?php echo $patient['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </td></tr>
                <tr><th>Registered</th><td><?php echo escape(date('M j, Y', strtotime($patient['created_at']))); ?></td></tr>
            </table>
        </div>

        <div>
            <div class="card mb-30">
                <div class="card-header">
                    <div class="card-title">Queue History</div>
                </div>
                <?php if (empty($queueHistory)): ?>
                    <div class="empty-state"><p>No queue history.</p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr><th>Number</th><th>Service</th><th>Provider</th><th>Date</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($queueHistory as $q): ?>
                            <tr>
                                <td><strong><?php echo escape($q['queue_number']); ?></strong></td>
                                <td><?php echo escape($q['service_name']); ?></td>
                                <td><?php echo escape($q['provider_name'] ?? '--'); ?></td>
                                <td><?php echo escape(date('M j', strtotime($q['queue_date']))); ?></td>
                                <td><?php echo patientStatusBadge($q['status']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">Appointments</div>
                </div>
                <?php if (empty($appointments)): ?>
                    <div class="empty-state"><p>No appointments.</p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr><th>ID</th><th>Service</th><th>Provider</th><th>Date</th><th>Time</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $a): ?>
                            <tr>
                                <td><strong><?php echo escape($a['appointment_id']); ?></strong></td>
                                <td><?php echo escape($a['service_name']); ?></td>
                                <td><?php echo escape($a['provider_name'] ?? '--'); ?></td>
                                <td><?php echo escape(date('M j, Y', strtotime($a['appointment_date']))); ?></td>
                                <td><?php echo escape(date('g:i A', strtotime($a['appointment_time']))); ?></td>
                                <td><?php echo patientStatusBadge($a['status']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card mb-30">
        <div class="card-header">
            <div class="card-title">Patient Notes</div>
            <span class="badge badge-active"><?php echo count($notes); ?> note<?php echo count($notes) === 1 ? '' : 's'; ?></span>
        </div>
        <div class="card-body">
            <?php if (empty($notes)): ?>
                <div class="empty-state"><p>No notes recorded yet.</p></div>
            <?php else: ?>
                <?php foreach ($notes as $note): ?>
                <div class="note-item">
                    <div class="note-text"><?php echo nl2br(escape($note['note'])); ?></div>
                    <div class="note-meta">
                        <?php echo escape($note['author_name']); ?>
                        (<?php echo escape(ucfirst($note['role'])); ?>) &middot;
                        <?php echo escape(date('M j, Y g:i A', strtotime($note['created_at']))); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <hr style="margin:16px 0;border:none;border-top:1px solid var(--line,#e5e7eb);">
            <form method="POST" class="d-flex gap-10" style="align-items:flex-start;">
                <?php echo csrfField(); ?>
                <textarea name="note" class="form-control" rows="2" placeholder="Record a note about this patient (e.g. follow-up reminder, observation, admin detail)..." required></textarea>
                <button type="submit" name="add_note" class="btn btn-primary">Add Note</button>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>