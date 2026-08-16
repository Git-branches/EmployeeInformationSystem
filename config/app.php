<?php
// config/app.php
// Application bootstrap: settings, session, database, helpers.
// Paths are auto-detected so the system keeps working on any Laragon/XAMPP
// version without editing this file.

declare(strict_types=1);

define('APP_NAME', 'Employee Information System');

/**
 * Optional organisation name and address, printed as the letterhead of the
 * Employee Information Form (screen and PDF). Both are blank by default —
 * the form then carries its own title alone. Fill them in to put an
 * establishment's name on the printed record.
 */
define('APP_COMPANY', '');
define('APP_ADDRESS', '');

// Absolute filesystem paths
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('BACKUP_PATH', ROOT_PATH . '/backups');

/**
 * Base URL of the app relative to the web server document root.
 * Auto-detected so it works both as http://localhost/EmployeeInformationSystem
 * and as a Laragon auto virtual host (http://employeeinformationsystem.test).
 * Set EIS_BASE_URL below to override manually if ever needed.
 */
$eis_base_url = null;                       // e.g. '/EmployeeInformationSystem'
if ($eis_base_url === null) {
    $doc_root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $app_root = realpath(ROOT_PATH);
    $doc_root = $doc_root ? str_replace('\\', '/', $doc_root) : '';
    $app_root = $app_root ? str_replace('\\', '/', $app_root) : '';

    $eis_base_url = '';
    if ($doc_root !== '' && $app_root !== '' && str_starts_with($app_root, $doc_root)) {
        $eis_base_url = rtrim(substr($app_root, strlen($doc_root)), '/');
    }
}
define('BASE_URL', $eis_base_url);

/**
 * Folder holding the MySQL client tools (mysqldump.exe / mysql.exe) used by
 * the backup module. Auto-detected across Laragon/XAMPP installations and
 * MySQL/MariaDB versions.
 */
function eis_find_mysql_bin(): string
{
    $patterns = [
        // Portable stack shipped by the installer ({APPDIR}/stack/mariadb/bin)
        str_replace('\\', '/', dirname(ROOT_PATH)) . '/stack/mariadb/bin',
        str_replace('\\', '/', dirname(ROOT_PATH, 2)) . '/stack/mariadb/bin',
        // Developer machines
        'C:/laragon/bin/mysql/*/bin',
        'C:/laragon/bin/mariadb/*/bin',
        'C:/xampp/mysql/bin',
    ];
    foreach ($patterns as $pattern) {
        foreach (glob($pattern) ?: [] as $dir) {
            if (is_file($dir . '/mysqldump.exe')) {
                return str_replace('/', '\\', $dir);
            }
        }
    }
    return '';
}
define('MYSQL_BIN_PATH', eis_find_mysql_bin());

// ---- Google Drive backup ---------------------------------------------------
// Preferred (works with a personal Gmail): OAuth 2.0 client credentials saved
// as config/google_oauth_client.json. The administrator authorizes once and
// the refresh token is stored in config/google_token.json.
define('GOOGLE_OAUTH_CLIENT', ROOT_PATH . '/config/google_oauth_client.json');
define('GOOGLE_TOKEN', ROOT_PATH . '/config/google_token.json');

// Alternative: service-account key. Only works with Google Workspace Shared
// Drives — service accounts have no storage quota on a personal Drive.
define('GOOGLE_CREDENTIALS', ROOT_PATH . '/config/google_credentials.json');

// Drive folder that receives the backups (from the folder URL).
define('GDRIVE_FOLDER_ID', '1RAtkaea8zb1FkeYJMJUjhxGpR7PgohP9');

if (session_status() === PHP_SESSION_NONE) {
    session_name('eis_session');
    session_start();
}

require_once __DIR__ . '/db.php';
require_once ROOT_PATH . '/includes/functions.php';
