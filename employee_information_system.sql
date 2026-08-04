-- =============================================================
-- Employee Information System — Database Schema
-- Jollibee Tupi (Brgy. Poblacion, Tupi)
-- Engine: MySQL (InnoDB) | Charset: utf8mb4
-- =============================================================

CREATE DATABASE IF NOT EXISTS employee_information_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE employee_information_system;

-- -------------------------------------------------------------
-- 1. users — administrator accounts
-- -------------------------------------------------------------
CREATE TABLE users (
  user_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(50)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(100) NOT NULL,
  last_login    DATETIME     NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 2. departments — department reference list
-- -------------------------------------------------------------
CREATE TABLE departments (
  department_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  department_name VARCHAR(100) NOT NULL,
  description     VARCHAR(255) NULL,
  PRIMARY KEY (department_id),
  UNIQUE KEY uq_departments_name (department_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 3. employees — central employee / applicant profiles
-- -------------------------------------------------------------
CREATE TABLE employees (
  employee_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_no       VARCHAR(20)  NULL,
  first_name        VARCHAR(60)  NOT NULL,
  middle_name       VARCHAR(60)  NULL,
  last_name         VARCHAR(60)  NOT NULL,
  birthdate         DATE         NOT NULL,
  birthplace        VARCHAR(150) NULL,
  sex               ENUM('Male','Female') NOT NULL,
  civil_status      ENUM('Single','Married','Widowed','Separated','Annulled') NULL,
  blood_type        ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NULL,
  height_cm         DECIMAL(5,2) NULL COMMENT 'centimetres',
  weight_kg         DECIMAL(5,2) NULL COMMENT 'kilograms',
  contact_no        VARCHAR(20)  NULL,
  email             VARCHAR(100) NULL,
  address           VARCHAR(255) NULL,

  -- Government ID numbers. Whether a copy of each document has been
  -- submitted is tracked separately in employee_requirements.
  sss_no            VARCHAR(20)  NULL,
  philhealth_no     VARCHAR(20)  NULL,
  pagibig_no        VARCHAR(20)  NULL,
  tin_no            VARCHAR(20)  NULL,

  -- Personal status
  is_solo_parent    TINYINT(1)   NOT NULL DEFAULT 0,
  is_ip             TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Indigenous People',
  is_pwd            TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Person with Disability',
  is_smoker         TINYINT(1)   NOT NULL DEFAULT 0,

  -- Eligibility
  elig_professional     TINYINT(1) NOT NULL DEFAULT 0,
  elig_sub_professional TINYINT(1) NOT NULL DEFAULT 0,
  elig_ra1080           TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'RA 1080 (Bar/Board passer)',

  -- Emergency contact
  emergency_contact_name VARCHAR(150) NULL,
  emergency_contact_no   VARCHAR(20)  NULL,

  photo_path        VARCHAR(255) NULL,
  department_id     INT UNSIGNED NULL,
  position          VARCHAR(100) NULL,
  applicant_type    ENUM('New','Existing') NOT NULL DEFAULT 'New',
  employment_status ENUM('Applicant','Active','Inactive','Terminated') NOT NULL DEFAULT 'Applicant',
  date_hired        DATE         NULL,
  created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (employee_id),
  UNIQUE KEY uq_employees_employee_no (employee_no),
  KEY idx_employees_name (last_name, first_name),
  CONSTRAINT fk_employees_department
    FOREIGN KEY (department_id) REFERENCES departments (department_id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 4. requirement_types — master checklist of required documents
-- -------------------------------------------------------------
CREATE TABLE requirement_types (
  requirement_type_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  requirement_name    VARCHAR(100) NOT NULL,
  description         VARCHAR(255) NULL,
  is_active           TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (requirement_type_id),
  UNIQUE KEY uq_requirement_types_name (requirement_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 5. employee_requirements — per-employee document status
--    (junction table: employees N—N requirement_types)
-- -------------------------------------------------------------
CREATE TABLE employee_requirements (
  employee_requirement_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_id             INT UNSIGNED NOT NULL,
  requirement_type_id     INT UNSIGNED NOT NULL,
  status                  ENUM('Missing','Incomplete','Submitted','Verified') NOT NULL DEFAULT 'Missing',
  file_path               VARCHAR(255) NULL,
  date_submitted          DATE         NULL,
  remarks                 VARCHAR(255) NULL,
  updated_at              TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (employee_requirement_id),
  UNIQUE KEY uq_employee_requirement (employee_id, requirement_type_id),
  CONSTRAINT fk_empreq_employee
    FOREIGN KEY (employee_id) REFERENCES employees (employee_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_empreq_requirement_type
    FOREIGN KEY (requirement_type_id) REFERENCES requirement_types (requirement_type_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 6. notifications — in-app alerts for missing/incomplete documents
-- -------------------------------------------------------------
CREATE TABLE notifications (
  notification_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_id     INT UNSIGNED NOT NULL,
  message         VARCHAR(255) NOT NULL,
  is_read         TINYINT(1)   NOT NULL DEFAULT 0,
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (notification_id),
  KEY idx_notifications_unread (is_read, created_at),
  CONSTRAINT fk_notifications_employee
    FOREIGN KEY (employee_id) REFERENCES employees (employee_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 7. import_logs — history of Excel/PDF data imports
-- -------------------------------------------------------------
CREATE TABLE import_logs (
  import_id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  file_name          VARCHAR(255) NOT NULL,
  file_type          ENUM('Excel','PDF') NOT NULL,
  records_imported   INT UNSIGNED NOT NULL DEFAULT 0,
  duplicates_skipped INT UNSIGNED NOT NULL DEFAULT 0,
  imported_by        INT UNSIGNED NOT NULL,
  created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (import_id),
  CONSTRAINT fk_import_logs_user
    FOREIGN KEY (imported_by) REFERENCES users (user_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 8. backup_logs — history of local / Google Drive backups
-- -------------------------------------------------------------
CREATE TABLE backup_logs (
  backup_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  file_name   VARCHAR(255) NOT NULL,
  file_size   INT UNSIGNED NULL,
  destination ENUM('Local','GoogleDrive') NOT NULL DEFAULT 'Local',
  status      ENUM('Success','Failed','Pending') NOT NULL DEFAULT 'Pending',
  created_by  INT UNSIGNED NOT NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (backup_id),
  CONSTRAINT fk_backup_logs_user
    FOREIGN KEY (created_by) REFERENCES users (user_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- Seed data: default administrator account
-- Username: admin | Password: admin123  (change after first login)
-- =============================================================
INSERT INTO users (username, password_hash, full_name)
VALUES ('admin', '$2y$10$v1WY31K3EPdmV7clJLtLKOn3KvptqDB.SjBwHdYfmh6ozABRepuOm', 'System Administrator');
