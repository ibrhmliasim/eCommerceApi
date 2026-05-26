-- ============================================================
--  E-COMMERCE DATABASE SCHEMA  (final)
-- ============================================================

CREATE TABLE users (
  id                          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email                       VARCHAR(255) NOT NULL UNIQUE,
  password                    VARCHAR(255) NOT NULL,
  remember_token              VARCHAR(100) NULL,
  phone                       VARCHAR(20)  UNIQUE,
  first_name                  VARCHAR(100),
  last_name                   VARCHAR(100),
  role                        ENUM('user','admin') DEFAULT 'user',
  email_verified_at           TIMESTAMP NULL,
  default_shipping_address_id BIGINT UNSIGNED NULL,
  default_billing_address_id  BIGINT UNSIGNED NULL,
  created_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at                  TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────

CREATE TABLE addresses (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       BIGINT UNSIGNED NOT NULL,
  first_name    VARCHAR(100) NOT NULL,
  last_name     VARCHAR(100) NOT NULL,
  postal_code   VARCHAR(8)   NOT NULL,
  prefecture    VARCHAR(100) NOT NULL,
  city          VARCHAR(100) NOT NULL,
  ward          VARCHAR(100),
  address_line1 VARCHAR(255) NOT NULL,
  address_line2 VARCHAR(255),
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_address_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Циркулярные FK добавляем после создания обеих таблиц
ALTER TABLE users
  ADD CONSTRAINT fk_default_shipping
    FOREIGN KEY (default_shipping_address_id) REFERENCES addresses(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_default_billing
    FOREIGN KEY (default_billing_address_id)  REFERENCES addresses(id) ON DELETE SET NULL;

-- ────────────────────────────────────────────────────────────

CREATE TABLE categories (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id   BIGINT UNSIGNED NULL,
  name        VARCHAR(100) NOT NULL,
  slug        VARCHAR(255) NOT NULL,
  description TEXT NULL,
  is_active   BOOLEAN DEFAULT TRUE,
  sort_order  INT DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at  TIMESTAMP NULL,

  UNIQUE uq_slug_per_parent (parent_id, slug),

  CONSTRAINT fk_categories_parent
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE CASCADE,

  INDEX idx_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────

CREATE TABLE products (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id BIGINT UNSIGNED NOT NULL,
  name        VARCHAR(255) NOT NULL,
  slug        VARCHAR(255) NOT NULL UNIQUE,
  description TEXT NULL,
  is_active   BOOLEAN DEFAULT TRUE,
  sort_order  INT DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at  TIMESTAMP NULL,

  CONSTRAINT fk_product_category
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,

  INDEX idx_products_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────

CREATE TABLE product_variants (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id       BIGINT UNSIGNED NOT NULL,
  sku              VARCHAR(50)     NOT NULL UNIQUE,
  color            VARCHAR(50)     NOT NULL,
  size             VARCHAR(20)     NOT NULL,
  price            DECIMAL(12,2)   NOT NULL,
  compare_at_price DECIMAL(12,2)   NULL,
  stock_quantity   INT UNSIGNED    NOT NULL DEFAULT 0,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_variant_product
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,

  UNIQUE KEY uq_variant (product_id, color, size),
  INDEX idx_variants_product_filter (product_id, color, size)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────

CREATE TABLE product_images (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  variant_id BIGINT UNSIGNED NULL,
  url        VARCHAR(255) NOT NULL,
  is_main    BOOLEAN DEFAULT FALSE,
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  FOREIGN KEY (product_id) REFERENCES products(id)         ON DELETE CASCADE,
  FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,

  INDEX idx_product_sort (product_id, sort_order),
  INDEX idx_variant (variant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────

CREATE TABLE carts (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    BIGINT UNSIGNED NOT NULL,          -- только авторизованные
  status     ENUM('active','converted','abandoned') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────

CREATE TABLE cart_items (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cart_id    BIGINT UNSIGNED NOT NULL,
  variant_id BIGINT UNSIGNED NOT NULL,
  quantity   INT UNSIGNED NOT NULL DEFAULT 1 CHECK (quantity > 0),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  FOREIGN KEY (cart_id)    REFERENCES carts(id)            ON DELETE CASCADE,
  FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,

  UNIQUE KEY uq_cart_variant (cart_id, variant_id),
  INDEX idx_cart (cart_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────

CREATE TABLE orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  cart_id BIGINT UNSIGNED NULL,

  -- Snapshot покупателя
  shipping_first_name    VARCHAR(100) NOT NULL,
  shipping_last_name     VARCHAR(100) NOT NULL,
  shipping_email         VARCHAR(255) NOT NULL,
  shipping_phone         VARCHAR(20)  NULL,

  -- Snapshot адреса
  shipping_postal_code   VARCHAR(8)   NOT NULL,
  shipping_prefecture    VARCHAR(100) NOT NULL,
  shipping_city          VARCHAR(100) NOT NULL,
  shipping_ward          VARCHAR(100) NULL,
  shipping_address_line1 VARCHAR(255) NOT NULL,
  shipping_address_line2 VARCHAR(255) NULL,

  -- Финансы
  subtotal      DECIMAL(10,2) NOT NULL,
  shipping_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  tax_amount    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total_amount  DECIMAL(10,2) NOT NULL,
  currency      VARCHAR(3)    NOT NULL DEFAULT 'JPY',

  -- Статус и доставка
  status ENUM(
    'pending','paid','processing',
    'shipped','delivered','cancelled','returned'
  ) DEFAULT 'pending',
  tracking_number VARCHAR(100) NULL,

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE SET NULL,

  INDEX idx_user_created   (user_id, created_at DESC),
  INDEX idx_status_created (status,  created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────

CREATE TABLE order_items (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id          BIGINT UNSIGNED NOT NULL,
  variant_id        BIGINT UNSIGNED NULL,
  product_name      VARCHAR(255) NOT NULL,
  sku               VARCHAR(50)  NOT NULL,
  color             VARCHAR(50)  NOT NULL,
  size              VARCHAR(20)  NOT NULL,
  price_at_purchase DECIMAL(10,2) NOT NULL,
  quantity          INT UNSIGNED NOT NULL DEFAULT 1 CHECK (quantity > 0),
  created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  FOREIGN KEY (order_id)   REFERENCES orders(id)           ON DELETE CASCADE,
  FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,

  INDEX idx_order          (order_id),
  INDEX idx_variant        (variant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────

CREATE TABLE payments (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id        BIGINT UNSIGNED NOT NULL,
  transaction_id  VARCHAR(255) NOT NULL UNIQUE,
  idempotency_key VARCHAR(255) NULL UNIQUE,
  payment_method  VARCHAR(50)  NOT NULL,
  amount          DECIMAL(10,2) NOT NULL,
  currency        VARCHAR(3)   NOT NULL DEFAULT 'JPY',
  status          ENUM('pending','processing','success','failed','refunded','cancelled') NOT NULL,
  error_code      VARCHAR(50)  NULL,
  error_message   VARCHAR(255) NULL,
  gateway_response JSON        NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,

  INDEX idx_order          (order_id),
  INDEX idx_status         (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────

CREATE TABLE wishlists (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_wishlist_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
  CONSTRAINT fk_wishlist_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,

  UNIQUE KEY uq_user_product (user_id, product_id),
  INDEX idx_user_created (user_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────

CREATE TABLE order_status_history (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id      BIGINT UNSIGNED NOT NULL,
  old_status    ENUM('pending','paid','processing','shipped','delivered','cancelled','returned') NULL,
  new_status    ENUM('pending','paid','processing','shipped','delivered','cancelled','returned') NOT NULL,
  comment       VARCHAR(255) NULL,
  changed_by    BIGINT UNSIGNED NULL,
  change_source ENUM('system','admin','user','webhook') DEFAULT 'system',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,

  INDEX idx_order_created (order_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
