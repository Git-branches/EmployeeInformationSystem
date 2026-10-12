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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

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
        'SELECT e.*, d.department_name, s.status_name AS appointment_status FROM employees e
         LEFT JOIN departments d ON d.department_id = e.department_id
         LEFT JOIN employment_statuses s ON s.employment_status_id = e.employment_status_id
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

    $full_name = employee_display_name($emp);

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
    // Letterhead, printed only when an organisation is configured (config/app.php)
    $letterhead = (APP_COMPANY !== '' ? '<h1>' . e(APP_COMPANY) . '</h1>' : '')
                . (APP_ADDRESS !== '' ? '<div class="sub">' . e(APP_ADDRESS) . '</div>' : '');

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
    ' . $letterhead . '
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
        <tr><td class="lbl">GSIS No.</td><td>' . e($emp['gsis_no'] ?? '—') . '</td><td></td><td></td></tr>
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
        <tr><td class="lbl">Employment Status</td><td>' . e($emp['appointment_status'] ?? '—') . '</td></tr>
        <tr><td class="lbl">Record Status</td><td>' . e($emp['employment_status']) . '</td></tr>
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
// Carries the same scope, filters and ticked columns as the Reports page:
// an Overall report is grouped by department, with a summary first.
$f       = report_filters();
$chosen  = $f['columns'];

// An export of no columns is an empty sheet; send them back to choose.
if (!$chosen) {
    flash_set('danger', 'Choose the information to include before exporting the report.');
    redirect('/modules/reports/index.php');
}

$rows    = report_rows($pdo, $f);
$title   = report_title($pdo, $f);
$format  = (string)($_GET['format'] ?? 'pdf');
$columns = report_columns();
$groups  = report_groups($rows);
$grouped = $f['scope'] === 'overall' && count($groups) > 1;

$headers = array_map(fn(string $key) => $columns[$key][0], $chosen);
/** One row's ticked values, in printing order. */
$values  = fn(array $r) => array_map(fn(string $key) => $columns[$key][1]($r), $chosen);

if ($format === 'xlsx') {
    $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Employee Report');
    $sheet->fromArray([$title], null, 'A1');
    $sheet->fromArray(['Generated: ' . date('F j, Y g:i A')], null, 'A2');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

    $last_col = Coordinate::stringFromColumnIndex(max(1, count($chosen)));
    $line = 4;

    if ($grouped) {
        // Summary of the department sizes, then one section per department
        $sheet->fromArray(['Summary'], null, 'A' . $line);
        $sheet->getStyle('A' . $line++)->getFont()->setBold(true);
        foreach ($groups as $name => $members) {
            $sheet->fromArray([$name, count($members)], null, 'A' . $line++);
        }
        $sheet->fromArray(['Total', count($rows)], null, 'A' . $line);
        $sheet->getStyle('A' . $line . ':B' . $line)->getFont()->setBold(true);
        $line += 2;
    }

    foreach ($groups as $name => $members) {
        if ($grouped) {
            $sheet->fromArray([$name . ' (' . count($members) . ')'], null, 'A' . $line);
            $sheet->getStyle('A' . $line++)->getFont()->setBold(true);
        }
        $sheet->fromArray($headers, null, 'A' . $line);
        $sheet->getStyle('A' . $line . ':' . $last_col . $line)->getFont()->setBold(true);
        $line++;
        foreach ($members as $r) {
            $sheet->fromArray($values($r), null, 'A' . $line++);
        }
        $line++;   // blank line between sections
    }

    foreach (range(1, max(1, count($chosen))) as $index) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="employee_report_' . date('Ymd') . '.xlsx"');
    (new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
    exit;
}

// PDF list (landscape)
$head_row = '<tr>';
foreach ($headers as $heading) {
    $head_row .= '<th>' . e($heading) . '</th>';
}
$head_row .= '</tr>';

$body = '';
if (!$rows) {
    $body = '<table>' . $head_row . '<tr><td colspan="' . count($chosen)
          . '" align="center">No records match the selected filters.</td></tr></table>';
} else {
    if ($grouped) {
        $body .= '<h2>Summary</h2><table class="summary">';
        foreach ($groups as $name => $members) {
            $body .= '<tr><td>' . e($name) . '</td><td align="right">' . count($members) . '</td></tr>';
        }
        $body .= '<tr><td><strong>Total</strong></td><td align="right"><strong>' . count($rows)
               . '</strong></td></tr></table>';
    }
    foreach ($groups as $name => $members) {
        if ($grouped) {
            $body .= '<h2>' . e($name) . ' (' . count($members) . ')</h2>';
        }
        $body .= '<table>' . $head_row;
        foreach ($members as $r) {
            $body .= '<tr>';
            foreach ($values($r) as $value) {
                $body .= '<td>' . e((string)$value) . '</td>';
            }
            $body .= '</tr>';
        }
        $body .= '</table>';
    }
}

$html = '
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
    h1 { font-size: 15px; margin: 0; } .sub { color: #666; margin-bottom: 10px; }
    h2 { font-size: 11px; margin: 14px 0 4px; color: #c8102e;
         border-bottom: 1px solid #c8102e; padding-bottom: 2px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #999; padding: 3px 5px; text-align: left; }
    th { background: #c8102e; color: #fff; }
    table.summary { width: auto; }
    table.summary td { border: none; padding: 1px 12px 1px 0; }
</style>
<h1>' . (APP_COMPANY !== '' ? e(APP_COMPANY) . ' — ' : '') . e($title) . '</h1>
<div class="sub">Generated ' . e(date('F j, Y g:i A')) . ' · ' . count($rows) . ' record(s)</div>
' . $body;

stream_pdf($html, 'employee_report_' . date('Ymd') . '.pdf', 'landscape');
