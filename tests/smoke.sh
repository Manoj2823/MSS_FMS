#!/bin/bash
# HTTP smoke test. Usage: bash tests/smoke.sh   (serves the project under /mss-fleet-management/ on :8089; needs php, curl, mysql)
cd "$(dirname "$0")/.."; ROOT=$(pwd); PARENT=$(dirname "$ROOT"); B=http://localhost:8089/mss-fleet-management
mysql -uroot -e "DROP DATABASE IF EXISTS mss_fleet_management" && mysql -uroot < database/mss_fleet_management.sql || exit 1
rm -f storage/uploads/*.pdf storage/uploads/*.png; (php -S localhost:8089 -t "$PARENT" >/tmp/php-server.log 2>&1 &); sleep 1
PASS=0;FAIL=0; ok(){ if [ "$2" = "1" ]; then PASS=$((PASS+1)); echo "PASS - $1"; else FAIL=$((FAIL+1)); echo "FAIL - $1"; fi; }
J=/tmp/cj_admin; J2=/tmp/cj_ro; rm -f $J $J2
tok(){ curl -s -b $2 -c $2 "$B/$1" | grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
post(){ local jar=$1 path=$2 pg=$3; shift 3; local t=$(tok "$pg" $jar); curl -s -b $jar -c $jar -o /tmp/out.html -w "%{http_code}" -d "csrf=$t" "$@" "$B/$path"; }
get(){ curl -s -b ${2:-$J} -c ${2:-$J} -o /tmp/out.html -w "%{http_code}" "$B/$1"; }
clean(){ ! grep -qE "Warning:|Fatal error|Notice:|Deprecated:|Parse error|Stack trace" /tmp/out.html; }
# setup + login
C=$(post $J setup.php setup.php -d "name=Admin&email=admin@mss.test&password=SuperSecret123" ); ok "first-admin setup redirects (302)" $([ "$C" = 302 ] && echo 1)
C=$(post $J setup.php setup.php); ok "setup locked after first admin" $(grep -q "already completed" /tmp/out.html && echo 1)
C=$(post $J login.php login.php -d "email=admin@mss.test&password=wrong"); ok "wrong password rejected" $(grep -q "Invalid email" /tmp/out.html && echo 1)
C=$(curl -s -b $J -c $J -o /dev/null -w "%{http_code}" -d "email=admin@mss.test&password=SuperSecret123" "$B/login.php"); ok "login without CSRF token blocked (419)" $([ "$C" = 419 ] && echo 1)
C=$(post $J login.php "login.php" -d "email=admin@mss.test&password=SuperSecret123"); ok "login succeeds (302)" $([ "$C" = 302 ] && echo 1)
C=$(curl -s -o /dev/null -w "%{http_code}" "$B/dashboard.php"); ok "dashboard redirects when logged out (302)" $([ "$C" = 302 ] && echo 1)
# every page loads
KEYS=$(php -r 'chdir("'$ROOT'");$_SESSION=[];require "includes/bootstrap.php";require "includes/modules.php";echo implode(" ",array_keys(mods()));'); REPS=$(php -r 'require "'$ROOT'/includes/reports.php";echo implode(" ",array_keys(report_defs()));')
for P in dashboard.php modules/vehicles/index.php modules/vehicles/form.php modules/settings.php; do C=$(get $P); ok "page $P 200 & clean" $([ "$C" = 200 ] && clean && echo 1); done
for K in $KEYS; do C=$(get "modules/m.php?m=$K"); ok "list $K" $([ "$C" = 200 ] && clean && echo 1); [ "$K" != audit ] && [ "$K" != tyre_movements ] && { C=$(get "modules/m.php?m=$K&new=1"); ok "form $K" $([ "$C" = 200 ] && clean && echo 1); }; done
for R in $REPS; do C=$(get "modules/reports.php?r=$R"); ok "report $R" $([ "$C" = 200 ] && clean && echo 1); C=$(get "modules/reports.php?r=$R&export=csv"); ok "report $R csv" $([ "$C" = 200 ] && echo 1); done
C=$(get "api/dashboard.php"); ok "dashboard API valid JSON" $(php -r 'echo json_decode(file_get_contents("/tmp/out.html"))?1:0;')
# CRUD flows
C=$(post $J "modules/m.php?m=branches" "modules/m.php?m=branches&new=1" -d "action=save&id=0&name=Mettur+HO&city=Mettur&is_active=1"); ok "create branch" $([ "$C" = 302 ] && echo 1)
C=$(post $J "modules/vehicles/form.php" "modules/vehicles/form.php" -d "reg_no=tn 30 ab 1234&vehicle_type=bus&fuel_type=diesel&ownership=owned&status=available&odometer=1000&branch_id=1&insurance_expiry=2020-01-01&seating_capacity=50"); ok "create vehicle (302)" $([ "$C" = 302 ] && echo 1)
C=$(post $J "modules/vehicles/form.php" "modules/vehicles/form.php" -d "reg_no=TN30AB1234&vehicle_type=bus&fuel_type=diesel&ownership=owned&status=available&odometer=1"); ok "duplicate registration rejected" $(grep -q "already exists" /tmp/out.html && echo 1)
C=$(post $J "modules/vehicles/form.php?id=1" "modules/vehicles/form.php?id=1" -d "reg_no=TN30AB1234&vehicle_type=bus&fuel_type=diesel&ownership=owned&status=available&odometer=500"); ok "odometer decrease allowed only for super admin (admin edit ok)" $([ "$C" = 302 ] && echo 1)
C=$(get "modules/vehicles/view.php?id=1"); ok "vehicle profile with tabs" $([ "$C" = 200 ] && clean && grep -q "Fuel &amp; Mileage" /tmp/out.html && echo 1)
M="modules/m.php?m"
C=$(post $J "$M=drivers" "$M=drivers&new=1" -d "action=save&id=0&name=Ravi&availability=available&licence_no=DL1&licence_expiry=2099-01-01"); ok "create driver" $([ "$C" = 302 ] && echo 1)
C=$(post $J "$M=trips" "$M=trips&new=1" -d "action=save&id=0&vehicle_id=1&driver_id=1&planned_departure=2030-05-01T08:00&planned_arrival=2030-05-01T12:00&status=scheduled"); ok "create trip" $([ "$C" = 302 ] && echo 1)
C=$(post $J "$M=trips" "$M=trips&new=1" -d "action=save&id=0&vehicle_id=1&driver_id=1&planned_departure=2030-05-01T10:00&planned_arrival=2030-05-01T14:00&status=scheduled"); ok "overlapping trip blocked" $(grep -q "overlapping trip" /tmp/out.html && echo 1)
C=$(post $J "$M=trips" "$M=trips&new=1" -d "action=save&id=0&vehicle_id=1&driver_id=1&planned_departure=2030-05-01T10:00&planned_arrival=2030-05-01T14:00&status=scheduled&override=1"); ok "authorised override allowed + audited" $([ "$C" = 302 ] && [ "$(mysql -uroot -N mss_fleet_management -e "select count(*) from audit_logs where action='override'")" -ge 1 ] && echo 1)
C=$(post $J "$M=trips" "$M=trips&new=1" -d "action=save&id=0&vehicle_id=1&planned_departure=2031-05-01T10:00&planned_arrival=2031-05-01T14:00&status=completed&start_odo=900&end_odo=800"); ok "end odometer < start rejected" $(grep -q "cannot be lower" /tmp/out.html && echo 1)
C=$(post $J "$M=trips" "$M=trips&new=1" -d "action=save&id=0&vehicle_id=1&planned_departure=2032-05-01T10:00&planned_arrival=2032-05-01T14:00&status=in_progress"); ok "start trip sets vehicle on_trip" $([ "$(mysql -uroot -N mss_fleet_management -e "select status from vehicles where id=1")" = on_trip ] && echo 1)
for r in "2030-06-01T08:00|1000|50|1" "2030-06-05T08:00|1400|30|0" "2030-06-09T08:00|1600|30|1"; do IFS='|' read a o l f <<< "$r"; post $J "$M=fuel" "$M=fuel&new=1" -d "action=save&id=0&vehicle_id=1&filled_at=$a&litres=$l&price_per_litre=100&odometer=$o&is_full_tank=$f&vendor=HP&receipt_no=R$o" >/dev/null; done
C=$(post $J "$M=fuel" "$M=fuel&new=1" -d "action=save&id=0&vehicle_id=1&filled_at=2030-06-10T08:00&litres=10&price_per_litre=100&odometer=1700&vendor=HP&receipt_no=R1600&is_full_tank=1"); ok "duplicate fuel receipt rejected" $(grep -q "Duplicate receipt" /tmp/out.html && echo 1)
C=$(post $J "$M=fuel" "$M=fuel&new=1" -d "action=save&id=0&vehicle_id=1&filled_at=2030-06-11T08:00&litres=10&price_per_litre=100&odometer=1200&is_full_tank=1&receipt_no=Z9"); ok "decreasing fuel odometer rejected" $(grep -q "lower than the previous" /tmp/out.html && echo 1)
get "modules/reports.php?r=fuel_efficiency&from=2030-01-01&to=2030-12-31" >/dev/null; ok "mileage report: 600km / (30 partial+30 full)L = 10 km/l" $(grep -q "<td>10</td>" /tmp/out.html && echo 1)
C=$(post $J "$M=expenses" "$M=expenses&new=1" -d "action=save&id=0&vehicle_id=1&expense_date=2030-06-02&category=toll&amount=250"); C=$(post $J "$M=expenses" "$M=expenses" -d "action=approve&id=1"); ok "expense approved with approver recorded" $([ "$(mysql -uroot -N mss_fleet_management -e "select approval_status,approved_by is not null from expenses where id=1" | tr '\t' ' ')" = "approved 1" ] && echo 1)
post $J "$M=tyres" "$M=tyres&new=1" -d "action=save&id=0&tyre_code=T1&status=in_stock" >/dev/null; post $J "$M=tyres" "$M=tyres&new=1" -d "action=save&id=0&tyre_code=T2&status=in_stock" >/dev/null
C=$(post $J "$M=fitments" "$M=fitments&new=1" -d "action=save&id=0&tyre_id=1&vehicle_id=1&position=FL&fitted_on=2030-06-01"); ok "fit tyre (302) & status fitted" $([ "$C" = 302 ] && [ "$(mysql -uroot -N mss_fleet_management -e "select status from tyres where id=1")" = fitted ] && echo 1)
C=$(post $J "$M=fitments" "$M=fitments&new=1" -d "action=save&id=0&tyre_id=2&vehicle_id=1&position=FL&fitted_on=2030-06-01"); ok "occupied wheel position blocked" $(grep -q "already has an active tyre" /tmp/out.html && echo 1)
C=$(post $J "$M=fitments" "$M=fitments&new=1" -d "action=save&id=0&tyre_id=1&vehicle_id=1&position=FR&fitted_on=2030-06-01"); ok "fitted tyre cannot be double-fitted" $(grep -q "only in-stock or reusable\|already has an active fitment" /tmp/out.html && echo 1)
C=$(post $J "$M=fitments" "$M=fitments&id=1" -d "action=save&id=1&tyre_id=1&vehicle_id=1&position=FL&fitted_on=2030-06-01&removed_on=2030-07-01&removal_reason=worn"); ok "remove tyre -> reusable + movement logged" $([ "$(mysql -uroot -N mss_fleet_management -e "select status from tyres where id=1")-$(mysql -uroot -N mss_fleet_management -e "select count(*) from tyre_movements")" = reusable-2 ] && echo 1)
# uploads
echo '<?php echo 1;' > /tmp/evil.php; cp /tmp/evil.php /tmp/evil.pdf; printf '\x89PNG\r\n\x1a\n\0\0\0\rIHDR\0\0\0\1\0\0\0\1\x08\x06\0\0\0\x1f\x15\xc4\x89\0\0\0\rIDATx\x9cc\xf8\xcf\xc0\0\0\x03\x01\x01\0\x18\xdd\x8d\xb0\0\0\0\0IEND\xaeB`\x82' > /tmp/ok.png
T=$(tok "$M=documents&new=1" $J); C=$(curl -s -b $J -c $J -o /tmp/out.html -w "%{http_code}" -F csrf=$T -F action=save -F id=0 -F vehicle_id=1 -F doc_type=rc -F "file_path=@/tmp/evil.php" "$B/$M=documents"); ok "uploaded .php rejected" $(grep -q "Only PDF, JPG or PNG" /tmp/out.html && echo 1)
T=$(tok "$M=documents&new=1" $J); C=$(curl -s -b $J -c $J -o /tmp/out.html -w "%{http_code}" -F csrf=$T -F action=save -F id=0 -F vehicle_id=1 -F doc_type=rc -F "file_path=@/tmp/evil.pdf" "$B/$M=documents"); ok "PHP disguised as .pdf rejected (MIME check)" $(grep -q "Only PDF, JPG or PNG" /tmp/out.html && echo 1)
T=$(tok "$M=documents&new=1" $J); C=$(curl -s -b $J -c $J -o /tmp/out.html -w "%{http_code}" -F csrf=$T -F action=save -F id=0 -F vehicle_id=1 -F doc_type=insurance -F expiry_date=2099-01-01 -F "file_path=@/tmp/ok.png" "$B/$M=documents"); ok "valid PNG upload stored with random name" $([ "$C" = 302 ] && ls storage/uploads | grep -qE '^[a-f0-9]{32}\.png$' && echo 1)
C=$(get "file.php?id=1"); ok "authorised file download" $([ "$C" = 200 ] && echo 1); C=$(curl -s -o /dev/null -w "%{http_code}" "$B/file.php?id=1"); ok "file download needs login (302)" $([ "$C" = 302 ] && echo 1)
C=$(get "file.php?id=../../config/app.php"); ok "path traversal id rejected" $([ "$C" = 404 ] && echo 1)
# CSV + XSS
post $J "$M=drivers" "$M=drivers&new=1" -d "action=save&id=0&name==cmd|calc&availability=available" >/dev/null; get "$M=drivers&export=csv" >/dev/null; ok "CSV export neutralises formula" $(grep -q "'=cmd" /tmp/out.html && echo 1)
post $J "$M=drivers" "$M=drivers&new=1" -d "action=save&id=0&name=<script>alert(1)</script>&availability=available" >/dev/null; get "$M=drivers" >/dev/null; ok "stored XSS escaped in list" $(! grep -q "<script>alert(1)" /tmp/out.html && grep -q "&lt;script&gt;" /tmp/out.html && echo 1)
# role enforcement (read-only user)
H=$(php -r 'echo password_hash("ReadOnly12345",PASSWORD_DEFAULT);'); mysql -uroot mss_fleet_management -e "INSERT INTO users(role_id,name,email,password_hash) VALUES((SELECT id FROM roles WHERE slug='readonly'),'RO','ro@mss.test','$H')"
post $J2 login.php "login.php" -d "email=ro@mss.test&password=ReadOnly12345" >/dev/null
C=$(get "modules/m.php?m=drivers" $J2); ok "read-only can view" $([ "$C" = 200 ] && echo 1)
C=$(get "modules/m.php?m=drivers&new=1" $J2); ok "read-only blocked from form (403)" $([ "$C" = 403 ] && echo 1)
C=$(post $J2 "$M=drivers" "$M=drivers" -d "action=save&id=0&name=Hack&availability=available"); ok "read-only POST create blocked (403)" $([ "$C" = 403 ] && echo 1)
C=$(post $J2 "$M=drivers" "$M=drivers" -d "action=archive&id=1"); ok "read-only archive blocked (403)" $([ "$C" = 403 ] && echo 1)
C=$(get "modules/m.php?m=users" $J2); ok "read-only blocked from users admin (403)" $([ "$C" = 403 ] && echo 1)
C=$(get "api/dashboard.php?x=1" $J2); ok "read-only can use dashboard API" $([ "$C" = 200 ] && echo 1)
C=$(post $J "$M=drivers" "$M=drivers" -d "action=archive&id=1"); ok "archive driver is soft delete (row kept)" $([ "$(mysql -uroot -N mss_fleet_management -e "select deleted_at is not null from drivers where id=1")" = 1 ] && echo 1)
C=$(post $J "modules/vehicles/index.php" "modules/vehicles/index.php" -d "id=1"); ok "archive vehicle keeps history" $([ "$(mysql -uroot -N mss_fleet_management -e "select count(*) from vehicles where id=1 and deleted_at is not null")" = 1 ] && [ "$(mysql -uroot -N mss_fleet_management -e "select count(*) from fuel_entries")" -ge 3 ] && echo 1)
for i in 1 2 3 4 5 6; do curl -s -o /dev/null -c /tmp/cj_x -b /tmp/cj_x -d "csrf=$(tok login.php /tmp/cj_x)&email=nobody@x.y&password=bad" "$B/login.php"; done; ok "login lockout after repeated failures" $(grep -q "Too many" /tmp/out.html || curl -s -b /tmp/cj_x -c /tmp/cj_x -d "csrf=$(tok login.php /tmp/cj_x)&email=nobody@x.y&password=bad" "$B/login.php" | grep -q "Too many" && echo 1)
ok "no PHP errors logged" $([ ! -s storage/logs/php.log ] && echo 1)
pkill -f "php -S localhost:8089"; echo; echo "SMOKE: $PASS passed, $FAIL failed"; [ $FAIL = 0 ]
