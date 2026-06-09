-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jun 09, 2026 at 05:57 PM
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
(1, 'Office Supplies', 'Stationery, printing, office materials', 1, '2026-06-09 17:51:12'),
(2, 'Utilities', 'Electricity, water, internet', 1, '2026-06-09 17:51:12'),
(3, 'Staff Costs', 'Salaries, allowances, welfare', 1, '2026-06-09 17:51:12'),
(4, 'Travel & Transport', 'Transport, fuel, travel allowances', 1, '2026-06-09 17:51:12'),
(5, 'Meetings & Events', 'Meeting costs, AGM, workshops', 1, '2026-06-09 17:51:12'),
(6, 'Software & Systems', 'Software licenses, IT equipment', 1, '2026-06-09 17:51:12'),
(7, 'Loan Write-offs', 'Bad debt write-offs', 1, '2026-06-09 17:51:12'),
(8, 'Auditing & Legal', 'Audit fees, legal counsel', 1, '2026-06-09 17:51:12'),
(9, 'Marketing', 'Promotion, member recruitment', 1, '2026-06-09 17:51:12'),
(10, 'Miscellaneous', 'Other operational costs', 1, '2026-06-09 17:51:12');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `groups`
--

DROP TABLE IF EXISTS `groups`;
CREATE TABLE IF NOT EXISTS `groups` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_code` varchar(20) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `description` text,
  `chairperson_id` int UNSIGNED DEFAULT NULL,
  `treasurer_id` int UNSIGNED DEFAULT NULL,
  `secretary_id` int UNSIGNED DEFAULT NULL,
  `meeting_schedule` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive','closed') DEFAULT 'active',
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_code` (`group_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
(1, 'Emergency Loan', 'EMRG', 'Quick emergency financial assistance', 50000.00, 500000.00, 8.00, 'flat', 1, 3, 1.00, 0.00, 0, 0, 2, 0.00, 0, 'active', '2026-06-04 10:42:00'),
(2, 'Normal Loan', 'NORM', 'Standard member loan', 100000.00, 5000000.00, 10.00, 'reducing_balance', 3, 12, 1.50, 0.00, 0, 1, 3, 0.00, 0, 'active', '2026-06-04 10:42:00'),
(3, 'Business Loan', 'BIZ', 'Business development loan', 500000.00, 20000000.00, 12.00, 'reducing_balance', 6, 24, 2.00, 0.00, 0, 1, 3, 0.00, 0, 'active', '2026-06-04 10:42:00'),
(4, 'School Fees Loan', 'EDU', 'Education fees financing', 100000.00, 3000000.00, 8.00, 'flat', 1, 6, 1.00, 0.00, 0, 0, 2, 0.00, 0, 'active', '2026-06-04 10:42:00'),
(5, 'Development Loan', 'DEV', 'Long-term development loan', 1000000.00, 50000000.00, 15.00, 'reducing_balance', 12, 36, 2.50, 0.00, 0, 1, 3, 0.00, 0, 'active', '2026-06-04 10:42:00');

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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

DROP TABLE IF EXISTS `members`;
CREATE TABLE IF NOT EXISTS `members` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_no` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `middle_name` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` enum('male','female','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `national_id` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `passport_no` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone_alt` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `district` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `occupation` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employer` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `next_of_kin_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `next_of_kin_phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `next_of_kin_relationship` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_front` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_back` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `membership_date` date NOT NULL,
  `membership_fee_paid` tinyint(1) DEFAULT '0',
  `membership_fee_amount` decimal(15,2) DEFAULT '0.00',
  `status` enum('active','inactive','suspended','exited') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `member_no`, `first_name`, `last_name`, `middle_name`, `gender`, `date_of_birth`, `national_id`, `passport_no`, `email`, `phone`, `phone_alt`, `address`, `district`, `occupation`, `employer`, `next_of_kin_name`, `next_of_kin_phone`, `next_of_kin_relationship`, `avatar`, `id_front`, `id_back`, `membership_date`, `membership_fee_paid`, `membership_fee_amount`, `status`, `kyc_verified`, `is_shareholder`, `shares_held`, `kyc_verified_by`, `kyc_verified_at`, `user_id`, `group_id`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'AKB-00001', 'Musamba', 'Cosmas', '', 'male', '1999-09-14', 'CM99060105UKRG', NULL, 'cosmasmusamba@gmail.com', '+256708786267', '+25677183163', 'Nabulagala Village', 'Kampala', 'IT Executive', 'iSON BPO', 'Bulage Angella', '+256742083569', 'Child', 'avatar_6a1c7b643917f.jpg', 'id_front_6a1ecfd0a61e9.jpg', 'id_back_6a1ecfd0a71ce.jpg', '2025-10-29', 1, 10000.00, 'active', 0, 0, 0, NULL, NULL, NULL, 1, 1, '2026-05-31 18:18:12', '2026-06-03 08:53:55', NULL),
(2, 'AKB-00002', 'Happy', 'Rwamuhabwa', 'Alex', 'male', '2010-06-01', 'To be edited', NULL, 'happyalex@gmail.com', '+256 755 930689', '', 'Nakulabye- Lubaga Division, Kamplala', 'Kampala', 'Senior IT Executive', 'iSON BPO', 'To be edited', '+256 755 930689', 'Child', 'avatar_6a1efa8d952e4.png', NULL, NULL, '2026-06-01', 1, 10000.00, 'active', 0, 0, 0, NULL, NULL, NULL, 1, 1, '2026-06-01 14:51:52', '2026-06-03 08:54:10', NULL),
(3, 'AKB-00003', 'Test1', 'Test1', '', 'female', '2010-06-02', 'Test1 nin', NULL, 'happyalex1@gmail.com', '+256 755 930680', '', 'Test1 wandegeya', 'Test1 kampla', 'Test1 occupation', 'Test1 bpo', 'Test1 emergency', '+256 755 930689', 'Sibling', 'avatar_6a1f08e0e14a0.jpg', 'id_front_6a1f08e0e1e36.jpg', 'id_back_6a1f08e0e2aca.jpg', '2026-06-02', 1, 10000.00, 'active', 0, 0, 0, NULL, NULL, NULL, NULL, 1, '2026-06-02 16:46:24', '2026-06-02 16:46:24', NULL);

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
(1, 3, 'AKB', '2026-06-09 17:51:11'),
(2, 3, 'AKB', '2026-06-09 17:54:29'),
(3, 3, 'AKB', '2026-06-09 17:56:10');

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
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `module`, `action`, `slug`, `description`) VALUES
(1, 'members', 'view', 'members.view', 'View members'),
(2, 'members', 'create', 'members.create', 'Create members'),
(3, 'members', 'edit', 'members.edit', 'Edit members'),
(4, 'members', 'delete', 'members.delete', 'Delete members'),
(5, 'members', 'export', 'members.export', 'Export member data'),
(6, 'loans', 'view', 'loans.view', 'View loans'),
(7, 'loans', 'create', 'loans.create', 'Create loan applications'),
(8, 'loans', 'approve', 'loans.approve', 'Approve/reject loans'),
(9, 'loans', 'disburse', 'loans.disburse', 'Disburse approved loans'),
(10, 'loans', 'edit', 'loans.edit', 'Edit loan records'),
(11, 'loans', 'delete', 'loans.delete', 'Delete loan records'),
(12, 'savings', 'view', 'savings.view', 'View savings'),
(13, 'savings', 'deposit', 'savings.deposit', 'Record deposits'),
(14, 'savings', 'withdraw', 'savings.withdraw', 'Process withdrawals'),
(15, 'savings', 'edit', 'savings.edit', 'Edit savings records'),
(16, 'transactions', 'view', 'transactions.view', 'View transactions'),
(17, 'transactions', 'create', 'transactions.create', 'Create transactions'),
(18, 'transactions', 'approve', 'transactions.approve', 'Approve transactions'),
(19, 'transactions', 'reverse', 'transactions.reverse', 'Reverse transactions'),
(20, 'reports', 'view', 'reports.view', 'View reports'),
(21, 'reports', 'export', 'reports.export', 'Export reports'),
(22, 'audit', 'view', 'audit.view', 'View audit logs'),
(23, 'settings', 'view', 'settings.view', 'View settings'),
(24, 'settings', 'edit', 'settings.edit', 'Edit settings'),
(25, 'users', 'view', 'users.view', 'View system users'),
(26, 'users', 'create', 'users.create', 'Create system users'),
(27, 'users', 'edit', 'users.edit', 'Edit system users'),
(28, 'users', 'delete', 'users.delete', 'Delete system users'),
(29, 'shares', 'view', 'shares.view', 'View share holdings'),
(30, 'shares', 'manage', 'shares.manage', 'Issue and manage shares'),
(31, 'shares', 'approve', 'shares.approve', 'Approve share transactions'),
(32, 'expenses', 'view', 'expenses.view', 'View expenses'),
(33, 'expenses', 'create', 'expenses.create', 'Create expense records'),
(34, 'expenses', 'approve', 'expenses.approve', 'Approve expenses'),
(35, 'expenses', 'delete', 'expenses.delete', 'Delete expense records'),
(36, 'approvals', 'view', 'approvals.view', 'View pending approvals'),
(37, 'approvals', 'process', 'approvals.process', 'Approve or reject items'),
(38, 'social_fund', 'view', 'social_fund.view', 'View social fund fees'),
(39, 'social_fund', 'manage', 'social_fund.manage', 'Manage social fund fees'),
(40, 'transfers', 'view', 'transfers.view', 'View fund transfers'),
(41, 'transfers', 'create', 'transfers.create', 'Initiate fund transfers');

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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `is_system`, `created_at`) VALUES
(1, 'Super Administrator', 'super_admin', 'Full system access', 1, '2026-06-04 10:41:59'),
(2, 'System Administrator', 'admin', 'Administrative access', 1, '2026-06-04 10:41:59'),
(3, 'Finance Manager', 'finance_manager', 'Manage finances and approvals', 0, '2026-06-04 10:41:59'),
(4, 'Loans Officer', 'loans_officer', 'Manage loan applications and disbursements', 0, '2026-06-04 10:41:59'),
(5, 'Savings Officer', 'savings_officer', 'Manage member savings', 0, '2026-06-04 10:41:59'),
(6, 'Auditor', 'auditor', 'Read-only audit access', 0, '2026-06-04 10:41:59'),
(7, 'Group Chairperson', 'chairperson', 'Group leadership access', 0, '2026-06-04 10:41:59'),
(8, 'Treasurer', 'treasurer', 'Financial records management', 0, '2026-06-04 10:41:59'),
(9, 'Standard Member', 'member', 'Basic member access', 0, '2026-06-04 10:41:59');

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
(1, 29),
(3, 29),
(8, 29),
(1, 30),
(3, 30),
(1, 31),
(1, 32),
(3, 32),
(8, 32),
(1, 33),
(3, 33),
(1, 34),
(3, 34),
(1, 35),
(1, 36),
(3, 36),
(8, 36),
(1, 37),
(3, 37),
(1, 38),
(3, 38),
(8, 38),
(1, 39),
(3, 39),
(1, 40),
(3, 40),
(8, 40),
(1, 41),
(3, 41),
(8, 41);

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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(1, 'GRP-0001', 'Akabbo Social Fund', 'It is not all about what you earn it&amp;amp;amp;amp;amp;apos;s what you save that matters', 2, 1, 3, 'group_6a2001f720ffd.jpeg', '', 'active', 1, '2026-06-03 08:52:38', '2026-06-03 10:29:11');

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
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `key`, `value`, `type`, `group`, `description`, `updated_at`) VALUES
(1, 'org_name', 'Akabbo Social Fund', 'string', 'general', 'Organization name', '2026-06-04 10:41:59'),
(2, 'org_tagline', 'Growing Together, Prospering Together', 'string', 'general', 'Organization tagline', '2026-06-04 10:41:59'),
(3, 'org_email', 'info@akabbofund.org', 'string', 'general', 'Contact email', '2026-06-04 10:41:59'),
(4, 'org_phone', '+256 700 000000', 'string', 'general', 'Contact phone', '2026-06-04 10:41:59'),
(5, 'org_address', 'Kampala, Uganda', 'string', 'general', 'Physical address', '2026-06-04 10:41:59'),
(6, 'currency', 'UGX', 'string', 'finance', 'Default currency code', '2026-06-04 10:41:59'),
(7, 'currency_symbol', 'USh', 'string', 'finance', 'Currency symbol', '2026-06-04 10:41:59'),
(8, 'min_savings', '10000', 'integer', 'finance', 'Minimum savings contribution', '2026-06-04 10:41:59'),
(9, 'loan_interest_rate', '10', 'integer', 'finance', 'Default loan interest rate (%)', '2026-06-04 10:41:59'),
(10, 'loan_interest_type', 'reducing_balance', 'string', 'finance', 'Interest calculation method', '2026-06-04 10:41:59'),
(11, 'max_loan_multiplier', '3', 'integer', 'finance', 'Max loan = multiplier x savings', '2026-06-04 10:41:59'),
(12, 'loan_processing_fee', '1', 'integer', 'finance', 'Loan processing fee (%)', '2026-06-04 10:41:59'),
(13, 'late_payment_penalty', '5', 'integer', 'finance', 'Late payment penalty rate (%)', '2026-06-04 10:41:59'),
(14, 'grace_period_days', '5', 'integer', 'finance', 'Grace period in days before penalty', '2026-06-04 10:41:59'),
(15, 'fiscal_year_start', '01-01', 'string', 'finance', 'Fiscal year start MM-DD', '2026-06-04 10:41:59'),
(16, 'session_timeout', '1800', 'integer', 'security', 'Session timeout in seconds', '2026-06-04 10:41:59'),
(17, 'max_login_attempts', '5', 'integer', 'security', 'Max login attempts before lockout', '2026-06-04 10:41:59'),
(18, 'lockout_duration', '900', 'integer', 'security', 'Account lockout duration in seconds', '2026-06-04 10:41:59'),
(19, 'sms_enabled', 'false', 'boolean', 'notifications', 'Enable SMS notifications', '2026-06-04 10:41:59'),
(20, 'email_enabled', 'true', 'boolean', 'notifications', 'Enable email notifications', '2026-06-04 10:41:59'),
(21, 'backup_frequency', 'daily', 'string', 'backup', 'Automated backup frequency', '2026-06-04 10:41:59'),
(22, 'app_version', '1.0.0', 'string', 'system', 'Application version', '2026-06-04 10:41:59'),
(23, 'require_withdrawal_approval', 'true', 'boolean', 'finance', 'Withdrawals require approval before processing', '2026-06-09 17:56:10'),
(24, 'require_transfer_approval', 'true', 'boolean', 'finance', 'Transfers between members require approval', '2026-06-09 17:56:10'),
(25, 'require_disbursement_approval', 'true', 'boolean', 'finance', 'Loan disbursements require secondary approval', '2026-06-09 17:56:10'),
(26, 'withdrawal_approval_threshold', '100000', 'integer', 'finance', 'Auto-approve withdrawals below this amount', '2026-06-09 17:56:10'),
(27, 'share_par_value', '1000', 'integer', 'finance', 'Par value per share unit (UGX)', '2026-06-09 17:56:10'),
(28, 'social_fund_fee', '5000', 'integer', 'finance', 'Monthly social fund fee amount', '2026-06-09 17:56:10'),
(29, 'social_fund_fee_due_day', '1', 'integer', 'finance', 'Day of month fee is due', '2026-06-09 17:56:10'),
(30, 'social_fund_fee_grace_days', '5', 'integer', 'finance', 'Grace days before late penalty', '2026-06-09 17:56:10'),
(31, 'social_fund_fee_penalty', '1000', 'integer', 'finance', 'Penalty for late social fund fee payment', '2026-06-09 17:56:10');

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
(1, 1000.00, 1, 1000, 2.00, 1, 5.00, 0, NULL, '2026-06-09 17:51:11'),
(2, 1000.00, 1, 1000, 2.00, 1, 5.00, 0, NULL, '2026-06-09 17:54:29'),
(3, 1000.00, 1, 1000, 2.00, 1, 5.00, 0, NULL, '2026-06-09 17:56:10');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `social_fund_fees`
--

INSERT INTO `social_fund_fees` (`id`, `name`, `amount`, `frequency`, `due_day`, `grace_days`, `penalty_amount`, `is_mandatory`, `applies_to`, `status`, `effective_from`, `effective_to`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Monthly Social Fund Fee', 5000.00, 'monthly', 1, 5, 1000.00, 1, 'all', 'active', '2026-06-09', NULL, 1, '2026-06-09 17:51:11', '2026-06-09 17:51:11'),
(2, 'Monthly Social Fund Fee', 5000.00, 'monthly', 1, 5, 1000.00, 1, 'all', 'active', '2026-06-09', NULL, 1, '2026-06-09 17:54:29', '2026-06-09 17:54:29'),
(3, 'Monthly Social Fund Fee', 5000.00, 'monthly', 1, 5, 1000.00, 1, 'all', 'active', '2026-06-09', NULL, 1, '2026-06-09 17:56:10', '2026-06-09 17:56:10');

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
  `txn_type` enum('deposit','withdrawal','loan_disbursement','loan_repayment','membership_fee','penalty','interest','transfer','reversal') NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `password_hash` varchar(255) NOT NULL,
  `role_id` tinyint UNSIGNED NOT NULL,
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
  KEY `role_id` (`role_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `employee_id`, `first_name`, `last_name`, `email`, `phone`, `password_hash`, `role_id`, `avatar`, `status`, `email_verified`, `must_change_password`, `failed_login_attempts`, `locked_until`, `last_login`, `last_login_ip`, `password_reset_token`, `password_reset_expires`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'EMP-0001', 'Cosmas', 'Musamba', 'cosmasmusamba@gmail.com', '+256700000000', '$2y$12$Z.e/nePsMni05MmmvJ.Km.DV2sK9d5OVUW2TQOc3YIuVvBF8mcNTS', 1, NULL, 'active', 1, 0, 0, NULL, '2026-06-09 20:42:11', '::1', NULL, NULL, NULL, '2026-05-31 17:59:14', '2026-06-09 17:42:11');

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
