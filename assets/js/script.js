/**
 * MediQueue - Main JavaScript
 */

// ============================================================
// Sidebar Toggle (Mobile)
// ============================================================
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');

if (menuToggle) {
    menuToggle.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        sidebarOverlay.classList.toggle('show');
    });
}

if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', () => {
        sidebar.classList.remove('open');
        sidebarOverlay.classList.remove('show');
    });
}

// ============================================================
// User Menu Dropdown
// ============================================================
function toggleUserMenu() {
    const dropdown = document.getElementById('userDropdown');
    dropdown.classList.toggle('show');
}

document.addEventListener('click', (e) => {
    const userMenuBtn = document.getElementById('userMenuBtn');
    const dropdown = document.getElementById('userDropdown');
    if (dropdown && userMenuBtn && !userMenuBtn.contains(e.target)) {
        dropdown.classList.remove('show');
    }
});

// ============================================================
// Notification Panel
// ============================================================
function toggleNotifications() {
    const panel = document.getElementById('notificationPanel');
    panel.classList.toggle('show');
    if (panel.classList.contains('show')) {
        loadNotifications();
    }
}

function loadNotifications() {
    const list = document.getElementById('notificationList');
    fetch('/MediQueue2/api/notifications.php')
        .then(r => r.json())
        .then(data => {
            if (data.notifications && data.notifications.length > 0) {
                list.innerHTML = data.notifications.map(n => `
                    <div class="notification-item ${n.is_read == 0 ? 'unread' : ''}" onclick="markNotificationRead(${n.id})">
                        <div class="notification-icon ${n.type}">${getNotificationIcon(n.type)}</div>
                        <div class="notification-content">
                            <div class="title">${escapeHtml(n.title)}</div>
                            <div class="message">${escapeHtml(n.message)}</div>
                            <div class="time">${formatTime(n.created_at)}</div>
                        </div>
                    </div>
                `).join('');
            } else {
                list.innerHTML = '<div class="empty-state"><div class="icon">&#128276;</div><h3>No notifications</h3><p>You\'re all caught up!</p></div>';
            }
        })
        .catch(() => {
            list.innerHTML = '<div class="empty-state"><p>Unable to load notifications</p></div>';
        });
}

function markNotificationRead(id) {
    fetch('/MediQueue2/api/notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=mark_read&id=' + id
    }).then(() => {
        loadNotifications();
        refreshNotificationBadge();
    });
}

function refreshNotificationBadge() {
    fetch('/MediQueue2/api/notification_count.php')
        .then(r => r.json())
        .then(d => {
            const el = document.getElementById('notifCount');
            if (el) el.textContent = (d && d.count) ? d.count : 0;
        })
        .catch(() => {});
}

let notifLastCount = -1;

function pollNotifications() {
    const el = document.getElementById('notifCount');
    if (!el) return;
    fetch('/MediQueue2/api/notification_count.php')
        .then(r => r.json())
        .then(d => {
            const c = (d && d.count) ? d.count : 0;
            if (el) el.textContent = c;
            if (notifLastCount >= 0 && c > notifLastCount) {
                loadNotifications();
                fetch('/MediQueue2/api/notifications.php')
                    .then(r => r.json())
                    .then(nd => {
                        if (nd.notifications && nd.notifications.length) showToast(nd.notifications[0]);
                    })
                    .catch(() => {});
            }
            notifLastCount = c;
        })
        .catch(() => {});
}

function showToast(n) {
    const wrap = document.getElementById('toastContainer');
    if (!wrap) return;
    const t = document.createElement('div');
    t.className = 'toast notification-toast';
    t.innerHTML =
        '<div class="toast-icon">' + getNotificationIcon(n.type) + '</div>' +
        '<div class="toast-body">' +
        '<div class="toast-title">' + escapeHtml(n.title) + '</div>' +
        '<div class="toast-msg">' + escapeHtml(n.message) + '</div>' +
        '</div>' +
        '<button class="toast-close" title="Dismiss">&times;</button>';
    t.querySelector('.toast-close').onclick = function (e) {
        e.stopPropagation();
        t.classList.remove('show');
        setTimeout(function () { t.remove(); }, 350);
    };
    t.onclick = function () {
        t.classList.remove('show');
        setTimeout(function () { t.remove(); }, 350);
        toggleNotifications();
    };
    wrap.appendChild(t);
    setTimeout(function () { t.classList.add('show'); }, 30);
    setTimeout(function () {
        t.classList.remove('show');
        setTimeout(function () { t.remove(); }, 350);
    }, 9000);
}

(function initNotificationPolling() {
    const el = document.getElementById('notifCount');
    if (el) {
        notifLastCount = parseInt(el.textContent, 10) || 0;
        setInterval(pollNotifications, 10000);
    }
})();

function getNotificationIcon(type) {
    const icons = { appointment: '&#128197;', queue: '&#9201;', system: '&#9881;', reminder: '&#128276;' };
    return icons[type] || '&#128172;';
}

function formatTime(dateStr) {
    const date = new Date(dateStr);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
    return date.toLocaleDateString();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============================================================
// Queue Status Polling
// ============================================================
let queuePollInterval = null;

function startQueuePolling(queueEntryId, callback) {
    if (queuePollInterval) clearInterval(queuePollInterval);
    queuePollInterval = setInterval(() => {
        fetch('/MediQueue2/api/queue_status.php?id=' + queueEntryId)
            .then(r => r.json())
            .then(data => {
                if (callback) callback(data);
                updateQueueDisplay(data);
            });
    }, 5000);
}

function updateQueueDisplay(data) {
    if (!data || data.error) return;

    const servingEl = document.getElementById('currentServing');
    const peopleAheadEl = document.getElementById('peopleAhead');
    const waitTimeEl = document.getElementById('estimatedWait');
    const statusEl = document.getElementById('queueStatus');
    const waitingCountEl = document.getElementById('waitingCount');
    const servedCountEl = document.getElementById('servedCount');

    if (servingEl) servingEl.textContent = data.currently_serving || '--';
    if (peopleAheadEl) peopleAheadEl.textContent = data.people_ahead ?? '--';
    if (waitTimeEl) waitTimeEl.textContent = (data.estimated_wait || 0) + ' min';
    if (statusEl) {
        statusEl.textContent = data.my_status || '--';
        statusEl.className = 'queue-status-big badge-' + (data.my_status || '').toLowerCase();
    }
    if (waitingCountEl) waitingCountEl.textContent = data.waiting_count ?? '--';
    if (servedCountEl) servedCountEl.textContent = data.served_count ?? '--';
}

function stopQueuePolling() {
    if (queuePollInterval) {
        clearInterval(queuePollInterval);
        queuePollInterval = null;
    }
}

// ============================================================
// Staff Queue Management Polling
// ============================================================
let staffQueuePollInterval = null;

function startStaffQueuePolling(serviceId, callback) {
    if (staffQueuePollInterval) clearInterval(staffQueuePollInterval);
    const poll = () => {
        fetch('/MediQueue2/api/queue_list.php?service_id=' + serviceId)
            .then(r => r.json())
            .then(data => {
                if (callback) callback(data);
                updateStaffQueueDisplay(data);
            });
    };
    poll();
    staffQueuePollInterval = setInterval(poll, 5000);
}

function renderStaffCurrent(current) {
    const box = document.getElementById('staffCurrentCardBody');
    if (!box) return;
    if (!current) {
        box.innerHTML = '<div class="current-number">---</div>';
        return;
    }
    const badgeClass = current.status === 'Called' ? 'badge-called' : 'badge-serving';
    const badgeLabel = current.status === 'Called' ? 'Called \u2014 please start' : current.status;
    let actions = '';
    if (current.status === 'Called') {
        actions += '<button onclick="startService(' + current.id + ', ' + current.service_id + ')" class="btn btn-success btn-sm">Start Service</button>';
    } else if (current.status === 'Serving') {
        actions += '<button onclick="markServed(' + current.id + ', ' + current.service_id + ')" class="btn btn-success btn-sm">Mark Served</button>';
    }
    actions += '<button onclick="skipPatient(' + current.id + ', ' + current.service_id + ')" class="btn btn-warning btn-sm">Skip</button>';
    box.innerHTML =
        '<div class="current-number">' + escapeHtml(current.queue_number) + '</div>' +
        '<div style="margin-top:8px;opacity:0.9;">' + escapeHtml(current.first_name + ' ' + current.last_name) + '</div>' +
        '<div style="margin-top:4px;"><span class="badge ' + badgeClass + '">' + escapeHtml(badgeLabel) + '</span></div>' +
        '<div class="queue-actions">' + actions + '</div>';
}

function updateStaffQueueDisplay(data) {
    if (!data || data.error) return;

    const nextEl = document.getElementById('staffNextPatient');
    const waitingEl = document.getElementById('staffWaitingList');

    if (nextEl) nextEl.textContent = data.next_patient || '---';

    renderStaffCurrent(Array.isArray(data.active) && data.active.length ? data.active[0] : null);

    const waitCount = document.getElementById('staffWaitingCount');
    if (waitCount && Array.isArray(data.waiting)) waitCount.textContent = data.waiting.length;
    const servingCount = document.getElementById('staffServingCount');
    if (servingCount) servingCount.textContent = data.serving_count || 0;
    const servedCount = document.getElementById('staffServedCount');
    if (servedCount) servedCount.textContent = data.served_count || 0;

    if (waitingEl && data.waiting) {
        if (data.waiting.length > 0) {
            const targets = data.forward_targets || [];
            waitingEl.innerHTML = data.waiting.map(w => {
                const priorityClass = (w.priority && w.priority !== 'Normal') ? ' waiting-item-priority' : '';
                const pBadge = w.priority && w.priority !== 'Normal'
                    ? `<span class="badge badge-priority-${w.priority.toLowerCase()}">${w.priority === 'Emergency' ? '\u26A0\u26A0 EMERGENCY' : '\u26A0 URGENT'}</span>`
                    : '';
                const providerBadge = w.doctor_name
                    ? `<span class="badge badge-info" style="font-size:0.75rem;">${escapeHtml(w.doctor_name)}</span>`
                    : '';
                const forwardBar = targets.length
                    ? `<div class="forward-bar">
                           <select class="form-control fwd-select" data-entry="${w.id}" aria-label="Forward patient">
                               <option value="">Forward to&hellip;</option>
                               ${targets.map(t => `<option value="${t.id}">${escapeHtml(t.label)}</option>`).join('')}
                           </select>
                       </div>`
                    : '';
                const releaseBar = '';
                return `
                <div class="waiting-item${priorityClass}">
                    <span class="queue-num">${escapeHtml(w.queue_number)}</span>
                    <span class="patient-name">${escapeHtml(w.first_name + ' ' + w.last_name)}</span>
                    ${providerBadge}
                    ${pBadge}
                    <span class="service-name">${escapeHtml(w.service_name)}</span>
                    ${(w.est_minutes > 0) ? `<span class="service-name">~${w.est_minutes} min</span>` : ''}
                    ${forwardBar}
                    ${releaseBar}
                </div>
            `;
            }).join('');
        } else {
            waitingEl.innerHTML = '<div class="empty-state"><p>No patients waiting</p></div>';
        }
    }
}

function stopStaffQueuePolling() {
    if (staffQueuePollInterval) {
        clearInterval(staffQueuePollInterval);
        staffQueuePollInterval = null;
    }
}

function afterQueueAction(serviceId) {
    if (typeof window.refreshDoctorQueue === 'function') {
        window.refreshDoctorQueue(serviceId);
    } else if (window.location.pathname.indexOf('/staff/') !== -1) {
        startStaffQueuePolling(serviceId);
    } else {
        setTimeout(() => window.location.reload(), 1200);
    }
}

// ============================================================
// Queue Actions
// ============================================================
function callNextPatient(serviceId) {
    if (!confirm('Call the next patient in queue?')) return;
    fetch('/MediQueue2/api/queue_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=call_next&service_id=' + serviceId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message || 'Next patient called');
            if (typeof window.showQueueCallNextTick === 'function') window.showQueueCallNextTick();
            setTimeout(() => afterQueueAction(serviceId), 600);
        } else {
            showAlert('danger', data.message || 'No patients waiting');
        }
    })
    .catch(() => showAlert('danger', 'Network error'));
}

function startService(entryId, serviceId) {
    fetch('/MediQueue2/api/queue_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=start_service&id=' + entryId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showAlert('success', 'Service started');
            if (typeof window.showQueueActionTick === 'function') window.showQueueActionTick(entryId, 'Started');
            setTimeout(() => afterQueueAction(serviceId), 600);
        } else {
            showAlert('danger', data.message || 'Could not start service');
        }
    });
}

function markServed(entryId, serviceId) {
    if (!confirm('Mark this patient as served?')) return;
    fetch('/MediQueue2/api/queue_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=mark_served&id=' + entryId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showAlert('success', 'Patient marked as served');
            if (typeof window.showQueueActionTick === 'function') window.showQueueActionTick(entryId, 'Served');
            setTimeout(() => afterQueueAction(serviceId), 600);
        } else {
            showAlert('danger', data.message || 'Could not mark as served');
        }
    });
}

function skipPatient(entryId, serviceId) {
    if (!confirm('Skip this patient?')) return;
    fetch('/MediQueue2/api/queue_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=skip&id=' + entryId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showAlert('warning', 'Patient skipped');
            if (typeof window.showQueueActionTick === 'function') window.showQueueActionTick(entryId, 'Skipped');
            setTimeout(() => afterQueueAction(serviceId), 600);
        } else {
            showAlert('danger', data.message || 'Could not skip patient');
        }
    });
}

function leaveQueue(entryId) {
    if (!confirm('Are you sure you want to leave the queue?')) return;
    fetch('/MediQueue2/api/queue_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=leave&id=' + entryId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showAlert('success', 'You have left the queue');
            stopQueuePolling();
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert('danger', data.message || 'Could not leave queue');
        }
    });
}

// ============================================================
// Filter doctor dropdown by selected service
// ============================================================
function filterDoctorsByService() {
    const serviceSel = document.getElementById('service_id');
    const doctorSel = document.getElementById('doctor_id');
    if (!serviceSel || !doctorSel || serviceSel.getAttribute('data-filter-doctors') === null) return;

    const svc = serviceSel.value ? String(serviceSel.value) : '';
    let anyVisible = false;

    doctorSel.querySelectorAll('option').forEach(opt => {
        if (!opt.hasAttribute('data-services')) return;
        const ids = (opt.getAttribute('data-services') || '').split(',').filter(Boolean);
        const show = (svc === '' || ids.indexOf(svc) !== -1);
        opt.style.display = show ? '' : 'none';
        if (show && opt.value !== '') anyVisible = true;
    });

    doctorSel.querySelectorAll('option[data-nodoctor]').forEach(opt => {
        opt.style.display = (svc !== '' && !anyVisible) ? '' : 'none';
    });

    if (doctorSel.selectedIndex > -1 && doctorSel.options[doctorSel.selectedIndex].style.display === 'none') {
        doctorSel.value = '';
    }
    if (anyVisible && !doctorSel.value && doctorSel.getAttribute('data-auto-select') !== null) {
        doctorSel.querySelectorAll('option').forEach(opt => {
            if (opt.value !== '' && opt.style.display !== 'none') { doctorSel.value = opt.value; opt.selected = true; }
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const serviceSel = document.getElementById('service_id');
    if (serviceSel && serviceSel.getAttribute('data-filter-doctors') !== null) {
        serviceSel.addEventListener('change', filterDoctorsByService);
        filterDoctorsByService();
    }
});

// ============================================================
// Alert Helper
// ============================================================
function showAlert(type, message) {
    const existing = document.querySelector('.alert.flash-alert');
    if (existing) existing.remove();

    const alert = document.createElement('div');
    alert.className = `alert alert-${type} flash-alert`;
    alert.innerHTML = `${message} <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>`;
    document.querySelector('.page-content').prepend(alert);
    setTimeout(() => alert.remove(), 5000);
}

// ============================================================
// Auto-hide alerts
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.alert .close-btn').forEach(btn => {
        btn.addEventListener('click', () => btn.parentElement.remove());
    });
});
