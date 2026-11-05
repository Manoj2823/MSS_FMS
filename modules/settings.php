<?php $R = dirname(__DIR__);
require_once "$R/includes/bootstrap.php";
require_once "$R/includes/layout.php";
require_once "$R/includes/modules.php";
require_login('admin.manage');
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d = (int) ($_POST['expiry_warn_days'] ?? 30);
    if ($d < 1 || $d > 365) {
        flash('Enter 1-365 days.', 'danger');
    } else {
        vset_s($pdo, $d);
        audit('update', 'settings', null, "expiry_warn_days=$d");
        flash('Settings saved.');
    }
    header('Location: settings.php');
    exit;
}
function vset_s($p, $d)
{
    $p->prepare("INSERT INTO system_settings(k,v) VALUES('expiry_warn_days',?) ON DUPLICATE KEY UPDATE v=VALUES(v)")->execute([$d]);
}
$v = $pdo->query("SELECT v FROM system_settings WHERE k='expiry_warn_days'")->fetchColumn() ?: 30;
layout_top('Settings', 'settings', ['Administration', 'Settings']); ?>
<form method="post" class="cardx" style="max-width:480px"><?= csrf_field() ?>
    <h5>System settings</h5><label class="form-label">Expiry warning window (days) - informational; alert bands are
        fixed at 7/15/30</label><input type="number" name="expiry_warn_days" class="form-control mb-3"
        value="<?= e($v) ?>" min="1" max="365"><button class="btn btn-primary">Save</button>
</form>
<?php layout_bottom();
