<?php
// modules/settings/index.php
// Administrator's own account settings: the profile printed on reports, and
// the password. Both sections post to this same page — each is a separate
// form, so filling one never disturbs the other.

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// Long enough to be worth having, short enough that nobody writes it down.
const PASSWORD_MIN_LENGTH = 8;

$user_id = (int)$_SESSION['user_id'];

// Which panel to show after a post, so an error is not hidden behind the
// other tab. 'profile' unless the password form was the one submitted.
$tab = ($_GET['tab'] ?? '') === 'password' ? 'password' : 'profile';

$errors = [];                      // field => message, for the profile form
$password_error = null;
$posted = [];                      // what the administrator typed, on error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $form = (string)($_POST['form'] ?? '');

    // ---- Admin profile ----------------------------------------------
    if ($form === 'profile') {
        $full_name = trim((string)($_POST['full_name'] ?? ''));
        $username  = trim((string)($_POST['username'] ?? ''));
        $position  = trim((string)($_POST['position'] ?? ''));
        $email     = trim((string)($_POST['email'] ?? ''));
        $posted    = compact('full_name', 'username', 'position', 'email');

        if ($full_name === '') {
            $errors['full_name'] = 'Your full name is required — it is printed on every report.';
        }
        if ($username === '') {
            $errors['username'] = 'A username is required to log in.';
        } elseif (mb_strlen($username) < 3) {
            $errors['username'] = 'The username must be at least 3 characters long.';
        } elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
            $errors['username'] = 'Use letters, numbers, dots, dashes or underscores only.';
        } else {
            // Taken by somebody else? The administrator keeping their own
            // username is not a duplicate.
            $stmt = $pdo->prepare('SELECT 1 FROM users WHERE username = ? AND user_id <> ?');
            $stmt->execute([$username, $user_id]);
            if ($stmt->fetchColumn()) {
                $errors['username'] = "The username \"$username\" is already taken.";
            }
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'That does not look like an e-mail address.';
        }

        if (!$errors) {
            try {
                $pdo->prepare(
                    'UPDATE users SET full_name = ?, username = ?, position = ?, email = ?
                     WHERE user_id = ?'
                )->execute([
                    $full_name,
                    $username,
                    $position !== '' ? $position : null,
                    $email !== '' ? $email : null,
                    $user_id,
                ]);

                // The navbar and the report signatory read these
                $_SESSION['full_name'] = $full_name;
                $_SESSION['username']  = $username;

                flash_set('success', 'Your profile has been saved. Reports generated from now on carry these details.');
                redirect('/modules/settings/index.php');
            } catch (PDOException $e) {
                // A username taken between the check above and the update
                if ($e->getCode() === '23000') {
                    $errors['username'] = "The username \"$username\" is already taken.";
                } else {
                    throw $e;
                }
            }
        }
        $tab = 'profile';
    }

    // ---- Change password ---------------------------------------------
    if ($form === 'password') {
        $tab     = 'password';
        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = ?');
        $stmt->execute([$user_id]);
        $hash = (string)$stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            $password_error = 'Your current password is not correct.';
        } elseif (mb_strlen($new) < PASSWORD_MIN_LENGTH) {
            $password_error = 'The new password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
        } elseif ($new !== $confirm) {
            $password_error = 'The new password and its confirmation do not match.';
        } elseif (password_verify($new, $hash)) {
            $password_error = 'The new password must be different from your current one.';
        } else {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $user_id]);

            // A fresh session id after a credential change, so a session
            // cookie captured earlier cannot go on being used.
            session_regenerate_id(true);

            flash_set('success', 'Your password has been changed. Use it the next time you log in.');
            redirect('/modules/settings/index.php?tab=password');
        }
    }
}

// Current values, with anything just typed kept over them after an error
$user = current_user($pdo) ?: [];
$val  = fn(string $k) => (string)($posted[$k] ?? $user[$k] ?? '');
$bad  = fn(string $k) => isset($errors[$k]) ? ' is-invalid' : '';

$page_title = 'Settings';
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-gear me-2"></i>Settings</h1>
</div>

<ul class="nav nav-tabs mb-0" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link<?= $tab === 'profile' ? ' active' : '' ?>" data-bs-toggle="tab"
                data-bs-target="#tab-profile" type="button" role="tab">
            <i class="bi bi-person-badge me-1"></i>Admin Profile
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link<?= $tab === 'password' ? ' active' : '' ?>" data-bs-toggle="tab"
                data-bs-target="#tab-password" type="button" role="tab">
            <i class="bi bi-shield-lock me-1"></i>Change Password
        </button>
    </li>
</ul>

<div class="tab-content">
    <!-- ---- Admin profile ------------------------------------------- -->
    <div class="tab-pane fade<?= $tab === 'profile' ? ' show active' : '' ?>" id="tab-profile" role="tabpanel">
        <div class="card shadow-sm border-top-0 rounded-top-0">
            <div class="card-body">
                <p class="text-muted small mb-4">
                    Your name and designation are printed under the signature line of
                    every report. Change them here and the next report you generate
                    carries the new details.
                </p>
                <form method="post" autocomplete="off" style="max-width:640px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form" value="profile">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="full_name">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control<?= $bad('full_name') ?>" id="full_name"
                                   name="full_name" maxlength="100" required value="<?= e($val('full_name')) ?>">
                            <div class="invalid-feedback"><?= e($errors['full_name'] ?? '') ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="position">Position / Designation</label>
                            <input type="text" class="form-control<?= $bad('position') ?>" id="position"
                                   name="position" maxlength="100" value="<?= e($val('position')) ?>"
                                   placeholder="e.g. HR Officer">
                            <div class="form-text">Printed under your name on reports. Blank prints “Administrator”.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="username">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control<?= $bad('username') ?>" id="username"
                                   name="username" maxlength="50" required value="<?= e($val('username')) ?>">
                            <div class="invalid-feedback"><?= e($errors['username'] ?? '') ?></div>
                            <div class="form-text">You log in with this. It must be unique.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">E-mail Address</label>
                            <input type="email" class="form-control<?= $bad('email') ?>" id="email"
                                   name="email" maxlength="100" value="<?= e($val('email')) ?>">
                            <div class="invalid-feedback"><?= e($errors['email'] ?? '') ?></div>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-save me-1"></i>Save Profile
                        </button>
                    </div>
                </form>

                <?php if (!empty($user['last_login'])): ?>
                    <div class="text-muted small mt-4 pt-3 border-top">
                        Last signed in <?= e(date('F j, Y g:i A', strtotime((string)$user['last_login']))) ?>.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ---- Change password ----------------------------------------- -->
    <div class="tab-pane fade<?= $tab === 'password' ? ' show active' : '' ?>" id="tab-password" role="tabpanel">
        <div class="card shadow-sm border-top-0 rounded-top-0">
            <div class="card-body">
                <?php if ($password_error): ?>
                    <div class="alert alert-danger py-2" style="max-width:520px"><?= e($password_error) ?></div>
                <?php endif; ?>
                <p class="text-muted small">
                    Signed in as <span class="fw-semibold"><?= e($user['username'] ?? '') ?></span>.
                    Choose a password only you know — the records in this system are personal
                    information of your employees.
                </p>
                <form method="post" autocomplete="off" style="max-width:520px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form" value="password">
                    <div class="mb-3">
                        <label class="form-label" for="current_password">Current Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="current_password" name="current_password"
                               required autocomplete="current-password">
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
                </form>
            </div>
        </div>
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
