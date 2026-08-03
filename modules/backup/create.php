<?php
// modules/backup/create.php
// Creates a local .sql dump via mysqldump and queues a Google Drive sync.

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/backup/index.php');
}
csrf_check();

if (MYSQL_BIN_PATH === '') {
    flash_set('danger', 'Could not locate mysqldump.exe. Check that MySQL is installed with Laragon or XAMPP.');
    redirect('/modules/backup/index.php');
}

$file_name = 'eis_backup_' . date('Ymd_His') . '.sql';
$file_path = BACKUP_PATH . '/' . $file_name;

$cmd = '"' . MYSQL_BIN_PATH . '\\mysqldump.exe" --user=' . escapeshellarg(DB_USER)
     . (DB_PASS !== '' ? ' --password=' . escapeshellarg(DB_PASS) : '')
     . ' --host=' . escapeshellarg(DB_HOST) . ' --port=' . escapeshellarg(DB_PORT)
     . ' --routines --single-transaction ' . escapeshellarg(DB_NAME)
     . ' > ' . escapeshellarg($file_path) . ' 2>&1';

exec($cmd, $output, $exit_code);

$ok = $exit_code === 0 && is_file($file_path) && filesize($file_path) > 0;

$log = $pdo->prepare(
    'INSERT INTO backup_logs (file_name, file_size, destination, status, created_by) VALUES (?,?,?,?,?)'
);

if ($ok) {
    $size = (int)filesize($file_path);
    $log->execute([$file_name, $size, 'Local', 'Success', $_SESSION['user_id']]);
    // Queue the same file for Google Drive upload
    $log->execute([$file_name, $size, 'GoogleDrive', 'Pending', $_SESSION['user_id']]);
    flash_set('success', "Backup created: $file_name (" . number_format($size / 1024, 1) . ' KB). Queued for Google Drive sync.');
} else {
    if (is_file($file_path)) {
        unlink($file_path);
    }
    $log->execute([$file_name, null, 'Local', 'Failed', $_SESSION['user_id']]);
    flash_set('danger', 'Backup failed. Check that MySQL is running and mysqldump is available.');
}

redirect('/modules/backup/index.php');
