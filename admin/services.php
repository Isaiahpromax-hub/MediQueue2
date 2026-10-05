<?php
$pageTitle = 'Manage Services';
require_once '../includes/header.php';
require_once '../includes/auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $department = trim($_POST['department'] ?? '');
            $duration = intval($_POST['estimated_duration'] ?? 15);

            if ($name) {
                $id = db()->insert(
                    "INSERT INTO services (name, description, department, estimated_duration, is_active) VALUES (?, ?, ?, ?, 1)",
                    [$name, $description, $department, $duration > 0 ? $duration : 15]
                );
                logAudit(getUserId(), 'service_create', "Created service: $name (ID #$id)");
                setFlash('success', "Service '$name' created successfully.");
            } else {
                setFlash('error', 'Service name is required.');
            }
            redirect('admin/services.php');

        } elseif ($action === 'update') {
            $serviceId = intval($_POST['service_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $department = trim($_POST['department'] ?? '');
            $duration = intval($_POST['estimated_duration'] ?? 15);

            if ($serviceId > 0 && $name) {
                db()->update(
                    "UPDATE services SET name = ?, description = ?, department = ?, estimated_duration = ? WHERE id = ?",
                    [$name, $description, $department, $duration > 0 ? $duration : 15, $serviceId]
                );
                logAudit(getUserId(), 'service_update', "Updated service #$serviceId: $name");
                setFlash('success', 'Service updated successfully.');
            } else {
                setFlash('error', 'Service name is required.');
            }
            redirect('admin/services.php');

        } elseif ($action === 'toggle_active') {
            $serviceId = intval($_POST['service_id'] ?? 0);
            if ($serviceId > 0) {
                $svc = db()->fetch("SELECT is_active, name FROM services WHERE id = ?", [$serviceId]);
                if ($svc) {
                    $newVal = $svc['is_active'] ? 0 : 1;
                    db()->update("UPDATE services SET is_active = ? WHERE id = ?", [$newVal, $serviceId]);
                    $statusText = $newVal ? 'activated' : 'deactivated';
                    logAudit(getUserId(), 'service_toggle', "Service '{$svc['name']}' $statusText");
                    setFlash('success', "Service {$statusText}.");
                }
            }
            redirect('admin/services.php');
        }
    }
}

$services = db()->fetchAll("SELECT * FROM services ORDER BY department ASC, name ASC");
$editService = null;
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $editService = db()->fetch("SELECT * FROM services WHERE id = ?", [$editId]);
}
?>

<div class="page-content">
    <div class="d-flex justify-between align-center mb-20">
        <div>
            <div class="page-title">Services</div>
            <div class="page-subtitle">Manage available healthcare services.</div>
        </div>
        <button class="btn btn-primary" onclick="document.getElementById('addServiceModal').style.display='flex'">+ Add Service</button>
    </div>

    <div class="card">
        <?php if (empty($services)): ?>
            <div class="empty-state"><h3>No services found</h3></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Duration (min)</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $svc): ?>
                    <tr>
                        <td><strong>#<?php echo $svc['id']; ?></strong></td>
                        <td>
                            <?php echo escape($svc['name']); ?>
                            <?php if ($svc['description']): ?>
                                <br><small style="color:var(--gray);"><?php echo escape($svc['description']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo escape($svc['department'] ?? '--'); ?></td>
                        <td><?php echo $svc['estimated_duration']; ?></td>
                        <td>
                            <span class="badge <?php echo $svc['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                <?php echo $svc['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-10">
                                <a href="?edit=<?php echo $svc['id']; ?>" class="btn btn-sm btn-outline">Edit</a>
                                <form method="POST" style="display:inline;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="service_id" value="<?php echo $svc['id']; ?>">
                                    <button type="submit" class="btn btn-sm <?php echo $svc['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                        <?php echo $svc['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
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

<div class="modal" id="addServiceModal" style="display:<?php echo $editService ? 'flex' : 'none'; ?>;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php echo $editService ? 'Edit Service' : 'Add New Service'; ?></h3>
            <a href="services.php" class="modal-close">&times;</a>
        </div>
        <form method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="<?php echo $editService ? 'update' : 'create'; ?>">
            <?php if ($editService): ?>
                <input type="hidden" name="service_id" value="<?php echo $editService['id']; ?>">
            <?php endif; ?>
            <div class="form-group">
                <label>Service Name *</label>
                <input type="text" name="name" class="form-control" required value="<?php echo $editService ? escape($editService['name']) : ''; ?>">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="3"><?php echo $editService ? escape($editService['description'] ?? '') : ''; ?></textarea>
            </div>
            <div class="form-group">
                <label>Department</label>
                <input type="text" name="department" class="form-control" value="<?php echo $editService ? escape($editService['department'] ?? '') : ''; ?>">
            </div>
            <div class="form-group">
                <label>Estimated Duration (minutes)</label>
                <input type="number" name="estimated_duration" class="form-control" min="1" value="<?php echo $editService ? $editService['estimated_duration'] : 15; ?>">
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary"><?php echo $editService ? 'Update Service' : 'Create Service'; ?></button>
                <a href="services.php" class="btn btn-outline" style="margin-left:10px;">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
