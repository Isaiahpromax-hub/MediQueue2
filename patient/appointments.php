<?php
$pageTitle = 'My Appointments';
require_once '../includes/header.php';
requirePatient();

$patient = db()->fetch("SELECT * FROM patients WHERE user_id = ?", [$_SESSION['user_id']]);
$patientId = $patient['id'];

$filter = $_GET['status'] ?? '';
$sql = "SELECT a.*, s.name as service_name, d.first_name as doc_first, d.last_name as doc_last
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        JOIN doctors d ON a.doctor_id = d.id
        WHERE a.patient_id = ?";
$params = [$patientId];

if ($filter && in_array($filter, ['Pending', 'Confirmed', 'Completed', 'Cancelled', 'No Show'])) {
    $sql .= " AND a.status = ?";
    $params[] = $filter;
}

$sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
$appointments = db()->fetchAll($sql, $params);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appt'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        require_once __DIR__ . '/../includes/appointment_actions.php';
        $apptId = intval($_POST['appointment_id']);
        $res = cancelAppointment($apptId);
        setFlash($res['ok'] ? 'success' : 'danger', $res['msg']);
        if ($res['ok']) {
            redirect('patient/appointments.php');
        }
    }
}
?>

<div class="page-content">
    <div class="page-title">My Appointments</div>
    <div class="page-subtitle">View and manage your appointments.</div>

    <div class="filters-bar">
        <a href="appointments.php" class="btn btn-sm <?php echo !$filter ? 'btn-primary' : 'btn-outline'; ?>">All</a>
        <a href="appointments.php?status=Pending" class="btn btn-sm <?php echo $filter === 'Pending' ? 'btn-warning' : 'btn-outline'; ?>">Pending</a>
        <a href="appointments.php?status=Confirmed" class="btn btn-sm <?php echo $filter === 'Confirmed' ? 'btn-info' : 'btn-outline'; ?>">Confirmed</a>
        <a href="appointments.php?status=Completed" class="btn btn-sm <?php echo $filter === 'Completed' ? 'btn-success' : 'btn-outline'; ?>">Completed</a>
        <a href="appointments.php?status=Cancelled" class="btn btn-sm <?php echo $filter === 'Cancelled' ? 'btn-danger' : 'btn-outline'; ?>">Cancelled</a>
        <a href="book_appointment.php" class="btn btn-primary btn-sm" style="margin-left: auto;">+ New Appointment</a>
    </div>

    <div class="card">
        <?php if (empty($appointments)): ?>
            <div class="empty-state">
                <div class="icon">&#128197;</div>
                <h3>No appointments found</h3>
                <p>You haven't booked any appointments yet.</p>
                <a href="book_appointment.php" class="btn btn-primary">Book Your First Appointment</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Doctor</th>
                            <th>Service</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $a): ?>
                        <tr>
                            <td><strong><?php echo escape($a['appointment_id']); ?></strong></td>
                            <td>Dr. <?php echo escape($a['doc_first'] . ' ' . $a['doc_last']); ?></td>
                            <td><?php echo escape($a['service_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($a['appointment_date'])); ?></td>
                            <td><?php echo date('h:i A', strtotime($a['appointment_time'])); ?></td>
                            <td><span class="badge badge-<?php echo strtolower($a['status']); ?>"><?php echo $a['status']; ?></span></td>
                            <td>
                                <?php if (in_array($a['status'], ['Pending', 'Confirmed'])): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this appointment?')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="appointment_id" value="<?php echo $a['id']; ?>">
                                    <button type="submit" name="cancel_appt" class="btn btn-sm btn-danger">Cancel</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
