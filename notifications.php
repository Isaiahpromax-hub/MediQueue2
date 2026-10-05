<?php
$pageTitle = 'Notifications';
require_once 'includes/header.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_all_read'])) {
        db()->update("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0", [$_SESSION['user_id']]);
        setFlash('success', 'All notifications marked as read.');
        redirect('notifications.php');
    }
    if (isset($_POST['mark_read'])) {
        db()->update("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?", [intval($_POST['notif_id']), $_SESSION['user_id']]);
    }
}

$notifications = db()->fetchAll(
    "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50",
    [$_SESSION['user_id']]
);
$unreadCount = db()->fetch("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0", [$_SESSION['user_id']])['count'];
?>

<div class="page-content">
    <div class="d-flex justify-between align-center mb-20">
        <div>
            <div class="page-title">Notifications</div>
            <div class="page-subtitle">Stay updated on your appointments, queue status, and system alerts.</div>
        </div>
        <?php if (!empty($notifications)): ?>
        <form method="POST">
            <input type="hidden" name="mark_all_read" value="1">
            <button type="submit" class="btn btn-outline btn-sm">Mark All as Read</button>
        </form>
        <?php endif; ?>
    </div>

    <div class="card">
        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <div class="icon">&#128276;</div>
                <h3>No notifications</h3>
                <p>You're all caught up!</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $n): ?>
            <div class="notification-item <?php echo $n['is_read'] ? '' : 'unread'; ?>" style="border-radius:8px;margin:4px 0;">
                <div class="notification-icon <?php echo escape($n['type']); ?>">
                    <?php
                    $icon = match ($n['type']) {
                        'appointment' => '&#128197;',
                        'queue'       => '&#9201;',
                        'system'      => '&#9881;',
                        'reminder'    => '&#128276;',
                        'emergency'   => '&#9873;',
                        default       => '&#128172;',
                    };
                    echo $icon;
                    ?>
                </div>
                <div class="notification-content">
                    <div class="title">
                        <?php echo escape($n['title']); ?>
                        <?php if (!$n['is_read']): ?><span class="badge badge-danger" style="font-size:.65rem;vertical-align:middle;">NEW</span><?php endif; ?>
                    </div>
                    <div class="message"><?php echo escape($n['message']); ?></div>
                    <div class="time"><?php echo date('M j, Y g:i A', strtotime($n['created_at'])); ?></div>
                </div>
                <?php if (!$n['is_read']): ?>
                <form method="POST" style="margin-left:auto">
                    <input type="hidden" name="notif_id" value="<?php echo (int)$n['id']; ?>">
                    <input type="hidden" name="mark_read" value="1">
                    <button type="submit" class="btn btn-sm btn-outline" title="Mark as read">&#10003;</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>