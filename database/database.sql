CREATE DATABASE IF NOT EXISTS `whatsapp_bot` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `whatsapp_bot`;

-- 消息任务表
CREATE TABLE IF NOT EXISTS `message_jobs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `recipient` VARCHAR(30) NOT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('PENDING', 'PROCESSING', 'SENT', 'FAILED') NOT NULL DEFAULT 'PENDING',
  `attempts` INT NOT NULL DEFAULT 0,
  `scheduled_at` DATETIME NOT NULL,
  `completed_at` DATETIME NULL,
  `error_info` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Worker 节点表
CREATE TABLE IF NOT EXISTS `workers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL,
  `status` ENUM('Online', 'Offline', 'Revoked') DEFAULT 'Online',
  `last_seen` DATETIME NULL,
  `registered_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 系统配置表
CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_key` VARCHAR(50) PRIMARY KEY,
  `setting_value` TEXT NULL
) ENGINE=InnoDB;

-- 初始化配置默认值
INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('api_base_url', 'http://localhost/whatsapp-bot/web/api'),
('app_timezone', 'Asia/Kuala_Lumpur')
ON DUPLICATE KEY UPDATE `setting_key`=`setting_key`;