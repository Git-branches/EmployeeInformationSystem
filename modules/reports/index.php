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
<?php // This report is wide, so it is the one page that prints landscape. ?>
<style>@media print { @page { size: A4 landscape; margin: 10mm; } }</style>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-file-earmark-text me-2"></i>Reports</h1>
    <div>
        <?php // Nothing to print or export until something is ticked ?>
        <button onclick="window.print()" class="btn btn-outline-secondary" <?= $chosen ? '' : 'disabled' ?>>
            <i class="bi bi-printer me-1"></i>Print
        </button>
        <a href="export.php?format=pdf&<?= e($qs) ?>"
           class="btn btn-outline-danger<?= $chosen ? '' : ' disabled' ?>" <?= $chosen ? '' : 'aria-disabled="true" tabindex="-1"' ?>>
            <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
        </a>
        <a href="export.php?format=xlsx&<?= e($qs) ?>"
           class="btn btn-outline-success<?= $chosen ? '' : ' disabled' ?>" <?= $chosen ? '' : 'aria-disabled="true" tabindex="-1"' ?>>
            <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
        </a>
    </div>
</div>

<div class="card shadow-sm mb-4 d-print-none">
    <div class="card-body">
        <form method="get" id="report-filters">
            <div class="row g-4">
                <!-- 1. What the report covers -->
                <div class="col-xl-4 col-lg-6">
                    <h2 class="h6 text-danger border-bottom pb-2 mb-3">1 · Report type</h2>
                    <div class="form-check mb-2">
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
                            aria-label="Department" <?= $f['scope'] === 'department' ? '' : 'disabled' ?>>
                        <option value="">Select a department…</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= (int)$d['department_id'] ?>" <?= $f['department'] === (int)$d['department_id'] ? 'selected' : '' ?>>
                                <?= e($d['department_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 2. Which employees -->
                <div class="col-xl-3 col-lg-6">
                    <h2 class="h6 text-danger border-bottom pb-2 mb-3">2 · Filters</h2>
                    <label class="form-label small mb-1" for="report-type">Applicant Type</label>
                    <select class="form-select mb-3" name="type" id="report-type">
                        <option value="">All</option>
                        <option <?= $f['type'] === 'New' ? 'selected' : '' ?>>New</option>
                        <option <?= $f['type'] === 'Existing' ? 'selected' : '' ?>>Existing</option>
                    </select>

                    <label class="form-label small mb-1" for="report-status">Record Status</label>
                    <select class="form-select" name="status" id="report-status">
                        <option value="">All</option>
                        <?php foreach (['Applicant', 'Active', 'Inactive', 'Terminated'] as $s): ?>
                            <option <?= $f['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 3. Which columns -->
                <div class="col-xl-5">
                    <h2 class="h6 text-danger border-bottom pb-2 mb-3 d-flex justify-content-between align-items-center">
                        <span>3 · Include in report</span>
                        <span class="fw-normal small text-muted" id="columns-chosen-count"><?= count($chosen) ?> of <?= count($columns) ?></span>
                    </h2>
                    <!-- autocomplete="off" on every box: without it the browser
                         restores the ticks from the previous visit, which would
                         contradict what the server rendered. -->
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="columns-all" autocomplete="off"
                               <?= $chosen && count($chosen) === count($columns) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="columns-all">Select all</label>
                    </div>
                    <!-- Two or three columns of boxes, so all 22 fit without a
                         scrollbar inside the card on a normal screen. -->
                    <div class="report-columns border rounded p-2">
                        <?php foreach ($columns as $key => [$label, $_]): ?>
                            <div class="form-check">
                                <input class="form-check-input report-column" type="checkbox" name="columns[]"
                                       value="<?= e($key) ?>" id="col-<?= e($key) ?>" autocomplete="off"
                                       <?= in_array($key, $chosen, true) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="col-<?= e($key) ?>"><?= e($label) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-text<?= $chosen ? '' : ' text-danger' ?>">
                        <?= $chosen
                            ? 'Only ticked items appear in the report and its exports.'
                            : 'Tick the information to include, or use Select all.' ?>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4 pt-3 border-top">
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

<div class="card shadow-sm report-card">
    <div class="card-header bg-white">
        <div class="fw-semibold"><?= e($title) ?></div>
        <div class="small text-muted">
            <?php if ($chosen): ?>
                Generated <?= e(date('F j, Y g:i A')) ?> — <?= count($rows) ?> record(s)
                <?= $f['scope'] === 'overall' ? 'in ' . count($groups) . ' department(s)' : '' ?>
            <?php else: ?>
                Nothing selected yet
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$chosen): ?>
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-list-check fs-1 d-block mb-2 opacity-50"></i>
            Tick the information to include under <strong>3 · Include in report</strong>,
            then press Generate.
        </div>
    <?php elseif (!$rows): ?>
        <div class="card-body text-center text-muted py-5">No records match the selected filters.</div>
    <?php else: ?>
        <?php $grouped = $f['scope'] === 'overall' && count($groups) > 1; ?>

        <?php if ($grouped): ?>
            <!-- Summary first: how many employees each department holds -->
            <div class="card-body pb-2">
                <h2 class="h6 text-danger mb-2">Summary</h2>
                <div class="report-summary">
                    <?php foreach ($groups as $name => $members): ?>
                        <div class="report-summary-item">
                            <span class="text-truncate"><?= e($name) ?></span>
                            <span class="badge text-bg-secondary ms-2"><?= count($members) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="report-summary-item fw-semibold">
                        <span>Total</span>
                        <span class="badge text-bg-primary ms-2"><?= count($rows) ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- One table for the whole report: the headings stay put and every
             section lines up under the same columns. It is deliberately not
             wrapped in .table-responsive — that would scroll the headings
             out of view (see .report-table in style.css). -->
        <div class="report-table">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <?php foreach ($chosen as $key): ?>
                            <th scope="col"><?= e($columns[$key][0]) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <?php foreach ($groups as $name => $members): ?>
                    <tbody>
                    <?php if ($grouped): ?>
                        <tr class="report-group">
                            <th scope="rowgroup" colspan="<?= count($chosen) ?>">
                                <?= e($name) ?>
                                <span class="fw-normal text-muted">· <?= count($members) ?> employee<?= count($members) === 1 ? '' : 's' ?></span>
                            </th>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($members as $r): ?>
                        <tr>
                            <?php foreach ($chosen as $key): ?>
                                <td class="<?= in_array($key, REPORT_WRAPPING_COLUMNS, true) ? 'report-wrap' : '' ?>"><?= e($columns[$key][1]($r)) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
