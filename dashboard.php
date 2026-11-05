<?php require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_login('dashboard.view');
$branches = db()->query('SELECT id,name FROM branches WHERE is_active=1 ORDER BY name')->fetchAll();
layout_top('Dashboard', 'dashboard', ['Dashboard']); ?>
<form id="flt" class="row g-2 mb-3">
    <div class="col-6 col-md-3"><input type="date" name="from" class="form-control"
            value="<?= date('Y-m-01', strtotime('-5 months')) ?>"></div>
    <div class="col-6 col-md-3"><input type="date" name="to" class="form-control" value="<?= date('Y-m-d') ?>"></div>
    <div class="col-6 col-md-3"><select name="branch" class="form-select">
            <option value="0">All branches</option><?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-3"><select name="type" class="form-select">
            <option value="">All types</option><?php foreach (TYPES as $k => $v): ?>
                <option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?>
        </select></div>
</form>
<div id="empty" class="cardx text-center py-5 d-none"><i class="bi bi-truck fs-1 text-primary"></i>
    <h5 class="mt-2">No vehicles yet</h5>
    <p class="text-secondary">Add your first vehicle to see live fleet analytics.</p>
    <?php if (can('vehicles.edit')): ?><a class="btn btn-primary" href="<?= url('modules/vehicles/form.php') ?>">Add
            vehicle</a><?php endif; ?>
</div>
<div id="board">
    <div class="row g-3 mb-3" id="kpis"></div>
    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6 col-xl-4">
            <div class="cardx">
                <h6>Vehicle Status</h6>
                <div class="chart-box"><canvas id="cStatus"></canvas></div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="cardx">
                <h6>Vehicle Types</h6>
                <div class="chart-box"><canvas id="cType"></canvas></div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="cardx">
                <h6>Trips by Status</h6>
                <div class="chart-box"><canvas id="cTrip"></canvas></div><small class="text-secondary" id="tkm"></small>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-6">
            <div class="cardx">
                <h6>Monthly Expenses</h6>
                <div class="chart-box"><canvas id="cExp"></canvas></div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="cardx">
                <h6>Fuel: Litres &amp; Spend</h6>
                <div class="chart-box"><canvas id="cFuel"></canvas></div>
            </div>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <div class="cardx d-flex flex-column h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Upcoming Alerts (30 days)</h6>
                    <span class="badge text-bg-light border" id="alertsCount">0 items</span>
                </div>
                <div class="table-responsive flex-grow-1">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Target</th>
                                <th>Alert Type</th>
                                <th>Due Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="alerts"></tbody>
                    </table>
                </div>
                <!-- Pagination Controls -->
                <div id="alertsPagination" class="d-flex flex-wrap justify-content-between align-items-center pt-2 mt-2 border-top gap-2">
                    <small class="text-secondary" id="alertsPageInfo">Showing 0-0 of 0</small>
                    <nav aria-label="Alerts pagination">
                        <ul class="pagination pagination-sm mb-0" id="alertsPageNav"></ul>
                    </nav>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-5">
            <div class="cardx d-flex flex-column h-100">
                <h6 class="mb-2">Recently Added Vehicles</h6>
                <ul class="list-group list-group-flush flex-grow-1" id="recent"></ul>
            </div>
        </div>
    </div>
    <!-- Urgent 7-Day Due Alert Section (At the end of the page) -->
    <div id="urgentAlertBox" class="d-none alert alert-danger border-danger-subtle shadow-sm mt-3 mb-0">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-octagon-fill fs-4 text-danger"></i>
                <div>
                    <h6 class="mb-0 text-danger fw-bold">⚠️ Urgent Expiry Alert: Due Within 7 Days</h6>
                    <small class="text-dark">Vehicle documents, scheduled services, or driver licences requiring immediate attention.</small>
                </div>
            </div>
            <span class="badge bg-danger fs-6 px-3 py-2 rounded-pill" id="urgentBadgeCount">0 Due</span>
        </div>
        <div class="table-responsive bg-white rounded-3 p-2 border">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Target (Reg / Name)</th>
                        <th>Expiry Type</th>
                        <th>Due Date</th>
                        <th>Countdown</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="urgentAlertsList"></tbody>
            </table>
        </div>
    </div>
</div>
<?php $js = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script><script>window.API=' . json_encode(url('api/dashboard.php')) . ';window.ROOT=' . json_encode(url()) . ';window.VEH=' . json_encode(url('modules/vehicles/')) . ';window.TYPES=' . json_encode(TYPES) . ';window.STAT=' . json_encode(STATUSES) . '</script><script src="' . url('assets/js/dashboard.js') . '"></script>';
layout_bottom($js);
