<?php
// modules/requirements/checklist.php
// Per-employee requirements checklist editor: status, date, document, remarks.

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

$employee_id = (int)($_GET['employee_id'] ?? $_POST['employee_id'] ?? 0);

$stmt = $pdo->prepare('SELECT employee_id, first_name, middle_name, no_middle_name, last_name, name_extension FROM employees WHERE employee_id = ?');
$stmt->execute([$employee_id]);
$emp = $stmt->fetch();

if (!$emp) {
    flash_set('danger', 'Employee not found.');
    redirect('/modules/employees/index.php');
}

sync_requirements($pdo, $employee_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $statuses = $_POST['status'] ?? [];
    $dates    = $_POST['date_submitted'] ?? [];
    $remarks  = $_POST['remarks'] ?? [];
    $valid    = ['Missing', 'Incomplete', 'Submitted', 'Verified'];

    $update = $pdo->prepare(
        'UPDATE employee_requirements
         SET status = ?, date_submitted = ?, remarks = ?
         WHERE employee_requirement_id = ? AND employee_id = ?'
    );
    $file_update = $pdo->prepare(
        'UPDATE employee_requirements SET file_path = ?
         WHERE employee_requirement_id = ? AND employee_id = ?'
    );

    foreach ($statuses as $er_id => $status) {
        $er_id = (int)$er_id;
        if (!in_array($status, $valid, true)) {
            continue;
        }
        $date   = ($dates[$er_id] ?? '') !== '' ? $dates[$er_id] : null;
        $remark = trim((string)($remarks[$er_id] ?? '')) ?: null;
        $update->execute([$status, $date, $remark, $er_id, $employee_id]);

        // Optional scanned document per requirement (PDF/JPG/PNG, max 5 MB)
        $file = $_FILES['document']['tmp_name'][$er_id] ?? '';
        if ($file !== '' && ($_FILES['document']['error'][$er_id] ?? 1) === UPLOAD_ERR_OK) {
            $size = (int)$_FILES['document']['size'][$er_id];
            $ext  = strtolower(pathinfo((string)$_FILES['document']['name'][$er_id], PATHINFO_EXTENSION));
            if ($size <= 5 * 1024 * 1024 && in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
                $name = 'req_' . $employee_id . '_' . $er_id . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($file, UPLOAD_PATH . '/requirements/' . $name)) {
                    $file_update->execute(['uploads/requirements/' . $name, $er_id, $employee_id]);
                }
            }
        }
    }

    // Refresh alerts: clear read state only for this employee's unread alert
    // if now complete, or create one if pending and none exists.
    $pending = (int)$pdo->query(
        "SELECT COUNT(*) FROM employee_requirements
         WHERE employee_id = $employee_id AND status IN ('Missing','Incomplete')"
    )->fetchColumn();
    if ($pending === 0) {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE employee_id = ?')->execute([$employee_id]);
    }
    generate_notifications($pdo);

    flash_set('success', 'Checklist updated.');
    redirect('/modules/employees/view.php?id=' . $employee_id);
}

$reqs = $pdo->prepare(
    'SELECT er.employee_requirement_id, er.status, er.date_submitted, er.file_path, er.remarks,
            rt.requirement_name
     FROM employee_requirements er
     JOIN requirement_types rt ON rt.requirement_type_id = er.requirement_type_id
     WHERE er.employee_id = ? AND rt.is_active = 1
     ORDER BY rt.requirement_name'
);
$reqs->execute([$employee_id]);
$requirements = $reqs->fetchAll();

$page_title = 'Update Checklist';
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="bi bi-card-checklist me-2"></i>Checklist:
        <?= e(employee_display_name($emp)) ?>
    </h1>
    <a href="<?= BASE_URL ?>/modules/employees/view.php?id=<?= $employee_id ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to profile
    </a>
</div>

<?php if (!$requirements): ?>
    <div class="alert alert-warning">
        No active requirement types defined. <a href="types.php">Set up the requirement list first.</a>
    </div>
<?php else: ?>
<form method="post" enctype="multipart/form-data" class="card shadow-sm">
    <?= csrf_field() ?>
    <input type="hidden" name="employee_id" value="<?= $employee_id ?>">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr><th>Requirement</th><th style="width:150px">Status</th><th style="width:170px">Date Submitted</th><th style="width:220px">Document</th><th>Remarks</th></tr>
            </thead>
            <tbody>
            <?php foreach ($requirements as $r): $id = (int)$r['employee_requirement_id']; ?>
                <tr>
                    <td class="fw-semibold"><?= e($r['requirement_name']) ?></td>
                    <td>
                        <select class="form-select form-select-sm" name="status[<?= $id ?>]">
                            <?php foreach (['Missing', 'Incomplete', 'Submitted', 'Verified'] as $s): ?>
                                <option <?= $r['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <input type="date" class="form-control form-control-sm" name="date_submitted[<?= $id ?>]"
                               value="<?= e($r['date_submitted'] ?? '') ?>">
                    </td>
                    <td>
                        <input type="file" class="form-control form-control-sm" name="document[<?= $id ?>]" accept=".pdf,.jpg,.jpeg,.png">
                        <?php if ($r['file_path']): ?>
                            <a class="small" href="<?= BASE_URL . '/' . e($r['file_path']) ?>" target="_blank">Current file</a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm" name="remarks[<?= $id ?>]"
                               maxlength="255" value="<?= e($r['remarks'] ?? '') ?>">
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">
        <button type="submit" class="btn btn-danger"><i class="bi bi-save me-1"></i>Save Checklist</button>
    </div>
</form>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
