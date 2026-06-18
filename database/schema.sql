-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jun 18, 2026 at 05:55 PM
-- Server version: 9.1.0
-- PHP Version: 8.2.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `akabbo_fund`
--

-- --------------------------------------------------------

--
-- Table structure for table `approvals`
--

DROP TABLE IF EXISTS `approvals`;
CREATE TABLE IF NOT EXISTS `approvals` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference_type` enum('transaction','loan','share_transaction','withdrawal','transfer','disbursement','expense') NOT NULL,
  `reference_id` int UNSIGNED NOT NULL,
  `reference_ref` varchar(50) DEFAULT NULL COMMENT 'Human-readable ref like TXN-XXX',
  `amount` decimal(18,2) DEFAULT NULL,
  `requested_by` int UNSIGNED NOT NULL,
  `requested_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `assigned_to` int UNSIGNED DEFAULT NULL COMMENT 'Specific approver if directed',
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `reviewed_by` int UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `approval_notes` text NOT NULL COMMENT 'Mandatory reason/notes',
  `rejection_notes` text,
  `escalated` tinyint(1) DEFAULT '0',
  `escalated_at` datetime DEFAULT NULL,
  `due_by` datetime DEFAULT NULL COMMENT 'SLA deadline',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `requested_by` (`requested_by`),
  KEY `reviewed_by` (`reviewed_by`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `approvals`
--

INSERT INTO `approvals` (`id`, `reference_type`, `reference_id`, `reference_ref`, `amount`, `requested_by`, `requested_at`, `assigned_to`, `status`, `reviewed_by`, `reviewed_at`, `approval_notes`, `rejection_notes`, `escalated`, `escalated_at`, `due_by`, `created_at`, `updated_at`) VALUES
(1, 'expense', 1, 'EXP-2026-0001', 1500000.00, 1, '2026-06-13 07:13:45', NULL, 'pending', NULL, NULL, 'Expense: We dvdeloped Akabbo Social Fund System — USh 1,500,000.00', NULL, 0, NULL, '2026-06-14 13:13:45', '2026-06-13 07:13:45', '2026-06-13 07:13:45'),
(2, 'expense', 2, 'EXP-2026-0002', 1500000.00, 1, '2026-06-13 07:17:08', NULL, 'pending', NULL, NULL, 'Expense: We dvdeloped Akabbo Social Fund System — USh 1,500,000.00', NULL, 0, NULL, '2026-06-14 13:17:08', '2026-06-13 07:17:08', '2026-06-13 07:17:08'),
(3, 'expense', 3, 'EXP-2026-0003', 1500000.00, 1, '2026-06-13 07:27:50', NULL, 'pending', NULL, NULL, 'Expense: We dvdeloped Akabbo Social Fund System — USh 1,500,000.00', NULL, 0, NULL, '2026-06-14 13:27:50', '2026-06-13 07:27:50', '2026-06-13 07:27:50'),
(4, 'transfer', 1, 'TRF-20260616-7C07E', 5000.00, 1, '2026-06-16 13:14:12', NULL, 'pending', NULL, NULL, 'Transfer of USh 5,000.00 from member #2 to member #1. Member savings deposit-Transfer', NULL, 0, NULL, '2026-06-17 16:14:12', '2026-06-16 13:14:12', '2026-06-16 13:14:12');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED DEFAULT NULL,
  `user_name` varchar(150) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `module` varchar(60) NOT NULL,
  `record_id` int UNSIGNED DEFAULT NULL,
  `record_type` varchar(60) DEFAULT NULL,
  `description` text,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `severity` enum('info','warning','critical') DEFAULT 'info',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=204 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `user_name`, `action`, `module`, `record_id`, `record_type`, `description`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `severity`, `created_at`) VALUES
(20, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'h8ukut6p4pj47ter08d7viacv0', 'info', '2026-06-12 22:43:39'),
(21, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'keqg5c1hrr2f0o2b8938mchmk2', 'info', '2026-06-12 22:48:29'),
(22, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'gsd605kun2rc8d99rgdn66dkej', 'info', '2026-06-12 22:49:57'),
(23, 1, 'Cosmas Musamba', 'member_deleted', 'members', 3, 'Member', 'Member AKB-00003 moved to trash', NULL, NULL, '{\"id\":3,\"member_no\":\"AKB-00003\",\"first_name\":', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'gsd605kun2rc8d99rgdn66dkej', 'info', '2026-06-12 23:11:32'),
(24, 1, 'Cosmas Musamba', 'member_created', 'members', 4, 'Member', 'Member AKB-00004 created', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'gsd605kun2rc8d99rgdn66dkej', 'info', '2026-06-12 23:15:52'),
(25, 1, 'Cosmas Musamba', 'interest_posted', 'savings', NULL, NULL, 'Monthly interest posted to 0 account(s)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'gsd605kun2rc8d99rgdn66dkej', 'info', '2026-06-12 23:17:00'),
(26, 1, 'Cosmas Musamba', 'member_deleted', 'members', 4, 'Member', 'Member AKB-00004 moved to trash', NULL, NULL, '{\"id\":4,\"member_no\":\"AKB-00004\",\"first_name\":', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'gsd605kun2rc8d99rgdn66dkej', 'info', '2026-06-12 23:18:11'),
(27, 1, 'Cosmas Musamba', 'members_exported', 'members', NULL, NULL, '2 members exported', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'gsd605kun2rc8d99rgdn66dkej', 'info', '2026-06-12 23:21:31'),
(28, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'gsd605kun2rc8d99rgdn66dkej', 'info', '2026-06-12 23:37:27'),
(29, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'kdfr2ccb9pngm7a76mnbvp4jf0', 'info', '2026-06-12 23:37:30'),
(30, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'aqg2vnkudh8rsef17tegjs0k66', 'info', '2026-06-13 07:02:47'),
(31, 1, 'Cosmas Musamba', 'expense_created', 'expenses', 1, 'Expense', 'Expense EXP-2026-0001 recorded', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'aqg2vnkudh8rsef17tegjs0k66', 'info', '2026-06-13 07:13:45'),
(32, 1, 'Cosmas Musamba', 'expense_created', 'expenses', 2, 'Expense', 'Expense EXP-2026-0002 recorded', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'aqg2vnkudh8rsef17tegjs0k66', 'info', '2026-06-13 07:17:08'),
(33, 1, 'Cosmas Musamba', 'expense_created', 'expenses', 3, 'Expense', 'Expense EXP-2026-0003 recorded', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'aqg2vnkudh8rsef17tegjs0k66', 'info', '2026-06-13 07:27:50'),
(34, NULL, NULL, 'login_failed', 'auth', NULL, NULL, 'Failed login for unknown email: admin@akabbofund.org', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '56mob0vslii14qs9t5e07j3189', 'info', '2026-06-13 07:52:59'),
(35, NULL, NULL, 'login_failed', 'auth', NULL, NULL, 'Failed login for unknown email: admin@akabbofund.org', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '56mob0vslii14qs9t5e07j3189', 'info', '2026-06-13 07:53:37'),
(36, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'kh1cohe115u3ep6cl8smrc8e1p', 'info', '2026-06-13 07:53:48'),
(37, 1, 'Cosmas Musamba', 'social_fund_fee_created', 'social_fund', 4, 'SocialFundFee', 'Fee \'Social Fee\' created', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'kh1cohe115u3ep6cl8smrc8e1p', 'info', '2026-06-13 08:49:31'),
(38, 1, 'Cosmas Musamba', 'share_purchase_requested', 'shares', 1, 'ShareTransaction', 'Requested 10 shares for member #1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'kh1cohe115u3ep6cl8smrc8e1p', 'info', '2026-06-13 09:14:55'),
(39, 1, 'Cosmas Musamba', 'share_config_updated', 'shares', NULL, 'ShareConfig', 'Share configuration updated', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'kh1cohe115u3ep6cl8smrc8e1p', 'info', '2026-06-13 09:15:39'),
(40, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'kh1cohe115u3ep6cl8smrc8e1p', 'info', '2026-06-13 10:07:05'),
(41, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'qbi6lsfpjuilk4u074v5pa7bhl', 'info', '2026-06-13 10:07:07'),
(42, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'qbi6lsfpjuilk4u074v5pa7bhl', 'info', '2026-06-13 10:38:07'),
(43, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'flkkma21tr5rj1bc2oa6vdjjc0', 'info', '2026-06-13 10:38:09'),
(44, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'm1egqhfov4qcadkjqm0v6ggt0r', 'info', '2026-06-14 06:10:13'),
(45, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'm1egqhfov4qcadkjqm0v6ggt0r', 'info', '2026-06-14 07:36:32'),
(46, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'vos2hsc82bmniiqqqut7qg48je', 'info', '2026-06-14 07:36:35'),
(47, 1, 'Cosmas Musamba', 'user_created', 'users', 2, 'User', 'User cosmasmusamba1@gmail.com created', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'vos2hsc82bmniiqqqut7qg48je', 'info', '2026-06-14 08:01:06'),
(48, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'vos2hsc82bmniiqqqut7qg48je', 'info', '2026-06-14 08:01:14'),
(49, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'tndvopcb04bb895qq562f0vjic', 'info', '2026-06-14 08:01:16'),
(50, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'tndvopcb04bb895qq562f0vjic', 'info', '2026-06-14 08:01:22'),
(51, 2, 'Test User', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'gdumjphr3odkklqsrr4mul7t1d', 'info', '2026-06-14 08:01:32'),
(52, 2, 'Test User', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'gdumjphr3odkklqsrr4mul7t1d', 'info', '2026-06-14 08:03:45'),
(53, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'doj40ftuh7lfh1etvt81itpf2h', 'info', '2026-06-14 08:03:57'),
(54, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'doj40ftuh7lfh1etvt81itpf2h', 'info', '2026-06-14 09:02:37'),
(55, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'fvm3d81st95f1ahqp8tell4pgn', 'info', '2026-06-14 09:02:40'),
(56, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'fvm3d81st95f1ahqp8tell4pgn', 'info', '2026-06-14 10:14:40'),
(57, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '5aoot97mnkmf70t909p5dv4obe', 'info', '2026-06-14 10:14:42'),
(58, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '5aoot97mnkmf70t909p5dv4obe', 'info', '2026-06-14 10:18:12'),
(59, 2, 'Test User', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'fohdpmljauvbpbm0crlm3bqhvh', 'info', '2026-06-14 10:18:25'),
(60, 2, 'Test User', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'fohdpmljauvbpbm0crlm3bqhvh', 'info', '2026-06-14 10:23:33'),
(61, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '8dppormad1q43qo9ssc8ujmtna', 'info', '2026-06-14 10:23:34'),
(62, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '8dppormad1q43qo9ssc8ujmtna', 'info', '2026-06-14 11:02:47'),
(63, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'tp12e1bghd6radfp002hf7785g', 'info', '2026-06-14 11:02:49'),
(64, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'tp12e1bghd6radfp002hf7785g', 'info', '2026-06-14 14:00:38'),
(65, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'e075h51cb0gd952mo6m7v0v8s5', 'info', '2026-06-14 14:04:12'),
(66, 1, 'Cosmas Musamba', 'member_updated', 'members', 2, 'Member', 'Member AKB-00002 updated', NULL, NULL, '{\"id\":2,\"member_no\":\"AKB-00002\",\"first_name\":', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'e075h51cb0gd952mo6m7v0v8s5', 'info', '2026-06-14 14:31:39'),
(67, 1, 'Cosmas Musamba', 'member_added_to_group', 'savings_groups', 1, 'Group', 'Member AKB-00002 added to Akabbo Social Fund', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'e075h51cb0gd952mo6m7v0v8s5', 'info', '2026-06-14 14:34:59'),
(68, 1, 'Cosmas Musamba', 'member_removed_from_group', 'savings_groups', 1, 'Group', 'Member AKB-00002 removed from Akabbo Social Fund', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'e075h51cb0gd952mo6m7v0v8s5', 'info', '2026-06-14 14:35:22'),
(69, 1, 'Cosmas Musamba', 'kyc_verified', 'members', 1, 'Member', 'KYC verified for AKB-00001 by User ID 1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'e075h51cb0gd952mo6m7v0v8s5', 'info', '2026-06-14 14:42:13'),
(70, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'e075h51cb0gd952mo6m7v0v8s5', 'info', '2026-06-14 14:52:23'),
(71, NULL, NULL, 'login_failed', 'auth', NULL, NULL, 'Failed login for unknown email: cosmasmusam1ba@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '5ijkpqsibj6rd00hda17h3553s', 'info', '2026-06-14 14:52:28'),
(72, 2, 'Test User', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'ipqi0ni8ead6et5cjkihoicukc', 'info', '2026-06-14 14:52:38'),
(73, 2, 'Test User', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'ipqi0ni8ead6et5cjkihoicukc', 'info', '2026-06-14 15:53:16'),
(74, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'l0u4m9qb44u6mli680t657c4nm', 'info', '2026-06-14 15:53:19'),
(75, 1, 'Cosmas Musamba', 'member_created', 'members', 5, 'Member', 'Member AKB-00005 created', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'l0u4m9qb44u6mli680t657c4nm', 'info', '2026-06-14 15:56:29'),
(76, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'l0u4m9qb44u6mli680t657c4nm', 'info', '2026-06-14 15:56:41'),
(77, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'fufjd0qar01rsdsjuua13j9v4e', 'info', '2026-06-14 15:58:58'),
(78, 1, 'Cosmas Musamba', 'deposit', 'savings', 6, 'SavingsAccount', 'Deposit of USh 100,000.00 to account SAV-000001', NULL, NULL, '{\"balance\":0}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'fufjd0qar01rsdsjuua13j9v4e', 'info', '2026-06-14 16:49:08'),
(79, 1, 'Cosmas Musamba', 'share_purchase_requested', 'shares', 2, 'ShareTransaction', 'Requested 5 shares for member #1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'fufjd0qar01rsdsjuua13j9v4e', 'info', '2026-06-14 17:05:38'),
(80, 1, 'Cosmas Musamba', 'withdrawal_requested', 'savings', 6, 'SavingsAccount', 'Withdrawal request of USh 50,000.00 from account SAV-000001', NULL, NULL, '{\"balance\":100000,\"status\":\"pending\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'fufjd0qar01rsdsjuua13j9v4e', 'info', '2026-06-14 17:09:53'),
(81, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'fufjd0qar01rsdsjuua13j9v4e', 'info', '2026-06-14 17:44:21'),
(82, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'uckone6cm8g26et5p9paspldsa', 'info', '2026-06-15 08:35:07'),
(83, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'uckone6cm8g26et5p9paspldsa', 'info', '2026-06-15 11:39:26'),
(84, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'g5g8iouk5k0bingehfjc80989b', 'info', '2026-06-15 11:39:27'),
(85, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'g5g8iouk5k0bingehfjc80989b', 'info', '2026-06-15 16:28:45'),
(86, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '64a2hrmtkqfknqijev27ns8blt', 'info', '2026-06-15 16:28:46'),
(87, 1, 'Cosmas Musamba', 'kyc_reminder_sent', 'members', 5, 'Member', 'Manual KYC reminder sent to AKB-00005', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '64a2hrmtkqfknqijev27ns8blt', 'info', '2026-06-15 16:52:06'),
(88, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '64a2hrmtkqfknqijev27ns8blt', 'info', '2026-06-16 06:07:10'),
(89, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '6hq2lf83scttqlfgbljsd72o2h', 'info', '2026-06-16 06:07:13'),
(90, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'jspkrht6n8tms46rj9ubnvjugb', 'info', '2026-06-16 09:44:40'),
(91, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'jspkrht6n8tms46rj9ubnvjugb', 'info', '2026-06-16 10:21:34'),
(92, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'h5gvkpngnbr7q1aof8ndddo1d1', 'info', '2026-06-16 10:21:37'),
(93, 1, 'Cosmas Musamba', 'kyc_reminder_sent', 'members', 5, 'Member', 'Manual KYC reminder sent to AKB-00005', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'h5gvkpngnbr7q1aof8ndddo1d1', 'info', '2026-06-16 10:23:36'),
(94, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'h5gvkpngnbr7q1aof8ndddo1d1', 'info', '2026-06-16 11:24:57'),
(95, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '68un82s1qukkvld7c3cr0dbtui', 'info', '2026-06-16 11:24:59'),
(96, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '68un82s1qukkvld7c3cr0dbtui', 'info', '2026-06-16 12:44:57'),
(97, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'q67j72oojsbcblv7mhpjrb9h9e', 'info', '2026-06-16 12:46:05'),
(98, 1, 'Cosmas Musamba', 'deposit', 'savings', 7, 'SavingsAccount', 'Deposit of USh 300,000.00 to account SAV-000002', NULL, NULL, '{\"balance\":0}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'q67j72oojsbcblv7mhpjrb9h9e', 'info', '2026-06-16 13:13:36'),
(99, 1, 'Cosmas Musamba', 'transfer_requested', 'transfers', 1, 'FundTransfer', 'Transfer TRF-20260616-7C07E: USh 5,000.00 from M#2 to M#1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'q67j72oojsbcblv7mhpjrb9h9e', 'info', '2026-06-16 13:14:12'),
(100, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'q67j72oojsbcblv7mhpjrb9h9e', 'info', '2026-06-16 14:25:59'),
(101, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'dviq79b34gmit8eql55q12f1j6', 'info', '2026-06-16 14:26:02'),
(102, 1, 'Cosmas Musamba', 'loan_applied', 'loans', 1, 'Loan', 'Loan LN-2026-00001 applied for member AKB-00001', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'dviq79b34gmit8eql55q12f1j6', 'info', '2026-06-16 14:49:25'),
(103, 1, 'Cosmas Musamba', 'loan_auto_rejected_limit', 'loans', 0, 'Loan', 'Auto-rejected loan application for member AKB-00001: Active loan limit (1) exceeded. Current active: 1.', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'dviq79b34gmit8eql55q12f1j6', 'info', '2026-06-16 14:53:13'),
(104, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'dviq79b34gmit8eql55q12f1j6', 'info', '2026-06-16 16:08:40'),
(105, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'pkih05fpq1eek960ho6s31pn52', 'info', '2026-06-16 16:08:41'),
(106, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'pkih05fpq1eek960ho6s31pn52', 'info', '2026-06-17 14:36:24'),
(107, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '4ahv69d1s3735immkejp0k4mbt', 'info', '2026-06-17 14:36:26'),
(108, 1, 'Cosmas Musamba', 'login', 'auth', 1, 'User', 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '4ahv69d1s3735immkejp0k4mbt', 'info', '2026-06-17 14:36:26'),
(109, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '4ahv69d1s3735immkejp0k4mbt', 'info', '2026-06-17 15:39:55'),
(110, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'pvvt56vai27bk8h27tmo7v63il', 'info', '2026-06-17 15:39:57'),
(111, 1, 'Cosmas Musamba', 'login', 'auth', 1, 'User', 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'pvvt56vai27bk8h27tmo7v63il', 'info', '2026-06-17 15:39:57'),
(112, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'pvvt56vai27bk8h27tmo7v63il', 'info', '2026-06-17 16:52:38'),
(113, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '1tsib4luujscit5lmi6k8pkm7i', 'info', '2026-06-17 16:52:40'),
(114, 1, 'Cosmas Musamba', 'login', 'auth', 1, 'User', 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '1tsib4luujscit5lmi6k8pkm7i', 'info', '2026-06-17 16:52:40'),
(115, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '1tsib4luujscit5lmi6k8pkm7i', 'info', '2026-06-18 07:41:17'),
(116, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'ltj6u8bs8f3057lcepnqe30j09', 'info', '2026-06-18 07:41:19'),
(117, 1, 'Cosmas Musamba', 'login', 'auth', 1, 'User', 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'ltj6u8bs8f3057lcepnqe30j09', 'info', '2026-06-18 07:41:19'),
(118, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'ltj6u8bs8f3057lcepnqe30j09', 'info', '2026-06-18 10:48:42'),
(119, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'oaa45mm75er60lb145o7s4k54v', 'info', '2026-06-18 10:48:44'),
(120, 1, 'Cosmas Musamba', 'login', 'auth', 1, 'User', 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'oaa45mm75er60lb145o7s4k54v', 'info', '2026-06-18 10:48:44'),
(121, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'oaa45mm75er60lb145o7s4k54v', 'info', '2026-06-18 12:41:42'),
(122, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'ure7l260n2dcj5et8hed9nqtub', 'info', '2026-06-18 12:41:44'),
(123, 1, 'Cosmas Musamba', 'login', 'auth', 1, 'User', 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'ure7l260n2dcj5et8hed9nqtub', 'info', '2026-06-18 12:41:44'),
(124, 2, 'Test User', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'lhjfp9v9lc4g2lce6qua8svt70', 'info', '2026-06-18 13:09:23'),
(125, 2, 'Test User', 'login', 'auth', 2, 'User', 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'lhjfp9v9lc4g2lce6qua8svt70', 'info', '2026-06-18 13:09:23'),
(126, 1, 'Cosmas Musamba', 'user_updated', 'users', 2, 'User', 'User updated', NULL, NULL, '{\"id\":2,\"employee_id\":null,\"first_name\":\"Test', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'ure7l260n2dcj5et8hed9nqtub', 'info', '2026-06-18 14:08:08'),
(127, 2, 'Test User', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'lhjfp9v9lc4g2lce6qua8svt70', 'info', '2026-06-18 14:08:16'),
(128, 2, 'Test User', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '02r0r59lmqo5ss6d3hfbpa921u', 'info', '2026-06-18 14:08:19'),
(129, 2, 'Test User', 'login', 'auth', 2, 'User', 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '02r0r59lmqo5ss6d3hfbpa921u', 'info', '2026-06-18 14:08:19'),
(130, 1, 'Cosmas Musamba', 'user_updated', 'users', 2, 'User', 'User updated', NULL, NULL, '{\"id\":2,\"employee_id\":null,\"first_name\":\"Test', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'ure7l260n2dcj5et8hed9nqtub', 'info', '2026-06-18 14:10:27'),
(131, 2, 'Test User', 'logout', 'auth', 2, 'User', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '02r0r59lmqo5ss6d3hfbpa921u', 'info', '2026-06-18 14:10:41'),
(132, 2, 'Test User', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '02r0r59lmqo5ss6d3hfbpa921u', 'info', '2026-06-18 14:10:42'),
(133, 2, 'Test User', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '69074ebmb3058otronqkn4gtma', 'info', '2026-06-18 14:10:43'),
(134, 2, 'Test User', 'login', 'auth', 2, 'User', 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '69074ebmb3058otronqkn4gtma', 'info', '2026-06-18 14:10:43'),
(135, 1, 'Cosmas Musamba', 'role_updated', 'access_control', 5, 'Role', 'Role Savings Officer updated', NULL, NULL, '{\"id\":5,\"name\":\"Savings Officer\",\"slug\":\"savi', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'ure7l260n2dcj5et8hed9nqtub', 'info', '2026-06-18 14:12:39'),
(136, 2, 'Test User', 'logout', 'auth', 2, 'User', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '69074ebmb3058otronqkn4gtma', 'info', '2026-06-18 14:32:41'),
(137, 2, 'Test User', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '69074ebmb3058otronqkn4gtma', 'info', '2026-06-18 14:32:41'),
(138, 2, 'Test User', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '94qgeaius34rgrg3k2g8comon8', 'info', '2026-06-18 14:32:42'),
(139, 2, 'Test User', 'login', 'auth', 2, 'User', 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '94qgeaius34rgrg3k2g8comon8', 'info', '2026-06-18 14:32:42'),
(140, 1, 'Cosmas Musamba', 'role_updated', 'access_control', 5, 'Role', 'Role Savings Officer updated', NULL, NULL, '{\"id\":5,\"name\":\"Savings Officer\",\"slug\":\"savi', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'ure7l260n2dcj5et8hed9nqtub', 'info', '2026-06-18 14:33:20'),
(141, 1, 'Cosmas Musamba', 'role_updated', 'access_control', 5, 'Role', 'Role Savings Officer updated', NULL, NULL, '{\"id\":5,\"name\":\"Savings Officer\",\"slug\":\"savi', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'ure7l260n2dcj5et8hed9nqtub', 'info', '2026-06-18 14:35:29'),
(142, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'ure7l260n2dcj5et8hed9nqtub', 'info', '2026-06-18 15:10:21'),
(143, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '0bi2v03fubrh5tcglsvqdmkk5h', 'info', '2026-06-18 15:10:24'),
(144, 1, 'Cosmas Musamba', 'login', 'auth', 1, 'User', 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '0bi2v03fubrh5tcglsvqdmkk5h', 'info', '2026-06-18 15:10:24'),
(145, 2, 'Test User', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '94qgeaius34rgrg3k2g8comon8', 'info', '2026-06-18 15:25:56'),
(146, 2, 'Test User', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'etanl3ndaatjic0829k099fsqh', 'info', '2026-06-18 15:25:58'),
(147, 2, 'Test User', 'login', 'auth', 2, 'User', 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'etanl3ndaatjic0829k099fsqh', 'info', '2026-06-18 15:25:58'),
(148, 1, 'Cosmas Musamba', 'users_viewed', 'users', NULL, NULL, 'Viewed users list (Page 1)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '0bi2v03fubrh5tcglsvqdmkk5h', 'info', '2026-06-18 15:50:07'),
(149, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '0bi2v03fubrh5tcglsvqdmkk5h', 'info', '2026-06-18 16:30:06'),
(150, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'kouvn0v4kjh6ci13sls3ugnc1m', 'info', '2026-06-18 16:30:53'),
(151, 1, 'Cosmas Musamba', 'login', 'auth', 1, 'User', 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'kouvn0v4kjh6ci13sls3ugnc1m', 'info', '2026-06-18 16:30:53'),
(152, 1, 'Cosmas Musamba', 'trash_viewed', 'trash', NULL, NULL, 'Viewed trash list (Page 1)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'kouvn0v4kjh6ci13sls3ugnc1m', 'info', '2026-06-18 16:46:07'),
(153, 1, 'Cosmas Musamba', 'trash_viewed', 'trash', NULL, NULL, 'Viewed trash list (Page 1)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'kouvn0v4kjh6ci13sls3ugnc1m', 'info', '2026-06-18 16:46:12'),
(154, 1, 'Cosmas Musamba', 'trash_viewed', 'trash', NULL, NULL, 'Viewed trash list (Page 1)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'kouvn0v4kjh6ci13sls3ugnc1m', 'info', '2026-06-18 16:46:15'),
(155, 1, 'Cosmas Musamba', 'trash_viewed', 'trash', NULL, NULL, 'Viewed trash list (Page 1)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'kouvn0v4kjh6ci13sls3ugnc1m', 'info', '2026-06-18 16:46:20'),
(156, 1, 'Cosmas Musamba', 'trash_viewed', 'trash', NULL, NULL, 'Viewed trash list (Page 1)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'kouvn0v4kjh6ci13sls3ugnc1m', 'info', '2026-06-18 16:46:22'),
(157, 1, 'Cosmas Musamba', 'trash_viewed', 'trash', NULL, NULL, 'Viewed trash list (Page 1)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'kouvn0v4kjh6ci13sls3ugnc1m', 'info', '2026-06-18 16:46:39'),
(158, 1, 'Cosmas Musamba', 'trash_viewed', 'trash', NULL, NULL, 'Viewed trash list (Page 1)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'kouvn0v4kjh6ci13sls3ugnc1m', 'info', '2026-06-18 16:49:23'),
(159, 1, 'Cosmas Musamba', 'trash_viewed', 'trash', NULL, NULL, 'Viewed trash list (Page 1)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'kouvn0v4kjh6ci13sls3ugnc1m', 'info', '2026-06-18 16:53:37'),
(160, 1, 'Cosmas Musamba', 'trash_viewed', 'trash', NULL, NULL, 'Viewed trash list (Page 1)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'kouvn0v4kjh6ci13sls3ugnc1m', 'info', '2026-06-18 16:53:46'),
(161, 1, 'Cosmas Musamba', 'trash_viewed', 'trash', NULL, NULL, 'Viewed trash list (Page 1)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'kouvn0v4kjh6ci13sls3ugnc1m', 'info', '2026-06-18 16:55:20'),
(162, 1, 'Cosmas Musamba', 'trash_viewed', 'trash', NULL, NULL, 'Viewed trash list (Page 1)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'kouvn0v4kjh6ci13sls3ugnc1m', 'info', '2026-06-18 16:56:54'),
(163, 1, 'Cosmas Musamba', 'logout', 'auth', 1, 'User', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'kouvn0v4kjh6ci13sls3ugnc1m', 'info', '2026-06-18 16:57:03'),
(164, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'kouvn0v4kjh6ci13sls3ugnc1m', 'info', '2026-06-18 16:57:03'),
(165, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'qu5d53urvf02qnojn0emq7rdbs', 'info', '2026-06-18 16:57:05'),
(166, 1, 'Cosmas Musamba', 'login', 'auth', 1, 'User', 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'qu5d53urvf02qnojn0emq7rdbs', 'info', '2026-06-18 16:57:05'),
(167, 1, 'Cosmas Musamba', 'logout', 'auth', 1, 'User', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'qu5d53urvf02qnojn0emq7rdbs', 'info', '2026-06-18 16:57:11'),
(168, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'qu5d53urvf02qnojn0emq7rdbs', 'info', '2026-06-18 16:57:11'),
(169, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'sbsc1r2ae4fh152iks8n2oijs3', 'info', '2026-06-18 16:57:13'),
(170, 1, 'Cosmas Musamba', 'login', 'auth', 1, 'User', 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'sbsc1r2ae4fh152iks8n2oijs3', 'info', '2026-06-18 16:57:13'),
(171, 1, 'Cosmas Musamba', 'groups_viewed', 'savings_groups', NULL, NULL, 'Viewed groups list', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'sbsc1r2ae4fh152iks8n2oijs3', 'info', '2026-06-18 17:20:18'),
(172, 1, 'Cosmas Musamba', 'group_viewed', 'savings_groups', 1, 'Group', 'Viewed group Akabbo Social Fund', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'sbsc1r2ae4fh152iks8n2oijs3', 'info', '2026-06-18 17:20:21'),
(173, 1, 'Cosmas Musamba', 'users_viewed', 'users', NULL, NULL, 'Viewed users list (Page 1)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'sbsc1r2ae4fh152iks8n2oijs3', 'info', '2026-06-18 17:20:26'),
(174, 2, 'Test User', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'etanl3ndaatjic0829k099fsqh', 'info', '2026-06-18 17:20:34'),
(175, 2, 'Test User', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '1vbe5bdprekv2k2hlbnau34bdc', 'info', '2026-06-18 17:20:36'),
(176, 2, 'Test User', 'login', 'auth', 2, 'User', 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '1vbe5bdprekv2k2hlbnau34bdc', 'info', '2026-06-18 17:20:36'),
(177, 1, 'Cosmas Musamba', 'trash_viewed', 'trash', NULL, NULL, 'Viewed trash list (Page 1)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'sbsc1r2ae4fh152iks8n2oijs3', 'info', '2026-06-18 17:21:21'),
(178, 1, 'Cosmas Musamba', 'trash_viewed', 'trash', NULL, NULL, 'Viewed trash list (Page 1)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'sbsc1r2ae4fh152iks8n2oijs3', 'info', '2026-06-18 17:28:09'),
(179, 1, 'Cosmas Musamba', 'trash_viewed', 'trash', NULL, NULL, 'Viewed trash list (Page 1)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'sbsc1r2ae4fh152iks8n2oijs3', 'info', '2026-06-18 17:28:28'),
(180, 1, 'Cosmas Musamba', 'global_search_performed', 'search', NULL, NULL, 'Performed global search for \'cosmas\'', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'sbsc1r2ae4fh152iks8n2oijs3', 'info', '2026-06-18 17:28:34'),
(181, 1, 'Cosmas Musamba', 'trash_viewed', 'trash', NULL, NULL, 'Viewed trash list (Page 1)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'sbsc1r2ae4fh152iks8n2oijs3', 'info', '2026-06-18 17:30:09'),
(182, 1, 'Cosmas Musamba', 'dashboard_viewed', 'dashboard', NULL, NULL, 'Viewed dashboard (Scope: global)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'sbsc1r2ae4fh152iks8n2oijs3', 'info', '2026-06-18 17:30:11'),
(183, 1, 'Cosmas Musamba', 'dashboard_viewed', 'dashboard', NULL, NULL, 'Viewed dashboard (Scope: global)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'sbsc1r2ae4fh152iks8n2oijs3', 'info', '2026-06-18 17:30:13'),
(184, 1, 'Cosmas Musamba', 'dashboard_viewed', 'dashboard', NULL, NULL, 'Viewed dashboard (Scope: global)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'sbsc1r2ae4fh152iks8n2oijs3', 'info', '2026-06-18 17:33:32'),
(185, 1, 'Cosmas Musamba', 'global_search_performed', 'search', NULL, NULL, 'Performed global search for \'cosmas\'', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'sbsc1r2ae4fh152iks8n2oijs3', 'info', '2026-06-18 17:33:36'),
(186, 2, 'Test User', 'dashboard_viewed', 'dashboard', NULL, NULL, 'Viewed dashboard (Scope: personal)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '1vbe5bdprekv2k2hlbnau34bdc', 'info', '2026-06-18 17:34:42'),
(187, 2, 'Test User', 'dashboard_viewed', 'dashboard', NULL, NULL, 'Viewed dashboard (Scope: personal)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '1vbe5bdprekv2k2hlbnau34bdc', 'info', '2026-06-18 17:34:59'),
(188, 1, 'Cosmas Musamba', 'loan_viewed', 'loans', 1, 'Loan', 'Viewed loan LN-2026-00001', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'sbsc1r2ae4fh152iks8n2oijs3', 'info', '2026-06-18 17:35:35');
INSERT INTO `audit_logs` (`id`, `user_id`, `user_name`, `action`, `module`, `record_id`, `record_type`, `description`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `severity`, `created_at`) VALUES
(189, 1, 'Cosmas Musamba', 'global_search_performed', 'search', NULL, NULL, 'Performed global search for \'cosmas\'', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'sbsc1r2ae4fh152iks8n2oijs3', 'info', '2026-06-18 17:35:42'),
(190, 1, 'Cosmas Musamba', 'global_search_performed', 'search', NULL, NULL, 'Performed global search for \'cosmas\'', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'sbsc1r2ae4fh152iks8n2oijs3', 'info', '2026-06-18 17:35:49'),
(191, 1, 'Cosmas Musamba', 'transaction_viewed', 'transactions', 1, 'Transaction', 'Viewed transaction TXN-6A2EDB840B032', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'sbsc1r2ae4fh152iks8n2oijs3', 'info', '2026-06-18 17:35:57'),
(192, 1, 'Cosmas Musamba', 'global_search_performed', 'search', NULL, NULL, 'Performed global search for \'cosmas\'', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'sbsc1r2ae4fh152iks8n2oijs3', 'info', '2026-06-18 17:36:11'),
(193, 1, 'Cosmas Musamba', 'dashboard_viewed', 'dashboard', NULL, NULL, 'Viewed dashboard (Scope: global)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'sbsc1r2ae4fh152iks8n2oijs3', 'info', '2026-06-18 17:43:58'),
(194, 1, 'Cosmas Musamba', 'groups_viewed', 'savings_groups', NULL, NULL, 'Viewed groups list', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'sbsc1r2ae4fh152iks8n2oijs3', 'info', '2026-06-18 17:44:02'),
(195, 1, 'Cosmas Musamba', 'logout', 'auth', 1, 'User', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'sbsc1r2ae4fh152iks8n2oijs3', 'info', '2026-06-18 17:50:23'),
(196, 1, 'Cosmas Musamba', 'logout', 'auth', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'sbsc1r2ae4fh152iks8n2oijs3', 'info', '2026-06-18 17:50:23'),
(197, 1, 'Cosmas Musamba', 'login', 'auth', NULL, NULL, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '9clkibomg7o72lir2qrh02vaie', 'info', '2026-06-18 17:50:25'),
(198, 1, 'Cosmas Musamba', 'login', 'auth', 1, 'User', 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '9clkibomg7o72lir2qrh02vaie', 'info', '2026-06-18 17:50:25'),
(199, 1, 'Cosmas Musamba', 'dashboard_viewed', 'dashboard', NULL, NULL, 'Viewed dashboard (Scope: global)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '9clkibomg7o72lir2qrh02vaie', 'info', '2026-06-18 17:50:25'),
(200, 1, 'Cosmas Musamba', 'dashboard_viewed', 'dashboard', NULL, NULL, 'Viewed dashboard (Scope: global)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '9clkibomg7o72lir2qrh02vaie', 'info', '2026-06-18 17:51:26'),
(201, 1, 'Cosmas Musamba', 'groups_viewed', 'savings_groups', NULL, NULL, 'Viewed groups list', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '9clkibomg7o72lir2qrh02vaie', 'info', '2026-06-18 17:51:33'),
(202, 1, 'Cosmas Musamba', 'group_viewed', 'savings_groups', 1, 'Group', 'Viewed group Akabbo Social Fund', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '9clkibomg7o72lir2qrh02vaie', 'info', '2026-06-18 17:51:35'),
(203, 1, 'Cosmas Musamba', 'group_viewed', 'savings_groups', 1, 'Group', 'Viewed group Akabbo Social Fund', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '9clkibomg7o72lir2qrh02vaie', 'info', '2026-06-18 17:54:57');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
CREATE TABLE IF NOT EXISTS `documents` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference_type` enum('member','loan','transaction','system') NOT NULL,
  `reference_id` int UNSIGNED NOT NULL,
  `document_name` varchar(150) NOT NULL,
  `document_type` varchar(60) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int UNSIGNED DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `uploaded_by` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
CREATE TABLE IF NOT EXISTS `expenses` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `expense_ref` varchar(30) NOT NULL,
  `category_id` tinyint UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text,
  `amount` decimal(18,2) NOT NULL,
  `currency` varchar(5) DEFAULT 'UGX',
  `payment_method` enum('cash','mobile_money','bank_transfer','cheque') DEFAULT 'cash',
  `payee_name` varchar(150) DEFAULT NULL,
  `payee_contact` varchar(100) DEFAULT NULL,
  `receipt_no` varchar(100) DEFAULT NULL,
  `expense_date` date NOT NULL,
  `period_month` tinyint UNSIGNED DEFAULT NULL,
  `period_year` smallint UNSIGNED DEFAULT NULL,
  `status` enum('draft','pending','approved','rejected','paid') DEFAULT 'draft',
  `approved_by` int UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approval_notes` text,
  `paid_by` int UNSIGNED DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL COMMENT 'Receipt/invoice scan',
  `created_by` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `expense_ref` (`expense_ref`),
  KEY `category_id` (`category_id`),
  KEY `created_by` (`created_by`),
  KEY `approved_by` (`approved_by`),
  KEY `paid_by` (`paid_by`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `expense_ref`, `category_id`, `title`, `description`, `amount`, `currency`, `payment_method`, `payee_name`, `payee_contact`, `receipt_no`, `expense_date`, `period_month`, `period_year`, `status`, `approved_by`, `approved_at`, `approval_notes`, `paid_by`, `paid_at`, `attachment_path`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'EXP-2026-0001', 6, 'We dvdeloped Akabbo Social Fund System', NULL, 1500000.00, 'UGX', 'cash', 'Musamba Cosmas', '0708786267', '', '2026-06-13', 6, 2026, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-06-13 07:13:45', '2026-06-13 07:13:45', NULL),
(2, 'EXP-2026-0002', 6, 'We dvdeloped Akabbo Social Fund System', NULL, 1500000.00, 'UGX', 'cash', 'Musamba Cosmas', '0708786267', '', '2026-06-13', 6, 2026, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-06-13 07:17:08', '2026-06-13 07:17:08', NULL),
(3, 'EXP-2026-0003', 6, 'We dvdeloped Akabbo Social Fund System', NULL, 1500000.00, 'UGX', 'cash', 'Musamba Cosmas', '0708786267', '', '2026-06-13', 6, 2026, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-06-13 07:27:50', '2026-06-13 07:27:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--

DROP TABLE IF EXISTS `expense_categories`;
CREATE TABLE IF NOT EXISTS `expense_categories` (
  `id` tinyint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `expense_categories`
--

INSERT INTO `expense_categories` (`id`, `name`, `description`, `is_active`, `created_at`) VALUES
(1, 'Office Supplies', 'Stationery, printing, office materials', 1, '2026-06-09 14:51:12'),
(2, 'Utilities', 'Electricity, water, internet', 1, '2026-06-09 14:51:12'),
(3, 'Staff Costs', 'Salaries, allowances, welfare', 1, '2026-06-09 14:51:12'),
(4, 'Travel & Transport', 'Transport, fuel, travel allowances', 1, '2026-06-09 14:51:12'),
(5, 'Meetings & Events', 'Meeting costs, AGM, workshops', 1, '2026-06-09 14:51:12'),
(6, 'Software & Systems', 'Software licenses, IT equipment', 1, '2026-06-09 14:51:12'),
(7, 'Loan Write-offs', 'Bad debt write-offs', 1, '2026-06-09 14:51:12'),
(8, 'Auditing & Legal', 'Audit fees, legal counsel', 1, '2026-06-09 14:51:12'),
(9, 'Marketing', 'Promotion, member recruitment', 1, '2026-06-09 14:51:12'),
(10, 'Miscellaneous', 'Other operational costs', 1, '2026-06-09 14:51:12');

-- --------------------------------------------------------

--
-- Table structure for table `fund_transfers`
--

DROP TABLE IF EXISTS `fund_transfers`;
CREATE TABLE IF NOT EXISTS `fund_transfers` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `transfer_ref` varchar(30) NOT NULL,
  `from_member_id` int UNSIGNED NOT NULL,
  `to_member_id` int UNSIGNED NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `description` text,
  `transfer_date` date NOT NULL,
  `status` enum('pending','approved','rejected','completed','reversed') DEFAULT 'pending',
  `approved_by` int UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approval_notes` text,
  `rejection_notes` text,
  `reversed_by` int UNSIGNED DEFAULT NULL,
  `reversed_at` datetime DEFAULT NULL,
  `reversal_notes` text,
  `created_by` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transfer_ref` (`transfer_ref`),
  KEY `from_member_id` (`from_member_id`),
  KEY `to_member_id` (`to_member_id`),
  KEY `created_by` (`created_by`),
  KEY `approved_by` (`approved_by`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `fund_transfers`
--

INSERT INTO `fund_transfers` (`id`, `transfer_ref`, `from_member_id`, `to_member_id`, `amount`, `description`, `transfer_date`, `status`, `approved_by`, `approved_at`, `approval_notes`, `rejection_notes`, `reversed_by`, `reversed_at`, `reversal_notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'TRF-20260616-7C07E', 2, 1, 5000.00, 'Member savings deposit-Transfer', '2026-06-16', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-06-16 13:14:12', '2026-06-16 13:14:12');

-- --------------------------------------------------------

--
-- Table structure for table `loans`
--

DROP TABLE IF EXISTS `loans`;
CREATE TABLE IF NOT EXISTS `loans` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `loan_no` varchar(25) NOT NULL,
  `member_id` int UNSIGNED NOT NULL,
  `loan_product_id` tinyint UNSIGNED NOT NULL,
  `principal_amount` decimal(18,2) NOT NULL,
  `interest_rate` decimal(5,2) NOT NULL,
  `interest_type` enum('flat','reducing_balance','compound') DEFAULT NULL,
  `term_months` tinyint UNSIGNED NOT NULL,
  `processing_fee` decimal(18,2) DEFAULT '0.00',
  `insurance_fee` decimal(18,2) DEFAULT '0.00',
  `total_interest` decimal(18,2) DEFAULT '0.00',
  `total_payable` decimal(18,2) DEFAULT '0.00',
  `monthly_installment` decimal(18,2) DEFAULT '0.00',
  `amount_paid` decimal(18,2) DEFAULT '0.00',
  `balance_outstanding` decimal(18,2) DEFAULT '0.00',
  `penalty_accrued` decimal(18,2) DEFAULT '0.00',
  `purpose` text,
  `collateral_description` text,
  `disbursement_method` enum('cash','bank_transfer','mobile_money') DEFAULT 'cash',
  `disbursement_account` varchar(100) DEFAULT NULL,
  `application_date` date NOT NULL,
  `approval_date` date DEFAULT NULL,
  `approval_notes` text,
  `disbursement_date` date DEFAULT NULL,
  `first_repayment_date` date DEFAULT NULL,
  `expected_maturity_date` date DEFAULT NULL,
  `actual_maturity_date` date DEFAULT NULL,
  `status` enum('draft','pending','approved','rejected','disbursed','active','completed','defaulted','written_off','cancelled') DEFAULT 'draft',
  `approved_by` int UNSIGNED DEFAULT NULL,
  `rejected_by` int UNSIGNED DEFAULT NULL,
  `rejection_reason` text,
  `disbursed_by` int UNSIGNED DEFAULT NULL,
  `created_by` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `loan_no` (`loan_no`),
  KEY `member_id` (`member_id`),
  KEY `loan_product_id` (`loan_product_id`),
  KEY `created_by` (`created_by`),
  KEY `approved_by` (`approved_by`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `loans`
--

INSERT INTO `loans` (`id`, `loan_no`, `member_id`, `loan_product_id`, `principal_amount`, `interest_rate`, `interest_type`, `term_months`, `processing_fee`, `insurance_fee`, `total_interest`, `total_payable`, `monthly_installment`, `amount_paid`, `balance_outstanding`, `penalty_accrued`, `purpose`, `collateral_description`, `disbursement_method`, `disbursement_account`, `application_date`, `approval_date`, `approval_notes`, `disbursement_date`, `first_repayment_date`, `expected_maturity_date`, `actual_maturity_date`, `status`, `approved_by`, `rejected_by`, `rejection_reason`, `disbursed_by`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'LN-2026-00001', 1, 1, 50000.00, 8.00, 'flat', 3, 500.00, 0.00, 1000.00, 51000.00, 17000.00, 0.00, 51000.00, 0.00, 'Sickness issue', NULL, 'cash', '', '2026-06-16', NULL, NULL, NULL, '2026-07-16', '2026-09-16', NULL, 'pending', NULL, NULL, NULL, NULL, 1, '2026-06-16 14:49:25', '2026-06-16 14:49:25', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `loan_guarantors`
--

DROP TABLE IF EXISTS `loan_guarantors`;
CREATE TABLE IF NOT EXISTS `loan_guarantors` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `loan_id` int UNSIGNED NOT NULL,
  `guarantor_member_id` int UNSIGNED NOT NULL,
  `amount_guaranteed` decimal(18,2) DEFAULT NULL,
  `consent_given` tinyint(1) DEFAULT '0',
  `consent_date` datetime DEFAULT NULL,
  `status` enum('pending','confirmed','declined') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `loan_id` (`loan_id`),
  KEY `guarantor_member_id` (`guarantor_member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loan_products`
--

DROP TABLE IF EXISTS `loan_products`;
CREATE TABLE IF NOT EXISTS `loan_products` (
  `id` tinyint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `description` text,
  `min_amount` decimal(18,2) NOT NULL,
  `max_amount` decimal(18,2) NOT NULL,
  `interest_rate` decimal(5,2) NOT NULL,
  `interest_type` enum('flat','reducing_balance','compound') DEFAULT 'reducing_balance',
  `min_term_months` tinyint NOT NULL,
  `max_term_months` tinyint NOT NULL,
  `processing_fee_pct` decimal(5,2) DEFAULT '0.00',
  `insurance_fee_pct` decimal(5,2) DEFAULT '0.00',
  `requires_collateral` tinyint(1) DEFAULT '0',
  `requires_guarantor` tinyint(1) DEFAULT '0',
  `max_loan_multiplier` tinyint DEFAULT '3',
  `shareholder_rate_discount` decimal(5,2) DEFAULT '0.00' COMMENT 'Interest rate discount for shareholders',
  `shareholder_multiplier_bonus` tinyint DEFAULT '0' COMMENT 'Extra loan multiplier for shareholders',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `loan_products`
--

INSERT INTO `loan_products` (`id`, `name`, `code`, `description`, `min_amount`, `max_amount`, `interest_rate`, `interest_type`, `min_term_months`, `max_term_months`, `processing_fee_pct`, `insurance_fee_pct`, `requires_collateral`, `requires_guarantor`, `max_loan_multiplier`, `shareholder_rate_discount`, `shareholder_multiplier_bonus`, `status`, `created_at`) VALUES
(1, 'Emergency Loan', 'EMRG', 'Quick emergency financial assistance', 50000.00, 500000.00, 8.00, 'flat', 1, 3, 1.00, 0.00, 0, 0, 2, 0.00, 0, 'active', '2026-06-04 07:42:00'),
(2, 'Normal Loan', 'NORM', 'Standard member loan', 100000.00, 5000000.00, 10.00, 'reducing_balance', 3, 12, 1.50, 0.00, 0, 1, 3, 0.00, 0, 'active', '2026-06-04 07:42:00'),
(3, 'Business Loan', 'BIZ', 'Business development loan', 500000.00, 20000000.00, 12.00, 'reducing_balance', 6, 24, 2.00, 0.00, 0, 1, 3, 0.00, 0, 'active', '2026-06-04 07:42:00'),
(4, 'School Fees Loan', 'EDU', 'Education fees financing', 100000.00, 3000000.00, 8.00, 'flat', 1, 6, 1.00, 0.00, 0, 0, 2, 0.00, 0, 'active', '2026-06-04 07:42:00'),
(5, 'Development Loan', 'DEV', 'Long-term development loan', 1000000.00, 50000000.00, 15.00, 'reducing_balance', 12, 36, 2.50, 0.00, 0, 1, 3, 0.00, 0, 'active', '2026-06-04 07:42:00');

-- --------------------------------------------------------

--
-- Table structure for table `loan_repayment_schedules`
--

DROP TABLE IF EXISTS `loan_repayment_schedules`;
CREATE TABLE IF NOT EXISTS `loan_repayment_schedules` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `loan_id` int UNSIGNED NOT NULL,
  `installment_no` tinyint UNSIGNED NOT NULL,
  `due_date` date NOT NULL,
  `principal_due` decimal(18,2) NOT NULL,
  `interest_due` decimal(18,2) NOT NULL,
  `total_due` decimal(18,2) NOT NULL,
  `principal_paid` decimal(18,2) DEFAULT '0.00',
  `interest_paid` decimal(18,2) DEFAULT '0.00',
  `total_paid` decimal(18,2) DEFAULT '0.00',
  `penalty_due` decimal(18,2) DEFAULT '0.00',
  `penalty_paid` decimal(18,2) DEFAULT '0.00',
  `status` enum('upcoming','due','partially_paid','paid','overdue','waived') DEFAULT 'upcoming',
  `paid_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `loan_id` (`loan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loan_sequence`
--

DROP TABLE IF EXISTS `loan_sequence`;
CREATE TABLE IF NOT EXISTS `loan_sequence` (
  `year` year NOT NULL,
  `last_seq` int UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `loan_sequence`
--

INSERT INTO `loan_sequence` (`year`, `last_seq`) VALUES
('2026', 1);

-- --------------------------------------------------------

--
-- Table structure for table `login_sessions`
--

DROP TABLE IF EXISTS `login_sessions`;
CREATE TABLE IF NOT EXISTS `login_sessions` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL,
  `session_id` varchar(150) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `login_at` datetime NOT NULL,
  `logout_at` datetime DEFAULT NULL,
  `last_activity` datetime DEFAULT NULL,
  `status` enum('active','expired','logged_out') DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `login_sessions`
--

INSERT INTO `login_sessions` (`id`, `user_id`, `session_id`, `ip_address`, `user_agent`, `location`, `login_at`, `logout_at`, `last_activity`, `status`) VALUES
(8, 1, 'lohh003bvp6o7qsll0mi82q0u3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-13 04:43:39', NULL, '2026-06-13 04:43:39', 'active'),
(9, 1, 'h8ukut6p4pj47ter08d7viacv0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-13 04:48:29', NULL, '2026-06-13 04:48:29', 'active'),
(10, 1, 'keqg5c1hrr2f0o2b8938mchmk2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-13 04:49:57', NULL, '2026-06-13 04:49:57', 'active'),
(11, 1, 'dbecsopai5824l9qeer95aebtm', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-13 05:37:30', NULL, '2026-06-13 05:37:30', 'active'),
(12, 1, '1tcflq3hbvrfhjqrfmfuol0q8e', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-13 13:02:47', NULL, '2026-06-13 13:02:47', 'active'),
(13, 1, '56mob0vslii14qs9t5e07j3189', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', NULL, '2026-06-13 13:53:48', NULL, '2026-06-13 13:53:48', 'active'),
(14, 1, 'mopm68dfvf2k78ms9t7b6lc79u', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', NULL, '2026-06-13 16:07:07', NULL, '2026-06-13 16:07:07', 'active'),
(15, 1, 's45n7v8i839ru2g22hqnvgfhsi', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', NULL, '2026-06-13 16:38:09', NULL, '2026-06-13 16:38:09', 'active'),
(16, 1, '1gbj9hea102qoofiqp6l13knrf', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-14 09:10:13', NULL, '2026-06-14 09:10:13', 'active'),
(17, 1, 'ubbp8di5ot5hpc4lo8m2r5m8c3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-14 10:36:35', NULL, '2026-06-14 10:36:35', 'active'),
(18, 1, 'vqbt6g2q58prcdpkdom4it886k', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-14 11:01:16', NULL, '2026-06-14 11:01:16', 'active'),
(19, 2, '5ja7kjj475ejunmgb6741pnsdm', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-14 11:01:32', NULL, '2026-06-14 11:01:32', 'active'),
(20, 1, 'aiv9dc57cq9qkkhb3jmplefaqp', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-14 11:03:57', NULL, '2026-06-14 11:03:57', 'active'),
(21, 1, 'btro6cd80n9en5b0t5e0b2p7kd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-14 12:02:40', NULL, '2026-06-14 12:02:40', 'active'),
(22, 1, 'qo47de74im6gieaj6os5k6gg1l', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-14 13:14:42', NULL, '2026-06-14 13:14:42', 'active'),
(23, 2, '6k9nfctbsr6ma64rmavujg3fdn', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-14 13:18:25', NULL, '2026-06-14 13:18:25', 'active'),
(24, 1, 'e30d9q4ig8s6j63umrrvoo8c3j', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-14 13:23:34', NULL, '2026-06-14 13:23:34', 'active'),
(25, 1, 'ot5h8tf4vs0ic5gb8627o1b436', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-14 14:02:49', NULL, '2026-06-14 14:02:49', 'active'),
(26, 1, 'sd67hvre1e7e222730obk5ciqr', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-14 17:04:12', NULL, '2026-06-14 17:04:12', 'active'),
(27, 2, '5ijkpqsibj6rd00hda17h3553s', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-14 17:52:38', NULL, '2026-06-14 17:52:38', 'active'),
(28, 1, 't3nude69r4qf4rf9p0dmfh0fgi', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-14 18:53:19', NULL, '2026-06-14 18:53:19', 'active'),
(29, 1, '2qf4ra06gui48vrgj50p5jqhtn', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-14 18:58:58', NULL, '2026-06-14 18:58:58', 'active'),
(30, 1, 'mvfv0d2ufr7lmbnhsjfrt18g3d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-15 11:35:07', NULL, '2026-06-15 11:35:07', 'active'),
(31, 1, 'nocpuqak81lo166lpom631jauq', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-15 14:39:27', NULL, '2026-06-15 14:39:27', 'active'),
(32, 1, 'qffsjpui2mu8shlbr8e9eov7po', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-15 19:28:46', NULL, '2026-06-15 19:28:46', 'active'),
(33, 1, 'vnilcmdnd7271f36352lkhmvfa', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-16 09:07:13', NULL, '2026-06-16 09:07:13', 'active'),
(34, 1, 'dildbjql96grivtqlian5h5lh1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-16 12:44:40', NULL, '2026-06-16 12:44:40', 'active'),
(35, 1, 'llh3h1vqao77ok7br0a4od7akj', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-16 13:21:37', NULL, '2026-06-16 13:21:37', 'active'),
(36, 1, 'rtn9ubotdbj8bnqgbd4a8456n1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-16 14:24:59', NULL, '2026-06-16 14:24:59', 'active'),
(37, 1, 'dhltunpopengqdvplj7s5qfrc2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-16 15:46:05', NULL, '2026-06-16 15:46:05', 'active'),
(38, 1, 'h6qn2ll7tnblnqvuf12sgd6agr', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-16 17:26:02', NULL, '2026-06-16 17:26:02', 'active'),
(39, 1, 'ithb47vl5j34581ihhtj781htv', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-16 19:08:41', NULL, '2026-06-16 19:08:41', 'active'),
(40, 1, '00mfi1a338fvm6ldlru5nsd79n', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-17 17:36:26', NULL, '2026-06-17 17:36:26', 'active'),
(41, 1, 'lnmfppe3obtr7b00emd9cadnm3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-17 18:39:57', NULL, '2026-06-17 18:39:57', 'active'),
(42, 1, '27m9stm066vh8tiugrtv2ilfro', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-17 19:52:40', NULL, '2026-06-17 19:52:40', 'active'),
(43, 1, 't0mtd2dfmdskvnd04dquf5m39a', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-18 10:41:19', NULL, '2026-06-18 10:41:19', 'active'),
(44, 1, 'gjp06j8656kir1bp5r3dfn8oc2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-18 13:48:44', NULL, '2026-06-18 13:48:44', 'active'),
(45, 1, '0gcccblu3m1u01uv1gr25ju1c9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-18 15:41:44', NULL, '2026-06-18 15:41:44', 'active'),
(46, 2, 'q3e58g3hhkclsu2jcis3d79iud', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', NULL, '2026-06-18 16:09:23', NULL, '2026-06-18 16:09:23', 'active'),
(47, 2, 'c958gq81e6qn8u3gqhuomsduod', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', NULL, '2026-06-18 17:08:19', NULL, '2026-06-18 17:08:19', 'active'),
(48, 2, '4sg8e6e670p0dd9pjqc0qgij0u', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', NULL, '2026-06-18 17:10:43', NULL, '2026-06-18 17:10:43', 'active'),
(49, 2, 'v9qr37ae7pd8q5vmk6aj3cpple', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', NULL, '2026-06-18 17:32:42', NULL, '2026-06-18 17:32:42', 'active'),
(50, 1, 'ggqvqjd2hjjnc9lllm5h15dcno', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-18 18:10:24', NULL, '2026-06-18 18:10:24', 'active'),
(51, 2, 'oka4t2rvc7gclp1hnpil2entae', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', NULL, '2026-06-18 18:25:58', NULL, '2026-06-18 18:25:58', 'active'),
(52, 1, '3ibujsmr068victh1jeoklc4cl', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-18 19:30:53', NULL, '2026-06-18 19:30:53', 'active'),
(53, 1, 'ac8kjd19mdoum6o9vb7a1f2ei0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-18 19:57:05', NULL, '2026-06-18 19:57:05', 'active'),
(54, 1, '43r7n13s959d0bngmo0kc8jbk7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-18 19:57:13', NULL, '2026-06-18 19:57:13', 'active'),
(55, 2, '6o7ibdkph12uhpv7jjh2527odd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', NULL, '2026-06-18 20:20:36', NULL, '2026-06-18 20:20:36', 'active'),
(56, 1, 'nldeh43uttn7fdc7epdh3b1k23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-18 20:50:25', NULL, '2026-06-18 20:50:25', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

DROP TABLE IF EXISTS `members`;
CREATE TABLE IF NOT EXISTS `members` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_no` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `first_name` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `last_name` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `middle_name` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `gender` enum('male','female','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `national_id` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `passport_no` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `email` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `phone_alt` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `district` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `occupation` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `employer` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `next_of_kin_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `next_of_kin_phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `next_of_kin_relationship` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `id_front` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `id_back` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `membership_date` date NOT NULL,
  `membership_fee_paid` tinyint(1) DEFAULT '0',
  `membership_fee_amount` decimal(15,2) DEFAULT '0.00',
  `status` enum('active','inactive','suspended','exited') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'active',
  `kyc_verified` tinyint(1) DEFAULT '0',
  `is_shareholder` tinyint(1) DEFAULT '0',
  `shares_held` int UNSIGNED DEFAULT '0',
  `kyc_verified_by` int UNSIGNED DEFAULT NULL,
  `kyc_verified_at` datetime DEFAULT NULL,
  `user_id` int UNSIGNED DEFAULT NULL COMMENT 'Linked system user if any',
  `group_id` int UNSIGNED DEFAULT NULL,
  `created_by` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `member_no` (`member_no`),
  UNIQUE KEY `national_id` (`national_id`),
  KEY `created_by` (`created_by`),
  KEY `kyc_verified_by` (`kyc_verified_by`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `member_no`, `first_name`, `last_name`, `middle_name`, `gender`, `date_of_birth`, `national_id`, `passport_no`, `email`, `phone`, `phone_alt`, `address`, `district`, `occupation`, `employer`, `next_of_kin_name`, `next_of_kin_phone`, `next_of_kin_relationship`, `avatar`, `id_front`, `id_back`, `membership_date`, `membership_fee_paid`, `membership_fee_amount`, `status`, `kyc_verified`, `is_shareholder`, `shares_held`, `kyc_verified_by`, `kyc_verified_at`, `user_id`, `group_id`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'AKB-00001', 'Musamba', 'Cosmas', '', 'male', '1999-09-14', 'CM99060105UKRG', NULL, 'cosmasmusamba@gmail.com', '+256708786267', '+25677183163', 'Nabulagala Village', 'Kampala', 'IT Executive', 'iSON BPO', 'Bulage Angella', '+256742083569', 'Child', 'avatar_6a1c7b643917f.jpg', 'id_front_6a1ecfd0a61e9.jpg', 'id_back_6a1ecfd0a71ce.jpg', '2025-10-29', 1, 10000.00, 'active', 1, 0, 0, 1, '2026-06-14 17:42:13', NULL, 1, 1, '2026-05-31 15:18:12', '2026-06-14 14:42:13', NULL),
(2, 'AKB-00002', 'Happy', 'Rwamuhabwa', 'Alex', 'male', '2010-06-01', 'To be edited', NULL, 'happyalex@gmail.com', '+256 755 930689', '', 'Nakulabye- Lubaga Division, Kamplala', 'Kampala', 'Senior IT Executive', 'iSON BPO', 'To be edited', '+256 755 930689', 'Child', 'avatar_6a1efa8d952e4.png', NULL, NULL, '2026-06-01', 1, 10000.00, 'active', 0, 0, 0, NULL, NULL, NULL, NULL, 1, '2026-06-01 11:51:52', '2026-06-14 14:35:22', NULL),
(3, 'AKB-00003', 'Test1', 'Test1', '', 'female', '2010-06-02', 'Test1 nin', NULL, 'happyalex1@gmail.com', '+256 755 930680', '', 'Test1 wandegeya', 'Test1 kampla', 'Test1 occupation', 'Test1 bpo', 'Test1 emergency', '+256 755 930689', 'Sibling', 'avatar_6a1f08e0e14a0.jpg', 'id_front_6a1f08e0e1e36.jpg', 'id_back_6a1f08e0e2aca.jpg', '2026-06-02', 1, 10000.00, 'active', 0, 0, 0, NULL, NULL, NULL, NULL, 1, '2026-06-02 13:46:24', '2026-06-12 23:11:32', '2026-06-13 05:11:32'),
(4, 'AKB-00004', 'Musamba', 'Cosmas', '', 'male', '2010-06-08', 'CM99050105UKRG', NULL, 'cosmasmusamba5@gmail.com', '+256708786266', '+256708786265', 'Nakivubo', 'Kampala', 'Test1 occupation', 'Test1 bpo', 'Musamba Cosmas', '+256708786267', 'Spouse', 'avatar_6a2cbd57ca7192.77062446.png', 'id_front_6a2cbd58b6fc99.51043439.pdf', 'id_back_6a2cbd58b76504.79445381.pdf', '2026-06-13', 0, 0.00, 'active', 0, 0, 0, NULL, NULL, NULL, NULL, 1, '2026-06-12 23:15:51', '2026-06-12 23:18:11', '2026-06-13 05:18:11'),
(5, 'AKB-00005', 'John', 'Doe', '', 'female', '2010-06-09', 'CM99060105UKRT', NULL, 'cosmasmusamba1@gmail.com', '+256708786269', '', '-Test', 'Mukono-Test', 'IT Executive-Test', 'iSON BPO-Test', 'Musamba Cosmas-Test', '+256708786268', 'Spouse', 'avatar_6a2ecf2d8ae259.47793790.jpg', 'id_front_6a2ecf2d8c6ed0.44843267.jpg', 'id_back_6a2ecf2d8d1f11.05022623.jpg', '2026-06-14', 0, 0.00, 'active', 0, 0, 0, NULL, NULL, NULL, 1, 1, '2026-06-14 15:56:29', '2026-06-14 15:56:29', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `member_sequence`
--

DROP TABLE IF EXISTS `member_sequence`;
CREATE TABLE IF NOT EXISTS `member_sequence` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `last_seq` int UNSIGNED NOT NULL DEFAULT '0',
  `prefix` varchar(10) NOT NULL DEFAULT 'AKB',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `member_sequence`
--

INSERT INTO `member_sequence` (`id`, `last_seq`, `prefix`, `updated_at`) VALUES
(1, 5, 'AKB', '2026-06-14 15:56:29'),
(2, 3, 'AKB', '2026-06-09 14:54:29'),
(3, 3, 'AKB', '2026-06-09 14:56:10');

-- --------------------------------------------------------

--
-- Table structure for table `member_shares`
--

DROP TABLE IF EXISTS `member_shares`;
CREATE TABLE IF NOT EXISTS `member_shares` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id` int UNSIGNED NOT NULL,
  `shares_held` int UNSIGNED NOT NULL DEFAULT '0',
  `total_invested` decimal(18,2) NOT NULL DEFAULT '0.00',
  `is_shareholder` tinyint(1) NOT NULL DEFAULT '1',
  `share_date` date NOT NULL,
  `status` enum('active','suspended','exited') DEFAULT 'active',
  `created_by` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_member_shares` (`member_id`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED DEFAULT NULL,
  `member_id` int UNSIGNED DEFAULT NULL,
  `type` varchar(60) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `channel` enum('system','email','sms') DEFAULT 'system',
  `is_read` tinyint(1) DEFAULT '0',
  `read_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `send_status` enum('pending','sent','failed') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `member_id`, `type`, `title`, `message`, `channel`, `is_read`, `read_at`, `sent_at`, `send_status`, `created_at`) VALUES
(1, 1, NULL, 'login_success', 'New Login Detected', 'Dear Cosmas, a new login to your account was detected from IP ::1. If this was not you, please change your password immediately.', 'system', 0, NULL, '2026-06-17 17:36:26', 'sent', '2026-06-17 14:36:26'),
(2, 1, NULL, 'login_success', 'New Login Detected', 'Dear Cosmas, a new login to your account was detected from IP ::1. If this was not you, please change your password immediately.', 'system', 0, NULL, '2026-06-17 18:39:57', 'sent', '2026-06-17 15:39:57'),
(3, 1, NULL, 'login_success', 'New Login Detected', 'Dear Cosmas, a new login to your account was detected from IP ::1. If this was not you, please change your password immediately.', 'system', 0, NULL, '2026-06-17 19:52:40', 'sent', '2026-06-17 16:52:40'),
(4, 1, NULL, 'login_success', 'New Login Detected', 'Dear Cosmas, a new login to your account was detected from IP ::1. If this was not you, please change your password immediately.', 'system', 0, NULL, '2026-06-18 10:41:19', 'sent', '2026-06-18 07:41:19'),
(5, 1, NULL, 'login_success', 'New Login Detected', 'Dear Cosmas, a new login to your account was detected from IP ::1. If this was not you, please change your password immediately.', 'system', 0, NULL, '2026-06-18 13:48:44', 'sent', '2026-06-18 10:48:44'),
(6, 1, NULL, 'login_success', 'New Login Detected', 'Dear Cosmas, a new login to your account was detected from IP ::1. If this was not you, please change your password immediately.', 'system', 0, NULL, '2026-06-18 15:41:44', 'sent', '2026-06-18 12:41:44'),
(7, 2, NULL, 'login_success', 'New Login Detected', 'Dear Test, a new login to your account was detected from IP ::1. If this was not you, please change your password immediately.', 'system', 0, NULL, '2026-06-18 16:09:23', 'sent', '2026-06-18 13:09:23'),
(8, 2, NULL, 'user_profile_updated', 'Account Information Updated', 'Dear Test, your system account information or role has been updated by an administrator.', 'system', 0, NULL, '2026-06-18 17:08:08', 'sent', '2026-06-18 14:08:08'),
(9, 1, NULL, 'user_updated', 'User Profile Updated', 'The profile for Test User (cosmasmusamba1@gmail.com) was updated by an administrator.', 'system', 0, NULL, '2026-06-18 17:08:08', 'sent', '2026-06-18 14:08:08'),
(10, 2, NULL, 'login_success', 'New Login Detected', 'Dear Test, a new login to your account was detected from IP ::1. If this was not you, please change your password immediately.', 'system', 0, NULL, '2026-06-18 17:08:19', 'sent', '2026-06-18 14:08:19'),
(11, 2, NULL, 'user_profile_updated', 'Account Information Updated', 'Dear Test, your system account information or role has been updated by an administrator.', 'system', 0, NULL, '2026-06-18 17:10:27', 'sent', '2026-06-18 14:10:27'),
(12, 1, NULL, 'user_updated', 'User Profile Updated', 'The profile for Test User (cosmasmusamba1@gmail.com) was updated by an administrator.', 'system', 0, NULL, '2026-06-18 17:10:27', 'sent', '2026-06-18 14:10:27'),
(13, 2, NULL, 'login_success', 'New Login Detected', 'Dear Test, a new login to your account was detected from IP ::1. If this was not you, please change your password immediately.', 'system', 0, NULL, '2026-06-18 17:10:43', 'sent', '2026-06-18 14:10:43'),
(14, 2, NULL, 'role_updated', 'Your Role Updated', 'Dear Test, your role details have been updated.', 'system', 0, NULL, '2026-06-18 17:12:39', 'sent', '2026-06-18 14:12:39'),
(15, 1, NULL, 'admin_role_changed', 'System Role Changed', 'A system role (Savings Officer) was created/updated.', 'system', 0, NULL, '2026-06-18 17:12:39', 'sent', '2026-06-18 14:12:39'),
(16, 2, NULL, 'login_success', 'New Login Detected', 'Dear Test, a new login to your account was detected from IP ::1. If this was not you, please change your password immediately.', 'system', 0, NULL, '2026-06-18 17:32:42', 'sent', '2026-06-18 14:32:42'),
(17, 2, NULL, 'role_updated', 'Your Role Updated', 'Dear Test, your role details have been updated.', 'system', 0, NULL, '2026-06-18 17:33:20', 'sent', '2026-06-18 14:33:20'),
(18, 1, NULL, 'admin_role_changed', 'System Role Changed', 'A system role (Savings Officer) was created/updated.', 'system', 0, NULL, '2026-06-18 17:33:20', 'sent', '2026-06-18 14:33:20'),
(19, 2, NULL, 'role_updated', 'Your Role Updated', 'Dear Test, your role details have been updated.', 'system', 0, NULL, '2026-06-18 17:35:29', 'sent', '2026-06-18 14:35:29'),
(20, 1, NULL, 'admin_role_changed', 'System Role Changed', 'A system role (Savings Officer) was created/updated.', 'system', 0, NULL, '2026-06-18 17:35:29', 'sent', '2026-06-18 14:35:29'),
(21, 1, NULL, 'login_success', 'New Login Detected', 'Dear Cosmas, a new login to your account was detected from IP ::1. If this was not you, please change your password immediately.', 'system', 0, NULL, '2026-06-18 18:10:24', 'sent', '2026-06-18 15:10:24'),
(22, 2, NULL, 'login_success', 'New Login Detected', 'Dear Test, a new login to your account was detected from IP ::1. If this was not you, please change your password immediately.', 'system', 0, NULL, '2026-06-18 18:25:58', 'sent', '2026-06-18 15:25:58'),
(23, 1, NULL, 'login_success', 'New Login Detected', 'Dear Cosmas, a new login to your account was detected from IP ::1. If this was not you, please change your password immediately.', 'system', 0, NULL, '2026-06-18 19:30:53', 'sent', '2026-06-18 16:30:53'),
(24, 1, NULL, 'login_success', 'New Login Detected', 'Dear Cosmas, a new login to your account was detected from IP ::1. If this was not you, please change your password immediately.', 'system', 0, NULL, '2026-06-18 19:57:05', 'sent', '2026-06-18 16:57:05'),
(25, 1, NULL, 'login_success', 'New Login Detected', 'Dear Cosmas, a new login to your account was detected from IP ::1. If this was not you, please change your password immediately.', 'system', 0, NULL, '2026-06-18 19:57:13', 'sent', '2026-06-18 16:57:13'),
(26, 2, NULL, 'login_success', 'New Login Detected', 'Dear Test, a new login to your account was detected from IP ::1. If this was not you, please change your password immediately.', 'system', 0, NULL, '2026-06-18 20:20:36', 'sent', '2026-06-18 17:20:36'),
(27, 1, NULL, 'login_success', 'New Login Detected', 'Dear Cosmas, a new login to your account was detected from IP ::1. If this was not you, please change your password immediately.', 'system', 0, NULL, '2026-06-18 20:50:25', 'sent', '2026-06-18 17:50:25');

-- --------------------------------------------------------

--
-- Table structure for table `pending_debits`
--

DROP TABLE IF EXISTS `pending_debits`;
CREATE TABLE IF NOT EXISTS `pending_debits` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id` int UNSIGNED NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `reason` varchar(50) NOT NULL COMMENT 'e.g., balance_inquiry_fee, statement_request_fee',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `settled_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` smallint UNSIGNED NOT NULL AUTO_INCREMENT,
  `module` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `scope` enum('global','own','group') NOT NULL DEFAULT 'global',
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `module`, `action`, `slug`, `scope`, `description`) VALUES
(1, 'members', 'view', 'members.view', 'global', 'View members'),
(2, 'members', 'create', 'members.create', 'global', 'Create members'),
(3, 'members', 'edit', 'members.edit', 'global', 'Edit members'),
(4, 'members', 'delete', 'members.delete', 'global', 'Delete members'),
(5, 'members', 'export', 'members.export', 'global', 'Export member data'),
(6, 'loans', 'view', 'loans.view', 'global', 'View loans'),
(7, 'loans', 'create', 'loans.create', 'global', 'Create loan applications'),
(8, 'loans', 'approve', 'loans.approve', 'global', 'Approve/reject loans'),
(9, 'loans', 'disburse', 'loans.disburse', 'global', 'Disburse approved loans'),
(10, 'loans', 'edit', 'loans.edit', 'global', 'Edit loan records'),
(11, 'loans', 'delete', 'loans.delete', 'global', 'Delete loan records'),
(12, 'savings', 'view', 'savings.view', 'global', 'View savings'),
(13, 'savings', 'deposit', 'savings.deposit', 'global', 'Record deposits'),
(14, 'savings', 'withdraw', 'savings.withdraw', 'global', 'Process withdrawals'),
(15, 'savings', 'edit', 'savings.edit', 'global', 'Edit savings records'),
(16, 'transactions', 'view', 'transactions.view', 'global', 'View transactions'),
(17, 'transactions', 'create', 'transactions.create', 'global', 'Create transactions'),
(18, 'transactions', 'approve', 'transactions.approve', 'global', 'Approve transactions'),
(19, 'transactions', 'reverse', 'transactions.reverse', 'global', 'Reverse transactions'),
(20, 'reports', 'view', 'reports.view', 'global', 'View reports'),
(21, 'reports', 'export', 'reports.export', 'global', 'Export reports'),
(22, 'audit', 'view', 'audit.view', 'global', 'View audit logs'),
(23, 'settings', 'view', 'settings.view', 'global', 'View settings'),
(24, 'settings', 'edit', 'settings.edit', 'global', 'Edit settings'),
(25, 'users', 'view', 'users.view', 'global', 'View system users'),
(26, 'users', 'create', 'users.create', 'global', 'Create system users'),
(27, 'users', 'edit', 'users.edit', 'global', 'Edit system users'),
(28, 'users', 'delete', 'users.delete', 'global', 'Delete system users'),
(29, 'shares', 'view', 'shares.view', 'global', 'View share holdings'),
(30, 'shares', 'manage', 'shares.manage', 'global', 'Issue and manage shares'),
(31, 'shares', 'approve', 'shares.approve', 'global', 'Approve share transactions'),
(32, 'expenses', 'view', 'expenses.view', 'global', 'View expenses'),
(33, 'expenses', 'create', 'expenses.create', 'global', 'Create expense records'),
(34, 'expenses', 'approve', 'expenses.approve', 'global', 'Approve expenses'),
(35, 'expenses', 'delete', 'expenses.delete', 'global', 'Delete expense records'),
(36, 'approvals', 'view', 'approvals.view', 'global', 'View pending approvals'),
(37, 'approvals', 'process', 'approvals.process', 'global', 'Approve or reject items'),
(38, 'social_fund', 'view', 'social_fund.view', 'global', 'View social fund fees'),
(39, 'social_fund', 'manage', 'social_fund.manage', 'global', 'Manage social fund fees'),
(40, 'transfers', 'view', 'transfers.view', 'global', 'View fund transfers'),
(41, 'transfers', 'create', 'transfers.create', 'global', 'Initiate fund transfers'),
(42, 'permissions', 'manage', 'permissions.manage', 'global', 'Manage user access control'),
(43, 'groups', 'view', 'groups.view', 'global', 'View groups'),
(44, 'groups', 'create', 'groups.create', 'global', 'Create groups'),
(45, 'groups', 'edit', 'groups.edit', 'global', 'Edit groups'),
(46, 'groups', 'delete', 'groups.delete', 'global', 'Delete groups'),
(47, 'loan_products', 'view', 'loan_products.view', 'global', 'View loan products'),
(48, 'loan_products', 'create', 'loan_products.create', 'global', 'Create loan products'),
(49, 'loan_products', 'edit', 'loan_products.edit', 'global', 'Edit loan products'),
(50, 'loan_products', 'delete', 'loan_products.delete', 'global', 'Delete loan products'),
(51, 'import', 'upload', 'import.upload', 'global', 'Import members from CSV/Excel'),
(52, 'trash', 'view', 'trash.view', 'global', 'View trashed records'),
(53, 'trash', 'restore', 'trash.restore', 'global', 'Restore from trash'),
(54, 'trash', 'destroy', 'trash.destroy', 'global', 'Permanently delete'),
(55, 'members', 'view_own', 'members.view_own', 'own', 'Can view their own member profile'),
(56, 'savings', 'view_own', 'savings.view_own', 'own', 'Can view their own savings accounts'),
(57, 'loans', 'view_own', 'loans.view_own', 'own', 'Can view their own loan applications'),
(58, 'groups', 'view_members', 'groups.view_members', 'group', 'Can view all members in their assigned group'),
(59, 'members', 'kyc-verify', 'members.kyc-verify', 'global', 'Verify member KYC documents');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `id` tinyint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `description` text,
  `is_system` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `is_system`, `created_at`) VALUES
(1, 'Super Administrator', 'super_admin', 'Full system access', 1, '2026-06-04 07:41:59'),
(2, 'System Administrator', 'admin', 'Administrative access', 1, '2026-06-04 07:41:59'),
(3, 'Finance Manager', 'finance_manager', 'Manage finances and approvals', 0, '2026-06-04 07:41:59'),
(4, 'Loans Officer', 'loans_officer', 'Manage loan applications and disbursements', 0, '2026-06-04 07:41:59'),
(5, 'Savings Officer', 'savings_officer', 'Manage member savings', 0, '2026-06-04 07:41:59'),
(6, 'Auditor', 'auditor', 'Read-only audit access', 0, '2026-06-04 07:41:59'),
(7, 'Group Chairperson', 'chairperson', 'Group leadership access', 0, '2026-06-04 07:41:59'),
(8, 'Treasurer', 'treasurer', 'Financial records management', 0, '2026-06-04 07:41:59'),
(9, 'Member', 'member', 'Regular member with access to own records only', 0, '2026-06-04 07:41:59'),
(10, 'Group Manager', 'group_manager', 'Can manage and view all members in their assigned group', 0, '2026-06-13 14:41:41');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` tinyint UNSIGNED NOT NULL,
  `permission_id` smallint UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(2, 1),
(3, 1),
(4, 1),
(5, 1),
(6, 1),
(8, 1),
(1, 2),
(2, 2),
(1, 3),
(2, 3),
(1, 4),
(2, 4),
(1, 5),
(2, 5),
(1, 6),
(2, 6),
(3, 6),
(4, 6),
(6, 6),
(8, 6),
(1, 7),
(2, 7),
(4, 7),
(1, 8),
(2, 8),
(4, 8),
(1, 9),
(2, 9),
(4, 9),
(1, 10),
(2, 10),
(4, 10),
(1, 11),
(2, 11),
(4, 11),
(1, 12),
(2, 12),
(3, 12),
(5, 12),
(6, 12),
(8, 12),
(1, 13),
(2, 13),
(5, 13),
(1, 14),
(2, 14),
(5, 14),
(1, 15),
(2, 15),
(5, 15),
(1, 16),
(2, 16),
(3, 16),
(6, 16),
(8, 16),
(1, 17),
(2, 17),
(1, 18),
(2, 18),
(1, 19),
(2, 19),
(1, 20),
(2, 20),
(3, 20),
(4, 20),
(6, 20),
(7, 20),
(8, 20),
(1, 21),
(2, 21),
(3, 21),
(6, 21),
(1, 22),
(2, 22),
(3, 22),
(6, 22),
(1, 23),
(2, 23),
(1, 24),
(2, 24),
(1, 25),
(2, 25),
(1, 26),
(2, 26),
(1, 27),
(2, 27),
(1, 28),
(2, 28),
(1, 29),
(2, 29),
(3, 29),
(6, 29),
(8, 29),
(1, 30),
(2, 30),
(3, 30),
(1, 31),
(2, 31),
(3, 31),
(1, 32),
(2, 32),
(3, 32),
(6, 32),
(8, 32),
(1, 33),
(2, 33),
(3, 33),
(1, 34),
(2, 34),
(3, 34),
(1, 35),
(2, 35),
(1, 36),
(2, 36),
(3, 36),
(4, 36),
(6, 36),
(8, 36),
(1, 37),
(2, 37),
(3, 37),
(4, 37),
(1, 38),
(2, 38),
(3, 38),
(6, 38),
(8, 38),
(1, 39),
(2, 39),
(3, 39),
(1, 40),
(2, 40),
(3, 40),
(6, 40),
(8, 40),
(1, 41),
(2, 41),
(3, 41),
(1, 42),
(2, 42),
(1, 43),
(2, 43),
(1, 44),
(2, 44),
(1, 45),
(2, 45),
(1, 46),
(2, 46),
(1, 47),
(2, 47),
(3, 47),
(1, 48),
(2, 48),
(1, 49),
(2, 49),
(1, 50),
(2, 50),
(1, 51),
(2, 51),
(1, 52),
(2, 52),
(1, 53),
(2, 53),
(1, 54),
(2, 54),
(1, 55),
(5, 55),
(7, 55),
(9, 55),
(10, 55),
(1, 56),
(5, 56),
(7, 56),
(9, 56),
(10, 56),
(1, 57),
(7, 57),
(9, 57),
(10, 57),
(1, 58),
(7, 58),
(10, 58),
(1, 59),
(2, 59);

-- --------------------------------------------------------

--
-- Table structure for table `savings_accounts`
--

DROP TABLE IF EXISTS `savings_accounts`;
CREATE TABLE IF NOT EXISTS `savings_accounts` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_no` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_id` int UNSIGNED NOT NULL,
  `account_type` enum('regular','fixed','target','share') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'regular',
  `balance` decimal(18,2) DEFAULT '0.00',
  `interest_rate` decimal(5,2) DEFAULT '0.00',
  `target_amount` decimal(18,2) DEFAULT NULL,
  `target_date` date DEFAULT NULL,
  `status` enum('active','dormant','closed','frozen') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `opened_at` date NOT NULL,
  `closed_at` date DEFAULT NULL,
  `created_by` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `interest_accrued` decimal(18,2) DEFAULT '0.00',
  `last_interest_posted` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_no` (`account_no`),
  KEY `member_id` (`member_id`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `savings_accounts`
--

INSERT INTO `savings_accounts` (`id`, `account_no`, `member_id`, `account_type`, `balance`, `interest_rate`, `target_amount`, `target_date`, `status`, `opened_at`, `closed_at`, `created_by`, `created_at`, `updated_at`, `interest_accrued`, `last_interest_posted`) VALUES
(4, 'SAV-000004', 4, 'regular', 0.00, 0.00, NULL, NULL, 'active', '2026-06-13', NULL, 1, '2026-06-12 23:15:52', '2026-06-12 23:15:52', 0.00, NULL),
(5, 'SAV-000005', 5, 'regular', 0.00, 0.00, NULL, NULL, 'active', '2026-06-14', NULL, 1, '2026-06-14 15:56:29', '2026-06-14 15:56:29', 0.00, NULL),
(6, 'SAV-000001', 1, 'regular', 100000.00, 0.00, NULL, NULL, 'active', '2026-06-14', NULL, 1, '2026-06-14 16:49:07', '2026-06-14 16:49:08', 0.00, NULL),
(7, 'SAV-000002', 2, 'regular', 300000.00, 0.00, NULL, NULL, 'active', '2026-06-16', NULL, 1, '2026-06-16 13:13:36', '2026-06-16 13:13:36', 0.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `savings_account_sequence`
--

DROP TABLE IF EXISTS `savings_account_sequence`;
CREATE TABLE IF NOT EXISTS `savings_account_sequence` (
  `year_month` varchar(6) NOT NULL COMMENT 'Format: YYYYMM (e.g., 202606)',
  `last_seq` int UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`year_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `savings_groups`
--

DROP TABLE IF EXISTS `savings_groups`;
CREATE TABLE IF NOT EXISTS `savings_groups` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `chairperson_id` int UNSIGNED DEFAULT NULL,
  `treasurer_id` int UNSIGNED DEFAULT NULL,
  `secretary_id` int UNSIGNED DEFAULT NULL,
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meeting_schedule` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_code` (`group_code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `savings_groups`
--

INSERT INTO `savings_groups` (`id`, `group_code`, `name`, `description`, `chairperson_id`, `treasurer_id`, `secretary_id`, `avatar`, `meeting_schedule`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'GRP-0001', 'Akabbo Social Fund', 'It is not all about what you earn it&amp;amp;amp;amp;amp;apos;s what you save that matters', 2, 1, 3, 'group_6a2001f720ffd.jpeg', '', 'active', 1, '2026-06-03 05:52:38', '2026-06-03 07:29:11');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL,
  `value` text,
  `type` enum('string','integer','boolean','json') DEFAULT 'string',
  `group` varchar(50) DEFAULT 'general',
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `key`, `value`, `type`, `group`, `description`, `updated_at`) VALUES
(1, 'org_name', 'Akabbo Social Fund', 'string', 'general', 'Organization name', '2026-06-04 07:41:59'),
(2, 'org_tagline', 'Growing Together, Prospering Together', 'string', 'general', 'Organization tagline', '2026-06-04 07:41:59'),
(3, 'org_email', 'info@akabbofund.org', 'string', 'general', 'Contact email', '2026-06-04 07:41:59'),
(4, 'org_phone', '+256 700 000000', 'string', 'general', 'Contact phone', '2026-06-04 07:41:59'),
(5, 'org_address', 'Kampala, Uganda', 'string', 'general', 'Physical address', '2026-06-04 07:41:59'),
(6, 'currency', 'UGX', 'string', 'finance', 'Default currency code', '2026-06-04 07:41:59'),
(7, 'currency_symbol', 'USh', 'string', 'finance', 'Currency symbol', '2026-06-04 07:41:59'),
(8, 'min_savings', '10000', 'integer', 'finance', 'Minimum savings contribution', '2026-06-04 07:41:59'),
(9, 'loan_interest_rate', '10', 'integer', 'finance', 'Default loan interest rate (%)', '2026-06-04 07:41:59'),
(10, 'loan_interest_type', 'reducing_balance', 'string', 'finance', 'Interest calculation method', '2026-06-04 07:41:59'),
(11, 'max_loan_multiplier', '3', 'integer', 'finance', 'Max loan = multiplier x savings', '2026-06-04 07:41:59'),
(12, 'loan_processing_fee', '1', 'integer', 'finance', 'Loan processing fee (%)', '2026-06-04 07:41:59'),
(13, 'late_payment_penalty', '5', 'integer', 'finance', 'Late payment penalty rate (%)', '2026-06-04 07:41:59'),
(14, 'grace_period_days', '5', 'integer', 'finance', 'Grace period in days before penalty', '2026-06-04 07:41:59'),
(15, 'fiscal_year_start', '01-01', 'string', 'finance', 'Fiscal year start MM-DD', '2026-06-04 07:41:59'),
(16, 'session_timeout', '1800', 'integer', 'security', 'Session timeout in seconds', '2026-06-04 07:41:59'),
(17, 'max_login_attempts', '5', 'integer', 'security', 'Max login attempts before lockout', '2026-06-04 07:41:59'),
(18, 'lockout_duration', '900', 'integer', 'security', 'Account lockout duration in seconds', '2026-06-04 07:41:59'),
(19, 'sms_enabled', 'false', 'boolean', 'notifications', 'Enable SMS notifications', '2026-06-04 07:41:59'),
(20, 'email_enabled', 'true', 'boolean', 'notifications', 'Enable email notifications', '2026-06-04 07:41:59'),
(21, 'backup_frequency', 'daily', 'string', 'backup', 'Automated backup frequency', '2026-06-04 07:41:59'),
(22, 'app_version', '1.0.0', 'string', 'system', 'Application version', '2026-06-04 07:41:59'),
(23, 'require_withdrawal_approval', 'true', 'boolean', 'finance', 'Withdrawals require approval before processing', '2026-06-09 14:56:10'),
(24, 'require_transfer_approval', 'true', 'boolean', 'finance', 'Transfers between members require approval', '2026-06-09 14:56:10'),
(25, 'require_disbursement_approval', 'true', 'boolean', 'finance', 'Loan disbursements require secondary approval', '2026-06-09 14:56:10'),
(26, 'withdrawal_approval_threshold', '100000', 'integer', 'finance', 'Auto-approve withdrawals below this amount', '2026-06-09 14:56:10'),
(27, 'share_par_value', '1000', 'integer', 'finance', 'Par value per share unit (UGX)', '2026-06-09 14:56:10'),
(28, 'social_fund_fee', '5000', 'integer', 'finance', 'Monthly social fund fee amount', '2026-06-09 14:56:10'),
(29, 'social_fund_fee_due_day', '1', 'integer', 'finance', 'Day of month fee is due', '2026-06-09 14:56:10'),
(30, 'social_fund_fee_grace_days', '5', 'integer', 'finance', 'Grace days before late penalty', '2026-06-09 14:56:10'),
(31, 'social_fund_fee_penalty', '1000', 'integer', 'finance', 'Penalty for late social fund fee payment', '2026-06-09 14:56:10'),
(32, 'balance_inquiry_fee_required', 'false', 'boolean', 'service_fees', 'Enable fees for balance inquiries', '2026-06-16 06:11:44'),
(33, 'balance_inquiry_fee_amount', '500', 'integer', 'service_fees', 'Fee amount for balance inquiry (USh)', '2026-06-16 06:11:44'),
(34, 'balance_inquiry_free_limit', '3', 'integer', 'service_fees', 'Number of free inquiries per frequency period', '2026-06-16 06:11:44'),
(35, 'balance_inquiry_charge_frequency', 'daily', 'string', 'service_fees', 'Frequency for free limit reset (daily, monthly, per_request)', '2026-06-16 06:11:44'),
(36, 'statement_request_fee_required', 'true', 'boolean', 'service_fees', 'Enable fees for statement requests', '2026-06-16 06:11:44'),
(37, 'statement_request_fee_amount', '2000', 'integer', 'service_fees', 'Fee amount for statement request (USh)', '2026-06-16 06:11:44'),
(38, 'statement_request_free_limit', '1', 'integer', 'service_fees', 'Number of free statements per frequency period', '2026-06-16 06:11:44'),
(39, 'statement_request_charge_frequency', 'monthly', 'string', 'service_fees', 'Frequency for free limit reset (daily, monthly, per_request)', '2026-06-16 06:11:44');

-- --------------------------------------------------------

--
-- Table structure for table `share_config`
--

DROP TABLE IF EXISTS `share_config`;
CREATE TABLE IF NOT EXISTS `share_config` (
  `id` tinyint UNSIGNED NOT NULL AUTO_INCREMENT,
  `par_value` decimal(15,2) NOT NULL DEFAULT '1000.00' COMMENT 'Price per share unit (UGX)',
  `min_shares` int UNSIGNED NOT NULL DEFAULT '1',
  `max_shares_per_member` int UNSIGNED NOT NULL DEFAULT '1000',
  `loan_rate_discount` decimal(5,2) NOT NULL DEFAULT '2.00' COMMENT '% discount off standard rate for shareholders',
  `loan_multiplier_bonus` tinyint NOT NULL DEFAULT '1' COMMENT 'Extra multiplier for shareholders',
  `dividend_rate` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'Annual dividend rate %',
  `is_transferable` tinyint(1) NOT NULL DEFAULT '0',
  `updated_by` int UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `updated_by` (`updated_by`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `share_config`
--

INSERT INTO `share_config` (`id`, `par_value`, `min_shares`, `max_shares_per_member`, `loan_rate_discount`, `loan_multiplier_bonus`, `dividend_rate`, `is_transferable`, `updated_by`, `updated_at`) VALUES
(1, 1000.00, 1, 1000, 2.00, 1, 5.00, 1, 1, '2026-06-13 09:15:39'),
(2, 1000.00, 1, 1000, 2.00, 1, 5.00, 0, NULL, '2026-06-09 14:54:29'),
(3, 1000.00, 1, 1000, 2.00, 1, 5.00, 0, NULL, '2026-06-09 14:56:10');

-- --------------------------------------------------------

--
-- Table structure for table `share_transactions`
--

DROP TABLE IF EXISTS `share_transactions`;
CREATE TABLE IF NOT EXISTS `share_transactions` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `txn_ref` varchar(30) NOT NULL,
  `member_id` int UNSIGNED NOT NULL,
  `txn_type` enum('purchase','sale','transfer_in','transfer_out','dividend','refund') NOT NULL,
  `shares_qty` int UNSIGNED NOT NULL,
  `par_value` decimal(15,2) NOT NULL,
  `total_amount` decimal(18,2) NOT NULL,
  `to_member_id` int UNSIGNED DEFAULT NULL COMMENT 'For transfers',
  `notes` text,
  `payment_method` enum('cash','mobile_money','bank_transfer','deduction') DEFAULT 'cash',
  `transaction_date` date NOT NULL,
  `status` enum('pending','approved','rejected','completed') DEFAULT 'pending',
  `approved_by` int UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approval_notes` text,
  `created_by` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `txn_ref` (`txn_ref`),
  KEY `member_id` (`member_id`),
  KEY `to_member_id` (`to_member_id`),
  KEY `created_by` (`created_by`),
  KEY `approved_by` (`approved_by`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `share_transactions`
--

INSERT INTO `share_transactions` (`id`, `txn_ref`, `member_id`, `txn_type`, `shares_qty`, `par_value`, `total_amount`, `to_member_id`, `notes`, `payment_method`, `transaction_date`, `status`, `approved_by`, `approved_at`, `approval_notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'SHR-20260613-3C559', 1, 'purchase', 10, 0.00, 10000.00, NULL, 'Shares issued to Cosmas are for testing', 'cash', '2026-06-13', 'pending', NULL, NULL, NULL, 1, '2026-06-13 09:14:55', '2026-06-13 09:14:55'),
(2, 'SHR-20260614-D5D15', 1, 'purchase', 5, 0.00, 5000.00, NULL, 'Testing share purchase', 'cash', '2026-06-14', 'pending', NULL, NULL, NULL, 1, '2026-06-14 17:05:38', '2026-06-14 17:05:38');

-- --------------------------------------------------------

--
-- Table structure for table `social_fund_fees`
--

DROP TABLE IF EXISTS `social_fund_fees`;
CREATE TABLE IF NOT EXISTS `social_fund_fees` (
  `id` tinyint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT 'Monthly Social Fund Fee',
  `amount` decimal(15,2) NOT NULL,
  `frequency` enum('monthly','quarterly','annually','once') DEFAULT 'monthly',
  `due_day` tinyint NOT NULL DEFAULT '1' COMMENT 'Day of month fee is due',
  `grace_days` tinyint NOT NULL DEFAULT '5',
  `penalty_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Fixed penalty for late payment',
  `is_mandatory` tinyint(1) NOT NULL DEFAULT '1',
  `applies_to` enum('all','shareholders','non_shareholders') DEFAULT 'all',
  `status` enum('active','inactive') DEFAULT 'active',
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `created_by` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `social_fund_fees`
--

INSERT INTO `social_fund_fees` (`id`, `name`, `amount`, `frequency`, `due_day`, `grace_days`, `penalty_amount`, `is_mandatory`, `applies_to`, `status`, `effective_from`, `effective_to`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Monthly Social Fund Fee', 5000.00, 'monthly', 1, 5, 1000.00, 1, 'all', 'inactive', '2026-06-09', NULL, 1, '2026-06-09 14:51:11', '2026-06-13 08:49:31'),
(2, 'Monthly Social Fund Fee', 5000.00, 'monthly', 1, 5, 1000.00, 1, 'all', 'inactive', '2026-06-09', NULL, 1, '2026-06-09 14:54:29', '2026-06-13 08:49:31'),
(3, 'Monthly Social Fund Fee', 5000.00, 'monthly', 1, 5, 1000.00, 1, 'all', 'inactive', '2026-06-09', NULL, 1, '2026-06-09 14:56:10', '2026-06-13 08:49:31'),
(4, 'Social Fee', 3000.00, 'monthly', 1, 5, 0.00, 1, 'all', 'active', '2026-06-01', NULL, 1, '2026-06-13 08:49:31', '2026-06-13 08:49:31');

-- --------------------------------------------------------

--
-- Table structure for table `social_fund_fee_payments`
--

DROP TABLE IF EXISTS `social_fund_fee_payments`;
CREATE TABLE IF NOT EXISTS `social_fund_fee_payments` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `fee_id` tinyint UNSIGNED NOT NULL,
  `member_id` int UNSIGNED NOT NULL,
  `period_month` tinyint UNSIGNED NOT NULL COMMENT '1-12',
  `period_year` smallint UNSIGNED NOT NULL,
  `amount_due` decimal(15,2) NOT NULL,
  `amount_paid` decimal(15,2) NOT NULL DEFAULT '0.00',
  `penalty_paid` decimal(15,2) NOT NULL DEFAULT '0.00',
  `paid_date` date DEFAULT NULL,
  `payment_method` enum('cash','mobile_money','bank_transfer','deduction') DEFAULT 'cash',
  `status` enum('pending','paid','partial','overdue','waived') DEFAULT 'pending',
  `waived_by` int UNSIGNED DEFAULT NULL,
  `waived_reason` text,
  `created_by` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fee_member_period` (`fee_id`,`member_id`,`period_month`,`period_year`),
  KEY `member_id` (`member_id`),
  KEY `created_by` (`created_by`),
  KEY `waived_by` (`waived_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `txn_ref` varchar(30) NOT NULL,
  `txn_type` enum('deposit','withdrawal','loan_disbursement','loan_repayment','membership_fee','penalty','interest','transfer','reversal','balance_inquiry_fee','statement_request_fee') NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `member_id` int UNSIGNED DEFAULT NULL,
  `savings_account_id` int UNSIGNED DEFAULT NULL,
  `loan_id` int UNSIGNED DEFAULT NULL,
  `reference_txn_id` int UNSIGNED DEFAULT NULL COMMENT 'For reversals',
  `payment_method` enum('cash','bank_transfer','mobile_money','cheque','internal') DEFAULT 'cash',
  `payment_channel` varchar(60) DEFAULT NULL,
  `external_ref` varchar(100) DEFAULT NULL COMMENT 'External payment reference',
  `description` text,
  `balance_before` decimal(18,2) DEFAULT NULL,
  `balance_after` decimal(18,2) DEFAULT NULL,
  `status` enum('pending','approved','rejected','reversed','completed') DEFAULT 'pending',
  `approved_by` int UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text,
  `approval_notes` text,
  `transaction_date` date NOT NULL,
  `created_by` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `txn_ref` (`txn_ref`),
  KEY `member_id` (`member_id`),
  KEY `savings_account_id` (`savings_account_id`),
  KEY `created_by` (`created_by`),
  KEY `approved_by` (`approved_by`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `txn_ref`, `txn_type`, `amount`, `member_id`, `savings_account_id`, `loan_id`, `reference_txn_id`, `payment_method`, `payment_channel`, `external_ref`, `description`, `balance_before`, `balance_after`, `status`, `approved_by`, `approved_at`, `rejection_reason`, `approval_notes`, `transaction_date`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'TXN-6A2EDB840B032', 'deposit', 100000.00, 1, 6, NULL, NULL, 'cash', NULL, '', 'Member savings deposit', 0.00, 100000.00, 'completed', NULL, NULL, NULL, NULL, '2026-06-14', 1, '2026-06-14 16:49:08', '2026-06-14 16:49:08', NULL),
(2, 'WDR-6A2EE0614693B', 'withdrawal', 50000.00, 1, 6, NULL, NULL, 'cash', NULL, NULL, 'Member savings withdrawal', 100000.00, 50000.00, 'pending', NULL, NULL, NULL, NULL, '2026-06-14', 1, '2026-06-14 17:09:53', '2026-06-14 17:09:53', NULL),
(3, 'TXN-6A314C000E7BB', 'deposit', 300000.00, 2, 7, NULL, NULL, 'cash', NULL, '', 'Member savings deposit', 0.00, 300000.00, 'completed', NULL, NULL, NULL, NULL, '2026-06-16', 1, '2026-06-16 13:13:36', '2026-06-16 13:13:36', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `trash`
--

DROP TABLE IF EXISTS `trash`;
CREATE TABLE IF NOT EXISTS `trash` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `record_type` varchar(60) NOT NULL,
  `record_id` int UNSIGNED NOT NULL,
  `record_data` json DEFAULT NULL,
  `deleted_by` int UNSIGNED DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `restored_at` datetime DEFAULT NULL,
  `restored_by` int UNSIGNED DEFAULT NULL,
  `permanently_deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deleted_by` (`deleted_by`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `trash`
--

INSERT INTO `trash` (`id`, `record_type`, `record_id`, `record_data`, `deleted_by`, `deleted_at`, `restored_at`, `restored_by`, `permanently_deleted_at`) VALUES
(1, 'members', 3, '{\"id\": 3, \"email\": \"happyalex1@gmail.com\", \"phone\": \"+256 755 930680\", \"avatar\": \"avatar_6a1f08e0e14a0.jpg\", \"gender\": \"female\", \"status\": \"active\", \"address\": \"Test1 wandegeya\", \"id_back\": \"id_back_6a1f08e0e2aca.jpg\", \"user_id\": null, \"district\": \"Test1 kampla\", \"employer\": \"Test1 bpo\", \"group_id\": null, \"id_front\": \"id_front_6a1f08e0e1e36.jpg\", \"last_name\": \"Test1\", \"member_no\": \"AKB-00003\", \"phone_alt\": \"\", \"created_at\": \"2026-06-02 19:46:24\", \"created_by\": 1, \"deleted_at\": null, \"first_name\": \"Test1\", \"occupation\": \"Test1 occupation\", \"updated_at\": \"2026-06-02 19:46:24\", \"middle_name\": \"\", \"national_id\": \"Test1 nin\", \"passport_no\": null, \"shares_held\": 0, \"kyc_verified\": 0, \"date_of_birth\": \"2010-06-02\", \"is_shareholder\": 0, \"kyc_verified_at\": null, \"kyc_verified_by\": null, \"membership_date\": \"2026-06-02\", \"next_of_kin_name\": \"Test1 emergency\", \"next_of_kin_phone\": \"+256 755 930689\", \"membership_fee_paid\": 1, \"membership_fee_amount\": \"10000.00\", \"next_of_kin_relationship\": \"Sibling\"}', 1, '2026-06-12 23:11:31', NULL, NULL, NULL),
(2, 'members', 4, '{\"id\": 4, \"email\": \"cosmasmusamba5@gmail.com\", \"phone\": \"+256708786266\", \"avatar\": \"avatar_6a2cbd57ca7192.77062446.png\", \"gender\": \"male\", \"status\": \"active\", \"address\": \"Nakivubo\", \"id_back\": \"id_back_6a2cbd58b76504.79445381.pdf\", \"user_id\": null, \"district\": \"Kampala\", \"employer\": \"Test1 bpo\", \"group_id\": null, \"id_front\": \"id_front_6a2cbd58b6fc99.51043439.pdf\", \"last_name\": \"Cosmas\", \"member_no\": \"AKB-00004\", \"phone_alt\": \"+256708786265\", \"created_at\": \"2026-06-13 05:15:51\", \"created_by\": 1, \"deleted_at\": null, \"first_name\": \"Musamba\", \"occupation\": \"Test1 occupation\", \"updated_at\": \"2026-06-13 05:15:52\", \"middle_name\": \"\", \"national_id\": \"CM99050105UKRG\", \"passport_no\": null, \"shares_held\": 0, \"kyc_verified\": 0, \"date_of_birth\": \"2010-06-08\", \"is_shareholder\": 0, \"kyc_verified_at\": null, \"kyc_verified_by\": null, \"membership_date\": \"2026-06-13\", \"next_of_kin_name\": \"Musamba Cosmas\", \"next_of_kin_phone\": \"+256708786267\", \"membership_fee_paid\": 0, \"membership_fee_amount\": \"0.00\", \"next_of_kin_relationship\": \"Spouse\"}', 1, '2026-06-12 23:18:11', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(20) DEFAULT NULL,
  `first_name` varchar(60) NOT NULL,
  `last_name` varchar(60) NOT NULL,
  `email` varchar(120) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `nin` varchar(50) NOT NULL DEFAULT '',
  `passport_number` varchar(50) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` tinyint UNSIGNED NOT NULL,
  `is_super_admin` tinyint(1) NOT NULL DEFAULT '0',
  `member_id` int UNSIGNED DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','suspended','locked') DEFAULT 'active',
  `email_verified` tinyint(1) DEFAULT '0',
  `must_change_password` tinyint(1) DEFAULT '0',
  `failed_login_attempts` tinyint DEFAULT '0',
  `locked_until` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `password_reset_token` varchar(100) DEFAULT NULL,
  `password_reset_expires` datetime DEFAULT NULL,
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `employee_id` (`employee_id`),
  KEY `role_id` (`role_id`),
  KEY `fk_users_member` (`member_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `employee_id`, `first_name`, `last_name`, `email`, `phone`, `nin`, `passport_number`, `password_hash`, `role_id`, `is_super_admin`, `member_id`, `avatar`, `status`, `email_verified`, `must_change_password`, `failed_login_attempts`, `locked_until`, `last_login`, `last_login_ip`, `password_reset_token`, `password_reset_expires`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'EMP-0001', 'Cosmas', 'Musamba', 'cosmasmusamba@gmail.com', '+256700000000', '', NULL, '$2y$12$Z.e/nePsMni05MmmvJ.Km.DV2sK9d5OVUW2TQOc3YIuVvBF8mcNTS', 1, 1, 1, NULL, 'active', 1, 0, 0, NULL, '2026-06-18 20:50:25', '::1', NULL, NULL, NULL, '2026-05-31 14:59:14', '2026-06-18 17:50:25'),
(2, NULL, 'Test', 'User', 'cosmasmusamba1@gmail.com', '+256708786260', '', NULL, '$2y$12$tV188Vgid5OW6Pu2dF9QVuoshWwhJsE3wGnDJ3TzOREBsYDpkkDj.', 5, 0, 5, NULL, 'active', 0, 1, 0, NULL, '2026-06-18 20:20:36', '::1', NULL, NULL, 1, '2026-06-14 08:01:06', '2026-06-18 17:20:36');

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

DROP TABLE IF EXISTS `user_permissions`;
CREATE TABLE IF NOT EXISTS `user_permissions` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL,
  `permission_id` smallint UNSIGNED NOT NULL,
  `is_allowed` tinyint(1) NOT NULL COMMENT '1=ALLOW, 0=DENY',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_permission` (`user_id`,`permission_id`),
  KEY `fk_user_permissions_permission_id` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_loan_overview`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `v_loan_overview`;
CREATE TABLE IF NOT EXISTS `v_loan_overview` (
`id` int unsigned
,`loan_no` varchar(25)
,`member_name` varchar(121)
,`member_no` varchar(20)
,`phone` varchar(20)
,`product_name` varchar(100)
,`principal_amount` decimal(18,2)
,`total_payable` decimal(18,2)
,`amount_paid` decimal(18,2)
,`balance_outstanding` decimal(18,2)
,`term_months` tinyint unsigned
,`interest_rate` decimal(5,2)
,`status` enum('draft','pending','approved','rejected','disbursed','active','completed','defaulted','written_off','cancelled')
,`application_date` date
,`disbursement_date` date
,`expected_maturity_date` date
);

-- --------------------------------------------------------

--
-- Structure for view `v_loan_overview`
--
DROP TABLE IF EXISTS `v_loan_overview`;

DROP VIEW IF EXISTS `v_loan_overview`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_loan_overview`  AS SELECT `l`.`id` AS `id`, `l`.`loan_no` AS `loan_no`, concat(`m`.`first_name`,' ',`m`.`last_name`) AS `member_name`, `m`.`member_no` AS `member_no`, `m`.`phone` AS `phone`, `lp`.`name` AS `product_name`, `l`.`principal_amount` AS `principal_amount`, `l`.`total_payable` AS `total_payable`, `l`.`amount_paid` AS `amount_paid`, `l`.`balance_outstanding` AS `balance_outstanding`, `l`.`term_months` AS `term_months`, `l`.`interest_rate` AS `interest_rate`, `l`.`status` AS `status`, `l`.`application_date` AS `application_date`, `l`.`disbursement_date` AS `disbursement_date`, `l`.`expected_maturity_date` AS `expected_maturity_date` FROM ((`loans` `l` join `members` `m` on((`m`.`id` = `l`.`member_id`))) join `loan_products` `lp` on((`lp`.`id` = `l`.`loan_product_id`))) WHERE (`l`.`deleted_at` is null) ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `approvals`
--
ALTER TABLE `approvals`
  ADD CONSTRAINT `approvals_ibfk_1` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `approvals_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`),
  ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `expenses_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `expenses_ibfk_4` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `fund_transfers`
--
ALTER TABLE `fund_transfers`
  ADD CONSTRAINT `fund_transfers_ibfk_1` FOREIGN KEY (`from_member_id`) REFERENCES `members` (`id`),
  ADD CONSTRAINT `fund_transfers_ibfk_2` FOREIGN KEY (`to_member_id`) REFERENCES `members` (`id`),
  ADD CONSTRAINT `fund_transfers_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fund_transfers_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `member_shares`
--
ALTER TABLE `member_shares`
  ADD CONSTRAINT `member_shares_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`),
  ADD CONSTRAINT `member_shares_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `share_config`
--
ALTER TABLE `share_config`
  ADD CONSTRAINT `share_config_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `share_transactions`
--
ALTER TABLE `share_transactions`
  ADD CONSTRAINT `share_transactions_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`),
  ADD CONSTRAINT `share_transactions_ibfk_2` FOREIGN KEY (`to_member_id`) REFERENCES `members` (`id`),
  ADD CONSTRAINT `share_transactions_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `share_transactions_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `social_fund_fees`
--
ALTER TABLE `social_fund_fees`
  ADD CONSTRAINT `social_fund_fees_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `social_fund_fee_payments`
--
ALTER TABLE `social_fund_fee_payments`
  ADD CONSTRAINT `social_fund_fee_payments_ibfk_1` FOREIGN KEY (`fee_id`) REFERENCES `social_fund_fees` (`id`),
  ADD CONSTRAINT `social_fund_fee_payments_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`),
  ADD CONSTRAINT `social_fund_fee_payments_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `social_fund_fee_payments_ibfk_4` FOREIGN KEY (`waived_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `fk_user_permissions_permission_id` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_permissions_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
