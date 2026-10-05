<?php
$pageTitle = 'Queue Management';
require_once '../includes/header.php';
requireStaff();

$serviceId = intval($_GET['service_id'] ?? 0);
$services = db()->fetchAll("SELECT * FROM services WHERE is_active = 1 ORDER BY name");

if (!$serviceId && !empty($services)) {
    $serviceId = $services[0]['id'];
}

$service = $serviceId ? db()->fetch("SELECT * FROM services WHERE id = ?", [$serviceId]) : null;
$duration = intval($service['estimated_duration'] ?? 0);

$currentServing = null;
$nextPatient = null;
$waitingList = [];
$servingCount = 0;
$calledCount = 0;
$servedCount = 0;

if ($serviceId) {
    $currentServing = db()->fetch(
        "SELECT q.*, p.first_name, p.last_name
         FROM queue_entries q JOIN patients p ON q.patient_id = p.id
         WHERE q.service_id = ? AND q.queue_date = CURDATE() AND q.status IN ('Called','Serving')
         ORDER BY FIELD(q.priority, 'Emergency', 'Urgent', 'Normal'), FIELD(q.status, 'Serving', 'Called'), q.position ASC LIMIT 1",
        [$serviceId]
    );

    $waitingList = db()->fetchAll(
        "SELECT q.*, p.first_name, p.last_name, s.name as service_name,
                d.first_name AS p_first, d.last_name AS p_last, d.type AS p_type
         FROM queue_entries q
         JOIN patients p ON q.patient_id = p.id
         JOIN services s ON q.service_id = s.id
         LEFT JOIN doctors d ON q.doctor_id = d.id
         WHERE q.service_id = ? AND q.queue_date = CURDATE() AND q.status = 'Waiting'
         ORDER BY FIELD(q.priority, 'Emergency', 'Urgent', 'Normal'), q.position ASC",
        [$serviceId]
    );

    $forwardTargets = db()->fetchAll(
        "SELECT d.id, d.first_name, d.last_name, d.specialization, d.type,
                (SELECT COUNT(*) FROM queue_entries q
                 WHERE q.doctor_id = d.id AND q.service_id = ? AND q.queue_date = CURDATE()
                   AND q.status IN ('Waiting','Called','Serving')) AS workload,
                (SELECT COUNT(*) FROM queue_entries q
                 WHERE q.doctor_id = d.id AND q.service_id = ? AND q.queue_date = CURDATE()
                   AND q.status = 'Waiting') AS waiting
         FROM doctors d
         JOIN users u ON d.user_id = u.id
         JOIN doctor_services ds ON ds.doctor_id = d.id AND ds.service_id = ?
         WHERE u.role IN ('doctor','nurse') AND u.is_active = 1 AND d.is_available = 1
         ORDER BY workload ASC, d.type, d.first_name",
        [$serviceId, $serviceId, $serviceId]
    );

    $nextPatient = !empty($waitingList) ? $waitingList[0] : null;

    $servingCount = db()->fetch("SELECT COUNT(*) as c FROM queue_entries WHERE service_id = ? AND queue_date = CURDATE() AND status IN ('Called','Serving')", [$serviceId])['c'];
    $calledCount = db()->fetch("SELECT COUNT(*) as c FROM queue_entries WHERE service_id = ? AND queue_date = CURDATE() AND status = 'Called'", [$serviceId])['c'];
    $servedCount = db()->fetch("SELECT COUNT(*) as c FROM queue_entries WHERE service_id = ? AND queue_date = CURDATE() AND status = 'Served'", [$serviceId])['c'];
}
?>

<div class="page-content">
    <div class="d-flex justify-between align-center mb-20">
        <div>
            <div class="page-title">Queue Management</div>
            <div class="page-subtitle">Manage the patient queue for your selected service.</div>
        </div>
        <a href="add_to_queue.php" class="btn btn-primary">+ Add Patient to Queue</a>
    </div>

    <div class="filters-bar mb-30">
        <label style="font-weight:600;margin-right:8px;">Service:</label>
        <?php foreach ($services as $s): ?>
            <a href="?service_id=<?php echo $s['id']; ?>" class="btn btn-sm <?php echo $serviceId == $s['id'] ? 'btn-primary' : 'btn-outline'; ?>"><?php echo escape($s['name']); ?></a>
        <?php endforeach; ?>
    </div>

    <?php if ($serviceId): ?>
    <div class="stats-grid mb-30">
        <div class="stat-card blue">
            <div class="stat-icon blue">&#9201;</div>
            <div class="stat-info">
                <h3 id="staffWaitingCount"><?php echo count($waitingList); ?></h3>
                <p>Waiting</p>
            </div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon orange">&#128226;</div>
            <div class="stat-info">
                <h3 id="staffServingCount"><?php echo $servingCount; ?></h3>
                <p>Being Served</p>
            </div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon green">&#10004;</div>
            <div class="stat-info">
                <h3 id="staffServedCount"><?php echo $servedCount; ?></h3>
                <p>Served Today</p>
            </div>
        </div>
    </div>

    <div class="grid-2">
        <div>
            <div class="queue-current">
                <div class="current-label">Currently Serving</div>
                <div id="staffCurrentCardBody">
                    <?php if ($currentServing): ?>
                        <div class="current-number"><?php echo escape($currentServing['queue_number']); ?></div>
                        <div style="margin-top:8px;opacity:0.9;"><?php echo escape($currentServing['first_name'] . ' ' . $currentServing['last_name']); ?></div>
                        <div class="queue-actions">
                            <?php if ($currentServing['status'] === 'Called'): ?>
                                <button onclick="startService(<?php echo $currentServing['id']; ?>, <?php echo $serviceId; ?>)" class="btn btn-success btn-sm">Start Service</button>
                            <?php endif; ?>
                            <?php if ($currentServing['status'] === 'Serving'): ?>
                                <button onclick="markServed(<?php echo $currentServing['id']; ?>, <?php echo $serviceId; ?>)" class="btn btn-success btn-sm">Mark Served</button>
                            <?php endif; ?>
                            <button onclick="skipPatient(<?php echo $currentServing['id']; ?>, <?php echo $serviceId; ?>)" class="btn btn-warning btn-sm">Skip</button>
                        </div>
                    <?php else: ?>
                        <div class="current-number">---</div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($nextPatient): ?>
            <div class="queue-next">
                <div class="next-label">Next Patient</div>
                <div class="next-number" id="staffNextPatient"><?php echo escape($nextPatient['queue_number']); ?></div>
                <div style="margin-top:4px;opacity:0.9;"><?php echo escape($nextPatient['first_name'] . ' ' . $nextPatient['last_name']); ?></div>
                <?php if ($duration > 0): ?>
                <div style="margin-top:4px;opacity:0.8;" class="text-muted">Est. wait for next: ~<?php echo $duration; ?> min</div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="text-center mt-20">
                <button onclick="callNextPatient(<?php echo $serviceId; ?>)" class="btn btn-primary btn-lg" style="min-width:200px;">Call Next Patient</button>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">Waiting Patients</div>
            </div>
            <div class="waiting-list" id="staffWaitingList">
                <?php if (empty($waitingList)): ?>
                    <div class="empty-state"><p>No patients waiting.</p></div>
                <?php else: ?>
                    <?php $i = 0; foreach ($waitingList as $w): $i++; ?>
                    <div class="waiting-item <?php echo $w['priority'] !== 'Normal' ? 'waiting-item-priority' : ''; ?>">
                        <span class="queue-num"><?php echo escape($w['queue_number']); ?></span>
                        <span class="patient-name"><?php echo escape($w['first_name'] . ' ' . $w['last_name']); ?></span>
                        <?php if ($w['p_first']): ?>
                        <span class="badge badge-info" style="font-size:0.75rem;"><?php echo escape(providerDisplayName(['first_name' => $w['p_first'], 'last_name' => $w['p_last'], 'type' => $w['p_type']])); ?></span>
                        <?php endif; ?>
                        <span class="service-name"><?php echo priorityBadge($w['priority']); ?></span>
                        <?php if ($duration > 0): ?>
                        <span class="service-name">~<?php echo $duration * $i; ?> min</span>
                        <?php endif; ?>
                        <?php if ($forwardTargets): ?>
                        <div class="forward-bar">
                            <select class="form-control fwd-select" data-entry="<?php echo $w['id']; ?>" aria-label="Forward patient">
                                <option value="">Forward to&hellip;</option>
                                <?php foreach ($forwardTargets as $t): ?>
                                    <?php $targetLabel = providerDisplayName($t);
                                          $wl = (int) $t['workload'];
                                          $targetLabel .= $wl > 0 ? ' — ' . (int) $t['waiting'] . ' waiting · ' . ($wl - (int) $t['waiting']) . ' active' : ' — free'; ?>
                                    <option value="<?php echo $t['id']; ?>"><?php echo escape($targetLabel); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($serviceId): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    startStaffQueuePolling(<?php echo $serviceId; ?>);

    const list = document.getElementById('staffWaitingList');
    if (list) {
        list.addEventListener('change', (e) => {
            const sel = e.target.closest('.fwd-select');
            if (!sel) return;
            const targetId = sel.value;
            const entryId = sel.getAttribute('data-entry');
            sel.value = '';
            if (!targetId) return;
            const opt = sel.querySelector('option[value="' + targetId + '"]');
            const who = opt ? opt.textContent.trim() : 'the selected provider';
            if (!confirm('Forward this patient to ' + who + '? The patient will join their queue from the back.')) return;
            fetch('/MediQueue2/api/queue_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=forward&id=' + entryId + '&target_doctor_id=' + targetId
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    setTimeout(() => location.reload(), 600);
                } else {
                    showAlert('danger', data.message || 'Could not forward patient');
                }
            });
        });
    }
});
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
