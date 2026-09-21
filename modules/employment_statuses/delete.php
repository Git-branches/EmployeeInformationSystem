<?php
// modules/employment_statuses/delete.php
// Deletes an employment status (POST only). Employees with it are left
// without one automatically by the FK rule ON DELETE SET NULL.

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/employment_statuses/index.php');
}
csrf_check();

$id = isset($_POST['employment_status_id']) ? (int)$_POST['employment_status_id'] : 0;

$stmt = $pdo->prepare('DELETE FROM employment_statuses WHERE employment_status_id = ?');
$stmt->execute([$id]);

if ($stmt->rowCount() > 0) {
    flash_set('success', 'Employment status deleted.');
} else {
    flash_set('danger', 'Employment status not found.');
}

redirect('/modules/employment_statuses/index.php');
