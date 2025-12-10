<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

auth_require_login();
$user = auth_user();

$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($current === '' || $new === '' || $confirm === '') {
        $error = 'All fields are required.';
    } elseif ($new !== $confirm) {
        $error = 'New password and confirmation do not match.';
    } elseif (strlen($new) < 6) {
        $error = 'New password should be at least 6 characters.';
    } else {
        $pdo = db();
        // Get user with hash
        $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE id = :id');
        $stmt->execute([':id' => $user['id']]);
        $dbUser = $stmt->fetch();

        if (!$dbUser || !password_verify($current, $dbUser['password_hash'])) {
            $error = 'Current password is incorrect.';
        } else {
            $newHash = password_hash($new, PASSWORD_BCRYPT);
            $upd = $pdo->prepare('UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id');
            $upd->execute([
                ':hash' => $newHash,
                ':id'   => $user['id'],
            ]);
            $success = 'Password updated successfully.';
        }
    }
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card bg-white border-0 shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3">Change Password</h5>
                <p class="text-muted" style="font-size: 0.9rem;">
                    Update your account password. Use a strong, unique password.
                </p>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2">
                        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success py-2">
                        <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current password</label>
                        <input
                            type="password"
                            class="form-control form-control-sm"
                            id="current_password"
                            name="current_password"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label">New password</label>
                        <input
                            type="password"
                            class="form-control form-control-sm"
                            id="new_password"
                            name="new_password"
                            required
                            minlength="6"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm new password</label>
                        <input
                            type="password"
                            class="form-control form-control-sm"
                            id="confirm_password"
                            name="confirm_password"
                            required
                            minlength="6"
                        >
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm">
                        Save changes
                    </button>
                    <a href="/index.php" class="btn btn-outline-light btn-sm ms-2">
                        Cancel
                    </a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>