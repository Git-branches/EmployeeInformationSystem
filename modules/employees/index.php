<?php
// modules/employees/index.php
// Employee list with search and New/Existing + department + status filters.

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

$search     = trim((string)($_GET['q'] ?? ''));
$dept_id    = (int)($_GET['department'] ?? 0);
$app_type   = (string)($_GET['type'] ?? '');
$emp_status = (string)($_GET['status'] ?? '');

$where  = [];
$params = [];
if ($search !== '') {
    $where[] = '(CONCAT(e.first_name, " ", e.last_name) LIKE ? OR e.last_name LIKE ? OR e.employee_no LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($dept_id > 0) {
    $where[] = 'e.department_id = ?';
    $params[] = $dept_id;
}
if (in_array($app_type, ['New', 'Existing'], true)) {
    $where[] = 'e.applicant_type = ?';
    $params[] = $app_type;
}
if (in_array($emp_status, ['Applicant', 'Active', 'Inactive', 'Terminated'], true)) {
    $where[] = 'e.employment_status = ?';
    $params[] = $emp_status;
}

$sql = 'SELECT e.*, d.department_name,
               SUM(er.status IN ("Missing","Incomplete")) AS pending_reqs
        FROM employees e
        LEFT JOIN departments d ON d.department_id = e.department_id
        LEFT JOIN employee_requirements er ON er.employee_id = e.employee_id'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' GROUP BY e.employee_id ORDER BY e.last_name, e.first_name';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();

$departments = $pdo->query('SELECT department_id, department_name FROM departments ORDER BY department_name')->fetchAll();

$status_badge = [
    'Applicant'  => 'text-bg-warning',
    'Active'     => 'text-bg-success',
    'Inactive'   => 'text-bg-secondary',
    'Terminated' => 'text-bg-dark',
];

$page_title = 'Employees';
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-people me-2"></i>Employees</h1>
    <a href="add.php" class="btn btn-danger"><i class="bi bi-person-plus me-1"></i>Add Employee</a>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1">Search</label>
                <input type="text" class="form-control" name="q" value="<?= e($search) ?>"
                       placeholder="Name or employee no.">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Department</label>
                <select class="form-select" name="department">
                    <option value="">All departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int)$d['department_id'] ?>" <?= $dept_id === (int)$d['department_id'] ? 'selected' : '' ?>>
                            <?= e($d['department_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Applicant Type</label>
                <select class="form-select" name="type">
                    <option value="">All</option>
                    <option <?= $app_type === 'New' ? 'selected' : '' ?>>New</option>
                    <option <?= $app_type === 'Existing' ? 'selected' : '' ?>>Existing</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Status</label>
                <select class="form-select" name="status">
                    <option value="">All</option>
                    <?php foreach (array_keys($status_badge) as $s): ?>
                        <option <?= $emp_status === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1 d-grid">
                <button class="btn btn-outline-danger"><i class="bi bi-search"></i></button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Employee</th>
                    <th>Employee No.</th>
                    <th>Department</th>
                    <th>Position</th>
                    <th class="text-center">Type</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Requirements</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$employees): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No employees found.</td></tr>
            <?php endif; ?>
            <?php foreach ($employees as $emp): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($emp['photo_path']): ?>
                                <img src="<?= BASE_URL . '/' . e($emp['photo_path']) ?>" class="rounded-circle" width="36" height="36" style="object-fit:cover" alt="">
                            <?php else: ?>
                                <i class="bi bi-person-circle fs-3 text-secondary"></i>
                            <?php endif; ?>
                            <span class="fw-semibold"><?= e(employee_display_name($emp)) ?></span>
                        </div>
                    </td>
                    <td><?= e($emp['employee_no'] ?? '—') ?></td>
                    <td><?= e($emp['department_name'] ?? 'Unassigned') ?></td>
                    <td><?= e($emp['position'] ?? '—') ?></td>
                    <td class="text-center">
                        <span class="badge <?= $emp['applicant_type'] === 'New' ? 'text-bg-info' : 'text-bg-primary' ?>">
                            <?= e($emp['applicant_type']) ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="badge <?= $status_badge[$emp['employment_status']] ?>"><?= e($emp['employment_status']) ?></span>
                    </td>
                    <td class="text-center">
                        <?php if ((int)$emp['pending_reqs'] > 0): ?>
                            <span class="badge text-bg-danger"><?= (int)$emp['pending_reqs'] ?> pending</span>
                        <?php else: ?>
                            <span class="badge text-bg-success">Complete</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="view.php?id=<?= (int)$emp['employee_id'] ?>" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                        <a href="edit.php?id=<?= (int)$emp['employee_id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                        <form method="post" action="delete.php" class="d-inline"
                              data-confirm="Delete <?= e(employee_display_name($emp)) ?>? This also removes their requirements records.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="employee_id" value="<?= (int)$emp['employee_id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white text-muted small"><?= count($employees) ?> record(s)</div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
