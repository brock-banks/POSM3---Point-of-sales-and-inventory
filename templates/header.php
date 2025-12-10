<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/translate.php';

$user = auth_user();
$role = $user['role'] ?? null;
$isAdmin   = strtoupper((string)$role) === 'ADMIN';
$isCashier = strtoupper((string)$role) === 'CASHIER';
?>
<!doctype html>
<html lang="<?= app_language() === 'ar' ? 'ar' : 'en' ?>">
<head>
    <meta charset="utf-8">
    <title>POSM3</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php if (app_language() === 'ar'): ?>
        <style>
            body {
                direction: rtl;
                text-align: right;
            }
            .navbar .navbar-nav.me-auto {
                margin-right: 0;
                margin-left: auto;
            }
        </style>
    <?php endif; ?>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top shadow-sm">
    <div class="container-fluid px-3 px-md-4">
        <a class="navbar-brand d-flex align-items-center" href="/POSM3/public/index.php">
            <span class="me-2 px-2 py-1 bg-primary rounded-3">POS</span>
            <span>POSM3</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarMain" aria-controls="navbarMain"
                aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMain">
            <!-- LEFT: main navigation -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <?php if ($user): ?>
                    <!-- Dashboard (both admin & cashier) -->
                    <li class="nav-item">
                        <a class="nav-link<?= $_SERVER['SCRIPT_NAME'] === '/POSM3/public/index.php' ? ' active' : '' ?>"
                           href="/POSM3/public/index.php">
                            <?= __('nav.dashboard', 'Dashboard') ?>
                        </a>
                    </li>

                    <!-- POS screen (both) -->
                    <li class="nav-item">
                        <a class="nav-link<?= $_SERVER['SCRIPT_NAME'] === '/POSM3/public/pos.php' ? ' active' : '' ?>"
                           href="/POSM3/public/pos.php">
                             <?= __('nav.pos', 'Point of Sale') ?>
                        </a>
                    </li>

                    <!-- Products / Inventory (both, but cashiers only see basic options) -->
                    <li class="nav-item dropdown">
                        <?php
                        $invScripts = [
                            '/POSM3/public/products_list.php',
                            '/POSM3/public/product_edit.php',
                            '/POSM3/public/product_categories_list.php',
                            '/POSM3/public/product_category_edit.php',
                            '/POSM3/public/units_list.php',
                            '/POSM3/public/unit_edit.php',
                            '/POSM3/public/stock_low_report.php',
                            '/POSM3/public/purchases_list.php',
                            '/POSM3/public/purchase_new.php',
                        ];
                        $invActive = in_array($_SERVER['SCRIPT_NAME'], $invScripts, true) ? ' active' : '';
                        ?>
                        <a class="nav-link dropdown-toggle<?= $invActive ?>" href="#" id="invMenu"
                           role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?= __('nav.products', 'Products') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="invMenu">
                            <li><a class="dropdown-item" href="/POSM3/public/products_list.php">Products</a></li>

                            <?php if ($isAdmin): ?>
                                <li><a class="dropdown-item" href="/POSM3/public/product_categories_list.php">Categories</a></li>
                                <li><a class="dropdown-item" href="/POSM3/public/units_list.php">Units</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/POSM3/public/stock_low_report.php">Low Stock</a></li>
                                <li><a class="dropdown-item" href="/POSM3/public/purchases_list.php">Purchases</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>

                    <!-- Customers (both) -->
                    <li class="nav-item">
                        <a class="nav-link<?= $_SERVER['SCRIPT_NAME'] === '/POSM3/public/customers_list.php' ? ' active' : '' ?>"
                           href="/POSM3/public/customers_list.php">
                            <?= __('nav.customers', 'Customers') ?>
                        </a>
                    </li>

                    <?php if ($isAdmin): ?>
                        <!-- Suppliers (admin only) -->
                        <li class="nav-item">
                            <a class="nav-link<?= $_SERVER['SCRIPT_NAME'] === '/POSM3/public/suppliers_list.php' ? ' active' : '' ?>"
                               href="/POSM3/public/suppliers_list.php">
                                <?= __('nav.suppliers', 'Suppliers') ?>
                            </a>
                        </li>

                        <!-- Reports (admin only) -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle<?= ($_SERVER['SCRIPT_NAME'] === '/POSM3/public/sales_report.php'
                                                                  || $_SERVER['SCRIPT_NAME'] === '/POSM3/public/sales_by_product.php')
                                                                ? ' active' : '' ?>"
                               href="#" id="reportsMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <?= __('nav.reports', 'Reports') ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="reportsMenu">
                                <li><a class="dropdown-item" href="/POSM3/public/sales_report.php"><?= __('nav.sales_report', 'Sales report') ?></a></li>
                                <li><a class="dropdown-item" href="/POSM3/public/sales_by_product.php"><?= __('nav.sales_by_product', 'Sales by product') ?></a></li>
                            </ul>
                        </li>

                        <!-- Stock (admin only) -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle<?= $_SERVER['SCRIPT_NAME'] === '/POSM3/public/stock_report.php' ? ' active' : '' ?>"
                               href="#" id="stockMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <?= __('nav.stock', 'Stock') ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="stockMenu">
                                <li><a class="dropdown-item" href="/POSM3/public/stock_report.php"><?= __('nav.stock_report', 'Stock Report') ?></a></li>
                                <li><a class="dropdown-item" href="/POSM3/public/stock_adjustment.php"><?= __('nav.stock_adjustment', 'Stock Adjustment') ?></a></li>
                                <li><a class="dropdown-item" href="/POSM3/public/stock_movements.php"><?= __('nav.stock_movements', 'Stock Movements') ?></a></li>
                            </ul>
                        </li>

                        <!-- Finance (admin only) -->
                        <li class="nav-item dropdown">
                            <?php
                            $finScripts = [
                                '/POSM3/public/customer_payments_list.php',
                                '/POSM3/public/customer_payment_new.php',
                                '/POSM3/public/supplier_payments_list.php',
                                '/POSM3/public/supplier_payment_new.php',
                                '/POSM3/public/customer_ledger.php',
                                '/POSM3/public/supplier_ledger.php',
                                '/POSM3/public/report_customer_balances.php',
                                '/POSM3/public/report_supplier_balances.php',
                            ];
                            $finActive = in_array($_SERVER['SCRIPT_NAME'], $finScripts, true) ? ' active' : '';
                            ?>
                            <a class="nav-link dropdown-toggle<?= $finActive ?>" href="#" id="finMenu"
                               role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <?= __('nav.finance', 'Finance') ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="finMenu">
                                <li class="dropdown-header text-muted"><?= __('nav.payments', 'Payments') ?></li>
                                <li><a class="dropdown-item" href="/POSM3/public/customer_payments_list.php"><?= __('nav.customer_payments', 'Customer Payments') ?></a></li>
                                <li><a class="dropdown-item" href="/POSM3/public/supplier_payments_list.php"><?= __('nav.supplier_payments', 'Supplier Payments') ?></a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li class="dropdown-header text-muted"><?= __('nav.balances_ledgers', 'Balances & Ledgers') ?></li>
                                <li><a class="dropdown-item" href="/POSM3/public/report_customer_balances.php"><?= __('nav.customer_balances', 'Customer Balances') ?></a></li>
                                <li><a class="dropdown-item" href="/POSM3/public/report_supplier_balances.php"><?= __('nav.supplier_balances', 'Supplier Balances') ?></a></li>
                                <li><a class="dropdown-item" href="/POSM3/public/customer_ledger.php"><?= __('nav.customer_ledger', 'Customer Ledger') ?></a></li>
                                <li><a class="dropdown-item" href="/POSM3/public/supplier_ledger.php"><?= __('nav.supplier_ledger', 'Supplier Ledger') ?></a></li>
                            </ul>
                        </li>

                        <!-- Users (admin only) -->
                        <li class="nav-item">
                            <a class="nav-link<?= $_SERVER['SCRIPT_NAME'] === '/POSM3/public/users_list.php' ? ' active' : '' ?>"
                               href="/POSM3/public/users_list.php">
                                <?= __('nav.users', 'Users') ?>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>

            <!-- RIGHT: user dropdown -->
            <?php if ($user): ?>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-light small d-none d-md-inline">
                        <?= htmlspecialchars($user['full_name'] ?? $user['username'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-light dropdown-toggle" type="button"
                                id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            Account
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark"
                            aria-labelledby="userMenuDropdown">

                            <?php if ($isAdmin): ?>
                                <li>
                                    <a class="dropdown-item" href="/POSM3/public/settings_general.php">
                                        <?= __('nav.settings', 'Settings') ?>
                                    </a>
                                </li>
                                <li><a class="dropdown-item" href="/POSM3/public/pos_settings.php"><?= __('nav.pos_settings', 'POS settings') ?></a></li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>

                            <li>
                                <a class="dropdown-item" href="/POSM3/public/change_password.php">
                                    <?= __('nav.change_password', 'Change password') ?>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="/POSM3/public/logout.php">
                                    <?= __('nav.sign_out', 'Sign out') ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="content-wrapper container-fluid px-3 px-md-4" style="padding-top: 4.5rem;">