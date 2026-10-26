<?php
// modules/school_assignments/delete.php
// Deletes a school assignment (POST only). Employees posted there are left
// without one: the FK rule ON DELETE SET NULL clears school_assignment_id,
// and the stored name is cleared here so it does not linger in reports.

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/school_assignments/index.php');
}
csrf_check();

$id = isset($_POST['school_assignment_id']) ? (int)$_POST['school_assignment_id'] : 0;

$pdo->beginTransaction();
$pdo->prepare('UPDATE employees SET school_assignment = NULL WHERE school_assignment_id = ?')->execute([$id]);
$stmt = $pdo->prepare('DELETE FROM school_assignments WHERE school_assignment_id = ?');
$stmt->execute([$id]);
$pdo->commit();

if ($stmt->rowCount() > 0) {
    flash_set('success', 'School assignment deleted.');
} else {
    flash_set('danger', 'School assignment not found.');
}

redirect('/modules/school_assignments/index.php');
