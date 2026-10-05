<?php
$pageTitle = 'SMS & WhatsApp Log';
require_once '../includes/header.php';
requireAdmin();

$filter = $_GET['status'] ?? '';
$period = $_GET['period'] ?? '';

$conditions = [];
$params = [];
if ($filter && in_array($filter, ['queued', 'sent', 'failed'])) {
    $conditions[] = "status = ?";
    $params[] = $filter;
}

$rangeLabel = 'All time';
if ($period) {
    $start = null;
    switch ($period) {
        case 'today': $start = date('Y-m-d 00:00:00'); $rangeLabel = 'Today'; break;
        case 'week':  $start = date('Y-m-d 00:00:00', strtotime('monday this week')); $rangeLabel = 'This Week (' . date('M j', strtotime($start)) . ' - Today)'; break;
        case 'month': $start = date('Y-m-01 00:00:00'); $rangeLabel = 'This Month (' . date('M Y', strtotime($start)) . ')'; break;
        case 'year':  $start = date('Y-01-01 00:00:00'); $rangeLabel = 'This Year (' . date('Y') . ')'; break;
    }
    if ($start) {
        $conditions[] = "created_at >= ?";
        $params[] = $start;
    }
}

$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

$sql = "SELECT * FROM sms_messages$where ORDER BY id DESC LIMIT 100";
$messages = db()->fetchAll($sql, $params);

$summary = db()->fetch(
    "SELECT SUM(status = 'queued') AS queued, SUM(status = 'sent') AS sent, SUM(status = 'failed') AS failed, COUNT(*) AS total FROM sms_messages$where",
    $params
);
?>

<div class="page-content">
    <div class="d-flex justify-between align-center mb-20">
        <div>
            <div class="page-title">SMS &amp; WhatsApp Log</div>
            <div class="page-subtitle">
                Messages are logged here. With no gateway configured they remain
                <strong>queued</strong>; set <code>SMS_GATEWAY_URL</code> and <code>SMS_API_KEY</code> in
                <code>config/database.php</code> to enable real delivery.
            </div>
        </div>
    </div>

    <div class="card mb-30">
        <form method="GET" class="filters-bar">
            <select name="status" class="form-control">
                <option value="">All Statuses</option>
                <?php foreach (['queued', 'sent', 'failed'] as $s): ?>
                    <option value="<?php echo $s; ?>" <?php echo $filter === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="period" class="form-control">
                <option value="">All Time</option>
                <?php foreach (['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year'] as $k => $v): ?>
                    <option value="<?php echo $k; ?>" <?php echo $period === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <?php if ($filter || $period): ?>
                <a href="sms_log.php" class="btn btn-outline btn-sm">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($filter || $period): ?>
        <div class="alert alert-info" style="margin-bottom:20px;font-size:.88rem;padding:10px 14px;">
            Showing: <strong><?php echo $rangeLabel; ?></strong><?php echo $filter ? ' &middot; Status: <strong>' . ucfirst($filter) . '</strong>' : ''; ?> &middot; <?php echo (int)($summary['total'] ?? 0); ?> message(s)
        </div>
    <?php endif; ?>

    <?php if (!$messages): ?>
        <div class="card"><div class="empty-state"><h3>No messages in this range</h3><p>Try adjusting the filters, or send an SMS via the system to see it logged here.</p></div></div>
    <?php else: ?>
    <div class="stats-grid mb-30">
        <div class="stat-card blue">
            <div class="stat-info"><h3><?php echo ($summary['total'] ?? 0); ?></h3><p>Total Messages</p></div>
        </div>
        <div class="stat-card orange">
            <div class="stat-info"><h3><?php echo ($summary['queued'] ?? 0); ?></h3><p>Queued</p></div>
        </div>
        <div class="stat-card green">
            <div class="stat-info"><h3><?php echo ($summary['sent'] ?? 0); ?></h3><p>Sent</p></div>
        </div>
        <div class="stat-card red">
            <div class="stat-info"><h3><?php echo ($summary['failed'] ?? 0); ?></h3><p>Failed</p></div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>ID</th><th>Phone</th><th>Message</th><th>Channel</th><th>Status</th><th>Created</th><th>Sent</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $msg): ?>
                    <tr>
                        <td><strong>#<?php echo $msg['id']; ?></strong></td>
                        <td><?php echo escape($msg['phone']); ?></td>
                        <td style="max-width:380px;"><?php echo escape($msg['message']); ?><?php if ($msg['error']): ?><br><small style="color:#dc3545;"><?php echo escape($msg['error']); ?></small><?php endif; ?></td>
                        <td><?php echo strtoupper(escape($msg['channel'])); ?></td>
                        <td><span class="badge badge-<?php echo $msg['status'] === 'failed' ? 'danger' : ($msg['status'] === 'sent' ? 'active' : 'inactive'); ?>"><?php echo ucfirst($msg['status']); ?></span></td>
                        <td><?php echo date('M d, g:i A', strtotime($msg['created_at'])); ?></td>
                        <td><?php echo $msg['sent_at'] ? date('M d, g:i A', strtotime($msg['sent_at'])) : '--'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>