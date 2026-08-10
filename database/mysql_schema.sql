-- MySQL Schema for XAMPP - FileServer Enterprise
-- Run in phpMyAdmin > file_server > Import, or via mysql CLI
CREATE DATABASE IF NOT EXISTS `file_server` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `file_server`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `avatar` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `folders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `parent_id` INT NULL,
  `name` VARCHAR(255) NOT NULL,
  `created_by` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL,
  FOREIGN KEY (`parent_id`) REFERENCES `folders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `files` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `folder_id` INT NULL,
  `name` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `extension` VARCHAR(20) NULL,
  `mime_type` VARCHAR(120) NULL,
  `size` BIGINT NOT NULL DEFAULT 0,
  `storage_provider` VARCHAR(30) NOT NULL DEFAULT 'local',
  `storage_path` VARCHAR(500) NOT NULL,
  `storage_file_id` VARCHAR(255) NULL,
  `thumbnail_path` VARCHAR(500) NULL,
  `uploaded_by` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL,
  FOREIGN KEY (`folder_id`) REFERENCES `folders`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `file_versions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `file_id` INT NOT NULL,
  `version_no` INT NOT NULL,
  `storage_path` VARCHAR(500) NOT NULL,
  `size` BIGINT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`file_id`) REFERENCES `files`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `starred_files` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `file_id` INT NULL,
  `folder_id` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_star` (`user_id`,`file_id`,`folder_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`file_id`) REFERENCES `files`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`folder_id`) REFERENCES `folders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `file_shares` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `file_id` INT NULL,
  `folder_id` INT NULL,
  `shared_by` INT NOT NULL,
  `shared_with` INT NULL,
  `share_token` VARCHAR(64) NULL,
  `permission` VARCHAR(20) NOT NULL DEFAULT 'viewer',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `target_type` VARCHAR(20) NOT NULL,
  `target_id` INT NULL,
  `target_name` VARCHAR(255) NULL,
  `details` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `storage_connections` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `provider` VARCHAR(30) NOT NULL,
  `config_json` TEXT NULL,
  `is_active` TINYINT NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
