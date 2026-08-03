<?php
// modules/departments/save.php
// Handles add and update of departments (POST only).

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/departments/index.php');
}
csrf_check();

$id          = isset($_POST['department_id']) ? (int)$_POST['department_id'] : 0;
$name        = trim((string)($_POST['department_name'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));

if ($name === '') {
    flash_set('danger', 'Department name is required.');
    redirect('/modules/departments/index.php');
}

try {
    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE departments SET department_name = ?, description = ? WHERE department_id = ?');
        $stmt->execute([$name, $description !== '' ? $description : null, $id]);
        flash_set('success', "Department \"$name\" updated.");
    } else {
        $stmt = $pdo->prepare('INSERT INTO departments (department_name, description) VALUES (?, ?)');
        $stmt->execute([$name, $description !== '' ? $description : null]);
        flash_set('success', "Department \"$name\" added.");
    }
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        flash_set('danger', "A department named \"$name\" already exists.");
    } else {
        throw $e;
    }
}

redirect('/modules/departments/index.php');
