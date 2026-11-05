<?php
function report_defs(): array
{
    $dr = "BETWEEN :from AND :to";
    $vf = "(:vehicle=0 OR %s=:vehicle)";
    return [
        'vehicle_register' => ['t' => 'Vehicle Register', 'sql' => "SELECT v.reg_no,v.fleet_no,v.vehicle_type type,v.make,v.model,v.status,v.odometer,b.name branch FROM vehicles v LEFT JOIN branches b ON b.id=v.branch_id WHERE v.deleted_at IS NULL AND (:vehicle=0 OR v.id=:vehicle) ORDER BY v.reg_no", 'f' => ['vehicle']],
        'vehicle_status' => ['t' => 'Vehicle Status / Utilisation', 'sql' => "SELECT status,vehicle_type type,COUNT(*) vehicles FROM vehicles WHERE deleted_at IS NULL GROUP BY status,vehicle_type ORDER BY status", 'f' => []],
        'driver_register' => ['t' => 'Driver Register', 'sql' => "SELECT d.name,d.mobile,d.licence_no,d.licence_expiry,d.availability,b.name branch FROM drivers d LEFT JOIN branches b ON b.id=d.branch_id WHERE d.deleted_at IS NULL ORDER BY d.name", 'f' => []],
        'trips' => ['t' => 'Trip History & Distance', 'sql' => "SELECT t.id,v.reg_no,d.name driver,t.origin,t.destination,t.planned_departure,t.status,t.start_odo,t.end_odo,t.distance_km FROM trips t JOIN vehicles v ON v.id=t.vehicle_id LEFT JOIN drivers d ON d.id=t.driver_id WHERE DATE(t.planned_departure) $dr AND " . sprintf($vf, 't.vehicle_id') . " ORDER BY t.planned_departure DESC", 'f' => ['date', 'vehicle']],
        'fuel_consumption' => ['t' => 'Fuel Consumption & Spend', 'sql' => "SELECT DATE_FORMAT(f.filled_at,'%Y-%m') month,v.reg_no,SUM(f.litres) litres,SUM(f.total_amount) amount FROM fuel_entries f JOIN vehicles v ON v.id=f.vehicle_id WHERE DATE(f.filled_at) $dr AND " . sprintf($vf, 'f.vehicle_id') . " GROUP BY month,v.reg_no ORDER BY month,v.reg_no", 'f' => ['date', 'vehicle']],
        'fuel_efficiency' => ['t' => 'Fuel Efficiency (km/l)', 'fn' => 'rep_fuel_eff', 'f' => ['date', 'vehicle']],
        'maintenance' => ['t' => 'Maintenance History', 'sql' => "SELECT m.service_date,v.reg_no,m.service_type,m.workshop,m.total_cost,m.status,m.next_service_date FROM maintenance_records m JOIN vehicles v ON v.id=m.vehicle_id WHERE m.service_date $dr AND " . sprintf($vf, 'm.vehicle_id') . " ORDER BY m.service_date DESC", 'f' => ['date', 'vehicle']],
        'breakdown_history' => ['t' => 'Breakdown History', 'sql' => "SELECT b.breakdown_date,v.reg_no,b.location,b.downtime_hours,b.towing_cost,b.repair_cost,b.status FROM breakdown_records b JOIN vehicles v ON v.id=b.vehicle_id WHERE DATE(b.breakdown_date) $dr AND " . sprintf($vf, 'b.vehicle_id') . " ORDER BY b.breakdown_date DESC", 'f' => ['date', 'vehicle']],
        'doc_expiry' => [
            't' => 'Document Expiry Alerts',
            'post' => 'expiry',
            'sql' => "SELECT * FROM (
  SELECT reg_no subject,'Insurance' document,NULL number,insurance_expiry expiry FROM vehicles WHERE deleted_at IS NULL UNION ALL SELECT reg_no,'Permit',NULL,permit_expiry FROM vehicles WHERE deleted_at IS NULL
  UNION ALL SELECT reg_no,'Fitness',NULL,fitness_expiry FROM vehicles WHERE deleted_at IS NULL UNION ALL SELECT reg_no,'Road tax',NULL,road_tax_expiry FROM vehicles WHERE deleted_at IS NULL
  UNION ALL SELECT reg_no,'PUC',NULL,puc_expiry FROM vehicles WHERE deleted_at IS NULL UNION ALL SELECT reg_no,'Service due',NULL,next_service_date FROM vehicles WHERE deleted_at IS NULL
  UNION ALL SELECT name,'Driver licence',licence_no,licence_expiry FROM drivers WHERE deleted_at IS NULL
  UNION ALL SELECT COALESCE(v.reg_no,dr.name),d.doc_type,d.doc_number,d.expiry_date FROM vehicle_documents d LEFT JOIN vehicles v ON v.id=d.vehicle_id LEFT JOIN drivers dr ON dr.id=d.driver_id WHERE d.deleted_at IS NULL) x WHERE expiry IS NOT NULL ORDER BY expiry",
            'f' => []
        ],
        // Ledger without double counting: fuel + maintenance from their own tables, approved expenses (excluding loan_emi), loan payments from loan_payments.
        'expenses_monthly' => [
            't' => 'Monthly Expenses (all categories)',
            'sql' => "SELECT m month,category,SUM(amt) amount FROM (
  SELECT DATE_FORMAT(filled_at,'%Y-%m') m,'fuel' category,total_amount amt FROM fuel_entries WHERE DATE(filled_at) $dr AND " . sprintf($vf, 'vehicle_id') . "
  UNION ALL SELECT DATE_FORMAT(service_date,'%Y-%m'),'maintenance',total_cost FROM maintenance_records WHERE service_date $dr AND " . sprintf($vf, 'vehicle_id') . "
  UNION ALL SELECT DATE_FORMAT(expense_date,'%Y-%m'),category,amount FROM expenses WHERE approval_status='approved' AND category<>'loan_emi' AND expense_date $dr AND " . sprintf($vf, 'vehicle_id') . "
  UNION ALL SELECT DATE_FORMAT(p.paid_on,'%Y-%m'),'loan_emi',p.amount FROM loan_payments p JOIN vehicle_loans l ON l.id=p.loan_id WHERE p.paid_on $dr AND " . sprintf($vf, 'l.vehicle_id') . ") x GROUP BY m,category ORDER BY m,category",
            'f' => ['date', 'vehicle']
        ],
        'vehicle_expenses' => [
            't' => 'Vehicle-wise Expenses',
            'sql' => "SELECT v.reg_no,
  (SELECT COALESCE(SUM(total_amount),0) FROM fuel_entries WHERE vehicle_id=v.id AND DATE(filled_at) $dr) fuel,
  (SELECT COALESCE(SUM(total_cost),0) FROM maintenance_records WHERE vehicle_id=v.id AND service_date $dr) maintenance,
  (SELECT COALESCE(SUM(amount),0) FROM expenses WHERE vehicle_id=v.id AND approval_status='approved' AND category<>'loan_emi' AND expense_date $dr) other_approved,
  (SELECT COALESCE(SUM(p.amount),0) FROM loan_payments p JOIN vehicle_loans l ON l.id=p.loan_id WHERE l.vehicle_id=v.id AND p.paid_on $dr) loan_emi
  FROM vehicles v WHERE v.deleted_at IS NULL AND " . sprintf($vf, 'v.id') . " ORDER BY v.reg_no",
            'f' => ['date', 'vehicle']
        ],
        'loans' => ['t' => 'Loan / EMI Payments', 'sql' => "SELECT v.reg_no,l.lender,l.account_ref,l.emi_amount,p.paid_on,p.amount,p.reference FROM loan_payments p JOIN vehicle_loans l ON l.id=p.loan_id JOIN vehicles v ON v.id=l.vehicle_id WHERE p.paid_on $dr AND " . sprintf($vf, 'l.vehicle_id') . " ORDER BY p.paid_on DESC", 'f' => ['date', 'vehicle']],
        'tyre_inventory' => ['t' => 'Tyre Inventory & Lifecycle', 'sql' => "SELECT t.tyre_code,t.brand,t.size,t.status,v.reg_no fitted_to,f.position,f.fitted_on FROM tyres t LEFT JOIN tyre_fitments f ON f.tyre_id=t.id AND f.removed_on IS NULL LEFT JOIN vehicles v ON v.id=f.vehicle_id WHERE t.deleted_at IS NULL ORDER BY t.tyre_code", 'f' => []],
        'tyre_movements' => ['t' => 'Tyre Movement Log', 'sql' => "SELECT m.moved_at,t.tyre_code,m.action,v.reg_no,m.position,m.note FROM tyre_movements m JOIN tyres t ON t.id=m.tyre_id LEFT JOIN vehicles v ON v.id=m.vehicle_id WHERE DATE(m.moved_at) $dr ORDER BY m.moved_at DESC", 'f' => ['date']],
    ];
}
function run_report(PDO $p, string $k, array $in): array
{
    $d = report_defs()[$k];
    $a = ['from' => $in['from'] ?? '1900-01-01', 'to' => $in['to'] ?? '9999-12-31', 'vehicle' => (int) ($in['vehicle'] ?? 0)];
    if (isset($d['fn']))
        return ($d['fn'])($p, $a);
    $p->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    $use = [];
    foreach ($a as $n => $v)
        if (strpos($d['sql'], ":$n") !== false)
            $use[$n] = $v;
    $s = $p->prepare($d['sql']);
    $s->execute($use);
    $r = $s->fetchAll();
    if (($d['post'] ?? '') === 'expiry')
        foreach ($r as &$x)
            $x['status'] = expiry_category($x['expiry']);
    return $r;
}
function rep_fuel_eff(PDO $p, array $a): array
{
    $p->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    $s = $p->prepare("SELECT f.vehicle_id,v.reg_no,v.expected_kmpl,f.filled_at,f.odometer,f.litres,f.is_full_tank FROM fuel_entries f JOIN vehicles v ON v.id=f.vehicle_id WHERE (:v=0 OR f.vehicle_id=:v) ORDER BY f.vehicle_id,f.filled_at,f.id");
    $s->execute(['v' => $a['vehicle']]);
    $by = [];
    foreach ($s as $r)
        $by[$r['vehicle_id']][] = $r;
    $out = [];
    foreach ($by as $rows) {
        $fills = array_map(fn($r) => ['odo' => (int) $r['odometer'], 'litres' => (float) $r['litres'], 'full' => (bool) $r['is_full_tank'], 'at' => $r['filled_at']], $rows);
        $res = calc_mileage($fills);
        foreach ($res as $m) {
            $at = '';
            foreach ($fills as $f)
                if ($f['odo'] === $m['odo'])
                    $at = $f['at'];
            if (substr($at, 0, 10) < $a['from'] || substr($at, 0, 10) > $a['to'])
                continue;
            $ex = (float) $rows[0]['expected_kmpl'];
            $flag = $ex > 0 ? ($m['kmpl'] < $ex * 0.6 ? 'Unusually low' : ($m['kmpl'] > $ex * 1.5 ? 'Unusually high' : '')) : '';
            $out[] = ['reg_no' => $rows[0]['reg_no'], 'fill_date' => $at, 'km' => $m['km'], 'litres' => $m['litres'], 'km_per_litre' => $m['kmpl'], 'expected' => $ex ?: '—', 'flag' => $flag];
        }
    }
    return $out;
}
