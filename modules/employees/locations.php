<?php
// modules/employees/locations.php
// JSON options for the cascading address dropdowns on the employee form:
//   ?province=NAME                     → its cities/municipalities
//   ?province=NAME&municipality=NAME   → that municipality's barangays

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

$province     = (string)($_GET['province'] ?? '');
$municipality = (string)($_GET['municipality'] ?? '');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=86400');
echo json_encode($municipality !== ''
    ? ph_barangays($province, $municipality)
    : ph_municipalities($province));
