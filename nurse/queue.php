<?php
$pageTitle = 'Queue';
require_once '../includes/header.php';
requireNurse();

$nurse = db()->fetch("SELECT * FROM doctors WHERE user_id = ?", [$_SESSION['user_id']]);
$nurseId = $nurse['id'];

$serviceId = intval($_GET['service_id'] ?? 0);
$services = db()->fetchAll(
    "SELECT s.* FROM services s JOIN doctor_services ds ON s.id = ds.service_id WHERE ds.doctor_id = ? AND s.is_active = 1",
    [$nurseId]
);

if (!$serviceId && !empty($services)) {
    $serviceId = $services[0]['id'];
}
?>

<div class="page-content">
    <div class="page-title">Queue</div>
    <div class="page-subtitle">Department queue for your service — see all patients, manage only yours.</div>

    <?php if (!empty($services)): ?>
    <div class="filters-bar mb-30">
        <?php foreach ($services as $s): ?>
            <a href="?service_id=<?php echo $s['id']; ?>" class="btn btn-sm <?php echo $serviceId == $s['id'] ? 'btn-primary' : 'btn-outline'; ?>"><?php echo escape($s['name']); ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($serviceId): ?>
    <div class="grid-2">
        <div>
            <div class="queue-current mb-20">
                <div class="current-label">My Current Patient</div>
                <div id="myCurrentBox">
                    <div class="empty-state"><p>Loading queue data...</p></div>
                </div>
            </div>

            <div class="queue-next mb-20">
                <div class="next-label">Next For You</div>
                <div id="myNextBox">
                    <div class="next-number">---</div>
                </div>
            </div>

            <button id="callNextBtn" onclick="callNextPatient(<?php echo $serviceId; ?>)" class="btn btn-primary btn-lg btn-block mb-20">Call Next Patient</button>

            <div class="card">
                <p style="color:var(--gray);font-size:0.9rem;">Served today (whole department): <strong id="servedCountSpan">0</strong></p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">Department Waiting List (<span id="deptWaitCount">0</span>)</div>
            </div>
            <div id="deptWaitingList">
                <div class="empty-state"><p>Loading queue data...</p></div>
            </div>
        </div>
    </div>

    <div class="card mt-30">
        <div class="card-header">
            <div class="card-title">In Consultation / Called — All Providers</div>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Patient</th>
                        <th>Status</th>
                        <th>Provider</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="activeTableBody"></tbody>
            </table>
        </div>
        <div id="activeEmpty" class="empty-state" style="display:none;"><p>No patients are currently being called or served.</p></div>
    </div>
    <?php else: ?>
        <div class="card">
            <div class="empty-state">
                <h3>No services assigned</h3>
                <p>Contact an administrator to assign services to your profile.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($serviceId): ?>
<script>
let currentServiceId = <?php echo $serviceId; ?>;
let nurseQueuePollInterval = null;

function queueBtn(id, serviceId, action, label, cls) {
    return '<button onclick="' + action + '(' + id + ', currentServiceId)" class="btn ' + cls + ' btn-sm">' + label + '</button>';
}

function renderMyCurrent(m) {
    const box = document.getElementById('myCurrentBox');
    if (!box) return;
    if (!m) {
        box.innerHTML = '<div class="empty-state"><p>No patient currently in your consultation.</p></div>';
        return;
    }
    const badgeClass = m.status === 'Called' ? 'badge-called' : 'badge-serving';
    const badgeLabel = m.status === 'Called' ? 'Called — please start' : m.status;
    let actions = '';
    if (m.status === 'Called') {
        actions += queueBtn(m.id, currentServiceId, 'startService', 'Start Consultation', 'btn-success');
    } else if (m.status === 'Serving') {
        actions += queueBtn(m.id, currentServiceId, 'markServed', 'Mark Served', 'btn-success');
    }
    actions += queueBtn(m.id, currentServiceId, 'skipPatient', 'Skip', 'btn-warning');
    const vitalsLine = m.vitals_json ? '<div class="vitals-line">' + escapeHtml(m.vitals_json) + '</div>' : '';
    box.innerHTML =
        '<div class="current-number">' + escapeHtml(m.queue_number) + '</div>' +
        '<div style="margin-top:6px;">' + escapeHtml(m.first_name + ' ' + m.last_name) + '</div>' +
        '<div style="margin-top:4px;">' + priorityBadgeHtml(m.priority) + ' <span class="badge ' + badgeClass + '">' + escapeHtml(badgeLabel) + '</span></div>' +
        vitalsLine +
        '<div class="queue-actions" id="myCurrentActions">' + actions + '</div>';
}

function renderMyNext(n) {
    const box = document.getElementById('myNextBox');
    if (!box) return;
    if (!n) {
        box.innerHTML = '<div class="next-number">---</div><div style="margin-top:4px;opacity:0.8;">No patients assigned to you currently waiting.</div>';
        return;
    }
    box.innerHTML =
        '<div class="next-number">' + escapeHtml(n.queue_number) + '</div>' +
        '<div style="margin-top:4px;">' + escapeHtml(n.first_name + ' ' + n.last_name) + '</div>' +
        '<div style="margin-top:4px;">' + priorityBadgeHtml(n.priority) + '</div>';
}

function priorityBadgeHtml(priority) {
    if (!priority || priority === 'Normal') return '';
    const label = priority === 'Emergency' ? '\u26A0 EMERGENCY' : '\u26A0 URGENT';
    return '<span class="badge badge-priority-' + String(priority).toLowerCase() + '">' + label + '</span>';
}

function renderWaiting(list) {
    const el = document.getElementById('deptWaitingList');
    const countEl = document.getElementById('deptWaitCount');
    if (countEl) countEl.textContent = list.length;
    if (!el) return;
    if (!list.length) {
        el.innerHTML = '<div class="empty-state"><p>No patients waiting.</p></div>';
        return;
    }
    el.innerHTML = list.map(function (w) {
        let badge = priorityBadgeHtml(w.priority) + ' ';
        if (w.is_mine) {
            badge += '<span class="badge badge-primary">Assigned to you</span>';
        } else if (w.doctor_name) {
            badge += '<span class="badge badge-info">' + escapeHtml(w.doctor_name) + '</span>';
        } else {
            badge += '<span class="badge">No preference</span>';
        }

        let forward = '';
        const targets = (window.forwardTargets || {})[w.service_id] || [];
        const canForward = (w.is_mine || !w.doctor_name);
        if (canForward && targets.length) {
            forward = '<div class="forward-bar">' +
                '<select class="form-control fwd-select" data-entry="' + w.id + '" aria-label="Forward patient">' +
                '<option value="">Forward to&hellip; (pick lightest load)</option>' +
                targets.map(function (t) {
                    return '<option value="' + t.id + '">' + escapeHtml(t.name) + '</option>';
                }).join('') +
                '</select></div>';
        }

        let release = '';
        if (w.is_mine && w.status === 'Waiting') {
            release = '<div class="forward-bar">' +
                '<button class="btn btn-sm btn-outline-danger release-btn" data-entry="' + w.id + '">&#8617; Release &mdash; too busy</button>' +
                '</div>';
        }

        const prioClass = (w.priority && w.priority !== 'Normal') ? ' waiting-item-priority' : '';

        return '<div class="waiting-item' + prioClass + '">' +
            '<span class="queue-num">' + escapeHtml(w.queue_number) + '</span>' +
            '<span class="patient-name">' + escapeHtml(w.first_name + ' ' + w.last_name) + '</span>' +
            badge + forward + release + '</div>';
    }).join('');
    bindForwardSelection();
    bindReleaseButtons();
}

function bindForwardSelection() {
    document.querySelectorAll('.fwd-select').forEach(function (sel) {
        sel.onchange = function () {
            const entryId = sel.getAttribute('data-entry');
            const targetId = sel.value;
            sel.value = '';
            if (!targetId) return;
            const opt = sel.querySelector('option[value="' + targetId + '"]');
            const who = opt ? opt.textContent.trim() : 'the selected provider';
            if (!confirm('Forward this patient to ' + who + '? The patient will join their queue from the back.')) return;
            forwardPatient(entryId, targetId, currentServiceId);
        };
    });
}

function bindReleaseButtons() {
    document.querySelectorAll('.release-btn').forEach(function (btn) {
        btn.onclick = function () {
            const entryId = btn.getAttribute('data-entry');
            if (!confirm('Release this patient back to the pool so the first available provider handles them? They will re-join the queue from the back.')) return;
            fetch('/MediQueue2/api/queue_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=release&id=' + entryId
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    setTimeout(() => afterQueueAction(currentServiceId), 600);
                } else {
                    showAlert('danger', data.message || 'Could not release patient');
                }
            });
        };
    });
}

function forwardPatient(entryId, targetId, serviceId) {
    fetch('/MediQueue2/api/queue_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=forward&id=' + entryId + '&target_doctor_id=' + targetId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            showQueueActionTick(entryId, 'Forwarded');
            setTimeout(() => afterQueueAction(serviceId), 600);
        } else {
            showAlert('danger', data.message || 'Could not forward patient');
        }
    });
}

function renderActive(list) {
    const tbody = document.getElementById('activeTableBody');
    const emptyEl = document.getElementById('activeEmpty');
    if (!tbody) return;
    if (!list.length) {
        tbody.innerHTML = '';
        if (emptyEl) emptyEl.style.display = '';
        return;
    }
    if (emptyEl) emptyEl.style.display = 'none';
    tbody.innerHTML = list.map(function (a) {
        let provider;
        if (a.is_mine) {
            provider = '<span class="badge badge-primary">You</span>';
        } else if (a.doctor_name) {
            provider = escapeHtml(a.doctor_name);
        } else {
            provider = '<span class="badge">Not assigned</span>';
        }
        let actions;
        if (a.is_mine) {
            actions = '';
            if (a.status === 'Called') {
                actions += queueBtn(a.id, currentServiceId, 'startService', 'Start', 'btn-success');
            } else if (a.status === 'Serving') {
                actions += queueBtn(a.id, currentServiceId, 'markServed', 'Mark Served', 'btn-success');
            }
            actions += queueBtn(a.id, currentServiceId, 'skipPatient', 'Skip', 'btn-warning');
        } else if (a.can_claim) {
            actions = queueBtn(a.id, currentServiceId, 'startService', 'Claim & Start', 'btn-success');
        } else {
            actions = '<span style="color:#777;font-size:0.85rem;">Managed by provider</span>';
        }
        return '<tr>' +
            '<td>' + escapeHtml(a.queue_number) + '<br>' + priorityBadgeHtml(a.priority) + '</td>' +
            '<td>' + escapeHtml(a.first_name + ' ' + a.last_name) + '</td>' +
            '<td><span class="badge badge-' + (a.status === 'Called' ? 'called' : 'serving') + '">' + escapeHtml(a.status) + '</span></td>' +
            '<td>' + provider + '</td>' +
            '<td id="actCell-' + a.id + '">' + actions + '</td>' +
            '</tr>';
    }).join('');
}

function renderNurseQueueState(data) {
    if (!data || data.error) return;
    window.forwardTargets = data.forward_targets || {};
    const btn = document.getElementById('callNextBtn');
    if (btn) btn.innerHTML = 'Call Next Patient';
    const served = document.getElementById('servedCountSpan');
    if (served) served.textContent = data.served_count;
    renderMyCurrent(data.my_current);
    renderMyNext(data.my_next);
    renderWaiting(data.waiting);
    renderActive(data.active);
}

function refreshNurseQueue(serviceId) {
    fetch('/MediQueue2/api/doctor_queue_state.php?service_id=' + serviceId)
        .then(r => r.json())
        .then(data => {
            if (data.error) throw new Error(data.error);
            renderNurseQueueState(data);
        })
        .catch(() => {
            const box = document.getElementById('myCurrentBox');
            if (box) box.innerHTML = '<div class="empty-state"><p>Could not load queue data. Please refresh the page.</p></div>';
        });
}

function startNurseQueuePolling(serviceId) {
    if (nurseQueuePollInterval) clearInterval(nurseQueuePollInterval);
    nurseQueuePollInterval = setInterval(() => refreshNurseQueue(serviceId), 5000);
}

function showQueueActionTick(entryId, label) {
    const cell = document.getElementById('actCell-' + entryId);
    if (cell) {
        cell.innerHTML = '<span class="action-tick">&#10004;</span> <small style="color:#28a745;">' + escapeHtml(label) + '</small>';
    } else {
        const fwd = document.querySelector('.fwd-select[data-entry="' + entryId + '"]');
        if (fwd) {
            fwd.outerHTML = '<span class="action-tick">&#10004;</span> <small style="color:#28a745;">' + escapeHtml(label) + '</small>';
        } else {
            const cur = document.getElementById('myCurrentActions');
            if (cur) cur.innerHTML = '<span class="action-tick">&#10004;</span> <small style="color:#28a745;">' + escapeHtml(label) + '</small>';
        }
    }
}

function showQueueCallNextTick() {
    const btn = document.getElementById('callNextBtn');
    if (btn) btn.innerHTML = '<span class="action-tick">&#10004;</span> Called';
}

document.addEventListener('DOMContentLoaded', () => {
    refreshNurseQueue(currentServiceId);
    startNurseQueuePolling(currentServiceId);
});
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
