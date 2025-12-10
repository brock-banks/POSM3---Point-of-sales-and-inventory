<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

auth_require_login();
$pdo = db();

$stmt = $pdo->query("
    SELECT
        p.id,
        p.name,
        p.sku,
        p.stock_qty,
        p.min_stock_qty,
        p.is_active,
        c.name AS category_name,
        u.symbol AS unit_symbol
    FROM products p
    LEFT JOIN product_categories c ON c.id = p.category_id
    LEFT JOIN units u ON u.id = p.base_unit_id
    ORDER BY p.name ASC
");
$products = $stmt->fetchAll();

require_once __DIR__ . '/../templates/header.php';
?>

<div class="row gy-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Stock Report</h4>
        <a href="/POSM3/public/stock_adjustment.php" class="btn btn-sm btn-outline-secondary">
            Stock adjustment
        </a>
    </div>

    <div class="col-12">
        <div class="card bg-white border-0 shadow-sm">
            <div class="card-body">
                <?php if (empty($products)): ?>
                    <p class="text-muted mb-0">No products found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                            <tr>
                                <th style="width:60px;">ID</th>
                                <th>Name</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th class="text-end">Stock</th>
                                <th class="text-end">Min stock</th>
                                <th class="text-center">Status</th>
                                <th style="width:80px;"></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($products as $p): ?>
                                <?php
                                $stock = (float)$p['stock_qty'];
                                $min   = (float)$p['min_stock_qty'];
                                $low   = $min > 0 && $stock < $min;
                                ?>
                                <tr>
                                    <td><?= (int)$p['id'] ?></td>
                                    <td><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($p['sku'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($p['category_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end <?= $low ? 'text-danger fw-semibold' : '' ?>">
                                        <?= number_format($stock, 4) ?>
                                        <span class="text-muted" style="font-size:0.8rem;">
                                            <?= htmlspecialchars($p['unit_symbol'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <?= number_format($min, 4) ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ((int)$p['is_active'] === 1): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="/POSM3/public/stock_movements.php?product_id=<?= (int)$p['id'] ?>"
                                           class="btn btn-sm btn-outline-secondary">
                                            Movements
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