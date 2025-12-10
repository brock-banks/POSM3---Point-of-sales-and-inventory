<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

auth_require_login();

$pdo = db();

$sql = "
    SELECT
        s.id,
        s.name,
        s.phone,
        s.email,
        s.is_active,
        IFNULL(SUM(t.amount), 0) AS balance,
        MAX(t.transaction_date) AS last_txn_date
    FROM suppliers s
    LEFT JOIN supplier_transactions t ON t.supplier_id = s.id
    GROUP BY s.id, s.name, s.phone, s.email, s.is_active
    ORDER BY balance DESC, s.name ASC
";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll();

require_once __DIR__ . '/../templates/header.php';
?>

<div class="row gy-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Supplier Balances</h4>
        <a href="/POSM3/public/supplier_ledger.php" class="btn btn-sm btn-outline-light">
            View supplier ledger
        </a>
    </div>

    <div class="col-12">
        <div class="card bg-white border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted" style="font-size: 0.9rem;">
                    Positive balance = you owe supplier. Negative balance = supplier owes you.
                </p>

                <?php if (empty($rows)): ?>
                    <p class="text-muted mb-0">No suppliers or no transactions yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-white table-sm align-middle mb-0">
                            <thead>
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th class="text-end">Balance</th>
                                <th>Last transaction</th>
                                <th class="text-center">Status</th>
                                <th style="width: 80px;"></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rows as $r): ?>
                                <?php
                                $bal = (float)$r['balance'];
                                $cls = $bal > 0 ? 'text-danger' : ($bal < 0 ? 'text-success' : 'text-muted');
                                ?>
                                <tr>
                                    <td><?= (int)$r['id'] ?></td>
                                    <td><?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($r['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($r['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end <?= $cls ?>">
                                        <?= number_format($bal, 2) ?>
                                    </td>
                                    <td>
                                        <?= $r['last_txn_date']
                                            ? htmlspecialchars($r['last_txn_date'], ENT_QUOTES, 'UTF-8')
                                            : '-' ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ((int)$r['is_active'] === 1): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="/POSM3/public/supplier_ledger.php?supplier_id=<?= (int)$r['id'] ?>"
                                           class="btn btn-sm btn-outline-light">
                                            Ledger
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