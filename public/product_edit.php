<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

auth_require_login();

$pdo = db();

$id      = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit  = $id > 0;
$error   = null;
$success = null;

// Fetch units and categories for dropdowns
$unitsStmt = $pdo->query('SELECT id, name, symbol FROM units WHERE is_active = 1 ORDER BY name');
$units = $unitsStmt->fetchAll();

$catStmt = $pdo->query('SELECT id, name FROM product_categories WHERE is_active = 1 ORDER BY name');
$categories = $catStmt->fetchAll();

if (!$units) {
    $error = 'You must define units in the database before adding products.';
}

$product = [
    'name'          => '',
    'sku'           => '',
    'category_id'   => null,
    'base_unit_id'  => null,
    'stock_qty'     => 0,
    'min_stock_qty' => 0,
    'price_default' => 0,
    'cost_default'  => 0,
    'is_active'     => 1,
    'image_path'    => null,
];

$productUnits = []; // existing product_units rows

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        http_response_code(404);
        echo 'Product not found.';
        exit;
    }
    $product = $existing;

    $puStmt = $pdo->prepare('
        SELECT pu.*, u.name AS unit_name, u.symbol
        FROM product_units pu
        JOIN units u ON u.id = pu.unit_id
        WHERE pu.product_id = :pid
        ORDER BY u.name
    ');
    $puStmt->execute([':pid' => $id]);
    $productUnits = $puStmt->fetchAll();
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name          = trim($_POST['name'] ?? '');
    $sku           = trim($_POST['sku'] ?? '');
    $categoryId    = $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
    $baseUnitId    = isset($_POST['base_unit_id']) ? (int)$_POST['base_unit_id'] : null;
    $stockQty      = (float)($_POST['stock_qty'] ?? 0);
    $minStockQty   = (float)($_POST['min_stock_qty'] ?? 0);
    $priceDefault  = (float)($_POST['price_default'] ?? 0);
    $costDefault   = (float)($_POST['cost_default'] ?? 0);
    $isActive      = isset($_POST['is_active']) ? 1 : 0;

    // Unit arrays
    $unitIds            = $_POST['unit_id'] ?? [];
    $conversions        = $_POST['conversion_to_base'] ?? [];
    $sellPrices         = $_POST['sell_price'] ?? [];
    $defaultsForSales   = $_POST['is_default_for_sales'] ?? [];

    // --- IMAGE UPLOAD HANDLING ---
    $currentImagePath = $product['image_path'] ?? null;
    $newImagePath = $currentImagePath;

    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $tmpName  = $_FILES['image_file']['tmp_name'];
            $origName = $_FILES['image_file']['name'];

            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($ext, $allowed, true)) {
                $error = 'Invalid image type. Allowed: jpg, jpeg, png, gif, webp.';
            } else {
                $newFileName = 'prod_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

                $uploadDir  = __DIR__ . '/../uploads/products/'; // filesystem path
                $uploadPath = $uploadDir . $newFileName;

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }

                if (!move_uploaded_file($tmpName, $uploadPath)) {
                    $error = 'Failed to save uploaded image.';
                } else {
                    // URL/path stored in DB
                    $newImagePath = '/POSM3/uploads/products/' . $newFileName;
                }
            }
        } else {
            $error = 'Error uploading image (code ' . (int)$_FILES['image_file']['error'] . ').';
        }
    }
    // --- END IMAGE UPLOAD HANDLING ---

    if ($name === '') {
        $error = 'Product name is required.';
    } elseif (!$baseUnitId) {
        $error = 'Base unit is required.';
    } elseif (empty($units)) {
        $error = 'No units defined.';
    }

    if (!$error) {
        // Check SKU uniqueness (if provided)
        if ($sku !== '') {
            $params = [':sku' => $sku];
            $sqlSku = 'SELECT id FROM products WHERE sku = :sku';
            if ($isEdit) {
                $sqlSku .= ' AND id <> :id';
                $params[':id'] = $id;
            }
            $skuStmt = $pdo->prepare($sqlSku);
            $skuStmt->execute($params);
            if ($skuStmt->fetch()) {
                $error = 'SKU is already used by another product.';
            }
        }
    }

    if (!$error) {
        // Validate units form arrays
        $unitRows = [];
        for ($i = 0; $i < count($unitIds); $i++) {
            if ($unitIds[$i] === '') {
                continue;
            }
            $uId   = (int)$unitIds[$i];
            $conv  = (float)($conversions[$i] ?? 0);
            $sPrice = $sellPrices[$i] !== '' ? (float)$sellPrices[$i] : null;
            $isDef  = isset($defaultsForSales[$i]) ? 1 : 0;

            if ($conv <= 0) {
                $error = 'Conversion to base must be positive for all units.';
                break;
            }

            $unitRows[] = [
                'unit_id'              => $uId,
                'conversion_to_base'   => $conv,
                'sell_price'           => $sPrice,
                'is_default_for_sales' => $isDef,
            ];
        }
    }

    if (!$error) {
        // Ensure base unit is included with conversion 1
        $hasBase = false;
        foreach ($unitRows as $r) {
            if ($r['unit_id'] === $baseUnitId) {
                $hasBase = true;
                break;
            }
        }
        if (!$hasBase) {
            $unitRows[] = [
                'unit_id'              => $baseUnitId,
                'conversion_to_base'   => 1,
                'sell_price'           => $priceDefault,
                'is_default_for_sales' => 1,
            ];
        }

        // Ensure exactly one default_for_sales
        $hasDefault = false;
        foreach ($unitRows as $idx => $r) {
            if ($r['is_default_for_sales']) {
                if ($hasDefault) {
                    $unitRows[$idx]['is_default_for_sales'] = 0;
                }
                $hasDefault = true;
            }
        }
        if (!$hasDefault && !empty($unitRows)) {
            $unitRows[0]['is_default_for_sales'] = 1;
        }

        try {
            $pdo->beginTransaction();

            if ($isEdit) {
                $sql = '
                    UPDATE products
                    SET name = :name,
                        sku = :sku,
                        category_id = :category_id,
                        base_unit_id = :base_unit_id,
                        price_default = :price_default,
                        cost_default = :cost_default,
                        stock_qty = :stock_qty,
                        min_stock_qty = :min_stock_qty,
                        image_path = :image_path,
                        is_active = :is_active,
                        updated_at = NOW()
                    WHERE id = :id
                ';
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':name'          => $name,
                    ':sku'           => $sku !== '' ? $sku : null,
                    ':category_id'   => $categoryId,
                    ':base_unit_id'  => $baseUnitId,
                    ':stock_qty'     => $stockQty,
                    ':min_stock_qty' => $minStockQty,
                    ':price_default' => $priceDefault,
                    ':cost_default'  => $costDefault,
                    ':image_path'    => $newImagePath,
                    ':is_active'     => $isActive,
                    ':id'            => $id,
                ]);
                $productId = $id;

                // Clear existing product_units
                $del = $pdo->prepare('DELETE FROM product_units WHERE product_id = :pid');
                $del->execute([':pid' => $productId]);
            } else {
                $sql = '
                    INSERT INTO products
                        (name, sku, category_id, base_unit_id,
                         price_default, cost_default, stock_qty, min_stock_qty,
                         image_path, is_active, created_at)
                    VALUES
                        (:name, :sku, :category_id, :base_unit_id,
                         :price_default, :cost_default, :stock_qty, :min_stock_qty,
                         :image_path, :is_active, NOW())
                ';
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':name'          => $name,
                    ':sku'           => $sku !== '' ? $sku : null,
                    ':category_id'   => $categoryId,
                    ':base_unit_id'  => $baseUnitId,
                    ':stock_qty'     => $stockQty,
                    ':min_stock_qty' => $minStockQty,
                    ':price_default' => $priceDefault,
                    ':cost_default'  => $costDefault,
                    ':image_path'    => $newImagePath,
                    ':is_active'     => $isActive,
                ]);
                $productId = (int)$pdo->lastInsertId();
                $isEdit = true;
            }

            // Insert product_units
            $ins = $pdo->prepare('
                INSERT INTO product_units
                    (product_id, unit_id, conversion_to_base, sell_price, is_default_for_sales)
                VALUES
                    (:product_id, :unit_id, :conversion_to_base, :sell_price, :is_default_for_sales)
            ');
            foreach ($unitRows as $r) {
                $ins->execute([
                    ':product_id'          => $productId,
                    ':unit_id'             => $r['unit_id'],
                    ':conversion_to_base'  => $r['conversion_to_base'],
                    ':sell_price'          => $r['sell_price'],
                    ':is_default_for_sales'=> $r['is_default_for_sales'],
                ]);
            }

            $pdo->commit();
            $success = $isEdit ? 'Product updated successfully.' : 'Product created successfully.';

            // Reload data for display
            $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id');
            $stmt->execute([':id' => $productId]);
            $product = $stmt->fetch();

            $puStmt = $pdo->prepare('
                SELECT pu.*, u.name AS unit_name, u.symbol
                FROM product_units pu
                JOIN units u ON u.id = pu.unit_id
                WHERE pu.product_id = :pid
                ORDER BY u.name
            ');
            $puStmt->execute([':pid' => $productId]);
            $productUnits = $puStmt->fetchAll();

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error saving product: ' . $e->getMessage();
        }
    }

    // Ensure product array has latest image path for preview
    $product['image_path'] = $product['image_path'] ?? $newImagePath;
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="row">
    <div class="col-lg-6">
        <div class="card bg-white border-0 shadow-sm mb-3">
            <div class="card-body">
                <h5 class="card-title mb-3">
                    <?= $isEdit ? 'Edit Product' : 'New Product' ?>
                </h5>

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

                <form method="post" enctype="multipart/form-data" novalidate>
                    <div class="mb-3">
                        <label for="name" class="form-label">Product name</label>
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            id="name"
                            name="name"
                            required
                            value="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="sku" class="form-label">SKU (optional)</label>
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            id="sku"
                            name="sku"
                            value="<?= htmlspecialchars($product['sku'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="category_id" class="form-label">Category</label>
                        <select id="category_id" name="category_id" class="form-select form-select-sm">
                            <option value="">-- None --</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"
                                    <?= $product['category_id'] == $c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="base_unit_id" class="form-label">Base unit</label>
                        <select id="base_unit_id" name="base_unit_id" class="form-select form-select-sm" required>
                            <option value="">-- Select base unit --</option>
                            <?php foreach ($units as $u): ?>
                                <option value="<?= (int)$u['id'] ?>"
                                    <?= $product['base_unit_id'] == $u['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['name'] . ' (' . $u['symbol'] . ')', ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-muted" style="font-size: 0.8rem;">
                            Stock is stored in base units.
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label for="stock_qty" class="form-label">Current stock (base)</label>
                            <input
                                type="number"
                                step="0.0001"
                                class="form-control form-control-sm"
                                id="stock_qty"
                                name="stock_qty"
                                value="<?= htmlspecialchars($product['stock_qty'], ENT_QUOTES, 'UTF-8') ?>"
                            >
                        </div>
                        <div class="col-6">
                            <label for="min_stock_qty" class="form-label">Min stock (alert)</label>
                            <input
                                type="number"
                                step="0.0001"
                                class="form-control form-control-sm"
                                id="min_stock_qty"
                                name="min_stock_qty"
                                value="<?= htmlspecialchars($product['min_stock_qty'], ENT_QUOTES, 'UTF-8') ?>"
                            >
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label for="price_default" class="form-label">Default price (per base)</label>
                            <input
                                type="number"
                                step="0.0001"
                                class="form-control form-control-sm"
                                id="price_default"
                                name="price_default"
                                value="<?= htmlspecialchars($product['price_default'], ENT_QUOTES, 'UTF-8') ?>"
                            >
                        </div>
                        <div class="col-6">
                            <label for="cost_default" class="form-label">Default cost (per base)</label>
                            <input
                                type="number"
                                step="0.0001"
                                class="form-control form-control-sm"
                                id="cost_default"
                                name="cost_default"
                                value="<?= htmlspecialchars($product['cost_default'], ENT_QUOTES, 'UTF-8') ?>"
                            >
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="image_file" class="form-label">Product image</label>
                        <input
                            type="file"
                            class="form-control form-control-sm"
                            id="image_file"
                            name="image_file"
                            accept="image/*"
                        >
                        <div class="form-text text-muted" style="font-size:0.8rem;">
                            Allowed types: jpg, jpeg, png, gif, webp.
                        </div>
                    </div>

                    <?php if (!empty($product['image_path'])): ?>
                        <div class="mb-3">
                            <label class="form-label">Current image</label>
                            <div>
                                <img src="<?= htmlspecialchars($product['image_path'], ENT_QUOTES, 'UTF-8') ?>"
                                     alt="Product image"
                                     style="max-width: 200px; max-height: 150px; object-fit: cover; border-radius: 4px;">
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="form-check form-switch mb-3">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="is_active"
                            name="is_active"
                            value="1"
                            <?= (int)$product['is_active'] === 1 ? 'checked' : '' ?>
                        >
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="/POSM3/public/products_list.php" class="btn btn-outline-secondary btn-sm">
                            Back to list
                        </a>
                        <button type="submit" class="btn btn-primary btn-sm">
                            Save product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Units configuration -->
    <div class="col-lg-6">
        <div class="card bg-white border-0 shadow-sm">
            <div class="card-body">
                <h6 class="card-title mb-2">Units for this product</h6>
                <p class="text-muted" style="font-size: 0.8rem;">
                    Define how this product can be sold/purchased (box, pack, kg, etc.).  
                    1 unit = conversion_to_base × base unit.
                </p>

                <div class="table-responsive mb-2">
                    <table class="table table-white table-sm align-middle mb-0" id="unitsTable">
                        <thead>
                        <tr>
                            <th>Unit</th>
                            <th>Conversion<br><small>(to base)</small></th>
                            <th>Sell price<br><small>(per unit)</small></th>
                            <th>Default<br>for sale</th>
                            <th style="width: 40px;"></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        if (!empty($productUnits)) {
                            foreach ($productUnits as $idx => $pu):
                                ?>
                                <tr>
                                    <td>
                                        <select name="unit_id[]" class="form-select form-select-sm">
                                            <option value="">-- select --</option>
                                            <?php foreach ($units as $u): ?>
                                                <option value="<?= (int)$u['id'] ?>"
                                                    <?= $pu['unit_id'] == $u['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($u['name'] . ' (' . $u['symbol'] . ')', ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" step="0.0001" class="form-control form-control-sm"
                                               name="conversion_to_base[]"
                                               value="<?= htmlspecialchars($pu['conversion_to_base'], ENT_QUOTES, 'UTF-8') ?>">
                                    </td>
                                    <td>
                                        <input type="number" step="0.0001" class="form-control form-control-sm"
                                               name="sell_price[]"
                                               value="<?= htmlspecialchars($pu['sell_price'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    </td>
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check-input"
                                               name="is_default_for_sales[<?= $idx ?>]"
                                            <?= (int)$pu['is_default_for_sales'] === 1 ? 'checked' : '' ?>>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row">&times;</button>
                                    </td>
                                </tr>
                                <?php
                            endforeach;
                        } else {
                            ?>
                            <tr>
                                <td>
                                    <select name="unit_id[]" class="form-select form-select-sm">
                                        <option value="">-- select --</option>
                                        <?php foreach ($units as $u): ?>
                                            <option value="<?= (int)$u['id'] ?>">
                                                <?= htmlspecialchars($u['name'] . ' (' . $u['symbol'] . ')', ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" step="0.0001" class="form-control form-control-sm"
                                           name="conversion_to_base[]" value="1">
                                </td>
                                <td>
                                    <input type="number" step="0.0001" class="form-control form-control-sm"
                                           name="sell_price[]" value="">
                                </td>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input"
                                           name="is_default_for_sales[0]" checked>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row">&times;</button>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>

                <button type="button" class="btn btn-sm btn-outline-secondary" id="addUnitRow">
                    + Add unit
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tableBody = document.querySelector('#unitsTable tbody');
    const addBtn = document.getElementById('addUnitRow');

    function bindRemoveButtons() {
        document.querySelectorAll('.btn-remove-row').forEach(function (btn) {
            btn.onclick = function () {
                const rowCount = tableBody.rows.length;
                if (rowCount > 1) {
                    btn.closest('tr').remove();
                }
            };
        });
    }

    addBtn.addEventListener('click', function () {
        const firstRow = tableBody.rows[0];
        const newRow = firstRow.cloneNode(true);

        newRow.querySelectorAll('input').forEach(function (input) {
            if (input.type === 'checkbox') {
                input.checked = false;
            } else {
                input.value = '';
            }
        });
        newRow.querySelectorAll('select').forEach(function (select) {
            select.selectedIndex = 0;
        });

        tableBody.appendChild(newRow);
        bindRemoveButtons();
    });

    bindRemoveButtons();
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>