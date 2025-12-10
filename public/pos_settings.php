<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

auth_require_login();
// Optionally restrict:
// if (!auth_user_is_admin()) { http_response_code(403); exit('Forbidden'); }

$pdo = db();
$error = null;
$success = null;

// Load existing value
$stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'pos_default_printer'");
$stmt->execute();
$row = $stmt->fetch();
$currentPrinter = $row ? $row['setting_value'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $printer = trim($_POST['pos_default_printer'] ?? '');

    try {
        $up = $pdo->prepare("
            INSERT INTO app_settings (setting_key, setting_value)
            VALUES ('pos_default_printer', :val)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $up->execute([':val' => $printer]);
        $currentPrinter = $printer;
        $success = 'POS default printer updated.';
    } catch (Exception $e) {
        $error = 'Error saving settings: ' . $e->getMessage();
    }
}

require_once __DIR__ . '/../templates/header.php';
?>

<!-- QZ Tray library (from CDN) -->
<script src="https://cdn.jsdelivr.net/npm/qz-tray@2.2.3/qz-tray.js"></script>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card bg-white border-0 shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3">POS Settings</h5>

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
                        <label for="pos_default_printer" class="form-label">
                            Default POS printer
                        </label>

                        <select
                            class="form-select form-select-sm"
                            id="pos_default_printer"
                            name="pos_default_printer"
                        >
                            <!-- will be filled by JS -->
                            <option value="">Loading printers...</option>
                        </select>

                        <div class="form-text" style="font-size:0.8rem;">
                            This list comes from QZ Tray on this computer. Make sure
                            QZ Tray is installed and running.  
                            If the list does not load, start QZ Tray and refresh the page.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm">
                        Save settings
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Current printer from server-side setting
const CURRENT_PRINTER = <?= json_encode($currentPrinter) ?>;

async function loadPrinters() {
    const select = document.getElementById('pos_default_printer');
    if (!select) return;

    // Helper to set options
    function setOptions(printers) {
        select.innerHTML = '';
        // Empty / none
        const optNone = document.createElement('option');
        optNone.value = '';
        optNone.textContent = '-- No default / ask --';
        select.appendChild(optNone);

        printers.forEach(name => {
            const opt = document.createElement('option');
            opt.value = name;
            opt.textContent = name;
            if (CURRENT_PRINTER && name === CURRENT_PRINTER) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });

        // If current printer is not in list but set, keep it
        if (CURRENT_PRINTER && !printers.includes(CURRENT_PRINTER)) {
            const opt = document.createElement('option');
            opt.value = CURRENT_PRINTER;
            opt.textContent = CURRENT_PRINTER + ' (saved, not found)';
            opt.selected = true;
            select.appendChild(opt);
        }
    }

    try {
        // connect to QZ Tray if not connected
        if (!qz.websocket.isActive()) {
            await qz.websocket.connect();
        }

        const printers = await qz.printers.find(); // get list of printer names
        setOptions(printers);
    } catch (e) {
        console.error('Failed to load printers from QZ', e);
        // Fallback: show saved value only
        select.innerHTML = '';
        const opt = document.createElement('option');
        opt.value = CURRENT_PRINTER || '';
        opt.textContent = CURRENT_PRINTER
            ? CURRENT_PRINTER + ' (saved, QZ not available)'
            : 'No printers (QZ Tray not available)';
        opt.selected = true;
        select.appendChild(opt);
    }
}

document.addEventListener('DOMContentLoaded', loadPrinters);
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>