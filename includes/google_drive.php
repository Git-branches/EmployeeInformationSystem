<?php
// includes/google_drive.php
// Builds an authorized Google Drive client.
//
// Two credential styles are supported:
//   1. OAuth 2.0 (recommended for personal Gmail) — the administrator
//      authorizes once; backups are owned by them and use their storage.
//   2. Service account — only usable with Google Workspace Shared Drives,
//      because service accounts have no storage quota of their own.

declare(strict_types=1);

/** Redirect URI that must be registered in the Google Cloud console. */
function google_redirect_uri(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . BASE_URL . '/modules/backup/google_oauth.php';
}

/** 'oauth' | 'service_account' | 'none' — which credentials are installed. */
function google_credential_type(): string
{
    if (is_file(GOOGLE_OAUTH_CLIENT)) {
        return 'oauth';
    }
    if (is_file(GOOGLE_CREDENTIALS)) {
        return 'service_account';
    }
    return 'none';
}

/** True when OAuth has been completed and a refresh token is stored. */
function google_is_connected(): bool
{
    if (google_credential_type() === 'service_account') {
        return true;
    }
    return is_file(GOOGLE_TOKEN);
}

/** Base OAuth client (no token applied yet). */
function google_oauth_client(): Google\Client
{
    $client = new Google\Client();
    $client->setAuthConfig(GOOGLE_OAUTH_CLIENT);
    $client->setRedirectUri(google_redirect_uri());
    $client->addScope(Google\Service\Drive::DRIVE_FILE);
    $client->setAccessType('offline');       // needed to receive a refresh token
    $client->setPrompt('consent');
    return $client;
}

/**
 * Authorized client ready for API calls, or null when not configured.
 * Refreshes an expired access token automatically and re-saves it.
 */
function google_authorized_client(?string &$error = null): ?Google\Client
{
    $type = google_credential_type();

    if ($type === 'service_account') {
        $client = new Google\Client();
        $client->setAuthConfig(GOOGLE_CREDENTIALS);
        $client->addScope(Google\Service\Drive::DRIVE_FILE);
        return $client;
    }

    if ($type !== 'oauth') {
        $error = 'Google Drive is not configured yet.';
        return null;
    }
    if (!is_file(GOOGLE_TOKEN)) {
        $error = 'Google Drive is not connected yet. Click "Connect Google Drive" first.';
        return null;
    }

    $client = google_oauth_client();
    $token  = json_decode((string)file_get_contents(GOOGLE_TOKEN), true);
    if (!is_array($token)) {
        $error = 'Stored Google token is unreadable. Please reconnect.';
        return null;
    }
    $client->setAccessToken($token);

    if ($client->isAccessTokenExpired()) {
        $refresh = $client->getRefreshToken() ?: ($token['refresh_token'] ?? null);
        if (!$refresh) {
            $error = 'Google authorization expired and no refresh token is stored. Please reconnect.';
            return null;
        }
        $new = $client->fetchAccessTokenWithRefreshToken($refresh);
        if (isset($new['error'])) {
            $error = 'Could not refresh Google authorization: ' . $new['error'];
            return null;
        }
        $new['refresh_token'] = $new['refresh_token'] ?? $refresh;
        file_put_contents(GOOGLE_TOKEN, json_encode($new));
    }

    return $client;
}
