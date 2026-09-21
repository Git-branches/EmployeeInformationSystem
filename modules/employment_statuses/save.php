<?php
// modules/employment_statuses/save.php
// Handles add and update of employment statuses (POST only).

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/employment_statuses/index.php');
}
csrf_check();

$id          = isset($_POST['employment_status_id']) ? (int)$_POST['employment_status_id'] : 0;
// Inner spaces collapsed so "Job  Order" and "Job Order" count as the same status.
$name        = trim(preg_replace('/\s+/', ' ', (string)($_POST['status_name'] ?? '')));
$description = trim((string)($_POST['description'] ?? ''));

if ($name === '') {
    flash_set('danger', 'Status name is required.');
    redirect('/modules/employment_statuses/index.php' . ($id > 0 ? "?edit=$id" : ''));
}

try {
    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE employment_statuses SET status_name = ?, description = ? WHERE employment_status_id = ?');
        $stmt->execute([$name, $description !== '' ? $description : null, $id]);
        flash_set('success', "Employment status \"$name\" updated.");
    } else {
        $stmt = $pdo->prepare('INSERT INTO employment_statuses (status_name, description) VALUES (?, ?)');
        $stmt->execute([$name, $description !== '' ? $description : null]);
        flash_set('success', "Employment status \"$name\" added.");
    }
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        flash_set('danger', "An employment status named \"$name\" already exists.");
        redirect('/modules/employment_statuses/index.php' . ($id > 0 ? "?edit=$id" : ''));
    }
    throw $e;
}

redirect('/modules/employment_statuses/index.php');
