<?php
// modules/requirements/notifications_read.php
// Marks all notifications as read. AJAX (?ajax=1) returns JSON;
// plain requests redirect back to the previous page.

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

$pdo->exec('UPDATE notifications SET is_read = 1 WHERE is_read = 0');

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

$back = $_SERVER['HTTP_REFERER'] ?? (BASE_URL . '/modules/dashboard/index.php');
header('Location: ' . $back);
exit;
