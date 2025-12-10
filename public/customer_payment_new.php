<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

auth_require_login();

$pdo = db();
$user = auth_user();

$error   = null;
$success = null;

// Load customers
$cStmt = $pdo->query("SELECT id, name FROM customers WHERE is_active = 1 ORDER BY name");
$customers = $cStmt->fetchAll();

$customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $amount     = (float)($_POST['amount'] ?? 0);
    $method     = $_POST['method'] ?? 'CASH';
    $reference  = trim($_POST['reference'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');

    if ($customerId <= 0) {
        $error = 'Please select a customer.';
    } elseif ($amount <= 0) {
        $error = 'Amount must be greater than zero.';
    } else {
        // Validate customer exists
        $cCheck = $pdo->prepare("SELECT id, name FROM customers WHERE id = :id");
        $cCheck->execute([':id' => $customerId]);
        $customer = $cCheck->fetch();

        if (!$customer) {
            $error = 'Customer not found.';
        } else {
            try {
                $pdo->beginTransaction();

                // Insert into customer_payments
                $pStmt = $pdo->prepare("
                    INSERT INTO customer_payments
                        (customer_id, payment_date, amount, method, reference, notes, created_by, created_at)
                    VALUES
                        (:customer_id, NOW(), :amount, :method, :reference, :notes, :created_by, NOW())
                ");
                $pStmt->execute([
                    ':customer_id' => $customerId,
                    ':amount'      => $amount,
                    ':method'      => $method,
                    ':reference'   => $reference !== '' ? $reference : null,
                    ':notes'       => $notes !== '' ? $notes : null,
                    ':created_by'  => $user ? $user['id'] : null,
                ]);
                $paymentId = (int)$pdo->lastInsertId();

                // Insert into customer_transactions (payment reduces balance, so negative amount)
                $tStmt = $pdo->prepare("
                    INSERT INTO customer_transactions
                        (customer_id, transaction_date, type, amount, related_sale_id, related_payment_id, notes, created_by, created_at)
                    VALUES
                        (:customer_id, NOW(), 'PAYMENT', :amount, NULL, :payment_id, :notes, :created_by, NOW())
                ");
                $tStmt->execute([
                    ':customer_id' => $customerId,
                    ':amount'      => -$amount,
                    ':payment_id'  => $paymentId,
                    ':notes'       => $notes !== '' ? $notes : null,
                    ':created_by'  => $user ? $user['id'] : null,
                ]);

                $pdo->commit();
                $success = 'Payment recorded successfully.';
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
        <div class="card bg-white border-0 shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3">New Customer Payment</h5>
                <p class="text-muted" style="font-size: 0.9rem;">
                    Record a payment received from a customer. This will reduce their outstanding balance.
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
                        <label for="customer_id" class="form-label">Customer</label>
                        <select id="customer_id" name="customer_id" class="form-select form-select-sm" required>
                            <option value="">-- Select customer --</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"
                                    <?= $customerId === (int)$c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?>
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
                        <a href="/POSM3/public/customer_payments_list.php" class="btn btn-outline-light btn-sm">
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