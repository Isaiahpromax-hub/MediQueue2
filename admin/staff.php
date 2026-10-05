<?php
$pageTitle = 'Manage Receptionists';
require_once '../includes/header.php';
require_once '../includes/auth.php';
requireAdmin();

function nextStaffCode() {
    $rows = db()->fetchAll("SELECT staff_code FROM staff");
    $max = 0;
    foreach ($rows as $r) {
        if (preg_match('/REC(\d+)$/', $r['staff_code'], $m)) {
            $max = max($max, intval($m[1]));
        }
    }
    do {
        $max++;
        $code = 'REC' . str_pad($max, 3, '0', STR_PAD_LEFT);
    } while (db()->fetch("SELECT id FROM staff WHERE staff_code = ?", [$code]));
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
            $position = trim($_POST['position'] ?? '');

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
                    "INSERT INTO users (email, password, role, is_active) VALUES (?, ?, 'receptionist', 1)",
                    [$email, $hashedPassword]
                );

                db()->insert(
                    "INSERT INTO staff (user_id, staff_code, first_name, last_name, phone, position) VALUES (?, ?, ?, ?, ?, ?)",
                    [$newUserId, nextStaffCode(), $firstName, $lastName, $phone ?: null, $position ?: 'Front Desk']
                );

                logAudit(getUserId(), 'staff_create', "Created receptionist account: $firstName $lastName ($email)");
                setFlash('success', "Receptionist $firstName $lastName added successfully.");
            }
            redirect('admin/staff.php');

        } elseif ($action === 'activate' && $userId > 0) {
            db()->update("UPDATE users SET is_active = 1 WHERE id = ? AND role = 'receptionist'", [$userId]);
            logAudit(getUserId(), 'staff_activate', "Activated receptionist account (user #$userId)");
            setFlash('success', 'Receptionist account activated.');
} elseif ($action === 'deactivate' && $userId > 0) {
            db()->update("UPDATE users SET is_active = 0 WHERE id = ? AND role = 'receptionist'", [$userId]);
            logAudit(getUserId(), 'staff_deactivate', "Deactivated receptionist account (user #$userId)");
            setFlash('success', 'Receptionist account deactivated.');
            redirect('admin/staff.php');
        } elseif ($action === 'delete' && $userId > 0) {
            $victim = db()->fetch("SELECT email FROM users WHERE id = ? AND role = 'receptionist'", [$userId]);
            if (!$victim) {
                setFlash('error', 'Receptionist account not found.');
            } else {
                db()->update("DELETE FROM users WHERE id = ?", [$userId]);
                logAudit(getUserId(), 'staff_delete', "Deleted receptionist account permanently: {$victim['email']} (user #$userId)");
                setFlash('success', 'Receptionist account deleted permanently.');
            }
            redirect('admin/staff.php');
        }
        redirect('admin/staff.php');
    }
}

$sql = "SELECT s.*, u.email, u.is_active, u.profile_pic
        FROM staff s
        JOIN users u ON s.user_id = u.id
        WHERE u.role = 'receptionist'";
$params = [];

if ($search) {
    $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.staff_code LIKE ? OR u.email LIKE ? OR s.position LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s, $s]);
}
$sql .= " ORDER BY s.created_at DESC LIMIT 200";
$staffMembers = db()->fetchAll($sql, $params);
?>

<div class="page-content">
    <div class="d-flex justify-between align-center mb-20">
        <div>
            <div class="page-title">Receptionists</div>
            <div class="page-subtitle">View and manage receptionist accounts.</div>
        </div>
        <button class="btn btn-primary" onclick="document.getElementById('addStaffModal').style.display='flex'">+ Add Receptionist</button>
    </div>

    <div class="card mb-30">
        <form method="GET" class="filters-bar">
            <input type="text" name="search" class="form-control" placeholder="Search by name, code, email, or position..." value="<?php echo escape($search); ?>">
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
            <?php if ($search): ?>
                <a href="staff.php" class="btn btn-sm btn-outline">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <?php if (empty($staffMembers)): ?>
            <div class="empty-state"><h3>No staff members found</h3></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Position</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staffMembers as $s): ?>
                    <tr>
                        <td><?php echo avatarThumb($s['profile_pic'] ?? '', strtoupper(substr($s['first_name'] ?? 'U', 0, 1) . substr($s['last_name'] ?? '', 0, 1))); ?></td>
                        <td><strong><?php echo escape($s['staff_code']); ?></strong></td>
                        <td><?php echo escape($s['first_name'] . ' ' . $s['last_name']); ?></td>
                        <td><?php echo escape($s['email']); ?></td>
                        <td><?php echo escape($s['position'] ?? '--'); ?></td>
                        <td>
                            <span class="badge <?php echo $s['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                <?php echo $s['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-10">
                                <?php if ($s['is_active']): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Deactivate this staff member?')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="deactivate">
                                    <input type="hidden" name="user_id" value="<?php echo $s['user_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Deactivate</button>
                                </form>
                                <?php else: ?>
                                <form method="POST" style="display:inline;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="activate">
                                    <input type="hidden" name="user_id" value="<?php echo $s['user_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-success">Activate</button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to DELETE this receptionist permanently? This removes all their records and cannot be undone.')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?php echo $s['user_id']; ?>">
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

<div class="modal" id="addStaffModal" style="display:none;">
    <div class="modal-content" style="max-width:600px;">
        <div class="modal-header">
            <h3>Add New Receptionist</h3>
            <a href="staff.php" class="modal-close">&times;</a>
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
                    <label>Position</label>
                    <input type="text" name="position" class="form-control" placeholder="e.g. Front Desk">
                </div>
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" class="form-control" required minlength="6">
                </div>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Add Receptionist</button>
                <a href="staff.php" class="btn btn-outline" style="margin-left:10px;">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
