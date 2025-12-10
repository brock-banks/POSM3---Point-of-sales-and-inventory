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

$supplier = [
    'name'       => '',
    'phone'      => '',
    'email'      => '',
    'address'    => '',
    'notes'      => '',
    'is_active'  => 1,
];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM suppliers WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        http_response_code(404);
        echo 'Supplier not found.';
        exit;
    }

    $supplier = $existing;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $address   = trim($_POST['address'] ?? '');
    $notes     = trim($_POST['notes'] ?? '');
    $isActive  = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') {
        $error = 'Supplier name is required.';
    } else {
        try {
            if ($isEdit) {
                $sql = '
                    UPDATE suppliers
                    SET name = :name,
                        phone = :phone,
                        email = :email,
                        address = :address,
                        notes = :notes,
                        is_active = :is_active,
                        updated_at = NOW()
                    WHERE id = :id
                ';
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':name'      => $name,
                    ':phone'     => $phone !== '' ? $phone : null,
                    ':email'     => $email !== '' ? $email : null,
                    ':address'   => $address !== '' ? $address : null,
                    ':notes'     => $notes !== '' ? $notes : null,
                    ':is_active' => $isActive,
                    ':id'        => $id,
                ]);
                $success = 'Supplier updated successfully.';
            } else {
                $sql = '
                    INSERT INTO suppliers
                    (name, phone, email, address, notes, is_active, created_at)
                    VALUES
                    (:name, :phone, :email, :address, :notes, :is_active, NOW())
                ';
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':name'      => $name,
                    ':phone'     => $phone !== '' ? $phone : null,
                    ':email'     => $email !== '' ? $email : null,
                    ':address'   => $address !== '' ? $address : null,
                    ':notes'     => $notes !== '' ? $notes : null,
                    ':is_active' => $isActive,
                ]);
                $newId = (int)$pdo->lastInsertId();
                header('Location: /POSM3/public/supplier_edit.php?id=' . $newId);
                exit;
            }

            // Refresh current data
            $supplier['name']      = $name;
            $supplier['phone']     = $phone;
            $supplier['email']     = $email;
            $supplier['address']   = $address;
            $supplier['notes']     = $notes;
            $supplier['is_active'] = $isActive;
        } catch (Exception $e) {
            $error = 'Error saving supplier: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card bg-white border-0 shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3">
                    <?= $isEdit ? 'Edit Supplier' : 'New Supplier' ?>
                </h5>
                <p class="text-muted" style="font-size: 0.9rem;">
                    Store supplier information for purchases and debts.
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
                        <label for="name" class="form-label">Supplier name</label>
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            id="name"
                            name="name"
                            required
                            value="<?= htmlspecialchars($supplier['name'], ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            id="phone"
                            name="phone"
                            value="<?= htmlspecialchars($supplier['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input
                            type="email"
                            class="form-control form-control-sm"
                            id="email"
                            name="email"
                            value="<?= htmlspecialchars($supplier['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea
                            class="form-control form-control-sm"
                            id="address"
                            name="address"
                            rows="2"
                        ><?= htmlspecialchars($supplier['address'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea
                            class="form-control form-control-sm"
                            id="notes"
                            name="notes"
                            rows="2"
                        ><?= htmlspecialchars($supplier['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="is_active"
                            name="is_active"
                            value="1"
                            <?= (int)$supplier['is_active'] === 1 ? 'checked' : '' ?>
                        >
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="/POSM3/public/suppliers_list.php" class="btn btn-outline-light btn-sm">
                            Back to list
                        </a>
                        <button type="submit" class="btn btn-primary btn-sm">
                            Save supplier
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>