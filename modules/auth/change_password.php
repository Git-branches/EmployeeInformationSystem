<?php
// modules/auth/change_password.php
// The password form now lives with the rest of the account settings. This
// file stays so older links and bookmarks still arrive at the right place.

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

redirect('/modules/settings/index.php?tab=password');
