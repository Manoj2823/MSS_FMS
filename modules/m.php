<?php // Generic module engine: list / create / edit / archive / CSV / approvals, driven by includes/modules.php
$R = dirname(__DIR__);
require_once "$R/includes/bootstrap.php";
require_once "$R/includes/layout.php";
require_once "$R/includes/modules.php";
require_once "$R/includes/rules.php";
$key = $_GET['m'] ?? '';
$M = mods()[$key] ?? null;
if (!$M) {
  http_response_code(404);
  exit('Unknown module');
}
$P = $M['perm'];
$T = $M['table'];
$F = $M['fields'];
$soft = in_array($T, SOFT, true);
$ro = !empty($M['ro']);
$pdo = db();
$need = fn($a) => $P === 'admin' ? 'admin.manage' : "$P.$a";
require_login($need('view'));
$label = fn($s) => ucwords(str_replace('_', ' ', (string) $s));
function ref_opts(PDO $p, string $x): array
{
  [$t, $c] = explode(':', $x);
  return $p->query("SELECT id,$c AS l FROM $t" . (in_array($t, SOFT, true) ? ' WHERE deleted_at IS NULL' : '') . " ORDER BY $c LIMIT 1000")->fetchAll();
}
function norm($f, $v)
{
  $v = is_string($v) ? trim($v) : $v;
  if ($v === '' || $v === null)
    return null;
  return $f['t'] === 'datetime' ? date('Y-m-d H:i:s', strtotime($v)) : $v;
}
$err = [];
$id = (int) ($_GET['id'] ?? 0);
$old = [];
$in = [];
$mode = isset($_GET['new']) || $id ? 'form' : 'list';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $act = $_POST['action'] ?? 'save';
  $id = (int) ($_POST['id'] ?? 0);
  if ($act === 'archive') {
    require_login($need('archive'));
    if ($soft) {
      vset($pdo, "UPDATE $T SET deleted_at=NOW() WHERE id=? AND deleted_at IS NULL", [$id]);
      audit('archive', $T, $id);
      flash('Record archived (history retained).');
    }
    header("Location: m.php?m=$key");
    exit;
  }
  if (in_array($act, ['approve', 'reject'], true) && !empty($M['approval'])) {
    require_login('expenses.approve');
    vset($pdo, "UPDATE expenses SET approval_status=?,approved_by=?,approved_at=NOW() WHERE id=? AND approval_status='pending'", [$act === 'approve' ? 'approved' : 'rejected', $_SESSION['uid'], $id]);
    audit($act, 'expense', $id);
    flash("Expense {$act}d.");
    header("Location: m.php?m=$key");
    exit;
  }
  require_login($need('edit'));
  if ($ro) {
    http_response_code(403);
    exit('Read-only');
  }
  if ($id) {
    $s = $pdo->prepare("SELECT * FROM $T WHERE id=?" . ($soft ? ' AND deleted_at IS NULL' : ''));
    $s->execute([$id]);
    $old = $s->fetch() ?: [];
    if (!$old) {
      http_response_code(404);
      exit('Not found');
    }
  }
  $d = [];
  $in = $_POST;
  $up = null;
  foreach ($F as $n => $f) {
    $t = $f['t'];
    if ($t === 'calc' || $t === 'flag')
      continue;
    if ($t === 'auto') {
      $d[$n] = null;
      continue;
    }
    if ($t === 'file') {
      if (!empty($_FILES[$n]['name'])) {
        $fl = $_FILES[$n];
        if ($fl['error'] !== UPLOAD_ERR_OK || $fl['size'] > 5 * 1048576) {
          $err[$n] = 'Upload failed or file exceeds 5 MB.';
          continue;
        }
        $ext = strtolower(pathinfo($fl['name'], PATHINFO_EXTENSION));
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($fl['tmp_name']);
        $ok = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
        if (!isset($ok[$ext]) || $ok[$ext] !== $mime) {
          $err[$n] = 'Only PDF, JPG or PNG files are allowed.';
          continue;
        }
        $up = bin2hex(random_bytes(16)) . '.' . $ext;
        $d[$n] = $up;
        $fl['_n'] = $up;
        $upl[$n] = $fl;
      } elseif ($old)
        $d[$n] = $old[$n];
      else
        $d[$n] = null;
      continue;
    }
    if ($t === 'password') {
      $v = $_POST[$n] ?? '';
      if ($v !== '')
        $d[$n] = password_hash($v, PASSWORD_DEFAULT);
      elseif ($old)
        $d[$n] = $old[$n];
      else
        $d[$n] = null;
      continue;
    }
    $v = norm($f, $_POST[$n] ?? null);
    if ($v === null) {
      if (strpos($f['f'], 'r') !== false)
        $err[$n] = $f['l'] . ' is required.';
      $d[$n] = null;
      continue;
    }
    switch ($t) {
      case 'number':
        if (!ctype_digit((string) $v))
          $err[$n] = 'Enter a whole number (0 or more).';
        else
          $v = (int) $v;
        break;
      case 'decimal':
        if (!is_numeric($v) || $v < 0)
          $err[$n] = 'Enter a valid non-negative number.';
        break;
      case 'date':
        $x = DateTime::createFromFormat('Y-m-d', $v);
        if (!$x || $x->format('Y-m-d') !== $v)
          $err[$n] = 'Invalid date.';
        break;
      case 'datetime':
        if (strtotime($v) === false)
          $err[$n] = 'Invalid date/time.';
        break;
      case 'select':
        if (!in_array($v, $f['x'], true))
          $err[$n] = 'Invalid choice.';
        break;
      case 'ref':
        [$rt] = explode(':', $f['x']);
        $c = $pdo->prepare("SELECT id FROM $rt WHERE id=?" . (in_array($rt, SOFT, true) ? ' AND deleted_at IS NULL' : ''));
        $c->execute([(int) $v]);
        if (!$c->fetch())
          $err[$n] = 'Selected record not found.';
        else
          $v = (int) $v;
        break;
    }
    $d[$n] = $v;
  }
  if (!$err && function_exists($h = 'v_' . $key)) {
    $err = $h($d, $id, $old, $pdo);
  }
  if (!$err) {
    try {
      $pdo->beginTransaction();
      $cols = array_keys($d);
      if ($id) {
        vset($pdo, "UPDATE $T SET " . implode(',', array_map(fn($c) => "$c=:$c", $cols)) . " WHERE id=:__id", $d + ['__id' => $id]);
      } else {
        vset($pdo, "INSERT INTO $T(" . implode(',', $cols) . ') VALUES(:' . implode(',:', $cols) . ')', $d);
        $id = (int) $pdo->lastInsertId();
      }
      if (function_exists($h = 'a_' . $key))
        $h($d, $id, $old, $pdo);
      foreach ($upl ?? [] as $n => $fl) {
        if (!move_uploaded_file($fl['tmp_name'], "$R/storage/uploads/" . $fl['_n']))
          throw new RuntimeException('upload move failed');
      }
      $pdo->commit();
      audit($old ? 'update' : 'create', $T, $id);
      flash('Saved.');
      foreach ($WARN as $w)
        flash($w, 'warning');
      header("Location: m.php?m=$key");
      exit;
    } catch (Throwable $x) {
      if ($pdo->inTransaction())
        $pdo->rollBack();
      error_log($x->getMessage());
      $err['_'] = ($x instanceof PDOException && $x->getCode() === '23000') ? 'Duplicate or conflicting value (unique constraint).' : 'Could not save. Check the values and try again.';
    }
  }
  $mode = 'form';
}
if ($mode === 'form' && !$in && $id) {
  $s = $pdo->prepare("SELECT * FROM $T WHERE id=?" . ($soft ? ' AND deleted_at IS NULL' : ''));
  $s->execute([$id]);
  $in = $s->fetch() ?: [];
  if (!$in) {
    http_response_code(404);
    exit('Not found');
  }
}
if ($mode === 'form') {
  if ($ro) {
    http_response_code(403);
    exit('Read-only');
  }
  require_login($need('edit'));
  layout_top(($id ? 'Edit ' : 'New ') . $M['n'], $key, [$M['g'], $M['n'], $id ? 'Edit' : 'New']);
  $in['_'] ??= null; ?>
  <form method="post" enctype="multipart/form-data" class="cardx" novalidate><?= csrf_field() ?><input type="hidden"
      name="id" value="<?= $id ?>"><?php if (isset($err['_'])): ?>
      <div class="alert alert-danger"><?= e($err['_']) ?></div><?php endif; ?>
    <div class="row g-3">
      <?php foreach ($F as $n => $f) {
        $t = $f['t'];
        if (in_array($t, ['calc', 'auto'], true))
          continue;
        if ($t === 'flag' && !can($need('override')))
          continue;
        $v = $in[$n] ?? '';
        if ($t === 'datetime' && $v)
          $v = date('Y-m-d\TH:i', strtotime($v));
        $c = isset($err[$n]) ? ' is-invalid' : ''; ?>
        <div class="col-12 col-md-4"><label
            class="form-label"><?= e($f['l']) ?><?= strpos($f['f'], 'r') !== false ? ' *' : '' ?></label>
          <?php if ($t === 'select'): ?><select name="<?= $n ?>" class="form-select<?= $c ?>">
              <option value="">—</option><?php foreach ($f['x'] as $o): ?>
                <option value="<?= e($o) ?>" <?= (string) $v === (string) $o ? 'selected' : '' ?>><?= e($label($o)) ?></option>
              <?php endforeach; ?>
            </select>
          <?php elseif ($t === 'ref'): ?><select name="<?= $n ?>" class="form-select<?= $c ?>">
              <option value="">—</option><?php foreach (ref_opts($pdo, $f['x']) as $o): ?>
                <option value="<?= $o['id'] ?>" <?= (string) $v === (string) $o['id'] ? 'selected' : '' ?>><?= e($o['l']) ?></option>
              <?php endforeach; ?>
            </select>
          <?php elseif ($t === 'textarea'): ?><textarea name="<?= $n ?>" rows="2" class="form-control"><?= e($v) ?></textarea>
          <?php elseif ($t === 'file'): ?><input type="file" name="<?= $n ?>" accept=".pdf,.jpg,.jpeg,.png"
              class="form-control<?= $c ?>"><?php if ($v): ?><small class="text-secondary">A file is already stored; upload to
                replace.</small><?php endif; ?>
          <?php elseif ($t === 'flag'): ?>
            <div class="form-check"><input type="checkbox" class="form-check-input" name="<?= $n ?>" value="1"></div>
          <?php elseif ($t === 'password'): ?><input type="password" name="<?= $n ?>" autocomplete="new-password"
              class="form-control<?= $c ?>">
          <?php else:
            $ty = ['number' => 'number', 'decimal' => 'number', 'date' => 'date', 'datetime' => 'datetime-local'][$t] ?? 'text'; ?><input
              type="<?= $ty ?>" <?= $t === 'decimal' ? 'step="0.01" min="0"' : ($t === 'number' ? 'min="0"' : '') ?> name="<?= $n ?>"
              class="form-control<?= $c ?>" value="<?= e($v) ?>"><?php endif; ?>
          <?php if (isset($err[$n])): ?>
            <div class="text-danger small"><?= e($err[$n]) ?></div><?php endif; ?>
        </div><?php } ?>
    </div>
    <div class="mt-3 d-flex gap-2"><button class="btn btn-primary">Save</button><a class="btn btn-light"
        href="m.php?m=<?= $key ?>">Cancel</a></div>
  </form><?php layout_bottom();
  exit;
}
// ---- list ----
$q = trim($_GET['q'] ?? '');
$w = $soft ? 't.deleted_at IS NULL' : '1=1';
$p = [];
$sel = 't.*';
$jn = '';
foreach ($F as $n => $f)
  if ($f['t'] === 'ref') {
    [$rt, $rc] = explode(':', $f['x']);
    $sel .= ",r_$n.$rc AS {$n}__l";
    $jn .= " LEFT JOIN $rt r_$n ON r_$n.id=t.$n";
  }
$tx = array_keys(array_filter($F, fn($f) => $f['t'] === 'text'));
if ($q !== '' && $tx) {
  $w .= ' AND (' . implode(' OR ', array_map(fn($c) => "t.$c LIKE ?", $tx)) . ')';
  foreach ($tx as $_)
    $p[] = "%$q%";
}
$dc = null;
foreach (['planned_departure', 'filled_at', 'service_date', 'expense_date', 'expiry_date', 'paid_on', 'breakdown_date', 'created_at'] as $c)
  if (isset($F[$c])) {
    $dc = $c;
    break;
  }
if ($dc && ($_GET['from'] ?? '') !== '') {
  $w .= " AND DATE(t.$dc)>=?";
  $p[] = $_GET['from'];
}
if ($dc && ($_GET['to'] ?? '') !== '') {
  $w .= " AND DATE(t.$dc)<=?";
  $p[] = $_GET['to'];
}
if (isset($F['vehicle_id']) && (int) ($_GET['vehicle_id'] ?? 0)) {
  $w .= ' AND t.vehicle_id=?';
  $p[] = (int) $_GET['vehicle_id'];
}
if (isset($F['status']) && $F['status']['t'] === 'select' && in_array($_GET['status'] ?? '', $F['status']['x'], true)) {
  $w .= ' AND t.status=?';
  $p[] = $_GET['status'];
}
$ord = $M['sort'] ?? 't.id DESC';
if (isset($M['sort']))
  $ord = 't.' . $M['sort'];
$cols = array_filter($F, fn($f) => strpos($f['f'], 'l') !== false);
$cell = function ($r, $n, $f) use ($label) {
  if ($f['t'] === 'ref')
    return $r[$n . '__l'] ?? '—';
  $v = $r[$n] ?? '';
  if ($v === '' || $v === null)
    return '—';
  if ($f['t'] === 'select' && $n !== 'is_active')
    return $label($v);
  if ($f['t'] === 'file')
    return 'Attached';
  return $v; };
if (($_GET['export'] ?? '') === 'csv') {
  header('Content-Type: text/csv; charset=utf-8');
  header("Content-Disposition: attachment; filename=\"$key.csv\"");
  $o = fopen('php://output', 'w');
  fwrite($o, "\xEF\xBB\xBF");
  fputcsv($o, array_map(fn($f) => $f['l'], $cols));
  $s = $pdo->prepare("SELECT $sel FROM $T t$jn WHERE $w ORDER BY $ord");
  $s->execute($p);
  foreach ($s as $r)
    fputcsv($o, array_map(fn($n) => csv_safe($cell($r, $n, $F[$n])), array_keys($cols)));
  audit('export', $T);
  exit;
}
$per = 20;
$pg = max(1, (int) ($_GET['page'] ?? 1));
$c = $pdo->prepare("SELECT COUNT(*) FROM $T t WHERE $w");
$c->execute($p);
$tot = (int) $c->fetchColumn();
$pages = max(1, (int) ceil($tot / $per));
$s = $pdo->prepare("SELECT $sel FROM $T t$jn WHERE $w ORDER BY $ord LIMIT $per OFFSET " . (($pg - 1) * $per));
$s->execute($p);
$rows = $s->fetchAll();
$qs = fn($x = []) => http_build_query(array_merge($_GET, $x));
layout_top($M['n'], $key, [$M['g'], $M['n']]); ?>
<div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
  <h4 class="mb-0"><?= e($M['n']) ?> <small class="text-secondary fs-6">(<?= $tot ?>)</small></h4>
  <div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="?<?= e($qs(['export' => 'csv'])) ?>"><i
        class="bi bi-download"></i> CSV</a>
    <?php if (!$ro && can($need('edit'))): ?><a class="btn btn-primary" href="?m=<?= $key ?>&new=1"><i
          class="bi bi-plus-lg"></i> New</a><?php endif; ?></div>
</div>
<form class="row g-2 mb-3"><input type="hidden" name="m" value="<?= e($key) ?>">
  <div class="col-12 col-md-4"><input name="q" value="<?= e($q) ?>" class="form-control" placeholder="Search…"></div>
  <?php if ($dc): ?>
    <div class="col-6 col-md-2"><input type="date" name="from" value="<?= e($_GET['from'] ?? '') ?>" class="form-control">
    </div>
    <div class="col-6 col-md-2"><input type="date" name="to" value="<?= e($_GET['to'] ?? '') ?>" class="form-control"></div>
  <?php endif; ?>
  <?php if (isset($F['status']) && $F['status']['t'] === 'select'): ?>
    <div class="col-6 col-md-2"><select name="status" class="form-select" onchange="this.form.submit()">
        <option value="">All status</option><?php foreach ($F['status']['x'] as $o): ?>
          <option value="<?= e($o) ?>" <?= ($_GET['status'] ?? '') === $o ? 'selected' : '' ?>><?= e($label($o)) ?></option>
        <?php endforeach; ?>
      </select></div><?php endif; ?>
  <div class="col-6 col-md-1"><button class="btn btn-light w-100">Go</button></div>
</form>
<div class="cardx p-0">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0 table-stack">
      <thead>
        <tr><?php foreach ($cols as $f): ?>
            <th><?= e($f['l']) ?></th><?php endforeach; ?>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr><?php foreach ($cols as $n => $f):
            $v = $cell($r, $n, $f); ?>
              <td data-l="<?= e($f['l']) ?>"><?php if ($n === 'approval_status'): ?><span
                    class="badge text-bg-<?= ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'][$v] ?? 'secondary' ?>"><?= e($label($v)) ?></span>
                <?php elseif ($n === 'file_path' && $r[$n]): ?><a href="<?= url('file.php?id=' . $r['id']) ?>" target="_blank">View
                    file</a><?php elseif (in_array($n, ['expiry_date', 'licence_expiry']) && $r[$n]):
                [$bc, $bt] = expiry_badge($r[$n]); ?><?= e($v) ?>
                  <span class="badge text-bg-<?= $bc ?>"><?= e($bt) ?></span><?php else: ?><?= e($v) ?><?php endif; ?></td>
            <?php endforeach; ?>
            <td class="text-end text-nowrap"><?php if (!$ro && can($need('edit'))): ?><a class="btn btn-sm btn-light"
                  href="?m=<?= $key ?>&id=<?= $r['id'] ?>">Edit</a><?php endif; ?>
              <?php if (!empty($M['approval']) && $r['approval_status'] === 'pending' && can('expenses.approve')):
                foreach (['approve' => 'success', 'reject' => 'danger'] as $a => $cl): ?>
                  <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id"
                      value="<?= $r['id'] ?>"><button name="action" value="<?= $a ?>"
                      class="btn btn-sm btn-outline-<?= $cl ?>"><?= ucfirst($a) ?></button></form><?php endforeach; endif;
              if ($soft && can($need('archive'))): ?>
                <form method="post" class="d-inline" data-confirm="Archive this record? History is kept.">
                  <?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><button name="action"
                    value="archive" class="btn btn-sm btn-outline-danger">Archive</button></form><?php endif; ?>
            </td>
          </tr>
        <?php endforeach;
        if (!$rows): ?>
          <tr>
            <td colspan="<?= count($cols) + 1 ?>" class="text-center text-secondary py-5"><i
                class="bi bi-inbox fs-2 d-block"></i>No records yet.</td>
          </tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<nav class="mt-3">
  <ul class="pagination justify-content-center flex-wrap"><?php for ($i = 1; $i <= $pages; $i++): ?>
      <li class="page-item <?= $i === $pg ? 'active' : '' ?>"><a class="page-link" href="?<?= e($qs(['page' => $i])) ?>"><?= $i ?></a>
      </li><?php endfor; ?>
  </ul>
</nav>
<?php layout_bottom();
