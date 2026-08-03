<?php
// modules/import/preview.php
// Parses the uploaded Excel/PDF, extracts employee rows, flags duplicates,
// and shows a preview. Parsed rows are held in the session for process.php.

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once ROOT_PATH . '/vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/import/index.php');
}
csrf_check();

$file = $_FILES['import_file'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    flash_set('danger', 'No file uploaded or the upload failed.');
    redirect('/modules/import/index.php');
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['xlsx', 'xls', 'csv', 'pdf'], true)) {
    flash_set('danger', 'Unsupported file type. Use .xlsx, .xls, .csv, or .pdf.');
    redirect('/modules/import/index.php');
}

// Keep a copy of the source file for the record
$stored = 'uploads/imports/import_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
move_uploaded_file($file['tmp_name'], ROOT_PATH . '/' . $stored);
$source_path = ROOT_PATH . '/' . $stored;

/** Normalize a header label: "Employee No." → "employeeno" */
$norm = fn(string $s) => preg_replace('/[^a-z0-9]/', '', strtolower($s));

/** Normalize a date value from Excel/PDF into Y-m-d or null. */
function parse_date(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_numeric($value)) { // Excel serial date
        try {
            return PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$value)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }
    $ts = strtotime(trim((string)$value));
    return $ts ? date('Y-m-d', $ts) : null;
}

$field_map = [
    'employeeno' => 'employee_no',   'firstname' => 'first_name',
    'middlename' => 'middle_name',   'lastname' => 'last_name',
    'birthdate' => 'birthdate',      'dateofbirth' => 'birthdate',
    'sex' => 'sex',                  'gender' => 'sex',
    'contactno' => 'contact_no',     'contactnumber' => 'contact_no',
    'email' => 'email',              'emailaddress' => 'email',
    'address' => 'address',          'department' => 'department',
    'position' => 'position',        'applicanttype' => 'applicant_type',
    'employmentstatus' => 'employment_status', 'status' => 'employment_status',
    'datehired' => 'date_hired',
];

$rows = [];

try {
    if ($ext === 'pdf') {
        $text = (new Smalot\PdfParser\Parser())->parseFile($source_path)->getText();
        if (trim($text) === '') {
            throw new RuntimeException('No text could be extracted — this PDF appears to be a scanned image, which is not supported.');
        }
        // Split into one block per record, using "First Name" as the anchor label
        $blocks = preg_split('/(?=First\s*Name\s*[:\-])/i', $text) ?: [];
        foreach ($blocks as $block) {
            if (!preg_match('/First\s*Name\s*[:\-]/i', $block)) {
                continue;
            }
            $row = [];
            foreach (['Employee No', 'First Name', 'Middle Name', 'Last Name', 'Birthdate',
                      'Sex', 'Contact No', 'Email', 'Address', 'Department', 'Position',
                      'Applicant Type', 'Employment Status', 'Date Hired'] as $label) {
                $pattern = '/' . str_replace(' ', '\s*', preg_quote($label, '/')) . '\s*[:\-]\s*(.+)/i';
                if (preg_match($pattern, $block, $m)) {
                    $key = $field_map[$norm($label)] ?? null;
                    if ($key) {
                        $row[$key] = trim($m[1]);
                    }
                }
            }
            if ($row) {
                $rows[] = $row;
            }
        }
    } else {
        $reader = PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($source_path);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($source_path)->getActiveSheet();
        $data  = $sheet->toArray(null, false, false, false);
        if (count($data) < 2) {
            throw new RuntimeException('The file has no data rows below the header.');
        }
        // Map column positions from the header row
        $columns = [];
        foreach ($data[0] as $i => $header) {
            $key = $field_map[$norm((string)$header)] ?? null;
            if ($key) {
                $columns[$i] = $key;
            }
        }
        if (!in_array('first_name', $columns, true) || !in_array('last_name', $columns, true)) {
            throw new RuntimeException('Could not find "First Name" / "Last Name" columns. Use the provided template.');
        }
        foreach (array_slice($data, 1) as $line) {
            $row = [];
            foreach ($columns as $i => $key) {
                $value = $line[$i] ?? null;
                $row[$key] = is_string($value) ? trim($value) : $value;
            }
            if (array_filter($row, fn($v) => $v !== null && $v !== '')) {
                $rows[] = $row;
            }
        }
    }
} catch (RuntimeException $ex) {
    flash_set('danger', 'Extraction failed: ' . $ex->getMessage());
    redirect('/modules/import/index.php');
} catch (Throwable $ex) {
    flash_set('danger', 'Could not read the file. Make sure it matches the template format.');
    redirect('/modules/import/index.php');
}

if (!$rows) {
    flash_set('danger', 'No employee records were found in the file.');
    redirect('/modules/import/index.php');
}

// Validate + duplicate-check each row
$check_no   = $pdo->prepare('SELECT employee_id FROM employees WHERE employee_no = ?');
$check_name = $pdo->prepare('SELECT employee_id FROM employees WHERE first_name = ? AND last_name = ? AND birthdate = ?');
$seen = [];

foreach ($rows as $i => &$row) {
    // Normalize values
    $row['birthdate']  = parse_date($row['birthdate'] ?? null);
    $row['date_hired'] = parse_date($row['date_hired'] ?? null);
    $sex = ucfirst(strtolower(trim((string)($row['sex'] ?? ''))));
    $row['sex'] = in_array($sex, ['Male', 'Female'], true) ? $sex : null;
    $type = ucfirst(strtolower(trim((string)($row['applicant_type'] ?? ''))));
    $row['applicant_type'] = in_array($type, ['New', 'Existing'], true) ? $type : 'New';
    $status = ucfirst(strtolower(trim((string)($row['employment_status'] ?? ''))));
    $row['employment_status'] = in_array($status, ['Applicant', 'Active', 'Inactive', 'Terminated'], true) ? $status : 'Applicant';
    $row['employee_no'] = trim((string)($row['employee_no'] ?? '')) ?: null;

    // Verdict
    if (empty($row['first_name']) || empty($row['last_name']) || !$row['birthdate'] || !$row['sex']) {
        $row['_verdict'] = 'invalid';
        $row['_reason']  = 'Missing/invalid required field (first name, last name, birthdate, or sex)';
        continue;
    }
    $dup_key = strtolower($row['first_name'] . '|' . $row['last_name'] . '|' . $row['birthdate']);
    if (isset($seen[$dup_key]) || ($row['employee_no'] && isset($seen['no:' . $row['employee_no']]))) {
        $row['_verdict'] = 'duplicate';
        $row['_reason']  = 'Duplicate of another row in this file';
        continue;
    }
    $seen[$dup_key] = true;
    if ($row['employee_no']) {
        $seen['no:' . $row['employee_no']] = true;
    }
    if ($row['employee_no']) {
        $check_no->execute([$row['employee_no']]);
        if ($check_no->fetch()) {
            $row['_verdict'] = 'duplicate';
            $row['_reason']  = 'Employee number already exists in the database';
            continue;
        }
    }
    $check_name->execute([$row['first_name'], $row['last_name'], $row['birthdate']]);
    if ($check_name->fetch()) {
        $row['_verdict'] = 'duplicate';
        $row['_reason']  = 'Same name and birthdate already exists in the database';
        continue;
    }
    $row['_verdict'] = 'new';
    $row['_reason']  = '';
}
unset($row);

$_SESSION['import'] = [
    'rows'      => $rows,
    'file_name' => $file['name'],
    'file_type' => $ext === 'pdf' ? 'PDF' : 'Excel',
];

$counts = array_count_values(array_column($rows, '_verdict'));

$page_title = 'Import Preview';
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-search me-2"></i>Import Preview: <?= e($file['name']) ?></h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>Cancel</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-auto"><span class="badge text-bg-success fs-6"><?= $counts['new'] ?? 0 ?> to import</span></div>
    <div class="col-auto"><span class="badge text-bg-danger fs-6"><?= $counts['duplicate'] ?? 0 ?> duplicate(s) — will be skipped</span></div>
    <div class="col-auto"><span class="badge text-bg-secondary fs-6"><?= $counts['invalid'] ?? 0 ?> invalid — will be skipped</span></div>
</div>

<div class="card shadow-sm mb-4">
    <div class="table-responsive" style="max-height:60vh">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light" style="position:sticky;top:0">
                <tr>
                    <th>#</th><th>Verdict</th><th>Employee No</th><th>Name</th><th>Birthdate</th>
                    <th>Sex</th><th>Department</th><th>Position</th><th>Type</th><th>Status</th><th>Reason</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $i => $r):
                $badge = ['new' => 'text-bg-success', 'duplicate' => 'text-bg-danger', 'invalid' => 'text-bg-secondary'][$r['_verdict']];
            ?>
                <tr class="<?= $r['_verdict'] !== 'new' ? 'table-light text-muted' : '' ?>">
                    <td><?= $i + 1 ?></td>
                    <td><span class="badge <?= $badge ?>"><?= e($r['_verdict']) ?></span></td>
                    <td><?= e((string)($r['employee_no'] ?? '')) ?></td>
                    <td><?= e(trim(($r['first_name'] ?? '') . ' ' . ($r['middle_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))) ?></td>
                    <td><?= e((string)($r['birthdate'] ?? '')) ?></td>
                    <td><?= e((string)($r['sex'] ?? '')) ?></td>
                    <td><?= e((string)($r['department'] ?? '')) ?></td>
                    <td><?= e((string)($r['position'] ?? '')) ?></td>
                    <td><?= e((string)($r['applicant_type'] ?? '')) ?></td>
                    <td><?= e((string)($r['employment_status'] ?? '')) ?></td>
                    <td class="small"><?= e($r['_reason']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<form method="post" action="process.php">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-danger" <?= ($counts['new'] ?? 0) === 0 ? 'disabled' : '' ?>>
        <i class="bi bi-check2-circle me-1"></i>Confirm Import (<?= $counts['new'] ?? 0 ?> record<?= ($counts['new'] ?? 0) === 1 ? '' : 's' ?>)
    </button>
    <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
