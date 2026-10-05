<?php
/**
 * "Sign in with Google" OAuth 2.0 handler.
 *
 * First visit redirects the user to Google. After consent Google redirects
 * back with a code + state; we exchange the code for an access token
 * (server-to-server, using the client secret), fetch the verified Google
 * profile, then find-or-create a PATIENT account and sign them in.
 * Staff roles are never auto-created here — those stay password + admin approval.
 */

require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    redirectToDashboard(getRole());
}

if (!defined('GOOGLE_CLIENT_ID') || !GOOGLE_CLIENT_ID || !defined('GOOGLE_CLIENT_SECRET') || !GOOGLE_CLIENT_SECRET) {
    setFlash('danger', 'Google Sign-In is not configured yet. Please use the normal login.');
    redirect('login.php');
}

$goToError = function ($message) {
    setFlash('danger', $message);
    redirect('login.php');
};

/* User cancelled or Google returned an error. */
if (!empty($_GET['error'])) {
    $goToError('Google Sign-In was cancelled or failed. Please try again or use the normal login.');
}

/* Step 1: build the consent URL and send the user to Google. */
if (empty($_GET['code'])) {
    $state = bin2hex(random_bytes(24));
    $_SESSION['google_state'] = $state;

    $params = [
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'prompt'        => 'select_account',
        'access_type'   => 'online',
    ];

    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
    exit;
}

/* Step 2: callback — verify state (anti-CSRF) then exchange the code. */
$state = $_GET['state'] ?? '';
if (empty($_SESSION['google_state']) || !hash_equals($_SESSION['google_state'], $state)) {
    unset($_SESSION['google_state']);
    $goToError('Security check failed. Please try signing in with Google again.');
}
unset($_SESSION['google_state']);

$payload = http_build_query([
    'code'          => $_GET['code'],
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'grant_type'    => 'authorization_code',
]);

$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
]);
$tokenRes = curl_exec($ch);
$tokenErr = curl_errno($ch);
curl_close($ch);

if ($tokenErr) {
    $goToError('Could not reach Google. Please try again.');
}
$tokens = json_decode((string) $tokenRes, true);
if (empty($tokens['access_token'])) {
    $goToError('Sign in with Google failed. Please try again or use the normal login.');
}

/* Step 3: fetch the verified Google profile using the access token. */
$ch = curl_init('https://openidconnect.googleapis.com/v1/userinfo');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $tokens['access_token']],
]);
$userRes = curl_exec($ch);
$userErr = curl_errno($ch);
curl_close($ch);

if ($userErr) {
    $goToError('Could not fetch your Google profile. Please try again.');
}
$googleUser = json_decode((string) $userRes, true);

$email = strtolower(trim($googleUser['email'] ?? ''));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $goToError('Google did not return a valid email address for your account.');
}
if (empty($googleUser['email_verified'])) {
    $goToError('Your Google email is not verified. Please verify it on your Google account first.');
}

$existing = db()->fetch("SELECT * FROM users WHERE email = ?", [$email]);
if ($existing) {
    if (!$existing['is_active']) {
        setFlash('warning', 'Your account is inactive or awaiting admin approval. Please contact support.');
        redirect('login.php');
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = $existing['id'];
    $_SESSION['role']    = $existing['role'];
    logAudit($existing['id'], 'login', 'User logged in with Google');
    redirectToDashboard($existing['role']);
}

/* New user — patient only. Generate a random password (cannot be guessed or used for recovery). */
$name     = trim($googleUser['name'] ?? '');
$nameParts = $name !== '' ? preg_split('/\s+/', $name, 2) : [];
$firstName = $nameParts[0] ?? '';
$lastName  = $nameParts[1] ?? '';

$hashedPassword = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
$userId = db()->insert(
    "INSERT INTO users (email, password, role, is_active) VALUES (?, ?, 'patient', 1)",
    [$email, $hashedPassword]
);

$patientCode = 'PAT' . str_pad($userId, 3, '0', STR_PAD_LEFT);
db()->insert(
    "INSERT INTO patients (user_id, patient_code, first_name, last_name) VALUES (?, ?, ?, ?)",
    [$userId, $patientCode, $firstName ?: 'Google', $lastName ?: 'User']
);

logAudit($userId, 'registration', 'New patient registered via Google');
createNotification($userId, 'Welcome to MediQueue', 'Your account was created with Google Sign-In. Welcome aboard!');

session_regenerate_id(true);
$_SESSION['user_id'] = $userId;
$_SESSION['role']    = 'patient';
redirect('patient/dashboard.php');