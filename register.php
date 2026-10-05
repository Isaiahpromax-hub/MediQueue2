<?php
require_once 'includes/auth.php';

if (isLoggedIn()) {
    redirect('login.php');
}

$error = '';
$success = '';
$flash = getFlash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? 'patient';
    if (!in_array($role, ['patient', 'doctor', 'nurse', 'receptionist'])) {
        $role = 'patient';
    }

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $dob = $_POST['date_of_birth'] ?? '';
    $specialization = trim($_POST['specialization'] ?? '');
    $qualification = trim($_POST['qualification'] ?? '');
    $licenseNumber = trim($_POST['license_number'] ?? '');
    $position = trim($_POST['position'] ?? '');

    if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $existing = db()->fetch("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            $error = 'An account with this email already exists.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Staff accounts are created pending (inactive) until admin approves.
            $isActive = ($role === 'patient') ? 1 : 0;

            $userId = db()->insert(
                "INSERT INTO users (email, password, role, is_active) VALUES (?, ?, ?, ?)",
                [$email, $hashedPassword, $role, $isActive]
            );

            if ($role === 'patient') {
                $patientCode = 'PAT' . str_pad($userId, 3, '0', STR_PAD_LEFT);
                db()->insert(
                    "INSERT INTO patients (user_id, patient_code, first_name, last_name, phone, date_of_birth, gender) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$userId, $patientCode, $firstName, $lastName, $phone ?: null, $dob ?: null, $gender ?: null]
                );
                logAudit($userId, 'registration', 'New patient registered');
                setFlash('success', 'Registration successful! Please log in.');
                redirect('login.php');
            }

            if ($role === 'receptionist') {
                $staffCode = 'REC' . str_pad($userId, 3, '0', STR_PAD_LEFT);
                db()->insert(
                    "INSERT INTO staff (user_id, staff_code, first_name, last_name, phone, position) VALUES (?, ?, ?, ?, ?, ?)",
                    [$userId, $staffCode, $firstName, $lastName, $phone ?: null, $position ?: 'Front Desk']
                );
                logAudit($userId, 'registration', 'New receptionist registered (pending approval)');
            } else {
                // doctor or nurse -> doctors table with type
                $type = ($role === 'doctor') ? 'doctor' : 'nurse';
                $codePrefix = ($role === 'doctor') ? 'DOC' : 'NRS';
                $doctorCode = $codePrefix . str_pad($userId, 3, '0', STR_PAD_LEFT);
                db()->insert(
                    "INSERT INTO doctors (user_id, doctor_code, first_name, last_name, phone, specialization, qualification, license_number, type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$userId, $doctorCode, $firstName, $lastName, $phone ?: null, $specialization ?: null, $qualification ?: null, $licenseNumber ?: null, $type]
                );
                logAudit($userId, 'registration', 'New ' . $role . ' registered (pending approval)');
            }

            // Notify all admins that a staff account is awaiting approval.
            $admins = db()->fetchAll("SELECT id FROM users WHERE role = 'admin'");
            foreach ($admins as $admin) {
                createNotification(
                    $admin['id'],
                    'New ' . $role . ' pending approval',
                    "$firstName $lastName ($email) registered as a $role and is awaiting your approval.",
                    'system'
                );
            }

            setFlash('success', 'Your ' . $role . ' account has been submitted and is awaiting admin approval. You can sign in once approved.');
            redirect('login.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MediQueue</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .role-selector{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:20px}
        .role-option{position:relative;text-align:center;padding:12px 6px;border:2px solid #E9ECEF;border-radius:10px;cursor:pointer;transition:all .15s;font-size:.78rem;font-weight:600;color:var(--gray)}
        .role-option:hover{border-color:var(--info)}
        .role-option input{position:absolute;opacity:0;pointer-events:none}
        .role-option .role-icon{display:block;font-size:1.4rem;margin-bottom:4px}
        .role-option.selected{border-color:var(--primary);background:#E7F3FF;color:var(--primary-dark)}
        @media (max-width:520px){.role-selector{grid-template-columns:repeat(2,1fr)}}
        .auth-divider{display:flex;align-items:center;gap:12px;margin:22px 0 14px;color:var(--gray);font-size:.85rem;font-weight:600}
        .auth-divider::before,.auth-divider::after{content:"";flex:1;height:1px;background:var(--border)}
        .google-btn{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:12px 14px;border:1px solid var(--border);border-radius:10px;background:#fff;color:#3c4043;font-size:.92rem;font-weight:600;text-decoration:none;transition:all .15s;box-shadow:0 1px 2px rgba(60,64,67,.1)}
        .google-btn:hover{background:#f6f8fa;border-color:#c7ccd3;color:#202124;box-shadow:0 1px 4px rgba(60,64,67,.2)}
    </style>
</head>
<body>
<div class="auth-page">
    <div class="auth-card" style="max-width: 560px;">
        <div class="logo">
            <h1>Medi<span>Queue</span></h1>
            <p>Create your account</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo escape($error); ?></div>
        <?php endif; ?>
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo escape($flash['message']); ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="regForm">
            <?php echo csrfField(); ?>

            <!-- ROLE SELECTOR -->
            <div class="form-group">
                <label>Register as *</label>
                <div class="role-selector">
                    <label class="role-option selected">
                        <input type="radio" name="role" value="patient" checked>
                        <span class="role-icon">&#128101;</span>Patient
                    </label>
                    <label class="role-option">
                        <input type="radio" name="role" value="doctor">
                        <span class="role-icon">&#129657;</span>Doctor
                    </label>
                    <label class="role-option">
                        <input type="radio" name="role" value="nurse">
                        <span class="role-icon">&#128138;</span>Nurse
                    </label>
                    <label class="role-option">
                        <input type="radio" name="role" value="receptionist">
                        <span class="role-icon">&#128222;</span>Receptionist
                    </label>
                </div>
                <div class="alert alert-info" id="pendingNotice" style="display:none;margin:0;font-size:.78rem">
                    &#128276; Staff accounts are created <b>pending</b> and must be approved by an administrator before you can sign in.
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name *</label>
                    <input type="text" id="first_name" name="first_name" class="form-control" value="<?php echo escape($firstName ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name *</label>
                    <input type="text" id="last_name" name="last_name" class="form-control" value="<?php echo escape($lastName ?? ''); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo escape($email ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo escape($phone ?? ''); ?>">
                </div>
            </div>

            <!-- PATIENT-ONLY FIELDS -->
            <div id="patientFields" class="form-row">
                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender" class="form-control">
                        <option value="">Select</option>
                        <option value="Male" <?php echo ($gender ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo ($gender ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo ($gender ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date_of_birth">Date of Birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" value="<?php echo escape($dob ?? ''); ?>">
                </div>
            </div>

            <!-- DOCTOR/NURSE FIELDS -->
            <div id="staffClinicalFields" style="display:none">
                <div class="form-group">
                    <label id="specializationLabel" for="specialization">Specialization *</label>
                    <input type="text" id="specialization" name="specialization" class="form-control" value="<?php echo escape($specialization ?? ''); ?>" placeholder="e.g. General Clinic, Paediatrics">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="qualification">Qualification *</label>
                        <input type="text" id="qualification" name="qualification" class="form-control" value="<?php echo escape($qualification ?? ''); ?>" placeholder="e.g. MBChB, Diploma in Nursing">
                    </div>
                    <div class="form-group">
                        <label for="license_number">License / RP Number *</label>
                        <input type="text" id="license_number" name="license_number" class="form-control" value="<?php echo escape($licenseNumber ?? ''); ?>" placeholder="e.g. KMPDC 12345">
                    </div>
                </div>
            </div>

            <!-- RECEPTIONIST FIELD -->
            <div id="receptionistField" style="display:none">
                <div class="form-group">
                    <label for="position">Position</label>
                    <input type="text" id="position" name="position" class="form-control" value="<?php echo escape($position ?? ''); ?>" placeholder="e.g. Front Desk">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password" class="form-control" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg">Create Account</button>
        </form>

        <?php echo googleSignInButton('Sign up with Google'); ?>

        <div class="auth-footer">
            Already have an account? <a href="login.php">Sign in</a>
        </div>
        <div class="auth-footer" style="margin-top: 8px;">
            <a href="index.php" style="color: var(--gray);">Back to Home</a>
        </div>
    </div>
</div>

<script>
(function () {
    var options = document.querySelectorAll('.role-option');
    var roleInputs = document.querySelectorAll('input[name="role"]');
    var patientFields = document.getElementById('patientFields');
    var clinicalFields = document.getElementById('staffClinicalFields');
    var receptionistField = document.getElementById('receptionistField');
    var pendingNotice = document.getElementById('pendingNotice');
    var specLabel = document.getElementById('specializationLabel');

    function applyRole(role) {
        options.forEach(function (o) {
            o.classList.toggle('selected', o.querySelector('input').value === role);
        });
        var isPatient = role === 'patient';
        var isClinical = role === 'doctor' || role === 'nurse';
        var isReceptionist = role === 'receptionist';

        patientFields.style.display = isPatient ? '' : 'none';
        clinicalFields.style.display = isClinical ? '' : 'none';
        receptionistField.style.display = isReceptionist ? '' : 'none';
        pendingNotice.style.display = isPatient ? 'none' : '';
        if (isClinical) {
            specLabel.textContent = role === 'doctor' ? 'Specialization *' : 'Department *';
        }
    }

    roleInputs.forEach(function (input) {
        input.addEventListener('change', function () { applyRole(input.value); });
    });
    applyRole('patient');
})();
</script>
</body>
</html>