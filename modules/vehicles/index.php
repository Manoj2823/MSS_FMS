<?php require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';
require_login('vehicles.view');
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login('vehicles.archive');
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $h = $pdo->prepare('SELECT (SELECT COUNT(*) FROM trips WHERE vehicle_id=?)+(SELECT COUNT(*) FROM fuel_entries WHERE vehicle_id=?)+(SELECT COUNT(*) FROM expenses WHERE vehicle_id=?) n');
    $h->execute([$id, $id, $id]);
    $pdo->prepare('UPDATE vehicles SET deleted_at=NOW() WHERE id=? AND deleted_at IS NULL')->execute([$id]);
    audit('archive', 'vehicle', $id, 'history rows: ' . $h->fetchColumn());
    flash('Vehicle archived (history retained).');
    header('Location: index.php');
    exit;
}
$q = trim($_GET['q'] ?? '');
$type = array_key_exists($_GET['type'] ?? '', TYPES) ? $_GET['type'] : '';
$status = array_key_exists($_GET['status'] ?? '', STATUSES) ? $_GET['status'] : '';
$w = 'v.deleted_at IS NULL';
$p = [];
if ($q !== '') {
    $w .= ' AND (v.reg_no LIKE ? OR v.fleet_no LIKE ? OR v.make LIKE ? OR v.model LIKE ?)';
    array_push($p, ...array_fill(0, 4, "%$q%"));
}
if ($type) {
    $w .= ' AND v.vehicle_type=?';
    $p[] = $type;
}
if ($status) {
    $w .= ' AND v.status=?';
    $p[] = $status;
}
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="vehicles.csv"');
    $o = fopen('php://output', 'w');
    fputcsv($o, ['Reg No', 'Fleet No', 'Type', 'Make', 'Model', 'Status', 'Odometer']);
    $s = $pdo->prepare("SELECT v.* FROM vehicles v WHERE $w ORDER BY v.reg_no");
    $s->execute($p);
    foreach ($s as $r) {
        fputcsv($o, array_map(fn($x) => preg_match('/^[=+\-@\t\r]/', (string) $x) ? "'" . $x : $x, [$r['reg_no'], $r['fleet_no'], $r['vehicle_type'], $r['make'], $r['model'], $r['status'], $r['odometer']]));
    }
    exit;
}
$per = 15;
$pg = max(1, (int) ($_GET['page'] ?? 1));
$c = $pdo->prepare("SELECT COUNT(*) FROM vehicles v WHERE $w");
$c->execute($p);
$tot = (int) $c->fetchColumn();
$pages = max(1, (int) ceil($tot / $per));
$s = $pdo->prepare("SELECT v.*,b.name branch FROM vehicles v LEFT JOIN branches b ON b.id=v.branch_id WHERE $w ORDER BY v.reg_no LIMIT $per OFFSET " . (($pg - 1) * $per));
$s->execute($p);
$rows = $s->fetchAll();
$qs = fn($x = []) => http_build_query(array_merge($_GET, $x));
layout_top('Vehicles', 'vehicles', ['Fleet', 'Vehicles']); ?>
<div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
    <h4 class="mb-0">Vehicles <small class="text-secondary fs-6">(<?= $tot ?>)</small></h4>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="?<?= e($qs(['export' => 'csv'])) ?>"><i class="bi bi-download"></i>
            CSV</a><?php if (can('vehicles.edit')): ?><a class="btn btn-primary" href="form.php"><i
                    class="bi bi-plus-lg"></i> Add vehicle</a><?php endif; ?>
    </div>
</div>
<form class="row g-2 mb-3">
    <div class="col-12 col-md-5"><input name="q" value="<?= e($q) ?>" class="form-control"
            placeholder="Search reg no, fleet no, make, model"></div>
    <div class="col-6 col-md-3"><select name="type" class="form-select" onchange="this.form.submit()">
            <option value="">All types</option><?php foreach (TYPES as $k => $v): ?>
                <option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-3"><select name="status" class="form-select" onchange="this.form.submit()">
            <option value="">All statuses</option><?php foreach (STATUSES as $k => $v): ?>
                <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-12 col-md-1"><button class="btn btn-light w-100">Go</button></div>
</form>
<div class="cardx p-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-stack">
            <thead>
                <tr>
                    <th>Reg No</th>
                    <th>Type</th>
                    <th>Make / Model</th>
                    <th>Branch</th>
                    <th>Odometer</th>
                    <th>Insurance</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r):
                    [$bc, $bt] = expiry_badge($r['insurance_expiry']); ?>
                    <tr>
                        <td data-l="Reg No"><a href="view.php?id=<?= $r['id'] ?>"><b><?= e($r['reg_no']) ?></b></a></td>
                        <td data-l="Type"><?= e(TYPES[$r['vehicle_type']]) ?></td>
                        <td data-l="Make"><?= e(trim($r['make'] . ' ' . $r['model'])) ?></td>
                        <td data-l="Branch"><?= e($r['branch'] ?? '—') ?></td>
                        <td data-l="Odometer"><?= number_format($r['odometer']) ?> km</td>
                        <td data-l="Insurance"><span class="badge text-bg-<?= $bc ?>"><?= e($bt) ?></span></td>
                        <td data-l="Status"><span class="badge text-bg-primary"><?= e(STATUSES[$r['status']]) ?></span></td>
                        <td class="text-end text-nowrap"><a class="btn btn-sm btn-light"
                                href="view.php?id=<?= $r['id'] ?>">View</a><?php if (can('vehicles.edit')): ?> <a
                                    class="btn btn-sm btn-light"
                                    href="form.php?id=<?= $r['id'] ?>">Edit</a><?php endif;
                                if (can('vehicles.archive')): ?>
                                <form method="post" class="d-inline"
                                    data-confirm="Archive <?= e($r['reg_no']) ?>? History is kept."><?= csrf_field() ?><input
                                        type="hidden" name="id" value="<?= $r['id'] ?>"><button
                                        class="btn btn-sm btn-outline-danger">Archive</button></form><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach;
                if (!$rows): ?>
                    <tr>
                        <td colspan="8" class="text-center text-secondary py-5">No vehicles found.</td>
                    </tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<nav class="mt-3">
    <ul class="pagination justify-content-center flex-wrap"><?php for ($i = 1; $i <= $pages; $i++): ?>
            <li class="page-item <?= $i === $pg ? 'active' : '' ?>"><a class="page-link"
                    href="?<?= e($qs(['page' => $i])) ?>"><?= $i ?></a></li><?php endfor; ?>
    </ul>
</nav>
<?php layout_bottom();
