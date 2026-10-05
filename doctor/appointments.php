<?php
$pageTitle = 'Appointments';
require_once '../includes/header.php';
requireDoctor();
require_once __DIR__ . '/../includes/appointment_actions.php';

$doctor = db()->fetch("SELECT * FROM doctors WHERE user_id = ?", [$_SESSION['user_id']]);
$doctorId = $doctor['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $apptId = intval($_POST['appointment_id'] ?? 0);
        $action = $_POST['action'] ?? '';
        if ($action === 'confirm') {
            $res = confirmAppointment($apptId);
            setFlash($res['ok'] ? 'success' : 'danger', $res['msg']);
        } elseif ($action === 'cancel') {
            $res = cancelAppointment($apptId);
            setFlash($res['ok'] ? 'success' : 'danger', $res['msg']);
        }
    }
    redirect('doctor/appointments.php');
}

$filter = $_GET['status'] ?? '';
$sql = "SELECT a.*, p.first_name, p.last_name, p.phone, s.name as service_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN services s ON a.service_id = s.id
        WHERE a.doctor_id = ?";
$params = [$doctorId];

if ($filter && in_array($filter, ['Pending','Confirmed','Completed','Cancelled','No Show'])) {
    $sql .= " AND a.status = ?";
    $params[] = $filter;
}
$sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT 50";
$appointments = db()->fetchAll($sql, $params);
?>

<div class="page-content">
    <div class="page-title">My Appointments</div>
    <div class="page-subtitle">View your scheduled appointments.</div>

    <div class="filters-bar mb-30">
        <a href="appointments.php" class="btn btn-sm <?php echo !$filter ? 'btn-primary' : 'btn-outline'; ?>">All</a>
        <?php foreach (['Pending','Confirmed','Completed','Cancelled'] as $s): ?>
            <a href="appointments.php?status=<?php echo $s; ?>" class="btn btn-sm <?php echo $filter === $s ? 'btn-primary' : 'btn-outline'; ?>"><?php echo $s; ?></a>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <?php if (empty($appointments)): ?>
            <div class="empty-state"><h3>No appointments found</h3></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>ID</th><th>Patient</th><th>Service</th><th>Date</th><th>Time</th><th>Reason</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($appointments as $a): ?>
                    <tr>
                        <td><strong><?php echo escape($a['appointment_id']); ?></strong></td>
                        <td><?php echo escape($a['first_name'] . ' ' . $a['last_name']); ?></td>
                        <td><?php echo escape($a['service_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($a['appointment_date'])); ?></td>
                        <td><?php echo date('h:i A', strtotime($a['appointment_time'])); ?></td>
                        <td><?php echo escape(substr($a['reason'] ?? '', 0, 40)); ?></td>
                        <td><span class="badge badge-<?php echo strtolower($a['status']); ?>"><?php echo $a['status']; ?></span></td>
                        <td>
                            <?php if ($a['status'] === 'Pending'): ?>
                            <form method="POST" style="display:inline;">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="appointment_id" value="<?php echo $a['id']; ?>">
                                <button name="action" value="confirm" class="btn btn-sm btn-success" title="I will be available for this appointment">&#10004; Approve</button>
                            </form>
                            <?php endif; ?>
                            <?php if (in_array($a['status'], ['Pending', 'Confirmed'])): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this appointment? The patient will be notified.')">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="appointment_id" value="<?php echo $a['id']; ?>">
                                <button name="action" value="cancel" class="btn btn-sm btn-danger">Cancel</button>
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
