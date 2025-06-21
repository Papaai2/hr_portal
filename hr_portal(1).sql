-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 21, 2025 at 03:16 AM
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
-- Database: `hr_portal`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance_logs`
--

CREATE TABLE `attendance_logs` (
  `id` bigint(20) NOT NULL,
  `device_id` int(11) DEFAULT NULL COMMENT 'Foreign key to devices. NULL for manual entry.',
  `employee_code` varchar(50) NOT NULL COMMENT 'The User ID from the machine',
  `punch_time` datetime NOT NULL COMMENT 'The exact date and time of the punch',
  `expected_in` time DEFAULT NULL,
  `expected_out` time DEFAULT NULL,
  `punch_state` int(11) NOT NULL COMMENT 'Standardized code: 0=Check-In, 1=Check-Out',
  `status` enum('unprocessed','corrected','error') NOT NULL DEFAULT 'unprocessed' COMMENT 'Processing status of the log entry',
  `violation_type` varchar(50) DEFAULT NULL COMMENT 'Type of violation, e.g., double_punch',
  `notes` text DEFAULT NULL COMMENT 'Notes for manual corrections or actions',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Staging table for raw and processed attendance data';

--
-- Truncate table before insert `attendance_logs`
--

TRUNCATE TABLE `attendance_logs`;
-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncate table before insert `audit_logs`
--

TRUNCATE TABLE `audit_logs`;
--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `details`, `created_at`) VALUES
(1, NULL, 'USER_LOGIN', 'User admin@example.com logged in successfully.', '2025-06-20 20:29:02'),
(2, NULL, 'USER_CREATE', 'Admin User created a new user: Marketing Specialist (marketing@example.com).', '2025-06-20 20:29:02'),
(3, 4, 'REQUEST_SUBMIT', 'User Engineering Manager submitted leave request #3.', '2025-06-20 20:29:02'),
(4, 2, 'REQUEST_REJECT', 'User HR Manager rejected leave request #4.', '2025-06-20 20:29:02'),
(5, 1, 'add_shift', '{\"name\":\"Fixed\"}', '2025-06-20 21:39:11'),
(6, 6, 'cancel_request', '{\"request_id\":5,\"status\":\"cancelled\"}', '2025-06-20 21:47:59'),
(7, 4, 'approve_request_manager', '{\"request_id\":6,\"status\":\"pending_hr\"}', '2025-06-20 21:50:03'),
(8, 4, 'add_request_comment', '{\"request_id\":6,\"comment\":\"X\"}', '2025-06-20 21:53:35'),
(9, 2, 'approve_request_hr', '{\"request_id\":6,\"status\":\"approved\"}', '2025-06-20 21:54:57'),
(10, 2, 'add_request_comment', '{\"request_id\":6,\"comment\":\"Congrats\"}', '2025-06-20 21:55:00'),
(11, 1, 'update_device', '{\"device_id\":\"2\",\"name\":\"TRy 1\",\"ip_address\":\"127.0.0.1\",\"port\":\"4370\",\"device_type\":\"ZKTeco\"}', '2025-06-20 22:06:50'),
(12, 1, 'delete_device', '{\"device_id\":3}', '2025-06-20 22:12:28'),
(13, 1, 'add_device', '{\"device_id\":\"\",\"name\":\"TRy 2\",\"ip_address\":\"127.0.0.1\",\"port\":\"4370\",\"device_brand\":\"Fingertec\"}', '2025-06-20 22:12:34'),
(14, 1, 'update_device', '{\"device_id\":\"2\",\"name\":\"TRy 1\",\"ip_address\":\"127.0.0.1\",\"port\":\"4371\",\"device_brand\":\"ZKTeco\"}', '2025-06-20 22:28:14'),
(15, 1, 'update_device', '{\"device_id\":\"4\",\"name\":\"TRy 2\",\"ip_address\":\"127.0.0.1\",\"port\":\"4371\",\"device_brand\":\"Fingertec\"}', '2025-06-20 22:33:20'),
(16, 1, 'update_device', '{\"device_id\":\"4\",\"name\":\"TRy 2\",\"ip_address\":\"127.0.0.1\",\"port\":\"4370\",\"device_brand\":\"Fingertec\"}', '2025-06-20 22:33:25'),
(17, 1, 'update_device', '{\"device_id\":\"2\",\"name\":\"TRy 1\",\"ip_address\":\"127.0.0.1\",\"port\":\"4370\",\"device_brand\":\"ZKTeco\"}', '2025-06-20 22:48:27'),
(18, 1, 'update_device', '{\"device_id\":\"2\",\"name\":\"TRy 1\",\"ip_address\":\"127.0.0.1\",\"port\":\"4371\",\"device_brand\":\"ZKTeco\"}', '2025-06-20 22:49:53'),
(19, 1, 'update_device', '{\"device_id\":\"4\",\"name\":\"TRy 2\",\"ip_address\":\"127.0.0.1\",\"port\":\"4371\",\"device_brand\":\"Fingertec\"}', '2025-06-20 22:54:41'),
(20, 1, 'update_device', '{\"device_id\":\"4\",\"name\":\"TRy 2\",\"ip_address\":\"127.0.0.1\",\"port\":\"4370\",\"device_brand\":\"Fingertec\"}', '2025-06-20 22:54:46'),
(21, 1, 'update_device', '{\"device_id\":\"4\",\"name\":\"TRy 2\",\"ip_address\":\"127.0.0.1\",\"port\":\"8099\",\"device_brand\":\"Fingertec\"}', '2025-06-20 22:56:47'),
(22, 1, 'update_device', '{\"action\":\"save_device\",\"device_id\":\"2\",\"name\":\"TRy 1\",\"ip_address\":\"127.0.0.1\",\"port\":\"4370\",\"device_brand\":\"ZKTeco\"}', '2025-06-20 23:11:22'),
(23, 1, 'update_shift', '{\"shift_id\":1,\"name\":\"Fixed\"}', '2025-06-21 00:14:59'),
(24, 1, 'update_user', '{\"user_id\":\"1\",\"email\":\"eptj0e@gmail.com\"}', '2025-06-21 00:15:21'),
(25, 1, 'update_shift', 'Updated shift \'Fixed\' (ID: 1).', '2025-06-21 01:03:50'),
(26, 1, 'update_shift', 'Updated shift \'Fixed\' (ID: 1).', '2025-06-21 01:05:14'),
(27, 1, 'add_request_comment', 'User \'Joseph Ashraf\' added a comment to request #4.', '2025-06-21 01:14:28'),
(28, 1, 'update_shift', 'Updated shift \'Fixed\' (ID: 1).', '2025-06-21 01:15:43');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncate table before insert `departments`
--

TRUNCATE TABLE `departments`;
--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`) VALUES
(1, 'Human Resources'),
(2, 'Engineering'),
(3, 'Marketing'),
(4, 'Sales');

-- --------------------------------------------------------

--
-- Table structure for table `devices`
--

CREATE TABLE `devices` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL COMMENT 'A user-friendly name, e.g., "Main Entrance"',
  `ip_address` varchar(45) NOT NULL COMMENT 'IP for PULL mode, reference for PUSH mode',
  `port` int(11) NOT NULL DEFAULT 4370,
  `device_type` varchar(50) NOT NULL DEFAULT 'ZKTeco',
  `device_brand` varchar(50) NOT NULL COMMENT 'The driver to use, e.g., fingertec, zkteco',
  `serial_number` varchar(100) DEFAULT NULL,
  `communication_key` varchar(255) DEFAULT '0' COMMENT 'Device password, if any',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Toggle if the sync script should poll this device',
  `last_sync_timestamp` datetime DEFAULT NULL COMMENT 'Tracks the last successful communication',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Stores configuration for physical attendance devices';

--
-- Truncate table before insert `devices`
--

TRUNCATE TABLE `devices`;
--
-- Dumping data for table `devices`
--

INSERT INTO `devices` (`id`, `name`, `ip_address`, `port`, `device_type`, `device_brand`, `serial_number`, `communication_key`, `is_active`, `last_sync_timestamp`, `created_at`, `updated_at`) VALUES
(2, 'TRy 1', '127.0.0.1', 4370, 'ZKTeco', 'ZKTeco', 'TEST-SN-12345', '0', 1, NULL, '2025-06-20 22:01:03', '2025-06-20 23:11:22'),
(4, 'TRy 2', '127.0.0.1', 8099, 'ZKTeco', 'Fingertec', NULL, '0', 1, NULL, '2025-06-20 22:12:34', '2025-06-20 22:56:47');

-- --------------------------------------------------------

--
-- Table structure for table `leave_balances`
--

CREATE TABLE `leave_balances` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `balance_days` float NOT NULL DEFAULT 0,
  `last_updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_accrual_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncate table before insert `leave_balances`
--

TRUNCATE TABLE `leave_balances`;
--
-- Dumping data for table `leave_balances`
--

INSERT INTO `leave_balances` (`id`, `user_id`, `leave_type_id`, `balance_days`, `last_updated_at`, `last_accrual_date`) VALUES
(1, 2, 1, 21, '2025-06-21 00:55:30', '2025-06-21'),
(3, 3, 1, 21, '2025-06-21 00:55:30', '2025-06-21'),
(5, 4, 1, 21, '2025-06-21 00:55:30', '2025-06-21'),
(7, 5, 1, 21, '2025-06-21 00:55:30', '2025-06-21'),
(9, 6, 1, 21, '2025-06-21 00:55:30', '2025-06-21'),
(11, 7, 1, 21, '2025-06-21 00:55:30', '2025-06-21'),
(13, 1, 1, 21, '2025-06-21 00:55:30', '2025-06-21');

-- --------------------------------------------------------

--
-- Table structure for table `leave_types`
--

CREATE TABLE `leave_types` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `accrual_days_per_year` float NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncate table before insert `leave_types`
--

TRUNCATE TABLE `leave_types`;
--
-- Dumping data for table `leave_types`
--

INSERT INTO `leave_types` (`id`, `name`, `accrual_days_per_year`, `is_active`) VALUES
(1, 'Annual Leave 21', 21, 1);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `request_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncate table before insert `notifications`
--

TRUNCATE TABLE `notifications`;
--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `message`, `is_read`, `request_id`, `created_at`) VALUES
(1, 4, 'Junior Engineer has submitted a new leave request for your approval.', 1, 1, '2025-06-20 20:29:02'),
(2, 2, 'A leave request for Senior Engineer is awaiting your approval.', 0, 2, '2025-06-20 20:29:02'),
(3, 3, 'A leave request for Senior Engineer is awaiting your approval.', 0, 2, '2025-06-20 20:29:02'),
(4, 4, 'Your leave request for 2025-09-15 has been approved.', 1, 3, '2025-06-20 20:29:02'),
(5, 3, 'Your leave request for 2025-07-20 has been rejected.', 0, 4, '2025-06-20 20:29:02'),
(6, 5, 'Your vacation request has received final approval.', 0, 2, '2025-06-20 20:30:11'),
(7, 6, 'Your request was approved by your manager and sent to HR.', 1, 1, '2025-06-20 20:31:45'),
(8, 1, 'A request from Junior Engineer requires final approval.', 1, 1, '2025-06-20 20:31:45'),
(9, 2, 'A request from Junior Engineer requires final approval.', 0, 1, '2025-06-20 20:31:45'),
(10, 3, 'A request from Junior Engineer requires final approval.', 0, 1, '2025-06-20 20:31:45'),
(11, 6, 'Your vacation request has received final approval.', 1, 1, '2025-06-20 20:31:55'),
(12, 4, 'New vacation request from Junior Engineer.', 0, 5, '2025-06-20 21:47:28'),
(13, 1, 'New request submitted by Junior Engineer, awaiting manager review.', 1, 5, '2025-06-20 21:47:28'),
(14, 2, 'New request submitted by Junior Engineer, awaiting manager review.', 0, 5, '2025-06-20 21:47:28'),
(15, 3, 'New request submitted by Junior Engineer, awaiting manager review.', 0, 5, '2025-06-20 21:47:28'),
(16, 4, 'Leave request for Junior Engineer (#5) has been cancelled.', 0, 5, '2025-06-20 21:47:59'),
(17, 4, 'New vacation request from Junior Engineer.', 0, 6, '2025-06-20 21:49:28'),
(18, 1, 'New request submitted by Junior Engineer, awaiting manager review.', 1, 6, '2025-06-20 21:49:28'),
(19, 2, 'New request submitted by Junior Engineer, awaiting manager review.', 0, 6, '2025-06-20 21:49:28'),
(20, 3, 'New request submitted by Junior Engineer, awaiting manager review.', 0, 6, '2025-06-20 21:49:28'),
(21, 2, 'Leave request for Junior Engineer (ID: 6) has been approved by their manager.', 0, 6, '2025-06-20 21:50:03'),
(22, 6, 'Your leave request (#6) has been approved by your manager.', 0, 6, '2025-06-20 21:50:03'),
(23, 6, 'A comment was added to your request (#6).', 0, 6, '2025-06-20 21:53:35'),
(24, 2, 'A comment was added to request (#6) for Junior Engineer.', 0, 6, '2025-06-20 21:53:35'),
(25, 6, 'Your leave request (#6) has been fully approved by HR.', 0, 6, '2025-06-20 21:54:57'),
(26, 4, 'Leave request for Junior Engineer (#6) has been fully approved by HR.', 0, 6, '2025-06-20 21:54:57'),
(27, 6, 'A comment was added to your request (#6).', 0, 6, '2025-06-20 21:55:00'),
(28, 4, 'A comment was added to a team request (#6) for Junior Engineer.', 0, 6, '2025-06-20 21:55:00'),
(29, 3, 'A comment was added to your request (#4).', 0, 4, '2025-06-21 01:14:28'),
(30, 2, 'A comment was added to a team request (#4) for HR Staff.', 0, 4, '2025-06-21 01:14:28');

-- --------------------------------------------------------

--
-- Table structure for table `request_attachments`
--

CREATE TABLE `request_attachments` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncate table before insert `request_attachments`
--

TRUNCATE TABLE `request_attachments`;
-- --------------------------------------------------------

--
-- Table structure for table `request_comments`
--

CREATE TABLE `request_comments` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncate table before insert `request_comments`
--

TRUNCATE TABLE `request_comments`;
--
-- Dumping data for table `request_comments`
--

INSERT INTO `request_comments` (`id`, `request_id`, `user_id`, `comment`, `created_at`) VALUES
(1, 4, 2, 'Project deadline during this period. Please reschedule.', '2025-06-21 12:00:00'),
(2, 6, 4, 'X', '2025-06-20 21:53:35'),
(3, 6, 2, 'Congrats', '2025-06-20 21:55:00'),
(4, 4, 1, 'X', '2025-06-21 01:14:28');

-- --------------------------------------------------------

--
-- Table structure for table `shifts`
--

CREATE TABLE `shifts` (
  `id` int(11) NOT NULL,
  `shift_name` varchar(100) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `grace_period_in` int(11) DEFAULT 0 COMMENT 'Minutes allowed to be late',
  `grace_period_out` int(11) DEFAULT 0 COMMENT 'Minutes allowed to leave early',
  `break_start_time` time DEFAULT NULL,
  `break_end_time` time DEFAULT NULL,
  `is_night_shift` tinyint(1) DEFAULT 0 COMMENT 'TRUE if shift crosses midnight',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `grace_in_minutes` int(11) NOT NULL DEFAULT 0,
  `grace_out_minutes` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncate table before insert `shifts`
--

TRUNCATE TABLE `shifts`;
--
-- Dumping data for table `shifts`
--

INSERT INTO `shifts` (`id`, `shift_name`, `start_time`, `end_time`, `grace_period_in`, `grace_period_out`, `break_start_time`, `break_end_time`, `is_night_shift`, `created_at`, `updated_at`, `grace_in_minutes`, `grace_out_minutes`) VALUES
(1, 'Fixed', '07:45:00', '16:00:00', 15, 0, NULL, NULL, 0, '2025-06-20 21:39:11', '2025-06-21 01:15:43', 15, 0);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `employee_code` varchar(50) DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','manager','hr','hr_manager','admin') NOT NULL DEFAULT 'user',
  `shift_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `direct_manager_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `manager_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncate table before insert `users`
--

TRUNCATE TABLE `users`;
--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `employee_code`, `full_name`, `email`, `password`, `role`, `shift_id`, `department_id`, `direct_manager_id`, `is_active`, `must_change_password`, `created_at`, `updated_at`, `manager_id`) VALUES
(1, '1', 'Joseph Ashraf', 'eptj0e@gmail.com', '$2y$10$MqAfaXTI4x2pU5cOmwPR9e6FZgFFpyk8CQkQiX5PDZj.K1uTuogHK', 'admin', 1, NULL, NULL, 1, 0, '2025-06-20 20:30:35', '2025-06-21 00:15:21', NULL),
(2, '1000', 'HR Manager', 'hr.manager@example.com', '$2y$10$MqAfaXTI4x2pU5cOmwPR9e6FZgFFpyk8CQkQiX5PDZj.K1uTuogHK', 'hr_manager', NULL, 1, NULL, 1, 0, '2025-06-20 20:29:02', '2025-06-20 23:59:03', NULL),
(3, '1001', 'HR Staff', 'hr.staff@example.com', '$2y$10$MqAfaXTI4x2pU5cOmwPR9e6FZgFFpyk8CQkQiX5PDZj.K1uTuogHK', 'hr', NULL, 1, 2, 1, 0, '2025-06-20 20:29:02', '2025-06-20 23:59:07', NULL),
(4, '1002', 'Engineering Manager', 'eng.manager@example.com', '$2y$10$MqAfaXTI4x2pU5cOmwPR9e6FZgFFpyk8CQkQiX5PDZj.K1uTuogHK', 'manager', NULL, 2, NULL, 1, 0, '2025-06-20 20:29:02', '2025-06-20 23:59:11', NULL),
(5, '1003', 'Senior Engineer', 'senior.engineer@example.com', '$2y$10$MqAfaXTI4x2pU5cOmwPR9e6FZgFFpyk8CQkQiX5PDZj.K1uTuogHK', 'user', NULL, 2, 4, 1, 0, '2025-06-20 20:29:02', '2025-06-20 23:59:13', NULL),
(6, '1004', 'Junior Engineer', 'junior.engineer@example.com', '$2y$10$MqAfaXTI4x2pU5cOmwPR9e6FZgFFpyk8CQkQiX5PDZj.K1uTuogHK', 'user', NULL, 2, 4, 1, 0, '2025-06-20 20:29:02', '2025-06-20 23:59:15', NULL),
(7, '1005', 'Marketing Specialist', 'marketing@example.com', '$2y$10$MqAfaXTI4x2pU5cOmwPR9e6FZgFFpyk8CQkQiX5PDZj.K1uTuogHK', 'user', NULL, 3, NULL, 1, 1, '2025-06-20 20:29:02', '2025-06-20 23:59:17', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vacation_requests`
--

CREATE TABLE `vacation_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `duration_days` int(11) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `status` enum('pending_manager','pending_hr','approved','rejected','cancelled') NOT NULL DEFAULT 'pending_manager',
  `leave_type_id` int(11) DEFAULT NULL,
  `manager_action_at` timestamp NULL DEFAULT NULL,
  `hr_id` int(11) DEFAULT NULL,
  `hr_action_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncate table before insert `vacation_requests`
--

TRUNCATE TABLE `vacation_requests`;
--
-- Dumping data for table `vacation_requests`
--

INSERT INTO `vacation_requests` (`id`, `user_id`, `start_date`, `end_date`, `duration_days`, `reason`, `manager_id`, `status`, `leave_type_id`, `manager_action_at`, `hr_id`, `hr_action_at`, `rejection_reason`, `created_at`) VALUES
(1, 6, '2025-07-10', '2025-07-11', NULL, 'Family event.', 4, 'approved', 1, '2025-06-20 20:31:45', 1, '2025-06-20 20:31:55', NULL, '2025-06-21 07:00:00'),
(2, 5, '2025-08-01', '2025-08-05', NULL, 'Short vacation.', 4, 'approved', 1, '2025-06-21 08:00:00', 6, '2025-06-20 20:30:11', NULL, '2025-06-20 06:30:00'),
(3, 4, '2025-09-15', '2025-09-15', NULL, 'Personal day.', NULL, 'approved', NULL, '2025-06-19 11:00:00', 2, '2025-06-20 13:00:00', NULL, '2025-06-19 09:00:00'),
(4, 3, '2025-07-20', '2025-07-21', NULL, 'Conference.', 2, 'rejected', 1, '2025-06-21 12:00:00', NULL, NULL, 'Project deadline during this period. Please reschedule.', '2025-06-21 11:00:00'),
(5, 6, '2025-06-28', '2025-06-28', NULL, 'XX', 4, 'cancelled', 1, NULL, NULL, NULL, NULL, '2025-06-20 21:47:28'),
(6, 6, '2025-06-21', '2025-06-21', 1, 'Test', 4, 'approved', 1, NULL, NULL, NULL, NULL, '2025-06-20 21:49:28');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_employee_code` (`employee_code`),
  ADD KEY `idx_punch_time_status` (`punch_time`,`status`),
  ADD KEY `device_id` (`device_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_action` (`action`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `devices`
--
ALTER TABLE `devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `serial_number` (`serial_number`);

--
-- Indexes for table `leave_balances`
--
ALTER TABLE `leave_balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_leave_type` (`user_id`,`leave_type_id`),
  ADD KEY `leave_type_id` (`leave_type_id`);

--
-- Indexes for table `leave_types`
--
ALTER TABLE `leave_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`),
  ADD KEY `request_id` (`request_id`);

--
-- Indexes for table `request_attachments`
--
ALTER TABLE `request_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`);

--
-- Indexes for table `request_comments`
--
ALTER TABLE `request_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `shift_name` (`shift_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `employee_code` (`employee_code`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `direct_manager_id` (`direct_manager_id`),
  ADD KEY `fk_user_shift` (`shift_id`),
  ADD KEY `fk_users_direct_manager` (`manager_id`);

--
-- Indexes for table `vacation_requests`
--
ALTER TABLE `vacation_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `manager_id` (`manager_id`),
  ADD KEY `leave_type_id` (`leave_type_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `fk_request_hr` (`hr_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `devices`
--
ALTER TABLE `devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `leave_balances`
--
ALTER TABLE `leave_balances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT for table `leave_types`
--
ALTER TABLE `leave_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `request_attachments`
--
ALTER TABLE `request_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `request_comments`
--
ALTER TABLE `request_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `shifts`
--
ALTER TABLE `shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `vacation_requests`
--
ALTER TABLE `vacation_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD CONSTRAINT `fk_attendance_device` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_attendance_user` FOREIGN KEY (`employee_code`) REFERENCES `users` (`employee_code`) ON DELETE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `leave_balances`
--
ALTER TABLE `leave_balances`
  ADD CONSTRAINT `fk_balance_leavetype` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_balance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notification_request` FOREIGN KEY (`request_id`) REFERENCES `vacation_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `request_attachments`
--
ALTER TABLE `request_attachments`
  ADD CONSTRAINT `fk_attachment_request` FOREIGN KEY (`request_id`) REFERENCES `vacation_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `request_comments`
--
ALTER TABLE `request_comments`
  ADD CONSTRAINT `fk_comment_request` FOREIGN KEY (`request_id`) REFERENCES `vacation_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_user_manager` FOREIGN KEY (`direct_manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_user_shift` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_users_direct_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `vacation_requests`
--
ALTER TABLE `vacation_requests`
  ADD CONSTRAINT `fk_request_hr` FOREIGN KEY (`hr_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_request_leavetype` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_request_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_request_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
