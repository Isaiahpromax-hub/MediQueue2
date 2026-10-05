<?php
$pageTitle = 'Queue Management';
require_once '../includes/header.php';
require_once '../includes/auth.php';
requireAdmin();

$serviceFilter = intval($_GET['service_id'] ?? 0);
$services = db()->fetchAll("SELECT * FROM services WHERE is_active = 1 ORDER BY name");

if (!$serviceFilter && !empty($services)) {
    $serviceFilter = $services[0]['id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        $entryId = intval($_POST['entry_id'] ?? 0);

        if ($entryId > 0) {
            $entry = db()->fetch(
                "SELECT q.*, p.first_name, p.last_name, p.user_id FROM queue_entries q JOIN patients p ON q.patient_id = p.id WHERE q.id = ?",
                [$entryId]
            );

            if ($action === 'skip' && $entry) {
                db()->update("UPDATE queue_entries SET status = 'Skipped' WHERE id = ? AND status IN ('Waiting','Called','Serving')", [$entryId]);
                logAudit(getUserId(), 'queue_skip', "Skipped queue {$entry['queue_number']}");
                setFlash('success', "Queue {$entry['queue_number']} skipped.");
            }
        }
        redirect("admin/queues.php?service_id=" . ($serviceFilter ?: ''));
    }
}

$currentServing = null;
$nextPatient = null;
$waitingList = [];
$servingCount = 0;
$waitingCount = 0;
$servedCount = 0;

if ($serviceFilter) {
    $currentServing = db()->fetch(
        "SELECT q.*, p.first_name, p.last_name, s.name as service_name
         FROM queue_entries q
         JOIN patients p ON q.patient_id = p.id
         JOIN services s ON q.service_id = s.id
         WHERE q.service_id = ? AND q.queue_date = CURDATE() AND q.status IN ('Called','Serving')
         ORDER BY FIELD(q.priority, 'Emergency', 'Urgent', 'Normal'), FIELD(q.status, 'Serving', 'Called'), q.position ASC LIMIT 1",
        [$serviceFilter]
    );

    $waitingList = db()->fetchAll(
        "SELECT q.*, p.first_name, p.last_name, s.name as service_name
         FROM queue_entries q
         JOIN patients p ON q.patient_id = p.id
         JOIN services s ON q.service_id = s.id
         WHERE q.service_id = ? AND q.queue_date = CURDATE() AND q.status = 'Waiting'
         ORDER BY FIELD(q.priority, 'Emergency', 'Urgent', 'Normal'), q.position ASC",
        [$serviceFilter]
    );

    $nextPatient = !empty($waitingList) ? $waitingList[0] : null;
    $servingCount = db()->fetch("SELECT COUNT(*) as c FROM queue_entries WHERE service_id = ? AND queue_date = CURDATE() AND status IN ('Called','Serving')", [$serviceFilter])['c'];
    $waitingCount = count($waitingList);
    $servedCount = db()->fetch("SELECT COUNT(*) as c FROM queue_entries WHERE service_id = ? AND queue_date = CURDATE() AND status = 'Served'", [$serviceFilter])['c'];
}

$allTodayEntries = db()->fetchAll(
    "SELECT q.*, p.first_name, p.last_name, s.name as service_name
     FROM queue_entries q
     JOIN patients p ON q.patient_id = p.id
     JOIN services s ON q.service_id = s.id
     WHERE q.queue_date = CURDATE()
     ORDER BY FIELD(q.priority, 'Emergency', 'Urgent', 'Normal'), q.position ASC"
);
?>

<div class="page-content">
    <div class="page-title">Queue Management</div>
    <div class="page-subtitle">Manage today's patient queue across all services.</div>

    <div class="filters-bar mb-30">
        <label style="font-weight:600;margin-right:8px;">Service:</label>
        <?php foreach ($services as $s): ?>
            <a href="?service_id=<?php echo $s['id']; ?>" class="btn btn-sm <?php echo $serviceFilter == $s['id'] ? 'btn-primary' : 'btn-outline'; ?>">
                <?php echo escape($s['name']); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($serviceFilter): ?>
    <div class="stats-grid mb-30">
        <div class="stat-card blue">
            <div class="stat-icon blue">&#9201;</div>
            <div class="stat-info">
                <h3><?php echo $waitingCount; ?></h3>
                <p>Waiting</p>
            </div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon orange">&#128226;</div>
            <div class="stat-info">
                <h3><?php echo $servingCount; ?></h3>
                <p>Being Served</p>
            </div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon green">&#10004;</div>
            <div class="stat-info">
                <h3><?php echo $servedCount; ?></h3>
                <p>Served Today</p>
            </div>
        </div>
    </div>

    <div class="grid-2 mb-30">
        <div>
            <div class="queue-current">
                <div class="current-label">Currently Serving</div>
                <div class="current-number"><?php echo $currentServing ? escape($currentServing['queue_number']) : '---'; ?></div>
                <?php if ($currentServing): ?>
                    <div style="margin-top:8px;opacity:0.9;"><?php echo escape($currentServing['first_name'] . ' ' . $currentServing['last_name']); ?></div>
                    <div class="queue-actions">
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Skip this patient?')">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="skip">
                            <input type="hidden" name="entry_id" value="<?php echo $currentServing['id']; ?>">
                            <button type="submit" class="btn btn-warning btn-sm">Skip</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($nextPatient): ?>
            <div class="queue-next">
                <div class="next-label">Next Patient</div>
                <div class="next-number"><?php echo escape($nextPatient['queue_number']); ?></div>
                <div style="margin-top:4px;opacity:0.9;"><?php echo escape($nextPatient['first_name'] . ' ' . $nextPatient['last_name']); ?></div>
            </div>
            <?php endif; ?>

            <div class="card mt-20">
                <div class="card-header">
                    <div class="card-title">Administrator Override</div>
                </div>
                <p style="color:var(--gray);font-size:13px;margin-bottom:14px;">
                    Administrators cannot call, start or complete patients directly. Use this override form to change the status of the currently serving patient if needed. All overrides are logged for auditing.
                </p>
                <?php if ($currentServing): ?>
                <form method="POST" id="overrideForm" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="id" value="<?php echo $currentServing['id']; ?>">
                    <div>
                        <label style="display:block;font-size:12px;color:var(--gray);margin-bottom:4px;">Entry</label>
                        <select name="status" required style="padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;">
                            <option value="Waiting">Return to queue</option>
                            <option value="Skipped">Mark skipped</option>
                            <option value="Served">Mark served</option>
                        </select>
                    </div>
                    <div style="flex:1;min-width:220px;">
                        <label style="display:block;font-size:12px;color:var(--gray);margin-bottom:4px;">Reason (required)</label>
                        <input type="text" name="reason" required minlength="5" placeholder="e.g. Patient left without being seen"
                               style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;">
                    </div>
                    <button type="submit" class="btn btn-danger">Apply Override</button>
                </form>
                <div id="overrideMsg" style="margin-top:10px;font-size:13px;"></div>
                <?php else: ?>
                <p style="color:var(--gray);font-size:13px;margin:0;">No active entry to override.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">Waiting Patients</div>
            </div>
            <?php if (empty($waitingList)): ?>
                <div class="empty-state"><p>No patients waiting.</p></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>#</th><th>Priority</th><th>Patient</th><th>Joined</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($waitingList as $w): ?>
                        <tr>
                            <td><strong><?php echo escape($w['queue_number']); ?></strong></td>
                            <td><?php echo priorityBadge($w['priority']); ?></td>
                            <td><?php echo escape($w['first_name'] . ' ' . $w['last_name']); ?></td>
                            <td><?php echo date('h:i A', strtotime($w['joined_at'])); ?></td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Skip this patient?')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="skip">
                                    <input type="hidden" name="entry_id" value="<?php echo $w['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Skip</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <div class="card-title">All Queue Entries (Today)</div>
        </div>
        <?php if (empty($allTodayEntries)): ?>
            <div class="empty-state"><p>No queue entries today.</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Queue #</th><th>Priority</th><th>Patient</th><th>Service</th><th>Status</th><th>Joined</th><th>Called</th><th>Served</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($allTodayEntries as $e): ?>
                    <tr>
                        <td><strong><?php echo escape($e['queue_number']); ?></strong></td>
                        <td><?php echo priorityBadge($e['priority']); ?></td>
                        <td><?php echo escape($e['first_name'] . ' ' . $e['last_name']); ?></td>
                        <td><?php echo escape($e['service_name']); ?></td>
                        <td><span class="badge badge-<?php echo strtolower($e['status']); ?>"><?php echo $e['status']; ?></span></td>
                        <td><?php echo date('h:i A', strtotime($e['joined_at'])); ?></td>
                        <td><?php echo $e['called_at'] ? date('h:i A', strtotime($e['called_at'])) : '--'; ?></td>
                        <td><?php echo $e['served_at'] ? date('h:i A', strtotime($e['served_at'])) : '--'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('overrideForm')?.addEventListener('submit', async function (e) {
    e.preventDefault();
    const form = e.target;
    const msg = document.getElementById('overrideMsg');
    const data = new FormData(form);
    if (!confirm('Apply this override? It will be recorded in the audit log.')) return;
    try {
        const res = await fetch('/MediQueue2/api/queue_action.php', {
            method: 'POST',
            body: new URLSearchParams(data)
        });
        const json = await res.json();
        if (json.success) {
            msg.style.color = '#16a34a';
            msg.textContent = json.message;
            setTimeout(function () { location.reload(); }, 900);
        } else {
            msg.style.color = '#dc2626';
            msg.textContent = json.message || 'Override failed.';
        }
    } catch (err) {
        msg.style.color = '#dc2626';
        msg.textContent = 'Network error. Please try again.';
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
