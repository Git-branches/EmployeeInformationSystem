<?php
// modules/employees/form.php
// Shared add/edit form + save logic. Included by add.php ($employee = null)
// and edit.php ($employee = existing row). Not accessed directly.

if (!defined('ROOT_PATH')) {
    exit('Direct access not allowed.');
}

$is_edit = $employee !== null;
$error   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $data = [
        'employee_no'       => trim((string)($_POST['employee_no'] ?? '')) ?: null,
        'first_name'        => trim((string)($_POST['first_name'] ?? '')),
        'middle_name'       => trim((string)($_POST['middle_name'] ?? '')) ?: null,
        'last_name'         => trim((string)($_POST['last_name'] ?? '')),
        'birthdate'         => (string)($_POST['birthdate'] ?? ''),
        'sex'               => (string)($_POST['sex'] ?? ''),
        'contact_no'        => trim((string)($_POST['contact_no'] ?? '')) ?: null,
        'email'             => trim((string)($_POST['email'] ?? '')) ?: null,
        'address'           => trim((string)($_POST['address'] ?? '')) ?: null,
        'department_id'     => (int)($_POST['department_id'] ?? 0) ?: null,
        'position'          => trim((string)($_POST['position'] ?? '')) ?: null,
        'applicant_type'    => (string)($_POST['applicant_type'] ?? 'New'),
        'employment_status' => (string)($_POST['employment_status'] ?? 'Applicant'),
        'date_hired'        => (string)($_POST['date_hired'] ?? '') ?: null,
    ];

    if ($data['first_name'] === '' || $data['last_name'] === '' || $data['birthdate'] === '') {
        $error = 'First name, last name, and birthdate are required.';
    } elseif (!in_array($data['sex'], ['Male', 'Female'], true)) {
        $error = 'Please select a sex.';
    } elseif (!in_array($data['applicant_type'], ['New', 'Existing'], true)
        || !in_array($data['employment_status'], ['Applicant', 'Active', 'Inactive', 'Terminated'], true)) {
        $error = 'Invalid category selection.';
    } elseif ($data['email'] !== null && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    }

    if ($error === null) {
        try {
            $photo = save_photo($_FILES['photo'] ?? []);

            if ($is_edit) {
                if ($photo !== null && $employee['photo_path'] && is_file(ROOT_PATH . '/' . $employee['photo_path'])) {
                    unlink(ROOT_PATH . '/' . $employee['photo_path']);
                }
                $sql = 'UPDATE employees SET employee_no=?, first_name=?, middle_name=?, last_name=?, birthdate=?,
                        sex=?, contact_no=?, email=?, address=?, department_id=?, position=?, applicant_type=?,
                        employment_status=?, date_hired=?' . ($photo !== null ? ', photo_path=?' : '') . '
                        WHERE employee_id=?';
                $params = array_values($data);
                if ($photo !== null) {
                    $params[] = $photo;
                }
                $params[] = (int)$employee['employee_id'];
                $pdo->prepare($sql)->execute($params);
                $id = (int)$employee['employee_id'];
                flash_set('success', 'Employee profile updated.');
            } else {
                $sql = 'INSERT INTO employees (employee_no, first_name, middle_name, last_name, birthdate, sex,
                        contact_no, email, address, department_id, position, applicant_type, employment_status,
                        date_hired, photo_path)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
                $params = array_values($data);
                $params[] = $photo;
                $pdo->prepare($sql)->execute($params);
                $id = (int)$pdo->lastInsertId();
                sync_requirements($pdo, $id);
                flash_set('success', 'Employee added. Requirements checklist was created.');
            }
            redirect('/modules/employees/view.php?id=' . $id);
        } catch (RuntimeException $ex) {
            $error = $ex->getMessage();
        } catch (PDOException $ex) {
            $error = $ex->getCode() === '23000'
                ? 'That employee number is already in use.'
                : 'Database error while saving. Please try again.';
        }
    }
    // Re-fill form with submitted values on error
    $employee = array_merge($employee ?? [], $data, ['photo_path' => $employee['photo_path'] ?? null]);
}

$departments = $pdo->query('SELECT department_id, department_name FROM departments ORDER BY department_name')->fetchAll();

$page_title = $is_edit ? 'Edit Employee' : 'Add Employee';
require __DIR__ . '/../../includes/header.php';

$v = fn(string $key) => e((string)($employee[$key] ?? ''));
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="bi <?= $is_edit ? 'bi-pencil-square' : 'bi-person-plus' ?> me-2"></i><?= $page_title ?>
    </h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to list</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card shadow-sm"
      data-autosave="employee-<?= $is_edit ? (int)$employee['employee_id'] : 'new' ?>">
    <?= csrf_field() ?>
    <div class="card-body">
        <h2 class="h6 text-danger border-bottom pb-2">Personal Information</h2>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label">First Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="first_name" maxlength="60" required value="<?= $v('first_name') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Middle Name</label>
                <input type="text" class="form-control" name="middle_name" maxlength="60" value="<?= $v('middle_name') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Last Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="last_name" maxlength="60" required value="<?= $v('last_name') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Birthdate <span class="text-danger">*</span></label>
                <input type="date" class="form-control" name="birthdate" required value="<?= $v('birthdate') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Sex <span class="text-danger">*</span></label>
                <select class="form-select" name="sex" required>
                    <option value="">Select…</option>
                    <option <?= ($employee['sex'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                    <option <?= ($employee['sex'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Contact No.</label>
                <input type="text" class="form-control" name="contact_no" maxlength="20" value="<?= $v('contact_no') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" name="email" maxlength="100" value="<?= $v('email') ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Address</label>
                <input type="text" class="form-control" name="address" maxlength="255" value="<?= $v('address') ?>">
            </div>
        </div>

        <h2 class="h6 text-danger border-bottom pb-2">Employment Information</h2>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Employee No.</label>
                <input type="text" class="form-control" name="employee_no" maxlength="20"
                       placeholder="Leave blank for applicants" value="<?= $v('employee_no') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Department</label>
                <select class="form-select" name="department_id">
                    <option value="">Unassigned</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int)$d['department_id'] ?>"
                            <?= (int)($employee['department_id'] ?? 0) === (int)$d['department_id'] ? 'selected' : '' ?>>
                            <?= e($d['department_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Position</label>
                <input type="text" class="form-control" name="position" maxlength="100" value="<?= $v('position') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Date Hired</label>
                <input type="date" class="form-control" name="date_hired" value="<?= $v('date_hired') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Applicant Type <span class="text-danger">*</span></label>
                <select class="form-select" name="applicant_type" required>
                    <option <?= ($employee['applicant_type'] ?? 'New') === 'New' ? 'selected' : '' ?>>New</option>
                    <option <?= ($employee['applicant_type'] ?? '') === 'Existing' ? 'selected' : '' ?>>Existing</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Employment Status <span class="text-danger">*</span></label>
                <select class="form-select" name="employment_status" required>
                    <?php foreach (['Applicant', 'Active', 'Inactive', 'Terminated'] as $s): ?>
                        <option <?= ($employee['employment_status'] ?? 'Applicant') === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Photo (JPG/PNG, max 2 MB)</label>
                <input type="file" class="form-control" name="photo" accept=".jpg,.jpeg,.png">
                <?php if (!empty($employee['photo_path'])): ?>
                    <small class="text-muted">A photo is already on file; uploading replaces it.</small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white">
        <button type="submit" class="btn btn-danger">
            <i class="bi bi-save me-1"></i><?= $is_edit ? 'Update Profile' : 'Save Employee' ?>
        </button>
        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
        <span class="text-muted small ms-2" id="autosave-status"></span>
    </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
