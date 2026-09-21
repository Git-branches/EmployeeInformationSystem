<?php
// modules/dashboard/index.php
// Centralized status dashboard: counts, department distribution,
// status breakdown, and employees with pending requirements.

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

generate_notifications($pdo);

$counts = [
    'employees'   => (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE employment_status <> 'Applicant'")->fetchColumn(),
    'applicants'  => (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE employment_status = 'Applicant'")->fetchColumn(),
    'departments' => (int)$pdo->query('SELECT COUNT(*) FROM departments')->fetchColumn(),
    'missing'     => (int)$pdo->query("SELECT COUNT(*) FROM employee_requirements er
                                       JOIN requirement_types rt ON rt.requirement_type_id = er.requirement_type_id
                                       WHERE er.status IN ('Missing','Incomplete') AND rt.is_active = 1")->fetchColumn(),
];

$cards = [
    ['label' => 'Employees',            'value' => $counts['employees'],   'icon' => 'bi-people-fill',              'class' => 'text-bg-danger',    'href' => '/modules/employees/index.php?status=Active'],
    ['label' => 'Applicants',           'value' => $counts['applicants'],  'icon' => 'bi-person-plus-fill',         'class' => 'text-bg-warning',   'href' => '/modules/employees/index.php?status=Applicant'],
    ['label' => 'Departments',          'value' => $counts['departments'], 'icon' => 'bi-diagram-3-fill',           'class' => 'text-bg-secondary', 'href' => '/modules/departments/index.php'],
    ['label' => 'Pending Requirements', 'value' => $counts['missing'],     'icon' => 'bi-exclamation-triangle-fill','class' => 'text-bg-dark',      'href' => '/modules/requirements/monitor.php'],
];

$by_dept = $pdo->query(
    'SELECT COALESCE(d.department_name, "Unassigned") AS name, COUNT(e.employee_id) AS total
     FROM employees e LEFT JOIN departments d ON d.department_id = e.department_id
     GROUP BY d.department_id, d.department_name ORDER BY total DESC'
)->fetchAll();
$dept_max = max(1, ...array_map(fn($r) => (int)$r['total'], $by_dept ?: [['total' => 1]]));

$by_status = $pdo->query(
    'SELECT employment_status, COUNT(*) AS total FROM employees GROUP BY employment_status'
)->fetchAll();
$total_people = max(1, array_sum(array_column($by_status, 'total')));
$status_colors = ['Applicant' => 'bg-warning', 'Active' => 'bg-success', 'Inactive' => 'bg-secondary', 'Terminated' => 'bg-dark'];

$pending_list = $pdo->query(
    "SELECT e.employee_id, e.first_name, e.middle_name, e.no_middle_name, e.last_name, e.name_extension, d.department_name,
            SUM(er.status IN ('Missing','Incomplete')) AS pending
     FROM employees e
     LEFT JOIN departments d ON d.department_id = e.department_id
     JOIN employee_requirements er ON er.employee_id = e.employee_id
     JOIN requirement_types rt ON rt.requirement_type_id = er.requirement_type_id AND rt.is_active = 1
     GROUP BY e.employee_id, e.first_name, e.middle_name, e.no_middle_name, e.last_name, e.name_extension, d.department_name
     HAVING pending > 0 ORDER BY pending DESC LIMIT 8"
)->fetchAll();

$page_title = 'Dashboard';
$storage = storage_stats();
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h3 mb-1"><i class="bi bi-speedometer2 me-2"></i>Dashboard</h1>
<p class="text-muted mb-4">Welcome, <?= e($_SESSION['full_name']) ?>. Here is the current overview for <?= e(APP_COMPANY) ?>.</p>

<div class="row g-3 mb-4">
<?php foreach ($cards as $card): ?>
    <div class="col-sm-6 col-xl-3">
        <a href="<?= BASE_URL . $card['href'] ?>" class="text-decoration-none">
            <div class="card shadow-sm <?= $card['class'] ?>">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fs-2 fw-bold"><?= $card['value'] ?></div>
                        <div><?= e($card['label']) ?></div>
                    </div>
                    <i class="bi <?= $card['icon'] ?> fs-1 opacity-50"></i>
                </div>
            </div>
        </a>
    </div>
<?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Employees per Department</div>
            <div class="card-body">
                <?php if (!$by_dept): ?><p class="text-muted mb-0">No employees yet.</p><?php endif; ?>
                <?php foreach ($by_dept as $d): ?>
                    <div class="d-flex justify-content-between small mb-1">
                        <span><?= e($d['name']) ?></span><span class="fw-semibold"><?= (int)$d['total'] ?></span>
                    </div>
                    <div class="progress mb-3" style="height:10px">
                        <div class="progress-bar bg-danger" style="width:<?= (int)($d['total'] / $dept_max * 100) ?>%"></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-3">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Status Breakdown</div>
            <div class="card-body">
                <?php foreach ($by_status as $s): $pct = (int)round((int)$s['total'] / $total_people * 100); ?>
                    <div class="d-flex justify-content-between small mb-1">
                        <span><?= e($s['employment_status']) ?></span>
                        <span class="fw-semibold"><?= (int)$s['total'] ?> (<?= $pct ?>%)</span>
                    </div>
                    <div class="progress mb-3" style="height:10px">
                        <div class="progress-bar <?= $status_colors[$s['employment_status']] ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$by_status): ?><p class="text-muted mb-0">No records yet.</p><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Pending Requirements</span>
                <a href="<?= BASE_URL ?>/modules/requirements/monitor.php" class="small">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <tbody>
                    <?php if (!$pending_list): ?>
                        <tr><td class="text-center text-muted py-4">All requirements are complete. 🎉</td></tr>
                    <?php endif; ?>
                    <?php foreach ($pending_list as $p): ?>
                        <tr>
                            <td>
                                <a href="<?= BASE_URL ?>/modules/employees/view.php?id=<?= (int)$p['employee_id'] ?>" class="text-decoration-none">
                                    <?= e(employee_display_name($p)) ?>
                                </a>
                                <div class="small text-muted"><?= e($p['department_name'] ?? 'Unassigned') ?></div>
                            </td>
                            <td class="text-end">
                                <span class="badge text-bg-danger"><?= (int)$p['pending'] ?> pending</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mt-4">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-hdd me-1"></i>Storage Capacity
        <span class="text-muted fw-normal small">— photos stored as files, DB keeps path only</span>
    </div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col-sm-3">
                <div class="fs-5 fw-bold"><?= (int)$storage['files'] ?></div>
                <div class="small text-muted">Photos stored (<?= e(format_bytes($storage['used'])) ?> used)</div>
            </div>
            <div class="col-sm-3">
                <div class="fs-5 fw-bold"><?= e(format_bytes($storage['free'])) ?></div>
                <div class="small text-muted">Free disk space</div>
            </div>
            <div class="col-sm-3">
                <div class="fs-5 fw-bold">~<?= number_format((int)$storage['fits_typical']) ?></div>
                <div class="small text-muted">More photos fit at ~500 KB avg</div>
            </div>
            <div class="col-sm-3">
                <div class="fs-5 fw-bold">~<?= number_format((int)$storage['fits_max']) ?></div>
                <div class="small text-muted">More photos fit at 2 MB max</div>
            </div>
        </div>
        <p class="small text-muted mb-0 mt-2">Max 2 MB per photo (JPG/PNG). Total capacity grows with disk space.</p>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
