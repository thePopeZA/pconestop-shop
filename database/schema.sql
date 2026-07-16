-- ============================================================
--  PC One Stop Shop — Database schema
--  Engine: MySQL / MariaDB, utf8mb4
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------- Categories ----------
CREATE TABLE IF NOT EXISTS categories (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(150) NOT NULL,
    slug        VARCHAR(180) NOT NULL,
    parent_id   INT UNSIGNED DEFAULT NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    product_count INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cat_slug (slug),
    KEY idx_cat_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Products ----------
CREATE TABLE IF NOT EXISTS products (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sku           VARCHAR(100) NOT NULL,
    name          VARCHAR(300) NOT NULL,
    slug          VARCHAR(320) NOT NULL,
    brand         VARCHAR(150) DEFAULT NULL,
    category_id   INT UNSIGNED DEFAULT NULL,
    category_path VARCHAR(300) DEFAULT NULL,
    description   MEDIUMTEXT,
    short_desc    VARCHAR(500) DEFAULT NULL,
    -- Pricing
    cost_price    DECIMAL(12,2) NOT NULL DEFAULT 0.00,  -- feed price (dealer cost)
    price         DECIMAL(12,2) NOT NULL DEFAULT 0.00,  -- sell price = cost * markup * vat
    rrp           DECIMAL(12,2) DEFAULT NULL,           -- supplier RRP incl VAT (for "you save")
    -- Stock
    stock_qty     INT NOT NULL DEFAULT 0,
    stock_status  ENUM('in_stock','low_stock','out_of_stock','backorder') NOT NULL DEFAULT 'out_of_stock',
    warehouse     VARCHAR(150) DEFAULT NULL,
    supplier_eta  VARCHAR(100) DEFAULT NULL,
    -- Media
    image_url     VARCHAR(600) DEFAULT NULL,
    image_gallery TEXT,                                  -- JSON array of extra image URLs
    product_url   VARCHAR(600) DEFAULT NULL,             -- supplier product page
    -- Meta
    barcode       VARCHAR(100) DEFAULT NULL,
    weight_kg     DECIMAL(10,3) DEFAULT NULL,
    active        TINYINT(1) NOT NULL DEFAULT 1,
    featured      TINYINT(1) NOT NULL DEFAULT 0,
    source        VARCHAR(50) NOT NULL DEFAULT 'syntech',
    last_feed_seen DATETIME DEFAULT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_prod_sku (sku),
    KEY idx_prod_slug (slug),
    KEY idx_prod_cat (category_id),
    KEY idx_prod_active (active),
    KEY idx_prod_brand (brand),
    KEY idx_prod_stock (stock_status),
    FULLTEXT KEY ft_prod_search (name, brand, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Orders ----------
CREATE TABLE IF NOT EXISTS orders (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_number   VARCHAR(30) NOT NULL,
    -- Customer
    customer_name  VARCHAR(200) NOT NULL,
    email          VARCHAR(200) NOT NULL,
    phone          VARCHAR(50) DEFAULT NULL,
    -- Shipping address
    address_line1  VARCHAR(255) DEFAULT NULL,
    address_line2  VARCHAR(255) DEFAULT NULL,
    city           VARCHAR(120) DEFAULT NULL,
    province       VARCHAR(120) DEFAULT NULL,
    postal_code    VARCHAR(20) DEFAULT NULL,
    -- Totals
    subtotal       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    shipping       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    vat_amount     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    -- Payment
    payment_status ENUM('pending','paid','failed','cancelled','refunded') NOT NULL DEFAULT 'pending',
    payment_method VARCHAR(50) DEFAULT 'yoco',
    yoco_checkout_id VARCHAR(120) DEFAULT NULL,
    yoco_payment_id  VARCHAR(120) DEFAULT NULL,
    -- Fulfilment
    status         ENUM('new','processing','shipped','completed','cancelled') NOT NULL DEFAULT 'new',
    notes          TEXT,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_order_number (order_number),
    KEY idx_order_email (email),
    KEY idx_order_status (payment_status),
    KEY idx_order_yoco (yoco_checkout_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Order items ----------
CREATE TABLE IF NOT EXISTS order_items (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id    INT UNSIGNED NOT NULL,
    product_id  INT UNSIGNED DEFAULT NULL,
    sku         VARCHAR(100) NOT NULL,
    name        VARCHAR(300) NOT NULL,
    unit_price  DECIMAL(12,2) NOT NULL,
    cost_price  DECIMAL(12,2) NOT NULL DEFAULT 0.00,  -- feed dealer cost (ex VAT) snapshotted at sale time
    quantity    INT NOT NULL DEFAULT 1,
    line_total  DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_oi_order (order_id),
    CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Settings (key/value) ----------
CREATE TABLE IF NOT EXISTS settings (
    skey       VARCHAR(80) NOT NULL,
    svalue     TEXT,
    PRIMARY KEY (skey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Admin users ----------
CREATE TABLE IF NOT EXISTS admin_users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username      VARCHAR(80) NOT NULL,
    email         VARCHAR(200) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login    DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_user (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Feed import log ----------
CREATE TABLE IF NOT EXISTS feed_log (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    feed_type       VARCHAR(30) NOT NULL,
    started_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at     DATETIME DEFAULT NULL,
    products_seen   INT NOT NULL DEFAULT 0,
    products_added  INT NOT NULL DEFAULT 0,
    products_updated INT NOT NULL DEFAULT 0,
    products_deactivated INT NOT NULL DEFAULT 0,
    status          ENUM('running','success','failed') NOT NULL DEFAULT 'running',
    message         TEXT,
    PRIMARY KEY (id),
    KEY idx_feed_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------- Seed default settings ----------
INSERT INTO settings (skey, svalue) VALUES
    ('markup_multiplier', '1.25'),
    ('vat_multiplier', '1.15'),
    ('shipping_fee_ex', '180.00'),
    ('shipping_free_cost_over', '2500.00'),
    ('syntech_rep_name', ''),
    ('syntech_rep_email', ''),
    ('store_tagline', 'Your one stop shop for PC hardware & tech'),
    ('commission_rate_pct', '40'),
    ('price_floor_margin_pct', '15'),
    ('price_cap_margin_pct', '35'),
    ('price_rrp_nudge_pct', '100')
ON DUPLICATE KEY UPDATE svalue = VALUES(svalue);
