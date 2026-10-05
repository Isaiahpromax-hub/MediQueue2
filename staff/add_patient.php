<?php
$pageTitle = 'Register Patient';
require_once '../includes/header.php';
requireStaff();

$error = '';
$firstName = $lastName = $phone = $email = $address = '';
$gender = $dob = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $gender = $_POST['gender'] ?? '';
        $dob = $_POST['date_of_birth'] ?? '';
        $address = trim($_POST['address'] ?? '');

        if (empty($firstName) || empty($lastName)) {
            $error = 'First name and last name are required.';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address (or leave blank for a walk-in patient).';
        } elseif ($email !== '' && db()->fetch("SELECT id FROM users WHERE email = ?", [$email])) {
            $error = 'An account with this email already exists.';
        } else {
            if ($email === '') {
                $digits = preg_replace('/\D/', '', $phone);
                $email = $digits !== '' ? ($digits . '@mediqueue.com') : ('walkin' . rand(10000, 99999) . '@mediqueue.com');
                $generated = true;
                $autoPassword = 'mq-' . substr(str_shuffle('abcdefghjkmnpqrstuvwxyz23456789'), 0, 6);
            } else {
                $autoPassword = 'password';
                $generated = false;
            }

            $hashedPassword = password_hash($autoPassword, PASSWORD_DEFAULT);
            $userId = db()->insert(
                "INSERT INTO users (email, password, role, is_active) VALUES (?, ?, 'patient', 1)",
                [$email, $hashedPassword]
            );

            $patientCode = 'PAT' . str_pad($userId, 3, '0', STR_PAD_LEFT);
            db()->insert(
                "INSERT INTO patients (user_id, patient_code, first_name, last_name, phone, date_of_birth, gender, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$userId, $patientCode, $firstName, $lastName, $phone ?: null, $dob ?: null, $gender ?: null, $address ?: null]
            );

            createNotification($userId, 'Welcome to MediQueue', "Your patient account has been created. Your code is $patientCode.", 'appointment');
            logAudit(getUserId(), 'patient_registered', "Registered patient $firstName $lastName ($email)");

            $loginInfo = $generated
                ? " Walk-in login: $email / $autoPassword (tell the patient to change it in their profile)."
                : " The patient can log in with the email and the password you set.";
            setFlash('success', "Patient $firstName $lastName registered successfully (patient code: $patientCode).$loginInfo");
            redirect('staff/patients.php');
        }
    }
}
?>

<div class="page-content">
    <div class="d-flex justify-between align-center mb-20">
        <div>
            <div class="page-title">Register Patient</div>
            <div class="page-subtitle">Create an account for a patient (for walk-ins who came without a phone or email).</div>
        </div>
        <a href="patients.php" class="btn btn-outline">&larr; Back to Patients</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo escape($error); ?></div>
    <?php endif; ?>

    <div class="card" style="max-width: 700px;">
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <div class="form-row">
                <div class="form-group">
                    <label>First Name *</label>
                    <input type="text" name="first_name" class="form-control" value="<?php echo escape($firstName); ?>" required>
                </div>
                <div class="form-group">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" class="form-control" value="<?php echo escape($lastName); ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" class="form-control" value="<?php echo escape($phone); ?>" placeholder="e.g. 0712345678">
                </div>
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender" class="form-control">
                        <option value="">Select</option>
                        <option value="Male" <?php echo $gender === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo $gender === 'Female' ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo $gender === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?php echo escape($email); ?>" placeholder="Leave blank for walk-in patients (a login will be generated)">
                </div>
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control" value="<?php echo escape($dob); ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Address</label>
                <textarea name="address" class="form-control" rows="2" placeholder="Optional address..."><?php echo escape($address); ?></textarea>
            </div>
            <div class="alert alert-info">
                Walk-in patients without an email get a generated login so they can still track their queue/appointments online. Password will be shown after registering.
            </div>
            <div class="d-flex gap-10">
                <button type="submit" class="btn btn-primary btn-lg">Register Patient</button>
                <a href="patients.php" class="btn btn-outline btn-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>