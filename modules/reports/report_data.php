<?php
// modules/reports/report_data.php
// Shared query builder for the employee list report (used by index.php and export.php).

if (!defined('ROOT_PATH')) {
    exit('Direct access not allowed.');
}

/**
 * Every column a report can carry, in the order they are printed:
 * key => [heading, how to read the value from a row].
 * The administrator ticks the ones to include; REPORT_DEFAULT_COLUMNS are
 * ticked when the page is first opened.
 */
function report_columns(): array
{
    $dash = fn(?string $v) => ($v === null || $v === '') ? '—' : $v;
    $date = fn(?string $v) => $v ? date('M j, Y', strtotime($v)) : '—';

    return [
        'employee_no'     => ['Employee No', fn(array $r) => $dash($r['employee_no'])],
        'name'            => ['Name', fn(array $r) => employee_display_name($r)],
        'birthdate'       => ['Birthdate', fn(array $r) => $date($r['birthdate'])],
        'sex'             => ['Sex', fn(array $r) => $dash($r['sex'])],
        'civil_status'    => ['Civil Status', fn(array $r) => $dash($r['civil_status'])],
        'contact_no'      => ['Contact', fn(array $r) => $dash($r['contact_no'])],
        'email'           => ['Email', fn(array $r) => $dash($r['email'])],
        'address'         => ['Address', fn(array $r) => $dash($r['address'])],
        'department_name' => ['Department', fn(array $r) => $r['department_name'] ?? 'Unassigned'],
        'position'        => ['Position', fn(array $r) => $dash($r['position'])],
        'applicant_type'  => ['Type', fn(array $r) => $dash($r['applicant_type'])],
        'employment_status' => ['Record Status', fn(array $r) => $dash($r['employment_status'])],
        'appointment_status' => ['Employment Status', fn(array $r) => $dash($r['appointment_status'])],
        'date_hired'      => ['Date Hired', fn(array $r) => $date($r['date_hired'])],
        'monthly_salary'  => ['Monthly Salary', fn(array $r) => peso($r['monthly_salary'])],
        'daily_salary'    => ['Daily Salary', fn(array $r) => peso(daily_salary($r['monthly_salary']))],
        'sss_no'          => ['SSS No', fn(array $r) => $dash($r['sss_no'])],
        'philhealth_no'   => ['PhilHealth No', fn(array $r) => $dash($r['philhealth_no'])],
        'pagibig_no'      => ['Pag-IBIG No', fn(array $r) => $dash($r['pagibig_no'])],
        'tin_no'          => ['TIN No', fn(array $r) => $dash($r['tin_no'])],
        'gsis_no'         => ['GSIS No', fn(array $r) => $dash($r['gsis_no'])],
        'pending_reqs'    => ['Pending Reqs', fn(array $r) => (string)(int)$r['pending_reqs']],
    ];
}

/** Columns whose text is long enough to wrap rather than widen the table. */
const REPORT_WRAPPING_COLUMNS = ['address', 'email', 'name', 'position', 'department_name'];

/** Columns ticked before the administrator chooses: the long-standing set. */
const REPORT_DEFAULT_COLUMNS = [
    'employee_no', 'name', 'birthdate', 'sex', 'contact_no',
    'department_name', 'position', 'applicant_type', 'employment_status',
    'date_hired', 'pending_reqs',
];

function report_filters(): array
{
    // scope: 'overall' groups every employee under their department;
    // 'department' reports on one chosen department only.
    $scope = (string)($_GET['scope'] ?? 'overall');
    $scope = in_array($scope, ['overall', 'department'], true) ? $scope : 'overall';
    $department = (int)($_GET['department'] ?? 0);

    // Picking a department only narrows the report in 'department' scope
    if ($scope !== 'department') {
        $department = 0;
    }

    return [
        'scope'      => $scope,
        'department' => $department,
        'type'       => (string)($_GET['type'] ?? ''),
        'status'     => (string)($_GET['status'] ?? ''),
        'columns'    => report_selected_columns(),
    ];
}

/**
 * The ticked columns, in printing order. Falls back to the defaults when the
 * page is opened without a choice, and to Name alone if every box was cleared
 * (a report of nothing but row numbers is of no use to anyone).
 */
function report_selected_columns(): array
{
    $available = array_keys(report_columns());

    if (!isset($_GET['columns'])) {
        return REPORT_DEFAULT_COLUMNS;
    }
    $chosen = array_intersect($available, (array)$_GET['columns']);
    return $chosen ? array_values($chosen) : ['name'];
}

/** Filter/column choices as a query string, for the export links. */
function report_query(array $f): string
{
    return http_build_query([
        'scope'      => $f['scope'],
        'department' => $f['department'] ?: '',
        'type'       => $f['type'],
        'status'     => $f['status'],
        'columns'    => $f['columns'],
    ]);
}

function report_rows(PDO $pdo, array $f): array
{
    $where  = [];
    $params = [];
    if ($f['department'] > 0) {
        $where[] = 'e.department_id = ?';
        $params[] = $f['department'];
    }
    if (in_array($f['type'], ['New', 'Existing'], true)) {
        $where[] = 'e.applicant_type = ?';
        $params[] = $f['type'];
    }
    if (in_array($f['status'], ['Applicant', 'Active', 'Inactive', 'Terminated'], true)) {
        $where[] = 'e.employment_status = ?';
        $params[] = $f['status'];
    }
    // Every column report_columns() can print is selected, so ticking more
    // boxes never needs another query.
    $sql = 'SELECT e.employee_no, e.last_name, e.first_name, e.middle_name, e.no_middle_name,
                   e.name_extension, e.birthdate, e.sex, e.civil_status,
                   e.contact_no, e.email, e.address,
                   e.sss_no, e.philhealth_no, e.pagibig_no, e.tin_no, e.gsis_no,
                   d.department_name, e.position,
                   e.applicant_type, e.employment_status, s.status_name AS appointment_status,
                   e.date_hired, e.monthly_salary,
                   SUM(er.status IN ("Missing","Incomplete")) AS pending_reqs
            FROM employees e
            LEFT JOIN departments d ON d.department_id = e.department_id
            LEFT JOIN employment_statuses s ON s.employment_status_id = e.employment_status_id
            LEFT JOIN employee_requirements er ON er.employee_id = e.employee_id'
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' GROUP BY e.employee_id'
         . ' ORDER BY d.department_name IS NULL, d.department_name, e.last_name, e.first_name';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Rows grouped by department, as "DEPARTMENT NAME" => rows. Employees with
 * no department land under "Unassigned", kept last so the named departments
 * read first.
 */
function report_groups(array $rows): array
{
    $groups = [];
    foreach ($rows as $row) {
        $groups[$row['department_name'] ?? 'Unassigned'][] = $row;
    }
    if (isset($groups['Unassigned']) && count($groups) > 1) {
        $unassigned = $groups['Unassigned'];
        unset($groups['Unassigned']);
        $groups['Unassigned'] = $unassigned;
    }
    return $groups;
}

function report_title(PDO $pdo, array $f): string
{
    $parts = [];
    if ($f['department'] > 0) {
        $stmt = $pdo->prepare('SELECT department_name FROM departments WHERE department_id = ?');
        $stmt->execute([$f['department']]);
        $parts[] = ($stmt->fetchColumn() ?: 'Unknown') . ' Department';
    }
    if ($f['type'] !== '') {
        $parts[] = $f['type'] . ' Applicants';
    }
    if ($f['status'] !== '') {
        $parts[] = 'Status: ' . $f['status'];
    }
    if (!$parts) {
        $parts[] = 'All Employees by Department';
    }
    return 'Employee Report — ' . implode(', ', $parts);
}
