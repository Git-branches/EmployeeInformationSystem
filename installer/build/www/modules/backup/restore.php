<?php
// modules/backup/restore.php
// Restores the database from a selected local backup file (POST only).

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/backup/index.php');
}
csrf_check();

if (MYSQL_BIN_PATH === '') {
    flash_set('danger', 'Could not locate mysql.exe. Check that MySQL is installed with Laragon or XAMPP.');
    redirect('/modules/backup/index.php');
}

$name = basename((string)($_POST['file_name'] ?? ''));
$path = BACKUP_PATH . '/' . $name;

if (!preg_match('/^eis_backup_[\w.]+\.sql$/', $name) || !is_file($path)) {
    flash_set('danger', 'Backup file not found.');
    redirect('/modules/backup/index.php');
}

$cmd = '"' . MYSQL_BIN_PATH . '\\mysql.exe" --user=' . escapeshellarg(DB_USER)
     . (DB_PASS !== '' ? ' --password=' . escapeshellarg(DB_PASS) : '')
     . ' --host=' . escapeshellarg(DB_HOST) . ' --port=' . escapeshellarg(DB_PORT)
     . ' ' . escapeshellarg(DB_NAME) . ' < ' . escapeshellarg($path) . ' 2>&1';

exec($cmd, $output, $exit_code);

if ($exit_code === 0) {
    flash_set('success', "Database restored from $name.");
} else {
    flash_set('danger', 'Restore failed: ' . e(implode(' ', array_slice($output, 0, 3))));
}

redirect('/modules/backup/index.php');
