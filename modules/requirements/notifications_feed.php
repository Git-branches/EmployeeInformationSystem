<?php
// modules/requirements/notifications_feed.php
// JSON feed of unread notifications for the live navbar bell.

require_once __DIR__ . '/../../config/app.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['count' => 0, 'items' => []]);
    exit;
}

generate_notifications($pdo);
[$count, $latest] = get_unread_notifications($pdo);

echo json_encode([
    'count' => $count,
    'items' => array_map(fn($n) => [
        'employee_id' => (int)$n['employee_id'],
        'message'     => $n['message'],
        'time'        => date('M j, Y g:i A', strtotime($n['created_at'])),
    ], $latest),
]);
