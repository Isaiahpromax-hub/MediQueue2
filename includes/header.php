<?php
require_once __DIR__ . '/auth.php';

$currentUser = getCurrentUser();
$unreadCount = 0;
$userName = '';
$userInitials = '';
$userRole = '';
$userProfilePic = '';

if ($currentUser) {
    $userName = $currentUser['email'];
    $userRole = $currentUser['role'];
    $userInitials = strtoupper(substr($userName, 0, 1));
    $userProfilePic = $currentUser['profile_pic'] ?? '';
    $unreadCount = getUnreadNotificationCount($currentUser['id']);

    if ($userRole === 'patient') {
        $patient = db()->fetch("SELECT * FROM patients WHERE user_id = ?", [$currentUser['id']]);
        if ($patient) {
            $userName = $patient['first_name'] . ' ' . $patient['last_name'];
            $userInitials = strtoupper(substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1));
        }
    } elseif ($userRole === 'doctor' || $userRole === 'nurse') {
        $doc = db()->fetch("SELECT * FROM doctors WHERE user_id = ?", [$currentUser['id']]);
        if ($doc) {
            $userName = $doc['first_name'] . ' ' . $doc['last_name'];
            $userInitials = strtoupper(substr($doc['first_name'], 0, 1) . substr($doc['last_name'], 0, 1));
        }
    } elseif ($userRole === 'receptionist' || $userRole === 'admin') {
        $stf = db()->fetch("SELECT * FROM staff WHERE user_id = ?", [$currentUser['id']]);
        if ($stf) {
            $userName = $stf['first_name'] . ' ' . $stf['last_name'];
            $userInitials = strtoupper(substr($stf['first_name'], 0, 1) . substr($stf['last_name'], 0, 1));
        } elseif ($userRole === 'admin') {
            $userName = 'Administrator';
            $userInitials = 'AD';
        }
    }
}

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$basePath = '/' . explode('/', trim($_SERVER['REQUEST_URI'], '/'))[0];

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediQueue - Healthcare Queue & Appointment Management</title>
    <link rel="stylesheet" href="/MediQueue2/assets/css/style.css">
</head>
<body>
<div class="app-layout">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div>
                <div class="sidebar-logo">Medi<span>Queue</span></div>
                <div class="sidebar-subtitle"><?php echo ucfirst($userRole); ?> Portal</div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <?php if ($userRole === 'patient'): ?>
                <div class="nav-section">Main</div>
                <a href="/MediQueue2/patient/dashboard.php" class="<?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                    <span class="icon">&#9632;</span> Dashboard
                </a>
                <div class="nav-section">Services</div>
                <a href="/MediQueue2/patient/book_appointment.php" class="<?php echo $currentPage === 'book_appointment' ? 'active' : ''; ?>">
                    <span class="icon">&#9998;</span> Book Appointment
                </a>
                <a href="/MediQueue2/patient/appointments.php" class="<?php echo $currentPage === 'appointments' ? 'active' : ''; ?>">
                    <span class="icon">&#9783;</span> My Appointments
                </a>
                <div class="nav-section">Queue</div>
                <a href="/MediQueue2/patient/join_queue.php" class="<?php echo $currentPage === 'join_queue' ? 'active' : ''; ?>">
                    <span class="icon">&#10010;</span> Join Queue
                </a>
                <a href="/MediQueue2/patient/check_queue.php" class="<?php echo $currentPage === 'check_queue' ? 'active' : ''; ?>">
                    <span class="icon">&#9201;</span> Check Queue
                </a>
                <a href="/MediQueue2/patient/queue_history.php" class="<?php echo $currentPage === 'queue_history' ? 'active' : ''; ?>">
                    <span class="icon">&#9776;</span> Queue History
                </a>
                <div class="nav-section">Account</div>
                <a href="/MediQueue2/notifications.php" class="<?php echo $currentPage === 'notifications' ? 'active' : ''; ?>">
                    <span class="icon">&#128276;</span> Notifications
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </a>
                <a href="/MediQueue2/patient/profile.php" class="<?php echo $currentPage === 'profile' ? 'active' : ''; ?>">
                    <span class="icon">&#128100;</span> My Profile
                </a>

            <?php elseif ($userRole === 'receptionist'): ?>
                <div class="nav-section">Main</div>
                <a href="/MediQueue2/staff/dashboard.php" class="<?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                    <span class="icon">&#9632;</span> Dashboard
                </a>
                <div class="nav-section">Management</div>
                <a href="/MediQueue2/staff/appointments.php" class="<?php echo $currentPage === 'appointments' ? 'active' : ''; ?>">
                    <span class="icon">&#9783;</span> Appointments
                </a>
                <a href="/MediQueue2/staff/queue.php" class="<?php echo $currentPage === 'queue' ? 'active' : ''; ?>">
                    <span class="icon">&#9201;</span> Queue Management
                </a>
                <a href="/MediQueue2/staff/patients.php" class="<?php echo $currentPage === 'patients' ? 'active' : ''; ?>">
                    <span class="icon">&#128101;</span> Patients
                </a>
                <div class="nav-section">Tools</div>
                <a href="/MediQueue2/public/display.php" target="_blank">
                    <span class="icon">&#128265;</span> Waiting-Room Board
                </a>
                <div class="nav-section">Account</div>
                <a href="/MediQueue2/notifications.php" class="<?php echo $currentPage === 'notifications' ? 'active' : ''; ?>">
                    <span class="icon">&#128276;</span> Notifications
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </a>
                <a href="/MediQueue2/staff/profile.php" class="<?php echo $currentPage === 'profile' ? 'active' : ''; ?>">
                    <span class="icon">&#128100;</span> Profile
                </a>

            <?php elseif ($userRole === 'doctor'): ?>
                <div class="nav-section">Main</div>
                <a href="/MediQueue2/doctor/dashboard.php" class="<?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                    <span class="icon">&#9632;</span> Dashboard
                </a>
                <div class="nav-section">Clinical</div>
                <a href="/MediQueue2/doctor/appointments.php" class="<?php echo $currentPage === 'appointments' ? 'active' : ''; ?>">
                    <span class="icon">&#9783;</span> Appointments
                </a>
                <a href="/MediQueue2/doctor/queue.php" class="<?php echo $currentPage === 'queue' ? 'active' : ''; ?>">
                    <span class="icon">&#9201;</span> Queue
                </a>
                <a href="/MediQueue2/doctor/history.php" class="<?php echo $currentPage === 'history' ? 'active' : ''; ?>">
                    <span class="icon">&#128221;</span> Patients Worked
                </a>
                <div class="nav-section">Account</div>
                <a href="/MediQueue2/notifications.php" class="<?php echo $currentPage === 'notifications' ? 'active' : ''; ?>">
                    <span class="icon">&#128276;</span> Notifications
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </a>
                <a href="/MediQueue2/doctor/profile.php" class="<?php echo $currentPage === 'profile' ? 'active' : ''; ?>">
                    <span class="icon">&#128100;</span> Profile
                </a>

            <?php elseif ($userRole === 'nurse'): ?>
                <div class="nav-section">Main</div>
                <a href="/MediQueue2/nurse/dashboard.php" class="<?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                    <span class="icon">&#9632;</span> Dashboard
                </a>
                <div class="nav-section">Clinical</div>
                <a href="/MediQueue2/nurse/appointments.php" class="<?php echo $currentPage === 'appointments' ? 'active' : ''; ?>">
                    <span class="icon">&#9783;</span> Appointments
                </a>
                <a href="/MediQueue2/nurse/queue.php" class="<?php echo $currentPage === 'queue' ? 'active' : ''; ?>">
                    <span class="icon">&#9201;</span> Queue
                </a>
                <a href="/MediQueue2/nurse/history.php" class="<?php echo $currentPage === 'history' ? 'active' : ''; ?>">
                    <span class="icon">&#128221;</span> Patients Worked
                </a>
                <div class="nav-section">Account</div>
                <a href="/MediQueue2/notifications.php" class="<?php echo $currentPage === 'notifications' ? 'active' : ''; ?>">
                    <span class="icon">&#128276;</span> Notifications
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </a>
                <a href="/MediQueue2/nurse/profile.php" class="<?php echo $currentPage === 'profile' ? 'active' : ''; ?>">
                    <span class="icon">&#128100;</span> Profile
                </a>

            <?php elseif ($userRole === 'admin'): ?>
                <div class="nav-section">Main</div>
                <a href="/MediQueue2/admin/dashboard.php" class="<?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                    <span class="icon">&#9632;</span> Dashboard
                </a>
                <div class="nav-section">Management</div>
                <a href="/MediQueue2/admin/patients.php" class="<?php echo $currentPage === 'patients' ? 'active' : ''; ?>">
                    <span class="icon">&#128101;</span> Patients
                </a>
                <a href="/MediQueue2/admin/doctors.php" class="<?php echo $currentPage === 'doctors' ? 'active' : ''; ?>">
                    <span class="icon">&#129657;</span> Doctors
                </a>
                <a href="/MediQueue2/admin/nurses.php" class="<?php echo $currentPage === 'nurses' ? 'active' : ''; ?>">
                    <span class="icon">&#129657;</span> Nurses
                </a>
                <a href="/MediQueue2/admin/staff.php" class="<?php echo $currentPage === 'staff' ? 'active' : ''; ?>">
                    <span class="icon">&#128188;</span> Receptionists
                </a>
                <a href="/MediQueue2/admin/appointments.php" class="<?php echo $currentPage === 'appointments' ? 'active' : ''; ?>">
                    <span class="icon">&#128197;</span> Appointments
                </a>
                <a href="/MediQueue2/admin/queues.php" class="<?php echo $currentPage === 'queues' ? 'active' : ''; ?>">
                    <span class="icon">&#9201;</span> Queues
                </a>
                <a href="/MediQueue2/admin/services.php" class="<?php echo $currentPage === 'services' ? 'active' : ''; ?>">
                    <span class="icon">&#9881;</span> Services
                </a>
                <a href="/MediQueue2/admin/contact_messages.php" class="<?php echo $currentPage === 'contact_messages' ? 'active' : ''; ?>">
                    <span class="icon">&#9993;</span> Contact Messages
                </a>
                <div class="nav-section">Analytics</div>
                <a href="/MediQueue2/admin/reports.php" class="<?php echo $currentPage === 'reports' ? 'active' : ''; ?>">
                    <span class="icon">&#128200;</span> Reports
                </a>
                <a href="/MediQueue2/admin/audit_logs.php" class="<?php echo $currentPage === 'audit_logs' ? 'active' : ''; ?>">
                    <span class="icon">&#128220;</span> Audit Logs
                </a>
                <a href="/MediQueue2/admin/sms_log.php" class="<?php echo $currentPage === 'sms_log' ? 'active' : ''; ?>">
                    <span class="icon">&#128231;</span> SMS Log
                </a>
                <a href="/MediQueue2/admin/settings.php" class="<?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
                    <span class="icon">&#9881;</span> Settings
                </a>
                <div class="nav-section">Account</div>
                <a href="/MediQueue2/notifications.php" class="<?php echo $currentPage === 'notifications' ? 'active' : ''; ?>">
                    <span class="icon">&#128276;</span> Notifications
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </a>
                <a href="/MediQueue2/admin/profile.php" class="<?php echo $currentPage === 'profile' ? 'active' : ''; ?>">
                    <span class="icon">&#128100;</span> Profile
                </a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-footer">
            <a href="/MediQueue2/logout.php">
                <span class="icon">&#9211;</span> Logout
            </a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-header">
            <div class="header-left">
                <button class="menu-toggle" id="menuToggle">&#9776;</button>
                <h2><?php echo $pageTitle ?? 'Dashboard'; ?></h2>
            </div>
            <div class="header-right">
                <?php if ($userRole === 'patient' || $userRole === 'doctor' || $userRole === 'nurse'): ?>
                <button class="notification-btn" id="notifBtn" onclick="toggleNotifications()">
                    &#128276;
                    <?php if ($unreadCount > 0): ?>
                        <span class="notification-badge" id="notifCount"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </button>
                <?php endif; ?>
                <div class="user-menu" id="userMenuBtn" onclick="toggleUserMenu()">
                    <div class="user-avatar"><?php if ($userProfilePic): ?><img src="/MediQueue2/uploads/profile/<?php echo escape($userProfilePic); ?>" alt="<?php echo escape($userName); ?>"><?php else: ?><?php echo $userInitials; ?><?php endif; ?></div>
                    <div class="user-info">
                        <span class="user-name"><?php echo escape($userName); ?></span>
                        <span class="user-role"><?php echo ucfirst($userRole); ?></span>
                    </div>
                    <div class="dropdown-menu" id="userDropdown">
                        <a href="/MediQueue2/<?php echo $userRole === 'patient' ? 'patient' : ($userRole === 'doctor' ? 'doctor' : ($userRole === 'nurse' ? 'nurse' : ($userRole === 'admin' ? 'admin' : 'staff'))); ?>/dashboard.php">&#127968; Dashboard</a>
                        <?php if ($userRole === 'patient'): ?>
                        <a href="/MediQueue2/patient/profile.php">&#128100; Profile</a>
                        <?php elseif ($userRole === 'doctor'): ?>
                        <a href="/MediQueue2/doctor/profile.php">&#128100; Profile</a>
                        <?php elseif ($userRole === 'nurse'): ?>
                        <a href="/MediQueue2/nurse/profile.php">&#128100; Profile</a>
                        <?php elseif ($userRole === 'admin'): ?>
                        <a href="/MediQueue2/admin/profile.php">&#128100; Profile</a>
                        <?php elseif ($userRole === 'receptionist'): ?>
                        <a href="/MediQueue2/staff/profile.php">&#128100; Profile</a>
                        <?php endif; ?>
                        <hr>
                        <a href="/MediQueue2/logout.php">&#9211; Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <?php if ($flash): ?>
        <div class="page-content" style="padding-bottom: 0;">
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <?php echo escape($flash['message']); ?>
                <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
            </div>
        </div>
        <?php endif; ?>
