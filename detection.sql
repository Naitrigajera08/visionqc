-- =====================================================
--  Database : detection
--  Project  : VisionQC - Quality Control Dashboard
--  Engine   : MySQL 5.7+ / MariaDB 10.3+ (XAMPP / WAMP OK)
-- =====================================================

DROP DATABASE IF EXISTS `detection`;
CREATE DATABASE `detection` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `detection`;

-- -----------------------------------------------------
-- 1. Defect types (Passed / Damaged / Cracked / Scratches)
-- -----------------------------------------------------
CREATE TABLE `defect_types` (
    `id`         TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`       VARCHAR(20)  NOT NULL,
    `name`       VARCHAR(50)  NOT NULL,
    `class_code` VARCHAR(10)  DEFAULT NULL,   -- XYZ / ABC / NGA (NULL for Passed)
    `tone`       VARCHAR(10)  NOT NULL,       -- green, red, amber, orange
    `color`      CHAR(7)      NOT NULL,       -- hex colour used in charts
    `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_defect_code` (`code`)
) ENGINE=InnoDB;

INSERT INTO `defect_types` (`code`, `name`, `class_code`, `tone`, `color`, `sort_order`) VALUES
('PASSED',  'Passed',  NULL,  'green',  '#428765', 1),
('DAMAGED', 'Damaged', 'XYZ', 'red',    '#bd514b', 2),
('CRACKED', 'Cracked', 'ABC', 'amber',  '#d68b35', 3),
('SCRATCHES', 'Scratches', 'SCR', 'orange', '#edac45', 4);

-- -----------------------------------------------------
-- 2. Cameras
-- -----------------------------------------------------
CREATE TABLE `cameras` (
    `id`         TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(30) NOT NULL,
    `line_name`  VARCHAR(50) NOT NULL,
    `model_name` VARCHAR(50) NOT NULL DEFAULT 'YOLO',
    `status`     ENUM('online','offline','maintenance') NOT NULL DEFAULT 'online',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_camera_name` (`name`)
) ENGINE=InnoDB;

INSERT INTO `cameras` (`name`, `line_name`, `model_name`, `status`) VALUES
('Camera 01', 'Assembly line', 'YOLO', 'online'),
('Camera 02', 'Assembly line', 'YOLO', 'online');

-- -----------------------------------------------------
-- 3. Inspections (one row per product checked by AI)
-- -----------------------------------------------------
CREATE TABLE `inspections` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_code`   VARCHAR(30) NOT NULL,
    `inspected_at`   DATETIME    NOT NULL,
    `defect_type_id` TINYINT UNSIGNED NOT NULL,
    `confidence`     TINYINT UNSIGNED NOT NULL,          -- 0 - 100 %
    `camera_id`      TINYINT UNSIGNED NOT NULL,
    `image_path`     VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_product_code` (`product_code`),
    KEY `idx_inspected_at` (`inspected_at`),
    CONSTRAINT `fk_insp_defect` FOREIGN KEY (`defect_type_id`) REFERENCES `defect_types` (`id`),
    CONSTRAINT `fk_insp_camera` FOREIGN KEY (`camera_id`)      REFERENCES `cameras` (`id`),
    CONSTRAINT `chk_confidence` CHECK (`confidence` BETWEEN 0 AND 100)
) ENGINE=InnoDB;

INSERT INTO `inspections` (`product_code`, `inspected_at`, `defect_type_id`, `confidence`, `camera_id`) VALUES
('P-20261012-3842', TIMESTAMP(CURDATE(), '10:24:12'), 3, 92, 1),
('P-20261012-3841', TIMESTAMP(CURDATE(), '10:23:41'), 2, 87, 1),
('P-20261012-3840', TIMESTAMP(CURDATE(), '10:22:18'), 4, 90, 2),
('P-20261012-3839', TIMESTAMP(CURDATE(), '10:21:55'), 1, 98, 1),
('P-20261012-3838', TIMESTAMP(CURDATE(), '10:21:20'), 1, 97, 2),
('P-20261012-3837', TIMESTAMP(CURDATE(), '10:20:48'), 1, 99, 1),
('P-20261012-3836', TIMESTAMP(CURDATE(), '10:20:05'), 2, 85, 2),
('P-20261012-3835', TIMESTAMP(CURDATE(), '10:19:33'), 1, 96, 1);

-- -----------------------------------------------------
-- 4. Hourly production (chart + metric cards)
-- -----------------------------------------------------
CREATE TABLE `hourly_production` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `stat_date`   DATE NOT NULL,
    `hour_of_day` TINYINT UNSIGNED NOT NULL,             -- 0 - 23
    `passed`      INT UNSIGNED NOT NULL DEFAULT 0,
    `damaged`     INT UNSIGNED NOT NULL DEFAULT 0,
    `cracked`     INT UNSIGNED NOT NULL DEFAULT 0,
    `burned`      INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_date_hour` (`stat_date`, `hour_of_day`)
) ENGINE=InnoDB;

-- Totals: passed 3426, damaged 212, cracked 128, scratches 76 = 3842 inspected
INSERT INTO `hourly_production` (`stat_date`, `hour_of_day`, `passed`, `damaged`, `cracked`, `burned`) VALUES
(CURDATE(),  8, 230, 14,  8, 5),
(CURDATE(),  9, 255, 15,  9, 5),
(CURDATE(), 10, 270, 16, 10, 6),
(CURDATE(), 11, 285, 17, 10, 6),
(CURDATE(), 12, 295, 17, 10, 6),
(CURDATE(), 13, 300, 18, 11, 6),
(CURDATE(), 14, 292, 18, 11, 6),
(CURDATE(), 15, 280, 17, 10, 6),
(CURDATE(), 16, 268, 16, 10, 6),
(CURDATE(), 17, 255, 16, 10, 6),
(CURDATE(), 18, 240, 16, 10, 6),
(CURDATE(), 19, 235, 16, 10, 6),
(CURDATE(), 20, 221, 16,  9, 6);

-- -----------------------------------------------------
-- 5. Daily summary (totals, yesterday comparison, cost)
-- -----------------------------------------------------
CREATE TABLE `daily_summary` (
    `stat_date`        DATE NOT NULL,
    `total_inspected`  INT UNSIGNED NOT NULL DEFAULT 0,
    `estimated_loss`   DECIMAL(12,2) NOT NULL DEFAULT 0,  -- INR
    `material_waste_kg` DECIMAL(8,2) NOT NULL DEFAULT 0,
    PRIMARY KEY (`stat_date`)
) ENGINE=InnoDB;

INSERT INTO `daily_summary` (`stat_date`, `total_inspected`, `estimated_loss`, `material_waste_kg`) VALUES
(CURDATE() - INTERVAL 1 DAY, 3544, 16980.00, 4.80),
(CURDATE(),                  3842, 18720.00, 5.20);

-- -----------------------------------------------------
-- 6. Alerts
-- -----------------------------------------------------
CREATE TABLE `alerts` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(120) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `tone`        ENUM('red','amber','blue') NOT NULL DEFAULT 'blue',
    `alerted_at`  DATETIME NOT NULL,
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_alerted_at` (`alerted_at`)
) ENGINE=InnoDB;

INSERT INTO `alerts` (`title`, `description`, `tone`, `alerted_at`) VALUES
('High defect rate on Machine M-2',  'Defect rate increased to 18% in the last 30 minutes.',    'red',   TIMESTAMP(CURDATE(), '10:22:00')),
('Camera 2 lens needs cleaning',     'Image quality is low. Please clean the camera lens.',      'amber', TIMESTAMP(CURDATE(), '09:50:00')),
('Scratches defect detected',         'Multiple scratch defects detected in the last 10 minutes.', 'red',   TIMESTAMP(CURDATE(), '09:15:00')),
('Scheduled maintenance reminder',   'Machine M-2 is due for maintenance in 2 hours.',           'blue',  TIMESTAMP(CURDATE(), '08:30:00'));

-- -----------------------------------------------------
-- 7. Machines
-- -----------------------------------------------------
CREATE TABLE `machines` (
    `id`      TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`    VARCHAR(10) NOT NULL,
    `process` VARCHAR(40) NOT NULL,
    `health`  TINYINT UNSIGNED NOT NULL,                  -- 0 - 100 %
    `state`   VARCHAR(30) NOT NULL DEFAULT 'Normal',
    `tone`    ENUM('green','amber','red') NOT NULL DEFAULT 'green',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_machine_code` (`code`),
    CONSTRAINT `chk_health` CHECK (`health` BETWEEN 0 AND 100)
) ENGINE=InnoDB;

INSERT INTO `machines` (`code`, `process`, `health`, `state`, `tone`) VALUES
('M-1', 'Assembly',      95, 'Normal',       'green'),
('M-2', 'Painting',      78, 'Service soon', 'amber'),
('M-3', 'Packing',       92, 'Normal',       'green'),
('M-4', 'Quality check', 98, 'Normal',       'green');

-- -----------------------------------------------------
-- 8. Operators + daily accuracy
-- -----------------------------------------------------
CREATE TABLE `operators` (
    `id`    SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`  VARCHAR(60) NOT NULL,
    `shift` ENUM('Morning','Afternoon','Night') NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE `operator_performance` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `operator_id` SMALLINT UNSIGNED NOT NULL,
    `perf_date`   DATE NOT NULL,
    `accuracy`    DECIMAL(5,2) NOT NULL,                  -- inspection accuracy %
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_operator_day` (`operator_id`, `perf_date`),
    CONSTRAINT `fk_perf_operator` FOREIGN KEY (`operator_id`) REFERENCES `operators` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO `operators` (`name`, `shift`) VALUES
('Rajesh', 'Morning'),
('Ankit',  'Afternoon'),
('Karan',  'Night');

INSERT INTO `operator_performance` (`operator_id`, `perf_date`, `accuracy`) VALUES
(1, CURDATE(), 98.00),
(2, CURDATE(), 92.00),
(3, CURDATE(), 80.00);

-- -----------------------------------------------------
-- 9. Dashboard users (VisionQC account sign-in and profiles)
-- -----------------------------------------------------
CREATE TABLE `users` (
    `id`            SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(60)  NOT NULL,
    `email`         VARCHAR(120) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role`          VARCHAR(40)  NOT NULL DEFAULT 'Factory manager',
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_email` (`email`)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- 10. Uploaded inspection photos (awaiting a configured AI model)
-- -----------------------------------------------------
CREATE TABLE `photo_uploads` (
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
    `review_status` VARCHAR(24) NOT NULL DEFAULT 'pending',
    `review_notes`  TEXT DEFAULT NULL,
    `reviewed_by`   SMALLINT UNSIGNED DEFAULT NULL,
    `reviewed_at`   DATETIME DEFAULT NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_photo_stored_name` (`stored_name`),
    KEY `idx_photo_user_created` (`user_id`, `created_at`),
    KEY `idx_photo_user_category_created` (`user_id`, `product_category`, `created_at`),
    KEY `idx_photo_user_review_category` (`user_id`, `review_status`, `product_category`),
    CONSTRAINT `fk_photo_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_photo_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Alternatively create a user from PHP so the hash is valid, e.g.:
--   echo password_hash('your-password', PASSWORD_DEFAULT);
-- then:
--   INSERT INTO users (name, email, password_hash, role)
--   VALUES ('Alex Morgan', 'alex@factoryos.local', '<paste hash>', 'Factory manager');
--
-- To grant administrator access to an existing account, sign up normally and
-- then run the following query with that account's email address:
--   UPDATE users SET role = 'Administrator' WHERE email = 'admin@example.com';
-- Sign in through login.php?mode=admin to open the admin-only panel.
