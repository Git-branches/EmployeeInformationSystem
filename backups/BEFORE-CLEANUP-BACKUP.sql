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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `backup_logs`
--

LOCK TABLES `backup_logs` WRITE;
/*!40000 ALTER TABLE `backup_logs` DISABLE KEYS */;
INSERT INTO `backup_logs` VALUES (1,'eis_backup_20260803_081953.sql',12166,'Local','Success',1,'2026-08-03 06:19:53'),(2,'eis_backup_20260803_081953.sql',12166,'GoogleDrive','Success',1,'2026-08-03 06:19:53'),(3,'eis_backup_20260803_081305.sql',17847,'Local','Success',1,'2026-08-03 08:13:05'),(4,'eis_backup_20260803_081305.sql',17847,'GoogleDrive','Success',1,'2026-08-03 08:13:05'),(5,'eis_backup_20260803_104743.sql',18079,'Local','Success',1,'2026-08-03 10:47:43'),(6,'eis_backup_20260803_104743.sql',18079,'GoogleDrive','Success',1,'2026-08-03 10:47:43');
/*!40000 ALTER TABLE `backup_logs` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` VALUES (3,'Service Crew',NULL),(4,'Kitchen Crew',NULL);
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB AUTO_INCREMENT=132 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_requirements`
--

LOCK TABLES `employee_requirements` WRITE;
/*!40000 ALTER TABLE `employee_requirements` DISABLE KEYS */;
INSERT INTO `employee_requirements` VALUES (1,2,1,'Verified',NULL,'2026-08-01','OK','2026-08-03 06:25:23'),(2,2,2,'Submitted',NULL,NULL,NULL,'2026-08-03 06:48:53'),(3,2,3,'Missing',NULL,NULL,NULL,'2026-08-03 06:25:23'),(4,2,4,'Missing',NULL,NULL,NULL,'2026-08-03 06:25:23'),(5,2,5,'Missing',NULL,NULL,NULL,'2026-08-03 06:25:23'),(6,2,6,'Missing',NULL,NULL,NULL,'2026-08-03 06:25:23'),(7,2,7,'Missing',NULL,NULL,NULL,'2026-08-03 06:25:23'),(8,2,8,'Missing',NULL,NULL,NULL,'2026-08-03 06:25:23'),(9,2,9,'Missing',NULL,NULL,NULL,'2026-08-03 06:25:23'),(10,2,10,'Missing',NULL,NULL,NULL,'2026-08-03 06:25:23'),(18,3,1,'Missing',NULL,NULL,NULL,'2026-08-03 06:26:14'),(19,3,2,'Missing',NULL,NULL,NULL,'2026-08-03 06:26:14'),(20,3,3,'Missing',NULL,NULL,NULL,'2026-08-03 06:26:14'),(21,3,4,'Missing',NULL,NULL,NULL,'2026-08-03 06:26:14'),(22,3,5,'Missing',NULL,NULL,NULL,'2026-08-03 06:26:14'),(23,3,6,'Missing',NULL,NULL,NULL,'2026-08-03 06:26:14'),(24,3,7,'Missing',NULL,NULL,NULL,'2026-08-03 06:26:14'),(25,3,8,'Missing',NULL,NULL,NULL,'2026-08-03 06:26:14'),(26,3,9,'Missing',NULL,NULL,NULL,'2026-08-03 06:26:14'),(27,3,10,'Missing',NULL,NULL,NULL,'2026-08-03 06:26:14'),(33,4,1,'Missing',NULL,NULL,NULL,'2026-08-03 06:26:32'),(34,4,2,'Missing',NULL,NULL,NULL,'2026-08-03 06:26:32'),(35,4,3,'Missing',NULL,NULL,NULL,'2026-08-03 06:26:32'),(36,4,4,'Missing',NULL,NULL,NULL,'2026-08-03 06:26:32'),(37,4,5,'Missing',NULL,NULL,NULL,'2026-08-03 06:26:32'),(38,4,6,'Missing',NULL,NULL,NULL,'2026-08-03 06:26:32'),(39,4,7,'Missing',NULL,NULL,NULL,'2026-08-03 06:26:32'),(40,4,8,'Missing',NULL,NULL,NULL,'2026-08-03 06:26:32'),(41,4,9,'Missing',NULL,NULL,NULL,'2026-08-03 06:26:32'),(42,4,10,'Verified','uploads/requirements/req_4_42_644978e4.jpg','2026-08-03',NULL,'2026-08-03 07:07:21');
/*!40000 ALTER TABLE `employee_requirements` ENABLE KEYS */;
UNLOCK TABLES;

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
  `last_name` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `birthdate` date NOT NULL,
  `sex` enum('Male','Female') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_no` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department_id` int unsigned DEFAULT NULL,
  `position` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `applicant_type` enum('New','Existing') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'New',
  `employment_status` enum('Applicant','Active','Inactive','Terminated') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Applicant',
  `date_hired` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`employee_id`),
  UNIQUE KEY `uq_employees_employee_no` (`employee_no`),
  KEY `idx_employees_name` (`last_name`,`first_name`),
  KEY `fk_employees_department` (`department_id`),
  CONSTRAINT `fk_employees_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
INSERT INTO `employees` VALUES (2,NULL,'Maria','Reyes','Santos','1999-03-22','Female','09181112222','maria@example.com','Tupi, South Cotabato',NULL,3,'Cashier','New','Applicant',NULL,'2026-08-03 06:25:23','2026-08-03 06:25:23'),(3,'JB-1001','Pedro',NULL,'Penduko','1995-11-02','Male','09170001111','pedro@example.com','Tupi',NULL,4,'Cook','New','Active','2026-01-15','2026-08-03 06:26:14','2026-08-03 06:26:14'),(4,NULL,'Juana','Cruz','Dizon','2000-07-09','Female','09223334444','juana@example.com','Poblacion Tupi',NULL,3,'Counter Staff','New','Applicant',NULL,'2026-08-03 06:26:32','2026-08-03 06:26:32');
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `import_logs`
--

LOCK TABLES `import_logs` WRITE;
/*!40000 ALTER TABLE `import_logs` DISABLE KEYS */;
INSERT INTO `import_logs` VALUES (1,'test_import.xlsx','Excel',1,2,1,'2026-08-03 06:26:14'),(2,'test_import.pdf','PDF',1,1,1,'2026-08-03 06:26:32'),(3,'qa_import.xlsx','Excel',1,0,1,'2026-08-03 10:13:24');
/*!40000 ALTER TABLE `import_logs` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,2,'Maria Santos has 9 pending requirement(s)',1,'2026-08-03 06:25:23'),(2,3,'Pedro Penduko has 10 pending requirement(s)',1,'2026-08-03 06:26:14'),(3,4,'Juana Dizon has 10 pending requirement(s)',1,'2026-08-03 06:26:32'),(4,4,'Juana Dizon has 10 pending requirement(s)',1,'2026-08-03 06:36:04'),(5,3,'Pedro Penduko has 10 pending requirement(s)',1,'2026-08-03 06:36:04'),(6,2,'Maria Santos has 9 pending requirement(s)',1,'2026-08-03 06:36:04'),(7,4,'Juana Dizon has 10 pending requirement(s)',1,'2026-08-03 06:41:49'),(8,3,'Pedro Penduko has 10 pending requirement(s)',1,'2026-08-03 06:41:49'),(9,2,'Maria Santos has 9 pending requirement(s)',1,'2026-08-03 06:41:49'),(10,4,'Juana Dizon has 10 pending requirement(s)',1,'2026-08-03 06:43:02'),(11,3,'Pedro Penduko has 10 pending requirement(s)',1,'2026-08-03 06:43:02'),(12,2,'Maria Santos has 9 pending requirement(s)',1,'2026-08-03 06:43:02'),(13,4,'Juana Dizon has 10 pending requirement(s)',1,'2026-08-03 06:43:05'),(14,3,'Pedro Penduko has 10 pending requirement(s)',1,'2026-08-03 06:43:05'),(15,2,'Maria Santos has 9 pending requirement(s)',1,'2026-08-03 06:43:05'),(16,4,'Juana Dizon has 10 pending requirement(s)',1,'2026-08-03 06:46:21'),(17,3,'Pedro Penduko has 10 pending requirement(s)',1,'2026-08-03 06:46:21'),(18,2,'Maria Santos has 9 pending requirement(s)',1,'2026-08-03 06:46:21'),(19,4,'Juana Dizon has 10 pending requirement(s)',1,'2026-08-03 06:46:26'),(20,3,'Pedro Penduko has 10 pending requirement(s)',1,'2026-08-03 06:46:26'),(21,2,'Maria Santos has 9 pending requirement(s)',1,'2026-08-03 06:46:26'),(22,4,'Juana Dizon has 10 pending requirement(s)',1,'2026-08-03 06:46:26'),(23,3,'Pedro Penduko has 10 pending requirement(s)',1,'2026-08-03 06:46:26'),(24,2,'Maria Santos has 9 pending requirement(s)',1,'2026-08-03 06:46:26'),(25,4,'Juana Dizon has 10 pending requirement(s)',1,'2026-08-03 06:46:40'),(26,3,'Pedro Penduko has 10 pending requirement(s)',1,'2026-08-03 06:46:40'),(27,2,'Maria Santos has 9 pending requirement(s)',1,'2026-08-03 06:46:40'),(28,2,'Maria Santos has 8 pending requirement(s)',1,'2026-08-03 06:48:53'),(29,4,'Juana Dizon has 9 pending requirement(s)',1,'2026-08-03 07:07:21');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `requirement_types`
--

LOCK TABLES `requirement_types` WRITE;
/*!40000 ALTER TABLE `requirement_types` DISABLE KEYS */;
INSERT INTO `requirement_types` VALUES (1,'SSS Number/E-1 Form',NULL,1),(2,'PhilHealth MDR',NULL,1),(3,'Pag-IBIG MDF',NULL,1),(4,'TIN/BIR Form 1902',NULL,1),(5,'NBI Clearance',NULL,1),(6,'Barangay Clearance',NULL,1),(7,'Health Certificate',NULL,1),(8,'PSA Birth Certificate',NULL,1),(9,'Resume/Biodata',NULL,1),(10,'2x2 ID Pictures',NULL,1);
/*!40000 ALTER TABLE `requirement_types` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','$2y$10$v1WY31K3EPdmV7clJLtLKOn3KvptqDB.SjBwHdYfmh6ozABRepuOm','System Administrator','2026-08-03 19:00:46','2026-08-03 05:26:30');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'employee_information_system'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-03 19:13:18
