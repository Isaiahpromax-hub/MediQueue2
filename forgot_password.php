<?php
require_once 'includes/auth.php';

if (isLoggedIn()) {
    $role = getRole();
    if ($role === 'patient') redirect('patient/dashboard.php');
    elseif ($role === 'receptionist' || $role === 'admin') redirect('staff/dashboard.php');
    elseif ($role === 'doctor') redirect('doctor/dashboard.php');
    elseif ($role === 'nurse') redirect('nurse/dashboard.php');
}

$error = '';
$success = '';
$email = '';
$demoCode = '';

/* ---- Step 2: code + new password ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password_step2'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $code = trim($_POST['code'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $resetEmail = $_SESSION['reset_email'] ?? '';

        if (empty($code) || empty($password) || empty($confirm)) {
            $error = 'Please fill in all fields.';
            $showStep2 = true;
        } elseif (strlen($code) !== 6 || !ctype_digit($code)) {
            $error = 'Reset code must be exactly 6 digits.';
            $showStep2 = true;
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
            $showStep2 = true;
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
            $showStep2 = true;
        } else {
            $reset = db()->fetch(
                "SELECT id, email, expires_at, used FROM password_resets WHERE email = ? AND code = ? ORDER BY id DESC LIMIT 1",
                [$resetEmail, $code]
            );
            if (!$reset) {
                $error = 'Invalid reset code. Please check and try again.';
                $showStep2 = true;
            } elseif ($reset['used']) {
                $error = 'This code has already been used. Please request a new one.';
                unset($_SESSION['reset_email']);
                $showStep2 = true;
            } elseif (strtotime($reset['expires_at']) < time()) {
                $error = 'This code has expired. Please request a new one.';
                unset($_SESSION['reset_email']);
                $showStep2 = true;
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                db()->update("UPDATE users SET password = ? WHERE email = ?", [$hashed, $resetEmail]);

                $user = db()->fetch("SELECT id FROM users WHERE email = ?", [$resetEmail]);
                if ($user) {
                    logAudit($user['id'], 'password_reset', 'Password reset via emailed code');
                }

                db()->update("UPDATE password_resets SET used = 1 WHERE id = ?", [$reset['id']]);
                unset($_SESSION['reset_email']);
                setFlash('success', 'Your password has been reset successfully. You can now sign in.');
                redirect('login.php');
            }
        }
    }
}

/* ---- Step 1: request a code by email ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        if (empty($email)) {
            $error = 'Please enter your email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $user = db()->fetch("SELECT id, is_active, role FROM users WHERE email = ?", [$email]);
            if (!$user) {
                $error = 'No account found with that email address.';
            } elseif (!$user['is_active']) {
                $error = 'Your account has been deactivated. Please contact support.';
            } else {
                $code = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                db()->update("UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0", [$email]);
                db()->insert(
                    "INSERT INTO password_resets (email, code, expires_at) VALUES (?, ?, ?)",
                    [$email, $code, $expiresAt]
                );

                $msg = "MediQueue Password Reset\nYour 6-digit reset code is: $code\nThis code expires in 15 minutes.\nIf you did not request this, ignore this message.";

                $html = "
                    <div style='font-family:Arial,sans-serif;max-width:520px;margin:0 auto;border:1px solid #E9ECEF;border-radius:12px;overflow:hidden'>
                        <div style='background:#03045E;padding:22px;text-align:center'>
                            <span style='color:#fff;font-size:1.3rem;font-weight:700'>Medi<span style='color:#00B4D8'>Queue</span></span>
                        </div>
                        <div style='padding:28px 30px;color:#212529'>
                            <h2 style='margin:0 0 8px;font-size:1.15rem'>Password Reset Code</h2>
                            <p style='font-size:.9rem;color:#6C757D;margin:0 0 18px'>Use the 6-digit code below to reset the password for <b style='color:#212529'>" . escape($email) . "</b>. It expires in 15 minutes.</p>
                            <div style='background:#F0F4F8;border:2px dashed #00B4D8;border-radius:10px;text-align:center;padding:18px'>
                                <div style='font-size:2rem;font-weight:900;letter-spacing:8px;color:#03045E'>" . escape($code) . "</div>
                            </div>
                            <p style='font-size:.75rem;color:#adb5bd;margin:16px 0 0'>If you did not request this, you can ignore this email.</p>
                        </div>
                    </div>";

                $domain = strtolower(substr(strrchr($email, '@'), 1) ?: $emailZero);
                $realInbox = !in_array($domain, ['mediqueue.com', 'example.com', 'test.com']);
                $emailSent = false;
                if ($realInbox) {
                    $emailSent = sendEmail($email, 'MediQueue Password Reset Code', $msg, $html);
                }

                $phoneTable = 'patients';
                if ($user['role'] === 'doctor' || $user['role'] === 'nurse') {
                    $phoneTable = 'doctors';
                } elseif ($user['role'] === 'receptionist' || $user['role'] === 'admin') {
                    $phoneTable = 'staff';
                }
                $userPhone = db()->fetch("SELECT phone FROM `$phoneTable` WHERE user_id = ?", [$user['id']]);
                if (!empty($userPhone['phone'])) {
                    sendSMS($userPhone['phone'], $msg);
                }

                logAudit($user['id'], 'password_reset_request', 'Password reset code generated (email ' . ($emailSent ? 'delivered' : 'failed') . ')');

                $_SESSION['reset_email'] = $email;
                if (empty($_SESSION['reset_demo_code'])) {
                    $_SESSION['reset_demo_code'] = $emailSent ? '' : $code;
                }
                $demoCode = $_SESSION['reset_demo_code'];
                $showStep2 = true;
                $success = $emailSent
                    ? 'A reset code has been sent to your email.'
                    : 'The email could not be delivered to this address, so your reset code is shown below.';
            }
        }
    }
}

$showStep2 = $showStep2 ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - MediQueue</title>
    <link rel="stylesheet" href="assets/css/style.css?v=6">
    <style>
        .fp-modal .pre{
            letter-spacing:10px;font-size:2rem;font-weight:900;color:var(--primary-dark);
            text-align:center;padding:14px;background:var(--bg);border:2px dashed var(--info);
            border-radius:10px;margin-bottom:4px
        }
        .fp-success{text-align:center;color:var(--success);font-size:.9rem;font-weight:600;margin-bottom:14px}
        .fp-sent-note{font-size:.78rem;color:var(--gray);text-align:center;line-height:1.5}
        .fp-demo-note{font-size:.78rem;color:var(--gray);text-align:center;line-height:1.5;margin-top:6px}
    </style>
</head>
<body>
<div class="auth-page">
    <div class="auth-card">
        <div class="logo">
            <h1>Medi<span>Queue</span></h1>
            <p>Healthcare Queue & Appointment Management</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo escape($error); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['reset_email']) && !$showStep2 && !$error): ?>
            <div class="alert alert-success"><?php echo escape($success); ?></div>
        <?php endif; ?>

        <!-- STEP 1 MODAL: request a code -->
        <div class="modal-overlay" id="fpModal1" <?php echo $showStep2 ? 'style="display:none"' : ''; ?>>
            <div class="modal">
                <div class="modal-header">
                    <h3>Reset Password</h3>
                    <button class="modal-close" onclick="window.location.href='login.php'">&times;</button>
                </div>
                <div class="modal-body">
                    <p class="subtitle">Enter the email address linked to your account and we'll email you a 6-digit reset code.</p>

                    <form method="POST" action="">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="forgot_password" value="1">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?php echo escape($email); ?>" placeholder="you@example.com" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">Send Reset Code</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- STEP 2 MODAL: enter code + new password -->
        <div class="modal-overlay" id="fpModal2" <?php echo $showStep2 ? '' : 'style="display:none"'; ?>>
            <div class="modal">
                <div class="modal-header">
                    <h3>Set a New Password</h3>
                    <button class="modal-close" onclick="window.location.href='login.php'">&times;</button>
                </div>
                <div class="modal-body">
                    <?php if ($success): ?>
                        <div class="fp-success">&#9989; <?php echo escape($success); ?></div>
                        <?php if ($demoCode): ?>
                            <div class="pre"><?php echo escape($demoCode); ?></div>
                            <div class="fp-demo-note">Demo mode: your reset code. Enter it below along with your new password.</div>
                        <?php endif; ?>
                        <div class="fp-sent-note" style="margin-bottom:14px"><?php echo escape($_SESSION['reset_email'] ?? ''); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="reset_password_step2" value="1">
                        <div class="form-group">
                            <label for="code">Reset Code</label>
                            <input type="text" id="code" name="code" class="form-control" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="000000" required>
                        </div>
                        <div class="form-group">
                            <label for="password">New Password</label>
                            <input type="password" id="password" name="password" class="form-control" minlength="6" placeholder="Min 6 characters" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="6" placeholder="Re-enter password" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
                    </form>
                    <div class="auth-footer">
                        <a href="forgot_password.php" onclick="window.location.href='forgot_password.php';return false;">Request a new code</a> &middot; <a href="login.php">Back to Sign In</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>