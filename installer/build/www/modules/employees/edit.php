<?php
// modules/employees/edit.php
// Edit an existing employee profile.

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

$stmt = $pdo->prepare('SELECT * FROM employees WHERE employee_id = ?');
$stmt->execute([(int)($_GET['id'] ?? 0)]);
$employee = $stmt->fetch();

if (!$employee) {
    flash_set('danger', 'Employee not found.');
    redirect('/modules/employees/index.php');
}

require __DIR__ . '/form.php';
