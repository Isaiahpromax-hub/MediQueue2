<?php
$pageTitle = 'Admin Dashboard';
require_once '../includes/header.php';
require_once '../includes/auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        $userId = intval($_POST['user_id'] ?? 0);
        if ($action === 'approve' && $userId > 0) {
            db()->update(
                "UPDATE users SET is_active = 1 WHERE id = ? AND role IN ('doctor','nurse','receptionist') AND is_active = 0",
                [$userId]
            );
            $pendingCheck = db()->fetch("SELECT role, email FROM users WHERE id = ?", [$userId]);
            if ($pendingCheck) {
                logAudit(getUserId(), 'staff_activate', "Approved {$pendingCheck['role']} account ($userId)");
                createNotification($userId, 'Account approved', 'Your account has been approved by an administrator. You can now sign in.', 'system');
                setFlash('success', 'Staff account approved. They can now log in.');
            } else {
                setFlash('danger', 'Account could not be approved.');
            }
            redirect('admin/dashboard.php');
        }
    }
}

$totalPatients = db()->fetch("SELECT COUNT(*) as count FROM patients")['count'];
$totalDoctors = (int) db()->fetch("SELECT COUNT(*) AS c FROM doctors WHERE type = 'doctor'")['c'];
$totalNurses = (int) db()->fetch("SELECT COUNT(*) AS c FROM doctors WHERE type = 'nurse'")['c'];
$totalStaff = (int) db()->fetch("SELECT COUNT(*) AS c FROM users WHERE role IN ('doctor','nurse','receptionist')")['c'];
$totalReceptionists = (int) db()->fetch("SELECT COUNT(*) AS c FROM users WHERE role = 'receptionist'")['c'];
$todayAppts = db()->fetch("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = CURDATE()")['count'];
$waitingPatients = db()->fetch("SELECT COUNT(*) as count FROM queue_entries WHERE queue_date = CURDATE() AND status = 'Waiting'")['count'];
$servedToday = db()->fetch("SELECT COUNT(*) as count FROM queue_entries WHERE queue_date = CURDATE() AND status = 'Served'")['count'];
$activeQueue = db()->fetch("SELECT COUNT(*) as count FROM queue_entries WHERE queue_date = CURDATE() AND status IN ('Waiting','Called','Serving')")['count'];
$cancelledAppts = db()->fetch("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = CURDATE() AND status = 'Cancelled'")['count'];

$recentAppts = db()->fetchAll(
    "SELECT a.*, p.first_name, p.last_name, s.name as service_name
     FROM appointments a
     JOIN patients p ON a.patient_id = p.id
     JOIN services s ON a.service_id = s.id
     WHERE a.appointment_date = CURDATE()
     ORDER BY a.appointment_time ASC LIMIT 5"
);

$recentQueue = db()->fetchAll(
    "SELECT q.*, p.first_name, p.last_name, s.name as service_name
     FROM queue_entries q
     JOIN patients p ON q.patient_id = p.id
     JOIN services s ON q.service_id = s.id
     WHERE q.queue_date = CURDATE()
     ORDER BY q.position ASC LIMIT 5"
);

$pendingStaff = db()->fetchAll(
    "SELECT u.id AS user_id, u.email, u.role,
            COALESCE(d.first_name, s.first_name) AS first_name,
            COALESCE(d.last_name, s.last_name) AS last_name,
            COALESCE(d.doctor_code, s.staff_code) AS code
     FROM users u
     LEFT JOIN doctors d ON d.user_id = u.id
     LEFT JOIN staff s ON s.user_id = u.id
     WHERE u.role IN ('doctor','nurse','receptionist') AND u.is_active = 0
     ORDER BY u.created_at DESC LIMIT 20"
);
?>

<div class="page-content">
    <div class="page-title">Admin Dashboard</div>
    <div class="page-subtitle">System overview and management hub.</div>

    <?php if (!empty($pendingStaff)): ?>
    <div class="card mb-30" style="border:2px solid var(--warning);">
        <div class="card-header">
            <div class="card-title">&#128276; Pending Staff Approvals (<?php echo count($pendingStaff); ?>)</div>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Code</th><th>Name</th><th>Role</th><th>Email</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingStaff as $p): ?>
                    <tr>
                        <td><strong><?php echo escape($p['code'] ?? '--'); ?></strong></td>
                        <td><?php echo escape($p['first_name'] . ' ' . $p['last_name']); ?></td>
                        <td><span class="badge badge-info"><?php echo ucfirst($p['role']); ?></span></td>
                        <td><?php echo escape($p['email']); ?></td>
                        <td>
                            <div class="d-flex gap-10">
                                <form method="POST" style="display:inline;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="user_id" value="<?php echo $p['user_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                </form>
                                <?php if ($p['role'] === 'receptionist'): ?>
                                <a href="staff.php" class="btn btn-sm btn-outline">Manage</a>
                                <?php elseif ($p['role'] === 'doctor'): ?>
                                <a href="doctors.php" class="btn btn-sm btn-outline">Manage</a>
                                <?php else: ?>
                                <a href="nurses.php" class="btn btn-sm btn-outline">Manage</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-icon blue">&#128101;</div>
            <div class="stat-info">
                <h3><?php echo $totalPatients; ?></h3>
                <p>Total Patients</p>
            </div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon green">&#129657;</div>
            <div class="stat-info">
                <h3><?php echo $totalDoctors; ?></h3>
                <p>Total Doctors</p>
            </div>
        </div>
        <div class="stat-card purple">
            <div class="stat-icon purple">&#128138;</div>
            <div class="stat-info">
                <h3><?php echo $totalNurses; ?></h3>
                <p>Total Nurses</p>
            </div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon orange">&#128222;</div>
            <div class="stat-info">
                <h3><?php echo $totalReceptionists; ?></h3>
                <p>Total Receptionists</p>
            </div>
        </div>
        <div class="stat-card purple">
            <div class="stat-icon purple">&#128188;</div>
            <div class="stat-info">
                <h3><?php echo $totalStaff; ?></h3>
                <p>Total Staff (Doctors, Nurses, Receptionists)</p>
            </div>
        </div>
        <div class="stat-card teal">
            <div class="stat-icon teal">&#128197;</div>
            <div class="stat-info">
                <h3><?php echo $todayAppts; ?></h3>
                <p>Today's Appointments</p>
            </div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon orange">&#9201;</div>
            <div class="stat-info">
                <h3><?php echo $waitingPatients; ?></h3>
                <p>Waiting Patients</p>
            </div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon green">&#10004;</div>
            <div class="stat-info">
                <h3><?php echo $servedToday; ?></h3>
                <p>Patients Served Today</p>
            </div>
        </div>
        <div class="stat-card blue">
            <div class="stat-icon blue">&#128203;</div>
            <div class="stat-info">
                <h3><?php echo $activeQueue; ?></h3>
                <p>Active Queue Entries</p>
            </div>
        </div>
        <div class="stat-card red">
            <div class="stat-icon red">&#10006;</div>
            <div class="stat-info">
                <h3><?php echo $cancelledAppts; ?></h3>
                <p>Cancelled Appointments</p>
            </div>
        </div>
    </div>

    <div class="grid-2 mb-30">
        <div class="card">
            <div class="card-header">
                <div class="card-title">Today's Appointments</div>
                <a href="appointments.php" class="btn btn-sm btn-outline">View All</a>
            </div>
            <?php if (empty($recentAppts)): ?>
                <div class="empty-state"><p>No appointments scheduled for today.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr><th>Time</th><th>Patient</th><th>Service</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentAppts as $a): ?>
                        <tr>
                            <td><?php echo date('h:i A', strtotime($a['appointment_time'])); ?></td>
                            <td><?php echo escape($a['first_name'] . ' ' . $a['last_name']); ?></td>
                            <td><?php echo escape($a['service_name']); ?></td>
                            <td><span class="badge badge-<?php echo strtolower($a['status']); ?>"><?php echo $a['status']; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">Queue Overview</div>
                <a href="queues.php" class="btn btn-sm btn-primary">Manage Queue</a>
            </div>
            <?php if (empty($recentQueue)): ?>
                <div class="empty-state"><p>No queue entries today.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr><th>#</th><th>Patient</th><th>Service</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentQueue as $q): ?>
                        <tr>
                            <td><strong><?php echo escape($q['queue_number']); ?></strong></td>
                            <td><?php echo escape($q['first_name'] . ' ' . $q['last_name']); ?></td>
                            <td><?php echo escape($q['service_name']); ?></td>
                            <td><span class="badge badge-<?php echo strtolower($q['status']); ?>"><?php echo $q['status']; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Quick Actions</div>
        </div>
        <div class="stats-grid" style="padding:0;">
            <a href="patients.php" class="stat-card blue" style="text-decoration:none;cursor:pointer;">
                <div class="stat-icon blue">&#128101;</div>
                <div class="stat-info">
                    <h3 style="font-size:1rem;">Manage Patients</h3>
                    <p>View & manage all patients</p>
                </div>
            </a>
            <a href="doctors.php" class="stat-card green" style="text-decoration:none;cursor:pointer;">
                <div class="stat-icon green">&#129657;</div>
                <div class="stat-info">
                    <h3 style="font-size:1rem;">Manage Doctors</h3>
                    <p>View & manage all doctors</p>
                </div>
            </a>
            <a href="staff.php" class="stat-card purple" style="text-decoration:none;cursor:pointer;">
                <div class="stat-icon purple">&#128188;</div>
                <div class="stat-info">
                    <h3 style="font-size:1rem;">Manage Receptionists</h3>
                    <p>View & manage all receptionists</p>
                </div>
            </a>
            <a href="appointments.php" class="stat-card teal" style="text-decoration:none;cursor:pointer;">
                <div class="stat-icon teal">&#128197;</div>
                <div class="stat-info">
                    <h3 style="font-size:1rem;">Appointments</h3>
                    <p>Manage all appointments</p>
                </div>
            </a>
            <a href="queues.php" class="stat-card orange" style="text-decoration:none;cursor:pointer;">
                <div class="stat-icon orange">&#9201;</div>
                <div class="stat-info">
                    <h3 style="font-size:1rem;">Queue Management</h3>
                    <p>Manage today's queue</p>
                </div>
            </a>
            <a href="services.php" class="stat-card blue" style="text-decoration:none;cursor:pointer;">
                <div class="stat-icon blue">&#9881;</div>
                <div class="stat-info">
                    <h3 style="font-size:1rem;">Services</h3>
                    <p>Manage available services</p>
                </div>
            </a>
            <a href="reports.php" class="stat-card green" style="text-decoration:none;cursor:pointer;">
                <div class="stat-icon green">&#128200;</div>
                <div class="stat-info">
                    <h3 style="font-size:1rem;">Reports</h3>
                    <p>View analytics & reports</p>
                </div>
            </a>
            <a href="settings.php" class="stat-card red" style="text-decoration:none;cursor:pointer;">
                <div class="stat-icon red">&#9881;</div>
                <div class="stat-info">
                    <h3 style="font-size:1rem;">Settings</h3>
                    <p>System configuration</p>
                </div>
            </a>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
