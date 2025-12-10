<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/translate.php';

auth_require_login();

$pdo  = db();
$user = auth_user();

// Only allow ADMIN to change settings
if (!$user || strtoupper($user['role']) !== 'ADMIN') {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

$error   = null;
$success = null;

/**
 * Shop settings are stored in `settings` table (shop_name, etc.).
 * Language is stored in `app_settings` under key `app_language`.
 */

// Helpers for `settings` table (shop info)
function get_setting(PDO $pdo, string $key, string $default = ''): string {
    $stmt = $pdo->prepare('SELECT value FROM settings WHERE `key` = :k');
    $stmt->execute([':k' => $key]);
    $row = $stmt->fetch();
    return $row ? (string)$row['value'] : $default;
}

function set_setting(PDO $pdo, string $key, string $value): void {
    $stmt = $pdo->prepare('
        INSERT INTO settings (`key`, `value`, `updated_at`)
        VALUES (:k, :v, NOW())
        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()
    ');
    $stmt->execute([':k' => $key, ':v' => $value]);
}

// Helper for app_settings (language)
function get_app_setting(PDO $pdo, string $key, string $default = ''): string {
    $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = :k');
    $stmt->execute([':k' => $key]);
    $row = $stmt->fetch();
    return $row ? (string)$row['setting_value'] : $default;
}

function set_app_setting(PDO $pdo, string $key, string $value): void {
    $stmt = $pdo->prepare('
        INSERT INTO app_settings (setting_key, setting_value)
        VALUES (:k, :v)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ');
    $stmt->execute([':k' => $key, ':v' => $value]);
}

// Load current shop settings
$shopName       = get_setting($pdo, 'shop_name', 'My Shop');
$shopAddress    = get_setting($pdo, 'shop_address', '');
$shopPhone      = get_setting($pdo, 'shop_phone', '');
$currencySymbol = get_setting($pdo, 'currency_symbol', '$');

// Load current language
$currentLang = get_app_setting($pdo, 'app_language', 'en');
if (!in_array($currentLang, ['en', 'ar'], true)) {
    $currentLang = 'en';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shopName       = trim($_POST['shop_name'] ?? '');
    $shopAddress    = trim($_POST['shop_address'] ?? '');
    $shopPhone      = trim($_POST['shop_phone'] ?? '');
    $currencySymbol = trim($_POST['currency_symbol'] ?? '');
    $lang           = $_POST['app_language'] ?? $currentLang;

    if (!in_array($lang, ['en', 'ar'], true)) {
        $lang = 'en';
    }

    if ($shopName === '') {
        $error = __('settings.error_shop_name_required', 'Shop name is required.');
    } elseif ($currencySymbol === '') {
        $error = __('settings.error_currency_required', 'Currency symbol is required.');
    } else {
        try {
            // Save shop info
            set_setting($pdo, 'shop_name', $shopName);
            set_setting($pdo, 'shop_address', $shopAddress);
            set_setting($pdo, 'shop_phone', $shopPhone);
            set_setting($pdo, 'currency_symbol', $currencySymbol);

            // Save language
            set_app_setting($pdo, 'app_language', $lang);
            $currentLang = $lang;

            $success = __('settings.saved_ok', 'Settings saved successfully.');
        } catch (Exception $e) {
            $error = __('settings.error_saving', 'Error saving settings: ') . $e->getMessage();
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
                    <?= __('settings.general_title', 'General Settings') ?>
                </h5>
                <p class="text-muted" style="font-size: 0.9rem;">
                    <?= __('settings.general_desc', 'Configure basic shop information and defaults.') ?>
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
                        <label for="shop_name" class="form-label">
                            <?= __('settings.shop_name', 'Shop name') ?>
                        </label>
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            id="shop_name"
                            name="shop_name"
                            required
                            value="<?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="shop_address" class="form-label">
                            <?= __('settings.shop_address', 'Address') ?>
                        </label>
                        <textarea
                            class="form-control form-control-sm"
                            id="shop_address"
                            name="shop_address"
                            rows="2"
                        ><?= htmlspecialchars($shopAddress, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="shop_phone" class="form-label">
                            <?= __('settings.shop_phone', 'Phone') ?>
                        </label>
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            id="shop_phone"
                            name="shop_phone"
                            value="<?= htmlspecialchars($shopPhone, ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="currency_symbol" class="form-label">
                            <?= __('settings.currency_symbol', 'Currency symbol') ?>
                        </label>
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            id="currency_symbol"
                            name="currency_symbol"
                            required
                            value="<?= htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') ?>"
                        >
                        <div class="form-text text-muted" style="font-size: 0.8rem;">
                            <?= __('settings.currency_help', 'Example: $, €, £, ر.س , etc.') ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="app_language" class="form-label">
                            <?= __('settings.app_language', 'Interface language') ?>
                        </label>
                        <select id="app_language" name="app_language" class="form-select form-select-sm">
                            <option value="en" <?= $currentLang === 'en' ? 'selected' : '' ?>>
                                <?= __('settings.lang_en', 'English') ?>
                            </option>
                            <option value="ar" <?= $currentLang === 'ar' ? 'selected' : '' ?>>
                                <?= __('settings.lang_ar', 'العربية') ?>
                            </option>
                        </select>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="/POSM3/public/index.php" class="btn btn-outline-light btn-sm">
                            <?= __('settings.back_to_dashboard', 'Back to dashboard') ?>
                        </a>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <?= __('settings.save', 'Save settings') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>