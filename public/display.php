<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Now Serving &middot; MediQueue</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .board-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 32px;
            background: #1e293b;
            border-bottom: 4px solid #2563eb;
        }
        .board-header h1 { font-size: 26px; font-weight: 700; }
        .board-header h1 span { color: #60a5fa; }
        .board-back { display: flex; align-items: center; gap: 26px; }
        .board-back a {
            color: #e2e8f0; text-decoration: none; font-size: 15px; font-weight: 600;
            background: #334155; border: 1px solid #475569; border-radius: 8px; padding: 9px 16px;
        }
        .board-back a:hover { background: #475569; border-color: #60a5fa; }
        .board-clock { font-size: 24px; font-weight: 600; color: #94a3b8; font-variant-numeric: tabular-nums; }
        .board-grid {
            flex: 1;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 22px;
            padding: 26px 32px;
            align-content: start;
        }
        .service-card {
            background: #1e293b;
            border-radius: 14px;
            padding: 22px;
            border-top: 5px solid #2563eb;
            box-shadow: 0 6px 20px rgba(0,0,0,.35);
        }
        .service-card.emergency-card {
            border-top: 5px solid #dc2626;
            background: #2a1b1b;
            box-shadow: 0 0 30px rgba(220,38,38,.4);
            animation: emerPulse 1.5s infinite;
        }
        @keyframes emerPulse {
            0%, 100% { box-shadow: 0 0 30px rgba(220,38,38,.4); }
            50% { box-shadow: 0 0 60px rgba(220,38,38,.9); }
        }
        .emer-tag {
            display: inline-block;
            background: #dc2626;
            color: #fff;
            font-weight: 800;
            font-size: 13px;
            letter-spacing: 1px;
            padding: 4px 12px;
            border-radius: 999px;
            margin-bottom: 10px;
            animation: tagBlink 1s infinite;
        }
        @keyframes tagBlink { 0%,100% { opacity: 1; } 50% { opacity: .55; } }
        .service-card .svc-name {
            font-size: 23px;
            font-weight: 800;
            color: #93c5fd;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .service-card .svc-dept {
            font-size: 14px;
            font-weight: 600;
            color: #cbd5e1;
            margin-bottom: 14px;
        }
        .now-label {
            display: inline-block;
            background: #2563eb;
            color: #fff;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            padding: 4px 12px;
            border-radius: 8px;
            box-shadow: 0 0 14px rgba(37,99,235,.5);
        }
        .now-label.called {
            background: #dc2626;
            box-shadow: 0 0 14px rgba(220,38,38,.55);
            animation: call-pulse 1.1s ease-in-out infinite;
        }
        @keyframes call-pulse { 0%,100% { opacity: 1; } 50% { opacity: .55; } }
        .now-number {
            font-size: 64px;
            font-weight: 800;
            color: #fbbf24;
            line-height: 1.1;
            font-variant-numeric: tabular-nums;
        }
        .now-name { font-size: 20px; color: #f1f5f9; margin-bottom: 16px; }
        .board-next {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #334155;
            border-radius: 10px;
            padding: 12px 16px;
            margin-top: 4px;
        }
        .board-next .next-num { font-size: 22px; font-weight: 700; color: #e2e8f0; }
        .board-next .next-name { font-size: 14px; color: #94a3b8; }
        .board-next small {
            color: #7dd3fc;
            text-transform: uppercase;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 1.5px;
        }
        .idle { opacity: .45; color: #94a3b8; font-size: 15px; }
        .idle.no-serving {
            color: #fbbf24;
            opacity: 1;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .board-waiting { margin-top: 16px; border-top: 1px solid #334155; padding-top: 12px; }
        .board-waiting .wl-head {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #fbbf24;
            font-weight: 800;
            margin-bottom: 8px;
        }
        .board-waiting .wl-row {
            display: flex; align-items: center; gap: 10px;
            padding: 5px 0; font-size: 14px; border-bottom: 1px solid rgba(51,65,85,.5);
        }
        .board-waiting .wl-row:last-child { border-bottom: none; }
        .board-waiting .wl-pos {
            color: #64748b; font-size: 11px; min-width: 18px; text-align: right;
            font-variant-numeric: tabular-nums;
        }
        .board-waiting .wl-num {
            color: #fbbf24; font-weight: 700; min-width: 58px;
            font-variant-numeric: tabular-nums;
        }
        .board-waiting .wl-name { color: #e2e8f0; flex: 1; }
        .board-waiting .wl-pri {
            font-size: 10px; font-weight: 800; letter-spacing: 1px;
            padding: 2px 7px; border-radius: 999px; white-space: nowrap;
        }
        .board-waiting .wl-pri.urgent { background: #f59e0b; color: #1f2937; }
        .board-waiting .wl-pri.emergency { background: #dc2626; color: #fff; }
        .board-waiting .wl-empty { color: #64748b; font-size: 13px; }
        .board-footer {
            padding: 12px 32px;
            text-align: center;
            color: #64748b;
            font-size: 13px;
            border-top: 1px solid #1e293b;
        }
        .board-footer .pulse { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #22c55e; margin-right: 6px; animation: pulse 1.6s infinite; }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: .3; } }
        .flash-ring { animation: flashRing .5s ease; }
        @keyframes flashRing { 0% { box-shadow: 0 0 0 0 rgba(59,130,246,.6); } 100% { box-shadow: 0 0 0 26px rgba(59,130,246,0); } }
    </style>
</head>
<body>
    <div class="board-header">
        <h1>Medi<span>Queue</span> &middot; Now Serving</h1>
        <div class="board-back">
            <div class="board-clock" id="clock"></div>
            <a href="/MediQueue2/staff/dashboard.php">&larr; Back to Dashboard</a>
        </div>
    </div>

    <div class="board-grid" id="board"></div>

    <div class="board-footer"><span class="pulse"></span>Queue updates automatically &middot; <?php echo date('l, F j, Y'); ?></div>

    <script>
    const base = '/MediQueue2';
    const stateApi = base + '/api/display_state.php';
    const board = document.getElementById('board');
    let prevSignatures = {};

    function esc(s) {
        return String(s).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
    }

    function signature(card) {
        const c = (card.currently_serving ? card.currently_serving.queue_number : '-');
        const n = (card.next_patient ? card.next_patient.queue_number : '-');
        return c + '|' + n;
    }

    function render(cards) {
        board.innerHTML = '';
        prevSignatures = {};
        cards.forEach(card => {
            const svc = document.createElement('div');
            svc.className = 'service-card';
            const cur = card.currently_serving;
            const next = card.next_patient;
            const emergency = (cur && cur.priority === 'Emergency') || (next && next.priority === 'Emergency') || (card.emergency_waiting || 0) > 0;
            if (emergency) {
                svc.classList.add('emergency-card');
            }
            const waiting = card.waiting || [];
            const waitingHtml = (card.waiting_count > 0 || waiting.length)
                ? '<div class="board-waiting">' +
                  '<div class="wl-head">Waiting (' + (card.waiting_count || waiting.length) + ')</div>' +
                  (waiting.length
                    ? waiting.map((w, i) => {
                        const cls = w.priority === 'Emergency' ? 'emergency' : (w.priority === 'Urgent' ? 'urgent' : '');
                        const badge = cls
                            ? '<span class="wl-pri ' + cls + '">' + (cls === 'emergency' ? 'EMERGENCY' : 'URGENT') + '</span>'
                            : '';
                        return '<div class="wl-row">' +
                            '<span class="wl-pos">' + (i + 1) + '</span>' +
                            '<span class="wl-num">' + esc(w.queue_number) + '</span>' +
                            '<span class="wl-name">' + esc(w.patient_name) + '</span>' +
                            badge + '</div>';
                      }).join('')
                    : '<div class="wl-empty">None listed</div>') +
                  '</div>'
                : '';
            svc.innerHTML =
                '<div class="svc-name">' + esc(card.service_name) + '</div>' +
                '<div class="svc-dept">' + esc(card.department) +
                (card.emergency_waiting > 0 ? ' &middot; <span style="color:#f87171;font-weight:700;">' + card.emergency_waiting + ' EMERGENCY</span> ' : '') +
                (card.waiting_count > 0 ? ' &middot; ' + card.waiting_count + ' waiting' : '') + '</div>' +
                (emergency ? '<div class="emer-tag">&#9873; EMERGENCY</div>' : '') +
                (cur && cur.status === 'Called'
                    ? '<div class="now-label called">&#128276; Being Called</div>'
                    : '<div class="now-label">Now Serving</div>') +
                (cur
                    ? '<div class="now-number">' + esc(cur.queue_number) + '</div><div class="now-name">' + esc(cur.patient_name) + '</div>'
                    : '<div class="now-number idle">---</div><div class="now-name idle no-serving">No patient currently serving</div>') +
                '<div class="board-next">' +
                '<div><small>Next</small><div class="next-num">' + (next ? esc(next.queue_number) : '---') + '</div></div>' +
                '<div style="text-align:right">' + (next ? '<div class="next-name">' + esc(next.patient_name) + '</div>' : '<div class="next-name idle">Queue empty</div>') + '</div>' +
                '</div>' + waitingHtml;
            board.appendChild(svc);
            const sig = signature(card);
            if (prevSignatures[card.service_id] !== undefined && prevSignatures[card.service_id] !== sig) {
                svc.classList.add('flash-ring');
                setTimeout(() => svc.classList.remove('flash-ring'), 600);
            }
            prevSignatures[card.service_id] = sig;
        });
    }

    async function refresh() {
        try {
            const res = await fetch(stateApi);
            const data = await res.json();
            render(data.services);
        } catch (e) {
            board.innerHTML = '<div class="idle" style="grid-column:1/-1;padding:40px;text-align:center;">Unable to load queue data. Retrying...</div>';
        }
    }

    function tick() {
        document.getElementById('clock').textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }

    tick();
    refresh();
    setInterval(refresh, 5000);
    setInterval(tick, 1000);
    </script>
</body>
</html>