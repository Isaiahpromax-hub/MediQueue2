<?php
$pageTitle = 'Manage Appointments';
require_once '../includes/header.php';
require_once '../includes/auth.php';
requireAdmin();

$filter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        $apptId = intval($_POST['appointment_id'] ?? 0);

        if ($apptId > 0) {
            if ($action === 'confirm') {
                db()->update("UPDATE appointments SET status = 'Confirmed' WHERE id = ? AND status = 'Pending'", [$apptId]);
                $appt = db()->fetch(
                    "SELECT a.*, p.user_id, a.appointment_id as apt_code FROM appointments a JOIN patients p ON a.patient_id = p.id WHERE a.id = ?",
                    [$apptId]
                );
                if ($appt) {
                    createNotification($appt['user_id'], 'Appointment Confirmed', "Your appointment ({$appt['apt_code']}) has been confirmed.", 'appointment', $apptId, 'appointment');
                    logAudit(getUserId(), 'appointment_confirm', "Confirmed appointment {$appt['apt_code']}");
                }
                setFlash('success', 'Appointment confirmed.');
            } elseif ($action === 'cancel') {
                $appt = db()->fetch(
                    "SELECT a.*, p.user_id, a.appointment_id as apt_code FROM appointments a JOIN patients p ON a.patient_id = p.id WHERE a.id = ?",
                    [$apptId]
                );
                db()->update("UPDATE appointments SET status = 'Cancelled' WHERE id = ? AND status IN ('Pending','Confirmed')", [$apptId]);
                if ($appt) {
                    createNotification($appt['user_id'], 'Appointment Cancelled', "Your appointment ({$appt['apt_code']}) has been cancelled.", 'appointment', $apptId, 'appointment');
                    logAudit(getUserId(), 'appointment_cancel', "Cancelled appointment {$appt['apt_code']}");
                }
                setFlash('success', 'Appointment cancelled.');
            } elseif ($action === 'complete') {
                $appt = db()->fetch(
                    "SELECT a.*, a.appointment_id as apt_code FROM appointments a WHERE a.id = ?",
                    [$apptId]
                );
                db()->update("UPDATE appointments SET status = 'Completed' WHERE id = ? AND status IN ('Confirmed','Pending')", [$apptId]);
                if ($appt) {
                    logAudit(getUserId(), 'appointment_complete', "Completed appointment {$appt['apt_code']}");
                }
                setFlash('success', 'Appointment marked as completed.');
            }
        }
        redirect('admin/appointments.php');
    }
}

$sql = "SELECT a.*, p.first_name, p.last_name, p.patient_code, s.name as service_name,
               d.first_name as doc_first, d.last_name as doc_last
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
    $sql .= " AND (a.appointment_id LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_code LIKE ? OR d.first_name LIKE ? OR d.last_name LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s, $s, $s]);
}
$sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT 200";
$appointments = db()->fetchAll($sql, $params);
?>

<div class="page-content">
    <div class="d-flex justify-between align-center mb-20">
        <div>
            <div class="page-title">Appointments</div>
            <div class="page-subtitle">Manage all appointments across the system.</div>
        </div>
    </div>

    <div class="card mb-30">
        <form method="GET" class="filters-bar">
            <select name="status" class="form-control">
                <option value="">All Statuses</option>
                <?php foreach (['Pending','Confirmed','Completed','Cancelled','No Show'] as $st): ?>
                    <option value="<?php echo $st; ?>" <?php echo $filter === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="search" class="form-control" placeholder="Search by ID, patient name, doctor name..." value="<?php echo escape($search); ?>">
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
            <?php if ($filter || $search): ?>
                <a href="appointments.php" class="btn btn-sm btn-outline">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <?php if (empty($appointments)): ?>
            <div class="empty-state"><h3>No appointments found</h3></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
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
                        <td>
                            <?php echo escape($a['first_name'] . ' ' . $a['last_name']); ?>
                            <br><small style="color:var(--gray);"><?php echo escape($a['patient_code']); ?></small>
                        </td>
                        <td>Dr. <?php echo escape($a['doc_first'] . ' ' . $a['doc_last']); ?></td>
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
