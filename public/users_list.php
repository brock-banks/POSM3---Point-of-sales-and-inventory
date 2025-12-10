<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

auth_require_login();
auth_require_role('ADMIN');

$pdo = db();
$stmt = $pdo->query('SELECT id, username, full_name, role, is_active, created_at, last_login_at
                     FROM users
                     ORDER BY id ASC');
$users = $stmt->fetchAll();

require_once __DIR__ . '/../templates/header.php';
?>

<div class="row gy-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Users Management</h4>
        <a href="/POSM3/public/user_edit.php" class="btn btn-sm btn-primary">
            + New User
        </a>
    </div>

    <div class="col-12">
        <div class="card bg-white border-0 shadow-sm">
            <div class="card-body">
                <?php if (empty($users)): ?>
                    <p class="text-muted mb-0">No users found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-white table-sm align-middle mb-0">
                            <thead>
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th>Username</th>
                                <th>Full name</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last login</th>
                                <th style="width: 90px;"></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?= (int)$u['id'] ?></td>
                                    <td><?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($u['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?= htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ((int)$u['is_active'] === 1): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 0.8rem;">
                                        <?= $u['last_login_at'] ? htmlspecialchars($u['last_login_at'], ENT_QUOTES, 'UTF-8') : '-' ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="/POSM3/public/user_edit.php?id=<?= (int)$u['id'] ?>"
                                           class="btn btn-sm btn-outline-light">
                                            Edit
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>