<?php
// modules/employees/view.php
// Employee profile page with requirements checklist summary.

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT e.*, d.department_name
     FROM employees e
     LEFT JOIN departments d ON d.department_id = e.department_id
     WHERE e.employee_id = ?'
);
$stmt->execute([$id]);
$emp = $stmt->fetch();

if (!$emp) {
    flash_set('danger', 'Employee not found.');
    redirect('/modules/employees/index.php');
}

sync_requirements($pdo, $id);

$reqs = $pdo->prepare(
    'SELECT rt.requirement_name, er.status, er.date_submitted, er.file_path, er.remarks
     FROM employee_requirements er
     JOIN requirement_types rt ON rt.requirement_type_id = er.requirement_type_id
     WHERE er.employee_id = ? AND rt.is_active = 1
     ORDER BY rt.requirement_name'
);
$reqs->execute([$id]);
$requirements = $reqs->fetchAll();

$req_badge = [
    'Missing'    => 'text-bg-danger',
    'Incomplete' => 'text-bg-warning',
    'Submitted'  => 'text-bg-info',
    'Verified'   => 'text-bg-success',
];
$status_badge = [
    'Applicant'  => 'text-bg-warning',
    'Active'     => 'text-bg-success',
    'Inactive'   => 'text-bg-secondary',
    'Terminated' => 'text-bg-dark',
];

$pending = count(array_filter($requirements, fn($r) => in_array($r['status'], ['Missing', 'Incomplete'], true)));

$page_title = $emp['first_name'] . ' ' . $emp['last_name'];
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-person-vcard me-2"></i>Employee Profile</h1>
    <div>
        <a href="<?= BASE_URL ?>/modules/reports/employee_form.php?id=<?= $id ?>" class="btn btn-outline-secondary">
            <i class="bi bi-printer me-1"></i>Information Form
        </a>
        <a href="edit.php?id=<?= $id ?>" class="btn btn-danger"><i class="bi bi-pencil me-1"></i>Edit</a>
        <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm text-center">
            <div class="card-body">
                <?php if ($emp['photo_path']): ?>
                    <img src="<?= BASE_URL . '/' . e($emp['photo_path']) ?>" class="rounded-circle mb-3" width="120" height="120" style="object-fit:cover" alt="Photo">
                <?php else: ?>
                    <i class="bi bi-person-circle text-secondary" style="font-size:7rem"></i>
                <?php endif; ?>
                <h2 class="h5 mb-1"><?= e($emp['first_name'] . ' ' . ($emp['middle_name'] ? $emp['middle_name'] . ' ' : '') . $emp['last_name']) ?></h2>
                <div class="text-muted mb-2"><?= e($emp['position'] ?? 'No position') ?></div>
                <span class="badge <?= $status_badge[$emp['employment_status']] ?>"><?= e($emp['employment_status']) ?></span>
                <span class="badge <?= $emp['applicant_type'] === 'New' ? 'text-bg-info' : 'text-bg-primary' ?>"><?= e($emp['applicant_type']) ?> Applicant</span>
            </div>
            <ul class="list-group list-group-flush text-start">
                <li class="list-group-item"><i class="bi bi-hash me-2 text-danger"></i><?= e($emp['employee_no'] ?? 'No employee number') ?></li>
                <li class="list-group-item"><i class="bi bi-diagram-3 me-2 text-danger"></i><?= e($emp['department_name'] ?? 'Unassigned') ?></li>
                <li class="list-group-item"><i class="bi bi-cake2 me-2 text-danger"></i><?= e(date('F j, Y', strtotime($emp['birthdate']))) ?> (<?= e($emp['sex']) ?>)</li>
                <li class="list-group-item"><i class="bi bi-telephone me-2 text-danger"></i><?= e($emp['contact_no'] ?? '—') ?></li>
                <li class="list-group-item"><i class="bi bi-envelope me-2 text-danger"></i><?= e($emp['email'] ?? '—') ?></li>
                <li class="list-group-item"><i class="bi bi-geo-alt me-2 text-danger"></i><?= e($emp['address'] ?? '—') ?></li>
                <li class="list-group-item"><i class="bi bi-calendar-check me-2 text-danger"></i>
                    Hired: <?= $emp['date_hired'] ? e(date('F j, Y', strtotime($emp['date_hired']))) : '—' ?>
                </li>
            </ul>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Requirements Checklist</span>
                <div>
                    <?php if ($pending > 0): ?>
                        <span class="badge text-bg-danger me-2"><?= $pending ?> pending</span>
                    <?php else: ?>
                        <span class="badge text-bg-success me-2">Complete</span>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/modules/requirements/checklist.php?employee_id=<?= $id ?>" class="btn btn-sm btn-danger">
                        <i class="bi bi-pencil-square me-1"></i>Update Checklist
                    </a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Requirement</th><th class="text-center">Status</th><th>Date Submitted</th><th>Document</th><th>Remarks</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$requirements): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">
                            No requirement types defined yet.
                            <a href="<?= BASE_URL ?>/modules/requirements/types.php">Set them up here.</a>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($requirements as $r): ?>
                        <tr>
                            <td><?= e($r['requirement_name']) ?></td>
                            <td class="text-center"><span class="badge <?= $req_badge[$r['status']] ?>"><?= e($r['status']) ?></span></td>
                            <td><?= $r['date_submitted'] ? e(date('M j, Y', strtotime($r['date_submitted']))) : '—' ?></td>
                            <td>
                                <?php if ($r['file_path']): ?>
                                    <a href="<?= BASE_URL . '/' . e($r['file_path']) ?>" target="_blank"><i class="bi bi-file-earmark-arrow-down me-1"></i>View</a>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= e($r['remarks'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
