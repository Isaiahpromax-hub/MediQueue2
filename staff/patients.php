<?php
$pageTitle = 'Patients';
require_once '../includes/header.php';
requireStaff();

$search = trim($_GET['search'] ?? '');

$sql = "SELECT p.*, u.email, u.is_active, (SELECT COUNT(*) FROM patient_notes pn WHERE pn.patient_id = p.id) AS notes_count
        FROM patients p JOIN users u ON p.user_id = u.id WHERE 1=1";
$params = [];
if ($search) {
    $sql .= " AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_code LIKE ? OR u.email LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s]);
}
$sql .= " ORDER BY p.created_at DESC LIMIT 100";
$patients = db()->fetchAll($sql, $params);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'];
        $userId = intval($_POST['user_id']);
        if ($action === 'deactivate') {
            db()->update("UPDATE users SET is_active = 0 WHERE id = ?", [$userId]);
            setFlash('success', 'Patient account deactivated.');
        } elseif ($action === 'activate') {
            db()->update("UPDATE users SET is_active = 1 WHERE id = ?", [$userId]);
            setFlash('success', 'Patient account activated.');
        }
        redirect('staff/patients.php');
    }
}
?>

<div class="page-content">
    <div class="d-flex justify-between align-center mb-20">
        <div>
            <div class="page-title">Patients</div>
            <div class="page-subtitle">View and manage registered patients.</div>
        </div>
        <div class="d-flex gap-10">
            <a href="add_to_queue.php" class="btn btn-outline">Add to Queue</a>
            <a href="add_patient.php" class="btn btn-primary">+ New Patient</a>
        </div>
    </div>

    <div class="card mb-30">
        <form method="GET" class="filters-bar">
            <input type="text" name="search" class="form-control" placeholder="Search patients..." value="<?php echo escape($search); ?>">
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
        </form>
    </div>

    <div class="card">
        <?php if (empty($patients)): ?>
            <div class="empty-state"><h3>No patients found</h3></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Code</th><th>Name</th><th>Email</th><th>Phone</th><th>Gender</th><th>Notes</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $p): ?>
                    <tr>
                        <td><strong><?php echo escape($p['patient_code']); ?></strong></td>
                        <td><?php echo escape($p['first_name'] . ' ' . $p['last_name']); ?></td>
                        <td><?php echo escape($p['email']); ?></td>
                        <td><?php echo escape($p['phone'] ?? '--'); ?></td>
                        <td><?php echo escape($p['gender'] ?? '--'); ?></td>
                        <td>
                            <?php if ($p['notes_count'] > 0): ?>
                                <span class="badge badge-active" title="Patient notes"><?php echo (int)$p['notes_count']; ?></span>
                            <?php else: ?>
                                <span style="opacity:0.5;">&ndash;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $p['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                <?php echo $p['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-10">
                                <a href="patient_detail.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline">View</a>
                                <a href="add_to_queue.php?patient_id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline">Queue</a>
                                <a href="book_appointment.php?patient_id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline">Book</a>
                                <?php if ($p['is_active']): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Deactivate this patient?')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="deactivate">
                                    <input type="hidden" name="user_id" value="<?php echo $p['user_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Deactivate</button>
                                </form>
                                <?php else: ?>
                                <form method="POST" style="display:inline;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="activate">
                                    <input type="hidden" name="user_id" value="<?php echo $p['user_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-success">Activate</button>
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
