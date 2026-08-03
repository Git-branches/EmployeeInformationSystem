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
$emp_insert  = $pdo->prepare(
    'INSERT INTO employees (employee_no, first_name, middle_name, last_name, birthdate, sex,
     contact_no, email, address, department_id, position, applicant_type, employment_status, date_hired)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
);

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

        $emp_insert->execute([
            $row['employee_no'],
            $row['first_name'],
            trim((string)($row['middle_name'] ?? '')) ?: null,
            $row['last_name'],
            $row['birthdate'],
            $row['sex'],
            trim((string)($row['contact_no'] ?? '')) ?: null,
            trim((string)($row['email'] ?? '')) ?: null,
            trim((string)($row['address'] ?? '')) ?: null,
            $department_id,
            trim((string)($row['position'] ?? '')) ?: null,
            $row['applicant_type'],
            $row['employment_status'],
            $row['date_hired'],
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
