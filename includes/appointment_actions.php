<?php
require_once __DIR__ . '/auth.php';

function appointmentActionFetch($apptId) {
    return db()->fetch(
        "SELECT a.*, p.user_id AS patient_user_id, p.phone,
                d.user_id AS doctor_user_id, d.first_name AS d_first, d.last_name AS d_last, d.type AS d_type
         FROM appointments a
         JOIN patients p ON a.patient_id = p.id
         JOIN doctors d ON a.doctor_id = d.id
         WHERE a.id = ?",
        [$apptId]
    );
}

function appointmentProviderLabel($appt) {
    return providerDisplayName([
        'first_name' => $appt['d_first'] ?? '',
        'last_name' => $appt['d_last'] ?? '',
        'type' => $appt['d_type'] ?? 'doctor',
        'specialization' => null,
    ]);
}

function appointmentWhen($appt) {
    return date('M d, Y', strtotime($appt['appointment_date'])) . ' at ' . date('h:i A', strtotime($appt['appointment_time']));
}

function confirmAppointment($apptId) {
    $actorId = $_SESSION['user_id'] ?? null;
    $role = $_SESSION['role'] ?? null;
    if (!$actorId) return ['ok' => false, 'msg' => 'Not authenticated.'];

    $appt = appointmentActionFetch($apptId);
    if (!$appt) return ['ok' => false, 'msg' => 'Appointment not found.'];

    if (in_array($role, ['doctor', 'nurse'], true)) {
        $myPid = getDoctorIdForUser();
        if (!$myPid || (int) $appt['doctor_id'] !== (int) $myPid) {
            return ['ok' => false, 'msg' => 'You can only approve your own appointments.'];
        }
    } elseif (!in_array($role, ['receptionist', 'admin'], true)) {
        return ['ok' => false, 'msg' => 'You are not allowed to confirm appointments.'];
    }

    $affected = db()->update(
        "UPDATE appointments SET status = 'Confirmed' WHERE id = ? AND status = 'Pending'",
        [$apptId]
    );
    if (!$affected) return ['ok' => false, 'msg' => 'Only pending appointments can be confirmed.'];

    $provider = appointmentProviderLabel($appt);
    $when = appointmentWhen($appt);
    createNotification(
        $appt['patient_user_id'],
        'Appointment Confirmed',
        "Your appointment ({$appt['appointment_id']}) with $provider is confirmed — the provider will be available on $when.",
        'appointment'
    );
    sendSMS($appt['phone'], "MediQueue: Your appointment {$appt['appointment_id']} with $provider is confirmed on $when.");
    logAudit($actorId, 'appointment_confirmed', "Confirmed appointment {$appt['appointment_id']}");

    return ['ok' => true, 'msg' => "Appointment {$appt['appointment_id']} confirmed — patient notified."];
}

function cancelAppointment($apptId) {
    $actorId = $_SESSION['user_id'] ?? null;
    $role = $_SESSION['role'] ?? null;
    if (!$actorId) return ['ok' => false, 'msg' => 'Not authenticated.'];

    $appt = appointmentActionFetch($apptId);
    if (!$appt) return ['ok' => false, 'msg' => 'Appointment not found.'];

    if (in_array($role, ['doctor', 'nurse'], true)) {
        $myPid = getDoctorIdForUser();
        if (!$myPid || (int) $appt['doctor_id'] !== (int) $myPid) {
            return ['ok' => false, 'msg' => 'You can only cancel your own appointments.'];
        }
    } elseif ($role === 'patient') {
        $myPatient = db()->fetch("SELECT id FROM patients WHERE user_id = ?", [$actorId]);
        if (!$myPatient || (int) $appt['patient_id'] !== (int) $myPatient['id']) {
            return ['ok' => false, 'msg' => 'You can only cancel your own appointments.'];
        }
    } elseif (!in_array($role, ['receptionist', 'admin'], true)) {
        return ['ok' => false, 'msg' => 'You are not allowed to cancel appointments.'];
    }

    $affected = db()->update(
        "UPDATE appointments SET status = 'Cancelled' WHERE id = ? AND status IN ('Pending','Confirmed')",
        [$apptId]
    );
    if (!$affected) return ['ok' => false, 'msg' => 'Only pending or confirmed appointments can be cancelled.'];

    $provider = appointmentProviderLabel($appt);
    $when = appointmentWhen($appt);
    createNotification(
        $appt['patient_user_id'],
        'Appointment Cancelled',
        "Your appointment ({$appt['appointment_id']}) with $provider on $when has been cancelled.",
        'appointment'
    );
    sendSMS($appt['phone'], "MediQueue: Your appointment {$appt['appointment_id']} on $when has been cancelled.");
    if ((int) $appt['doctor_user_id'] !== (int) $actorId) {
        createNotification(
            $appt['doctor_user_id'],
            'Appointment Cancelled',
            "Appointment {$appt['appointment_id']} scheduled for $when was cancelled.",
            'appointment'
        );
    }
    logAudit($actorId, 'appointment_cancelled', "Cancelled appointment {$appt['appointment_id']}");

    return ['ok' => true, 'msg' => "Appointment {$appt['appointment_id']} cancelled — patient notified."];
}