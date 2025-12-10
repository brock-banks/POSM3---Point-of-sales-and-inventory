<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

auth_require_login();
$pdo = db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo 'Missing or invalid sale id.';
    exit;
}

// Load sale + customer
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

// Load items
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
        u.symbol AS unit_symbol
    FROM sale_items si
    JOIN products p   ON p.id = si.product_id
    LEFT JOIN units u ON u.id = si.unit_id
    WHERE si.sale_id = :id
");
$itemStmt->execute([':id' => $id]);
$items = $itemStmt->fetchAll();

// Compute totals similar to pos.php / pos_save_sale.php
$taxRate   = 0.15;
$subTotal  = 0;
$totalDisc = 0;

foreach ($items as $it) {
    $gross = (float)$it['qty_base'] * (float)$it['unit_price'];
    $disc  = (float)$it['discount'];
    $net   = $gross - $disc;
    if ($net < 0) $net = 0;
    $subTotal  += $net;
    $totalDisc += $disc;
}

$tax   = $subTotal * $taxRate;
$total = $subTotal + $tax;

// Simple shop info (you can move to config)
$shopName    = 'My Shop';
$shopAddress = '123 Street, City';
$shopPhone   = 'Phone: 000-000-000';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt #<?= (int)$sale['id'] ?></title>
    <style>
        /* 80mm receipt style */
        @page {
            size: 80mm auto;
            margin: 2mm;
        }
        @media print {
            body {
                margin: 0;
            }
        }
        body {
            font-family: monospace, Arial, sans-serif;
            font-size: 11px;
            line-height: 1.3;
            background: #fff;
        }
        .receipt {
            width: 75mm;
            max-width: 75mm;
            margin: 0 auto;
        }
        .center {
            text-align: center;
        }
        .right {
            text-align: right;
        }
        .bold {
            font-weight: bold;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 4px 0;
        }
        .header-title {
            font-size: 14px;
            font-weight: bold;
        }
        .small {
            font-size: 10px;
        }
        .mt-4 { margin-top: 4px; }
        .mb-4 { margin-bottom: 4px; }
        .row {
            display: flex;
        }
        .col-qty {
            width: 16mm;
        }
        .col-name {
            flex: 1;
        }
        .col-total {
            width: 23mm;
            text-align: right;
        }
        .no-print-btn {
            margin: 6px 0;
        }
    </style>
</head>
<body onload="window.print()">
<div class="receipt">
    <!-- Optional print button when not auto-printing -->
    <div class="no-print-btn" style="text-align:right;">
        <button onclick="window.print();" style="font-size:10px;">Print</button>
    </div>

    <div class="center">
        <div class="header-title"><?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="small"><?= nl2br(htmlspecialchars($shopAddress, ENT_QUOTES, 'UTF-8')) ?></div>
        <div class="small"><?= htmlspecialchars($shopPhone, ENT_QUOTES, 'UTF-8') ?></div>
    </div>

    <div class="divider"></div>

    <div class="small">
        <div>Date: <?= htmlspecialchars($sale['sale_date'], ENT_QUOTES, 'UTF-8') ?></div>
        <div>Receipt: #<?= (int)$sale['id'] ?></div>
        <div>Cashier: <?= htmlspecialchars(auth_user()['username'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
        <div>Customer: <?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in', ENT_QUOTES, 'UTF-8') ?></div>
    </div>

    <div class="divider"></div>

    <!-- Items -->
    <?php foreach ($items as $it): ?>
        <?php
        $qty     = (float)$it['qty_unit'];
        $price   = (float)$it['unit_price'];
        $disc    = (float)$it['discount'];
        $gross   = $qty * $price;
        $net     = $gross - $disc;
        if ($net < 0) $net = 0;
        ?>
        <div class="row">
            <div class="col-qty">
                <?= number_format($qty, 2) ?>
                <?php if (!empty($it['unit_symbol'])): ?>
                    <?= htmlspecialchars($it['unit_symbol'], ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
            </div>
            <div class="col-name">
                <?= htmlspecialchars($it['product_name'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div class="col-total">
                <?= number_format($net, 2) ?>
            </div>
        </div>
        <div class="small">
            @ <?= number_format($price, 2) ?>
            <?php if ($disc > 0): ?>
                | Disc <?= number_format($disc, 2) ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <div class="divider"></div>

    <!-- Totals -->
    <table style="width:100%; font-size:11px;">
        <tr>
            <td>Subtotal</td>
            <td class="right"><?= number_format($subTotal, 2) ?></td>
        </tr>
        <tr>
            <td>Tax (<?= (int)($taxRate*100) ?>%)</td>
            <td class="right"><?= number_format($tax, 2) ?></td>
        </tr>
        <tr>
            <td class="bold">Total</td>
            <td class="right bold"><?= number_format($total, 2) ?></td>
        </tr>
        <tr>
            <td>Paid</td>
            <td class="right"><?= number_format((float)$sale['paid_amount'], 2) ?></td>
        </tr>
        <tr>
            <td>Credit</td>
            <td class="right"><?= number_format((float)$sale['credit_amount'], 2) ?></td>
        </tr>
    </table>

    <div class="divider"></div>

    <div class="center small">
        Thank you for your purchase!
    </div>
</div>
</body>
</html>