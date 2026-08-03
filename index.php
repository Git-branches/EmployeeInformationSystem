<?php
// index.php
// Entry point: send visitors to the dashboard (or login if not authenticated).

require_once __DIR__ . '/config/app.php';

if (is_logged_in()) {
    redirect('/modules/dashboard/index.php');
}
redirect('/modules/auth/login.php');
