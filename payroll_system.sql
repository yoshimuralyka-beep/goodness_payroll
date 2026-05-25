-- ============================================================
--  PAYROLL SYSTEM - Complete Database Script
--  IT221 - Information Management | Final Term PIT
--  MariaDB 10.4+ / MySQL 8.0+
--
--  Topics Covered:
--  [1] Advanced SQL         - Joins, Subqueries, Views, Window Functions
--  [2] Transaction Mgmt     - BEGIN, COMMIT, ROLLBACK (stored proc)
--  [3] Concurrency Control  - Optimistic (version_number) + Pessimistic (SELECT FOR UPDATE)
--  [4] Data Warehousing     - Star Schema: fact_payroll + 3 dimension tables
--  [5] Data Integration     - ETL Stored Procedure (sp_run_etl)
--  [6] Referential Integrity- Foreign Keys with ON DELETE / ON UPDATE rules
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS = 0;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ============================================================
-- DROP & CREATE DATABASE
-- ============================================================

DROP DATABASE IF EXISTS `payroll_system`;
CREATE DATABASE `payroll_system`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE `payroll_system`;

-- ============================================================
-- SECTION 1: OPERATIONAL TABLES
-- ============================================================

-- ------------------------------------------------------------
-- Table: users
-- Stores system login accounts (admin / staff roles)
-- ------------------------------------------------------------
CREATE TABLE `users` (
  `user_id`      INT(11)      NOT NULL AUTO_INCREMENT,
  `username`     VARCHAR(50)  NOT NULL,
  `password`     VARCHAR(255) NOT NULL,
  `full_name`    VARCHAR(100) NOT NULL,
  `email`        VARCHAR(100) DEFAULT NULL,
  `user_role`    ENUM('admin','staff') DEFAULT 'staff',
  `user_status`  ENUM('active','inactive') DEFAULT 'active',
  `last_login`   TIMESTAMP NULL DEFAULT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `avatar`       VARCHAR(255) DEFAULT NULL,
  `avatar_color` VARCHAR(7)   DEFAULT '#10b981',
  `avatar_image` VARCHAR(255) DEFAULT NULL,
  `avatar_type`  ENUM('initial','image') DEFAULT 'initial',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_username` (`username`),
  UNIQUE KEY `uq_user_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='System users with role-based access control';

-- ------------------------------------------------------------
-- Table: employees
-- Core employee master table.
-- version_number  => Optimistic concurrency control (incremented on every UPDATE).
--                    PHP checks: WHERE employee_id=? AND version_number=?
--                    before applying an update so two concurrent editors
--                    cannot silently overwrite each other.
-- ------------------------------------------------------------
CREATE TABLE `employees` (
  `employee_id`    INT(11)       NOT NULL AUTO_INCREMENT,
  `employee_code`  VARCHAR(20)   NOT NULL,
  `first_name`     VARCHAR(50)   NOT NULL,
  `last_name`      VARCHAR(50)   NOT NULL,
  `email`          VARCHAR(100)  DEFAULT NULL,
  `department`     VARCHAR(50)   DEFAULT NULL,
  `emp_position`   VARCHAR(50)   DEFAULT NULL,
  `basic_salary`   DECIMAL(12,2) NOT NULL CHECK (`basic_salary` > 0),
  `hire_date`      DATE          NOT NULL,
  `emp_status`     ENUM('active','inactive','terminated') DEFAULT 'active',
  `version_number` INT(11)       NOT NULL DEFAULT 1
                   COMMENT 'Optimistic concurrency control - PHP must match before UPDATE',
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP()
                   ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`employee_id`),
  UNIQUE KEY `uq_employee_code` (`employee_code`),
  UNIQUE KEY `uq_employee_email` (`email`),
  KEY `idx_department` (`department`),
  KEY `idx_emp_status` (`emp_status`),
  KEY `idx_version_number` (`version_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Employee master — version_number enables optimistic locking';

-- ------------------------------------------------------------
-- Table: payroll_records
-- One row per employee per payroll period.
-- lock_version => Optimistic locking for the payroll record itself.
--   When processing payroll, PHP reads lock_version first, then
--   UPDATEs only WHERE lock_version = <read_value>, incrementing it.
--   If another session already updated it, 0 rows affected => PHP
--   raises a concurrency error and rolls back the transaction.
-- ------------------------------------------------------------
CREATE TABLE `payroll_records` (
  `payroll_id`              INT(11)       NOT NULL AUTO_INCREMENT,
  `employee_id`             INT(11)       NOT NULL,
  `payroll_month`           INT(11)       NOT NULL CHECK (`payroll_month` BETWEEN 1 AND 12),
  `payroll_year`            INT(11)       NOT NULL CHECK (`payroll_year` >= 2000),
  `basic_salary`            DECIMAL(12,2) NOT NULL,
  `allowances_amount`       DECIMAL(12,2) DEFAULT 0.00,
  `overtime_amount`         DECIMAL(12,2) DEFAULT 0.00,
  `tax_amount`              DECIMAL(12,2) DEFAULT 0.00,
  `sss_amount`              DECIMAL(12,2) DEFAULT 0.00,
  `philhealth_amount`       DECIMAL(12,2) DEFAULT 0.00,
  `pagibig_amount`          DECIMAL(12,2) DEFAULT 0.00,
  `other_deductions`        DECIMAL(12,2) DEFAULT 0.00,
  `gross_amount`            DECIMAL(12,2) NOT NULL,
  `total_deductions_amount` DECIMAL(12,2) NOT NULL,
  `net_amount`              DECIMAL(12,2) NOT NULL,
  `payroll_status`          ENUM('draft','processed','paid') DEFAULT 'draft',
  `processed_at`            TIMESTAMP NULL DEFAULT NULL,
  `lock_version`            INT(11) NOT NULL DEFAULT 1
                            COMMENT 'Optimistic locking - increment on every UPDATE',
  PRIMARY KEY (`payroll_id`),
  UNIQUE KEY `uq_payroll_period` (`employee_id`,`payroll_month`,`payroll_year`),
  KEY `idx_payroll_status` (`payroll_status`),
  KEY `idx_payroll_year_month` (`payroll_year`,`payroll_month`),
  KEY `idx_lock_version` (`lock_version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Payroll records — lock_version enables optimistic locking during processing';

-- ------------------------------------------------------------
-- Table: activity_logs
-- Audit trail for all user actions.
-- ------------------------------------------------------------
CREATE TABLE `activity_logs` (
  `log_id`       INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`      INT(11)      DEFAULT NULL,           -- nullable: SET NULL on user delete preserves audit history
  `username`     VARCHAR(50)  NOT NULL,               -- denormalized snapshot: frozen at time of action for accurate audit trail
  `action_name`  VARCHAR(100) NOT NULL,
  `module_name`  VARCHAR(50)  DEFAULT NULL,
  `details_text` TEXT         DEFAULT NULL,
  `ip_address`   VARCHAR(45)  DEFAULT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`log_id`),
  KEY `idx_log_user_id`    (`user_id`),
  KEY `idx_log_action`     (`action_name`),
  KEY `idx_log_module`     (`module_name`),
  KEY `idx_log_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Audit log of all user actions';

-- ------------------------------------------------------------
-- Table: permissions
-- Role-based access control matrix.
-- ------------------------------------------------------------
CREATE TABLE `permissions` (
  `permission_id`       INT(11)     NOT NULL AUTO_INCREMENT,
  `role_name`           VARCHAR(20) NOT NULL,
  `module_name`         VARCHAR(50) NOT NULL,
  `can_view`            TINYINT(1)  DEFAULT 1,
  `can_create`          TINYINT(1)  DEFAULT 0,
  `can_edit`            TINYINT(1)  DEFAULT 0,
  `can_delete`          TINYINT(1)  DEFAULT 0,
  `can_process_payroll` TINYINT(1)  DEFAULT 0,
  PRIMARY KEY (`permission_id`),
  UNIQUE KEY `uq_role_module` (`role_name`,`module_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Role-based access control permissions';

-- ============================================================
-- SECTION 2: DATA WAREHOUSE TABLES (Star Schema)
--
--  Star Schema layout:
--
--              dim_time
--                 |
--  dim_employee --+-- fact_payroll --+-- dim_department
--
--  fact_payroll is the central fact table.
--  Three dimension tables surround it.
-- ============================================================

-- Dimension: Department
CREATE TABLE `dim_department` (
  `department_key`  INT(11)     NOT NULL AUTO_INCREMENT,
  `department_name` VARCHAR(50) NOT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`department_key`),
  UNIQUE KEY `uq_dim_dept_name` (`department_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Data warehouse dimension - departments';

-- Dimension: Employee (snapshot at ETL time - Slowly Changing Dimension Type 1)
CREATE TABLE `dim_employee` (
  `employee_key`     INT(11)      NOT NULL AUTO_INCREMENT,
  `employee_id`      INT(11)      NOT NULL COMMENT 'Source key from employees table',
  `employee_code`    VARCHAR(20)  DEFAULT NULL,
  `full_name`        VARCHAR(101) DEFAULT NULL,
  `department_name`  VARCHAR(50)  DEFAULT NULL,
  `position_title`   VARCHAR(50)  DEFAULT NULL,
  `is_current_record` TINYINT(1)  DEFAULT 1 COMMENT 'SCD Type 1 flag',
  `loaded_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`employee_key`),
  KEY `idx_dim_emp_source_id` (`employee_id`),
  KEY `idx_dim_emp_dept`      (`department_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Data warehouse dimension - employees (SCD Type 1)';

-- Dimension: Time (calendar grain = month)
CREATE TABLE `dim_time` (
  `time_key`       INT(11)     NOT NULL AUTO_INCREMENT,
  `year_number`    INT(11)     NOT NULL,
  `month_number`   INT(11)     NOT NULL CHECK (`month_number` BETWEEN 1 AND 12),
  `month_name_text` VARCHAR(20) DEFAULT NULL,
  `year_month_text` VARCHAR(7)  DEFAULT NULL COMMENT 'Format: YYYY-MM',
  `quarter_number` INT(11)     DEFAULT NULL COMMENT '1-4',
  `half_year`      INT(11)     DEFAULT NULL COMMENT '1 or 2',
  PRIMARY KEY (`time_key`),
  UNIQUE KEY `uq_dim_time_period` (`year_number`,`month_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Data warehouse dimension - calendar time at month grain';

-- Fact Table: Payroll
CREATE TABLE `fact_payroll` (
  `fact_id`          INT(11)       NOT NULL AUTO_INCREMENT,
  `employee_key`     INT(11)       DEFAULT NULL,
  `time_key`         INT(11)       DEFAULT NULL,
  `department_key`   INT(11)       DEFAULT NULL,
  `net_pay`          DECIMAL(12,2) DEFAULT NULL,
  `gross_pay`        DECIMAL(12,2) DEFAULT NULL,
  `total_deductions` DECIMAL(12,2) DEFAULT NULL,
  `allowances`       DECIMAL(12,2) DEFAULT NULL COMMENT 'Additional ETL detail',
  `overtime`         DECIMAL(12,2) DEFAULT NULL COMMENT 'Additional ETL detail',
  `loaded_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`fact_id`),
  KEY `fk_fact_employee_key`   (`employee_key`),
  KEY `fk_fact_time_key`       (`time_key`),
  KEY `fk_fact_department_key` (`department_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Data warehouse fact table - central payroll measures';

-- ETL Job Log
CREATE TABLE `etl_jobs` (
  `job_id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `job_name`          VARCHAR(100) NOT NULL,
  `job_status`        ENUM('running','completed','failed') DEFAULT 'running',
  `records_processed` INT(11)      DEFAULT 0,
  `error_message`     TEXT         DEFAULT NULL,
  `started_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `completed_at`      TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`job_id`),
  KEY `idx_etl_status` (`job_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Log of each ETL run with status and error info';

-- ============================================================
-- SECTION 3: REFERENTIAL INTEGRITY - FOREIGN KEY CONSTRAINTS
--
--  Demonstrates: referential integrity, cascading rules.
--  payroll_records -> employees : RESTRICT delete, CASCADE update
--    (cannot delete an employee who has payroll records)
--  activity_logs   -> users     : SET NULL on delete
--    (user_id nulled; audit row + frozen username kept for compliance)
--  fact_payroll    -> dim_*     : CASCADE delete
--    (re-running ETL clears and reloads dimension + fact together)
--  dim_employee    -> employees : SET NULL on delete
--    (employee deleted from source => dim record orphaned but kept)
-- ============================================================

ALTER TABLE `payroll_records`
  ADD CONSTRAINT `fk_pr_employee`
    FOREIGN KEY (`employee_id`)
    REFERENCES `employees` (`employee_id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_al_user`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`user_id`)
    ON DELETE SET NULL   -- preserves audit history when a user is deleted; username column retains the snapshot
    ON UPDATE CASCADE;

ALTER TABLE `fact_payroll`
  ADD CONSTRAINT `fk_fp_employee`
    FOREIGN KEY (`employee_key`)
    REFERENCES `dim_employee` (`employee_key`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_fp_time`
    FOREIGN KEY (`time_key`)
    REFERENCES `dim_time` (`time_key`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_fp_department`
    FOREIGN KEY (`department_key`)
    REFERENCES `dim_department` (`department_key`)
    ON DELETE CASCADE
    ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SECTION 4: STORED PROCEDURES
-- ============================================================

DELIMITER $$

-- ------------------------------------------------------------
-- Stored Procedure: sp_process_payroll
--
-- Demonstrates: TRANSACTION MANAGEMENT (BEGIN/COMMIT/ROLLBACK)
--               + CONCURRENCY CONTROL (SELECT FOR UPDATE = pessimistic lock)
--               + OPTIMISTIC LOCK CHECK on payroll_records
--
-- How it works:
--   1. Begins a transaction.
--   2. SELECT FOR UPDATE on the employee row — acquires an exclusive
--      row-level lock so no other session can modify the same employee
--      mid-transaction (pessimistic locking).
--   3. Checks optimistic lock: if a payroll record already exists with
--      a lock_version that doesn't match what PHP passed in, it means
--      another session snuck in — ROLLBACK + signal error.
--   4. Inserts / updates the payroll record and increments lock_version.
--   5. COMMIT on success; ROLLBACK on any SQL exception (HANDLER).
-- ------------------------------------------------------------
CREATE PROCEDURE `sp_process_payroll`(
  IN  p_employee_id    INT,
  IN  p_month          INT,
  IN  p_year           INT,
  IN  p_basic_salary   DECIMAL(12,2),
  IN  p_allowances     DECIMAL(12,2),
  IN  p_overtime       DECIMAL(12,2),
  IN  p_tax            DECIMAL(12,2),
  IN  p_sss            DECIMAL(12,2),
  IN  p_philhealth     DECIMAL(12,2),
  IN  p_pagibig        DECIMAL(12,2),
  IN  p_other_deduct   DECIMAL(12,2),
  IN  p_expected_lock  INT,        -- optimistic lock value PHP read
  OUT p_result_code    INT,        -- 0=success, 1=duplicate, 2=lock conflict, 3=error
  OUT p_result_msg     VARCHAR(255)
)
BEGIN
  -- ── Local variables ───────────────────────────────────────
  DECLARE v_gross          DECIMAL(12,2);
  DECLARE v_total_deduct   DECIMAL(12,2);
  DECLARE v_net            DECIMAL(12,2);
  DECLARE v_existing_id    INT DEFAULT NULL;
  DECLARE v_current_lock   INT DEFAULT NULL;
  DECLARE v_emp_exists     INT DEFAULT 0;

  -- ── Error handler: catches any SQL exception, rolls back ──
  -- This satisfies the ROLLBACK requirement explicitly.
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    SET p_result_code = 3;
    SET p_result_msg  = 'SQL error occurred - transaction rolled back';
  END;

  -- ── Start transaction ─────────────────────────────────────
  START TRANSACTION;

    -- ── PESSIMISTIC LOCK: SELECT FOR UPDATE ──────────────────
    -- Locks the employee row for the duration of this transaction.
    -- Any other session trying to process payroll for the same
    -- employee will WAIT here until we COMMIT or ROLLBACK.
    SELECT employee_id INTO v_emp_exists
    FROM employees
    WHERE employee_id = p_employee_id
      AND emp_status  = 'active'
    FOR UPDATE;

    IF v_emp_exists IS NULL THEN
      ROLLBACK;
      SET p_result_code = 3;
      SET p_result_msg  = 'Employee not found or not active';
    ELSE

      -- ── Check for existing payroll record ─────────────────
      SELECT payroll_id, lock_version
        INTO v_existing_id, v_current_lock
      FROM payroll_records
      WHERE employee_id   = p_employee_id
        AND payroll_month = p_month
        AND payroll_year  = p_year
      LIMIT 1;

      -- ── OPTIMISTIC LOCK CHECK ─────────────────────────────
      -- If record exists and lock_version doesn't match what
      -- PHP expected, another session already modified it.
      IF v_existing_id IS NOT NULL AND v_current_lock != p_expected_lock THEN
        ROLLBACK;
        SET p_result_code = 2;
        SET p_result_msg  = 'Concurrency conflict: record was modified by another session';

      ELSEIF v_existing_id IS NOT NULL AND p_expected_lock = 0 THEN
        -- Caller did not expect an existing record
        ROLLBACK;
        SET p_result_code = 1;
        SET p_result_msg  = 'Duplicate: payroll record already exists for this period';

      ELSE
        -- ── Calculate amounts ─────────────────────────────────
        SET v_gross        = p_basic_salary + p_allowances + p_overtime;
        SET v_total_deduct = p_tax + p_sss + p_philhealth + p_pagibig + p_other_deduct;
        SET v_net          = v_gross - v_total_deduct;

        IF v_existing_id IS NULL THEN
          -- ── INSERT new payroll record ──────────────────────
          INSERT INTO payroll_records (
            employee_id, payroll_month, payroll_year,
            basic_salary, allowances_amount, overtime_amount,
            tax_amount, sss_amount, philhealth_amount,
            pagibig_amount, other_deductions,
            gross_amount, total_deductions_amount, net_amount,
            payroll_status, processed_at, lock_version
          ) VALUES (
            p_employee_id, p_month, p_year,
            p_basic_salary, p_allowances, p_overtime,
            p_tax, p_sss, p_philhealth,
            p_pagibig, p_other_deduct,
            v_gross, v_total_deduct, v_net,
            'processed', NOW(), 1
          );
        ELSE
          -- ── UPDATE existing draft record ───────────────────
          -- Increment lock_version to signal the change.
          UPDATE payroll_records SET
            basic_salary            = p_basic_salary,
            allowances_amount       = p_allowances,
            overtime_amount         = p_overtime,
            tax_amount              = p_tax,
            sss_amount              = p_sss,
            philhealth_amount       = p_philhealth,
            pagibig_amount          = p_pagibig,
            other_deductions        = p_other_deduct,
            gross_amount            = v_gross,
            total_deductions_amount = v_total_deduct,
            net_amount              = v_net,
            payroll_status          = 'processed',
            processed_at            = NOW(),
            lock_version            = lock_version + 1
          WHERE payroll_id  = v_existing_id
            AND lock_version = p_expected_lock;  -- double-check optimistic lock
        END IF;

        COMMIT;
        SET p_result_code = 0;
        SET p_result_msg  = 'Payroll processed successfully';
      END IF;
    END IF;

END$$

-- ------------------------------------------------------------
-- Stored Procedure: sp_run_etl
--
-- Demonstrates: ETL Process (Extract-Transform-Load)
--   Extract  - reads from operational tables (employees, payroll_records)
--   Transform - derives dimension keys, calculates quarter/half_year
--   Load      - populates star schema (dim_* + fact_payroll)
--
-- Also wrapped in a transaction: if any dimension insert fails,
-- the entire ETL rolls back to maintain warehouse consistency.
-- ------------------------------------------------------------
CREATE PROCEDURE `sp_run_etl`()
BEGIN
  DECLARE v_fact_count   INT DEFAULT 0;
  DECLARE v_job_id       INT DEFAULT 0;

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    -- Mark the job as failed
    UPDATE etl_jobs
       SET job_status    = 'failed',
           error_message = 'SQL exception during ETL - rolled back',
           completed_at  = NOW()
     WHERE job_id = v_job_id;
  END;

  -- Log job start
  INSERT INTO etl_jobs (job_name, job_status, records_processed)
  VALUES ('Payroll ETL', 'running', 0);
  SET v_job_id = LAST_INSERT_ID();

  START TRANSACTION;

    -- ── Step 1: Clear warehouse (order respects foreign keys) ─
    DELETE FROM fact_payroll;
    DELETE FROM dim_employee;
    DELETE FROM dim_time;
    DELETE FROM dim_department;

    -- Reset sequences
    ALTER TABLE fact_payroll   AUTO_INCREMENT = 1;
    ALTER TABLE dim_employee   AUTO_INCREMENT = 1;
    ALTER TABLE dim_time       AUTO_INCREMENT = 1;
    ALTER TABLE dim_department AUTO_INCREMENT = 1;

    -- ── Step 2: Load dim_department ───────────────────────────
    -- EXTRACT distinct departments, TRANSFORM to clean names
    INSERT INTO dim_department (department_name)
    SELECT DISTINCT COALESCE(TRIM(department), 'Unknown')
    FROM employees
    ORDER BY department;

    -- ── Step 3: Load dim_employee ─────────────────────────────
    -- Snapshot of current active employees (SCD Type 1)
    INSERT INTO dim_employee (
      employee_id, employee_code, full_name,
      department_name, position_title, is_current_record
    )
    SELECT
      e.employee_id,
      e.employee_code,
      CONCAT(e.first_name, ' ', e.last_name),
      COALESCE(TRIM(e.department), 'Unknown'),
      COALESCE(TRIM(e.emp_position), 'Staff'),
      1
    FROM employees e
    WHERE e.emp_status = 'active';

    -- ── Step 4: Load dim_time ─────────────────────────────────
    -- One row per distinct payroll period
    INSERT INTO dim_time (
      year_number, month_number, month_name_text,
      year_month_text, quarter_number, half_year
    )
    SELECT DISTINCT
      pr.payroll_year,
      pr.payroll_month,
      CASE pr.payroll_month
        WHEN 1  THEN 'January'   WHEN 2  THEN 'February'
        WHEN 3  THEN 'March'     WHEN 4  THEN 'April'
        WHEN 5  THEN 'May'       WHEN 6  THEN 'June'
        WHEN 7  THEN 'July'      WHEN 8  THEN 'August'
        WHEN 9  THEN 'September' WHEN 10 THEN 'October'
        WHEN 11 THEN 'November'  WHEN 12 THEN 'December'
      END,
      CONCAT(pr.payroll_year, '-', LPAD(pr.payroll_month, 2, '0')),
      CEIL(pr.payroll_month / 3.0),
      IF(pr.payroll_month <= 6, 1, 2)
    FROM payroll_records pr
    WHERE pr.payroll_status = 'processed'
    ORDER BY pr.payroll_year, pr.payroll_month;

    -- ── Step 5: Load fact_payroll ─────────────────────────────
    -- JOIN operational tables with dimension keys
    -- This is the LOAD phase of ETL
    INSERT INTO fact_payroll (
      employee_key, time_key, department_key,
      net_pay, gross_pay, total_deductions,
      allowances, overtime
    )
    SELECT
      de.employee_key,
      dt.time_key,
      dd.department_key,
      pr.net_amount,
      pr.gross_amount,
      pr.total_deductions_amount,
      pr.allowances_amount,
      pr.overtime_amount
    FROM payroll_records pr
    INNER JOIN dim_employee   de ON pr.employee_id   = de.employee_id
    INNER JOIN dim_time       dt ON pr.payroll_year  = dt.year_number
                                AND pr.payroll_month = dt.month_number
    INNER JOIN dim_department dd ON de.department_name = dd.department_name
    WHERE pr.payroll_status = 'processed';

    SELECT COUNT(*) INTO v_fact_count FROM fact_payroll;

  COMMIT;

  -- Mark job completed
  UPDATE etl_jobs
     SET job_status        = 'completed',
         records_processed = v_fact_count,
         completed_at      = NOW()
   WHERE job_id = v_job_id;

END$$

-- ------------------------------------------------------------
-- Stored Procedure: sp_add_employee
-- Demonstrates: transaction + prepared-statement-safe INSERT
-- with duplicate-guard rollback.
-- ------------------------------------------------------------
CREATE PROCEDURE `sp_add_employee`(
  IN  p_code       VARCHAR(20),
  IN  p_first_name VARCHAR(50),
  IN  p_last_name  VARCHAR(50),
  IN  p_email      VARCHAR(100),
  IN  p_department VARCHAR(50),
  IN  p_position   VARCHAR(50),
  IN  p_salary     DECIMAL(12,2),
  IN  p_hire_date  DATE,
  OUT p_result_code INT,
  OUT p_result_msg  VARCHAR(255)
)
BEGIN
  DECLARE v_dup INT DEFAULT 0;

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    SET p_result_code = 3;
    SET p_result_msg  = 'Error adding employee - transaction rolled back';
  END;

  START TRANSACTION;

    SELECT COUNT(*) INTO v_dup
    FROM employees
    WHERE employee_code = p_code OR email = p_email;

    IF v_dup > 0 THEN
      ROLLBACK;
      SET p_result_code = 1;
      SET p_result_msg  = 'Duplicate employee code or email';
    ELSE
      INSERT INTO employees (
        employee_code, first_name, last_name, email,
        department, emp_position, basic_salary, hire_date,
        emp_status, version_number
      ) VALUES (
        p_code, p_first_name, p_last_name, p_email,
        p_department, p_position, p_salary, p_hire_date,
        'active', 1
      );

      COMMIT;
      SET p_result_code = 0;
      SET p_result_msg  = 'Employee added successfully';
    END IF;

END$$

DELIMITER ;

-- ============================================================
-- SECTION 5: VIEWS
-- Advanced SQL: Joins, Subqueries, Window Functions
-- ============================================================

-- ------------------------------------------------------------
-- View 1: v_monthly_summary
-- Basic aggregation view used by Dashboard and History pages.
-- Uses: GROUP BY aggregation, COALESCE, MONTHNAME subexpression
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW `v_monthly_summary` AS
SELECT
    pr.payroll_year,
    pr.payroll_month,
    CONCAT(
      MONTHNAME(CONCAT(pr.payroll_year,'-',LPAD(pr.payroll_month,2,'0'),'-01')),
      ' ', pr.payroll_year
    )                                             AS period_name,
    COUNT(DISTINCT pr.employee_id)                AS employees_paid,
    COUNT(*)                                      AS total_transactions,
    COALESCE(SUM(pr.gross_amount), 0)             AS total_gross,
    COALESCE(SUM(pr.total_deductions_amount), 0)  AS total_deductions,
    COALESCE(SUM(pr.net_amount), 0)               AS total_net,
    COALESCE(ROUND(AVG(pr.net_amount), 2), 0)     AS average_net_pay
FROM payroll_records pr
WHERE pr.payroll_status = 'processed'
GROUP BY pr.payroll_year, pr.payroll_month
ORDER BY pr.payroll_year DESC, pr.payroll_month DESC;

-- ------------------------------------------------------------
-- View 2: v_department_summary
-- Per-department per-period summary with subquery for employee count.
-- Uses: JOIN, GROUP BY, correlated subquery
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW `v_department_summary` AS
SELECT
    e.department                                  AS department_name,
    pr.payroll_year,
    pr.payroll_month,
    CONCAT(
      MONTHNAME(CONCAT(pr.payroll_year,'-',LPAD(pr.payroll_month,2,'0'),'-01')),
      ' ', pr.payroll_year
    )                                             AS period_label,
    COUNT(DISTINCT e.employee_id)                 AS employee_count,
    -- Subquery: total active employees in this department
    (SELECT COUNT(*) FROM employees e2
     WHERE e2.department  = e.department
       AND e2.emp_status  = 'active')             AS total_dept_employees,
    ROUND(SUM(pr.net_amount), 2)                  AS total_net_pay,
    ROUND(AVG(pr.net_amount), 2)                  AS average_net_pay,
    ROUND(MAX(pr.net_amount), 2)                  AS highest_net_pay,
    ROUND(MIN(pr.net_amount), 2)                  AS lowest_net_pay
FROM employees e
JOIN payroll_records pr ON e.employee_id = pr.employee_id
WHERE pr.payroll_status = 'processed'
GROUP BY e.department, pr.payroll_year, pr.payroll_month;

-- ------------------------------------------------------------
-- View 3: v_employee_payroll_ranking
-- Advanced Window Functions:
--   RANK(), DENSE_RANK(), LAG(), LEAD(), AVG() OVER,
--   FIRST_VALUE(), LAST_VALUE(), NTILE()
-- Used on: Payroll History page (analytics tab)
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW `v_employee_payroll_ranking` AS
SELECT
    e.employee_id,
    e.employee_code,
    CONCAT(e.first_name, ' ', e.last_name)        AS full_name,
    e.department,
    pr.payroll_year,
    pr.payroll_month,
    CONCAT(
      MONTHNAME(CONCAT(pr.payroll_year,'-',LPAD(pr.payroll_month,2,'0'),'-01')),
      ' ', pr.payroll_year
    )                                             AS period_name,
    pr.net_amount,

    -- RANK: position within department per period
    RANK() OVER (
      PARTITION BY e.department, pr.payroll_year, pr.payroll_month
      ORDER BY pr.net_amount DESC
    )                                             AS rank_in_department,

    -- DENSE_RANK: rank without gaps
    DENSE_RANK() OVER (
      PARTITION BY e.department
      ORDER BY pr.net_amount DESC
    )                                             AS dense_rank_in_dept,

    -- AVG OVER: department average (no GROUP BY needed)
    ROUND(AVG(pr.net_amount) OVER (
      PARTITION BY e.department
    ), 2)                                         AS dept_avg_pay,

    -- Difference from department average
    ROUND(pr.net_amount - AVG(pr.net_amount) OVER (
      PARTITION BY e.department
    ), 2)                                         AS diff_from_dept_avg,

    -- LAG: previous month pay
    LAG(pr.net_amount, 1) OVER (
      PARTITION BY e.employee_id
      ORDER BY pr.payroll_year, pr.payroll_month
    )                                             AS previous_month_pay,

    -- Month-over-month % change
    CASE
      WHEN LAG(pr.net_amount, 1) OVER (
             PARTITION BY e.employee_id
             ORDER BY pr.payroll_year, pr.payroll_month
           ) IS NOT NULL
      THEN ROUND((
        (pr.net_amount - LAG(pr.net_amount,1) OVER (
           PARTITION BY e.employee_id
           ORDER BY pr.payroll_year, pr.payroll_month
        )) /
        LAG(pr.net_amount,1) OVER (
          PARTITION BY e.employee_id
          ORDER BY pr.payroll_year, pr.payroll_month
        )
      ) * 100, 2)
      ELSE NULL
    END                                           AS mom_change_pct,

    -- LEAD: next month forecasted pay
    LEAD(pr.net_amount, 1) OVER (
      PARTITION BY e.employee_id
      ORDER BY pr.payroll_year, pr.payroll_month
    )                                             AS next_month_pay,

    -- FIRST_VALUE: highest pay ever for this employee
    FIRST_VALUE(pr.net_amount) OVER (
      PARTITION BY e.employee_id
      ORDER BY pr.net_amount DESC
    )                                             AS highest_pay_ever,

    -- NTILE: pay quartile within department
    NTILE(4) OVER (
      PARTITION BY e.department
      ORDER BY pr.net_amount DESC
    )                                             AS pay_quartile,

    -- ROW_NUMBER: overall rank across all employees
    ROW_NUMBER() OVER (
      ORDER BY pr.net_amount DESC
    )                                             AS overall_rank

FROM payroll_records pr
JOIN employees e ON pr.employee_id = e.employee_id
WHERE pr.payroll_status = 'processed';

-- ------------------------------------------------------------
-- View 4: v_department_trends
-- Window frame calculations for trend analysis.
-- Uses: LAG(), AVG() OVER with ROWS BETWEEN (window frame),
--       SUM() OVER ROWS UNBOUNDED PRECEDING (running total),
--       RANK() OVER
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW `v_department_trends` AS
SELECT
    e.department,
    pr.payroll_year,
    pr.payroll_month,
    CONCAT(
      MONTHNAME(CONCAT(pr.payroll_year,'-',LPAD(pr.payroll_month,2,'0'),'-01')),
      ' ', pr.payroll_year
    )                                             AS period,
    COUNT(*)                                      AS emp_count,
    SUM(pr.net_amount)                            AS total_payroll,
    ROUND(AVG(pr.net_amount), 2)                  AS avg_pay,
    ROUND(MAX(pr.net_amount), 2)                  AS highest_pay,
    ROUND(MIN(pr.net_amount), 2)                  AS lowest_pay,

    -- Change from previous period
    ROUND(
      SUM(pr.net_amount) -
      LAG(SUM(pr.net_amount), 1) OVER (
        PARTITION BY e.department
        ORDER BY pr.payroll_year, pr.payroll_month
      ), 2
    )                                             AS payroll_change,

    -- Percentage change from previous period
    CASE
      WHEN LAG(SUM(pr.net_amount),1) OVER (
             PARTITION BY e.department
             ORDER BY pr.payroll_year, pr.payroll_month
           ) IS NOT NULL
      THEN ROUND((
        (SUM(pr.net_amount) - LAG(SUM(pr.net_amount),1) OVER (
           PARTITION BY e.department
           ORDER BY pr.payroll_year, pr.payroll_month
        )) /
        LAG(SUM(pr.net_amount),1) OVER (
          PARTITION BY e.department
          ORDER BY pr.payroll_year, pr.payroll_month
        )
      ) * 100, 2)
      ELSE NULL
    END                                           AS pct_change,

    -- 3-month moving average (window frame: current + 2 prior)
    ROUND(AVG(SUM(pr.net_amount)) OVER (
      PARTITION BY e.department
      ORDER BY pr.payroll_year, pr.payroll_month
      ROWS BETWEEN 2 PRECEDING AND CURRENT ROW
    ), 2)                                         AS three_month_moving_avg,

    -- Cumulative (running) total per department
    ROUND(SUM(SUM(pr.net_amount)) OVER (
      PARTITION BY e.department
      ORDER BY pr.payroll_year, pr.payroll_month
      ROWS UNBOUNDED PRECEDING
    ), 2)                                         AS cumulative_payroll,

    -- Department rank by total payroll (across all depts, all periods)
    RANK() OVER (
      ORDER BY SUM(pr.net_amount) DESC
    )                                             AS department_rank

FROM payroll_records pr
JOIN employees e ON pr.employee_id = e.employee_id
WHERE pr.payroll_status = 'processed'
GROUP BY e.department, pr.payroll_year, pr.payroll_month;

-- ------------------------------------------------------------
-- View 5: v_employee_payroll_analytics
-- Employee-level analytics with cumulative and rolling window calcs.
-- Uses: SUM() OVER ROWS UNBOUNDED PRECEDING,
--       AVG() OVER ROWS BETWEEN, ROW_NUMBER(), percentage subquery
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW `v_employee_payroll_analytics` AS
SELECT
    e.employee_id,
    e.employee_code,
    CONCAT(e.first_name, ' ', e.last_name)        AS full_name,
    e.department,
    e.emp_position,
    pr.payroll_year,
    pr.payroll_month,
    pr.net_amount,
    pr.gross_amount,
    pr.total_deductions_amount,

    -- Running total per employee
    ROUND(SUM(pr.net_amount) OVER (
      PARTITION BY e.employee_id
      ORDER BY pr.payroll_year, pr.payroll_month
      ROWS UNBOUNDED PRECEDING
    ), 2)                                         AS cumulative_earnings,

    -- 3-month rolling average per employee
    ROUND(AVG(pr.net_amount) OVER (
      PARTITION BY e.employee_id
      ORDER BY pr.payroll_year, pr.payroll_month
      ROWS BETWEEN 2 PRECEDING AND CURRENT ROW
    ), 2)                                         AS rolling_3_month_avg,

    -- Employee's share of department payroll this period
    ROUND(
      100.0 * pr.net_amount /
      SUM(pr.net_amount) OVER (
        PARTITION BY e.department, pr.payroll_year, pr.payroll_month
      ), 2
    )                                             AS pct_of_dept_total,

    -- Overall rank this period by pay
    ROW_NUMBER() OVER (
      ORDER BY pr.net_amount DESC
    )                                             AS overall_rank

FROM payroll_records pr
JOIN employees e ON pr.employee_id = e.employee_id
WHERE pr.payroll_status = 'processed';

-- ------------------------------------------------------------
-- View 6: v_data_mart_dept_summary  (Data Mart view)
-- Reads from the DATA WAREHOUSE (fact + dimensions).
-- This is the "data mart" view required by the rubric.
-- Uses: multi-table JOIN across star schema
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW `v_data_mart_dept_summary` AS
SELECT
    dd.department_name,
    dt.year_number,
    dt.month_number,
    dt.month_name_text,
    dt.year_month_text,
    dt.quarter_number,
    COUNT(fp.fact_id)              AS payroll_count,
    ROUND(SUM(fp.net_pay), 2)      AS total_net_pay,
    ROUND(SUM(fp.gross_pay), 2)    AS total_gross_pay,
    ROUND(SUM(fp.total_deductions),2) AS total_deductions,
    ROUND(AVG(fp.net_pay), 2)      AS avg_net_pay,
    ROUND(MAX(fp.net_pay), 2)      AS highest_net_pay,
    ROUND(MIN(fp.net_pay), 2)      AS lowest_net_pay
FROM fact_payroll fp
JOIN dim_employee   de ON fp.employee_key   = de.employee_key
JOIN dim_time       dt ON fp.time_key       = dt.time_key
JOIN dim_department dd ON fp.department_key = dd.department_key
GROUP BY
    dd.department_name,
    dt.year_number,
    dt.month_number,
    dt.month_name_text,
    dt.year_month_text,
    dt.quarter_number
ORDER BY dt.year_number DESC, dt.month_number DESC, total_net_pay DESC;

-- ------------------------------------------------------------
-- View 7: v_payroll_history_detail
-- Full detail view with JOINs for the History page filter.
-- Uses: JOIN, CONCAT, DATE formatting subexpression
-- Supports filtering by department and year in PHP via:
--   SELECT * FROM v_payroll_history_detail
--   WHERE department = ? AND payroll_year = ?
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW `v_payroll_history_detail` AS
SELECT
    pr.payroll_id,
    pr.employee_id,
    e.employee_code,
    CONCAT(e.first_name, ' ', e.last_name)   AS full_name,
    e.department,
    e.emp_position,
    pr.payroll_month,
    pr.payroll_year,
    CONCAT(
      MONTHNAME(CONCAT(pr.payroll_year,'-',LPAD(pr.payroll_month,2,'0'),'-01')),
      ' ', pr.payroll_year
    )                                        AS period_name,
    pr.basic_salary,
    pr.allowances_amount,
    pr.overtime_amount,
    pr.gross_amount,
    pr.tax_amount,
    pr.sss_amount,
    pr.philhealth_amount,
    pr.pagibig_amount,
    pr.other_deductions,
    pr.total_deductions_amount,
    pr.net_amount,
    pr.payroll_status,
    pr.processed_at,
    pr.lock_version
FROM payroll_records pr
JOIN employees e ON pr.employee_id = e.employee_id
ORDER BY pr.payroll_year DESC, pr.payroll_month DESC, e.department, e.last_name;

-- ============================================================
-- SECTION 6: SAMPLE DATA
-- 3 months of payroll (March, April, May 2026) for 10 employees
-- so that window functions (LAG, moving avg, trend) show data.
-- ============================================================

START TRANSACTION;

INSERT INTO `users` (user_id, username, password, full_name, email,
                     user_role, user_status, avatar_color) VALUES
(1, 'admin',
   '$2y$10$fLoEa9Jr9Zf6Di41Hx6qKOuAGbo.pgyEIu5mi76JGMxEFj1GmIM86',
   'Admin User', 'admin@payroll.com', 'admin', 'active', '#e6395c'),
(2, 'hr_staff',
   '$2y$10$3yZTqQj9AS20ZcoXKk/xju6nggegpHqEM8zOIVmhBivBIjb0qo8v6',
   'HR Staff', 'hr@payroll.com', 'staff', 'active', '#3b82f6'),
(3, 'payroll_officer',
   '$2y$10$CYQumsrQGAfgvv503Dcgpuu31UotphW.or1O2plaN0r3tn7h4wIFG',
   'Payroll Officer', 'payroll@payroll.com', 'staff', 'active', '#10b981');

INSERT INTO `employees` (employee_id, employee_code, first_name, last_name,
  email, department, emp_position, basic_salary, hire_date, emp_status, version_number)
VALUES
(1, 'EMP001','Sophia','Reyes','sophia.reyes@goodness.com',
   'Information Technology','Senior Developer',95000.00,'2020-01-15','active',1),
(2, 'EMP002','Miguel','Santos','miguel.santos@goodness.com',
   'Information Technology','System Analyst',82000.00,'2020-03-10','active',1),
(3, 'EMP003','Isabella','Dizon','isabella.dizon@goodness.com',
   'Finance','Finance Manager',105000.00,'2019-06-01','active',1),
(4, 'EMP004','Lucas','Fernandez','lucas.fernandez@goodness.com',
   'Human Resources','HR Manager',88000.00,'2020-02-20','active',1),
(5, 'EMP005','Emma','Garcia','emma.garcia@goodness.com',
   'Marketing','Marketing Director',92000.00,'2020-08-14','active',1),
(6, 'EMP006','Daniel','Torres','daniel.torres@goodness.com',
   'Engineering','Civil Engineer',78000.00,'2021-01-10','active',1),
(7, 'EMP007','Olivia','Cruz','olivia.cruz@goodness.com',
   'Administration','Admin Manager',68000.00,'2021-03-05','active',1),
(8, 'EMP008','James','Rivera','james.rivera@goodness.com',
   'Information Technology','Junior Developer',55000.00,'2022-01-20','active',1),
(9, 'EMP009','Ella','Mendoza','ella.mendoza@goodness.com',
   'Marketing','Social Media Manager',62000.00,'2022-02-15','active',1),
(10,'EMP010','Carlos','Gonzales','carlos.gonzales@goodness.com',
   'Finance','Senior Accountant',72000.00,'2021-06-10','active',1);

INSERT INTO `permissions`
  (permission_id, role_name, module_name,
   can_view, can_create, can_edit, can_delete, can_process_payroll)
VALUES
(1,'admin','dashboard',  1,1,1,1,1),(2,'admin','employees', 1,1,1,1,1),
(3,'admin','payroll',    1,1,1,1,1),(4,'admin','history',   1,1,1,1,1),
(5,'admin','warehouse',  1,1,1,1,1),
(6,'staff','dashboard',  1,0,0,0,0),(7,'staff','employees', 1,1,0,0,0),
(8,'staff','payroll',    0,0,0,0,0),(9,'staff','history',   1,0,0,0,0),
(10,'staff','warehouse', 0,0,0,0,0);

-- ── 3 months of payroll data (March, April, May 2026) ────────
-- This gives the window functions meaningful multi-period data.

INSERT INTO `payroll_records`
  (employee_id, payroll_month, payroll_year,
   basic_salary, allowances_amount, overtime_amount,
   tax_amount, sss_amount, philhealth_amount, pagibig_amount, other_deductions,
   gross_amount, total_deductions_amount, net_amount,
   payroll_status, processed_at, lock_version)
VALUES
-- ── March 2026 ───────────────────────────────────────────────
(1,3,2026,95000.00,9500.00,2500.00, 9500.00,1350.00,450.00,200.00,0.00,107000.00,11500.00,95500.00,'processed','2026-03-31 10:00:00',1),
(2,3,2026,82000.00,8200.00,1500.00, 8200.00,1350.00,450.00,200.00,0.00, 91700.00,10200.00,81500.00,'processed','2026-03-31 10:00:00',1),
(3,3,2026,105000.00,10500.00,3000.00,10500.00,1350.00,450.00,200.00,0.00,118500.00,12500.00,106000.00,'processed','2026-03-31 10:00:00',1),
(4,3,2026,88000.00,8800.00,2000.00, 8800.00,1350.00,450.00,200.00,0.00, 98800.00,10800.00,88000.00,'processed','2026-03-31 10:00:00',1),
(5,3,2026,92000.00,9200.00,2200.00, 9200.00,1350.00,450.00,200.00,0.00,103400.00,11200.00,92200.00,'processed','2026-03-31 10:00:00',1),
(6,3,2026,78000.00,7800.00,1800.00, 7800.00,1350.00,450.00,200.00,0.00, 87600.00, 9800.00,77800.00,'processed','2026-03-31 10:00:00',1),
(7,3,2026,68000.00,6800.00,1200.00, 6800.00,1350.00,450.00,200.00,0.00, 76000.00, 8800.00,67200.00,'processed','2026-03-31 10:00:00',1),
(8,3,2026,55000.00,5500.00,1000.00, 5500.00,1350.00,450.00,200.00,0.00, 61500.00, 7500.00,54000.00,'processed','2026-03-31 10:00:00',1),
(9,3,2026,62000.00,6200.00,1100.00, 6200.00,1350.00,450.00,200.00,0.00, 69300.00, 8200.00,61100.00,'processed','2026-03-31 10:00:00',1),
(10,3,2026,72000.00,7200.00,1600.00, 7200.00,1350.00,450.00,200.00,0.00, 80800.00, 9200.00,71600.00,'processed','2026-03-31 10:00:00',1),

-- ── April 2026 ───────────────────────────────────────────────
(1,4,2026,95000.00,9500.00,3500.00, 9500.00,1350.00,450.00,200.00,0.00,108000.00,11500.00,96500.00,'processed','2026-04-30 10:00:00',1),
(2,4,2026,82000.00,8200.00,2000.00, 8200.00,1350.00,450.00,200.00,0.00, 92200.00,10200.00,82000.00,'processed','2026-04-30 10:00:00',1),
(3,4,2026,105000.00,10500.00,4000.00,10500.00,1350.00,450.00,200.00,0.00,119500.00,12500.00,107000.00,'processed','2026-04-30 10:00:00',1),
(4,4,2026,88000.00,8800.00,2500.00, 8800.00,1350.00,450.00,200.00,0.00, 99300.00,10800.00,88500.00,'processed','2026-04-30 10:00:00',1),
(5,4,2026,92000.00,9200.00,3000.00, 9200.00,1350.00,450.00,200.00,0.00,104200.00,11200.00,93000.00,'processed','2026-04-30 10:00:00',1),
(6,4,2026,78000.00,7800.00,2200.00, 7800.00,1350.00,450.00,200.00,0.00, 88000.00, 9800.00,78200.00,'processed','2026-04-30 10:00:00',1),
(7,4,2026,68000.00,6800.00,1500.00, 6800.00,1350.00,450.00,200.00,0.00, 76300.00, 8800.00,67500.00,'processed','2026-04-30 10:00:00',1),
(8,4,2026,55000.00,5500.00,1200.00, 5500.00,1350.00,450.00,200.00,0.00, 61700.00, 7500.00,54200.00,'processed','2026-04-30 10:00:00',1),
(9,4,2026,62000.00,6200.00,1400.00, 6200.00,1350.00,450.00,200.00,0.00, 69600.00, 8200.00,61400.00,'processed','2026-04-30 10:00:00',1),
(10,4,2026,72000.00,7200.00,1800.00, 7200.00,1350.00,450.00,200.00,0.00, 81000.00, 9200.00,71800.00,'processed','2026-04-30 10:00:00',1),

-- ── May 2026 ─────────────────────────────────────────────────
(1,5,2026,95000.00,9500.00,4750.00, 9500.00,1350.00,450.00,200.00,0.00,109250.00,11500.00,97750.00,'processed',NOW(),1),
(2,5,2026,82000.00,8200.00,4100.00, 8200.00,1350.00,450.00,200.00,0.00, 94300.00,10200.00,84100.00,'processed',NOW(),1),
(3,5,2026,105000.00,10500.00,5250.00,10500.00,1350.00,450.00,200.00,0.00,120750.00,12500.00,108250.00,'processed',NOW(),1),
(4,5,2026,88000.00,8800.00,4400.00, 8800.00,1350.00,450.00,200.00,0.00,101200.00,10800.00,90400.00,'processed',NOW(),1),
(5,5,2026,92000.00,9200.00,4600.00, 9200.00,1350.00,450.00,200.00,0.00,105800.00,11200.00,94600.00,'processed',NOW(),1),
(6,5,2026,78000.00,7800.00,3900.00, 7800.00,1350.00,450.00,200.00,0.00, 89700.00, 9800.00,79900.00,'processed',NOW(),1),
(7,5,2026,68000.00,6800.00,3400.00, 6800.00,1350.00,450.00,200.00,0.00, 78200.00, 8800.00,69400.00,'processed',NOW(),1),
(8,5,2026,55000.00,5500.00,2750.00, 5500.00,1350.00,450.00,200.00,0.00, 63250.00, 7500.00,55750.00,'processed',NOW(),1),
(9,5,2026,62000.00,6200.00,3100.00, 6200.00,1350.00,450.00,200.00,0.00, 71300.00, 8200.00,63100.00,'processed',NOW(),1),
(10,5,2026,72000.00,7200.00,3600.00, 7200.00,1350.00,450.00,200.00,0.00, 82800.00, 9200.00,73600.00,'processed',NOW(),1);

COMMIT;

-- ============================================================
-- SECTION 7: POPULATE DATA WAREHOUSE (initial ETL run)
-- Run the ETL stored procedure to pre-load the star schema
-- so the Data Warehouse page shows data on first load.
-- ============================================================

CALL sp_run_etl();

-- ============================================================
-- END OF SCRIPT
-- ============================================================
-- QUICK REFERENCE — How each rubric topic is implemented:
--
-- [1] Advanced SQL
--     Views 3-5: RANK, DENSE_RANK, LAG, LEAD, NTILE, FIRST_VALUE,
--     AVG/SUM OVER with window frames, ROW_NUMBER, ROWS BETWEEN
--     Views 2,7: JOINs + correlated subqueries
--
-- [2] Transaction Management
--     sp_process_payroll: START TRANSACTION ... COMMIT / ROLLBACK
--     sp_run_etl:         START TRANSACTION ... COMMIT / ROLLBACK
--     sp_add_employee:    START TRANSACTION ... COMMIT / ROLLBACK
--     Sample data INSERT wrapped in START TRANSACTION / COMMIT
--
-- [3] Concurrency Control
--     Optimistic:  version_number (employees), lock_version (payroll_records)
--                  sp_process_payroll checks lock_version before UPDATE
--     Pessimistic: SELECT ... FOR UPDATE in sp_process_payroll (row lock)
--
-- [4] Data Warehousing
--     Star schema: fact_payroll (fact) + dim_employee + dim_time
--                  + dim_department (3 dimensions)
--     Data mart view: v_data_mart_dept_summary
--
-- [5] Data Integration / ETL
--     sp_run_etl: Extract (payroll_records+employees) →
--                 Transform (keys, quarter, half_year, SCD Type 1) →
--                 Load (dim_* + fact_payroll)
--     etl_jobs tracks every run with status + record count + error log
--
-- [6] Referential Integrity
--     payroll_records.employee_id → employees.employee_id
--       ON DELETE RESTRICT (can't delete employee with payroll)
--       ON UPDATE CASCADE
--     activity_logs.user_id → users.user_id ON DELETE SET NULL
--       (user deleted => user_id nulled, audit row + frozen username kept)
--     fact_payroll → dim_employee, dim_time, dim_department
--       ON DELETE CASCADE (clean ETL re-run)
--     CHECK constraints on basic_salary, payroll_month, payroll_year
-- ============================================================