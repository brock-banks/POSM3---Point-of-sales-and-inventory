<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
auth_require_login();

$pdo = db();

// Fetch products with category and base unit names
$sql = "
    SELECT
        p.id,
        p.name,
        p.sku,
        p.stock_qty,
        p.price_default,
        p.is_active,
        c.name AS category_name,
        u.symbol AS base_unit_symbol,
         p.image_path
    FROM products p
    LEFT JOIN product_categories c ON c.id = p.category_id
    LEFT JOIN units u ON u.id = p.base_unit_id
    ORDER BY p.name ASC
";
$stmt = $pdo->query($sql);
$products = $stmt->fetchAll();

require_once __DIR__ . '/../templates/header.php';
?>

<div class="row gy-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Products</h4>
        <a href="/POSM3/public/product_edit.php" class="btn btn-sm btn-primary">
            + New Product
        </a>
    </div>

    <div class="col-12">
        <div class="card bg-white border-0 shadow-sm">
            <div class="card-body">
                <?php if (empty($products)): ?>
                    <p class="text-muted mb-0">No products found. Click "New Product" to add one.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-white table-sm align-middle mb-0">
                            <thead>
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th>Name</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th class="text-end">Stock</th>
                                <th class="text-end">Price (base)</th>
                                <th class="text-center">Status</th>
                                <th style="width: 90px;"></th>
                                <th style="width:60px;">Image</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($products as $p): ?>
                                <tr>
                                    <td><?= (int)$p['id'] ?></td>
                                    <td><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($p['sku'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($p['category_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end">
                                        <?= number_format((float)$p['stock_qty'], 2) ?>
                                        <span class="text-muted" style="font-size: 0.8rem;">
                                            <?= htmlspecialchars($p['base_unit_symbol'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <?= number_format((float)$p['price_default'], 2) ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ((int)$p['is_active'] === 1): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="/product_edit.php?id=<?= (int)$p['id'] ?>"
                                           class="btn btn-sm btn-outline-light">
                                            Edit
                                        </a>
                                    </td>
                                    <td>
    <?php if (!empty($p['image_path'])): ?>
        <img src="<?= htmlspecialchars($p['image_path'], ENT_QUOTES, 'UTF-8') ?>"
             style="width:40px;height:40px;object-fit:cover;border-radius:3px;"
             alt="">
    <?php else: ?>
        <span class="text-muted" style="font-size:0.7rem;">No image</span>
    <?php endif; ?>
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