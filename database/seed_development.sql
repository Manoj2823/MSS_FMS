-- DEVELOPMENT ONLY. Fictional test records. NEVER import on a production database.
USE mss_fleet_management;
INSERT IGNORE INTO branches(name,city) VALUES('Head Office','Mettur'),('Salem Depot','Salem');
INSERT INTO drivers(name,mobile,licence_no,licence_expiry,branch_id) VALUES('DEV Driver One','9000000001','DEV-DL-001',CURDATE()+INTERVAL 20 DAY,1);
INSERT INTO vehicles(reg_no,fleet_no,vehicle_type,make,model,odometer,expected_kmpl,branch_id,insurance_expiry,puc_expiry) VALUES
('DEV-0001','DEV-B1','bus','TestMake','TM-1',10000,5.5,1,CURDATE()+INTERVAL 10 DAY,CURDATE()+INTERVAL 90 DAY),('DEV-0002','DEV-T1','truck','TestMake','TM-2',20000,4.0,1,CURDATE()+INTERVAL 200 DAY,CURDATE()-INTERVAL 5 DAY);
INSERT INTO fuel_entries(vehicle_id,filled_at,litres,price_per_litre,total_amount,odometer,is_full_tank,vendor,receipt_no) VALUES
((SELECT id FROM vehicles WHERE reg_no='DEV-0001'),NOW()-INTERVAL 20 DAY,60,95,5700,10000,1,'DEV Fuels','DEV-R1'),((SELECT id FROM vehicles WHERE reg_no='DEV-0001'),NOW()-INTERVAL 5 DAY,55,96,5280,10300,1,'DEV Fuels','DEV-R2');
