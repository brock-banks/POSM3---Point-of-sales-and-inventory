<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

auth_require_login();

$pdo = db();

// Quick stats
$stats = [];

// Total customers
$stats['customers'] = (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();

// Total suppliers
$stats['suppliers'] = (int)$pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();

// Total active products
$stats['products'] = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();

// Low stock count
$stats['low_stock'] = (int)$pdo->query("
    SELECT COUNT(*)
    FROM products
    WHERE is_active = 1
      AND min_stock_qty IS NOT NULL
      AND min_stock_qty > 0
      AND stock_qty < min_stock_qty
")->fetchColumn();

// Today date
$today = date('Y-m-d');

// Today customer payments
$stats['today_customer_payments'] = (float)$pdo->query("
    SELECT IFNULL(SUM(amount), 0)
    FROM customer_payments
    WHERE DATE(payment_date) = '{$today}'
")->fetchColumn();

// Today supplier payments
$stats['today_supplier_payments'] = (float)$pdo->query("
    SELECT IFNULL(SUM(amount), 0)
    FROM supplier_payments
    WHERE DATE(payment_date) = '{$today}'
")->fetchColumn();

// Today purchases total
$stats['today_purchases'] = (float)$pdo->query("
    SELECT IFNULL(SUM(total_amount), 0)
    FROM purchases
    WHERE DATE(purchase_date) = '{$today}'
")->fetchColumn();

// Latest 5 customers & suppliers
$custStmt = $pdo->query("
    SELECT id, name, created_at
    FROM customers
    ORDER BY created_at DESC
    LIMIT 5
");
$latestCustomers = $custStmt->fetchAll();

$suppStmt = $pdo->query("
    SELECT id, name, created_at
    FROM suppliers
    ORDER BY created_at DESC
    LIMIT 5
");
$latestSuppliers = $suppStmt->fetchAll();

require_once __DIR__ . '/../templates/header.php';
?>

<div class="row gy-3">
    <div class="col-12">
        <h4 class="mb-3">Dashboard</h4>
    </div>

    <!-- Top stats -->
    <div class="col-sm-6 col-lg-3">
        <div class="card bg-white border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted" style="font-size: 0.8rem;">Customers</div>
                <div class="display-6 fw-semibold"><?= $stats['customers'] ?></div>
                <a href="/POSM3/public/customers_list.php" class="text-decoration-none text-light" style="font-size: 0.8rem;">
                    View customers →
                </a>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <div class="card bg-white border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted" style="font-size: 0.8rem;">Suppliers</div>
                <div class="display-6 fw-semibold"><?= $stats['suppliers'] ?></div>
                <a href="/POSM3/public/suppliers_list.php" class="text-decoration-none text-light" style="font-size: 0.8rem;">
                    View suppliers →
                </a>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <div class="card bg-white border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted" style="font-size: 0.8rem;">Products</div>
                <div class="display-6 fw-semibold"><?= $stats['products'] ?></div>
                <a href="/POSM3/public/products_list.php" class="text-decoration-none text-light" style="font-size: 0.8rem;">
                    View products →
                </a>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <div class="card bg-white border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted" style="font-size: 0.8rem;">Low stock items</div>
                <div class="display-6 fw-semibold <?= $stats['low_stock'] > 0 ? 'text-warning' : '' ?>">
                    <?= $stats['low_stock'] ?>
                </div>
                <a href="/POSM3/public/stock_low_report.php" class="text-decoration-none text-light" style="font-size: 0.8rem;">
                    Low stock report →
                </a>
            </div>
        </div>
    </div>

    <!-- Today financial activity -->
    <div class="col-md-4">
        <div class="card bg-white border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="card-title mb-2">Today overview (<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>)</h6>
                <ul class="list-unstyled mb-0" style="font-size: 0.9rem;">
                    <li class="d-flex justify-content-between">
                        <span>Customer payments</span>
                        <span class="text-success"><?= number_format($stats['today_customer_payments'], 2) ?></span>
                    </li>
                    <li class="d-flex justify-content-between">
                        <span>Supplier payments</span>
                        <span class="text-danger"><?= number_format($stats['today_supplier_payments'], 2) ?></span>
                    </li>
                    <li class="d-flex justify-content-between">
                        <span>Purchases</span>
                        <span class="text-danger"><?= number_format($stats['today_purchases'], 2) ?></span>
                    </li>
                    <!-- After Sales is built, add: Today sales, profit, etc. -->
                </ul>
            </div>
        </div>
    </div>

    <!-- Latest customers -->
    <div class="col-md-4">
        <div class="card bg-white border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="card-title mb-2">Latest customers</h6>
                <?php if (empty($latestCustomers)): ?>
                    <p class="text-muted mb-0" style="font-size: 0.85rem;">No customers yet.</p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0" style="font-size: 0.85rem;">
                        <?php foreach ($latestCustomers as $c): ?>
                            <li class="mb-1 d-flex justify-content-between">
                                <span><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="text-muted">
                                    <?= htmlspecialchars(substr($c['created_at'], 0, 10), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="/POSM3/public/customers_list.php" class="text-decoration-none text-light" style="font-size: 0.8rem;">
                        View all customers →
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Latest suppliers -->
    <div class="col-md-4">
        <div class="card bg-white border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="card-title mb-2">Latest suppliers</h6>
                <?php if (empty($latestSuppliers)): ?>
                    <p class="text-muted mb-0" style="font-size: 0.85rem;">No suppliers yet.</p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0" style="font-size: 0.85rem;">
                        <?php foreach ($latestSuppliers as $s): ?>
                            <li class="mb-1 d-flex justify-content-between">
                                <span><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="text-muted">
                                    <?= htmlspecialchars(substr($s['created_at'], 0, 10), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="/POSM3/public/suppliers_list.php" class="text-decoration-none text-light" style="font-size: 0.8rem;">
                        View all suppliers →
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>