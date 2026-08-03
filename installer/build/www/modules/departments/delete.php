<?php
// modules/departments/delete.php
// Deletes a department (POST only). Employees under it are set to
// unassigned automatically by the FK rule ON DELETE SET NULL.

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/departments/index.php');
}
csrf_check();

$id = isset($_POST['department_id']) ? (int)$_POST['department_id'] : 0;

$stmt = $pdo->prepare('DELETE FROM departments WHERE department_id = ?');
$stmt->execute([$id]);

if ($stmt->rowCount() > 0) {
    flash_set('success', 'Department deleted.');
} else {
    flash_set('danger', 'Department not found.');
}

redirect('/modules/departments/index.php');
