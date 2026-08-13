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
