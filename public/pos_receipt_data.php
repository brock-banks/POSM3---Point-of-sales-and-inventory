<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

auth_require_login();
$pdo = db();

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid sale id']);
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
    echo json_encode(['error' => 'Sale not found']);
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

// Basic shop config – adjust or load from settings
$shopName    = 'My Shop';
$shopAddress = '123 Street, City';
$shopPhone   = 'Phone: 000-000-000';

// Tax calc (must match backend logic)
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

echo json_encode([
    'success' => true,
    'sale' => [
        'id'            => (int)$sale['id'],
        'date'          => $sale['sale_date'],
        'customer_name' => $sale['customer_name'] ?? 'Walk-in',
        'payment_type'  => $sale['payment_type'],
        'status'        => $sale['status'],
        'total_amount'  => (float)$sale['total_amount'],
        'paid_amount'   => (float)$sale['paid_amount'],
        'credit_amount' => (float)$sale['credit_amount'],
        'notes'         => $sale['notes'] ?? '',
    ],
    'shop' => [
        'name'    => $shopName,
        'address' => $shopAddress,
        'phone'   => $shopPhone,
    ],
    'totals' => [
        'sub_total'  => $subTotal,
        'tax'        => $tax,
        'tax_rate'   => $taxRate,
        'discount'   => $totalDisc,
        'total'      => $total,
    ],
    'items' => array_map(function ($it) {
        return [
            'product_name' => $it['product_name'],
            'sku'          => $it['sku'],
            'unit_symbol'  => $it['unit_symbol'],
            'qty_unit'     => (float)$it['qty_unit'],
            'qty_base'     => (float)$it['qty_base'],
            'unit_price'   => (float)$it['unit_price'],
            'discount'     => (float)$it['discount'],
            'subtotal'     => (float)$it['subtotal'],
        ];
    }, $items),
]);