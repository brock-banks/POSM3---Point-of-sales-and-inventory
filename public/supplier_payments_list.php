<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

auth_require_login();

$pdo = db();

$supplierId = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;

// Load suppliers for filter
$sStmt = $pdo->query("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name");
$suppliers = $sStmt->fetchAll();

// Base query
$sql = "
    SELECT
        p.id,
        p.supplier_id,
        s.name AS supplier_name,
        p.payment_date,
        p.amount,
        p.method,
        p.reference,
        p.notes
    FROM supplier_payments p
    JOIN suppliers s ON s.id = p.supplier_id
";
$params = [];

if ($supplierId > 0) {
    $sql .= " WHERE p.supplier_id = :sid";
    $params[':sid'] = $supplierId;
}

$sql .= " ORDER BY p.payment_date DESC, p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

require_once __DIR__ . '/../templates/header.php';
?>

<div class="row gy-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Supplier Payments</h4>
        <a href="/POSM3/public/supplier_payment_new.php" class="btn btn-sm btn-primary">
            + New Payment
        </a>
    </div>

    <div class="col-12">
        <form class="row g-2 mb-3" method="get">
            <div class="col-sm-6 col-md-4 col-lg-3">
                <label class="form-label form-label-sm">Filter by supplier</label>
                <select name="supplier_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">All suppliers</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"
                            <?= $supplierId === (int)$s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <div class="card bg-dark border-0 shadow-sm">
            <div class="card-body">
                <?php if (empty($payments)): ?>
                    <p class="text-muted mb-0">No payments found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-sm align-middle mb-0">
                            <thead>
                            <tr>
                                <th style="width: 70px;">ID</th>
                                <th>Date</th>
                                <th>Supplier</th>
                                <th class="text-end">Amount</th>
                                <th>Method</th>
                                <th>Reference</th>
                                <th>Notes</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($payments as $p): ?>
                                <tr>
                                    <td><?= (int)$p['id'] ?></td>
                                    <td><?= htmlspecialchars($p['payment_date'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($p['supplier_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end text-success">
                                        <?= number_format((float)$p['amount'], 2) ?>
                                    </td>
                                    <td><?= htmlspecialchars($p['method'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($p['reference'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($p['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
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