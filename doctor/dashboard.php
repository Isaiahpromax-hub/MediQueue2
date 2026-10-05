<?php
$pageTitle = 'Dashboard';
require_once '../includes/header.php';
requireDoctor();

$doctor = db()->fetch("SELECT * FROM doctors WHERE user_id = ?", [$_SESSION['user_id']]);
$doctorId = $doctor['id'];

$doctorServices = db()->fetchAll(
    "SELECT ds.service_id FROM doctor_services ds JOIN services s ON s.id = ds.service_id WHERE ds.doctor_id = ? AND s.is_active = 1",
    [$doctorId]
);
$serviceIds = array_map(function ($r) { return $r['service_id']; }, $doctorServices);

$todayAppts = db()->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND appointment_date = CURDATE() AND status NOT IN ('Cancelled','No Show')", [$doctorId])['count'];
$pendingAppts = db()->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND appointment_date = CURDATE() AND status = 'Pending'", [$doctorId])['count'];
$completedToday = db()->fetch("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND appointment_date = CURDATE() AND status = 'Completed'", [$doctorId])['count'];

$waitingPatients = 0;
$currentlyServing = null;
$nextPatient = null;

if (!empty($serviceIds)) {
    $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));

    $waitingPatients = db()->fetch(
        "SELECT COUNT(*) as count FROM queue_entries WHERE service_id IN ($placeholders) AND queue_date = CURDATE() AND status = 'Waiting' AND (doctor_id = ? OR doctor_id IS NULL)",
        array_merge($serviceIds, [$doctorId])
    )['count'];

    $activeList = db()->fetchAll(
        "SELECT q.*, p.first_name, p.last_name, s.name as service_name
         FROM queue_entries q
         JOIN patients p ON q.patient_id = p.id
         JOIN services s ON q.service_id = s.id
         WHERE q.service_id IN ($placeholders) AND q.queue_date = CURDATE() AND q.status IN ('Called','Serving')
         ORDER BY FIELD(q.status, 'Serving', 'Called'), q.position ASC",
        $serviceIds
    );

    foreach ($activeList as $a) {
        if ($a['doctor_id'] == $doctorId) {
            $currentlyServing = $a;
            break;
        }
    }

    $nextPatient = db()->fetch(
        "SELECT q.*, p.first_name, p.last_name, s.name as service_name
         FROM queue_entries q
         JOIN patients p ON q.patient_id = p.id
         JOIN services s ON q.service_id = s.id
         WHERE q.service_id IN ($placeholders) AND q.queue_date = CURDATE() AND q.status = 'Waiting' AND (q.doctor_id = ? OR q.doctor_id IS NULL)
         ORDER BY q.position ASC LIMIT 1",
        array_merge($serviceIds, [$doctorId])
    );
}

$primaryServiceId = $nextPatient['service_id'] ?? ($serviceIds[0] ?? 0);

$recentAppts = db()->fetchAll(
    "SELECT a.*, p.first_name, p.last_name, s.name as service_name
     FROM appointments a
     JOIN patients p ON a.patient_id = p.id
     JOIN services s ON a.service_id = s.id
     WHERE a.doctor_id = ? AND a.appointment_date = CURDATE()
     ORDER BY a.appointment_time ASC LIMIT 10",
    [$doctorId]
);

$forwardedToMe = db()->fetchAll(
    "SELECT n.id, n.title, n.message, n.created_at, n.is_read,
            q.queue_number, q.status AS q_status, p.first_name, p.last_name
     FROM notifications n
     LEFT JOIN queue_entries q ON n.related_id = q.id AND n.related_type = 'queue'
     LEFT JOIN patients p ON q.patient_id = p.id
     WHERE n.user_id = ? AND n.title = 'Patient Forwarded'
     ORDER BY n.created_at DESC LIMIT 6",
    [$_SESSION['user_id']]
);
?>

<div class="page-content">
    <div class="page-title">Doctor Dashboard</div>
    <div class="page-subtitle">Welcome, Dr. <?php echo escape($doctor['last_name']); ?>. Here's your overview.</div>

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
                <h3 id="dashWaitingCount"><?php echo $waitingPatients; ?></h3>
                <p>In My Queue</p>
            </div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon green">&#10004;</div>
            <div class="stat-info">
                <h3><?php echo $completedToday; ?></h3>
                <p>Completed Today</p>
            </div>
        </div>
        <div class="stat-card purple">
            <div class="stat-icon purple">&#128196;</div>
            <div class="stat-info">
                <h3><?php echo $pendingAppts; ?></h3>
                <p>Pending</p>
            </div>
        </div>
    </div>

    <div class="card mb-30">
        <div class="card-header">
            <div class="card-title">All Departments — Live Activity</div>
            <span style="font-size:0.8rem;color:var(--gray);">Same as the display board</span>
        </div>
        <div id="deptLiveActivity" class="table-responsive" style="padding:12px 16px;">
            <div class="empty-state"><p style="color:var(--gray);">No patients in any queue right now.</p></div>
        </div>
    </div>

    <div class="grid-2">
        <div>
            <div class="queue-current mb-20">
                <div class="current-label">My Current Patient</div>
                <div id="dashMyCurrent">
                    <?php if ($currentlyServing): ?>
                    <div class="current-number"><?php echo escape($currentlyServing['queue_number']); ?></div>
                    <div style="margin-top:6px;"><?php echo escape($currentlyServing['first_name'] . ' ' . $currentlyServing['last_name']); ?></div>
                    <div style="opacity:0.8;font-size:0.9rem;"><?php echo escape($currentlyServing['service_name']); ?></div>
                    <div class="queue-actions">
                        <?php if ($currentlyServing['status'] === 'Called'): ?>
                        <button onclick="startService(<?php echo $currentlyServing['id']; ?>, <?php echo $currentlyServing['service_id']; ?>)" class="btn btn-success btn-sm">Start Consultation</button>
                        <?php endif; ?>
                        <?php if ($currentlyServing['status'] === 'Serving'): ?>
                        <button onclick="markServed(<?php echo $currentlyServing['id']; ?>, <?php echo $currentlyServing['service_id']; ?>)" class="btn btn-success btn-sm">Mark Complete</button>
                        <?php endif; ?>
                        <button onclick="skipPatient(<?php echo $currentlyServing['id']; ?>, <?php echo $currentlyServing['service_id']; ?>)" class="btn btn-warning btn-sm">Skip</button>
                    </div>
                    <?php else: ?>
                    <div class="empty-state"><p>No patient currently in your consultation.</p></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="queue-next mb-20">
                <div class="next-label">Next For You</div>
                <div id="dashNext">
                    <?php if ($nextPatient): ?>
                    <div class="next-number"><?php echo escape($nextPatient['queue_number']); ?></div>
                    <div style="margin-top:4px;"><?php echo escape($nextPatient['first_name'] . ' ' . $nextPatient['last_name']); ?></div>
                    <?php else: ?>
                    <div class="next-number">---</div>
                    <div style="margin-top:4px;opacity:0.8;">No patients assigned to you currently waiting.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="text-center">
                <?php if ($primaryServiceId): ?>
                <button id="dashCallNext" onclick="callNextPatient(<?php echo $primaryServiceId; ?>)" class="btn btn-primary btn-lg">Call Next Patient</button>
                <?php else: ?>
                <button class="btn btn-primary btn-lg" disabled title="No services assigned">Call Next Patient</button>
                <?php endif; ?>
            </div>
        </div>

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
                    <thead><tr><th>Time</th><th>Patient</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($recentAppts as $a): ?>
                        <tr>
                            <td><?php echo date('h:i A', strtotime($a['appointment_time'])); ?></td>
                            <td><?php echo escape($a['first_name'] . ' ' . $a['last_name']); ?></td>
                            <td><span class="badge badge-<?php echo strtolower($a['status']); ?>"><?php echo $a['status']; ?></span></td>
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
            <div class="card-title">Forwarded to You</div>
            <span class="badge badge-primary"><?php echo count($forwardedToMe); ?> recent</span>
        </div>
        <?php if (empty($forwardedToMe)): ?>
            <div class="empty-state"><p>No patients have been forwarded to you recently.</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Time</th><th>Queue No.</th><th>Patient</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($forwardedToMe as $f): ?>
                    <tr>
                        <td><?php echo date('h:i A', strtotime($f['created_at'])); ?></td>
                        <td><strong><?php echo escape($f['queue_number'] ?? '--'); ?></strong></td>
                        <td>
                            <?php echo escape($f['first_name'] . ' ' . $f['last_name']); ?>
                            <?php if ($f['message']): ?><br><small style="color:var(--gray);"><?php echo escape($f['message']); ?></small><?php endif; ?>
                        </td>
                        <td>
                            <?php if ($f['q_status']): ?>
                            <span class="badge badge-<?php echo strtolower($f['q_status']); ?>"><?php echo escape($f['q_status']); ?></span>
                            <?php else: ?>
                            <span class="badge badge-inactive">Settled</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
let dashPollInterval = null;

function dashBtn(id, serviceId, action, label, cls) {
    return '<button onclick="' + action + '(' + id + ', ' + serviceId + ')" class="btn ' + cls + ' btn-sm">' + label + '</button>';
}

function renderDashMyCurrent(list) {
    const box = document.getElementById('dashMyCurrent');
    if (!box) return;
    if (!list.length) {
        box.innerHTML = '<div class="empty-state"><p>No patient currently in your consultation.</p></div>';
        return;
    }
    if (list.length === 1) {
        const m = list[0];
        const badgeClass = m.status === 'Called' ? 'badge-called' : 'badge-serving';
        const badgeLabel = m.status === 'Called' ? 'Called — please start' : m.status;
        let actions = '';
        if (m.status === 'Called') {
            actions += dashBtn(m.id, m.service_id, 'startService', 'Start Consultation', 'btn-success');
        } else if (m.status === 'Serving') {
            actions += dashBtn(m.id, m.service_id, 'markServed', 'Mark Complete', 'btn-success');
        }
        actions += dashBtn(m.id, m.service_id, 'skipPatient', 'Skip', 'btn-warning');
        box.innerHTML =
            '<div class="current-number">' + escapeHtml(m.queue_number) + '</div>' +
            '<div style="margin-top:6px;">' + escapeHtml(m.first_name + ' ' + m.last_name) + '</div>' +
            '<div style="opacity:0.8;font-size:0.9rem;">' + escapeHtml(m.service_name) + '</div>' +
            '<div style="margin-top:4px;"><span class="badge ' + badgeClass + '">' + escapeHtml(badgeLabel) + '</span></div>' +
            '<div class="queue-actions">' + actions + '</div>';
    } else {
        const rows = list.map(function (m) {
            let actions = '';
            if (m.status === 'Called') {
                actions += dashBtn(m.id, m.service_id, 'startService', 'Start', 'btn-success');
            } else if (m.status === 'Serving') {
                actions += dashBtn(m.id, m.service_id, 'markServed', 'Mark Served', 'btn-success');
            }
            actions += dashBtn(m.id, m.service_id, 'skipPatient', 'Skip', 'btn-warning');
            return '<div class="dash-current-row">' +
                '<div><strong>' + escapeHtml(m.queue_number) + '</strong> — ' + escapeHtml(m.first_name + ' ' + m.last_name) +
                ' <span style="opacity:0.7;">(' + escapeHtml(m.service_name) + ')</span>' +
                ' <span class="badge badge-' + (m.status === 'Called' ? 'called' : 'serving') + '">' + escapeHtml(m.status) + '</span></div>' +
                '<div class="queue-actions" style="margin-top:8px;">' + actions + '</div></div>';
        }).join('');
        box.innerHTML = rows;
    }
}

function renderDashNext(n) {
    const box = document.getElementById('dashNext');
    if (!box) return;
    if (!n) {
        box.innerHTML = '<div class="next-number">---</div><div style="margin-top:4px;opacity:0.8;">No patients assigned to you currently waiting.</div>';
        return;
    }
    box.innerHTML =
        '<div class="next-number">' + escapeHtml(n.queue_number) + '</div>' +
        '<div style="margin-top:4px;">' + escapeHtml(n.first_name + ' ' + n.last_name) + '</div>';
}

function refreshDashboardQueue() {
    fetch('/MediQueue2/api/doctor_queue_state.php?all=1')
        .then(r => r.json())
        .then(data => {
            if (!data || data.error) return;
            const mine = (data.active || []).filter(a => a.is_mine);
            renderDashMyCurrent(mine);
            renderDashNext(data.my_next);
            const wc = document.getElementById('dashWaitingCount');
            if (wc) wc.textContent = data.my_waiting_count;
            const btn = document.getElementById('dashCallNext');
            if (btn && data.primary_service_id) {
                btn.onclick = function () { callNextPatient(data.primary_service_id); };
            }
        })
        .catch(() => {});
}

document.addEventListener('DOMContentLoaded', () => {
    refreshDashboardQueue();
    refreshDeptActivity();
    dashPollInterval = setInterval(refreshDashboardQueue, 5000);
    setInterval(refreshDeptActivity, 8000);
});

const myServiceIds = <?php echo json_encode($serviceIds); ?>;
const deptPollInterval = null;

function escDept(s) {
    return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

function refreshDeptActivity() {
    fetch('/MediQueue2/api/display_state.php')
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('deptLiveActivity');
            if (!el) return;
            const svcs = data.services || [];
            if (!svcs.length) {
                el.innerHTML = '<div style="padding:12px;color:#94a3b8;">No patients in any queue right now.</div>';
                return;
            }
            el.innerHTML = '<table class="table" style="margin:0;"><thead><tr>' +
                '<th>Service</th><th>Now Serving</th><th>Next</th><th>Waiting</th><th>Emergency</th>' +
                '</tr></thead><tbody>' + svcs.map(s => {
                    const mine = myServiceIds.indexOf(s.service_id) !== -1;
                    const bg = mine ? 'background:rgba(37,99,235,0.06);' : '';
                    const emColor = s.emergency_waiting > 0 ? 'color:#dc2626;font-weight:800;' : '';
                    return '<tr style="' + bg + '">' +
                        '<td><strong>' + escDept(s.service_name) + '</strong>' + (mine ? ' <span class="badge badge-primary" style="font-size:0.65rem;">My Dept</span>' : '') + '</td>' +
                        '<td>' + (s.currently_serving ? '<strong style="color:#f59e0b;">' + escDept(s.currently_serving.queue_number) + '</strong> <small style="color:#94a3b8;">' + escDept(s.currently_serving.patient_name) + '</small>' : '<span style="opacity:0.3;">—</span>') + '</td>' +
                        '<td>' + (s.next_patient ? '<strong>' + escDept(s.next_patient.queue_number) + '</strong> ' + (s.next_patient.priority !== 'Normal' ? '<span style="color:#dc2626;font-weight:700;">⚑</span>' : '') : '—') + '</td>' +
                        '<td><strong>' + s.waiting_count + '</strong></td>' +
                        '<td style="' + emColor + '">' + (s.emergency_waiting > 0 ? '🚨 ' + s.emergency_waiting : '—') + '</td>' +
                        '</tr>';
                }).join('') + '</tbody></table>';
        })
        .catch(() => {});
}
</script>

<?php require_once '../includes/footer.php'; ?>
