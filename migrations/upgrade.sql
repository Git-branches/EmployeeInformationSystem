-- =============================================================
-- Schema upgrade — brings an EXISTING database up to date
--
-- Run automatically by the installer (scripts/eis-dbsetup.bat) on every
-- install. A fresh install already gets everything from
-- employee_information_system.sql, so this script then does nothing.
--
-- Every statement here must be safe to run repeatedly: each change is
-- applied only when it is actually missing. MySQL 8 has no
-- "ADD COLUMN IF NOT EXISTS", hence the information_schema check.
--
-- When a new numbered migration is added, mirror it here in the same
-- shape so upgrading installations pick it up too.
--
-- Run by hand with:
--   mysql -u root employee_information_system < upgrade.sql
-- =============================================================

USE employee_information_system;

-- ---- 002: monthly salary ------------------------------------
SET @stmt = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'employees'
        AND COLUMN_NAME  = 'monthly_salary') = 0,
    "ALTER TABLE employees ADD COLUMN monthly_salary DECIMAL(12,2) NULL COMMENT 'Philippine pesos' AFTER date_hired",
    'DO 0'
);
PREPARE eis_upgrade FROM @stmt;
EXECUTE eis_upgrade;
DEALLOCATE PREPARE eis_upgrade;

-- ---- 003: positions, employment statuses, name / address, GSIS ----
-- Seed the starting statuses only when the table is being created now,
-- so statuses an administrator deleted are not brought back on upgrade.
SET @eis_new_statuses = (SELECT COUNT(*) = 0 FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employment_statuses');

CREATE TABLE IF NOT EXISTS positions (
  position_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  position_name VARCHAR(100) NOT NULL,
  description   VARCHAR(255) NULL,
  PRIMARY KEY (position_id),
  UNIQUE KEY uq_positions_name (position_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employment_statuses (
  employment_status_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  status_name          VARCHAR(50)  NOT NULL,
  description          VARCHAR(255) NULL,
  PRIMARY KEY (employment_status_id),
  UNIQUE KEY uq_employment_statuses_name (status_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @stmt = IF(@eis_new_statuses,
    "INSERT IGNORE INTO employment_statuses (status_name) VALUES ('Permanent'), ('Temporary'), ('Contractual'), ('Casual')",
    'DO 0');
PREPARE eis_upgrade FROM @stmt;
EXECUTE eis_upgrade;
DEALLOCATE PREPARE eis_upgrade;

SET @stmt = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'no_middle_name') = 0,
    "ALTER TABLE employees ADD COLUMN no_middle_name TINYINT(1) NOT NULL DEFAULT 0 AFTER middle_name",
    'DO 0'
);
PREPARE eis_upgrade FROM @stmt;
EXECUTE eis_upgrade;
DEALLOCATE PREPARE eis_upgrade;

SET @stmt = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'name_extension') = 0,
    "ALTER TABLE employees ADD COLUMN name_extension VARCHAR(10) NULL COMMENT 'Jr., Sr., III ...' AFTER last_name",
    'DO 0'
);
PREPARE eis_upgrade FROM @stmt;
EXECUTE eis_upgrade;
DEALLOCATE PREPARE eis_upgrade;

SET @stmt = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'address_street') = 0,
    "ALTER TABLE employees ADD COLUMN address_street VARCHAR(150) NULL COMMENT 'Purok / street' AFTER address",
    'DO 0'
);
PREPARE eis_upgrade FROM @stmt;
EXECUTE eis_upgrade;
DEALLOCATE PREPARE eis_upgrade;

SET @stmt = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'address_barangay') = 0,
    "ALTER TABLE employees ADD COLUMN address_barangay VARCHAR(100) NULL AFTER address_street",
    'DO 0'
);
PREPARE eis_upgrade FROM @stmt;
EXECUTE eis_upgrade;
DEALLOCATE PREPARE eis_upgrade;

SET @stmt = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'address_municipality') = 0,
    "ALTER TABLE employees ADD COLUMN address_municipality VARCHAR(100) NULL AFTER address_barangay",
    'DO 0'
);
PREPARE eis_upgrade FROM @stmt;
EXECUTE eis_upgrade;
DEALLOCATE PREPARE eis_upgrade;

SET @stmt = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'address_province') = 0,
    "ALTER TABLE employees ADD COLUMN address_province VARCHAR(100) NULL AFTER address_municipality",
    'DO 0'
);
PREPARE eis_upgrade FROM @stmt;
EXECUTE eis_upgrade;
DEALLOCATE PREPARE eis_upgrade;

SET @stmt = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'gsis_no') = 0,
    "ALTER TABLE employees ADD COLUMN gsis_no VARCHAR(20) NULL AFTER tin_no",
    'DO 0'
);
PREPARE eis_upgrade FROM @stmt;
EXECUTE eis_upgrade;
DEALLOCATE PREPARE eis_upgrade;

SET @stmt = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'position_id') = 0,
    "ALTER TABLE employees ADD COLUMN position_id INT UNSIGNED NULL AFTER department_id",
    'DO 0'
);
PREPARE eis_upgrade FROM @stmt;
EXECUTE eis_upgrade;
DEALLOCATE PREPARE eis_upgrade;

SET @stmt = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'employment_status_id') = 0,
    "ALTER TABLE employees ADD COLUMN employment_status_id INT UNSIGNED NULL AFTER employment_status",
    'DO 0'
);
PREPARE eis_upgrade FROM @stmt;
EXECUTE eis_upgrade;
DEALLOCATE PREPARE eis_upgrade;

SET @stmt = IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND CONSTRAINT_NAME = 'fk_employees_position') = 0,
    "ALTER TABLE employees ADD CONSTRAINT fk_employees_position FOREIGN KEY (position_id) REFERENCES positions (position_id) ON UPDATE CASCADE ON DELETE SET NULL",
    'DO 0'
);
PREPARE eis_upgrade FROM @stmt;
EXECUTE eis_upgrade;
DEALLOCATE PREPARE eis_upgrade;

SET @stmt = IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND CONSTRAINT_NAME = 'fk_employees_employment_status') = 0,
    "ALTER TABLE employees ADD CONSTRAINT fk_employees_employment_status FOREIGN KEY (employment_status_id) REFERENCES employment_statuses (employment_status_id) ON UPDATE CASCADE ON DELETE SET NULL",
    'DO 0'
);
PREPARE eis_upgrade FROM @stmt;
EXECUTE eis_upgrade;
DEALLOCATE PREPARE eis_upgrade;

-- Link typed positions to the list (only rows not linked yet, so re-running is harmless)
INSERT IGNORE INTO positions (position_name)
SELECT DISTINCT TRIM(position) FROM employees
WHERE position_id IS NULL AND position IS NOT NULL AND TRIM(position) <> '';

UPDATE employees e
JOIN positions p ON p.position_name = TRIM(e.position)
SET e.position_id = p.position_id
WHERE e.position_id IS NULL;

-- Standardise valid mobile numbers as 0994-800-7500 (already-formatted ones are unchanged)
UPDATE employees e
JOIN (
    SELECT DISTINCT employee_id,
           CASE WHEN digits REGEXP '^09[0-9]{9}$'  THEN digits
                WHEN digits REGEXP '^639[0-9]{9}$' THEN CONCAT('0', SUBSTRING(digits, 3))
           END AS d
    FROM (SELECT employee_id, REGEXP_REPLACE(contact_no, '[^0-9]', '') AS digits
          FROM employees WHERE contact_no IS NOT NULL) raw
) n ON n.employee_id = e.employee_id
SET e.contact_no = CONCAT(SUBSTRING(n.d, 1, 4), '-', SUBSTRING(n.d, 5, 3), '-', SUBSTRING(n.d, 8, 4))
WHERE n.d IS NOT NULL;
