<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

auth_require_login();

$pdo = db();

$supplierId = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;

// Load suppliers for dropdown
$sStmt = $pdo->query("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name");
$suppliers = $sStmt->fetchAll();

$supplier = null;
$transactions = [];
$startDate = $_GET['start_date'] ?? '';
$endDate   = $_GET['end_date'] ?? '';

if ($supplierId > 0) {
    $stmt = $pdo->prepare("SELECT id, name FROM suppliers WHERE id = :id");
    $stmt->execute([':id' => $supplierId]);
    $supplier = $stmt->fetch();

    if ($supplier) {
        $sql = "
            SELECT
                id,
                transaction_date,
                type,
                amount,
                related_purchase_id,
                related_payment_id,
                notes
            FROM supplier_transactions
            WHERE supplier_id = :sid
        ";
        $params = [':sid' => $supplierId];

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
        <h4 class="mb-3">Supplier Ledger</h4>
    </div>

    <div class="col-12">
        <form class="row g-2 mb-3" method="get">
            <div class="col-sm-4 col-md-3">
                <label class="form-label form-label-sm">Supplier</label>
                <select name="supplier_id" class="form-select form-select-sm" required onchange="this.form.submit()">
                    <option value="">-- Select supplier --</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"
                            <?= $supplierId === (int)$s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?>
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
                <?php if (!$supplierId): ?>
                    <p class="text-muted mb-0">Select a supplier to view their ledger.</p>
                <?php elseif (!$supplier): ?>
                    <p class="text-danger mb-0">Supplier not found.</p>
                <?php elseif (empty($transactions)): ?>
                    <p class="text-muted mb-0">No transactions for this supplier in the selected period.</p>
                <?php else: ?>
                    <div class="mb-2">
                        <strong>Supplier:</strong>
                        <?= htmlspecialchars($supplier['name'], ENT_QUOTES, 'UTF-8') ?>
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
                                // For suppliers: positive = you owe them (debit), negative = they owe you (credit)
                                $isDebit  = $amount > 0;
                                $isCredit = $amount < 0;
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
                        Positive balance = you owe the supplier. Negative = supplier owes you (credit).
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>