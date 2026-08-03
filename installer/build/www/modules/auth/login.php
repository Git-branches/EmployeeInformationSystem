<?php
// modules/auth/login.php
// Administrator login form and authentication.

require_once __DIR__ . '/../../config/app.php';

if (is_logged_in()) {
    redirect('/modules/dashboard/index.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $stmt = $pdo->prepare('SELECT user_id, username, password_hash, full_name FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = (int)$user['user_id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];

            $pdo->prepare('UPDATE users SET last_login = NOW() WHERE user_id = ?')
                ->execute([$user['user_id']]);

            redirect('/modules/dashboard/index.php');
        }

        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/favicon.svg">
<link rel="preload" as="font" type="font/woff2" crossorigin href="<?= BASE_URL ?>/assets/css/fonts/baloo2-latin.woff2">
<link rel="preload" as="font" type="font/woff2" crossorigin href="<?= BASE_URL ?>/assets/css/fonts/bootstrap-icons.woff2">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<?php $has_bg = is_file(ROOT_PATH . '/assets/img/building.jpg'); ?>
<div class="login-page<?= $has_bg ? ' has-photo' : '' ?>"<?= $has_bg ? ' style="--login-bg:url(\'' . BASE_URL . '/assets/img/building.jpg\')"' : '' ?>>
    <div class="card shadow login-card">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <?php if (is_file(ROOT_PATH . '/assets/img/logo.png')): ?>
                    <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="Jollibee" height="110" class="mb-2">
                <?php else: ?>
                    <span class="brand-bee">🐝</span>
                <?php endif; ?>
                <h1 class="h4 mt-2 mb-0"><?= e(APP_NAME) ?></h1>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-danger py-2"><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post" autocomplete="off">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="username">Username</label>
                    <input type="text" class="form-control" id="username" name="username"
                           value="<?= e($_POST['username'] ?? '') ?>" required autofocus>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" required>
                        <button type="button" class="btn btn-outline-secondary" id="toggle-password"
                                tabindex="-1" title="Show password">
                            <i class="bi bi-eye" id="toggle-password-icon"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-danger w-100">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Log In
                </button>
            </form>
        </div>
    </div>
</div>
<script>
document.getElementById('toggle-password').addEventListener('click', function () {
    const input = document.getElementById('password');
    const icon = document.getElementById('toggle-password-icon');
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    this.title = show ? 'Hide password' : 'Show password';
});
</script>
</body>
</html>
