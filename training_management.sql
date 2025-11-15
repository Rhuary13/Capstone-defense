-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 15, 2025 at 06:13 AM
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
-- Database: `training_management`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `ensure_registrations_schema` ()   BEGIN
  -- Add full_name if missing
  IF NOT EXISTS (
      SELECT * FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = 'training_management'
        AND TABLE_NAME = 'registrations'
        AND COLUMN_NAME = 'full_name'
  ) THEN
      ALTER TABLE registrations ADD COLUMN full_name VARCHAR(255) NOT NULL AFTER id;
  END IF;

  -- Add email if missing
  IF NOT EXISTS (
      SELECT * FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = 'training_management'
        AND TABLE_NAME = 'registrations'
        AND COLUMN_NAME = 'email'
  ) THEN
      ALTER TABLE registrations ADD COLUMN email VARCHAR(255) NOT NULL AFTER full_name;
  END IF;

  -- Ensure unique index on email only once
  IF NOT EXISTS (
      SELECT * FROM INFORMATION_SCHEMA.STATISTICS
      WHERE TABLE_SCHEMA = 'training_management'
        AND TABLE_NAME = 'registrations'
        AND INDEX_NAME = 'idx_email'
  ) THEN
      ALTER TABLE registrations ADD UNIQUE KEY idx_email (email);
  END IF;

  -- Add contact_number if missing
  IF NOT EXISTS (
      SELECT * FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = 'training_management'
        AND TABLE_NAME = 'registrations'
        AND COLUMN_NAME = 'contact_number'
  ) THEN
      ALTER TABLE registrations ADD COLUMN contact_number VARCHAR(50) NOT NULL AFTER email;
  END IF;

  -- Add disaster_type if missing
  IF NOT EXISTS (
      SELECT * FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = 'training_management'
        AND TABLE_NAME = 'registrations'
        AND COLUMN_NAME = 'disaster_type'
  ) THEN
      ALTER TABLE registrations ADD COLUMN disaster_type VARCHAR(100) NOT NULL AFTER contact_number;
  END IF;

  -- Add location if missing
  IF NOT EXISTS (
      SELECT * FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = 'training_management'
        AND TABLE_NAME = 'registrations'
        AND COLUMN_NAME = 'location'
  ) THEN
      ALTER TABLE registrations ADD COLUMN location VARCHAR(255) NOT NULL AFTER disaster_type;
  END IF;

  -- Add created_at if missing
  IF NOT EXISTS (
      SELECT * FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = 'training_management'
        AND TABLE_NAME = 'registrations'
        AND COLUMN_NAME = 'created_at'
  ) THEN
      ALTER TABLE registrations ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER location;
  END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `assessments`
--

CREATE TABLE `assessments` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `validity_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assessment_questions`
--

CREATE TABLE `assessment_questions` (
  `id` int(11) NOT NULL,
  `assessment_id` int(11) NOT NULL,
  `question` text NOT NULL,
  `type` enum('checklist','multiple_choice','true_false') DEFAULT 'checklist',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `user_type` enum('staff','participant') NOT NULL,
  `check_in` datetime DEFAULT NULL,
  `check_out` datetime DEFAULT NULL,
  `date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `user_id`, `full_name`, `user_type`, `check_in`, `check_out`, `date`) VALUES
(15, 0, 'Guest', 'participant', '2025-10-30 14:52:48', NULL, '2025-10-30');

-- --------------------------------------------------------

--
-- Table structure for table `drills`
--

CREATE TABLE `drills` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `details` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `type` varchar(100) NOT NULL DEFAULT 'Drill',
  `file_path` varchar(512) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `drills`
--

INSERT INTO `drills` (`id`, `title`, `date`, `details`, `created_at`, `type`, `file_path`) VALUES
(1, 'Community Evacuation drill', '2025-11-01', 'A Community Evacuation Drill is a planned exercise that helps residents and local responders practice how to move safely and efficiently from hazardous areas to designated safe zones during disasters such as earthquakes, floods, fires, or typhoons.\r\nIt ensures that all members of the community understand what actions to take and how to coordinate with emergency personnel during an actual event.', '2025-10-30 23:59:49', 'Flood', NULL),
(2, 'Earthquake Drill', '2025-11-01', 'This lesson introduces earthquake hazards, safety procedures, and community coordination during seismic events. It emphasizes practical skills through simulation exercises designed to save lives, reduce injuries, and ensure order during real earthquakes.', '2025-10-31 00:10:04', 'Flood', 'C:\\xampp\\htdocs\\Capstone-defense\\admin\\module1/uploads/drills/1761869404_Earthquake_Drill.pdf');

-- --------------------------------------------------------

--
-- Table structure for table `learning_objectives`
--

CREATE TABLE `learning_objectives` (
  `id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lessons`
--

CREATE TABLE `lessons` (
  `id` int(11) NOT NULL,
  `module_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `summary` text DEFAULT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `disaster_type` varchar(100) DEFAULT NULL,
  `file_name` varchar(512) DEFAULT NULL,
  `scheduled_date` date DEFAULT NULL,
  `file_path` varchar(512) DEFAULT NULL,
  `published` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('draft','published') NOT NULL DEFAULT 'draft',
  `views` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `category_id` int(11) DEFAULT NULL,
  `topic` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lessons`
--

INSERT INTO `lessons` (`id`, `module_id`, `title`, `summary`, `content`, `created_at`, `disaster_type`, `file_name`, `scheduled_date`, `file_path`, `published`, `created_by`, `description`, `status`, `views`, `approved_by`, `approved_at`, `updated_at`, `category_id`, `topic`) VALUES
(1, NULL, 'Disaster Preparedness Training and Simulation (All Disaster Types)', NULL, 'Disaster preparedness refers to the systematic and continuous process of planning, organizing, training, equipping, exercising, evaluating, and improving actions and measures taken to ensure that individuals, families, and communities are ready to respond effectively when disasters occur. It is one of the four thematic areas of Disaster Risk Reduction and Management (DRRM), alongside prevention and mitigation, response, and recovery and rehabilitation, as mandated by Republic Act No. 10121 or the Philippine DRRM Act of 2010. Preparedness aims to strengthen the capability of communities to anticipate, cope with, and recover from the impacts of hazards through effective coordination, planning, and resource management.\r\n\r\nA disaster can be either natural or human-induced. Natural disasters include earthquakes, typhoons, floods, landslides, volcanic eruptions, droughts, and tsunamis. Human-induced or technological disasters include fires, explosions, chemical spills, transportation accidents, industrial failures, and civil disturbances. Each type of disaster presents different risks, but preparedness measures share common elements designed to reduce loss of life and property damage. Understanding these types of disasters allows communities to identify the hazards most likely to affect them and to plan accordingly.\r\n\r\nPreparedness begins with awareness and education. Every community must identify its specific hazards and conduct a risk assessment to determine which areas and populations are most vulnerable. Based on this assessment, local government units and barangay officials develop comprehensive DRRM plans that outline how to prepare for, respond to, and recover from disasters. These plans should include early warning systems, evacuation procedures, communication protocols, and stockpiles of emergency supplies. Public awareness campaigns, community seminars, and household-level preparedness activities are essential in ensuring that all residents understand what to do before, during, and after a disaster.\r\n\r\nSimulation exercises and drills are integral parts of disaster preparedness. They serve as practical applications of the DRRM plans and provide an opportunity to test coordination, response time, and resource management in a controlled environment. Common simulation activities include earthquake drills that demonstrate the “drop, cover, and hold” technique, flood evacuation exercises, and fire drills. These drills familiarize participants with emergency routes, safe zones, and standard operating procedures. During simulations, specific roles are assigned such as Incident Commander, Safety Officer, Medical Response, Logistics, and Communications, ensuring that everyone knows their function during an actual emergency. After the drill, a debriefing session is conducted to identify strengths, weaknesses, and areas for improvement.\r\n\r\nEmergency preparedness also involves the management of resources and supplies. Every household is encouraged to maintain an emergency “Go Bag” containing essential items such as food, water, flashlight, first aid supplies, medicines, identification documents, and clothing sufficient for at least seventy-two hours. Communities, on the other hand, should maintain shared resources like rescue tools, first aid kits, radios, emergency vehicles, and designated evacuation centers equipped to handle displaced residents. Effective resource management ensures that vital items and services are available when needed most.\r\n\r\nThe success of disaster preparedness relies heavily on the clear definition of roles and responsibilities. Local government units are responsible for activating the Barangay or City/Municipal DRRM Committees, updating contingency plans, and coordinating with the national government through the NDRRMC. Responders and volunteers must adhere to standard operating procedures, ensure their safety, and maintain accountability during operations. Community members play an equally important role by actively participating in drills, following official advisories, and keeping their families informed and prepared.\r\n\r\nContinuous monitoring and improvement are essential components of preparedness. After every drill or disaster event, an evaluation should be conducted to assess response effectiveness, coordination, and communication. Lessons learned must be documented and integrated into updated DRRM plans. Regular reviews of hazard maps, communication systems, and early warning mechanisms help ensure that preparedness strategies remain relevant and effective. Indicators such as the frequency of drills, level of community participation, and availability of resources provide measurable data on the community’s state of readiness.\r\n\r\nUltimately, the goal of disaster preparedness is to build a culture of safety and resilience. This can only be achieved through sustained public education, regular training, and active community involvement. Every household, school, and workplace must internalize the importance of readiness and integrate safety measures into their daily routines. Partnerships among local governments, private institutions, and non-government organizations strengthen community capacity to prevent and respond to disasters. When preparedness becomes a shared responsibility, communities become more capable of protecting lives and recovering swiftly after any calamity.\r\n\r\nIn summary, disaster preparedness and simulation activities are vital tools in minimizing the adverse effects of both natural and human-induced disasters. Preparedness saves lives, reduces economic losses, and strengthens the resilience of communities. By continuously training, simulating, and learning from each experience, societies can ensure that when disaster strikes, they are not caught off guard but are ready to act efficiently, cooperatively, and confidently. As the National Disaster Risk Reduction and Management Council (NDRRMC) emphasizes, “Preparedness today ensures safety tomorrow.”', '2025-10-31 19:52:46', NULL, NULL, NULL, NULL, 1, NULL, NULL, 'draft', 0, NULL, NULL, '2025-11-02 14:12:06', NULL, NULL),
(2, NULL, 'EARTHQUAKE DRILL', NULL, 'READY FOR EARTHQUAKE!', '2025-10-31 20:24:35', 'Earthquake', NULL, '2025-11-11', 'C:\\xampp\\htdocs\\Capstone-defense\\admin\\module1/uploads/lessons/1761942275_Earthquake_Drill.pdf', 1, 7, NULL, 'draft', 0, NULL, NULL, '2025-11-02 14:12:06', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `modules`
--

CREATE TABLE `modules` (
  `id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(150) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `order_index` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `module_categories`
--

CREATE TABLE `module_categories` (
  `id` int(11) NOT NULL,
  `module_table` varchar(100) NOT NULL,
  `module_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `module_categories`
--

INSERT INTO `module_categories` (`id`, `module_table`, `module_id`, `category_id`, `assigned_by`, `assigned_at`) VALUES
(1, 'lessons', 1, 1, 7, '2025-11-02 20:17:08'),
(2, 'lessons', 7, 1, 7, '2025-11-02 20:17:08');

-- --------------------------------------------------------

--
-- Table structure for table `module_postings`
--

CREATE TABLE `module_postings` (
  `id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `target` enum('participants','staff','all') NOT NULL DEFAULT 'all',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `module_postings`
--

INSERT INTO `module_postings` (`id`, `lesson_id`, `target`, `created_at`) VALUES
(1, 2, 'all', '2025-10-31 13:24:35');

-- --------------------------------------------------------

--
-- Table structure for table `module_staff_assignments`
--

CREATE TABLE `module_staff_assignments` (
  `module_id` int(11) NOT NULL,
  `staff_id` int(10) UNSIGNED NOT NULL
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `participants`
--

CREATE TABLE `participants` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `role` varchar(100) NOT NULL,
  `program_id` int(11) DEFAULT NULL,
  `completion_percent` int(11) DEFAULT 0,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `participant_records`
--

CREATE TABLE `participant_records` (
  `id` int(11) NOT NULL,
  `participant_id` int(11) NOT NULL,
  `training_module` varchar(150) DEFAULT NULL,
  `program_name` varchar(150) DEFAULT NULL,
  `simulation_title` varchar(150) DEFAULT NULL,
  `score` int(11) DEFAULT 0,
  `status` enum('completed','in-progress','failed') DEFAULT 'in-progress',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

CREATE TABLE `programs` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `progress`
--

CREATE TABLE `progress` (
  `id` int(11) NOT NULL,
  `participant_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `status` enum('not started','in progress','completed') DEFAULT 'not started',
  `score` decimal(5,2) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `progress_tracking`
--

CREATE TABLE `progress_tracking` (
  `id` int(11) NOT NULL,
  `module_name` varchar(255) NOT NULL,
  `is_mandatory` tinyint(1) DEFAULT 0,
  `prerequisite` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quizzes`
--

CREATE TABLE `quizzes` (
  `id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `question` text NOT NULL,
  `option_a` varchar(255) DEFAULT NULL,
  `option_b` varchar(255) DEFAULT NULL,
  `option_c` varchar(255) DEFAULT NULL,
  `option_d` varchar(255) DEFAULT NULL,
  `correct_option` char(1) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `title` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `quizzes`
--

INSERT INTO `quizzes` (`id`, `lesson_id`, `question`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_option`, `created_at`, `title`) VALUES
(1, 2, '', NULL, NULL, NULL, NULL, '', '2025-10-31 13:24:35', 'Auto Quiz — EARTHQUAKE DRILL');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_files`
--

CREATE TABLE `quiz_files` (
  `id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_questions`
--

CREATE TABLE `quiz_questions` (
  `question_id` int(11) NOT NULL,
  `lesson_id` int(11) DEFAULT NULL,
  `question` text NOT NULL,
  `option_a` varchar(255) NOT NULL,
  `option_b` varchar(255) NOT NULL,
  `option_c` varchar(255) NOT NULL,
  `option_d` varchar(255) NOT NULL,
  `correct_option` enum('A','B','C','D') NOT NULL,
  `quiz_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quiz_questions`
--

INSERT INTO `quiz_questions` (`question_id`, `lesson_id`, `question`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_option`, `quiz_id`, `title`, `created_at`) VALUES
(1, NULL, 'What is the primary purpose of disaster preparedness training?', 'To avoid all disasters completely', 'To equip people with skills to reduce risks and respond effectively', 'To eliminate natural hazards permanently', 'To replace government response teams', 'B', NULL, 'Disaster Preparedness', '2025-10-31 15:41:12'),
(2, NULL, 'Which of the following should always be included in a basic household emergency kit?', 'Jewelry, travel tickets, and luxury items', 'Fresh fruits and vegetables only', 'Water, non-perishable food, flashlight, and first aid kit', 'Television and microwave oven', 'C', NULL, 'Disaster Preparedness', '2025-10-31 15:41:12'),
(3, NULL, 'Why is it important to identify evacuation routes in advance?', 'To avoid waiting for government instructions', 'To reduce confusion and save time during emergencies', 'To test which route is fastest for vehicles', 'To keep law enforcement from interfering', 'B', NULL, 'Disaster Preparedness', '2025-10-31 15:41:12'),
(4, NULL, 'What should you do indoors during an earthquake?', 'Run outside immediately', 'Stand under the door frame', 'Drop, Cover, and Hold On', 'Jump to higher floors', 'C', NULL, 'Disaster Preparedness', '2025-10-31 15:41:12'),
(5, NULL, 'What is a “Go Bag”?', 'A bag for school or work', 'A portable emergency kit prepared for evacuation', 'A travel luggage for vacation', 'A government-issued relief pack', 'B', NULL, 'Disaster Preparedness', '2025-10-31 15:41:12'),
(6, NULL, 'Who should be included in a family emergency communication plan?', 'Only parents', 'Neighbors and strangers', 'Household members and designated contacts', 'The mayor and barangay captain only', 'C', NULL, 'Disaster Preparedness', '2025-10-31 15:41:12'),
(7, NULL, 'What is the difference between a hazard and a disaster?', 'Hazard is man-made, disaster is natural', 'Hazard is a potential threat, disaster is when it causes harm', 'Hazard always happens yearly, disaster does not', 'Hazard is recoverable, disaster is not', 'B', NULL, 'Disaster Preparedness', '2025-10-31 15:41:12'),
(8, NULL, 'Why are disaster drills and simulations conducted?', 'To predict when disasters will happen', 'To replace emergency response agencies', 'To practice actions and test preparedness plans', 'To increase government budget for disaster programs', 'C', NULL, 'Disaster Preparedness', '2025-10-31 15:41:12'),
(9, NULL, 'What is the role of community disaster risk reduction programs?', 'To rely on outside agencies only', 'To reduce vulnerabilities and strengthen local response capacity', 'To increase the number of hazards for study', 'To prevent natural disasters from happening', 'B', NULL, 'Disaster Preparedness', '2025-10-31 15:41:12'),
(10, NULL, 'After a disaster, what is the first thing you should do?', 'Post about it on social media', 'Check for injuries and follow official updates', 'Return to work immediately', 'Celebrate survival with neighbors', 'B', NULL, 'Disaster Preparedness', '2025-10-31 15:41:12'),
(11, NULL, 'What does “DRRM” stand for?', 'Disaster Recovery and Rehabilitation Management', 'Disaster Risk Reduction and Management', 'Disaster Response and Relief Mission', 'Disaster Resilience and Recovery Model', 'B', NULL, 'Risk Reduction & Mitigation', '2025-10-31 15:41:12'),
(12, NULL, 'Why is it important to know the hazards specific to your community?', 'To increase fear and panic', 'To design preparedness measures suited to local risks', 'To avoid paying local taxes', 'To blame the government for lack of response', 'B', NULL, 'Risk Reduction & Mitigation', '2025-10-31 15:41:12'),
(13, NULL, 'What does the “golden hour” in first aid mean?', 'The safest time to evacuate at sunset', 'The first 60 minutes after injury when treatment saves lives', 'The time government distributes relief goods', 'The time rescue teams usually arrive', 'B', NULL, 'Risk Reduction & Mitigation', '2025-10-31 15:41:12'),
(14, NULL, 'If a fire breaks out at home, what should you do?', 'Use the elevator immediately', 'Evacuate safely and call emergency services', 'Hide inside the bathroom', 'Wait for neighbors to rescue you', 'B', NULL, 'Risk Reduction & Mitigation', '2025-10-31 15:41:12'),
(15, NULL, 'Why should you avoid walking or driving through moving floodwater?', 'It may wash away dirt on the road', 'It is usually contaminated', 'Even shallow water can knock you down or sweep away vehicles', 'It is embarrassing to be seen wet', 'C', NULL, 'Risk Reduction & Mitigation', '2025-10-31 15:41:12'),
(16, NULL, 'What does the “triangle of life” theory suggest during earthquakes?', 'Stand under a door frame', 'Position next to sturdy objects to create survivable voids', 'Run outside immediately', 'Stay near glass windows', 'B', NULL, 'Risk Reduction & Mitigation', '2025-10-31 15:41:12'),
(17, NULL, 'What is an Early Warning System (EWS)?', 'A rumor spread by neighbors before a disaster', 'A system that provides alerts before hazards strike', 'A list of disasters from history books', 'A social media group for sharing news', 'B', NULL, 'Risk Reduction & Mitigation', '2025-10-31 15:41:12'),
(18, NULL, 'What is the main difference between an emergency plan and a contingency plan?', 'Emergency plans are long-term; contingency plans are short-term', 'Emergency plans are immediate actions; contingency plans include alternatives and long-term strategies', 'Emergency plans are for earthquakes only; contingency plans are for floods only', 'Emergency plans are optional; contingency plans are mandatory', 'B', NULL, 'Risk Reduction & Mitigation', '2025-10-31 15:41:12'),
(19, NULL, 'Why must vulnerable groups (children, elderly, PWDs) be included in disaster planning?', 'They are less important', 'They have specific needs and higher risks during emergencies', 'They can be evacuated later', 'They do not require extra care', 'B', NULL, 'Risk Reduction & Mitigation', '2025-10-31 15:41:12'),
(20, NULL, 'What is the role of first responders during a disaster?', 'To provide immediate assistance and rescue', 'To wait for community volunteers to act', 'To distribute goods only after one week', 'To stay at evacuation centers only', 'A', NULL, 'Risk Reduction & Mitigation', '2025-10-31 15:41:12'),
(21, NULL, 'What is “mitigation” in disaster risk management?', 'Actions to reduce disaster severity or likelihood', 'Waiting for a disaster to happen', 'Ignoring potential hazards', 'Celebrating survival after a disaster', 'A', NULL, 'Preparedness Actions', '2025-10-31 15:41:12'),
(22, NULL, 'Why should households store at least 72 hours’ worth of supplies?', 'Because supermarkets are closed for three days every month', 'Because relief may take time to arrive', 'Because storing more is illegal', 'Because families prefer to eat together during disasters', 'B', NULL, 'Preparedness Actions', '2025-10-31 15:41:12'),
(23, NULL, 'What is the “all-hazards approach” to preparedness?', 'Preparing for one disaster at a time', 'Preparing for common effects across different hazards', 'Ignoring rare hazards', 'Focusing only on earthquakes', 'B', NULL, 'Preparedness Actions', '2025-10-31 15:41:12'),
(24, NULL, 'What is the main purpose of a hazard map?', 'To entertain students with drawings', 'To identify safe areas for planning and evacuation', 'To predict the exact date of disasters', 'To measure rainfall during typhoons', 'B', NULL, 'Preparedness Actions', '2025-10-31 15:41:12'),
(25, NULL, 'Why should disaster drills be repeated regularly?', 'To create more expenses', 'To reinforce memory and adjust for new risks', 'To keep people afraid of hazards', 'To delay school or work hours', 'B', NULL, 'Preparedness Actions', '2025-10-31 15:41:12'),
(26, NULL, 'What is “resilience” in the context of disasters?', 'The ability to avoid disasters completely', 'The ability to recover quickly and adapt to change', 'The ability to wait for international aid', 'The ability to ignore hazards', 'B', NULL, 'Preparedness Actions', '2025-10-31 15:41:12'),
(27, NULL, 'What is the “buddy system” in emergency planning?', 'Pairing individuals to check on and help each other', 'Allowing one person to work alone', 'Only helping family members', 'Assigning friends to watch TV together', 'A', NULL, 'Preparedness Actions', '2025-10-31 15:41:12'),
(28, NULL, 'During a volcanic eruption, why should you avoid valleys and rivers?', 'They are cooler and attract ash', 'They are common paths for lava, lahars, and pyroclastic flows', 'They are safe zones', 'They are far from evacuation centers', 'B', NULL, 'Preparedness Actions', '2025-10-31 15:41:12'),
(29, NULL, 'What does “shelter-in-place” mean?', 'Staying indoors in a safe location during hazards', 'Going immediately to an evacuation center', 'Building a temporary tent outside', 'Evacuating to another city', 'A', NULL, 'Preparedness Actions', '2025-10-31 15:41:12'),
(30, NULL, 'Why is post-disaster debriefing important?', 'To assign blame', 'To evaluate response, learn lessons, and reduce trauma', 'To celebrate survival', 'To gather donations only', 'B', NULL, 'Preparedness Actions', '2025-10-31 15:41:12'),
(31, NULL, 'If you receive a tsunami warning near the coast, what should you do?', 'Wait for the waves to arrive', 'Go to higher ground immediately', 'Swim in the opposite direction', 'Stay indoors near the shore', 'B', NULL, 'Advanced Preparedness & Response', '2025-10-31 15:41:12'),
(32, NULL, 'Why should gas and electricity be turned off before evacuation?', 'To prevent fire and electrocution risks', 'To save money', 'To avoid high electric bills', 'To follow tradition', 'A', NULL, 'Advanced Preparedness & Response', '2025-10-31 15:41:12'),
(33, NULL, 'What is usually required in evacuation center protocol?', 'Avoiding registration', 'Following rules, respecting space, and cooperating', 'Cooking your own food separately', 'Staying only with your family', 'B', NULL, 'Advanced Preparedness & Response', '2025-10-31 15:41:12'),
(34, NULL, 'What is the purpose of a community hazard drill?', 'To scare residents', 'To practice response and test systems', 'To delay school or work', 'To replace first responders', 'B', NULL, 'Advanced Preparedness & Response', '2025-10-31 15:41:12'),
(35, NULL, 'Why should emergency contact numbers be written down?', 'Phones may run out of battery or get lost', 'Writing numbers is faster than calling', 'It is required by law', 'Phones are not useful in disasters', 'A', NULL, 'Advanced Preparedness & Response', '2025-10-31 15:41:12'),
(36, NULL, 'What is the first step when encountering an injured person?', 'Run away', 'Check scene safety before giving help', 'Take pictures for documentation', 'Wait for volunteers', 'B', NULL, 'Advanced Preparedness & Response', '2025-10-31 15:41:12'),
(37, NULL, 'What does the term “aftershock” mean?', 'A larger earthquake', 'A smaller earthquake that follows the main shock', 'A sudden flood', 'A volcanic eruption', 'B', NULL, 'Advanced Preparedness & Response', '2025-10-31 15:41:12'),
(38, NULL, 'Why should you avoid using candles after disasters?', 'Candles are expensive', 'Open flames may cause fires if gas leaks are present', 'Flashlights are brighter', 'It is illegal in some areas', 'B', NULL, 'Advanced Preparedness & Response', '2025-10-31 15:41:12'),
(39, NULL, 'What is “psychological first aid”?', 'Medical treatment for broken bones', 'Comfort and support to reduce stress and promote calm', 'Teaching survivors mathematics', 'Organizing relief distribution', 'B', NULL, 'Advanced Preparedness & Response', '2025-10-31 15:41:12'),
(40, NULL, 'What does “build back better” mean in disaster recovery?', 'Rebuilding communities exactly as they were', 'Reconstructing communities stronger and safer than before', 'Building temporary houses only', 'Waiting for international aid before building', 'B', NULL, 'Advanced Preparedness & Response', '2025-10-31 15:41:12'),
(41, NULL, 'Why is proper waste disposal important after disasters?', 'To prevent disease outbreaks and environmental hazards', 'To create more garbage collectors’ jobs', 'To make neighborhoods look cleaner only', 'To reduce the need for volunteers', 'A', NULL, 'Community & Recovery', '2025-10-31 15:41:12'),
(42, NULL, 'What should schools practice regularly?', 'Sports events', 'Earthquake, fire, and evacuation drills', 'Only classroom lessons', 'Only monthly clean-ups', 'B', NULL, 'Community & Recovery', '2025-10-31 15:41:12'),
(43, NULL, 'Why is coordination with LGUs critical during disasters?', 'LGUs manage evacuation, relief, and communication', 'LGUs control social media posts', 'LGUs provide entertainment', 'LGUs stop volunteers from helping', 'A', NULL, 'Community & Recovery', '2025-10-31 15:41:12'),
(44, NULL, 'What is the main purpose of pre-disaster risk assessment?', 'To identify vulnerabilities and guide planning', 'To compare disaster movies', 'To predict exact dates of hazards', 'To increase taxes', 'A', NULL, 'Community & Recovery', '2025-10-31 15:41:12'),
(45, NULL, 'Why are radios essential in emergency kits?', 'They provide reliable updates when power and internet fail', 'They are fun for entertainment', 'They help charge phones', 'They are traditional devices', 'A', NULL, 'Community & Recovery', '2025-10-31 15:41:12'),
(46, NULL, 'If trapped under rubble, what should you do?', 'Shout continuously', 'Stay calm, cover mouth, tap surfaces to signal', 'Run outside immediately', 'Use a lighter for light', 'B', NULL, 'Community & Recovery', '2025-10-31 15:41:12'),
(47, NULL, 'What is the role of volunteers in disaster response?', 'To provide rescue, relief, and support efforts', 'To replace government entirely', 'To observe only', 'To distribute money', 'A', NULL, 'Community & Recovery', '2025-10-31 15:41:12'),
(48, NULL, 'Why is community-based disaster preparedness effective?', 'Locals know risks, resources, and needs better', 'It avoids government involvement', 'It prevents hazards from happening', 'It is cheaper than national programs', 'A', NULL, 'Community & Recovery', '2025-10-31 15:41:12'),
(49, NULL, 'What does “hazard-prone area” mean?', 'A location with no disasters ever recorded', 'A location at high risk for hazards like floods or landslides', 'A safe evacuation site', 'A government office for DRRM', 'B', NULL, 'Community & Recovery', '2025-10-31 15:41:12'),
(50, NULL, 'Why is continuous training necessary for preparedness?', 'To refresh skills and ensure readiness', 'To keep people afraid of disasters', 'To spend more budget', 'To delay normal activities', 'A', NULL, 'Community & Recovery', '2025-10-31 15:41:12');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_results`
--

CREATE TABLE `quiz_results` (
  `id` int(11) NOT NULL,
  `participant_id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `score` int(11) NOT NULL,
  `total_questions` int(11) NOT NULL,
  `status` enum('Passed','Failed') NOT NULL DEFAULT 'Failed',
  `taken_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `records`
--

CREATE TABLE `records` (
  `id` int(11) NOT NULL,
  `participant_name` varchar(255) NOT NULL,
  `module` varchar(255) NOT NULL,
  `score` decimal(5,2) NOT NULL,
  `validity_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `module_name` varchar(255) DEFAULT NULL,
  `status` enum('in_progress','completed','failed') DEFAULT 'in_progress'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `registrations`
--

CREATE TABLE `registrations` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `contact_number` varchar(50) NOT NULL,
  `disaster_type` varchar(100) NOT NULL,
  `location` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `resources`
--

CREATE TABLE `resources` (
  `id` int(10) UNSIGNED NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(1024) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `venue` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `role` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expertise` varchar(255) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `name`, `role`, `created_at`, `expertise`) VALUES
(1, 'Aaliyah Jash Nebab', 'Assistant', '2025-10-25 14:01:50', ''),
(2, 'Aaliyah Jash Nebab', 'Assistant', '2025-10-25 14:02:12', ''),
(3, 'Aaliyah Jash Nebab', 'Assistant', '2025-10-25 14:02:18', ''),
(4, 'Aaliyah Jash Nebab', 'Assistant', '2025-10-25 14:02:26', ''),
(5, 'Aaliyah Jash Nebab', 'Assistant', '2025-10-25 14:02:36', ''),
(6, 'Aaliyah Jash Nebab', 'Assistant', '2025-10-25 14:03:18', ''),
(7, 'Aaliyah Jash Nebab', 'Assistant', '2025-10-25 14:05:06', ''),
(8, 'Aaliyah Jash Nebab', 'Assistant', '2025-10-25 14:05:14', ''),
(9, 'Aaliyah Jash Nebab', 'Assistant', '2025-10-25 14:05:20', ''),
(10, 'Aaliyah Jash Nebab', 'Assistant', '2025-10-25 14:15:04', ''),
(11, 'Aaliyah Jash Nebab', 'Assistant', '2025-10-25 14:16:18', ''),
(12, 'Aaliyah Jash Nebab', 'Assistant', '2025-10-25 14:16:45', ''),
(13, 'Aaliyah Jash Nebab', 'Assistant', '2025-10-25 14:19:31', ''),
(14, 'Aaliyah Jash Nebab', 'Assistant', '2025-10-25 14:19:32', ''),
(15, 'Aaliyah Jash Nebab', 'Assistant', '2025-10-25 14:19:51', ''),
(16, 'Aaliyah Jash Nebab', 'Assistant', '2025-10-25 14:19:53', ''),
(17, 'Aaliyah Jash Nebab', 'Assistant', '2025-10-25 14:29:04', '');

-- --------------------------------------------------------

--
-- Table structure for table `staff_achievements`
--

CREATE TABLE `staff_achievements` (
  `id` int(10) UNSIGNED NOT NULL,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `date_acquired` date DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_disaster_certifications`
--

CREATE TABLE `staff_disaster_certifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `disaster_type` varchar(150) NOT NULL,
  `certification_title` varchar(255) NOT NULL,
  `issued_by` varchar(255) DEFAULT NULL,
  `date_issued` date DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `topics`
--

CREATE TABLE `topics` (
  `id` int(11) NOT NULL,
  `topic_name` varchar(255) NOT NULL,
  `category` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `topic_categories`
--

CREATE TABLE `topic_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `visibility` enum('public','private') DEFAULT 'public',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `topic_categories`
--

INSERT INTO `topic_categories` (`id`, `name`, `slug`, `description`, `visibility`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Flood Preparedness', 'flood-preparedness', 'Ghost Project', '', 7, '2025-11-02 20:15:36', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `training_completion`
--

CREATE TABLE `training_completion` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `status` enum('Not Started','In Progress','Completed') DEFAULT 'Not Started',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `training_modules`
--

CREATE TABLE `training_modules` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `prerequisite_id` int(11) DEFAULT NULL,
  `objectives` text NOT NULL,
  `disaster_type` varchar(100) NOT NULL,
  `topic` varchar(255) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_by` varchar(50) DEFAULT 'staff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `description` text DEFAULT NULL,
  `is_mandatory` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `category_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `training_modules`
--

INSERT INTO `training_modules` (`id`, `title`, `prerequisite_id`, `objectives`, `disaster_type`, `topic`, `file_name`, `status`, `created_by`, `created_at`, `description`, `is_mandatory`, `updated_at`, `category_id`) VALUES
(1, 'Disaster Preparedness Training and Simulation', NULL, 'BEING PREPARED', 'All Disaster Type', '', NULL, 'pending', 'staff', '2025-09-24 15:44:27', NULL, 0, '2025-10-31 22:55:37', NULL),
(2, 'Earthquake Drills', NULL, 'Being prepared for natural disaster \"Earthquake\"', 'Earthquake', '', NULL, 'pending', 'staff', '2025-10-25 13:34:40', NULL, 0, '2025-10-31 22:55:37', NULL),
(4, 'NEXTGEN IT: PREPARING FOR THE DIGITAL WORKPLACE', NULL, 'This program teaches college students to be prepared for the workplace of an IT who\'s graduating', 'All Disaster Type', '', 'OJT_PRACTICUM_1_NARRATIVE_REPORT__1___1__1761417487_9eecc673a6ec.pdf', 'pending', 'staff', '2025-10-25 18:38:07', NULL, 0, '2025-10-31 22:55:37', NULL),
(5, 'Familiarizing with AI fundamentals', NULL, 'IT\'s should familiarize to the modern world', 'All Disaster Type', '', 'Session_1__Familiarizing_with_AI_fundamentals_1761417589_026b0b389d69.pdf', 'pending', 'staff', '2025-10-25 18:39:49', NULL, 0, '2025-10-31 22:55:37', NULL),
(6, 'AI Tools, technologies, and How to leverage them', NULL, 'Leveraging the AI tools, Technologies for advance tech', 'All Disaster Type', '', 'Session_2__AI_Tools__Technologies__and_How_to_Leve_1761417698_87653fbe3a0f.pdf', 'pending', 'staff', '2025-10-25 18:41:38', NULL, 0, '2025-10-31 22:55:37', NULL),
(7, 'Lesson ', NULL, 'Auto-created objectives', 'General', '', NULL, 'pending', 'staff', '2025-11-02 16:22:25', NULL, 0, '2025-11-02 16:22:25', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `training_programs`
--

CREATE TABLE `training_programs` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `definition` text DEFAULT NULL,
  `scope` text DEFAULT NULL,
  `goal` text DEFAULT NULL,
  `format` text DEFAULT NULL,
  `example` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `training_progress`
--

CREATE TABLE `training_progress` (
  `id` int(11) NOT NULL,
  `participant_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `status` enum('not_started','in_progress','completed') NOT NULL DEFAULT 'not_started',
  `progress_percentage` int(11) NOT NULL DEFAULT 0,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `training_variables`
--

CREATE TABLE `training_variables` (
  `id` int(11) NOT NULL,
  `variable_name` varchar(100) NOT NULL,
  `variable_value` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `training_variables`
--

INSERT INTO `training_variables` (`id`, `variable_name`, `variable_value`, `updated_at`) VALUES
(1, 'MaxParticipants', '50', '2025-09-24 12:11:07'),
(2, 'SimulationDuration', '60', '2025-09-24 12:11:07'),
(3, 'AlertThreshold', '75', '2025-09-24 12:11:07');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff','user') NOT NULL,
  `full_name` varchar(150) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_type` enum('admin','staff','participant') NOT NULL DEFAULT 'participant',
  `avatar` varchar(255) DEFAULT NULL,
  `name` varchar(191) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `assessments`
--
ALTER TABLE `assessments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_assessment` (`assessment_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_date` (`user_id`,`date`);

--
-- Indexes for table `drills`
--
ALTER TABLE `drills`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `learning_objectives`
--
ALTER TABLE `learning_objectives`
  ADD PRIMARY KEY (`id`),
  ADD KEY `module_id` (`module_id`);

--
-- Indexes for table `lessons`
--
ALTER TABLE `lessons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `title` (`title`);

--
-- Indexes for table `modules`
--
ALTER TABLE `modules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `module_categories`
--
ALTER TABLE `module_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_module_cat` (`module_table`,`module_id`,`category_id`),
  ADD KEY `fk_module_categories_category` (`category_id`);

--
-- Indexes for table `module_postings`
--
ALTER TABLE `module_postings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lesson_id` (`lesson_id`);

--
-- Indexes for table `module_staff_assignments`
--
ALTER TABLE `module_staff_assignments`
  ADD PRIMARY KEY (`module_id`,`staff_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_recipient` (`recipient_type`);

--
-- Indexes for table `participants`
--
ALTER TABLE `participants`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `participant_records`
--
ALTER TABLE `participant_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `participant_id` (`participant_id`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `progress`
--
ALTER TABLE `progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_progress` (`participant_id`,`module_id`);

--
-- Indexes for table `progress_tracking`
--
ALTER TABLE `progress_tracking`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_quizzes_lesson_id` (`lesson_id`);

--
-- Indexes for table `quiz_files`
--
ALTER TABLE `quiz_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_quizfiles_lesson` (`lesson_id`);

--
-- Indexes for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD PRIMARY KEY (`question_id`),
  ADD KEY `lesson_id` (`lesson_id`);

--
-- Indexes for table `quiz_results`
--
ALTER TABLE `quiz_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_quizresults_user` (`participant_id`),
  ADD KEY `fk_quizresults_lesson` (`lesson_id`);

--
-- Indexes for table `records`
--
ALTER TABLE `records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `registrations`
--
ALTER TABLE `registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `idx_email` (`email`);

--
-- Indexes for table `resources`
--
ALTER TABLE `resources`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_date_time` (`event_date`,`start_time`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staff_achievements`
--
ALTER TABLE `staff_achievements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ach_staff` (`staff_id`);

--
-- Indexes for table `staff_disaster_certifications`
--
ALTER TABLE `staff_disaster_certifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cert_staff` (`staff_id`);

--
-- Indexes for table `topics`
--
ALTER TABLE `topics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `topic_name` (`topic_name`);

--
-- Indexes for table `topic_categories`
--
ALTER TABLE `topic_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `training_completion`
--
ALTER TABLE `training_completion`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `training_modules`
--
ALTER TABLE `training_modules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_training_modules_prerequisite` (`prerequisite_id`),
  ADD KEY `idx_training_modules_disaster` (`disaster_type`),
  ADD KEY `idx_prerequisite` (`prerequisite_id`);

--
-- Indexes for table `training_programs`
--
ALTER TABLE `training_programs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `training_progress`
--
ALTER TABLE `training_progress`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_participant` (`participant_id`),
  ADD KEY `idx_module` (`module_id`);

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
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assessments`
--
ALTER TABLE `assessments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `drills`
--
ALTER TABLE `drills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `learning_objectives`
--
ALTER TABLE `learning_objectives`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lessons`
--
ALTER TABLE `lessons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `modules`
--
ALTER TABLE `modules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `module_categories`
--
ALTER TABLE `module_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `module_postings`
--
ALTER TABLE `module_postings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `participants`
--
ALTER TABLE `participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `participant_records`
--
ALTER TABLE `participant_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `progress`
--
ALTER TABLE `progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `progress_tracking`
--
ALTER TABLE `progress_tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `quiz_files`
--
ALTER TABLE `quiz_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  MODIFY `question_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `quiz_results`
--
ALTER TABLE `quiz_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `records`
--
ALTER TABLE `records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `registrations`
--
ALTER TABLE `registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `resources`
--
ALTER TABLE `resources`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `staff_achievements`
--
ALTER TABLE `staff_achievements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_disaster_certifications`
--
ALTER TABLE `staff_disaster_certifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `topics`
--
ALTER TABLE `topics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `topic_categories`
--
ALTER TABLE `topic_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `training_completion`
--
ALTER TABLE `training_completion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `training_modules`
--
ALTER TABLE `training_modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `training_programs`
--
ALTER TABLE `training_programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `training_progress`
--
ALTER TABLE `training_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `training_variables`
--
ALTER TABLE `training_variables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  ADD CONSTRAINT `assessment_questions_ibfk_1` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `learning_objectives`
--
ALTER TABLE `learning_objectives`
  ADD CONSTRAINT `fk_learning_objectives_module` FOREIGN KEY (`module_id`) REFERENCES `training_modules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `module_categories`
--
ALTER TABLE `module_categories`
  ADD CONSTRAINT `fk_module_categories_category` FOREIGN KEY (`category_id`) REFERENCES `topic_categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `module_postings`
--
ALTER TABLE `module_postings`
  ADD CONSTRAINT `module_postings_ibfk_1` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `module_staff_assignments`
--
ALTER TABLE `module_staff_assignments`
  ADD CONSTRAINT `module_staff_assignments_ibfk_1` FOREIGN KEY (`module_id`) REFERENCES `training_modules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `module_staff_assignments_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `participant_records`
--
ALTER TABLE `participant_records`
  ADD CONSTRAINT `participant_records_ibfk_1` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD CONSTRAINT `fk_quiz_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_quizzes_lessons` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_files`
--
ALTER TABLE `quiz_files`
  ADD CONSTRAINT `fk_quizfiles_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `quiz_questions` (`question_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD CONSTRAINT `quiz_questions_ibfk_1` FOREIGN KEY (`lesson_id`) REFERENCES `training_modules` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_results`
--
ALTER TABLE `quiz_results`
  ADD CONSTRAINT `fk_quizresults_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `quiz_questions` (`question_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_quizresults_user` FOREIGN KEY (`participant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `quiz_results_ibfk_1` FOREIGN KEY (`participant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `records`
--
ALTER TABLE `records`
  ADD CONSTRAINT `fk_records_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `staff_achievements`
--
ALTER TABLE `staff_achievements`
  ADD CONSTRAINT `fk_ach_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `staff_disaster_certifications`
--
ALTER TABLE `staff_disaster_certifications`
  ADD CONSTRAINT `fk_cert_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `training_modules`
--
ALTER TABLE `training_modules`
  ADD CONSTRAINT `fk_training_modules_prerequisite` FOREIGN KEY (`prerequisite_id`) REFERENCES `training_modules` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `training_progress`
--
ALTER TABLE `training_progress`
  ADD CONSTRAINT `fk_trainingprogress_module` FOREIGN KEY (`module_id`) REFERENCES `training_modules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_trainingprogress_participant` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
