<?php // CLI tests: php tests/run.php  (needs the imported database; all DB changes are rolled back)
chdir(dirname(__DIR__));
require 'includes/bootstrap.php';
require 'includes/rules.php';
require 'includes/reports.php';
$pass = 0;
$fail = 0;
function t(string $n, bool $ok)
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? 'PASS' : 'FAIL') . " - $n\n";
}
// --- mileage (full-tank method)
$m = calc_mileage([['odo' => 1000, 'litres' => 50, 'full' => true], ['odo' => 1300, 'litres' => 20, 'full' => false], ['odo' => 1500, 'litres' => 40, 'full' => true]]);
t('mileage: partial fill folded into next full fill (500km/60L=8.33)', count($m) === 1 && $m[0]['km'] === 500 && $m[0]['kmpl'] == 8.33);
t('mileage: no prior full fill -> no result', calc_mileage([['odo' => 100, 'litres' => 10, 'full' => false], ['odo' => 300, 'litres' => 20, 'full' => true]]) === []);
t('mileage: decreasing odometer resets baseline', calc_mileage([['odo' => 1000, 'litres' => 50, 'full' => true], ['odo' => 900, 'litres' => 30, 'full' => true]]) === []);
t('mileage: two consecutive full fills', count(calc_mileage([['odo' => 0, 'litres' => 1, 'full' => true], ['odo' => 400, 'litres' => 40, 'full' => true], ['odo' => 760, 'litres' => 40, 'full' => true]])) === 2);
// --- trip distance
t('trip distance 150-100=50', trip_distance(100, 150) === 50);
t('trip distance rejects end<start', trip_distance(150, 100) === null);
// --- expiry
t('expiry: expired', expiry_category('2026-01-01', '2026-02-01') === 'Expired');
t('expiry: 7 days', expiry_category('2026-02-08', '2026-02-01') === 'Within 7 days');
t('expiry: 15 days', expiry_category('2026-02-16', '2026-02-01') === 'Within 15 days');
t('expiry: 30 days', expiry_category('2026-03-03', '2026-02-01') === 'Within 30 days');
t('expiry: valid', expiry_category('2027-01-01', '2026-02-01') === 'Valid');
t('expiry: not set', expiry_category(null) === 'Not set');
// --- misc
t('overlap detection', intervals_overlap('2026-01-01 08:00', '2026-01-01 12:00', '2026-01-01 11:00', '2026-01-01 15:00') && !intervals_overlap('2026-01-01 08:00', '2026-01-01 10:00', '2026-01-01 10:00', '2026-01-01 12:00'));
t('csv formula injection escaped', csv_safe('=HYPERLINK("x")') === "'=HYPERLINK(\"x\")" && csv_safe('+1') === "'+1" && csv_safe('abc') === 'abc');
// --- permissions
$_SESSION['role'] = 'readonly';
t('perm: readonly can view vehicles, cannot edit', can('vehicles.view') && !can('vehicles.edit'));
$_SESSION['role'] = 'operations';
t('perm: operations edits trips, no override, no users', can('trips.edit') && !can('trips.override') && !can('admin.manage'));
$_SESSION['role'] = 'accounts';
t('perm: accounts approves expenses, cannot edit trips', can('expenses.approve') && !can('trips.edit'));
$_SESSION['role'] = 'fleet_manager';
t('perm: fleet manager override, no admin', can('trips.override') && !can('admin.manage'));
$_SESSION['role'] = 'super_admin';
t('perm: super admin all', can('admin.manage') && can('anything.edit'));
// --- database rules (rolled back)
$p = db();
$p->beginTransaction();
try {
    $p->exec("INSERT INTO vehicles(reg_no,vehicle_type) VALUES('TEST-1','bus'),('TEST-2','truck')");
    $v1 = (int) $p->query("SELECT id FROM vehicles WHERE reg_no='TEST-1'")->fetchColumn();
    $v2 = (int) $p->query("SELECT id FROM vehicles WHERE reg_no='TEST-2'")->fetchColumn();
    $p->exec("INSERT INTO drivers(name) VALUES('Test Driver')");
    $d1 = (int) $p->lastInsertId();
    $p->exec("INSERT INTO trips(vehicle_id,driver_id,planned_departure,planned_arrival,status) VALUES($v1,$d1,'2030-01-01 08:00','2030-01-01 12:00','scheduled')");
    t('trip conflict: same vehicle overlapping', count(trip_conflicts($p, $v1, null, '2030-01-01 10:00', '2030-01-01 14:00')) === 1);
    t('trip conflict: same driver, other vehicle', count(trip_conflicts($p, $v2, $d1, '2030-01-01 09:00', '2030-01-01 11:00')) === 1);
    t('trip conflict: back-to-back allowed', trip_conflicts($p, $v1, $d1, '2030-01-01 12:00', '2030-01-01 15:00') === []);
    $p->exec("UPDATE vehicles SET status='maintenance' WHERE id=$v2");
    t('trip conflict: vehicle under maintenance blocked', count(trip_conflicts($p, $v2, null, '2031-01-01 08:00', '2031-01-01 09:00')) === 1);
    try {
        $p->exec("INSERT INTO trips(vehicle_id,start_odo,end_odo) VALUES($v1,500,400)");
        t('DB rejects end_odo < start_odo', false);
    } catch (PDOException $x) {
        t('DB rejects end_odo < start_odo', true);
    }
    $p->exec("INSERT INTO trips(vehicle_id,start_odo,end_odo) VALUES($v1,100,160)");
    t('DB distance_km = end-start', (int) $p->query("SELECT distance_km FROM trips WHERE vehicle_id=$v1 AND start_odo=100")->fetchColumn() === 60);
    // tyres
    $p->exec("INSERT INTO tyres(tyre_code,status) VALUES('T-A','in_stock'),('T-B','in_stock')");
    $ta = (int) $p->query("SELECT id FROM tyres WHERE tyre_code='T-A'")->fetchColumn();
    $tb = (int) $p->query("SELECT id FROM tyres WHERE tyre_code='T-B'")->fetchColumn();
    t('tyre fit: allowed when in stock', tyre_fit_errors($p, $ta, $v1, 'FL') === []);
    $p->exec("INSERT INTO tyre_fitments(tyre_id,vehicle_id,position,fitted_on) VALUES($ta,$v1,'FL','2030-01-01')");
    $p->exec("UPDATE tyres SET status='fitted' WHERE id=$ta");
    t('tyre fit: fitted tyre cannot be fitted again', count(tyre_fit_errors($p, $ta, $v2, 'RR')) >= 1);
    t('tyre fit: occupied position blocked', count(tyre_fit_errors($p, $tb, $v1, 'FL')) === 1);
    try {
        $p->exec("SAVEPOINT s");
        $p->exec("INSERT INTO tyre_fitments(tyre_id,vehicle_id,position,fitted_on) VALUES($ta,$v2,'RR','2030-01-02')");
        t('DB unique blocks 2nd active fitment of one tyre', false);
    } catch (PDOException $x) {
        $p->exec("ROLLBACK TO SAVEPOINT s");
        t('DB unique blocks 2nd active fitment of one tyre', true);
    }
    $p->exec("UPDATE tyre_fitments SET removed_on='2030-02-01' WHERE tyre_id=$ta");
    $p->exec("UPDATE tyres SET status='reusable' WHERE id=$ta");
    t('tyre refit allowed after removal', tyre_fit_errors($p, $ta, $v2, 'RR') === []);
    // expense totals: no double counting
    $p->exec("INSERT INTO fuel_entries(vehicle_id,filled_at,litres,price_per_litre,total_amount,odometer) VALUES($v1,'2030-03-05 10:00',10,100,1000,500)");
    $p->exec("INSERT INTO maintenance_records(vehicle_id,service_type,service_date,total_cost) VALUES($v1,'routine','2030-03-06',300)");
    $p->exec("INSERT INTO expenses(vehicle_id,expense_date,category,amount,approval_status) VALUES($v1,'2030-03-07','toll',200,'approved'),($v1,'2030-03-08','toll',500,'pending'),($v1,'2030-03-09','loan_emi',999,'approved')");
    $p->exec("INSERT INTO vehicle_loans(vehicle_id,lender,principal,emi_amount) VALUES($v1,'Bank',100000,4000)");
    $ln = (int) $p->lastInsertId();
    $p->exec("INSERT INTO loan_payments(loan_id,paid_on,amount) VALUES($ln,'2030-03-10',4000)");
    $r = run_report($p, 'expenses_monthly', ['from' => '2030-03-01', 'to' => '2030-03-31', 'vehicle' => $v1]);
    $sum = array_sum(array_column($r, 'amount'));
    t('expense total = fuel1000+maint300+approved toll200+EMI payment4000 = 5500 (pending & loan_emi expense excluded)', abs($sum - 5500) < 0.01);
    $r = run_report($p, 'vehicle_expenses', ['from' => '2030-03-01', 'to' => '2030-03-31', 'vehicle' => $v1]);
    t('vehicle-wise report agrees', abs($r[0]['fuel'] + $r[0]['maintenance'] + $r[0]['other_approved'] + $r[0]['loan_emi'] - 5500) < 0.01);
    // doc expiry report
    $p->exec("UPDATE vehicles SET insurance_expiry=CURDATE()-INTERVAL 1 DAY WHERE id=$v1");
    $r = run_report($p, 'doc_expiry', []);
    $f = array_filter($r, fn($x) => $x['subject'] === 'TEST-1' && $x['document'] === 'Insurance');
    t('doc expiry report flags expired insurance', reset($f)['status'] === 'Expired');
    // duplicates
    try {
        $p->exec("SAVEPOINT s2");
        $p->exec("INSERT INTO vehicles(reg_no,vehicle_type) VALUES('TEST-1','bus')");
        t('DB rejects duplicate registration', false);
    } catch (PDOException $x) {
        $p->exec("ROLLBACK TO SAVEPOINT s2");
        t('DB rejects duplicate registration', true);
    }
} catch (Throwable $x) {
    t('DB test crashed: ' . $x->getMessage(), false);
}
$p->rollBack();
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
