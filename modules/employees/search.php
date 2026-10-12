<?php
// modules/employees/search.php
// Rows for the employee list as JSON, so the list updates while the
// administrator types instead of waiting for Enter or the search button.
// Takes the same q / department / type / status parameters as index.php.

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require __DIR__ . '/list_query.php';

ob_start();
require __DIR__ . '/list_rows.php';
$rows = (string)ob_get_clean();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(['rows' => $rows, 'count' => count($employees)]);
