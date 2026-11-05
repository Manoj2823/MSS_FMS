<?php // First-admin setup; works only while users table is empty. DELETE this file after use.
require __DIR__ . '/includes/bootstrap.php';
$pdo = db();
if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0)
  exit('Setup already completed. Delete setup.php.');
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $n = trim($_POST['name'] ?? '');
  $m = trim($_POST['email'] ?? '');
  $p = $_POST['password'] ?? '';
  if ($n === '' || !filter_var($m, FILTER_VALIDATE_EMAIL) || strlen($p) < 10)
    $err = 'Enter a name, valid email and a password of 10+ characters.';
  else {
    $rid = $pdo->query("SELECT id FROM roles WHERE slug='super_admin'")->fetchColumn();
    $pdo->prepare('INSERT INTO users(role_id,name,email,password_hash) VALUES(?,?,?,?)')->execute([$rid, $n, $m, password_hash($p, PASSWORD_DEFAULT)]);
    $pdo->exec("INSERT IGNORE INTO branches(name,city) VALUES('Head Office','Mettur')");
    header('Location: ' . url('login.php'));
    exit;
  }
}
?><!doctype html>
<html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Setup</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= url('assets/css/app.css') ?>" rel="stylesheet">
</head>

<body>
  <div class="login-wrap">
    <form class="login-card" method="post"><?= csrf_field() ?>
      <h5>Create Super Admin</h5><?php if ($err): ?>
        <div class="alert alert-danger py-2"><?= e($err) ?></div><?php endif; ?>
      <input name="name" class="form-control mb-2" placeholder="Full name" required><input name="email" type="email"
        class="form-control mb-2" placeholder="Email" required>
      <input name="password" type="password" class="form-control mb-3" placeholder="Password (10+ chars)"
        required><button class="btn btn-primary w-100">Create admin</button>
    </form>
  </div>
</body>

</html>