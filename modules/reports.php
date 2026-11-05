<?php $R = dirname(__DIR__);
require_once "$R/includes/bootstrap.php";
require_once "$R/includes/layout.php";
require_once "$R/includes/modules.php";
require_once "$R/includes/rules.php";
require_once "$R/includes/reports.php";
require_login('reports.view');
$k = $_GET['r'] ?? '';
$defs = report_defs();
$d = $defs[$k] ?? null;
if (!$d) {
    http_response_code(404);
    exit('Unknown report');
}
$pdo = db();
$q = trim($_GET['q'] ?? '');
$in = ['from' => $_GET['from'] ?? date('Y-m-01', strtotime('-5 months')), 'to' => $_GET['to'] ?? date('Y-m-d'), 'vehicle' => 0];
foreach (['from', 'to'] as $x)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $in[$x])) {
        http_response_code(400);
        exit('Bad date');
    }
$rows = run_report($pdo, $k, $in);

// Search filtering across all columns (vehicle reg_no, driver, document, workshop, etc.)
if ($q !== '') {
    $qClean = strtolower(str_replace(['-', ' ', '_'], '', $q));
    $rows = array_values(array_filter($rows, function ($r) use ($q, $qClean) {
        foreach ($r as $val) {
            if ($val === null) continue;
            $s = (string) $val;
            if (stripos($s, $q) !== false) return true;
            if ($qClean !== '') {
                $valClean = strtolower(str_replace(['-', ' ', '_'], '', $s));
                if (strpos($valClean, $qClean) !== false) return true;
            }
        }
        return false;
    }));
}

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$k.csv\"");
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    if ($rows) {
        fputcsv($o, array_keys($rows[0]));
        foreach ($rows as $r)
            fputcsv($o, array_map('csv_safe', $r));
    }
    audit('export', 'report:' . $k);
    exit;
}

// Pagination setup: 20 records per slide / page
$perPage = 20;
$totalRows = count($rows);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = max(1, min($totalPages, (int) ($_GET['page'] ?? $_GET['p'] ?? 1)));
$offset = ($page - 1) * $perPage;
$pagedRows = array_slice($rows, $offset, $perPage);
$fromRow = $totalRows > 0 ? $offset + 1 : 0;
$toRow = min($offset + $perPage, $totalRows);

$pageUrl = function ($p) {
    $params = $_GET;
    $params['page'] = $p;
    unset($params['p']);
    return '?' . http_build_query($params);
};

$veh = $pdo->query('SELECT id,reg_no FROM vehicles WHERE deleted_at IS NULL ORDER BY reg_no')->fetchAll();
layout_top($d['t'], "r_$k", ['Reports', $d['t']]); ?>
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
    <h4 class="mb-0"><?= e($d['t']) ?> <small class="text-secondary fs-6" id="reportTotalCount">(<?= $totalRows ?> total)</small></h4>
    <div class="d-flex gap-2 d-print-none">
        <a class="btn btn-outline-secondary" id="csvExportBtn"
            href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>"><i class="bi bi-filetype-csv"></i>
            CSV / Excel</a>
        <button class="btn btn-outline-secondary" onclick="print()"><i class="bi bi-printer"></i>
            Print / PDF</button>
    </div>
</div>

<form class="row g-2 mb-3 d-print-none align-items-end" id="reportFilterForm" method="GET" action="">
    <input type="hidden" name="r" value="<?= e($k) ?>">
    <?php if (in_array('date', $d['f'])): ?>
        <div class="col-6 col-sm-4 col-md-2">
            <label class="form-label small text-secondary mb-1">From Date</label>
            <input type="date" name="from" id="reportFromDate" class="form-control" value="<?= e($in['from']) ?>">
        </div>
        <div class="col-6 col-sm-4 col-md-2">
            <label class="form-label small text-secondary mb-1">To Date</label>
            <input type="date" name="to" id="reportToDate" class="form-control" value="<?= e($in['to']) ?>">
        </div>
    <?php endif; ?>
    <div class="col-12 <?= in_array('date', $d['f']) ? 'col-md-6 col-lg-5' : 'col-md-7 col-lg-6' ?>">
        <?php if (in_array('date', $d['f'])): ?>
            <label class="form-label small text-secondary mb-1">Search</label>
        <?php endif; ?>
        <div class="input-group">
            <span class="input-group-text bg-white" id="searchIconSpan"><i class="bi bi-search text-secondary" id="searchIcon"></i></span>
            <input type="search" name="q" id="reportSearch" class="form-control"
                placeholder="Search vehicle number or keyword…"
                value="<?= e($q) ?>" autocomplete="off" list="vehiclesDatalist">
            <button type="button" class="btn btn-outline-secondary <?= $q === '' ? 'd-none' : '' ?>" id="clearSearchBtn" title="Clear search">
                <i class="bi bi-x-lg"></i>
            </button>
            <button type="submit" class="btn btn-primary px-3" id="searchBtn">
                <i class="bi bi-search me-1"></i>Search
            </button>
        </div>
        <datalist id="vehiclesDatalist">
            <?php foreach ($veh as $v): ?>
                <option value="<?= e($v['reg_no']) ?>"><?= e($v['reg_no']) ?></option>
            <?php endforeach; ?>
        </datalist>
    </div>
</form>

<?php if ($k === 'fuel_efficiency'): ?>
    <p class="text-secondary small">Method: full-tank only. km/l = (odometer at this full fill − odometer at the previous
        full fill) ÷ litres added after the previous full fill up to and including this one. Records without a prior full
        fill, or with non-increasing odometer, are excluded.</p>
<?php endif; ?>

<div class="cardx p-0" id="reportTableArea">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0"><?php if ($rows): ?>
                <thead>
                    <tr><?php foreach (array_keys($rows[0]) as $h): ?>
                            <th><?= e(ucwords(str_replace('_', ' ', $h))) ?></th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody><?php foreach ($pagedRows as $r): ?>
                        <tr><?php foreach ($r as $c => $v): ?>
                                <td><?php if ($c === 'status' && $k === 'doc_expiry'): ?><span
                                            class="badge text-bg-<?= $v === 'Valid' ? 'success' : ($v === 'Expired' || $v === 'Within 7 days' ? 'danger' : ($v === 'Within 15 days' ? 'warning' : 'info')) ?>"><?= e($v) ?></span><?php else: ?><?= e($v ?? '—') ?><?php endif; ?>
                                </td><?php endforeach; ?>
                        </tr><?php endforeach; ?>
                </tbody>
            <?php else: ?>
                <tbody>
                    <tr>
                        <td class="text-center text-secondary py-5">
                            <i class="bi bi-inbox fs-2 d-block"></i>
                            <?= $q !== '' ? 'No records matching "' . e($q) . '".' : 'No data for the selected filters.' ?>
                        </td>
                    </tr>
                </tbody><?php endif; ?>
        </table>
    </div>
    <?php if ($totalRows > 0): ?>
        <div class="d-flex flex-wrap align-items-center justify-content-between p-3 border-top gap-2 d-print-none">
            <div class="text-secondary small">
                Showing <strong><?= $fromRow ?></strong> to <strong><?= $toRow ?></strong> of <strong><?= $totalRows ?></strong> entries (20 per page)
            </div>
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Report pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= e($pageUrl(1)) ?>" title="First page"><i class="bi bi-chevron-double-left"></i></a>
                        </li>
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= e($pageUrl(max(1, $page - 1))) ?>" title="Previous page">« Prev</a>
                        </li>
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        if ($end - $start < 4) {
                            if ($start == 1) $end = min($totalPages, $start + 4);
                            else $start = max(1, $end - 4);
                        }
                        if ($start > 1): ?>
                            <li class="page-item"><a class="page-link" href="<?= e($pageUrl(1)) ?>">1</a></li>
                            <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                        <?php endif; ?>
                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= e($pageUrl($i)) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($end < $totalPages): ?>
                            <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                            <li class="page-item"><a class="page-link" href="<?= e($pageUrl($totalPages)) ?>"><?= $totalPages ?></a></li>
                        <?php endif; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= e($pageUrl(min($totalPages, $page + 1))) ?>" title="Next page">Next »</a>
                        </li>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= e($pageUrl($totalPages)) ?>" title="Last page"><i class="bi bi-chevron-double-right"></i></a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
(function() {
    const searchInput = document.getElementById('reportSearch');
    const form = document.getElementById('reportFilterForm');
    const tableArea = document.getElementById('reportTableArea');
    const totalCount = document.getElementById('reportTotalCount');
    const csvBtn = document.getElementById('csvExportBtn');
    const clearBtn = document.getElementById('clearSearchBtn');
    const searchIcon = document.getElementById('searchIcon');
    let searchDebounce = null;
    let currentController = null;

    if (!searchInput || !form) return;

    function doLiveSearch(page = 1) {
        if (currentController) {
            currentController.abort();
        }
        currentController = new AbortController();

        const formData = new FormData(form);
        const params = new URLSearchParams(formData);
        params.set('page', page);

        const newUrl = '?' + params.toString();
        window.history.replaceState(null, '', newUrl);

        if (csvBtn) {
            const csvParams = new URLSearchParams(formData);
            csvParams.set('export', 'csv');
            csvBtn.href = '?' + csvParams.toString();
        }

        if (clearBtn) {
            if (searchInput.value.trim() !== '') {
                clearBtn.classList.remove('d-none');
            } else {
                clearBtn.classList.add('d-none');
            }
        }

        if (searchIcon) {
            searchIcon.className = 'spinner-border spinner-border-sm text-primary';
        }

        fetch(newUrl, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: currentController.signal
        })
        .then(res => res.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            const newTableArea = doc.getElementById('reportTableArea');
            if (newTableArea && tableArea) {
                tableArea.innerHTML = newTableArea.innerHTML;
            }

            const newCount = doc.getElementById('reportTotalCount');
            if (newCount && totalCount) {
                totalCount.innerHTML = newCount.innerHTML;
            }

            bindPaginationLinks();
        })
        .catch(err => {
            if (err.name !== 'AbortError') console.error(err);
        })
        .finally(() => {
            if (searchIcon) {
                searchIcon.className = 'bi bi-search text-secondary';
            }
        });
    }

    // 1. Live automatic search while typing (250ms debounce)
    searchInput.addEventListener('input', function() {
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(() => {
            doLiveSearch(1);
        }, 250);
    });

    // 2. Search button click or Enter key submit
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        clearTimeout(searchDebounce);
        doLiveSearch(1);
    });

    // 3. Clear button click
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            clearBtn.classList.add('d-none');
            searchInput.focus();
            doLiveSearch(1);
        });
    }

    // 4. Date changes
    const fromInput = document.getElementById('reportFromDate');
    const toInput = document.getElementById('reportToDate');
    if (fromInput) fromInput.addEventListener('change', () => doLiveSearch(1));
    if (toInput) toInput.addEventListener('change', () => doLiveSearch(1));

    // 5. Intercept pagination link clicks to keep it smooth AJAX
    function bindPaginationLinks() {
        if (!tableArea) return;
        tableArea.querySelectorAll('.pagination a.page-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const href = this.getAttribute('href');
                if (!href) return;
                const urlParams = new URLSearchParams(href.replace(/^[^\?]*\?/, ''));
                const pageNum = urlParams.get('page') || 1;
                doLiveSearch(pageNum);
            });
        });
    }

    bindPaginationLinks();
})();
</script>
<style>
    @media print {

        .sidebar,
        .topbar,
        .foot,
        .d-print-none {
            display: none !important
        }

        .main {
            margin: 0
        }
    }
</style>
<?php layout_bottom();
