USE `detection`;

SET @review_status_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'photo_uploads' AND COLUMN_NAME = 'review_status'
);
SET @review_status_sql = IF(
    @review_status_exists = 0,
    'ALTER TABLE `photo_uploads` ADD COLUMN `review_status` VARCHAR(24) NOT NULL DEFAULT ''pending''',
    'SELECT 1'
);
PREPARE add_review_status FROM @review_status_sql;
EXECUTE add_review_status;
DEALLOCATE PREPARE add_review_status;

SET @review_notes_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'photo_uploads' AND COLUMN_NAME = 'review_notes'
);
SET @review_notes_sql = IF(
    @review_notes_exists = 0,
    'ALTER TABLE `photo_uploads` ADD COLUMN `review_notes` TEXT DEFAULT NULL',
    'SELECT 1'
);
PREPARE add_review_notes FROM @review_notes_sql;
EXECUTE add_review_notes;
DEALLOCATE PREPARE add_review_notes;

SET @reviewer_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'photo_uploads' AND COLUMN_NAME = 'reviewed_by'
);
SET @reviewer_sql = IF(
    @reviewer_exists = 0,
    'ALTER TABLE `photo_uploads` ADD COLUMN `reviewed_by` SMALLINT UNSIGNED DEFAULT NULL',
    'SELECT 1'
);
PREPARE add_reviewer FROM @reviewer_sql;
EXECUTE add_reviewer;
DEALLOCATE PREPARE add_reviewer;

SET @reviewed_at_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'photo_uploads' AND COLUMN_NAME = 'reviewed_at'
);
SET @reviewed_at_sql = IF(
    @reviewed_at_exists = 0,
    'ALTER TABLE `photo_uploads` ADD COLUMN `reviewed_at` DATETIME DEFAULT NULL',
    'SELECT 1'
);
PREPARE add_reviewed_at FROM @reviewed_at_sql;
EXECUTE add_reviewed_at;
DEALLOCATE PREPARE add_reviewed_at;

SET @review_index_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'photo_uploads' AND INDEX_NAME = 'idx_photo_user_review_category'
);
SET @review_index_sql = IF(
    @review_index_exists = 0,
    'ALTER TABLE `photo_uploads` ADD KEY `idx_photo_user_review_category` (`user_id`, `review_status`, `product_category`)',
    'SELECT 1'
);
PREPARE add_review_index FROM @review_index_sql;
EXECUTE add_review_index;
DEALLOCATE PREPARE add_review_index;

SET @reviewer_fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'photo_uploads' AND CONSTRAINT_NAME = 'fk_photo_reviewer'
);
SET @reviewer_fk_sql = IF(
    @reviewer_fk_exists = 0,
    'ALTER TABLE `photo_uploads` ADD CONSTRAINT `fk_photo_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE add_reviewer_fk FROM @reviewer_fk_sql;
EXECUTE add_reviewer_fk;
DEALLOCATE PREPARE add_reviewer_fk;
