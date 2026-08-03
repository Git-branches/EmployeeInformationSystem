-- =============================================================
-- Migration 001 — Employee Information Form fields
-- Adds the personal, government ID, status, eligibility and
-- emergency-contact fields used by the Jollibee Tupi form.
--
-- Safe to run on a database that already contains employees:
-- every new column is optional (NULL) or defaults to unchecked.
--
-- Run with:
--   mysql -u root employee_information_system < 001_employee_form_fields.sql
-- =============================================================

USE employee_information_system;

ALTER TABLE employees
    -- ---- Personal information -------------------------------
    ADD COLUMN birthplace    VARCHAR(150) NULL AFTER birthdate,
    ADD COLUMN civil_status  ENUM('Single','Married','Widowed','Separated','Annulled') NULL AFTER sex,
    ADD COLUMN blood_type    ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NULL AFTER civil_status,
    ADD COLUMN height_cm     DECIMAL(5,2) NULL COMMENT 'centimetres' AFTER blood_type,
    ADD COLUMN weight_kg     DECIMAL(5,2) NULL COMMENT 'kilograms'   AFTER height_cm,

    -- ---- Government ID numbers ------------------------------
    -- The numbers themselves. Whether the employee has SUBMITTED a
    -- copy of each document is tracked separately in employee_requirements.
    ADD COLUMN sss_no        VARCHAR(20) NULL AFTER address,
    ADD COLUMN philhealth_no VARCHAR(20) NULL AFTER sss_no,
    ADD COLUMN pagibig_no    VARCHAR(20) NULL AFTER philhealth_no,
    ADD COLUMN tin_no        VARCHAR(20) NULL AFTER pagibig_no,

    -- ---- Personal status (checkboxes) -----------------------
    ADD COLUMN is_solo_parent TINYINT(1) NOT NULL DEFAULT 0 AFTER tin_no,
    ADD COLUMN is_ip          TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Indigenous People' AFTER is_solo_parent,
    ADD COLUMN is_pwd         TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Person with Disability' AFTER is_ip,
    ADD COLUMN is_smoker      TINYINT(1) NOT NULL DEFAULT 0 AFTER is_pwd,

    -- ---- Eligibility (checkboxes) ---------------------------
    ADD COLUMN elig_professional     TINYINT(1) NOT NULL DEFAULT 0 AFTER is_smoker,
    ADD COLUMN elig_sub_professional TINYINT(1) NOT NULL DEFAULT 0 AFTER elig_professional,
    ADD COLUMN elig_ra1080           TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'RA 1080 (Bar/Board passer)' AFTER elig_sub_professional,

    -- ---- Emergency contact ----------------------------------
    ADD COLUMN emergency_contact_name VARCHAR(150) NULL AFTER elig_ra1080,
    ADD COLUMN emergency_contact_no   VARCHAR(20)  NULL AFTER emergency_contact_name;
