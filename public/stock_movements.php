<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

auth_require_login();
$pdo = db();

$prodStmt = $pdo->query("
    SELECT p.id, p.name, p.sku, u.symbol AS unit_symbol
    FROM products p
    LEFT JOIN units u ON u.id = p.base_unit_id
    WHERE p.is_active = 1
    ORDER BY p.name ASC
");
$products = $prodStmt->fetchAll();

$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$product   = null;
$movements = [];

if ($productId) {
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.sku, p.stock_qty, u.symbol AS unit_symbol
        FROM products p
        LEFT JOIN units u ON u.id = p.base_unit_id
        WHERE p.id = :id
    ");
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch();

    if ($product) {
        $mv = $pdo->prepare("
            SELECT
                m.id,
                m.movement_date,
                m.source_type,
                m.source_id,
                m.qty_change,
                m.note
            FROM stock_movements m
            WHERE m.product_id = :pid
            ORDER BY m.movement_date ASC, m.id ASC
        ");
        $mv->execute([':pid' => $productId]);
        $movements = $mv->fetchAll();
    }
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="row gy-3">
    <div class="col-12">
        <h4 class="mb-3">Stock Movements</h4>
    </div>

    <div class="col-12">
        <form method="get" class="row g-2 mb-3">
            <div class="col-sm-6 col-md-4 col-lg-3">
                <label class="form-label form-label-sm" for="product_id">Product</label>
                <select name="product_id" id="product_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Select product --</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= $productId == $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>
                            <?php if (!empty($p['sku'])): ?>
                                (<?= htmlspecialchars($p['sku'], ENT_QUOTES, 'UTF-8') ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <div class="col-12">
        <div class="card bg-white border-0 shadow-sm">
            <div class="card-body">
                <?php if (!$productId): ?>
                    <p class="text-muted mb-0">Select a product to see its stock movements.</p>
                <?php elseif (!$product): ?>
                    <p class="text-danger mb-0">Product not found.</p>
                <?php else: ?>
                    <div class="mb-2">
                        <strong><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <?php if (!empty($product['sku'])): ?>
                            <span class="text-muted" style="font-size:0.85rem;">
                                (SKU: <?= htmlspecialchars($product['sku'], ENT_QUOTES, 'UTF-8') ?>)
                            </span>
                        <?php endif; ?>
                        <div class="text-muted" style="font-size:0.85rem;">
                            Current stock:
                            <span class="fw-semibold">
                                <?= number_format((float)$product['stock_qty'], 4) ?>
                                <?= htmlspecialchars($product['unit_symbol'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                    </div>

                    <?php if (empty($movements)): ?>
                        <p class="text-muted mb-0">No stock movements recorded for this product.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                <tr>
                                    <th style="width:60px;">ID</th>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th class="text-end">Qty change</th>
                                    <th>Note</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                $running = 0;
                                foreach ($movements as $m):
                                    $qty = (float)$m['qty_change'];
                                    $running += $qty;
                                    $cls = $qty > 0 ? 'text-success' : ($qty < 0 ? 'text-danger' : 'text-muted');
                                ?>
                                    <tr>
                                        <td><?= (int)$m['id'] ?></td>
                                        <td><?= htmlspecialchars($m['movement_date'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($m['source_type'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="text-end <?= $cls ?>">
                                            <?= number_format($qty, 4) ?>
                                        </td>
                                        <td><?= htmlspecialchars($m['note'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-2 text-muted" style="font-size:0.85rem;">
                            Positive = stock in, negative = stock out. Movements include purchases, sales, and adjustments.
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>