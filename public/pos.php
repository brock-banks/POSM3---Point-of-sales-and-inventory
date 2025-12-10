<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

auth_require_login();
$pdo   = db();
$user  = auth_user();
// Load default POS printer
$printerStmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'pos_default_printer'");
$printerStmt->execute();
$printerRow = $printerStmt->fetch();
$posDefaultPrinter = $printerRow ? trim($printerRow['setting_value']) : '';

// Load products including base_unit_id and image_path
$stmt = $pdo->query("
    SELECT id, name, sku, price_default, image_path, base_unit_id, category_id
    FROM products
    WHERE is_active = 1
    ORDER BY name ASC
");
$products = $stmt->fetchAll();

// Load POS categories (active only)
$catStmt = $pdo->query("SELECT id, name FROM product_categories WHERE is_active = 1 ORDER BY name");
$posCategories = $catStmt->fetchAll();

// Load customers for payment modal
$cStmt = $pdo->query("SELECT id, name FROM customers WHERE is_active = 1 ORDER BY name ASC");
$customers = $cStmt->fetchAll();

require_once __DIR__ . '/../templates/header.php';
?>

<style>
.pos-wrapper {
    position: relative;
    margin: -1.5rem -1.5rem 0 -1.5rem;
    height: calc(100vh - 56px);
    display: flex;
    overflow: hidden;
}
.pos-left {
    width: 40%;
    max-width: 520px;
    min-width: 380px;
    background: #f7f7f7;
    display: flex;
    flex-direction: column;
    border-right: 1px solid #ddd;
}
.pos-right {
    flex: 1;
    background: #f0f0f0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.pos-cart-header {
    padding: 0.5rem 1rem;
    border-bottom: 1px solid #ddd;
    background: #ffffff;
    font-weight: 500;
}
.pos-cart-lines {
    flex: 1;
    overflow-y: auto;
    background: #ffffff;
}
.pos-cart-line {
    display: flex;
    padding: 0.35rem 0.4rem 0.35rem 0.9rem;
    border-bottom: 1px solid #eee;
    cursor: pointer;
    font-size: 0.85rem;
}
.pos-cart-line.active {
    background: #e7f1ff;
}
.pos-cart-line-main {
    flex: 1;
}
.pos-cart-line-title {
    font-size: 0.9rem;
    font-weight: 500;
}
.pos-cart-line-sub {
    font-size: 0.75rem;
    color: #666;
}
.pos-cart-line-total {
    font-size: 0.9rem;
    font-weight: 600;
    min-width: 80px;
    text-align: right;
}
.pos-cart-line-delete {
    width: 26px;
    text-align: right;
    padding-left: 0.4rem;
}
.pos-cart-line-delete button {
    border: none;
    background: transparent;
    color: #dc3545;
    font-size: 0.8rem;
}
.pos-cart-totals {
    padding: 0.5rem 1rem;
    border-top: 1px solid #ddd;
    background: #ffffff;
    font-size: 0.9rem;
}
.pos-cart-totals .total-amount {
    font-size: 1.2rem;
    font-weight: 700;
}
.pos-actions {
    display: flex;
    padding: 0.4rem 0.4rem;
    border-top: 1px solid #ddd;
    background: #ffffff;
    gap: 0.35rem;
    font-size: 0.8rem;
}
.pos-actions button {
    font-size: 0.75rem;
}
.pos-keypad button.field-active {
    background: #d0e4ff;
    font-weight: 700;
}
.pos-bottom {
    display: flex;
    min-height: 220px;
    max-height: 260px;
    border-top: 1px solid #ddd;
}
.pos-bottom-left {
    width: 80px;
    background: #5f2747;
    color: #fff;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 0.25rem;
}
.pos-bottom-left .btn-payment {
    writing-mode: vertical-rl;
    transform: rotate(180deg);
    border: none;
    background: transparent;
    color: #fff;
    font-weight: 500;
    font-size: 1rem;
}
.pos-bottom-left small {
    font-size: 0.7rem;
    opacity: 0.8;
}
.pos-keypad {
    flex: 1;
    background: #f7f7f7;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    grid-auto-rows: minmax(50px, auto);
    border-left: 1px solid #ddd;
}
.pos-keypad button {
    border: 1px solid #ddd;
    background: #ffffff;
    font-size: 0.9rem;
}
.pos-keypad button.action {
    background: #f0f0f0;
    font-weight: 500;
}
.pos-keypad button:active {
    background: #e0e0e0;
}
.pos-topbar {
    padding: 0.5rem 1rem;
    display: flex;
    align-items: center;
    border-bottom: 1px solid #ddd;
    background: #ffffff;
    gap: 0.75rem;
}
.pos-topbar .path {
    font-size: 0.85rem;
    color: #666;
    flex: 1;
}
.pos-products-scroll {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
}
.pos-products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 0.75rem;
}
.pos-product-card {
    background: #ffffff;
    border-radius: 3px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    cursor: pointer;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transition: box-shadow 0.15s ease, transform 0.1s ease;
}
.pos-product-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    transform: translateY(-1px);
}
.pos-product-card img {
    width: 100%;
    height: 120px;
    object-fit: cover;
    background: #f5f5f5;
}
.pos-product-body {
    padding: 0.5rem 0.6rem 0.6rem;
    font-size: 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}
.pos-product-name {
    font-weight: 500;
    line-height: 1.2;
}
.pos-product-price {
    color: #e85c41;
    font-weight: 600;
}
.pos-product-sku {
    font-size: 0.75rem;
    color: #999;
}
@media (max-width: 992px) {
    .pos-wrapper {
        flex-direction: column;
        height: auto;
    }
    .pos-left {
        width: 100%;
        max-width: none;
        min-width: 0;
        height: 50vh;
    }
    .pos-right {
        width: 100%;
        height: 50vh;
    }
}
</style>

<div class="pos-wrapper">
    <!-- LEFT: CART -->
    <div class="pos-left">
        <div class="pos-cart-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <span>Order</span>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnNewBill">
                    New
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" id="btnDeleteBill">
                    Delete
                </button>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnHold">
                    Hold
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnShowHolds">
                    Held bills <span class="badge bg-secondary ms-1" id="heldCount">0</span>
                </button>
            </div>
        </div>

        <div id="cartLines" class="pos-cart-lines">
            <div class="text-center text-muted mt-4" id="cartEmptyMsg" style="font-size:0.9rem;">
                No items yet. Click a product on the right to add it.
            </div>
        </div>

        <div class="pos-cart-totals">
            <div class="d-flex justify-content-between">
                <span>Total:</span>
                <span class="total-amount" id="cartTotal">$ 0.00</span>
            </div>
            <div class="d-flex justify-content-between text-muted" style="font-size:0.8rem;">
                <span>Taxes:</span>
                <span id="cartTaxes">$ 0.00</span>
            </div>
        </div>

        <div class="pos-actions">
            <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" id="btnRefund">
                Refund
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" id="btnCustomerNote">
                Customer Note
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" id="btnInternalNote">
                Internal Note
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" id="btnQuotation">
                Quotation/Order
            </button>
        </div>

        <div class="pos-bottom">
            <div class="pos-bottom-left">
                <button class="btn-payment" type="button" id="btnPayment">
                    Payment
                </button>
                <small>Customer</small>
            </div>

            <div class="pos-keypad">
                <button type="button" class="action" data-key="customer">Customer</button>
                <button type="button" class="key" data-num="1">1</button>
                <button type="button" class="key" data-num="2">2</button>
                <button type="button" class="key" data-num="3">3</button>

                <button type="button" class="action" data-field="qty">Qty</button>
                <button type="button" class="key" data-num="4">4</button>
                <button type="button" class="key" data-num="5">5</button>
                <button type="button" class="key" data-num="6">6</button>

                <button type="button" class="action" data-field="discount">Disc</button>
                <button type="button" class="key" data-num="7">7</button>
                <button type="button" class="key" data-num="8">8</button>
                <button type="button" class="key" data-num="9">9</button>

                <button type="button" class="action" data-field="price">Price</button>
                <button type="button" class="action" data-sign="+/-">+/-</button>
                <button type="button" class="key" data-num="0">0</button>
                <button type="button" class="key" data-num=".">.</button>

                <!-- - and + buttons for qty -->
                <button type="button" class="action" data-qty-dec="1">-</button>
                <button type="button" class="action" data-qty-inc="1">+</button>
            </div>
        </div>
    </div>

    <!-- RIGHT: PRODUCTS -->
    <div class="pos-right">
        <div class="pos-topbar">
            <div class="path d-flex align-items-center gap-2">
                <span>Home</span>
                <span>&gt;</span>
                <select id="posCategoryFilter" class="form-select form-select-sm" style="width:auto; max-width:220px;">
                    <option value="0">All products</option>
                    <?php foreach ($posCategories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>">
                            <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-shrink-0" style="width: 260px;">
                <input type="text"
                       class="form-control form-control-sm"
                       id="productSearch"
                       placeholder="Search products...">
            </div>
        </div>

        <div class="pos-products-scroll">
            <div class="pos-products-grid" id="productGrid">
                <?php foreach ($products as $p): ?>
                    <div class="pos-product-card"
                         data-id="<?= (int)$p['id'] ?>"
                         data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>"
                         data-price="<?= htmlspecialchars($p['price_default'], ENT_QUOTES, 'UTF-8') ?>"
                         data-sku="<?= htmlspecialchars($p['sku'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                         data-unit-id="<?= (int)$p['base_unit_id'] ?>"
                         data-category-id="<?= (int)($p['category_id'] ?? 0) ?>">
                        <?php if (!empty($p['image_path'])): ?>
                            <img src="<?= htmlspecialchars($p['image_path'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                        <?php else: ?>
                            <img src="https://via.placeholder.com/300x200?text=No+Image" alt="">
                        <?php endif; ?>
                        <div class="pos-product-body">
                            <div class="pos-product-name">
                                <?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <div class="pos-product-price">
                                $ <?= number_format((float)$p['price_default'], 2) ?>
                            </div>
                            <?php if (!empty($p['sku'])): ?>
                                <div class="pos-product-sku">
                                    SKU: <?= htmlspecialchars($p['sku'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title">Payment</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="paymentForm">
            <div class="mb-2">
                <label class="form-label form-label-sm">Payment type</label>
                <select class="form-select form-select-sm" id="paymentType" name="payment_type">
                    <option value="CASH">Cash</option>
                    <option value="CREDIT">Customer Credit</option>
                </select>
            </div>

            <div class="mb-2">
                <label class="form-label form-label-sm">Customer (for credit)</label>
                <select class="form-select form-select-sm" id="paymentCustomer" name="customer_id">
                    <option value="">-- Walk-in / none --</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= (int)$c['id'] ?>">
                            <?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-2">
                <label class="form-label form-label-sm">Paid amount</label>
                <input type="number" step="0.01" min="0"
                       class="form-control form-control-sm"
                       id="paymentPaid"
                       name="paid_amount"
                       value="">
                <div class="form-text text-muted" style="font-size:0.8rem;">
                    Leave 0 for full cash payment. For credit, set 0 to defer full amount.
                </div>
            </div>

            <div class="mb-2">
                <label class="form-label form-label-sm">Note (optional)</label>
                <textarea class="form-control form-control-sm" id="paymentNote" rows="2"></textarea>
            </div>

            <div class="mb-1">
                <small class="text-muted">
                    Total: <span id="paymentTotalLabel">$ 0.00</span>
                </small>
            </div>
        </form>
        <div class="alert alert-danger py-1 px-2 mt-2 d-none" id="paymentError" style="font-size:0.8rem;"></div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-sm btn-outline-light" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-sm btn-primary" id="btnConfirmPayment">Confirm</button>
      </div>
    </div>
  </div>
</div>

<!-- Held bills modal -->
<div class="modal fade" id="heldModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title">Held bills</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="heldListBody" style="font-size:0.85rem;">
        <!-- injected by JS -->
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/qz-tray@2.2.3/qz-tray.js"></script>
<script>
const POS_DEFAULT_PRINTER = <?= json_encode($posDefaultPrinter) ?>;
const TAX_RATE = 0.15; // must match backend

// ---------------- QZ ESC/POS PRINT -----------------

async function printReceiptEscPos(receiptDataUrl, htmlFallbackUrl) {
    if (!receiptDataUrl) {
        if (htmlFallbackUrl) window.open(htmlFallbackUrl, '_blank');
        return;
    }

    // If QZ or printer not configured, fallback to HTML
    if (typeof qz === 'undefined' || !POS_DEFAULT_PRINTER) {
        if (htmlFallbackUrl) window.open(htmlFallbackUrl, '_blank');
        return;
    }

    try {
        const resp = await fetch(receiptDataUrl, { credentials: 'include' });
        const data = await resp.json();
        if (!data || !data.success) {
            throw new Error(data && data.error ? data.error : 'Invalid receipt data');
        }

        const sale   = data.sale;
        const shop   = data.shop;
        const items  = data.items || [];
        const totals = data.totals || {};
        const taxRatePercent = Math.round((totals.tax_rate || 0) * 100);

        if (!qz.websocket.isActive()) {
            await qz.websocket.connect();
        }

        const cfg = qz.configs.create(POS_DEFAULT_PRINTER, {
            encoding: 'UTF-8'
        });

        const esc  = '\x1B';
        const gs   = '\x1D';
        const lf   = '\x0A';

        function center()  { return esc + 'a' + '\x01'; }
        function left()    { return esc + 'a' + '\x00'; }
        function boldOn()  { return esc + 'E' + '\x01'; }
        function boldOff() { return esc + 'E' + '\x00'; }
        function cut()     { return gs + 'V' + '\x00'; }

        const lineWidth = 42;

        function repeat(ch, count) {
            return new Array(count + 1).join(ch);
        }
        function padRight(str, width) {
            str = String(str);
            if (str.length > width) return str.substring(0, width);
            return str + repeat(' ', width - str.length);
        }
        function formatMoney(num) {
            return Number(num).toFixed(2);
        }

        const cmds = [];

        // Init
        cmds.push(esc + '@');

        // Header
        cmds.push(center());
        cmds.push(boldOn() + (shop.name || 'My Shop') + boldOff() + lf);
        if (shop.address) cmds.push(shop.address + lf);
        if (shop.phone)   cmds.push(shop.phone + lf);
        cmds.push(lf);

        // Sale info
        cmds.push(left());
        cmds.push('Date: ' + (sale.date || '') + lf);
        cmds.push('Receipt: #' + sale.id + lf);
        cmds.push('Customer: ' + (sale.customer_name || 'Walk-in') + lf);
        cmds.push(repeat('-', lineWidth) + lf);

        // Items
        items.forEach(it => {
            const qty   = it.qty_unit || it.qty_base || 0;
            const price = it.unit_price || 0;
            const disc  = it.discount || 0;
            const gross = qty * price;
            let net     = gross - disc;
            if (net < 0) net = 0;

            const qtyStr   = formatMoney(qty);
            const qtyPart  = qtyStr + 'x ';
            const name     = it.product_name || '';
            const amountStr= formatMoney(net);
            const leftWidth= lineWidth - (amountStr.length + 1);
            const leftText = padRight(qtyPart + name, leftWidth);
            cmds.push(leftText + ' ' + amountStr + lf);

            let second = '  @' + formatMoney(price);
            if (disc > 0) second += '  Disc ' + formatMoney(disc);
            cmds.push(second + lf);
        });

        cmds.push(repeat('-', lineWidth) + lf);

        // Totals
        const sub       = totals.sub_total || 0;
        const tax       = totals.tax || 0;
        const discTotal = totals.discount || 0;
        const total     = totals.total || sale.total_amount || 0;

        function totalLine(label, value, bold) {
            const lbl  = label + ':';
            const val  = formatMoney(value);
            const leftWidth = lineWidth - (val.length + 1);
            const leftText  = padRight(lbl, leftWidth);
            const line      = leftText + ' ' + val + lf;
            return bold ? (boldOn() + line + boldOff()) : line;
        }

        if (discTotal > 0) cmds.push(totalLine('Discount', discTotal, false));
        cmds.push(totalLine('Subtotal', sub, false));
        cmds.push(totalLine('Tax ' + taxRatePercent + '%', tax, false));
        cmds.push(totalLine('TOTAL', total, true));
        cmds.push(totalLine('Paid', sale.paid_amount || 0, false));
        cmds.push(totalLine('Credit', sale.credit_amount || 0, false));

        cmds.push(repeat('-', lineWidth) + lf);

        if (sale.payment_type) cmds.push('Payment: ' + sale.payment_type + lf);
        if (sale.notes)        cmds.push('Notes: ' + sale.notes + lf);

        cmds.push(lf + center() + 'Thank you!' + lf + lf);
        cmds.push(cut());

        const dataArr = [{
            type: 'raw',
            format: 'plain',
            data: cmds.join('')
        }];

        await qz.print(cfg, dataArr);
    } catch (e) {
        console.error('ESC/POS print error', e);
        if (htmlFallbackUrl) window.open(htmlFallbackUrl, '_blank');
    }
}

// ---------------- EXISTING POS JS -----------------

const productCards = document.querySelectorAll('.pos-product-card');
const cartLinesEl  = document.getElementById('cartLines');
const cartEmptyMsg = document.getElementById('cartEmptyMsg');
const cartTotalEl  = document.getElementById('cartTotal');
const cartTaxesEl  = document.getElementById('cartTaxes');
const searchInput  = document.getElementById('productSearch');
const keypad       = document.querySelector('.pos-keypad');
const paymentBtn   = document.getElementById('btnPayment');
const categoryFilter = document.getElementById('posCategoryFilter');
const btnHold      = document.getElementById('btnHold');
const btnShowHolds = document.getElementById('btnShowHolds');
const heldCountEl  = document.getElementById('heldCount');
const heldListBody = document.getElementById('heldListBody');
const btnNewBill   = document.getElementById('btnNewBill');
const btnDeleteBill= document.getElementById('btnDeleteBill');

let cart = [];           // {id, name, price, qty, discount, unitId}
let activeIndex = -1;
let currentField = 'qty';
let inputBuffer = '';
let heldBills = [];      // [{id, cart, total, createdAt}]

function formatMoney(v) {
    return '$ ' + v.toFixed(2);
}

// Filter products by text + category
function applyProductFilters() {
    const term = (searchInput.value || '').toLowerCase();
    const catId = categoryFilter ? parseInt(categoryFilter.value || '0', 10) : 0;

    productCards.forEach(card => {
        const name = card.dataset.name.toLowerCase();
        const sku  = (card.dataset.sku || '').toLowerCase();
        const cardCatId = parseInt(card.dataset.categoryId || '0', 10);

        const matchesText = name.includes(term) || sku.includes(term);
        const matchesCat  = catId === 0 || cardCatId === catId;

        card.style.display = (matchesText && matchesCat) ? '' : 'none';
    });
}

searchInput.addEventListener('input', applyProductFilters);
if (categoryFilter) {
    categoryFilter.addEventListener('change', applyProductFilters);
}

function recalcTotals() {
    let subTotal = 0;

    cart.forEach(line => {
        const gross = line.qty * line.price;
        const net   = gross - line.discount;
        subTotal   += (net < 0 ? 0 : net);
    });

    const tax   = subTotal * TAX_RATE;
    const total = subTotal + tax;

    cartTotalEl.textContent = formatMoney(total);
    cartTaxesEl.textContent = formatMoney(tax);

    return {subTotal, tax, total};
}

function refreshCart() {
    cartLinesEl.innerHTML = '';
    if (cart.length === 0) {
        cartLinesEl.appendChild(cartEmptyMsg);
        cartEmptyMsg.style.display = 'block';
        cartTotalEl.textContent = formatMoney(0);
        cartTaxesEl.textContent = formatMoney(0);
        activeIndex = -1;
        return;
    }
    cartEmptyMsg.style.display = 'none';

    cart.forEach((line, index) => {
        const div = document.createElement('div');
        div.className = 'pos-cart-line' + (index === activeIndex ? ' active' : '');
        div.dataset.index = index;

        const main = document.createElement('div');
        main.className = 'pos-cart-line-main';

        const title = document.createElement('div');
        title.className = 'pos-cart-line-title';
        title.textContent = line.name;
        main.appendChild(title);

        const sub = document.createElement('div');
        sub.className = 'pos-cart-line-sub';

        const gross = line.qty * line.price;
        const net   = gross - line.discount;
        const discTxt = line.discount
            ? ' (Disc ' + formatMoney(line.discount) + ')'
            : '';

        sub.textContent = line.qty.toFixed(2) + ' x ' + formatMoney(line.price) + discTxt;
        main.appendChild(sub);

        const totalDiv = document.createElement('div');
        totalDiv.className = 'pos-cart-line-total';
        totalDiv.textContent = formatMoney(net < 0 ? 0 : net);

        const delDiv = document.createElement('div');
        delDiv.className = 'pos-cart-line-delete';
        delDiv.innerHTML = '<button type="button" data-del-index="' + index + '">&times;</button>';

        div.appendChild(main);
        div.appendChild(totalDiv);
        div.appendChild(delDiv);

        div.addEventListener('click', (ev) => {
            if (ev.target && ev.target.closest('button[data-del-index]')) return;
            activeIndex = index;
            refreshCart();
        });

        cartLinesEl.appendChild(div);
    });

    cartLinesEl.querySelectorAll('button[data-del-index]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const idx = parseInt(btn.dataset.delIndex, 10);
            cart.splice(idx, 1);
            if (activeIndex >= cart.length) activeIndex = cart.length - 1;
            refreshCart();
        });
    });

    recalcTotals();
}

// Add product to cart
productCards.forEach(card => {
    card.addEventListener('click', () => {
        const id     = parseInt(card.dataset.id, 10);
        const name   = card.dataset.name;
        const price  = parseFloat(card.dataset.price || '0');
        const unitId = parseInt(card.dataset.unitId || '0', 10);

        const existingIndex = cart.findIndex(l => l.id === id);
        if (existingIndex >= 0) {
            cart[existingIndex].qty += 1;
            activeIndex = existingIndex;
        } else {
            cart.push({
                id,
                name,
                price,
                qty: 1,
                discount: 0,
                unitId
            });
            activeIndex = cart.length - 1;
        }
        refreshCart();
    });
});

// Keypad logic
function applyBuffer() {
    if (activeIndex < 0 || cart.length === 0) return;
    const value = parseFloat(inputBuffer || '0');
    const line = cart[activeIndex];

    if (currentField === 'qty') {
        line.qty = value <= 0 ? 1 : value;
    } else if (currentField === 'discount') {
        line.discount = value < 0 ? 0 : value;
    } else if (currentField === 'price') {
        line.price = value <= 0 ? line.price : value;
    }
    refreshCart();
}

keypad.addEventListener('click', (e) => {
    const btn = e.target.closest('button');
    if (!btn) return;

    if (btn.classList.contains('key')) {
        const num = btn.dataset.num;
        if (num === '.' && inputBuffer.includes('.')) return;
        inputBuffer += num;
        applyBuffer();
    } else if (btn.classList.contains('action')) {
        if (btn.dataset.field) {
            currentField = btn.dataset.field;
            inputBuffer = '';
            keypad.querySelectorAll('button.action').forEach(b => b.classList.remove('field-active'));
            btn.classList.add('field-active');
        } else if (btn.dataset.backspace) {
            inputBuffer = inputBuffer.slice(0, -1);
            applyBuffer();
        } else if (btn.dataset.sign === '+/-') {
            if (inputBuffer.startsWith('-')) {
                inputBuffer = inputBuffer.slice(1);
            } else {
                inputBuffer = '-' + inputBuffer;
            }
            applyBuffer();
        } else if (btn.dataset.qtyInc) {
            if (activeIndex < 0 || cart.length === 0) return;
            cart[activeIndex].qty += 1;
            refreshCart();
        } else if (btn.dataset.qtyDec) {
            if (activeIndex < 0 || cart.length === 0) return;
            cart[activeIndex].qty -= 1;
            if (cart[activeIndex].qty === 0) cart[activeIndex].qty = 1;
            refreshCart();
        }
    }
});

// Refund button
document.getElementById('btnRefund').addEventListener('click', () => {
    if (activeIndex < 0 || cart.length === 0) {
        alert('Select a line to refund.');
        return;
    }
    const line = cart[activeIndex];
    line.qty = -line.qty;
    refreshCart();
});

// New bill
btnNewBill.addEventListener('click', () => {
    if (cart.length > 0 && !confirm('Clear current bill?')) return;
    cart = [];
    activeIndex = -1;
    refreshCart();
});

// Delete bill
btnDeleteBill.addEventListener('click', () => {
    if (cart.length === 0) {
        alert('No bill to delete.');
        return;
    }
    if (!confirm('Delete this bill (clear all items)?')) return;
    cart = [];
    activeIndex = -1;
    refreshCart();
});

// Hold bill
function updateHeldCount() {
    heldCountEl.textContent = heldBills.length.toString();
}

btnHold.addEventListener('click', () => {
    if (cart.length === 0) {
        alert('Cart is empty.');
        return;
    }
    const totals = recalcTotals();
    heldBills.push({
        id: Date.now(),
        cart: JSON.parse(JSON.stringify(cart)),
        total: totals.total,
        createdAt: new Date().toLocaleTimeString()
    });
    updateHeldCount();
    cart = [];
    activeIndex = -1;
    refreshCart();
});

let heldModal;
document.addEventListener('DOMContentLoaded', () => {
    heldModal = new bootstrap.Modal(document.getElementById('heldModal'));
});

btnShowHolds.addEventListener('click', () => {
    heldListBody.innerHTML = '';
    if (heldBills.length === 0) {
        heldListBody.innerHTML = '<p class="text-muted mb-0">No held bills.</p>';
    } else {
        heldBills.forEach((bill, index) => {
            const row = document.createElement('div');
            row.className = 'd-flex justify-content-between align-items-center mb-2';
            row.innerHTML = `
                <div>
                    <div><strong>Bill #${index + 1}</strong></div>
                    <div class="text-muted" style="font-size:0.75rem;">
                        ${bill.createdAt} &middot; Total ${formatMoney(bill.total)}
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-light" data-restore="${index}">Load</button>
            `;
            heldListBody.appendChild(row);
        });

        heldListBody.querySelectorAll('button[data-restore]').forEach(btn => {
            btn.addEventListener('click', () => {
                const idx = parseInt(btn.dataset.restore, 10);
                const bill = heldBills[idx];
                cart = JSON.parse(JSON.stringify(bill.cart));
                activeIndex = cart.length > 0 ? 0 : -1;
                heldBills.splice(idx, 1);
                updateHeldCount();
                refreshCart();
                heldModal.hide();
            });
        });
    }
    heldModal.show();
});

// Payment modal
let paymentModal;
document.addEventListener('DOMContentLoaded', () => {
    paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
});

paymentBtn.addEventListener('click', () => {
    if (cart.length === 0) {
        alert('Cart is empty.');
        return;
    }
    const totals = recalcTotals();
    document.getElementById('paymentTotalLabel').textContent = formatMoney(totals.total);
    document.getElementById('paymentPaid').value = totals.total.toFixed(2);
    document.getElementById('paymentType').value = 'CASH';
    document.getElementById('paymentCustomer').value = '';
    document.getElementById('paymentNote').value = '';
    document.getElementById('paymentError').classList.add('d-none');
    paymentModal.show();
});

document.getElementById('btnConfirmPayment').addEventListener('click', () => {
    if (cart.length === 0) return;

    const totals = recalcTotals();
    const type   = document.getElementById('paymentType').value;
    const custId = document.getElementById('paymentCustomer').value;
    const paid   = parseFloat(document.getElementById('paymentPaid').value || '0');
    const note   = document.getElementById('paymentNote').value || '';
    const errBox = document.getElementById('paymentError');

    if (type === 'CREDIT' && !custId) {
        errBox.textContent = 'Please select a customer for credit sale.';
        errBox.classList.remove('d-none');
        return;
    }

    const itemsPayload = cart.map(line => {
        const qtyBase = line.qty;
        const subtotal = (qtyBase * line.price) - line.discount;
        return {
            product_id: line.id,
            unit_id: line.unitId || 0,
            qty_unit: qtyBase,
            qty_base: qtyBase,
            unit_price: line.price,
            discount: line.discount,
            subtotal: subtotal < 0 ? 0 : subtotal
        };
    });

    fetch('pos_save_sale.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            items: itemsPayload,
            customer_id: custId || null,
            payment_type: type,
            paid_amount: paid,
            notes: note
        })
    })
    .then(r => r.json())
    .then(async (res) => {
        if (!res || res.error) {
            throw new Error(res && res.error ? res.error : 'Unknown error');
        }
        paymentModal.hide();

        await printReceiptEscPos(res.receipt_data_url, res.receipt_html_url);

        alert('Sale saved. ID: ' + res.sale_id);
        cart = [];
        activeIndex = -1;
        refreshCart();
    })
    .catch(err => {
        errBox.textContent = 'Error: ' + err.message;
        errBox.classList.remove('d-none');
    });
});

refreshCart();
applyProductFilters();
updateHeldCount();
</script>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>