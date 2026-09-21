<?php
// modules/positions/delete.php
// Deletes a position (POST only). Employees holding it are left without a
// position: the FK rule ON DELETE SET NULL clears position_id, and the
// stored position name is cleared here so it does not linger in reports.

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/positions/index.php');
}
csrf_check();

$id = isset($_POST['position_id']) ? (int)$_POST['position_id'] : 0;

$pdo->beginTransaction();
$pdo->prepare('UPDATE employees SET position = NULL WHERE position_id = ?')->execute([$id]);
$stmt = $pdo->prepare('DELETE FROM positions WHERE position_id = ?');
$stmt->execute([$id]);
$pdo->commit();

if ($stmt->rowCount() > 0) {
    flash_set('success', 'Position deleted.');
} else {
    flash_set('danger', 'Position not found.');
}

redirect('/modules/positions/index.php');
