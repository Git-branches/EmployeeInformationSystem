<?php
// modules/import/index.php
// Upload form for Excel/PDF files + Excel template download + import history.

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once ROOT_PATH . '/vendor/autoload.php';

// ?template=1 → download a ready-to-fill Excel template
if (isset($_GET['template'])) {
    $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Employees');
    $headers = ['Employee No', 'First Name', 'Middle Name', 'Surname', 'Birthday', 'Birthplace',
                'Sex', 'Civil Status', 'CP Number', 'Email', 'Address',
                'Blood Type', 'Height', 'Weight',
                'SSS No', 'PhilHealth No', 'Pag-IBIG No', 'TIN No',
                'Solo Parent', 'IP', 'PWD', 'Smoker',
                'Professional', 'Sub-Professional', 'RA 1080',
                'Emergency Contact Name', 'Emergency Contact Number',
                'Department', 'Position', 'Applicant Type', 'Employment Status', 'Date Hired'];
    $sheet->fromArray($headers, null, 'A1');
    $sheet->getStyle('A1:AF1')->getFont()->setBold(true);
    $sheet->fromArray(['JB-0001', 'Juan', 'Santos', 'Dela Cruz', '1998-05-14', 'Tupi, South Cotabato',
                       'Male', 'Single', '09171234567', 'juan@example.com', 'Poblacion, Tupi',
                       'O+', '170', '65',
                       '34-1234567-8', '12-345678901-2', '1234-5678-9012', '123-456-789-000',
                       'No', 'No', 'No', 'Yes',
                       'Yes', 'No', 'No',
                       'Maria Dela Cruz', '09181234567',
                       'Service Crew', 'Cashier', 'New', 'Applicant', ''], null, 'A2');

    // Note row so the encoder knows how the checkbox columns work
    $sheet->setCellValue('A4', 'Note: For the checkbox columns (Solo Parent, IP, PWD, Smoker, Professional, '
        . 'Sub-Professional, RA 1080) type Yes or No. Leave any column blank if the information is not available.');
    $sheet->getStyle('A4')->getFont()->setItalic(true);

    foreach (range('A', 'Z') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    foreach (['AA', 'AB', 'AC', 'AD', 'AE', 'AF'] as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="employee_import_template.xlsx"');
    (new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
    exit;
}

$history = $pdo->query(
    'SELECT il.*, u.full_name FROM import_logs il
     JOIN users u ON u.user_id = il.imported_by
     ORDER BY il.created_at DESC LIMIT 10'
)->fetchAll();

$page_title = 'Import Data';
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-file-earmark-arrow-up me-2"></i>Smart Document Import</h1>
    <a href="index.php?template=1" class="btn btn-outline-secondary">
        <i class="bi bi-download me-1"></i>Download Excel Template
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">Upload File</div>
            <div class="card-body">
                <form method="post" action="preview.php" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Excel (.xlsx/.xls/.csv) or text-based PDF</label>
                        <input type="file" class="form-control" name="import_file" required
                               accept=".xlsx,.xls,.csv,.pdf">
                    </div>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-search me-1"></i>Scan &amp; Preview
                    </button>
                </form>
                <hr>
                <p class="small text-muted mb-1"><strong>How it works:</strong></p>
                <ol class="small text-muted mb-0">
                    <li>The system scans the file and extracts employee information.</li>
                    <li>You review a preview — duplicates are flagged automatically.</li>
                    <li>Only valid, non-duplicate rows are imported on confirmation.</li>
                </ol>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">Recent Imports</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>File</th><th>Type</th><th class="text-center">Imported</th><th class="text-center">Duplicates</th><th>By</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$history): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No imports yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td class="small"><?= e($h['file_name']) ?></td>
                            <td><span class="badge text-bg-secondary"><?= e($h['file_type']) ?></span></td>
                            <td class="text-center text-success fw-semibold"><?= (int)$h['records_imported'] ?></td>
                            <td class="text-center text-danger"><?= (int)$h['duplicates_skipped'] ?></td>
                            <td class="small"><?= e($h['full_name']) ?></td>
                            <td class="small"><?= e(date('M j, Y g:i A', strtotime($h['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
