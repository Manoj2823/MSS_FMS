# MSS Fleet Intelligence
PHP 8.2 + MySQL/MariaDB fleet system (core PHP, PDO, Bootstrap 5, Chart.js). Runs in a subdirectory (`/mss-fleet-management/`) or at a domain root.

## XAMPP setup
1. Start Apache + MySQL in XAMPP. 2. Copy this folder to `C:\xampp\htdocs\mss-fleet-management`.
3. phpMyAdmin -> **Import** `database/mss_fleet_management.sql` (creates DB `mss_fleet_management`; uses `IF NOT EXISTS`, never drops anything).
4. Defaults (`root`, empty password) work on XAMPP. To change, copy `config/local.example.php` to `config/local.php`.
5. Open `http://localhost/mss-fleet-management/setup.php`, create the Super Admin, then **delete `setup.php`**.
6. Sign in at `/mss-fleet-management/login.php`. (Optional, dev only: import `database/seed_development.sql`.)
Troubleshooting: "Database connection failed" -> check MySQL is running and `config/local.php`; blank page -> see `storage/logs/php.log`; enable PHP extensions `pdo_mysql`, `fileinfo`, `mbstring` in php.ini.

## cPanel deployment
1. MySQL Databases: create DB + user, grant ALL on that DB. 2. Upload files (or zip + Extract) to `public_html/mss-fleet-management`.
3. phpMyAdmin: select your DB, import the SQL (remove its first two lines `CREATE DATABASE`/`USE` if cPanel prefixes names, or just import into the selected DB).
4. Copy `config/local.example.php` -> `config/local.php`, set real credentials, `'env'=>'production'` (hides PHP errors). 5. Select PHP 8.2+ (MultiPHP Manager) with pdo_mysql + fileinfo.
6. Make `storage/uploads` and `storage/logs` writable (755/775). `storage/.htaccess` denies direct access; files are served only via `file.php` after a permission check.
7. Enable AutoSSL and force HTTPS. 8. Enable cPanel Backups / schedule DB dumps. 9. Run `setup.php`, then **delete `setup.php`, `tests/` and `database/seed_development.sql`**.
10. Post-deploy checks: `/storage/` URL returns 403, `/config/` not browsable, login lockout works, read-only user cannot save.

## Business rules & ledger
Fuel and maintenance costs live in their own tables; the `expenses` table holds the other approved categories. Reports/dashboard sum fuel + maintenance + approved expenses (excluding `loan_emi`) + `loan_payments`, so nothing is counted twice. Record EMI only in *Loan Payments*.
Mileage = full-tank method (see Fuel Efficiency report). Overlapping trips/drivers and non-cleared vehicles are blocked; a Fleet Manager/Super Admin may tick *override* (audited). Records with history are archived, never deleted.

## Tests
`php tests/run.php` (34 unit/DB tests, rolled back) and `bash tests/smoke.sh` (118 HTTP checks; needs php, curl, mysql CLI; **it drops and recreates the database - run only on a throwaway DB**).
