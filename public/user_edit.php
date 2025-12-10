<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

auth_require_login();
auth_require_role('ADMIN');

$pdo = db();

$id         = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit     = $id > 0;
$error      = null;
$success    = null;
$userRow    = [
    'username'  => '',
    'full_name' => '',
    'role'      => 'CASHIER',
    'is_active' => 1,
];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT id, username, full_name, role, is_active FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        http_response_code(404);
        echo 'User not found.';
        exit;
    }

    $userRow = $existing;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username   = trim($_POST['username'] ?? '');
    $fullName   = trim($_POST['full_name'] ?? '');
    $role       = $_POST['role'] ?? 'CASHIER';
    $isActive   = isset($_POST['is_active']) ? 1 : 0;
    $newPass    = $_POST['new_password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';

    // Basic validations
    if ($username === '') {
        $error = 'Username is required.';
    } elseif (!$isEdit && $newPass === '') {
        $error = 'Password is required for new user.';
    } elseif ($newPass !== '' && strlen($newPass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($newPass !== $confirm) {
        $error = 'New password and confirmation do not match.';
    } else {
        // Check username uniqueness
        $params = [':username' => $username];
        $sqlCheck = 'SELECT id FROM users WHERE username = :username';
        if ($isEdit) {
            $sqlCheck .= ' AND id <> :id';
            $params[':id'] = $id;
        }

        $stmt = $pdo->prepare($sqlCheck);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $error = 'Username is already taken.';
        } else {
            if ($isEdit) {
                // Update existing user
                if ($newPass !== '') {
                    $hash = password_hash($newPass, PASSWORD_BCRYPT);
                    $sql = 'UPDATE users
                            SET username = :username,
                                full_name = :full_name,
                                role = :role,
                                is_active = :is_active,
                                password_hash = :hash,
                                updated_at = NOW()
                            WHERE id = :id';
                    $params = [
                        ':username'  => $username,
                        ':full_name' => $fullName,
                        ':role'      => $role,
                        ':is_active' => $isActive,
                        ':hash'      => $hash,
                        ':id'        => $id,
                    ];
                } else {
                    $sql = 'UPDATE users
                            SET username = :username,
                                full_name = :full_name,
                                role = :role,
                                is_active = :is_active,
                                updated_at = NOW()
                            WHERE id = :id';
                    $params = [
                        ':username'  => $username,
                        ':full_name' => $fullName,
                        ':role'      => $role,
                        ':is_active' => $isActive,
                        ':id'        => $id,
                    ];
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $success = 'User updated successfully.';
            } else {
                // Create new user
                $hash = password_hash($newPass, PASSWORD_BCRYPT);
                $sql = 'INSERT INTO users (username, password_hash, full_name, role, is_active, created_at)
                        VALUES (:username, :hash, :full_name, :role, :is_active, NOW())';
                $params = [
                    ':username'  => $username,
                    ':hash'      => $hash,
                    ':full_name' => $fullName,
                    ':role'      => $role,
                    ':is_active' => $isActive,
                ];
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $newId = $pdo->lastInsertId();
                $success = 'User created successfully.';
                // refresh for editing newly created user
                header('Location: /POSM3/public/user_edit.php?id=' . $newId);
                exit;
            }

            // Refresh userRow for display after update
            $userRow['username']  = $username;
            $userRow['full_name'] = $fullName;
            $userRow['role']      = $role;
            $userRow['is_active'] = $isActive;
        }
    }
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card bg-white border-0 shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3">
                    <?= $isEdit ? 'Edit User' : 'New User' ?>
                </h5>
                <p class="text-muted" style="font-size: 0.9rem;">
                    <?= $isEdit ? 'Update user information and optionally reset password.' : 'Create a new system user.' ?>
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
                        <label for="username" class="form-label">Username</label>
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            id="username"
                            name="username"
                            required
                            value="<?= htmlspecialchars($userRow['username'], ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full name</label>
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            id="full_name"
                            name="full_name"
                            value="<?= htmlspecialchars($userRow['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <select id="role" name="role" class="form-select form-select-sm">
                            <option value="ADMIN"   <?= $userRow['role'] === 'ADMIN' ? 'selected' : '' ?>>ADMIN</option>
                            <option value="MANAGER" <?= $userRow['role'] === 'MANAGER' ? 'selected' : '' ?>>MANAGER</option>
                            <option value="CASHIER" <?= $userRow['role'] === 'CASHIER' ? 'selected' : '' ?>>CASHIER</option>
                        </select>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="is_active"
                            name="is_active"
                            value="1"
                            <?= (int)$userRow['is_active'] === 1 ? 'checked' : '' ?>
                        >
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>

                    <hr>

                    <div class="mb-2">
                        <label class="form-label">
                            <?= $isEdit ? 'Reset password (optional)' : 'Password' ?>
                        </label>
                        <input
                            type="password"
                            class="form-control form-control-sm mb-2"
                            name="new_password"
                            placeholder="<?= $isEdit ? 'Leave empty to keep current password' : 'Enter password' ?>"
                        >
                        <input
                            type="password"
                            class="form-control form-control-sm"
                            name="confirm_password"
                            placeholder="<?= $isEdit ? 'Confirm new password if changed' : 'Confirm password' ?>"
                        >
                        <div class="form-text text-muted" style="font-size: 0.8rem;">
                            Minimum 6 characters.
                        </div>
                    </div>

                    <div class="mt-3 d-flex justify-content-between">
                        <div>
                            <a href="/users_list.php" class="btn btn-outline-light btn-sm">
                                Back to list
                            </a>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary btn-sm">
                                Save
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>