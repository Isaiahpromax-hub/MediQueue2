<?php
/**
 * AJAX: request a password reset code (returns JSON).
 * Keeps the same logic as forgot_password.php so both paths behave identically.
 */
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['ok' => false, 'error' => 'Bad request.']));
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    exit(json_encode(['ok' => false, 'error' => 'Security token expired. Please refresh and try again.']));
}

$email = strtolower(trim($_POST['email'] ?? ''));
if ($email === '') {
    exit(json_encode(['ok' => false, 'error' => 'Please enter your email address.']));
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit(json_encode(['ok' => false, 'error' => 'Please enter a valid email address.']));
}

$user = db()->fetch("SELECT id, is_active, role FROM users WHERE email = ?", [$email]);

if (!$user) {
    $_SESSION['reset_email'] = $email;
    exit(json_encode([
        'ok'        => true,
        'message'   => 'If an account exists with that email, a reset code has been sent.',
        'demo_code' => null,
        'email'     => $email,
    ]));
}

if (!$user['is_active']) {
    exit(json_encode(['ok' => false, 'error' => 'Your account has been deactivated. Please contact support.']));
}

$code = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
$expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

db()->query("UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0", [$email]);
db()->insert("INSERT INTO password_resets (email, code, expires_at) VALUES (?, ?, ?)", [$email, $code, $expiresAt]);

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
        <p style='font-size:.75rem;color:#adb5bd;margin:16px 0 0'>If you did not request a password reset, you can ignore this email.</p>
    </div>
</div>";

$domain = strtolower(substr(strrchr($email, '@'), 1) ?: $email);

// Placeholder demo domains have no real mailbox -> skip send, expose the code.
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

exit(json_encode([
    'ok'        => true,
    'message'   => $emailSent ? 'A reset code has been sent to your email.' : 'The email could not be delivered to this address.',
    'demo_code' => $emailSent ? '' : $code,
    'email'     => $email,
]));