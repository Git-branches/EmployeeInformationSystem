<?php
// modules/reports/index.php
// Report generation: filtered employee list, printable / exportable.

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/report_data.php';

$f     = report_filters();
$rows  = report_rows($pdo, $f);
$title = report_title($pdo, $f);
$qs    = http_build_query(array_filter(['department' => $f['department'], 'type' => $f['type'], 'status' => $f['status']]));

$departments = $pdo->query('SELECT department_id, department_name FROM departments ORDER BY department_name')->fetchAll();

$page_title = 'Reports';
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-file-earmark-text me-2"></i>Reports</h1>
    <div>
        <button onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</button>
        <a href="export.php?format=pdf&<?= $qs ?>" class="btn btn-outline-danger"><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF</a>
        <a href="export.php?format=xlsx&<?= $qs ?>" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1">Department</label>
                <select class="form-select" name="department">
                    <option value="">All departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int)$d['department_id'] ?>" <?= $f['department'] === (int)$d['department_id'] ? 'selected' : '' ?>>
                            <?= e($d['department_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Applicant Type</label>
                <select class="form-select" name="type">
                    <option value="">All</option>
                    <option <?= $f['type'] === 'New' ? 'selected' : '' ?>>New</option>
                    <option <?= $f['type'] === 'Existing' ? 'selected' : '' ?>>Existing</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Employment Status</label>
                <select class="form-select" name="status">
                    <option value="">All</option>
                    <?php foreach (['Applicant', 'Active', 'Inactive', 'Terminated'] as $s): ?>
                        <option <?= $f['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-danger"><i class="bi bi-funnel me-1"></i>Generate</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white">
        <div class="fw-semibold"><?= e($title) ?></div>
        <div class="small text-muted">Generated <?= e(date('F j, Y g:i A')) ?> — <?= count($rows) ?> record(s)</div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Employee No</th><th>Name</th><th>Birthdate</th><th>Sex</th><th>Contact</th>
                    <th>Department</th><th>Position</th><th>Type</th><th>Status</th><th>Date Hired</th><th>Pending Reqs</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="11" class="text-center text-muted py-4">No records match the selected filters.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['employee_no'] ?? '—') ?></td>
                    <td><?= e($r['last_name'] . ', ' . $r['first_name'] . ($r['middle_name'] ? ' ' . $r['middle_name'] : '')) ?></td>
                    <td><?= e($r['birthdate']) ?></td>
                    <td><?= e($r['sex']) ?></td>
                    <td><?= e($r['contact_no'] ?? '—') ?></td>
                    <td><?= e($r['department_name'] ?? 'Unassigned') ?></td>
                    <td><?= e($r['position'] ?? '—') ?></td>
                    <td><?= e($r['applicant_type']) ?></td>
                    <td><?= e($r['employment_status']) ?></td>
                    <td><?= e($r['date_hired'] ?? '—') ?></td>
                    <td><?= (int)$r['pending_reqs'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
