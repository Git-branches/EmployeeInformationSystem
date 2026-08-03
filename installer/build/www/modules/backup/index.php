<?php
// modules/backup/index.php
// Backup dashboard: history, create/restore/download, Google Drive sync.

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once ROOT_PATH . '/vendor/autoload.php';
require_once ROOT_PATH . '/includes/google_drive.php';

// ?download=<file> → stream a local backup file
if (isset($_GET['download'])) {
    $name = basename((string)$_GET['download']);
    $path = BACKUP_PATH . '/' . $name;
    if (preg_match('/^eis_backup_[\w.]+\.sql$/', $name) && is_file($path)) {
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
    flash_set('danger', 'Backup file not found.');
    redirect('/modules/backup/index.php');
}

$logs = $pdo->query(
    'SELECT bl.*, u.full_name FROM backup_logs bl
     JOIN users u ON u.user_id = bl.created_by
     ORDER BY bl.created_at DESC LIMIT 25'
)->fetchAll();

$pending_sync = (int)$pdo->query(
    "SELECT COUNT(*) FROM backup_logs WHERE destination = 'GoogleDrive' AND status = 'Pending'"
)->fetchColumn();

$cred_type    = google_credential_type();
$drive_ready  = google_is_connected();

$status_badge = ['Success' => 'text-bg-success', 'Failed' => 'text-bg-danger', 'Pending' => 'text-bg-warning'];

$page_title = 'Backup';
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-cloud-arrow-up me-2"></i>Backup &amp; Restore</h1>
    <div>
        <form method="post" action="create.php" class="d-inline">
            <?= csrf_field() ?>
            <button class="btn btn-danger"><i class="bi bi-database-down me-1"></i>Create Backup Now</button>
        </form>
        <?php if ($drive_ready): ?>
        <form method="post" action="google_sync.php" class="d-inline">
            <?= csrf_field() ?>
            <button class="btn btn-outline-success" <?= $pending_sync === 0 ? 'disabled' : '' ?>>
                <i class="bi bi-google me-1"></i>Sync to Google Drive
                <?php if ($pending_sync > 0): ?><span class="badge text-bg-warning ms-1"><?= $pending_sync ?></span><?php endif; ?>
            </button>
        </form>
        <?php elseif ($cred_type === 'oauth'): ?>
        <a href="google_oauth.php" class="btn btn-success">
            <i class="bi bi-google me-1"></i>Connect Google Drive
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($cred_type === 'none'): ?>
    <div class="alert alert-warning">
        <i class="bi bi-info-circle me-1"></i>
        <strong>Google Drive is not configured yet.</strong> Local backups still work.
        To enable cloud sync, create an <strong>OAuth client ID</strong> (type: Web application) in the
        Google Cloud console with the Drive API enabled, and save the downloaded file as
        <code>config/google_oauth_client.json</code>. Backups queue as <em>Pending</em> until then.
    </div>
<?php elseif ($cred_type === 'oauth' && !$drive_ready): ?>
    <div class="alert alert-info">
        <i class="bi bi-google me-1"></i>
        <strong>Almost there — authorize the system once.</strong>
        Click <strong>Connect Google Drive</strong> above and approve access with the Google account that
        owns the backup folder. Backups are then uploaded automatically whenever you are online.
        <div class="small mt-2">
            Authorized redirect URI to register in the Google console:
            <code><?= e(google_redirect_uri()) ?></code>
        </div>
    </div>
<?php elseif ($cred_type === 'oauth' && $drive_ready): ?>
    <div class="alert alert-success d-flex justify-content-between align-items-center">
        <div><i class="bi bi-check-circle me-1"></i><strong>Google Drive is connected.</strong> Pending backups upload when you press Sync.</div>
        <a href="google_oauth.php?disconnect=1" class="btn btn-sm btn-outline-secondary">Disconnect</a>
    </div>
<?php elseif ($cred_type === 'service_account'): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <strong>A service-account key is installed.</strong> Google does not give service accounts any
        Drive storage, so uploads to a personal Google account will fail. Use an
        <strong>OAuth client ID</strong> instead (saved as <code>config/google_oauth_client.json</code>).
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold">Backup History</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>File</th><th>Size</th><th>Destination</th><th>Status</th><th>By</th><th>Date</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
            <?php if (!$logs): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No backups yet. Click "Create Backup Now".</td></tr>
            <?php endif; ?>
            <?php foreach ($logs as $log): $local_file = is_file(BACKUP_PATH . '/' . $log['file_name']); ?>
                <tr>
                    <td class="small"><?= e($log['file_name']) ?></td>
                    <td class="small"><?= $log['file_size'] !== null ? e(number_format((int)$log['file_size'] / 1024, 1)) . ' KB' : '—' ?></td>
                    <td>
                        <?php if ($log['destination'] === 'GoogleDrive'): ?>
                            <span class="badge text-bg-primary"><i class="bi bi-google me-1"></i>Google Drive</span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary"><i class="bi bi-hdd me-1"></i>Local</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= $status_badge[$log['status']] ?>"><?= e($log['status']) ?></span></td>
                    <td class="small"><?= e($log['full_name']) ?></td>
                    <td class="small"><?= e(date('M j, Y g:i A', strtotime($log['created_at']))) ?></td>
                    <td class="text-end text-nowrap">
                        <?php if ($log['destination'] === 'Local' && $local_file): ?>
                            <a href="index.php?download=<?= e(rawurlencode($log['file_name'])) ?>"
                               class="btn btn-sm btn-outline-secondary" title="Download"><i class="bi bi-download"></i></a>
                            <form method="post" action="restore.php" class="d-inline"
                                  data-confirm="Restore the database from <?= e($log['file_name']) ?>? Current data will be REPLACED by this backup.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="file_name" value="<?= e($log['file_name']) ?>">
                                <button class="btn btn-sm btn-outline-danger" title="Restore"><i class="bi bi-arrow-counterclockwise"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white text-muted small">
        Backups are saved to the <code>backups/</code> folder. Google Drive rows marked
        <em>Pending</em> upload automatically the next time you press Sync while online.
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
