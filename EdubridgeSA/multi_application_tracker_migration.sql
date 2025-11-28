-- Multi-Application Tracker Migration (DDL only)
-- Creates required tables and adds missing column for progress tracking.
-- Target DB: u839420047_applications

/* 1) Add progress tracking column to applications */
ALTER TABLE `applications` ADD COLUMN `progress_percentage` INT DEFAULT 0;

/* 2) Create table: application_steps */
CREATE TABLE IF NOT EXISTS `application_steps` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_id` INT NOT NULL,
  `step_name` VARCHAR(100) NOT NULL,
  `step_description` TEXT,
  `is_completed` TINYINT(1) DEFAULT 0,
  `is_required` TINYINT(1) DEFAULT 1,
  `step_order` INT NOT NULL,
  `completion_date` TIMESTAMP NULL DEFAULT NULL,
  `completion_notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_application_steps_application`
    FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE,
  INDEX `idx_application_steps_application_id` (`application_id`),
  INDEX `idx_application_steps_step_order` (`step_order`),
  INDEX `idx_application_steps_is_completed` (`is_completed`),
  UNIQUE KEY `uniq_application_step_name` (`application_id`, `step_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* 3) Create table: application_progress_history */
CREATE TABLE IF NOT EXISTS `application_progress_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_id` INT NOT NULL,
  `old_percentage` INT DEFAULT 0,
  `new_percentage` INT DEFAULT 0,
  `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `change_reason` VARCHAR(255) NULL,
  CONSTRAINT `fk_progress_history_application`
    FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE,
  INDEX `idx_progress_history_application_id` (`application_id`),
  INDEX `idx_progress_history_changed_at` (`changed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* 4) Create table: application_next_steps */
CREATE TABLE IF NOT EXISTS `application_next_steps` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_id` INT NOT NULL,
  `step_name` VARCHAR(100) NOT NULL,
  `step_description` TEXT,
  `action_url` VARCHAR(255) NULL,
  `priority` INT DEFAULT 1,
  `is_completed` TINYINT(1) DEFAULT 0,
  `is_required` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_next_steps_application`
    FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE,
  INDEX `idx_next_steps_application_id` (`application_id`),
  INDEX `idx_next_steps_priority` (`priority`),
  INDEX `idx_next_steps_is_completed` (`is_completed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* Note: Indexes on applications table are omitted to avoid duplicate-key errors. */

