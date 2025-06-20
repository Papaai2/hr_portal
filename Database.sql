-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 20, 2025 at 09:13 PM
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
  `device_id` int(11) NOT NULL COMMENT 'Foreign key to the devices table',
  `employee_code` varchar(50) NOT NULL COMMENT 'The User ID from the machine',
  `punch_time` datetime NOT NULL COMMENT 'The exact date and time of the punch',
  `punch_state` int(11) NOT NULL COMMENT 'Standardized code: 0=Check-In, 1=Check-Out, 2=Break-Out, 3=Break-In etc.',
  `work_code` varchar(50) DEFAULT NULL COMMENT 'Optional work code, if supported by the device',
  `is_processed` tinyint(1) DEFAULT 0 COMMENT 'Flag to show if this log has been processed into a final timesheet',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Staging table for raw attendance data from devices';

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
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncate table before insert `audit_logs`
--

TRUNCATE TABLE `audit_logs`;
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
(3, 'Marketing');

-- --------------------------------------------------------

--
-- Table structure for table `devices`
--

CREATE TABLE `devices` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL COMMENT 'A user-friendly name, e.g., "Main Entrance"',
  `ip_address` varchar(45) NOT NULL COMMENT 'Local IP for PULL mode, public IP for reference in PUSH mode',
  `port` int(11) NOT NULL DEFAULT 4370,
  `device_brand` varchar(50) NOT NULL COMMENT 'The driver to use, e.g., fingertec, zkteco',
  `serial_number` varchar(100) DEFAULT NULL,
  `communication_key` varchar(255) DEFAULT '0' COMMENT 'Device password, if any',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Toggle whether the sync script should poll this device',
  `last_sync_timestamp` datetime DEFAULT NULL COMMENT 'Tracks the last successful communication for PULL mode',
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

INSERT INTO `devices` (`id`, `name`, `ip_address`, `port`, `device_brand`, `serial_number`, `communication_key`, `is_active`, `last_sync_timestamp`, `created_at`, `updated_at`) VALUES
(2, 'Local Fake Device', '127.0.0.1', 4370, 'zkteco', 'FAKE-PULL-1', '0', 1, NULL, '2025-06-20 18:29:33', '2025-06-20 18:57:26');

-- --------------------------------------------------------

--
-- Table structure for table `leave_balances`
--

CREATE TABLE `leave_balances` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `balance_days` float NOT NULL,
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
(1, 3, 1, 9, '2025-06-20 16:25:46', '2025-01-01'),
(2, 3, 2, 5, '2025-06-20 15:24:11', '2025-01-01');

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
(1, 'Annual Leave', 21, 1),
(2, 'Sick Leave', 7, 1),
(3, 'Unpaid Leave', 0, 1);

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
(1, 3, 'Your request was approved by your manager and sent to HR.', 0, 1, '2025-06-20 16:22:32'),
(2, 1, 'A request from John Doe requires final approval.', 0, 1, '2025-06-20 16:22:32'),
(3, 2, 'A request from John Doe requires final approval.', 0, 1, '2025-06-20 16:22:32'),
(4, 5, 'A request from John Doe requires final approval.', 1, 1, '2025-06-20 16:22:32'),
(5, 3, 'Your vacation request has received final approval.', 0, 1, '2025-06-20 16:22:39'),
(6, 4, 'New vacation request from John Doe.', 0, 2, '2025-06-20 16:25:33'),
(7, 1, 'New request submitted by John Doe, awaiting manager review.', 0, 2, '2025-06-20 16:25:33'),
(8, 2, 'New request submitted by John Doe, awaiting manager review.', 0, 2, '2025-06-20 16:25:33'),
(9, 5, 'New request submitted by John Doe, awaiting manager review.', 1, 2, '2025-06-20 16:25:33'),
(10, 3, 'Your request was approved by your manager and sent to HR.', 0, 2, '2025-06-20 16:25:39'),
(11, 1, 'A request from John Doe requires final approval.', 0, 2, '2025-06-20 16:25:39'),
(12, 2, 'A request from John Doe requires final approval.', 0, 2, '2025-06-20 16:25:39'),
(13, 5, 'A request from John Doe requires final approval.', 1, 2, '2025-06-20 16:25:39'),
(14, 3, 'Your vacation request has received final approval.', 0, 2, '2025-06-20 16:25:46');

-- --------------------------------------------------------

--
-- Table structure for table `request_attachments`
--

CREATE TABLE `request_attachments` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL
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
  `department_id` int(11) DEFAULT NULL,
  `direct_manager_id` int(11) DEFAULT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncate table before insert `users`
--

TRUNCATE TABLE `users`;
--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `employee_code`, `full_name`, `email`, `password`, `role`, `department_id`, `direct_manager_id`, `must_change_password`) VALUES
(1, '1001', 'Admin User', 'admin@example.com', '$2y$10$23ct2CuBTITAEeP0wIksTun3jicGZmtCcxyhhncr3QhwpmBeg02tS', 'admin', NULL, NULL, 0),
(2, '1002', 'HR Manager', 'hr.manager@example.com', '$2y$10$23ct2CuBTITAEeP0wIksTun3jicGZmtCcxyhhncr3QhwpmBeg02tS', 'hr_manager', 1, 1, 0),
(3, NULL, 'John Doe', 'john.doe@example.com', '$2y$10$23ct2CuBTITAEeP0wIksTun3jicGZmtCcxyhhncr3QhwpmBeg02tS', 'user', 2, 4, 0),
(4, NULL, 'Jane Smith', 'jane.smith@example.com', '$2y$10$23ct2CuBTITAEeP0wIksTun3jicGZmtCcxyhhncr3QhwpmBeg02tS', 'manager', 2, 1, 0),
(5, '1000', 'Joseph Ashraf', 'eptj0e@gmail.com', '$2y$10$23ct2CuBTITAEeP0wIksTun3jicGZmtCcxyhhncr3QhwpmBeg02tS', 'admin', NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `vacation_requests`
--

CREATE TABLE `vacation_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
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

INSERT INTO `vacation_requests` (`id`, `user_id`, `start_date`, `end_date`, `reason`, `manager_id`, `status`, `leave_type_id`, `manager_action_at`, `hr_id`, `hr_action_at`, `rejection_reason`, `created_at`) VALUES
(1, 3, '2025-07-01', '2025-07-05', 'Family vacation.', 4, 'approved', 1, '2025-06-20 16:22:32', 2, '2025-06-20 16:22:39', NULL, '2025-06-20 11:30:00'),
(2, 3, '2025-06-20', '2025-06-20', '', 4, 'approved', 1, '2025-06-20 16:25:39', 2, '2025-06-20 16:25:46', NULL, '2025-06-20 16:25:33');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_employee_code` (`employee_code`),
  ADD KEY `idx_is_processed` (`is_processed`),
  ADD KEY `device_id` (`device_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

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
  ADD KEY `user_id` (`user_id`),
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
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `employee_code` (`employee_code`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `direct_manager_id` (`direct_manager_id`);

--
-- Indexes for table `vacation_requests`
--
ALTER TABLE `vacation_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `manager_id` (`manager_id`),
  ADD KEY `leave_type_id` (`leave_type_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `devices`
--
ALTER TABLE `devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `leave_balances`
--
ALTER TABLE `leave_balances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `leave_types`
--
ALTER TABLE `leave_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `request_attachments`
--
ALTER TABLE `request_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `request_comments`
--
ALTER TABLE `request_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `vacation_requests`
--
ALTER TABLE `vacation_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD CONSTRAINT `attendance_logs_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `leave_balances`
--
ALTER TABLE `leave_balances`
  ADD CONSTRAINT `leave_balances_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_balances_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`request_id`) REFERENCES `vacation_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `request_attachments`
--
ALTER TABLE `request_attachments`
  ADD CONSTRAINT `request_attachments_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `vacation_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `request_comments`
--
ALTER TABLE `request_comments`
  ADD CONSTRAINT `request_comments_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `vacation_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `request_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`direct_manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `vacation_requests`
--
ALTER TABLE `vacation_requests`
  ADD CONSTRAINT `vacation_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vacation_requests_ibfk_2` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `vacation_requests_ibfk_3` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
