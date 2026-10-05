<?php
$pageTitle = 'My Profile';
require_once '../includes/header.php';
requireDoctor();

$doctor = db()->fetch("SELECT * FROM doctors WHERE user_id = ?", [$_SESSION['user_id']]);
$userAcc = db()->fetch("SELECT email FROM users WHERE id = ?", [$_SESSION['user_id']]);
$userEmail = $userAcc['email'] ?? '';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $specialization = trim($_POST['specialization'] ?? '');
        $qualification = trim($_POST['qualification'] ?? '');
        $licenseNumber = trim($_POST['license_number'] ?? '');
        $isAvailable = isset($_POST['is_available']) ? 1 : 0;

        if (empty($firstName) || empty($lastName) || empty($email)) {
            $error = 'First name, last name and email are required.';
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
                    db()->update(
                        "UPDATE doctors SET first_name=?, last_name=?, phone=?, specialization=?, qualification=?, license_number=?, is_available=? WHERE id=?",
                        [$firstName, $lastName, $phone ?: null, $specialization ?: null, $qualification ?: null, $licenseNumber ?: null, $isAvailable, $doctor['id']]
                    );
                    logAudit($_SESSION['user_id'], 'profile_updated', 'Doctor profile updated');
                    setFlash('success', 'Profile updated successfully.');
                    redirect('doctor/profile.php');
                }
            }
        }
    }
}
?>

<div class="page-content">
<div class="page-title">My Profile</div>
    <div class="page-subtitle">Manage your professional information.</div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo escape($error); ?></div>
    <?php endif; ?>

    <div class="grid-2">
        <div class="card">
            <div class="card-header">
                <div class="card-title">Professional Information</div>
            </div>
            <form method="POST" action="">
                <?php echo csrfField(); ?>
                <div style="margin-bottom:12px;color:var(--gray);font-size:0.85rem;">
                    Doctor Code: <strong><?php echo escape($doctor['doctor_code']); ?></strong>
                </div>
                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" class="form-control" value="<?php echo escape($userEmail); ?>" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" class="form-control" value="<?php echo escape($doctor['first_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" class="form-control" value="<?php echo escape($doctor['last_name']); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" class="form-control" value="<?php echo escape($doctor['phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Specialization</label>
                        <input type="text" name="specialization" class="form-control" value="<?php echo escape($doctor['specialization'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Qualification</label>
                        <input type="text" name="qualification" class="form-control" value="<?php echo escape($doctor['qualification'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>License Number</label>
                        <input type="text" name="license_number" class="form-control" value="<?php echo escape($doctor['license_number'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="is_available" value="1" <?php echo $doctor['is_available'] ? 'checked' : ''; ?> style="width:18px;height:18px;">
                        Available to accept patients
                    </label>
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
                <input type="hidden" name="first_name" value="<?php echo escape($doctor['first_name']); ?>">
                <input type="hidden" name="last_name" value="<?php echo escape($doctor['last_name']); ?>">
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