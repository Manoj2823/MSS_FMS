<?php require __DIR__ . '/includes/bootstrap.php';
$err = '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $pdo = db();
  $n = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip=? AND attempted_at>NOW()-INTERVAL 15 MINUTE");
  $n->execute([$ip]);
  if ($n->fetchColumn() >= 5)
    $err = 'Too many failed attempts. Try again in 15 minutes.';
  else {
    $st = $pdo->prepare('SELECT u.*,r.slug,r.name rname FROM users u JOIN roles r ON r.id=u.role_id WHERE u.email=? AND u.is_active=1');
    $st->execute([trim($_POST['email'] ?? '')]);
    $u = $st->fetch();
    if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
      session_regenerate_id(true);
      $_SESSION = ['uid' => (int) $u['id'], 'name' => $u['name'], 'role' => $u['slug'], 'role_name' => $u['rname'], 'last' => time()];
      $pdo->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$u['id']]);
      audit('login', 'user', (int) $u['id']);
      header('Location: ' . url('dashboard.php'));
      exit;
    }
    $pdo->prepare('INSERT INTO login_attempts(ip,email) VALUES(?,?)')->execute([$ip, $_POST['email'] ?? '']);
    $err = 'Invalid email or password.';
  }
}
?><!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Sign in · MSS Fleet Intelligence</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="<?= url('assets/css/app.css') ?>" rel="stylesheet">
</head>

<body>
  <div class="login-wrap">
    <form class="login-card" method="post"><?= csrf_field() ?>
      <div class="brand text-dark"><span class="mark" style="background:var(--navy);color:#fff">MSS</span>
        <div><b>MSS Fleet Intelligence</b><small class="text-secondary">Fleet Operations &amp; Management System</small>
        </div>
      </div>
      <?php if ($err || isset($_GET['timeout'])): ?>
        <div class="alert alert-danger py-2"><?= e($err ?: 'Session expired. Please sign in again.') ?></div><?php endif; ?>
      <label class="form-label">Email</label><input type="email" name="email" class="form-control mb-3" required
        autofocus>
      <label class="form-label">Password</label><input type="password" name="password" class="form-control mb-3"
        required>
      <div class="d-flex gap-2 mb-3">
        <button type="submit" class="btn btn-primary flex-fill" style="background:var(--royal)">Sign in</button>
        <a href="<?= url('signup.php') ?>" class="btn btn-outline-primary flex-fill">Sign Up</a>
      </div>
      <div class="text-center">
        <small class="text-secondary">Don't have an account? <a href="<?= url('signup.php') ?>" class="text-decoration-none fw-semibold">Create account</a></small>
      </div>
    </form>
  </div>
</body>

</html>