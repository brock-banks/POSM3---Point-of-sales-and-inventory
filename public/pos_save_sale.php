<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

auth_require_login();
$user = auth_user();
$pdo  = db();

$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$items       = $data['items'] ?? [];
$customerId  = isset($data['customer_id']) && $data['customer_id'] !== '' ? (int)$data['customer_id'] : null;
$paymentType = $data['payment_type'] ?? 'CASH';  // 'CASH' or 'CREDIT'
$paidAmount  = (float)($data['paid_amount'] ?? 0);
$notes       = trim($data['notes'] ?? '');
$taxRate     = 0.15; // keep in sync with TAX_RATE in pos.php

if (empty($items)) {
    http_response_code(400);
    echo json_encode(['error' => 'Cart is empty']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Recalculate totals on server
    $subTotal      = 0;
    $totalDiscount = 0;

    foreach ($items as &$it) {
        $pid      = (int)($it['product_id'] ?? 0);
        $qtyBase  = (float)($it['qty_base'] ?? 0);
        $price    = (float)($it['unit_price'] ?? 0);
        $discount = (float)($it['discount'] ?? 0); // absolute discount

        if ($pid <= 0 || $qtyBase == 0 || $price < 0) {
            throw new Exception('Invalid cart line.');
        }

        $lineGross = $qtyBase * $price;
        $lineNet   = $lineGross - $discount;
        if ($lineNet < 0) $lineNet = 0;

        $subTotal      += $lineNet;
        $totalDiscount += $discount;

        $it['subtotal'] = $lineNet;
    }
    unset($it);

    $taxAmount   = $subTotal * $taxRate;
    $totalAmount = $subTotal + $taxAmount;

    // Payment columns: total, paid_amount, credit_amount
    if ($paymentType === 'CREDIT') {
        $paidAmount = 0;
    } elseif ($paymentType === 'CASH' && $paidAmount <= 0.0001) {
        $paidAmount = $totalAmount;
    }

    $creditAmount = $totalAmount - $paidAmount;
    if ($creditAmount < 0) {
        $creditAmount = 0; // ignore change for now
    }

    // Insert into sales (your schema)
    $stmt = $pdo->prepare("
        INSERT INTO sales
            (sale_date, customer_id, total_amount, paid_amount, credit_amount,
             payment_type, status, notes, created_by, created_at)
        VALUES
            (NOW(), :customer_id, :total_amount, :paid_amount, :credit_amount,
             :payment_type, 'COMPLETED', :notes, :created_by, NOW())
    ");
    $stmt->execute([
        ':customer_id'   => $customerId,
        ':total_amount'  => $totalAmount,
        ':paid_amount'   => $paidAmount,
        ':credit_amount' => $creditAmount,
        ':payment_type'  => $paymentType,
        ':notes'         => $notes !== '' ? $notes : null,
        ':created_by'    => $user ? $user['id'] : null,
    ]);
    $saleId = (int)$pdo->lastInsertId();

    // Sale items + stock update
    $itemStmt = $pdo->prepare("
        INSERT INTO sale_items
            (sale_id, product_id, unit_id, qty_unit, qty_base,
             unit_price, discount, subtotal)
        VALUES
            (:sale_id, :product_id, :unit_id, :qty_unit, :qty_base,
             :unit_price, :discount, :subtotal)
    ");

    $stockStmt = $pdo->prepare("
        UPDATE products
        SET stock_qty = stock_qty - :qty_base,
            updated_at = NOW()
        WHERE id = :product_id
    ");

    // Stock movement: SALE
    $mvStmt = $pdo->prepare("
        INSERT INTO stock_movements
            (product_id, movement_date, source_type, source_id, qty_change, note, created_by, created_at)
        VALUES
            (:product_id, NOW(), 'SALE', :source_id, :qty_change, :note, :created_by, NOW())
    ");

    foreach ($items as $it) {
        $qtyBase = (float)$it['qty_base'];

        $itemStmt->execute([
            ':sale_id'    => $saleId,
            ':product_id' => (int)$it['product_id'],
            ':unit_id'    => (int)$it['unit_id'],
            ':qty_unit'   => $qtyBase,
            ':qty_base'   => $qtyBase,
            ':unit_price' => (float)$it['unit_price'],
            ':discount'   => (float)$it['discount'],
            ':subtotal'   => (float)$it['subtotal'],
        ]);

        // Decrease stock
        $stockStmt->execute([
            ':qty_base'   => $qtyBase,
            ':product_id' => (int)$it['product_id'],
        ]);

        // Log stock movement
        $mvStmt->execute([
            ':product_id' => (int)$it['product_id'],
            ':source_id'  => $saleId,
            ':qty_change' => -$qtyBase,
            ':note'       => $notes !== '' ? $notes : null,
            ':created_by' => $user ? $user['id'] : null,
        ]);
    }

    // Customer credit entry
    if ($customerId && $creditAmount > 0.0001) {
        $ctStmt = $pdo->prepare("
            INSERT INTO customer_transactions
                (customer_id, transaction_date, type, amount, related_sale_id, related_payment_id, notes, created_by, created_at)
            VALUES
                (:customer_id, NOW(), 'SALE', :amount, :sale_id, NULL, :notes, :created_by, NOW())
        ");
        $ctStmt->execute([
            ':customer_id' => $customerId,
            ':amount'      => $creditAmount,
            ':sale_id'     => $saleId,
            ':notes'       => $notes !== '' ? $notes : null,
            ':created_by'  => $user ? $user['id'] : null,
        ]);
    }

    $pdo->commit();

   echo json_encode([
    'success'       => true,
    'sale_id'       => $saleId,
    'total_amount'  => $totalAmount,
    'paid_amount'   => $paidAmount,
    'credit_amount' => $creditAmount,
    'receipt_data_url' => '/POSM3/public/pos_receipt_data.php?id=' . $saleId,
    // optionally keep old HTML receipt as fallback:
    'receipt_html_url'  => '/POSM3/public/pos_receipt.php?id=' . $saleId,
]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}