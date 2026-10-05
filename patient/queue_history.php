<?php
$pageTitle = 'Queue History';
require_once '../includes/header.php';
requirePatient();

$patient = db()->fetch("SELECT * FROM patients WHERE user_id = ?", [$_SESSION['user_id']]);
$patientId = $patient['id'];

$filterDate = $_GET['date'] ?? '';
$filterService = $_GET['service'] ?? '';
$filterStatus = $_GET['status'] ?? '';

$sql = "SELECT q.*, s.name as service_name,
        TIMESTAMPDIFF(MINUTE, q.joined_at, q.served_at) as waiting_duration
        FROM queue_entries q
        JOIN services s ON q.service_id = s.id
        WHERE q.patient_id = ?";
$params = [$patientId];

if ($filterDate) {
    $sql .= " AND q.queue_date = ?";
    $params[] = $filterDate;
}
if ($filterService) {
    $sql .= " AND q.service_id = ?";
    $params[] = $filterService;
}
if ($filterStatus && in_array($filterStatus, ['Waiting','Called','Serving','Served','Skipped','Cancelled'])) {
    $sql .= " AND q.status = ?";
    $params[] = $filterStatus;
}

$sql .= " ORDER BY q.joined_at DESC";
$history = db()->fetchAll($sql, $params);
$services = patientServices();
?>

<div class="page-content">
    <div class="page-title">Queue History</div>
    <div class="page-subtitle">View your past queue records.</div>

    <div class="card mb-30">
        <form method="GET" class="filters-bar">
            <input type="date" name="date" class="form-control" value="<?php echo escape($filterDate); ?>" placeholder="Filter by date">
            <select name="service" class="form-control">
                <option value="">All Services</option>
                <?php foreach ($services as $s): ?>
                    <option value="<?php echo $s['id']; ?>" <?php echo $filterService == $s['id'] ? 'selected' : ''; ?>><?php echo escape($s['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="form-control">
                <option value="">All Statuses</option>
                <?php foreach (['Waiting','Called','Serving','Served','Skipped','Cancelled'] as $st): ?>
                    <option value="<?php echo $st; ?>" <?php echo $filterStatus === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <a href="queue_history.php" class="btn btn-outline btn-sm">Clear</a>
        </form>
    </div>

    <div class="card">
        <?php if (empty($history)): ?>
            <div class="empty-state">
                <div class="icon">&#128221;</div>
                <h3>No history found</h3>
                <p>No queue records match your filters.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Queue #</th>
                            <th>Service</th>
                            <th>Date</th>
                            <th>Joined</th>
                            <th class="hide-sm">Served</th>
                            <th>Status</th>
                            <th class="hide-sm">Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $h): ?>
                        <tr>
                            <td><strong><?php echo escape($h['queue_number']); ?></strong></td>
                            <td><?php echo escape($h['service_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($h['queue_date'])); ?></td>
                            <td><?php echo date('h:i A', strtotime($h['joined_at'])); ?></td>
                            <td class="hide-sm"><?php echo $h['served_at'] ? date('h:i A', strtotime($h['served_at'])) : '--'; ?></td>
                            <td><span class="badge badge-<?php echo strtolower($h['status']); ?>"><?php echo $h['status']; ?></span></td>
                            <td class="hide-sm"><?php echo $h['waiting_duration'] !== null ? $h['waiting_duration'] . ' min' : '--'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
