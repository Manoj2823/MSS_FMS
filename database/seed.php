<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

$pdo = db();

echo "Seeding database with 5 records per entity...\n";

// Disable foreign key checks during seed to ensure clean setup
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

// Clear existing tables
$tables = ['audit_logs', 'expenses', 'maintenance_records', 'fuel_entries', 'trips', 'vehicles', 'drivers', 'users', 'branches'];
foreach ($tables as $t) {
    $pdo->exec("TRUNCATE TABLE $t");
}

$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

// 1. Branches (5)
$branches = [
    [1, 'Chennai Regional Hub', 'Chennai', 1],
    [2, 'Coimbatore Branch Depot', 'Coimbatore', 1],
    [3, 'Madurai Logistics Terminal', 'Madurai', 1],
    [4, 'Salem Operations Hub', 'Salem', 1],
    [5, 'Mettur Central Office', 'Mettur', 1],
];
$stmt = $pdo->prepare('INSERT INTO branches(id, name, city, is_active) VALUES(?,?,?,?)');
foreach ($branches as $b) {
    $stmt->execute($b);
}
echo "Branches inserted: 5\n";

// 2. Users (5) - password: password123
$passHash = password_hash('password123', PASSWORD_DEFAULT);
$users = [
    [1, 1, 5, 'Super Admin Manoj', 'admin@mss.com', $passHash, 1],
    [2, 2, 1, 'Suresh Kumar (Manager)', 'manager@mss.com', $passHash, 1],
    [3, 3, 2, 'Vimal Nathan (Operations)', 'operations@mss.com', $passHash, 1],
    [4, 4, 3, 'Praveen Raj (Maintenance)', 'maintenance@mss.com', $passHash, 1],
    [5, 5, 4, 'Deepak Sharma (Accounts)', 'accounts@mss.com', $passHash, 1],
];
$stmt = $pdo->prepare('INSERT INTO users(id, role_id, branch_id, name, email, password_hash, is_active) VALUES(?,?,?,?,?,?,?)');
foreach ($users as $u) {
    $stmt->execute($u);
}
echo "Users inserted: 5\n";

// 3. Drivers (5)
$drivers = [
    [1, 'K. Murugan', '9840112233', 'TN-27-2015-001234', '2028-05-15', 1, 'available'],
    [2, 'S. Selvam', '9840223344', 'TN-38-2018-005678', date('Y-m-d', strtotime('+18 days')), 2, 'on_trip'],
    [3, 'M. Raja', '9840334455', 'TN-58-2019-009101', '2027-10-10', 3, 'available'],
    [4, 'R. Karthik', '9840445566', 'TN-30-2020-001122', date('Y-m-d', strtotime('+12 days')), 4, 'available'],
    [5, 'P. Senthil', '9840556677', 'TN-28-2016-003344', '2029-01-22', 5, 'leave'],
];
$stmt = $pdo->prepare('INSERT INTO drivers(id, name, mobile, licence_no, licence_expiry, branch_id, availability) VALUES(?,?,?,?,?,?,?)');
foreach ($drivers as $d) {
    $stmt->execute($d);
}
echo "Drivers inserted: 5\n";

// 4. Vehicles (5) - NO BUS! (van, truck, car, bike, truck)
$vehicles = [
    [
        1,
        'TN 27 AA 1001',
        'FLT-TRK-01',
        'truck',
        'Tata Motors',
        'Signa 4825.T',
        2022,
        'diesel',
        'owned',
        'MAT4825TN2201',
        'ENG67812',
        2,
        28000,
        5,
        'Open Body Container',
        64200,
        4.20,
        1,
        1,
        'available',
        date('Y-m-d', strtotime('+15 days')), // insurance alert
        '2027-04-01',
        '2027-03-10',
        '2027-01-15',
        date('Y-m-d', strtotime('+10 days')), // puc alert
        '2026-12-05',
        70000,
        'Heavy interstate cargo truck',
        1
    ],
    [
        2,
        'TN 38 BB 2002',
        'FLT-VAN-02',
        'van',
        'Force Motors',
        'Traveller 3350',
        2021,
        'diesel',
        'owned',
        'FRC3350TN2102',
        'ENG44512',
        17,
        2500,
        2,
        'Passenger Van',
        88500,
        11.50,
        2,
        2,
        'on_trip',
        '2027-02-20',
        date('Y-m-d', strtotime('+22 days')), // permit alert
        date('Y-m-d', strtotime('+16 days')), // fitness alert
        '2027-05-10',
        date('Y-m-d', strtotime('+11 days')), // puc alert
        '2026-11-30',
        90000,
        'Staff and passenger shuttle van',
        1
    ],
    [
        3,
        'TN 58 CC 3003',
        'FLT-TRK-03',
        'truck',
        'Ashok Leyland',
        'Ecomet 1215',
        2023,
        'diesel',
        'owned',
        'AL1215TN2303',
        'ENG99823',
        2,
        8500,
        2,
        'Closed Box Lorry',
        35400,
        5.80,
        3,
        3,
        'maintenance',
        '2027-06-15',
        '2027-07-20',
        '2027-09-12',
        date('Y-m-d', strtotime('+19 days')), // road tax alert
        '2027-08-14',
        date('Y-m-d', strtotime('+7 days')), // service alert
        36000,
        'Medium duty delivery lorry',
        1
    ],
    [
        4,
        'TN 30 DD 4004',
        'FLT-CAR-04',
        'car',
        'Mahindra',
        'Scorpio-N Z8',
        2023,
        'diesel',
        'owned',
        'MAH2023TN3004',
        'ENG55231',
        7,
        650,
        2,
        'SUV',
        28900,
        14.20,
        4,
        4,
        'available',
        '2027-04-10',
        '2027-08-11',
        '2028-02-14',
        '2027-03-01',
        '2027-01-20',
        '2027-01-15',
        35000,
        'Inspection & executive travel car',
        1
    ],
    [
        5,
        'TN 28 EE 5005',
        'FLT-BIK-05',
        'bike',
        'Hero MotoCorp',
        'Splendor Plus',
        2022,
        'petrol',
        'owned',
        'HERO2022TN2805',
        'ENG11290',
        2,
        130,
        2,
        'Motorcycle',
        15300,
        62.00,
        5,
        null,
        'available',
        '2027-05-18',
        '2027-10-30',
        '2028-06-25',
        '2027-08-15',
        '2027-02-28',
        '2026-12-20',
        17000,
        'Branch courier & quick parcel bike',
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
echo "Vehicles inserted: 5\n";

// 5. Trips (5)
$trips = [
    [1, 2, 2, 'Mettur Central Depot', 'Chennai Koyambedu Hub', '2026-10-27 06:00:00', '2026-10-27 18:00:00', '2026-10-27 06:15:00', null, 88150, null, 'in_progress'],
    [2, 1, 1, 'Chennai Port', 'Coimbatore Industrial Estate', '2026-10-20 08:00:00', '2026-10-20 20:00:00', '2026-10-20 08:10:00', '2026-10-20 19:45:00', 63700, 64200, 'completed'],
    [3, 3, 3, 'Salem Operations Hub', 'Madurai Logistics Terminal', '2026-10-15 05:00:00', '2026-10-15 11:30:00', '2026-10-15 05:00:00', '2026-10-15 11:15:00', 35150, 35400, 'completed'],
    [4, 4, 4, 'Coimbatore Branch', 'Salem Operations Hub', '2026-10-28 09:00:00', '2026-10-28 14:00:00', null, null, null, null, 'scheduled'],
    [5, 1, 1, 'Madurai Ring Road', 'Mettur Plant', '2026-10-05 07:00:00', '2026-10-05 16:00:00', '2026-10-05 07:30:00', '2026-10-05 16:45:00', 63200, 63700, 'completed'],
];
$stmt = $pdo->prepare('INSERT INTO trips(id, vehicle_id, driver_id, origin, destination, planned_departure, planned_arrival, actual_departure, actual_arrival, start_odo, end_odo, status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
foreach ($trips as $tr) {
    $stmt->execute($tr);
}
echo "Trips inserted: 5\n";

// 6. Fuel Entries (5)
$fuel = [
    [1, 1, '2026-10-20 12:30:00', 120.00, 92.50, 11100.00, 63950, 1, 'Indian Oil - Salem NH', 'IOC-8821'],
    [2, 2, '2026-10-27 08:00:00', 45.00, 92.50, 4162.50, 88200, 1, 'Bharat Petroleum - Mettur', 'BP-4412'],
    [3, 3, '2026-09-18 10:15:00', 85.00, 92.30, 7845.50, 34800, 1, 'HPCL - Madurai Bypass', 'HP-9011'],
    [4, 4, '2026-08-12 14:00:00', 40.00, 92.00, 3680.00, 27500, 1, 'IOC - Coimbatore Avinashi Rd', 'IOC-5532'],
    [5, 1, '2026-07-25 11:00:00', 110.00, 92.10, 10131.00, 62400, 1, 'BPCL - Chennai GST Rd', 'BP-1299'],
];
$stmt = $pdo->prepare('INSERT INTO fuel_entries(id, vehicle_id, filled_at, litres, price_per_litre, total_amount, odometer, is_full_tank, vendor, receipt_no) VALUES(?,?,?,?,?,?,?,?,?,?)');
foreach ($fuel as $fe) {
    $stmt->execute($fe);
}
echo "Fuel entries inserted: 5\n";

// 7. Maintenance Records (5)
$maintenance = [
    [1, 3, 'Brake Overhaul & Fluid Change', '2026-10-25', 35400, 8500.00, 'open', 'Scheduled brake pad replacement and master cylinder check'],
    [2, 1, 'Full General Service & Engine Oil', '2026-10-10', 63000, 14200.00, 'completed', 'Engine oil filter, diesel filter, air filter replaced'],
    [3, 2, 'Wheel Alignment & Tyre Rotation', '2026-09-15', 86000, 2400.00, 'completed', 'Front tyres balanced and rotated'],
    [4, 4, 'AC System Check & Gas Refill', '2026-08-20', 27800, 4500.00, 'completed', 'Cabin filter replaced and refrigerant charged'],
    [5, 5, 'Oil Change & Chain Sprocket', '2026-07-10', 14500, 1200.00, 'completed', 'Spark plug cleaned, clutch cable adjusted'],
];
$stmt = $pdo->prepare('INSERT INTO maintenance_records(id, vehicle_id, service_type, service_date, odometer, total_cost, status, notes) VALUES(?,?,?,?,?,?,?,?)');
foreach ($maintenance as $m) {
    $stmt->execute($m);
}
echo "Maintenance records inserted: 5\n";

// 8. Expenses (5)
$expenses = [
    [1, 1, '2026-10-21', 'toll', 1850.00, 'Fastag Toll charges NH-44 Salem-Chennai', 'approved', 1, '2026-10-21 18:00:00'],
    [2, 2, '2026-10-18', 'parking', 450.00, 'Commercial parking charges at CMBT hub', 'approved', 1, '2026-10-18 20:00:00'],
    [3, 3, '2026-09-28', 'tyres', 32000.00, '2 Rear Radial Tyres (Apollo EnduRace)', 'approved', 1, '2026-09-28 17:30:00'],
    [4, 4, '2026-09-05', 'driver_allowance', 2500.00, 'Outstation food and night stay allowance', 'approved', 1, '2026-09-05 19:00:00'],
    [5, 1, '2026-08-15', 'permit', 5000.00, 'Quarterly state transit permit renewal fee', 'approved', 1, '2026-08-15 15:00:00'],
];
$stmt = $pdo->prepare('INSERT INTO expenses(id, vehicle_id, expense_date, category, amount, description, approval_status, approved_by, approved_at) VALUES(?,?,?,?,?,?,?,?,?)');
foreach ($expenses as $e) {
    $stmt->execute($e);
}
echo "Expenses inserted: 5\n";

// 9. Audit Logs (5)
$audits = [
    [1, 1, 'create', 'vehicle', 1, 'Added vehicle TN 27 AA 1001 (Tata Signa Truck)', '127.0.0.1'],
    [2, 1, 'create', 'vehicle', 2, 'Added vehicle TN 38 BB 2002 (Force Traveller Van)', '127.0.0.1'],
    [3, 2, 'dispatch', 'trip', 1, 'Dispatched trip from Mettur to Chennai', '127.0.0.1'],
    [4, 4, 'status_change', 'vehicle', 3, 'Moved vehicle TN 58 CC 3003 to maintenance', '127.0.0.1'],
    [5, 5, 'approve', 'expense', 1, 'Approved toll expense Rs.1850', '127.0.0.1'],
];
$stmt = $pdo->prepare('INSERT INTO audit_logs(id, user_id, action, entity, entity_id, details, ip) VALUES(?,?,?,?,?,?,?)');
foreach ($audits as $a) {
    $stmt->execute($a);
}
echo "Audit logs inserted: 5\n";

echo "All 5,5 records successfully added!\n";
