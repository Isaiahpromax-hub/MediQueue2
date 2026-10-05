<?php
$pageTitle = 'Audit Logs';
require_once '../includes/header.php';
require_once '../includes/auth.php';
requireAdmin();

$actionFilter = trim($_GET['action'] ?? '');
$dateFrom = $_GET['from'] ?? '';
$dateTo = $_GET['to'] ?? '';

$sql = "SELECT al.*, u.email as user_email, u.role as user_role
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE 1=1";
$params = [];

if ($actionFilter) {
    $sql .= " AND al.action = ?";
    $params[] = $actionFilter;
}
if ($dateFrom) {
    $sql .= " AND DATE(al.created_at) >= ?";
    $params[] = $dateFrom;
}
if ($dateTo) {
    $sql .= " AND DATE(al.created_at) <= ?";
    $params[] = $dateTo;
}
$sql .= " ORDER BY al.created_at DESC LIMIT 200";
$logs = db()->fetchAll($sql, $params);

$availableActions = db()->fetchAll("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC");
?>

<div class="page-content">
    <div class="page-title">Audit Logs</div>
    <div class="page-subtitle">Track all system actions and changes.</div>

    <div class="card mb-30">
        <form method="GET" class="filters-bar">
            <select name="action" class="form-control">
                <option value="">All Actions</option>
                <?php foreach ($availableActions as $aa): ?>
                    <option value="<?php echo escape($aa['action']); ?>" <?php echo $actionFilter === $aa['action'] ? 'selected' : ''; ?>>
                        <?php echo escape($aa['action']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="from" class="form-control" placeholder="From" value="<?php echo escape($dateFrom); ?>">
            <input type="date" name="to" class="form-control" placeholder="To" value="<?php echo escape($dateTo); ?>">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <?php if ($actionFilter || $dateFrom || $dateTo): ?>
                <a href="audit_logs.php" class="btn btn-sm btn-outline">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <?php if (empty($logs)): ?>
            <div class="empty-state"><h3>No audit logs found</h3></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>IP Address</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td>#<?php echo $log['id']; ?></td>
                        <td>
                            <?php if ($log['user_email']): ?>
                                <?php echo escape($log['user_email']); ?>
                                <br><small style="color:var(--gray);"><?php echo escape(ucfirst($log['user_role'])); ?></small>
                            <?php else: ?>
                                <em style="color:var(--gray);">System</em>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-info"><?php echo escape($log['action']); ?></span></td>
                        <td><?php echo escape($log['description']); ?></td>
                        <td><code><?php echo escape($log['ip_address'] ?? '--'); ?></code></td>
                        <td><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
