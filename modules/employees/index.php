<?php
// modules/employees/index.php
// Employee list with search and New/Existing + department + status filters.
// The query lives in list_query.php and the rows in list_rows.php, which
// search.php reuses to refresh the list as the administrator types.

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require __DIR__ . '/list_query.php';

$departments = $pdo->query('SELECT department_id, department_name FROM departments ORDER BY department_name')->fetchAll();

$page_title = 'Employees';
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-people me-2"></i>Employees</h1>
    <a href="add.php" class="btn btn-danger"><i class="bi bi-person-plus me-1"></i>Add Employee</a>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end" id="employee-filters"
              data-search-url="<?= BASE_URL ?>/modules/employees/search.php">
            <div class="col-md-4">
                <label class="form-label small mb-1" for="employee-search">Search</label>
                <div class="position-relative">
                    <input type="search" class="form-control" id="employee-search" name="q" value="<?= e($search) ?>"
                           placeholder="Name, employee no. or position" autocomplete="off">
                    <span class="spinner-border spinner-border-sm text-secondary position-absolute end-0 top-50 translate-middle-y me-2 d-none"
                          id="employee-search-busy" role="status" aria-hidden="true"></span>
                </div>
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
                <!-- Results refresh as you type; the button stays for keyboard
                     submits and for browsers with JavaScript turned off. -->
                <button class="btn btn-outline-danger" title="Search"><i class="bi bi-search"></i></button>
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
            <tbody id="employee-rows">
            <?php require __DIR__ . '/list_rows.php'; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white text-muted small">
        <span id="employee-count"><?= count($employees) ?></span> record(s)
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
