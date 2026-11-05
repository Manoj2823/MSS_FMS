<?php
declare(strict_types=1);
if (defined('MSS_BOOTSTRAP_LOADED')) {
  return $GLOBALS['CFG'] ?? [];
}
define('MSS_BOOTSTRAP_LOADED', true);
$CFG = require dirname(__DIR__) . '/config/app.php';
$GLOBALS['CFG'] = $CFG;
ini_set('display_errors', $CFG['env'] === 'local' ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . '/storage/logs/php.log');
if (session_status() === PHP_SESSION_NONE) {
  session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
  session_start();
}
$root = str_replace('\\', '/', realpath(dirname(__DIR__)));
$doc = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
if (!defined('BASE')) {
  define('BASE', rtrim(str_replace($doc, '', $root), '/'));   // works in /mss-fleet-management/ or domain root
}
function url(string $p = ''): string
{
  return BASE . '/' . ltrim($p, '/');
}
function e($v): string
{
  return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function db(): PDO
{
  static $pdo;
  global $CFG;
  if (!$pdo) {
    try {
      $port = !empty($CFG['db_port']) ? ";port={$CFG['db_port']}" : '';
      $pdo = new PDO("mysql:host={$CFG['db_host']}{$port};dbname={$CFG['db_name']};charset=utf8mb4", $CFG['db_user'], $CFG['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    } catch (Throwable $x) {
      error_log($x->getMessage());
      http_response_code(500);
      exit('Database connection failed. Check config/local.php.');
    }
  }
  return $pdo;
}
function csrf_token(): string
{
  return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function csrf_field(): string
{
  return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function csrf_check(): void
{
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(419);
    exit('Invalid or expired form token.');
  }
}
function flash(?string $msg = null, string $type = 'success')
{
  if ($msg !== null) {
    $_SESSION['flash'] = [$type, $msg];
    return;
  }
  $f = $_SESSION['flash'] ?? null;
  unset($_SESSION['flash']);
  return $f;
}
const PERMS = [
  'super_admin' => ['*'],
  'fleet_manager' => ['*.view', 'vehicles.*', 'drivers.*', 'trips.*', 'routes.*', 'fuel.*', 'maintenance.*', 'breakdowns.*', 'documents.*', 'tyres.*', 'expenses.*', 'branches.*', 'loans.view'],
  'operations' => ['dashboard.view', 'vehicles.view', 'drivers.view', 'routes.*', 'fuel.view', 'documents.view', 'reports.view', 'trips.view', 'trips.edit', 'trips.archive'],
  'maintenance' => ['dashboard.view', 'vehicles.view', 'maintenance.*', 'breakdowns.*', 'documents.view', 'tyres.view', 'reports.view'],
  'accounts' => ['dashboard.view', 'vehicles.view', 'expenses.*', 'loans.*', 'fuel.view', 'maintenance.view', 'reports.view'],
  'readonly' => ['*.view']
];
// Patterns: '*' (all), 'mod.*', '*.view', exact. Admin-only areas require 'admin.manage' (only '*').
function can(string $p): bool
{
  [$m, $a] = explode('.', $p . '.');
  foreach (PERMS[$_SESSION['role'] ?? ''] ?? [] as $x) {
    if ($x === '*' || $x === $p || $x === $m . '.*' || $x === '*.' . $a)
      return true;
  }
  return false;
}
function require_login(string $perm = 'dashboard.view', bool $json = false): void
{
  global $CFG;
  $fail = function (int $c, string $m) use ($json) {
    if ($json) {
      http_response_code($c);
      header('Content-Type: application/json');
      exit(json_encode(['error' => $m])); }if ($c === 401) {
      header('Location: ' . url('login.php'));
      exit; }http_response_code($c);
    exit($m); };
  if (empty($_SESSION['uid']))
    $fail(401, 'Not signed in');
  if (time() - ($_SESSION['last'] ?? 0) > $CFG['session_timeout']) {
    session_destroy();
    header('Location: ' . url('login.php?timeout=1'));
    exit;
  }
  $_SESSION['last'] = time();
  if (!can($perm))
    $fail(403, '403 - You do not have permission for this action.');
}
function audit(string $action, string $entity, ?int $id = null, string $d = ''): void
{
  db()->prepare('INSERT INTO audit_logs(user_id,action,entity,entity_id,details,ip) VALUES(?,?,?,?,?,?)')->execute([$_SESSION['uid'] ?? null, $action, $entity, $id, $d, $_SERVER['REMOTE_ADDR'] ?? '']);
}
function expiry_badge(?string $d): array
{
  if (!$d)
    return ['secondary', 'Not set'];
  $n = (int) floor((strtotime($d) - strtotime('today')) / 86400);
  return $n < 0 ? ['danger', 'Expired'] : ($n <= 7 ? ['danger', "⚠️ {$n}d left (Due Soon)"] : ($n <= 15 ? ['warning', "{$n}d left"] : ($n <= 30 ? ['info', "{$n}d left"] : ['success', 'Valid'])));
}

function get_urgent_expiries(int $days = 7): array
{
  static $cache;
  if ($cache !== null)
    return $cache;
  $pdo = db();
  $list = [];

  $cols = [
    'insurance_expiry' => 'Insurance',
    'permit_expiry' => 'Permit',
    'fitness_expiry' => 'Fitness',
    'road_tax_expiry' => 'Road Tax',
    'puc_expiry' => 'PUC',
    'next_service_date' => 'Service Due'
  ];
  foreach ($cols as $col => $label) {
    $st = $pdo->prepare("SELECT id, reg_no, make, model, $col as due_date, DATEDIFF($col, CURDATE()) as days_left 
      FROM vehicles 
      WHERE deleted_at IS NULL AND $col IS NOT NULL AND $col <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
      ORDER BY $col ASC");
    $st->execute([$days]);
    foreach ($st->fetchAll() as $r) {
      $daysLeft = (int) $r['days_left'];
      $badge = $daysLeft < 0 ? 'Expired' : ($daysLeft === 0 ? 'Today' : ($daysLeft === 1 ? 'Tomorrow' : "{$daysLeft}d left"));
      $list[] = [
        'entity' => 'vehicle',
        'id' => (int) $r['id'],
        'target' => $r['reg_no'],
        'sub' => trim($r['make'] . ' ' . $r['model']),
        'type' => $label,
        'date' => $r['due_date'],
        'days_left' => $daysLeft,
        'badge' => $badge,
        'url' => url("modules/vehicles/view.php?id={$r['id']}")
      ];
    }
  }

  $st = $pdo->prepare("SELECT id, name, licence_no, mobile, licence_expiry, DATEDIFF(licence_expiry, CURDATE()) as days_left 
    FROM drivers 
    WHERE deleted_at IS NULL AND licence_expiry IS NOT NULL AND licence_expiry <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
    ORDER BY licence_expiry ASC");
  $st->execute([$days]);
  foreach ($st->fetchAll() as $r) {
    $daysLeft = (int) $r['days_left'];
    $badge = $daysLeft < 0 ? 'Expired' : ($daysLeft === 0 ? 'Today' : ($daysLeft === 1 ? 'Tomorrow' : "{$daysLeft}d left"));
    $list[] = [
      'entity' => 'driver',
      'id' => (int) $r['id'],
      'target' => $r['name'],
      'sub' => $r['licence_no'] ?: $r['mobile'],
      'type' => 'Driver Licence',
      'date' => $r['licence_expiry'],
      'days_left' => $daysLeft,
      'badge' => $badge,
      'url' => '#'
    ];
  }

  usort($list, fn($a, $b) => $a['days_left'] <=> $b['days_left']);
  $cache = $list;
  return $cache;
}
const STATUSES = ['available' => 'Available', 'on_trip' => 'On Trip', 'reserved' => 'Reserved', 'maintenance' => 'Under Maintenance', 'accident_hold' => 'Accident Hold', 'inactive' => 'Inactive', 'disposed' => 'Disposed'];
const TYPES = ['van' => 'Van / Mini-bus', 'truck' => 'Truck / Lorry', 'car' => 'Car / Jeep', 'bike' => 'Bike', 'other' => 'Other'];
