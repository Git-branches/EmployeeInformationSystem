<?php
// modules/reports/employee_form.php
// Standardized Employee Information Form for one employee (view/print),
// with a PDF download via export.php?form_id=.

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT e.*, d.department_name, s.status_name AS appointment_status FROM employees e
     LEFT JOIN departments d ON d.department_id = e.department_id
     LEFT JOIN employment_statuses s ON s.employment_status_id = e.employment_status_id
     WHERE e.employee_id = ?'
);
$stmt->execute([$id]);
$emp = $stmt->fetch();

if (!$emp) {
    flash_set('danger', 'Employee not found.');
    redirect('/modules/employees/index.php');
}

$reqs = $pdo->prepare(
    'SELECT rt.requirement_name, er.status, er.date_submitted
     FROM employee_requirements er
     JOIN requirement_types rt ON rt.requirement_type_id = er.requirement_type_id
     WHERE er.employee_id = ? AND rt.is_active = 1 ORDER BY rt.requirement_name'
);
$reqs->execute([$id]);
$requirements = $reqs->fetchAll();

$page_title = 'Employee Information Form';
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-file-earmark-person me-2"></i>Employee Information Form</h1>
    <div>
        <button onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</button>
        <a href="export.php?form_id=<?= $id ?>" class="btn btn-outline-danger"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</a>
        <a href="<?= BASE_URL ?>/modules/employees/view.php?id=<?= $id ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    </div>
</div>

<div class="card shadow-sm mx-auto" style="max-width:850px">
    <div class="card-body p-5">
        <div class="text-center border-bottom pb-3 mb-4">
            <?php if (APP_COMPANY !== ''): ?>
                <h2 class="h4 mb-0"><?= e(APP_COMPANY) ?></h2>
            <?php endif; ?>
            <?php if (APP_ADDRESS !== ''): ?>
                <div class="text-muted"><?= e(APP_ADDRESS) ?></div>
            <?php endif; ?>
            <div class="fw-bold mt-2 text-uppercase">Employee Information Form</div>
        </div>

        <div class="row mb-4">
            <div class="col-9">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted" style="width:170px">Employee No.</td><td class="fw-semibold"><?= e($emp['employee_no'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">Full Name</td><td class="fw-semibold"><?= e(employee_display_name($emp)) ?></td></tr>
                    <tr><td class="text-muted">Birthday</td><td><?= e(date('F j, Y', strtotime($emp['birthdate']))) ?></td></tr>
                    <tr><td class="text-muted">Birthplace</td><td><?= e($emp['birthplace'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">Sex / Civil Status</td><td><?= e($emp['sex']) ?> / <?= e($emp['civil_status'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">CP Number</td><td><?= e($emp['contact_no'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">Email</td><td><?= e($emp['email'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">Address</td><td><?= e($emp['address'] ?? '—') ?></td></tr>
                    <tr>
                        <td class="text-muted">Blood Type / Height / Weight</td>
                        <td>
                            <?= e($emp['blood_type'] ?? '—') ?> /
                            <?= $emp['height_cm'] !== null ? e(rtrim(rtrim($emp['height_cm'], '0'), '.')) . ' cm' : '—' ?> /
                            <?= $emp['weight_kg'] !== null ? e(rtrim(rtrim($emp['weight_kg'], '0'), '.')) . ' kg' : '—' ?>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="col-3 text-center">
                <?php if ($emp['photo_path']): ?>
                    <img src="<?= BASE_URL . '/' . e($emp['photo_path']) ?>" class="border" width="130" height="130" style="object-fit:cover" alt="Photo">
                <?php else: ?>
                    <div class="border d-flex align-items-center justify-content-center text-muted" style="width:130px;height:130px">2x2 Photo</div>
                <?php endif; ?>
            </div>
        </div>

        <h3 class="h6 text-uppercase border-bottom pb-2">Government ID Numbers</h3>
        <table class="table table-sm table-borderless mb-4">
            <tr>
                <td class="text-muted" style="width:170px">SSS No.</td><td><?= e($emp['sss_no'] ?? '—') ?></td>
                <td class="text-muted" style="width:150px">PhilHealth No.</td><td><?= e($emp['philhealth_no'] ?? '—') ?></td>
            </tr>
            <tr>
                <td class="text-muted">Pag-IBIG No.</td><td><?= e($emp['pagibig_no'] ?? '—') ?></td>
                <td class="text-muted">TIN No.</td><td><?= e($emp['tin_no'] ?? '—') ?></td>
            </tr>
            <tr>
                <td class="text-muted">GSIS No.</td><td><?= e($emp['gsis_no'] ?? '—') ?></td>
                <td></td><td></td>
            </tr>
        </table>

        <div class="row mb-4">
            <div class="col-6">
                <h3 class="h6 text-uppercase border-bottom pb-2">Personal Status</h3>
                <?php foreach (['is_solo_parent' => 'Solo Parent', 'is_ip' => 'IP (Indigenous People)',
                                'is_pwd' => 'PWD (Person with Disability)', 'is_smoker' => 'Smoker'] as $key => $label): ?>
                    <div><span class="me-2"><?= !empty($emp[$key]) ? '&#9745;' : '&#9744;' ?></span><?= e($label) ?></div>
                <?php endforeach; ?>
            </div>
            <div class="col-6">
                <h3 class="h6 text-uppercase border-bottom pb-2">Eligibility</h3>
                <?php foreach (['elig_professional' => 'Professional (Prof.)',
                                'elig_sub_professional' => 'Sub-Professional (Sub-Prof.)',
                                'elig_ra1080' => 'RA 1080'] as $key => $label): ?>
                    <div><span class="me-2"><?= !empty($emp[$key]) ? '&#9745;' : '&#9744;' ?></span><?= e($label) ?></div>
                <?php endforeach; ?>
            </div>
        </div>

        <h3 class="h6 text-uppercase border-bottom pb-2">In Case of Emergency, Please Contact</h3>
        <table class="table table-sm table-borderless mb-4">
            <tr>
                <td class="text-muted" style="width:170px">Name</td><td><?= e($emp['emergency_contact_name'] ?? '—') ?></td>
                <td class="text-muted" style="width:150px">Contact Number</td><td><?= e($emp['emergency_contact_no'] ?? '—') ?></td>
            </tr>
        </table>

        <h3 class="h6 text-uppercase border-bottom pb-2">Employment Details</h3>
        <table class="table table-sm table-borderless mb-4">
            <tr><td class="text-muted" style="width:170px">Department</td><td><?= e($emp['department_name'] ?? 'Unassigned') ?></td></tr>
            <tr><td class="text-muted">Position</td><td><?= e($emp['position'] ?? '—') ?></td></tr>
            <tr><td class="text-muted">Applicant Type</td><td><?= e($emp['applicant_type']) ?></td></tr>
            <tr><td class="text-muted">Employment Status</td><td><?= e($emp['appointment_status'] ?? '—') ?></td></tr>
            <tr><td class="text-muted">Record Status</td><td><?= e($emp['employment_status']) ?></td></tr>
            <tr><td class="text-muted">Date Hired</td><td><?= $emp['date_hired'] ? e(date('F j, Y', strtotime($emp['date_hired']))) : '—' ?></td></tr>
            <tr><td class="text-muted">Monthly Salary</td><td class="fw-semibold"><?= e(peso($emp['monthly_salary'])) ?></td></tr>
            <tr>
                <td class="text-muted">Daily Salary</td>
                <td><?= e(peso(daily_salary($emp['monthly_salary']))) ?>
                    <span class="text-muted small">(÷ <?= WORKING_DAYS_PER_MONTH ?> working days)</span>
                </td>
            </tr>
        </table>

        <h3 class="h6 text-uppercase border-bottom pb-2">Requirements Checklist</h3>
        <table class="table table-sm table-bordered">
            <thead class="table-light"><tr><th>Requirement</th><th style="width:120px">Status</th><th style="width:140px">Date Submitted</th></tr></thead>
            <tbody>
            <?php foreach ($requirements as $r): ?>
                <tr>
                    <td><?= e($r['requirement_name']) ?></td>
                    <td><?= e($r['status']) ?></td>
                    <td><?= $r['date_submitted'] ? e(date('M j, Y', strtotime($r['date_submitted']))) : '' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$requirements): ?>
                <tr><td colspan="3" class="text-muted">No requirements defined.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

        <div class="row mt-5 pt-4">
            <div class="col-6 text-center">
                <div class="border-top mx-4 pt-1">Employee Signature</div>
            </div>
            <div class="col-6 text-center">
                <div class="border-top mx-4 pt-1">Administrator</div>
            </div>
        </div>
        <div class="text-muted small mt-4">Generated by <?= e(APP_NAME) ?> on <?= e(date('F j, Y g:i A')) ?></div>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
