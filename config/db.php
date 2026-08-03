<?php
// config/db.php
// PDO connection to the MySQL/MariaDB database.
//
// Defaults suit a normal Laragon/XAMPP setup. The installer writes
// config/local.php to point the deployed copy at its bundled MariaDB
// (port 3307), so this file never has to be edited by hand.

declare(strict_types=1);

$eis_db = [
    'host' => '127.0.0.1',
    'port' => '3306',
    'name' => 'employee_information_system',
    'user' => 'root',
    'pass' => '',
];

if (is_file(__DIR__ . '/local.php')) {
    $override = require __DIR__ . '/local.php';
    if (is_array($override)) {
        $eis_db = array_merge($eis_db, $override);
    }
}

define('DB_HOST', $eis_db['host']);
define('DB_PORT', (string)$eis_db['port']);
define('DB_NAME', $eis_db['name']);
define('DB_USER', $eis_db['user']);
define('DB_PASS', $eis_db['pass']);

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed. Please make sure the database service is running.');
}
