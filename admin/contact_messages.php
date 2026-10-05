<?php
$pageTitle = 'Contact Messages';
require_once '../includes/header.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        if (isset($_POST['toggle'])) {
            $m = db()->fetch("SELECT is_handled FROM contact_messages WHERE id = ?", [$id]);
            if ($m) {
                $new = $m['is_handled'] ? 0 : 1;
                db()->update("UPDATE contact_messages SET is_handled = ? WHERE id = ?", [$new, $id]);
                logAudit(getUserId(), 'contact_message_' . ($new ? 'handled' : 'reopened'), "Contact message #$id " . ($new ? 'marked handled' : 'reopened'));
                setFlash('success', 'Contact message updated.');
            }
        } elseif (isset($_POST['delete'])) {
            db()->update("DELETE FROM contact_messages WHERE id = ?", [$id]);
            logAudit(getUserId(), 'contact_message_deleted', "Contact message #$id deleted");
            setFlash('success', 'Contact message deleted.');
        }
    }
    redirect('admin/contact_messages.php');
}

$messages = db()->fetchAll("SELECT * FROM contact_messages ORDER BY id DESC LIMIT 100");
$summary = db()->fetch(
    "SELECT COUNT(*) AS total, SUM(is_handled = 0) AS unhandled, SUM(is_handled = 1) AS handled FROM contact_messages"
);
?>

<div class="page-content">
    <div class="d-flex justify-between align-center mb-20">
        <div>
            <div class="page-title">Contact Messages</div>
            <div class="page-subtitle">Messages submitted through the website <strong>Contact Us</strong> form. Visible to administrators only.</div>
        </div>
    </div>

    <?php if ($flash = getFlash()): ?>
        <div class="alert alert-<?php echo $flash['type']; ?>" style="margin-bottom:20px"><?php echo escape($flash['message']); ?></div>
    <?php endif; ?>

    <?php if (!$messages): ?>
        <div class="card"><div class="empty-state"><div class="icon">&#9993;</div><h3>No contact messages yet</h3><p>Messages from the landing page Contact Us form will appear here.</p></div></div>
    <?php else: ?>
    <div class="stats-grid mb-30">
        <div class="stat-card blue">
            <div class="stat-info"><h3><?php echo (int)($summary['total'] ?? 0); ?></h3><p>Total Messages</p></div>
        </div>
        <div class="stat-card orange">
            <div class="stat-info"><h3><?php echo (int)($summary['unhandled'] ?? 0); ?></h3><p>Unhandled</p></div>
        </div>
        <div class="stat-card green">
            <div class="stat-info"><h3><?php echo (int)($summary['handled'] ?? 0); ?></h3><p>Handled</p></div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>#</th><th>From</th><th>Subject</th><th>Message</th><th>Time</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $m): ?>
                    <tr>
                        <td><strong>#<?php echo $m['id']; ?></strong></td>
                        <td>
                            <strong><?php echo escape($m['name']); ?></strong><br>
                            <small><a href="mailto:<?php echo escape($m['email']); ?>"><?php echo escape($m['email']); ?></a></small>
                            <?php if ($m['ip_address']): ?><br><small style="color:var(--gray)">IP: <?php echo escape($m['ip_address']); ?></small><?php endif; ?>
                        </td>
                        <td><?php echo escape($m['subject']); ?></td>
                        <td style="max-width:360px;"><?php echo escape($m['message']); ?></td>
                        <td style="white-space:nowrap"><?php echo date('M j, Y g:i A', strtotime($m['created_at'])); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $m['is_handled'] ? 'active' : 'danger'; ?>"><?php echo $m['is_handled'] ? 'Handled' : 'New'; ?></span>
                        </td>
                        <td style="white-space:nowrap">
                            <form method="POST" style="display:inline-block;margin:0">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>">
                                <button type="submit" name="toggle" value="1" class="btn btn-sm <?php echo $m['is_handled'] ? 'btn-outline' : 'btn-primary'; ?>">
                                    <?php echo $m['is_handled'] ? 'Reopen' : 'Mark Handled'; ?>
                                </button>
                            </form>
                            <form method="POST" style="display:inline-block;margin:0" onsubmit="return confirm('Delete this contact message?');">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>">
                                <button type="submit" name="delete" value="1" class="btn btn-sm btn-outline" style="color:var(--danger)">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>