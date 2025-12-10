<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

auth_require_login();
$pdo  = db();
$user = auth_user();

$error   = null;
$success = null;

$prodStmt = $pdo->query("
    SELECT p.id, p.name, p.sku, p.stock_qty, u.symbol AS unit_symbol
    FROM products p
    LEFT JOIN units u ON u.id = p.base_unit_id
    WHERE p.is_active = 1
    ORDER BY p.name ASC
");
$products = $prodStmt->fetchAll();

$selectedProduct = null;
$selectedProductId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

if ($selectedProductId) {
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.sku, p.stock_qty, u.symbol AS unit_symbol
        FROM products p
        LEFT JOIN units u ON u.id = p.base_unit_id
        WHERE p.id = :id
    ");
    $stmt->execute([':id' => $selectedProductId]);
    $selectedProduct = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedProductId = (int)($_POST['product_id'] ?? 0);
    $adjustQty         = (float)($_POST['adjust_qty'] ?? 0);
    $reason            = trim($_POST['reason'] ?? '');

    if ($selectedProductId <= 0) {
        $error = 'Please select a product.';
    } elseif ($adjustQty == 0) {
        $error = 'Adjustment quantity cannot be zero.';
    } else {
        $stmt = $pdo->prepare("SELECT id, name, stock_qty FROM products WHERE id = :id");
        $stmt->execute([':id' => $selectedProductId]);
        $prod = $stmt->fetch();

        if (!$prod) {
            $error = 'Product not found.';
        } else {
            try {
                $pdo->beginTransaction();

                // Update stock
                $newStock = (float)$prod['stock_qty'] + $adjustQty;
                $up = $pdo->prepare("
                    UPDATE products
                    SET stock_qty = :new_stock, updated_at = NOW()
                    WHERE id = :id
                ");
                $up->execute([
                    ':new_stock' => $newStock,
                    ':id'        => $selectedProductId,
                ]);

                // Log movement
                $mv = $pdo->prepare("
                    INSERT INTO stock_movements
                        (product_id, movement_date, source_type, source_id, qty_change, note, created_by, created_at)
                    VALUES
                        (:product_id, NOW(), 'ADJUSTMENT', NULL, :qty_change, :note, :created_by, NOW())
                ");
                $mv->execute([
                    ':product_id' => $selectedProductId,
                    ':qty_change' => $adjustQty,
                    ':note'       => $reason !== '' ? $reason : null,
                    ':created_by' => $user ? $user['id'] : null,
                ]);

                $pdo->commit();

                $success = 'Stock adjusted successfully.';
                $selectedProductId = $prod['id'];
                $selectedProduct = [
                    'id'          => $prod['id'],
                    'name'        => $prod['name'],
                    'sku'         => $prod['sku'] ?? '',
                    'stock_qty'   => $newStock,
                    'unit_symbol' => $selectedProduct['unit_symbol'] ?? '',
                ];
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error adjusting stock: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card bg-white border-0 shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3">Stock Adjustment</h5>
                <p class="text-muted" style="font-size:0.9rem;">
                    Increase or decrease stock quantities manually (e.g. damage, manual count).
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

                <form method="get" class="mb-3">
                    <label class="form-label form-label-sm" for="product_id">Product</label>
                    <select name="product_id" id="product_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- Select product --</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" <?= $selectedProductId == $p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($p['sku'])): ?>
                                    (<?= htmlspecialchars($p['sku'], ENT_QUOTES, 'UTF-8') ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <?php if ($selectedProduct): ?>
                    <form method="post" novalidate>
                        <input type="hidden" name="product_id" value="<?= (int)$selectedProduct['id'] ?>">

                        <div class="mb-2">
                            <strong><?= htmlspecialchars($selectedProduct['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <?php if (!empty($selectedProduct['sku'])): ?>
                                <span class="text-muted" style="font-size:0.85rem;">
                                    (SKU: <?= htmlspecialchars($selectedProduct['sku'], ENT_QUOTES, 'UTF-8') ?>)
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <span class="text-muted" style="font-size:0.85rem;">
                                Current stock:
                            </span>
                            <span class="fw-semibold">
                                <?= number_format((float)$selectedProduct['stock_qty'], 4) ?>
                                <?= htmlspecialchars($selectedProduct['unit_symbol'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>

                        <div class="mb-3">
                            <label for="adjust_qty" class="form-label">Adjustment quantity</label>
                            <input
                                type="number"
                                step="0.0001"
                                class="form-control form-control-sm"
                                id="adjust_qty"
                                name="adjust_qty"
                                required
                            >
                            <div class="form-text text-muted" style="font-size:0.8rem;">
                                Positive = increase stock, negative = decrease stock.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="reason" class="form-label">Reason (optional)</label>
                            <textarea
                                class="form-control form-control-sm"
                                id="reason"
                                name="reason"
                                rows="2"
                            ></textarea>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="/POSM3/public/stock_report.php" class="btn btn-outline-secondary btn-sm">
                                Stock report
                            </a>
                            <button type="submit" class="btn btn-primary btn-sm">
                                Save adjustment
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="text-muted mb-0" style="font-size:0.85rem;">
                        Choose a product above to adjust its stock.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>