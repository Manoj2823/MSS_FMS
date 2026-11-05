<?php require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login('dashboard.view', true);
header('Content-Type: application/json');
$pdo = db();
$from = $_GET['from'] ?? date('Y-m-01', strtotime('-5 months'));
$to = $_GET['to'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    http_response_code(400);
    exit('{"error":"bad date"}');
}
$branch = (int) ($_GET['branch'] ?? 0);
$type = array_key_exists($_GET['type'] ?? '', TYPES) ? $_GET['type'] : '';
$vw = 'v.deleted_at IS NULL';
$vp = [];
if ($branch) {
    $vw .= ' AND v.branch_id=?';
    $vp[] = $branch;
}
if ($type) {
    $vw .= ' AND v.vehicle_type=?';
    $vp[] = $type;
}
$q = function (string $sql, array $p = []) use ($pdo) {
    $s = $pdo->prepare($sql);
    $s->execute($p);
    return $s->fetchAll(); };
$one = fn($sql, $p = []) => $q($sql, $p)[0] ?? [];
$st = $q("SELECT v.status k,COUNT(*) n FROM vehicles v WHERE $vw GROUP BY v.status", $vp);
$ty = $q("SELECT v.vehicle_type k,COUNT(*) n FROM vehicles v WHERE $vw GROUP BY v.vehicle_type", $vp);
$stm = array_column($st, 'n', 'k');
$total = array_sum($stm);
$drivers = (int) $pdo->query('SELECT COUNT(*) FROM drivers WHERE deleted_at IS NULL')->fetchColumn();
$fp = array_merge($vp, [$from, $to . ' 23:59:59']);
$fuel = $q("SELECT DATE_FORMAT(f.filled_at,'%Y-%m') m,SUM(f.litres) l,SUM(f.total_amount) a FROM fuel_entries f JOIN vehicles v ON v.id=f.vehicle_id WHERE $vw AND f.filled_at BETWEEN ? AND ? GROUP BY m ORDER BY m", $fp);
$mnt = $q("SELECT DATE_FORMAT(m.service_date,'%Y-%m') m,SUM(m.total_cost) a FROM maintenance_records m JOIN vehicles v ON v.id=m.vehicle_id WHERE $vw AND m.service_date BETWEEN ? AND ? GROUP BY m", array_merge($vp, [$from, $to]));
$ex = $q("SELECT DATE_FORMAT(e.expense_date,'%Y-%m') m,e.category c,SUM(e.amount) a FROM expenses e LEFT JOIN vehicles v ON v.id=e.vehicle_id WHERE e.approval_status='approved' AND e.expense_date BETWEEN ? AND ? " . ($vp ? "AND $vw" : '') . " GROUP BY m,c", array_merge([$from, $to], $vp));
$tr = $q("SELECT t.status k,COUNT(*) n,COALESCE(SUM(t.distance_km),0) km FROM trips t JOIN vehicles v ON v.id=t.vehicle_id WHERE $vw AND t.planned_departure BETWEEN ? AND ? GROUP BY t.status", $fp);
$al = [];
$days = 30;
foreach (['insurance_expiry' => 'Insurance', 'permit_expiry' => 'Permit', 'fitness_expiry' => 'Fitness', 'road_tax_expiry' => 'Road tax', 'puc_expiry' => 'PUC', 'next_service_date' => 'Service due'] as $c => $l) {
    foreach ($q("SELECT v.id,v.reg_no,v.$c d, DATEDIFF(v.$c, CURDATE()) dl FROM vehicles v WHERE $vw AND v.$c IS NOT NULL AND v.$c<=CURDATE()+INTERVAL $days DAY ORDER BY v.$c LIMIT 100", $vp) as $r) {
        $al[] = ['id' => $r['id'], 'reg' => $r['reg_no'], 'type' => $l, 'date' => $r['d'], 'days_left' => (int) $r['dl'], 'is_driver' => false];
    }
}
foreach ($q("SELECT d.id, d.name, d.licence_no, d.licence_expiry d, DATEDIFF(d.licence_expiry, CURDATE()) dl FROM drivers d WHERE d.deleted_at IS NULL AND d.licence_expiry IS NOT NULL AND d.licence_expiry <= CURDATE()+INTERVAL $days DAY ORDER BY d.licence_expiry LIMIT 100") as $r) {
    $al[] = ['id' => $r['id'], 'reg' => $r['name'], 'type' => 'Driver Licence', 'date' => $r['d'], 'days_left' => (int) $r['dl'], 'is_driver' => true];
}
usort($al, fn($a, $b) => strcmp($a['date'], $b['date']));

$urgent_alerts = [];
foreach ($al as $item) {
    if ($item['days_left'] <= 7) {
        $dl = $item['days_left'];
        $badge = $dl < 0 ? 'Expired' : ($dl === 0 ? 'Today' : ($dl === 1 ? 'Tomorrow' : "{$dl}d left"));
        $urgent_alerts[] = [
            'id' => $item['id'],
            'target' => $item['reg'],
            'type' => $item['type'],
            'date' => $item['date'],
            'days_left' => $dl,
            'badge' => $badge,
            'is_driver' => $item['is_driver'],
            'url' => $item['is_driver'] ? '#' : url("modules/vehicles/view.php?id={$item['id']}")
        ];
    }
}

$licn = (int) $one("SELECT COUNT(*) n FROM drivers WHERE deleted_at IS NULL AND licence_expiry<=CURDATE()+INTERVAL 30 DAY")['n'];
$mFuel = (float) $one("SELECT COALESCE(SUM(f.total_amount),0) s FROM fuel_entries f JOIN vehicles v ON v.id=f.vehicle_id WHERE $vw AND DATE_FORMAT(f.filled_at,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')", $vp)['s'];
$mMnt = (float) $one("SELECT COALESCE(SUM(m.total_cost),0) s FROM maintenance_records m JOIN vehicles v ON v.id=m.vehicle_id WHERE $vw AND DATE_FORMAT(m.service_date,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')", $vp)['s'];
$mOth = (float) $one("SELECT COALESCE(SUM(e.amount),0) s FROM expenses e LEFT JOIN vehicles v ON v.id=e.vehicle_id WHERE e.approval_status='approved' AND DATE_FORMAT(e.expense_date,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m') " . ($vp ? "AND $vw" : ''), $vp)['s'];
$recent = $q("SELECT v.id,v.reg_no,v.vehicle_type,v.created_at FROM vehicles v WHERE $vw ORDER BY v.id DESC LIMIT 5", $vp);
echo json_encode([
    'total' => $total,
    'status' => $stm,
    'types' => array_column($ty, 'n', 'k'),
    'drivers' => $drivers,
    'licence_expiring' => $licn,
    'alerts' => $al,
    'urgent_alerts' => $urgent_alerts,
    'urgent_count' => count($urgent_alerts),
    'month_expense' => $mFuel + $mMnt + $mOth,
    'fuel' => $fuel,
    'maintenance' => $mnt,
    'expenses' => $ex,
    'trips' => $tr,
    'recent' => $recent,
    'active_trips' => (int) ($stm['on_trip'] ?? 0)
]);
