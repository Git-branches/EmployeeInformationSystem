<?php
// modules/employees/form.php
// Shared add/edit form + save logic. Included by add.php ($employee = null)
// and edit.php ($employee = existing row). Not accessed directly.
//
// The SQL is generated from the keys of $data, so adding a field here only
// needs a new entry in that array plus its input below.

if (!defined('ROOT_PATH')) {
    exit('Direct access not allowed.');
}

$is_edit = $employee !== null;
$error   = null;

$CIVIL_STATUS = ['Single', 'Married', 'Widowed', 'Separated', 'Annulled'];
$BLOOD_TYPES  = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
$STATUSES     = ['Applicant', 'Active', 'Inactive', 'Terminated'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    /** Trimmed text, or null when left blank. */
    $text = fn(string $k) => trim((string)($_POST[$k] ?? '')) ?: null;
    /** Checkbox → 1/0. */
    $flag = fn(string $k) => isset($_POST[$k]) ? 1 : 0;
    /** Decimal, or null when blank/invalid. */
    $num  = function (string $k) {
        $v = trim((string)($_POST[$k] ?? ''));
        return ($v === '' || !is_numeric($v)) ? null : round((float)$v, 2);
    };

    // Salary is validated separately (below) so a bad figure is reported
    // instead of being silently dropped like the other optional numbers.
    $salary_raw = trim((string)($_POST['monthly_salary'] ?? ''));
    $salary_ok  = $salary_raw === ''
        || (is_numeric($salary_raw) && (float)$salary_raw >= 0 && (float)$salary_raw <= 9999999999.99);

    $data = [
        // ---- Personal information
        'employee_no'   => $text('employee_no'),
        'first_name'    => trim((string)($_POST['first_name'] ?? '')),
        'middle_name'   => $text('middle_name'),
        'last_name'     => trim((string)($_POST['last_name'] ?? '')),
        'birthdate'     => (string)($_POST['birthdate'] ?? ''),
        'birthplace'    => $text('birthplace'),
        'sex'           => (string)($_POST['sex'] ?? ''),
        'civil_status'  => $text('civil_status'),
        'blood_type'    => $text('blood_type'),
        'height_cm'     => $num('height_cm'),
        'weight_kg'     => $num('weight_kg'),
        'contact_no'    => $text('contact_no'),
        'email'         => $text('email'),
        'address'       => $text('address'),

        // ---- Government ID numbers
        'sss_no'        => $text('sss_no'),
        'philhealth_no' => $text('philhealth_no'),
        'pagibig_no'    => $text('pagibig_no'),
        'tin_no'        => $text('tin_no'),

        // ---- Personal status
        'is_solo_parent' => $flag('is_solo_parent'),
        'is_ip'          => $flag('is_ip'),
        'is_pwd'         => $flag('is_pwd'),
        'is_smoker'      => $flag('is_smoker'),

        // ---- Eligibility
        'elig_professional'     => $flag('elig_professional'),
        'elig_sub_professional' => $flag('elig_sub_professional'),
        'elig_ra1080'           => $flag('elig_ra1080'),

        // ---- Emergency contact
        'emergency_contact_name' => $text('emergency_contact_name'),
        'emergency_contact_no'   => $text('emergency_contact_no'),

        // ---- Employment information
        'department_id'     => (int)($_POST['department_id'] ?? 0) ?: null,
        'position'          => $text('position'),
        'applicant_type'    => (string)($_POST['applicant_type'] ?? 'New'),
        'employment_status' => (string)($_POST['employment_status'] ?? 'Applicant'),
        'date_hired'        => (string)($_POST['date_hired'] ?? '') ?: null,

        // ---- Compensation
        // Only the monthly salary is stored; the daily salary is always
        // derived from it with daily_salary().
        'monthly_salary'    => ($salary_ok && $salary_raw !== '') ? round((float)$salary_raw, 2) : null,
    ];

    // Names, addresses and other free-text entries are recorded in upper case.
    $data = upper_employee_fields($data);

    if (($data['first_name'] ?? '') === '' || ($data['last_name'] ?? '') === '' || $data['birthdate'] === '') {
        $error = 'First name, surname, and birthday are required.';
    } elseif (!$salary_ok) {
        $error = 'Monthly salary must be a number of 0 or more.';
    } elseif (!in_array($data['sex'], ['Male', 'Female'], true)) {
        $error = 'Please select a sex.';
    } elseif ($data['civil_status'] !== null && !in_array($data['civil_status'], $CIVIL_STATUS, true)) {
        $error = 'Invalid civil status.';
    } elseif ($data['blood_type'] !== null && !in_array($data['blood_type'], $BLOOD_TYPES, true)) {
        $error = 'Invalid blood type.';
    } elseif (!in_array($data['applicant_type'], ['New', 'Existing'], true)
        || !in_array($data['employment_status'], $STATUSES, true)) {
        $error = 'Invalid category selection.';
    } elseif ($data['email'] !== null && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    }

    if ($error === null) {
        try {
            $photo   = save_photo($_FILES['photo'] ?? []);
            $columns = array_keys($data);

            if ($is_edit) {
                if ($photo !== null && $employee['photo_path'] && is_file(ROOT_PATH . '/' . $employee['photo_path'])) {
                    unlink(ROOT_PATH . '/' . $employee['photo_path']);
                }
                if ($photo !== null) {
                    $columns[] = 'photo_path';
                }
                $set    = implode(' = ?, ', $columns) . ' = ?';
                $params = array_values($data);
                if ($photo !== null) {
                    $params[] = $photo;
                }
                $params[] = (int)$employee['employee_id'];

                $pdo->prepare("UPDATE employees SET $set WHERE employee_id = ?")->execute($params);
                $id = (int)$employee['employee_id'];
                flash_set('success', 'Employee profile updated.');
            } else {
                $columns[] = 'photo_path';
                $params    = array_values($data);
                $params[]  = $photo;
                $holders   = implode(',', array_fill(0, count($columns), '?'));

                $pdo->prepare('INSERT INTO employees (' . implode(',', $columns) . ") VALUES ($holders)")
                    ->execute($params);
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
    // Re-fill the form with what was submitted when validation failed
    $employee = array_merge($employee ?? [], $data, [
        'photo_path'     => $employee['photo_path'] ?? null,
        'monthly_salary' => $salary_raw,   // keep the figure as typed, so it can be corrected
    ]);
}

$departments = $pdo->query('SELECT department_id, department_name FROM departments ORDER BY department_name')->fetchAll();

$page_title = $is_edit ? 'Edit Employee' : 'Add Employee';
require __DIR__ . '/../../includes/header.php';

$v   = fn(string $key) => e((string)($employee[$key] ?? ''));
$sel = fn(string $key, string $val) => ($employee[$key] ?? '') === $val ? 'selected' : '';
$chk = fn(string $key) => !empty($employee[$key]) ? 'checked' : '';

// Server-side value for the read-only daily salary box; the same figure the
// browser recomputes as the monthly salary is typed.
$emp_daily = daily_salary($employee['monthly_salary'] ?? null);
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

        <!-- ================= Personal Information ================= -->
        <h2 class="h6 text-danger border-bottom pb-2">Personal Information</h2>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label">First Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="first_name" maxlength="60" required data-uppercase value="<?= $v('first_name') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Middle Name</label>
                <input type="text" class="form-control" name="middle_name" maxlength="60" data-uppercase value="<?= $v('middle_name') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Surname <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="last_name" maxlength="60" required data-uppercase value="<?= $v('last_name') ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Birthday <span class="text-danger">*</span></label>
                <input type="date" class="form-control" name="birthdate" required value="<?= $v('birthdate') ?>">
            </div>
            <div class="col-md-5">
                <label class="form-label">Birthplace</label>
                <input type="text" class="form-control" name="birthplace" maxlength="150" data-uppercase value="<?= $v('birthplace') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Sex <span class="text-danger">*</span></label>
                <select class="form-select" name="sex" required>
                    <option value="">Select…</option>
                    <option <?= $sel('sex', 'Male') ?>>Male</option>
                    <option <?= $sel('sex', 'Female') ?>>Female</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Civil Status</label>
                <select class="form-select" name="civil_status">
                    <option value="">Select…</option>
                    <?php foreach ($CIVIL_STATUS as $cs): ?>
                        <option <?= $sel('civil_status', $cs) ?>><?= $cs ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">CP Number</label>
                <input type="text" class="form-control" name="contact_no" maxlength="20"
                       placeholder="09XX XXX XXXX" value="<?= $v('contact_no') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" name="email" maxlength="100" value="<?= $v('email') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Blood Type</label>
                <select class="form-select" name="blood_type">
                    <option value="">Select…</option>
                    <?php foreach ($BLOOD_TYPES as $bt): ?>
                        <option <?= $sel('blood_type', $bt) ?>><?= $bt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Height (cm)</label>
                <input type="number" step="0.01" min="0" max="300" class="form-control" name="height_cm" value="<?= $v('height_cm') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Weight (kg)</label>
                <input type="number" step="0.01" min="0" max="500" class="form-control" name="weight_kg" value="<?= $v('weight_kg') ?>">
            </div>

            <div class="col-12">
                <label class="form-label">Address</label>
                <input type="text" class="form-control" name="address" maxlength="255" data-uppercase value="<?= $v('address') ?>">
            </div>
        </div>

        <!-- ================= Government ID Numbers ================= -->
        <h2 class="h6 text-danger border-bottom pb-2">Government ID Numbers</h2>
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label">SSS No.</label>
                <input type="text" class="form-control" name="sss_no" maxlength="20" value="<?= $v('sss_no') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">PhilHealth No.</label>
                <input type="text" class="form-control" name="philhealth_no" maxlength="20" value="<?= $v('philhealth_no') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Pag-IBIG No.</label>
                <input type="text" class="form-control" name="pagibig_no" maxlength="20" value="<?= $v('pagibig_no') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">TIN No.</label>
                <input type="text" class="form-control" name="tin_no" maxlength="20" value="<?= $v('tin_no') ?>">
            </div>
            <div class="col-12">
                <div class="form-text">
                    These are the ID numbers themselves. Whether the employee has submitted a copy of each
                    document is tracked in the Requirements checklist.
                </div>
            </div>
        </div>

        <!-- ================= Status and Eligibility ================= -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <h2 class="h6 text-danger border-bottom pb-2">Personal Status</h2>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="is_solo_parent" name="is_solo_parent" <?= $chk('is_solo_parent') ?>>
                    <label class="form-check-label" for="is_solo_parent">Solo Parent</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="is_ip" name="is_ip" <?= $chk('is_ip') ?>>
                    <label class="form-check-label" for="is_ip">IP (Indigenous People)</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="is_pwd" name="is_pwd" <?= $chk('is_pwd') ?>>
                    <label class="form-check-label" for="is_pwd">PWD (Person with Disability)</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="is_smoker" name="is_smoker" <?= $chk('is_smoker') ?>>
                    <label class="form-check-label" for="is_smoker">Smoker</label>
                </div>
            </div>
            <div class="col-md-6">
                <h2 class="h6 text-danger border-bottom pb-2">Eligibility</h2>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="elig_professional" name="elig_professional" <?= $chk('elig_professional') ?>>
                    <label class="form-check-label" for="elig_professional">Professional (Prof.)</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="elig_sub_professional" name="elig_sub_professional" <?= $chk('elig_sub_professional') ?>>
                    <label class="form-check-label" for="elig_sub_professional">Sub-Professional (Sub-Prof.)</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="elig_ra1080" name="elig_ra1080" <?= $chk('elig_ra1080') ?>>
                    <label class="form-check-label" for="elig_ra1080">RA 1080</label>
                </div>
            </div>
        </div>

        <!-- ================= Emergency Contact ================= -->
        <h2 class="h6 text-danger border-bottom pb-2">Emergency Contact</h2>
        <div class="row g-3 mb-4">
            <div class="col-md-7">
                <label class="form-label">In Case of Emergency, Please Contact</label>
                <input type="text" class="form-control" name="emergency_contact_name" maxlength="150"
                       placeholder="Full name" data-uppercase value="<?= $v('emergency_contact_name') ?>">
            </div>
            <div class="col-md-5">
                <label class="form-label">Contact Number</label>
                <input type="text" class="form-control" name="emergency_contact_no" maxlength="20"
                       placeholder="09XX XXX XXXX" value="<?= $v('emergency_contact_no') ?>">
            </div>
        </div>

        <!-- ================= Employment Information ================= -->
        <h2 class="h6 text-danger border-bottom pb-2">Employment Information</h2>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Employee No.</label>
                <input type="text" class="form-control" name="employee_no" maxlength="20"
                       placeholder="Leave blank for applicants" data-uppercase value="<?= $v('employee_no') ?>">
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
                <input type="text" class="form-control" name="position" maxlength="100" data-uppercase value="<?= $v('position') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Date Hired</label>
                <input type="date" class="form-control" name="date_hired" value="<?= $v('date_hired') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Applicant Type <span class="text-danger">*</span></label>
                <select class="form-select" name="applicant_type" required>
                    <option <?= ($employee['applicant_type'] ?? 'New') === 'New' ? 'selected' : '' ?>>New</option>
                    <option <?= $sel('applicant_type', 'Existing') ?>>Existing</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Employment Status <span class="text-danger">*</span></label>
                <select class="form-select" name="employment_status" required>
                    <?php foreach ($STATUSES as $s): ?>
                        <option <?= ($employee['employment_status'] ?? 'Applicant') === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="monthly_salary">Monthly Salary</label>
                <div class="input-group">
                    <span class="input-group-text">₱</span>
                    <input type="number" step="0.01" min="0" max="9999999999.99" class="form-control"
                           id="monthly_salary" name="monthly_salary" placeholder="0.00" value="<?= $v('monthly_salary') ?>">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="daily_salary">Daily Salary</label>
                <div class="input-group">
                    <span class="input-group-text">₱</span>
                    <input type="text" class="form-control bg-light" id="daily_salary" readonly tabindex="-1"
                           data-working-days="<?= WORKING_DAYS_PER_MONTH ?>"
                           value="<?= $emp_daily !== null ? e(number_format($emp_daily, 2)) : '' ?>">
                </div>
                <div class="form-text">Computed automatically: monthly ÷ <?= WORKING_DAYS_PER_MONTH ?> working days.</div>
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
