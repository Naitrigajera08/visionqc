USE `detection`;

SET @category_column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'photo_uploads'
      AND COLUMN_NAME = 'product_category'
);
SET @category_column_sql = IF(
    @category_column_exists = 0,
    'ALTER TABLE `photo_uploads` ADD COLUMN `product_category` VARCHAR(24) NOT NULL DEFAULT ''other'' AFTER `product_code`',
    'SELECT 1'
);
PREPARE add_category_column FROM @category_column_sql;
EXECUTE add_category_column;
DEALLOCATE PREPARE add_category_column;

SET @category_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'photo_uploads'
      AND INDEX_NAME = 'idx_photo_user_category_created'
);
SET @category_index_sql = IF(
    @category_index_exists = 0,
    'ALTER TABLE `photo_uploads` ADD KEY `idx_photo_user_category_created` (`user_id`, `product_category`, `created_at`)',
    'SELECT 1'
);
PREPARE add_category_index FROM @category_index_sql;
EXECUTE add_category_index;
DEALLOCATE PREPARE add_category_index;
