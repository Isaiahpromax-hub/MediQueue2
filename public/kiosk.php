<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check My Queue &middot; MediQueue</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; }
        .wrap { max-width: 640px; margin: 0 auto; padding: 40px 20px; }
        .logo { text-align: center; font-size: 30px; font-weight: 800; margin-bottom: 6px; }
        .logo span { color: #60a5fa; }
        .tag { text-align: center; color: #94a3b8; margin-bottom: 28px; }
        .card { background: #1e293b; border-radius: 16px; padding: 26px; box-shadow: 0 10px 30px rgba(0,0,0,.35); }
        .card h2 { font-size: 18px; margin-bottom: 14px; color: #60a5fa; }
        input[type=text], input[type=tel] {
            width: 100%; padding: 16px 18px; border-radius: 10px; border: 1px solid #334155;
            background: #0f172a; color: #e2e8f0; font-size: 22px; letter-spacing: 1px; outline: none;
        }
        input:focus { border-color: #2563eb; }
        .btn { width: 100%; margin-top: 14px; padding: 15px; border: 0; border-radius: 10px; background: #2563eb; color: #fff; font-size: 18px; font-weight: 700; cursor: pointer; }
        .btn:active { opacity: .8; }
        .or { text-align: center; color: #64748b; margin: 16px 0 8px; font-size: 13px; text-transform: uppercase; letter-spacing: 2px; }
        .result { display: none; margin-top: 20px; }
        .result .status-line { font-size: 20px; padding: 14px 0; border-bottom: 1px solid #334155; }
        .result .status-line .num { font-size: 30px; font-weight: 800; color: #fbbf24; }
        .result .status-line .st { float: right; font-size: 15px; background: #2563eb; color:#fff; padding: 6px 12px; border-radius: 20px; }
        .appt-row { padding: 10px 0; border-bottom: 1px dashed #334155; font-size: 15px; color: #cbd5e1; }
        .appt-row b { color: #f1f5f9; }
        .err { color: #f87171; text-align: center; padding: 18px; }
        .auto-note { text-align:center; color:#64748b; font-size:13px; margin-top:18px; }
        .back-home { text-align: center; margin-top: 16px; }
        .back-home a { color: #94a3b8; text-decoration: none; font-size: 14px; }
        .back-home a:hover { color: #60a5fa; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="logo">Medi<span>Queue</span></div>
        <div class="tag">Check your queue position</div>

        <div class="card">
            <h2>Look up my number</h2>
            <input type="tel" id="phone" placeholder="Phone number e.g. 0700000123" autocomplete="off">
            <div class="or">or</div>
            <input type="text" id="queue" placeholder="Queue number e.g. A015" autocomplete="off" style="font-size:18px;">
            <button class="btn" id="go">Check Status</button>
            <div class="result" id="result"></div>
            <div class="auto-note">Results refresh automatically every 10 seconds.</div>
        </div>
        <div class="back-home"><a href="/MediQueue2/login.php">&larr; Back to Home</a></div>
    </div>

    <script>
    const api = '/MediQueue2/api/kiosk_lookup.php';
    const phoneEl = document.getElementById('phone');
    const queueEl = document.getElementById('queue');
    const resultEl = document.getElementById('result');
    const goBtn = document.getElementById('go');
    let currentQuery = null;
    let timer = null;

    function esc(s) { return String(s || '').replace(/[&<>"']/g, m => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[m])); }

    function statusLabel(s) {
        const map = { Waiting: 'Waiting', Called: 'Please proceed', Serving: 'Now being served', Served: 'Served' };
        return map[s] || s;
    }

    async function lookup(withTimer) {
        const phone = phoneEl.value.trim();
        const queue = queueEl.value.trim();
        if (!phone && !queue) return;
        const params = new URLSearchParams();
        if (phone) params.set('phone', phone.replace(/\D/g, ''));
        if (queue) params.set('queue', queue);
        currentQuery = params.toString();
        try {
            const res = await fetch(api + '?' + params.toString());
            const data = await res.json();
            render(data);
        } catch (e) {
            resultEl.innerHTML = '<div class="err">Connection error. Retrying...</div>';
            resultEl.style.display = 'block';
        }
        if (withTimer) {
            clearTimeout(timer);
            timer = setTimeout(() => lookup(true), 10000);
        }
    }

    function render(data) {
        if (!data.found) {
            resultEl.innerHTML = '<div class="err">' + esc(data.message || 'No record found.') + '</div>';
            resultEl.style.display = 'block';
            return;
        }
        let html = '';
        if (data.patient && data.patient.name) {
            html += '<div style="text-align:center;color:#94a3b8;margin-bottom:6px;">' + esc(data.patient.name) +
                (data.patient.code ? ' &middot; ' + esc(data.patient.code) : '') + '</div>';
        }
        if (data.queue.length === 0) {
            html += '<div class="err" style="color:#94a3b8;">No queue entries today.</div>';
        } else {
            data.queue.forEach(q => {
                const est = q.status === 'Waiting' && q.est_minutes > 0 ? ' &middot; est ~' + q.est_minutes + ' min' : '';
                html += '<div class="status-line">Queue <span class="num">' + esc(q.queue_number) + '</span>' +
                    '<span class="st">' + esc(statusLabel(q.status)) + '</span>' +
                    '<div style="font-size:14px;color:#94a3b8;margin-top:4px;">' + esc(q.service_name) + est + '</div></div>';
            });
        }
        if (data.appointments.length > 0) {
            html += '<div style="font-weight:700;margin:14px 0 4px;color:#60a5fa;">Upcoming Appointments</div>';
            data.appointments.forEach(a => {
                html += '<div class="appt-row"><b>' + esc(a.appointment_id) + '</b> &middot; ' + esc(a.service_name) +
                    ' &middot; ' + esc(a.date) + ' ' + esc(a.time) + ' &middot; ' + esc(a.status) + '</div>';
            });
        }
        resultEl.innerHTML = html;
        resultEl.style.display = 'block';
    }

    goBtn.addEventListener('click', () => lookup(true));
    phoneEl.addEventListener('keydown', e => { if (e.key === 'Enter') lookup(true); });
    queueEl.addEventListener('keyup', e => { if (e.key === 'Enter') lookup(true); });
    </script>
</body>
</html>