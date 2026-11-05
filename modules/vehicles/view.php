<?php require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';
require_login('vehicles.view');
$pdo = db();
$s = $pdo->prepare('SELECT v.*,b.name branch FROM vehicles v LEFT JOIN branches b ON b.id=v.branch_id WHERE v.id=? AND v.deleted_at IS NULL');
$s->execute([(int) ($_GET['id'] ?? 0)]);
$v = $s->fetch();
if (!$v) {
    http_response_code(404);
    exit('Vehicle not found');
}
$tr = $pdo->prepare('SELECT * FROM trips WHERE vehicle_id=? ORDER BY planned_departure DESC LIMIT 10');
$tr->execute([$v['id']]);
$cnt = fn($t) => (function () use ($pdo, $t, $v) {
    $c = $pdo->prepare("SELECT COUNT(*) FROM $t WHERE vehicle_id=?");
    $c->execute([$v['id']]);
    return $c->fetchColumn(); })();
layout_top($v['reg_no'], 'vehicles', ['Fleet', 'Vehicles', $v['reg_no']]); ?>
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
    <div>
        <h4 class="mb-0"><?= e($v['reg_no']) ?> <span
                class="badge text-bg-primary fs-6"><?= e(STATUSES[$v['status']]) ?></span></h4><small
            class="text-secondary"><?= e(TYPES[$v['vehicle_type']]) ?> · <?= e(trim($v['make'] . ' ' . $v['model'])) ?></small>
    </div>
    <?php if (can('vehicles.edit')): ?><a class="btn btn-primary align-self-start"
            href="form.php?id=<?= $v['id'] ?>">Edit</a><?php endif; ?>
</div>
<div class="row g-3 mb-3">
    <?php foreach ([['Odometer', number_format($v['odometer']) . ' km'], ['Trips', $cnt('trips')], ['Fuel entries', $cnt('fuel_entries')], ['Service records', $cnt('maintenance_records')]] as $m): ?>
        <div class="col-6 col-lg-3">
            <div class="cardx"><small class="text-secondary"><?= $m[0] ?></small>
                <div class="fs-4 fw-bold"><?= e($m[1]) ?></div>
            </div>
        </div><?php endforeach; ?>
</div>
<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="cardx">
            <h6>Document expiry</h6>
            <?php foreach (['insurance_expiry' => 'Insurance', 'permit_expiry' => 'Permit', 'fitness_expiry' => 'Fitness', 'road_tax_expiry' => 'Road tax', 'puc_expiry' => 'PUC'] as $k => $l):
                [$c, $t] = expiry_badge($v[$k]); ?>
                <div class="d-flex justify-content-between border-bottom py-2"><span><?= $l ?></span><span><?= e($v[$k] ?: '—') ?>
                        <span class="badge text-bg-<?= $c ?>"><?= e($t) ?></span></span></div><?php endforeach; ?>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="cardx">
            <h6>Details</h6>
            <dl class="row mb-0 small">
                <dt class="col-5">Branch</dt>
                <dd class="col-7"><?= e($v['branch'] ?? '—') ?></dd>
                <dt class="col-5">Fuel</dt>
                <dd class="col-7"><?= e(ucfirst($v['fuel_type'])) ?></dd>
                <dt class="col-5">Ownership</dt>
                <dd class="col-7"><?= e(ucfirst($v['ownership'])) ?></dd>
                <dt class="col-5">Chassis</dt>
                <dd class="col-7"><?= e($v['chassis_no'] ?: '—') ?></dd>
                <dt class="col-5">GPS</dt>
                <dd class="col-7">
                    <?= $v['gps_device_id'] && $v['gps_updated_at'] ? e($v['gps_lat'] . ', ' . $v['gps_lng'] . ' @ ' . $v['gps_updated_at']) : '<span class="text-secondary">GPS not connected (live tracking needs a device/API integration)</span>' ?>
                </dd>
            </dl>
        </div>
    </div>
    <div class="col-12">
        <div class="cardx">
            <?php $TB = [
                'Trips' => "SELECT id,origin,destination,planned_departure,distance_km,status FROM trips WHERE vehicle_id=? ORDER BY planned_departure DESC LIMIT 25",
                'Fuel & Mileage' => "SELECT filled_at,litres,total_amount,odometer,is_full_tank FROM fuel_entries WHERE vehicle_id=? ORDER BY filled_at DESC LIMIT 25",
                'Maintenance' => "SELECT service_date,service_type,workshop,total_cost,status FROM maintenance_records WHERE vehicle_id=? ORDER BY service_date DESC LIMIT 25",
                'Documents' => "SELECT doc_type,doc_number,expiry_date FROM vehicle_documents WHERE vehicle_id=? AND deleted_at IS NULL ORDER BY expiry_date",
                'Expenses' => "SELECT expense_date,category,amount,approval_status FROM expenses WHERE vehicle_id=? ORDER BY expense_date DESC LIMIT 25",
                'Tyres' => "SELECT t.tyre_code,f.position,f.fitted_on,f.removed_on FROM tyre_fitments f JOIN tyres t ON t.id=f.tyre_id WHERE f.vehicle_id=? ORDER BY f.fitted_on DESC",
                'Loan / EMI' => "SELECT lender,account_ref,emi_amount,end_date FROM vehicle_loans WHERE vehicle_id=? AND deleted_at IS NULL",
                'Activity' => "SELECT created_at,action,details FROM audit_logs WHERE entity='vehicle' AND entity_id=? ORDER BY id DESC LIMIT 25"
            ];
            $ti = 0; ?>
            <ul class="nav nav-tabs flex-nowrap overflow-auto mb-3"><?php foreach ($TB as $n => $_): ?>
                    <li class="nav-item"><button class="nav-link text-nowrap <?= $ti++ ? '' : 'active' ?>" data-bs-toggle="tab"
                            data-bs-target="#tb<?= md5($n) ?>"><?= e($n) ?></button></li><?php endforeach;
            $ti = 0; ?>
            </ul>
            <div class="tab-content">
                <?php foreach ($TB as $n => $sql):
                    $q = $pdo->prepare($sql);
                    $q->execute([$v['id']]);
                    $rs = $q->fetchAll(); ?>
                    <div class="tab-pane fade <?= $ti++ ? '' : 'show active' ?>" id="tb<?= md5($n) ?>">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <?php if ($rs): ?>
                                    <tr><?php foreach (array_keys($rs[0]) as $h): ?>
                                            <th><?= e(ucwords(str_replace('_', ' ', $h))) ?></th><?php endforeach; ?>
                                    </tr><?php foreach ($rs as $r): ?>
                                        <tr><?php foreach ($r as $x): ?>
                                                <td><?= e($x ?? '—') ?></td><?php endforeach; ?>
                                        </tr><?php endforeach; else: ?>
                                    <tr>
                                        <td class="text-secondary">No records.</td>
                                    </tr><?php endif; ?>
                            </table>
                        </div>
                    </div><?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php layout_bottom();
