<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

auth_require_login();

$pdo = db();

$sql = "
    SELECT
        p.id,
        p.purchase_date,
        p.supplier_id,
        s.name AS supplier_name,
        p.total_amount,
        p.status
    FROM purchases p
    LEFT JOIN suppliers s ON s.id = p.supplier_id
    ORDER BY p.purchase_date DESC, p.id DESC
";
$stmt = $pdo->query($sql);
$purchases = $stmt->fetchAll();

require_once __DIR__ . '/../templates/header.php';
?>

<div class="row gy-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Purchases</h4>
        <a href="/POSM3/public/purchase_new.php" class="btn btn-sm btn-primary">
            + New Purchase
        </a>
    </div>

    <div class="col-12">
        <div class="card bg-white border-0 shadow-sm">
            <div class="card-body">
                <?php if (empty($purchases)): ?>
                    <p class="text-muted mb-0">No purchases found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-white table-sm align-middle mb-0">
                            <thead>
                            <tr>
                                <th style="width: 70px;">ID</th>
                                <th>Date</th>
                                <th>Supplier</th>
                                <th class="text-end">Total</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($purchases as $p): ?>
                                <tr>
                                    <td><?= (int)$p['id'] ?></td>
                                    <td><?= htmlspecialchars($p['purchase_date'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($p['supplier_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end"><?= number_format((float)$p['total_amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($p['status'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
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