<?php
$pageTitle = 'Staff Dashboard';
require_once '../includes/header.php';
requireStaff();

$assignedServicesSql = "SELECT service_id FROM doctor_services";

$todayAppts = db()->fetch("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = CURDATE() AND service_id IN ($assignedServicesSql)")['count'];
$pendingAppts = db()->fetch("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = CURDATE() AND status = 'Pending' AND service_id IN ($assignedServicesSql)")['count'];
$waitingPatients = db()->fetch("SELECT COUNT(*) as count FROM queue_entries WHERE queue_date = CURDATE() AND status = 'Waiting' AND service_id IN ($assignedServicesSql)")['count'];
$servedToday = db()->fetch("SELECT COUNT(*) as count FROM queue_entries WHERE queue_date = CURDATE() AND status = 'Served' AND service_id IN ($assignedServicesSql)")['count'];
$totalPatients = db()->fetch("SELECT COUNT(*) as count FROM patients")['count'];
$cancelledToday = db()->fetch("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = CURDATE() AND status = 'Cancelled' AND service_id IN ($assignedServicesSql)")['count'];

$recentAppts = db()->fetchAll(
    "SELECT a.*, p.first_name, p.last_name, s.name as service_name
     FROM appointments a
     JOIN patients p ON a.patient_id = p.id
     JOIN services s ON a.service_id = s.id
     WHERE a.appointment_date = CURDATE() AND a.service_id IN ($assignedServicesSql)
     ORDER BY a.appointment_time ASC LIMIT 10"
);

$services = db()->fetchAll("SELECT * FROM services WHERE is_active = 1 ORDER BY name");
$providerName = "CASE WHEN d.type = 'nurse' THEN CONCAT('Nurse ', CASE WHEN d.first_name LIKE 'Dr. %' THEN SUBSTRING(d.first_name, 5) ELSE d.first_name END, ' ', d.last_name) ELSE CONCAT('Dr. ', CASE WHEN d.first_name LIKE 'Dr. %' THEN SUBSTRING(d.first_name, 5) ELSE d.first_name END, ' ', d.last_name) END";
$nextQueue = db()->fetchAll(
    "SELECT q.*, p.first_name, p.last_name, s.name as service_name, $providerName AS provider_name
     FROM queue_entries q
     JOIN patients p ON q.patient_id = p.id
     JOIN services s ON q.service_id = s.id
     LEFT JOIN doctors d ON q.doctor_id = d.id
     WHERE q.queue_date = CURDATE() AND q.status IN ('Waiting','Called','Serving')
     AND q.service_id IN ($assignedServicesSql)
     ORDER BY FIELD(q.priority, 'Emergency', 'Urgent', 'Normal'), FIELD(q.status, 'Serving', 'Called', 'Waiting'), q.position ASC"
);
?>

<div class="page-content">
    <div class="page-title">Staff Dashboard</div>
    <div class="page-subtitle">Welcome back. Today's activity for services with an assigned provider.</div>

    <div class="d-flex justify-between align-center mb-20">
        <div class="alert alert-danger" id="activeEmergenciesBanner" style="display:none;margin:0;flex:1;">
            <strong id="emergencyBannerText"></strong>
        </div>
        <a href="emergency_checkin.php" class="btn btn-danger btn-lg" style="min-width:220px;font-weight:700;white-space:nowrap;margin-left:auto;">&#9873; Emergency Check-in</a>
    </div>

    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-icon blue">&#128197;</div>
            <div class="stat-info">
                <h3><?php echo $todayAppts; ?></h3>
                <p>Today's Appointments</p>
            </div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon orange">&#9201;</div>
            <div class="stat-info">
                <h3><?php echo $waitingPatients; ?></h3>
                <p>Waiting Patients</p>
            </div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon green">&#10004;</div>
            <div class="stat-info">
                <h3><?php echo $servedToday; ?></h3>
                <p>Served Today</p>
            </div>
        </div>
        <div class="stat-card red">
            <div class="stat-icon red">&#10006;</div>
            <div class="stat-info">
                <h3><?php echo $cancelledToday; ?></h3>
                <p>Cancelled</p>
            </div>
        </div>
        <div class="stat-card teal">
            <div class="stat-icon teal">&#128101;</div>
            <div class="stat-info">
                <h3><?php echo $totalPatients; ?></h3>
                <p>Total Patients</p>
            </div>
        </div>
        <div class="stat-card purple">
            <div class="stat-icon purple">&#128196;</div>
            <div class="stat-info">
                <h3><?php echo $pendingAppts; ?></h3>
                <p>Pending Approval</p>
            </div>
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <div class="card-header">
                <div class="card-title">Today's Appointments</div>
                <a href="appointments.php" class="btn btn-sm btn-outline">View All</a>
            </div>
            <?php if (empty($recentAppts)): ?>
                <div class="empty-state"><p>No appointments today.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr><th>Time</th><th>Patient</th><th>Service</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentAppts as $a): ?>
                        <tr>
                            <td><?php echo date('h:i A', strtotime($a['appointment_time'])); ?></td>
                            <td><?php echo escape($a['first_name'] . ' ' . $a['last_name']); ?></td>
                            <td><?php echo escape($a['service_name']); ?></td>
                            <td><span class="badge badge-<?php echo strtolower($a['status']); ?>"><?php echo $a['status']; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">Queue Overview</div>
                <a href="queue.php" class="btn btn-sm btn-primary">Manage Queue</a>
            </div>
            <?php if (empty($nextQueue)): ?>
                <div class="empty-state"><p>No patients in queue.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr><th>#</th><th>Priority</th><th>Patient</th><th>Provider</th><th>Service</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($nextQueue as $q): ?>
                        <tr>
                            <td><strong><?php echo escape($q['queue_number']); ?></strong></td>
                            <td><?php echo priorityBadge($q['priority']); ?></td>
                            <td><?php echo escape($q['first_name'] . ' ' . $q['last_name']); ?></td>
                            <td><?php echo escape($q['provider_name'] ?? 'Unassigned'); ?></td>
                            <td><?php echo escape($q['service_name']); ?></td>
                            <td><span class="badge badge-<?php echo strtolower($q['status']); ?>"><?php echo $q['status']; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mt-30">
        <div class="card-header">
            <div class="card-title">Providers &amp; Patients by Staff</div>
            <span class="muted" style="font-size:12px;">Who is in your service — who they are serving, who is waiting, and what else happened today.</span>
        </div>
        <div class="table-responsive">
            <table class="table" id="providersTable">
                <thead>
                    <tr><th>Provider</th><th>Services</th><th>Status</th><th>Now Being Served</th><th>Waiting</th><th>Others Today</th></tr>
                </thead>
                <tbody id="providersBody">
                    <tr><td colspan="6" class="muted">Loading providers…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
}
function listText(items, max) {
    if (!items || !items.length) return '<span class="muted">—</span>';
    const shown = items.slice(0, max);
    let out = shown.map(i => '<div class="queue-chip">' + esc(i) + '</div>').join('');
    if (items.length > max) out += '<span class="muted">+' + (items.length - max) + ' more</span>';
    return out;
}
function refreshProviders() {
    fetch('/MediQueue2/api/staff_providers.php')
        .then(r => r.json())
        .then(data => {
            const body = document.getElementById('providersBody');
            if (!body) return;
            if (!data.ok || !data.providers || !data.providers.length) {
                body.innerHTML = '<tr><td colspan="6" class="muted">No providers are assigned to any service yet.</td></tr>';
                return;
            }
            body.innerHTML = data.providers.map(p => {
                const status = p.is_available
                    ? '<span class="online-dot"></span><span class="text-success">On duty</span>'
                    : '<span class="offline-dot"></span><span class="muted">Off duty</span>';
                return '<tr>'
                    + '<td><strong>' + esc(p.name) + '</strong>' + (p.type === 'nurse' ? ' <span class="badge badge-nurse">Nurse</span>' : ' <span class="badge badge-doctor">Doctor</span>') + '</td>'
                    + '<td><span class="muted">' + esc(p.services) + '</span></td>'
                    + '<td>' + status + '</td>'
                    + '<td>' + listText(p.serving, 50) + '</td>'
                    + '<td>' + listText(p.waiting, 100) + '</td>'
                    + '<td><span class="muted">Served ' + esc(p.served_count) + ' · Skipped ' + esc(p.skipped_count) + '</span></td>'
                    + '</tr>';
            }).join('');
        })
        .catch(() => {
            const body = document.getElementById('providersBody');
            if (body) body.innerHTML = '<tr><td colspan="6" class="muted">Could not load providers.</td></tr>';
        });
}
function refreshEmergencies() {
    fetch('/MediQueue2/api/emergencies.php')
        .then(r => r.json())
        .then(data => {
            const banner = document.getElementById('activeEmergenciesBanner');
            if (!banner) return;
            const n = (data.emergencies || []).length;
            if (n > 0) {
                const labels = data.emergencies.map(e => e.queue_number + ' (' + e.first_name + ')').join(', ');
                document.getElementById('emergencyBannerText').textContent =
                    'ACTIVE EMERGENCIES: ' + labels + ' — please attend immediately.';
                banner.style.display = 'flex';
            } else {
                banner.style.display = 'none';
            }
        })
        .catch(() => {});
}
document.addEventListener('DOMContentLoaded', () => {
    refreshEmergencies();
    setInterval(refreshEmergencies, 5000);
    refreshProviders();
    setInterval(refreshProviders, 10000);
});
</script>

<?php require_once '../includes/footer.php'; ?>
