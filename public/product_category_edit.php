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

$category = [
    'name'        => '',
    'description' => '',
    'is_active'   => 1,
];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM product_categories WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        http_response_code(404);
        echo 'Category not found.';
        exit;
    }

    $category = $existing;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $isActive    = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') {
        $error = 'Category name is required.';
    } else {
        try {
            if ($isEdit) {
                $sql = '
                    UPDATE product_categories
                    SET name = :name,
                        description = :description,
                        is_active = :is_active,
                        updated_at = NOW()
                    WHERE id = :id
                ';
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':name'        => $name,
                    ':description' => $description !== '' ? $description : null,
                    ':is_active'   => $isActive,
                    ':id'          => $id,
                ]);
                $success = 'Category updated successfully.';
            } else {
                $sql = '
                    INSERT INTO product_categories
                        (name, description, is_active, created_at)
                    VALUES
                        (:name, :description, :is_active, NOW())
                ';
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':name'        => $name,
                    ':description' => $description !== '' ? $description : null,
                    ':is_active'   => $isActive,
                ]);
                $newId = (int)$pdo->lastInsertId();
                header('Location: /POSM3/public/product_category_edit.php?id=' . $newId);
                exit;
            }

            $category['name']        = $name;
            $category['description'] = $description;
            $category['is_active']   = $isActive;
        } catch (Exception $e) {
            $error = 'Error saving category: ' . $e->getMessage();
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
                    <?= $isEdit ? 'Edit Category' : 'New Category' ?>
                </h5>
                <p class="text-muted" style="font-size: 0.9rem;">
                    Group products by category for easier management and reporting.
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
                        <label for="name" class="form-label">Category name</label>
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            id="name"
                            name="name"
                            required
                            value="<?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea
                            class="form-control form-control-sm"
                            id="description"
                            name="description"
                            rows="2"
                        ><?= htmlspecialchars($category['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="is_active"
                            name="is_active"
                            value="1"
                            <?= (int)$category['is_active'] === 1 ? 'checked' : '' ?>
                        >
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="/POSM3/public/product_categories_list.php" class="btn btn-outline-light btn-sm">
                            Back to list
                        </a>
                        <button type="submit" class="btn btn-primary btn-sm">
                            Save category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>