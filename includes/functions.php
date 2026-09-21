<?php
// includes/functions.php
// Shared helpers used across all modules.

declare(strict_types=1);

/**
 * Working days in a month, used to derive the daily salary. Saturdays and
 * Sundays are excluded, so a month counts as 22 working days.
 */
const WORKING_DAYS_PER_MONTH = 22;

/**
 * Employee text columns stored in upper case, matching how the fields are
 * typed on the printed information form. Deliberately excluded: email,
 * contact numbers, government ID numbers and anything numeric.
 */
const UPPERCASE_FIELDS = [
    'employee_no', 'first_name', 'middle_name', 'last_name', 'name_extension',
    'birthplace', 'address', 'address_street', 'address_barangay',
    'address_municipality', 'address_province', 'emergency_contact_name', 'position',
];

/** HTML-escape a value for safe output. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Upper-case a free-text value; null and blanks stay as they are. */
function upper_text(?string $value): ?string
{
    $value = $value === null ? null : trim($value);
    return ($value === null || $value === '') ? null : mb_strtoupper($value, 'UTF-8');
}

/** Upper-case every UPPERCASE_FIELDS entry present in a row of employee data. */
function upper_employee_fields(array $row): array
{
    foreach (UPPERCASE_FIELDS as $field) {
        if (array_key_exists($field, $row)) {
            $row[$field] = upper_text($row[$field] === null ? null : (string)$row[$field]);
        }
    }
    return $row;
}

/**
 * Name extension as printed: JR and SR get their period (JR.), others
 * (II, III, IV ...) are left as typed. Null when blank.
 */
function normalize_name_extension(?string $ext): ?string
{
    $ext = upper_text($ext === null ? null : rtrim(trim($ext), '.'));
    if ($ext === null) {
        return null;
    }
    return in_array($ext, ['JR', 'SR'], true) ? $ext . '.' : $ext;
}

/**
 * Display name from the separately stored name parts:
 * "SURNAME, FIRST M. EXT" — e.g. ROMERO, RHON J. JR.
 * The middle initial is left out when there is no middle name.
 */
function employee_display_name(array $emp): string
{
    $last   = trim((string)($emp['last_name'] ?? ''));
    $first  = trim((string)($emp['first_name'] ?? ''));
    $middle = empty($emp['no_middle_name']) ? trim((string)($emp['middle_name'] ?? '')) : '';
    $ext    = trim((string)($emp['name_extension'] ?? ''));

    $given = $first;
    if ($middle !== '') {
        $given .= ' ' . mb_strtoupper(mb_substr($middle, 0, 1, 'UTF-8'), 'UTF-8') . '.';
    }
    if ($ext !== '') {
        $given .= ' ' . $ext;
    }
    $given = trim($given);

    if ($last === '') {
        return $given;
    }
    return $given === '' ? $last : $last . ', ' . $given;
}

/**
 * Philippine mobile number in the standard 0994-800-7500 format.
 * Accepts 09XXXXXXXXX, +639XXXXXXXXX or 639XXXXXXXXX with any spacing or
 * punctuation. Returns null when the value is not a valid mobile number.
 */
function format_ph_mobile(?string $value): ?string
{
    $digits = preg_replace('/\D/', '', (string)$value);
    if (preg_match('/^639\d{9}$/', $digits)) {
        $digits = '0' . substr($digits, 2);
    }
    if (!preg_match('/^09\d{9}$/', $digits)) {
        return null;
    }
    return substr($digits, 0, 4) . '-' . substr($digits, 4, 3) . '-' . substr($digits, 7);
}

/**
 * Philippine provinces, cities/municipalities and barangays (PSGC) for the
 * address dropdowns, loaded once per request.
 */
function ph_locations(): array
{
    static $data = null;
    return $data ??= require ROOT_PATH . '/includes/data/ph_locations.php';
}

/** Cities/municipalities of a province (by name), as a list of names. */
function ph_municipalities(string $province): array
{
    $data = ph_locations();
    $code = array_search($province, $data['provinces'], true);
    return $code === false ? [] : array_values($data['cities'][$code] ?? []);
}

/** Barangays of a city/municipality within a province (both by name). */
function ph_barangays(string $province, string $municipality): array
{
    $data = ph_locations();
    $code = array_search($province, $data['provinces'], true);
    if ($code === false) {
        return [];
    }
    $city = array_search($municipality, $data['cities'][$code] ?? [], true);
    return $city === false ? [] : ($data['barangays'][$city] ?? []);
}

/** Full address line from its parts, skipping blanks: STREET, BARANGAY, TOWN, PROVINCE. */
function compose_address(?string ...$parts): ?string
{
    $parts = array_filter(array_map(fn($p) => trim((string)$p), $parts), fn($p) => $p !== '');
    return $parts ? implode(', ', $parts) : null;
}

/**
 * Daily salary derived from a monthly salary, rounded to centavos.
 * Returns null when no monthly salary is on record.
 */
function daily_salary(int|float|string|null $monthly): ?float
{
    if ($monthly === null || $monthly === '' || !is_numeric($monthly)) {
        return null;
    }
    return round((float)$monthly / WORKING_DAYS_PER_MONTH, 2);
}

/** Format an amount as pesos (₱10,000.00), or an em dash when unset. */
function peso(int|float|string|null $amount): string
{
    if ($amount === null || $amount === '' || !is_numeric($amount)) {
        return '—';
    }
    return '₱' . number_format((float)$amount, 2);
}

/** Redirect to a path relative to BASE_URL and stop the script. */
function redirect(string $path): void
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

/** Store a one-time flash message: type is 'success' or 'danger'. */
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** Retrieve and clear the flash message, or null when none. */
function flash_get(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/** Return the CSRF token for the current session, creating it if needed. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input carrying the CSRF token, for use inside forms. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Abort the request when the submitted CSRF token is missing or wrong. */
function csrf_check(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        exit('Invalid request token. Please go back and try again.');
    }
}

/** True when an administrator is logged in. */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * Save an uploaded image (photo) and return its relative path, or null when
 * no file was sent. Throws RuntimeException with a user-friendly message.
 */
function save_photo(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Photo upload failed. Please try again.');
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Photo must be 2 MB or smaller.');
    }
    $info = @getimagesize($file['tmp_name']);
    $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];
    if (!$info || !isset($allowed[$info[2]])) {
        throw new RuntimeException('Photo must be a JPG or PNG image.');
    }
    $name = 'photo_' . bin2hex(random_bytes(8)) . '.' . $allowed[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_PATH . '/photos/' . $name)) {
        throw new RuntimeException('Could not save the uploaded photo.');
    }
    return 'uploads/photos/' . $name;
}

/**
 * Ensure the employee has one employee_requirements row per active
 * requirement type (new rows start as 'Missing').
 */
function sync_requirements(PDO $pdo, int $employee_id): void
{
    $pdo->prepare(
        'INSERT IGNORE INTO employee_requirements (employee_id, requirement_type_id)
         SELECT ?, requirement_type_id FROM requirement_types WHERE is_active = 1'
    )->execute([$employee_id]);
}

/**
 * Create one unread notification per employee whose Missing/Incomplete
 * count changed since their last alert. Marking alerts as read keeps them
 * hidden until the employee's pending situation actually changes.
 * Returns rows created.
 */
function generate_notifications(PDO $pdo): int
{
    // Drop unread alerts that no longer reflect reality: either the employee
    // is now fully compliant, or their pending count changed. This keeps the
    // bell accurate instead of showing an outdated number.
    $pdo->exec(
        "DELETE n FROM notifications n
         LEFT JOIN (
             SELECT er.employee_id,
                    CONCAT(e.first_name, ' ', e.last_name, ' has ',
                           SUM(er.status IN ('Missing','Incomplete')), ' pending requirement(s)') AS msg
             FROM employee_requirements er
             JOIN employees e ON e.employee_id = er.employee_id
             JOIN requirement_types rt
               ON rt.requirement_type_id = er.requirement_type_id AND rt.is_active = 1
             GROUP BY er.employee_id, e.first_name, e.last_name
             HAVING SUM(er.status IN ('Missing','Incomplete')) > 0
         ) t ON t.employee_id = n.employee_id
         WHERE n.is_read = 0 AND (t.employee_id IS NULL OR n.message <> t.msg)"
    );

    $stmt = $pdo->query(
        "INSERT INTO notifications (employee_id, message)
         SELECT t.employee_id, t.msg
         FROM (
             SELECT er.employee_id,
                    CONCAT(e.first_name, ' ', e.last_name, ' has ',
                           SUM(er.status IN ('Missing','Incomplete')), ' pending requirement(s)') AS msg,
                    SUM(er.status IN ('Missing','Incomplete')) AS pending
             FROM employee_requirements er
             JOIN employees e ON e.employee_id = er.employee_id
             JOIN requirement_types rt
               ON rt.requirement_type_id = er.requirement_type_id AND rt.is_active = 1
             GROUP BY er.employee_id, e.first_name, e.last_name
             HAVING pending > 0
         ) t
         WHERE NOT EXISTS (
             SELECT 1 FROM notifications n
             WHERE n.employee_id = t.employee_id
               AND (n.is_read = 0 OR n.message = t.msg)
         )"
    );
    return $stmt->rowCount();
}

/** Unread notifications: [count, latest five] for the header bell. */
function get_unread_notifications(PDO $pdo): array
{
    $count = (int)$pdo->query('SELECT COUNT(*) FROM notifications WHERE is_read = 0')->fetchColumn();
    $latest = $pdo->query(
        'SELECT n.notification_id, n.employee_id, n.message, n.created_at
         FROM notifications n WHERE n.is_read = 0
         ORDER BY n.created_at DESC LIMIT 5'
    )->fetchAll();
    return [$count, $latest];
}

/**
 * Read-only storage overview for the dashboard card.
 * Never throws: on failure returns zeros so the UI shows dashes.
 */
function storage_stats(): array
{
    $photos_dir = UPLOAD_PATH . '/photos';
    $used = 0;
    $files = 0;
    foreach (glob($photos_dir . '/*') ?: [] as $f) {
        if (is_file($f)) {
            $files++;
            $size = @filesize($f);
            $used += $size === false ? 0 : $size;
        }
    }
    $free = @disk_free_space(ROOT_PATH);
    $total = @disk_total_space(ROOT_PATH);
    return [
        'used' => $used,
        'files' => $files,
        'free' => $free === false ? 0 : (int)$free,
        'total' => $total === false ? 0 : (int)$total,
        // Rough capacity at typical (500 KB) and max (2 MB) photo sizes.
        'fits_typical' => $free === false ? 0 : (int)floor(((int)$free) / (500 * 1024)),
        'fits_max' => $free === false ? 0 : (int)floor(((int)$free) / (2 * 1024 * 1024)),
    ];
}

/** Format bytes as KB/MB/GB, or an em dash when unknown. */
function format_bytes(int $bytes): string
{
    if ($bytes <= 0) {
        return '—';
    }
    if ($bytes < 1024 * 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    if ($bytes < 1024 * 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 1) . ' MB';
    }
    return number_format($bytes / (1024 * 1024 * 1024), 2) . ' GB';
}
