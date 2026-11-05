<?php
if (defined('MSS_LAYOUT_LOADED')) return;
define('MSS_LAYOUT_LOADED', true);
require_once __DIR__ . "/modules.php";
require_once __DIR__ . "/rules.php";
function bell(): int
{
    require_once __DIR__ . "/reports.php";
    try {
        $n = 0;
        foreach (run_report(db(), "doc_expiry", []) as $r)
            if ($r["status"] !== "Valid")
                $n++;
        return $n;
    } catch (Throwable $x) {
        return 0;
    }
}
function layout_top(string $title, string $active = 'dashboard', array $crumbs = []): void
{
    $f = flash(); ?>
    <!doctype html>
    <html lang="en">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title><?= e($title) ?> · MSS Fleet Intelligence</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link href="<?= url('assets/css/app.css') ?>" rel="stylesheet">
    </head>

    <body>
        <aside class="sidebar" id="sidebar">
            <div class="brand"><span class="mark">MSS</span>
                <div><b>MSS TRANSPORT</b><small>Fleet Intelligence</small></div>
            </div>
            <nav class="nav flex-column">
                <a class="nav-link <?= $active === 'dashboard' ? 'active' : '' ?>" href="<?= url('dashboard.php') ?>"><i
                        class="bi bi-grid-1x2-fill"></i>Dashboard</a>

                <!-- Vehicles Collapsible Menu (Only open if active is vehicles or on vehicle page) -->
                <?php 
                $isVehiclesActive = ($active === 'vehicles' || str_contains($_SERVER['REQUEST_URI'] ?? '', '/modules/vehicles'));
                ?>
                <details class="ng" <?= $isVehiclesActive ? 'open' : '' ?>>
                    <summary class="nav-h">
                        <span><i class="bi bi-truck-front-fill me-2"></i>Vehicles</span>
                        <i class="bi bi-chevron-down arrow"></i>
                    </summary>
                    <a class="nav-link sub2 <?= ($active === 'vehicles' && empty($_GET['type']) && !str_contains($_SERVER['REQUEST_URI'] ?? '', 'form.php')) ? 'active' : '' ?>"
                        href="<?= url('modules/vehicles/index.php') ?>"><i
                            class="bi bi-collection-fill"></i>All Vehicles</a>
                    <?php 
                    $typeIcons = [
                        'van' => 'bus-front-fill',
                        'truck' => 'truck-flatbed',
                        'car' => 'car-front-fill',
                        'bike' => 'bicycle',
                        'other' => 'circle-square'
                    ];
                    foreach (TYPES as $k => $v): 
                        $isTypeActive = ($active === 'vehicles' && ($_GET['type'] ?? '') === $k);
                        $ico = $typeIcons[$k] ?? 'truck';
                    ?>
                        <a class="nav-link sub2 <?= $isTypeActive ? 'active' : '' ?>"
                            href="<?= url('modules/vehicles/index.php?type=' . $k) ?>"><i
                                class="bi bi-<?= $ico ?>"></i><?= e($v) ?></a>
                    <?php endforeach; ?>
                    <?php if (can('vehicles.edit')): ?>
                        <a class="nav-link sub2 <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'form.php') ? 'active' : '' ?>"
                            href="<?= url('modules/vehicles/form.php') ?>"><i
                                class="bi bi-plus-circle-fill"></i>Add Vehicle</a>
                    <?php endif; ?>
                </details>

                <!-- Nav Groups (Operations, Maintenance, Tyres, Finance, Documents, Reports, Admin) -->
                <?php 
                $groupIcons = [
                    'People' => 'person-badge-fill',
                    'Operations' => 'compass-fill',
                    'Fuel & Mileage' => 'fuel-pump-fill',
                    'Maintenance' => 'wrench-adjustable-circle-fill',
                    'Documents' => 'folder-fill',
                    'Finance' => 'cash-stack',
                    'Tyre Management' => 'disc-fill',
                    'Administration' => 'shield-lock-fill',
                    'Reports' => 'bar-chart-fill'
                ];
                foreach (nav_groups() as $g => $items):
                    $open = in_array($active, array_column($items, 'k'), true); 
                    $gIcon = $groupIcons[$g] ?? 'folder2-open';
                ?>
                    <details class="ng" <?= $open ? 'open' : '' ?>>
                        <summary class="nav-h">
                            <span><i class="bi bi-<?= $gIcon ?> me-2"></i><?= e($g) ?></span>
                            <i class="bi bi-chevron-down arrow"></i>
                        </summary>
                        <?php foreach ($items as $it): ?>
                            <a class="nav-link sub2 <?= $active === $it['k'] ? 'active' : '' ?>"
                                href="<?= url($it['h']) ?>"><i
                                    class="bi bi-<?= e($it['i']) ?>"></i><?= e($it['n']) ?></a>
                        <?php endforeach; ?>
                    </details>
                <?php endforeach; ?>
            </nav>
            <div class="user"><i class="bi bi-person-circle fs-4"></i>
                <div><b><?= e($_SESSION['name'] ?? '') ?></b><small><?= e($_SESSION['role_name'] ?? '') ?></small></div><a
                    href="<?= url('logout.php?bye=1') ?>" title="Sign out"><i class="bi bi-box-arrow-right"></i></a>
            </div>
        </aside>
        <script>
            (function() {
                try {
                    var sb = document.getElementById('sidebar');
                    if (!sb) return;
                    var pos = sessionStorage.getItem('sidebar_scroll');
                    if (pos !== null) {
                        sb.scrollTop = parseInt(pos, 10);
                    }
                    var act = sb.querySelector('.nav-link.active');
                    if (act) {
                        var sRect = sb.getBoundingClientRect();
                        var aRect = act.getBoundingClientRect();
                        if (aRect.top < sRect.top || aRect.bottom > sRect.bottom) {
                            act.scrollIntoView({ block: 'nearest' });
                        }
                    }
                } catch (e) {}
            })();
        </script>
        <div class="backdrop" id="backdrop"></div>
        <div class="main">
            <header class="topbar"><button class="btn btn-light" id="menuBtn" aria-label="Menu"><i
                        class="bi bi-list"></i></button>
                <nav aria-label="breadcrumb" class="d-none d-sm-block">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?= url('dashboard.php') ?>">Home</a></li>
                        <?php foreach ($crumbs as $c): ?>
                            <li class="breadcrumb-item active"><?= e($c) ?></li><?php endforeach; ?>
                    </ol>
                </nav>
                <a class="btn btn-light position-relative ms-auto" href="<?= url("modules/reports.php?r=doc_expiry") ?>"
                    title="Expiry alerts"><i class="bi bi-bell"></i><?php if ($n = bell()): ?><span
                            class="badge text-bg-danger position-absolute top-0 start-100 translate-middle rounded-pill"><?= $n ?></span><?php endif; ?></a>
                <form class="search" action="<?= url('modules/vehicles/index.php') ?>"><i class="bi bi-search"></i><input
                        name="q" class="form-control" placeholder="Search vehicles…"></form>
            </header>
            <main class="content"><?php if ($f): ?>
                    <div class="alert alert-<?= e($f[0]) ?> alert-dismissible fade show"><?= e($f[1]) ?><button class="btn-close"
                            data-bs-dismiss="alert"></button></div><?php endif;
}
function layout_bottom(string $extra = ''): void
{ ?>
            </main>
            <footer class="foot">© <?= date('Y') ?> MSS Transport / Mettur Super Service</footer>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="<?= url('assets/js/app.js') ?>"></script><?= $extra ?>
    </body>

    </html>
<?php }
