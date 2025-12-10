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

$customer = [
    'name'       => '',
    'phone'      => '',
    'email'      => '',
    'address'    => '',
    'notes'      => '',
    'is_active'  => 1,
];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        http_response_code(404);
        echo 'Customer not found.';
        exit;
    }

    $customer = $existing;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $address   = trim($_POST['address'] ?? '');
    $notes     = trim($_POST['notes'] ?? '');
    $isActive  = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') {
        $error = 'Customer name is required.';
    } else {
        try {
            if ($isEdit) {
                $sql = '
                    UPDATE customers
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
                $success = 'Customer updated successfully.';
            } else {
                $sql = '
                    INSERT INTO customers
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
                header('Location: /POSM3/public/customer_edit.php?id=' . $newId);
                exit;
            }

            // Refresh current data
            $customer['name']      = $name;
            $customer['phone']     = $phone;
            $customer['email']     = $email;
            $customer['address']   = $address;
            $customer['notes']     = $notes;
            $customer['is_active'] = $isActive;
        } catch (Exception $e) {
            $error = 'Error saving customer: ' . $e->getMessage();
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
                    <?= $isEdit ? 'Edit Customer' : 'New Customer' ?>
                </h5>
                <p class="text-muted" style="font-size: 0.9rem;">
                    Store customer information for credit sales and loans.
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
                        <label for="name" class="form-label">Customer name</label>
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            id="name"
                            name="name"
                            required
                            value="<?= htmlspecialchars($customer['name'], ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            id="phone"
                            name="phone"
                            value="<?= htmlspecialchars($customer['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input
                            type="email"
                            class="form-control form-control-sm"
                            id="email"
                            name="email"
                            value="<?= htmlspecialchars($customer['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea
                            class="form-control form-control-sm"
                            id="address"
                            name="address"
                            rows="2"
                        ><?= htmlspecialchars($customer['address'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea
                            class="form-control form-control-sm"
                            id="notes"
                            name="notes"
                            rows="2"
                        ><?= htmlspecialchars($customer['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="is_active"
                            name="is_active"
                            value="1"
                            <?= (int)$customer['is_active'] === 1 ? 'checked' : '' ?>
                        >
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="/POSM3/public/customers_list.php" class="btn btn-outline-light btn-sm">
                            Back to list
                        </a>
                        <button type="submit" class="btn btn-primary btn-sm">
                            Save customer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>