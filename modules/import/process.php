<?php
// modules/import/process.php
// Commits the previewed rows: inserts new employees, creates their
// requirements checklists, and writes the import log.

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['import'])) {
    redirect('/modules/import/index.php');
}
csrf_check();

$import = $_SESSION['import'];
unset($_SESSION['import']);

$rows = $import['rows'];

$dept_lookup = $pdo->prepare('SELECT department_id FROM departments WHERE LOWER(department_name) = LOWER(?)');
$dept_insert = $pdo->prepare('INSERT INTO departments (department_name) VALUES (?)');
$pos_lookup  = $pdo->prepare('SELECT position_id, position_name FROM positions WHERE LOWER(position_name) = LOWER(?)');
$pos_insert  = $pdo->prepare('INSERT INTO positions (position_name) VALUES (?)');

// Columns filled from an imported row, in the order used below.
$import_columns = [
    'employee_no', 'first_name', 'middle_name', 'last_name', 'birthdate', 'birthplace',
    'sex', 'civil_status', 'blood_type', 'height_cm', 'weight_kg',
    'contact_no', 'email', 'address',
    'sss_no', 'philhealth_no', 'pagibig_no', 'tin_no',
    'is_solo_parent', 'is_ip', 'is_pwd', 'is_smoker',
    'elig_professional', 'elig_sub_professional', 'elig_ra1080',
    'emergency_contact_name', 'emergency_contact_no',
    'department_id', 'position_id', 'position', 'applicant_type', 'employment_status', 'date_hired',
    'monthly_salary',
];
$emp_insert = $pdo->prepare(
    'INSERT INTO employees (' . implode(',', $import_columns) . ') VALUES ('
    . implode(',', array_fill(0, count($import_columns), '?')) . ')'
);

/** Blank strings become NULL; anything else is trimmed. */
$val = fn(array $row, string $key) => trim((string)($row[$key] ?? '')) ?: null;

$imported = 0;
$skipped  = 0;

$pdo->beginTransaction();
try {
    foreach ($rows as $row) {
        if ($row['_verdict'] !== 'new') {
            $skipped++;
            continue;
        }

        // Resolve (or create) the department by name
        $department_id = null;
        $dept_name = trim((string)($row['department'] ?? ''));
        if ($dept_name !== '') {
            $dept_lookup->execute([$dept_name]);
            $found = $dept_lookup->fetch();
            if ($found) {
                $department_id = (int)$found['department_id'];
            } else {
                $dept_insert->execute([$dept_name]);
                $department_id = (int)$pdo->lastInsertId();
            }
        }

        // Resolve (or create) the position by name, like the department
        $position_id = null;
        $position    = upper_text(preg_replace('/\s+/', ' ', (string)($row['position'] ?? '')));
        if ($position !== null) {
            $pos_lookup->execute([$position]);
            $found = $pos_lookup->fetch();
            if ($found) {
                $position_id = (int)$found['position_id'];
                $position    = $found['position_name'];
            } else {
                $pos_insert->execute([$position]);
                $position_id = (int)$pdo->lastInsertId();
            }
        }

        // Mobile numbers in the standard 0994-800-7500 form; anything else as given
        $contact_no = $val($row, 'contact_no');
        $contact_no = format_ph_mobile($contact_no) ?? $contact_no;

        $emp_insert->execute([
            $row['employee_no'],
            $row['first_name'],
            $val($row, 'middle_name'),
            $row['last_name'],
            $row['birthdate'],
            $val($row, 'birthplace'),
            $row['sex'],
            $row['civil_status'] ?? null,
            $row['blood_type'] ?? null,
            $row['height_cm'] ?? null,
            $row['weight_kg'] ?? null,
            $contact_no,
            $val($row, 'email'),
            $val($row, 'address'),
            $val($row, 'sss_no'),
            $val($row, 'philhealth_no'),
            $val($row, 'pagibig_no'),
            $val($row, 'tin_no'),
            (int)($row['is_solo_parent'] ?? 0),
            (int)($row['is_ip'] ?? 0),
            (int)($row['is_pwd'] ?? 0),
            (int)($row['is_smoker'] ?? 0),
            (int)($row['elig_professional'] ?? 0),
            (int)($row['elig_sub_professional'] ?? 0),
            (int)($row['elig_ra1080'] ?? 0),
            $val($row, 'emergency_contact_name'),
            $val($row, 'emergency_contact_no'),
            $department_id,
            $position_id,
            $position,
            $row['applicant_type'],
            $row['employment_status'],
            $row['date_hired'],
            $row['monthly_salary'],
        ]);
        sync_requirements($pdo, (int)$pdo->lastInsertId());
        $imported++;
    }

    $pdo->prepare(
        'INSERT INTO import_logs (file_name, file_type, records_imported, duplicates_skipped, imported_by)
         VALUES (?,?,?,?,?)'
    )->execute([$import['file_name'], $import['file_type'], $imported, $skipped, $_SESSION['user_id']]);

    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    flash_set('danger', 'Import failed — nothing was saved. (' . $ex->getMessage() . ')');
    redirect('/modules/import/index.php');
}

generate_notifications($pdo);

flash_set('success', "Import complete: $imported record(s) added, $skipped skipped.");
redirect('/modules/employees/index.php');
