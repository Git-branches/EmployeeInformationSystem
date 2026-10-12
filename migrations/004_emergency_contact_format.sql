-- =============================================================
-- Migration 004 — Emergency contact number format
--
-- The employee's CP number is stored as 0994-800-7500; the emergency
-- contact number was left as typed. This brings the existing records
-- into the same format, so both read alike on screen and in print.
--
-- Only valid mobile numbers (09XXXXXXXXX or +639XXXXXXXXX) are rewritten.
-- A landline or any other entry is left exactly as it is.
--
-- Run with:
--   mysql -u root employee_information_system < 004_emergency_contact_format.sql
-- =============================================================

USE employee_information_system;

UPDATE employees e
JOIN (
    SELECT DISTINCT employee_id,
           CASE WHEN digits REGEXP '^09[0-9]{9}$'  THEN digits
                WHEN digits REGEXP '^639[0-9]{9}$' THEN CONCAT('0', SUBSTRING(digits, 3))
           END AS d
    FROM (SELECT employee_id, REGEXP_REPLACE(emergency_contact_no, '[^0-9]', '') AS digits
          FROM employees WHERE emergency_contact_no IS NOT NULL) raw
) n ON n.employee_id = e.employee_id
SET e.emergency_contact_no = CONCAT(SUBSTRING(n.d, 1, 4), '-', SUBSTRING(n.d, 5, 3), '-', SUBSTRING(n.d, 8, 4))
WHERE n.d IS NOT NULL;
