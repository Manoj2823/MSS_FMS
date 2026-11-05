<?php
require __DIR__ . '/includes/bootstrap.php';

$pdo = db();
$roles = $pdo->query('SELECT id, slug, name FROM roles ORDER BY id')->fetchAll();
$branches = $pdo->query('SELECT id, name, city FROM branches WHERE is_active=1 ORDER BY name')->fetchAll();

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $role_id = (int) ($_POST['role_id'] ?? 0);
  $branch_id = !empty($_POST['branch_id']) ? (int) $_POST['branch_id'] : null;

  if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $err = 'Please enter a valid name and email address.';
  } elseif (strlen($password) < 6) {
    $err = 'Password must be at least 6 characters.';
  } else {
    // Check if email already registered
    $chk = $pdo->prepare('SELECT id FROM users WHERE email=?');
    $chk->execute([$email]);
    if ($chk->fetch()) {
      $err = 'This email is already registered. Please sign in.';
    } else {
      // Find role info
      $roleRow = null;
      foreach ($roles as $r) {
        if ((int) $r['id'] === $role_id) {
          $roleRow = $r;
          break;
        }
      }
      if (!$roleRow) {
        // Default to super_admin if first user, else fleet_manager
        $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $defSlug = $userCount === 0 ? 'super_admin' : 'fleet_manager';
        foreach ($roles as $r) {
          if ($r['slug'] === $defSlug) {
            $roleRow = $r;
            break;
          }
        }
      }

      $rid = $roleRow ? (int) $roleRow['id'] : 1;
      $rslug = $roleRow ? $roleRow['slug'] : 'super_admin';
      $rname = $roleRow ? $roleRow['name'] : 'Super Admin';

      $hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare('INSERT INTO users(role_id, branch_id, name, email, password_hash) VALUES(?,?,?,?,?)');
      $stmt->execute([$rid, $branch_id, $name, $email, $hash]);
      $uid = (int) $pdo->lastInsertId();

      session_regenerate_id(true);
      $_SESSION = [
        'uid' => $uid,
        'name' => $name,
        'role' => $rslug,
        'role_name' => $rname,
        'last' => time()
      ];
      audit('signup', 'user', $uid, 'User self registered');
      flash('Registration successful! Welcome to MSS Fleet Intelligence.', 'success');
      header('Location: ' . url('dashboard.php'));
      exit;
    }
  }
}
?><!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Sign Up · MSS Fleet Intelligence</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="<?= url('assets/css/app.css') ?>" rel="stylesheet">
  <style>
    .login-card {
      max-width: 460px;
    }
  </style>
</head>

<body>
  <div class="login-wrap">
    <form class="login-card" method="post">
      <?= csrf_field() ?>
      <div class="brand text-dark mb-3">
        <span class="mark" style="background:var(--navy);color:#fff">MSS</span>
        <div>
          <b>MSS Fleet Intelligence</b>
          <small class="text-secondary">Create a New Account</small>
        </div>
      </div>

      <?php if ($err): ?>
        <div class="alert alert-danger py-2"><?= e($err) ?></div>
      <?php endif; ?>

      <div class="mb-2">
        <label class="form-label mb-1">Full Name</label>
        <input name="name" class="form-control" placeholder="Enter your full name"
          value="<?= e($_POST['name'] ?? '') ?>" required autofocus>
      </div>

      <div class="mb-2">
        <label class="form-label mb-1">Email Address</label>
        <input name="email" type="email" class="form-control" placeholder="name@mss.com"
          value="<?= e($_POST['email'] ?? '') ?>" required>
      </div>

      <div class="mb-2">
        <label class="form-label mb-1">Password</label>
        <input name="password" type="password" class="form-control" placeholder="At least 6 characters" required>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-6">
          <label class="form-label mb-1">Role</label>
          <select name="role_id" class="form-select">
            <?php foreach ($roles as $r): ?>
              <option value="<?= $r['id'] ?>" <?= ($r['slug'] === 'super_admin' ? 'selected' : '') ?>>
                <?= e($r['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6">
          <label class="form-label mb-1">Branch</label>
          <select name="branch_id" class="form-select">
            <option value="">All / Head Office</option>
            <?php foreach ($branches as $b): ?>
              <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="d-flex gap-2 mb-3">
        <button type="submit" class="btn btn-primary flex-fill" style="background:var(--royal)">Sign Up</button>
        <a href="<?= url('login.php') ?>" class="btn btn-outline-secondary flex-fill">Back to Login</a>
      </div>

      <div class="text-center">
        <small class="text-secondary">Already have an account? <a href="<?= url('login.php') ?>"
            class="text-decoration-none fw-semibold">Sign in</a></small>
      </div>
    </form>
  </div>
</body>

</html>