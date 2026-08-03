<?php
// modules/backup/google_oauth.php
// Connects the system to the administrator's Google Drive:
//   - no parameters  → send the admin to Google's consent screen
//   - ?code=...      → Google's callback; exchange it for a refresh token
//   - ?disconnect=1  → forget the stored authorization

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once ROOT_PATH . '/vendor/autoload.php';
require_once ROOT_PATH . '/includes/google_drive.php';

if (isset($_GET['disconnect'])) {
    if (is_file(GOOGLE_TOKEN)) {
        unlink(GOOGLE_TOKEN);
    }
    flash_set('success', 'Google Drive has been disconnected.');
    redirect('/modules/backup/index.php');
}

if (!is_file(GOOGLE_OAUTH_CLIENT)) {
    flash_set('danger', 'OAuth client file is missing. Save it as config/google_oauth_client.json first.');
    redirect('/modules/backup/index.php');
}

$client = google_oauth_client();

// Google sends the admin back here with ?code=... after they approve.
if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode((string)$_GET['code']);

    if (isset($token['error'])) {
        flash_set('danger', 'Google authorization failed: ' . e((string)$token['error_description'] ?? $token['error']));
        redirect('/modules/backup/index.php');
    }
    if (empty($token['refresh_token'])) {
        flash_set('danger', 'Google did not return a refresh token. Remove the app at myaccount.google.com/permissions and connect again.');
        redirect('/modules/backup/index.php');
    }

    file_put_contents(GOOGLE_TOKEN, json_encode($token));
    flash_set('success', 'Google Drive connected. Backups can now be synced automatically.');
    redirect('/modules/backup/index.php');
}

if (isset($_GET['error'])) {
    flash_set('danger', 'Google authorization was cancelled.');
    redirect('/modules/backup/index.php');
}

// No code yet — start the consent flow.
header('Location: ' . $client->createAuthUrl());
exit;
