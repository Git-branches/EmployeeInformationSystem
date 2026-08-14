<?php
// modules/auth/change_password.php
// Lets the signed-in administrator replace their own password. Reached from
// the account menu in the navbar.

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// Long enough to be worth having, short enough that nobody writes it down.
const PASSWORD_MIN_LENGTH = 8;

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $current = (string)($_POST['current_password'] ?? '');
    $new     = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = ?');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $hash = (string)$stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        $error = 'Your current password is not correct.';
    } elseif (mb_strlen($new) < PASSWORD_MIN_LENGTH) {
        $error = 'The new password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
    } elseif ($new !== $confirm) {
        $error = 'The new password and its confirmation do not match.';
    } elseif (password_verify($new, $hash)) {
        $error = 'The new password must be different from your current one.';
    } else {
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), (int)$_SESSION['user_id']]);

        // A fresh session id after a credential change, so a session cookie
        // captured earlier cannot go on being used.
        session_regenerate_id(true);

        flash_set('success', 'Your password has been changed. Use it the next time you log in.');
        redirect('/modules/dashboard/index.php');
    }
}

$page_title = 'Change Password';
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-shield-lock me-2"></i>Change Password</h1>
    <a href="<?= BASE_URL ?>/modules/dashboard/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="card shadow-sm" style="max-width:520px">
    <div class="card-body">
        <p class="text-muted small">
            Signed in as <span class="fw-semibold"><?= e($_SESSION['username'] ?? '') ?></span>.
            Choose a password only you know — the records in this system are personal
            information of your employees.
        </p>
        <form method="post" autocomplete="off">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="current_password">Current Password <span class="text-danger">*</span></label>
                <input type="password" class="form-control" id="current_password" name="current_password"
                       required autofocus autocomplete="current-password">
            </div>
            <div class="mb-3">
                <label class="form-label" for="new_password">New Password <span class="text-danger">*</span></label>
                <input type="password" class="form-control" id="new_password" name="new_password"
                       required minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password">
                <div class="form-text">At least <?= PASSWORD_MIN_LENGTH ?> characters.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="confirm_password">Confirm New Password <span class="text-danger">*</span></label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                       required minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password">
            </div>
            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" id="show-passwords">
                <label class="form-check-label" for="show-passwords">Show passwords</label>
            </div>
            <button type="submit" class="btn btn-danger">
                <i class="bi bi-save me-1"></i>Update Password
            </button>
            <a href="<?= BASE_URL ?>/modules/dashboard/index.php" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>

<script>
// One switch for all three boxes, rather than an eye button on each.
document.getElementById('show-passwords').addEventListener('change', function () {
    const type = this.checked ? 'text' : 'password';
    ['current_password', 'new_password', 'confirm_password'].forEach(function (id) {
        document.getElementById(id).type = type;
    });
});
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
