<?php require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';
require_login('vehicles.edit');
$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$v = [];
if ($id) {
   $s = $pdo->prepare('SELECT * FROM vehicles WHERE id=? AND deleted_at IS NULL');
   $s->execute([$id]);
   $v = $s->fetch() ?: [];
   if (!$v) {
      http_response_code(404);
      exit('Vehicle not found');
   }
}
$err = [];
$in = $v;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   csrf_check();
   $in = $_POST;
   $reg = strtoupper(preg_replace('/\s+/', '', $_POST['reg_no'] ?? ''));
   $in['reg_no'] = $reg;
   if (!preg_match('/^[A-Z0-9\-]{4,20}$/', $reg))
      $err['reg_no'] = 'Enter a valid registration number.';
   if (!array_key_exists($_POST['vehicle_type'] ?? '', TYPES))
      $err['vehicle_type'] = 'Select a vehicle type.';
   if (!array_key_exists($_POST['status'] ?? 'available', STATUSES))
      $err['status'] = 'Invalid status.';
   $odo = (int) ($_POST['odometer'] ?? 0);
   if ($odo < 0)
      $err['odometer'] = 'Invalid odometer.';
   if ($id && $odo < (int) $v['odometer'] && !can('*'))
      $err['odometer'] = 'Odometer cannot decrease without an authorised correction (Super Admin).';
   $chassis = trim($_POST['chassis_no'] ?? '') ?: null;
   $d = $pdo->prepare('SELECT id FROM vehicles WHERE (reg_no=? OR (chassis_no IS NOT NULL AND chassis_no=?)) AND id<>?');
   $d->execute([$reg, $chassis, $id]);
   if ($d->fetch())
      $err['reg_no'] = 'Registration or chassis number already exists.';
   if (!$err) {
      $n = fn($k) => ($_POST[$k] ?? '') === '' ? null : $_POST[$k];
      $f = [
         'reg_no' => $reg,
         'fleet_no' => $n('fleet_no'),
         'vehicle_type' => $_POST['vehicle_type'],
         'make' => $n('make'),
         'model' => $n('model'),
         'mfg_year' => $n('mfg_year'),
         'fuel_type' => $_POST['fuel_type'],
         'ownership' => $_POST['ownership'],
         'chassis_no' => $chassis,
         'engine_no' => $n('engine_no'),
         'seating_capacity' => $n('seating_capacity'),
         'load_capacity_kg' => $n('load_capacity_kg'),
         'axle_count' => $n('axle_count'),
         'body_type' => $n('body_type'),
         'odometer' => $odo,
         'expected_kmpl' => $n('expected_kmpl'),
         'branch_id' => $n('branch_id'),
         'status' => $_POST['status'],
         'insurance_expiry' => $n('insurance_expiry'),
         'permit_expiry' => $n('permit_expiry'),
         'fitness_expiry' => $n('fitness_expiry'),
         'road_tax_expiry' => $n('road_tax_expiry'),
         'puc_expiry' => $n('puc_expiry'),
         'remarks' => $n('remarks')
      ];
      try {
         if ($id) {
            $set = implode(',', array_map(fn($k) => "$k=:$k", array_keys($f)));
            $f['id'] = $id;
            $pdo->prepare("UPDATE vehicles SET $set WHERE id=:id")->execute($f);
            audit('update', 'vehicle', $id, $reg);
         } else {
            $f['created_by'] = $_SESSION['uid'];
            $pdo->prepare('INSERT INTO vehicles(' . implode(',', array_keys($f)) . ') VALUES(:' . implode(',:', array_keys($f)) . ')')->execute($f);
            $id = (int) $pdo->lastInsertId();
            audit('create', 'vehicle', $id, $reg);
         }
         flash('Vehicle saved.');
         header('Location: view.php?id=' . $id);
         exit;
      } catch (PDOException $x) {
         error_log($x->getMessage());
         $err['_'] = 'Could not save. Check the values and try again.';
      }
   }
}
$branches = $pdo->query('SELECT id,name FROM branches WHERE is_active=1 ORDER BY name')->fetchAll();
$val = fn($k) => e($in[$k] ?? '');
$fe = fn($k) => isset($err[$k]) ? '<div class="text-danger small">' . e($err[$k]) . '</div>' : '';
$sel = fn($k, $o) => ($in[$k] ?? '') === $o ? 'selected' : '';
layout_top($id ? 'Edit vehicle' : 'Add vehicle', 'vehicles', ['Fleet', 'Vehicles', $id ? 'Edit' : 'Add']); ?>
<form method="post" class="cardx" novalidate><?= csrf_field() ?><?php if (isset($err['_'])): ?>
      <div class="alert alert-danger"><?= e($err['_']) ?></div><?php endif; ?>
   <div class="row g-3">
      <div class="col-12 col-md-4"><label class="form-label">Registration No *</label><input name="reg_no"
            class="form-control <?= isset($err['reg_no']) ? 'is-invalid' : '' ?>" value="<?= $val('reg_no') ?>"
            required><?= $fe('reg_no') ?></div>
      <div class="col-6 col-md-4"><label class="form-label">Fleet No</label><input name="fleet_no" class="form-control"
            value="<?= $val('fleet_no') ?>"></div>
      <div class="col-6 col-md-4"><label class="form-label">Vehicle Type *</label><select name="vehicle_type" id="vtype"
            class="form-select">
            <option value="">Select</option><?php foreach (TYPES as $k => $t): ?>
               <option value="<?= $k ?>" <?= $sel('vehicle_type', $k) ?>><?= e($t) ?></option><?php endforeach; ?>
         </select><?= $fe('vehicle_type') ?></div>
      <div class="col-6 col-md-3"><label class="form-label">Make</label><input name="make" class="form-control"
            value="<?= $val('make') ?>"></div>
      <div class="col-6 col-md-3"><label class="form-label">Model</label><input name="model" class="form-control"
            value="<?= $val('model') ?>"></div>
      <div class="col-6 col-md-3"><label class="form-label">Year</label><input type="number" name="mfg_year" min="1980"
            max="<?= date('Y') + 1 ?>" class="form-control" value="<?= $val('mfg_year') ?>"></div>
      <div class="col-6 col-md-3"><label class="form-label">Fuel</label><select name="fuel_type"
            class="form-select"><?php foreach (['diesel', 'petrol', 'cng', 'electric', 'other'] as $o): ?>
               <option value="<?= $o ?>" <?= $sel('fuel_type', $o) ?>><?= ucfirst($o) ?></option><?php endforeach; ?>
         </select></div>
      <div class="col-6 col-md-3"><label class="form-label">Ownership</label><select name="ownership"
            class="form-select"><?php foreach (['owned', 'leased', 'hired'] as $o): ?>
               <option value="<?= $o ?>" <?= $sel('ownership', $o) ?>><?= ucfirst($o) ?></option><?php endforeach; ?>
         </select></div>
      <div class="col-6 col-md-3"><label class="form-label">Branch</label><select name="branch_id" class="form-select">
            <option value="">—</option><?php foreach ($branches as $b): ?>
               <option value="<?= $b['id'] ?>" <?= (string) ($in['branch_id'] ?? '') === (string) $b['id'] ? 'selected' : '' ?>>
                  <?= e($b['name']) ?></option><?php endforeach; ?>
         </select></div>
      <div class="col-6 col-md-3"><label class="form-label">Status</label><select name="status"
            class="form-select"><?php foreach (STATUSES as $k => $t): ?>
               <option value="<?= $k ?>" <?= $sel('status', $k) ?>><?= e($t) ?></option><?php endforeach; ?>
         </select></div>
      <div class="col-6 col-md-3"><label class="form-label">Odometer (km)</label><input type="number" min="0"
            name="odometer" class="form-control <?= isset($err['odometer']) ? 'is-invalid' : '' ?>"
            value="<?= $val('odometer') ?: 0 ?>"><?= $fe('odometer') ?></div>
      <div class="col-6 col-md-3"><label class="form-label">Expected km/l</label><input type="number" step="0.01"
            name="expected_kmpl" class="form-control" value="<?= $val('expected_kmpl') ?>"></div>
      <div class="col-6 col-md-3"><label class="form-label">Chassis / VIN</label><input name="chassis_no"
            class="form-control" value="<?= $val('chassis_no') ?>"></div>
      <div class="col-6 col-md-3"><label class="form-label">Engine No</label><input name="engine_no"
            class="form-control" value="<?= $val('engine_no') ?>"></div>
      <div class="col-12">
         <hr class="my-1"><small class="text-secondary">Type-specific details</small>
      </div>
      <div class="col-6 col-md-3 tf" data-t="bus van car"><label class="form-label">Seating capacity</label><input
            type="number" name="seating_capacity" class="form-control" value="<?= $val('seating_capacity') ?>"></div>
      <div class="col-6 col-md-3 tf" data-t="truck other"><label class="form-label">Load capacity (kg)</label><input
            type="number" name="load_capacity_kg" class="form-control" value="<?= $val('load_capacity_kg') ?>"></div>
      <div class="col-6 col-md-3 tf" data-t="truck bus"><label class="form-label">Axle count</label><input type="number"
            name="axle_count" class="form-control" value="<?= $val('axle_count') ?>"></div>
      <div class="col-6 col-md-3 tf" data-t="truck bus van"><label class="form-label">Body type</label><input
            name="body_type" class="form-control" value="<?= $val('body_type') ?>"></div>
      <div class="col-12">
         <hr class="my-1"><small class="text-secondary">Document expiry dates</small>
      </div>
      <?php foreach (['insurance_expiry' => 'Insurance', 'permit_expiry' => 'Permit', 'fitness_expiry' => 'Fitness', 'road_tax_expiry' => 'Road tax', 'puc_expiry' => 'PUC'] as $k => $l): ?>
         <div class="col-6 col-md-2"><label class="form-label"><?= $l ?></label><input type="date" name="<?= $k ?>"
               class="form-control" value="<?= $val($k) ?>"></div><?php endforeach; ?>
      <div class="col-12"><label class="form-label">Remarks</label><textarea name="remarks" rows="2"
            class="form-control"><?= $val('remarks') ?></textarea></div>
   </div>
   <div class="mt-3 d-flex gap-2"><button class="btn btn-primary">Save vehicle</button><a href="index.php"
         class="btn btn-light">Cancel</a></div>
</form>
<script>const t = document.getElementById('vtype'); function sh() { document.querySelectorAll('.tf').forEach(e => e.style.display = (!t.value || e.dataset.t.split(' ').includes(t.value)) ? '' : 'none') } t.onchange = sh; sh();</script>
<?php layout_bottom();
