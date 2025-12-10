<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

auth_require_login();

$pdo = db();

$sql = "
    SELECT
        p.id,
        p.name,
        p.sku,
        p.stock_qty,
        p.min_stock_qty,
        p.is_active,
        c.name AS category_name,
        u.symbol AS base_unit_symbol
    FROM products p
    LEFT JOIN product_categories c ON c.id = p.category_id
    LEFT JOIN units u ON u.id = p.base_unit_id
    WHERE p.is_active = 1
      AND p.min_stock_qty IS NOT NULL
      AND p.min_stock_qty > 0
      AND p.stock_qty < p.min_stock_qty
    ORDER BY (p.min_stock_qty - p.stock_qty) DESC
";
$stmt = $pdo->query($sql);
$products = $stmt->fetchAll();

require_once __DIR__ . '/../templates/header.php';
?>

<div class="row gy-3">
    <div class="col-12">
        <h4 class="mb-3">Low Stock Report</h4>
        <p class="text-muted" style="font-size: 0.9rem;">
            Products where current stock is below the minimum stock level.
        </p>
    </div>

    <div class="col-12">
        <div class="card bg-white border-0 shadow-sm">
            <div class="card-body">
                <?php if (empty($products)): ?>
                    <p class="text-muted mb-0">No low‑stock products at the moment.</p>
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
                                <th class="text-end">Minimum</th>
                                <th class="text-end">Short by</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($products as $p): ?>
                                <?php
                                $stock = (float)$p['stock_qty'];
                                $min   = (float)$p['min_stock_qty'];
                                $short = $min - $stock;
                                ?>
                                <tr>
                                    <td><?= (int)$p['id'] ?></td>
                                    <td><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($p['sku'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($p['category_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end">
                                        <?= number_format($stock, 2) ?>
                                        <span class="text-muted" style="font-size: 0.8rem;">
                                            <?= htmlspecialchars($p['base_unit_symbol'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <?= number_format($min, 2) ?>
                                    </td>
                                    <td class="text-end text-danger">
                                        <?= number_format($short, 2) ?>
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