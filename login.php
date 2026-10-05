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

$error = '';
$fpEmail = trim($_POST['fp_email'] ?? $_GET['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['fp_email']) || isset($_POST['forgot_password'])) {
        /* handled by the in-page popup via api/reset_request.php — nothing to do on full submit */
        $fpEmail = trim($_POST['email'] ?? $fpEmail);
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } else {
            $user = db()->fetch("SELECT * FROM users WHERE email = ?", [$email]);
            if (!$user) {
                setFlash('warning', 'No account found with that email. Please register first.');
                redirect('register.php');
            }
            if ($user && password_verify($password, $user['password'])) {
                if (!$user['is_active']) {
                    $error = 'Your account is inactive or awaiting admin approval. Please contact support.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    logAudit($user['id'], 'login', 'User logged in');

                    if ($user['role'] === 'patient') redirect('patient/dashboard.php');
                    elseif ($user['role'] === 'receptionist') redirect('staff/dashboard.php');
                    elseif ($user['role'] === 'doctor') redirect('doctor/dashboard.php');
                    elseif ($user['role'] === 'nurse') redirect('nurse/dashboard.php');
                    elseif ($user['role'] === 'admin') redirect('admin/dashboard.php');
                }
            } else {
                $error = 'Incorrect password. Please try again or reset your password.';
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
    <title>Login - MediQueue</title>
    <link rel="stylesheet" href="assets/css/style.css?t=<?php echo time(); ?>">
    <style>
        .auth-divider{display:flex;align-items:center;gap:12px;margin:22px 0 14px;color:var(--gray);font-size:.85rem;font-weight:600}
        .auth-divider::before,.auth-divider::after{content:"";flex:1;height:1px;background:var(--border)}
        .google-btn{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:12px 14px;border:1px solid var(--border);border-radius:10px;background:#fff;color:#3c4043;font-size:.92rem;font-weight:600;text-decoration:none;transition:all .15s;box-shadow:0 1px 2px rgba(60,64,67,.1)}
        .google-btn:hover{background:#f6f8fa;border-color:#c7ccd3;color:#202124;box-shadow:0 1px 4px rgba(60,64,67,.2)}
        .reset-note{display:none;margin:0 0 16px;padding:12px 14px;border-radius:10px;font-size:.88rem;background:#E7F7EE;border:1px solid #BFE8D0;color:#0B6623}
        .reset-note.show{display:block}
        .reset-note b{color:#075e1e}
        .fp-modal .democode{font-size:1.9rem;font-weight:900;letter-spacing:8px;color:var(--primary-dark);background:var(--bg);border:2px dashed var(--info);border-radius:10px;text-align:center;padding:12px 8px;margin:16px 0 4px;font-variant-numeric:tabular-nums}
        .fp-tip{font-size:.76rem;color:var(--gray);line-height:1.5;text-align:center;margin-top:6px}
        .auth-page.auth-bg{background:linear-gradient(135deg, rgba(15,46,92,.55) 0%, rgba(29,78,216,.45) 100%), url('assets/images/hero-doctor-patient.jpg') center/cover no-repeat}
    </style>
</head>
<body>
<div class="auth-page auth-bg">
    <div class="auth-card">
        <div class="logo">
            <h1>Medi<span>Queue</span></h1>
            <p>Healthcare Queue & Appointment Management</p>
        </div>
        <h2>Sign In</h2>

        <div class="reset-note" id="resetNote"></div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo escape($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" value="<?php echo escape($fpEmail); ?>" required placeholder="you@example.com">
            </div>
            <div class="form-group" style="display:flex;justify-content:space-between;align-items:center">
                <label for="password" style="margin-bottom:0">Password</label>
                <a href="javascript:void(0)" id="forgotLink" style="font-size:.82rem;font-weight:600;color:var(--primary)">Forgot Password?</a>
            </div>
            <input type="password" id="password" name="password" class="form-control" required placeholder="Enter your password">
            <button type="submit" class="btn btn-primary btn-block btn-lg">Sign In</button>
        </form>

        <?php echo googleSignInButton('Sign in with Google'); ?>

        <div class="auth-footer">
            Don't have an account? <a href="register.php">Register here</a>
        </div>
        <div class="auth-footer" style="margin-top: 8px;">
            <a href="index.php" style="color: var(--gray);">Back to Home</a>
        </div>
    </div>
</div>

<!-- STEP 1 POPUP: enter email -->
<div class="modal-overlay" id="fpModal1" style="display:none">
    <div class="modal fp-modal">
        <div class="modal-header">
            <h3>Reset Password</h3>
            <button type="button" class="modal-close" onclick="closeFp()">&times;</button>
        </div>
        <div class="modal-body">
            <p class="fp-subtitle">Enter the email address linked to your account and we'll email you a 6-digit reset code.</p>
            <div class="form-group">
                <label for="fpEmail">Email Address</label>
                <input type="email" id="fpEmail" class="form-control" placeholder="you@example.com" required>
            </div>
            <button type="button" class="btn btn-primary btn-block" id="fpSendBtn" onclick="sendCode()">Send Reset Code</button>
            <div id="fpStep1Err"></div>
        </div>
    </div>
</div>

<!-- STEP 2 POPUP: enter code + new password -->
<div class="modal-overlay" id="fpModal2" style="display:none">
    <div class="modal fp-modal">
        <div class="modal-header">
            <h3>Enter Code &amp; Reset</h3>
            <button type="button" class="modal-close" onclick="closeFp()">&times;</button>
        </div>
        <div class="modal-body">
            <p class="fp-subtitle">Code sent to <b><span id="fpEmailShown"></span></b>. Enter it with your new password.</p>
            <div class="form-group">
                <label for="fpCode">Reset Code</label>
                <input type="text" id="fpCode" class="form-control" maxlength="6" placeholder="000000" required>
            </div>
            <div class="form-group">
                <label for="fpPass">New Password</label>
                <input type="password" id="fpPass" class="form-control" minlength="6" placeholder="Min 6 characters" required>
            </div>
            <div class="form-group">
                <label for="fpPass2">Confirm New Password</label>
                <input type="password" id="fpPass2" class="form-control" minlength="6" placeholder="Re-enter password" required>
            </div>
            <button type="button" class="btn btn-primary btn-block" onclick="verifyCode()">Reset Password</button>
            <div id="fpStep2Err"></div>
        </div>
    </div>
</div>

<script>
    var FP_CSRF = '<?php echo escape(generateCSRFToken()); ?>';
    var fpEmail = '';

    function openFp() {
        document.getElementById('fpModal1').style.display = 'flex';
        document.getElementById('fpModal2').style.display = 'none';
        clearErr();
        var typed = document.getElementById('email').value.trim();
        document.getElementById('fpEmail').value = typed;
        document.getElementById('fpEmail').focus();
    }
    function closeFp() {
        document.getElementById('fpModal1').style.display = 'none';
        document.getElementById('fpModal2').style.display = 'none';
    }
    function clearErr() {
        var e1 = document.getElementById('fpStep1Err'), e2 = document.getElementById('fpStep2Err');
        if (e1) e1.innerHTML = '';
        if (e2) e2.innerHTML = '';
    }

    document.getElementById('forgotLink').addEventListener('click', function (ev) { ev.preventDefault(); openFp(); });

    function sendCode() {
        var btn = document.getElementById('fpSendBtn');
        var email = document.getElementById('fpEmail').value.trim();
        var err = document.getElementById('fpStep1Err');
        err.innerHTML = '';
        if (!email) { err.innerHTML = '<div class="alert alert-danger" style="margin-top:10px">Please enter your email address.</div>'; return; }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { err.innerHTML = '<div class="alert alert-danger" style="margin-top:10px">Please enter a valid email address.</div>'; return; }
        btn.disabled = true; btn.textContent = 'Sending...';
        fetch('api/reset_request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'csrf_token=' + encodeURIComponent(FP_CSRF) + '&email=' + encodeURIComponent(email),
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (data) {
            btn.disabled = false; btn.textContent = 'Send Reset Code';
            if (!data.ok) { err.innerHTML = '<div class="alert alert-danger" style="margin-top:10px">' + data.error + '</div>'; return; }
            fpEmail = data.email;
            document.getElementById('fpModal1').style.display = 'none';
            document.getElementById('fpEmailShown').textContent = fpEmail;
            document.getElementById('fpModal2').style.display = 'flex';
            if (data.demo_code) {
                var d = document.createElement('div');
                d.className = 'democode';
                d.textContent = data.demo_code;
                d.style.marginTop = '12px';
                var tip = document.createElement('div');
                tip.className = 'fp-tip';
                tip.textContent = 'Demo mode: this inbox has no real mailbox, so your code is shown above.';
                document.getElementById('fpModal2').querySelector('.modal-body').appendChild(d);
                document.getElementById('fpModal2').querySelector('.modal-body').appendChild(tip);
            }
            document.getElementById('fpCode').focus();
        }).catch(function () {
            btn.disabled = false; btn.textContent = 'Send Reset Code';
            err.innerHTML = '<div class="alert alert-danger" style="margin-top:10px">Network error. Please try again.</div>';
        });
    }

    function verifyCode() {
        var code = document.getElementById('fpCode').value.trim();
        var pass = document.getElementById('fpPass').value;
        var pass2 = document.getElementById('fpPass2').value;
        var err = document.getElementById('fpStep2Err');
        err.innerHTML = '';
        if (!/^\d{6}$/.test(code)) { err.innerHTML = '<div class="alert alert-danger" style="margin-top:10px">Enter the 6-digit reset code.</div>'; return; }
        if (pass.length < 6) { err.innerHTML = '<div class="alert alert-danger" style="margin-top:10px">Password must be at least 6 characters.</div>'; return; }
        if (pass !== pass2) { err.innerHTML = '<div class="alert alert-danger" style="margin-top:10px">Passwords do not match.</div>'; return; }
        var body = 'csrf_token=' + encodeURIComponent(FP_CSRF) + '&code=' + encodeURIComponent(code) + '&password=' + encodeURIComponent(pass) + '&confirm_password=' + encodeURIComponent(pass2);
        fetch('api/reset_verify.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body,
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data.ok) { err.innerHTML = '<div class="alert alert-danger" style="margin-top:10px">' + data.error + '</div>'; return; }
            closeFp();
            document.getElementById('resetNote').innerHTML = 'Password reset successful for <b>' + fpEmail + '</b>. You can now sign in with your new password.';
            document.getElementById('resetNote').classList.add('show');
            document.getElementById('email').value = fpEmail;
            document.getElementById('password').value = '';
            document.getElementById('password').focus();
        }).catch(function () {
            err.innerHTML = '<div class="alert alert-danger" style="margin-top:10px">Network error. Please try again.</div>';
        });
    }
</script>
</body>
</html>
