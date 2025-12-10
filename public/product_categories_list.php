<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

auth_require_login();

$pdo = db();

$stmt = $pdo->query("
    SELECT
        id,
        name,
        description,
        is_active
       
    FROM product_categories
    ORDER BY name ASC
");
$categories = $stmt->fetchAll();

require_once __DIR__ . '/../templates/header.php';
?>

<div class="row gy-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Product Categories</h4>
        <a href="/POSM3/public/product_category_edit.php" class="btn btn-sm btn-primary">
            + New Category
        </a>
    </div>

    <div class="col-12">
        <div class="card bg-white border-0 shadow-sm">
            <div class="card-body">
                <?php if (empty($categories)): ?>
                    <p class="text-muted mb-0">No categories found. Click "New Category" to add one.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-white table-sm align-middle mb-0">
                            <thead>
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th class="text-center">Status</th>
                                <th style="width: 90px;"></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td><?= (int)$cat['id'] ?></td>
                                    <td><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($cat['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-center">
                                        <?php if ((int)$cat['is_active'] === 1): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="/POSM3/public/product_category_edit.php?id=<?= (int)$cat['id'] ?>"
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