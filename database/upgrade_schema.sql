-- ========================================
-- SHEBAMILES EMS - ENHANCED FEATURES UPGRADE
-- Run this after importing shebamiles_db.sql
-- ========================================

USE `shebamiles_ems_new`;

-- --------------------------------------------------------
-- Table: documents (Employee document management)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `documents` (
  `document_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `document_type` enum('id_card','certificate','contract','resume','other') NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `upload_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`document_id`),
  KEY `employee_id` (`employee_id`),
  KEY `uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: company_settings (System configuration)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `company_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_group` varchar(50) DEFAULT 'general',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: activity_log (Audit trail)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `activity_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  KEY `created_at` (`created_at`),
  KEY `entity_type` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: holidays (Company holidays)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `holidays` (
  `holiday_id` int(11) NOT NULL AUTO_INCREMENT,
  `holiday_name` varchar(100) NOT NULL,
  `holiday_date` date NOT NULL,
  `is_recurring` tinyint(1) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`holiday_id`),
  KEY `holiday_date` (`holiday_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: leave_balance (Employee leave entitlements)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `leave_balance` (
  `balance_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `leave_type` enum('sick','vacation','personal','emergency','other') NOT NULL,
  `year` int(4) NOT NULL,
  `total_days` int(11) NOT NULL DEFAULT 0,
  `used_days` int(11) NOT NULL DEFAULT 0,
  `remaining_days` int(11) GENERATED ALWAYS AS (`total_days` - `used_days`) STORED,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`balance_id`),
  UNIQUE KEY `employee_leave_year` (`employee_id`, `leave_type`, `year`),
  KEY `employee_id` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: salary_history (Track salary changes)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `salary_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `old_salary` decimal(10,2) NOT NULL,
  `new_salary` decimal(10,2) NOT NULL,
  `effective_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `changed_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  KEY `employee_id` (`employee_id`),
  KEY `changed_by` (`changed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: announcements (Company-wide announcements)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `announcements` (
  `announcement_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `target_audience` enum('all','admins','managers','employees','department') NOT NULL DEFAULT 'all',
  `department_id` int(11) DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `publish_date` date DEFAULT NULL,
  `expire_date` date DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`announcement_id`),
  KEY `created_by` (`created_by`),
  KEY `department_id` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: timesheet (Detailed time tracking)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `timesheet` (
  `timesheet_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `project_name` varchar(200) DEFAULT NULL,
  `hours_worked` decimal(5,2) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`timesheet_id`),
  KEY `employee_id` (`employee_id`),
  KEY `approved_by` (`approved_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Update: notifications table (adds link + read_at)
-- Works on MySQL and MariaDB and is safe to run more than once.
-- --------------------------------------------------------

SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `notifications` ADD COLUMN `link` varchar(255) DEFAULT NULL',
  'SELECT 1') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'link');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `notifications` ADD COLUMN `read_at` timestamp NULL DEFAULT NULL',
  'SELECT 1') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'read_at');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Activity log entity ids can be text (e.g. 'all'), so widen the column on older installs
ALTER TABLE `activity_log` MODIFY `entity_id` varchar(50) DEFAULT NULL;

-- --------------------------------------------------------
-- Foreign key constraints (each added only if missing)
-- --------------------------------------------------------

SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `documents` ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE',
  'SELECT 1') FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND CONSTRAINT_NAME = 'documents_ibfk_1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `documents` ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT',
  'SELECT 1') FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND CONSTRAINT_NAME = 'documents_ibfk_2');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `activity_log` ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE',
  'SELECT 1') FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'activity_log' AND CONSTRAINT_NAME = 'activity_log_ibfk_1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `leave_balance` ADD CONSTRAINT `leave_balance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE',
  'SELECT 1') FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_balance' AND CONSTRAINT_NAME = 'leave_balance_ibfk_1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
