<?php
$pageTitle = 'Manage Patients';
require_once '../includes/header.php';
require_once '../includes/auth.php';
requireAdmin();

function nextPatientCode() {
    $rows = db()->fetchAll("SELECT patient_code FROM patients");
    $max = 0;
    foreach ($rows as $r) {
        if (preg_match('/PAT(\d+)$/', $r['patient_code'], $m)) {
            $max = max($max, intval($m[1]));
        }
    }
    do {
        $max++;
        $code = 'PAT' . str_pad($max, 3, '0', STR_PAD_LEFT);
    } while (db()->fetch("SELECT id FROM patients WHERE patient_code = ?", [$code]));
    return $code;
}

$search = trim($_GET['search'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        $userId = intval($_POST['user_id'] ?? 0);

        if ($action === 'create') {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $gender = $_POST['gender'] ?? '';
            $dob = $_POST['date_of_birth'] ?? '';

            if (empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
                setFlash('error', 'Email, password and full name are required.');
            } elseif (strlen($password) < 6) {
                setFlash('error', 'Password must be at least 6 characters.');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                setFlash('error', 'Please enter a valid email address.');
            } elseif (db()->fetch("SELECT id FROM users WHERE email = ?", [$email])) {
                setFlash('error', 'An account with this email already exists.');
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $newUserId = db()->insert(
                    "INSERT INTO users (email, password, role, is_active) VALUES (?, ?, 'patient', 1)",
                    [$email, $hashedPassword]
                );

                db()->insert(
                    "INSERT INTO patients (user_id, patient_code, first_name, last_name, phone, date_of_birth, gender) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$newUserId, nextPatientCode(), $firstName, $lastName, $phone ?: null, $dob ?: null, $gender ?: null]
                );

                logAudit(getUserId(), 'patient_create', "Created patient account: $firstName $lastName ($email)");
                setFlash('success', "Patient $firstName $lastName added successfully.");
            }
            redirect('admin/patients.php');

        } elseif ($action === 'activate' && $userId > 0) {
            db()->update("UPDATE users SET is_active = 1 WHERE id = ? AND role = 'patient'", [$userId]);
            logAudit(getUserId(), 'patient_activate', "Activated patient account (user #$userId)");
            setFlash('success', 'Patient account activated.');
            redirect('admin/patients.php');
} elseif ($action === 'deactivate' && $userId > 0) {
            db()->update("UPDATE users SET is_active = 0 WHERE id = ? AND role = 'patient'", [$userId]);
            logAudit(getUserId(), 'patient_deactivate', "Deactivated patient account (user #$userId)");
            setFlash('success', 'Patient account deactivated.');
            redirect('admin/patients.php');
        } elseif ($action === 'delete' && $userId > 0) {
            $victim = db()->fetch("SELECT email FROM users WHERE id = ? AND role = 'patient'", [$userId]);
            if (!$victim) {
                setFlash('error', 'Patient account not found.');
            } else {
                db()->update("DELETE FROM users WHERE id = ?", [$userId]);
                logAudit(getUserId(), 'patient_delete', "Deleted patient account permanently: {$victim['email']} (user #$userId)");
                setFlash('success', 'Patient account deleted permanently, including all their records.');
            }
            redirect('admin/patients.php');
        } else {
            redirect('admin/patients.php');
        }
    }
}

$sql = "SELECT p.*, u.email, u.is_active, u.profile_pic
        FROM patients p
        JOIN users u ON p.user_id = u.id
        WHERE u.role = 'patient'";
$params = [];

if ($search) {
    $sql .= " AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_code LIKE ? OR u.email LIKE ? OR p.phone LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s, $s]);
}
$sql .= " ORDER BY p.created_at DESC LIMIT 200";
$patients = db()->fetchAll($sql, $params);
$regDate = $_GET['reg_date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $regDate)) {
    $regDate = date('Y-m-d');
}

$newRegistrations = (int) db()->fetch(
    "SELECT COUNT(*) AS c FROM users WHERE role = 'patient' AND DATE(created_at) = ?",
    [$regDate]
)['c'];

$serviceRegs = db()->fetchAll(
    "SELECT s.name AS service,
            COUNT(DISTINCT q.patient_id) AS patients,
            COUNT(q.id) AS entries,
            SUM(q.status = 'Served') AS served
     FROM queue_entries q
     JOIN services s ON q.service_id = s.id
     WHERE q.queue_date = ?
     GROUP BY s.id, s.name
     ORDER BY patients DESC, entries DESC",
    [$regDate]
);

$totalDayPatients = 0;
$totalDayEntries = 0;
foreach ($serviceRegs as $r) {
    $totalDayPatients += (int) $r['patients'];
    $totalDayEntries += (int) $r['entries'];
}
?>

<div class="page-content">
    <div class="d-flex justify-between align-center mb-20">
        <div>
            <div class="page-title">Patients</div>
            <div class="page-subtitle">View and manage all registered patients.</div>
        </div>
        <button class="btn btn-primary" onclick="document.getElementById('addPatientModal').style.display='flex'">+ Add Patient</button>
    </div>

    <div class="card mb-30">
        <form method="GET" class="filters-bar">
            <input type="text" name="search" class="form-control" placeholder="Search by name, code, email, or phone..." value="<?php echo escape($search); ?>">
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
            <?php if ($search): ?>
                <a href="patients.php" class="btn btn-sm btn-outline">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card mb-30" style="border-left: 4px solid var(--success);">
        <div class="card-header">
            <div class="card-title">Daily Patient Registrations by Service</div>
        </div>
        <form method="GET" class="filters-bar">
            <input type="date" name="reg_date" class="form-control" value="<?php echo escape($regDate); ?>">
            <button type="submit" class="btn btn-primary btn-sm">View Day</button>
            <a href="patients.php" class="btn btn-sm btn-outline">Reset</a>
        </form>
        <p style="color:var(--gray);margin:14px 0 16px;">Results for <strong><?php echo date('M d, Y', strtotime($regDate)); ?></strong> - <?php echo $totalDayPatients; ?> patient(s) served by a queue, <?php echo $newRegistrations; ?> new account(s) registered this day.</p>
        <?php if (empty($serviceRegs) && $newRegistrations === 0): ?>
            <div class="empty-state"><p>No registrations recorded on this day.</p></div>
        <?php else: ?>
        <div class="stats-grid mb-20">
            <div class="stat-card green">
                <div class="stat-icon green">&#128100;</div>
                <div class="stat-info"><h3><?php echo $newRegistrations; ?></h3><p>New Accounts Registered</p></div>
            </div>
            <div class="stat-card blue">
                <div class="stat-icon blue">&#128203;</div>
                <div class="stat-info"><h3><?php echo $totalDayPatients; ?></h3><p>Distinct Patients (All Services)</p></div>
            </div>
        </div>
        <?php if (!empty($serviceRegs)): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Service</th><th>Patients</th><th>Queue Entries</th><th>Served</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($serviceRegs as $r): ?>
                    <tr>
                        <td><strong><?php echo escape($r['service']); ?></strong></td>
                        <td><span class="badge badge-primary"><?php echo (int) $r['patients']; ?></span></td>
                        <td><?php echo (int) $r['entries']; ?></td>
                        <td><?php echo (int) $r['served']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <div class="card">
        <?php if (empty($patients)): ?>
            <div class="empty-state"><h3>No patients found</h3></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
<thead>
                    <tr>
                        <th>Photo</th>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Gender</th>
                        <th>Registered On</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $p): ?>
                    <tr>
                        <td><?php echo avatarThumb($p['profile_pic'] ?? '', strtoupper(substr($p['first_name'] ?? 'U', 0, 1) . substr($p['last_name'] ?? '', 0, 1))); ?></td>
                        <td><strong><?php echo escape($p['patient_code']); ?></strong></td>
                        <td><?php echo escape($p['first_name'] . ' ' . $p['last_name']); ?></td>
                        <td><?php echo escape($p['email']); ?></td>
                        <td><?php echo escape($p['phone'] ?? '--'); ?></td>
                        <td><?php echo escape($p['gender'] ?? '--'); ?></td>
                        <td>
                            <?php if ($p['created_at']): ?>
                                <?php echo date('M d, Y', strtotime($p['created_at'])); ?><br>
                                <small style="color:var(--gray);"><?php echo date('h:i A', strtotime($p['created_at'])); ?></small>
                            <?php else: ?>
                                --
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $p['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                <?php echo $p['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-10">
                                <?php if ($p['is_active']): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Deactivate this patient?')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="deactivate">
                                    <input type="hidden" name="user_id" value="<?php echo $p['user_id']; ?>">
                                    <input type="hidden" name="patient_id" value="<?php echo $p['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Deactivate</button>
                                </form>
                                <?php else: ?>
<form method="POST" style="display:inline;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="activate">
                                    <input type="hidden" name="user_id" value="<?php echo $p['user_id']; ?>">
                                    <input type="hidden" name="patient_id" value="<?php echo $p['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-success">Activate</button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to DELETE this patient permanently? This removes all their records and cannot be undone.')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?php echo $p['user_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" style="background:transparent;color:var(--danger);border:1px solid var(--danger);">Delete</button>
                                </form>
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

<div class="modal" id="addPatientModal" style="display:none;">
    <div class="modal-content" style="max-width:600px;">
        <div class="modal-header">
            <h3>Add New Patient</h3>
            <a href="patients.php" class="modal-close">&times;</a>
        </div>
        <form method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="create">
            <div class="form-row">
                <div class="form-group">
                    <label>First Name *</label>
                    <input type="text" name="first_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender" class="form-control">
                        <option value="">Select gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Add Patient</button>
                <a href="patients.php" class="btn btn-outline" style="margin-left:10px;">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
