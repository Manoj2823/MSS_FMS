const $ = s => document.querySelector(s), esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const P = ['#1d4ed8', '#06b6d4', '#0b1f4d', '#f59e0b', '#ef4444', '#64748b', '#10b981'], charts = {};
function draw(id, cfg) { charts[id]?.destroy(); charts[id] = new Chart($('#' + id), cfg) }
const inr = n => '₹' + Number(n).toLocaleString('en-IN', { maximumFractionDigits: 0 });
let currentAlerts = [];
let alertPage = 1;
const alertPageSize = 10;

function renderAlertsPage(page) {
    alertPage = page;
    const total = currentAlerts.length;
    const totalPages = Math.ceil(total / alertPageSize) || 1;
    if (alertPage > totalPages) alertPage = totalPages;
    if (alertPage < 1) alertPage = 1;

    const countBadge = $('#alertsCount');
    if (countBadge) countBadge.textContent = total + (total === 1 ? ' item' : ' items');

    const pBox = $('#alertsPagination');
    if (total === 0) {
        $('#alerts').innerHTML = '<tr><td class="text-secondary text-center py-3" colspan="4">No upcoming expiries in the next 30 days.</td></tr>';
        if (pBox) pBox.classList.add('d-none');
        return;
    }
    if (pBox) pBox.classList.remove('d-none');

    const start = (alertPage - 1) * alertPageSize;
    const end = Math.min(start + alertPageSize, total);
    const pageItems = currentAlerts.slice(start, end);

    $('#alerts').innerHTML = pageItems.map(a => {
        const n = a.days_left !== undefined ? a.days_left : Math.floor((new Date(a.date) - new Date().setHours(0, 0, 0, 0)) / 864e5);
        const link = a.is_driver 
            ? `<span class="fw-semibold">${esc(a.reg)}</span> <small class="text-secondary">(Driver)</small>` 
            : `<a href="${VEH}view.php?id=${a.id}" class="fw-semibold text-dark text-decoration-none">${esc(a.reg)}</a>`;
        return `<tr>
            <td>${link}</td>
            <td><span class="badge ${n <= 7 ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'text-bg-light border'}">${esc(a.type)}</span></td>
            <td>${esc(a.date)}</td>
            <td><span class="badge text-bg-${n < 0 ? 'danger' : (n <= 7 ? 'danger' : (n <= 15 ? 'warning' : 'info'))}">${n < 0 ? 'Overdue' : (n <= 7 ? '⚠️ ' + n + 'd (Due)' : n + 'd')}</span></td>
        </tr>`;
    }).join('');

    const pInfo = $('#alertsPageInfo');
    if (pInfo) {
        pInfo.textContent = `Showing ${start + 1}–${end} of ${total}`;
    }

    const pNav = $('#alertsPageNav');
    if (pNav) {
        if (totalPages <= 1) {
            pNav.innerHTML = '';
        } else {
            let html = `<li class="page-item ${alertPage === 1 ? 'disabled' : ''}">
                <button type="button" class="page-link py-1 px-2" onclick="renderAlertsPage(${alertPage - 1})" aria-label="Previous">«</button>
            </li>`;

            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= alertPage - 1 && i <= alertPage + 1)) {
                    html += `<li class="page-item ${i === alertPage ? 'active' : ''}">
                        <button type="button" class="page-link py-1 px-2" onclick="renderAlertsPage(${i})">${i}</button>
                    </li>`;
                } else if (i === alertPage - 2 || i === alertPage + 2) {
                    html += `<li class="page-item disabled"><span class="page-link py-1 px-1">…</span></li>`;
                }
            }

            html += `<li class="page-item ${alertPage === totalPages ? 'disabled' : ''}">
                <button type="button" class="page-link py-1 px-2" onclick="renderAlertsPage(${alertPage + 1})" aria-label="Next">»</button>
            </li>`;
            pNav.innerHTML = html;
        }
    }
}
window.renderAlertsPage = renderAlertsPage;

async function load() {
    const q = new URLSearchParams(new FormData($('#flt')));
    const r = await fetch(API + '?' + q); if (!r.ok) { $('#kpis').innerHTML = '<div class="alert alert-danger">Could not load dashboard data.</div>'; return }
    const d = await r.json(); $('#empty').classList.toggle('d-none', d.total > 0); $('#board').classList.toggle('d-none', d.total === 0); if (!d.total) return;

    const uCount = d.urgent_alerts?.length || 0;
    const uBox = $('#urgentAlertBox');
    if (uBox) {
        uBox.classList.toggle('d-none', uCount === 0);
        if (uCount > 0) {
            $('#urgentBadgeCount').textContent = uCount + ' Action Required';
            $('#urgentAlertsList').innerHTML = d.urgent_alerts.map(u => `
                <tr>
                    <td class="fw-semibold">${u.is_driver ? esc(u.target) + ` <span class="badge text-bg-light border">Driver</span>` : `<a href="${u.url}" class="text-decoration-none fw-bold text-dark">${esc(u.target)}</a>`}</td>
                    <td><span class="badge bg-danger-subtle text-danger border border-danger-subtle">${esc(u.type)}</span></td>
                    <td>${esc(u.date)}</td>
                    <td><span class="badge text-bg-danger fw-bold">⚠️ ${esc(u.badge)}</span></td>
                    <td>${u.is_driver ? `<span class="text-secondary small">Renew licence</span>` : `<a href="${u.url}" class="btn btn-sm btn-outline-danger py-0 px-2">View</a>`}</td>
                </tr>`).join('');
        }
    }

    const s = d.status, k = [
        ['truck', 'Total Vehicles', d.total, VEH],
        ['check-circle', 'Available', s.available || 0, VEH + '?status=available'],
        ['signpost-2', 'On Trip', s.on_trip || 0, VEH + '?status=on_trip'],
        ['wrench', 'Under Maintenance', s.maintenance || 0, VEH + '?status=maintenance'],
        ['person-badge', 'Drivers', d.drivers, (typeof ROOT !== 'undefined' ? ROOT + 'modules/m.php?m=drivers' : '#')],
        ['exclamation-octagon-fill text-danger', 'Due Soon (≤7 Days)', uCount, '#urgentAlertBox'],
        ['exclamation-triangle', 'Alerts (30d)', d.alerts.length, (typeof ROOT !== 'undefined' ? ROOT + 'modules/reports.php?r=doc_expiry' : '#alerts')],
        ['currency-rupee', 'Expenses This Month', inr(d.month_expense), (typeof ROOT !== 'undefined' ? ROOT + 'modules/reports.php?r=expenses_monthly' : '#')]
    ];
    $('#kpis').innerHTML = k.map(x => `<div class="col-6 col-md-4 col-xl-3"><a class="cardx kpi" href="${x[3]}"><div class="ic"><i class="bi bi-${x[0]}"></i></div><div><b>${x[2]}</b><span>${x[1]}</span></div></a></div>`).join('');
    const dn = (o, m) => ({ type: 'doughnut', data: { labels: Object.keys(o).map(x => m[x] || x), datasets: [{ data: Object.values(o), backgroundColor: P }] }, options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } } });
    draw('cStatus', dn(s, STAT)); draw('cType', dn(d.types, TYPES));
    draw('cTrip', { type: 'bar', data: { labels: d.trips.map(t => t.k), datasets: [{ label: 'Trips', data: d.trips.map(t => +t.n), backgroundColor: P[0] }] }, options: { maintainAspectRatio: false, plugins: { legend: { display: false } } } });
    $('#tkm').textContent = d.trips.length ? 'Total distance: ' + d.trips.reduce((a, t) => a + +t.km, 0).toLocaleString('en-IN') + ' km' : 'No trips in range.';
    const months = [...new Set([...d.fuel.map(x => x.m), ...d.maintenance.map(x => x.m), ...d.expenses.map(x => x.m)])].sort();
    const cats = [...new Set(d.expenses.map(x => x.c))];
    const ds = [{ label: 'Fuel', data: months.map(m => +(d.fuel.find(x => x.m === m)?.a || 0)) }, { label: 'Maintenance', data: months.map(m => +(d.maintenance.find(x => x.m === m)?.a || 0)) }, ...cats.map(c => ({ label: c, data: months.map(m => +(d.expenses.find(x => x.m === m && x.c === c)?.a || 0)) }))].map((x, i) => ({ ...x, backgroundColor: P[i % P.length] }));
    draw('cExp', { type: 'bar', data: { labels: months, datasets: ds }, options: { maintainAspectRatio: false, scales: { x: { stacked: true }, y: { stacked: true } } } });
    draw('cFuel', { data: { labels: d.fuel.map(x => x.m), datasets: [{ type: 'bar', label: 'Litres', data: d.fuel.map(x => +x.l), backgroundColor: P[1], yAxisID: 'y' }, { type: 'line', label: 'Spend (₹)', data: d.fuel.map(x => +x.a), borderColor: P[0], yAxisID: 'y1' }] }, options: { maintainAspectRatio: false, scales: { y1: { position: 'right', grid: { drawOnChartArea: false } } } } });
    currentAlerts = d.alerts || [];
    renderAlertsPage(1);
    $('#recent').innerHTML = d.recent.map(v => `<li class="list-group-item d-flex justify-content-between"><span>${esc(v.reg_no)}</span><small class="text-secondary">${esc(TYPES[v.vehicle_type])}</small></li>`).join('');
}
$('#flt').addEventListener('change', load); load();
