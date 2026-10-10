USE `detection`;

CREATE TABLE IF NOT EXISTS `photo_uploads` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`       SMALLINT UNSIGNED NOT NULL,
    `stored_name`   CHAR(36) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `product_code`  VARCHAR(30) DEFAULT NULL,
    `product_category` VARCHAR(24) NOT NULL DEFAULT 'other',
    `mime_type`     VARCHAR(30) NOT NULL,
    `file_size`     INT UNSIGNED NOT NULL,
    `image_width`   INT UNSIGNED NOT NULL,
    `image_height`  INT UNSIGNED NOT NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_photo_stored_name` (`stored_name`),
    KEY `idx_photo_user_created` (`user_id`, `created_at`),
    KEY `idx_photo_user_category_created` (`user_id`, `product_category`, `created_at`),
    CONSTRAINT `fk_photo_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;
