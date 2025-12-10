-- POSM3 Database Schema
-- MySQL 8+ recommended

-- 1) Create database
CREATE DATABASE IF NOT EXISTS POSM3
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE POSM3;

-- =========================================================
-- 2) USERS
-- =========================================================
CREATE TABLE users (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username        VARCHAR(100) NOT NULL UNIQUE,
  password_hash   VARCHAR(255) NOT NULL,
  full_name       VARCHAR(150),
  role            ENUM('ADMIN','MANAGER','CASHIER') NOT NULL DEFAULT 'CASHIER',
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at   DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================================================
-- 3) MASTER DATA
-- =========================================================

-- 3.1 Units
CREATE TABLE units (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(50) NOT NULL,
  symbol     VARCHAR(20) NOT NULL,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_units_name (name),
  UNIQUE KEY uq_units_symbol (symbol)
) ENGINE=InnoDB;

-- 3.2 Product Categories
CREATE TABLE product_categories (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_product_categories_name (name)
) ENGINE=InnoDB;

-- 3.3 Products
CREATE TABLE products (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(150) NOT NULL,
  sku            VARCHAR(100) NULL,
  category_id    INT UNSIGNED NULL,
  base_unit_id   INT UNSIGNED NOT NULL,
  stock_qty      DECIMAL(18,4) NOT NULL DEFAULT 0,  -- in base units
  min_stock_qty  DECIMAL(18,4) NOT NULL DEFAULT 0,
  price_default  DECIMAL(18,4) NOT NULL DEFAULT 0,  -- per base unit
  cost_default   DECIMAL(18,4) NOT NULL DEFAULT 0,  -- per base unit
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_category
    FOREIGN KEY (category_id) REFERENCES product_categories(id),
  CONSTRAINT fk_products_base_unit
    FOREIGN KEY (base_unit_id) REFERENCES units(id),
  UNIQUE KEY uq_products_sku (sku)
) ENGINE=InnoDB;

-- 3.4 Product Units
CREATE TABLE product_units (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id         INT UNSIGNED NOT NULL,
  unit_id            INT UNSIGNED NOT NULL,
  conversion_to_base DECIMAL(18,6) NOT NULL, -- 1 unit = X base units
  sell_price         DECIMAL(18,4) NULL,     -- price for this unit (optional)
  is_default_for_sales TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_product_units_product
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_product_units_unit
    FOREIGN KEY (unit_id) REFERENCES units(id),
  UNIQUE KEY uq_product_units (product_id, unit_id)
) ENGINE=InnoDB;

-- 3.5 Customers
CREATE TABLE customers (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(150) NOT NULL,
  phone      VARCHAR(50) NULL,
  email      VARCHAR(150) NULL,
  address    VARCHAR(255) NULL,
  notes      VARCHAR(255) NULL,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3.6 Suppliers
CREATE TABLE suppliers (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(150) NOT NULL,
  phone      VARCHAR(50) NULL,
  email      VARCHAR(150) NULL,
  address    VARCHAR(255) NULL,
  notes      VARCHAR(255) NULL,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================================================
-- 4) SALES
-- =========================================================

CREATE TABLE sales (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_date     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  customer_id   INT UNSIGNED NULL,
  total_amount  DECIMAL(18,4) NOT NULL DEFAULT 0,
  paid_amount   DECIMAL(18,4) NOT NULL DEFAULT 0,
  credit_amount DECIMAL(18,4) NOT NULL DEFAULT 0,
  payment_type  ENUM('CASH','CREDIT','MIXED') NOT NULL DEFAULT 'CASH',
  status        ENUM('DRAFT','COMPLETED','CANCELLED') NOT NULL DEFAULT 'COMPLETED',
  notes         VARCHAR(255) NULL,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_sales_customer
    FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_sales_user
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE sale_items (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_id     INT UNSIGNED NOT NULL,
  product_id  INT UNSIGNED NOT NULL,
  unit_id     INT UNSIGNED NOT NULL,
  qty_unit    DECIMAL(18,4) NOT NULL,
  qty_base    DECIMAL(18,4) NOT NULL,
  unit_price  DECIMAL(18,4) NOT NULL,
  discount    DECIMAL(18,4) NOT NULL DEFAULT 0,
  subtotal    DECIMAL(18,4) NOT NULL,
  CONSTRAINT fk_sale_items_sale
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
  CONSTRAINT fk_sale_items_product
    FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_sale_items_unit
    FOREIGN KEY (unit_id) REFERENCES units(id)
) ENGINE=InnoDB;

-- =========================================================
-- 5) PURCHASES
-- =========================================================

CREATE TABLE purchases (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  purchase_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  supplier_id   INT UNSIGNED NOT NULL,
  total_amount  DECIMAL(18,4) NOT NULL DEFAULT 0,
  paid_amount   DECIMAL(18,4) NOT NULL DEFAULT 0,
  credit_amount DECIMAL(18,4) NOT NULL DEFAULT 0,
  payment_type  ENUM('CASH','CREDIT','MIXED') NOT NULL DEFAULT 'CASH',
  status        ENUM('PENDING','RECEIVED','CANCELLED') NOT NULL DEFAULT 'RECEIVED',
  notes         VARCHAR(255) NULL,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_purchases_supplier
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  CONSTRAINT fk_purchases_user
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE purchase_items (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  purchase_id    INT UNSIGNED NOT NULL,
  product_id     INT UNSIGNED NOT NULL,
  unit_id        INT UNSIGNED NOT NULL,
  qty_unit       DECIMAL(18,4) NOT NULL,
  qty_base       DECIMAL(18,4) NOT NULL,
  unit_cost      DECIMAL(18,4) NOT NULL,
  subtotal_cost  DECIMAL(18,4) NOT NULL,
  CONSTRAINT fk_purchase_items_purchase
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
  CONSTRAINT fk_purchase_items_product
    FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_purchase_items_unit
    FOREIGN KEY (unit_id) REFERENCES units(id)
) ENGINE=InnoDB;

-- =========================================================
-- 6) CUSTOMER ACCOUNTS (LEDGER + PAYMENTS)
-- =========================================================

CREATE TABLE customer_payments (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id  INT UNSIGNED NOT NULL,
  payment_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  amount       DECIMAL(18,4) NOT NULL,
  method       ENUM('CASH','CARD','BANK_TRANSFER','MOBILE','OTHER') NOT NULL DEFAULT 'CASH',
  reference    VARCHAR(100) NULL,
  notes        VARCHAR(255) NULL,
  created_by   INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_customer_payments_customer
    FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_customer_payments_user
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE customer_transactions (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id        INT UNSIGNED NOT NULL,
  transaction_date   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  type               ENUM('SALE','PAYMENT','LOAN_OUT','LOAN_REPAYMENT','ADJUSTMENT') NOT NULL,
  amount             DECIMAL(18,4) NOT NULL,
  related_sale_id    INT UNSIGNED NULL,
  related_payment_id INT UNSIGNED NULL,
  notes              VARCHAR(255) NULL,
  created_by         INT UNSIGNED NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_customer_transactions_customer
    FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_customer_transactions_sale
    FOREIGN KEY (related_sale_id) REFERENCES sales(id),
  CONSTRAINT fk_customer_transactions_payment
    FOREIGN KEY (related_payment_id) REFERENCES customer_payments(id),
  CONSTRAINT fk_customer_transactions_user
    FOREIGN KEY (created_by) REFERENCES users(id),
  INDEX idx_customer_transactions_customer (customer_id)
) ENGINE=InnoDB;

-- =========================================================
-- 7) SUPPLIER ACCOUNTS (LEDGER + PAYMENTS)
-- =========================================================

CREATE TABLE supplier_payments (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  supplier_id  INT UNSIGNED NOT NULL,
  payment_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  amount       DECIMAL(18,4) NOT NULL,
  method       ENUM('CASH','CARD','BANK_TRANSFER','MOBILE','OTHER') NOT NULL DEFAULT 'CASH',
  reference    VARCHAR(100) NULL,
  notes        VARCHAR(255) NULL,
  created_by   INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_supplier_payments_supplier
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  CONSTRAINT fk_supplier_payments_user
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE supplier_transactions (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  supplier_id         INT UNSIGNED NOT NULL,
  transaction_date    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  type                ENUM('PURCHASE','PAYMENT','LOAN_IN','LOAN_REPAYMENT','ADJUSTMENT') NOT NULL,
  amount              DECIMAL(18,4) NOT NULL,
  related_purchase_id INT UNSIGNED NULL,
  related_payment_id  INT UNSIGNED NULL,
  notes               VARCHAR(255) NULL,
  created_by          INT UNSIGNED NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_supplier_transactions_supplier
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  CONSTRAINT fk_supplier_transactions_purchase
    FOREIGN KEY (related_purchase_id) REFERENCES purchases(id),
  CONSTRAINT fk_supplier_transactions_payment
    FOREIGN KEY (related_payment_id) REFERENCES supplier_payments(id),
  CONSTRAINT fk_supplier_transactions_user
    FOREIGN KEY (created_by) REFERENCES users(id),
  INDEX idx_supplier_transactions_supplier (supplier_id)
) ENGINE=InnoDB;

-- =========================================================
-- 8) STOCK ADJUSTMENTS
-- =========================================================

CREATE TABLE stock_adjustments (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id      INT UNSIGNED NOT NULL,
  adjustment_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  qty_base_change DECIMAL(18,4) NOT NULL,
  reason          VARCHAR(100) NOT NULL,
  notes           VARCHAR(255) NULL,
  created_by      INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_stock_adjustments_product
    FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_stock_adjustments_user
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- =========================================================
-- 9) BALANCE VIEWS
-- =========================================================

-- Customer balances: positive => customer owes you
CREATE OR REPLACE VIEW customer_balances AS
SELECT
  c.id AS customer_id,
  c.name AS customer_name,
  IFNULL(SUM(ct.amount), 0) AS balance
FROM customers c
LEFT JOIN customer_transactions ct ON ct.customer_id = c.id
GROUP BY c.id, c.name;

-- Supplier balances: positive => you owe supplier
CREATE OR REPLACE VIEW supplier_balances AS
SELECT
  s.id AS supplier_id,
  s.name AS supplier_name,
  IFNULL(SUM(st.amount), 0) AS balance
FROM suppliers s
LEFT JOIN supplier_transactions st ON st.supplier_id = s.id
GROUP BY s.id, s.name;

-- =========================================================
-- 10) SAMPLE DATA (OPTIONAL)
-- =========================================================

-- Sample units
INSERT INTO units (name, symbol) VALUES
  ('Piece', 'pc'),
  ('Box', 'box'),
  ('Gram', 'g'),
  ('Kilogram', 'kg');

-- Sample categories
INSERT INTO product_categories (name) VALUES
  ('General'),
  ('Beverages'),
  ('Snacks');

-- Sample admin user (password hash is just placeholder; change to a real hash)
INSERT INTO users (username, password_hash, full_name, role)
VALUES ('admin', '$2y$10$changethishash', 'Administrator', 'ADMIN');

CREATE TABLE IF NOT EXISTS settings (
    `key`        VARCHAR(100) NOT NULL PRIMARY KEY,
    `value`      TEXT NULL,
    `updated_at` DATETIME NULL
);

-- Some default values
INSERT IGNORE INTO settings (`key`, `value`, `updated_at`) VALUES
('shop_name', 'My Shop', NOW()),
('shop_address', 'Main Street', NOW()),
('shop_phone', '000-000-0000', NOW()),
('currency_symbol', '$', NOW());

ALTER TABLE products
    ADD COLUMN image_path VARCHAR(255) NULL AFTER price_default;

    CREATE TABLE IF NOT EXISTS stock_movements (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id      INT UNSIGNED NOT NULL,
  movement_date   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  source_type     ENUM('PURCHASE','SALE','ADJUSTMENT') NOT NULL,
  source_id       INT UNSIGNED NULL,       -- purchase_id, sale_id, or adjustment id if you add a table later
  qty_change      DECIMAL(18,4) NOT NULL,  -- + for in, - for out, in base units
  note            VARCHAR(255) NULL,
  created_by      INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_stock_movements_product
    FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_stock_movements_user
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS app_settings (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key  VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT NOT NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO app_settings (setting_key, setting_value)
VALUES ('pos_default_printer', '');