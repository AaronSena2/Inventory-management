-- IT Inventory Management System Schema
-- MySQL 8.0+

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS `inventory_db`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `inventory_db`;

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(64)     NOT NULL,
  `email`         VARCHAR(255)    NOT NULL DEFAULT '',
  `password_hash` VARCHAR(255)    NOT NULL,
  `role`          ENUM('admin','viewer') NOT NULL DEFAULT 'viewer',
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: computers
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `computers` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `hostname`      VARCHAR(255)    NOT NULL,
  `ip_address`    VARCHAR(45)     NOT NULL DEFAULT '',
  `mac_address`   VARCHAR(17)     NOT NULL DEFAULT '',
  `os_name`       VARCHAR(128)    NOT NULL DEFAULT '',
  `os_version`    VARCHAR(128)    NOT NULL DEFAULT '',
  `os_arch`       VARCHAR(32)     NOT NULL DEFAULT '',
  `cpu_info`      VARCHAR(255)    NOT NULL DEFAULT '',
  `ram_gb`        DECIMAL(8,2)    NOT NULL DEFAULT 0,
  `storage_gb`    DECIMAL(10,2)   NOT NULL DEFAULT 0,
  `last_checkin`  DATETIME                 DEFAULT NULL,
  `status`        ENUM('online','offline','unknown') NOT NULL DEFAULT 'unknown',
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status`        (`status`),
  KEY `idx_last_checkin`  (`last_checkin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: agents
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `agents` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `computer_id`   INT UNSIGNED    NOT NULL,
  `token_hash`    CHAR(64)        NOT NULL,
  `registered_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen`     DATETIME                 DEFAULT NULL,
  `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token_hash` (`token_hash`),
  KEY `idx_token_hash`   (`token_hash`),
  KEY `idx_computer_id`  (`computer_id`),
  CONSTRAINT `fk_agents_computer`
    FOREIGN KEY (`computer_id`) REFERENCES `computers` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: software_inventory
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `software_inventory` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `computer_id`   INT UNSIGNED    NOT NULL,
  `name`          VARCHAR(255)    NOT NULL,
  `version`       VARCHAR(128)    NOT NULL DEFAULT '',
  `publisher`     VARCHAR(255)    NOT NULL DEFAULT '',
  `install_date`  DATE                     DEFAULT NULL,
  `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sw_computer_id` (`computer_id`),
  CONSTRAINT `fk_sw_computer`
    FOREIGN KEY (`computer_id`) REFERENCES `computers` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: commands
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `commands` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `computer_id`   INT UNSIGNED    NOT NULL,
  `created_by`    INT UNSIGNED    NOT NULL,
  `command_type`  ENUM('patch','install','uninstall','shell','restart','shutdown') NOT NULL,
  `payload`       JSON                     DEFAULT NULL,
  `status`        ENUM('pending','sent','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
  `scheduled_at`  DATETIME                 DEFAULT NULL,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cmd_status`      (`status`),
  KEY `idx_cmd_computer_id` (`computer_id`),
  CONSTRAINT `fk_cmd_computer`
    FOREIGN KEY (`computer_id`) REFERENCES `computers` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cmd_user`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: command_results
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `command_results` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `command_id`    INT UNSIGNED    NOT NULL,
  `computer_id`   INT UNSIGNED    NOT NULL,
  `exit_code`     INT                      DEFAULT NULL,
  `output`        TEXT,
  `error_output`  TEXT,
  `executed_at`   DATETIME                 DEFAULT NULL,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cr_command_id`  (`command_id`),
  KEY `idx_cr_computer_id` (`computer_id`),
  CONSTRAINT `fk_cr_command`
    FOREIGN KEY (`command_id`) REFERENCES `commands` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cr_computer`
    FOREIGN KEY (`computer_id`) REFERENCES `computers` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Default admin user
-- password: "password"  (bcrypt hash below)
-- --------------------------------------------------------
INSERT INTO `users` (`username`, `email`, `password_hash`, `role`)
VALUES (
  'admin',
  'admin@example.com',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uZutcjFCe',
  'admin'
) ON DUPLICATE KEY UPDATE `id` = `id`;
