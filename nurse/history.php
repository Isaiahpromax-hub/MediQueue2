<?php
$pageTitle = 'Patients Worked';
require_once '../includes/header.php';
requireNurse();

$doctor = db()->fetch("SELECT * FROM doctors WHERE user_id = ?", [$_SESSION['user_id']]);
$doctorId = $doctor['id'];

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
    $date = date('Y-m-d');
}
$hasFilter = isset($_GET['date']) && $_GET['date'] !== '';

$rows = [];
    $qParams = [$doctorId];
    $qExtra = '';
    if ($hasFilter) {
        $qExtra = " AND q.queue_date = ?";
        $qParams[] = $date;
    }
    $served = db()->fetchAll(
        "SELECT 'queue' AS source, q.queue_number AS ref_no, p.first_name, p.last_name,
                s.name AS service_name, q.queue_date AS work_date,
                q.served_at, NULL AS appt_time, q.notes, NULL AS reason, q.id AS record_id,
                q.priority AS priority
         FROM queue_entries q
         JOIN patients p ON q.patient_id = p.id
         JOIN services s ON q.service_id = s.id
         WHERE q.doctor_id = ? AND q.status = 'Served'$qExtra",
        $qParams
    );
    foreach ($served as $r) {
        $r['ts'] = strtotime($r['served_at'] ?? $r['work_date']);
        $r['status_label'] = 'Served';
        $rows[] = $r;
    }

    $aParams = [$doctorId];
    $aExtra = '';
    if ($hasFilter) {
        $aExtra = " AND a.appointment_date = ?";
        $aParams[] = $date;
    }
    $completed = db()->fetchAll(
        "SELECT 'appointment' AS source, a.appointment_id AS ref_no, p.first_name, p.last_name,
                s.name AS service_name, a.appointment_date AS work_date,
                NULL AS served_at, a.appointment_time, a.notes, a.reason, a.id AS record_id,
                a.priority AS priority
         FROM appointments a
         JOIN patients p ON a.patient_id = p.id
         JOIN services s ON a.service_id = s.id
         WHERE a.doctor_id = ? AND a.status = 'Completed'$aExtra",
        $aParams
    );
    foreach ($completed as $r) {
        $r['ts'] = strtotime($r['work_date'] . ' ' . ($r['appointment_time'] ?? '00:00:00'));
        $r['status_label'] = 'Completed';
        $rows[] = $r;
    }

usort($rows, function ($a, $b) {
    return ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0);
});

$queueCount = 0;
$apptCount = 0;
foreach ($rows as $r) {
    if ($r['source'] === 'queue') $queueCount++;
    else $apptCount++;
}
?>

<div class="page-content">
    <div class="d-flex justify-between align-center mb-20">
        <div>
            <div class="page-title">Patients Worked</div>
            <div class="page-subtitle">Patients you served or saw — day by day, with short notes on what was done.</div>
        </div>
    </div>

    <form method="get" class="filters-bar mb-30">
        <label style="font-weight:600;margin-right:8px;">Date:</label>
        <input type="date" name="date" value="<?php echo escape($date); ?>" class="form-control" style="width:170px;display:inline-block;">
        <button class="btn btn-sm btn-primary">Search</button>
        <a href="history.php" class="btn btn-sm <?php echo $hasFilter ? 'btn-outline' : 'btn-primary'; ?>">All Dates</a>
        <span style="margin-left:14px;color:var(--gray);font-size:0.9rem;">
            <?php echo count($rows); ?> patient(s) · <?php echo $queueCount; ?> served · <?php echo $apptCount; ?> completed
        </span>
    </form>

    <div class="card">
        <?php if (empty($rows)): ?>
            <div class="empty-state">
                <h3>No patients found</h3>
                <p>No patients worked on<?php echo $hasFilter ? ' on ' . date('M j, Y', strtotime($date)) : ''; ?>.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th><th>Time</th><th>Patient</th><th>Service</th><th>Ref</th>
                        <th>Source</th><th>Reason</th><th>Notes — what was done</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo date('M j, Y', strtotime($r['work_date'])); ?></td>
                        <td>
                            <?php
                            if ($r['source'] === 'queue') {
                                echo $r['served_at'] ? date('h:i A', strtotime($r['served_at'])) : date('h:i A', $r['ts'] ?? time());
                            } else {
                                echo $r['appointment_time'] ? date('h:i A', strtotime($r['appointment_time'])) : '—';
                            }
                            ?>
                        </td>
                        <td><strong><?php echo escape($r['first_name'] . ' ' . $r['last_name']); ?></strong></td>
                        <td><?php echo escape($r['service_name']); ?></td>
                        <td><strong><?php echo escape($r['ref_no']); ?></strong></td>
                        <td>
                            <?php if ($r['source'] === 'queue'): ?>
                                <span class="badge badge-serving">Queue · Served</span>
                            <?php else: ?>
                                <span class="badge badge-success">Appointment · Completed</span>
                            <?php endif; ?>
                            <?php if (!empty($r['priority']) && $r['priority'] !== 'Normal'): ?>
                                <span class="badge badge-priority-<?php echo strtolower($r['priority']); ?>"><?php echo escape(strtoupper($r['priority'])); ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="max-width:180px;"><?php echo $r['reason'] ? escape(substr($r['reason'], 0, 60)) : '<span style="color:#999;">—</span>'; ?></td>
                        <td style="max-width:240px;">
                            <div class="note-row">
                                <textarea class="form-control note-input" rows="2" maxlength="2000" placeholder="Short note about what was done..."><?php echo escape($r['notes'] ?? ''); ?></textarea>
                                <button type="button" class="btn btn-sm btn-primary note-save" data-type="<?php echo $r['source']; ?>" data-id="<?php echo (int) $r['record_id']; ?>" style="margin-top:6px;">Save Note</button>
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

<script>
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.note-save');
    if (!btn) return;
    const row = btn.closest('.note-row');
    const area = row ? row.querySelector('.note-input') : null;
    if (!area) return;
    btn.disabled = true;
    fetch('/MediQueue2/api/save_note.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'type=' + encodeURIComponent(btn.dataset.type) +
              '&id=' + encodeURIComponent(btn.dataset.id) +
              '&notes=' + encodeURIComponent(area.value)
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        showAlert(data.success ? 'success' : 'danger', data.message || 'Could not save note');
        if (data.success) area.blur();
    })
    .catch(() => {
        btn.disabled = false;
        showAlert('danger', 'Network error saving note');
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>