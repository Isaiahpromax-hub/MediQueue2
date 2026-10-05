<?php
$pageTitle = 'System Settings';
require_once '../includes/header.php';
require_once '../includes/auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $settings = [
            'clinic_name'           => trim($_POST['clinic_name'] ?? ''),
            'clinic_phone'          => trim($_POST['clinic_phone'] ?? ''),
            'clinic_address'        => trim($_POST['clinic_address'] ?? ''),
            'queue_prefix'          => trim($_POST['queue_prefix'] ?? 'A'),
            'max_queue_per_day'     => intval($_POST['max_queue_per_day'] ?? 100),
            'avg_service_time'      => intval($_POST['avg_service_time'] ?? 15),
            'operating_hours_start' => $_POST['operating_hours_start'] ?? '08:00',
            'operating_hours_end'   => $_POST['operating_hours_end'] ?? '17:00',
        ];

        $changes = [];
        foreach ($settings as $key => $value) {
            $current = db()->fetch("SELECT setting_value FROM system_settings WHERE setting_key = ?", [$key]);
            if ($current && (string)$current['setting_value'] !== (string)$value) {
                $changes[] = "$key: '{$current['setting_value']}' -> '$value'";
            }
            db()->update(
                "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?",
                [$key, $value, $value]
            );
        }

        if (!empty($changes)) {
            logAudit(getUserId(), 'settings_update', 'Updated settings: ' . implode('; ', $changes));
        }

        setFlash('success', 'Settings saved successfully.');
        redirect('admin/settings.php');
    }
}

$settings = [];
$raw = db()->fetchAll("SELECT setting_key, setting_value, description FROM system_settings ORDER BY setting_key ASC");
foreach ($raw as $r) {
    $settings[$r['setting_key']] = [
        'value' => $r['setting_value'],
        'description' => $r['description'],
    ];
}
?>

<div class="page-content">
    <div class="page-title">System Settings</div>
    <div class="page-subtitle">Configure clinic and queue management settings.</div>

    <div class="card">
        <form method="POST">
            <?php echo csrfField(); ?>

            <h3 style="margin-bottom:20px;font-size:1.1rem;">Clinic Information</h3>

            <div class="form-group">
                <label>Clinic Name</label>
                <input type="text" name="clinic_name" class="form-control" value="<?php echo escape($settings['clinic_name']['value'] ?? ''); ?>">
                <small style="color:var(--gray);"><?php echo escape($settings['clinic_name']['description'] ?? ''); ?></small>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="clinic_phone" class="form-control" value="<?php echo escape($settings['clinic_phone']['value'] ?? ''); ?>">
                    <small style="color:var(--gray);"><?php echo escape($settings['clinic_phone']['description'] ?? ''); ?></small>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <input type="text" name="clinic_address" class="form-control" value="<?php echo escape($settings['clinic_address']['value'] ?? ''); ?>">
                    <small style="color:var(--gray);"><?php echo escape($settings['clinic_address']['description'] ?? ''); ?></small>
                </div>
            </div>

            <hr style="margin:20px 0;border-color:var(--border);">

            <h3 style="margin-bottom:20px;font-size:1.1rem;">Queue Configuration</h3>

            <div class="grid-2">
                <div class="form-group">
                    <label>Queue Prefix</label>
                    <input type="text" name="queue_prefix" class="form-control" maxlength="5" value="<?php echo escape($settings['queue_prefix']['value'] ?? 'A'); ?>">
                    <small style="color:var(--gray);"><?php echo escape($settings['queue_prefix']['description'] ?? ''); ?></small>
                </div>
                <div class="form-group">
                    <label>Max Queue Per Day</label>
                    <input type="number" name="max_queue_per_day" class="form-control" min="1" value="<?php echo escape($settings['max_queue_per_day']['value'] ?? '100'); ?>">
                    <small style="color:var(--gray);"><?php echo escape($settings['max_queue_per_day']['description'] ?? ''); ?></small>
                </div>
            </div>

            <div class="form-group">
                <label>Average Service Time (minutes)</label>
                <input type="number" name="avg_service_time" class="form-control" min="1" value="<?php echo escape($settings['avg_service_time']['value'] ?? '15'); ?>">
                <small style="color:var(--gray);"><?php echo escape($settings['avg_service_time']['description'] ?? ''); ?></small>
            </div>

            <hr style="margin:20px 0;border-color:var(--border);">

            <h3 style="margin-bottom:20px;font-size:1.1rem;">Operating Hours</h3>

            <div class="grid-2">
                <div class="form-group">
                    <label>Opening Time</label>
                    <input type="time" name="operating_hours_start" class="form-control" value="<?php echo escape($settings['operating_hours_start']['value'] ?? '08:00'); ?>">
                    <small style="color:var(--gray);"><?php echo escape($settings['operating_hours_start']['description'] ?? ''); ?></small>
                </div>
                <div class="form-group">
                    <label>Closing Time</label>
                    <input type="time" name="operating_hours_end" class="form-control" value="<?php echo escape($settings['operating_hours_end']['value'] ?? '17:00'); ?>">
                    <small style="color:var(--gray);"><?php echo escape($settings['operating_hours_end']['description'] ?? ''); ?></small>
                </div>
            </div>

            <div class="form-group" style="margin-top:20px;">
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
