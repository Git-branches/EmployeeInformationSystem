-- =============================================================
-- Employee Information System — Database
-- Engine: MySQL (InnoDB) | Charset: utf8mb4
--
-- Exported from the development database. Imported by the installer
-- (scripts/eis-dbsetup.bat) on a fresh install only; existing installs
-- are brought up to date by migrations/upgrade.sql instead.
--
-- Contains the schema plus starting data: the default administrator
-- (username: admin | password: admin123 — change after first login),
-- requirement types, employment statuses and departments.
-- No employee records, backup history or notifications.
-- =============================================================

-- MySQL dump 10.13  Distrib 8.0.30, for Win64 (x86_64)
--
-- Host: localhost    Database: employee_information_system
-- ------------------------------------------------------
-- Server version	8.0.30

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `employee_information_system`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `employee_information_system` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `employee_information_system`;

--
-- Table structure for table `backup_logs`
--

DROP TABLE IF EXISTS `backup_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `backup_logs` (
  `backup_id` int unsigned NOT NULL AUTO_INCREMENT,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int unsigned DEFAULT NULL,
  `destination` enum('Local','GoogleDrive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Local',
  `status` enum('Success','Failed','Pending') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  `created_by` int unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`backup_id`),
  KEY `fk_backup_logs_user` (`created_by`),
  CONSTRAINT `fk_backup_logs_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `departments` (
  `department_id` int unsigned NOT NULL AUTO_INCREMENT,
  `department_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`department_id`),
  UNIQUE KEY `uq_departments_name` (`department_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `employee_requirements`
--

DROP TABLE IF EXISTS `employee_requirements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_requirements` (
  `employee_requirement_id` int unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int unsigned NOT NULL,
  `requirement_type_id` int unsigned NOT NULL,
  `status` enum('Missing','Incomplete','Submitted','Verified') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Missing',
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_submitted` date DEFAULT NULL,
  `remarks` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`employee_requirement_id`),
  UNIQUE KEY `uq_employee_requirement` (`employee_id`,`requirement_type_id`),
  KEY `fk_empreq_requirement_type` (`requirement_type_id`),
  CONSTRAINT `fk_empreq_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_empreq_requirement_type` FOREIGN KEY (`requirement_type_id`) REFERENCES `requirement_types` (`requirement_type_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employees` (
  `employee_id` int unsigned NOT NULL AUTO_INCREMENT,
  `employee_no` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `middle_name` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_middle_name` tinyint(1) NOT NULL DEFAULT '0',
  `last_name` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_extension` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Jr., Sr., III ...',
  `birthdate` date NOT NULL,
  `birthplace` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sex` enum('Male','Female') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `civil_status` enum('Single','Married','Widowed','Separated','Annulled') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `height_cm` decimal(5,2) DEFAULT NULL COMMENT 'centimetres',
  `weight_kg` decimal(5,2) DEFAULT NULL COMMENT 'kilograms',
  `contact_no` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_street` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Purok / street',
  `address_barangay` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_municipality` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_province` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sss_no` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `philhealth_no` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pagibig_no` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tin_no` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gsis_no` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_solo_parent` tinyint(1) NOT NULL DEFAULT '0',
  `is_ip` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Indigenous People',
  `is_pwd` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Person with Disability',
  `is_smoker` tinyint(1) NOT NULL DEFAULT '0',
  `elig_professional` tinyint(1) NOT NULL DEFAULT '0',
  `elig_sub_professional` tinyint(1) NOT NULL DEFAULT '0',
  `elig_ra1080` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'RA 1080 (Bar/Board passer)',
  `emergency_contact_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_no` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department_id` int unsigned DEFAULT NULL,
  `position_id` int unsigned DEFAULT NULL,
  `position` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `applicant_type` enum('New','Existing') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'New',
  `employment_status` enum('Applicant','Active','Inactive','Terminated') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Applicant',
  `employment_status_id` int unsigned DEFAULT NULL,
  `date_hired` date DEFAULT NULL,
  `monthly_salary` decimal(12,2) DEFAULT NULL COMMENT 'Philippine pesos',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`employee_id`),
  UNIQUE KEY `uq_employees_employee_no` (`employee_no`),
  KEY `idx_employees_name` (`last_name`,`first_name`),
  KEY `fk_employees_department` (`department_id`),
  KEY `fk_employees_position` (`position_id`),
  KEY `fk_employees_employment_status` (`employment_status_id`),
  CONSTRAINT `fk_employees_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_employees_employment_status` FOREIGN KEY (`employment_status_id`) REFERENCES `employment_statuses` (`employment_status_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_employees_position` FOREIGN KEY (`position_id`) REFERENCES `positions` (`position_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `employment_statuses`
--

DROP TABLE IF EXISTS `employment_statuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employment_statuses` (
  `employment_status_id` int unsigned NOT NULL AUTO_INCREMENT,
  `status_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`employment_status_id`),
  UNIQUE KEY `uq_employment_statuses_name` (`status_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `import_logs`
--

DROP TABLE IF EXISTS `import_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `import_logs` (
  `import_id` int unsigned NOT NULL AUTO_INCREMENT,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` enum('Excel','PDF') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `records_imported` int unsigned NOT NULL DEFAULT '0',
  `duplicates_skipped` int unsigned NOT NULL DEFAULT '0',
  `imported_by` int unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`import_id`),
  KEY `fk_import_logs_user` (`imported_by`),
  CONSTRAINT `fk_import_logs_user` FOREIGN KEY (`imported_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `notification_id` int unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int unsigned NOT NULL,
  `message` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `idx_notifications_unread` (`is_read`,`created_at`),
  KEY `fk_notifications_employee` (`employee_id`),
  CONSTRAINT `fk_notifications_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `positions`
--

DROP TABLE IF EXISTS `positions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `positions` (
  `position_id` int unsigned NOT NULL AUTO_INCREMENT,
  `position_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`position_id`),
  UNIQUE KEY `uq_positions_name` (`position_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `requirement_types`
--

DROP TABLE IF EXISTS `requirement_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `requirement_types` (
  `requirement_type_id` int unsigned NOT NULL AUTO_INCREMENT,
  `requirement_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`requirement_type_id`),
  UNIQUE KEY `uq_requirement_types_name` (`requirement_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `user_id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed

-- =============================================================
-- Starting data
-- =============================================================
-- MySQL dump 10.13  Distrib 8.0.30, for Win64 (x86_64)
--
-- Host: localhost    Database: employee_information_system
-- ------------------------------------------------------
-- Server version	8.0.30

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` (`department_id`, `department_name`, `description`) VALUES (3,'Service Crew',NULL);
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `employment_statuses`
--

LOCK TABLES `employment_statuses` WRITE;
/*!40000 ALTER TABLE `employment_statuses` DISABLE KEYS */;
INSERT INTO `employment_statuses` (`employment_status_id`, `status_name`, `description`) VALUES (1,'Permanent',NULL);
INSERT INTO `employment_statuses` (`employment_status_id`, `status_name`, `description`) VALUES (2,'Temporary',NULL);
INSERT INTO `employment_statuses` (`employment_status_id`, `status_name`, `description`) VALUES (3,'Contractual',NULL);
INSERT INTO `employment_statuses` (`employment_status_id`, `status_name`, `description`) VALUES (4,'Casual',NULL);
/*!40000 ALTER TABLE `employment_statuses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `positions`
--

LOCK TABLES `positions` WRITE;
/*!40000 ALTER TABLE `positions` DISABLE KEYS */;
/*!40000 ALTER TABLE `positions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `requirement_types`
--

LOCK TABLES `requirement_types` WRITE;
/*!40000 ALTER TABLE `requirement_types` DISABLE KEYS */;
INSERT INTO `requirement_types` (`requirement_type_id`, `requirement_name`, `description`, `is_active`) VALUES (1,'SSS Number/E-1 Form',NULL,1);
INSERT INTO `requirement_types` (`requirement_type_id`, `requirement_name`, `description`, `is_active`) VALUES (2,'PhilHealth MDR',NULL,1);
INSERT INTO `requirement_types` (`requirement_type_id`, `requirement_name`, `description`, `is_active`) VALUES (3,'Pag-IBIG MDF',NULL,1);
INSERT INTO `requirement_types` (`requirement_type_id`, `requirement_name`, `description`, `is_active`) VALUES (4,'TIN/BIR Form 1902',NULL,1);
INSERT INTO `requirement_types` (`requirement_type_id`, `requirement_name`, `description`, `is_active`) VALUES (5,'NBI Clearance',NULL,1);
INSERT INTO `requirement_types` (`requirement_type_id`, `requirement_name`, `description`, `is_active`) VALUES (6,'Barangay Clearance',NULL,1);
INSERT INTO `requirement_types` (`requirement_type_id`, `requirement_name`, `description`, `is_active`) VALUES (7,'Health Certificate',NULL,1);
INSERT INTO `requirement_types` (`requirement_type_id`, `requirement_name`, `description`, `is_active`) VALUES (8,'PSA Birth Certificate',NULL,1);
INSERT INTO `requirement_types` (`requirement_type_id`, `requirement_name`, `description`, `is_active`) VALUES (9,'Resume/Biodata',NULL,1);
INSERT INTO `requirement_types` (`requirement_type_id`, `requirement_name`, `description`, `is_active`) VALUES (10,'2x2 ID Pictures',NULL,1);
/*!40000 ALTER TABLE `requirement_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` (`user_id`, `username`, `password_hash`, `full_name`, `last_login`, `created_at`) VALUES (1,'admin','$2y$10$v1WY31K3EPdmV7clJLtLKOn3KvptqDB.SjBwHdYfmh6ozABRepuOm','System Administrator',NULL,'2026-08-03 05:26:30');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed
