<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

auth_require_login();

$pdo = db();

$customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;

// Load customers for dropdown
$cStmt = $pdo->query("SELECT id, name FROM customers WHERE is_active = 1 ORDER BY name");
$customers = $cStmt->fetchAll();

$customer = null;
$transactions = [];
$startDate = $_GET['start_date'] ?? '';
$endDate   = $_GET['end_date'] ?? '';

if ($customerId > 0) {
    $stmt = $pdo->prepare("SELECT id, name FROM customers WHERE id = :id");
    $stmt->execute([':id' => $customerId]);
    $customer = $stmt->fetch();

    if ($customer) {
        // Build query with optional date filter
        $sql = "
            SELECT
                id,
                transaction_date,
                type,
                amount,
                related_sale_id,
                related_payment_id,
                notes
            FROM customer_transactions
            WHERE customer_id = :cid
        ";
        $params = [':cid' => $customerId];

        if ($startDate !== '') {
            $sql .= " AND transaction_date >= :start_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
        }
        if ($endDate !== '') {
            $sql .= " AND transaction_date <= :end_date";
            $params[':end_date'] = $endDate . ' 23:59:59';
        }

        $sql .= " ORDER BY transaction_date ASC, id ASC";

        $tStmt = $pdo->prepare($sql);
        $tStmt->execute($params);
        $transactions = $tStmt->fetchAll();
    }
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="row gy-3">
    <div class="col-12">
        <h4 class="mb-3">Customer Ledger</h4>
    </div>

    <div class="col-12">
        <form class="row g-2 mb-3" method="get">
            <div class="col-sm-4 col-md-3">
                <label class="form-label form-label-sm">Customer</label>
                <select name="customer_id" class="form-select form-select-sm" required onchange="this.form.submit()">
                    <option value="">-- Select customer --</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"
                            <?= $customerId === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-3 col-md-2">
                <label class="form-label form-label-sm">From</label>
                <input type="date" name="start_date" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-sm-3 col-md-2">
                <label class="form-label form-label-sm">To</label>
                <input type="date" name="end_date" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-sm-2 col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-sm btn-outline-light w-100">Filter</button>
            </div>
        </form>
    </div>

    <div class="col-12">
        <div class="card bg-white border-0 shadow-sm">
            <div class="card-body">
                <?php if (!$customerId): ?>
                    <p class="text-muted mb-0">Select a customer to view their ledger.</p>
                <?php elseif (!$customer): ?>
                    <p class="text-danger mb-0">Customer not found.</p>
                <?php elseif (empty($transactions)): ?>
                    <p class="text-muted mb-0">No transactions for this customer in the selected period.</p>
                <?php else: ?>
                    <div class="mb-2">
                        <strong>Customer:</strong>
                        <?= htmlspecialchars($customer['name'], ENT_QUOTES, 'UTF-8') ?>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-white table-sm align-middle mb-0">
                            <thead>
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th class="text-end">Debit</th>
                                <th class="text-end">Credit</th>
                                <th class="text-end">Balance</th>
                                <th>Notes</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            $balance = 0.0;
                            foreach ($transactions as $t):
                                $amount = (float)$t['amount'];
                                $balance += $amount;
                                $isDebit  = $amount > 0;   // customer owes you more
                                $isCredit = $amount < 0;   // customer paid you
                            ?>
                                <tr>
                                    <td><?= (int)$t['id'] ?></td>
                                    <td><?= htmlspecialchars($t['transaction_date'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($t['type'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end <?= $isDebit ? 'text-danger' : 'text-muted' ?>">
                                        <?= $isDebit ? number_format($amount, 2) : '' ?>
                                    </td>
                                    <td class="text-end <?= $isCredit ? 'text-success' : 'text-muted' ?>">
                                        <?= $isCredit ? number_format(abs($amount), 2) : '' ?>
                                    </td>
                                    <td class="text-end <?= $balance > 0 ? 'text-danger' : ($balance < 0 ? 'text-success' : 'text-muted') ?>">
                                        <?= number_format($balance, 2) ?>
                                    </td>
                                    <td><?= htmlspecialchars($t['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <p class="mt-2 text-muted" style="font-size: 0.85rem;">
                        Positive balance = customer owes you. Negative = you owe customer (rare).
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>