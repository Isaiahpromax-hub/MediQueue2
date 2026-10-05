<?php
/**
 * AJAX: verify the reset code and set a new password (returns JSON).
 * Mirrors reset_password.php so both paths behave identically.
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

$email = $_SESSION['reset_email'] ?? '';
if ($email === '') {
    exit(json_encode(['ok' => false, 'error' => 'No reset is in progress. Please start again.']));
}

$code = trim($_POST['code'] ?? '');
$password = $_POST['password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

if (empty($code) || empty($password) || empty($confirm)) {
    exit(json_encode(['ok' => false, 'error' => 'Please fill in all fields.']));
}
if (strlen($code) !== 6 || !ctype_digit($code)) {
    exit(json_encode(['ok' => false, 'error' => 'Reset code must be exactly 6 digits.']));
}
if (strlen($password) < 6) {
    exit(json_encode(['ok' => false, 'error' => 'Password must be at least 6 characters.']));
}
if ($password !== $confirm) {
    exit(json_encode(['ok' => false, 'error' => 'Passwords do not match.']));
}

$reset = db()->fetch(
    "SELECT id, expires_at, used FROM password_resets WHERE email = ? AND code = ? ORDER BY id DESC LIMIT 1",
    [$email, $code]
);

if (!$reset) {
    exit(json_encode(['ok' => false, 'error' => 'Invalid reset code. Please check and try again.']));
}
if ($reset['used']) {
    unset($_SESSION['reset_email']);
    exit(json_encode(['ok' => false, 'error' => 'This code has already been used. Please request a new one.']));
}
if (strtotime($reset['expires_at']) < time()) {
    unset($_SESSION['reset_email']);
    exit(json_encode(['ok' => false, 'error' => 'This code has expired. Please request a new one.']));
}

db()->update("UPDATE users SET password = ? WHERE email = ?", [password_hash($password, PASSWORD_DEFAULT), $email]);
db()->update("UPDATE password_resets SET used = 1 WHERE id = ?", [$reset['id']]);

$user = db()->fetch("SELECT id FROM users WHERE email = ?", [$email]);
if ($user) {
    logAudit($user['id'], 'password_reset', 'Password reset via code');
}

unset($_SESSION['reset_email']);
setFlash('success', 'Your password has been reset successfully. You can now sign in.');

exit(json_encode(['ok' => true, 'message' => 'Your password has been reset successfully.']));