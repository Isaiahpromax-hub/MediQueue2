<?php
$pageTitle = 'Appointments';
require_once '../includes/header.php';
requireStaff();
require_once __DIR__ . '/../includes/appointment_actions.php';

$filter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

$sql = "SELECT a.*, p.first_name, p.last_name, p.patient_code, s.name as service_name, d.first_name as doc_first, d.last_name as doc_last
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN services s ON a.service_id = s.id
        JOIN doctors d ON a.doctor_id = d.id
        WHERE 1=1";
$params = [];

if ($filter && in_array($filter, ['Pending','Confirmed','Completed','Cancelled','No Show'])) {
    $sql .= " AND a.status = ?";
    $params[] = $filter;
}
if ($search) {
    $sql .= " AND (a.appointment_id LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_code LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s]);
}
$sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT 100";
$appointments = db()->fetchAll($sql, $params);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        $apptId = intval($_POST['appointment_id'] ?? 0);

        if ($action === 'confirm') {
            $res = confirmAppointment($apptId);
            setFlash($res['ok'] ? 'success' : 'danger', $res['msg']);
        } elseif ($action === 'cancel') {
            $res = cancelAppointment($apptId);
            setFlash($res['ok'] ? 'success' : 'danger', $res['msg']);
        } elseif ($action === 'noshow') {
            db()->update("UPDATE appointments SET status = 'No Show' WHERE id = ? AND status IN ('Pending','Confirmed')", [$apptId]);
            logAudit(getUserId(), 'appointment_no_show', "Marked appointment #$apptId as no show");
            setFlash('success', 'Appointment marked as No Show.');
        } elseif ($action === 'complete') {
            db()->update("UPDATE appointments SET status = 'Completed' WHERE id = ? AND status IN ('Confirmed','Pending')", [$apptId]);
            setFlash('success', 'Appointment marked as completed.');
        }
        redirect('staff/appointments.php');
    }
}
?>

<div class="page-content">
    <div class="d-flex justify-between align-center mb-20">
        <div>
            <div class="page-title">Appointments</div>
            <div class="page-subtitle">Manage all appointments.</div>
        </div>
        <a href="book_appointment.php" class="btn btn-primary">+ Book Appointment</a>
    </div>

    <div class="card mb-30">
        <form method="GET" class="filters-bar">
            <select name="status" class="form-control">
                <option value="">All Statuses</option>
                <?php foreach (['Pending','Confirmed','Completed','Cancelled','No Show'] as $s): ?>
                    <option value="<?php echo $s; ?>" <?php echo $filter === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="search" class="form-control" placeholder="Search by ID, name, or code..." value="<?php echo escape($search); ?>">
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
        </form>
    </div>

    <div class="card">
        <?php if (empty($appointments)): ?>
            <div class="empty-state"><h3>No appointments found</h3></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>ID</th><th>Patient</th><th>Doctor</th><th>Service</th><th>Date</th><th>Time</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $a): ?>
                    <tr>
                        <td><strong><?php echo escape($a['appointment_id']); ?></strong></td>
                        <td><?php echo escape($a['first_name'] . ' ' . $a['last_name']); ?><br><small style="color:var(--gray);"><?php echo escape($a['patient_code']); ?></small></td>
                        <td><?php $docFirst = (stripos($a['doc_first'], 'Dr. ') === 0) ? substr($a['doc_first'], 4) : $a['doc_first'];
                        echo 'Dr. ' . escape($docFirst . ' ' . $a['doc_last']); ?></td>
                        <td><?php echo escape($a['service_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($a['appointment_date'])); ?></td>
                        <td><?php echo date('h:i A', strtotime($a['appointment_time'])); ?></td>
                        <td><span class="badge badge-<?php echo strtolower($a['status']); ?>"><?php echo $a['status']; ?></span></td>
                        <td>
                            <div class="d-flex gap-10">
                                <?php if ($a['status'] === 'Pending'): ?>
                                <form method="POST" style="display:inline;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="appointment_id" value="<?php echo $a['id']; ?>">
                                    <button name="action" value="confirm" class="btn btn-sm btn-success">Confirm</button>
                                </form>
                                <?php endif; ?>
                                <?php if (in_array($a['status'], ['Pending','Confirmed'])): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this appointment?')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="appointment_id" value="<?php echo $a['id']; ?>">
                                    <button name="action" value="cancel" class="btn btn-sm btn-danger">Cancel</button>
                                </form>
                                <?php endif; ?>
                                <?php if (in_array($a['status'], ['Pending','Confirmed'])): ?>
                                <a href="reschedule_appointment.php?id=<?php echo $a['id']; ?>" class="btn btn-sm btn-outline">Reschedule</a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Mark this appointment as No Show?')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="appointment_id" value="<?php echo $a['id']; ?>">
                                    <button name="action" value="noshow" class="btn btn-sm btn-warning">No Show</button>
                                </form>
                                <?php endif; ?>
                                <?php if (in_array($a['status'], ['Pending','Confirmed'])): ?>
                                <form method="POST" style="display:inline;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="appointment_id" value="<?php echo $a['id']; ?>">
                                    <button name="action" value="complete" class="btn btn-sm btn-info">Complete</button>
                                </form>
                                <?php endif; ?>
                            </div>
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
