<?php
$pageTitle = 'Reports & Analytics';
require_once '../includes/header.php';
require_once '../includes/auth.php';
requireAdmin();

$period = $_GET['period'] ?? 'today';
$customFrom = $_GET['from'] ?? '';
$customTo = $_GET['to'] ?? '';
$weekStartInput = $_GET['week_start'] ?? '';

$dateFrom = date('Y-m-d');
$dateTo = date('Y-m-d');

if ($period === 'today') {
    $dateFrom = date('Y-m-d');
    $dateTo = date('Y-m-d');
} elseif ($period === 'week') {
    $dateFrom = date('Y-m-d', strtotime('monday this week'));
    $dateTo = date('Y-m-d');
} elseif ($period === 'last_week') {
    $dateFrom = date('Y-m-d', strtotime('monday last week'));
    $dateTo = date('Y-m-d', strtotime('sunday last week'));
} elseif ($period === 'week_start' && $weekStartInput) {
    $ws = new DateTime($weekStartInput);
    $ws->modify('monday this week');
    $dateFrom = $ws->format('Y-m-d');
    $dateTo = (clone $ws)->modify('+6 days')->format('Y-m-d');
} elseif ($period === 'month') {
    $dateFrom = date('Y-m-d', strtotime('first day of this month'));
    $dateTo = date('Y-m-d');
} elseif ($period === 'last_30') {
    $dateFrom = date('Y-m-d', strtotime('-29 days'));
    $dateTo = date('Y-m-d');
} elseif ($period === 'last_month') {
    $dateFrom = date('Y-m-d', strtotime('first day of last month'));
    $dateTo = date('Y-m-d', strtotime('last day of last month'));
} elseif ($period === 'custom' && $customFrom && $customTo) {
    $dateFrom = $customFrom;
    $dateTo = $customTo;
} else {
    $period = 'today';
}

$patientsServed = db()->fetch(
    "SELECT COUNT(DISTINCT q.patient_id) as count FROM queue_entries q WHERE q.queue_date BETWEEN ? AND ? AND q.status = 'Served'",
    [$dateFrom, $dateTo]
)['count'];

$totalAppointments = db()->fetch(
    "SELECT COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN ? AND ?",
    [$dateFrom, $dateTo]
)['count'];

$completedAppointments = db()->fetch(
    "SELECT COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status = 'Completed'",
    [$dateFrom, $dateTo]
)['count'];

$cancelledAppointments = db()->fetch(
    "SELECT COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status = 'Cancelled'",
    [$dateFrom, $dateTo]
)['count'];

$avgWaitTime = db()->fetch(
    "SELECT AVG(TIMESTAMPDIFF(MINUTE, joined_at, COALESCE(called_at, serving_at))) as avg_min
     FROM queue_entries
     WHERE queue_date BETWEEN ? AND ? AND status IN ('Served','Skipped') AND called_at IS NOT NULL",
    [$dateFrom, $dateTo]
)['avg_min'];

$totalQueueEntries = db()->fetch(
    "SELECT COUNT(*) as count FROM queue_entries WHERE queue_date BETWEEN ? AND ?",
    [$dateFrom, $dateTo]
)['count'];

$servedQueue = db()->fetch(
    "SELECT COUNT(*) as count FROM queue_entries WHERE queue_date BETWEEN ? AND ? AND status = 'Served'",
    [$dateFrom, $dateTo]
)['count'];

$skippedQueue = db()->fetch(
    "SELECT COUNT(*) as count FROM queue_entries WHERE queue_date BETWEEN ? AND ? AND status = 'Skipped'",
    [$dateFrom, $dateTo]
)['count'];

$pendingAppts = db()->fetch(
    "SELECT COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status = 'Pending'",
    [$dateFrom, $dateTo]
)['count'];

$confirmedAppts = db()->fetch(
    "SELECT COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status = 'Confirmed'",
    [$dateFrom, $dateTo]
)['count'];

$noShowAppts = db()->fetch(
    "SELECT COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status = 'No Show'",
    [$dateFrom, $dateTo]
)['count'];

$serviceStats = db()->fetchAll(
    "SELECT s.name, COUNT(q.id) as total, SUM(q.status = 'Served') as served, SUM(q.status = 'Skipped') as skipped
     FROM queue_entries q
     JOIN services s ON q.service_id = s.id
     WHERE q.queue_date BETWEEN ? AND ?
     GROUP BY s.id, s.name
     ORDER BY total DESC",
    [$dateFrom, $dateTo]
);

$doctorStats = db()->fetchAll(
    "SELECT CONCAT(d.first_name, ' ', d.last_name) as name, COUNT(a.id) as total,
            SUM(a.status = 'Completed') as completed, SUM(a.status = 'Cancelled') as cancelled
     FROM appointments a
     JOIN doctors d ON a.doctor_id = d.id
     WHERE a.appointment_date BETWEEN ? AND ?
     GROUP BY d.id, d.first_name, d.last_name
     ORDER BY total DESC LIMIT 10",
    [$dateFrom, $dateTo]
);

$providerSearch = trim($_GET['provider'] ?? '');
$providerWhere = '';
$providerParams = [$dateFrom, $dateTo, $dateFrom, $dateTo];
if ($providerSearch) {
    $providerWhere = " AND (d.first_name LIKE ? OR d.last_name LIKE ? OR CONCAT(d.first_name, ' ', d.last_name) LIKE ?)";
    $sp = "%$providerSearch%";
    $providerParams = array_merge($providerParams, [$sp, $sp, $sp]);
}
$providerStats = db()->fetchAll(
    "SELECT d.id, d.first_name, d.last_name, d.type, u.is_active,
            COALESCE(q.served, 0) AS served_queue, COALESCE(a.completed, 0) AS completed_appts,
            COALESCE(q.served, 0) + COALESCE(a.completed, 0) AS total
     FROM doctors d
     JOIN users u ON d.user_id = u.id
     LEFT JOIN (
         SELECT doctor_id, COUNT(*) AS served FROM queue_entries
         WHERE status = 'Served' AND queue_date BETWEEN ? AND ? AND doctor_id IS NOT NULL
         GROUP BY doctor_id
     ) q ON q.doctor_id = d.id
     LEFT JOIN (
         SELECT doctor_id, COUNT(*) AS completed FROM appointments
         WHERE status = 'Completed' AND appointment_date BETWEEN ? AND ?
         GROUP BY doctor_id
     ) a ON a.doctor_id = d.id
     WHERE u.role IN ('doctor', 'nurse') $providerWhere
     ORDER BY total DESC, d.type, d.first_name
     LIMIT 20",
    $providerParams
);

$maxProviderActivity = 0;
foreach ($providerStats as $ds) {
    $maxProviderActivity = max($maxProviderActivity, (int) $ds['total']);
}

$dailyAppts = db()->fetchAll(
    "SELECT appointment_date AS d, COUNT(*) AS total,
            SUM(status = 'Completed') AS completed, SUM(status = 'Pending') AS pending,
            SUM(status = 'Confirmed') AS confirmed, SUM(status = 'Cancelled') AS cancelled
     FROM appointments
     WHERE appointment_date BETWEEN ? AND ?
     GROUP BY appointment_date",
    [$dateFrom, $dateTo]
);

$dailyQueue = db()->fetchAll(
    "SELECT queue_date AS d, COUNT(*) AS total, SUM(status = 'Served') AS served,
            ROUND(AVG(TIMESTAMPDIFF(MINUTE, joined_at, COALESCE(called_at, serving_at))), 1) AS avg_wait
     FROM queue_entries
     WHERE queue_date BETWEEN ? AND ?
     GROUP BY queue_date",
    [$dateFrom, $dateTo]
);

$dailyRegs = db()->fetchAll(
    "SELECT DATE(created_at) AS d, COUNT(*) AS total
     FROM users
     WHERE role = 'patient' AND DATE(created_at) BETWEEN ? AND ?
     GROUP BY DATE(created_at)",
    [$dateFrom, $dateTo]
);

$dailyMap = [];
foreach ($dailyAppts as $r) { $dailyMap[$r['d']]['appts'] = $r; }
foreach ($dailyQueue as $r) { $dailyMap[$r['d']]['queue'] = $r; }
foreach ($dailyRegs as $r) { $dailyMap[$r['d']]['regs'] = $r; }

$dayCursor = new DateTime($dateFrom);
$dayEnd = (new DateTime($dateTo))->modify('+1 day');
$dailyRows = [];
while ($dayCursor < $dayEnd) {
    $k = $dayCursor->format('Y-m-d');
    $dailyRows[] = [
        'date' => $k,
        'regs' => $dailyMap[$k]['regs']['total'] ?? 0,
        'appts_total' => $dailyMap[$k]['appts']['total'] ?? 0,
        'appts_completed' => $dailyMap[$k]['appts']['completed'] ?? 0,
        'appts_pending' => $dailyMap[$k]['appts']['pending'] ?? 0,
        'appts_confirmed' => $dailyMap[$k]['appts']['confirmed'] ?? 0,
        'appts_cancelled' => $dailyMap[$k]['appts']['cancelled'] ?? 0,
        'queue_total' => $dailyMap[$k]['queue']['total'] ?? 0,
        'queue_served' => $dailyMap[$k]['queue']['served'] ?? 0,
        'queue_avg_wait' => $dailyMap[$k]['queue']['avg_wait'] ?? 0,
    ];
    $dayCursor->modify('+1 day');
}

$workDate = $_GET['work_date'] ?? date('Y-m-d');

$staffSummary = db()->fetchAll(
    "SELECT al.user_id, u.email, u.role,
            COALESCE(
                NULLIF(CONCAT(st.first_name, ' ', st.last_name), ' '),
                NULLIF(CONCAT(dr.first_name, ' ', dr.last_name), ' '),
                NULL
            ) AS full_name,
            COUNT(*) AS actions
     FROM audit_logs al
     JOIN users u ON al.user_id = u.id
     LEFT JOIN staff st ON st.user_id = u.id
     LEFT JOIN doctors dr ON dr.user_id = u.id
     WHERE DATE(al.created_at) = ?
     GROUP BY al.user_id, u.email, u.role
     ORDER BY actions DESC",
    [$workDate]
);

$workLogs = db()->fetchAll(
    "SELECT al.*, u.email
     FROM audit_logs al
     JOIN users u ON al.user_id = u.id
     WHERE DATE(al.created_at) = ?
     ORDER BY al.created_at DESC LIMIT 300",
    [$workDate]
);
?>

<div class="page-content">
    <div class="page-title">Reports & Analytics</div>
    <div class="page-subtitle">Performance metrics and statistics.</div>

    <div class="card mb-30">
        <form method="GET" class="filters-bar" id="reportFilters">
            <select name="period" class="form-control" onchange="document.getElementById('customDates').style.display = this.value === 'custom' ? 'flex' : 'none'; document.getElementById('weekDates').style.display = this.value === 'week_start' ? 'flex' : 'none';">
                <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Today</option>
                <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>This Week</option>
                <option value="last_week" <?php echo $period === 'last_week' ? 'selected' : ''; ?>>Last Week</option>
                <option value="week_start" <?php echo $period === 'week_start' ? 'selected' : ''; ?>>Select Week</option>
                <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>This Month</option>
                <option value="last_month" <?php echo $period === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                <option value="last_30" <?php echo $period === 'last_30' ? 'selected' : ''; ?>>Last 30 Days</option>
                <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
            </select>
            <div id="weekDates" class="d-flex gap-10" style="display:<?php echo $period === 'week_start' ? 'flex' : 'none'; ?> !important;">
                <input type="date" name="week_start" class="form-control" value="<?php echo escape($weekStartInput ?: date('Y-m-d', strtotime('monday this week'))); ?>">
            </div>
            <div id="customDates" class="d-flex gap-10" style="display:<?php echo $period === 'custom' ? 'flex' : 'none'; ?> !important;">
                <input type="date" name="from" class="form-control" value="<?php echo escape($customFrom ?: date('Y-m-d')); ?>">
                <span style="line-height:36px;">to</span>
                <input type="date" name="to" class="form-control" value="<?php echo escape($customTo ?: date('Y-m-d')); ?>">
            </div>
            <input type="text" name="provider" class="form-control" placeholder="Filter provider (name)" value="<?php echo escape($providerSearch); ?>">
            <button type="submit" class="btn btn-primary btn-sm">Generate</button>
        </form>
    </div>

    <p style="color:var(--gray);margin-bottom:20px;">Showing data from <strong><?php echo date('M d, Y', strtotime($dateFrom)); ?></strong> to <strong><?php echo date('M d, Y', strtotime($dateTo)); ?></strong></p>

    <div class="stats-grid mb-30">
        <div class="stat-card green">
            <div class="stat-icon green">&#10004;</div>
            <div class="stat-info">
                <h3><?php echo $patientsServed; ?></h3>
                <p>Patients Served</p>
            </div>
        </div>
        <div class="stat-card blue">
            <div class="stat-icon blue">&#128197;</div>
            <div class="stat-info">
                <h3><?php echo $totalAppointments; ?></h3>
                <p>Total Appointments</p>
            </div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon orange">&#9201;</div>
            <div class="stat-info">
                <h3><?php echo round($avgWaitTime ?? 0, 1); ?> min</h3>
                <p>Avg Wait Time</p>
            </div>
        </div>
        <div class="stat-card teal">
            <div class="stat-icon teal">&#128203;</div>
            <div class="stat-info">
                <h3><?php echo $totalQueueEntries; ?></h3>
                <p>Total Queue Entries</p>
            </div>
        </div>
    </div>

    <div class="grid-2 mb-30">
        <div class="card">
            <div class="card-header">
                <div class="card-title">Appointment Breakdown</div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Status</th><th>Count</th></tr></thead>
                    <tbody>
                        <tr><td>Pending</td><td><strong><?php echo $pendingAppts; ?></strong></td></tr>
                        <tr><td>Confirmed</td><td><strong><?php echo $confirmedAppts; ?></strong></td></tr>
                        <tr><td>Completed</td><td><strong><?php echo $completedAppointments; ?></strong></td></tr>
                        <tr><td>Cancelled</td><td><strong><?php echo $cancelledAppointments; ?></strong></td></tr>
                        <tr><td>No Show</td><td><strong><?php echo $noShowAppts; ?></strong></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">Queue Performance</div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Metric</th><th>Value</th></tr></thead>
                    <tbody>
                        <tr><td>Total Entries</td><td><strong><?php echo $totalQueueEntries; ?></strong></td></tr>
                        <tr><td>Served</td><td><strong><?php echo $servedQueue; ?></strong></td></tr>
                        <tr><td>Skipped</td><td><strong><?php echo $skippedQueue; ?></strong></td></tr>
                        <tr><td>Avg Wait Time</td><td><strong><?php echo round($avgWaitTime ?? 0, 1); ?> min</strong></td></tr>
                        <tr><td>Completion Rate</td><td><strong><?php echo $totalQueueEntries > 0 ? round(($servedQueue / $totalQueueEntries) * 100, 1) : 0; ?>%</strong></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if (!empty($dailyRows) && count($dailyRows) > 1): ?>
    <div class="card mb-30">
        <div class="card-header">
            <div class="card-title">Daily Breakdown (<?php echo count($dailyRows); ?> days)</div>
        </div>
        <p style="color:var(--gray);margin-bottom:16px;">Complete per-day data for the selected range. Switch to <strong>Today</strong> for the current day view.</p>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>New Patients</th>
                        <th>Appointments</th>
                        <th>Queue Entries</th>
                        <th>Served</th>
                        <th>Avg Wait</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dailyRows as $row): ?>
                    <tr>
                        <td><strong><?php echo date('D, M d, Y', strtotime($row['date'])); ?></strong></td>
                        <td><?php echo $row['regs']; ?></td>
                        <td>
                            <?php echo $row['appts_total']; ?>
                            <small style="color:var(--gray);">
                                (<?php echo $row['appts_completed']; ?> completed, <?php echo $row['appts_pending']; ?> pending)
                            </small>
                        </td>
                        <td><?php echo $row['queue_total']; ?></td>
                        <td><?php echo $row['queue_served']; ?></td>
                        <td><?php echo $row['queue_avg_wait'] ? round($row['queue_avg_wait'], 1) . ' min' : '--'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="grid-2 mb-30">
        <div class="card">
            <div class="card-header">
                <div class="card-title">Service Utilization</div>
            </div>
            <?php if (empty($serviceStats)): ?>
                <div class="empty-state"><p>No data available.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Service</th><th>Total</th><th>Served</th><th>Skipped</th><th>Rate</th></tr></thead>
                    <tbody>
                        <?php foreach ($serviceStats as $ss): ?>
                        <tr>
                            <td><?php echo escape($ss['name']); ?></td>
                            <td><strong><?php echo $ss['total']; ?></strong></td>
                            <td><?php echo $ss['served']; ?></td>
                            <td><?php echo $ss['skipped']; ?></td>
                            <td><strong><?php echo $ss['total'] > 0 ? round(($ss['served'] / $ss['total']) * 100, 1) : 0; ?>%</strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">Doctor Performance</div>
            </div>
            <?php if (empty($doctorStats)): ?>
                <div class="empty-state"><p>No data available.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Doctor</th><th>Total</th><th>Completed</th><th>Cancelled</th></tr></thead>
                    <tbody>
                        <?php foreach ($doctorStats as $ds): ?>
                        <tr>
                            <td>Dr. <?php echo escape($ds['name']); ?></td>
                            <td><strong><?php echo $ds['total']; ?></strong></td>
                            <td><?php echo $ds['completed']; ?></td>
                            <td><?php echo $ds['cancelled']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-30" style="border-left: 4px solid var(--primary);">
        <div class="card-header">
            <div class="card-title">Provider Performance Chart</div>
            <a href="reports.php" class="btn btn-sm btn-outline">Reset</a>
        </div>
        <p style="color:var(--gray);margin-bottom:16px;">Activities for <strong><?php echo date('M d, Y', strtotime($dateFrom)); ?></strong> to <strong><?php echo date('M d, Y', strtotime($dateTo)); ?></strong> &mdash; count = queue entries served + appointments completed by each doctor/nurse.</p>

        <?php if (empty($providerStats)): ?>
            <div class="empty-state"><p>No provider activity in this period.</p></div>
        <?php else: ?>
        <style>
            .provider-chart { display: flex; align-items: flex-end; gap: 8px; height: 230px; padding: 10px 6px 0; overflow-x: auto; }
            .p-col { flex: 1 0 56px; max-width: 64px; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; height: 100%; }
            .p-val { font-size: 12px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
            .p-bar { width: 70%; background: linear-gradient(180deg, #2563eb, #3b82f6); border-radius: 6px 6px 0 0; min-height: 2px; transition: height .3s; }
            .p-bar.active { background: linear-gradient(180deg, #16a34a, #22c55e); }
            .p-name { font-size: 11px; color: #64748b; text-align: center; margin-top: 6px; line-height: 1.15; word-break: break-word; }
        </style>
        <?php $barCount = min(count($providerStats), 12); ?>
        <div class="provider-chart">
            <?php foreach (array_slice($providerStats, 0, $barCount) as $i => $ds): $active = (int)$ds['is_active'] === 1; $pct = $maxProviderActivity > 0 ? max(2, round(((int)$ds['total'] / $maxProviderActivity) * 100)) : 0; ?>
            <div class="p-col" title="<?php echo escape(providerDisplayName($ds) . ' - ' . $ds['total'] . ' activities'); ?>">
                <div class="p-val"><?php echo (int)$ds['total']; ?></div>
                <div class="p-bar <?php echo $active ? 'active' : ''; ?>" style="height:<?php echo $pct; ?>%;"></div>
                <div class="p-name"><?php echo escape(($ds['type'] === 'nurse' ? 'N' : '') . '' . ($ds['type'] === 'nurse' ? substr($ds['first_name'], 0, 6) . '.' : substr($ds['first_name'], 0, 8))); ?><br><?php echo escape($ds['last_name']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <p style="color:var(--gray);font-size:12px;margin:8px 0 0;">Green = active account &middot; up to <?php echo $barCount; ?> providers shown here (full list below).</p>

        <div class="table-responsive mt-20">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th><th>Provider</th><th>Type</th><th>Queue Served</th><th>Appointments Completed</th><th>Total Activities</th><th>Account</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rank = 0; foreach ($providerStats as $ds): $rank++; ?>
                    <tr>
                        <td><strong><?php echo $rank; ?></strong></td>
                        <td><strong><?php echo escape(providerDisplayName($ds)); ?></strong></td>
                        <td><span class="badge <?php echo $ds['type'] === 'nurse' ? 'badge-info' : 'badge-active'; ?>"><?php echo ucfirst(escape($ds['type'])); ?></span></td>
                        <td><?php echo (int)$ds['served_queue']; ?></td>
                        <td><?php echo (int)$ds['completed_appts']; ?></td>
                        <td><span class="badge badge-primary"><?php echo (int)$ds['total']; ?></span></td>
                        <td><span class="badge <?php echo (int)$ds['is_active'] === 1 ? 'badge-active' : 'badge-inactive'; ?>"><?php echo (int)$ds['is_active'] === 1 ? 'Active' : 'Inactive'; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="card mb-30" style="border-left: 4px solid var(--primary);">
        <div class="card-header">
            <div class="card-title">Staff Work Lookup</div>
        </div>
        <p style="color:var(--gray);margin-bottom:16px;">Enter a date to see how each staff member, doctor, and admin worked that day (from audit logs).</p>
        <form method="GET" class="filters-bar">
            <input type="hidden" name="period" value="<?php echo escape($period); ?>">
            <?php if ($period === 'custom'): ?>
                <input type="hidden" name="from" value="<?php echo escape($customFrom); ?>">
                <input type="hidden" name="to" value="<?php echo escape($customTo); ?>">
            <?php elseif ($period === 'week_start'): ?>
                <input type="hidden" name="week_start" value="<?php echo escape($weekStartInput); ?>">
            <?php endif; ?>
            <input type="date" name="work_date" class="form-control" value="<?php echo escape($workDate); ?>">
            <button type="submit" class="btn btn-primary btn-sm">Search Day</button>
        </form>

        <?php if ($staffSummary): ?>
        <div class="table-responsive mt-20">
            <table class="table">
                <thead><tr><th>User</th><th>Role</th><th>Actions Performed</th></tr></thead>
                <tbody>
                    <?php foreach ($staffSummary as $ss): ?>
                    <tr>
                        <td><strong><?php echo escape($ss['full_name'] ?: $ss['email']); ?></strong><br><small style="color:var(--gray);"><?php echo escape($ss['email']); ?></small></td>
                        <td><?php echo escape(ucfirst($ss['role'])); ?></td>
                        <td><span class="badge badge-primary"><?php echo $ss['actions']; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty-state mt-20"><p>No activity recorded for this day.</p></div>
        <?php endif; ?>

        <?php if ($workLogs): ?>
        <div class="card-header mt-20" style="padding-left:0;">
            <div class="card-title">Activity Log for <?php echo date('M d, Y', strtotime($workDate)); ?></div>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($workLogs as $log): ?>
                    <tr>
                        <td><?php echo date('h:i A', strtotime($log['created_at'])); ?></td>
                        <td><?php echo escape($log['email']); ?></td>
                        <td><span class="badge badge-info"><?php echo escape($log['action']); ?></span></td>
                        <td><?php echo escape($log['description'] ?? '--'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>