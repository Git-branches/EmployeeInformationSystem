<?php
// modules/reports/export.php
// Exports: employee list (format=pdf|xlsx + filters) or a single
// employee information form (form_id= → PDF).

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/report_data.php';
require_once ROOT_PATH . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function stream_pdf(string $html, string $filename, string $orientation = 'portrait'): void
{
    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $options->set('chroot', ROOT_PATH);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', $orientation);
    $dompdf->render();
    $dompdf->stream($filename, ['Attachment' => true]);
    exit;
}

// ---- Single employee information form → PDF -------------------------------
if (isset($_GET['form_id'])) {
    $id = (int)$_GET['form_id'];
    $stmt = $pdo->prepare(
        'SELECT e.*, d.department_name FROM employees e
         LEFT JOIN departments d ON d.department_id = e.department_id
         WHERE e.employee_id = ?'
    );
    $stmt->execute([$id]);
    $emp = $stmt->fetch();
    if (!$emp) {
        flash_set('danger', 'Employee not found.');
        redirect('/modules/reports/index.php');
    }
    $reqs = $pdo->prepare(
        'SELECT rt.requirement_name, er.status, er.date_submitted
         FROM employee_requirements er
         JOIN requirement_types rt ON rt.requirement_type_id = er.requirement_type_id
         WHERE er.employee_id = ? AND rt.is_active = 1 ORDER BY rt.requirement_name'
    );
    $reqs->execute([$id]);
    $requirements = $reqs->fetchAll();

    $photo_html = '<div style="width:120px;height:120px;border:1px solid #999;text-align:center;line-height:120px;color:#999;font-size:11px">2x2 Photo</div>';
    if ($emp['photo_path'] && is_file(ROOT_PATH . '/' . $emp['photo_path'])) {
        $photo_html = '<img src="' . e(ROOT_PATH . '/' . $emp['photo_path']) . '" style="width:120px;height:120px;object-fit:cover;border:1px solid #999">';
    }

    $req_rows = '';
    foreach ($requirements as $r) {
        $req_rows .= '<tr><td>' . e($r['requirement_name']) . '</td><td>' . e($r['status']) . '</td><td>'
                   . ($r['date_submitted'] ? e(date('M j, Y', strtotime($r['date_submitted']))) : '') . '</td></tr>';
    }
    if ($req_rows === '') {
        $req_rows = '<tr><td colspan="3">No requirements defined.</td></tr>';
    }

    $full_name = $emp['first_name'] . ' ' . ($emp['middle_name'] ? $emp['middle_name'] . ' ' : '') . $emp['last_name'];

    // Checkbox glyphs for the printed form
    $box = fn($on) => $on ? '&#9745;' : '&#9744;';
    $num = fn($v, $unit) => $v !== null ? rtrim(rtrim((string)$v, '0'), '.') . ' ' . $unit : '—';

    $status_rows = '';
    foreach (['is_solo_parent' => 'Solo Parent', 'is_ip' => 'IP (Indigenous People)',
              'is_pwd' => 'PWD (Person with Disability)', 'is_smoker' => 'Smoker'] as $k => $label) {
        $status_rows .= '<div>' . $box(!empty($emp[$k])) . ' ' . e($label) . '</div>';
    }
    $elig_rows = '';
    foreach (['elig_professional' => 'Professional (Prof.)',
              'elig_sub_professional' => 'Sub-Professional (Sub-Prof.)',
              'elig_ra1080' => 'RA 1080'] as $k => $label) {
        $elig_rows .= '<div>' . $box(!empty($emp[$k])) . ' ' . e($label) . '</div>';
    }
    $html = '
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1 { font-size: 16px; text-align: center; margin: 0; }
        .sub { text-align: center; color: #666; margin-bottom: 4px; }
        .form-title { text-align: center; font-weight: bold; text-transform: uppercase; margin: 8px 0 16px; border-bottom: 2px solid #c8102e; padding-bottom: 8px; }
        table.info td { padding: 3px 6px; vertical-align: top; }
        td.lbl { color: #666; width: 150px; }
        h2 { font-size: 13px; text-transform: uppercase; border-bottom: 1px solid #999; padding-bottom: 3px; margin-top: 18px; }
        table.req { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.req th, table.req td { border: 1px solid #999; padding: 4px 6px; text-align: left; }
        table.req th { background: #f0f0f0; }
        .sig { margin-top: 60px; width: 100%; }
        .sig td { width: 50%; text-align: center; }
        .sig .line { border-top: 1px solid #222; margin: 0 40px; padding-top: 3px; }
        .foot { color: #888; font-size: 10px; margin-top: 30px; }
    </style>
    <h1>' . e(APP_COMPANY) . '</h1>
    <div class="sub">National Highway Brgy. Poblacion Tupi</div>
    <div class="form-title">Employee Information Form</div>
    <table width="100%"><tr>
        <td>
            <table class="info">
                <tr><td class="lbl">Employee No.</td><td><strong>' . e($emp['employee_no'] ?? '—') . '</strong></td></tr>
                <tr><td class="lbl">Full Name</td><td><strong>' . e($full_name) . '</strong></td></tr>
                <tr><td class="lbl">Birthday</td><td>' . e(date('F j, Y', strtotime($emp['birthdate']))) . '</td></tr>
                <tr><td class="lbl">Birthplace</td><td>' . e($emp['birthplace'] ?? '—') . '</td></tr>
                <tr><td class="lbl">Sex / Civil Status</td><td>' . e($emp['sex']) . ' / ' . e($emp['civil_status'] ?? '—') . '</td></tr>
                <tr><td class="lbl">CP Number</td><td>' . e($emp['contact_no'] ?? '—') . '</td></tr>
                <tr><td class="lbl">Email</td><td>' . e($emp['email'] ?? '—') . '</td></tr>
                <tr><td class="lbl">Address</td><td>' . e($emp['address'] ?? '—') . '</td></tr>
                <tr><td class="lbl">Blood Type</td><td>' . e($emp['blood_type'] ?? '—') . '</td></tr>
                <tr><td class="lbl">Height / Weight</td><td>' . $num($emp['height_cm'], 'cm') . ' / ' . $num($emp['weight_kg'], 'kg') . '</td></tr>
            </table>
        </td>
        <td width="130" align="right">' . $photo_html . '</td>
    </tr></table>

    <h2>Government ID Numbers</h2>
    <table class="info">
        <tr><td class="lbl">SSS No.</td><td>' . e($emp['sss_no'] ?? '—') . '</td>
            <td class="lbl">PhilHealth No.</td><td>' . e($emp['philhealth_no'] ?? '—') . '</td></tr>
        <tr><td class="lbl">Pag-IBIG No.</td><td>' . e($emp['pagibig_no'] ?? '—') . '</td>
            <td class="lbl">TIN No.</td><td>' . e($emp['tin_no'] ?? '—') . '</td></tr>
    </table>

    <table width="100%"><tr>
        <td width="50%" valign="top"><h2>Personal Status</h2>' . $status_rows . '</td>
        <td width="50%" valign="top"><h2>Eligibility</h2>' . $elig_rows . '</td>
    </tr></table>

    <h2>In Case of Emergency, Please Contact</h2>
    <table class="info">
        <tr><td class="lbl">Name</td><td>' . e($emp['emergency_contact_name'] ?? '—') . '</td>
            <td class="lbl">Contact Number</td><td>' . e($emp['emergency_contact_no'] ?? '—') . '</td></tr>
    </table>

    <h2>Employment Details</h2>
    <table class="info">
        <tr><td class="lbl">Department</td><td>' . e($emp['department_name'] ?? 'Unassigned') . '</td></tr>
        <tr><td class="lbl">Position</td><td>' . e($emp['position'] ?? '—') . '</td></tr>
        <tr><td class="lbl">Applicant Type</td><td>' . e($emp['applicant_type']) . '</td></tr>
        <tr><td class="lbl">Employment Status</td><td>' . e($emp['employment_status']) . '</td></tr>
        <tr><td class="lbl">Date Hired</td><td>' . ($emp['date_hired'] ? e(date('F j, Y', strtotime($emp['date_hired']))) : '—') . '</td></tr>
        <tr><td class="lbl">Monthly Salary</td><td><strong>' . e(peso($emp['monthly_salary'])) . '</strong></td></tr>
        <tr><td class="lbl">Daily Salary</td><td>' . e(peso(daily_salary($emp['monthly_salary'])))
            . ' <span style="color:#666">(&divide; ' . WORKING_DAYS_PER_MONTH . ' working days)</span></td></tr>
    </table>
    <h2>Requirements Checklist</h2>
    <table class="req">
        <tr><th>Requirement</th><th width="110">Status</th><th width="120">Date Submitted</th></tr>
        ' . $req_rows . '
    </table>
    <table class="sig"><tr>
        <td><div class="line">Employee Signature</div></td>
        <td><div class="line">Administrator</div></td>
    </tr></table>
    <div class="foot">Generated by ' . e(APP_NAME) . ' on ' . e(date('F j, Y g:i A')) . '</div>';

    stream_pdf($html, 'employee_form_' . $id . '.pdf');
}

// ---- Employee list export -------------------------------------------------
$f      = report_filters();
$rows   = report_rows($pdo, $f);
$title  = report_title($pdo, $f);
$format = (string)($_GET['format'] ?? 'pdf');

$headers = ['Employee No', 'Last Name', 'First Name', 'Middle Name', 'Birthdate', 'Sex',
            'Contact No', 'Email', 'Department', 'Position', 'Applicant Type',
            'Employment Status', 'Date Hired', 'Pending Requirements'];

if ($format === 'xlsx') {
    $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Employee Report');
    $sheet->fromArray([$title], null, 'A1');
    $sheet->fromArray(['Generated: ' . date('F j, Y g:i A')], null, 'A2');
    $sheet->fromArray($headers, null, 'A4');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A4:N4')->getFont()->setBold(true);
    $line = 5;
    foreach ($rows as $r) {
        $sheet->fromArray([
            $r['employee_no'], $r['last_name'], $r['first_name'], $r['middle_name'],
            $r['birthdate'], $r['sex'], $r['contact_no'], $r['email'],
            $r['department_name'] ?? 'Unassigned', $r['position'],
            $r['applicant_type'], $r['employment_status'], $r['date_hired'],
            (int)$r['pending_reqs'],
        ], null, 'A' . $line++);
    }
    foreach (range('A', 'N') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="employee_report_' . date('Ymd') . '.xlsx"');
    (new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
    exit;
}

// PDF list (landscape)
$body_rows = '';
foreach ($rows as $r) {
    $body_rows .= '<tr>'
        . '<td>' . e($r['employee_no'] ?? '—') . '</td>'
        . '<td>' . e($r['last_name'] . ', ' . $r['first_name'] . ($r['middle_name'] ? ' ' . $r['middle_name'] : '')) . '</td>'
        . '<td>' . e($r['birthdate']) . '</td>'
        . '<td>' . e($r['sex']) . '</td>'
        . '<td>' . e($r['contact_no'] ?? '—') . '</td>'
        . '<td>' . e($r['department_name'] ?? 'Unassigned') . '</td>'
        . '<td>' . e($r['position'] ?? '—') . '</td>'
        . '<td>' . e($r['applicant_type']) . '</td>'
        . '<td>' . e($r['employment_status']) . '</td>'
        . '<td>' . e($r['date_hired'] ?? '—') . '</td>'
        . '<td align="center">' . (int)$r['pending_reqs'] . '</td>'
        . '</tr>';
}
if ($body_rows === '') {
    $body_rows = '<tr><td colspan="11" align="center">No records match the selected filters.</td></tr>';
}

$html = '
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
    h1 { font-size: 15px; margin: 0; } .sub { color: #666; margin-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #999; padding: 3px 5px; text-align: left; }
    th { background: #c8102e; color: #fff; }
</style>
<h1>' . e(APP_COMPANY) . ' — ' . e($title) . '</h1>
<div class="sub">Generated ' . e(date('F j, Y g:i A')) . ' · ' . count($rows) . ' record(s)</div>
<table>
    <tr><th>Emp No</th><th>Name</th><th>Birthdate</th><th>Sex</th><th>Contact</th><th>Department</th>
        <th>Position</th><th>Type</th><th>Status</th><th>Hired</th><th>Pending</th></tr>
    ' . $body_rows . '
</table>';

stream_pdf($html, 'employee_report_' . date('Ymd') . '.pdf', 'landscape');
