-- WordPress Headless API Database Schema
-- Additional tables for custom functionality

-- Custom post type: Portfolio
-- (WordPress core will create the main posts table)

-- Portfolio meta table (if using custom fields)
CREATE TABLE IF NOT EXISTS `wp_portfolio_meta` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `portfolio_id` bigint(20) unsigned NOT NULL,
  `meta_key` varchar(255) DEFAULT NULL,
  `meta_value` longtext,
  PRIMARY KEY (`id`),
  KEY `portfolio_id` (`portfolio_id`),
  KEY `meta_key` (`meta_key`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API access logs table
CREATE TABLE IF NOT EXISTS `wp_api_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `endpoint` varchar(255) NOT NULL,
  `method` varchar(10) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `response_code` int(11) NOT NULL,
  `response_time` decimal(8,3) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `endpoint` (`endpoint`(191)),
  KEY `method` (`method`),
  KEY `user_id` (`user_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Performance metrics table
CREATE TABLE IF NOT EXISTS `wp_performance_metrics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `page_url` varchar(255) NOT NULL,
  `load_time` decimal(8,3) NOT NULL,
  `memory_usage` bigint(20) NOT NULL,
  `query_count` int(11) NOT NULL,
  `slow_queries` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `page_url` (`page_url`(191)),
  KEY `load_time` (`load_time`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- JWT tokens blacklist table
CREATE TABLE IF NOT EXISTS `wp_jwt_blacklist` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `token_hash` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `user_id` (`user_id`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contact form submissions table
CREATE TABLE IF NOT EXISTS `wp_contact_submissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text,
  `status` enum('unread','read','replied','spam') DEFAULT 'unread',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `email` (`email`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API rate limiting table
CREATE TABLE IF NOT EXISTS `wp_api_rate_limits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `endpoint` varchar(255) NOT NULL,
  `requests_count` int(11) NOT NULL DEFAULT 1,
  `window_start` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_request` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip_endpoint` (`ip_address`, `endpoint`(191)),
  KEY `window_start` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Custom options table for API settings
CREATE TABLE IF NOT EXISTS `wp_api_options` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `option_name` varchar(255) NOT NULL,
  `option_value` longtext,
  `autoload` enum('yes','no') NOT NULL DEFAULT 'yes',
  PRIMARY KEY (`id`),
  UNIQUE KEY `option_name` (`option_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default API options
INSERT INTO `wp_api_options` (`option_name`, `option_value`, `autoload`) VALUES
('api_version', '1.0.0', 'yes'),
('api_rate_limit_enabled', '1', 'yes'),
('api_rate_limit_requests_per_minute', '60', 'yes'),
('api_cors_enabled', '1', 'yes'),
('api_jwt_expiration_days', '7', 'yes'),
('api_performance_monitoring', '1', 'yes'),
('api_logging_enabled', '1', 'yes'),
('api_cache_enabled', '1', 'yes'),
('api_cache_ttl', '3600', 'yes');

-- Indexes for better performance
ALTER TABLE `wp_posts` ADD INDEX `idx_post_type_status` (`post_type`, `post_status`);
ALTER TABLE `wp_posts` ADD INDEX `idx_post_date` (`post_date`);
ALTER TABLE `wp_postmeta` ADD INDEX `idx_meta_key_value` (`meta_key`(191), `meta_value`(191));

-- Full-text search indexes for better API search
ALTER TABLE `wp_posts` ADD FULLTEXT `idx_search_content` (`post_title`, `post_content`, `post_excerpt`);

-- Create views for common API queries
CREATE VIEW `v_published_posts` AS
SELECT 
    `ID`,
    `post_title`,
    `post_content`,
    `post_excerpt`,
    `post_name`,
    `post_date`,
    `post_modified`,
    `post_type`,
    `post_status`
FROM `wp_posts` 
WHERE `post_status` = 'publish' 
AND `post_type` IN ('post', 'page', 'portfolio', 'service');

CREATE VIEW `v_portfolio_with_meta` AS
SELECT 
    p.`ID`,
    p.`post_title`,
    p.`post_content`,
    p.`post_excerpt`,
    p.`post_name`,
    p.`post_date`,
    p.`post_modified`,
    GROUP_CONCAT(CONCAT(pm.meta_key, ':', pm.meta_value) SEPARATOR '|') as meta_data
FROM `wp_posts` p
LEFT JOIN `wp_postmeta` pm ON p.ID = pm.post_id
WHERE p.post_type = 'portfolio' 
AND p.post_status = 'publish'
GROUP BY p.ID;

-- Trigger to clean up expired JWT tokens
DELIMITER $$
CREATE EVENT IF NOT EXISTS `cleanup_expired_tokens`
ON SCHEDULE EVERY 1 HOUR
DO
BEGIN
    DELETE FROM `wp_jwt_blacklist` WHERE `expires_at` < NOW();
    DELETE FROM `wp_api_logs` WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 30 DAY);
    DELETE FROM `wp_performance_metrics` WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 7 DAY);
    DELETE FROM `wp_api_rate_limits` WHERE `window_start` < DATE_SUB(NOW(), INTERVAL 1 HOUR);
END$$
DELIMITER ;
