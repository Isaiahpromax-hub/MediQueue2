<?php
/**
 * Authentication Helper
 */

require_once __DIR__ . '/../config/database.php';

session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return db()->fetch("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
}

function getRole() {
    return $_SESSION['role'] ?? null;
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /MediQueue2/login.php');
        exit;
    }
}

function requireRole($roles) {
    requireLogin();
    if (!is_array($roles)) $roles = [$roles];
    if (!in_array($_SESSION['role'], $roles)) {
        header('Location: /MediQueue2/login.php');
        exit;
    }
}

function requirePatient() { requireRole('patient'); }
function requireStaff() { requireRole(['receptionist', 'admin']); }
function requireDoctor() { requireRole(['doctor', 'admin']); }
function requireNurse() { requireRole(['nurse', 'admin']); }
function requireProvider() { requireRole(['doctor', 'nurse', 'admin']); }
function requireAdmin() { requireRole('admin'); }

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

function createNotification($userId, $title, $message, $type = 'system', $relatedId = null, $relatedType = null) {
    db()->insert(
        "INSERT INTO notifications (user_id, title, message, type, related_id, related_type) VALUES (?, ?, ?, ?, ?, ?)",
        [$userId, $title, $message, $type, $relatedId, $relatedType]
    );
}

function notifyStaffInbox($title, $message, $type = 'system', $relatedId = null, $relatedType = null) {
    $staff = db()->fetchAll("SELECT id FROM users WHERE role IN ('admin','receptionist') AND is_active = 1");
    foreach ($staff as $s) {
        if (isset($_SESSION['user_id']) && (int)$s['id'] === (int)$_SESSION['user_id']) {
            continue;
        }
        createNotification($s['id'], $title, $message, $type, $relatedId, $relatedType);
    }
}

function normalizePhone($phone) {
    $digits = preg_replace('/[^0-9]/', '', (string) $phone);
    if ($digits === '') {
        return null;
    }
    if (strlen($digits) === 10 && $digits[0] === '0') {
        return '254' . substr($digits, 1);
    }
    return $digits;
}

/**
 * Records and (optionally) delivers an SMS/WhatsApp message.
 * In log-only mode (SMS_GATEWAY_URL empty) the message is stored as 'queued'.
 */
function sendSMS($phone, $message, $channel = 'sms') {
    $phone = normalizePhone($phone);
    if ($phone === null) {
        return null;
    }

    $status = 'queued';
    $error = null;

    if (defined('SMS_GATEWAY_URL') && SMS_GATEWAY_URL && defined('SMS_API_KEY') && SMS_API_KEY) {
        $payload = json_encode([
            'to' => $phone,
            'message' => $message,
            'channel' => $channel,
            'sender' => SMS_SENDER,
        ]);
        $ch = curl_init(SMS_GATEWAY_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . SMS_API_KEY,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            $status = 'failed';
        } elseif ($httpCode >= 200 && $httpCode < 300) {
            $status = 'sent';
        } else {
            $error = "HTTP $httpCode: " . substr((string) $response, 0, 200);
            $status = 'failed';
        }
        curl_close($ch);
    }

    db()->insert(
        "INSERT INTO sms_messages (phone, message, channel, status, error, sent_at) VALUES (?, ?, ?, ?, ?, ?)",
        [
            $phone,
            $message,
            $channel,
            $status,
            $error,
            $status === 'sent' ? date('Y-m-d H:i:s') : null,
        ]
    );

    return ['phone' => $phone, 'status' => $status];
}

/**
 * Sends a real email via SMTP (Gmail). Requires SMTP_* constants in
 * config/database.php. Returns true on success, false on failure.
 */
function sendEmail($to, $subject, $message, $html = null) {
    require_once __DIR__ . '/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/SMTP.php';
    require_once __DIR__ . '/PHPMailer/Exception.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->isHTML(!empty($html));
        $mail->Body = $html ?: nl2br(htmlspecialchars($message));
        $mail->AltBody = $message;

        if (!$mail->send()) {
            error_log('sendEmail failed: ' . $mail->ErrorInfo);
            return false;
        }
        return true;
    } catch (\Throwable $e) {
        error_log('sendEmail exception: ' . $e->getMessage());
        return false;
    }
}

function logAudit($userId, $action, $description) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    db()->insert(
        "INSERT INTO audit_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)",
        [$userId, $action, $description, $ip]
    );
}

function getUnreadNotificationCount($userId) {
    $result = db()->fetch("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0", [$userId]);
    return $result['count'] ?? 0;
}

function redirect($path) {
    if (!headers_sent()) {
        header("Location: /MediQueue2/$path");
        exit;
    }
    $jsPath = htmlspecialchars($path, ENT_QUOTES, 'UTF-8');
    echo '<script>window.location.replace("/MediQueue2/' . $jsPath . '");</script>';
    exit;
}

function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * "Sign in with Google" button markup (with divider).
 * Returns an empty string when Google OAuth is not configured.
 */
function googleSignInButton($label = 'Continue with Google') {
    if (!defined('GOOGLE_CLIENT_ID') || !GOOGLE_CLIENT_ID) {
        return '';
    }
    $svg = '<svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>';
    $label = escape($label);
    return '<div class="auth-divider"><span>or</span></div>'
        . '<a href="api/google_auth.php" class="google-btn">' . $svg . '<span>' . $label . '</span></a>';
}

/**
 * Sends the current user to their role dashboard.
 */
function redirectToDashboard($role) {
    $paths = [
        'patient'      => 'patient/dashboard.php',
        'receptionist' => 'staff/dashboard.php',
        'doctor'       => 'doctor/dashboard.php',
        'nurse'        => 'nurse/dashboard.php',
        'admin'        => 'admin/dashboard.php',
    ];
    redirect($paths[$role] ?? 'login.php');
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Queue permission helpers.
 * Receptionists/admins manage any entry. Doctors manage only entries for
 * their assigned services that are assigned to them (or not yet assigned).
 */

function getDoctorIdForUser($userId = null) {
    $userId = $userId ?? ($_SESSION['user_id'] ?? null);
    if (!$userId) return null;
    $doc = db()->fetch("SELECT id FROM doctors WHERE user_id = ?", [$userId]);
    return $doc ? $doc['id'] : null;
}

function getDoctorServiceIds($doctorId) {
    if (!$doctorId) return [];
    $rows = db()->fetchAll("SELECT service_id FROM doctor_services WHERE doctor_id = ?", [$doctorId]);
    return array_map(function ($r) { return $r['service_id']; }, $rows);
}

function canDoctorManageService($doctorId, $serviceId) {
    return in_array($serviceId, getDoctorServiceIds($doctorId));
}

function nextQueueNumber($prefix = null) {
    if ($prefix === null) {
        $prefix = db()->fetch("SELECT setting_value FROM system_settings WHERE setting_key = 'queue_prefix'")['setting_value'] ?? 'A';
    }
    $maxQueued = db()->fetch(
        "SELECT MAX(CAST(SUBSTRING(queue_number, CHAR_LENGTH(?) + 1) AS UNSIGNED)) AS m FROM queue_entries WHERE queue_number LIKE ?",
        [$prefix, $prefix . '%']
    );
    return $prefix . str_pad(($maxQueued['m'] ?? 0) + 1, 3, '0', STR_PAD_LEFT);
}

/**
 * The patient's currently-active queue entry, if any. A patient can hold at
 * most ONE live queue entry per day (Waiting / Called / Serving) — this keeps
 * self-joins, receptionist adds and triage check-ins from duplicating a patient.
 */
function getActiveQueueEntry($patientId) {
    return db()->fetch(
        "SELECT * FROM queue_entries WHERE patient_id = ? AND queue_date = CURDATE() AND status IN ('Waiting','Called','Serving') ORDER BY id DESC LIMIT 1",
        [$patientId]
    );
}

/**
 * Patient-facing service list: ONLY services actually assigned to an active
 * doctor or nurse via doctor_services, so unassigned services never appear
 * on the patient dashboard / booking / queue-join flows.
 */
function patientServices() {
    return db()->fetchAll(
        "SELECT DISTINCT s.*
         FROM services s
         JOIN doctor_services ds ON ds.service_id = s.id
         JOIN doctors d ON d.id = ds.doctor_id
         JOIN users u ON d.user_id = u.id
         WHERE s.is_active = 1 AND u.is_active = 1 AND u.role IN ('doctor','nurse')
         ORDER BY s.name"
    );
}

function serviceHasProvider($serviceId) {
    return (bool) db()->fetch(
        "SELECT 1 FROM doctor_services ds
         JOIN doctors d ON d.id = ds.doctor_id
         JOIN users u ON d.user_id = u.id
         WHERE ds.service_id = ? AND u.is_active = 1 AND u.role IN ('doctor','nurse')
         LIMIT 1",
        [$serviceId]
    );
}

function doctorServesService($doctorId, $serviceId) {
    return (bool) db()->fetch(
        "SELECT 1 FROM doctor_services WHERE doctor_id = ? AND service_id = ? LIMIT 1",
        [$doctorId, $serviceId]
    );
}

/**
 * Role-aware provider label: "Dr. First Last (Specialization)" for doctors,
 * "Nurse First Last" for nurses. Works off the doctors.d.type column.
 */
function providerDisplayName($row) {
    $isNurse = strtolower($row['type'] ?? '') === 'nurse';
    $first = preg_replace('/^(Dr\.|Nurse)\s*/i', '', $row['first_name'] ?? '');
    $out = ($isNurse ? 'Nurse ' : 'Dr. ') . $first . ' ' . ($row['last_name'] ?? '');
    if (!$isNurse && !empty($row['specialization'])) {
        $out .= ' (' . $row['specialization'] . ')';
    }
    return $out;
}

function priorityQueuePrefix($priority) {
    return $priority === 'Emergency' ? 'ER' : 'UR';
}

function priorityBadge($priority, $labelOnly = false) {
    $priority = $priority ?: 'Normal';
    $label = $priority === 'Emergency' ? 'EMERGENCY' : ($priority === 'Urgent' ? 'URGENT' : 'Normal');
    $icon = $priority === 'Emergency' ? '&#9873;' : ($priority === 'Urgent' ? '&#9888;' : '');
    $class = strtolower($priority);
    if ($labelOnly) return $label;
    return '<span class="badge badge-priority-' . $class . '">' . $icon . ' ' . $label . '</span>';
}

function notifyEmergencyProviders($serviceId, $queueNumber, $patientName) {
    $providers = db()->fetchAll(
        "SELECT DISTINCT u.id
         FROM doctors d
         JOIN users u ON d.user_id = u.id
         JOIN doctor_services ds ON ds.doctor_id = d.id
         LEFT JOIN system_settings ss ON ss.setting_key = 'on_duty_only'
         WHERE ds.service_id = ? AND u.is_active = 1 AND u.role IN ('doctor','nurse')
           AND (ss.setting_value IS NULL OR ss.setting_value = '0'
                OR (ss.setting_value = '1' AND d.is_available = 1))",
        [$serviceId]
    );
    foreach ($providers as $p) {
        createNotification(
            $p['id'],
            'EMERGENCY in Queue',
            "Emergency patient $patientName ($queueNumber) just entered the queue for your service. Please attend immediately.",
            'queue', null, 'emergency'
        );
    }
    notifyStaffInbox(
        'EMERGENCY in Queue',
        "Emergency patient $patientName ($queueNumber) just entered the queue. Please attend immediately.",
        'emergency'
    );
    logAudit(getUserId() ?: 0, 'emergency_queued', "Emergency $patientName added as $queueNumber (service $serviceId)");
}

/**
 * Triage reference lists. Returned by triageSymptomLists() for the UI and
 * consumed by assessTriage() so the UI and the engine never drift.
 */
function triageSymptomLists() {
    return [
        'critical' => [
            'chest_pain'   => 'Chest pain or pressure',
            'breathing'    => 'Difficulty breathing / shortness of breath',
            'bleeding'     => 'Severe / uncontrolled bleeding',
            'unconscious'  => 'Unconscious or unresponsive',
            'stroke'       => 'Stroke signs (facial droop, arm weakness, slurred speech)',
            'seizure'      => 'Seizure / convulsions',
            'allergic'     => 'Severe allergic reaction (face swelling, throat closing)',
            'poisoning'    => 'Poisoning or overdose',
            'self_harm'    => 'Self-harm or suicidal thoughts',
            'head_trauma'  => 'Severe head injury',
            'pregnancy'    => 'Pregnancy complications / serious bleeding',
        ],
        'urgent' => [
            'severe_pain'  => 'Severe pain (e.g. abdomen, back)',
            'high_fever'   => 'High fever',
            'vomiting'     => 'Persistent vomiting / diarrhea',
            'dizzy'        => 'Dizziness / fainting spells',
            'confused'     => 'Confusion / disorientation',
            'injury'       => 'Suspected fracture / major injury (not bleeding)',
        ],
    ];
}

/**
 * Triages a patient from reported symptoms + vitals.
 * Returns ['priority' => Normal|Urgent|Emergency, 'reasons' => [...]].
 * Any critical red-flag symptom OR a dangerously abnormal vital = Emergency.
 * Otherwise urgent symptoms / moderately abnormal vitals = Urgent.
 * Nothing abnormal = Normal (stay in the normal queue).
 */
function assessTriage($symptoms, $vitals) {
    $symptomLists = triageSymptomLists();
    $critical = array_fill_keys(array_keys($symptomLists['critical']), true);
    $urgentMap = array_fill_keys(array_keys($symptomLists['urgent']), true);

    $reasons = [];
    $emergency = false;
    $urgent = false;

    $symptoms = is_array($symptoms) ? $symptoms : [];

    foreach ($symptoms as $s) {
        if (isset($critical[$s])) {
            $emergency = true;
            $reasons[] = 'Red-flag symptom: ' . $symptomLists['critical'][$s];
        } elseif (isset($urgentMap[$s])) {
            $urgent = true;
            $reasons[] = 'Urgent symptom: ' . $symptomLists['urgent'][$s];
        }
    }

    $vitals = is_array($vitals) ? $vitals : [];

    $bp = preg_replace('/[^0-9\/]/', '', $vitals['bp'] ?? '');
    $hr = (int) ($vitals['hr'] ?? 0);
    $temp = (float) ($vitals['temp'] ?? 0);
    $spo2 = (int) ($vitals['spo2'] ?? 0);

    if ($bp !== '') {
        $parts = explode('/', $bp);
        $sys = (int) ($parts[0] ?? 0);
        $dia = (int) ($parts[1] ?? 0);
        if ($sys >= 180 || $dia >= 110) {
            $emergency = true;
            $reasons[] = "Critically high blood pressure {$sys}/{$dia}";
        } elseif ($sys >= 140 || $dia >= 90) {
            $urgent = true;
            $reasons[] = "Elevated blood pressure {$sys}/{$dia}";
        }
    }

    if ($hr > 0) {
        if ($hr >= 120 || $hr <= 50) {
            $emergency = true;
            $reasons[] = "Abnormal heart rate $hr bpm (normal 50-120)";
        } elseif ($hr >= 100) {
            $urgent = true;
            $reasons[] = "Elevated heart rate $hr bpm";
        }
    }

    if ($spo2 > 0) {
        if ($spo2 <= 90) {
            $emergency = true;
            $reasons[] = "Low oxygen saturation $spo2% (SpO2 ≤ 90)";
        } elseif ($spo2 <= 93) {
            $urgent = true;
            $reasons[] = "Borderline oxygen saturation $spo2%";
        }
    }

    if ($temp > 0) {
        if ($temp >= 39.5) {
            $emergency = true;
            $reasons[] = "Very high temperature $temp°C (≥ 39.5)";
        } elseif ($temp >= 38.0) {
            $urgent = true;
            $reasons[] = "Raised temperature $temp°C";
        }
    }

    if ($emergency) {
        $priority = 'Emergency';
    } elseif ($urgent) {
        $priority = 'Urgent';
    } else {
        $priority = 'Normal';
        $reasons[] = 'No red-flag symptoms or abnormal vitals recorded — patient can stay in the normal queue.';
    }

    if (!$reasons) {
        $reasons[] = 'No symptoms or vitals provided — treated as a normal queue entry.';
    }

    return ['priority' => $priority, 'reasons' => $reasons];
}

function vitalsSummary($vitalsJson) {
    if (empty($vitalsJson)) return '';
    $v = json_decode($vitalsJson, true);
    if (!is_array($v)) return '';
    $parts = [];
    if (!empty($v['bp'])) $parts[] = 'BP ' . $v['bp'];
    if (!empty($v['hr'])) $parts[] = 'HR ' . $v['hr'];
    if (!empty($v['temp'])) $parts[] = 'Temp ' . $v['temp'] . '&deg;C';
    if (!empty($v['spo2'])) $parts[] = 'SpO2 ' . $v['spo2'] . '%';
    if (!empty($v['notes'])) $parts[] = $v['notes'];
    return htmlspecialchars(implode(' &middot; ', $parts), ENT_QUOTES, 'UTF-8');
}

function canManageQueueEntry($entry) {
    $role = $_SESSION['role'] ?? null;
    if ($role === 'receptionist') return true;
    if ($role === 'doctor' || $role === 'nurse') {
        $me = getDoctorIdForUser();
        if (!$me) return false;
        if (!canDoctorManageService($me, $entry['service_id'])) return false;
        return ($entry['doctor_id'] == $me) || ($entry['doctor_id'] === null);
    }
    return false;
}

function uploadProfilePicture($file, $userId) {
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return 'Please choose an image file.';
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return 'Upload failed. Please try again.';
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        return 'Only JPG, PNG, WEBP or GIF images are allowed.';
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        return 'Image must be 2MB or smaller.';
    }
    $dir = __DIR__ . '/../uploads/profile';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $name = 'user_' . (int) $userId . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return 'Could not save the image. Please try again.';
    }
    $current = db()->fetch("SELECT profile_pic FROM users WHERE id = ?", [$userId]);
    if ($current && $current['profile_pic']) {
        $old = $dir . '/' . basename($current['profile_pic']);
        if (is_file($old)) { @unlink($old); }
    }
    db()->update("UPDATE users SET profile_pic = ? WHERE id = ?", [$name, $userId]);
    logAudit($userId, 'profile_photo_updated', 'Profile picture updated');
    return true;
}

function avatarThumb($profilePic, $initials = '') {
    $initials = escape($initials ?: 'U');
    if ($profilePic) {
        $url = '/MediQueue2/uploads/profile/' . escape($profilePic);
        return '<img class="avatar-thumb photo-zoom" src="' . $url . '" data-src="' . $url . '" alt="Avatar" title="Click to view">';
    }
    return '<span class="avatar-thumb avatar-thumb-text">' . $initials . '</span>';
}

function avatarLarge($profilePic, $initials = '') {
    $initials = escape($initials ?: 'U');
    if ($profilePic) {
        return '<img class="profile-avatar" src="/MediQueue2/uploads/profile/' . escape($profilePic) . '" alt="Avatar">';
    }
    return '<span class="profile-avatar profile-avatar-text">' . $initials . '</span>';
}
