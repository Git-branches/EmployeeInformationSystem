-- =============================================================
-- Migration 002 — Monthly salary
-- Adds the monthly salary captured on the employee information form.
--
-- The daily salary is NOT stored: it is always derived as
-- monthly_salary / 22 working days (see daily_salary() in
-- includes/functions.php), so the two values can never drift apart.
--
-- Safe to run on a database that already contains employees:
-- the column is optional (NULL) for records entered before this change.
--
-- Run with:
--   mysql -u root employee_information_system < 002_monthly_salary.sql
-- =============================================================

USE employee_information_system;

ALTER TABLE employees
    ADD COLUMN monthly_salary DECIMAL(12,2) NULL COMMENT 'Philippine pesos' AFTER date_hired;
