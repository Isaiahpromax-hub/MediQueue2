<?php
$pageTitle = 'Join Queue';
require_once '../includes/header.php';
requirePatient();

function doctorDisplayName($row) {
    return providerDisplayName($row);
}

$patient = db()->fetch("SELECT * FROM patients WHERE user_id = ?", [$_SESSION['user_id']]);
$patientId = $patient['id'];

$activeQueue = getActiveQueueEntry($patientId);

$services = patientServices();
$doctors = db()->fetchAll(
    "SELECT d.*, GROUP_CONCAT(DISTINCT ds.service_id) AS service_ids
     FROM doctors d
     JOIN users u ON d.user_id = u.id
     LEFT JOIN doctor_services ds ON d.id = ds.doctor_id
     WHERE d.is_available = 1 AND u.is_active = 1 AND u.role IN ('doctor','nurse')
       AND EXISTS (SELECT 1 FROM doctor_services ds2 WHERE ds2.doctor_id = d.id)
     GROUP BY d.id
     ORDER BY d.type DESC, d.first_name"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$activeQueue) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $serviceId = intval($_POST['service_id'] ?? 0);
        $doctorId = intval($_POST['doctor_id'] ?? null);

        if (!$serviceId) {
            $error = 'Please select a service.';
        } else {
            $service = db()->fetch("SELECT * FROM services WHERE id = ? AND is_active = 1", [$serviceId]);
            if (!$service) {
                $error = 'Selected service is not available.';
            } elseif (!serviceHasProvider($service['id'])) {
                $error = 'This service has no available provider at the moment. Please choose another.';
            } else {
                $queueNumber = nextQueueNumber();

                $maxPos = db()->fetch("SELECT MAX(position) as max_pos FROM queue_entries WHERE queue_date = CURDATE() AND service_id = ? AND status = 'Waiting'", [$serviceId]);
                $position = ($maxPos['max_pos'] ?? 0) + 1;

                $entryId = db()->insert(
                    "INSERT INTO queue_entries (queue_number, patient_id, service_id, doctor_id, queue_date, status, position) VALUES (?, ?, ?, ?, CURDATE(), 'Waiting', ?)",
                    [$queueNumber, $patientId, $serviceId, $doctorId ?: null, $position]
                );

                createNotification($_SESSION['user_id'], 'Joined Queue', "You have joined the queue. Your number is $queueNumber.", 'queue', $entryId, 'queue');
                notifyStaffInbox(
                    'Patient Joined Queue',
                    "{$patient['first_name']} {$patient['last_name']} joined the queue with number $queueNumber.",
                    'queue', $entryId, 'queue'
                );
                logAudit($_SESSION['user_id'], 'queue_joined', "Joined queue $queueNumber");

                redirect('patient/check_queue.php');
            }
        }
    }
}
?>

<div class="page-content">
    <div class="page-title">Join Queue</div>
    <div class="page-subtitle">Enter a virtual queue and wait from wherever you are.</div>

    <?php if ($activeQueue): ?>
        <div class="card">
            <div class="alert alert-info">
                You are already in the queue. Your number is <strong><?php echo escape($activeQueue['queue_number']); ?></strong> (Status: <?php echo $activeQueue['status']; ?>).
            </div>
            <div class="text-center mt-20">
                <a href="check_queue.php" class="btn btn-primary btn-lg">View Queue Status</a>
            </div>
        </div>
    <?php else: ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo escape($error); ?></div>
        <?php endif; ?>

        <div class="card" style="max-width: 600px;">
            <form method="POST" action="">
                <?php echo csrfField(); ?>
                <div class="form-group">
                    <label for="service_id">Select Service / Department *</label>
                    <select id="service_id" name="service_id" class="form-control" required data-filter-doctors="1">
                        <option value="">Choose a service</option>
                        <?php foreach ($services as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo escape($s['name']); ?> (<?php echo escape($s['department']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="doctor_id">Preferred Provider (Optional)</label>
                    <select id="doctor_id" name="doctor_id" class="form-control">
                        <option value="">No preference</option>
                        <?php foreach ($doctors as $d): ?>
                            <option value="<?php echo $d['id']; ?>" data-services="<?php echo escape($d['service_ids']); ?>"><?php echo escape(doctorDisplayName($d)); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Leave blank if you have no preference. You will be served by the next available provider.</div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg btn-block">Join Queue</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
