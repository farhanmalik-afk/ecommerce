-- E-commerce Admin Database Backup Snapshot
-- Generated: 2026-11-19 04:56:55



CREATE TABLE `activity_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int NOT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('1', '1', 'Unauthorized Access Attempt to user_management (create)', 'Security', '::1', '2026-11-08 12:18:10');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('2', '1', 'Created new user: Farhan Malik (farhan2650902@gmail.com)', 'User Management', '::1', '2026-11-08 12:29:05');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('3', '1', 'Deleted user ID: 2', 'User Management', '::1', '2026-11-08 12:29:13');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('4', '1', 'Created new user: noman (fm3023098@gmail.com)', 'User Management', '::1', '2026-11-09 09:05:42');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('5', '1', 'Toggled status for user ID: 1', 'User Management', '::1', '2026-11-09 09:06:13');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('6', '1', 'Toggled status for user ID: 1', 'User Management', '::1', '2026-11-09 09:06:14');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('7', '1', 'Toggled status for user ID: 3', 'User Management', '::1', '2026-11-09 09:06:18');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('8', '1', 'Toggled status for user ID: 3', 'User Management', '::1', '2026-11-09 09:06:21');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('9', '1', 'Unauthorized Access Attempt to user_management (delete)', 'Security', '::1', '2026-11-09 09:21:13');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('10', '1', 'Toggled status for user ID: 3', 'User Management', '::1', '2026-11-09 09:21:46');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('11', '1', 'Toggled status for user ID: 3', 'User Management', '::1', '2026-11-09 09:21:48');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('12', '1', 'Unauthorized Access Attempt to user_management (edit)', 'Security', '::1', '2026-11-09 09:36:52');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('13', '1', 'Unauthorized Access Attempt to user_management (edit)', 'Security', '::1', '2026-11-09 09:37:58');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('14', '1', 'Moved user ID 3 to Trash Bin', 'User Management', '::1', '2026-11-09 09:42:12');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('15', '1', 'Toggled status for user ID: 1', 'User Management', '::1', '2026-11-09 09:42:18');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('16', '1', 'Toggled status for user ID: 1', 'User Management', '::1', '2026-11-09 09:42:19');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('17', '1', 'Updated user ID: 1 (Farhan Malik)', 'User Management', '::1', '2026-11-09 09:42:33');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('18', '1', 'Moved user ID 3 to Trash Bin', 'User Management', '::1', '2026-11-09 10:19:25');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('19', '1', 'Moved user ID 3 to Trash Bin', 'User Management', '::1', '2026-11-09 11:08:55');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('20', '1', 'Created new user: Faizan', 'User Management', '::1', '2026-11-09 11:50:21');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('21', '1', 'Moved user ID 4 to Trash Bin', 'User Management', '::1', '2026-11-09 11:50:26');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('22', '1', 'Moved user ID 4 to Trash Bin', 'User Management', '::1', '2026-11-09 12:53:24');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('23', '1', 'Updated user ID: 4 (Faizan)', 'User Management', '::1', '2026-11-09 12:55:22');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('24', '1', 'Updated user ID: 3 (noman)', 'User Management', '::1', '2026-11-09 12:58:21');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('25', '1', 'Created new user: Ali', 'User Management', '::1', '2026-11-09 13:11:39');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('26', '1', 'Created new user: Farhan Malik', 'User Management', '::1', '2026-11-09 13:38:55');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('27', '1', 'Updated user ID: 3 (noman)', 'User Management', '::1', '2026-11-09 14:54:04');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('28', '1', 'Toggled status for user ID: 5', 'User Management', '::1', '2026-11-09 15:20:53');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('29', '1', 'Toggled status for user ID: 6', 'User Management', '::1', '2026-11-09 15:20:56');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('30', '1', 'Toggled status for user ID: 5', 'User Management', '::1', '2026-11-09 15:20:58');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('31', '1', 'Toggled status for user ID: 6', 'User Management', '::1', '2026-11-09 15:32:09');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('32', '1', 'Toggled status for user ID: 6', 'User Management', '::1', '2026-11-10 15:11:20');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('33', '1', 'Toggled status for user ID: 6', 'User Management', '::1', '2026-11-10 16:01:34');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('34', '1', 'Unauthorized Access Attempt to settings (edit)', 'Security', '::1', '2026-11-17 19:07:52');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('35', '1', 'Unauthorized Access Attempt to settings (edit)', 'Security', '::1', '2026-11-17 19:09:31');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('36', '3', 'Updated permissions for role ID: 2', 'Security', '::1', '2026-11-17 19:34:52');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `module`, `ip_address`, `created_at`) VALUES ('37', '3', 'Toggled status for user ID: 1', 'User Management', '::1', '2026-11-17 19:41:27');


CREATE TABLE `admins` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `role_id` int DEFAULT '3',
  `status` enum('Active','Inactive','Suspended','Trash') COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'default-avatar.png',
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `admins` (`id`, `name`, `email`, `password_hash`, `created_at`, `role_id`, `status`, `avatar`, `phone`, `address`) VALUES ('1', 'Farhan Malik', 'farhan26509029@gmail.com', '$2y$10$9fmJgDofiTjuvt1sgDWUtO8i1PkVsFCScZ6QzNkp5rzrdYrXGBY2i', '2026-11-08 12:13:00', '1', 'Active', 'default-avatar.png', '+923437745265', 'Nari Road , Khushab');
INSERT INTO `admins` (`id`, `name`, `email`, `password_hash`, `created_at`, `role_id`, `status`, `avatar`, `phone`, `address`) VALUES ('3', 'noman', 'fm3023098@gmail.com', '$2y$10$MTNqdgV3kqdTK5TQ7RoOtesUgfL4SRdGQ4easzkTA9hnli07PrBDu', '2026-11-09 09:05:42', '1', 'Active', 'default-avatar.png', '+923437745265', 'Nari Road , Khushab');
INSERT INTO `admins` (`id`, `name`, `email`, `password_hash`, `created_at`, `role_id`, `status`, `avatar`, `phone`, `address`) VALUES ('4', 'Faizan', 'hamid23@gmail.com', '$2y$10$ILLcG8wxGWrDshqIzkHL0ellEPEkiGhksrWCWpfKvPAUyztcQj432', '2026-11-09 11:50:21', '1', 'Active', '1794207021_pixel-bee-u8isJQ6QjF4-unsplash.jpg', '+923437745265', 'Nari Road , Khushab');
INSERT INTO `admins` (`id`, `name`, `email`, `password_hash`, `created_at`, `role_id`, `status`, `avatar`, `phone`, `address`) VALUES ('5', 'Ali', 'farhan265@gmail.com', '$2y$10$GQcn9D3FVEPrajMXqZhvMev0QAgwmd/PPGhKGoBCyfhav9IQ.uNiO', '2026-11-09 13:11:39', '1', 'Active', 'default-avatar.png', '+923437745265', 'Nari Road , Khushab');
INSERT INTO `admins` (`id`, `name`, `email`, `password_hash`, `created_at`, `role_id`, `status`, `avatar`, `phone`, `address`) VALUES ('6', 'Farhan Malik', 'farhan2651@gmail.com', '$2y$10$VMYplq7hwRqe12iPpKfgvuNYfraPizjd0Qv.ZRUhZHGau4o.Pav.e', '2026-11-09 13:38:55', '1', 'Active', 'default-avatar.png', '+923437745265', 'Nari Road , Khushab');


CREATE TABLE `app_module_status` (
  `module_name` varchar(80) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`module_name`),
  KEY `idx_module_status_enabled` (`is_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('categories', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('customers', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('dashboard', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('inventory', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('notifications', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('orders', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('payments', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('pos', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('products', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('profit_tracker', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('purchase_management', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('returns', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('sales_reports', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('settings', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('settings_backup', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('settings_general', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('settings_inventory', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('settings_invoices', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('settings_notifications', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('settings_payments', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('settings_pos', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('settings_roles', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('settings_security', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('settings_smtp', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('settings_store', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('settings_system', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('settings_tax', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('stock_management', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('suppliers', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('trash', '1', NULL, '2026-11-18 14:01:58');
INSERT INTO `app_module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('user_management', '1', NULL, '2026-11-18 14:01:58');


CREATE TABLE `app_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` mediumtext NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('accent_color', '#c6a15b');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('admin_email_alerts', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('alert_notification_email', 'admin@store.com');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('allow_negative_stock', '0');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('app_name', 'Ecommerce Store');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('auto_logout', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('auto_stock_deduct', '0');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('business_name', 'WebCraft Digital Enterprise');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('csrf_enabled', '0');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('currency', 'PKR');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('currency_symbol', '₨');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('date_format', 'd M Y');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('default_language', 'en');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('default_pagination', '10');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('default_stock_reason', 'Routine Inventory Audit');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('enable_pdf_download', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('enforce_strong_password', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('inv_show_biz_address', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('inv_show_cust_address', '0');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('inv_show_logo', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('inv_show_tax', '0');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('invoice_due_days', '7');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('invoice_footer', 'Computer generated invoice. Signature not required.');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('invoice_footer_note', 'Thank you for your business!');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('invoice_header_note', 'Tax Invoice / Cash Memorandum');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('invoice_paper_size', 'A4');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('invoice_prefix', 'INV-');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('invoice_show_discount', '0');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('invoice_show_tax', '0');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('invoice_start_num', '1001');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('language', 'en');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('layout_preference', 'comfortable');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('log_admin_activity', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('login_protection_enabled', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('logo', 'settings-logo-6be0906c2b48.jpg');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('low_stock_alerts', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('low_stock_threshold', '5');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('maintenance_mode', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('max_login_attempts', '5');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('min_password_length', '8');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('notif_low_stock', '0');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('notif_out_stock', '0');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('notify_low_stock', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('notify_new_customer', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('notify_new_order', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('notify_order_status', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('notify_out_of_stock', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('notify_payment_conf', '0');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('number_formatting', 'standard');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('out_of_stock_alerts', '0');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pass_require_numbers', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pass_require_special', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pass_require_uppercase', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_auto_print', '0');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_barcode', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_barcode_support', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_card_enabled', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_cash_enabled', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_cashier_display', '0');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_cust_select', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_default_discount', '0.00');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_default_payment', 'cash');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_enable_discounts', '0');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_enable_tax', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_enabled', '0');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_layout', 'grid');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_manual_price', '0');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_max_discount', '20.00');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_module_enabled', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_paper_size', '80mm');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_pay_card', '0');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_pay_cash', '0');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_pay_other', '0');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_receipt_footer', 'No returns without valid sales receipt within 7 days.');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_receipt_header', 'Thank you for shopping with us!');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_receipt_type', 'Thermal (80mm)');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_search', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_show_address', '0');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_show_customer', '0');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_show_discount', '0');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_show_logo', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_show_tax', '0');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_tax_method', 'exclusive');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('pos_tax_rate', '10.03');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('rbac_schema_version', '3');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('receipt_format', '80mm');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('reduce_stock_on_sale', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('remember_me_option', '0');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('restore_stock_on_cancel', '0');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('role_permissions', '{\"admin\":[\"dashboard\",\"customers\",\"products\",\"categories\",\"orders\",\"payments\",\"returns\",\"inventory\",\"pos\",\"reports\",\"trash\",\"settings\",\"notifications\",\"virtual-reality\",\"profit-report\"],\"manager\":[],\"cashier\":[]}');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('role_permissions_managed', '[2,3,1]');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('session_timeout', '120');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('session_timeout_minutes', '60');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('show_stock_on_frontend', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('sidebar_appearance', 'navy');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('smtp_enabled', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('smtp_encryption', 'TLS');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('smtp_host', 'smtp.mailtrap.io');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('smtp_password', '123');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('smtp_port', '587');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('smtp_sender_email', 'noreply@store.com');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('smtp_sender_name', 'Store Admin Notifications');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('smtp_username', 'farhan26509029@gmail.com');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('stock_tracking_enabled', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('store_address', 'Main Boulevard, Suite 100');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('store_alt_phone', '');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('store_brn', 'REG-987654321');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('store_city', 'Lahore');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('store_country', 'Pakistan');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('store_description', 'Official Online Store Front & Distribution Center');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('store_email', 'farhan23@gmail.com');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('store_hours', '');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('store_logo', 'store_logo_1794995536.jpg');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('store_name', 'Ecommerce Store');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('store_phone', '892 300 1234567');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('store_postal', '41000');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('store_state', 'Punjab');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('store_status', '1');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('store_tagline', 'Your Trusted Online Shopping Partner');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('store_tax_num', 'TAX-554433221');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('store_website', 'https://mystore.com');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('store_zip', '54000');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('theme', 'navy-gold');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('time_format', '24h');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('timezone', 'Asia/Karachi');
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('website_url', '');


CREATE TABLE `categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int unsigned DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `fk_cat_parent` (`parent_id`),
  CONSTRAINT `fk_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `status`, `created_at`) VALUES ('1', NULL, 'Electronics', 'electronics', 'Active', '2026-10-14 16:08:42');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `status`, `created_at`) VALUES ('2', NULL, 'Fashion', 'fashion', 'Active', '2026-10-14 16:08:42');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `status`, `created_at`) VALUES ('3', NULL, 'Home', 'home', 'Active', '2026-10-14 16:08:42');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `status`, `created_at`) VALUES ('4', '2', 'Shoes', 'shoes', 'Active', '2026-10-14 16:08:42');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `status`, `created_at`) VALUES ('5', NULL, 'Toys', 'toys', 'Active', '2026-10-14 16:08:42');


CREATE TABLE `coupons` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_type` enum('percent','fixed','free_shipping') COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_value` decimal(10,2) NOT NULL DEFAULT '0.00',
  `min_order` decimal(10,2) NOT NULL DEFAULT '0.00',
  `usage_limit` int unsigned DEFAULT NULL,
  `used_count` int unsigned NOT NULL DEFAULT '0',
  `expires_at` date DEFAULT NULL,
  `status` enum('Active','Expired','Disabled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `coupons` (`id`, `code`, `discount_type`, `discount_value`, `min_order`, `usage_limit`, `used_count`, `expires_at`, `status`, `created_at`) VALUES ('1', 'WELCOME10', 'percent', '10.00', '0.00', NULL, '84', '2026-12-31', 'Active', '2026-10-14 16:08:42');
INSERT INTO `coupons` (`id`, `code`, `discount_type`, `discount_value`, `min_order`, `usage_limit`, `used_count`, `expires_at`, `status`, `created_at`) VALUES ('2', 'EID25', 'percent', '25.00', '50.00', '500', '210', '2026-04-30', 'Expired', '2026-10-14 16:08:42');
INSERT INTO `coupons` (`id`, `code`, `discount_type`, `discount_value`, `min_order`, `usage_limit`, `used_count`, `expires_at`, `status`, `created_at`) VALUES ('3', 'FREESHIP', 'free_shipping', '0.00', '30.00', NULL, '40', '2026-11-30', 'Active', '2026-10-14 16:08:42');


CREATE TABLE `customers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'General',
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('Active','Inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `profile_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_group` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `customers` (`id`, `name`, `email`, `group_name`, `phone`, `address`, `city`, `country`, `status`, `created_at`, `profile_image`, `password`, `customer_group`, `is_deleted`) VALUES ('67', 'Farhan Malik', 'fm3023098@gmail.com', 'General', '03437745265', 'Nari Road , Khushab', 'Khushab', 'Pakistan', 'Active', '2026-11-06 15:20:34', '', '$2y$10$DsbDOk9puokQT4AdNU46febI7CBt6kTRDL0OTDvEHlgz5SHfBN9jy', 'Wholesale', '0');
INSERT INTO `customers` (`id`, `name`, `email`, `group_name`, `phone`, `address`, `city`, `country`, `status`, `created_at`, `profile_image`, `password`, `customer_group`, `is_deleted`) VALUES ('68', 'Hamid', 'developer4264@gmail.com', 'General', '03094131613', 'Joiya , Khushab', '', 'Pakistan', 'Active', '2026-11-06 15:32:13', '', '$2y$10$5g1hb/MrQabN1RXHaIfcheOJdD2NinWms19iuhim0eStKYyqMDzu2', 'VIP', '0');


CREATE TABLE `inventory` (
  `product_id` int unsigned NOT NULL,
  `quantity` int NOT NULL DEFAULT '0',
  `reorder_level` int NOT NULL DEFAULT '10',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`),
  CONSTRAINT `fk_inv_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_chk_1` CHECK ((`quantity` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `inventory` (`product_id`, `quantity`, `reorder_level`, `updated_at`) VALUES ('88', '10', '10', '2026-11-06 16:14:44');
INSERT INTO `inventory` (`product_id`, `quantity`, `reorder_level`, `updated_at`) VALUES ('89', '10', '10', '2026-11-06 16:14:44');
INSERT INTO `inventory` (`product_id`, `quantity`, `reorder_level`, `updated_at`) VALUES ('91', '10', '10', '2026-11-06 16:14:44');
INSERT INTO `inventory` (`product_id`, `quantity`, `reorder_level`, `updated_at`) VALUES ('92', '10', '10', '2026-11-17 13:59:43');


CREATE TABLE `inventory_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,
  `change_qty` int NOT NULL,
  `reason` enum('Purchase','Sale','Return','Adjustment') COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mov_prod` (`product_id`),
  CONSTRAINT `fk_mov_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `module_status` (
  `module_name` varchar(100) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`module_name`),
  KEY `idx_module_status_enabled` (`is_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('categories', '1', NULL, '2026-11-18 15:18:14');
INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('customers', '1', NULL, '2026-11-18 15:18:14');
INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('dashboard', '1', NULL, '2026-11-18 15:18:14');
INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('inventory', '1', NULL, '2026-11-18 15:18:14');
INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('notifications', '1', NULL, '2026-11-18 15:18:14');
INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('orders', '1', NULL, '2026-11-18 15:18:14');
INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('payments', '1', NULL, '2026-11-18 15:18:14');
INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('pos', '1', NULL, '2026-11-18 15:18:14');
INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('products', '1', NULL, '2026-11-18 15:18:14');
INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('profit_tracker', '1', NULL, '2026-11-18 15:18:14');
INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('purchase_management', '1', NULL, '2026-11-18 15:18:14');
INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('returns', '1', NULL, '2026-11-18 15:18:14');
INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('sales_reports', '1', NULL, '2026-11-18 15:18:14');
INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('settings', '1', NULL, '2026-11-18 15:18:14');
INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('settings_backup', '1', NULL, '2026-11-18 15:18:14');
INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('settings_general', '1', NULL, '2026-11-18 15:18:14');
INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('settings_inventory', '1', NULL, '2026-11-18 15:18:14');
INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('settings_invoices', '1', NULL, '2026-11-18 15:18:14');
INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('settings_notifications', '1', NULL, '2026-11-18 15:18:14');
INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('settings_payments', '1', NULL, '2026-11-18 15:18:14');
INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('settings_pos', '1', NULL, '2026-11-18 15:18:14');
INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('settings_security', '1', NULL, '2026-11-18 15:18:14');
INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('settings_smtp', '1', NULL, '2026-11-18 15:18:14');
INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('settings_store', '1', NULL, '2026-11-18 15:18:14');
INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('settings_system', '1', NULL, '2026-11-18 15:18:14');
INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('settings_tax', '1', NULL, '2026-11-18 15:18:14');
INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('stock_management', '1', NULL, '2026-11-18 15:18:14');
INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('suppliers', '1', NULL, '2026-11-18 15:18:14');
INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('trash', '1', NULL, '2026-11-18 15:18:14');
INSERT INTO `module_status` (`module_name`, `is_enabled`, `updated_by`, `updated_at`) VALUES ('user_management', '1', NULL, '2026-11-18 15:18:14');


CREATE TABLE `notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('order','stock','customer','return') COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_unread_created` (`is_read`,`created_at`),
  KEY `idx_notifications_type_created` (`type`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `notifications` (`id`, `title`, `message`, `type`, `is_read`, `is_deleted`, `created_at`) VALUES ('11', 'New customer registered', 'Farhan Malik has registered as a customer.', 'customer', '1', '1', '2026-11-06 15:20:34');
INSERT INTO `notifications` (`id`, `title`, `message`, `type`, `is_read`, `is_deleted`, `created_at`) VALUES ('12', 'New order received', 'Order ORD-CBEE79 has been created.', 'order', '1', '1', '2026-11-06 15:30:04');
INSERT INTO `notifications` (`id`, `title`, `message`, `type`, `is_read`, `is_deleted`, `created_at`) VALUES ('13', 'New customer registered', 'Hamid has registered as a customer.', 'customer', '1', '1', '2026-11-06 15:32:13');
INSERT INTO `notifications` (`id`, `title`, `message`, `type`, `is_read`, `is_deleted`, `created_at`) VALUES ('14', 'New product added', 'Helment has been added to the product catalog.', 'stock', '1', '1', '2026-11-06 15:38:26');
INSERT INTO `notifications` (`id`, `title`, `message`, `type`, `is_read`, `is_deleted`, `created_at`) VALUES ('15', 'New product added', '4u papar has been added to the product catalog.', 'stock', '1', '1', '2026-11-06 16:05:39');
INSERT INTO `notifications` (`id`, `title`, `message`, `type`, `is_read`, `is_deleted`, `created_at`) VALUES ('16', 'New order received', 'Order ORD-EAA48C was completed through POS.', 'order', '1', '1', '2026-11-07 09:41:34');
INSERT INTO `notifications` (`id`, `title`, `message`, `type`, `is_read`, `is_deleted`, `created_at`) VALUES ('17', 'New order received', 'Order ORD-DA0C9F was completed through POS.', 'order', '1', '1', '2026-11-07 09:59:25');
INSERT INTO `notifications` (`id`, `title`, `message`, `type`, `is_read`, `is_deleted`, `created_at`) VALUES ('18', 'New order received (Online Transfer)', 'Order ORD-0B1A3F was completed through Online Transfer.', 'order', '1', '1', '2026-11-07 10:05:36');
INSERT INTO `notifications` (`id`, `title`, `message`, `type`, `is_read`, `is_deleted`, `created_at`) VALUES ('19', 'New order received (Cash)', 'Order ORD-62B7E9 was completed through Cash.', 'order', '1', '1', '2026-11-07 10:06:46');
INSERT INTO `notifications` (`id`, `title`, `message`, `type`, `is_read`, `is_deleted`, `created_at`) VALUES ('20', 'New order received (Online Transfer)', 'Order ORD-1831BB was completed through Online Transfer.', 'order', '1', '1', '2026-11-08 09:25:21');
INSERT INTO `notifications` (`id`, `title`, `message`, `type`, `is_read`, `is_deleted`, `created_at`) VALUES ('21', 'New order received (Online Transfer)', 'Order ORD-41BACD was completed through Online Transfer.', 'order', '1', '1', '2026-11-11 14:32:36');
INSERT INTO `notifications` (`id`, `title`, `message`, `type`, `is_read`, `is_deleted`, `created_at`) VALUES ('22', 'New order received (Cash)', 'Order ORD-1315CD was completed through Cash.', 'order', '1', '1', '2026-11-13 10:18:57');
INSERT INTO `notifications` (`id`, `title`, `message`, `type`, `is_read`, `is_deleted`, `created_at`) VALUES ('23', 'New order received (Cash)', 'Order ORD-F203FF was completed through Cash.', 'order', '1', '1', '2026-11-14 10:49:03');
INSERT INTO `notifications` (`id`, `title`, `message`, `type`, `is_read`, `is_deleted`, `created_at`) VALUES ('24', 'New product added', 'Ac has been added to the product catalog.', 'stock', '1', '1', '2026-11-14 10:56:08');
INSERT INTO `notifications` (`id`, `title`, `message`, `type`, `is_read`, `is_deleted`, `created_at`) VALUES ('25', 'New order received (Cash)', 'Order ORD-750824 was completed through Cash.', 'order', '1', '1', '2026-11-14 10:56:39');
INSERT INTO `notifications` (`id`, `title`, `message`, `type`, `is_read`, `is_deleted`, `created_at`) VALUES ('26', 'New order received (Cash)', 'POS order 79 was completed through Cash.', 'order', '1', '0', '2026-11-18 09:54:29');
INSERT INTO `notifications` (`id`, `title`, `message`, `type`, `is_read`, `is_deleted`, `created_at`) VALUES ('27', 'New order received (Cash)', 'POS order 80 was completed through Cash.', 'order', '1', '0', '2026-11-18 13:57:44');


CREATE TABLE `order_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `product_id` int unsigned DEFAULT NULL,
  `product_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `quantity` int unsigned NOT NULL DEFAULT '1',
  `price` decimal(10,2) NOT NULL,
  `line_total` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_item_order` (`order_id`),
  KEY `fk_item_prod` (`product_id`),
  CONSTRAINT `fk_item_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_item_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `price`, `line_total`) VALUES ('36', '70', NULL, 'Helment', '1000.00', '1', '1000.00', '1000.00');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `price`, `line_total`) VALUES ('37', '70', '89', 'Watch', '1000.00', '1', '1000.00', '1000.00');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `price`, `line_total`) VALUES ('38', '71', NULL, 'Helment', '1000.00', '1', '1000.00', '1000.00');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `price`, `line_total`) VALUES ('39', '71', '91', '4u papar', '50.00', '1', '50.00', '50.00');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `price`, `line_total`) VALUES ('40', '71', '89', 'Watch', '1000.00', '1', '1000.00', '1000.00');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `price`, `line_total`) VALUES ('41', '72', '91', '4u papar', '50.00', '1', '50.00', '50.00');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `price`, `line_total`) VALUES ('42', '72', NULL, 'Helment', '1000.00', '1', '1000.00', '1000.00');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `price`, `line_total`) VALUES ('43', '72', '89', 'Watch', '1000.00', '1', '1000.00', '1000.00');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `price`, `line_total`) VALUES ('44', '73', NULL, 'Helment', '1000.00', '2', '1000.00', '2000.00');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `price`, `line_total`) VALUES ('45', '74', '89', 'Watch', '1000.00', '2', '1000.00', '2000.00');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `price`, `line_total`) VALUES ('46', '75', '89', 'Watch', '1000.00', '2', '1000.00', '2000.00');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `price`, `line_total`) VALUES ('47', '75', '88', 'Shoes', '1000.00', '1', '1000.00', '1000.00');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `price`, `line_total`) VALUES ('48', '76', '89', 'Watch', '1000.00', '1', '1000.00', '1000.00');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `price`, `line_total`) VALUES ('49', '77', '89', 'Watch', '1000.00', '1', '1000.00', '1000.00');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `price`, `line_total`) VALUES ('50', '78', '92', 'Ac', '100000.00', '2', '100000.00', '200000.00');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `price`, `line_total`) VALUES ('51', '79', '89', 'Watch', '1000.00', '1', '1000.00', '1000.00');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `price`, `line_total`) VALUES ('52', '79', '91', '4u papar', '50.00', '1', '50.00', '50.00');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `price`, `line_total`) VALUES ('53', '79', '92', 'Ac', '100000.00', '1', '100000.00', '100000.00');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `price`, `line_total`) VALUES ('54', '80', '89', 'Watch', '1000.00', '1', '1000.00', '1000.00');


CREATE TABLE `orders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` int unsigned DEFAULT NULL,
  `coupon_id` int unsigned DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `shipping_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `shipping_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('Pending','Processing','Shipped','Delivered','Cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `payment_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Cash',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_no` (`order_no`),
  KEY `fk_ord_cust` (`customer_id`),
  KEY `fk_ord_coupon` (`coupon_id`),
  KEY `idx_ord_status` (`status`),
  KEY `idx_ord_date` (`created_at`),
  CONSTRAINT `fk_ord_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ord_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `orders` (`id`, `order_no`, `customer_id`, `coupon_id`, `subtotal`, `discount`, `shipping_fee`, `total`, `shipping_address`, `status`, `created_at`, `payment_method`, `is_deleted`) VALUES ('69', 'ORD-CBEE79', '67', NULL, '1000.00', '10.00', '2.00', '992.00', 'jsuxuw', 'Delivered', '2026-11-06 15:30:04', 'Cash', '0');
INSERT INTO `orders` (`id`, `order_no`, `customer_id`, `coupon_id`, `subtotal`, `discount`, `shipping_fee`, `total`, `shipping_address`, `status`, `created_at`, `payment_method`, `is_deleted`) VALUES ('70', 'ORD-EAA48C', NULL, NULL, '2000.00', '0.00', '0.00', '2200.60', 'Walk-in Counter POS', 'Delivered', '2026-11-07 09:41:34', 'Cash', '0');
INSERT INTO `orders` (`id`, `order_no`, `customer_id`, `coupon_id`, `subtotal`, `discount`, `shipping_fee`, `total`, `shipping_address`, `status`, `created_at`, `payment_method`, `is_deleted`) VALUES ('71', 'ORD-DA0C9F', NULL, NULL, '2050.00', '0.00', '0.00', '2255.62', 'Walk-in Counter POS (Online Paid)', 'Delivered', '2026-11-07 09:59:25', 'Online Transfer', '0');
INSERT INTO `orders` (`id`, `order_no`, `customer_id`, `coupon_id`, `subtotal`, `discount`, `shipping_fee`, `total`, `shipping_address`, `status`, `created_at`, `payment_method`, `is_deleted`) VALUES ('72', 'ORD-0B1A3F', NULL, NULL, '2050.00', '0.00', '0.00', '2255.62', 'Walk-in Counter POS (Online Paid)', 'Delivered', '2026-11-07 10:05:36', 'Online Transfer', '0');
INSERT INTO `orders` (`id`, `order_no`, `customer_id`, `coupon_id`, `subtotal`, `discount`, `shipping_fee`, `total`, `shipping_address`, `status`, `created_at`, `payment_method`, `is_deleted`) VALUES ('73', 'ORD-62B7E9', NULL, NULL, '2000.00', '0.00', '0.00', '2200.60', 'Walk-in Counter POS', 'Delivered', '2026-11-07 10:06:46', 'Cash', '0');
INSERT INTO `orders` (`id`, `order_no`, `customer_id`, `coupon_id`, `subtotal`, `discount`, `shipping_fee`, `total`, `shipping_address`, `status`, `created_at`, `payment_method`, `is_deleted`) VALUES ('74', 'ORD-1831BB', NULL, NULL, '2000.00', '0.00', '0.00', '2200.60', 'Walk-in Counter POS (Online Paid)', 'Delivered', '2026-11-08 09:25:21', 'Online Transfer', '0');
INSERT INTO `orders` (`id`, `order_no`, `customer_id`, `coupon_id`, `subtotal`, `discount`, `shipping_fee`, `total`, `shipping_address`, `status`, `created_at`, `payment_method`, `is_deleted`) VALUES ('75', 'ORD-41BACD', NULL, NULL, '3000.00', '0.00', '0.00', '3300.90', 'Walk-in Counter POS (Online Paid)', 'Delivered', '2026-11-11 14:32:36', 'Online Transfer', '0');
INSERT INTO `orders` (`id`, `order_no`, `customer_id`, `coupon_id`, `subtotal`, `discount`, `shipping_fee`, `total`, `shipping_address`, `status`, `created_at`, `payment_method`, `is_deleted`) VALUES ('76', 'ORD-1315CD', NULL, NULL, '1000.00', '0.00', '0.00', '1100.30', 'Walk-in Counter POS', 'Delivered', '2026-11-13 10:18:57', 'Cash', '0');
INSERT INTO `orders` (`id`, `order_no`, `customer_id`, `coupon_id`, `subtotal`, `discount`, `shipping_fee`, `total`, `shipping_address`, `status`, `created_at`, `payment_method`, `is_deleted`) VALUES ('77', 'ORD-F203FF', NULL, NULL, '1000.00', '0.00', '0.00', '1100.30', 'Walk-in Counter POS', 'Delivered', '2026-11-14 10:49:03', 'Cash', '0');
INSERT INTO `orders` (`id`, `order_no`, `customer_id`, `coupon_id`, `subtotal`, `discount`, `shipping_fee`, `total`, `shipping_address`, `status`, `created_at`, `payment_method`, `is_deleted`) VALUES ('78', 'ORD-750824', NULL, NULL, '200000.00', '0.00', '0.00', '220060.00', 'Walk-in Counter POS', 'Delivered', '2026-11-14 10:56:39', 'Cash', '0');
INSERT INTO `orders` (`id`, `order_no`, `customer_id`, `coupon_id`, `subtotal`, `discount`, `shipping_fee`, `total`, `shipping_address`, `status`, `created_at`, `payment_method`, `is_deleted`) VALUES ('79', 'ORD-5972EBC6CA', NULL, NULL, '101050.00', '0.00', '0.00', '111185.32', 'Walk-in Counter POS', 'Delivered', '2026-11-18 09:54:29', 'Cash', '0');
INSERT INTO `orders` (`id`, `order_no`, `customer_id`, `coupon_id`, `subtotal`, `discount`, `shipping_fee`, `total`, `shipping_address`, `status`, `created_at`, `payment_method`, `is_deleted`) VALUES ('80', 'ORD-976AF8AE7B', NULL, NULL, '1000.00', '0.00', '0.00', '1100.30', 'Walk-in Counter POS', 'Delivered', '2026-11-18 13:57:43', 'Cash', '0');


CREATE TABLE `payments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `txn_no` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` int unsigned NOT NULL,
  `method` enum('Card','Cash on delivery','Wallet','Bank transfer') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('Paid','Pending','Failed','Refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `txn_no` (`txn_no`),
  KEY `fk_pay_order` (`order_id`),
  KEY `idx_pay_status` (`status`),
  CONSTRAINT `fk_pay_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=374 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('1', 'Create Users', 'user_management', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('2', 'Create User', 'user_management', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('3', 'View Users', 'user_management', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('4', 'Create User', 'user_management', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('5', 'Edit User', 'user_management', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('6', 'View Dashboard', 'dashboard', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('7', 'View Customers', 'customers', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('8', 'Create Customers', 'customers', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('9', 'Edit Customers', 'customers', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('10', 'Delete Customers', 'customers', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('11', 'View Products', 'products', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('12', 'Create Products', 'products', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('13', 'Edit Products', 'products', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('14', 'Delete Products', 'products', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('15', 'View Categories', 'categories', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('16', 'Create Categories', 'categories', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('17', 'Edit Categories', 'categories', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('18', 'Delete Categories', 'categories', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('19', 'View Orders', 'orders', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('20', 'Create Orders', 'orders', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('21', 'Edit Orders', 'orders', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('22', 'Delete Orders', 'orders', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('23', 'View Payments', 'payments', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('24', 'Edit Payments', 'payments', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('25', 'View Returns', 'returns', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('26', 'Create Returns', 'returns', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('27', 'Edit Returns', 'returns', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('28', 'Delete Returns', 'returns', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('29', 'View Inventory', 'inventory', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('30', 'Create Inventory', 'inventory', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('31', 'Edit Inventory', 'inventory', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('32', 'Delete Inventory', 'inventory', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('33', 'View POS', 'pos', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('34', 'Create POS', 'pos', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('35', 'View Reports', 'reports', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('36', 'View Notifications', 'notifications', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('37', 'Edit Notifications', 'notifications', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('38', 'Delete Notifications', 'notifications', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('39', 'Delete User Management', 'user_management', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('40', 'View Settings', 'settings', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('41', 'Edit Settings', 'settings', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('42', 'View Trash', 'trash', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('43', 'View Profit Tracker', 'profit-report', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('44', 'View Dashboard', 'dashboard', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('45', 'Create Dashboard', 'dashboard', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('46', 'Edit Dashboard', 'dashboard', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('47', 'Delete Dashboard', 'dashboard', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('48', 'Export Dashboard', 'dashboard', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('49', 'Manage Dashboard', 'dashboard', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('50', 'View Customers', 'customers', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('51', 'Create Customers', 'customers', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('52', 'Edit Customers', 'customers', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('53', 'Delete Customers', 'customers', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('54', 'Export Customers', 'customers', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('55', 'Manage Customers', 'customers', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('56', 'View Products', 'products', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('57', 'Create Products', 'products', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('58', 'Edit Products', 'products', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('59', 'Delete Products', 'products', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('60', 'Export Products', 'products', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('61', 'Manage Products', 'products', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('62', 'View Categories', 'categories', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('63', 'Create Categories', 'categories', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('64', 'Edit Categories', 'categories', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('65', 'Delete Categories', 'categories', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('66', 'Export Categories', 'categories', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('67', 'Manage Categories', 'categories', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('68', 'View Orders', 'orders', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('69', 'Create Orders', 'orders', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('70', 'Edit Orders', 'orders', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('71', 'Delete Orders', 'orders', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('72', 'Export Orders', 'orders', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('73', 'Manage Orders', 'orders', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('74', 'View Payments', 'payments', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('75', 'Create Payments', 'payments', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('76', 'Edit Payments', 'payments', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('77', 'Delete Payments', 'payments', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('78', 'Export Payments', 'payments', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('79', 'Manage Payments', 'payments', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('80', 'View Returns', 'returns', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('81', 'Create Returns', 'returns', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('82', 'Edit Returns', 'returns', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('83', 'Delete Returns', 'returns', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('84', 'Export Returns', 'returns', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('85', 'Manage Returns', 'returns', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('86', 'View Inventory', 'inventory', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('87', 'Create Inventory', 'inventory', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('88', 'Edit Inventory', 'inventory', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('89', 'Delete Inventory', 'inventory', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('90', 'Export Inventory', 'inventory', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('91', 'Manage Inventory', 'inventory', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('92', 'View POS', 'pos', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('93', 'Create POS', 'pos', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('94', 'Edit POS', 'pos', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('95', 'Delete POS', 'pos', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('96', 'Export POS', 'pos', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('97', 'Manage POS', 'pos', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('98', 'View Suppliers', 'suppliers', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('99', 'Create Suppliers', 'suppliers', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('100', 'Edit Suppliers', 'suppliers', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('101', 'Delete Suppliers', 'suppliers', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('102', 'Export Suppliers', 'suppliers', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('103', 'Manage Suppliers', 'suppliers', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('104', 'View Purchase Management', 'purchase_management', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('105', 'Create Purchase Management', 'purchase_management', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('106', 'Edit Purchase Management', 'purchase_management', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('107', 'Delete Purchase Management', 'purchase_management', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('108', 'Export Purchase Management', 'purchase_management', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('109', 'Manage Purchase Management', 'purchase_management', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('110', 'View Stock Management', 'stock_management', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('111', 'Create Stock Management', 'stock_management', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('112', 'Edit Stock Management', 'stock_management', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('113', 'Delete Stock Management', 'stock_management', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('114', 'Export Stock Management', 'stock_management', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('115', 'Manage Stock Management', 'stock_management', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('116', 'View Notifications', 'notifications', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('117', 'Create Notifications', 'notifications', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('118', 'Edit Notifications', 'notifications', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('119', 'Delete Notifications', 'notifications', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('120', 'Export Notifications', 'notifications', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('121', 'Manage Notifications', 'notifications', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('122', 'View Sales Reports', 'sales_reports', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('123', 'Create Sales Reports', 'sales_reports', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('124', 'Edit Sales Reports', 'sales_reports', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('125', 'Delete Sales Reports', 'sales_reports', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('126', 'Export Sales Reports', 'sales_reports', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('127', 'Manage Sales Reports', 'sales_reports', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('128', 'View Profit Tracker', 'profit_tracker', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('129', 'Create Profit Tracker', 'profit_tracker', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('130', 'Edit Profit Tracker', 'profit_tracker', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('131', 'Delete Profit Tracker', 'profit_tracker', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('132', 'Export Profit Tracker', 'profit_tracker', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('133', 'Manage Profit Tracker', 'profit_tracker', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('134', 'View User Management', 'user_management', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('135', 'Create User Management', 'user_management', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('136', 'Edit User Management', 'user_management', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('137', 'Delete User Management', 'user_management', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('138', 'Export User Management', 'user_management', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('139', 'Manage User Management', 'user_management', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('140', 'View Trash Bin', 'trash', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('141', 'Create Trash Bin', 'trash', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('142', 'Edit Trash Bin', 'trash', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('143', 'Delete Trash Bin', 'trash', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('144', 'Export Trash Bin', 'trash', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('145', 'Manage Trash Bin', 'trash', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('146', 'View Settings', 'settings', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('147', 'Create Settings', 'settings', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('148', 'Edit Settings', 'settings', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('149', 'Delete Settings', 'settings', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('150', 'Export Settings', 'settings', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('151', 'Manage Settings', 'settings', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('152', 'View Settings: General', 'settings_general', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('153', 'Edit Settings: General', 'settings_general', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('154', 'Manage Settings: General', 'settings_general', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('155', 'View Settings: Store', 'settings_store', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('156', 'Edit Settings: Store', 'settings_store', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('157', 'Manage Settings: Store', 'settings_store', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('158', 'View Settings: POS', 'settings_pos', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('159', 'Edit Settings: POS', 'settings_pos', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('160', 'Manage Settings: POS', 'settings_pos', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('161', 'View Settings: Security', 'settings_security', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('162', 'Edit Settings: Security', 'settings_security', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('163', 'Manage Settings: Security', 'settings_security', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('164', 'View Settings: SMTP', 'settings_smtp', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('165', 'Edit Settings: SMTP', 'settings_smtp', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('166', 'Manage Settings: SMTP', 'settings_smtp', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('167', 'View Settings: Invoices', 'settings_invoices', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('168', 'Edit Settings: Invoices', 'settings_invoices', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('169', 'Manage Settings: Invoices', 'settings_invoices', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('170', 'View Settings: Inventory', 'settings_inventory', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('171', 'Edit Settings: Inventory', 'settings_inventory', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('172', 'Manage Settings: Inventory', 'settings_inventory', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('173', 'View Settings: Backup', 'settings_backup', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('174', 'Edit Settings: Backup', 'settings_backup', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('175', 'Manage Settings: Backup', 'settings_backup', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('176', 'View Settings: Payments', 'settings_payments', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('177', 'Edit Settings: Payments', 'settings_payments', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('178', 'Manage Settings: Payments', 'settings_payments', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('179', 'View Settings: Tax', 'settings_tax', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('180', 'Edit Settings: Tax', 'settings_tax', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('181', 'Manage Settings: Tax', 'settings_tax', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('182', 'View Settings: Notifications', 'settings_notifications', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('183', 'Edit Settings: Notifications', 'settings_notifications', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('184', 'Manage Settings: Notifications', 'settings_notifications', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('185', 'View Settings: System & Maintenance', 'settings_system', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('186', 'Edit Settings: System & Maintenance', 'settings_system', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('187', 'Manage Settings: System & Maintenance', 'settings_system', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('188', 'View Settings: Roles & Permissions', 'settings_roles', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('189', 'Edit Settings: Roles & Permissions', 'settings_roles', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('190', 'Manage Settings: Roles & Permissions', 'settings_roles', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('191', 'View Dashboard', 'dashboard', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('192', 'Create Dashboard', 'dashboard', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('193', 'Edit Dashboard', 'dashboard', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('194', 'Delete Dashboard', 'dashboard', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('195', 'Export Dashboard', 'dashboard', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('196', 'Manage Dashboard', 'dashboard', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('197', 'View Customers', 'customers', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('198', 'Create Customers', 'customers', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('199', 'Edit Customers', 'customers', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('200', 'Delete Customers', 'customers', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('201', 'Export Customers', 'customers', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('202', 'Manage Customers', 'customers', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('203', 'View Products', 'products', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('204', 'Create Products', 'products', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('205', 'Edit Products', 'products', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('206', 'Delete Products', 'products', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('207', 'Export Products', 'products', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('208', 'Manage Products', 'products', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('209', 'View Categories', 'categories', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('210', 'Create Categories', 'categories', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('211', 'Edit Categories', 'categories', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('212', 'Delete Categories', 'categories', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('213', 'Export Categories', 'categories', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('214', 'Manage Categories', 'categories', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('215', 'View Orders', 'orders', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('216', 'Create Orders', 'orders', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('217', 'Edit Orders', 'orders', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('218', 'Delete Orders', 'orders', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('219', 'Export Orders', 'orders', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('220', 'Manage Orders', 'orders', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('221', 'View Payments', 'payments', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('222', 'Create Payments', 'payments', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('223', 'Edit Payments', 'payments', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('224', 'Delete Payments', 'payments', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('225', 'Export Payments', 'payments', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('226', 'Manage Payments', 'payments', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('227', 'View Returns', 'returns', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('228', 'Create Returns', 'returns', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('229', 'Edit Returns', 'returns', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('230', 'Delete Returns', 'returns', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('231', 'Export Returns', 'returns', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('232', 'Manage Returns', 'returns', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('233', 'View Inventory', 'inventory', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('234', 'Create Inventory', 'inventory', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('235', 'Edit Inventory', 'inventory', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('236', 'Delete Inventory', 'inventory', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('237', 'Export Inventory', 'inventory', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('238', 'Manage Inventory', 'inventory', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('239', 'View POS', 'pos', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('240', 'Create POS', 'pos', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('241', 'Edit POS', 'pos', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('242', 'Delete POS', 'pos', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('243', 'Export POS', 'pos', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('244', 'Manage POS', 'pos', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('245', 'View Suppliers', 'suppliers', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('246', 'Create Suppliers', 'suppliers', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('247', 'Edit Suppliers', 'suppliers', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('248', 'Delete Suppliers', 'suppliers', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('249', 'Export Suppliers', 'suppliers', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('250', 'Manage Suppliers', 'suppliers', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('251', 'View Purchase Management', 'purchase_management', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('252', 'Create Purchase Management', 'purchase_management', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('253', 'Edit Purchase Management', 'purchase_management', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('254', 'Delete Purchase Management', 'purchase_management', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('255', 'Export Purchase Management', 'purchase_management', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('256', 'Manage Purchase Management', 'purchase_management', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('257', 'View Stock Management', 'stock_management', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('258', 'Create Stock Management', 'stock_management', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('259', 'Edit Stock Management', 'stock_management', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('260', 'Delete Stock Management', 'stock_management', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('261', 'Export Stock Management', 'stock_management', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('262', 'Manage Stock Management', 'stock_management', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('263', 'View Notifications', 'notifications', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('264', 'Create Notifications', 'notifications', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('265', 'Edit Notifications', 'notifications', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('266', 'Delete Notifications', 'notifications', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('267', 'Export Notifications', 'notifications', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('268', 'Manage Notifications', 'notifications', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('269', 'View Sales Reports', 'sales_reports', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('270', 'Create Sales Reports', 'sales_reports', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('271', 'Edit Sales Reports', 'sales_reports', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('272', 'Delete Sales Reports', 'sales_reports', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('273', 'Export Sales Reports', 'sales_reports', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('274', 'Manage Sales Reports', 'sales_reports', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('275', 'View Profit Tracker', 'profit_tracker', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('276', 'Create Profit Tracker', 'profit_tracker', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('277', 'Edit Profit Tracker', 'profit_tracker', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('278', 'Delete Profit Tracker', 'profit_tracker', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('279', 'Export Profit Tracker', 'profit_tracker', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('280', 'Manage Profit Tracker', 'profit_tracker', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('281', 'View User Management', 'user_management', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('282', 'Create User Management', 'user_management', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('283', 'Edit User Management', 'user_management', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('284', 'Delete User Management', 'user_management', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('285', 'Export User Management', 'user_management', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('286', 'Manage User Management', 'user_management', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('287', 'View Trash Bin', 'trash', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('288', 'Create Trash Bin', 'trash', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('289', 'Edit Trash Bin', 'trash', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('290', 'Delete Trash Bin', 'trash', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('291', 'Export Trash Bin', 'trash', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('292', 'Manage Trash Bin', 'trash', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('293', 'View Settings', 'settings', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('294', 'Create Settings', 'settings', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('295', 'Edit Settings', 'settings', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('296', 'Delete Settings', 'settings', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('297', 'Export Settings', 'settings', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('298', 'Manage Settings', 'settings', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('299', 'View Settings: General', 'settings_general', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('300', 'Edit Settings: General', 'settings_general', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('301', 'Manage Settings: General', 'settings_general', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('302', 'View Settings: Store', 'settings_store', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('303', 'Edit Settings: Store', 'settings_store', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('304', 'Manage Settings: Store', 'settings_store', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('305', 'View Settings: POS', 'settings_pos', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('306', 'Edit Settings: POS', 'settings_pos', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('307', 'Manage Settings: POS', 'settings_pos', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('308', 'View Settings: Security', 'settings_security', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('309', 'Edit Settings: Security', 'settings_security', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('310', 'Manage Settings: Security', 'settings_security', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('311', 'View Settings: SMTP', 'settings_smtp', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('312', 'Edit Settings: SMTP', 'settings_smtp', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('313', 'Manage Settings: SMTP', 'settings_smtp', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('314', 'View Settings: Invoices', 'settings_invoices', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('315', 'Edit Settings: Invoices', 'settings_invoices', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('316', 'Manage Settings: Invoices', 'settings_invoices', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('317', 'View Settings: Inventory', 'settings_inventory', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('318', 'Edit Settings: Inventory', 'settings_inventory', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('319', 'Manage Settings: Inventory', 'settings_inventory', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('320', 'View Settings: Backup', 'settings_backup', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('321', 'Edit Settings: Backup', 'settings_backup', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('322', 'Manage Settings: Backup', 'settings_backup', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('323', 'View Settings: Payments', 'settings_payments', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('324', 'Edit Settings: Payments', 'settings_payments', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('325', 'Manage Settings: Payments', 'settings_payments', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('326', 'View Settings: Tax', 'settings_tax', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('327', 'Edit Settings: Tax', 'settings_tax', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('328', 'Manage Settings: Tax', 'settings_tax', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('329', 'View Settings: Notifications', 'settings_notifications', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('330', 'Edit Settings: Notifications', 'settings_notifications', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('331', 'Manage Settings: Notifications', 'settings_notifications', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('332', 'View Settings: System & Maintenance', 'settings_system', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('333', 'Edit Settings: System & Maintenance', 'settings_system', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('334', 'Manage Settings: System & Maintenance', 'settings_system', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('335', 'View Settings: Roles & Permissions', 'settings_roles', 'view');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('336', 'Edit Settings: Roles & Permissions', 'settings_roles', 'edit');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('337', 'Manage Settings: Roles & Permissions', 'settings_roles', 'manage');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('338', 'Create Settings: General', 'settings_general', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('339', 'Delete Settings: General', 'settings_general', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('340', 'Export Settings: General', 'settings_general', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('341', 'Create Settings: Store', 'settings_store', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('342', 'Delete Settings: Store', 'settings_store', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('343', 'Export Settings: Store', 'settings_store', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('344', 'Create Settings: POS', 'settings_pos', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('345', 'Delete Settings: POS', 'settings_pos', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('346', 'Export Settings: POS', 'settings_pos', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('347', 'Create Settings: Security', 'settings_security', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('348', 'Delete Settings: Security', 'settings_security', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('349', 'Export Settings: Security', 'settings_security', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('350', 'Create Settings: SMTP', 'settings_smtp', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('351', 'Delete Settings: SMTP', 'settings_smtp', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('352', 'Export Settings: SMTP', 'settings_smtp', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('353', 'Create Settings: Invoices', 'settings_invoices', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('354', 'Delete Settings: Invoices', 'settings_invoices', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('355', 'Export Settings: Invoices', 'settings_invoices', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('356', 'Create Settings: Inventory', 'settings_inventory', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('357', 'Delete Settings: Inventory', 'settings_inventory', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('358', 'Export Settings: Inventory', 'settings_inventory', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('359', 'Create Settings: Backup', 'settings_backup', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('360', 'Delete Settings: Backup', 'settings_backup', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('361', 'Export Settings: Backup', 'settings_backup', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('362', 'Create Settings: Payments', 'settings_payments', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('363', 'Delete Settings: Payments', 'settings_payments', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('364', 'Export Settings: Payments', 'settings_payments', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('365', 'Create Settings: Tax', 'settings_tax', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('366', 'Delete Settings: Tax', 'settings_tax', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('367', 'Export Settings: Tax', 'settings_tax', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('368', 'Create Settings: Notifications', 'settings_notifications', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('369', 'Delete Settings: Notifications', 'settings_notifications', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('370', 'Export Settings: Notifications', 'settings_notifications', 'export');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('371', 'Create Settings: System & Maintenance', 'settings_system', 'create');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('372', 'Delete Settings: System & Maintenance', 'settings_system', 'delete');
INSERT INTO `permissions` (`id`, `name`, `module_name`, `action_name`) VALUES ('373', 'Export Settings: System & Maintenance', 'settings_system', 'export');


CREATE TABLE `products` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int unsigned DEFAULT NULL,
  `sku` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(10,2) NOT NULL,
  `original_price` decimal(10,2) DEFAULT '0.00',
  `discount_percent` int DEFAULT '0',
  `sale_price` decimal(10,2) DEFAULT '0.00',
  `stock` int DEFAULT '0',
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'default-avatar.png',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `rating` decimal(2,1) DEFAULT '0.0',
  `likes_count` int DEFAULT '0',
  `cost_price` decimal(10,2) DEFAULT '0.00',
  `is_deleted` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`),
  KEY `fk_prod_cat` (`category_id`),
  KEY `idx_prod_status` (`status`),
  CONSTRAINT `fk_prod_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_chk_1` CHECK ((`price` >= 0))
) ENGINE=InnoDB AUTO_INCREMENT=93 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `products` (`id`, `category_id`, `sku`, `name`, `description`, `price`, `original_price`, `discount_percent`, `sale_price`, `stock`, `image`, `status`, `created_at`, `updated_at`, `rating`, `likes_count`, `cost_price`, `is_deleted`) VALUES ('88', '4', 'SKU-001', 'Shoes', '', '1000.00', '0.00', '0', '0.00', '199', 'https://cdn-icons-png.flaticon.com/512/1165/1165687.png', 'Active', '2026-11-06 15:03:01', '2026-11-11 14:32:36', '0.0', '0', '0.00', '0');
INSERT INTO `products` (`id`, `category_id`, `sku`, `name`, `description`, `price`, `original_price`, `discount_percent`, `sale_price`, `stock`, `image`, `status`, `created_at`, `updated_at`, `rating`, `likes_count`, `cost_price`, `is_deleted`) VALUES ('89', '4', 'SKU-002', 'Watch', 'Watch', '1000.00', '1000.00', '10', '900.00', '189', '1793960941_pixel-bee-u8isJQ6QjF4-unsplash.jpg', 'Active', '2026-11-06 15:29:01', '2026-11-18 13:57:44', '0.0', '0', '0.00', '0');
INSERT INTO `products` (`id`, `category_id`, `sku`, `name`, `description`, `price`, `original_price`, `discount_percent`, `sale_price`, `stock`, `image`, `status`, `created_at`, `updated_at`, `rating`, `likes_count`, `cost_price`, `is_deleted`) VALUES ('91', '3', '6009510804782', '4u papar', 'test', '50.00', '35.00', '0', '35.00', '27', 'https://cdn-icons-png.flaticon.com/512/1165/1165687.png', 'Active', '2026-11-06 16:05:39', '2026-11-18 09:54:29', '0.0', '0', '0.00', '0');
INSERT INTO `products` (`id`, `category_id`, `sku`, `name`, `description`, `price`, `original_price`, `discount_percent`, `sale_price`, `stock`, `image`, `status`, `created_at`, `updated_at`, `rating`, `likes_count`, `cost_price`, `is_deleted`) VALUES ('92', '1', 'Ac-001', 'Ac', '', '100000.00', '100000.00', '5', '95000.00', '497', '1794635768_Screenshot 2026-11-14 105542.jpg', 'Active', '2026-11-14 10:56:08', '2026-11-18 09:54:29', '0.0', '0', '0.00', '0');


CREATE TABLE `returns` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `return_no` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` int unsigned NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `refund_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('Pending','Approved','Rejected','Refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `return_no` (`return_no`),
  KEY `fk_ret_order` (`order_id`),
  CONSTRAINT `fk_ret_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `reviews` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,
  `customer_id` int unsigned NOT NULL,
  `rating` tinyint unsigned NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `status` enum('Pending','Approved','Rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_rev_prod` (`product_id`),
  KEY `fk_rev_cust` (`customer_id`),
  KEY `idx_rev_status` (`status`),
  CONSTRAINT `fk_rev_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rev_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_chk_1` CHECK ((`rating` between 1 and 5))
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `role_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `role_id` int NOT NULL,
  `permission_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `role_id` (`role_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('1', '1', '1');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('10', '2', '3');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('11', '2', '1');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('12', '2', '5');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('13', '2', '41');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('14', '2', '40');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('16', '2', '153');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('17', '2', '156');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('18', '2', '159');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('19', '2', '162');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('20', '2', '165');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('21', '2', '168');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('22', '2', '171');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('23', '2', '174');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('24', '2', '177');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('25', '2', '180');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('26', '2', '183');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('27', '2', '186');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('28', '2', '152');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('29', '2', '155');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('30', '2', '158');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('31', '2', '161');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('32', '2', '164');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('33', '2', '167');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('34', '2', '170');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('35', '2', '173');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('36', '2', '176');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('37', '2', '179');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('38', '2', '182');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('39', '2', '185');


CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `roles` (`id`, `name`, `description`, `created_at`, `is_active`) VALUES ('1', 'Super Admin', 'Full system control and user management', '2026-11-08 09:54:35', '1');
INSERT INTO `roles` (`id`, `name`, `description`, `created_at`, `is_active`) VALUES ('2', 'Admin', 'Store operations and staff management', '2026-11-08 09:54:35', '1');
INSERT INTO `roles` (`id`, `name`, `description`, `created_at`, `is_active`) VALUES ('3', 'Staff', 'Assigned operational responsibilities', '2026-11-08 09:54:35', '1');


CREATE TABLE `shipping` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tracking_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` int unsigned NOT NULL,
  `courier` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `eta` date DEFAULT NULL,
  `shipped_at` datetime DEFAULT NULL,
  `status` enum('Processing','Shipped','Delivered','Returned') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Processing',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tracking_no` (`tracking_no`),
  KEY `fk_ship_order` (`order_id`),
  CONSTRAINT `fk_ship_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `stock_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int DEFAULT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int DEFAULT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `stock_history` (`id`, `product_id`, `type`, `quantity`, `reason`, `created_at`) VALUES ('1', '1', 'add', '23', '35', '2026-10-26 08:25:53');
INSERT INTO `stock_history` (`id`, `product_id`, `type`, `quantity`, `reason`, `created_at`) VALUES ('2', '26', 'add', '23', '', '2026-10-26 08:31:10');
INSERT INTO `stock_history` (`id`, `product_id`, `type`, `quantity`, `reason`, `created_at`) VALUES ('3', '26', 'add', '23', '', '2026-10-26 08:31:29');
INSERT INTO `stock_history` (`id`, `product_id`, `type`, `quantity`, `reason`, `created_at`) VALUES ('4', '25', 'add', '5464', 'wf', '2026-10-26 09:20:41');


CREATE TABLE `suppliers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `suppliers` (`id`, `name`, `email`, `phone`, `address`, `created_at`) VALUES ('1', 'Farhan Malik', 'fm3023098@gmail.com', '+923437745265', 'Nari Road , Khushab', '2026-10-26 08:26:30');


;

INSERT INTO `v_customers` (`id`, `name`, `email`, `phone`, `status`, `orders`, `total_spent`) VALUES ('67', 'Farhan Malik', 'fm3023098@gmail.com', '03437745265', 'Active', '1', '992.00');
INSERT INTO `v_customers` (`id`, `name`, `email`, `phone`, `status`, `orders`, `total_spent`) VALUES ('68', 'Hamid', 'developer4264@gmail.com', '03094131613', 'Active', '0', '0.00');


;

INSERT INTO `v_inventory` (`product_id`, `sku`, `name`, `quantity`, `reorder_level`, `status`) VALUES ('88', 'SKU-001', 'Shoes', '10', '10', 'Low Stock');
INSERT INTO `v_inventory` (`product_id`, `sku`, `name`, `quantity`, `reorder_level`, `status`) VALUES ('89', 'SKU-002', 'Watch', '10', '10', 'Low Stock');
INSERT INTO `v_inventory` (`product_id`, `sku`, `name`, `quantity`, `reorder_level`, `status`) VALUES ('91', '6009510804782', '4u papar', '10', '10', 'Low Stock');
INSERT INTO `v_inventory` (`product_id`, `sku`, `name`, `quantity`, `reorder_level`, `status`) VALUES ('92', 'Ac-001', 'Ac', '10', '10', 'Low Stock');
