<?php // Business rules: pure functions are unit-tested in tests/run.php
$WARN = [];
function intervals_overlap(string $a1, string $a2, string $b1, string $b2): bool
{
  return $a1 < $b2 && $b1 < $a2;
}
function trip_distance(?int $s, ?int $e): ?int
{
  if ($s === null || $e === null || $e < $s)
    return null;
  return $e - $s;
}
/** Full-tank method. $f = chronological fills [odo,litres,full]. Mileage is reported only at a full fill that has a previous valid full fill;
 *  litres = all fuel added after the previous full fill up to and including this one. Non-increasing odometer resets the baseline. */
function calc_mileage(array $f): array
{
  $out = [];
  $prev = null;
  $acc = 0.0;
  foreach ($f as $x) {
    if ($prev === null) {
      if ($x['full']) {
        $prev = $x['odo'];
        $acc = 0.0;
      }
      continue;
    }
    if ($x['odo'] <= $prev) {
      $prev = $x['full'] ? $x['odo'] : null;
      $acc = 0.0;
      continue;
    }
    $acc += $x['litres'];
    if ($x['full'] && $acc > 0) {
      $km = $x['odo'] - $prev;
      $out[] = ['odo' => $x['odo'], 'km' => $km, 'litres' => $acc, 'kmpl' => round($km / $acc, 2)];
      $prev = $x['odo'];
      $acc = 0.0;
    }
  }
  return $out;
}
function expiry_category(?string $d, ?string $today = null): string
{
  if (!$d)
    return 'Not set';
  $n = (int) floor((strtotime($d) - strtotime($today ?? date('Y-m-d'))) / 86400);
  return $n < 0 ? 'Expired' : ($n <= 7 ? 'Within 7 days' : ($n <= 15 ? 'Within 15 days' : ($n <= 30 ? 'Within 30 days' : 'Valid')));
}
function csv_safe($v): string
{
  $v = (string) $v;
  return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
}
function trip_conflicts(PDO $p, int $veh, ?int $drv, string $from, string $to, int $excl = 0): array
{
  $from = date('Y-m-d H:i:s', strtotime($from));
  $to = date('Y-m-d H:i:s', strtotime($to));
  $m = [];
  $act = "status IN('scheduled','in_progress','delayed')";
  $q = $p->prepare("SELECT id FROM trips WHERE vehicle_id=? AND id<>? AND $act AND planned_departure<? AND (planned_arrival IS NULL OR planned_arrival>?)");
  $q->execute([$veh, $excl, $to, $from]);
  if ($q->fetch())
    $m[] = 'Vehicle already has an overlapping trip.';
  if ($drv) {
    $q = $p->prepare("SELECT id FROM trips WHERE driver_id=? AND id<>? AND $act AND planned_departure<? AND (planned_arrival IS NULL OR planned_arrival>?)");
    $q->execute([$drv, $excl, $to, $from]);
    if ($q->fetch())
      $m[] = 'Driver already has an overlapping trip.';
  }
  $q = $p->prepare('SELECT status FROM vehicles WHERE id=?');
  $q->execute([$veh]);
  $s = $q->fetchColumn();
  if (in_array($s, ['maintenance', 'accident_hold', 'inactive', 'disposed'], true))
    $m[] = "Vehicle is '$s' and not cleared for trips.";
  return $m;
}
function tyre_fit_errors(PDO $p, int $tyre, int $veh, string $pos, int $excl = 0): array
{
  $e = [];
  $q = $p->prepare('SELECT status FROM tyres WHERE id=?');
  $q->execute([$tyre]);
  $s = $q->fetchColumn();
  if (!in_array($s, ['in_stock', 'reusable'], true))
    $e[] = "Tyre status is '$s'; only in-stock or reusable tyres can be fitted.";
  $q = $p->prepare('SELECT id FROM tyre_fitments WHERE tyre_id=? AND removed_on IS NULL AND id<>?');
  $q->execute([$tyre, $excl]);
  if ($q->fetch())
    $e[] = 'Tyre already has an active fitment.';
  $q = $p->prepare('SELECT id FROM tyre_fitments WHERE vehicle_id=? AND position=? AND removed_on IS NULL AND id<>?');
  $q->execute([$veh, $pos, $excl]);
  if ($q->fetch())
    $e[] = 'That wheel position already has an active tyre.';
  return $e;
}
function vset(PDO $p, string $sql, array $a = []): void
{
  $p->prepare($sql)->execute($a);
}
// ---- validation hooks: return [field=>message]; may adjust $d ----
function v_trips(array &$d, int $id, array $old, PDO $p): array
{
  $e = [];
  if ($d['planned_arrival'] && $d['planned_arrival'] <= $d['planned_departure'])
    $e['planned_arrival'] = 'Arrival must be after departure.';
  if ($d['start_odo'] !== null && $d['end_odo'] !== null && $d['end_odo'] < $d['start_odo'])
    $e['end_odo'] = 'End odometer cannot be lower than start odometer.';
  if ($d['status'] === 'completed' && ($d['start_odo'] === null || $d['end_odo'] === null))
    $e['end_odo'] = 'Start and end odometer are required to complete a trip.';
  $v = $p->prepare('SELECT odometer FROM vehicles WHERE id=?');
  $v->execute([$d['vehicle_id']]);
  $vo = (int) $v->fetchColumn();
  if ($d['start_odo'] !== null && $d['start_odo'] < $vo) {
    global $WARN;
    $WARN[] = "Start odometer ({$d['start_odo']}) is below the vehicle's recorded odometer ($vo) - flagged for review.";
  }
  if (!$e && in_array($d['status'], ['scheduled', 'in_progress', 'delayed'], true)) {
    $c = trip_conflicts($p, (int) $d['vehicle_id'], $d['driver_id'] ? (int) $d['driver_id'] : null, $d['planned_departure'], $d['planned_arrival'] ?? '9999-12-30 00:00:00', $id);
    if ($c) {
      if (!empty($_POST['override']) && can('trips.override')) {
        audit('override', 'trip', $id ?: null, implode(' ', $c));
      } else
        $e['vehicle_id'] = implode(' ', $c) . (can('trips.override') ? ' Tick the override box to proceed.' : '');
    }
  }
  return $e;
}
function a_trips(array $d, int $id, array $old, PDO $p): void
{
  $v = (int) $d['vehicle_id'];
  $dr = $d['driver_id'];
  $st = $d['status'];
  if ($st === 'in_progress') {
    vset($p, "UPDATE vehicles SET status='on_trip' WHERE id=? AND status IN('available','reserved','on_trip')", [$v]);
    if ($dr)
      vset($p, "UPDATE drivers SET availability='on_trip' WHERE id=?", [$dr]);
  } elseif ($st === 'completed' || $st === 'cancelled') {
    $x = $p->prepare("SELECT COUNT(*) FROM trips WHERE vehicle_id=? AND status='in_progress'");
    $x->execute([$v]);
    if (!$x->fetchColumn())
      vset($p, "UPDATE vehicles SET status='available' WHERE id=? AND status='on_trip'", [$v]);
    if ($dr) {
      $x = $p->prepare("SELECT COUNT(*) FROM trips WHERE driver_id=? AND status='in_progress'");
      $x->execute([$dr]);
      if (!$x->fetchColumn())
        vset($p, "UPDATE drivers SET availability='available' WHERE id=? AND availability='on_trip'", [$dr]);
    }
    if ($st === 'completed' && $d['end_odo'] !== null)
      vset($p, 'UPDATE vehicles SET odometer=GREATEST(odometer,?) WHERE id=?', [$d['end_odo'], $v]);
  }
}
function v_fuel(array &$d, int $id, array $old, PDO $p): array
{
  $e = [];
  if ((float) $d['litres'] <= 0)
    $e['litres'] = 'Litres must be greater than zero.';
  if ((float) $d['price_per_litre'] <= 0)
    $e['price_per_litre'] = 'Price must be greater than zero.';
  $d['total_amount'] = round((float) $d['litres'] * (float) $d['price_per_litre'], 2);
  $q = $p->prepare('SELECT odometer FROM fuel_entries WHERE vehicle_id=? AND id<>? AND filled_at<? ORDER BY filled_at DESC LIMIT 1');
  $q->execute([$d['vehicle_id'], $id, $d['filled_at']]);
  $pv = $q->fetchColumn();
  if ($pv !== false && $d['odometer'] < $pv)
    $e['odometer'] = "Odometer is lower than the previous fill ($pv).";
  $q = $p->prepare('SELECT odometer FROM fuel_entries WHERE vehicle_id=? AND id<>? AND filled_at>? ORDER BY filled_at LIMIT 1');
  $q->execute([$d['vehicle_id'], $id, $d['filled_at']]);
  $nx = $q->fetchColumn();
  if ($nx !== false && $d['odometer'] > $nx)
    $e['odometer'] = "Odometer is higher than the next fill ($nx).";
  if ($d['receipt_no']) {
    $q = $p->prepare('SELECT id FROM fuel_entries WHERE receipt_no=? AND COALESCE(vendor,"")=? AND id<>?');
    $q->execute([$d['receipt_no'], $d['vendor'] ?? '', $id]);
    if ($q->fetch())
      $e['receipt_no'] = 'Duplicate receipt number for this station.';
  } else {
    global $WARN;
    $WARN[] = 'Fuel entry saved without a receipt number.';
  }
  return $e;
}
function a_fuel(array $d, int $id, array $old, PDO $p): void
{
  vset($p, 'UPDATE vehicles SET odometer=GREATEST(odometer,?) WHERE id=?', [$d['odometer'], $d['vehicle_id']]);
}
function v_maintenance(array &$d, int $id, array $old, PDO $p): array
{
  foreach (['parts_cost', 'labour_cost', 'other_cost'] as $k) {
    $d[$k] = $d[$k] ?? 0;
    if ($d[$k] < 0)
      return [$k => 'Cost cannot be negative.'];
  }
  $d['total_cost'] = round($d['parts_cost'] + $d['labour_cost'] + $d['other_cost'], 2);
  return [];
}
function a_maintenance(array $d, int $id, array $old, PDO $p): void
{
  $v = (int) $d['vehicle_id'];
  if ($d['next_service_date'] || $d['next_service_km'])
    vset($p, 'UPDATE vehicles SET next_service_date=?,next_service_km=? WHERE id=?', [$d['next_service_date'], $d['next_service_km'], $v]);
  if ($d['status'] === 'open')
    vset($p, "UPDATE vehicles SET status='maintenance' WHERE id=? AND status='available'", [$v]);
  else {
    $x = $p->prepare("SELECT COUNT(*) FROM maintenance_records WHERE vehicle_id=? AND status='open'");
    $x->execute([$v]);
    if (!$x->fetchColumn())
      vset($p, "UPDATE vehicles SET status='available' WHERE id=? AND status='maintenance'", [$v]);
  }
}
function a_breakdowns(array $d, int $id, array $old, PDO $p): void
{
  if ($d['status'] === 'open')
    vset($p, "UPDATE vehicles SET status='maintenance' WHERE id=? AND status IN('available','on_trip')", [$d['vehicle_id']]);
}
function v_documents(array &$d, int $id, array $old, PDO $p): array
{
  $e = [];
  if (!$d['vehicle_id'] && !$d['driver_id'])
    $e['vehicle_id'] = 'Select a vehicle or a driver.';
  if ($d['issue_date'] && $d['expiry_date'] && $d['expiry_date'] < $d['issue_date'])
    $e['expiry_date'] = 'Expiry is before issue date.';
  $d['uploaded_by'] = $_SESSION['uid'] ?? null;
  return $e;
}
function v_expenses(array &$d, int $id, array $old, PDO $p): array
{
  return (float) $d['amount'] > 0 ? [] : ['amount' => 'Amount must be greater than zero.'];
}
function v_loan_payments(array &$d, int $id, array $old, PDO $p): array
{
  return (float) $d['amount'] > 0 ? [] : ['amount' => 'Amount must be greater than zero.'];
}
function v_assignments(array &$d, int $id, array $old, PDO $p): array
{
  return ($d['end_date'] && $d['end_date'] < $d['start_date']) ? ['end_date' => 'End is before start.'] : [];
}
function a_assignments(array $d, int $id, array $old, PDO $p): void
{
  if (!$d['end_date'])
    vset($p, 'UPDATE vehicles SET driver_id=? WHERE id=?', [$d['driver_id'], $d['vehicle_id']]);
}
function v_fitments(array &$d, int $id, array $old, PDO $p): array
{
  $e = [];
  if ($d['removed_on']) {
    if ($d['removed_on'] < $d['fitted_on'])
      $e['removed_on'] = 'Removal date is before fitment date.';
    if ($d['odo_fitted'] !== null && $d['odo_removed'] !== null && $d['odo_removed'] < $d['odo_fitted'])
      $e['odo_removed'] = 'Removal odometer is lower than fitment odometer.';
  }
  if (!$id) {
    foreach (tyre_fit_errors($p, (int) $d['tyre_id'], (int) $d['vehicle_id'], $d['position']) as $m)
      $e['tyre_id'] = ($e['tyre_id'] ?? '') . $m . ' ';
  } elseif (empty($d['removed_on'])) {
    foreach (tyre_fit_errors($p, (int) $d['tyre_id'], (int) $d['vehicle_id'], $d['position'], $id) as $m)
      if (strpos($m, 'status') === false)
        $e['tyre_id'] = ($e['tyre_id'] ?? '') . $m . ' ';
  }
  return $e;
}
function a_fitments(array $d, int $id, array $old, PDO $p): void
{
  $me = (int) ($_SESSION['uid'] ?? 0);
  if (!$old) {
    vset($p, "UPDATE tyres SET status='fitted' WHERE id=?", [$d['tyre_id']]);
    vset($p, "INSERT INTO tyre_movements(tyre_id,action,vehicle_id,position,user_id,note) VALUES(?,?,?,?,?,?)", [$d['tyre_id'], 'fit', $d['vehicle_id'], $d['position'], $me, 'Fitted']);
  } elseif (empty($old['removed_on']) && $d['removed_on']) {
    vset($p, "UPDATE tyres SET status='reusable' WHERE id=?", [$d['tyre_id']]);
    vset($p, "INSERT INTO tyre_movements(tyre_id,action,vehicle_id,position,user_id,note) VALUES(?,?,?,?,?,?)", [$d['tyre_id'], 'remove', $d['vehicle_id'], $d['position'], $me, $d['removal_reason']]);
  }
}
function v_disposals(array &$d, int $id, array $old, PDO $p): array
{
  $q = $p->prepare('SELECT status FROM tyres WHERE id=?');
  $q->execute([$d['tyre_id']]);
  $s = $q->fetchColumn();
  $d['approved_by'] = $_SESSION['uid'] ?? null;
  return in_array($s, ['fitted', 'scrapped', 'sold'], true) && !$id ? ['tyre_id' => "Tyre is '$s' and cannot be disposed. Remove it from the vehicle first."] : [];
}
function a_disposals(array $d, int $id, array $old, PDO $p): void
{
  vset($p, 'UPDATE tyres SET status=? WHERE id=?', [$d['disposal_type'] === 'sale' ? 'sold' : 'scrapped', $d['tyre_id']]);
  if (!$old)
    vset($p, "INSERT INTO tyre_movements(tyre_id,action,user_id,note) VALUES(?,?,?,?)", [$d['tyre_id'], $d['disposal_type'], $_SESSION['uid'] ?? null, $d['reason']]);
}
function a_retreading(array $d, int $id, array $old, PDO $p): void
{
  vset($p, 'UPDATE tyres SET status=? WHERE id=? AND status IN(\'in_stock\',\'reusable\',\'under_retreading\')', [$d['returned_on'] ? 'reusable' : 'under_retreading', $d['tyre_id']]);
}
function v_users(array &$d, int $id, array $old, PDO $p): array
{
  if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL))
    return ['email' => 'Invalid email.'];
  if (!$id && strlen((string) ($_POST['password_hash'] ?? '')) < 10)
    return ['password_hash' => 'Password of 10+ characters required.'];
  if ($_POST['password_hash'] !== '' && strlen($_POST['password_hash']) < 10)
    return ['password_hash' => 'Minimum 10 characters.'];
  return [];
}
