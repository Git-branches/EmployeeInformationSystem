<?php
// modules/reports/report_data.php
// Shared query builder for the employee list report (used by index.php and export.php).

if (!defined('ROOT_PATH')) {
    exit('Direct access not allowed.');
}

function report_filters(): array
{
    return [
        'department' => (int)($_GET['department'] ?? 0),
        'type'       => (string)($_GET['type'] ?? ''),
        'status'     => (string)($_GET['status'] ?? ''),
    ];
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
    $sql = 'SELECT e.employee_no, e.last_name, e.first_name, e.middle_name, e.birthdate, e.sex,
                   e.contact_no, e.email, d.department_name, e.position,
                   e.applicant_type, e.employment_status, e.date_hired,
                   SUM(er.status IN ("Missing","Incomplete")) AS pending_reqs
            FROM employees e
            LEFT JOIN departments d ON d.department_id = e.department_id
            LEFT JOIN employee_requirements er ON er.employee_id = e.employee_id'
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' GROUP BY e.employee_id ORDER BY e.last_name, e.first_name';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
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
    return 'Employee Report' . ($parts ? ' — ' . implode(', ', $parts) : ' — All Employees');
}
