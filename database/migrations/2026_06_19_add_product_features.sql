ALTER TABLE `products`
ADD COLUMN `brand` VARCHAR(255) DEFAULT NULL AFTER `sku`,
ADD COLUMN `key_features` TEXT DEFAULT NULL AFTER `description`,
ADD COLUMN `variants` TEXT DEFAULT NULL AFTER `key_features`;
