<?php
require_once 'includes/auth.php';

if (isLoggedIn()) {
    $role = getRole();
    if ($role === 'patient') redirect('patient/dashboard.php');
    elseif ($role === 'receptionist') redirect('staff/dashboard.php');
    elseif ($role === 'doctor') redirect('doctor/dashboard.php');
    elseif ($role === 'nurse') redirect('nurse/dashboard.php');
    elseif ($role === 'admin') redirect('admin/dashboard.php');
}

$email = $_SESSION['reset_email'] ?? '';
if (empty($email)) {
    redirect('forgot_password.php');
}

$error = '';
$success = '';
$code = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $code = trim($_POST['code'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (empty($code) || empty($password) || empty($confirm)) {
            $error = 'Please fill in all fields.';
        } elseif (strlen($code) !== 6 || !ctype_digit($code)) {
            $error = 'Reset code must be exactly 6 digits.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $reset = db()->fetch(
                "SELECT id, expires_at, used FROM password_resets WHERE email = ? AND code = ? ORDER BY id DESC LIMIT 1",
                [$email, $code]
            );

            if (!$reset) {
                $error = 'Invalid reset code. Please check and try again.';
            } elseif ($reset['used']) {
                $error = 'This code has already been used. Please request a new one.';
                unset($_SESSION['reset_email']);
            } elseif (strtotime($reset['expires_at']) < time()) {
                $error = 'This code has expired. Please request a new one.';
                unset($_SESSION['reset_email']);
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                db()->update("UPDATE users SET password = ? WHERE email = ?", [$hashed, $email]);
                db()->update("UPDATE password_resets SET used = 1 WHERE id = ?", [$reset['id']]);

                $user = db()->fetch("SELECT id FROM users WHERE email = ?", [$email]);
                if ($user) {
                    logAudit($user['id'], 'password_reset', 'Password reset via code');
                }

                unset($_SESSION['reset_email']);

                setFlash('success', 'Your password has been reset successfully. You can now sign in.');
                redirect('login.php');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - MediQueue</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .auth-page{min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--secondary),var(--primary));padding:20px}
        .auth-card{background:#fff;border-radius:var(--radius);padding:40px 36px;width:100%;max-width:420px;box-shadow:var(--shadow-lg);text-align:center}
        .auth-card .logo h1{font-size:1.8rem;margin-bottom:4px}
        .auth-card .logo p{color:var(--gray);font-size:.85rem;margin-bottom:24px}
        .auth-card h2{font-size:1.2rem;margin-bottom:8px;text-align:left}
        .auth-card .subtitle{color:var(--gray);font-size:.85rem;margin-bottom:20px;text-align:left}
        .icon-circle{width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,var(--success),#1a9e42);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:1.8rem;color:#fff}
        .code-input{text-align:center;font-size:1.6rem;letter-spacing:8px;font-weight:700;padding:12px}
        .auth-footer{margin-top:16px;font-size:.85rem;color:var(--gray)}
        .auth-footer a{color:var(--primary);font-weight:600}
        .email-badge{display:inline-block;background:#E9ECEF;padding:4px 14px;border-radius:20px;font-size:.8rem;color:var(--gray);margin-bottom:16px}
    </style>
</head>
<body>
<div class="auth-page">
    <div class="auth-card">
        <div class="logo">
            <h1>Medi<span>Queue</span></h1>
            <p>Healthcare Queue & Appointment Management</p>
        </div>

        <div class="icon-circle">&#128273;</div>
        <h2>Reset Your Password</h2>
        <div class="email-badge">&#9993; <?php echo escape($email); ?></div>
        <p class="subtitle">Enter the 6-digit code sent to your email, then create a new password.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo escape($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <div class="form-group">
                <label for="code">Reset Code</label>
                <input type="text" id="code" name="code" class="form-control code-input" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" value="<?php echo escape($code); ?>" required placeholder="000000">
            </div>
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" class="form-control" required minlength="6" placeholder="Min 6 characters">
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="6" placeholder="Re-enter password">
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg">Reset Password</button>
        </form>

        <div class="auth-footer">
            <a href="forgot_password.php">Request a new code</a> &middot; <a href="login.php">Back to Sign In</a>
        </div>
    </div>
</div>
</body>
</html>