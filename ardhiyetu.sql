-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 11, 2025 at 11:14 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ardhiyetu`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `deactivate_inactive_users` (IN `days_inactive` INT)   BEGIN
    UPDATE users 
    SET is_active = FALSE 
    WHERE last_login < DATE_SUB(NOW(), INTERVAL days_inactive DAY)
    AND is_active = TRUE
    AND role = 'user';
END$$

--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `calculate_age` (`date_of_birth` DATE) RETURNS INT(11) DETERMINISTIC BEGIN
    RETURN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE());
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `active_users`
-- (See below for the actual view)
--
CREATE TABLE `active_users` (
`user_id` int(11)
,`name` varchar(100)
,`email` varchar(100)
,`phone` varchar(15)
,`county` varchar(100)
,`role` enum('admin','officer','user')
,`created_at` timestamp
,`last_login` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `admin_actions`
--

CREATE TABLE `admin_actions` (
  `action_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `related_entity_type` enum('user','land_record','transfer','document','other') DEFAULT NULL,
  `related_entity_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `admin_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `is_super_admin` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_trail`
--

CREATE TABLE `audit_trail` (
  `audit_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) NOT NULL,
  `action` enum('INSERT','UPDATE','DELETE') NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `changed_by` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `backups`
--

CREATE TABLE `backups` (
  `backup_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `message_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `status` enum('unread','read','replied') DEFAULT 'unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `replied_at` timestamp NULL DEFAULT NULL,
  `admin_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`message_id`, `name`, `email`, `subject`, `message`, `status`, `created_at`, `replied_at`, `admin_notes`) VALUES
(1, 'Tonny Odhiambo', 'tonnyodhiambo49@gmail.com', 'Land Transfer', 'how can i transfer land to another person', 'unread', '2025-12-10 15:52:24', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `email_verifications`
--

CREATE TABLE `email_verifications` (
  `verification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `land_records`
--

CREATE TABLE `land_records` (
  `record_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `parcel_no` varchar(50) NOT NULL,
  `title_deed_no` varchar(50) DEFAULT NULL,
  `location` varchar(255) NOT NULL,
  `county` varchar(100) DEFAULT NULL,
  `size` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `size_unit` enum('acres','hectares','square_meters') DEFAULT 'acres',
  `land_use` enum('agricultural','residential','commercial','industrial','mixed') DEFAULT NULL,
  `land_class` varchar(50) DEFAULT NULL,
  `status` enum('active','pending','transferred','disputed','archived') DEFAULT 'pending',
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `registered_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `coordinates_updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `land_records`
--

INSERT INTO `land_records` (`record_id`, `owner_id`, `parcel_no`, `title_deed_no`, `location`, `county`, `size`, `description`, `document_path`, `size_unit`, `land_use`, `land_class`, `status`, `registered_at`, `updated_at`, `registered_by`, `notes`, `latitude`, `longitude`, `is_public`, `coordinates_updated_at`) VALUES
(1, 8, 'LR001/2025', NULL, 'Bungoma, Kibabii', NULL, 0.41, NULL, NULL, 'acres', NULL, NULL, 'pending', '2025-12-07 19:50:34', '2025-12-07 19:50:34', NULL, NULL, NULL, NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `legal_documents`
--

CREATE TABLE `legal_documents` (
  `legal_doc_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `land_id` int(11) DEFAULT NULL,
  `template_id` int(11) NOT NULL,
  `document_title` varchar(255) NOT NULL,
  `document_content` longtext NOT NULL,
  `status` enum('draft','finalized','signed','archived') DEFAULT 'draft',
  `signed_date` date DEFAULT NULL,
  `signed_by` varchar(255) DEFAULT NULL,
  `witnesses` text DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_history`
--

CREATE TABLE `login_history` (
  `login_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `logout_time` timestamp NULL DEFAULT NULL,
  `success` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `newsletter_subscribers`
--

CREATE TABLE `newsletter_subscribers` (
  `subscriber_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `subscribed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `unsubscribed_at` timestamp NULL DEFAULT NULL,
  `preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferences`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `newsletter_subscribers`
--

INSERT INTO `newsletter_subscribers` (`subscriber_id`, `email`, `name`, `user_id`, `is_active`, `subscribed_at`, `unsubscribed_at`, `preferences`) VALUES
(1, 'tonnyodhiambo49@gmail.com', 'Tonny Odhiambo', NULL, 1, '2025-12-07 08:20:44', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `type` enum('info','alert','reminder','success','warning','error') DEFAULT 'info',
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `action_url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `is_archived` tinyint(1) DEFAULT 0,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ownership_transfers`
--

CREATE TABLE `ownership_transfers` (
  `transfer_id` int(11) NOT NULL,
  `record_id` int(11) NOT NULL,
  `from_user_id` int(11) NOT NULL,
  `to_user_id` int(11) NOT NULL,
  `transfer_type` enum('sale','gift','inheritance','lease','other') DEFAULT NULL,
  `transfer_date` date DEFAULT NULL,
  `consideration_amount` decimal(15,2) DEFAULT NULL,
  `consideration_currency` varchar(3) DEFAULT 'KES',
  `document_path` varchar(255) DEFAULT NULL,
  `status` enum('submitted','under_review','approved','declined','cancelled') DEFAULT 'submitted',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `reset_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `pending_verifications`
-- (See below for the actual view)
--
CREATE TABLE `pending_verifications` (
`user_id` int(11)
,`name` varchar(100)
,`email` varchar(100)
,`phone` varchar(15)
,`county` varchar(100)
,`created_at` timestamp
,`token` varchar(64)
,`expires_at` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `security_logs`
--

CREATE TABLE `security_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `details` text DEFAULT NULL,
  `status` enum('success','failed','warning') DEFAULT 'success',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','number','boolean','json','array') DEFAULT 'string',
  `category` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `category`, `description`, `is_public`, `updated_at`, `updated_by`) VALUES
(1, 'site_name', 'ArdhiYetu', 'string', 'general', 'Website name', 1, '2025-12-07 07:11:05', NULL),
(2, 'site_description', 'Digital Land Management System', 'string', 'general', 'Website description', 1, '2025-12-07 07:11:05', NULL),
(3, 'maintenance_mode', '0', 'boolean', 'system', 'Enable maintenance mode', 0, '2025-12-07 07:11:05', NULL),
(4, 'user_registration', '1', 'boolean', 'user', 'Allow user registration', 1, '2025-12-07 07:11:05', NULL),
(5, 'email_verification', '1', 'boolean', 'user', 'Require email verification', 1, '2025-12-07 07:11:05', NULL),
(6, 'max_login_attempts', '5', 'number', 'security', 'Maximum failed login attempts', 0, '2025-12-07 07:11:05', NULL),
(7, 'session_timeout', '30', 'number', 'security', 'Session timeout in minutes', 0, '2025-12-07 07:11:05', NULL),
(8, 'default_user_role', 'user', 'string', 'user', 'Default role for new users', 0, '2025-12-07 07:11:05', NULL),
(9, 'min_password_length', '8', 'number', 'security', 'Minimum password length', 1, '2025-12-07 07:11:05', NULL),
(10, 'require_strong_password', '1', 'boolean', 'security', 'Require strong passwords', 1, '2025-12-07 07:11:05', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `id_number` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other','prefer_not_to_say') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `county` varchar(100) DEFAULT NULL,
  `security_question` varchar(255) DEFAULT NULL,
  `security_answer` varchar(255) DEFAULT NULL,
  `verification_token` varchar(64) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `is_verified` tinyint(1) DEFAULT 0,
  `verification_date` timestamp NULL DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `failed_attempts` int(11) DEFAULT 0,
  `remember_token` varchar(64) DEFAULT NULL,
  `newsletter_subscribed` tinyint(1) DEFAULT 1,
  `marketing_consent` tinyint(1) DEFAULT 0,
  `role` enum('admin','officer','user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `phone`, `id_number`, `password`, `date_of_birth`, `gender`, `address`, `county`, `security_question`, `security_answer`, `verification_token`, `is_active`, `is_verified`, `verification_date`, `last_login`, `failed_attempts`, `remember_token`, `newsletter_subscribed`, `marketing_consent`, `role`, `created_at`, `updated_at`) VALUES
(8, 'Tonny Odhiambo', 'tonnyodhiambo49@gmail.com', '0792069328', '38969021', '$2y$10$K2JlMxj.AtP7TecKZLqTte8KmjFXxGvwo8T3NnXvJqG1aodo4WBAW', '2000-04-16', 'male', '190-50100 Kakamega', 'Kakamega', 'first_car', 'demio', 'ac371dc27dccf8e01290e68b77b41922e639d3345d5f4f164883232c923602cb', 1, 0, NULL, '2025-12-10 15:43:26', 0, NULL, 1, 0, 'user', '2025-12-07 08:32:54', '2025-12-10 15:43:26'),
(10, 'Tonny Odhiambo', 'tonnyodhiambo707@gmail.com', '', '', '$2y$10$FqKRZ9eL2rlobWGRyaESoub5JSCMcEuOhkNg3SsscSXk6Gcmhj6Zy', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, '2025-12-09 12:24:05', 0, NULL, 1, 0, 'admin', '2025-12-08 17:32:55', '2025-12-09 12:24:05');

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `log_user_changes` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
    IF OLD.name != NEW.name OR OLD.email != NEW.email OR OLD.phone != NEW.phone THEN
        INSERT INTO audit_trail (
            user_id, 
            table_name, 
            record_id, 
            action, 
            old_values, 
            new_values,
            changed_by
        ) VALUES (
            NEW.user_id,
            'users',
            NEW.user_id,
            'UPDATE',
            JSON_OBJECT(
                'name', OLD.name,
                'email', OLD.email,
                'phone', OLD.phone
            ),
            JSON_OBJECT(
                'name', NEW.name,
                'email', NEW.email,
                'phone', NEW.phone
            ),
            NEW.user_id
        );
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_user_timestamp` BEFORE UPDATE ON `users` FOR EACH ROW BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `user_activities`
--

CREATE TABLE `user_activities` (
  `activity_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_activities`
--

INSERT INTO `user_activities` (`activity_id`, `user_id`, `action_type`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(5, 8, 'registration', 'New user registered', NULL, NULL, '2025-12-07 08:32:54'),
(6, 8, 'login', 'User logged in', NULL, NULL, '2025-12-07 08:33:05'),
(7, 8, 'login', 'User logged in', NULL, NULL, '2025-12-07 08:33:06'),
(8, 8, 'logout', 'User logged out', NULL, NULL, '2025-12-07 08:42:55'),
(9, 8, 'login', 'User logged in', NULL, NULL, '2025-12-07 08:48:34'),
(10, 8, 'login', 'User logged in', NULL, NULL, '2025-12-07 08:48:35'),
(11, 8, 'logout', 'User logged out', NULL, NULL, '2025-12-07 09:19:06'),
(12, 8, 'login', 'User logged in', NULL, NULL, '2025-12-07 09:21:31'),
(13, 8, 'login', 'User logged in', NULL, NULL, '2025-12-07 09:21:31'),
(14, 8, 'logout', 'User logged out', NULL, NULL, '2025-12-07 09:40:15'),
(15, 8, 'login', 'User logged in', NULL, NULL, '2025-12-07 09:40:31'),
(16, 8, 'login', 'User logged in', NULL, NULL, '2025-12-07 09:40:31'),
(17, 8, 'logout', 'User logged out', NULL, NULL, '2025-12-07 10:17:23'),
(18, 8, 'login', 'User logged in', NULL, NULL, '2025-12-07 10:19:25'),
(19, 8, 'login', 'User logged in', NULL, NULL, '2025-12-07 10:19:25'),
(20, 8, 'logout', 'User logged out', NULL, NULL, '2025-12-07 10:19:33'),
(21, 8, 'login', 'User logged in', NULL, NULL, '2025-12-07 10:20:20'),
(22, 8, 'login', 'User logged in', NULL, NULL, '2025-12-07 10:20:21'),
(23, 8, 'login', 'User logged in', NULL, NULL, '2025-12-07 18:39:01'),
(24, 8, 'login', 'User logged in', NULL, NULL, '2025-12-07 18:39:02'),
(25, 8, 'land_registration', 'Registered new land: LR001/2025', NULL, NULL, '2025-12-07 19:50:35'),
(26, 8, 'logout', 'User logged out', NULL, NULL, '2025-12-07 20:16:37'),
(27, 8, 'login', 'User logged in', NULL, NULL, '2025-12-07 20:18:00'),
(28, 8, 'login', 'User logged in', NULL, NULL, '2025-12-07 20:18:00'),
(29, 8, 'logout', 'User logged out', NULL, NULL, '2025-12-07 20:18:41'),
(30, 8, 'login', 'User logged in', NULL, NULL, '2025-12-07 20:21:52'),
(31, 8, 'login', 'User logged in', NULL, NULL, '2025-12-07 20:21:53'),
(32, 8, 'logout', 'User logged out', NULL, NULL, '2025-12-07 21:52:13'),
(34, 8, 'login', 'User logged in', NULL, NULL, '2025-12-08 17:41:57'),
(35, 8, 'login', 'User logged in', NULL, NULL, '2025-12-08 17:41:57'),
(36, 8, 'logout', 'User logged out', NULL, NULL, '2025-12-08 17:42:05'),
(37, 10, 'login', 'User logged in', NULL, NULL, '2025-12-08 17:42:21'),
(38, 10, 'login', 'User logged in', NULL, NULL, '2025-12-08 17:42:21'),
(39, 10, 'logout', 'User logged out', NULL, NULL, '2025-12-08 17:48:10'),
(40, 8, 'login', 'User logged in', NULL, NULL, '2025-12-08 20:12:25'),
(41, 8, 'login', 'User logged in', NULL, NULL, '2025-12-08 20:12:25'),
(42, 8, 'logout', 'User logged out', NULL, NULL, '2025-12-08 20:12:38'),
(43, 10, 'login', 'User logged in', NULL, NULL, '2025-12-08 20:51:24'),
(44, 10, 'login', 'User logged in', NULL, NULL, '2025-12-08 20:51:24'),
(45, 10, 'logout', 'User logged out', NULL, NULL, '2025-12-09 08:42:29'),
(46, 8, 'login', 'User logged in', NULL, NULL, '2025-12-09 10:30:31'),
(47, 8, 'login', 'User logged in', NULL, NULL, '2025-12-09 10:30:32'),
(48, 8, 'logout', 'User logged out', NULL, NULL, '2025-12-09 10:30:47'),
(49, 10, 'login', 'User logged in', NULL, NULL, '2025-12-09 10:31:06'),
(50, 10, 'login', 'User logged in', NULL, NULL, '2025-12-09 10:31:06'),
(51, 10, 'logout', 'User logged out', NULL, NULL, '2025-12-09 10:32:01'),
(52, 8, 'login', 'User logged in', NULL, NULL, '2025-12-09 10:32:18'),
(53, 8, 'login', 'User logged in', NULL, NULL, '2025-12-09 10:32:18'),
(54, 8, 'logout', 'User logged out', NULL, NULL, '2025-12-09 10:32:23'),
(55, 10, 'failed_login', 'Failed login attempt', NULL, NULL, '2025-12-09 10:32:42'),
(56, 10, 'login', 'User logged in', NULL, NULL, '2025-12-09 10:32:51'),
(57, 10, 'login', 'User logged in', NULL, NULL, '2025-12-09 10:32:51'),
(58, 10, 'logout', 'User logged out', NULL, NULL, '2025-12-09 11:19:54'),
(59, 10, 'failed_login', 'Failed login attempt', NULL, NULL, '2025-12-09 12:23:46'),
(60, 10, 'failed_login', 'Failed login attempt', NULL, NULL, '2025-12-09 12:23:57'),
(61, 10, 'login', 'User logged in', NULL, NULL, '2025-12-09 12:24:05'),
(62, 10, 'login', 'User logged in', NULL, NULL, '2025-12-09 12:24:05'),
(63, 10, 'logout', 'User logged out', NULL, NULL, '2025-12-10 12:08:27'),
(64, 8, 'login', 'User logged in', NULL, NULL, '2025-12-10 12:37:53'),
(65, 8, 'login', 'User logged in', NULL, NULL, '2025-12-10 12:37:54'),
(66, 8, 'logout', 'User logged out', NULL, NULL, '2025-12-10 12:38:36'),
(67, 8, 'login', 'User logged in', NULL, NULL, '2025-12-10 12:38:54'),
(68, 8, 'login', 'User logged in', NULL, NULL, '2025-12-10 12:38:54'),
(69, 8, 'logout', 'User logged out', NULL, NULL, '2025-12-10 12:39:08'),
(70, 8, 'failed_login', 'Failed login attempt', NULL, NULL, '2025-12-10 15:43:15'),
(71, 8, 'login', 'User logged in', NULL, NULL, '2025-12-10 15:43:26'),
(72, 8, 'login', 'User logged in', NULL, NULL, '2025-12-10 15:43:26'),
(73, 8, 'contact_form', 'Submitted contact form', NULL, NULL, '2025-12-10 15:52:25');

-- --------------------------------------------------------

--
-- Table structure for table `user_documents`
--

CREATE TABLE `user_documents` (
  `document_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `document_type` enum('id_front','id_back','passport','photo','signature','other') DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(50) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11) DEFAULT NULL,
  `verification_date` timestamp NULL DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_preferences`
--

CREATE TABLE `user_preferences` (
  `preference_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `preference_key` varchar(50) NOT NULL,
  `preference_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_profiles`
--

CREATE TABLE `user_profiles` (
  `profile_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `education_level` enum('primary','secondary','diploma','degree','masters','phd','other') DEFAULT NULL,
  `marital_status` enum('single','married','divorced','widowed') DEFAULT NULL,
  `nationality` varchar(50) DEFAULT 'Kenyan',
  `postal_address` varchar(255) DEFAULT NULL,
  `next_of_kin_name` varchar(100) DEFAULT NULL,
  `next_of_kin_phone` varchar(15) DEFAULT NULL,
  `next_of_kin_relationship` varchar(50) DEFAULT NULL,
  `emergency_contact` varchar(15) DEFAULT NULL,
  `preferred_language` enum('en','sw') DEFAULT 'en',
  `theme_preference` enum('light','dark','system') DEFAULT 'light',
  `email_notifications` tinyint(1) DEFAULT 1,
  `sms_notifications` tinyint(1) DEFAULT 1,
  `two_factor_auth` tinyint(1) DEFAULT 0,
  `last_profile_update` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `user_statistics`
-- (See below for the actual view)
--
CREATE TABLE `user_statistics` (
`county` varchar(100)
,`total_users` bigint(21)
,`verified_users` decimal(22,0)
,`admin_users` decimal(22,0)
,`officer_users` decimal(22,0)
,`regular_users` decimal(22,0)
,`avg_age` decimal(14,4)
);

-- --------------------------------------------------------

--
-- Structure for view `active_users`
--
DROP TABLE IF EXISTS `active_users`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `active_users`  AS SELECT `users`.`user_id` AS `user_id`, `users`.`name` AS `name`, `users`.`email` AS `email`, `users`.`phone` AS `phone`, `users`.`county` AS `county`, `users`.`role` AS `role`, `users`.`created_at` AS `created_at`, `users`.`last_login` AS `last_login` FROM `users` WHERE `users`.`is_active` = 1 AND `users`.`is_verified` = 1 ;

-- --------------------------------------------------------

--
-- Structure for view `pending_verifications`
--
DROP TABLE IF EXISTS `pending_verifications`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `pending_verifications`  AS SELECT `u`.`user_id` AS `user_id`, `u`.`name` AS `name`, `u`.`email` AS `email`, `u`.`phone` AS `phone`, `u`.`county` AS `county`, `u`.`created_at` AS `created_at`, `ev`.`token` AS `token`, `ev`.`expires_at` AS `expires_at` FROM (`users` `u` left join `email_verifications` `ev` on(`u`.`user_id` = `ev`.`user_id`)) WHERE `u`.`is_verified` = 0 AND `u`.`is_active` = 1 AND (`ev`.`verified_at` is null OR `ev`.`expires_at` > current_timestamp()) ;

-- --------------------------------------------------------

--
-- Structure for view `user_statistics`
--
DROP TABLE IF EXISTS `user_statistics`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `user_statistics`  AS SELECT `u`.`county` AS `county`, count(0) AS `total_users`, sum(case when `u`.`is_verified` = 1 then 1 else 0 end) AS `verified_users`, sum(case when `u`.`role` = 'admin' then 1 else 0 end) AS `admin_users`, sum(case when `u`.`role` = 'officer' then 1 else 0 end) AS `officer_users`, sum(case when `u`.`role` = 'user' then 1 else 0 end) AS `regular_users`, avg(`calculate_age`(`u`.`date_of_birth`)) AS `avg_age` FROM `users` AS `u` WHERE `u`.`is_active` = 1 GROUP BY `u`.`county` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_actions`
--
ALTER TABLE `admin_actions`
  ADD PRIMARY KEY (`action_id`),
  ADD KEY `idx_admin_actions` (`admin_id`,`timestamp`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_related_entity` (`related_entity_type`,`related_entity_id`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD PRIMARY KEY (`audit_id`),
  ADD KEY `idx_table_record` (`table_name`,`record_id`),
  ADD KEY `idx_action_time` (`action`,`changed_at`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `backups`
--
ALTER TABLE `backups`
  ADD PRIMARY KEY (`backup_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`message_id`);

--
-- Indexes for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD PRIMARY KEY (`verification_id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `land_records`
--
ALTER TABLE `land_records`
  ADD PRIMARY KEY (`record_id`),
  ADD UNIQUE KEY `parcel_no` (`parcel_no`),
  ADD UNIQUE KEY `title_deed_no` (`title_deed_no`),
  ADD KEY `registered_by` (`registered_by`),
  ADD KEY `idx_owner` (`owner_id`),
  ADD KEY `idx_parcel` (`parcel_no`),
  ADD KEY `idx_location` (`location`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_land_records_composite` (`owner_id`,`status`,`registered_at`),
  ADD KEY `idx_coordinates` (`latitude`,`longitude`),
  ADD KEY `idx_public_status` (`is_public`,`status`);

--
-- Indexes for table `legal_documents`
--
ALTER TABLE `legal_documents`
  ADD PRIMARY KEY (`legal_doc_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_template_id` (`template_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `login_history`
--
ALTER TABLE `login_history`
  ADD PRIMARY KEY (`login_id`),
  ADD KEY `idx_user_login` (`user_id`,`login_time`),
  ADD KEY `idx_login_time` (`login_time`),
  ADD KEY `idx_login_composite` (`user_id`,`login_time`);

--
-- Indexes for table `newsletter_subscribers`
--
ALTER TABLE `newsletter_subscribers`
  ADD PRIMARY KEY (`subscriber_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_user_notifications` (`user_id`,`is_read`,`sent_at`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_notifications_composite` (`user_id`,`is_read`,`sent_at`);

--
-- Indexes for table `ownership_transfers`
--
ALTER TABLE `ownership_transfers`
  ADD PRIMARY KEY (`transfer_id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_record` (`record_id`),
  ADD KEY `idx_from_user` (`from_user_id`),
  ADD KEY `idx_to_user` (`to_user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_transfer_date` (`transfer_date`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`reset_id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `security_logs`
--
ALTER TABLE `security_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_user_action` (`user_id`,`action_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_setting_key` (`setting_key`),
  ADD KEY `idx_category` (`category`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `id_number` (`id_number`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_phone` (`phone`),
  ADD KEY `idx_id_number` (`id_number`),
  ADD KEY `idx_county` (`county`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`is_active`,`is_verified`),
  ADD KEY `idx_user_composite` (`is_active`,`is_verified`,`role`,`county`);

--
-- Indexes for table `user_activities`
--
ALTER TABLE `user_activities`
  ADD PRIMARY KEY (`activity_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_documents`
--
ALTER TABLE `user_documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `idx_user_docs` (`user_id`,`document_type`),
  ADD KEY `idx_verified` (`is_verified`);

--
-- Indexes for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD PRIMARY KEY (`preference_id`),
  ADD UNIQUE KEY `unique_user_preference` (`user_id`,`preference_key`),
  ADD KEY `idx_user_key` (`user_id`,`preference_key`);

--
-- Indexes for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`profile_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_actions`
--
ALTER TABLE `admin_actions`
  MODIFY `action_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_trail`
--
ALTER TABLE `audit_trail`
  MODIFY `audit_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `backups`
--
ALTER TABLE `backups`
  MODIFY `backup_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `email_verifications`
--
ALTER TABLE `email_verifications`
  MODIFY `verification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `land_records`
--
ALTER TABLE `land_records`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `legal_documents`
--
ALTER TABLE `legal_documents`
  MODIFY `legal_doc_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_history`
--
ALTER TABLE `login_history`
  MODIFY `login_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `newsletter_subscribers`
--
ALTER TABLE `newsletter_subscribers`
  MODIFY `subscriber_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `ownership_transfers`
--
ALTER TABLE `ownership_transfers`
  MODIFY `transfer_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `reset_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `security_logs`
--
ALTER TABLE `security_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `user_activities`
--
ALTER TABLE `user_activities`
  MODIFY `activity_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT for table `user_documents`
--
ALTER TABLE `user_documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_preferences`
--
ALTER TABLE `user_preferences`
  MODIFY `preference_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_profiles`
--
ALTER TABLE `user_profiles`
  MODIFY `profile_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_actions`
--
ALTER TABLE `admin_actions`
  ADD CONSTRAINT `admin_actions_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `backups`
--
ALTER TABLE `backups`
  ADD CONSTRAINT `backups_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD CONSTRAINT `email_verifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `land_records`
--
ALTER TABLE `land_records`
  ADD CONSTRAINT `land_records_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `land_records_ibfk_2` FOREIGN KEY (`registered_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `login_history`
--
ALTER TABLE `login_history`
  ADD CONSTRAINT `login_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `newsletter_subscribers`
--
ALTER TABLE `newsletter_subscribers`
  ADD CONSTRAINT `newsletter_subscribers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `ownership_transfers`
--
ALTER TABLE `ownership_transfers`
  ADD CONSTRAINT `ownership_transfers_ibfk_1` FOREIGN KEY (`record_id`) REFERENCES `land_records` (`record_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ownership_transfers_ibfk_2` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `ownership_transfers_ibfk_3` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `ownership_transfers_ibfk_4` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `ownership_transfers_ibfk_5` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `user_activities`
--
ALTER TABLE `user_activities`
  ADD CONSTRAINT `user_activities_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_documents`
--
ALTER TABLE `user_documents`
  ADD CONSTRAINT `user_documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_documents_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD CONSTRAINT `user_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `user_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
