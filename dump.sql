/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.8.2-MariaDB, for osx10.20 (arm64)
--
-- Host: localhost    Database: clubano_dev
-- ------------------------------------------------------
-- Server version	11.8.2-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `accounts`
--

DROP TABLE IF EXISTS `accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` char(36) NOT NULL,
  `name` varchar(255) NOT NULL,
  `number` varchar(255) DEFAULT NULL,
  `type` enum('bank','kasse','einnahme','ausgabe') NOT NULL DEFAULT 'kasse',
  `online` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `accounts_tenant_id_index` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accounts`
--

LOCK TABLES `accounts` WRITE;
/*!40000 ALTER TABLE `accounts` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `accounts` VALUES
(1,'1','Kasse','1000','kasse',0,'2025-06-13 06:01:42','2025-06-13 06:01:42'),
(2,'1','Bank','1200','bank',0,'2025-06-13 06:01:42','2025-06-13 06:01:42'),
(3,'1','Mitgliedsbeiträge','8400','einnahme',0,'2025-06-13 06:01:42','2025-06-13 06:01:42'),
(4,'1','Portokasse','1001','kasse',0,'2025-06-13 06:01:42','2025-06-13 06:01:42'),
(5,'1','Büromaterial','6600','ausgabe',0,'2025-06-13 06:01:42','2025-06-13 06:01:42'),
(6,'9','Kasse','1000','kasse',0,'2025-06-13 09:42:32','2025-06-13 09:42:32'),
(7,'9','Bank','1200','bank',0,'2025-06-13 09:42:49','2025-06-13 09:42:49'),
(8,'9','Echte Mitgliedsbeiträge','4000','einnahme',0,'2025-06-13 09:44:48','2025-06-13 09:44:48'),
(9,'9','Wareneinkauf','5200','ausgabe',0,'2025-06-13 09:46:16','2025-06-13 09:46:16'),
(10,'9','Porto','7200','ausgabe',0,'2025-06-16 11:02:18','2025-06-16 11:02:18');
/*!40000 ALTER TABLE `accounts` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `cache` VALUES
('laravel_cache_maiktowet@web.de|192.168.178.189','i:1;',1749823389),
('laravel_cache_maiktowet@web.de|192.168.178.189:timer','i:1749823389;',1749823389);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `custom_member_fields`
--

DROP TABLE IF EXISTS `custom_member_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `custom_member_fields` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `slug` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `required` tinyint(1) NOT NULL DEFAULT 0,
  `visible` tinyint(1) NOT NULL DEFAULT 1,
  `order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `custom_member_fields_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `custom_member_fields_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `custom_member_fields`
--

LOCK TABLES `custom_member_fields` WRITE;
/*!40000 ALTER TABLE `custom_member_fields` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `custom_member_fields` VALUES
(1,9,'Email','Zusätzliche Email','email','email',NULL,0,1,1,'2025-06-17 07:21:09','2025-06-17 09:04:05'),
(2,9,'Test','Test','test','text',NULL,0,1,2,'2025-06-17 09:02:45','2025-06-17 09:04:12');
/*!40000 ALTER TABLE `custom_member_fields` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `custom_member_values`
--

DROP TABLE IF EXISTS `custom_member_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `custom_member_values` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `member_id` bigint(20) unsigned NOT NULL,
  `custom_member_field_id` bigint(20) unsigned NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `custom_member_values_member_id_foreign` (`member_id`),
  KEY `custom_member_values_custom_member_field_id_foreign` (`custom_member_field_id`),
  CONSTRAINT `custom_member_values_custom_member_field_id_foreign` FOREIGN KEY (`custom_member_field_id`) REFERENCES `custom_member_fields` (`id`) ON DELETE CASCADE,
  CONSTRAINT `custom_member_values_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `custom_member_values`
--

LOCK TABLES `custom_member_values` WRITE;
/*!40000 ALTER TABLE `custom_member_values` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `custom_member_values` VALUES
(1,1,1,'maiktowet@web.de','2025-06-17 09:04:32','2025-06-17 09:04:32'),
(2,1,2,'test','2025-06-17 09:04:32','2025-06-17 09:04:32'),
(3,11,1,NULL,'2025-06-17 10:29:49','2025-06-17 10:29:49'),
(4,11,2,NULL,'2025-06-17 10:29:49','2025-06-17 10:29:49'),
(5,7,1,NULL,'2025-06-18 05:52:08','2025-06-18 05:52:08'),
(6,7,2,NULL,'2025-06-18 05:52:08','2025-06-18 05:52:08');
/*!40000 ALTER TABLE `custom_member_values` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `events`
--

DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `start` datetime NOT NULL,
  `end` datetime NOT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `events`
--

LOCK TABLES `events` WRITE;
/*!40000 ALTER TABLE `events` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `events` VALUES
(1,9,'test','test','test','2025-06-16 16:43:00','2025-06-17 12:48:00',1,'2025-06-16 08:43:41','2025-06-16 08:43:41'),
(2,9,'test','test','Sarstedt','2025-06-17 09:58:00','2025-06-17 14:58:00',1,'2025-06-17 05:58:20','2025-06-17 05:58:20'),
(3,9,'test intern','ufkfkz','gluguh','2025-06-18 10:32:00','2025-06-18 16:32:00',0,'2025-06-17 06:32:44','2025-06-17 06:32:44');
/*!40000 ALTER TABLE `events` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `members`
--

DROP TABLE IF EXISTS `members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `gender` enum('weiblich','männlich','divers') DEFAULT NULL,
  `salutation` enum('Frau','Herr','Liebe','Lieber','Hallo') DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `organization` varchar(255) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `member_id` varchar(255) DEFAULT NULL,
  `entry_date` date DEFAULT NULL,
  `exit_date` date DEFAULT NULL,
  `termination_date` date DEFAULT NULL,
  `cancellation_date` date DEFAULT NULL,
  `membership_id` bigint(20) unsigned DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `mobile` varchar(255) DEFAULT NULL,
  `landline` varchar(255) DEFAULT NULL,
  `street` varchar(255) DEFAULT NULL,
  `address_addition` varchar(255) DEFAULT NULL,
  `address_extra` varchar(255) DEFAULT NULL,
  `zip` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `country` varchar(255) NOT NULL DEFAULT 'Deutschland',
  `care_of` varchar(255) DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `members_membership_id_foreign` (`membership_id`),
  KEY `members_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `members_membership_id_foreign` FOREIGN KEY (`membership_id`) REFERENCES `memberships` (`id`) ON DELETE SET NULL,
  CONSTRAINT `members_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `members`
--

LOCK TABLES `members` WRITE;
/*!40000 ALTER TABLE `members` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `members` VALUES
(1,9,NULL,NULL,NULL,'Stefan','Böker',NULL,NULL,'photos/ITuJx7iBUYAi2eW5l2LOiphxLteiae3iq0JqiSmV.jpg',NULL,NULL,NULL,NULL,NULL,1,'stefanboeker1971@gmail.com',NULL,NULL,NULL,NULL,NULL,'31157','Sarstedt','AF',NULL,NULL,'2025-06-13 09:29:29','2025-06-17 09:04:32'),
(2,9,NULL,'Herr',NULL,'Peter','Borgaes',NULL,NULL,'photos/haGqCNFuwQZi5rOZOlzj8LITMadQhfYonpUDatm0.png',NULL,NULL,NULL,NULL,NULL,2,'p.borgaes@ff-sarstedt.de','01786569100',NULL,'Starenweg 1',NULL,NULL,'31157','Sarstedt','AF',NULL,NULL,'2025-06-13 09:29:29','2025-06-14 09:36:11'),
(3,9,NULL,NULL,NULL,'Oliver','Brandt',NULL,NULL,'photos/uMg0JuIf9bH39NSPLlLCurU5T7DQtxZCbzO6RzAz.jpg',NULL,'2025-06-02',NULL,NULL,NULL,NULL,'mail@ollikom.de','016096272727',NULL,'Paul-Gerhardt-Straße 1',NULL,NULL,'31157','Sarstedt','AF',NULL,NULL,'2025-06-13 09:29:29','2025-06-16 07:51:27'),
(4,9,NULL,NULL,NULL,'Christel','Brede',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'christel.brede@gmx.de','0171-9263897',NULL,'Wellweg 42',NULL,NULL,'31157','Sarstedt','DE',NULL,NULL,'2025-06-13 09:29:29','2025-06-16 08:01:26'),
(5,9,NULL,NULL,NULL,'Peter','Brede',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'brede@gmx.de','015755902312',NULL,'Wellweg 42','test',NULL,'31157','Sarstedt','DE',NULL,NULL,'2025-06-13 09:29:29','2025-06-16 07:54:02'),
(6,9,NULL,'Liebe',NULL,'Heike','Brennecke',NULL,'1961-02-13',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'heike.brennecke@me.com','015202444454',NULL,'Am Bruchgraben 19',NULL,NULL,'31157','Sarstedt','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(7,9,'männlich',NULL,NULL,'Andreas','Bresien',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'bresiens.fleissigehaendchen@gmail.com','015157671437',NULL,'In den Gehlen 5',NULL,NULL,'31157','Sarstedt','AF',NULL,NULL,'2025-06-13 09:29:29','2025-06-18 05:52:08'),
(8,9,NULL,'Lieber',NULL,'Marc','Busch',NULL,'1970-04-11',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'marcbusch1970@gmail.com','01624320126',NULL,'Breite Straße 7',NULL,NULL,'31157','Sarstedt','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(9,9,NULL,'Lieber',NULL,'Karl-Heinz','Deppe',NULL,'1965-05-16',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'khdtransporte@freenet.de','+4901725118095',NULL,'',NULL,NULL,'31157','Sarstedt','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(10,9,'männlich','Lieber',NULL,'Ruben','Ewert',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'rubenewert@gmx.de','017622049887',NULL,'Hildesheimer Str. 31',NULL,NULL,'31180','Giesen','DE',NULL,NULL,'2025-06-13 09:29:29','2025-06-17 05:29:23'),
(11,9,NULL,NULL,NULL,'Angelika','Gelo',NULL,NULL,NULL,NULL,NULL,'2025-06-20',NULL,NULL,1,'info@restaurant-dalmatia.com','017647359197',NULL,'Holztorstr 37',NULL,NULL,'31157','Sarstedt','AF',NULL,NULL,'2025-06-13 09:29:29','2025-06-17 10:29:49'),
(12,9,NULL,'Lieber',NULL,'Heiko','Jacob',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'jacob-sarstedt@trinkgut.de','01714894788',NULL,'Moorberg 3',NULL,NULL,'31157','Sarstedt','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(13,9,NULL,'Lieber',NULL,'Dennis','Gnauck',NULL,'1995-07-09',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'dennis.gnauck@web.de','01756773084',NULL,'Schmiedestraße 34',NULL,NULL,'30952','Ronnenberg','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(14,9,NULL,'Lieber',NULL,'Norman','Gries',NULL,'1964-06-10',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'info@neg-media.de','+4901608077376',NULL,'',NULL,NULL,'31157','Sarstedt','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(15,9,NULL,'Lieber',NULL,'Joscha','Gruber',NULL,'1976-09-13',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'j.gruber75@gmx.de','01757259374',NULL,'Flachsrotten 17A',NULL,NULL,'31171','Nordstemmen','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(16,9,NULL,'Lieber',NULL,'Nick','Hess',NULL,'2000-07-13',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'nick2000hess@gmail.com','017661746740',NULL,'Triftäckerstraße 13',NULL,NULL,'31135','Hildesheim','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(17,9,NULL,'Lieber',NULL,'Christian','Kasten',NULL,'1977-04-27',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'c.kasten@kasten-web.de','01758989863',NULL,'Weichsstraße 5',NULL,NULL,'31157','Sarstedt','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(18,9,NULL,'Lieber',NULL,'Olaf-Kurt','Kemsies',NULL,'1968-05-26',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'o.kemsies@gmx.de','+4901772539700',NULL,'',NULL,NULL,'31157','Sarstedt','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(19,9,NULL,'Lieber',NULL,'Thomas','Kollecker',NULL,'1969-07-21',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'s.kollecker@akl-sarstedt.de','+4901776080448',NULL,'',NULL,NULL,'31157','Sarstedt','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(20,9,NULL,'Lieber',NULL,'Holger','Kroh',NULL,'1965-03-18',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'kroh65@web.de','015775379356',NULL,'Deike-Busch-Str. 3',NULL,NULL,'31157','Sarstedt','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(21,9,NULL,'Lieber',NULL,'Thomas','Lippert',NULL,'1960-06-30',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'thomas.lippert60@web.de','+4901722084943',NULL,'',NULL,NULL,'31275','Lehrte','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(22,9,NULL,'Lieber',NULL,'Reinhard','Lysiak',NULL,'1949-10-09',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'dentalbrokerbrd@gmail.com','+49015758266206',NULL,'',NULL,NULL,'31157','Sarstedt','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(23,9,NULL,NULL,NULL,'Olaf','Malitte',NULL,'1963-11-29',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'','0175-4821767',NULL,'Mühlenstr. 58',NULL,NULL,'31157','Sarstedt','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(24,9,NULL,'Lieber',NULL,'Andreas Bernd','Matz',NULL,'1972-10-08',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'matzab@aol.com','01772676314',NULL,'Heimgartenstraße 2',NULL,NULL,'31157','Sarstedt','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(25,9,NULL,'Lieber',NULL,'Alexander','Meyer',NULL,'1969-06-08',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'alexkalle2001@gmail.com','+49017621516652',NULL,'',NULL,NULL,'31157','Sarstedt','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(26,9,NULL,'Lieber',NULL,'Stefan','Othmer',NULL,'1969-03-16',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'stefan.othmer@web.de','01631338222',NULL,'Mozartstraße 24',NULL,NULL,'31157','Sarstedt','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(27,9,NULL,'Lieber',NULL,'Dirk','Radecker',NULL,'1953-05-13',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'theonemanshowhd@web.de','+49017630583350',NULL,'',NULL,NULL,'31157','Sarstedt','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(28,9,NULL,'Lieber',NULL,'Marcus','Radecker',NULL,'1996-02-02',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'marcus-radecker@web.de','017682403710',NULL,'In den Stuken 3a',NULL,NULL,'30880','Laatzen','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(29,9,NULL,'Lieber',NULL,'Dietmar','Riedner',NULL,'1955-12-23',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'dietmarriedner@gmx.de','+49015730102272',NULL,'',NULL,NULL,'31157','Sarstedt','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(30,9,NULL,'Liebe',NULL,'Ines','Salewski',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'ineskonrad1807@gmail.com','+49017631787086',NULL,NULL,NULL,NULL,'31137','Hildesheim','AF',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 12:55:52'),
(31,9,NULL,'Lieber',NULL,'Kilian','Salewski',NULL,'1999-02-06',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'salewskikilian@googlemail.com','',NULL,'',NULL,NULL,'31137','Sarstedt','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(32,9,NULL,'Lieber',NULL,'Mathias','Schmidt',NULL,'1983-03-21',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'mschmidt2.1@web.de','01797902776',NULL,'Peiner Straße 27',NULL,NULL,'31137','Hildesheim','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(33,9,NULL,'Lieber',NULL,'Lutz','Schmiechen',NULL,'1956-06-10',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'lutz.schmiechen@gmx.de','017621953706',NULL,'Hindenburger Weg 3',NULL,NULL,'31157','Sarstedt','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(34,9,NULL,'Lieber',NULL,'Wolfgang','Scholz',NULL,'1964-04-14',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'entspannttauchen@web.de','01713166979',NULL,'Heiseder Straße 21',NULL,NULL,'31157','Sarstedt','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(35,9,NULL,'Lieber',NULL,'Rainer','Schumann',NULL,'1956-07-27',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'schumann07@web.de','',NULL,'Giesener Straße 22',NULL,NULL,'31157','Sarstedt','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(36,9,NULL,'Herr',NULL,'Stefan','Senft',NULL,'1970-08-22',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'stefansenft@web.de','0177-5262030',NULL,'Ahornweg 7',NULL,NULL,'31157','Sarstedt','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(37,9,NULL,'Lieber',NULL,'Andrej','Strakhov',NULL,'1978-11-17',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'andrej.strakhov@web.de','01719588247',NULL,'Voss-Straße 37',NULL,NULL,'31157','Sarstedt','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(38,9,NULL,'Liebe',NULL,'Dagmar','Stümpel',NULL,'1954-07-30',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'','+49017663143843',NULL,'Kleine Straße 7',NULL,NULL,'31180','Giesen','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(39,9,NULL,'Lieber',NULL,'Roland','Stümpel',NULL,'1964-03-28',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'rolandstuempel@gmx.de','+4901799146125',NULL,'Kleine Straße 7',NULL,NULL,'31180','Giesen / Hasede','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(40,9,NULL,'Lieber',NULL,'Michael','Thomsen',NULL,'1959-05-28',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'thomsen@thomsenundpartner.de','01714586888',NULL,'Altes Dorf 5',NULL,NULL,'31157','Sarstedt','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(41,9,NULL,'Liebe',NULL,'Diana','Towet',NULL,'1972-12-10',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'dianatowet@web.de','',NULL,'Voss-Straße 35B',NULL,NULL,'31157','Sarstedt','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(42,9,NULL,'Lieber',NULL,'Maik-Oliver','Towet',NULL,'1975-01-31',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'info@sarstedter-bier.de','+49017677649752',NULL,'Voss-Straße 35B',NULL,NULL,'31157','Deutschland','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(43,9,NULL,'Lieber',NULL,'Manfred','Waltke',NULL,'1955-04-02',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'manfred.waltke@gmail.com','',NULL,'Weberstr. 15b',NULL,NULL,'31157','Sarstedt','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(44,9,NULL,'Lieber',NULL,'Gerhard','Weber-Walleck',NULL,'1955-10-02',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'mfgwewa@freenet.de','',NULL,'',NULL,NULL,'31157','Sarstedt','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(45,9,NULL,'Lieber',NULL,'Christian','Zidek',NULL,'1958-12-22',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'czidek@outlook.de','',NULL,'Holztorstraße 22',NULL,NULL,'31157','Sarstedt','Deutschland',NULL,NULL,'2025-06-13 09:29:29','2025-06-13 09:29:29'),
(46,9,'weiblich','Frau',NULL,'test','tester',NULL,'1979-10-17','photos/yesGg3USxvdnHS5gW0qA7iURSYYL1wGyT9E8Jwcu.png',NULL,'2025-06-16',NULL,NULL,NULL,1,'maiktowet@web.de','0176 77649752',NULL,'Robert-Koch-Straße','27',NULL,'30853','Langenhagen','AF',NULL,NULL,'2025-06-16 08:04:17','2025-06-16 08:04:17');
/*!40000 ALTER TABLE `members` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `memberships`
--

DROP TABLE IF EXISTS `memberships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `memberships` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `fee` decimal(8,2) DEFAULT NULL,
  `billing_cycle` enum('monatlich','quartalsweise','halbjährlich','jährlich') NOT NULL DEFAULT 'jährlich',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `memberships_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `memberships_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `memberships`
--

LOCK TABLES `memberships` WRITE;
/*!40000 ALTER TABLE `memberships` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `memberships` VALUES
(1,9,'Aktiv',60.00,'jährlich','2025-06-13 09:41:36','2025-06-13 09:41:36'),
(2,9,'fördernd',30.00,'jährlich','2025-06-13 09:41:53','2025-06-13 09:41:53'),
(3,9,'Test',5.00,'jährlich','2025-06-13 15:28:21','2025-06-13 15:28:21');
/*!40000 ALTER TABLE `memberships` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `migrations` VALUES
(1,'0001_01_01_000000_create_users_table',1),
(2,'0001_01_01_000001_create_cache_table',1),
(3,'0001_01_01_000002_create_jobs_table',1),
(4,'2025_06_09_071310_create_tenants_table',1),
(5,'2025_06_09_080059_add_tenant_id_to_users_table',1),
(6,'2025_06_09_100012_add_fields_to_tenants_table',1),
(7,'2025_06_09_104924_create_memberships_table',1),
(8,'2025_06_09_104925_create_members_table',1),
(9,'2025_06_10_093036_create_events_table',1),
(10,'2025_06_11_153000_add_termination_date_to_members_table',1),
(11,'2025_06_12_062025_add_photo_to_members_table',1),
(12,'2025_06_12_063715_add_photo_and_address_addition_to_members_table',1),
(13,'2025_06_12_093840_create_accounts_table',1),
(14,'2025_06_12_112228_create_transactions_table',1),
(15,'2025_06_12_125523_add_account_fields_to_transactions_table',1),
(16,'2025_06_12_130146_remove_account_id_from_transactions_table',1),
(17,'2025_06_13_075606_add_two_factor_columns_to_users_table',1),
(18,'2025_06_13_075616_create_personal_access_tokens_table',1),
(19,'2025_06_17_085118_create_custom_member_fields_table',2),
(20,'2025_06_17_095003_add_custom_fields_to_members_table',3),
(21,'2025_06_17_101054_add_visible_to_custom_member_fields_table',4),
(22,'2025_06_17_102708_create_custom_member_values_table',5),
(23,'2025_06_18_061142_add_receipt_number_to_transactions_table',6),
(24,'2025_06_18_062205_add_receipt_file_to_transactions_table',7);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `sessions` VALUES
('gWqaSprYgDCRhcqiBrNWEQYoUufJrP51b0riuSlL',1,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36','YTo1OntzOjY6Il90b2tlbiI7czo0MDoiUHlmUkk1cVNKUzZLaHBGeG01UDI4bHczRjJLZlluTjE3dXRFMzQ0cCI7czozOiJ1cmwiO2E6MDp7fXM6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjI5OiJodHRwOi8vbG9jYWxob3N0OjgwMDAvbWVtYmVycyI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjE7fQ==',1750251615),
('luykV06JF0yma4mnjC0xwb9MpqyEmyK4lBovZZ0l',NULL,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Safari/605.1.15','YTo0OntzOjY6Il90b2tlbiI7czo0MDoiZmZya2s1VnBGSkNQblprWXpvbkVQeFFMM2M4MlRzY1hCd0JHZ3hMMyI7czozOiJ1cmwiO2E6MTp7czo4OiJpbnRlbmRlZCI7czozNDoiaHR0cDovL2xvY2FsaG9zdDo4MDAwL3RyYW5zYWN0aW9ucyI7fXM6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjI3OiJodHRwOi8vbG9jYWxob3N0OjgwMDAvbG9naW4iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1750251667);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `tenants`
--

DROP TABLE IF EXISTS `tenants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `zip` varchar(10) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `register_number` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenants_slug_unique` (`slug`),
  UNIQUE KEY `tenants_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenants`
--

LOCK TABLES `tenants` WRITE;
/*!40000 ALTER TABLE `tenants` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `tenants` VALUES
(1,'Sarstedter Bierfreunde e.V.','sarstedter-bierfreunde-ev','maiktowet@web.de',NULL,NULL,NULL,NULL,NULL,NULL,'2025-06-13 07:16:45','2025-06-13 07:16:45'),
(3,'testverein','testverein','olli@olli.de',NULL,NULL,NULL,NULL,NULL,NULL,'2025-06-13 07:23:24','2025-06-13 07:23:24'),
(6,'Kasse','kasse','ttt@ttt.de',NULL,NULL,NULL,NULL,NULL,NULL,'2025-06-13 07:53:20','2025-06-13 07:53:20'),
(7,'fff','fff','fff@fff.de',NULL,NULL,NULL,NULL,NULL,NULL,'2025-06-13 08:02:29','2025-06-13 08:02:29'),
(8,'aaa','aaa','aaa@aaa.de',NULL,NULL,NULL,NULL,NULL,NULL,'2025-06-13 08:05:25','2025-06-13 08:05:25'),
(9,'bb','bb','bb@bb.de','logos/WRw9SSuUFwxcr80Z4cCV4AqKOFXhtytk10vJw9nQ.jpg','ggg',NULL,NULL,NULL,NULL,'2025-06-13 08:14:01','2025-06-16 10:49:04');
/*!40000 ALTER TABLE `tenants` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` char(36) NOT NULL,
  `date` date NOT NULL,
  `description` varchar(255) NOT NULL,
  `receipt_number` varchar(255) DEFAULT NULL,
  `receipt_file` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `account_from_id` bigint(20) unsigned NOT NULL,
  `account_to_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transactions_receipt_number_unique` (`receipt_number`),
  KEY `transactions_tenant_id_index` (`tenant_id`),
  KEY `transactions_account_from_id_foreign` (`account_from_id`),
  KEY `transactions_account_to_id_foreign` (`account_to_id`),
  CONSTRAINT `transactions_account_from_id_foreign` FOREIGN KEY (`account_from_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `transactions_account_to_id_foreign` FOREIGN KEY (`account_to_id`) REFERENCES `accounts` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transactions`
--

LOCK TABLES `transactions` WRITE;
/*!40000 ALTER TABLE `transactions` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `transactions` VALUES
(1,'9','2025-06-13','Testbuchung',NULL,NULL,10.00,'2025-06-13 09:46:34','2025-06-13 09:46:34',6,9),
(2,'9','2025-06-13','Towet',NULL,NULL,120.00,'2025-06-13 09:46:52','2025-06-13 09:46:52',8,7),
(3,'9','2025-06-13','Test',NULL,NULL,10000.00,'2025-06-13 10:23:18','2025-06-13 10:23:18',8,7),
(4,'9','2025-05-13','test',NULL,NULL,500.00,'2025-06-13 12:00:58','2025-06-13 12:00:58',7,9),
(5,'9','2025-06-16','tetetete',NULL,NULL,2000.00,'2025-06-16 10:40:13','2025-06-16 10:40:13',6,9),
(6,'9','2025-06-16','aus',NULL,NULL,100.00,'2025-06-16 11:01:08','2025-06-16 11:01:08',7,9),
(7,'9','2025-06-16','lwuerghr',NULL,NULL,100.00,'2025-06-16 11:02:45','2025-06-16 11:02:45',7,10),
(8,'9','2025-06-16','lsughseughrgiö',NULL,NULL,45.00,'2025-06-16 11:24:02','2025-06-16 11:24:02',6,10),
(9,'9','2025-06-17','Storno: tetetete – Grund: Test',NULL,NULL,2000.00,'2025-06-17 12:26:42','2025-06-17 12:26:42',9,6),
(10,'9','2025-06-17','Storno: Test – Grund: test2',NULL,NULL,10000.00,'2025-06-17 12:28:59','2025-06-17 12:28:59',7,8),
(11,'9','2025-06-18','Towet',NULL,NULL,10.00,'2025-06-18 04:09:54','2025-06-18 04:09:54',6,9),
(12,'9','2025-06-18','aus','TRX-2025-0012','1750227778_Einladung_Blanko.pdf',25.00,'2025-06-18 04:22:58','2025-06-18 04:22:58',6,9),
(13,'9','2025-06-18','Mit beleg','TRX-2025-0013','1750228247_Datenauskunft_Böker_1.pdf',100.00,'2025-06-18 04:30:47','2025-06-18 04:30:47',7,9),
(14,'9','2025-06-18','Towet','TRX-2025-0014','1750228335_Ruv Auszahlung .pdf',250.00,'2025-06-18 04:32:15','2025-06-18 04:32:15',6,9),
(15,'9','2025-06-18','Testbuchung','TRX-2025-0015','1750229097_Angebot_A-20250617005_Friseurinnung_Hannover_2025-06-17.pdf',5.00,'2025-06-18 04:44:57','2025-06-18 04:44:57',7,10),
(16,'9','2025-06-18','Towet','TRX-2025-0016','1750229398_PersoTowet.pdf',1000.00,'2025-06-18 04:49:58','2025-06-18 04:49:58',7,8),
(17,'9','2025-06-18','12234567','TRX-2025-0017','1750232278_Briefmarken.1Stk.06.06.2025_0742.pdf',5.00,'2025-06-18 05:37:58','2025-06-18 05:37:58',6,10),
(18,'9','2025-06-18','sdkjvsflkjvfjk','TRX-2025-0018','1750232712_GHGKopf-2.pdf',1.00,'2025-06-18 05:45:12','2025-06-18 05:45:12',6,10);
/*!40000 ALTER TABLE `transactions` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `two_factor_secret` text DEFAULT NULL,
  `two_factor_recovery_codes` text DEFAULT NULL,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `current_team_id` bigint(20) unsigned DEFAULT NULL,
  `profile_photo_path` varchar(2048) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `users_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `users` VALUES
(1,9,'bb','bb@bb.de',NULL,'$2y$12$Q68qyhIA/dIzAgjiW8xKmO4nHr.6UU2NXN3k34M9D5fJAwuOhQIpS',NULL,NULL,NULL,NULL,NULL,NULL,'2025-06-13 08:14:01','2025-06-13 08:14:01');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
commit;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2025-06-18 15:11:42
