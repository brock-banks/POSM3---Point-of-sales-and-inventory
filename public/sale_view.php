<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

auth_require_login();
$pdo = db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo 'Missing sale id.';
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        s.*,
        c.name AS customer_name
    FROM sales s
    LEFT JOIN customers c ON c.id = s.customer_id
    WHERE s.id = :id
");
$stmt->execute([':id' => $id]);
$sale = $stmt->fetch();

if (!$sale) {
    http_response_code(404);
    echo 'Sale not found.';
    exit;
}

$itemStmt = $pdo->prepare("
    SELECT
        si.product_id,
        si.unit_id,
        si.qty_unit,
        si.qty_base,
        si.unit_price,
        si.discount,
        si.subtotal,
        p.name AS product_name,
        p.sku,
        u.name AS unit_name,
        u.symbol AS unit_symbol
    FROM sale_items si
    JOIN products p ON p.id = si.product_id
    LEFT JOIN units u ON u.id = si.unit_id
    WHERE si.sale_id = :id
");
$itemStmt->execute([':id' => $id]);
$items = $itemStmt->fetchAll();

require_once __DIR__ . '/../templates/header.php';
?>

<div class="row gy-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Sale #<?= (int)$sale['id'] ?></h4>
        <a href="/POSM3/public/sales_report.php" class="btn btn-sm btn-outline-secondary">
            Back to sales report
        </a>
    </div>

    <div class="col-md-6">
        <div class="card bg-white border-0 shadow-sm">
            <div class="card-body">
                <h6 class="card-title mb-3">Summary</h6>
                <dl class="row mb-0" style="font-size:0.9rem;">
                    <dt class="col-sm-4">Date</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars($sale['sale_date'], ENT_QUOTES, 'UTF-8') ?></dd>

                    <dt class="col-sm-4">Customer</dt>
                    <dd class="col-sm-8">
                        <?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in', ENT_QUOTES, 'UTF-8') ?>
                    </dd>

                    <dt class="col-sm-4">Payment type</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars($sale['payment_type'], ENT_QUOTES, 'UTF-8') ?></dd>

                    <dt class="col-sm-4">Status</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars($sale['status'], ENT_QUOTES, 'UTF-8') ?></dd>

                    <dt class="col-sm-4">Total</dt>
                    <dd class="col-sm-8">$ <?= number_format((float)$sale['total_amount'], 2) ?></dd>

                    <dt class="col-sm-4">Paid</dt>
                    <dd class="col-sm-8">$ <?= number_format((float)$sale['paid_amount'], 2) ?></dd>

                    <dt class="col-sm-4">Credit</dt>
                    <dd class="col-sm-8">$ <?= number_format((float)$sale['credit_amount'], 2) ?></dd>

                    <?php if (!empty($sale['notes'])): ?>
                        <dt class="col-sm-4">Notes</dt>
                        <dd class="col-sm-8">
                            <?= nl2br(htmlspecialchars($sale['notes'], ENT_QUOTES, 'UTF-8')) ?>
                        </dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card bg-white border-0 shadow-sm">
            <div class="card-body">
                <h6 class="card-title mb-2">Items</h6>

                <?php if (empty($items)): ?>
                    <p class="text-muted mb-0">No items found for this sale.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-end" style="width:80px;">Qty</th>
                                <th class="text-end" style="width:90px;">Unit price</th>
                                <th class="text-end" style="width:90px;">Discount</th>
                                <th class="text-end" style="width:110px;">Subtotal</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($items as $it): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($it['product_name'], ENT_QUOTES, 'UTF-8') ?>
                                        <?php if (!empty($it['sku'])): ?>
                                            <div class="text-muted" style="font-size:0.75rem;">
                                                SKU: <?= htmlspecialchars($it['sku'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($it['unit_name'])): ?>
                                            <div class="text-muted" style="font-size:0.75rem;">
                                                Unit: <?= htmlspecialchars($it['unit_name'], ENT_QUOTES, 'UTF-8') ?>
                                                <?= htmlspecialchars($it['unit_symbol'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?= number_format((float)$it['qty_unit'], 4) ?>
                                    </td>
                                    <td class="text-end">
                                        $ <?= number_format((float)$it['unit_price'], 2) ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ((float)$it['discount'] > 0): ?>
                                            $ <?= number_format((float)$it['discount'], 2) ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        $ <?= number_format((float)$it['subtotal'], 2) ?>
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