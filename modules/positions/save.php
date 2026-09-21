<?php
// modules/positions/save.php
// Handles add and update of positions (POST only).

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/positions/index.php');
}
csrf_check();

$id          = isset($_POST['position_id']) ? (int)$_POST['position_id'] : 0;
// Upper case, like positions typed on employee records; inner spaces collapsed
// so "NURSE  I" and "NURSE I" count as the same position.
$name        = (string)upper_text(preg_replace('/\s+/', ' ', (string)($_POST['position_name'] ?? '')));
$description = trim((string)($_POST['description'] ?? ''));

if ($name === '') {
    flash_set('danger', 'Position name is required.');
    redirect('/modules/positions/index.php' . ($id > 0 ? "?edit=$id" : ''));
}

try {
    if ($id > 0) {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE positions SET position_name = ?, description = ? WHERE position_id = ?');
        $stmt->execute([$name, $description !== '' ? $description : null, $id]);
        // Employee records carry the position name for reports; keep it in step.
        $pdo->prepare('UPDATE employees SET position = ? WHERE position_id = ?')->execute([$name, $id]);
        $pdo->commit();
        flash_set('success', "Position \"$name\" updated.");
    } else {
        $stmt = $pdo->prepare('INSERT INTO positions (position_name, description) VALUES (?, ?)');
        $stmt->execute([$name, $description !== '' ? $description : null]);
        flash_set('success', "Position \"$name\" added.");
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($e->getCode() === '23000') {
        flash_set('danger', "A position named \"$name\" already exists.");
        redirect('/modules/positions/index.php' . ($id > 0 ? "?edit=$id" : ''));
    }
    throw $e;
}

redirect('/modules/positions/index.php');
