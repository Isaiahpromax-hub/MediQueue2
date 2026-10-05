<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'POST required']);
    exit;
}

$action = $_POST['action'] ?? '';
$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];

switch ($action) {
    case 'call_next':
        if (!in_array($role, ['doctor', 'nurse', 'receptionist'], true)) {
            echo json_encode(['success' => false, 'message' => 'You are not allowed to call patients.']);
            exit;
        }
        $serviceId = intval($_POST['service_id'] ?? 0);
        if (!$serviceId) {
            echo json_encode(['error' => 'Invalid service']);
            exit;
        }

        $next = null;

        if ($role === 'doctor' || $role === 'nurse') {
            $me = getDoctorIdForUser();
            if (!$me || !canDoctorManageService($me, $serviceId)) {
                echo json_encode(['success' => false, 'message' => 'You can only manage patients for your assigned services.']);
                exit;
            }
            $next = db()->fetch(
                "SELECT * FROM queue_entries WHERE service_id = ? AND queue_date = CURDATE() AND status = 'Waiting' AND (doctor_id = ? OR doctor_id IS NULL) ORDER BY FIELD(priority, 'Emergency', 'Urgent', 'Normal'), position ASC LIMIT 1",
                [$serviceId, $me]
            );
        } else {
            $next = db()->fetch(
                "SELECT * FROM queue_entries WHERE service_id = ? AND queue_date = CURDATE() AND status = 'Waiting' ORDER BY FIELD(priority, 'Emergency', 'Urgent', 'Normal'), position ASC LIMIT 1",
                [$serviceId]
            );
        }

        if (!$next) {
            echo json_encode(['success' => false, 'message' => 'No patients waiting in this queue.']);
            exit;
        }

        if ($role === 'doctor' || $role === 'nurse') {
            $me = getDoctorIdForUser();
            db()->update("UPDATE queue_entries SET status = 'Called', called_at = NOW(), doctor_id = ? WHERE id = ?", [$me, $next['id']]);
        } else {
            db()->update("UPDATE queue_entries SET status = 'Called', called_at = NOW() WHERE id = ?", [$next['id']]);
        }

        $patient = db()->fetch("SELECT user_id, phone FROM patients WHERE id = ?", [$next['patient_id']]);
        if ($patient) {
            createNotification($patient['user_id'], 'You Are Being Called', "Your queue number {$next['queue_number']} is now being called. Please proceed.", 'queue', $next['id'], 'queue');
            sendSMS($patient['phone'], "MediQueue: Your queue number {$next['queue_number']} is now being called. Please proceed.");
        }

        logAudit($userId, 'queue_called', "Called patient {$next['queue_number']}");
        echo json_encode(['success' => true, 'message' => "Patient {$next['queue_number']} has been called."]);
        break;

    case 'start_service':
        if ($role === 'admin') {
            echo json_encode(['success' => false, 'message' => 'Administrators cannot start patient care. Use Override if this entry is stuck.']);
            exit;
        }
        $entryId = intval($_POST['id'] ?? 0);
        $entry = db()->fetch("SELECT * FROM queue_entries WHERE id = ?", [$entryId]);
        if (!$entry) {
            echo json_encode(['error' => 'Entry not found']);
            exit;
        }
        if (!canManageQueueEntry($entry)) {
            echo json_encode(['success' => false, 'message' => 'This patient is assigned to another provider.']);
            exit;
        }
        if ($role === 'doctor' || $role === 'nurse') {
            $me = getDoctorIdForUser();
            db()->update("UPDATE queue_entries SET status = 'Serving', serving_at = NOW(), doctor_id = ? WHERE id = ? AND status = 'Called'", [$me, $entryId]);
        } else {
            db()->update("UPDATE queue_entries SET status = 'Serving', serving_at = NOW() WHERE id = ? AND status = 'Called'", [$entryId]);
        }
        logAudit($userId, 'queue_serving', "Started service for {$entry['queue_number']}");
        echo json_encode(['success' => true, 'message' => 'Service started.']);
        break;

    case 'mark_served':
        if ($role === 'admin') {
            echo json_encode(['success' => false, 'message' => 'Administrators cannot mark patients as served. Use Override if this entry is stuck.']);
            exit;
        }
        $entryId = intval($_POST['id'] ?? 0);
        $entry = db()->fetch("SELECT * FROM queue_entries WHERE id = ?", [$entryId]);
        if (!$entry) {
            echo json_encode(['error' => 'Entry not found']);
            exit;
        }
        if (!canManageQueueEntry($entry)) {
            echo json_encode(['success' => false, 'message' => 'This patient is assigned to another provider.']);
            exit;
        }
        if ($role === 'doctor' || $role === 'nurse') {
            $me = getDoctorIdForUser();
            db()->update("UPDATE queue_entries SET status = 'Served', served_at = NOW(), doctor_id = ? WHERE id = ? AND status IN ('Serving','Called')", [$me, $entryId]);
        } else {
            db()->update("UPDATE queue_entries SET status = 'Served', served_at = NOW() WHERE id = ? AND status IN ('Serving','Called')", [$entryId]);
        }

        $patient = db()->fetch("SELECT user_id, phone FROM patients WHERE id = ?", [$entry['patient_id']]);
        if ($patient) {
            createNotification($patient['user_id'], 'Service Complete', "You have been served. Queue number {$entry['queue_number']}.", 'queue', $entry['id'], 'queue');
            sendSMS($patient['phone'], "MediQueue: You have been served. Queue number {$entry['queue_number']}. Thank you.");
        }

        $autoCompleted = null;
        if ($entry['patient_id'] && !empty($entry['queue_date'])) {
            $appt = db()->fetch(
                "SELECT a.id, a.appointment_id FROM appointments a
                 WHERE a.patient_id = ? AND a.appointment_date = ? AND a.status = 'Confirmed'
                   AND (? IS NULL OR a.doctor_id = ?)
                 ORDER BY a.appointment_time ASC LIMIT 1",
                [$entry['patient_id'], $entry['queue_date'], $entry['doctor_id'], $entry['doctor_id']]
            );
            if ($appt) {
                db()->update("UPDATE appointments SET status = 'Completed' WHERE id = ?", [$appt['id']]);
                $autoCompleted = $appt['appointment_id'];
            }
        }

        logAudit($userId, 'queue_served', "Served patient {$entry['queue_number']}" . ($autoCompleted ? " - auto-completed appointment $autoCompleted" : ''));
        echo json_encode(['success' => true, 'message' => 'Patient marked as served.' . ($autoCompleted ? " Appointment $autoCompleted completed automatically." : '')]);
        break;

    case 'skip':
        $entryId = intval($_POST['id'] ?? 0);
        $entry = db()->fetch("SELECT * FROM queue_entries WHERE id = ?", [$entryId]);
        if (!$entry) {
            echo json_encode(['error' => 'Entry not found']);
            exit;
        }
        if (!canManageQueueEntry($entry)) {
            echo json_encode(['success' => false, 'message' => 'This patient is assigned to another provider.']);
            exit;
        }
        db()->update("UPDATE queue_entries SET status = 'Skipped' WHERE id = ? AND status IN ('Waiting','Called')", [$entryId]);
        logAudit($userId, 'queue_skipped', "Skipped patient {$entry['queue_number']}");
        echo json_encode(['success' => true, 'message' => 'Patient skipped.']);
        break;

    case 'forward':
        $entryId = intval($_POST['id'] ?? 0);
        $targetDoctorId = intval($_POST['target_doctor_id'] ?? 0);
        $entry = db()->fetch("SELECT * FROM queue_entries WHERE id = ?", [$entryId]);
        if (!$entry) {
            echo json_encode(['error' => 'Entry not found']);
            exit;
        }
        if (!in_array($role, ['doctor', 'nurse', 'receptionist'], true)) {
            echo json_encode(['success' => false, 'message' => 'Not permitted to forward patients.']);
            exit;
        }
        if (!canManageQueueEntry($entry)) {
            echo json_encode(['success' => false, 'message' => 'You can only forward patients assigned to you or unassigned patients.']);
            exit;
        }
        if (!in_array($entry['status'], ['Waiting', 'Called'])) {
            echo json_encode(['success' => false, 'message' => 'Only waiting or called patients can be forwarded.']);
            exit;
        }
        if (($role === 'doctor' || $role === 'nurse') && $targetDoctorId == getDoctorIdForUser()) {
            echo json_encode(['success' => false, 'message' => 'The patient is already assigned to this provider.']);
            exit;
        }
        $target = db()->fetch(
            "SELECT d.id, d.user_id, d.first_name, d.last_name, d.type, u.is_active
             FROM doctors d
             JOIN users u ON d.user_id = u.id
             JOIN doctor_services ds ON ds.doctor_id = d.id AND ds.service_id = ?
             WHERE d.id = ? AND u.role IN ('doctor','nurse') LIMIT 1",
            [$entry['service_id'], $targetDoctorId]
        );
        if (!$target || !$target['is_active']) {
            echo json_encode(['success' => false, 'message' => 'Selected provider does not cover this service.']);
            exit;
        }

        $maxPos = db()->fetch(
            "SELECT MAX(position) AS max_pos FROM queue_entries WHERE queue_date = CURDATE() AND service_id = ? AND status = 'Waiting'",
            [$entry['service_id']]
        );
        $newPos = ($maxPos['max_pos'] ?? 0) + 1;
        $restorePos = $entry['restore_position'] !== null ? $entry['restore_position'] : $entry['position'];
        db()->update(
            "UPDATE queue_entries SET doctor_id = ?, status = 'Waiting', position = ?, restore_position = ? WHERE id = ? AND status IN ('Waiting','Called')",
            [$targetDoctorId, $newPos, $restorePos, $entryId]
        );

        $fromName = 'A provider';
        if ($role === 'doctor' || $role === 'nurse') {
            $meId = getDoctorIdForUser();
            $fd = db()->fetch("SELECT CONCAT(first_name, ' ', last_name) AS n, type FROM doctors WHERE id = ?", [$meId]);
            if ($fd) {
                $fromName = trim(preg_replace('/^Dr\.\s*/i', '', $fd['n']));
            }
        }
        $targetTitle = ($target['type'] ?? 'doctor') === 'nurse' ? 'Nurse' : 'Dr.';
        $targetName = trim(preg_replace('/^Dr\.\s*/i', '', $target['first_name'] . ' ' . $target['last_name']));
        $service = db()->fetch("SELECT name FROM services WHERE id = ?", [$entry['service_id']]);

        createNotification(
            $target['user_id'],
            'Patient Forwarded',
            "$fromName forwarded patient {$entry['queue_number']} for " . ($service['name'] ?? 'your service') . " to you.",
            'queue', $entry['id'], 'queue'
        );

        $patient = db()->fetch("SELECT user_id FROM patients WHERE id = ?", [$entry['patient_id']]);
        if ($patient) {
            createNotification(
                $patient['user_id'],
                'Provider Changed',
                "Your request {$entry['queue_number']} has been assigned to $targetTitle $targetName.",
                'queue', $entry['id'], 'queue'
            );
        }

        logAudit($userId, 'queue_forwarded', "Forwarded {$entry['queue_number']} to $targetTitle $targetName");
        echo json_encode(['success' => true, 'message' => "Patient {$entry['queue_number']} forwarded to $targetTitle $targetName."]);
        break;

    case 'release':
        if ($role === 'admin') {
            echo json_encode(['success' => false, 'message' => 'Administrators cannot release patients. Use Override to return the entry to the queue.']);
            exit;
        }
        $entryId = intval($_POST['id'] ?? 0);
        $entry = db()->fetch("SELECT * FROM queue_entries WHERE id = ?", [$entryId]);
        if (!$entry) {
            echo json_encode(['error' => 'Entry not found']);
            exit;
        }
        $myProviderId = getDoctorIdForUser();
        $isStaff = ($role === 'receptionist');
        if (!$isStaff) {
            if (!$myProviderId) {
                echo json_encode(['success' => false, 'message' => 'Provider profile not found.']);
                exit;
            }
            if ($entry['doctor_id'] != $myProviderId) {
                echo json_encode(['success' => false, 'message' => 'You can only release patients assigned to you.']);
                exit;
            }
        }
        if (!in_array($entry['status'], ['Waiting', 'Called'])) {
            echo json_encode(['success' => false, 'message' => 'Only waiting or called patients can be released.']);
            exit;
        }
        $restorePos = ($entry['restore_position'] !== null) ? $entry['restore_position'] : null;
        if ($restorePos === null) {
            $maxPos = db()->fetch(
                "SELECT MAX(position) AS max_pos FROM queue_entries WHERE queue_date = CURDATE() AND service_id = ? AND status = 'Waiting'",
                [$entry['service_id']]
            );
            $restorePos = ($maxPos['max_pos'] ?? 0) + 1;
        }
        db()->update(
            "UPDATE queue_entries SET doctor_id = NULL, position = ?, restore_position = NULL WHERE id = ? AND status IN ('Waiting','Called')",
            [$restorePos, $entryId]
        );
        $patient = db()->fetch("SELECT user_id FROM patients WHERE id = ?", [$entry['patient_id']]);
        if ($patient) {
            $restored = $restorePos !== null && $entry['restore_position'] !== null;
            $detail = $restored
                ? "You were returned to your original position (#{$restorePos}) and will be served by the first available provider."
                : 'You were returned to the end of the queue and will be served by the first available provider.';
            createNotification(
                $patient['user_id'],
                'Provider Released',
                "Your request {$entry['queue_number']} was released by the assigned provider. {$detail}",
                'queue', $entry['id'], 'queue'
            );
        }
        logAudit($userId, 'queue_released', "Released {$entry['queue_number']} back to the pool");
        echo json_encode(['success' => true, 'message' => 'Patient released back to the pool from the back of the queue — will be served by the first available provider.']);
        break;

    case 'leave':
        $entryId = intval($_POST['id'] ?? 0);
        $entry = db()->fetch("SELECT * FROM queue_entries WHERE id = ? AND patient_id = (SELECT id FROM patients WHERE user_id = ?)", [$entryId, $userId]);
        if (!$entry) {
            echo json_encode(['error' => 'Entry not found']);
            exit;
        }
        db()->update("UPDATE queue_entries SET status = 'Cancelled' WHERE id = ? AND status IN ('Waiting','Called')", [$entryId]);
        logAudit($userId, 'queue_left', "Left queue {$entry['queue_number']}");
        echo json_encode(['success' => true, 'message' => 'You have left the queue.']);
        break;

    case 'override':
        if ($role !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Only administrators can override a queue entry.']);
            exit;
        }
        $entryId = intval($_POST['id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        $newStatus = $_POST['status'] ?? '';
        if (mb_strlen($reason) < 5) {
            echo json_encode(['success' => false, 'message' => 'A reason of at least 5 characters is required.']);
            exit;
        }
        if (!in_array($newStatus, ['Served', 'Skipped', 'Waiting'], true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid override status.']);
            exit;
        }
        $entry = db()->fetch("SELECT * FROM queue_entries WHERE id = ?", [$entryId]);
        if (!$entry) {
            echo json_encode(['error' => 'Entry not found']);
            exit;
        }
        if ($newStatus === 'Served') {
            db()->update("UPDATE queue_entries SET status = 'Served', served_at = NOW() WHERE id = ?", [$entryId]);
        } elseif ($newStatus === 'Waiting') {
            $maxPos = db()->fetch(
                "SELECT MAX(position) AS max_pos FROM queue_entries WHERE queue_date = CURDATE() AND service_id = ? AND status = 'Waiting'",
                [$entry['service_id']]
            );
            db()->update(
                "UPDATE queue_entries SET status = 'Waiting', served_at = NULL, position = ? WHERE id = ?",
                [($maxPos['max_pos'] ?? 0) + 1, $entryId]
            );
        } else {
            db()->update("UPDATE queue_entries SET status = 'Skipped', served_at = NULL WHERE id = ?", [$entryId]);
        }
        logAudit($userId, 'queue_override', "ADMIN OVERRIDE: set {$entry['queue_number']} to {$newStatus}. Reason: {$reason}");
        echo json_encode(['success' => true, 'message' => "Override recorded for {$entry['queue_number']}."]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
        break;
}