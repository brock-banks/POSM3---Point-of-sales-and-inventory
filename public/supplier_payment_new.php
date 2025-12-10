<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

auth_require_login();

$pdo = db();
$user = auth_user();

$error   = null;
$success = null;

// Load suppliers
$sStmt = $pdo->query("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name");
$suppliers = $sStmt->fetchAll();

$supplierId = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplierId = (int)($_POST['supplier_id'] ?? 0);
    $amount     = (float)($_POST['amount'] ?? 0);
    $method     = $_POST['method'] ?? 'CASH';
    $reference  = trim($_POST['reference'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');

    if ($supplierId <= 0) {
        $error = 'Please select a supplier.';
    } elseif ($amount <= 0) {
        $error = 'Amount must be greater than zero.';
    } else {
        // Validate supplier exists
        $sCheck = $pdo->prepare("SELECT id, name FROM suppliers WHERE id = :id");
        $sCheck->execute([':id' => $supplierId]);
        $supplier = $sCheck->fetch();

        if (!$supplier) {
            $error = 'Supplier not found.';
        } else {
            try {
                $pdo->beginTransaction();

                // Insert into supplier_payments
                $pStmt = $pdo->prepare("
                    INSERT INTO supplier_payments
                        (supplier_id, payment_date, amount, method, reference, notes, created_by, created_at)
                    VALUES
                        (:supplier_id, NOW(), :amount, :method, :reference, :notes, :created_by, NOW())
                ");
                $pStmt->execute([
                    ':supplier_id' => $supplierId,
                    ':amount'      => $amount,
                    ':method'      => $method,
                    ':reference'   => $reference !== '' ? $reference : null,
                    ':notes'       => $notes !== '' ? $notes : null,
                    ':created_by'  => $user ? $user['id'] : null,
                ]);
                $paymentId = (int)$pdo->lastInsertId();

                // Insert into supplier_transactions (payment reduces liability, so negative amount)
                $tStmt = $pdo->prepare("
                    INSERT INTO supplier_transactions
                        (supplier_id, transaction_date, type, amount, related_purchase_id, related_payment_id, notes, created_by, created_at)
                    VALUES
                        (:supplier_id, NOW(), 'PAYMENT', :amount, NULL, :payment_id, :notes, :created_by, NOW())
                ");
                $tStmt->execute([
                    ':supplier_id' => $supplierId,
                    ':amount'      => -$amount,
                    ':payment_id'  => $paymentId,
                    ':notes'       => $notes !== '' ? $notes : null,
                    ':created_by'  => $user ? $user['id'] : null,
                ]);

                $pdo->commit();
                $success = 'Supplier payment recorded successfully.';
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error saving payment: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card bg-dark border-0 shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3">New Supplier Payment</h5>
                <p class="text-muted" style="font-size: 0.9rem;">
                    Record a payment made to a supplier. This will reduce your outstanding balance to them.
                </p>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2">
                        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success py-2">
                        <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <div class="mb-3">
                        <label for="supplier_id" class="form-label">Supplier</label>
                        <select id="supplier_id" name="supplier_id" class="form-select form-select-sm" required>
                            <option value="">-- Select supplier --</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= (int)$s['id'] ?>"
                                    <?= $supplierId === (int)$s['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            class="form-control form-control-sm"
                            id="amount"
                            name="amount"
                            required
                            value="<?= isset($_POST['amount']) ? htmlspecialchars($_POST['amount'], ENT_QUOTES, 'UTF-8') : '' ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="method" class="form-label">Payment method</label>
                        <select id="method" name="method" class="form-select form-select-sm">
                            <option value="CASH">Cash</option>
                            <option value="CARD">Card</option>
                            <option value="BANK_TRANSFER">Bank transfer</option>
                            <option value="MOBILE">Mobile</option>
                            <option value="OTHER">Other</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="reference" class="form-label">Reference (optional)</label>
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            id="reference"
                            name="reference"
                            value="<?= htmlspecialchars($_POST['reference'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes (optional)</label>
                        <textarea
                            class="form-control form-control-sm"
                            id="notes"
                            name="notes"
                            rows="2"
                        ><?= htmlspecialchars($_POST['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="/POSM3/public/supplier_payments_list.php" class="btn btn-outline-light btn-sm">
                            Back to list
                        </a>
                        <button type="submit" class="btn btn-primary btn-sm">
                            Save payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>