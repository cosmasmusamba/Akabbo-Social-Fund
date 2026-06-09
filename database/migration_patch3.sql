-- ============================================================
-- AKABBO SOCIAL FUND — Migration Patch 3
-- Shares, Social Fund Fees, Approval Workflow,
-- Expenses, Member Sequence Control
-- ============================================================

USE `akabbo_fund`;

-- ── 1. Member number sequence control (prevents import conflicts) ──
CREATE TABLE IF NOT EXISTS `member_sequence` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `last_seq`     INT UNSIGNED NOT NULL DEFAULT 0,
  `prefix`       VARCHAR(10)  NOT NULL DEFAULT 'AKB',
  `updated_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed from current max member number
INSERT IGNORE INTO `member_sequence` (`last_seq`, `prefix`)
SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(member_no, '-', -1) AS UNSIGNED)), 0), 'AKB'
FROM members
WHERE member_no REGEXP '^AKB-[0-9]+$';

-- ── 2. Share configuration ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `share_config` (
  `id`                   TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `par_value`            DECIMAL(15,2)   NOT NULL DEFAULT 1000.00  COMMENT 'Price per share unit (UGX)',
  `min_shares`           INT UNSIGNED    NOT NULL DEFAULT 1,
  `max_shares_per_member`INT UNSIGNED    NOT NULL DEFAULT 1000,
  `loan_rate_discount`   DECIMAL(5,2)    NOT NULL DEFAULT 2.00  COMMENT '% discount off standard rate for shareholders',
  `loan_multiplier_bonus`TINYINT         NOT NULL DEFAULT 1     COMMENT 'Extra multiplier for shareholders',
  `dividend_rate`        DECIMAL(5,2)    NOT NULL DEFAULT 0.00  COMMENT 'Annual dividend rate %',
  `is_transferable`      TINYINT(1)      NOT NULL DEFAULT 0,
  `updated_by`           INT UNSIGNED,
  `updated_at`           TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO `share_config`
  (`par_value`,`min_shares`,`max_shares_per_member`,`loan_rate_discount`,`loan_multiplier_bonus`,`dividend_rate`)
VALUES (1000.00, 1, 1000, 2.00, 1, 5.00);

-- ── 3. Member shares ledger ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `member_shares` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `member_id`      INT UNSIGNED NOT NULL,
  `shares_held`    INT UNSIGNED NOT NULL DEFAULT 0,
  `total_invested` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `is_shareholder` TINYINT(1)   NOT NULL DEFAULT 1,
  `share_date`     DATE         NOT NULL,
  `status`         ENUM('active','suspended','exited') DEFAULT 'active',
  `created_by`     INT UNSIGNED NOT NULL,
  `created_at`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_member_shares` (`member_id`),
  FOREIGN KEY (`member_id`)  REFERENCES `members`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ── 4. Share transactions (purchases, transfers, exits) ───────────
CREATE TABLE IF NOT EXISTS `share_transactions` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `txn_ref`         VARCHAR(30)  NOT NULL UNIQUE,
  `member_id`       INT UNSIGNED NOT NULL,
  `txn_type`        ENUM('purchase','sale','transfer_in','transfer_out','dividend','refund') NOT NULL,
  `shares_qty`      INT UNSIGNED NOT NULL,
  `par_value`       DECIMAL(15,2) NOT NULL,
  `total_amount`    DECIMAL(18,2) NOT NULL,
  `to_member_id`    INT UNSIGNED NULL COMMENT 'For transfers',
  `notes`           TEXT,
  `payment_method`  ENUM('cash','mobile_money','bank_transfer','deduction') DEFAULT 'cash',
  `transaction_date` DATE        NOT NULL,
  `status`          ENUM('pending','approved','rejected','completed') DEFAULT 'pending',
  `approved_by`     INT UNSIGNED,
  `approved_at`     DATETIME,
  `approval_notes`  TEXT,
  `created_by`      INT UNSIGNED NOT NULL,
  `created_at`      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`member_id`)   REFERENCES `members`(`id`),
  FOREIGN KEY (`to_member_id`)REFERENCES `members`(`id`),
  FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`),
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ── 5. Social Fund Fee configuration ─────────────────────────────
CREATE TABLE IF NOT EXISTS `social_fund_fees` (
  `id`            TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`          VARCHAR(100)  NOT NULL DEFAULT 'Monthly Social Fund Fee',
  `amount`        DECIMAL(15,2) NOT NULL,
  `frequency`     ENUM('monthly','quarterly','annually','once') DEFAULT 'monthly',
  `due_day`       TINYINT       NOT NULL DEFAULT 1  COMMENT 'Day of month fee is due',
  `grace_days`    TINYINT       NOT NULL DEFAULT 5,
  `penalty_amount`DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Fixed penalty for late payment',
  `is_mandatory`  TINYINT(1)    NOT NULL DEFAULT 1,
  `applies_to`    ENUM('all','shareholders','non_shareholders') DEFAULT 'all',
  `status`        ENUM('active','inactive') DEFAULT 'active',
  `effective_from`DATE          NOT NULL,
  `effective_to`  DATE          NULL,
  `created_by`    INT UNSIGNED  NOT NULL,
  `created_at`    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- Seed a default fee
INSERT IGNORE INTO `social_fund_fees`
  (`name`,`amount`,`frequency`,`due_day`,`grace_days`,`penalty_amount`,`is_mandatory`,`applies_to`,`status`,`effective_from`,`created_by`)
VALUES ('Monthly Social Fund Fee', 5000.00, 'monthly', 1, 5, 1000.00, 1, 'all', 'active', CURDATE(), 1);

-- ── 6. Social Fund Fee payment ledger ────────────────────────────
CREATE TABLE IF NOT EXISTS `social_fund_fee_payments` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `fee_id`        TINYINT UNSIGNED NOT NULL,
  `member_id`     INT UNSIGNED     NOT NULL,
  `period_month`  TINYINT UNSIGNED NOT NULL COMMENT '1-12',
  `period_year`   SMALLINT UNSIGNED NOT NULL,
  `amount_due`    DECIMAL(15,2)    NOT NULL,
  `amount_paid`   DECIMAL(15,2)    NOT NULL DEFAULT 0.00,
  `penalty_paid`  DECIMAL(15,2)    NOT NULL DEFAULT 0.00,
  `paid_date`     DATE,
  `payment_method`ENUM('cash','mobile_money','bank_transfer','deduction') DEFAULT 'cash',
  `status`        ENUM('pending','paid','partial','overdue','waived') DEFAULT 'pending',
  `waived_by`     INT UNSIGNED,
  `waived_reason` TEXT,
  `created_by`    INT UNSIGNED NOT NULL,
  `created_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_fee_member_period` (`fee_id`, `member_id`, `period_month`, `period_year`),
  FOREIGN KEY (`fee_id`)     REFERENCES `social_fund_fees`(`id`),
  FOREIGN KEY (`member_id`)  REFERENCES `members`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
  FOREIGN KEY (`waived_by`)  REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ── 7. Approval Workflow ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `approvals` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `reference_type`  ENUM('transaction','loan','share_transaction','withdrawal','transfer','disbursement','expense') NOT NULL,
  `reference_id`    INT UNSIGNED NOT NULL,
  `reference_ref`   VARCHAR(50)  NULL  COMMENT 'Human-readable ref like TXN-XXX',
  `amount`          DECIMAL(18,2),
  `requested_by`    INT UNSIGNED NOT NULL,
  `requested_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `assigned_to`     INT UNSIGNED NULL  COMMENT 'Specific approver if directed',
  `status`          ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `reviewed_by`     INT UNSIGNED,
  `reviewed_at`     DATETIME,
  `approval_notes`  TEXT         NOT NULL COMMENT 'Mandatory reason/notes',
  `rejection_notes` TEXT,
  `escalated`       TINYINT(1)   DEFAULT 0,
  `escalated_at`    DATETIME,
  `due_by`          DATETIME     NULL  COMMENT 'SLA deadline',
  `created_at`      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`requested_by`) REFERENCES `users`(`id`),
  FOREIGN KEY (`reviewed_by`)  REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ── 8. Expense Categories ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `expense_categories` (
  `id`          TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(100) NOT NULL UNIQUE,
  `description` VARCHAR(255),
  `is_active`   TINYINT(1)   DEFAULT 1,
  `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO `expense_categories` (`name`, `description`) VALUES
('Office Supplies',      'Stationery, printing, office materials'),
('Utilities',           'Electricity, water, internet'),
('Staff Costs',         'Salaries, allowances, welfare'),
('Travel & Transport',  'Transport, fuel, travel allowances'),
('Meetings & Events',   'Meeting costs, AGM, workshops'),
('Software & Systems',  'Software licenses, IT equipment'),
('Loan Write-offs',     'Bad debt write-offs'),
('Auditing & Legal',    'Audit fees, legal counsel'),
('Marketing',           'Promotion, member recruitment'),
('Miscellaneous',       'Other operational costs');

-- ── 9. Expenses ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `expenses` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `expense_ref`     VARCHAR(30)      NOT NULL UNIQUE,
  `category_id`     TINYINT UNSIGNED NOT NULL,
  `title`           VARCHAR(200)     NOT NULL,
  `description`     TEXT,
  `amount`          DECIMAL(18,2)    NOT NULL,
  `currency`        VARCHAR(5)       DEFAULT 'UGX',
  `payment_method`  ENUM('cash','mobile_money','bank_transfer','cheque') DEFAULT 'cash',
  `payee_name`      VARCHAR(150),
  `payee_contact`   VARCHAR(100),
  `receipt_no`      VARCHAR(100),
  `expense_date`    DATE             NOT NULL,
  `period_month`    TINYINT UNSIGNED,
  `period_year`     SMALLINT UNSIGNED,
  `status`          ENUM('draft','pending','approved','rejected','paid') DEFAULT 'draft',
  `approved_by`     INT UNSIGNED,
  `approved_at`     DATETIME,
  `approval_notes`  TEXT,
  `paid_by`         INT UNSIGNED,
  `paid_at`         DATETIME,
  `attachment_path` VARCHAR(255)     COMMENT 'Receipt/invoice scan',
  `created_by`      INT UNSIGNED     NOT NULL,
  `created_at`      TIMESTAMP        DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`      DATETIME,
  FOREIGN KEY (`category_id`) REFERENCES `expense_categories`(`id`),
  FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`),
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`),
  FOREIGN KEY (`paid_by`)     REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ── 10. Fund Transfers (member-to-member) ─────────────────────────
CREATE TABLE IF NOT EXISTS `fund_transfers` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `transfer_ref`    VARCHAR(30)   NOT NULL UNIQUE,
  `from_member_id`  INT UNSIGNED  NOT NULL,
  `to_member_id`    INT UNSIGNED  NOT NULL,
  `amount`          DECIMAL(18,2) NOT NULL,
  `description`     TEXT,
  `transfer_date`   DATE          NOT NULL,
  `status`          ENUM('pending','approved','rejected','completed','reversed') DEFAULT 'pending',
  `approved_by`     INT UNSIGNED,
  `approved_at`     DATETIME,
  `approval_notes`  TEXT,
  `rejection_notes` TEXT,
  `reversed_by`     INT UNSIGNED,
  `reversed_at`     DATETIME,
  `reversal_notes`  TEXT,
  `created_by`      INT UNSIGNED NOT NULL,
  `created_at`      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`from_member_id`) REFERENCES `members`(`id`),
  FOREIGN KEY (`to_member_id`)   REFERENCES `members`(`id`),
  FOREIGN KEY (`created_by`)     REFERENCES `users`(`id`),
  FOREIGN KEY (`approved_by`)    REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ── 11. Add share-awareness to loan products (MySQL 5.7 compatible) ──
SET @dbname = DATABASE();
SET @tablename = 'loan_products';
SET @column1 = 'shareholder_rate_discount';
SET @column2 = 'shareholder_multiplier_bonus';

SELECT COUNT(*) INTO @exists1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @column1;
SELECT COUNT(*) INTO @exists2 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @column2;

SET @sql1 = IF(@exists1 = 0, 
    CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @column1, '` DECIMAL(5,2) DEFAULT 0.00 COMMENT ''Interest rate discount for shareholders'' AFTER `max_loan_multiplier`'),
    'SELECT 1');
PREPARE stmt1 FROM @sql1;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

SET @sql2 = IF(@exists2 = 0,
    CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @column2, '` TINYINT DEFAULT 0 COMMENT ''Extra loan multiplier for shareholders'' AFTER `', @column1, '`'),
    'SELECT 1');
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- ── 12. Add shareholder flag to members ──
SET @tablename = 'members';
SET @col1 = 'is_shareholder';
SET @col2 = 'shares_held';

SELECT COUNT(*) INTO @exists1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @col1;
SELECT COUNT(*) INTO @exists2 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @col2;

SET @sql1 = IF(@exists1 = 0, CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @col1, '` TINYINT(1) DEFAULT 0 AFTER `kyc_verified`'), 'SELECT 1');
PREPARE stmt1 FROM @sql1;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

SET @sql2 = IF(@exists2 = 0, CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @col2, '` INT UNSIGNED DEFAULT 0 AFTER `', @col1, '`'), 'SELECT 1');
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- ── 13. Approval audit on transactions ──
SET @tablename = 'transactions';
SET @col = 'approval_notes';
SELECT COUNT(*) INTO @exists FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @col;
SET @sql = IF(@exists = 0, CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @col, '` TEXT AFTER `rejection_reason`'), 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ── 14. Add approval notes to loans ──
SET @tablename = 'loans';
SET @col = 'approval_notes';
SELECT COUNT(*) INTO @exists FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @col;
SET @sql = IF(@exists = 0, CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @col, '` TEXT AFTER `approval_date`'), 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ── 15. Settings for approval workflow ───────────────────────────
INSERT IGNORE INTO `settings` (`key`, `value`, `type`, `group`, `description`) VALUES
('require_withdrawal_approval','true','boolean','finance','Withdrawals require approval before processing'),
('require_transfer_approval','true','boolean','finance','Transfers between members require approval'),
('require_disbursement_approval','true','boolean','finance','Loan disbursements require secondary approval'),
('withdrawal_approval_threshold','100000','integer','finance','Auto-approve withdrawals below this amount'),
('share_par_value','1000','integer','finance','Par value per share unit (UGX)'),
('social_fund_fee','5000','integer','finance','Monthly social fund fee amount'),
('social_fund_fee_due_day','1','integer','finance','Day of month fee is due'),
('social_fund_fee_grace_days','5','integer','finance','Grace days before late penalty'),
('social_fund_fee_penalty','1000','integer','finance','Penalty for late social fund fee payment');

-- ── 16. Permissions for new modules ──────────────────────────────
INSERT IGNORE INTO `permissions` (`module`, `action`, `slug`, `description`) VALUES
('shares','view','shares.view','View share holdings'),
('shares','manage','shares.manage','Issue and manage shares'),
('shares','approve','shares.approve','Approve share transactions'),
('expenses','view','expenses.view','View expenses'),
('expenses','create','expenses.create','Create expense records'),
('expenses','approve','expenses.approve','Approve expenses'),
('expenses','delete','expenses.delete','Delete expense records'),
('approvals','view','approvals.view','View pending approvals'),
('approvals','process','approvals.process','Approve or reject items'),
('social_fund','view','social_fund.view','View social fund fees'),
('social_fund','manage','social_fund.manage','Manage social fund fees'),
('transfers','view','transfers.view','View fund transfers'),
('transfers','create','transfers.create','Initiate fund transfers');

-- Grant all new permissions to super_admin (role_id=1)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`
WHERE slug IN ('shares.view','shares.manage','shares.approve',
               'expenses.view','expenses.create','expenses.approve','expenses.delete',
               'approvals.view','approvals.process',
               'social_fund.view','social_fund.manage',
               'transfers.view','transfers.create');

-- Grant finance permissions to finance_manager (role_id=3)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, id FROM `permissions`
WHERE slug IN ('shares.view','shares.manage',
               'expenses.view','expenses.create','expenses.approve',
               'approvals.view','approvals.process',
               'social_fund.view','social_fund.manage',
               'transfers.view','transfers.create');

-- Grant view permissions to treasurer (role_id=8)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 8, id FROM `permissions`
WHERE slug IN ('shares.view','expenses.view','approvals.view',
               'social_fund.view','transfers.view','transfers.create');
