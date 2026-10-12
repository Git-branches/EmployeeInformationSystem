<?php
// modules/reports/index.php
// Report generation: Overall (every employee, grouped by department) or a
// single department, with the included columns ticked by the administrator.
// Printable, and exportable to PDF/Excel through export.php.

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/report_data.php';

$f       = report_filters();
$rows    = report_rows($pdo, $f);
$title   = report_title($pdo, $f);
$qs      = report_query($f);
$columns = report_columns();
$chosen  = $f['columns'];
$groups  = report_groups($rows);

$departments = $pdo->query('SELECT department_id, department_name FROM departments ORDER BY department_name')->fetchAll();

$page_title = 'Reports';
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-file-earmark-text me-2"></i>Reports</h1>
    <div>
        <button onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</button>
        <a href="export.php?format=pdf&<?= e($qs) ?>" class="btn btn-outline-danger"><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF</a>
        <a href="export.php?format=xlsx&<?= e($qs) ?>" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
    </div>
</div>

<div class="card shadow-sm mb-4 d-print-none">
    <div class="card-body">
        <form method="get" id="report-filters">
            <div class="row g-3">
                <div class="col-lg-5">
                    <label class="form-label small mb-1 fw-semibold">Report</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="scope" value="overall" id="scope-overall"
                               <?= $f['scope'] === 'overall' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="scope-overall">
                            Overall report
                            <span class="text-muted small d-block">All employees, grouped by department</span>
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="scope" value="department" id="scope-department"
                               <?= $f['scope'] === 'department' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="scope-department">
                            By department
                            <span class="text-muted small d-block">One department only</span>
                        </label>
                    </div>
                    <select class="form-select mt-2" name="department" id="report-department"
                            <?= $f['scope'] === 'department' ? '' : 'disabled' ?>>
                        <option value="">Select a department…</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= (int)$d['department_id'] ?>" <?= $f['department'] === (int)$d['department_id'] ? 'selected' : '' ?>>
                                <?= e($d['department_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-3">
                    <label class="form-label small mb-1" for="report-type">Applicant Type</label>
                    <select class="form-select" name="type" id="report-type">
                        <option value="">All</option>
                        <option <?= $f['type'] === 'New' ? 'selected' : '' ?>>New</option>
                        <option <?= $f['type'] === 'Existing' ? 'selected' : '' ?>>Existing</option>
                    </select>

                    <label class="form-label small mb-1 mt-3" for="report-status">Record Status</label>
                    <select class="form-select" name="status" id="report-status">
                        <option value="">All</option>
                        <?php foreach (['Applicant', 'Active', 'Inactive', 'Terminated'] as $s): ?>
                            <option <?= $f['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small mb-0 fw-semibold">Include in report</label>
                        <div class="form-check form-check-inline me-0">
                            <input class="form-check-input" type="checkbox" id="columns-all"
                                   <?= count($chosen) === count($columns) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="columns-all">Select all</label>
                        </div>
                    </div>
                    <div class="border rounded p-2 overflow-auto" style="max-height:260px">
                        <?php foreach ($columns as $key => [$label, $_]): ?>
                            <div class="form-check">
                                <input class="form-check-input report-column" type="checkbox" name="columns[]"
                                       value="<?= e($key) ?>" id="col-<?= e($key) ?>"
                                       <?= in_array($key, $chosen, true) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="col-<?= e($key) ?>"><?= e($label) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-text">
                        Only ticked items appear in the report and its exports.
                        <?php if (isset($_GET['columns']) && $chosen === ['name']): ?>
                            <span class="text-danger d-block">Nothing was ticked, so the name alone is shown.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <button class="btn btn-danger"><i class="bi bi-funnel me-1"></i>Generate</button>
                <a href="index.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<?php if ($f['scope'] === 'department' && $f['department'] === 0): ?>
    <div class="alert alert-info d-print-none">
        <i class="bi bi-info-circle me-1"></i>Choose a department above, then press Generate.
        Showing all employees in the meantime.
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header bg-white">
        <div class="fw-semibold"><?= e($title) ?></div>
        <div class="small text-muted">
            Generated <?= e(date('F j, Y g:i A')) ?> — <?= count($rows) ?> record(s)
            <?= $f['scope'] === 'overall' ? 'in ' . count($groups) . ' department(s)' : '' ?>
        </div>
    </div>

    <?php if (!$rows): ?>
        <div class="card-body text-center text-muted py-5">No records match the selected filters.</div>
    <?php else: ?>
        <?php if ($f['scope'] === 'overall' && count($groups) > 1): ?>
            <!-- Summary first: how many employees each department holds -->
            <div class="card-body pb-0">
                <h2 class="h6 text-danger border-bottom pb-2">Summary</h2>
                <div class="table-responsive">
                    <table class="table table-sm w-auto mb-0">
                        <tbody>
                        <?php foreach ($groups as $name => $members): ?>
                            <tr>
                                <td class="pe-4"><?= e($name) ?></td>
                                <td class="text-end fw-semibold"><?= count($members) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="border-top">
                            <td class="pe-4 fw-semibold">Total</td>
                            <td class="text-end fw-semibold"><?= count($rows) ?></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php foreach ($groups as $name => $members): ?>
            <?php if ($f['scope'] === 'overall'): ?>
                <div class="card-body pb-1 pt-4">
                    <h2 class="h6 text-danger border-bottom pb-2 mb-0">
                        <?= e($name) ?> <span class="text-muted fw-normal">(<?= count($members) ?>)</span>
                    </h2>
                </div>
            <?php endif; ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <?php foreach ($chosen as $key): ?>
                                <th><?= e($columns[$key][0]) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($members as $r): ?>
                        <tr>
                            <?php foreach ($chosen as $key): ?>
                                <td><?= e($columns[$key][1]($r)) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
