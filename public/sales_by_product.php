<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

auth_require_login();
$pdo = db();

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

// Aggregate from sale_items + sales
$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.name,
        p.sku,
        SUM(si.qty_base)   AS qty_base_total,
        SUM(si.qty_unit)   AS qty_unit_total,
        SUM(si.subtotal)   AS revenue,
        u.symbol           AS unit_symbol
    FROM sale_items si
    JOIN sales s      ON s.id = si.sale_id
    JOIN products p   ON p.id = si.product_id
    LEFT JOIN units u ON u.id = si.unit_id
    WHERE s.sale_date BETWEEN :from AND :to
      AND s.status = 'COMPLETED'
    GROUP BY p.id, p.name, p.sku, u.symbol
    ORDER BY revenue DESC
");
$stmt->execute([
    ':from' => $from . ' 00:00:00',
    ':to'   => $to . ' 23:59:59',
]);
$rows = $stmt->fetchAll();

require_once __DIR__ . '/../templates/header.php';
?>

<div class="row gy-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Sales by product</h4>
        <a href="/POSM3/public/sales_report.php" class="btn btn-sm btn-outline-secondary">
            Sales list
        </a>
    </div>

    <div class="col-12">
        <form class="row g-2 mb-3" method="get">
            <div class="col-sm-3 col-md-2">
                <label class="form-label form-label-sm" for="from">From</label>
                <input type="date" id="from" name="from"
                       class="form-control form-control-sm"
                       value="<?= htmlspecialchars($from, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-sm-3 col-md-2">
                <label class="form-label form-label-sm" for="to">To</label>
                <input type="date" id="to" name="to"
                       class="form-control form-control-sm"
                       value="<?= htmlspecialchars($to, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-sm-2 col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-sm btn-primary me-2">Filter</button>
                <a href="/POSM3/public/sales_by_product.php" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <div class="col-12">
        <div class="card bg-white border-0 shadow-sm">
            <div class="card-body">
                <?php if (empty($rows)): ?>
                    <p class="text-muted mb-0">No sales found for the selected period.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                            <tr>
                                <th style="width:60px;">ID</th>
                                <th>Product</th>
                                <th>SKU</th>
                                <th class="text-end" style="width:140px;">Qty (unit)</th>
                                <th class="text-end" style="width:140px;">Qty (base)</th>
                                <th class="text-end" style="width:140px;">Revenue</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            $totalRevenue = 0;
                            foreach ($rows as $r):
                                $totalRevenue += (float)$r['revenue'];
                            ?>
                                <tr>
                                    <td><?= (int)$r['id'] ?></td>
                                    <td><?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($r['sku'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end">
                                        <?= number_format((float)$r['qty_unit_total'], 4) ?>
                                        <span class="text-muted" style="font-size:0.8rem;">
                                            <?= htmlspecialchars($r['unit_symbol'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <?= number_format((float)$r['qty_base_total'], 4) ?>
                                    </td>
                                    <td class="text-end">
                                        $ <?= number_format((float)$r['revenue'], 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                            <tr>
                                <th colspan="5" class="text-end">Total revenue:</th>
                                <th class="text-end">
                                    $ <?= number_format($totalRevenue, 2) ?>
                                </th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>