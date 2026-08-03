<?php
// includes/auth_check.php
// Session guard: include at the top of every protected page (after app.php).

declare(strict_types=1);

if (!is_logged_in()) {
    redirect('/modules/auth/login.php');
}
