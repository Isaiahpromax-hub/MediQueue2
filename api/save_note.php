<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$role = $_SESSION['role'];
if (!in_array($role, ['doctor', 'nurse'], true)) {
    echo json_encode(['success' => false, 'message' => 'Only medical staff can save clinical notes.']);
    exit;
}

$userId = $_SESSION['user_id'];
$me = getDoctorIdForUser();
if (!$me) {
    echo json_encode(['success' => false, 'message' => 'Provider profile not found.']);
    exit;
}

$type = $_POST['type'] ?? '';
$id = intval($_POST['id'] ?? 0);
$notes = trim($_POST['notes'] ?? '');

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Missing record.']);
    exit;
}
if (mb_strlen($notes) > 2000) {
    echo json_encode(['success' => false, 'message' => 'Note is too long (max 2000 characters).']);
    exit;
}

if ($type === 'queue') {
    $entry = db()->fetch("SELECT * FROM queue_entries WHERE id = ?", [$id]);
    if (!$entry) {
        echo json_encode(['success' => false, 'message' => 'Queue entry not found.']);
        exit;
    }
    if ((int) $entry['doctor_id'] !== (int) $me) {
        echo json_encode(['success' => false, 'message' => 'You can only write notes for patients you served.']);
        exit;
    }
    db()->update("UPDATE queue_entries SET notes = ? WHERE id = ?", [$notes, $id]);
    logAudit($userId, 'note_saved', "Saved note for queue patient {$entry['queue_number']}");
    echo json_encode(['success' => true, 'message' => 'Note saved.']);
} elseif ($type === 'appointment') {
    $appt = db()->fetch("SELECT * FROM appointments WHERE id = ?", [$id]);
    if (!$appt) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found.']);
        exit;
    }
    if ((int) $appt['doctor_id'] !== (int) $me) {
        echo json_encode(['success' => false, 'message' => 'You can only write notes for your own appointments.']);
        exit;
    }
    db()->update("UPDATE appointments SET notes = ? WHERE id = ?", [$notes, $id]);
    logAudit($userId, 'note_saved', "Saved note for appointment {$appt['appointment_id']}");
    echo json_encode(['success' => true, 'message' => 'Note saved.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Unknown record type.']);
}