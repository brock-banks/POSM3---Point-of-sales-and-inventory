<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

auth_require_login();

$pdo = db();

$stmt = $pdo->query("
    SELECT
        c.id,
        c.name,
        c.phone,
        c.email,
        c.address,
        c.is_active,
        c.created_at,
        -- if you created the customer_balances view, you can join it; otherwise comment this out
        (SELECT IFNULL(SUM(ct.amount), 0)
         FROM customer_transactions ct
         WHERE ct.customer_id = c.id) AS balance
    FROM customers c
    ORDER BY c.name ASC
");
$customers = $stmt->fetchAll();

require_once __DIR__ . '/../templates/header.php';
?>

<div class="row gy-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Customers</h4>
        <a href="/POSM3/public/customer_edit.php" class="btn btn-sm btn-primary">
            + New Customer
        </a>
    </div>

    <div class="col-12">
        <div class="card bg-white border-0 shadow-sm">
            <div class="card-body">
                <?php if (empty($customers)): ?>
                    <p class="text-muted mb-0">No customers found. Click "New Customer" to add one.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-white table-sm align-middle mb-0">
                            <thead>
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Address</th>
                                <th class="text-end">Balance</th>
                                <th class="text-center">Status</th>
                                <th style="width: 90px;"></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($customers as $c): ?>
                                <tr>
                                    <td><?= (int)$c['id'] ?></td>
                                    <td><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($c['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($c['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($c['address'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end">
                                        <?php
                                        $balance = isset($c['balance']) ? (float)$c['balance'] : 0.0;
                                        $balanceClass = $balance > 0 ? 'text-danger' : ($balance < 0 ? 'text-success' : 'text-muted');
                                        ?>
                                        <span class="<?= $balanceClass ?>">
                                            <?= number_format($balance, 2) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ((int)$c['is_active'] === 1): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="/POSM3/public/customer_edit.php?id=<?= (int)$c['id'] ?>"
                                           class="btn btn-sm btn-outline-light">
                                            Edit
                                        </a>
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