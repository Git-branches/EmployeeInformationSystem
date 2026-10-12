<?php
// modules/employees/list_query.php
// Search and filter query behind the employee list. Included by index.php
// (full page) and by search.php (rows only, for search-as-you-type), so both
// always show exactly the same records.

if (!defined('ROOT_PATH')) {
    exit('Direct access not allowed.');
}

$search     = trim((string)($_GET['q'] ?? ''));
$dept_id    = (int)($_GET['department'] ?? 0);
$app_type   = (string)($_GET['type'] ?? '');
$emp_status = (string)($_GET['status'] ?? '');

$where  = [];
$params = [];
if ($search !== '') {
    // Every word typed must appear somewhere in the name, employee number or
    // position, in any order. So "rhon romero", "romero, rhon" and
    // "romero jr" all find ROMERO, RHON J. JR.
    $haystack = 'CONCAT_WS(" ", e.first_name, e.middle_name, e.last_name,
                             e.name_extension, e.employee_no, e.position)';
    foreach (preg_split('/[\s,]+/', $search, -1, PREG_SPLIT_NO_EMPTY) as $word) {
        // % and _ are LIKE wildcards; a typed one is meant literally.
        $where[]  = "$haystack LIKE ? ESCAPE '!'";
        $params[] = '%' . addcslashes($word, '%_!') . '%';
    }
}
if ($dept_id > 0) {
    $where[] = 'e.department_id = ?';
    $params[] = $dept_id;
}
if (in_array($app_type, ['New', 'Existing'], true)) {
    $where[] = 'e.applicant_type = ?';
    $params[] = $app_type;
}
if (in_array($emp_status, ['Applicant', 'Active', 'Inactive', 'Terminated'], true)) {
    $where[] = 'e.employment_status = ?';
    $params[] = $emp_status;
}

$sql = 'SELECT e.*, d.department_name,
               SUM(er.status IN ("Missing","Incomplete")) AS pending_reqs
        FROM employees e
        LEFT JOIN departments d ON d.department_id = e.department_id
        LEFT JOIN employee_requirements er ON er.employee_id = e.employee_id'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' GROUP BY e.employee_id ORDER BY e.last_name, e.first_name';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();

$status_badge = [
    'Applicant'  => 'text-bg-warning',
    'Active'     => 'text-bg-success',
    'Inactive'   => 'text-bg-secondary',
    'Terminated' => 'text-bg-dark',
];
