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

$unit = [
    'name'      => '',
    'symbol'    => '',
    'is_active' => 1,
];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM units WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        http_response_code(404);
        echo 'Unit not found.';
        exit;
    }

    $unit = $existing;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $symbol    = trim($_POST['symbol'] ?? '');
    $isActive  = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') {
        $error = 'Unit name is required.';
    } elseif ($symbol === '') {
        $error = 'Unit symbol is required (e.g. PCS, BOX, KG).';
    } else {
        try {
            if ($isEdit) {
                $sql = '
                    UPDATE units
                    SET name = :name,
                        symbol = :symbol,
                        is_active = :is_active,
                        updated_at = NOW()
                    WHERE id = :id
                ';
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':name'      => $name,
                    ':symbol'    => $symbol,
                    ':is_active' => $isActive,
                    ':id'        => $id,
                ]);
                $success = 'Unit updated successfully.';
            } else {
                $sql = '
                    INSERT INTO units
                        (name, symbol, is_active, created_at)
                    VALUES
                        (:name, :symbol, :is_active, NOW())
                ';
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':name'      => $name,
                    ':symbol'    => $symbol,
                    ':is_active' => $isActive,
                ]);
                $newId = (int)$pdo->lastInsertId();
                header('Location: /POSM3/public/unit_edit.php?id=' . $newId);
                exit;
            }

            $unit['name']      = $name;
            $unit['symbol']    = $symbol;
            $unit['is_active'] = $isActive;
        } catch (Exception $e) {
            $error = 'Error saving unit: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card bg-secondary border-0 shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3">
                    <?= $isEdit ? 'Edit Unit' : 'New Unit' ?>
                </h5>
                <p class="text-muted" style="font-size: 0.9rem;">
                    Define measurement units like PCS, BOX, PACK, KG for your products.
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
                        <label for="name" class="form-label">Unit name</label>
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            id="name"
                            name="name"
                            required
                            value="<?= htmlspecialchars($unit['name'], ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="symbol" class="form-label">Symbol</label>
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            id="symbol"
                            name="symbol"
                            required
                            value="<?= htmlspecialchars($unit['symbol'], ENT_QUOTES, 'UTF-8') ?>"
                        >
                        <div class="form-text text-muted" style="font-size: 0.8rem;">
                            Short code used in product screens (e.g. PCS, BOX, KG).
                        </div>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="is_active"
                            name="is_active"
                            value="1"
                            <?= (int)$unit['is_active'] === 1 ? 'checked' : '' ?>
                        >
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="/POSM3/public/units_list.php" class="btn btn-outline-light btn-sm">
                            Back to list
                        </a>
                        <button type="submit" class="btn btn-primary btn-sm">
                            Save unit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>