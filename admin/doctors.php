<?php
$pageTitle = 'Manage Doctors';
require_once '../includes/auth.php';
requireAdmin();

function nextDoctorCode() {
    $rows = db()->fetchAll("SELECT doctor_code FROM doctors");
    $max = 0;
    foreach ($rows as $r) {
        if (preg_match('/DOC(\d+)$/', $r['doctor_code'], $m)) {
            $max = max($max, intval($m[1]));
        }
    }
    do {
        $max++;
        $code = 'DOC' . str_pad($max, 3, '0', STR_PAD_LEFT);
    } while (db()->fetch("SELECT id FROM doctors WHERE doctor_code = ?", [$code]));
    return $code;
}

$search = trim($_GET['search'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        $userId = intval($_POST['user_id'] ?? 0);
        $doctorId = intval($_POST['doctor_id'] ?? 0);

        if ($action === 'create') {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $specialization = trim($_POST['specialization'] ?? '');
            $qualification = trim($_POST['qualification'] ?? '');
            $license = trim($_POST['license_number'] ?? '');
            $isAvailable = isset($_POST['is_available']) ? 1 : 0;
            $services = array_map('intval', (array)($_POST['services'] ?? []));

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
                    "INSERT INTO users (email, password, role, is_active) VALUES (?, ?, 'doctor', 1)",
                    [$email, $hashedPassword]
                );

                $newDoctorId = db()->insert(
                    "INSERT INTO doctors (user_id, doctor_code, first_name, last_name, phone, specialization, qualification, license_number, is_available) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$newUserId, nextDoctorCode(), $firstName, $lastName, $phone ?: null, $specialization ?: null, $qualification ?: null, $license ?: null, $isAvailable]
                );

                foreach ($services as $sid) {
                    if (db()->fetch("SELECT id FROM services WHERE id = ? AND is_active = 1", [$sid])) {
                        db()->insert("INSERT INTO doctor_services (doctor_id, service_id) VALUES (?, ?)", [$newDoctorId, $sid]);
                    }
                }

                logAudit(getUserId(), 'doctor_create', "Created doctor account: $firstName $lastName ($email)");
                setFlash('success', "Doctor $firstName $lastName added successfully.");
            }
            redirect('admin/doctors.php');

        } elseif ($action === 'assign_services' && $doctorId > 0) {
            $services = array_map('intval', (array)($_POST['services'] ?? []));
            db()->query("DELETE FROM doctor_services WHERE doctor_id = ?", [$doctorId]);
            foreach ($services as $sid) {
                if (db()->fetch("SELECT id FROM services WHERE id = ? AND is_active = 1", [$sid])) {
                    db()->insert("INSERT INTO doctor_services (doctor_id, service_id) VALUES (?, ?)", [$doctorId, $sid]);
                }
            }
            logAudit(getUserId(), 'doctor_services_update', "Updated services for doctor #$doctorId");
            setFlash('success', 'Doctor service assignments updated.');
            redirect('admin/doctors.php');

        } elseif ($action === 'activate' && $userId > 0) {
            db()->update("UPDATE users SET is_active = 1 WHERE id = ? AND role = 'doctor'", [$userId]);
            logAudit(getUserId(), 'doctor_activate', "Activated doctor account (user #$userId)");
            setFlash('success', 'Doctor account activated.');
            redirect('admin/doctors.php');
        } elseif ($action === 'deactivate' && $userId > 0) {
            db()->update("UPDATE users SET is_active = 0 WHERE id = ? AND role = 'doctor'", [$userId]);
            logAudit(getUserId(), 'doctor_deactivate', "Deactivated doctor account (user #$userId)");
            setFlash('success', 'Doctor account deactivated.');
            redirect('admin/doctors.php');
        } elseif ($action === 'delete' && $userId > 0) {
            $victim = db()->fetch("SELECT email FROM users WHERE id = ? AND role = 'doctor'", [$userId]);
            if (!$victim) {
                setFlash('error', 'Doctor account not found.');
            } else {
                db()->update("DELETE FROM users WHERE id = ?", [$userId]);
                logAudit(getUserId(), 'doctor_delete', "Deleted doctor account permanently: {$victim['email']} (user #$userId)");
                setFlash('success', 'Doctor account deleted permanently, including their assigned services and appointments.');
            }
            redirect('admin/doctors.php');
        } elseif ($action === 'toggle_available' && $doctorId > 0) {
            $doc = db()->fetch("SELECT is_available FROM doctors WHERE id = ?", [$doctorId]);
            if ($doc) {
                $newVal = $doc['is_available'] ? 0 : 1;
                db()->update("UPDATE doctors SET is_available = ? WHERE id = ?", [$newVal, $doctorId]);
                logAudit(getUserId(), 'doctor_availability_toggle', "Doctor #$doctorId availability set to " . ($newVal ? 'available' : 'unavailable'));
                setFlash('success', 'Doctor availability updated.');
            }
            redirect('admin/doctors.php');
        } else {
            redirect('admin/doctors.php');
        }
    }
}

$sql = "SELECT d.*, u.email, u.is_active, u.profile_pic
        FROM doctors d
        JOIN users u ON d.user_id = u.id
        WHERE u.role = 'doctor' AND d.type = 'doctor'";
$params = [];

if ($search) {
    $sql .= " AND (d.first_name LIKE ? OR d.last_name LIKE ? OR d.doctor_code LIKE ? OR u.email LIKE ? OR d.specialization LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s, $s]);
}
$sql .= " ORDER BY d.created_at DESC LIMIT 200";
$doctors = db()->fetchAll($sql, $params);

$activeServices = db()->fetchAll("SELECT * FROM services WHERE is_active = 1 ORDER BY department ASC, name ASC");
$assignMap = [];
foreach (db()->fetchAll("SELECT ds.doctor_id, ds.service_id FROM doctor_services ds") as $ds) {
    $assignMap[$ds['doctor_id']][] = $ds['service_id'];
}
require_once '../includes/header.php';
?>

<div class="page-content">
    <div class="d-flex justify-between align-center mb-20">
        <div>
            <div class="page-title">Doctors</div>
            <div class="page-subtitle">View and manage all registered doctors.</div>
        </div>
        <button class="btn btn-primary" onclick="document.getElementById('addDoctorModal').style.display='flex'">+ Add Doctor</button>
    </div>

    <div class="card mb-30">
        <form method="GET" class="filters-bar">
            <input type="text" name="search" class="form-control" placeholder="Search by name, code, email, or specialization..." value="<?php echo escape($search); ?>">
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
            <?php if ($search): ?>
                <a href="doctors.php" class="btn btn-sm btn-outline">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <?php if (empty($doctors)): ?>
            <div class="empty-state"><h3>No doctors found</h3></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Specialization</th>
                        <th>Services</th>
                        <th>Available</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($doctors as $d):
                        $assignedIds = $assignMap[$d['id']] ?? [];
                        $assignedNames = [];
                        foreach ($activeServices as $as) {
                            if (in_array($as['id'], $assignedIds)) {
                                $assignedNames[] = $as['name'];
                            }
                        }
                    ?>
                    <tr>
                        <td><?php echo avatarThumb($d['profile_pic'] ?? '', strtoupper(substr($d['first_name'] ?? 'U', 0, 1) . substr($d['last_name'] ?? '', 0, 1))); ?></td>
                        <td><strong><?php echo escape($d['doctor_code']); ?></strong></td>
                        <td><?php echo escape($d['first_name'] . ' ' . $d['last_name']); ?></td>
                        <td><?php echo escape($d['email']); ?></td>
                        <td><?php echo escape($d['specialization'] ?? '--'); ?></td>
                        <td>
                            <?php if ($assignedNames): ?>
                                <?php foreach ($assignedNames as $an): ?>
                                    <span class="badge badge-active"><?php echo escape($an); ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="badge badge-inactive">None</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $d['is_available'] ? 'badge-active' : 'badge-inactive'; ?>">
                                <?php echo $d['is_available'] ? 'Available' : 'Unavailable'; ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?php echo $d['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                <?php echo $d['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td><?php echo $d['created_at'] ? date('M d, Y', strtotime($d['created_at'])) : '--'; ?></td>
                        <td>
                            <div class="d-flex gap-10">
                                <button type="button" class="btn btn-sm btn-outline" data-doctor-id="<?php echo $d['id']; ?>" data-assigned="<?php echo escape(implode(',', $assignedIds)); ?>" onclick="openAssignServices(this)">Services</button>
                                <form method="POST" style="display:inline;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="toggle_available">
                                    <input type="hidden" name="doctor_id" value="<?php echo $d['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline"><?php echo $d['is_available'] ? 'Set Unavailable' : 'Set Available'; ?></button>
                                </form>
                                <?php if ($d['is_active']): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Deactivate this doctor?')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="deactivate">
                                    <input type="hidden" name="user_id" value="<?php echo $d['user_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Deactivate</button>
                                </form>
                                <?php else: ?>
                                <form method="POST" style="display:inline;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="activate">
                                    <input type="hidden" name="user_id" value="<?php echo $d['user_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-success">Activate</button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to DELETE this doctor permanently? This removes all their records and cannot be undone.')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?php echo $d['user_id']; ?>">
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

<div class="modal" id="addDoctorModal" style="display:none;">
    <div class="modal-content" style="max-width:640px;">
        <div class="modal-header">
            <h3>Add New Doctor</h3>
            <a href="doctors.php" class="modal-close">&times;</a>
        </div>
        <form method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="create">
            <p style="color:var(--gray);margin-bottom:16px;">The doctor will receive a login account and appear to patients for the services assigned below.</p>
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
                    <label>Specialization</label>
                    <input type="text" name="specialization" class="form-control" placeholder="e.g. Cardiology">
                </div>
                <div class="form-group">
                    <label>Qualification</label>
                    <input type="text" name="qualification" class="form-control" placeholder="e.g. MBChB, MMed">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>License Number</label>
                    <input type="text" name="license_number" class="form-control">
                </div>
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
            </div>
            <div class="form-group">
                <label>Assigned Services</label>
                <div class="checkbox-grid">
                    <?php foreach ($activeServices as $as): ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="services[]" value="<?php echo $as['id']; ?>">
                            <span><?php echo escape($as['name']); ?> <small style="color:var(--gray);">(<?php echo escape($as['department']); ?>)</small></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if (empty($activeServices)): ?><small style="color:var(--gray);">No active services yet. Add services first.</small><?php endif; ?>
            </div>
            <div class="form-group">
                <label class="checkbox-item">
                    <input type="checkbox" name="is_available" value="1" checked>
                    <span>Available to accept patients immediately</span>
                </label>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Add Doctor</button>
                <a href="doctors.php" class="btn btn-outline" style="margin-left:10px;">Cancel</a>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="assignServicesModal" style="display:none;">
    <div class="modal-content" style="max-width:620px;">
        <div class="modal-header">
            <h3>Assign Services</h3>
            <a href="doctors.php" class="modal-close">&times;</a>
        </div>
        <form method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="assign_services">
            <input type="hidden" name="doctor_id" id="assignDoctorId">
            <p style="color:var(--gray);margin-bottom:16px;">Select the services this doctor will manage and appear for on the patient side.</p>
            <div class="form-group">
                <div class="checkbox-grid">
                    <?php foreach ($activeServices as $as): ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="services[]" value="<?php echo $as['id']; ?>" class="assign-svc">
                            <span><?php echo escape($as['name']); ?> <small style="color:var(--gray);">(<?php echo escape($as['department']); ?>)</small></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Save Assignments</button>
                <a href="doctors.php" class="btn btn-outline" style="margin-left:10px;">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function openAssignServices(btn) {
    var doctorId = btn.getAttribute('data-doctor-id');
    var assigned = (btn.getAttribute('data-assigned') || '').split(',').filter(function (v) { return v !== ''; });
    document.getElementById('assignDoctorId').value = doctorId;
    document.querySelectorAll('.assign-svc').forEach(function (cb) {
        cb.checked = assigned.indexOf(String(cb.value)) !== -1;
    });
    document.getElementById('assignServicesModal').style.display = 'flex';
}
</script>

<?php require_once '../includes/footer.php'; ?>