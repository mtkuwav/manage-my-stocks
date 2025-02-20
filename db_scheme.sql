-- --------------------------------------------------------
-- `users` table for JWT auth
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `role` ENUM('admin', 'manager') NOT NULL DEFAULT 'manager',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `username_UNIQUE` (`username` ASC),
  UNIQUE INDEX `email_UNIQUE` (`email` ASC)
);

-- --------------------------------------------------------
-- `categories` table to order products
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `name_UNIQUE` (`name` ASC)
);

-- --------------------------------------------------------
-- `products` table for stocks management
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `sku` VARCHAR(50) NOT NULL,
  `price` DECIMAL(10,2) UNSIGNED NOT NULL,
  `quantity_in_stock` INT UNSIGNED NOT NULL DEFAULT 0,
  `category_id` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `sku_UNIQUE` (`sku` ASC),
  CONSTRAINT `fk_products_categories`
    FOREIGN KEY (`category_id`)
    REFERENCES `categories` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
);

-- --------------------------------------------------------
-- `orders` table for orders management
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `order_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('pending', 'processing', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
  `total_amount` DECIMAL(10,2) UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `fk_orders_users_idx` (`user_id` ASC),
  CONSTRAINT `fk_orders_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
);

-- --------------------------------------------------------
-- `order_items` table for the products in the orders
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED NOT NULL,
  `unit_price` DECIMAL(10,2) UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `fk_order_items_orders_idx` (`order_id` ASC),
  INDEX `fk_order_items_products_idx` (`product_id` ASC),
  CONSTRAINT `fk_order_items_orders`
    FOREIGN KEY (`order_id`)
    REFERENCES `orders` (`id`),
  CONSTRAINT `fk_order_items_products`
    FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`)
);

-- --------------------------------------------------------
-- `deliveries` table for delivery tracking
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `deliveries` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `delivery_date` TIMESTAMP NULL,
  `status` ENUM('pending', 'shipped', 'delivered', 'failed') NOT NULL DEFAULT 'pending',
  `tracking_number` VARCHAR(100) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `fk_deliveries_orders_idx` (`order_id` ASC),
  CONSTRAINT `fk_deliveries_orders`
    FOREIGN KEY (`order_id`)
    REFERENCES `orders` (`id`)
);

-- --------------------------------------------------------
-- `returns` table for returns management
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `returns` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_item_id` INT UNSIGNED NOT NULL,
  `quantity_returned` INT UNSIGNED NOT NULL,
  `reason` TEXT NULL,
  `status` ENUM('requested', 'approved', 'rejected', 'refunded') NOT NULL DEFAULT 'requested',
  `processed_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `fk_returns_order_items_idx` (`order_item_id` ASC),
  INDEX `fk_returns_users_idx` (`processed_by` ASC),
  CONSTRAINT `fk_returns_order_items`
    FOREIGN KEY (`order_item_id`)
    REFERENCES `order_items` (`id`),
  CONSTRAINT `fk_returns_users`
    FOREIGN KEY (`processed_by`)
    REFERENCES `users` (`id`)
);

-- --------------------------------------------------------
-- `inventory_logs` table for inventory audit
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventory_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NULL,
  `old_quantity` INT NOT NULL,
  `new_quantity` INT NOT NULL,
  `change_type` ENUM('sale', 'return', 'adjustment', 'restock', 'initial') NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `fk_inventory_logs_products_idx` (`product_id` ASC),
  INDEX `fk_inventory_logs_users_idx` (`user_id` ASC),
  CONSTRAINT `fk_inventory_logs_products`
    FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`),
  CONSTRAINT `fk_inventory_logs_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
);

-- --------------------------------------------------------
-- `refresh_tokens` table for JWT management
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `refresh_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `token` VARCHAR(255) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `revoked` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `fk_refresh_tokens_users_idx` (`user_id` ASC),
  CONSTRAINT `fk_refresh_tokens_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
);

-- --------------------------------------------------------
-- Initial admin user
-- Password: adminmdp (hashed with PASSWORD_BCRYPT and salt 'sqidq7sà')
-- --------------------------------------------------------
INSERT INTO `users` (`username`, `email`, `password_hash`, `role`) 
VALUES (
    'admin',
    'admin@boutique.com',
    '$2y$10$fV3K74l/sZhS11cKKKbNJO0nRJltaVrygPpUuLvENKAEMcIz1J6me',
    'admin'
) ON DUPLICATE KEY UPDATE `id` = `id`;
