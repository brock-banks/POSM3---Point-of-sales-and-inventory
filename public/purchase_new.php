<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

auth_require_login();

$pdo  = db();
$user = auth_user();

$error   = null;
$success = null;

// Suppliers
$sStmt = $pdo->query("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name");
$suppliers = $sStmt->fetchAll();

// Products (include base_unit_id so we can satisfy unit_id FK)
$prStmt = $pdo->query("
    SELECT p.id, p.name, p.sku, p.stock_qty, p.base_unit_id
    FROM products p
    WHERE p.is_active = 1
    ORDER BY p.name
");
$products = $prStmt->fetchAll();

// Build a map product_id => base_unit_id
$productBaseUnits = [];
foreach ($products as $p) {
    $productBaseUnits[(int)$p['id']] = (int)($p['base_unit_id'] ?? 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplierId   = (int)($_POST['supplier_id'] ?? 0);
    $purchaseDate = $_POST['purchase_date'] ?? date('Y-m-d');
    $notes        = trim($_POST['notes'] ?? '');

    $productIds = $_POST['product_id'] ?? [];
    $quantities = $_POST['qty'] ?? [];
    $costs      = $_POST['unit_cost'] ?? [];

    if ($supplierId <= 0) {
        $error = 'Please select a supplier.';
    } else {
        $items = [];
        $total = 0;

        for ($i = 0; $i < count($productIds); $i++) {
            if ($productIds[$i] === '') {
                continue;
            }

            $pid  = (int)$productIds[$i];
            $qty  = (float)($quantities[$i] ?? 0);
            $cost = (float)($costs[$i] ?? 0);

            if ($qty <= 0 || $cost < 0) {
                $error = 'Quantity must be > 0 and cost >= 0 for all items.';
                break;
            }

            if (!isset($productBaseUnits[$pid]) || $productBaseUnits[$pid] <= 0) {
                $error = "Product ID $pid does not have a valid base unit defined.";
                break;
            }

            $unitId    = $productBaseUnits[$pid];
            $lineTotal = $qty * $cost;
            $total    += $lineTotal;

            $items[] = [
                'product_id'    => $pid,
                'unit_id'       => $unitId,
                'qty_unit'      => $qty,
                'qty_base'      => $qty,        // same as unit for now
                'unit_cost'     => $cost,
                'subtotal_cost' => $lineTotal,
            ];
        }

        if (!$error && empty($items)) {
            $error = 'Add at least one product line.';
        }

        if (!$error) {
            try {
                $pdo->beginTransaction();

                // Purchases header
                $pStmt = $pdo->prepare("
                    INSERT INTO purchases
                        (supplier_id, purchase_date, total_amount, status, notes, created_by, created_at)
                    VALUES
                        (:supplier_id, :purchase_date, :total_amount, 'POSTED', :notes, :created_by, NOW())
                ");
                $pStmt->execute([
                    ':supplier_id'   => $supplierId,
                    ':purchase_date' => $purchaseDate,
                    ':total_amount'  => $total,
                    ':notes'         => $notes !== '' ? $notes : null,
                    ':created_by'    => $user ? $user['id'] : null,
                ]);
                $purchaseId = (int)$pdo->lastInsertId();

                // purchase_items insert – matches your table:
                // id, purchase_id, product_id, unit_id, qty_unit, qty_base, unit_cost, subtotal_cost
                $iStmt = $pdo->prepare("
                    INSERT INTO purchase_items
                        (purchase_id, product_id, unit_id, qty_unit, qty_base, unit_cost, subtotal_cost)
                    VALUES
                        (:purchase_id, :product_id, :unit_id, :qty_unit, :qty_base, :unit_cost, :subtotal_cost)
                ");

                // products stock update
                $stockStmt = $pdo->prepare("
                    UPDATE products
                    SET stock_qty = stock_qty + :qty_base,
                        cost_default = :cost,
                        updated_at = NOW()
                    WHERE id = :product_id
                ");

                // Stock movements (if table exists)
                $hasStockMovements = false;
                $mvStmt = null;
                try {
                    $check = $pdo->query("SHOW TABLES LIKE 'stock_movements'");
                    if ($check && $check->fetchColumn()) {
                        $hasStockMovements = true;
                        $mvStmt = $pdo->prepare("
                            INSERT INTO stock_movements
                                (product_id, movement_date, source_type, source_id, qty_change, note, created_by, created_at)
                            VALUES
                                (:product_id, :movement_date, 'PURCHASE', :source_id, :qty_change, :note, :created_by, NOW())
                        ");
                    }
                } catch (Exception $ignore) {
                    $hasStockMovements = false;
                }

                foreach ($items as $it) {
                    $iStmt->execute([
                        ':purchase_id'   => $purchaseId,
                        ':product_id'    => $it['product_id'],
                        ':unit_id'       => $it['unit_id'],
                        ':qty_unit'      => $it['qty_unit'],
                        ':qty_base'      => $it['qty_base'],
                        ':unit_cost'     => $it['unit_cost'],
                        ':subtotal_cost' => $it['subtotal_cost'],
                    ]);

                    $stockStmt->execute([
                        ':qty_base'   => $it['qty_base'],
                        ':cost'       => $it['unit_cost'],
                        ':product_id' => $it['product_id'],
                    ]);

                    if ($hasStockMovements && $mvStmt) {
                        $mvStmt->execute([
                            ':product_id'    => $it['product_id'],
                            ':movement_date' => $purchaseDate . ' 00:00:00',
                            ':source_id'     => $purchaseId,
                            ':qty_change'    => $it['qty_base'],
                            ':note'          => $notes !== '' ? $notes : null,
                            ':created_by'    => $user ? $user['id'] : null,
                        ]);
                    }
                }

                // Supplier transaction
                $tStmt = $pdo->prepare("
                    INSERT INTO supplier_transactions
                        (supplier_id, transaction_date, type, amount, related_purchase_id, related_payment_id, notes, created_by, created_at)
                    VALUES
                        (:supplier_id, :transaction_date, 'PURCHASE', :amount, :purchase_id, NULL, :notes, :created_by, NOW())
                ");
                $tStmt->execute([
                    ':supplier_id'      => $supplierId,
                    ':transaction_date' => $purchaseDate,
                    ':amount'           => $total,
                    ':purchase_id'      => $purchaseId,
                    ':notes'            => $notes !== '' ? $notes : null,
                    ':created_by'       => $user ? $user['id'] : null,
                ]);

                $pdo->commit();
                $success = 'Purchase recorded successfully.';
                $_POST = []; // reset form
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Error saving purchase: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../templates/header.php';
?>

<form method="post" id="purchaseForm" novalidate>
    <div class="row">
        <div class="col-lg-4">
            <div class="card bg-white border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h5 class="card-title mb-3">New Purchase</h5>

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

                    <div class="mb-3">
                        <label for="supplier_id" class="form-label">Supplier</label>
                        <select id="supplier_id" name="supplier_id" class="form-select form-select-sm" required>
                            <option value="">-- Select supplier --</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= (int)$s['id'] ?>"
                                    <?= (($_POST['supplier_id'] ?? '') == $s['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="purchase_date" class="form-label">Date</label>
                        <input
                            type="date"
                            class="form-control form-control-sm"
                            id="purchase_date"
                            name="purchase_date"
                            value="<?= htmlspecialchars($_POST['purchase_date'] ?? date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea
                            class="form-control form-control-sm"
                            id="notes"
                            name="notes"
                            rows="2"
                        ><?= htmlspecialchars($_POST['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <p class="text-muted" style="font-size: 0.85rem;">
                        Add items on the right, then click "Save purchase".
                    </p>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card bg-white border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title mb-2">Items</h6>
                    <p class="text-muted" style="font-size: 0.8rem;">
                        Quantities and costs are assumed in base units for now.
                    </p>

                    <div class="table-responsive mb-2">
                        <table class="table table-white table-sm align-middle mb-0" id="itemsTable">
                            <thead>
                            <tr>
                                <th>Product</th>
                                <th style="width: 90px;">Qty</th>
                                <th style="width: 110px;">Unit cost</th>
                                <th class="text-end" style="width: 120px;">Line total</th>
                                <th style="width: 40px;"></th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td>
                                    <select name="product_id[]" class="form-select form-select-sm">
                                        <option value="">-- select --</option>
                                        <?php foreach ($products as $p): ?>
                                            <option value="<?= (int)$p['id'] ?>">
                                                <?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" step="0.0001" min="0"
                                           class="form-control form-control-sm qty-input"
                                           name="qty[]" value="0">
                                </td>
                                <td>
                                    <input type="number" step="0.0001" min="0"
                                           class="form-control form-control-sm cost-input"
                                           name="unit_cost[]" value="0">
                                </td>
                                <td class="text-end">
                                    <span class="line-total">0.00</span>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row">&times;</button>
                                </td>
                            </tr>
                            </tbody>
                            <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Total:</th>
                                <th class="text-end"><span id="grandTotal">0.00</span></th>
                                <th></th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>

                    <button type="button" class="btn btn-sm btn-outline-secondary mb-2" id="addItemRow">
                        + Add item
                    </button>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary btn-sm">
                            Save purchase
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tableBody    = document.querySelector('#itemsTable tbody');
    const addBtn       = document.getElementById('addItemRow');
    const grandTotalEl = document.getElementById('grandTotal');

    function recalcRow(row) {
        const qtyInput    = row.querySelector('.qty-input');
        const costInput   = row.querySelector('.cost-input');
        const lineTotalEl = row.querySelector('.line-total');

        const qty   = parseFloat(qtyInput.value) || 0;
        const cost  = parseFloat(costInput.value) || 0;
        const total = qty * cost;
        lineTotalEl.textContent = total.toFixed(2);
    }

    function recalcTotal() {
        let total = 0;
        tableBody.querySelectorAll('tr').forEach(function (row) {
            const lineTotalEl = row.querySelector('.line-total');
            total += parseFloat(lineTotalEl.textContent) || 0;
        });
        grandTotalEl.textContent = total.toFixed(2);
    }

    function bindRowEvents(row) {
        const qtyInput  = row.querySelector('.qty-input');
        const costInput = row.querySelector('.cost-input');
        const removeBtn = row.querySelector('.btn-remove-row');

        qtyInput.addEventListener('input', function () {
            recalcRow(row);
            recalcTotal();
        });

        costInput.addEventListener('input', function () {
            recalcRow(row);
            recalcTotal();
        });

        removeBtn.addEventListener('click', function () {
            if (tableBody.rows.length > 1) {
                row.remove();
            } else {
                row.querySelectorAll('select').forEach(function (select) {
                    select.selectedIndex = 0;
                });
                row.querySelectorAll('input').forEach(function (input) {
                    if (input.classList.contains('qty-input') ||
                        input.classList.contains('cost-input')) {
                        input.value = '0';
                    } else {
                        input.value = '';
                    }
                });
                row.querySelector('.line-total').textContent = '0.00';
            }
            recalcTotal();
        });
    }

    addBtn.addEventListener('click', function () {
        const firstRow = tableBody.rows[0];
        const newRow   = firstRow.cloneNode(true);

        newRow.querySelectorAll('select').forEach(function (select) {
            select.selectedIndex = 0;
        });
        newRow.querySelectorAll('input').forEach(function (input) {
            if (input.classList.contains('qty-input') ||
                input.classList.contains('cost-input')) {
                input.value = '0';
            } else {
                input.value = '';
            }
        });
        newRow.querySelector('.line-total').textContent = '0.00';

        tableBody.appendChild(newRow);
        bindRowEvents(newRow);
    });

    Array.from(tableBody.rows).forEach(bindRowEvents);
    recalcTotal();
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>