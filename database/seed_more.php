<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$pdo = db();

echo "Adding 5-6 additional records to each table...\n";

// 1. Branches (+5 = 10 total)
$branches = [
    [6, 'Tiruchirappalli Branch Depot', 'Trichy', 1],
    [7, 'Tirunelveli Regional Depot', 'Tirunelveli', 1],
    [8, 'Erode Logistics Hub', 'Erode', 1],
    [9, 'Vellore Terminal', 'Vellore', 1],
    [10, 'Thanjavur Logistics Center', 'Thanjavur', 1],
];
$stmt = $pdo->prepare('INSERT INTO branches(id, name, city, is_active) VALUES(?,?,?,?)');
foreach ($branches as $b) {
    $stmt->execute($b);
}
echo "Branches added: 5 (Total: 10)\n";

// 2. Users (+5 = 10 total)
$passHash = password_hash('password123', PASSWORD_DEFAULT);
$users = [
    [6, 2, 6, 'Ramesh Babu (FM - Trichy)', 'ramesh@mss.com', $passHash, 1],
    [7, 3, 7, 'Saravanan G (Ops - Tirunelveli)', 'saravanan@mss.com', $passHash, 1],
    [8, 4, 8, 'Anand Kumar (Maint - Erode)', 'anand@mss.com', $passHash, 1],
    [9, 5, 9, 'Sivakumar K (Acc - Vellore)', 'sivakumar@mss.com', $passHash, 1],
    [10, 6, 10, 'Meenakshi S (Management)', 'meenakshi@mss.com', $passHash, 1],
];
$stmt = $pdo->prepare('INSERT INTO users(id, role_id, branch_id, name, email, password_hash, is_active) VALUES(?,?,?,?,?,?,?)');
foreach ($users as $u) {
    $stmt->execute($u);
}
echo "Users added: 5 (Total: 10)\n";

// 3. Drivers (+6 = 11 total)
$drivers = [
    [6, 'A. Vijay Anand', '9840667788', 'TN-45-2017-004455', date('Y-m-d', strtotime('+4 days')), 6, 'available'], // Due in 4 days!
    [7, 'T. Manikandan', '9840778899', 'TN-72-2019-006677', date('Y-m-d', strtotime('+2 days')), 7, 'available'], // Due in 2 days!
    [8, 'G. Balaji', '9840889900', 'TN-33-2020-008899', date('Y-m-d', strtotime('+45 days')), 8, 'on_trip'],
    [9, 'N. Radhakrishnan', '9840990011', 'TN-23-2016-001020', date('Y-m-d', strtotime('+6 days')), 9, 'available'], // Due in 6 days!
    [10, 'K. Muthu', '9840102030', 'TN-49-2021-003040', date('Y-m-d', strtotime('+180 days')), 10, 'available'],
    [11, 'D. Elango', '9840203040', 'TN-27-2018-005060', date('Y-m-d', strtotime('+365 days')), 1, 'available'],
];
$stmt = $pdo->prepare('INSERT INTO drivers(id, name, mobile, licence_no, licence_expiry, branch_id, availability) VALUES(?,?,?,?,?,?,?)');
foreach ($drivers as $d) {
    $stmt->execute($d);
}
echo "Drivers added: 6 (Total: 11)\n";

// 4. Vehicles (+6 = 11 total) - NO BUS! (truck, van, truck, car, other, bike)
$vehicles = [
    [
        6,
        'TN 45 FF 6006',
        'FLT-TRK-06',
        'truck',
        'BharatBenz',
        '2823R Heavy Truck',
        2023,
        'diesel',
        'owned',
        'BBZ2823TN4506',
        'ENG77218',
        2,
        21000,
        3,
        'Heavy Container Cargo',
        48900,
        4.50,
        6,
        6,
        'available',
        date('Y-m-d', strtotime('+3 days')), // ⚠️ Insurance due in 3 days!
        date('Y-m-d', strtotime('+120 days')),
        date('Y-m-d', strtotime('+200 days')),
        date('Y-m-d', strtotime('+150 days')),
        date('Y-m-d', strtotime('+90 days')),
        date('Y-m-d', strtotime('+5 days')), // ⚠️ Service due in 5 days!
        50000,
        'Inter-state logistics carrier',
        1
    ],
    [
        7,
        'TN 72 GG 7007',
        'FLT-VAN-07',
        'van',
        'Force Motors',
        'Urbania 3615',
        2023,
        'diesel',
        'owned',
        'FRC3615TN7207',
        'ENG88412',
        13,
        2200,
        2,
        'Luxury Commuter Van',
        31200,
        10.80,
        7,
        7,
        'available',
        date('Y-m-d', strtotime('+140 days')),
        date('Y-m-d', strtotime('+180 days')),
        date('Y-m-d', strtotime('+4 days')), // ⚠️ Fitness due in 4 days!
        date('Y-m-d', strtotime('+220 days')),
        date('Y-m-d', strtotime('+6 days')), // ⚠️ PUC due in 6 days!
        date('Y-m-d', strtotime('+45 days')),
        35000,
        'Airport premium transfers',
        1
    ],
    [
        8,
        'TN 33 HH 8008',
        'FLT-TRK-08',
        'truck',
        'Eicher Motors',
        'Pro 3015 Medium Truck',
        2022,
        'diesel',
        'owned',
        'EIC3015TN3308',
        'ENG99124',
        2,
        10500,
        2,
        'Open Flatbed Body',
        72100,
        6.10,
        8,
        8,
        'on_trip',
        date('Y-m-d', strtotime('+90 days')),
        date('Y-m-d', strtotime('+2 days')), // ⚠️ Permit due in 2 days!
        date('Y-m-d', strtotime('+110 days')),
        date('Y-m-d', strtotime('+75 days')),
        date('Y-m-d', strtotime('+80 days')),
        date('Y-m-d', strtotime('+30 days')),
        75000,
        'Textile and yarn transport',
        1
    ],
    [
        9,
        'TN 23 II 9009',
        'FLT-CAR-09',
        'car',
        'Tata Motors',
        'Nexon EV Empowered',
        2023,
        'electric',
        'owned',
        'TAT2023TN2309',
        'ENG_EV09',
        5,
        450,
        2,
        'Compact SUV EV',
        21400,
        0,
        9,
        9,
        'available',
        date('Y-m-d', strtotime('+210 days')),
        date('Y-m-d', strtotime('+300 days')),
        date('Y-m-d', strtotime('+400 days')),
        date('Y-m-d', strtotime('+5 days')), // ⚠️ Road tax due in 5 days!
        date('Y-m-d', strtotime('+365 days')),
        date('Y-m-d', strtotime('+60 days')),
        25000,
        'Regional branch operational inspection EV',
        1
    ],
    [
        10,
        'TN 49 JJ 1010',
        'FLT-OTH-10',
        'other',
        'Mahindra',
        'Bolero Maxi Truck Plus',
        2022,
        'diesel',
        'owned',
        'MAH2022TN4910',
        'ENG33811',
        2,
        1200,
        2,
        'Pickup Delivery Utility',
        54300,
        13.50,
        10,
        10,
        'available',
        date('Y-m-d', strtotime('+180 days')),
        date('Y-m-d', strtotime('+90 days')),
        date('Y-m-d', strtotime('+120 days')),
        date('Y-m-d', strtotime('+240 days')),
        date('Y-m-d', strtotime('+7 days')), // ⚠️ PUC due in 7 days!
        date('Y-m-d', strtotime('+1 day')), // ⚠️ Service due in 1 day!
        55000,
        'Rural agricultural supply transport',
        1
    ],
    [
        11,
        'TN 27 KK 2020',
        'FLT-BIK-11',
        'bike',
        'TVS Motors',
        'Raider 125 Disc',
        2023,
        'petrol',
        'owned',
        'TVS2023TN2711',
        'ENG11845',
        2,
        140,
        2,
        'Sport Commuter Motorcycle',
        11200,
        58.00,
        1,
        11,
        'available',
        date('Y-m-d', strtotime('+300 days')),
        date('Y-m-d', strtotime('+300 days')),
        date('Y-m-d', strtotime('+400 days')),
        date('Y-m-d', strtotime('+300 days')),
        date('Y-m-d', strtotime('+180 days')),
        date('Y-m-d', strtotime('+90 days')),
        13000,
        'Fast document and cheque dispatch motorcycle',
        1
    ],
];
$stmt = $pdo->prepare('INSERT INTO vehicles(
    id, reg_no, fleet_no, vehicle_type, make, model, mfg_year, fuel_type, ownership,
    chassis_no, engine_no, seating_capacity, load_capacity_kg, axle_count, body_type, odometer, expected_kmpl,
    branch_id, driver_id, status,
    insurance_expiry, permit_expiry, fitness_expiry, road_tax_expiry, puc_expiry,
    next_service_date, next_service_km, remarks, created_by
) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
foreach ($vehicles as $v) {
    $stmt->execute($v);
}
echo "Vehicles added: 6 (Total: 11)\n";

// 5. Trips (+6 = 11 total)
$trips = [
    [6, 6, 6, 'Trichy Central Goods Shed', 'Chennai Port Gate 1', '2026-10-26 21:00:00', '2026-10-27 08:00:00', '2026-10-26 21:15:00', '2026-10-27 08:30:00', 48550, 48900, 'completed'],
    [7, 7, 7, 'Tirunelveli Junction', 'Madurai Logistics Terminal', '2026-10-27 05:30:00', '2026-10-27 09:30:00', '2026-10-27 05:45:00', '2026-10-27 09:20:00', 31040, 31200, 'completed'],
    [8, 8, 8, 'Erode Perundurai SIPCOT', 'Bengaluru Electronic City', '2026-10-27 10:00:00', '2026-10-27 17:00:00', '2026-10-27 10:10:00', null, 71850, null, 'in_progress'],
    [9, 9, 9, 'Vellore Branch Depot', 'Ranipet SIPCOT Industrial Hub', '2026-10-28 10:00:00', '2026-10-28 12:00:00', null, null, null, null, 'scheduled'],
    [10, 10, 10, 'Thanjavur Old Bus Stand', 'Kumbakonam Market', '2026-10-27 14:00:00', '2026-10-27 16:30:00', null, null, null, null, 'scheduled'],
    [11, 6, 6, 'Chennai Port Gate 1', 'Trichy Central Goods Shed', '2026-10-22 20:00:00', '2026-10-23 07:00:00', '2026-10-22 20:10:00', '2026-10-23 06:45:00', 48200, 48550, 'completed'],
];
$stmt = $pdo->prepare('INSERT INTO trips(id, vehicle_id, driver_id, origin, destination, planned_departure, planned_arrival, actual_departure, actual_arrival, start_odo, end_odo, status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
foreach ($trips as $tr) {
    $stmt->execute($tr);
}
echo "Trips added: 6 (Total: 11)\n";

// 6. Fuel Entries (+6 = 11 total)
$fuel = [
    [6, 6, '2026-10-26 20:00:00', 95.00, 92.50, 8787.50, 48550, 1, 'HPCL - Trichy By-pass', 'HP-6612'],
    [7, 7, '2026-10-27 05:00:00', 50.00, 92.40, 4620.00, 31040, 1, 'IOC - Tirunelveli Town', 'IOC-7714'],
    [8, 8, '2026-10-27 09:30:00', 80.00, 92.50, 7400.00, 71850, 1, 'BPCL - Erode Perundurai', 'BP-8819'],
    [9, 10, '2026-10-24 11:00:00', 35.00, 92.60, 3241.00, 54100, 1, 'Nayara Energy - Thanjavur', 'NY-1029'],
    [10, 11, '2026-10-25 15:30:00', 9.50, 101.50, 964.25, 11150, 1, 'IOC - Mettur Dam', 'IOC-2022'],
    [11, 6, '2026-09-22 18:00:00', 105.00, 92.30, 9691.50, 47200, 1, 'HPCL - Villupuram NH', 'HP-5521'],
];
$stmt = $pdo->prepare('INSERT INTO fuel_entries(id, vehicle_id, filled_at, litres, price_per_litre, total_amount, odometer, is_full_tank, vendor, receipt_no) VALUES(?,?,?,?,?,?,?,?,?,?)');
foreach ($fuel as $fe) {
    $stmt->execute($fe);
}
echo "Fuel entries added: 6 (Total: 11)\n";

// 7. Maintenance Records (+6 = 11 total)
$maintenance = [
    [6, 6, 'Differential Oil Replacement & Greasing', '2026-10-18', 48000, 5600.00, 'completed', 'Rear differential oil replaced with Castrol heavy commercial oil'],
    [7, 7, 'Clutch Master Cylinder Overhaul', '2026-10-22', 30800, 4200.00, 'completed', 'Replaced clutch fluid and bled slave cylinder'],
    [8, 8, 'Suspension Leaf Spring Bushing', '2026-10-12', 71000, 7800.00, 'completed', 'Replaced rear leaf spring rubber bushes and shackle pins'],
    [9, 9, 'High Voltage Battery Diagnostics', '2026-10-08', 21000, 3500.00, 'completed', 'State of health check at 98.4%, firmware updated'],
    [10, 10, 'Engine Coolant Flush & Radiator Clean', '2026-10-26', 54200, 2800.00, 'open', 'Radiator descaling in progress'],
    [11, 11, 'Periodic 10,000 km Paid Service', '2026-09-25', 10050, 1150.00, 'completed', 'Engine oil Motul 10W30, air filter clean, brake shoes checked'],
];
$stmt = $pdo->prepare('INSERT INTO maintenance_records(id, vehicle_id, service_type, service_date, odometer, total_cost, status, notes) VALUES(?,?,?,?,?,?,?,?)');
foreach ($maintenance as $m) {
    $stmt->execute($m);
}
echo "Maintenance records added: 6 (Total: 11)\n";

// 8. Expenses (+6 = 11 total)
$expenses = [
    [6, 6, '2026-10-26', 'toll', 1620.00, 'Fastag Toll charges NH-45 Trichy-Chennai', 'approved', 1, '2026-10-26 22:00:00'],
    [7, 7, '2026-10-27', 'parking', 350.00, 'Tirunelveli goods terminal entry and parking', 'approved', 1, '2026-10-27 10:00:00'],
    [8, 8, '2026-10-23', 'tyres', 28500.00, 'MRF Super Lug 50-Plus Truck Tyre replacement', 'approved', 1, '2026-10-23 17:00:00'],
    [9, 9, '2026-10-19', 'other', 2400.00, 'Public EV DC fast charging session at Zeon station', 'approved', 1, '2026-10-19 14:00:00'],
    [10, 10, '2026-10-25', 'driver_allowance', 1800.00, 'Night stay driver beta and food allowance', 'approved', 1, '2026-10-25 21:00:00'],
    [11, 6, '2026-10-15', 'road_tax', 6200.00, 'Half-yearly Tamil Nadu state commercial motor road tax', 'approved', 1, '2026-10-15 11:30:00'],
];
$stmt = $pdo->prepare('INSERT INTO expenses(id, vehicle_id, expense_date, category, amount, description, approval_status, approved_by, approved_at) VALUES(?,?,?,?,?,?,?,?,?)');
foreach ($expenses as $e) {
    $stmt->execute($e);
}
echo "Expenses added: 6 (Total: 11)\n";

// 9. Audit Logs (+6 = 11 total)
$audits = [
    [6, 1, 'create', 'vehicle', 6, 'Added BharatBenz 2823R truck TN 45 FF 6006', '127.0.0.1'],
    [7, 2, 'dispatch', 'trip', 6, 'Dispatched freight trip Trichy to Chennai', '127.0.0.1'],
    [8, 3, 'complete', 'trip', 7, 'Marked trip 7 as completed on time', '127.0.0.1'],
    [9, 4, 'service', 'maintenance', 10, 'Opened radiator maintenance for vehicle 10', '127.0.0.1'],
    [10, 5, 'approve', 'expense', 8, 'Approved MRF truck tyre expense Rs.28500', '127.0.0.1'],
    [11, 1, 'create', 'driver', 6, 'Registered driver A. Vijay Anand with TN-45 licence', '127.0.0.1'],
];
$stmt = $pdo->prepare('INSERT INTO audit_logs(id, user_id, action, entity, entity_id, details, ip) VALUES(?,?,?,?,?,?,?)');
foreach ($audits as $a) {
    $stmt->execute($a);
}
echo "Audit logs added: 6 (Total: 11)\n";

echo "All additional records successfully added!\n";
