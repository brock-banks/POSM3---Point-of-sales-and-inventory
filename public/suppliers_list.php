<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

auth_require_login();

$pdo = db();

$stmt = $pdo->query("
    SELECT
        s.id,
        s.name,
        s.phone,
        s.email,
        s.address,
        s.is_active,
        s.created_at,
        (SELECT IFNULL(SUM(st.amount), 0)
         FROM supplier_transactions st
         WHERE st.supplier_id = s.id) AS balance
    FROM suppliers s
    ORDER BY s.name ASC
");
$suppliers = $stmt->fetchAll();

require_once __DIR__ . '/../templates/header.php';
?>

<div class="row gy-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Suppliers</h4>
        <a href="/POSM3/public/supplier_edit.php" class="btn btn-sm btn-primary">
            + New Supplier
        </a>
    </div>

    <div class="col-12">
        <div class="card bg-secondary border-0 shadow-sm">
            <div class="card-body">
                <?php if (empty($suppliers)): ?>
                    <p class="text-muted mb-0">No suppliers found. Click "New Supplier" to add one.</p>
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
                            <?php foreach ($suppliers as $s): ?>
                                <tr>
                                    <td><?= (int)$s['id'] ?></td>
                                    <td><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($s['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($s['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($s['address'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end">
                                        <?php
                                        $balance = isset($s['balance']) ? (float)$s['balance'] : 0.0;
                                        // For suppliers: positive = you owe them (show red), negative = they owe you (green)
                                        $balanceClass = $balance > 0 ? 'text-danger' : ($balance < 0 ? 'text-success' : 'text-muted');
                                        ?>
                                        <span class="<?= $balanceClass ?>">
                                            <?= number_format($balance, 2) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ((int)$s['is_active'] === 1): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="/POSM3/public/supplier_edit.php?id=<?= (int)$s['id'] ?>"
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