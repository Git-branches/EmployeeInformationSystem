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
$errors  = [];   // field name => message, shown under that field
$error   = null; // message not tied to one field (upload, database)

$CIVIL_STATUS = ['Single', 'Married', 'Widowed', 'Separated', 'Annulled'];
$BLOOD_TYPES  = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
$STATUSES     = ['Applicant', 'Active', 'Inactive', 'Terminated'];
$EXTENSIONS   = ['JR.', 'SR.', 'II', 'III', 'IV', 'V'];

$departments = $pdo->query('SELECT department_id, department_name FROM departments ORDER BY department_name')->fetchAll();
$positions   = $pdo->query('SELECT position_id, position_name FROM positions ORDER BY position_name')->fetchAll();
$emp_statuses = $pdo->query('SELECT employment_status_id, status_name FROM employment_statuses ORDER BY status_name')->fetchAll();

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
    // The field shows thousands separators (1,000.09); only the number is stored.
    $salary_raw = str_replace([',', ' ', '₱'], '', trim((string)($_POST['monthly_salary'] ?? '')));
    $salary_ok  = $salary_raw === ''
        || (preg_match('/^\d+(\.\d{1,2})?$/', $salary_raw) && (float)$salary_raw <= 9999999999.99);

    // Mobile number, stored as 0994-800-7500. A number already on file in some
    // other form (e.g. a landline) is kept as long as it is left unchanged.
    $contact_raw = trim((string)($_POST['contact_no'] ?? ''));
    $contact     = format_ph_mobile($contact_raw);
    $contact_ok  = $contact_raw === '' || $contact !== null
        || ($is_edit && $contact_raw === (string)($employee['contact_no'] ?? ''));
    if ($contact === null && $contact_ok && $contact_raw !== '') {
        $contact = $contact_raw;
    }

    // Address: only the purok/street is typed; the rest come from the dropdowns
    $street       = $text('address_street');
    $province     = $text('address_province');
    $municipality = $text('address_municipality');
    $barangay     = $text('address_barangay');

    $no_middle = $flag('no_middle_name');
    $position_id = (int)($_POST['position_id'] ?? 0) ?: null;
    $status_id   = (int)($_POST['employment_status_id'] ?? 0) ?: null;
    $position_names = array_column($positions, 'position_name', 'position_id');
    $status_names   = array_column($emp_statuses, 'status_name', 'employment_status_id');

    $data = [
        // ---- Personal information
        'employee_no'   => $text('employee_no'),
        'first_name'    => trim((string)($_POST['first_name'] ?? '')),
        'middle_name'   => $no_middle ? null : $text('middle_name'),
        'no_middle_name' => $no_middle,
        'last_name'     => trim((string)($_POST['last_name'] ?? '')),
        'name_extension' => $text('name_extension'),
        'birthdate'     => (string)($_POST['birthdate'] ?? ''),
        'birthplace'    => $text('birthplace'),
        'sex'           => (string)($_POST['sex'] ?? ''),
        'civil_status'  => $text('civil_status'),
        'blood_type'    => $text('blood_type'),
        'height_cm'     => $num('height_cm'),
        'weight_kg'     => $num('weight_kg'),
        'contact_no'    => $contact_ok ? $contact : null,
        'email'         => $text('email'),
        'address_street'       => $street,
        'address_barangay'     => $barangay,
        'address_municipality' => $municipality,
        'address_province'     => $province,
        // Full address line kept for reports; an address recorded before the
        // parts existed is left as it is until the parts are filled in.
        'address'       => ($street || $barangay || $municipality || $province)
            ? compose_address($street, $barangay, $municipality, $province)
            : ($employee['address'] ?? null),

        // ---- Government ID numbers
        'sss_no'        => $text('sss_no'),
        'philhealth_no' => $text('philhealth_no'),
        'pagibig_no'    => $text('pagibig_no'),
        'tin_no'        => $text('tin_no'),
        'gsis_no'       => $text('gsis_no'),

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
        // position keeps the position's name alongside the link, for reports.
        'department_id'        => (int)($_POST['department_id'] ?? 0) ?: null,
        'position_id'          => $position_id,
        'position'             => $position_id !== null ? ($position_names[$position_id] ?? null) : null,
        'applicant_type'       => (string)($_POST['applicant_type'] ?? 'New'),
        'employment_status'    => (string)($_POST['employment_status'] ?? 'Applicant'),
        'employment_status_id' => $status_id,
        'date_hired'           => (string)($_POST['date_hired'] ?? '') ?: null,

        // ---- Compensation
        // Only the monthly salary is stored; the daily salary is always
        // derived from it with daily_salary().
        'monthly_salary'    => ($salary_ok && $salary_raw !== '') ? round((float)$salary_raw, 2) : null,
    ];

    // Names, addresses and other free-text entries are recorded in upper case.
    $data = upper_employee_fields($data);
    $data['name_extension'] = normalize_name_extension($data['name_extension']);

    if ($data['last_name'] === null || $data['last_name'] === '') {
        $errors['last_name'] = 'Surname is required.';
    }
    if ($data['first_name'] === null || $data['first_name'] === '') {
        $errors['first_name'] = 'First name is required.';
    }
    if (!$no_middle && $data['middle_name'] === null) {
        $errors['middle_name'] = 'Enter the middle name, or tick "No middle name".';
    }
    if ($data['birthdate'] === '') {
        $errors['birthdate'] = 'Birthday is required.';
    }
    if (!in_array($data['sex'], ['Male', 'Female'], true)) {
        $errors['sex'] = 'Please select a sex.';
    }
    if ($data['civil_status'] !== null && !in_array($data['civil_status'], $CIVIL_STATUS, true)) {
        $errors['civil_status'] = 'Invalid civil status.';
    }
    if ($data['blood_type'] !== null && !in_array($data['blood_type'], $BLOOD_TYPES, true)) {
        $errors['blood_type'] = 'Invalid blood type.';
    }
    if (!$contact_ok) {
        $errors['contact_no'] = 'Enter an 11-digit mobile number starting with 09, e.g. 0994-800-7500.';
    }
    if ($data['email'] !== null && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if ($province !== null && ph_municipalities($province) === []) {
        $errors['address_province'] = 'Please select a province from the list.';
    } elseif ($municipality !== null && !in_array($municipality, ph_municipalities((string)$province), true)) {
        $errors['address_municipality'] = 'Please select a city/municipality of the chosen province.';
    } elseif ($barangay !== null && !in_array($barangay, ph_barangays((string)$province, (string)$municipality), true)) {
        $errors['address_barangay'] = 'Please select a barangay of the chosen city/municipality.';
    }
    if ($position_id !== null && !isset($position_names[$position_id])) {
        $errors['position_id'] = 'That position no longer exists. Please choose another.';
    }
    if ($status_id !== null && !isset($status_names[$status_id])) {
        $errors['employment_status_id'] = 'That employment status no longer exists. Please choose another.';
    }
    if (!in_array($data['applicant_type'], ['New', 'Existing'], true)) {
        $errors['applicant_type'] = 'Invalid applicant type.';
    }
    if (!in_array($data['employment_status'], $STATUSES, true)) {
        $errors['employment_status'] = 'Invalid record status.';
    }
    if (!$salary_ok) {
        $errors['monthly_salary'] = 'Monthly salary must be a number of 0 or more, with up to two decimals.';
    }

    // The same person entered twice (same name and birthday)
    if (!array_intersect_key($errors, array_flip(['first_name', 'last_name', 'birthdate']))) {
        $dup = $pdo->prepare(
            'SELECT employee_id FROM employees
             WHERE first_name = ? AND last_name = ? AND birthdate = ? AND employee_id <> ?'
        );
        $dup->execute([$data['first_name'], $data['last_name'], $data['birthdate'],
                       $is_edit ? (int)$employee['employee_id'] : 0]);
        if ($dup->fetchColumn()) {
            $error = 'An employee with the same name and birthday is already on record.';
        }
    }

    if (!$errors && $error === null) {
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
            $errors['photo'] = $ex->getMessage();
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000' && str_contains($ex->getMessage(), 'employee_no')) {
                $errors['employee_no'] = 'That employee number is already in use.';
            } else {
                $error = 'Database error while saving. Please try again.';
            }
        }
    }
    if ($errors && $error === null) {
        $error = 'Please correct the highlighted fields.';
    }
    // Re-fill the form with what was submitted when validation failed
    $employee = array_merge($employee ?? [], $data, [
        'photo_path'     => $employee['photo_path'] ?? null,
        'contact_no'     => $contact_raw,  // keep what was typed, so it can be corrected
        'monthly_salary' => $salary_raw,
    ]);
}

$page_title = $is_edit ? 'Edit Employee' : 'Add Employee';
require __DIR__ . '/../../includes/header.php';

$v   = fn(string $key) => e((string)($employee[$key] ?? ''));
$sel = fn(string $key, string $val) => ($employee[$key] ?? '') === $val ? 'selected' : '';
$chk = fn(string $key) => !empty($employee[$key]) ? 'checked' : '';
/** Bootstrap invalid state + message for a field that failed validation. */
$bad = fn(string $key) => isset($errors[$key]) ? ' is-invalid' : '';
$msg = fn(string $key) => isset($errors[$key]) ? '<div class="invalid-feedback">' . e($errors[$key]) . '</div>' : '';

// Contact number as shown: standard format when it is a valid mobile number
$contact_shown = format_ph_mobile($employee['contact_no'] ?? null) ?? (string)($employee['contact_no'] ?? '');

// Salary with thousands separators; a figure that failed validation is shown as typed
$salary = $employee['monthly_salary'] ?? '';
$salary_shown = is_numeric($salary) && !isset($errors['monthly_salary'])
    ? number_format((float)$salary, 2) : (string)$salary;

// A position typed on a record before the Positions list existed and not yet
// linked to it: preselect the list entry with the same name, if there is one.
$legacy_position = '';
if (empty($employee['position_id']) && !empty($employee['position'])) {
    foreach ($positions as $p) {
        if (mb_strtoupper($p['position_name']) === mb_strtoupper((string)$employee['position'])) {
            $employee['position_id'] = $p['position_id'];
        }
    }
    if (empty($employee['position_id'])) {
        $legacy_position = (string)$employee['position'];
    }
}

// Address dropdown options for the current selection; the browser reloads the
// lower lists (locations.php) whenever a higher one changes.
$addr_province     = (string)($employee['address_province'] ?? '');
$addr_municipality = (string)($employee['address_municipality'] ?? '');
$addr_provinces      = ph_locations()['provinces'];
$addr_municipalities = $addr_province !== '' ? ph_municipalities($addr_province) : [];
$addr_barangays      = $addr_municipality !== '' ? ph_barangays($addr_province, $addr_municipality) : [];
$legacy_address = empty($employee['address_street']) && empty($employee['address_barangay'])
    && empty($employee['address_municipality']) && empty($employee['address_province'])
    ? (string)($employee['address'] ?? '') : '';

// Server-side value for the read-only daily salary box; the same figure the
// browser recomputes as the monthly salary is typed.
$emp_daily = daily_salary(is_numeric($salary) ? $salary : null);
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
        <p class="small text-muted mb-3">Fields marked <span class="text-danger">*</span> are required.</p>

        <!-- ================= Personal Information ================= -->
        <h2 class="h6 text-danger border-bottom pb-2">Personal Information</h2>
        <div class="row g-3 mb-4">
            <div class="col-md-6 col-lg-3">
                <label class="form-label" for="last_name">Surname <span class="text-danger">*</span></label>
                <input type="text" class="form-control<?= $bad('last_name') ?>" id="last_name" name="last_name" maxlength="60" required data-uppercase value="<?= $v('last_name') ?>">
                <?= $msg('last_name') ?>
            </div>
            <div class="col-md-6 col-lg-3">
                <label class="form-label" for="first_name">First Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control<?= $bad('first_name') ?>" id="first_name" name="first_name" maxlength="60" required data-uppercase value="<?= $v('first_name') ?>">
                <?= $msg('first_name') ?>
            </div>
            <div class="col-md-6 col-lg-4">
                <label class="form-label" for="middle_name">Middle Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control<?= $bad('middle_name') ?>" id="middle_name" name="middle_name" maxlength="60" data-uppercase
                       value="<?= $v('middle_name') ?>" <?= !empty($employee['no_middle_name']) ? 'disabled' : '' ?>>
                <?= $msg('middle_name') ?>
                <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" value="1" id="no_middle_name" name="no_middle_name" <?= $chk('no_middle_name') ?>>
                    <label class="form-check-label small" for="no_middle_name">No middle name</label>
                </div>
            </div>
            <div class="col-md-6 col-lg-2">
                <label class="form-label" for="name_extension">Extension</label>
                <input type="text" class="form-control" id="name_extension" name="name_extension" maxlength="10"
                       list="name-extensions" placeholder="Jr., III" data-uppercase value="<?= $v('name_extension') ?>">
                <datalist id="name-extensions">
                    <?php foreach ($EXTENSIONS as $ext): ?>
                        <option value="<?= $ext ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="col-12 mt-2">
                <div class="form-text">
                    Displayed as: <strong id="name-preview"><?= e(employee_display_name($employee ?? [])) ?: '—' ?></strong>
                </div>
            </div>

            <div class="col-md-3">
                <label class="form-label" for="birthdate">Birthday <span class="text-danger">*</span></label>
                <input type="date" class="form-control<?= $bad('birthdate') ?>" id="birthdate" name="birthdate" required value="<?= $v('birthdate') ?>">
                <?= $msg('birthdate') ?>
            </div>
            <div class="col-md-5">
                <label class="form-label">Birthplace</label>
                <input type="text" class="form-control" name="birthplace" maxlength="150" data-uppercase value="<?= $v('birthplace') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="sex">Sex <span class="text-danger">*</span></label>
                <select class="form-select<?= $bad('sex') ?>" id="sex" name="sex" required>
                    <option value="">Select…</option>
                    <option <?= $sel('sex', 'Male') ?>>Male</option>
                    <option <?= $sel('sex', 'Female') ?>>Female</option>
                </select>
                <?= $msg('sex') ?>
            </div>
            <div class="col-md-2">
                <label class="form-label">Civil Status</label>
                <select class="form-select<?= $bad('civil_status') ?>" name="civil_status">
                    <option value="">Select…</option>
                    <?php foreach ($CIVIL_STATUS as $cs): ?>
                        <option <?= $sel('civil_status', $cs) ?>><?= $cs ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $msg('civil_status') ?>
            </div>

            <div class="col-md-3">
                <label class="form-label" for="contact_no">CP Number</label>
                <input type="tel" class="form-control<?= $bad('contact_no') ?>" id="contact_no" name="contact_no" maxlength="20"
                       inputmode="numeric" autocomplete="tel" placeholder="0994-800-7500" data-ph-mobile
                       value="<?= e($contact_shown) ?>">
                <?= $msg('contact_no') ?>
            </div>
            <div class="col-md-3">
                <label class="form-label">Email</label>
                <input type="email" class="form-control<?= $bad('email') ?>" name="email" maxlength="100" value="<?= $v('email') ?>">
                <?= $msg('email') ?>
            </div>
            <div class="col-md-2">
                <label class="form-label">Blood Type</label>
                <select class="form-select<?= $bad('blood_type') ?>" name="blood_type">
                    <option value="">Select…</option>
                    <?php foreach ($BLOOD_TYPES as $bt): ?>
                        <option <?= $sel('blood_type', $bt) ?>><?= $bt ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $msg('blood_type') ?>
            </div>
            <div class="col-md-2">
                <label class="form-label">Height (cm)</label>
                <input type="number" step="0.01" min="0" max="300" class="form-control" name="height_cm" value="<?= $v('height_cm') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Weight (kg)</label>
                <input type="number" step="0.01" min="0" max="500" class="form-control" name="weight_kg" value="<?= $v('weight_kg') ?>">
            </div>
        </div>

        <!-- ================= Address ================= -->
        <h2 class="h6 text-danger border-bottom pb-2">Address</h2>
        <div class="row g-3 mb-4" id="address-fields" data-locations-url="<?= BASE_URL ?>/modules/employees/locations.php">
            <div class="col-md-6 col-lg-3">
                <label class="form-label" for="address_street">Purok / Street</label>
                <input type="text" class="form-control" id="address_street" name="address_street" maxlength="150"
                       placeholder="e.g. Purok 3, Rizal St." data-uppercase value="<?= $v('address_street') ?>">
            </div>
            <div class="col-md-6 col-lg-3">
                <label class="form-label" for="address_province">Province</label>
                <select class="form-select<?= $bad('address_province') ?>" id="address_province" name="address_province">
                    <option value="">Select province…</option>
                    <?php foreach ($addr_provinces as $name): ?>
                        <option <?= $name === $addr_province ? 'selected' : '' ?>><?= e($name) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $msg('address_province') ?>
            </div>
            <div class="col-md-6 col-lg-3">
                <label class="form-label" for="address_municipality">City / Municipality</label>
                <select class="form-select<?= $bad('address_municipality') ?>" id="address_municipality" name="address_municipality"
                        <?= $addr_municipalities ? '' : 'disabled' ?>>
                    <option value=""><?= $addr_municipalities ? 'Select city/municipality…' : 'Select a province first' ?></option>
                    <?php foreach ($addr_municipalities as $name): ?>
                        <option <?= $name === $addr_municipality ? 'selected' : '' ?>><?= e($name) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $msg('address_municipality') ?>
            </div>
            <div class="col-md-6 col-lg-3">
                <label class="form-label" for="address_barangay">Barangay</label>
                <select class="form-select<?= $bad('address_barangay') ?>" id="address_barangay" name="address_barangay"
                        <?= $addr_barangays ? '' : 'disabled' ?>>
                    <option value=""><?= $addr_barangays ? 'Select barangay…' : 'Select a city/municipality first' ?></option>
                    <?php foreach ($addr_barangays as $name): ?>
                        <option <?= $name === ($employee['address_barangay'] ?? '') ? 'selected' : '' ?>><?= e($name) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $msg('address_barangay') ?>
            </div>
            <?php if ($legacy_address !== ''): ?>
                <div class="col-12">
                    <div class="form-text">
                        <i class="bi bi-info-circle me-1"></i>Address on file: <strong><?= e($legacy_address) ?></strong>.
                        It is kept until the fields above are filled in.
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- ================= Government ID Numbers ================= -->
        <h2 class="h6 text-danger border-bottom pb-2">Government ID Numbers</h2>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-5 g-3">
            <div class="col">
                <label class="form-label">SSS No.</label>
                <input type="text" class="form-control" name="sss_no" maxlength="20" value="<?= $v('sss_no') ?>">
            </div>
            <div class="col">
                <label class="form-label">PhilHealth No.</label>
                <input type="text" class="form-control" name="philhealth_no" maxlength="20" value="<?= $v('philhealth_no') ?>">
            </div>
            <div class="col">
                <label class="form-label">Pag-IBIG No.</label>
                <input type="text" class="form-control" name="pagibig_no" maxlength="20" value="<?= $v('pagibig_no') ?>">
            </div>
            <div class="col">
                <label class="form-label">TIN No.</label>
                <input type="text" class="form-control" name="tin_no" maxlength="20" value="<?= $v('tin_no') ?>">
            </div>
            <div class="col">
                <label class="form-label">GSIS No.</label>
                <input type="text" class="form-control" name="gsis_no" maxlength="20" value="<?= $v('gsis_no') ?>">
            </div>
        </div>
        <div class="form-text mt-2 mb-4">
            These are the ID numbers themselves. Whether the employee has submitted a copy of each
            document is tracked in the Requirements checklist.
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
            <div class="col-md-6 col-lg-3">
                <label class="form-label" for="employee_no">Employee No.</label>
                <input type="text" class="form-control<?= $bad('employee_no') ?>" id="employee_no" name="employee_no" maxlength="20"
                       placeholder="Leave blank for applicants" data-uppercase value="<?= $v('employee_no') ?>">
                <?= $msg('employee_no') ?>
            </div>
            <div class="col-md-6 col-lg-3">
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
            <div class="col-md-6 col-lg-3">
                <label class="form-label" for="position_id">Position</label>
                <select class="form-select<?= $bad('position_id') ?>" id="position_id" name="position_id">
                    <option value="">No position</option>
                    <?php foreach ($positions as $p): ?>
                        <option value="<?= (int)$p['position_id'] ?>"
                            <?= (int)($employee['position_id'] ?? 0) === (int)$p['position_id'] ? 'selected' : '' ?>>
                            <?= e($p['position_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= $msg('position_id') ?>
                <?php if ($legacy_position !== ''): ?>
                    <div class="form-text">Recorded as <strong><?= e($legacy_position) ?></strong>, which is not in the Positions list. Choose a position to keep one on file.</div>
                <?php endif; ?>
                <?php if (!$positions): ?>
                    <div class="form-text">No positions yet — <a href="<?= BASE_URL ?>/modules/positions/index.php">add them in Positions</a>.</div>
                <?php endif; ?>
            </div>
            <div class="col-md-6 col-lg-3">
                <label class="form-label">Date Hired</label>
                <input type="date" class="form-control" name="date_hired" value="<?= $v('date_hired') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="employment_status_id">Employment Status</label>
                <select class="form-select<?= $bad('employment_status_id') ?>" id="employment_status_id" name="employment_status_id">
                    <option value="">Not set</option>
                    <?php foreach ($emp_statuses as $st): ?>
                        <option value="<?= (int)$st['employment_status_id'] ?>"
                            <?= (int)($employee['employment_status_id'] ?? 0) === (int)$st['employment_status_id'] ? 'selected' : '' ?>>
                            <?= e($st['status_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= $msg('employment_status_id') ?>
                <?php if (!$emp_statuses): ?>
                    <div class="form-text">No statuses yet — <a href="<?= BASE_URL ?>/modules/employment_statuses/index.php">add them in Employment Status</a>.</div>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="employment_status">Record Status <span class="text-danger">*</span></label>
                <select class="form-select<?= $bad('employment_status') ?>" id="employment_status" name="employment_status" required>
                    <?php foreach ($STATUSES as $s): ?>
                        <option <?= ($employee['employment_status'] ?? 'Applicant') === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $msg('employment_status') ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="applicant_type">Applicant Type <span class="text-danger">*</span></label>
                <select class="form-select<?= $bad('applicant_type') ?>" id="applicant_type" name="applicant_type" required>
                    <option <?= ($employee['applicant_type'] ?? 'New') === 'New' ? 'selected' : '' ?>>New</option>
                    <option <?= $sel('applicant_type', 'Existing') ?>>Existing</option>
                </select>
                <?= $msg('applicant_type') ?>
            </div>
            <div class="col-md-6 col-lg-3">
                <label class="form-label" for="monthly_salary">Monthly Salary</label>
                <div class="input-group<?= isset($errors['monthly_salary']) ? ' has-validation' : '' ?>">
                    <span class="input-group-text">₱</span>
                    <input type="text" inputmode="decimal" maxlength="16" class="form-control<?= $bad('monthly_salary') ?>"
                           id="monthly_salary" name="monthly_salary" placeholder="0.00" data-money value="<?= e($salary_shown) ?>">
                    <?= $msg('monthly_salary') ?>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <label class="form-label" for="daily_salary">Daily Salary</label>
                <div class="input-group">
                    <span class="input-group-text">₱</span>
                    <input type="text" class="form-control bg-light" id="daily_salary" readonly tabindex="-1"
                           data-working-days="<?= WORKING_DAYS_PER_MONTH ?>"
                           value="<?= $emp_daily !== null ? e(number_format($emp_daily, 2)) : '' ?>">
                </div>
                <div class="form-text">Computed automatically: monthly ÷ <?= WORKING_DAYS_PER_MONTH ?> working days.</div>
            </div>

            <div class="col-md-12 col-lg-6">
                <label class="form-label">Photo (JPG/PNG, max 2 MB)</label>
                <input type="file" class="form-control<?= $bad('photo') ?>" name="photo" accept=".jpg,.jpeg,.png">
                <?= $msg('photo') ?>
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
