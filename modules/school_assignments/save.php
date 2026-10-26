<?php
// modules/school_assignments/save.php
// Handles add and update of school assignments (POST only).

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/school_assignments/index.php');
}
csrf_check();

$id = isset($_POST['school_assignment_id']) ? (int)$_POST['school_assignment_id'] : 0;
// Upper case, with inner spaces collapsed, so "TAMPAKAN  CES" and
// "TAMPAKAN CES" count as the same school.
$name        = (string)upper_text(preg_replace('/\s+/', ' ', (string)($_POST['school_name'] ?? '')));
$description = trim((string)($_POST['description'] ?? ''));

if ($name === '') {
    flash_set('danger', 'School / workplace name is required.');
    redirect('/modules/school_assignments/index.php' . ($id > 0 ? "?edit=$id" : ''));
}

try {
    if ($id > 0) {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE school_assignments SET school_name = ?, description = ? WHERE school_assignment_id = ?')
            ->execute([$name, $description !== '' ? $description : null, $id]);
        // Employee records carry the name for reports; keep it in step.
        $pdo->prepare('UPDATE employees SET school_assignment = ? WHERE school_assignment_id = ?')
            ->execute([$name, $id]);
        $pdo->commit();
        flash_set('success', "School assignment \"$name\" updated.");
    } else {
        $pdo->prepare('INSERT INTO school_assignments (school_name, description) VALUES (?, ?)')
            ->execute([$name, $description !== '' ? $description : null]);
        flash_set('success', "School assignment \"$name\" added.");
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($e->getCode() === '23000') {
        flash_set('danger', "A school assignment named \"$name\" already exists.");
        redirect('/modules/school_assignments/index.php' . ($id > 0 ? "?edit=$id" : ''));
    }
    throw $e;
}

redirect('/modules/school_assignments/index.php');
