<?php
// modules/employees/add.php
// Add a new employee/applicant.

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

$employee = null;
require __DIR__ . '/form.php';
