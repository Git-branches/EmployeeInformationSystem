<?php
// modules/employees/delete.php
// Deletes an employee (POST only). Requirements and notifications rows
// are removed automatically by the FK ON DELETE CASCADE rules.

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/employees/index.php');
}
csrf_check();

$id = (int)($_POST['employee_id'] ?? 0);

$stmt = $pdo->prepare('SELECT photo_path FROM employees WHERE employee_id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();

if ($row) {
    // Collect uploaded requirement documents before the rows cascade away,
    // so no orphaned files are left behind in uploads/requirements.
    $docs = $pdo->prepare('SELECT file_path FROM employee_requirements WHERE employee_id = ? AND file_path IS NOT NULL');
    $docs->execute([$id]);
    $files = $docs->fetchAll(PDO::FETCH_COLUMN);

    $pdo->prepare('DELETE FROM employees WHERE employee_id = ?')->execute([$id]);

    if ($row['photo_path']) {
        $files[] = $row['photo_path'];
    }
    foreach ($files as $file) {
        if (is_file(ROOT_PATH . '/' . $file)) {
            unlink(ROOT_PATH . '/' . $file);
        }
    }
    flash_set('success', 'Employee record deleted.');
} else {
    flash_set('danger', 'Employee not found.');
}

redirect('/modules/employees/index.php');
