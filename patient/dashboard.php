<?php <!-- Git test change -->
$pageTitle = 'Dashboard';
require_once '../includes/header.php';
requirePatient();

$patient = db()->fetch("SELECT * FROM patients WHERE user_id = ?", [$_SESSION['user_id']]);
$patientId = $patient['id'];

$upcomingAppts = db()->fetch("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? AND appointment_date >= CURDATE() AND status IN ('Pending','Confirmed')", [$patientId]);
$queueEntry = db()->fetch("SELECT * FROM queue_entries WHERE patient_id = ? AND queue_date = CURDATE() AND status IN ('Waiting','Called','Serving') ORDER BY id DESC LIMIT 1", [$patientId]);

$peopleAhead = 0;
$estimatedWait = 0;
$serviceEstimate = 15;

if ($queueEntry) {
    $service = db()->fetch("SELECT estimated_duration FROM services WHERE id = ?", [$queueEntry['service_id']]);
    $serviceEstimate = $service['estimated_duration'] ?? 15;
    if ($queueEntry['status'] === 'Waiting') {
        $ahead = db()->fetch("SELECT COUNT(*) as count FROM queue_entries WHERE service_id = ? AND queue_date = CURDATE() AND status = 'Waiting' AND position < ?", [$queueEntry['service_id'], $queueEntry['position']]);
        $peopleAhead = $ahead['count'] ?? 0;
        $estimatedWait = $peopleAhead * $serviceEstimate;
    }
}

$totalPatients = db()->fetch("SELECT COUNT(*) as count FROM patients")['count'];
$totalDoctors = db()->fetch("SELECT COUNT(*) as count FROM doctors")['count'];
?>

<div class="page-content">
    <div class="page-title">Welcome back, <?php echo escape($patient['first_name']); ?>!</div>
    <div class="page-subtitle">Here's what's happening with your healthcare today.</div>

    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-icon blue">&#128197;</div>
            <div class="stat-info">
                <h3><?php echo $upcomingAppts['count']; ?></h3>
                <p>Upcoming Appointments</p>
            </div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon orange">&#9201;</div>
            <div class="stat-info">
                <h3><?php echo $queueEntry ? escape($queueEntry['queue_number']) : '--'; ?></h3>
                <p>Current Queue Number</p>
            </div>
        </div>
        <div class="stat-card teal">
            <div class="stat-icon teal">&#128101;</div>
            <div class="stat-info">
                <h3><?php echo $queueEntry && $queueEntry['status'] === 'Waiting' ? $peopleAhead : 0; ?></h3>
                <p>People Ahead</p>
            </div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon green">&#9203;</div>
            <div class="stat-info">
                <h3><?php echo $queueEntry && $queueEntry['status'] === 'Waiting' ? $estimatedWait . 'm' : '--'; ?></h3>
                <p>Estimated Wait</p>
            </div>
        </div>
    </div>

    <?php if ($queueEntry && in_array($queueEntry['status'], ['Waiting', 'Called'])): ?>
    <div class="card mb-30">
        <div class="card-header">
            <div class="card-title">Current Queue Status</div>
            <span class="badge badge-<?php echo strtolower($queueEntry['status']); ?>"><?php echo $queueEntry['status']; ?></span>
        </div>
        <div class="queue-display">
            <div class="current-label" style="font-size:0.9rem;color:var(--gray);text-transform:uppercase;letter-spacing:2px;">Your Queue Number</div>
            <div class="queue-number-big"><?php echo escape($queueEntry['queue_number']); ?></div>
            <div class="queue-status-big badge-<?php echo strtolower($queueEntry['status']); ?>" id="queueStatus"><?php echo $queueEntry['status']; ?></div>
            <div class="queue-info-grid">
                <div class="queue-info-item">
                    <div class="value" id="currentServing">--</div>
                    <div class="label">Currently Serving</div>
                </div>
                <div class="queue-info-item">
                    <div class="value" id="peopleAhead"><?php echo $peopleAhead; ?></div>
                    <div class="label">People Ahead</div>
                </div>
                <div class="queue-info-item">
                    <div class="value" id="estimatedWait"><?php echo $estimatedWait; ?> min</div>
                    <div class="label">Estimated Wait</div>
                </div>
                <div class="queue-info-item">
                    <div class="value" id="servedCount">--</div>
                    <div class="label">Served Today</div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="action-cards">
        <a href="book_appointment.php" class="action-card">
            <div class="icon" style="background:linear-gradient(135deg,var(--primary),var(--accent));">&#128197;</div>
            <h3>Book Appointment</h3>
            <p>Schedule a visit</p>
        </a>
        <a href="appointments.php" class="action-card">
            <div class="icon" style="background:linear-gradient(135deg,var(--secondary),#4A1A7C);">&#128203;</div>
            <h3>My Appointments</h3>
            <p>View all bookings</p>
        </a>
        <a href="join_queue.php" class="action-card">
            <div class="icon" style="background:linear-gradient(135deg,var(--warning),#e0a600);">&#10010;</div>
            <h3>Join Queue</h3>
            <p>Enter the queue</p>
        </a>
        <a href="check_queue.php" class="action-card">
            <div class="icon" style="background:linear-gradient(135deg,var(--info),var(--primary-light));">&#9201;</div>
            <h3>Check Queue</h3>
            <p>See your status</p>
        </a>
        <a href="queue_history.php" class="action-card">
            <div class="icon" style="background:linear-gradient(135deg,var(--success),#1a9e3c);">&#128221;</div>
            <h3>Queue History</h3>
            <p>Past queue records</p>
        </a>
        <a href="notifications.php" class="action-card">
            <div class="icon" style="background:linear-gradient(135deg,var(--danger),#c62b38);">&#128276;</div>
            <h3>Notifications</h3>
            <p>View updates</p>
        </a>
    </div>
</div>

<?php if ($queueEntry && in_array($queueEntry['status'], ['Waiting', 'Called'])): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    startQueuePolling(<?php echo $queueEntry['id']; ?>);
});
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
