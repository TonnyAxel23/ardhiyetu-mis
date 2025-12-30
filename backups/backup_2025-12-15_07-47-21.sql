-- Database Backup
-- Generated: 2025-12-15 07:47:21
-- Database: ardhiyetu

DROP TABLE IF EXISTS `active_users`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `active_users` AS select `users`.`user_id` AS `user_id`,`users`.`name` AS `name`,`users`.`email` AS `email`,`users`.`phone` AS `phone`,`users`.`county` AS `county`,`users`.`role` AS `role`,`users`.`created_at` AS `created_at`,`users`.`last_login` AS `last_login` from `users` where `users`.`is_active` = 1 and `users`.`is_verified` = 1;


DROP TABLE IF EXISTS `admin_actions`;

CREATE TABLE `admin_actions` (
  `action_id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `related_entity_type` enum('user','land_record','transfer','document','other') DEFAULT NULL,
  `related_entity_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`action_id`),
  KEY `idx_admin_actions` (`admin_id`,`timestamp`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_related_entity` (`related_entity_type`,`related_entity_id`),
  CONSTRAINT `admin_actions_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `admin_users`;

CREATE TABLE `admin_users` (
  `admin_id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `is_super_admin` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `audit_trail`;

CREATE TABLE `audit_trail` (
  `audit_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) NOT NULL,
  `action` enum('INSERT','UPDATE','DELETE') NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `changed_by` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`audit_id`),
  KEY `idx_table_record` (`table_name`,`record_id`),
  KEY `idx_action_time` (`action`,`changed_at`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `backups`;

CREATE TABLE `backups` (
  `backup_id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  PRIMARY KEY (`backup_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `backups_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `chat_agents`;

CREATE TABLE `chat_agents` (
  `agent_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `is_online` tinyint(1) DEFAULT 0,
  `max_chats` int(11) DEFAULT 5,
  `current_chats` int(11) DEFAULT 0,
  `last_active` timestamp NOT NULL DEFAULT current_timestamp(),
  `status_message` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`agent_id`),
  UNIQUE KEY `unique_agent_user` (`user_id`),
  CONSTRAINT `chat_agents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `chat_conversations`;

CREATE TABLE `chat_conversations` (
  `conversation_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `support_agent_id` int(11) DEFAULT NULL,
  `status` enum('active','pending','closed','archived') DEFAULT 'pending',
  `subject` varchar(255) DEFAULT NULL,
  `last_message_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`conversation_id`),
  KEY `idx_user_status` (`user_id`,`status`),
  KEY `idx_agent_status` (`support_agent_id`,`status`),
  CONSTRAINT `chat_conversations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `chat_conversations_ibfk_2` FOREIGN KEY (`support_agent_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `chat_messages`;

CREATE TABLE `chat_messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message_type` enum('text','file','image','system') DEFAULT 'text',
  `message` text DEFAULT NULL,
  `file_url` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`message_id`),
  KEY `idx_conversation_created` (`conversation_id`,`created_at`),
  KEY `idx_sender` (`sender_id`),
  KEY `idx_unread` (`conversation_id`,`is_read`),
  CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations` (`conversation_id`) ON DELETE CASCADE,
  CONSTRAINT `chat_messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `contact_messages`;

CREATE TABLE `contact_messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `status` enum('unread','read','replied') DEFAULT 'unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `replied_at` timestamp NULL DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  PRIMARY KEY (`message_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `contact_messages` VALUES("1","Tonny Odhiambo","tonnyodhiambo49@gmail.com","Land Transfer","how can i transfer land to another person","unread","2025-12-10 18:52:24","","");

DROP TABLE IF EXISTS `documents`;

CREATE TABLE `documents` (
  `document_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `land_id` int(11) DEFAULT NULL,
  `document_type` varchar(100) NOT NULL,
  `document_number` varchar(100) NOT NULL,
  `purpose` text DEFAULT NULL,
  `generated_data` longtext NOT NULL,
  `status` enum('pending','generated','verified','expired','revoked') DEFAULT 'generated',
  `format` varchar(50) DEFAULT 'pdf',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`document_id`),
  UNIQUE KEY `document_number` (`document_number`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_document_number` (`document_number`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `documents` VALUES("1","12","17","ownership_certificate","ARDHI/2025/12/000017","","{\"document_number\":\"ARDHI\\/2025\\/12\\/000017\",\"certificate_type\":\"ownership_certificate\",\"purpose\":\"\",\"generated_date\":\"2025-12-14 12:22:44\",\"valid_until\":\"2026-01-13\",\"land_data\":{\"record_id\":17,\"owner_id\":12,\"parcel_no\":\"LR005\\/2025\",\"title_deed_no\":null,\"location\":\"Bungoma, Kanduyi\",\"county\":null,\"size\":\"40.00\",\"description\":null,\"document_path\":null,\"size_unit\":\"acres\",\"land_use\":null,\"land_class\":null,\"status\":\"active\",\"rejection_reason\":null,\"registered_at\":\"2025-12-14 11:34:56\",\"updated_at\":\"2025-12-14 11:46:15\",\"registered_by\":null,\"notes\":null,\"latitude\":null,\"longitude\":null,\"is_public\":0,\"coordinates_updated_at\":null,\"reviewed_by\":null,\"review_notes\":null,\"reviewed_at\":null,\"previous_owner_id\":null,\"transfer_history\":null,\"original_parcel_no\":null,\"parent_record_id\":null,\"current_ownership_history_id\":null,\"owner_name\":\"Amina Habib\",\"id_number\":\"43568216\"},\"user_data\":{\"name\":\"Amina Habib\",\"id_number\":\"43568216\"}}","generated","pdf","2025-12-14 12:22:44","2025-12-14 12:22:44");
INSERT INTO `documents` VALUES("3","12","17","title_deed","ARDHI/2025/12/000017-1","","{\"document_number\":\"ARDHI\\/2025\\/12\\/000017-1\",\"certificate_type\":\"title_deed\",\"purpose\":\"\",\"generated_date\":\"2025-12-14 12:31:35\",\"valid_until\":\"2026-01-13\",\"land_data\":{\"record_id\":17,\"owner_id\":12,\"parcel_no\":\"LR005\\/2025\",\"title_deed_no\":null,\"location\":\"Bungoma, Kanduyi\",\"county\":null,\"size\":\"40.00\",\"description\":null,\"document_path\":null,\"size_unit\":\"acres\",\"land_use\":null,\"land_class\":null,\"status\":\"active\",\"rejection_reason\":null,\"registered_at\":\"2025-12-14 11:34:56\",\"updated_at\":\"2025-12-14 11:46:15\",\"registered_by\":null,\"notes\":null,\"latitude\":null,\"longitude\":null,\"is_public\":0,\"coordinates_updated_at\":null,\"reviewed_by\":null,\"review_notes\":null,\"reviewed_at\":null,\"previous_owner_id\":null,\"transfer_history\":null,\"original_parcel_no\":null,\"parent_record_id\":null,\"current_ownership_history_id\":null,\"owner_name\":\"Amina Habib\",\"id_number\":\"43568216\"},\"user_data\":{\"name\":\"Amina Habib\",\"id_number\":\"43568216\"}}","generated","pdf","2025-12-14 12:31:35","2025-12-14 12:31:35");
INSERT INTO `documents` VALUES("4","8","11","title_deed","ARDHI/2025/12/000011","","{\"document_number\":\"ARDHI\\/2025\\/12\\/000011\",\"certificate_type\":\"title_deed\",\"purpose\":\"\",\"generated_date\":\"2025-12-14 19:43:35\",\"valid_until\":\"2026-01-13\",\"land_data\":{\"record_id\":11,\"owner_id\":8,\"parcel_no\":\"LR002\\/2025\",\"title_deed_no\":null,\"location\":\"Nakuru, Molo\",\"county\":null,\"size\":\"20.00\",\"description\":null,\"document_path\":null,\"size_unit\":\"acres\",\"land_use\":null,\"land_class\":null,\"status\":\"active\",\"rejection_reason\":null,\"registered_at\":\"2025-12-12 22:42:56\",\"updated_at\":\"2025-12-14 07:47:20\",\"registered_by\":null,\"notes\":null,\"latitude\":null,\"longitude\":null,\"is_public\":0,\"coordinates_updated_at\":null,\"reviewed_by\":null,\"review_notes\":null,\"reviewed_at\":null,\"previous_owner_id\":null,\"transfer_history\":null,\"original_parcel_no\":null,\"parent_record_id\":null,\"current_ownership_history_id\":null,\"owner_name\":\"Tonny Odhiambo\",\"id_number\":\"38969021\"},\"user_data\":{\"name\":\"Tonny Odhiambo\",\"id_number\":\"38969021\"}}","generated","pdf","2025-12-14 19:43:35","2025-12-14 19:43:35");

DROP TABLE IF EXISTS `email_verifications`;

CREATE TABLE `email_verifications` (
  `verification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`verification_id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`),
  KEY `idx_token` (`token`),
  KEY `idx_email` (`email`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `email_verifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `land_chain`;

CREATE TABLE `land_chain` (
  `chain_id` int(11) NOT NULL AUTO_INCREMENT,
  `original_record_id` int(11) NOT NULL,
  `current_record_id` int(11) NOT NULL,
  `chain_path` varchar(1000) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`chain_id`),
  KEY `idx_original` (`original_record_id`),
  KEY `idx_current` (`current_record_id`),
  KEY `idx_chain` (`chain_path`(255)),
  CONSTRAINT `land_chain_ibfk_1` FOREIGN KEY (`original_record_id`) REFERENCES `land_records` (`record_id`) ON DELETE CASCADE,
  CONSTRAINT `land_chain_ibfk_2` FOREIGN KEY (`current_record_id`) REFERENCES `land_records` (`record_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `land_chain` VALUES("1","15","16","LR004/2025 → LR004/2025-SPLIT-20251214-10bed0","2025-12-14 10:50:18");
INSERT INTO `land_chain` VALUES("2","17","18","LR005/2025 → LR005/2025-SPLIT-20251214-f745a9","2025-12-14 11:46:15");

DROP TABLE IF EXISTS `land_history`;

CREATE TABLE `land_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `record_id` int(11) NOT NULL,
  `change_type` enum('size_change','status_change','location_update','document_update','split','merge','other') DEFAULT 'other',
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `changed_by` int(11) NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`history_id`),
  KEY `changed_by` (`changed_by`),
  KEY `idx_record_id` (`record_id`),
  KEY `idx_change_type` (`change_type`),
  CONSTRAINT `land_history_ibfk_1` FOREIGN KEY (`record_id`) REFERENCES `land_records` (`record_id`) ON DELETE CASCADE,
  CONSTRAINT `land_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `land_history` VALUES("1","11","status_change","pending","active","Status changed from pending to active","8","2025-12-14 07:47:20");
INSERT INTO `land_history` VALUES("2","15","status_change","pending","active","Status changed from pending to active","8","2025-12-14 10:35:22");
INSERT INTO `land_history` VALUES("5","15","size_change","100.00","80.00","Size changed from 100.00 to 80.00 acres","8","2025-12-14 10:50:18");
INSERT INTO `land_history` VALUES("6","15","split","100.00","80.00","Land split: Created new parcel LR004/2025-SPLIT-20251214-10bed0 (20.00 acres)","11","2025-12-14 10:50:18");
INSERT INTO `land_history` VALUES("7","16","split","","","Created from split of parent parcel","11","2025-12-14 10:50:18");
INSERT INTO `land_history` VALUES("8","16","status_change","","active","Status changed from  to active","11","2025-12-14 10:53:15");
INSERT INTO `land_history` VALUES("9","17","status_change","pending","active","Status changed from pending to active","12","2025-12-14 11:37:53");
INSERT INTO `land_history` VALUES("10","17","size_change","50.00","40.00","Size changed from 50.00 to 40.00 acres","12","2025-12-14 11:46:15");
INSERT INTO `land_history` VALUES("11","17","split","50.00","40.00","Land split: Created new parcel LR005/2025-SPLIT-20251214-f745a9 (10.00 acres)","8","2025-12-14 11:46:15");
INSERT INTO `land_history` VALUES("12","18","split","","","Created from split of parent parcel","8","2025-12-14 11:46:15");
INSERT INTO `land_history` VALUES("13","18","status_change","","active","Status changed from  to active","8","2025-12-14 11:49:14");
INSERT INTO `land_history` VALUES("14","19","status_change","pending","active","Status changed from pending to active","13","2025-12-14 12:52:14");
INSERT INTO `land_history` VALUES("15","20","status_change","pending","active","Status changed from pending to active","8","2025-12-14 13:25:26");
INSERT INTO `land_history` VALUES("16","21","status_change","pending","","Status changed from pending to ","8","2025-12-15 07:34:52");

DROP TABLE IF EXISTS `land_listings`;

CREATE TABLE `land_listings` (
  `listing_id` int(11) NOT NULL AUTO_INCREMENT,
  `record_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `listing_type` enum('sale','lease','rent') NOT NULL,
  `price` decimal(15,2) DEFAULT NULL,
  `price_type` enum('total','per_acre','per_month','per_year') DEFAULT NULL,
  `size_available` decimal(10,2) DEFAULT NULL,
  `status` enum('active','pending','sold','leased','expired','cancelled') DEFAULT 'pending',
  `featured` tinyint(1) DEFAULT 0,
  `views` int(11) DEFAULT 0,
  `expires_at` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`listing_id`),
  KEY `record_id` (`record_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_status_type` (`status`,`listing_type`),
  KEY `idx_featured` (`featured`,`status`),
  CONSTRAINT `land_listings_ibfk_1` FOREIGN KEY (`record_id`) REFERENCES `land_records` (`record_id`),
  CONSTRAINT `land_listings_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `land_records`;

CREATE TABLE `land_records` (
  `record_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `rejection_reason` text DEFAULT NULL,
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `registered_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `coordinates_updated_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `previous_owner_id` int(11) DEFAULT NULL,
  `transfer_history` text DEFAULT NULL,
  `original_parcel_no` varchar(100) DEFAULT NULL,
  `parent_record_id` int(11) DEFAULT NULL,
  `current_ownership_history_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`record_id`),
  UNIQUE KEY `parcel_no` (`parcel_no`),
  UNIQUE KEY `title_deed_no` (`title_deed_no`),
  KEY `registered_by` (`registered_by`),
  KEY `idx_owner` (`owner_id`),
  KEY `idx_parcel` (`parcel_no`),
  KEY `idx_location` (`location`),
  KEY `idx_status` (`status`),
  KEY `idx_land_records_composite` (`owner_id`,`status`,`registered_at`),
  KEY `idx_coordinates` (`latitude`,`longitude`),
  KEY `idx_public_status` (`is_public`,`status`),
  KEY `fk_parent_record` (`parent_record_id`),
  KEY `fk_current_ownership` (`current_ownership_history_id`),
  CONSTRAINT `fk_current_ownership` FOREIGN KEY (`current_ownership_history_id`) REFERENCES `ownership_history` (`history_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_parent_record` FOREIGN KEY (`parent_record_id`) REFERENCES `land_records` (`record_id`),
  CONSTRAINT `land_records_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `land_records_ibfk_2` FOREIGN KEY (`registered_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `land_records` VALUES("1","11","LR001/2025","","Bungoma, Kibabii","","0.41","","","acres","","","active","","2025-12-07 22:50:34","2025-12-13 10:22:14","","","","","0","","","","","","","","","");
INSERT INTO `land_records` VALUES("10","8","LR003/2025","","Kakamega, Lurambi","","3.00","","","acres","","","active","","2025-12-12 22:42:12","2025-12-12 22:44:26","","","","","0","","","","","","","","","");
INSERT INTO `land_records` VALUES("11","8","LR002/2025","","Nakuru, Molo","","20.00","","","acres","","","active","","2025-12-12 22:42:56","2025-12-14 07:47:20","","","","","0","","","","","","","","","");
INSERT INTO `land_records` VALUES("15","8","LR004/2025","","Nairobi, Westlands","","80.00","","","acres","","","active","","2025-12-14 10:33:41","2025-12-14 10:50:18","","","","","0","","","","","","","","","");
INSERT INTO `land_records` VALUES("16","11","LR004/2025-SPLIT-20251214-10bed0","","Nairobi, Westlands","","20.00","","","acres","","","active","","2025-12-14 10:50:18","2025-12-14 10:53:15","","","","","0","","","","","","","LR004/2025","15","");
INSERT INTO `land_records` VALUES("17","12","LR005/2025","","Bungoma, Kanduyi","","40.00","","","acres","","","active","","2025-12-14 11:34:56","2025-12-14 11:46:15","","","","","0","","","","","","","","","");
INSERT INTO `land_records` VALUES("18","8","LR005/2025-SPLIT-20251214-f745a9","","Bungoma, Kanduyi","","10.00","","","acres","","","active","","2025-12-14 11:46:15","2025-12-14 11:49:14","","","","","0","","","","","","","LR005/2025","17","");
INSERT INTO `land_records` VALUES("19","13","LR006/2025","","Kisumu, Kondele","","30.00","","","acres","","","active","","2025-12-14 12:49:34","2025-12-14 12:52:14","","","","","0","","","","","","","","","");
INSERT INTO `land_records` VALUES("20","8","LR007/2025","","Uasin Gishu, Eldoret","","0.50","Big tree","","acres","","","active","","2025-12-14 13:20:38","2025-12-14 13:25:26","","","1.56878000","39.09977800","0","","","","","","","","","");
INSERT INTO `land_records` VALUES("21","8","LR008/2025","","Nairobi, Kileleshwa","","25.00","","uploads/lands/land_693f8f3bc5e233.31158543_4561002_0.72421900_1760970792.pdf","acres","","","","document not valid","2025-12-15 07:31:55","2025-12-15 07:34:52","","","","","0","","","","","","","","","");

DROP TABLE IF EXISTS `legal_documents`;

CREATE TABLE `legal_documents` (
  `legal_doc_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`legal_doc_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_template_id` (`template_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `listing_images`;

CREATE TABLE `listing_images` (
  `image_id` int(11) NOT NULL AUTO_INCREMENT,
  `listing_id` int(11) NOT NULL,
  `image_path` varchar(500) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`image_id`),
  KEY `listing_id` (`listing_id`),
  CONSTRAINT `listing_images_ibfk_1` FOREIGN KEY (`listing_id`) REFERENCES `land_listings` (`listing_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `listing_requests`;

CREATE TABLE `listing_requests` (
  `request_id` int(11) NOT NULL AUTO_INCREMENT,
  `listing_id` int(11) NOT NULL,
  `buyer_id` int(11) DEFAULT NULL,
  `buyer_email` varchar(255) DEFAULT NULL,
  `buyer_name` varchar(255) DEFAULT NULL,
  `buyer_phone` varchar(20) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `offer_price` decimal(15,2) DEFAULT NULL,
  `status` enum('pending','under_review','approved','rejected','completed') DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  KEY `listing_id` (`listing_id`),
  KEY `buyer_id` (`buyer_id`),
  KEY `reviewed_by` (`reviewed_by`),
  CONSTRAINT `listing_requests_ibfk_1` FOREIGN KEY (`listing_id`) REFERENCES `land_listings` (`listing_id`),
  CONSTRAINT `listing_requests_ibfk_2` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `listing_requests_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `login_history`;

CREATE TABLE `login_history` (
  `login_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `logout_time` timestamp NULL DEFAULT NULL,
  `success` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`login_id`),
  KEY `idx_user_login` (`user_id`,`login_time`),
  KEY `idx_login_time` (`login_time`),
  KEY `idx_login_composite` (`user_id`,`login_time`),
  CONSTRAINT `login_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `newsletter_subscribers`;

CREATE TABLE `newsletter_subscribers` (
  `subscriber_id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `subscribed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `unsubscribed_at` timestamp NULL DEFAULT NULL,
  `preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferences`)),
  PRIMARY KEY (`subscriber_id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `newsletter_subscribers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `newsletter_subscribers` VALUES("1","tonnyodhiambo49@gmail.com","Tonny Odhiambo","","1","2025-12-07 11:20:44","","");

DROP TABLE IF EXISTS `notifications`;

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `type` enum('info','alert','reminder','success','warning','error') DEFAULT 'info',
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `action_url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_archived` tinyint(1) DEFAULT 0,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL,
  `related_entity_id` int(11) DEFAULT NULL,
  `related_entity_type` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`notification_id`),
  KEY `idx_user_notifications` (`user_id`,`is_read`,`sent_at`),
  KEY `idx_type` (`type`),
  KEY `idx_priority` (`priority`),
  KEY `idx_notifications_composite` (`user_id`,`is_read`,`sent_at`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `notifications` VALUES("3","10","New Land Registration","New land registration: LR003/2025 by Tonny Odhiambo","","medium","","0","2025-12-12 22:42:12","0","2025-12-12 22:42:12","","10","land");
INSERT INTO `notifications` VALUES("4","10","New Land Registration","New land registration: LR002/2025 by Luvuze Manase","","medium","","0","2025-12-12 22:42:56","0","2025-12-12 22:42:56","","11","land");
INSERT INTO `notifications` VALUES("5","10","New Transfer Request","New transfer request: LR001/2025 from Tonny Odhiambo to Luvuze Manase","","medium","","0","2025-12-12 23:01:51","0","2025-12-12 23:01:51","","1","transfer");
INSERT INTO `notifications` VALUES("6","8","Transfer Initiated","You have initiated transfer of LR001/2025 to Luvuze Manase. Transfer ID: 1","info","medium","","0","2025-12-12 23:01:52","0","2025-12-12 23:01:52","","1","transfer");
INSERT INTO `notifications` VALUES("7","11","Transfer Request","Tonny Odhiambo has initiated transfer of LR001/2025 to you. Transfer ID: 1","info","medium","","0","2025-12-12 23:01:52","0","2025-12-12 23:01:52","","1","transfer");
INSERT INTO `notifications` VALUES("8","10","New Transfer Request","Tonny Odhiambo has initiated transfer of LR001/2025 to Luvuze Manase. Transfer ID: 1","info","medium","","0","2025-12-12 23:01:52","0","2025-12-12 23:01:52","","1","transfer");
INSERT INTO `notifications` VALUES("9","11","Transfer Approved","Your land ownership transfer has been approved. You are now the owner of the land parcel.","success","medium","","0","2025-12-13 10:22:15","0","2025-12-13 10:22:15","","","");
INSERT INTO `notifications` VALUES("10","8","Transfer Completed","Your land ownership transfer has been completed. You are no longer the owner of the land parcel.","info","medium","","0","2025-12-13 10:22:15","0","2025-12-13 10:22:15","","","");
INSERT INTO `notifications` VALUES("11","10","New Transfer Request","New transfer request: LR002/2025 from Luvuze Manase to Tonny Odhiambo","","medium","","0","2025-12-13 14:30:56","0","2025-12-13 14:30:56","","3","transfer");
INSERT INTO `notifications` VALUES("12","11","Transfer Initiated","You have initiated transfer of LR002/2025 to Tonny Odhiambo. Transfer ID: 3","info","medium","","0","2025-12-13 14:30:56","0","2025-12-13 14:30:56","","3","transfer");
INSERT INTO `notifications` VALUES("13","8","Transfer Request","Luvuze Manase has initiated transfer of LR002/2025 to you. Transfer ID: 3","info","medium","","0","2025-12-13 14:30:57","0","2025-12-13 14:30:57","","3","transfer");
INSERT INTO `notifications` VALUES("14","10","New Transfer Request","Luvuze Manase has initiated transfer of LR002/2025 to Tonny Odhiambo. Transfer ID: 3","info","medium","","0","2025-12-13 14:30:58","0","2025-12-13 14:30:58","","3","transfer");
INSERT INTO `notifications` VALUES("15","8","Transfer Approved","Your land ownership transfer has been approved. You are now the owner of the land parcel.","success","medium","","0","2025-12-13 14:34:56","0","2025-12-13 14:34:56","","","");
INSERT INTO `notifications` VALUES("16","11","Transfer Completed","Your land ownership transfer has been completed. You are no longer the owner of the land parcel.","info","medium","","0","2025-12-13 14:34:56","0","2025-12-13 14:34:56","","","");
INSERT INTO `notifications` VALUES("17","10","New Land Registration","New land registration: LR004/2025 by Tonny Odhiambo","","medium","","0","2025-12-14 10:33:41","0","2025-12-14 10:33:41","","15","land");
INSERT INTO `notifications` VALUES("18","10","New Transfer Request","New partial transfer: 20 acres from LR004/2025 (Tonny Odhiambo to Luvuze Manase)","","medium","","0","2025-12-14 10:50:19","0","2025-12-14 10:50:19","","4","transfer");
INSERT INTO `notifications` VALUES("19","8","Transfer Initiated","You have initiated partial transfer of 20 acres from LR004/2025 to Luvuze Manase. New parcel: LR004/2025-SPLIT-20251214-10bed0. Transfer ID: 4","info","medium","","0","2025-12-14 10:50:20","0","2025-12-14 10:50:20","","4","transfer");
INSERT INTO `notifications` VALUES("20","11","Transfer Request","Tonny Odhiambo has initiated partial transfer of 20 acres from LR004/2025 to you. New parcel: LR004/2025-SPLIT-20251214-10bed0. Transfer ID: 4","info","medium","","0","2025-12-14 10:50:21","0","2025-12-14 10:50:21","","4","transfer");
INSERT INTO `notifications` VALUES("21","10","New Transfer Request","Tonny Odhiambo has initiated partial transfer of 20 acres from LR004/2025 to Luvuze Manase. New parcel: LR004/2025-SPLIT-20251214-10bed0. Transfer ID: 4","info","medium","","0","2025-12-14 10:50:21","0","2025-12-14 10:50:21","","4","transfer");
INSERT INTO `notifications` VALUES("22","11","Transfer Approved","Your land ownership transfer has been approved. You are now the owner of the land parcel.","success","medium","","0","2025-12-14 10:53:21","0","2025-12-14 10:53:21","","","");
INSERT INTO `notifications` VALUES("23","8","Transfer Completed","Your land ownership transfer has been completed. You are no longer the owner of the land parcel.","info","medium","","0","2025-12-14 10:53:21","0","2025-12-14 10:53:21","","","");
INSERT INTO `notifications` VALUES("24","10","New Land Registration","New land registration: LR005/2025 by Amina Habib","","medium","","0","2025-12-14 11:34:56","0","2025-12-14 11:34:56","","17","land");
INSERT INTO `notifications` VALUES("25","10","New Transfer Request","New partial transfer: 10 acres from LR005/2025 (Amina Habib to Tonny Odhiambo)","","medium","","0","2025-12-14 11:46:18","0","2025-12-14 11:46:18","","5","transfer");
INSERT INTO `notifications` VALUES("26","12","Transfer Initiated","You have initiated partial transfer of 10 acres from LR005/2025 to Tonny Odhiambo. New parcel: LR005/2025-SPLIT-20251214-f745a9. Transfer ID: 5","info","medium","","0","2025-12-14 11:46:18","0","2025-12-14 11:46:18","","5","transfer");
INSERT INTO `notifications` VALUES("27","8","Transfer Request","Amina Habib has initiated partial transfer of 10 acres from LR005/2025 to you. New parcel: LR005/2025-SPLIT-20251214-f745a9. Transfer ID: 5","info","medium","","0","2025-12-14 11:46:18","0","2025-12-14 11:46:18","","5","transfer");
INSERT INTO `notifications` VALUES("28","10","New Transfer Request","Amina Habib has initiated partial transfer of 10 acres from LR005/2025 to Tonny Odhiambo. New parcel: LR005/2025-SPLIT-20251214-f745a9. Transfer ID: 5","info","medium","","0","2025-12-14 11:46:19","0","2025-12-14 11:46:19","","5","transfer");
INSERT INTO `notifications` VALUES("29","8","Transfer Approved","Your land ownership transfer has been approved. You are now the owner of the land parcel.","success","medium","","0","2025-12-14 11:49:17","0","2025-12-14 11:49:17","","","");
INSERT INTO `notifications` VALUES("30","12","Transfer Completed","Your land ownership transfer has been completed. You are no longer the owner of the land parcel.","info","medium","","0","2025-12-14 11:49:17","0","2025-12-14 11:49:17","","","");
INSERT INTO `notifications` VALUES("31","10","New Land Registration","New land registration: LR006/2025 by Jefra Owino","","medium","","0","2025-12-14 12:49:34","0","2025-12-14 12:49:34","","19","land");
INSERT INTO `notifications` VALUES("32","10","New Land Registration","New land registration: LR007/2025 by Tonny Odhiambo","","medium","","0","2025-12-14 13:20:38","0","2025-12-14 13:20:38","","20","land");
INSERT INTO `notifications` VALUES("33","10","New Land Registration","New land registration: LR008/2025 by Tonny Odhiambo","","medium","","0","2025-12-15 07:31:55","0","2025-12-15 07:31:55","","21","land");

DROP TABLE IF EXISTS `ownership_history`;

CREATE TABLE `ownership_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `record_id` int(11) NOT NULL,
  `from_user_id` int(11) DEFAULT NULL,
  `to_user_id` int(11) NOT NULL,
  `transfer_type` enum('registration','transfer','partial_transfer','inheritance','court_order','other') DEFAULT 'transfer',
  `transfer_id` int(11) DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `effective_date` date NOT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `recorded_by` int(11) DEFAULT NULL,
  `previous_size` decimal(10,2) DEFAULT NULL,
  `new_size` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`history_id`),
  KEY `from_user_id` (`from_user_id`),
  KEY `recorded_by` (`recorded_by`),
  KEY `idx_record_id` (`record_id`),
  KEY `idx_user_id` (`to_user_id`),
  KEY `idx_effective_date` (`effective_date`),
  CONSTRAINT `ownership_history_ibfk_1` FOREIGN KEY (`record_id`) REFERENCES `land_records` (`record_id`) ON DELETE CASCADE,
  CONSTRAINT `ownership_history_ibfk_2` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `ownership_history_ibfk_3` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `ownership_history_ibfk_4` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `ownership_history` VALUES("4","16","8","11","partial_transfer","4","","","2025-12-14","2025-12-14 10:53:15","10","80.00","20.00");
INSERT INTO `ownership_history` VALUES("5","18","12","8","partial_transfer","5","","","2025-12-14","2025-12-14 11:49:14","10","40.00","10.00");

DROP TABLE IF EXISTS `ownership_transfers`;

CREATE TABLE `ownership_transfers` (
  `transfer_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `completed_at` timestamp NULL DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `transfer_completed` tinyint(1) DEFAULT 0,
  `completion_date` datetime DEFAULT NULL,
  `reference_no` varchar(50) NOT NULL,
  `price` decimal(15,2) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `is_partial_transfer` tinyint(1) DEFAULT 0,
  `transferred_size` decimal(10,2) DEFAULT NULL,
  `remaining_size` decimal(10,2) DEFAULT NULL,
  `new_parcel_no` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`transfer_id`),
  UNIQUE KEY `reference_no` (`reference_no`),
  KEY `reviewed_by` (`reviewed_by`),
  KEY `approved_by` (`approved_by`),
  KEY `idx_record` (`record_id`),
  KEY `idx_from_user` (`from_user_id`),
  KEY `idx_to_user` (`to_user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_transfer_date` (`transfer_date`),
  CONSTRAINT `ownership_transfers_ibfk_1` FOREIGN KEY (`record_id`) REFERENCES `land_records` (`record_id`) ON DELETE CASCADE,
  CONSTRAINT `ownership_transfers_ibfk_2` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `ownership_transfers_ibfk_3` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `ownership_transfers_ibfk_4` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `ownership_transfers_ibfk_5` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `ownership_transfers` VALUES("1","1","8","11","gift","","","KES","","approved","2025-12-12 23:01:51","10","2025-12-13 10:22:14","","","","2025-12-13 10:22:15","","0","","","0.00","","0","","","");
INSERT INTO `ownership_transfers` VALUES("3","11","11","8","sale","","","KES","","approved","2025-12-13 14:30:56","10","2025-12-13 14:34:55","","","","2025-12-13 14:34:55","","0","","TRF-20251213-000011-6069","200000.00","","0","","","");
INSERT INTO `ownership_transfers` VALUES("4","16","8","11","inheritance","","","KES","","approved","2025-12-14 10:50:19","10","2025-12-14 10:53:15","","","","2025-12-14 10:53:20","","0","","TRF-20251214-000015-2159","0.00","","1","20.00","80.00","LR004/2025-SPLIT-20251214-10bed0");
INSERT INTO `ownership_transfers` VALUES("5","18","12","8","gift","","","KES","","approved","2025-12-14 11:46:16","10","2025-12-14 11:49:14","","","","2025-12-14 11:49:17","","0","","TRF-20251214-000017-9679","0.00","","1","10.00","40.00","LR005/2025-SPLIT-20251214-f745a9");

DROP TABLE IF EXISTS `password_resets`;

CREATE TABLE `password_resets` (
  `reset_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`reset_id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_token` (`token`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `pending_verifications`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `pending_verifications` AS select `u`.`user_id` AS `user_id`,`u`.`name` AS `name`,`u`.`email` AS `email`,`u`.`phone` AS `phone`,`u`.`county` AS `county`,`u`.`created_at` AS `created_at`,`ev`.`token` AS `token`,`ev`.`expires_at` AS `expires_at` from (`users` `u` left join `email_verifications` `ev` on(`u`.`user_id` = `ev`.`user_id`)) where `u`.`is_verified` = 0 and `u`.`is_active` = 1 and (`ev`.`verified_at` is null or `ev`.`expires_at` > current_timestamp());

INSERT INTO `pending_verifications` VALUES("8","Tonny Odhiambo","tonnyodhiambo49@gmail.com","0792069328","Kakamega","2025-12-07 11:32:54","","");
INSERT INTO `pending_verifications` VALUES("10","Tonny Odhiambo","tonnyodhiambo707@gmail.com","","","2025-12-08 20:32:55","","");
INSERT INTO `pending_verifications` VALUES("11","Luvuze Manase","luvuzemanase@gmail.com","0785865788","Nakuru","2025-12-12 20:36:16","","");
INSERT INTO `pending_verifications` VALUES("12","Amina Habib","amina.fuad.habib2@gmail.com","0743393301","Bungoma","2025-12-14 11:33:04","","");
INSERT INTO `pending_verifications` VALUES("13","Jefra Owino","jefraowino@gmail.com","0713662543","Kisumu","2025-12-14 12:48:31","","");

DROP TABLE IF EXISTS `security_logs`;

CREATE TABLE `security_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `details` text DEFAULT NULL,
  `status` enum('success','failed','warning') DEFAULT 'success',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `idx_user_action` (`user_id`,`action_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `system_logs`;

CREATE TABLE `system_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `level` enum('info','warning','error','critical') NOT NULL DEFAULT 'info',
  `source` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `idx_level` (`level`),
  KEY `idx_source` (`source`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `system_settings`;

CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','number','boolean','json','array') DEFAULT 'string',
  `category` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `updated_by` (`updated_by`),
  KEY `idx_setting_key` (`setting_key`),
  KEY `idx_category` (`category`),
  CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `system_settings` VALUES("1","site_name","ArdhiYetu","string","general","Website name","1","2025-12-07 10:11:05","");
INSERT INTO `system_settings` VALUES("2","site_description","Digital Land Management System","string","general","Website description","1","2025-12-07 10:11:05","");
INSERT INTO `system_settings` VALUES("3","maintenance_mode","0","boolean","system","Enable maintenance mode","0","2025-12-07 10:11:05","");
INSERT INTO `system_settings` VALUES("4","user_registration","1","boolean","user","Allow user registration","1","2025-12-07 10:11:05","");
INSERT INTO `system_settings` VALUES("5","email_verification","1","boolean","user","Require email verification","1","2025-12-07 10:11:05","");
INSERT INTO `system_settings` VALUES("6","max_login_attempts","5","number","security","Maximum failed login attempts","0","2025-12-07 10:11:05","");
INSERT INTO `system_settings` VALUES("7","session_timeout","30","number","security","Session timeout in minutes","0","2025-12-07 10:11:05","");
INSERT INTO `system_settings` VALUES("8","default_user_role","user","string","user","Default role for new users","0","2025-12-07 10:11:05","");
INSERT INTO `system_settings` VALUES("9","min_password_length","8","number","security","Minimum password length","1","2025-12-07 10:11:05","");
INSERT INTO `system_settings` VALUES("10","require_strong_password","1","boolean","security","Require strong passwords","1","2025-12-07 10:11:05","");

DROP TABLE IF EXISTS `user_activities`;

CREATE TABLE `user_activities` (
  `activity_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`activity_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_activities_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=326 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `user_activities` VALUES("5","8","registration","New user registered","","","2025-12-07 11:32:54");
INSERT INTO `user_activities` VALUES("6","8","login","User logged in","","","2025-12-07 11:33:05");
INSERT INTO `user_activities` VALUES("7","8","login","User logged in","","","2025-12-07 11:33:06");
INSERT INTO `user_activities` VALUES("8","8","logout","User logged out","","","2025-12-07 11:42:55");
INSERT INTO `user_activities` VALUES("9","8","login","User logged in","","","2025-12-07 11:48:34");
INSERT INTO `user_activities` VALUES("10","8","login","User logged in","","","2025-12-07 11:48:35");
INSERT INTO `user_activities` VALUES("11","8","logout","User logged out","","","2025-12-07 12:19:06");
INSERT INTO `user_activities` VALUES("12","8","login","User logged in","","","2025-12-07 12:21:31");
INSERT INTO `user_activities` VALUES("13","8","login","User logged in","","","2025-12-07 12:21:31");
INSERT INTO `user_activities` VALUES("14","8","logout","User logged out","","","2025-12-07 12:40:15");
INSERT INTO `user_activities` VALUES("15","8","login","User logged in","","","2025-12-07 12:40:31");
INSERT INTO `user_activities` VALUES("16","8","login","User logged in","","","2025-12-07 12:40:31");
INSERT INTO `user_activities` VALUES("17","8","logout","User logged out","","","2025-12-07 13:17:23");
INSERT INTO `user_activities` VALUES("18","8","login","User logged in","","","2025-12-07 13:19:25");
INSERT INTO `user_activities` VALUES("19","8","login","User logged in","","","2025-12-07 13:19:25");
INSERT INTO `user_activities` VALUES("20","8","logout","User logged out","","","2025-12-07 13:19:33");
INSERT INTO `user_activities` VALUES("21","8","login","User logged in","","","2025-12-07 13:20:20");
INSERT INTO `user_activities` VALUES("22","8","login","User logged in","","","2025-12-07 13:20:21");
INSERT INTO `user_activities` VALUES("23","8","login","User logged in","","","2025-12-07 21:39:01");
INSERT INTO `user_activities` VALUES("24","8","login","User logged in","","","2025-12-07 21:39:02");
INSERT INTO `user_activities` VALUES("25","8","land_registration","Registered new land: LR001/2025","","","2025-12-07 22:50:35");
INSERT INTO `user_activities` VALUES("26","8","logout","User logged out","","","2025-12-07 23:16:37");
INSERT INTO `user_activities` VALUES("27","8","login","User logged in","","","2025-12-07 23:18:00");
INSERT INTO `user_activities` VALUES("28","8","login","User logged in","","","2025-12-07 23:18:00");
INSERT INTO `user_activities` VALUES("29","8","logout","User logged out","","","2025-12-07 23:18:41");
INSERT INTO `user_activities` VALUES("30","8","login","User logged in","","","2025-12-07 23:21:52");
INSERT INTO `user_activities` VALUES("31","8","login","User logged in","","","2025-12-07 23:21:53");
INSERT INTO `user_activities` VALUES("32","8","logout","User logged out","","","2025-12-08 00:52:13");
INSERT INTO `user_activities` VALUES("34","8","login","User logged in","","","2025-12-08 20:41:57");
INSERT INTO `user_activities` VALUES("35","8","login","User logged in","","","2025-12-08 20:41:57");
INSERT INTO `user_activities` VALUES("36","8","logout","User logged out","","","2025-12-08 20:42:05");
INSERT INTO `user_activities` VALUES("37","10","login","User logged in","","","2025-12-08 20:42:21");
INSERT INTO `user_activities` VALUES("38","10","login","User logged in","","","2025-12-08 20:42:21");
INSERT INTO `user_activities` VALUES("39","10","logout","User logged out","","","2025-12-08 20:48:10");
INSERT INTO `user_activities` VALUES("40","8","login","User logged in","","","2025-12-08 23:12:25");
INSERT INTO `user_activities` VALUES("41","8","login","User logged in","","","2025-12-08 23:12:25");
INSERT INTO `user_activities` VALUES("42","8","logout","User logged out","","","2025-12-08 23:12:38");
INSERT INTO `user_activities` VALUES("43","10","login","User logged in","","","2025-12-08 23:51:24");
INSERT INTO `user_activities` VALUES("44","10","login","User logged in","","","2025-12-08 23:51:24");
INSERT INTO `user_activities` VALUES("45","10","logout","User logged out","","","2025-12-09 11:42:29");
INSERT INTO `user_activities` VALUES("46","8","login","User logged in","","","2025-12-09 13:30:31");
INSERT INTO `user_activities` VALUES("47","8","login","User logged in","","","2025-12-09 13:30:32");
INSERT INTO `user_activities` VALUES("48","8","logout","User logged out","","","2025-12-09 13:30:47");
INSERT INTO `user_activities` VALUES("49","10","login","User logged in","","","2025-12-09 13:31:06");
INSERT INTO `user_activities` VALUES("50","10","login","User logged in","","","2025-12-09 13:31:06");
INSERT INTO `user_activities` VALUES("51","10","logout","User logged out","","","2025-12-09 13:32:01");
INSERT INTO `user_activities` VALUES("52","8","login","User logged in","","","2025-12-09 13:32:18");
INSERT INTO `user_activities` VALUES("53","8","login","User logged in","","","2025-12-09 13:32:18");
INSERT INTO `user_activities` VALUES("54","8","logout","User logged out","","","2025-12-09 13:32:23");
INSERT INTO `user_activities` VALUES("55","10","failed_login","Failed login attempt","","","2025-12-09 13:32:42");
INSERT INTO `user_activities` VALUES("56","10","login","User logged in","","","2025-12-09 13:32:51");
INSERT INTO `user_activities` VALUES("57","10","login","User logged in","","","2025-12-09 13:32:51");
INSERT INTO `user_activities` VALUES("58","10","logout","User logged out","","","2025-12-09 14:19:54");
INSERT INTO `user_activities` VALUES("59","10","failed_login","Failed login attempt","","","2025-12-09 15:23:46");
INSERT INTO `user_activities` VALUES("60","10","failed_login","Failed login attempt","","","2025-12-09 15:23:57");
INSERT INTO `user_activities` VALUES("61","10","login","User logged in","","","2025-12-09 15:24:05");
INSERT INTO `user_activities` VALUES("62","10","login","User logged in","","","2025-12-09 15:24:05");
INSERT INTO `user_activities` VALUES("63","10","logout","User logged out","","","2025-12-10 15:08:27");
INSERT INTO `user_activities` VALUES("64","8","login","User logged in","","","2025-12-10 15:37:53");
INSERT INTO `user_activities` VALUES("65","8","login","User logged in","","","2025-12-10 15:37:54");
INSERT INTO `user_activities` VALUES("66","8","logout","User logged out","","","2025-12-10 15:38:36");
INSERT INTO `user_activities` VALUES("67","8","login","User logged in","","","2025-12-10 15:38:54");
INSERT INTO `user_activities` VALUES("68","8","login","User logged in","","","2025-12-10 15:38:54");
INSERT INTO `user_activities` VALUES("69","8","logout","User logged out","","","2025-12-10 15:39:08");
INSERT INTO `user_activities` VALUES("70","8","failed_login","Failed login attempt","","","2025-12-10 18:43:15");
INSERT INTO `user_activities` VALUES("71","8","login","User logged in","","","2025-12-10 18:43:26");
INSERT INTO `user_activities` VALUES("72","8","login","User logged in","","","2025-12-10 18:43:26");
INSERT INTO `user_activities` VALUES("73","8","contact_form","Submitted contact form","","","2025-12-10 18:52:25");
INSERT INTO `user_activities` VALUES("74","8","login","User logged in","","","2025-12-11 17:56:59");
INSERT INTO `user_activities` VALUES("75","8","login","User logged in","","","2025-12-11 17:57:00");
INSERT INTO `user_activities` VALUES("76","8","logout","User logged out","","","2025-12-11 18:57:45");
INSERT INTO `user_activities` VALUES("77","10","login","User logged in","","","2025-12-11 18:58:02");
INSERT INTO `user_activities` VALUES("78","10","login","User logged in","","","2025-12-11 18:58:03");
INSERT INTO `user_activities` VALUES("79","10","land_approve","Land record ID 1 approved","","","2025-12-11 19:19:32");
INSERT INTO `user_activities` VALUES("80","10","logout","User logged out","","","2025-12-11 19:19:47");
INSERT INTO `user_activities` VALUES("81","8","login","User logged in","","","2025-12-11 19:19:57");
INSERT INTO `user_activities` VALUES("82","8","login","User logged in","","","2025-12-11 19:19:58");
INSERT INTO `user_activities` VALUES("83","8","login","User logged in","","","2025-12-12 15:53:42");
INSERT INTO `user_activities` VALUES("84","8","login","User logged in","","","2025-12-12 15:53:44");
INSERT INTO `user_activities` VALUES("85","8","logout","User logged out","","","2025-12-12 15:59:26");
INSERT INTO `user_activities` VALUES("86","10","login","User logged in","","","2025-12-12 15:59:40");
INSERT INTO `user_activities` VALUES("87","10","login","User logged in","","","2025-12-12 15:59:41");
INSERT INTO `user_activities` VALUES("88","10","logout","User logged out","","","2025-12-12 16:00:56");
INSERT INTO `user_activities` VALUES("89","8","login","User logged in","","","2025-12-12 16:01:21");
INSERT INTO `user_activities` VALUES("90","8","login","User logged in","","","2025-12-12 16:01:21");
INSERT INTO `user_activities` VALUES("91","8","logout","User logged out","","","2025-12-12 16:01:29");
INSERT INTO `user_activities` VALUES("92","8","login","User logged in","","","2025-12-12 16:01:48");
INSERT INTO `user_activities` VALUES("93","8","login","User logged in","","","2025-12-12 16:01:49");
INSERT INTO `user_activities` VALUES("94","8","logout","User logged out","","","2025-12-12 16:01:59");
INSERT INTO `user_activities` VALUES("95","11","registration","New user registered","","","2025-12-12 20:36:17");
INSERT INTO `user_activities` VALUES("96","11","login","User logged in","","","2025-12-12 20:36:39");
INSERT INTO `user_activities` VALUES("97","11","login","User logged in","","","2025-12-12 20:36:39");
INSERT INTO `user_activities` VALUES("98","11","logout","User logged out","","","2025-12-12 20:41:21");
INSERT INTO `user_activities` VALUES("99","10","login","User logged in","","","2025-12-12 20:41:40");
INSERT INTO `user_activities` VALUES("100","10","login","User logged in","","","2025-12-12 20:41:40");
INSERT INTO `user_activities` VALUES("101","10","logout","User logged out","","","2025-12-12 20:44:30");
INSERT INTO `user_activities` VALUES("102","8","login","User logged in","","","2025-12-12 20:44:42");
INSERT INTO `user_activities` VALUES("103","8","login","User logged in","","","2025-12-12 20:44:42");
INSERT INTO `user_activities` VALUES("104","8","logout","User logged out","","","2025-12-12 21:17:02");
INSERT INTO `user_activities` VALUES("105","11","login","User logged in","","","2025-12-12 21:17:17");
INSERT INTO `user_activities` VALUES("106","11","login","User logged in","","","2025-12-12 21:17:17");
INSERT INTO `user_activities` VALUES("107","11","logout","User logged out","","","2025-12-12 21:30:12");
INSERT INTO `user_activities` VALUES("108","10","failed_login","Failed login attempt","","","2025-12-12 21:30:35");
INSERT INTO `user_activities` VALUES("109","10","login","User logged in","","","2025-12-12 21:30:49");
INSERT INTO `user_activities` VALUES("110","10","login","User logged in","","","2025-12-12 21:30:49");
INSERT INTO `user_activities` VALUES("111","10","logout","User logged out","","","2025-12-12 21:31:32");
INSERT INTO `user_activities` VALUES("112","11","login","User logged in","","","2025-12-12 21:31:44");
INSERT INTO `user_activities` VALUES("113","11","login","User logged in","","","2025-12-12 21:31:44");
INSERT INTO `user_activities` VALUES("114","11","logout","User logged out","","","2025-12-12 21:32:29");
INSERT INTO `user_activities` VALUES("115","8","login","User logged in","","","2025-12-12 21:32:37");
INSERT INTO `user_activities` VALUES("116","8","login","User logged in","","","2025-12-12 21:32:37");
INSERT INTO `user_activities` VALUES("117","8","land_registration","Registered new land: LR003/2025","","","2025-12-12 22:42:12");
INSERT INTO `user_activities` VALUES("118","8","logout","User logged out","","","2025-12-12 22:42:27");
INSERT INTO `user_activities` VALUES("119","11","login","User logged in","","","2025-12-12 22:42:39");
INSERT INTO `user_activities` VALUES("120","11","login","User logged in","","","2025-12-12 22:42:40");
INSERT INTO `user_activities` VALUES("121","11","land_registration","Registered new land: LR002/2025","","","2025-12-12 22:42:56");
INSERT INTO `user_activities` VALUES("122","11","logout","User logged out","","","2025-12-12 22:43:00");
INSERT INTO `user_activities` VALUES("123","10","failed_login","Failed login attempt","","","2025-12-12 22:43:40");
INSERT INTO `user_activities` VALUES("124","10","login","User logged in","","","2025-12-12 22:43:48");
INSERT INTO `user_activities` VALUES("125","10","login","User logged in","","","2025-12-12 22:43:48");
INSERT INTO `user_activities` VALUES("126","10","land_approve","Land record ID 11 approved","","","2025-12-12 22:44:13");
INSERT INTO `user_activities` VALUES("127","10","land_approve","Land record ID 10 approved","","","2025-12-12 22:44:26");
INSERT INTO `user_activities` VALUES("128","10","logout","User logged out","","","2025-12-12 22:44:35");
INSERT INTO `user_activities` VALUES("129","8","login","User logged in","","","2025-12-12 22:44:49");
INSERT INTO `user_activities` VALUES("130","8","login","User logged in","","","2025-12-12 22:44:49");
INSERT INTO `user_activities` VALUES("131","8","transfer_initiate","Initiated transfer: ID 1","","","2025-12-12 23:01:52");
INSERT INTO `user_activities` VALUES("132","8","logout","User logged out","","","2025-12-12 23:02:29");
INSERT INTO `user_activities` VALUES("133","10","login","User logged in","","","2025-12-12 23:02:43");
INSERT INTO `user_activities` VALUES("134","10","login","User logged in","","","2025-12-12 23:02:43");
INSERT INTO `user_activities` VALUES("135","10","land_approve","Land record ID 1 approved","","","2025-12-13 09:15:24");
INSERT INTO `user_activities` VALUES("136","10","logout","User logged out","","","2025-12-13 10:22:48");
INSERT INTO `user_activities` VALUES("137","11","failed_login","Failed login attempt","","","2025-12-13 10:24:48");
INSERT INTO `user_activities` VALUES("138","11","login","User logged in","","","2025-12-13 10:25:03");
INSERT INTO `user_activities` VALUES("139","11","login","User logged in","","","2025-12-13 10:25:03");
INSERT INTO `user_activities` VALUES("140","11","logout","User logged out","","","2025-12-13 11:05:10");
INSERT INTO `user_activities` VALUES("141","8","login","User logged in","","","2025-12-13 11:05:21");
INSERT INTO `user_activities` VALUES("142","8","login","User logged in","","","2025-12-13 11:05:21");
INSERT INTO `user_activities` VALUES("143","8","logout","User logged out","","","2025-12-13 12:19:21");
INSERT INTO `user_activities` VALUES("144","8","login","User logged in","","","2025-12-13 12:19:49");
INSERT INTO `user_activities` VALUES("145","8","login","User logged in","","","2025-12-13 12:19:50");
INSERT INTO `user_activities` VALUES("146","8","logout","User logged out","","","2025-12-13 12:46:45");
INSERT INTO `user_activities` VALUES("147","10","failed_login","Failed login attempt","","","2025-12-13 12:47:09");
INSERT INTO `user_activities` VALUES("148","10","failed_login","Failed login attempt","","","2025-12-13 12:47:17");
INSERT INTO `user_activities` VALUES("149","10","login","User logged in","","","2025-12-13 12:47:24");
INSERT INTO `user_activities` VALUES("150","10","login","User logged in","","","2025-12-13 12:47:24");
INSERT INTO `user_activities` VALUES("151","10","logout","User logged out","","","2025-12-13 12:58:17");
INSERT INTO `user_activities` VALUES("152","11","login","User logged in","","","2025-12-13 12:58:47");
INSERT INTO `user_activities` VALUES("153","11","login","User logged in","","","2025-12-13 12:58:47");
INSERT INTO `user_activities` VALUES("154","11","transfer_initiate","Initiated transfer: ID 3","","","2025-12-13 14:30:56");
INSERT INTO `user_activities` VALUES("155","11","logout","User logged out","","","2025-12-13 14:32:04");
INSERT INTO `user_activities` VALUES("156","8","login","User logged in","","","2025-12-13 14:32:22");
INSERT INTO `user_activities` VALUES("157","8","login","User logged in","","","2025-12-13 14:32:23");
INSERT INTO `user_activities` VALUES("158","8","logout","User logged out","","","2025-12-13 14:33:10");
INSERT INTO `user_activities` VALUES("159","10","login","User logged in","","","2025-12-13 14:33:38");
INSERT INTO `user_activities` VALUES("160","10","login","User logged in","","","2025-12-13 14:33:38");
INSERT INTO `user_activities` VALUES("161","10","logout","User logged out","","","2025-12-13 16:27:24");
INSERT INTO `user_activities` VALUES("162","8","login","User logged in","","","2025-12-13 16:27:43");
INSERT INTO `user_activities` VALUES("163","8","login","User logged in","","","2025-12-13 16:27:44");
INSERT INTO `user_activities` VALUES("164","8","logout","User logged out","","","2025-12-13 16:29:05");
INSERT INTO `user_activities` VALUES("165","8","login","User logged in","","","2025-12-13 16:29:14");
INSERT INTO `user_activities` VALUES("166","8","login","User logged in","","","2025-12-13 16:29:15");
INSERT INTO `user_activities` VALUES("167","8","logout","User logged out","","","2025-12-13 16:30:16");
INSERT INTO `user_activities` VALUES("168","10","login","User logged in","","","2025-12-13 16:30:27");
INSERT INTO `user_activities` VALUES("169","10","login","User logged in","","","2025-12-13 16:30:28");
INSERT INTO `user_activities` VALUES("170","10","logout","User logged out","","","2025-12-13 18:03:06");
INSERT INTO `user_activities` VALUES("171","10","failed_login","Failed login attempt","","","2025-12-13 22:59:29");
INSERT INTO `user_activities` VALUES("172","10","login","User logged in","","","2025-12-13 22:59:36");
INSERT INTO `user_activities` VALUES("173","10","login","User logged in","","","2025-12-13 22:59:36");
INSERT INTO `user_activities` VALUES("174","10","setting_update","Updated setting: timezone","","","2025-12-13 23:18:12");
INSERT INTO `user_activities` VALUES("175","10","setting_update","Updated setting: date_format","","","2025-12-13 23:18:12");
INSERT INTO `user_activities` VALUES("176","10","logout","User logged out","","","2025-12-13 23:19:27");
INSERT INTO `user_activities` VALUES("177","8","login","User logged in","","","2025-12-13 23:19:35");
INSERT INTO `user_activities` VALUES("178","8","login","User logged in","","","2025-12-13 23:19:35");
INSERT INTO `user_activities` VALUES("179","8","logout","User logged out","","","2025-12-14 01:16:08");
INSERT INTO `user_activities` VALUES("180","10","login","User logged in","","","2025-12-14 01:16:23");
INSERT INTO `user_activities` VALUES("181","10","login","User logged in","","","2025-12-14 01:16:23");
INSERT INTO `user_activities` VALUES("182","10","logout","User logged out","","","2025-12-14 01:18:46");
INSERT INTO `user_activities` VALUES("183","8","login","User logged in","","","2025-12-14 01:18:55");
INSERT INTO `user_activities` VALUES("184","8","login","User logged in","","","2025-12-14 01:18:55");
INSERT INTO `user_activities` VALUES("185","8","logout","User logged out","","","2025-12-14 01:52:28");
INSERT INTO `user_activities` VALUES("186","8","login","User logged in","","","2025-12-14 02:00:18");
INSERT INTO `user_activities` VALUES("187","8","login","User logged in","","","2025-12-14 02:00:18");
INSERT INTO `user_activities` VALUES("188","8","logout","User logged out","","","2025-12-14 02:19:31");
INSERT INTO `user_activities` VALUES("189","10","login","User logged in","","","2025-12-14 02:19:45");
INSERT INTO `user_activities` VALUES("190","10","login","User logged in","","","2025-12-14 02:19:45");
INSERT INTO `user_activities` VALUES("191","10","land_approve","Land record ID 11 approved","","","2025-12-14 07:47:21");
INSERT INTO `user_activities` VALUES("192","10","logout","User logged out","","","2025-12-14 07:49:23");
INSERT INTO `user_activities` VALUES("193","8","login","User logged in","","","2025-12-14 07:49:33");
INSERT INTO `user_activities` VALUES("194","8","login","User logged in","","","2025-12-14 07:49:34");
INSERT INTO `user_activities` VALUES("195","8","logout","User logged out","","","2025-12-14 08:09:02");
INSERT INTO `user_activities` VALUES("196","10","failed_login","Failed login attempt","","","2025-12-14 08:09:15");
INSERT INTO `user_activities` VALUES("197","10","login","User logged in","","","2025-12-14 08:09:22");
INSERT INTO `user_activities` VALUES("198","10","login","User logged in","","","2025-12-14 08:09:22");
INSERT INTO `user_activities` VALUES("199","10","logout","User logged out","","","2025-12-14 09:25:42");
INSERT INTO `user_activities` VALUES("200","8","login","User logged in","","","2025-12-14 09:28:40");
INSERT INTO `user_activities` VALUES("201","8","login","User logged in","","","2025-12-14 09:28:40");
INSERT INTO `user_activities` VALUES("202","8","logout","User logged out","","","2025-12-14 09:28:45");
INSERT INTO `user_activities` VALUES("203","10","login","User logged in","","","2025-12-14 09:56:06");
INSERT INTO `user_activities` VALUES("204","10","login","User logged in","","","2025-12-14 09:56:06");
INSERT INTO `user_activities` VALUES("205","10","logout","User logged out","","","2025-12-14 09:57:47");
INSERT INTO `user_activities` VALUES("206","8","login","User logged in","","","2025-12-14 09:57:55");
INSERT INTO `user_activities` VALUES("207","8","login","User logged in","","","2025-12-14 09:57:56");
INSERT INTO `user_activities` VALUES("208","8","logout","User logged out","","","2025-12-14 09:58:52");
INSERT INTO `user_activities` VALUES("209","10","login","User logged in","","","2025-12-14 09:59:14");
INSERT INTO `user_activities` VALUES("210","10","login","User logged in","","","2025-12-14 09:59:14");
INSERT INTO `user_activities` VALUES("211","10","logout","User logged out","","","2025-12-14 10:02:07");
INSERT INTO `user_activities` VALUES("212","8","login","User logged in","","","2025-12-14 10:05:58");
INSERT INTO `user_activities` VALUES("213","8","login","User logged in","","","2025-12-14 10:05:59");
INSERT INTO `user_activities` VALUES("214","8","logout","User logged out","","","2025-12-14 10:07:49");
INSERT INTO `user_activities` VALUES("215","10","login","User logged in","","","2025-12-14 10:08:21");
INSERT INTO `user_activities` VALUES("216","10","login","User logged in","","","2025-12-14 10:08:22");
INSERT INTO `user_activities` VALUES("217","10","logout","User logged out","","","2025-12-14 10:08:22");
INSERT INTO `user_activities` VALUES("218","10","login","User logged in","","","2025-12-14 10:08:45");
INSERT INTO `user_activities` VALUES("219","10","login","User logged in","","","2025-12-14 10:08:46");
INSERT INTO `user_activities` VALUES("220","10","logout","User logged out","","","2025-12-14 10:09:12");
INSERT INTO `user_activities` VALUES("221","8","login","User logged in","","","2025-12-14 10:09:21");
INSERT INTO `user_activities` VALUES("222","8","login","User logged in","","","2025-12-14 10:09:21");
INSERT INTO `user_activities` VALUES("223","8","land_registration","Registered new land: LR004/2025","","","2025-12-14 10:33:41");
INSERT INTO `user_activities` VALUES("224","8","logout","User logged out","","","2025-12-14 10:33:51");
INSERT INTO `user_activities` VALUES("225","10","login","User logged in","","","2025-12-14 10:34:08");
INSERT INTO `user_activities` VALUES("226","10","login","User logged in","","","2025-12-14 10:34:08");
INSERT INTO `user_activities` VALUES("227","10","land_approve","Land record ID 15 approved","","","2025-12-14 10:35:23");
INSERT INTO `user_activities` VALUES("228","10","logout","User logged out","","","2025-12-14 10:36:07");
INSERT INTO `user_activities` VALUES("229","8","login","User logged in","","","2025-12-14 10:36:15");
INSERT INTO `user_activities` VALUES("230","8","login","User logged in","","","2025-12-14 10:36:15");
INSERT INTO `user_activities` VALUES("231","8","transfer_initiate","Initiated partial transfer: ID 4, 20 acres from LR004/2025","","","2025-12-14 10:50:20");
INSERT INTO `user_activities` VALUES("232","8","logout","User logged out","","","2025-12-14 10:51:55");
INSERT INTO `user_activities` VALUES("233","10","login","User logged in","","","2025-12-14 10:52:12");
INSERT INTO `user_activities` VALUES("234","10","login","User logged in","","","2025-12-14 10:52:12");
INSERT INTO `user_activities` VALUES("235","10","logout","User logged out","","","2025-12-14 10:54:05");
INSERT INTO `user_activities` VALUES("236","11","login","User logged in","","","2025-12-14 10:54:26");
INSERT INTO `user_activities` VALUES("237","11","login","User logged in","","","2025-12-14 10:54:26");
INSERT INTO `user_activities` VALUES("238","11","logout","User logged out","","","2025-12-14 11:27:19");
INSERT INTO `user_activities` VALUES("239","8","login","User logged in","","","2025-12-14 11:27:31");
INSERT INTO `user_activities` VALUES("240","8","login","User logged in","","","2025-12-14 11:27:31");
INSERT INTO `user_activities` VALUES("241","8","logout","User logged out","","","2025-12-14 11:29:05");
INSERT INTO `user_activities` VALUES("242","12","registration","New user registered","","","2025-12-14 11:33:04");
INSERT INTO `user_activities` VALUES("243","12","login","User logged in","","","2025-12-14 11:33:15");
INSERT INTO `user_activities` VALUES("244","12","login","User logged in","","","2025-12-14 11:33:15");
INSERT INTO `user_activities` VALUES("245","12","land_registration","Registered new land: LR005/2025","","","2025-12-14 11:34:56");
INSERT INTO `user_activities` VALUES("246","12","logout","User logged out","","","2025-12-14 11:35:55");
INSERT INTO `user_activities` VALUES("247","10","login","User logged in","","","2025-12-14 11:36:59");
INSERT INTO `user_activities` VALUES("248","10","login","User logged in","","","2025-12-14 11:36:59");
INSERT INTO `user_activities` VALUES("249","10","land_approve","Land record ID 17 approved","","","2025-12-14 11:37:53");
INSERT INTO `user_activities` VALUES("250","10","logout","User logged out","","","2025-12-14 11:41:54");
INSERT INTO `user_activities` VALUES("251","8","login","User logged in","","","2025-12-14 11:42:04");
INSERT INTO `user_activities` VALUES("252","8","login","User logged in","","","2025-12-14 11:42:04");
INSERT INTO `user_activities` VALUES("253","8","logout","User logged out","","","2025-12-14 11:42:51");
INSERT INTO `user_activities` VALUES("254","12","login","User logged in","","","2025-12-14 11:44:48");
INSERT INTO `user_activities` VALUES("255","12","login","User logged in","","","2025-12-14 11:44:49");
INSERT INTO `user_activities` VALUES("256","12","transfer_initiate","Initiated partial transfer: ID 5, 10 acres from LR005/2025","","","2025-12-14 11:46:18");
INSERT INTO `user_activities` VALUES("257","12","logout","User logged out","","","2025-12-14 11:47:01");
INSERT INTO `user_activities` VALUES("258","10","login","User logged in","","","2025-12-14 11:47:39");
INSERT INTO `user_activities` VALUES("259","10","login","User logged in","","","2025-12-14 11:47:39");
INSERT INTO `user_activities` VALUES("260","10","logout","User logged out","","","2025-12-14 11:49:56");
INSERT INTO `user_activities` VALUES("261","8","login","User logged in","","","2025-12-14 11:50:05");
INSERT INTO `user_activities` VALUES("262","8","login","User logged in","","","2025-12-14 11:50:06");
INSERT INTO `user_activities` VALUES("263","8","logout","User logged out","","","2025-12-14 11:51:32");
INSERT INTO `user_activities` VALUES("264","12","login","User logged in","","","2025-12-14 11:51:42");
INSERT INTO `user_activities` VALUES("265","12","login","User logged in","","","2025-12-14 11:51:42");
INSERT INTO `user_activities` VALUES("266","12","logout","User logged out","","","2025-12-14 12:09:15");
INSERT INTO `user_activities` VALUES("267","12","login","User logged in","","","2025-12-14 12:21:39");
INSERT INTO `user_activities` VALUES("268","12","login","User logged in","","","2025-12-14 12:21:40");
INSERT INTO `user_activities` VALUES("269","12","certificate_generated","Generated ownership_certificate for parcel: LR005/2025","","","2025-12-14 12:22:45");
INSERT INTO `user_activities` VALUES("270","12","certificate_generated","Generated title_deed for parcel: LR005/2025","","","2025-12-14 12:31:36");
INSERT INTO `user_activities` VALUES("271","12","logout","User logged out","","","2025-12-14 12:46:11");
INSERT INTO `user_activities` VALUES("272","13","registration","New user registered","","","2025-12-14 12:48:31");
INSERT INTO `user_activities` VALUES("273","13","login","User logged in","","","2025-12-14 12:48:45");
INSERT INTO `user_activities` VALUES("274","13","login","User logged in","","","2025-12-14 12:48:45");
INSERT INTO `user_activities` VALUES("275","13","land_registration","Registered new land: LR006/2025","","","2025-12-14 12:49:34");
INSERT INTO `user_activities` VALUES("276","13","logout","User logged out","","","2025-12-14 12:49:40");
INSERT INTO `user_activities` VALUES("277","10","login","User logged in","","","2025-12-14 12:49:59");
INSERT INTO `user_activities` VALUES("278","10","login","User logged in","","","2025-12-14 12:49:59");
INSERT INTO `user_activities` VALUES("279","10","land_approve","Land record ID 19 approved","","","2025-12-14 12:52:14");
INSERT INTO `user_activities` VALUES("280","10","logout","User logged out","","","2025-12-14 12:52:40");
INSERT INTO `user_activities` VALUES("281","13","login","User logged in","","","2025-12-14 12:53:04");
INSERT INTO `user_activities` VALUES("282","13","login","User logged in","","","2025-12-14 12:53:05");
INSERT INTO `user_activities` VALUES("283","13","logout","User logged out","","","2025-12-14 12:53:20");
INSERT INTO `user_activities` VALUES("284","10","failed_login","Failed login attempt","","","2025-12-14 13:01:31");
INSERT INTO `user_activities` VALUES("285","10","login","User logged in","","","2025-12-14 13:01:48");
INSERT INTO `user_activities` VALUES("286","10","login","User logged in","","","2025-12-14 13:01:49");
INSERT INTO `user_activities` VALUES("287","10","logout","User logged out","","","2025-12-14 13:05:33");
INSERT INTO `user_activities` VALUES("288","8","login","User logged in","","","2025-12-14 13:05:51");
INSERT INTO `user_activities` VALUES("289","8","login","User logged in","","","2025-12-14 13:05:51");
INSERT INTO `user_activities` VALUES("290","8","land_registration","Registered new land: LR007/2025","","","2025-12-14 13:20:38");
INSERT INTO `user_activities` VALUES("291","8","logout","User logged out","","","2025-12-14 13:20:52");
INSERT INTO `user_activities` VALUES("292","10","login","User logged in","","","2025-12-14 13:21:07");
INSERT INTO `user_activities` VALUES("293","10","login","User logged in","","","2025-12-14 13:21:07");
INSERT INTO `user_activities` VALUES("294","10","land_approve","Land record ID 20 approved","","","2025-12-14 13:25:26");
INSERT INTO `user_activities` VALUES("295","10","logout","User logged out","","","2025-12-14 13:25:47");
INSERT INTO `user_activities` VALUES("296","8","login","User logged in","","","2025-12-14 13:26:02");
INSERT INTO `user_activities` VALUES("297","8","login","User logged in","","","2025-12-14 13:26:02");
INSERT INTO `user_activities` VALUES("298","8","logout","User logged out","","","2025-12-14 13:29:35");
INSERT INTO `user_activities` VALUES("299","10","login","User logged in","","","2025-12-14 13:29:53");
INSERT INTO `user_activities` VALUES("300","10","login","User logged in","","","2025-12-14 13:29:53");
INSERT INTO `user_activities` VALUES("301","10","logout","User logged out","","","2025-12-14 13:31:21");
INSERT INTO `user_activities` VALUES("302","8","login","User logged in","","","2025-12-14 13:31:36");
INSERT INTO `user_activities` VALUES("303","8","login","User logged in","","","2025-12-14 13:31:36");
INSERT INTO `user_activities` VALUES("304","8","logout","User logged out","","","2025-12-14 13:40:46");
INSERT INTO `user_activities` VALUES("305","10","login","User logged in","","","2025-12-14 13:41:23");
INSERT INTO `user_activities` VALUES("306","10","login","User logged in","","","2025-12-14 13:41:23");
INSERT INTO `user_activities` VALUES("307","10","logout","User logged out","","","2025-12-14 13:43:30");
INSERT INTO `user_activities` VALUES("308","8","failed_login","Failed login attempt","","","2025-12-14 13:43:41");
INSERT INTO `user_activities` VALUES("309","8","login","User logged in","","","2025-12-14 13:43:55");
INSERT INTO `user_activities` VALUES("310","8","login","User logged in","","","2025-12-14 13:43:55");
INSERT INTO `user_activities` VALUES("311","8","logout","User logged out","","","2025-12-14 13:48:42");
INSERT INTO `user_activities` VALUES("312","8","login","User logged in","","","2025-12-14 19:41:03");
INSERT INTO `user_activities` VALUES("313","8","login","User logged in","","","2025-12-14 19:41:03");
INSERT INTO `user_activities` VALUES("314","8","certificate_generated","Generated title_deed for parcel: LR002/2025","","","2025-12-14 19:43:35");
INSERT INTO `user_activities` VALUES("315","8","land_registration","Registered new land: LR008/2025","","","2025-12-15 07:31:55");
INSERT INTO `user_activities` VALUES("316","8","logout","User logged out","","","2025-12-15 07:32:06");
INSERT INTO `user_activities` VALUES("317","10","login","User logged in","","","2025-12-15 07:32:22");
INSERT INTO `user_activities` VALUES("318","10","login","User logged in","","","2025-12-15 07:32:23");
INSERT INTO `user_activities` VALUES("319","10","land_reject","Land record ID 21 rejected","","","2025-12-15 07:34:52");
INSERT INTO `user_activities` VALUES("320","10","logout","User logged out","","","2025-12-15 07:38:35");
INSERT INTO `user_activities` VALUES("321","8","login","User logged in","","","2025-12-15 07:38:44");
INSERT INTO `user_activities` VALUES("322","8","login","User logged in","","","2025-12-15 07:38:44");
INSERT INTO `user_activities` VALUES("323","8","logout","User logged out","","","2025-12-15 07:41:19");
INSERT INTO `user_activities` VALUES("324","10","login","User logged in","","","2025-12-15 07:41:34");
INSERT INTO `user_activities` VALUES("325","10","login","User logged in","","","2025-12-15 07:41:34");

DROP TABLE IF EXISTS `user_documents`;

CREATE TABLE `user_documents` (
  `document_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `document_type` enum('id_front','id_back','passport','photo','signature','other') DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(50) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11) DEFAULT NULL,
  `verification_date` timestamp NULL DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`document_id`),
  KEY `verified_by` (`verified_by`),
  KEY `idx_user_docs` (`user_id`,`document_type`),
  KEY `idx_verified` (`is_verified`),
  CONSTRAINT `user_documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `user_documents_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `user_preferences`;

CREATE TABLE `user_preferences` (
  `preference_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `preference_key` varchar(50) NOT NULL,
  `preference_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`preference_id`),
  UNIQUE KEY `unique_user_preference` (`user_id`,`preference_key`),
  KEY `idx_user_key` (`user_id`,`preference_key`),
  CONSTRAINT `user_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `user_profiles`;

CREATE TABLE `user_profiles` (
  `profile_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `last_profile_update` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`profile_id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `user_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `user_statistics`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `user_statistics` AS select `u`.`county` AS `county`,count(0) AS `total_users`,sum(case when `u`.`is_verified` = 1 then 1 else 0 end) AS `verified_users`,sum(case when `u`.`role` = 'admin' then 1 else 0 end) AS `admin_users`,sum(case when `u`.`role` = 'officer' then 1 else 0 end) AS `officer_users`,sum(case when `u`.`role` = 'user' then 1 else 0 end) AS `regular_users`,avg(`calculate_age`(`u`.`date_of_birth`)) AS `avg_age` from `users` `u` where `u`.`is_active` = 1 group by `u`.`county`;

INSERT INTO `user_statistics` VALUES("","1","0","1","0","0","");
INSERT INTO `user_statistics` VALUES("Bungoma","1","0","0","0","1","22.0000");
INSERT INTO `user_statistics` VALUES("Kakamega","1","0","0","0","1","25.0000");
INSERT INTO `user_statistics` VALUES("Kisumu","1","0","0","0","1","25.0000");
INSERT INTO `user_statistics` VALUES("Nakuru","1","0","0","0","1","18.0000");

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `id_number` (`id_number`),
  KEY `idx_email` (`email`),
  KEY `idx_phone` (`phone`),
  KEY `idx_id_number` (`id_number`),
  KEY `idx_county` (`county`),
  KEY `idx_role` (`role`),
  KEY `idx_status` (`is_active`,`is_verified`),
  KEY `idx_user_composite` (`is_active`,`is_verified`,`role`,`county`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` VALUES("8","Tonny Odhiambo","tonnyodhiambo49@gmail.com","0792069328","38969021","$2y$10$K2JlMxj.AtP7TecKZLqTte8KmjFXxGvwo8T3NnXvJqG1aodo4WBAW","2000-04-16","male","190-50100 Kakamega","Kakamega","first_car","demio","ac371dc27dccf8e01290e68b77b41922e639d3345d5f4f164883232c923602cb","1","0","","2025-12-15 07:38:44","0","","1","0","user","2025-12-07 11:32:54","2025-12-15 07:38:44");
INSERT INTO `users` VALUES("10","Tonny Odhiambo","tonnyodhiambo707@gmail.com","","","$2y$10$FqKRZ9eL2rlobWGRyaESoub5JSCMcEuOhkNg3SsscSXk6Gcmhj6Zy","","","","","","","","1","0","","2025-12-15 07:41:34","0","","1","0","admin","2025-12-08 20:32:55","2025-12-15 07:41:34");
INSERT INTO `users` VALUES("11","Luvuze Manase","luvuzemanase@gmail.com","0785865788","41280883","$2y$10$2eGlaWjY7sY58oI4pPogye5Xq.gcXqque.EeriSYhkXyVMivwCOqq","2007-12-12","other","","Nakuru","first_car","Vanguard","4b6b28ae96691634c8856693edcf449363a74034e1893f9fb25484b29b47151c","1","0","","2025-12-14 10:54:26","0","","1","0","user","2025-12-12 20:36:16","2025-12-14 10:54:26");
INSERT INTO `users` VALUES("12","Amina Habib","amina.fuad.habib2@gmail.com","0743393301","43568216","$2y$10$wrpPi9PeAP5iSTrieAdgvuZqB1hItobqI80ubB8JzzPQRLTbJozd.","2003-12-14","female","","Bungoma","birth_city","Nairobi","561b1cbccb9a45a66162a0e28bf18fe1a50609c62dbd4cde78df0bbef989a83b","1","0","","2025-12-14 12:21:39","0","","1","0","user","2025-12-14 11:33:04","2025-12-14 12:21:39");
INSERT INTO `users` VALUES("13","Jefra Owino","jefraowino@gmail.com","0713662543","40100630","$2y$10$lM/NLh.nZpQCBxMoN5yJfeaf92S/rjcdq7wFKb0K72cIhM1c4x.DC","2000-02-14","male","","Kisumu","first_car","toyota","aa08cddfc0d29fcb2829a50dce529b7be50e554e758486b1ba32f17a67a4c3b5","1","0","","2025-12-14 12:53:04","0","","1","0","user","2025-12-14 12:48:31","2025-12-14 12:53:04");

