-- HR Portal Database Schema & Sample Data
--
-- This script will:
-- 1. Create the database 'hr_portal' if it doesn't already exist.
-- 2. Drop existing tables to ensure a clean setup.
-- 3. Create all required tables for the HR Portal application.
-- 4. Define primary keys, foreign keys, and other constraints.
-- 5. Insert sample data for users, departments, leave types, etc.
-- 6. Set the password for all created users to '111'.
--

-- Create and use the database
CREATE DATABASE IF NOT EXISTS `hr_portal` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `hr_portal`;

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

--
-- Table structure for table `audit_logs`
--
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `details` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--
-- Table structure for table `attendance_logs`
--
DROP TABLE IF EXISTS `attendance_logs`;
CREATE TABLE `attendance_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `device_id` int(11) DEFAULT NULL,
  `punch_time` datetime NOT NULL,
  `status` enum('Check-In','Check-Out') NOT NULL,
  `work_state` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_manual` tinyint(1) NOT NULL DEFAULT 0,
  `is_violation` tinyint(1) NOT NULL DEFAULT 0,
  `violation_reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `device_id` (`device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Inserting sample data for `attendance_logs`
--
INSERT INTO `attendance_logs` (`user_id`, `device_id`, `punch_time`, `status`, `is_violation`) VALUES
(4, 1, CONCAT(CURDATE(), ' 08:55:00'), 'Check-In', 0),
(4, 1, CONCAT(CURDATE(), ' 17:05:00'), 'Check-Out', 0),
(5, 1, CONCAT(CURDATE(), ' 09:15:00'), 'Check-In', 0),
(5, 1, CONCAT(CURDATE(), ' 17:30:00'), 'Check-Out', 0),
(5, 1, CONCAT(CURDATE() - INTERVAL 1 DAY, ' 09:00:00'), 'Check-In', 0),
(5, 1, CONCAT(CURDATE() - INTERVAL 1 DAY, ' 17:00:00'), 'Check-Out', 0);


--
-- Table structure for table `departments`
--
DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Inserting sample data for `departments`
--
INSERT INTO `departments` (`id`, `name`) VALUES
(1, 'Management'),
(2, 'Human Resources'),
(3, 'IT'),
(4, 'Sales & Marketing');

--
-- Table structure for table `devices`
--
DROP TABLE IF EXISTS `devices`;
CREATE TABLE `devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `port` int(11) NOT NULL,
  `device_type` enum('ZKTeco','Fingertec') NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `com_key` varchar(255) DEFAULT '0',
  `last_sync` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Inserting sample data for `devices`
--
INSERT INTO `devices` (`id`, `name`, `ip_address`, `port`, `device_type`, `is_active`, `com_key`) VALUES
(1, 'Main Entrance Device', '192.168.1.201', 4370, 'ZKTeco', 1, '0');

--
-- Table structure for table `leave_balances`
--
DROP TABLE IF EXISTS `leave_balances`;
CREATE TABLE `leave_balances` (
  `user_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `balance` decimal(5,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`user_id`,`leave_type_id`),
  KEY `leave_type_id` (`leave_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Inserting sample data for `leave_balances`
--
INSERT INTO `leave_balances` (`user_id`, `leave_type_id`, `balance`) VALUES
(1, 1, 21.00), (1, 2, 7.00), (1, 3, 999.00),
(2, 1, 21.00), (2, 2, 7.00), (2, 3, 999.00),
(3, 1, 21.00), (3, 2, 7.00), (3, 3, 999.00),
(4, 1, 21.00), (4, 2, 7.00), (4, 3, 999.00),
(5, 1, 15.50), (5, 2, 5.00), (5, 3, 999.00);


--
-- Table structure for table `leave_requests`
--
DROP TABLE IF EXISTS `leave_requests`;
CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending_manager','pending_hr','approved','rejected','cancelled') NOT NULL DEFAULT 'pending_manager',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `manager_id` int(11) DEFAULT NULL,
  `hr_manager_id` int(11) DEFAULT NULL,
  `manager_action_date` datetime DEFAULT NULL,
  `hr_action_date` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `leave_type_id` (`leave_type_id`),
  KEY `manager_id` (`manager_id`),
  KEY `hr_manager_id` (`hr_manager_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Inserting sample data for `leave_requests`
--
INSERT INTO `leave_requests` (`id`, `user_id`, `leave_type_id`, `start_date`, `end_date`, `reason`, `status`, `manager_id`) VALUES
(1, 4, 1, CURDATE() + INTERVAL 10 DAY, CURDATE() + INTERVAL 11 DAY, 'Family vacation.', 'pending_manager', 3),
(2, 5, 2, CURDATE() - INTERVAL 1 DAY, CURDATE() - INTERVAL 1 DAY, 'Feeling unwell.', 'pending_hr', 3),
(3, 5, 1, CURDATE() + INTERVAL 20 DAY, CURDATE() + INTERVAL 20 DAY, 'Personal day.', 'approved', 3),
(4, 4, 1, CURDATE() + INTERVAL 5 DAY, CURDATE() + INTERVAL 5 DAY, 'Doctor appointment.', 'rejected', 3);


--
-- Table structure for table `leave_types`
--
DROP TABLE IF EXISTS `leave_types`;
CREATE TABLE `leave_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `default_balance` decimal(5,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Inserting sample data for `leave_types`
--
INSERT INTO `leave_types` (`id`, `name`, `default_balance`) VALUES
(1, 'Annual Leave', 21.00),
(2, 'Sick Leave', 7.00),
(3, 'Unpaid Leave', 999.00);


--
-- Table structure for table `notifications`
--
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--
-- Table structure for table `request_attachments`
--
DROP TABLE IF EXISTS `request_attachments`;
CREATE TABLE `request_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `request_id` (`request_id`),
  KEY `uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--
-- Table structure for table `request_comments`
--
DROP TABLE IF EXISTS `request_comments`;
CREATE TABLE `request_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `request_id` (`request_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Inserting sample data for `request_comments`
--
INSERT INTO `request_comments` (`request_id`, `user_id`, `comment`) VALUES
(2, 3, 'Approved. Please proceed with HR.'),
(3, 3, 'Approved from my side.'),
(3, 2, 'Approved. Your balance has been updated.'),
(4, 3, 'Sorry, we have critical deadlines that week. Please reschedule.');


--
-- Table structure for table `users`
--
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('employee','manager','hr_manager','admin') NOT NULL DEFAULT 'employee',
  `department_id` int(11) DEFAULT NULL,
  `direct_manager_id` int(11) DEFAULT NULL,
  `employee_code` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `employee_code` (`employee_code`),
  KEY `department_id` (`department_id`),
  KEY `direct_manager_id` (`direct_manager_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Inserting sample data for `users`
-- Note: The password hash '$2y$10$wI/h81K6u7mZJcm2I39fLuA/ugOsUaWYSJ5vYyP9f10/I1l.qMhIm' is for the password '111'.
--
INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `role`, `department_id`, `direct_manager_id`, `employee_code`, `is_active`) VALUES
(1, 'Admin User', 'admin@example.com', '$2y$10$wI/h81K6u7mZJcm2I39fLuA/ugOsUaWYSJ5vYyP9f10/I1l.qMhIm', 'admin', 1, NULL, '001', 1),
(2, 'HR Manager', 'hr@example.com', '$2y$10$wI/h81K6u7mZJcm2I39fLuA/ugOsUaWYSJ5vYyP9f10/I1l.qMhIm', 'hr_manager', 2, 1, '002', 1),
(3, 'Team Manager', 'manager@example.com', '$2y$10$wI/h81K6u7mZJcm2I39fLuA/ugOsUaWYSJ5vYyP9f10/I1l.qMhIm', 'manager', 3, 1, '003', 1),
(4, 'John Doe', 'employee1@example.com', '$2y$10$wI/h81K6u7mZJcm2I39fLuA/ugOsUaWYSJ5vYyP9f10/I1l.qMhIm', 'employee', 3, 3, '004', 1),
(5, 'Jane Smith', 'employee2@example.com', '$2y$10$wI/h81K6u7mZJcm2I39fLuA/ugOsUaWYSJ5vYyP9f10/I1l.qMhIm', 'employee', 4, 3, '005', 1);

--
-- Adding Foreign Key Constraints
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `attendance_logs`
  ADD CONSTRAINT `attendance_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_logs_ibfk_2` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE SET NULL;

ALTER TABLE `leave_balances`
  ADD CONSTRAINT `leave_balances_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_balances_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE CASCADE;

ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_requests_ibfk_3` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `leave_requests_ibfk_4` FOREIGN KEY (`hr_manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `request_attachments`
  ADD CONSTRAINT `request_attachments_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `leave_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `request_attachments_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `request_comments`
  ADD CONSTRAINT `request_comments_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `leave_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `request_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`direct_manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
  
SET foreign_key_checks = 1;
