<?php
$pageTitle = 'My Profile';
require_once '../includes/header.php';
requireAdmin();

$userAcc = db()->fetch("SELECT email FROM users WHERE id = ?", [$_SESSION['user_id']]);
$userEmail = $userAcc['email'] ?? '';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            $error = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            if (!empty($_POST['new_password'])) {
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'];
                $confirmPassword = $_POST['confirm_password'] ?? '';

                $user = db()->fetch("SELECT password FROM users WHERE id = ?", [$_SESSION['user_id']]);

                if (!password_verify($currentPassword, $user['password'])) {
                    $error = 'Current password is incorrect.';
                } elseif ($newPassword !== $confirmPassword) {
                    $error = 'New passwords do not match.';
                } elseif (strlen($newPassword) < 6) {
                    $error = 'New password must be at least 6 characters.';
                } else {
                    db()->update("UPDATE users SET password = ? WHERE id = ?", [password_hash($newPassword, PASSWORD_DEFAULT), $_SESSION['user_id']]);
                }
            }

            if (empty($error)) {
                $existingEmail = db()->fetch("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $_SESSION['user_id']]);
                if ($existingEmail) {
                    $error = 'This email is already in use by another account.';
                } else {
                    db()->update("UPDATE users SET email = ? WHERE id = ?", [$email, $_SESSION['user_id']]);
                    logAudit($_SESSION['user_id'], 'profile_updated', 'Admin profile updated');
                    setFlash('success', 'Profile updated successfully.');
                    redirect('admin/profile.php');
                }
            }
        }
    }
}
?>

<div class="page-content">
    <div class="page-title">My Profile</div>
    <div class="page-subtitle">Manage your administrator account.</div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo escape($error); ?></div>
    <?php endif; ?>

    <div class="grid-2">
        <div class="card">
            <div class="card-header">
                <div class="card-title">Account Information</div>
            </div>
            <form method="POST" action="">
                <?php echo csrfField(); ?>
                <div style="margin-bottom:12px;color:var(--gray);font-size:0.85rem;">
                    Role: <strong>Administrator</strong>
                </div>
                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" class="form-control" value="<?php echo escape($userEmail); ?>" required>
                </div>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">Change Password</div>
            </div>
            <form method="POST" action="">
                <?php echo csrfField(); ?>
                <input type="hidden" name="email" value="<?php echo escape($userEmail); ?>">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" class="form-control">
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" class="form-control" minlength="6">
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control">
                </div>
                <button type="submit" class="btn btn-warning">Update Password</button>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>