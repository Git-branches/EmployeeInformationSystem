-- =============================================================
-- Migration 003 — Positions, employment statuses, structured
-- name / address and GSIS number
--
-- * positions / employment_statuses: admin-managed lookup lists
--   (Positions and Employment Status pages), linked to employees.
--   employees.position is kept as the position's name so reports,
--   exports and imports keep working; position_id is the link.
-- * employment_status_id is the nature of appointment (Permanent,
--   Casual, ...). It is separate from employees.employment_status
--   (Applicant / Active / Inactive / Terminated), the record status
--   the dashboard and filters rely on.
-- * Name extension and "no middle name" flag. The display name
--   (SURNAME, FIRST M. EXT) is built from these at runtime.
-- * Address parts. employees.address stays as the full address
--   composed from the parts, so existing records keep their address.
-- * GSIS number beside the TIN.
--
-- Safe to run on a database that already contains employees:
-- every new column is optional, existing positions are copied into
-- the new list and linked, and valid mobile numbers are rewritten
-- in the standard 0994-800-7500 format.
--
-- Run with:
--   mysql -u root employee_information_system < 003_positions_statuses_address.sql
-- =============================================================

USE employee_information_system;

CREATE TABLE positions (
  position_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  position_name VARCHAR(100) NOT NULL,
  description   VARCHAR(255) NULL,
  PRIMARY KEY (position_id),
  UNIQUE KEY uq_positions_name (position_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE employment_statuses (
  employment_status_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  status_name          VARCHAR(50)  NOT NULL,
  description          VARCHAR(255) NULL,
  PRIMARY KEY (employment_status_id),
  UNIQUE KEY uq_employment_statuses_name (status_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Starting list; administrators can rename or delete these.
INSERT INTO employment_statuses (status_name) VALUES
  ('Permanent'), ('Temporary'), ('Contractual'), ('Casual');

ALTER TABLE employees
    ADD COLUMN no_middle_name       TINYINT(1)   NOT NULL DEFAULT 0 AFTER middle_name,
    ADD COLUMN name_extension       VARCHAR(10)  NULL COMMENT 'Jr., Sr., III ...' AFTER last_name,
    ADD COLUMN address_street       VARCHAR(150) NULL COMMENT 'Purok / street' AFTER address,
    ADD COLUMN address_barangay     VARCHAR(100) NULL AFTER address_street,
    ADD COLUMN address_municipality VARCHAR(100) NULL AFTER address_barangay,
    ADD COLUMN address_province     VARCHAR(100) NULL AFTER address_municipality,
    ADD COLUMN gsis_no              VARCHAR(20)  NULL AFTER tin_no,
    ADD COLUMN position_id          INT UNSIGNED NULL AFTER department_id,
    ADD COLUMN employment_status_id INT UNSIGNED NULL AFTER employment_status,
    ADD CONSTRAINT fk_employees_position
        FOREIGN KEY (position_id) REFERENCES positions (position_id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    ADD CONSTRAINT fk_employees_employment_status
        FOREIGN KEY (employment_status_id) REFERENCES employment_statuses (employment_status_id)
        ON UPDATE CASCADE ON DELETE SET NULL;

-- Copy the positions already typed on employee records into the list
INSERT IGNORE INTO positions (position_name)
SELECT DISTINCT TRIM(position) FROM employees WHERE position IS NOT NULL AND TRIM(position) <> '';

UPDATE employees e
JOIN positions p ON p.position_name = TRIM(e.position)
SET e.position_id = p.position_id
WHERE e.position_id IS NULL;

-- Standardise valid mobile numbers (09XXXXXXXXX or +639XXXXXXXXX) as 0994-800-7500
-- (DISTINCT makes MySQL materialise the derived table, so the same table can be updated.)
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
