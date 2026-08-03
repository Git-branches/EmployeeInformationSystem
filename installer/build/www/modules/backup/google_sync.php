<?php
// modules/backup/google_sync.php
// Uploads pending backups to Google Drive when internet is available.
// Uses OAuth credentials (personal Gmail) or a service account (Workspace).

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once ROOT_PATH . '/vendor/autoload.php';
require_once ROOT_PATH . '/includes/google_drive.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/backup/index.php');
}
csrf_check();

if (google_credential_type() === 'none') {
    flash_set('danger', 'Google Drive is not configured. Save your OAuth client file as config/google_oauth_client.json first.');
    redirect('/modules/backup/index.php');
}

// Connectivity check: don't attempt uploads while offline.
$socket = @fsockopen('www.googleapis.com', 443, $errno, $errstr, 4);
if (!$socket) {
    flash_set('danger', 'No internet connection detected. Backups stay queued as Pending and can be synced later.');
    redirect('/modules/backup/index.php');
}
fclose($socket);

$auth_error = null;
$client = google_authorized_client($auth_error);
if (!$client) {
    flash_set('danger', $auth_error ?? 'Google Drive is not connected.');
    redirect('/modules/backup/index.php');
}

$pending = $pdo->query(
    "SELECT backup_id, file_name FROM backup_logs
     WHERE destination = 'GoogleDrive' AND status = 'Pending'
     ORDER BY created_at"
)->fetchAll();

if (!$pending) {
    flash_set('success', 'Nothing to sync — all backups are already uploaded.');
    redirect('/modules/backup/index.php');
}

$done = 0;
$failed = 0;
$last_error = '';

try {
    $drive = new Google\Service\Drive($client);
    $mark  = $pdo->prepare('UPDATE backup_logs SET status = ? WHERE backup_id = ?');

    foreach ($pending as $row) {
        $path = BACKUP_PATH . '/' . $row['file_name'];
        if (!is_file($path)) {
            $mark->execute(['Failed', $row['backup_id']]);
            $failed++;
            $last_error = 'backup file missing on disk';
            continue;
        }
        try {
            $meta = new Google\Service\Drive\DriveFile(['name' => $row['file_name']]);
            if (GDRIVE_FOLDER_ID !== '') {
                $meta->setParents([GDRIVE_FOLDER_ID]);
            }
            $drive->files->create($meta, [
                'data'       => file_get_contents($path),
                'mimeType'   => 'application/sql',
                'uploadType' => 'multipart',
                'fields'     => 'id',
            ]);
            $mark->execute(['Success', $row['backup_id']]);
            $done++;
        } catch (Throwable $ex) {
            $mark->execute(['Failed', $row['backup_id']]);
            $failed++;
            // Surface Google's own explanation instead of a generic failure.
            $decoded = json_decode($ex->getMessage(), true);
            $last_error = $decoded['error']['message'] ?? $ex->getMessage();
        }
    }
} catch (Throwable $ex) {
    flash_set('danger', 'Google Drive connection failed: ' . e(substr($ex->getMessage(), 0, 300)));
    redirect('/modules/backup/index.php');
}

if ($failed === 0) {
    flash_set('success', "Google Drive sync complete: $done file(s) uploaded.");
} else {
    flash_set('danger', "Sync finished with issues: $done uploaded, $failed failed. Reason: " . e(substr($last_error, 0, 250)));
}
redirect('/modules/backup/index.php');
