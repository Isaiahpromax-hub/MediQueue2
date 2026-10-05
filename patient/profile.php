<?php
$pageTitle = 'My Profile';
require_once '../includes/header.php';
requirePatient();

$patient = db()->fetch("SELECT * FROM patients WHERE user_id = ?", [$_SESSION['user_id']]);
$userAcc = db()->fetch("SELECT email FROM users WHERE id = ?", [$_SESSION['user_id']]);
$userEmail = $userAcc['email'] ?? '';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $dob = $_POST['date_of_birth'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $address = trim($_POST['address'] ?? '');
        $emergencyContact = trim($_POST['emergency_contact'] ?? '');
        $emergencyPhone = trim($_POST['emergency_phone'] ?? '');
        $bloodGroup = $_POST['blood_group'] ?? '';

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
                        "UPDATE patients SET first_name=?, last_name=?, phone=?, date_of_birth=?, gender=?, address=?, emergency_contact=?, emergency_phone=?, blood_group=? WHERE id=?",
                        [$firstName, $lastName, $phone ?: null, $dob ?: null, $gender ?: null, $address ?: null, $emergencyContact ?: null, $emergencyPhone ?: null, $bloodGroup ?: null, $patient['id']]
                    );
                    logAudit($_SESSION['user_id'], 'profile_updated', 'Patient profile updated');
                    setFlash('success', 'Profile updated successfully.');
                    redirect('patient/profile.php');
                }
            }
        }
    }
}
?>

<div class="page-content">
<div class="page-title">My Profile</div>
    <div class="page-subtitle">Manage your personal information.</div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo escape($error); ?></div>
    <?php endif; ?>

    <div class="grid-2">
        <div class="card">
            <div class="card-header">
                <div class="card-title">Personal Information</div>
            </div>
            <form method="POST" action="">
                <?php echo csrfField(); ?>
                <div style="margin-bottom:12px;color:var(--gray);font-size:0.85rem;">
                    Patient Code: <strong><?php echo escape($patient['patient_code']); ?></strong>
                </div>
                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" class="form-control" value="<?php echo escape($userEmail); ?>" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" class="form-control" value="<?php echo escape($patient['first_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" class="form-control" value="<?php echo escape($patient['last_name']); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" class="form-control" value="<?php echo escape($patient['phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-control" value="<?php echo escape($patient['date_of_birth'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender" class="form-control">
                            <option value="">Select</option>
                            <?php foreach (['Male','Female','Other'] as $g): ?>
                                <option value="<?php echo $g; ?>" <?php echo ($patient['gender'] ?? '') === $g ? 'selected' : ''; ?>><?php echo $g; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Blood Group</label>
                        <select name="blood_group" class="form-control">
                            <option value="">Select</option>
                            <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                                <option value="<?php echo $bg; ?>" <?php echo ($patient['blood_group'] ?? '') === $bg ? 'selected' : ''; ?>><?php echo $bg; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" class="form-control" rows="2"><?php echo escape($patient['address'] ?? ''); ?></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Emergency Contact</label>
                        <input type="text" name="emergency_contact" class="form-control" value="<?php echo escape($patient['emergency_contact'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Emergency Phone</label>
                        <input type="tel" name="emergency_phone" class="form-control" value="<?php echo escape($patient['emergency_phone'] ?? ''); ?>">
                    </div>
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
                <input type="hidden" name="first_name" value="<?php echo escape($patient['first_name']); ?>">
                <input type="hidden" name="last_name" value="<?php echo escape($patient['last_name']); ?>">
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
