<?php
// modules/requirements/monitor.php
// Compliance overview across all employees; refreshes notification alerts.

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

generate_notifications($pdo);

$filter = (string)($_GET['show'] ?? 'pending');

$rows = $pdo->query(
    "SELECT e.employee_id, e.first_name, e.last_name, e.employment_status,
            d.department_name,
            COUNT(er.employee_requirement_id) AS total,
            SUM(er.status = 'Verified')  AS verified,
            SUM(er.status = 'Submitted') AS submitted,
            SUM(er.status IN ('Missing','Incomplete')) AS pending
     FROM employees e
     LEFT JOIN departments d ON d.department_id = e.department_id
     LEFT JOIN employee_requirements er ON er.employee_id = e.employee_id
     LEFT JOIN requirement_types rt ON rt.requirement_type_id = er.requirement_type_id AND rt.is_active = 1
     GROUP BY e.employee_id, e.first_name, e.last_name, e.employment_status, d.department_name
     ORDER BY pending DESC, e.last_name"
)->fetchAll();

if ($filter === 'pending') {
    $rows = array_filter($rows, fn($r) => (int)$r['pending'] > 0);
}

$page_title = 'Requirements Monitor';
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-clipboard-data me-2"></i>Requirements Monitor</h1>
    <div>
        <div class="btn-group me-2">
            <a href="monitor.php?show=pending" class="btn btn-outline-danger <?= $filter === 'pending' ? 'active' : '' ?>">With Pending</a>
            <a href="monitor.php?show=all" class="btn btn-outline-danger <?= $filter === 'all' ? 'active' : '' ?>">All Employees</a>
        </div>
        <a href="types.php" class="btn btn-outline-secondary"><i class="bi bi-gear me-1"></i>Requirement Types</a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Employee</th><th>Department</th><th>Status</th>
                    <th class="text-center">Pending</th><th class="text-center">Submitted</th>
                    <th class="text-center">Verified</th><th style="width:220px">Completion</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">
                    <?= $filter === 'pending' ? 'No employees with pending requirements — all compliant. 🎉' : 'No employees yet.' ?>
                </td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r):
                $total = max((int)$r['total'], 1);
                $done  = (int)$r['verified'];
                $pct   = (int)round($done / $total * 100);
            ?>
                <tr>
                    <td class="fw-semibold"><?= e($r['last_name'] . ', ' . $r['first_name']) ?></td>
                    <td><?= e($r['department_name'] ?? 'Unassigned') ?></td>
                    <td><span class="badge text-bg-secondary"><?= e($r['employment_status']) ?></span></td>
                    <td class="text-center">
                        <span class="badge <?= (int)$r['pending'] > 0 ? 'text-bg-danger' : 'text-bg-success' ?>"><?= (int)$r['pending'] ?></span>
                    </td>
                    <td class="text-center"><?= (int)$r['submitted'] ?></td>
                    <td class="text-center"><?= (int)$r['verified'] ?></td>
                    <td>
                        <div class="progress" style="height:18px">
                            <div class="progress-bar <?= $pct === 100 ? 'bg-success' : 'bg-danger' ?>" style="width:<?= $pct ?>%">
                                <?= $pct ?>%
                            </div>
                        </div>
                    </td>
                    <td class="text-end">
                        <a href="checklist.php?employee_id=<?= (int)$r['employee_id'] ?>" class="btn btn-sm btn-danger">
                            <i class="bi bi-pencil-square me-1"></i>Update
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
