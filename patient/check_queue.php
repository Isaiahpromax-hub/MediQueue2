<?php
$pageTitle = 'Check Queue Status';
require_once '../includes/header.php';
requirePatient();

$patient = db()->fetch("SELECT * FROM patients WHERE user_id = ?", [$_SESSION['user_id']]);
$patientId = $patient['id'];

$queueEntry = db()->fetch(
    "SELECT q.*, s.name as service_name, s.estimated_duration
     FROM queue_entries q
     JOIN services s ON q.service_id = s.id
     WHERE q.patient_id = ? AND q.queue_date = CURDATE() AND q.status IN ('Waiting','Called','Serving')
     ORDER BY q.id DESC LIMIT 1",
    [$patientId]
);

$peopleAhead = 0;
$estimatedWait = 0;
$currentServing = '---';
$waitingCount = 0;
$servedCount = 0;

if ($queueEntry) {
    $serviceEstimate = $queueEntry['estimated_duration'] ?? 15;
    $currentlyServing = db()->fetch(
        "SELECT queue_number FROM queue_entries WHERE service_id = ? AND queue_date = CURDATE() AND status IN ('Called','Serving') ORDER BY position ASC LIMIT 1",
        [$queueEntry['service_id']]
    );
    $currentServing = $currentlyServing['queue_number'] ?? '---';

    $ahead = db()->fetch(
        "SELECT COUNT(*) as count FROM queue_entries
         WHERE service_id = ? AND queue_date = CURDATE() AND status = 'Waiting'
           AND (FIELD(priority, 'Emergency', 'Urgent', 'Normal') > FIELD(?, 'Emergency', 'Urgent', 'Normal')
                OR (FIELD(priority, 'Emergency', 'Urgent', 'Normal') = FIELD(?, 'Emergency', 'Urgent', 'Normal') AND position < ?))",
        [$queueEntry['service_id'], $queueEntry['priority'], $queueEntry['priority'], $queueEntry['position']]
    );
    $peopleAhead = $ahead['count'] ?? 0;
    $estimatedWait = $peopleAhead * $serviceEstimate;

    $waitingCount = db()->fetch("SELECT COUNT(*) as count FROM queue_entries WHERE service_id = ? AND queue_date = CURDATE() AND status = 'Waiting'", [$queueEntry['service_id']])['count'];
    $servedCount = db()->fetch("SELECT COUNT(*) as count FROM queue_entries WHERE service_id = ? AND queue_date = CURDATE() AND status = 'Served'", [$queueEntry['service_id']])['count'];
}
?>

<div class="page-content">
    <div class="page-title">Queue Status</div>
    <div class="page-subtitle">Your real-time queue position updates automatically.</div>

    <?php if (!$queueEntry): ?>
        <div class="card">
            <div class="empty-state">
                <div class="icon">&#9201;</div>
                <h3>Not in queue</h3>
                <p>You are not currently in any queue today.</p>
                <a href="join_queue.php" class="btn btn-primary">Join a Queue</a>
            </div>
        </div>
    <?php else: ?>
        <div class="card mb-30">
            <div class="queue-display">
                <div style="font-size:0.9rem;color:var(--gray);text-transform:uppercase;letter-spacing:2px;">Your Queue Number</div>
                <div class="queue-number-big"><?php echo escape($queueEntry['queue_number']); ?></div>
                <?php if ($queueEntry['priority'] !== 'Normal'): ?>
                    <div style="margin-bottom:10px;"><?php echo priorityBadge($queueEntry['priority']); ?></div>
                <?php endif; ?>
                <div class="queue-status-big badge-<?php echo strtolower($queueEntry['status']); ?>" id="queueStatus">
                    <?php echo strtoupper($queueEntry['status']); ?>
                </div>
                <div style="margin-top:10px;color:var(--gray);font-size:0.95rem;">
                    <?php echo escape($queueEntry['service_name']); ?>
                </div>

                <div class="queue-info-grid">
                    <div class="queue-info-item">
                        <div class="value" id="currentServing"><?php echo escape($currentServing); ?></div>
                        <div class="label">Currently Serving</div>
                    </div>
                    <div class="queue-info-item">
                        <div class="value" id="peopleAhead"><?php echo $peopleAhead; ?></div>
                        <div class="label">People Ahead</div>
                    </div>
                    <div class="queue-info-item">
                        <div class="value" id="waitingCount"><?php echo $waitingCount; ?></div>
                        <div class="label">Waiting</div>
                    </div>
                    <div class="queue-info-item">
                        <div class="value" id="servedCount"><?php echo $servedCount; ?></div>
                        <div class="label">Served Today</div>
                    </div>
                    <div class="queue-info-item">
                        <div class="value" id="estimatedWait"><?php echo $estimatedWait; ?> min</div>
                        <div class="label">Estimated Wait</div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($queueEntry['status'] === 'Waiting'): ?>
        <div class="text-center">
            <form method="POST" action="/MediQueue2/api/queue_action.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to leave the queue?')">
                <input type="hidden" name="action" value="leave">
                <input type="hidden" name="id" value="<?php echo $queueEntry['id']; ?>">
                <button type="button" onclick="leaveQueue(<?php echo $queueEntry['id']; ?>)" class="btn btn-danger btn-lg">Leave Queue</button>
            </form>
            <a href="queue_history.php" class="btn btn-outline btn-lg">View History</a>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($queueEntry): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    startQueuePolling(<?php echo $queueEntry['id']; ?>);
});
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
