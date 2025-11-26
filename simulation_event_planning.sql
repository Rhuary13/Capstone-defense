-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 26, 2025 at 04:05 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `simulation_event_planning`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `ensure_indexes_and_fks` ()   BEGIN
  DECLARE cnt INT DEFAULT 0;
  DECLARE sql_text TEXT;

  -- attendance: index participant_id
  SELECT COUNT(*) INTO cnt FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendance' AND INDEX_NAME = 'idx_attendance_participant';
  IF cnt = 0 THEN
    SET sql_text = 'CREATE INDEX idx_attendance_participant ON `attendance` (`participant_id`);';
    PREPARE s FROM sql_text; EXECUTE s; DEALLOCATE PREPARE s;
    SELECT 'CREATED INDEX idx_attendance_participant ON attendance(participant_id)' AS info;
  ELSE
    SELECT 'SKIP index idx_attendance_participant (exists)' AS info;
  END IF;

  -- attendance: index event_id
  SELECT COUNT(*) INTO cnt FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendance' AND INDEX_NAME = 'idx_attendance_event';
  IF cnt = 0 THEN
    SET sql_text = 'CREATE INDEX idx_attendance_event ON `attendance` (`event_id`);';
    PREPARE s FROM sql_text; EXECUTE s; DEALLOCATE PREPARE s;
    SELECT 'CREATED INDEX idx_attendance_event ON attendance(event_id)' AS info;
  ELSE
    SELECT 'SKIP index idx_attendance_event (exists)' AS info;
  END IF;

  -- events: index date (helpful)
  SELECT COUNT(*) INTO cnt FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND INDEX_NAME = 'idx_events_date';
  IF cnt = 0 THEN
    SET sql_text = 'CREATE INDEX idx_events_date ON `events` (`date`);';
    PREPARE s FROM sql_text; EXECUTE s; DEALLOCATE PREPARE s;
    SELECT 'CREATED INDEX idx_events_date ON events(date)' AS info;
  ELSE
    SELECT 'SKIP index idx_events_date (exists)' AS info;
  END IF;

  -- 5) Add foreign key attendance.event_id -> events.id if missing
  SELECT COUNT(*) INTO cnt
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendance' AND COLUMN_NAME = 'event_id' AND REFERENCED_TABLE_NAME = 'events';
  IF cnt = 0 THEN
    -- use a safe name and attempt to add; if fails, print warning but continue
    SET @fk_sql = 'ALTER TABLE `attendance` ADD CONSTRAINT fk_attendance_event FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;';
    BEGIN
      DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
      BEGIN
        SELECT 'WARN: could not add FK fk_attendance_event — check types or existing constraints' AS info;
      END;
      PREPARE s FROM @fk_sql; EXECUTE s; DEALLOCATE PREPARE s;
      SELECT 'ALTERED: fk_attendance_event added (attendance.event_id -> events.id)' AS info;
    END;
  ELSE
    SELECT 'SKIP: Foreign key attendance.event_id -> events.id already present' AS info;
  END IF;

  -- 6) Add foreign key attendance.participant_id -> participants.id (if participants table exists)
  SELECT COUNT(*) INTO cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'participants';
  IF cnt > 0 THEN
    SELECT COUNT(*) INTO cnt FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendance' AND COLUMN_NAME = 'participant_id' AND REFERENCED_TABLE_NAME = 'participants';
    IF cnt = 0 THEN
      SET @fk2 = 'ALTER TABLE `attendance` ADD CONSTRAINT fk_attendance_participant FOREIGN KEY (`participant_id`) REFERENCES `participants`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;';
      BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
        BEGIN
          SELECT 'WARN: could not add FK fk_attendance_participant — check parent participants table or types' AS info;
        END;
        PREPARE s FROM @fk2; EXECUTE s; DEALLOCATE PREPARE s;
        SELECT 'ALTERED: fk_attendance_participant added (attendance.participant_id -> participants.id)' AS info;
      END;
    ELSE
      SELECT 'SKIP: FK attendance.participant_id -> participants.id already present' AS info;
    END IF;
  ELSE
    SELECT 'SKIP: participants table not present — cannot add FK attendance.participant_id' AS info;
  END IF;

END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `audience` enum('General','Staff','User') NOT NULL DEFAULT 'General',
  `event_date` date NOT NULL,
  `time` time NOT NULL,
  `location` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `location_lat` decimal(10,7) DEFAULT NULL,
  `location_lng` decimal(10,7) DEFAULT NULL,
  `compliance_approved` tinyint(1) DEFAULT 0,
  `safety_procedure_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `participant_name` varchar(255) NOT NULL,
  `module` varchar(255) NOT NULL,
  `status` enum('Present','Absent') NOT NULL DEFAULT 'Absent',
  `date` date NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `user_type` enum('participant','staff') NOT NULL DEFAULT 'participant',
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `event_id` int(11) NOT NULL,
  `checked_at` timestamp NULL DEFAULT NULL,
  `participant_id` int(11) DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `attended_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_participants`
--

CREATE TABLE `attendance_participants` (
  `id` int(11) NOT NULL,
  `participant_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `status` enum('Present','Absent') NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_staff`
--

CREATE TABLE `attendance_staff` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `status` enum('Present','Absent') NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `barangay_locations`
--

CREATE TABLE `barangay_locations` (
  `id` int(11) NOT NULL,
  `district` varchar(50) NOT NULL,
  `barangay_name` varchar(150) NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `boundary_geojson` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barangay_locations`
--

INSERT INTO `barangay_locations` (`id`, `district`, `barangay_name`, `latitude`, `longitude`, `boundary_geojson`, `created_at`, `updated_at`) VALUES
(1, 'District 1', 'Alicia', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(2, 'District 1', 'Bagong Pag-asa', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(3, 'District 1', 'Bahay Toro', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(4, 'District 1', 'Balingasa', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(5, 'District 1', 'Bungad', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(6, 'District 1', 'Damar', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(7, 'District 1', 'Damayan', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(8, 'District 1', 'Del Monte', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(9, 'District 1', 'Katipunan', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(10, 'District 1', 'Lourdes', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(11, 'District 1', 'Maharlika', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(12, 'District 1', 'Manresa', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(13, 'District 1', 'Mariblo', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(14, 'District 1', 'Masambong', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(15, 'District 1', 'N. S. Amoranto (Gintong Silahis)', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(16, 'District 1', 'Nayong Kanluran', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(17, 'District 1', 'Paang Bundok', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(18, 'District 1', 'Pag-ibig sa Nayon', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(19, 'District 1', 'Paltok', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(20, 'District 1', 'Paraiso', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(21, 'District 1', 'Phil-Am', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(22, 'District 1', 'Project 6', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(23, 'District 1', 'Ramon Magsaysay', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(24, 'District 1', 'Saint Peter', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(25, 'District 1', 'Salvacion', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(26, 'District 1', 'San Antonio', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(27, 'District 1', 'San Isidro Labrador', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(28, 'District 1', 'San Jose', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(29, 'District 1', 'Santa Cruz', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(30, 'District 1', 'Santa Teresita', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(31, 'District 1', 'Sto. Cristo', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(32, 'District 1', 'Santo Domingo (Matalahib)', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(33, 'District 1', 'Siena', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(34, 'District 1', 'Talayan', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(35, 'District 1', 'Vasra', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(36, 'District 1', 'Veterans Village', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(37, 'District 1', 'West Triangle', NULL, NULL, NULL, '2025-11-25 15:50:19', '2025-11-25 15:50:19'),
(38, 'District 2', 'Bagong Silangan', NULL, NULL, NULL, '2025-11-25 15:50:26', '2025-11-25 15:50:26'),
(39, 'District 2', 'Batasan Hills', NULL, NULL, NULL, '2025-11-25 15:50:26', '2025-11-25 15:50:26'),
(40, 'District 2', 'Commonwealth', NULL, NULL, NULL, '2025-11-25 15:50:26', '2025-11-25 15:50:26'),
(41, 'District 2', 'Holy Spirit', NULL, NULL, NULL, '2025-11-25 15:50:26', '2025-11-25 15:50:26'),
(42, 'District 2', 'Payatas', NULL, NULL, NULL, '2025-11-25 15:50:26', '2025-11-25 15:50:26'),
(43, 'District 3', 'Amihan', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(44, 'District 3', 'Bagumbayan (Eastwood)', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(45, 'District 3', 'Bagumbuhay', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(46, 'District 3', 'Bayanihan', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(47, 'District 3', 'Blue Ridge A', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(48, 'District 3', 'Blue Ridge B', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(49, 'District 3', 'Camp Aguinaldo', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(50, 'District 3', 'Claro (Quirino 3-B)', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(51, 'District 3', 'Dioquino Zobel', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(52, 'District 3', 'Duyan-duyan', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(53, 'District 3', 'E. Rodriguez', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(54, 'District 3', 'East Kamias', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(55, 'District 3', 'Escopa I', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(56, 'District 3', 'Escopa II', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(57, 'District 3', 'Escopa III', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(58, 'District 3', 'Escopa IV', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(59, 'District 3', 'Libis', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(60, 'District 3', 'Loyola Heights', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(61, 'District 3', 'Mangga', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(62, 'District 3', 'Marilag', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(63, 'District 3', 'Masagana', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(64, 'District 3', 'Matandang Balara (Old Balara)', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(65, 'District 3', 'Milagrosa', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(66, 'District 3', 'Pansol', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(67, 'District 3', 'Quirino 2-A', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(68, 'District 3', 'Quirino 2-B', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(69, 'District 3', 'Quirino 2-C', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(70, 'District 3', 'Quirino 3-A', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(71, 'District 3', 'St. Ignatius', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(72, 'District 3', 'San Roque', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(73, 'District 3', 'Silangan', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(74, 'District 3', 'Socorro (Araneta City)', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(75, 'District 3', 'Tagumpay', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(76, 'District 3', 'Ugong Norte', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(77, 'District 3', 'Villa Maria Clara', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(78, 'District 3', 'West Kamias', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(79, 'District 3', 'White Plains', NULL, NULL, NULL, '2025-11-25 15:50:33', '2025-11-25 15:50:33'),
(80, 'District 4', 'Apolonio Samson', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(81, 'District 4', 'Botocan', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(82, 'District 4', 'Central', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(83, 'District 4', 'Dioquino Zobel', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(84, 'District 4', 'Don Manuel', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(85, 'District 4', 'Doña Aurora', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(86, 'District 4', 'Doña Imelda', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(87, 'District 4', 'Doña Josefa', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(88, 'District 4', 'Damayang Lagi', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(89, 'District 4', 'Horseshoe', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(90, 'District 4', 'Immaculate Concepcion', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(91, 'District 4', 'Kalusugan', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(92, 'District 4', 'Kamuning', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(93, 'District 4', 'Kaunlaran', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(94, 'District 4', 'Kristong Hari', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(95, 'District 4', 'Krus na Ligas', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(96, 'District 4', 'Laging Handa', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(97, 'District 4', 'Malaya', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(98, 'District 4', 'Mariana', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(99, 'District 4', 'Obrero', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(100, 'District 4', 'Old Capitol Site', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(101, 'District 4', 'Paligsahan', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(102, 'District 4', 'Pinagkaisahan', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(103, 'District 4', 'Pinyahan', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(104, 'District 4', 'Roxas District', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(105, 'District 4', 'Sacred Heart', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(106, 'District 4', 'San Isidro', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(107, 'District 4', 'San Martin de Porres', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(108, 'District 4', 'San Vicente', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(109, 'District 4', 'Sikatuna Village', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(110, 'District 4', 'Sto. Niño', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(111, 'District 4', 'Tatalon', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(112, 'District 4', 'Teachers Village East', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(113, 'District 4', 'Teachers Village West', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(114, 'District 4', 'U.P. Campus', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(115, 'District 4', 'U.P. Village', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(116, 'District 4', 'Valencia', NULL, NULL, NULL, '2025-11-25 15:50:40', '2025-11-25 15:50:40'),
(117, 'District 5', 'Bagbag', NULL, NULL, NULL, '2025-11-25 15:50:46', '2025-11-25 15:50:46'),
(118, 'District 5', 'Capri', NULL, NULL, NULL, '2025-11-25 15:50:46', '2025-11-25 15:50:46'),
(119, 'District 5', 'Fairview', NULL, NULL, NULL, '2025-11-25 15:50:46', '2025-11-25 15:50:46'),
(120, 'District 5', 'Gulod', NULL, NULL, NULL, '2025-11-25 15:50:46', '2025-11-25 15:50:46'),
(121, 'District 5', 'Greater Lagro', NULL, NULL, NULL, '2025-11-25 15:50:46', '2025-11-25 15:50:46'),
(122, 'District 5', 'Kaligayahan', NULL, NULL, NULL, '2025-11-25 15:50:46', '2025-11-25 15:50:46'),
(123, 'District 5', 'Nagkaisang Nayon', NULL, NULL, NULL, '2025-11-25 15:50:46', '2025-11-25 15:50:46'),
(124, 'District 5', 'North Fairview', NULL, NULL, NULL, '2025-11-25 15:50:46', '2025-11-25 15:50:46'),
(125, 'District 5', 'Novaliches Proper (Bayan)', NULL, NULL, NULL, '2025-11-25 15:50:46', '2025-11-25 15:50:46'),
(126, 'District 5', 'Pasong Putik Proper', NULL, NULL, NULL, '2025-11-25 15:50:46', '2025-11-25 15:50:46'),
(127, 'District 5', 'San Agustin', NULL, NULL, NULL, '2025-11-25 15:50:46', '2025-11-25 15:50:46'),
(128, 'District 5', 'San Bartolome', NULL, NULL, NULL, '2025-11-25 15:50:46', '2025-11-25 15:50:46'),
(129, 'District 5', 'Santa Lucia', NULL, NULL, NULL, '2025-11-25 15:50:46', '2025-11-25 15:50:46'),
(130, 'District 5', 'Santa Monica', NULL, NULL, NULL, '2025-11-25 15:50:46', '2025-11-25 15:50:46'),
(131, 'District 6', 'Baesa', NULL, NULL, NULL, '2025-11-25 15:50:55', '2025-11-25 15:50:55'),
(132, 'District 6', 'Balon-bato', NULL, NULL, NULL, '2025-11-25 15:50:55', '2025-11-25 15:50:55'),
(133, 'District 6', 'Culiat', NULL, NULL, NULL, '2025-11-25 15:50:55', '2025-11-25 15:50:55'),
(134, 'District 6', 'New Era', NULL, NULL, NULL, '2025-11-25 15:50:55', '2025-11-25 15:50:55'),
(135, 'District 6', 'Pasong Tamo', NULL, NULL, NULL, '2025-11-25 15:50:55', '2025-11-25 15:50:55'),
(136, 'District 6', 'Sangandaan', NULL, NULL, NULL, '2025-11-25 15:50:55', '2025-11-25 15:50:55'),
(137, 'District 6', 'Talipapa', NULL, NULL, NULL, '2025-11-25 15:50:55', '2025-11-25 15:50:55'),
(138, 'District 6', 'Tandang Sora', NULL, NULL, NULL, '2025-11-25 15:50:55', '2025-11-25 15:50:55'),
(139, 'District 6', 'Unang Sigaw', NULL, NULL, NULL, '2025-11-25 15:50:55', '2025-11-25 15:50:55');

-- --------------------------------------------------------

--
-- Table structure for table `certificates`
--

CREATE TABLE `certificates` (
  `id` int(11) NOT NULL,
  `cert_title` varchar(255) NOT NULL,
  `recipient` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `signatory1` varchar(255) NOT NULL,
  `signatory2` varchar(255) NOT NULL,
  `issue_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `uploaded_file` varchar(512) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `certificate_issuance`
--

CREATE TABLE `certificate_issuance` (
  `id` int(11) NOT NULL,
  `participant_name` varchar(255) NOT NULL,
  `certificate_title` varchar(255) NOT NULL,
  `issued_date` date DEFAULT NULL,
  `renewed_date` date DEFAULT NULL,
  `status` enum('Pending','Issued','Renewed') DEFAULT 'Pending',
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `certification_criteria`
--

CREATE TABLE `certification_criteria` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `min_score` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `criteria`
--

CREATE TABLE `criteria` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `disaster_type` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `criteria_scores`
--

CREATE TABLE `criteria_scores` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `participant_id` int(11) NOT NULL,
  `criteria_name` varchar(255) NOT NULL,
  `score` decimal(9,2) NOT NULL DEFAULT 0.00,
  `max_score` decimal(9,2) NOT NULL DEFAULT 0.00,
  `scored_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `damage_investigations`
--

CREATE TABLE `damage_investigations` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `investigation_notes` text DEFAULT NULL,
  `investigated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `damage_reports`
--

CREATE TABLE `damage_reports` (
  `id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `damage_type` varchar(100) DEFAULT NULL,
  `incident_date` datetime DEFAULT NULL,
  `description` text DEFAULT NULL,
  `staff_name` varchar(255) NOT NULL,
  `severity` enum('Low','Medium','High','Critical') DEFAULT 'Low',
  `reported_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Pending','Investigating','Closed') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `data_criteria`
--

CREATE TABLE `data_criteria` (
  `id` int(11) NOT NULL,
  `scenario` varchar(100) NOT NULL,
  `criterion` varchar(255) NOT NULL,
  `objective` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `data_entries`
--

CREATE TABLE `data_entries` (
  `id` int(11) NOT NULL,
  `participant_name` varchar(100) NOT NULL,
  `score` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `debriefings`
--

CREATE TABLE `debriefings` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `feedback_text` text DEFAULT NULL,
  `resources` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `debriefing_materials`
--

CREATE TABLE `debriefing_materials` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `type` varchar(100) NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `decisions`
--

CREATE TABLE `decisions` (
  `id` int(11) NOT NULL,
  `participant_id` int(11) NOT NULL,
  `inject_id` int(11) NOT NULL,
  `decision_text` text NOT NULL,
  `decided_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `equipment`
--

CREATE TABLE `equipment` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `available_qty` int(11) DEFAULT 1,
  `status` enum('Available','In Use','Damaged','Maintenance') DEFAULT 'Available',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `equipment_audits`
--

CREATE TABLE `equipment_audits` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `audit_reason` varchar(255) DEFAULT NULL,
  `audit_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Pending','In Progress','Completed') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `type` enum('Program','Training','Scenario-Based') NOT NULL,
  `disaster_type` varchar(255) DEFAULT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `duration` int(11) NOT NULL,
  `location` varchar(255) NOT NULL,
  `facilitator` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Pending','In Progress','Completed') DEFAULT 'Pending',
  `approval_status` enum('Pending','Approved') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL,
  `audience` varchar(100) DEFAULT 'General',
  `location_lat` decimal(10,7) DEFAULT NULL,
  `location_lng` decimal(10,7) DEFAULT NULL,
  `capacity` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `title`, `type`, `disaster_type`, `date`, `time`, `duration`, `location`, `facilitator`, `notes`, `status`, `approval_status`, `created_at`, `approved_at`, `audience`, `location_lat`, `location_lng`, `capacity`, `updated_at`, `description`, `event_date`, `start_time`, `end_time`) VALUES
(2, 'Earthquake Drills', 'Training', 'Earthquake', '2025-11-18', '15:00:00', 2, 'Brgy. Nagkaisang Nayon', 'Aaliyah Jash Nebab', 'Prepare for upcoming Earthquakes', 'Pending', '', '2025-11-18 01:45:41', NULL, 'General', NULL, NULL, 0, '2025-11-17 20:44:13', NULL, '0000-00-00', '00:00:00', '00:00:00'),
(3, 'Earthquake Drills', 'Training', 'Earthquake', '2025-11-18', '15:00:00', 2, 'Brgy. Nagkaisang Nayon', 'Aaliyah Jash Nebab', 'Prepare for upcoming Earthquakes', 'Pending', '', '2025-11-18 01:46:08', NULL, 'General', NULL, NULL, 0, '2025-11-17 20:44:15', NULL, '0000-00-00', '00:00:00', '00:00:00'),
(4, 'Earthquake Drills', 'Training', 'Earthquake', '2025-11-18', '15:00:00', 2, 'Brgy. Nagkaisang Nayon', 'Aaliyah Jash Nebab', 'Prepare for upcoming Earthquakes', 'Pending', 'Pending', '2025-11-18 01:46:18', NULL, 'General', NULL, NULL, 0, '2025-11-17 17:46:18', NULL, '0000-00-00', '00:00:00', '00:00:00'),
(5, 'Earthquake Drills', 'Training', 'Earthquake', '2025-11-18', '15:00:00', 2, 'Brgy. Nagkaisang Nayon', 'Aaliyah Jash Nebab', 'Prepare for upcoming Earthquakes', 'Pending', 'Pending', '2025-11-18 01:49:31', NULL, 'General', NULL, NULL, 0, '2025-11-17 17:49:31', NULL, '0000-00-00', '00:00:00', '00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `exercise_history`
--

CREATE TABLE `exercise_history` (
  `id` int(11) NOT NULL,
  `exercise_name` varchar(255) NOT NULL,
  `disaster_type` varchar(100) NOT NULL,
  `conducted_on` date NOT NULL,
  `participants_count` int(11) DEFAULT 0,
  `avg_score` decimal(5,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `participant_id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `participant_name` varchar(100) NOT NULL,
  `strengths` text NOT NULL,
  `weaknesses` text NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback_debrief`
--

CREATE TABLE `feedback_debrief` (
  `id` int(11) NOT NULL,
  `participant_id` int(11) NOT NULL,
  `reflection` text NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `final_scores`
--

CREATE TABLE `final_scores` (
  `id` int(11) NOT NULL,
  `participant_name` varchar(100) NOT NULL,
  `exercise_title` varchar(200) NOT NULL,
  `final_score` int(11) NOT NULL,
  `remarks` text DEFAULT NULL,
  `validated` enum('yes','no') DEFAULT 'yes',
  `finalized` enum('yes','no') DEFAULT 'yes',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gear_checkout`
--

CREATE TABLE `gear_checkout` (
  `id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `staff_name` varchar(255) DEFAULT NULL,
  `participant_name` varchar(255) DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Pending','Approved','Rejected','Returned','Damaged') DEFAULT 'Pending',
  `assigned_to` varchar(255) DEFAULT NULL,
  `role` enum('staff','participant') DEFAULT 'staff',
  `purpose` text DEFAULT NULL,
  `qty` int(11) DEFAULT 1,
  `approved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `history_records`
--

CREATE TABLE `history_records` (
  `id` int(11) NOT NULL,
  `participant_name` varchar(100) NOT NULL,
  `simulation_name` varchar(150) NOT NULL,
  `score` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `injects`
--

CREATE TABLE `injects` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `status` enum('pending','delivered') DEFAULT 'pending',
  `exercise_id` int(11) DEFAULT NULL,
  `decision_points` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`decision_points`)),
  `schedule_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`schedule_json`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `event_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `injects`
--

INSERT INTO `injects` (`id`, `title`, `description`, `status`, `exercise_id`, `decision_points`, `schedule_json`, `created_at`, `updated_at`, `event_id`) VALUES
(1, 'Power Outage Simulation', 'Simulate a city-wide power outage scenario.', 'pending', NULL, NULL, NULL, '2025-09-24 12:14:23', NULL, 0),
(2, 'Flood Response Drill', 'Coordinate flood response and evacuations.', 'pending', NULL, NULL, NULL, '2025-09-24 12:14:23', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `lat` decimal(10,6) NOT NULL,
  `lng` decimal(10,6) NOT NULL,
  `barangay` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `maintenance`
--

CREATE TABLE `maintenance` (
  `id` int(11) NOT NULL,
  `equipment` varchar(255) NOT NULL,
  `task_type` varchar(50) NOT NULL,
  `schedule_date` date NOT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_logs`
--

CREATE TABLE `maintenance_logs` (
  `id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `staff_name` varchar(255) NOT NULL,
  `activity` text NOT NULL,
  `status` enum('Completed','Ongoing','Needs Follow-up') DEFAULT 'Ongoing',
  `performed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `modules`
--

CREATE TABLE `modules` (
  `id` int(11) NOT NULL,
  `module_name` varchar(255) NOT NULL,
  `disaster_type` varchar(100) NOT NULL,
  `added_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `recipient_type` enum('all','staff','participant') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `target` enum('all','participants','staff') NOT NULL DEFAULT 'all',
  `location_lat` double DEFAULT NULL,
  `location_lng` double DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `participants`
--

CREATE TABLE `participants` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `role` varchar(100) NOT NULL,
  `program_id` int(11) DEFAULT NULL,
  `completion_percent` int(11) DEFAULT 0,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp(),
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `status` enum('Pending','Verified') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `participant_feedback`
--

CREATE TABLE `participant_feedback` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `module_type` enum('Training','Program','Simulation') NOT NULL,
  `comments` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `participant_scores`
--

CREATE TABLE `participant_scores` (
  `id` int(11) NOT NULL,
  `participant_name` varchar(255) NOT NULL,
  `scenario` varchar(100) NOT NULL,
  `final_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `registrations`
--

CREATE TABLE `registrations` (
  `id` int(11) NOT NULL,
  `participant_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `report_title` varchar(255) NOT NULL,
  `report_body` text NOT NULL,
  `created_by` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `responses`
--

CREATE TABLE `responses` (
  `id` int(11) NOT NULL,
  `inject_id` int(11) NOT NULL,
  `participant_name` varchar(100) NOT NULL,
  `response` text NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `responses`
--

INSERT INTO `responses` (`id`, `inject_id`, `participant_name`, `response`, `submitted_at`) VALUES
(1, 1, 'Alice', 'Notified emergency team.', '2025-09-24 12:14:23'),
(2, 2, 'Bob', 'Evacuation routes secured.', '2025-09-24 12:14:23');

-- --------------------------------------------------------

--
-- Table structure for table `role_assignments`
--

CREATE TABLE `role_assignments` (
  `id` int(11) NOT NULL,
  `participant_id` int(11) NOT NULL,
  `participant_name` varchar(255) NOT NULL,
  `role` varchar(255) NOT NULL,
  `acceptance_status` enum('Pending','Accepted','Declined') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `age` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `safety_procedures`
--

CREATE TABLE `safety_procedures` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `checklist` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`checklist`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `safety_protocols`
--

CREATE TABLE `safety_protocols` (
  `id` int(11) NOT NULL,
  `protocol_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `compliance_status` enum('Pending','Compliant','Non-Compliant') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scenarios`
--

CREATE TABLE `scenarios` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `status` enum('draft','deployed') NOT NULL DEFAULT 'draft',
  `type` varchar(50) NOT NULL,
  `difficulty` enum('Beginner','Intermediate','Advanced') NOT NULL,
  `creator_id` int(11) NOT NULL,
  `approval_status` enum('Pending','Approved') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scores`
--

CREATE TABLE `scores` (
  `id` int(11) NOT NULL,
  `participant_name` varchar(100) NOT NULL,
  `criteria_id` int(11) DEFAULT NULL,
  `score` int(11) NOT NULL,
  `feedback` text DEFAULT NULL,
  `scored_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff') NOT NULL DEFAULT 'staff',
  `certification` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `name`, `email`, `password`, `role`, `certification`) VALUES
(1, 'Default Admin', 'admin@example.com', '$2y$10$oyMSRrpNnJxAHjsR65U2gu8/iGY0AXbWxAw4bUa0YUkyldyQ.X1ru', 'admin', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `staff_suggestions`
--

CREATE TABLE `staff_suggestions` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `type` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `suggested_by` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `training_variables`
--

CREATE TABLE `training_variables` (
  `id` int(11) NOT NULL,
  `variable_name` varchar(100) NOT NULL,
  `variable_value` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `role` enum('admin','staff','participant') NOT NULL DEFAULT 'participant',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `variables`
--

CREATE TABLE `variables` (
  `id` int(11) NOT NULL,
  `scenario_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_safety_procedure` (`safety_procedure_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_event` (`user_id`,`event_id`),
  ADD UNIQUE KEY `unique_attendance` (`event_id`,`participant_id`),
  ADD KEY `idx_attendance_event` (`event_id`),
  ADD KEY `idx_attendance_user` (`user_id`),
  ADD KEY `idx_attendance_participant` (`participant_id`),
  ADD KEY `idx_attendance_event_user` (`event_id`,`user_id`),
  ADD KEY `idx_participant_event` (`participant_id`,`event_id`),
  ADD KEY `idx_attendance_event_status` (`event_id`,`status`),
  ADD KEY `idx_attendance_event_participant` (`event_id`,`participant_id`);

--
-- Indexes for table `attendance_participants`
--
ALTER TABLE `attendance_participants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `participant_id` (`participant_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `attendance_staff`
--
ALTER TABLE `attendance_staff`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `barangay_locations`
--
ALTER TABLE `barangay_locations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_district` (`district`),
  ADD KEY `idx_barangay` (`barangay_name`);

--
-- Indexes for table `certificates`
--
ALTER TABLE `certificates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `certificate_issuance`
--
ALTER TABLE `certificate_issuance`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `certification_criteria`
--
ALTER TABLE `certification_criteria`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `criteria`
--
ALTER TABLE `criteria`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `criteria_scores`
--
ALTER TABLE `criteria_scores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_criteria_scores_event` (`event_id`),
  ADD KEY `idx_criteria_scores_participant` (`participant_id`);

--
-- Indexes for table `damage_investigations`
--
ALTER TABLE `damage_investigations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_di_report` (`report_id`);

--
-- Indexes for table `damage_reports`
--
ALTER TABLE `damage_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_damage_equipment` (`equipment_id`),
  ADD KEY `idx_damage_equipment` (`equipment_id`);

--
-- Indexes for table `data_criteria`
--
ALTER TABLE `data_criteria`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `data_entries`
--
ALTER TABLE `data_entries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `debriefings`
--
ALTER TABLE `debriefings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_debriefs_event` (`event_id`);

--
-- Indexes for table `debriefing_materials`
--
ALTER TABLE `debriefing_materials`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `decisions`
--
ALTER TABLE `decisions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_decisions_participant` (`participant_id`),
  ADD KEY `idx_decisions_inject` (`inject_id`);

--
-- Indexes for table `equipment`
--
ALTER TABLE `equipment`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `equipment_audits`
--
ALTER TABLE `equipment_audits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ea_report` (`report_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_events_date` (`date`),
  ADD KEY `idx_events_date_time` (`date`,`time`),
  ADD KEY `idx_events_type` (`type`),
  ADD KEY `idx_events_type_status` (`type`,`approval_status`),
  ADD KEY `idx_events_location` (`location`);

--
-- Indexes for table `exercise_history`
--
ALTER TABLE `exercise_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `feedback_debrief`
--
ALTER TABLE `feedback_debrief`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_feedback_participant` (`participant_id`);

--
-- Indexes for table `final_scores`
--
ALTER TABLE `final_scores`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gear_checkout`
--
ALTER TABLE `gear_checkout`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_gear_checkout_equipment` (`equipment_id`);

--
-- Indexes for table `history_records`
--
ALTER TABLE `history_records`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `injects`
--
ALTER TABLE `injects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `maintenance`
--
ALTER TABLE `maintenance`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `maintenance_logs`
--
ALTER TABLE `maintenance_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_maintenance_equipment` (`equipment_id`);

--
-- Indexes for table `modules`
--
ALTER TABLE `modules`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `participants`
--
ALTER TABLE `participants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `participant_feedback`
--
ALTER TABLE `participant_feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `participant_scores`
--
ALTER TABLE `participant_scores`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `registrations`
--
ALTER TABLE `registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_registration` (`participant_id`,`event_id`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `responses`
--
ALTER TABLE `responses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_responses_injects` (`inject_id`);

--
-- Indexes for table `role_assignments`
--
ALTER TABLE `role_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_role_participant` (`participant_id`);

--
-- Indexes for table `safety_procedures`
--
ALTER TABLE `safety_procedures`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `safety_protocols`
--
ALTER TABLE `safety_protocols`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `scenarios`
--
ALTER TABLE `scenarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `creator_id` (`creator_id`);

--
-- Indexes for table `scores`
--
ALTER TABLE `scores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_criteria_id` (`criteria_id`),
  ADD KEY `idx_scores_participant` (`participant_name`),
  ADD KEY `idx_scores_criteria` (`criteria_id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `staff_suggestions`
--
ALTER TABLE `staff_suggestions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `training_variables`
--
ALTER TABLE `training_variables`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `variables`
--
ALTER TABLE `variables`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_variables_scenario_id_new` (`scenario_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_participants`
--
ALTER TABLE `attendance_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_staff`
--
ALTER TABLE `attendance_staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `barangay_locations`
--
ALTER TABLE `barangay_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=140;

--
-- AUTO_INCREMENT for table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `certificate_issuance`
--
ALTER TABLE `certificate_issuance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `certification_criteria`
--
ALTER TABLE `certification_criteria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `criteria`
--
ALTER TABLE `criteria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `criteria_scores`
--
ALTER TABLE `criteria_scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `damage_investigations`
--
ALTER TABLE `damage_investigations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `damage_reports`
--
ALTER TABLE `damage_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `data_criteria`
--
ALTER TABLE `data_criteria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `data_entries`
--
ALTER TABLE `data_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `debriefings`
--
ALTER TABLE `debriefings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `debriefing_materials`
--
ALTER TABLE `debriefing_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `decisions`
--
ALTER TABLE `decisions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `equipment`
--
ALTER TABLE `equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `equipment_audits`
--
ALTER TABLE `equipment_audits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `exercise_history`
--
ALTER TABLE `exercise_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback_debrief`
--
ALTER TABLE `feedback_debrief`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `final_scores`
--
ALTER TABLE `final_scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gear_checkout`
--
ALTER TABLE `gear_checkout`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `history_records`
--
ALTER TABLE `history_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `injects`
--
ALTER TABLE `injects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `maintenance`
--
ALTER TABLE `maintenance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `maintenance_logs`
--
ALTER TABLE `maintenance_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `modules`
--
ALTER TABLE `modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `participant_feedback`
--
ALTER TABLE `participant_feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `participant_scores`
--
ALTER TABLE `participant_scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `registrations`
--
ALTER TABLE `registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `responses`
--
ALTER TABLE `responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `role_assignments`
--
ALTER TABLE `role_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `safety_procedures`
--
ALTER TABLE `safety_procedures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `safety_protocols`
--
ALTER TABLE `safety_protocols`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scenarios`
--
ALTER TABLE `scenarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `scores`
--
ALTER TABLE `scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `staff_suggestions`
--
ALTER TABLE `staff_suggestions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `training_variables`
--
ALTER TABLE `training_variables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `variables`
--
ALTER TABLE `variables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_safety_procedure` FOREIGN KEY (`safety_procedure_id`) REFERENCES `safety_procedures` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `fk_attendance_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_attendance_event_v2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_attendance_participant` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_attendance_participant_v2` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_attendance_participant_v3` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `attendance_participants`
--
ALTER TABLE `attendance_participants`
  ADD CONSTRAINT `attendance_participants_ibfk_1` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_participants_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance_staff`
--
ALTER TABLE `attendance_staff`
  ADD CONSTRAINT `attendance_staff_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_staff_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `damage_investigations`
--
ALTER TABLE `damage_investigations`
  ADD CONSTRAINT `fk_di_report` FOREIGN KEY (`report_id`) REFERENCES `damage_reports` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `damage_reports`
--
ALTER TABLE `damage_reports`
  ADD CONSTRAINT `fk_damage_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `equipment_audits`
--
ALTER TABLE `equipment_audits`
  ADD CONSTRAINT `fk_ea_report` FOREIGN KEY (`report_id`) REFERENCES `damage_reports` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `gear_checkout`
--
ALTER TABLE `gear_checkout`
  ADD CONSTRAINT `fk_gear_checkout_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `maintenance_logs`
--
ALTER TABLE `maintenance_logs`
  ADD CONSTRAINT `fk_maintenance_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `participant_feedback`
--
ALTER TABLE `participant_feedback`
  ADD CONSTRAINT `participant_feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `responses`
--
ALTER TABLE `responses`
  ADD CONSTRAINT `fk_responses_injects` FOREIGN KEY (`inject_id`) REFERENCES `injects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `responses_ibfk_1` FOREIGN KEY (`inject_id`) REFERENCES `injects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `scenarios`
--
ALTER TABLE `scenarios`
  ADD CONSTRAINT `scenarios_ibfk_1` FOREIGN KEY (`creator_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `scores`
--
ALTER TABLE `scores`
  ADD CONSTRAINT `fk_scores_criteria` FOREIGN KEY (`criteria_id`) REFERENCES `certification_criteria` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `variables`
--
ALTER TABLE `variables`
  ADD CONSTRAINT `fk_variables_scenario_id_new` FOREIGN KEY (`scenario_id`) REFERENCES `scenarios` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
