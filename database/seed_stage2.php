<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$pdo = db();

echo "Seeding missing data into 14 Stage 2-4 tables...\n";

// Disable FK checks during insertion
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

// 1. Routes (4)
$routes = [
    [1, 'Chennai - Coimbatore Freight Express', 'Chennai Port Gate 1', 'Coimbatore Industrial Estate', 510],
    [2, 'Salem - Madurai Central Logistics', 'Salem Operations Hub', 'Madurai Logistics Terminal', 235],
    [3, 'Trichy - Chennai Coastal Highway', 'Trichy Central Goods Shed', 'Chennai Koyambedu Hub', 330],
    [4, 'Mettur - Chennai Plant Route', 'Mettur Central Depot', 'Chennai Port Gate 1', 360],
];
$stmt = $pdo->prepare('INSERT IGNORE INTO routes(id, name, origin, destination, distance_km) VALUES(?,?,?,?,?)');
foreach ($routes as $r) {
    $stmt->execute($r);
}
echo "Routes inserted: " . count($routes) . "\n";

// 2. Vendors (4)
$vendors = [
    [1, 'TVS Mobility Services Ltd', 'Workshop / Maintenance', '044-28521234'],
    [2, 'Apollo Tyres Commercial Hub', 'Tyres & Retreading', '0422-2612345'],
    [3, 'Indian Oil Corporation Ltd', 'Fuel Station', '0427-2445566'],
    [4, 'Sundaram Fasteners Auto Spares', 'Spare Parts', '0431-2701122'],
];
$stmt = $pdo->prepare('INSERT IGNORE INTO vendors(id, name, vendor_type, phone) VALUES(?,?,?,?)');
foreach ($vendors as $v) {
    $stmt->execute($v);
}
echo "Vendors inserted: " . count($vendors) . "\n";

// 3. Vehicle Documents (6) - Links to vehicles 1, 2, 6, 7 & driver 1
$documents = [
    [1, 1, null, 'rc', 'TN27AA1001-RC', '2022-03-15', '2037-03-14', 'storage/uploads/rc_tn27aa1001.pdf', 1, 'Original Registration Certificate'],
    [2, 1, null, 'insurance', 'NIC-FLEET-9901', '2025-11-12', '2026-11-11', 'storage/uploads/ins_tn27aa1001.pdf', 1, 'National Insurance Comprehensive Cover'],
    [3, 2, null, 'fitness', 'TN38-FC-2025-44', '2025-11-13', '2026-11-12', 'storage/uploads/fc_tn38bb2002.pdf', 1, 'Annual Commercial Fitness Certificate'],
    [4, 6, null, 'permit', 'AIP-TN-2023-8871', '2023-04-01', '2028-03-31', 'storage/uploads/permit_tn45ff6006.pdf', 1, 'All India Goods Carrier Interstate Permit'],
    [5, null, 1, 'driver_licence', 'TN-27-2015-001234', '2015-05-16', '2028-05-15', 'storage/uploads/dl_murugan.pdf', 1, 'Commercial Heavy Goods Transport License'],
    [6, 7, null, 'puc', 'PUC-2026-10291', '2026-05-02', '2026-11-02', 'storage/uploads/puc_tn72gg7007.pdf', 1, 'BS-VI Emission Compliance Certificate'],
];
$stmt = $pdo->prepare('INSERT IGNORE INTO vehicle_documents(id, vehicle_id, driver_id, doc_type, doc_number, issue_date, expiry_date, file_path, uploaded_by, notes) VALUES(?,?,?,?,?,?,?,?,?,?)');
foreach ($documents as $d) {
    $stmt->execute($d);
}
echo "Vehicle Documents inserted: " . count($documents) . "\n";

// 4. Trip Expenses (4) - Links to existing trips
$tripExp = [
    [1, 1, 'fuel', 3500.00, 'En-route fuel top-up at Tindivanam bypass'],
    [2, 2, 'toll', 980.00, 'Chengalpattu and Tindivanam toll plaza receipts'],
    [3, 3, 'driver_allowance', 800.00, 'Driver day allowance and loading beta'],
    [4, 6, 'loading', 1200.00, 'Port terminal crane container loading fee'],
];
$stmt = $pdo->prepare('INSERT IGNORE INTO trip_expenses(id, trip_id, category, amount, notes) VALUES(?,?,?,?,?)');
foreach ($tripExp as $te) {
    $stmt->execute($te);
}
echo "Trip Expenses inserted: " . count($tripExp) . "\n";

// 5. Driver Assignments (4)
$assignments = [
    [1, 1, 1, '2026-01-01', null, 'Primary driver for Tata Signa 4825.T truck'],
    [2, 2, 2, '2026-02-15', null, 'Dedicated driver for Force Traveller shuttle van'],
    [3, 6, 6, '2026-03-01', null, 'Interstate long haul pilot for BharatBenz truck'],
    [4, 7, 7, '2026-04-10', null, 'Assigned to Force Urbania luxury transfer van'],
];
$stmt = $pdo->prepare('INSERT IGNORE INTO driver_assignments(id, driver_id, vehicle_id, start_date, end_date, notes) VALUES(?,?,?,?,?,?)');
foreach ($assignments as $a) {
    $stmt->execute($a);
}
echo "Driver Assignments inserted: " . count($assignments) . "\n";

// 6. Breakdown Records (3)
$breakdowns = [
    [1, 3, '2026-09-10 14:30:00', 'NH-44 near Karur Toll Plaza', 'Brake booster air pressure drop causing wheel drag', 6.5, 3500.00, 4200.00, 'Replaced air brake valve assembly by mobile team', 'resolved'],
    [2, 8, '2026-10-02 09:15:00', 'Salem Bypass near Sankari', 'Alternator belt snapped resulting in battery drain', 3.0, 0.00, 1800.00, 'Installed new fan & alternator belt on spot', 'resolved'],
    [3, 10, '2026-10-26 16:45:00', 'Kumbakonam bypass road', 'Engine overheating due to radiator coolant leak', 12.0, 1500.00, 2800.00, 'Radiator hose replacement and pressure testing in progress', 'open'],
];
$stmt = $pdo->prepare('INSERT IGNORE INTO breakdown_records(id, vehicle_id, breakdown_date, location, description, downtime_hours, towing_cost, repair_cost, resolution, status) VALUES(?,?,?,?,?,?,?,?,?,?)');
foreach ($breakdowns as $b) {
    $stmt->execute($b);
}
echo "Breakdown Records inserted: " . count($breakdowns) . "\n";

// 7. Vehicle Loans (3) - Links to vehicles 1, 2, 6
$loans = [
    [1, 1, 'HDFC Bank Commercial Vehicle Finance', 'HDFC-CV-882910', 3200000.00, 8.75, 65400.00, '2022-04-01', '2027-03-31', 60],
    [2, 2, 'Sundaram Finance Limited', 'SND-VAN-552109', 1450000.00, 9.20, 32800.00, '2021-06-01', '2026-05-31', 60],
    [3, 6, 'ICICI Bank Heavy Equipment Loan', 'ICICI-TRK-771234', 2800000.00, 8.50, 58200.00, '2023-01-10', '2028-01-09', 60],
];
$stmt = $pdo->prepare('INSERT IGNORE INTO vehicle_loans(id, vehicle_id, lender, account_ref, principal, interest_rate, emi_amount, start_date, end_date, instalments) VALUES(?,?,?,?,?,?,?,?,?,?)');
foreach ($loans as $l) {
    $stmt->execute($l);
}
echo "Vehicle Loans inserted: " . count($loans) . "\n";

// 8. Loan Payments (3)
$payments = [
    [1, 1, '2026-10-05', 65400.00, 'HDFC-ACH-998811'],
    [2, 1, '2026-09-05', 65400.00, 'HDFC-ACH-994422'],
    [3, 2, '2026-10-07', 32800.00, 'SND-NEFT-332190'],
];
$stmt = $pdo->prepare('INSERT IGNORE INTO loan_payments(id, loan_id, paid_on, amount, reference) VALUES(?,?,?,?,?)');
foreach ($payments as $p) {
    $stmt->execute($p);
}
echo "Loan Payments inserted: " . count($payments) . "\n";

// 9. Tyres (6)
$tyres = [
    [1, 'TYR-APL-001', 'Apollo', '295/80 R22.5', 'Radial Tubeless', 'APL-RD-98214', '2025-06-10', 'Apollo Tyres Hub', 'INV-AP-441', 18500.00, 'Brand new steer axle tyre', 'fitted'],
    [2, 'TYR-APL-002', 'Apollo', '295/80 R22.5', 'Radial Tubeless', 'APL-RD-98215', '2025-06-10', 'Apollo Tyres Hub', 'INV-AP-441', 18500.00, 'Brand new steer axle tyre', 'fitted'],
    [3, 'TYR-MRF-003', 'MRF', '10.00 R20', 'Cross Ply Lug', 'MRF-SL-77219', '2025-08-15', 'Sundaram Tyres', 'INV-ST-882', 16200.00, 'Drive axle tyre in good condition', 'fitted'],
    [4, 'TYR-JK-004', 'JK Tyre', '215/75 R15', 'Radial', 'JK-JET-33120', '2026-01-20', 'National Tyre Agency', 'INV-NT-112', 7800.00, 'Spare inventory tyre', 'in_stock'],
    [5, 'TYR-CEAT-005', 'CEAT', '295/80 R22.5', 'Radial Tubeless', 'CT-WIN-55441', '2024-03-12', 'CEAT Pro Centre', 'INV-CP-901', 17900.00, 'Casing intact, cold retreading', 'under_retreading'],
    [6, 'TYR-BRDG-006', 'Bridgestone', '10.00 R20', 'Lug Pattern', 'BS-M840-22109', '2023-01-10', 'Southern Tyre Corp', 'INV-BS-671', 19500.00, 'Worn beyond limit, scrap approved', 'scrapped'],
];
$stmt = $pdo->prepare('INSERT IGNORE INTO tyres(id, tyre_code, brand, size, tyre_type, serial_no, purchase_date, supplier, invoice_no, purchase_price, condition_note, status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
foreach ($tyres as $t) {
    $stmt->execute($t);
}
echo "Tyres inserted: " . count($tyres) . "\n";

// 10. Tyre Fitments (3) - note: active_key is generated column, don't supply it
$fitments = [
    [1, 1, 1, 'Front-Left', '2025-06-15', 45000, null, null, null],
    [2, 2, 1, 'Front-Right', '2025-06-15', 45000, null, null, null],
    [3, 3, 6, 'Rear-Outer-Left', '2025-08-20', 32000, null, null, null],
];
$stmt = $pdo->prepare('INSERT IGNORE INTO tyre_fitments(id, tyre_id, vehicle_id, position, fitted_on, odo_fitted, removed_on, odo_removed, removal_reason) VALUES(?,?,?,?,?,?,?,?,?)');
foreach ($fitments as $f) {
    $stmt->execute($f);
}
echo "Tyre Fitments inserted: " . count($fitments) . "\n";

// 11. Tyre Movements (3)
$movements = [
    [1, 1, 'fit', 1, 'Front-Left', '2025-06-15 10:00:00', 1, 'New Apollo radial fitted to Front-Left'],
    [2, 2, 'fit', 1, 'Front-Right', '2025-06-15 10:30:00', 1, 'New Apollo radial fitted to Front-Right'],
    [3, 5, 'send_retread', null, null, '2026-10-15 14:00:00', 4, 'Sent to Sundaram Retreaders for cold retread'],
];
$stmt = $pdo->prepare('INSERT IGNORE INTO tyre_movements(id, tyre_id, action, vehicle_id, position, moved_at, user_id, note) VALUES(?,?,?,?,?,?,?,?)');
foreach ($movements as $m) {
    $stmt->execute($m);
}
echo "Tyre Movements inserted: " . count($movements) . "\n";

// 12. Tyre Inspections (3)
$inspections = [
    [1, 1, '2026-10-10', 11.5, 'Even wear across tread, inflation pressure 125 psi', 'Fit for long highway routes'],
    [2, 2, '2026-10-10', 11.2, 'Normal wear, no cuts or sidewall bulge', 'All wheel balancing verified'],
    [3, 3, '2026-09-28', 8.4, 'Minor stone trapping removed from tread groove', 'Recommended rotation in 5000 km'],
];
$stmt = $pdo->prepare('INSERT IGNORE INTO tyre_inspections(id, tyre_id, inspected_on, tread_mm, condition_note, notes) VALUES(?,?,?,?,?,?)');
foreach ($inspections as $i) {
    $stmt->execute($i);
}
echo "Tyre Inspections inserted: " . count($inspections) . "\n";

// 13. Tyre Retreading (2)
$retreading = [
    [1, 5, '2026-10-15', null, 'Sundaram Retread Works - Salem', 4800.00],
    [2, 6, '2025-11-01', '2025-11-20', 'Apollo Mileage Pro Retread', 5200.00],
];
$stmt = $pdo->prepare('INSERT IGNORE INTO tyre_retreading(id, tyre_id, sent_on, returned_on, vendor, cost) VALUES(?,?,?,?,?,?)');
foreach ($retreading as $r) {
    $stmt->execute($r);
}
echo "Tyre Retreading inserted: " . count($retreading) . "\n";

// 14. Tyre Disposals (2)
$disposals = [
    [1, 6, 'scrap', '2026-10-20', 'Bead crack and crown separation beyond repair', 'Salem Rubber Recyclers', 850.00, 'SCRAP-REC-2026-88', 1],
    [2, 4, 'sale', '2026-08-10', 'Surplus inventory liquidation', 'Mettur Auto Traders', 5500.00, 'SALE-TYR-9921', 1],
];
$stmt = $pdo->prepare('INSERT IGNORE INTO tyre_disposals(id, tyre_id, disposal_type, disposed_on, reason, buyer, sale_amount, reference, approved_by) VALUES(?,?,?,?,?,?,?,?,?)');
foreach ($disposals as $d) {
    $stmt->execute($d);
}
echo "Tyre Disposals inserted: " . count($disposals) . "\n";

$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

echo "\nAll 14 tables successfully populated with rich data!\n";
