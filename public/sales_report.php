<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

auth_require_login();
$pdo = db();

// Filters
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$customerId = isset($_GET['customer_id']) && $_GET['customer_id'] !== '' ? (int)$_GET['customer_id'] : null;

// Customers for filter
$cStmt = $pdo->query("SELECT id, name FROM customers WHERE is_active = 1 ORDER BY name");
$customers = $cStmt->fetchAll();

// Build query
$params = [
    ':from' => $from . ' 00:00:00',
    ':to'   => $to . ' 23:59:59',
];

$sql = "
    SELECT
        s.id,
        s.sale_date,
        s.total_amount,
        s.paid_amount,
        s.credit_amount,
        s.payment_type,
        s.status,
        c.name AS customer_name
    FROM sales s
    LEFT JOIN customers c ON c.id = s.customer_id
    WHERE s.sale_date BETWEEN :from AND :to
";

if ($customerId) {
    $sql .= " AND s.customer_id = :customer_id";
    $params[':customer_id'] = $customerId;
}

$sql .= " ORDER BY s.sale_date DESC, s.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

$totals = [
    'total_amount'  => 0,
    'paid_amount'   => 0,
    'credit_amount' => 0,
];

foreach ($sales as $row) {
    $totals['total_amount']  += (float)$row['total_amount'];
    $totals['paid_amount']   += (float)$row['paid_amount'];
    $totals['credit_amount'] += (float)$row['credit_amount'];
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="row gy-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Sales report</h4>
        <a href="/POSM3/public/pos.php" class="btn btn-sm btn-outline-secondary">
            Go to POS
        </a>
    </div>

    <div class="col-12">
        <form class="row g-2 mb-3" method="get">
            <div class="col-sm-3 col-md-2">
                <label class="form-label form-label-sm" for="from">From</label>
                <input type="date" id="from" name="from"
                       class="form-control form-control-sm"
                       value="<?= htmlspecialchars($from, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-sm-3 col-md-2">
                <label class="form-label form-label-sm" for="to">To</label>
                <input type="date" id="to" name="to"
                       class="form-control form-control-sm"
                       value="<?= htmlspecialchars($to, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-sm-4 col-md-3 col-lg-3">
                <label class="form-label form-label-sm" for="customer_id">Customer</label>
                <select id="customer_id" name="customer_id" class="form-select form-select-sm">
                    <option value="">-- All --</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"
                            <?= $customerId === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-2 col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-sm btn-primary me-2">Filter</button>
                <a href="/POSM3/public/sales_report.php" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <div class="col-12">
        <div class="card bg-white border-0 shadow-sm">
            <div class="card-body">
                <?php if (empty($sales)): ?>
                    <p class="text-muted mb-0">No sales found for the selected period.</p>
                <?php else: ?>
                    <div class="table-responsive mb-2">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                            <tr>
                                <th style="width:80px;">ID</th>
                                <th style="width:150px;">Date</th>
                                <th>Customer</th>
                                <th style="width:110px;" class="text-end">Total</th>
                                <th style="width:110px;" class="text-end">Paid</th>
                                <th style="width:110px;" class="text-end">Credit</th>
                                <th style="width:90px;">Type</th>
                                <th style="width:90px;">Status</th>
                                <th style="width:80px;"></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($sales as $s): ?>
                                <tr>
                                    <td>#<?= (int)$s['id'] ?></td>
                                    <td><?= htmlspecialchars($s['sale_date'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($s['customer_name'] ?? 'Walk-in', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end">$ <?= number_format((float)$s['total_amount'], 2) ?></td>
                                    <td class="text-end">$ <?= number_format((float)$s['paid_amount'], 2) ?></td>
                                    <td class="text-end">$ <?= number_format((float)$s['credit_amount'], 2) ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?= htmlspecialchars($s['payment_type'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($s['status'] === 'COMPLETED'): ?>
                                            <span class="badge bg-success">Completed</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <?= htmlspecialchars($s['status'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="/POSM3/public/sale_view.php?id=<?= (int)$s['id'] ?>"
                                           class="btn btn-sm btn-outline-secondary">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Totals:</th>
                                <th class="text-end">$ <?= number_format($totals['total_amount'], 2) ?></th>
                                <th class="text-end">$ <?= number_format($totals['paid_amount'], 2) ?></th>
                                <th class="text-end">$ <?= number_format($totals['credit_amount'], 2) ?></th>
                                <th colspan="3"></th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p class="text-muted mb-0" style="font-size:0.85rem;">
                        Totals are for the current filter only.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>