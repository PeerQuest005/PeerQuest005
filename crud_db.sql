-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 22, 2025 at 06:30 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `crud_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `achievement_tbl`
--

CREATE TABLE `achievement_tbl` (
  `achievement_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `badge_name` varchar(50) DEFAULT NULL,
  `badge_earned_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `achievement_tbl`
--

INSERT INTO `achievement_tbl` (`achievement_id`, `student_id`, `badge_name`, `badge_earned_date`) VALUES
(1, 8, '10 Modules Master', '2025-01-19'),
(2, 8, 'Assessment Beginner', '2025-01-19'),
(3, 8, 'Collaboration Novice', '2025-01-21'),
(4, 4, 'Assessment Beginner', '2025-01-22'),
(5, 4, 'Collaboration Novice', '2025-01-22'),
(6, 7, 'Assessment Beginner', '2025-01-22'),
(7, 7, 'Collaboration Novice', '2025-01-22');

-- --------------------------------------------------------

--
-- Table structure for table `answers_esy_tbl`
--

CREATE TABLE `answers_esy_tbl` (
  `essay_id` int(11) NOT NULL,
  `assessment_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer_text` varchar(500) DEFAULT NULL,
  `grade` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `Attempt` smallint(9) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `answers_mcq_collab_tbl`
--

CREATE TABLE `answers_mcq_collab_tbl` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `assessment_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_option` varchar(10) NOT NULL,
  `submitted_by` int(11) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `grades` int(11) NOT NULL,
  `attempt` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `answers_mcq_collab_tbl`
--

INSERT INTO `answers_mcq_collab_tbl` (`id`, `room_id`, `assessment_id`, `question_id`, `selected_option`, `submitted_by`, `submitted_at`, `grades`, `attempt`) VALUES
(445, 9305, 308, 109, 'A', 7, '2025-01-22 17:11:05', 5, 1),
(446, 9305, 308, 110, 'A', 7, '2025-01-22 17:11:06', 10, 1),
(447, 9305, 308, 111, 'A', 7, '2025-01-22 17:15:24', 0, 1);

-- --------------------------------------------------------

--
-- Table structure for table `answers_mcq_tbl`
--

CREATE TABLE `answers_mcq_tbl` (
  `answer_id` int(11) NOT NULL,
  `assessment_id` int(11) DEFAULT NULL,
  `question_id` int(11) DEFAULT NULL,
  `student_id` int(11) DEFAULT NULL,
  `selected_option` enum('A','B','C','D') DEFAULT NULL,
  `Attempt` smallint(9) NOT NULL DEFAULT 0,
  `correct_answer` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `answers_mcq_tbl`
--

INSERT INTO `answers_mcq_tbl` (`answer_id`, `assessment_id`, `question_id`, `student_id`, `selected_option`, `Attempt`, `correct_answer`) VALUES
(40, 304, 104, 8, 'A', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `answers_tf_tbl`
--

CREATE TABLE `answers_tf_tbl` (
  `true_false_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer_text` enum('True','False') NOT NULL,
  `assessment_id` int(11) NOT NULL,
  `correct_answer` tinyint(11) NOT NULL,
  `Attempt` int(11) DEFAULT 0,
  `student_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `answers_tf_tbl`
--

INSERT INTO `answers_tf_tbl` (`true_false_id`, `question_id`, `answer_text`, `assessment_id`, `correct_answer`, `Attempt`, `student_id`) VALUES
(49, 21, 'False', 309, 0, 1, 8),
(50, 22, 'True', 309, 0, 1, 8),
(51, 23, 'True', 309, 1, 1, 8),
(52, 24, 'True', 309, 1, 1, 8),
(53, 25, 'False', 309, 0, 1, 8),
(54, 21, 'True', 309, 1, 1, 7),
(55, 22, 'False', 309, 1, 1, 7),
(56, 23, 'True', 309, 1, 1, 7),
(57, 24, 'True', 309, 1, 1, 7),
(58, 25, 'True', 309, 1, 1, 7),
(59, 19, 'True', 303, 1, 1, 7),
(60, 20, 'True', 303, 1, 1, 7);

-- --------------------------------------------------------

--
-- Table structure for table `assessment_tbl`
--

CREATE TABLE `assessment_tbl` (
  `assessment_id` int(11) NOT NULL,
  `type` varchar(400) NOT NULL,
  `status` enum('Saved','Published') DEFAULT 'Saved',
  `time_limit` int(11) DEFAULT 10,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assessment_mode` varchar(50) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `teacher_id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `total_points` int(11) DEFAULT 0,
  `instructions` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assessment_tbl`
--

INSERT INTO `assessment_tbl` (`assessment_id`, `type`, `status`, `time_limit`, `created_at`, `assessment_mode`, `class_id`, `teacher_id`, `name`, `total_points`, `instructions`) VALUES
(302, 'Essay', 'Published', 10, '2025-01-17 15:07:02', 'Individual', 8, 3, 'essay', 2, ''),
(303, 'True or False', 'Published', 10, '2025-01-17 15:08:01', 'Individual', 8, 3, 'TF', 2, ''),
(304, 'Multiple Choice - Individual', 'Published', 10, '2025-01-17 15:08:25', 'Individual', 8, 3, 'mcq', 1, ''),
(308, 'Multiple Choice - Collaborative', 'Published', 10, '2025-01-22 00:05:12', 'Individual', 8, 3, 'mcq collab', 15, ''),
(309, 'True or False', 'Published', 10, '2025-01-22 05:15:43', 'Individual', 8, 3, 'Lesson 1 True False', 5, ''),
(310, 'Multiple Choice - Collaborative', 'Published', 10, '2025-01-22 14:24:04', 'Individual', 8, 3, 'cccccc', 4, '');

-- --------------------------------------------------------

--
-- Table structure for table `class_tbl`
--

CREATE TABLE `class_tbl` (
  `class_id` int(11) NOT NULL,
  `class_section` varchar(50) DEFAULT NULL,
  `class_subject` varchar(100) DEFAULT NULL,
  `class_code` varchar(20) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `student_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `class_tbl`
--

INSERT INTO `class_tbl` (`class_id`, `class_section`, `class_subject`, `class_code`, `teacher_id`, `student_id`) VALUES
(2, 'WD-401', 'CAPSTONE1', 'BD9706', 2, NULL),
(3, 'CYB-201', 'ETHICAL-HACKING', '1DE8C2', 2, 1),
(4, 'WD-401', 'CLOUDCOMP', '11A735', 2, NULL),
(5, 'WD-302', 'INFOASEC', 'DBCB24', 2, NULL),
(8, 'section121', 'subject121', '35BFD2', 3, NULL),
(9, 'Section202', 'Subject202', '9C6180', 3, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `leaderboard_tbl`
--

CREATE TABLE `leaderboard_tbl` (
  `leaderboard_id` int(11) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `student_id` int(11) DEFAULT NULL,
  `leaderboard_ranking` int(11) DEFAULT NULL,
  `leaderboard_points` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `modules_tbl`
--

CREATE TABLE `modules_tbl` (
  `module_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(255) NOT NULL DEFAULT 'Saved'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `modules_tbl`
--

INSERT INTO `modules_tbl` (`module_id`, `class_id`, `teacher_id`, `title`, `content`, `file_name`, `file_path`, `created_at`, `status`) VALUES
(16, 8, 3, 'Title ', 'Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry\'s standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.\r\n\r\nWhy do we use it?\r\nIt is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout. The point of using Lorem Ipsum is that it has a more-or-less normal distribution of letters, as opposed to using \'Content here, content here\', making it look like readable English. Many desktop publishing packages and web page editors now use Lorem Ipsum as their default model text, and a search for \'lorem ipsum\' will uncover many web sites still in their infancy. Various versions have evolved over the years, sometimes by accident, sometimes on purpose (injected humour and the like).', NULL, NULL, '2025-01-15 11:53:12', 'Saved'),
(17, 8, 3, 'a', 'a', NULL, NULL, '2025-01-17 08:45:04', 'Published'),
(18, 8, 3, 'a', 'a', NULL, NULL, '2025-01-17 08:45:08', 'Saved');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tbl`
--

CREATE TABLE `password_reset_tbl` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `user_type` enum('student','teacher') NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_reset_tbl`
--

INSERT INTO `password_reset_tbl` (`id`, `email`, `user_type`, `token`, `expires_at`) VALUES
(1, 'allencarlo32@gmail.com', 'student', '5c79807960c636711742fd03aaaa76d38241fe83d19326a1b42189502a21f723', '2025-01-22 03:54:36'),
(2, 'allencarlo32@gmail.com', 'student', '28deabf936f6b64ec4bc2fc3ccc0c9f3a42e5e55614111c8d6ad93d6a2396134', '2025-01-22 03:56:40'),
(3, 'allencarlo32@gmail.com', 'student', '42ece1e226a4739d4b5aaeb9a3c101aa21025be08483d4befa04f16412cf7434', '2025-01-22 03:56:56');

-- --------------------------------------------------------

--
-- Table structure for table `questions_esy_tbl`
--

CREATE TABLE `questions_esy_tbl` (
  `question_id` int(11) NOT NULL,
  `assessment_id` int(11) NOT NULL,
  `question_text` varchar(255) NOT NULL,
  `question_number` int(11) NOT NULL,
  `points` int(11) DEFAULT 0,
  `guided_answer` text DEFAULT NULL,
  `correct_answer` varchar(5) NOT NULL DEFAULT 'True'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `questions_esy_tbl`
--

INSERT INTO `questions_esy_tbl` (`question_id`, `assessment_id`, `question_text`, `question_number`, `points`, `guided_answer`, `correct_answer`) VALUES
(168, 302, 'a', 0, 1, '1', 'True'),
(169, 302, '1', 0, 1, '1', 'True');

-- --------------------------------------------------------

--
-- Table structure for table `questions_mcq_collab_tbl`
--

CREATE TABLE `questions_mcq_collab_tbl` (
  `question_id` int(11) NOT NULL,
  `assessment_id` int(11) NOT NULL,
  `question_text` varchar(255) NOT NULL,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`options`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `questions_mcq_tbl`
--

CREATE TABLE `questions_mcq_tbl` (
  `question_id` int(11) NOT NULL,
  `assessment_id` int(11) DEFAULT NULL,
  `question_text` varchar(500) DEFAULT NULL,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `correct_option` enum('A','B','C','D') DEFAULT NULL,
  `points` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `questions_mcq_tbl`
--

INSERT INTO `questions_mcq_tbl` (`question_id`, `assessment_id`, `question_text`, `options`, `correct_option`, `points`) VALUES
(104, 304, 'a', '{\"A\":\"a\",\"B\":\"a\",\"C\":\"a\",\"D\":\"a\"}', 'A', 1),
(109, 308, 'q1', '{\"A\":\"a\",\"B\":\"a\",\"C\":\"a\",\"D\":\"a\"}', 'A', 5),
(110, 308, 'q2', '{\"A\":\"a\",\"B\":\"a\",\"C\":\"a\",\"D\":\"a\"}', 'A', 10),
(111, 308, 'q3', '{\"A\":\"a\",\"B\":\"a\",\"C\":\"a\",\"D\":\"a\"}', 'A', 0),
(112, 310, 'q1', '{\"A\":\"a\",\"B\":\"a\",\"C\":\"a\",\"D\":\"a\"}', 'A', 2),
(113, 310, 'q2', '{\"A\":\"d\",\"B\":\"d\",\"C\":\"d\",\"D\":\"d\"}', 'A', 2);

-- --------------------------------------------------------

--
-- Table structure for table `questions_reci_tbl`
--

CREATE TABLE `questions_reci_tbl` (
  `question_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `assessment_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `revealed_student_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `questions_tf_tbl`
--

CREATE TABLE `questions_tf_tbl` (
  `question_id` int(11) NOT NULL,
  `assessment_id` int(11) NOT NULL,
  `question_text` varchar(255) NOT NULL,
  `question_number` int(11) NOT NULL,
  `points` int(11) NOT NULL DEFAULT 0,
  `guided_answer` text DEFAULT NULL,
  `correct_answer` varchar(5) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `questions_tf_tbl`
--

INSERT INTO `questions_tf_tbl` (`question_id`, `assessment_id`, `question_text`, `question_number`, `points`, `guided_answer`, `correct_answer`) VALUES
(19, 303, '1', 0, 1, NULL, 'True'),
(20, 303, 'a', 0, 1, NULL, 'True'),
(21, 309, 'q1', 0, 1, NULL, 'True'),
(22, 309, 'q2', 0, 1, NULL, 'False'),
(23, 309, 'q3', 0, 1, NULL, 'True'),
(24, 309, 'q3', 0, 1, NULL, 'True'),
(25, 309, 'q5', 0, 1, NULL, 'True');

-- --------------------------------------------------------

--
-- Table structure for table `room_ready_tbl`
--

CREATE TABLE `room_ready_tbl` (
  `collab_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `assessment_id` int(11) NOT NULL,
  `is_ready` tinyint(1) DEFAULT 0,
  `is_host` tinyint(1) DEFAULT 0,
  `status` enum('waiting','started') DEFAULT 'waiting'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_ready_tbl`
--

INSERT INTO `room_ready_tbl` (`collab_id`, `room_id`, `student_id`, `assessment_id`, `is_ready`, `is_host`, `status`) VALUES
(1, 2476, 7, 292, 1, 1, 'started'),
(2, 2476, 8, 292, 1, 0, 'started'),
(3, 4458, 7, 292, 0, 1, 'waiting'),
(4, 9727, 8, 292, 0, 1, 'waiting'),
(5, 6384, 7, 290, 0, 1, 'waiting'),
(6, 6233, 7, 290, 1, 1, 'started'),
(7, 2300, 8, 292, 0, 1, 'waiting'),
(8, 6233, 4, 290, 1, 0, 'started'),
(9, 6254, 8, 301, 1, 1, 'started'),
(10, 6254, 7, 301, 1, 0, 'started'),
(11, 6448, 8, 301, 1, 1, 'started'),
(12, 6448, 7, 301, 1, 0, 'started'),
(13, 8098, 8, 301, 1, 1, 'started'),
(14, 6999, 8, 301, 1, 1, 'started'),
(15, 1197, 8, 301, 1, 1, 'started'),
(16, 1264, 8, 301, 1, 1, 'started'),
(17, 4260, 8, 301, 1, 1, 'started'),
(18, 3268, 8, 301, 1, 1, 'started'),
(19, 8749, 8, 301, 1, 1, 'started'),
(20, 2282, 7, 301, 1, 1, 'started'),
(21, 5293, 8, 301, 1, 1, 'started'),
(22, 4142, 8, 301, 1, 1, 'started'),
(23, 3552, 8, 301, 1, 1, 'started'),
(24, 9311, 8, 301, 1, 1, 'started'),
(25, 2756, 7, 301, 1, 1, 'started'),
(26, 7680, 7, 301, 1, 1, 'started'),
(27, 5580, 8, 301, 1, 1, 'started'),
(28, 7976, 8, 301, 1, 1, 'started'),
(29, 9217, 8, 301, 1, 1, 'started'),
(30, 2376, 8, 301, 1, 1, 'started'),
(31, 9197, 8, 301, 1, 1, 'started'),
(32, 9447, 8, 301, 1, 1, 'started'),
(33, 6966, 8, 301, 1, 1, 'started'),
(34, 9883, 7, 301, 1, 1, 'started'),
(35, 1178, 8, 306, 0, 1, 'waiting'),
(36, 8116, 8, 306, 1, 1, 'started'),
(37, 7845, 4, 306, 1, 1, 'started'),
(38, 7845, 7, 306, 1, 0, 'started'),
(39, 5953, 4, 306, 1, 1, 'started'),
(40, 4371, 4, 307, 1, 1, 'started'),
(41, 4371, 7, 307, 1, 0, 'started'),
(42, 4891, 4, 307, 1, 1, 'started'),
(43, 4891, 7, 307, 1, 0, 'started'),
(44, 2463, 4, 307, 1, 1, 'started'),
(45, 2463, 7, 307, 1, 0, 'started'),
(46, 2072, 4, 307, 1, 1, 'started'),
(47, 2072, 7, 307, 1, 0, 'started'),
(48, 6952, 4, 307, 0, 1, 'waiting'),
(49, 7105, 4, 307, 1, 1, 'waiting'),
(50, 7105, 7, 307, 1, 0, 'waiting'),
(51, 8179, 4, 307, 0, 1, 'waiting'),
(52, 6556, 7, 307, 0, 1, 'started'),
(53, 5243, 7, 307, 0, 1, 'waiting'),
(54, 5243, 4, 307, 1, 0, 'waiting'),
(55, 8543, 4, 307, 1, 1, 'waiting'),
(56, 8543, 7, 307, 1, 0, 'waiting'),
(57, 9548, 4, 307, 1, 1, 'waiting'),
(58, 9548, 7, 307, 0, 0, 'waiting'),
(59, 5368, 4, 307, 0, 1, 'waiting'),
(60, 5368, 7, 307, 0, 0, 'waiting'),
(61, 5336, 8, 307, 1, 1, 'waiting'),
(62, 8817, 7, 307, 0, 1, 'waiting'),
(63, 5336, 7, 307, 1, 0, 'waiting'),
(64, 5336, 7, 307, 0, 0, 'waiting'),
(65, 4335, 8, 307, 1, 1, 'started'),
(66, 4335, 7, 307, 1, 0, 'started'),
(67, 8721, 8, 307, 1, 1, 'started'),
(68, 8721, 7, 307, 1, 0, 'started'),
(69, 8721, 10, 307, 1, 0, 'started'),
(70, 8721, 4, 307, 1, 0, 'started'),
(71, 4368, 8, 306, 1, 1, 'started'),
(72, 3684, 7, 306, 0, 1, 'waiting'),
(73, 8812, 10, 306, 0, 1, 'waiting'),
(74, 4368, 10, 306, 1, 0, 'started'),
(75, 4368, 7, 306, 1, 0, 'started'),
(76, 4368, 4, 306, 1, 0, 'started'),
(77, 6412, 8, 307, 1, 1, 'started'),
(78, 6412, 4, 307, 1, 0, 'started'),
(79, 6412, 10, 307, 1, 0, 'started'),
(80, 6412, 7, 307, 1, 0, 'started'),
(81, 4501, 8, 306, 1, 1, 'started'),
(82, 4501, 7, 306, 1, 0, 'started'),
(83, 4501, 4, 306, 1, 0, 'started'),
(84, 4501, 10, 306, 1, 0, 'started'),
(85, 5693, 7, 307, 1, 1, 'waiting'),
(86, 5693, 4, 307, 1, 0, 'waiting'),
(87, 5693, 8, 307, 1, 0, 'waiting'),
(88, 5693, 10, 307, 1, 0, 'waiting'),
(89, 6774, 8, 307, 1, 1, 'started'),
(90, 6774, 7, 307, 1, 0, 'started'),
(91, 6774, 4, 307, 1, 0, 'started'),
(92, 6774, 10, 307, 1, 0, 'started'),
(93, 8164, 8, 307, 0, 1, 'waiting'),
(94, 5061, 8, 307, 1, 1, 'started'),
(95, 5061, 8, 307, 1, 0, 'started'),
(96, 5061, 4, 307, 1, 0, 'waiting'),
(97, 2535, 8, 307, 1, 1, 'started'),
(98, 2535, 7, 307, 1, 0, 'started'),
(99, 4556, 8, 307, 1, 1, 'started'),
(100, 7588, 8, 307, 1, 1, 'started'),
(101, 1265, 8, 307, 1, 1, 'started'),
(102, 4162, 8, 307, 1, 1, 'started'),
(103, 2957, 8, 307, 1, 1, 'started'),
(104, 3558, 7, 308, 1, 1, 'started'),
(105, 4811, 7, 308, 1, 1, 'started'),
(106, 8526, 7, 310, 1, 1, 'started'),
(107, 8526, 4, 310, 1, 0, 'started'),
(108, 8526, 10, 310, 1, 0, 'started'),
(109, 8526, 8, 310, 1, 0, 'started'),
(110, 1349, 7, 308, 1, 1, 'started'),
(111, 6966, 7, 310, 0, 1, 'waiting'),
(112, 8115, 7, 310, 1, 1, 'started'),
(113, 5651, 7, 310, 1, 1, 'started'),
(114, 9496, 7, 308, 1, 1, 'started'),
(115, 4406, 7, 310, 1, 1, 'started'),
(116, 4406, 10, 310, 1, 0, 'started'),
(117, 2123, 7, 310, 1, 1, 'started'),
(118, 5306, 7, 310, 1, 1, 'started'),
(119, 1120, 7, 310, 1, 1, 'started'),
(120, 4942, 7, 308, 1, 1, 'started'),
(121, 2824, 7, 308, 1, 1, 'started'),
(122, 5653, 7, 308, 1, 1, 'started'),
(123, 7082, 7, 308, 1, 1, 'started'),
(124, 6098, 7, 310, 1, 1, 'started'),
(125, 6098, 4, 310, 1, 0, 'started'),
(126, 4126, 7, 310, 1, 1, 'started'),
(127, 1099, 7, 308, 1, 1, 'started'),
(128, 4410, 7, 308, 1, 1, 'started'),
(129, 9305, 7, 308, 1, 1, 'started');

-- --------------------------------------------------------

--
-- Table structure for table `student_classes`
--

CREATE TABLE `student_classes` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `status` varchar(50) DEFAULT NULL,
  `achievements` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_classes`
--

INSERT INTO `student_classes` (`id`, `student_id`, `class_id`, `status`, `achievements`) VALUES
(1, 4, 5, NULL, NULL),
(3, 4, 3, NULL, NULL),
(4, 1, 5, NULL, NULL),
(5, 4, 2, NULL, NULL),
(6, 7, 8, NULL, NULL),
(7, 4, 8, NULL, NULL),
(8, 8, 8, NULL, NULL),
(9, 7, 9, NULL, NULL),
(10, 8, 9, NULL, NULL),
(11, 10, 8, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `student_tbl`
--

CREATE TABLE `student_tbl` (
  `student_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `student_email` varchar(100) DEFAULT NULL,
  `student_password` varchar(255) DEFAULT NULL,
  `student_first_name` varchar(100) NOT NULL,
  `student_last_name` varchar(100) NOT NULL,
  `ach_last_login` date NOT NULL,
  `ach_streak` int(11) NOT NULL,
  `ach_modules_read` int(11) NOT NULL,
  `ach_answered_assessments` int(11) NOT NULL,
  `ach_collaborated` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_tbl`
--

INSERT INTO `student_tbl` (`student_id`, `username`, `student_email`, `student_password`, `student_first_name`, `student_last_name`, `ach_last_login`, `ach_streak`, `ach_modules_read`, `ach_answered_assessments`, `ach_collaborated`) VALUES
(1, 'cg18', 'cg18@gmail.com', '$2y$10$1YZZE5SAx7DjuIXnL1Zy2ufObrhMCc/fkOXx7IPX3SDk4whjNWfEK', 'Crisha', 'Hernandez', '0000-00-00', 0, 0, 0, 0),
(2, 'Princes123', 'cgm1@gmail.com', '$2y$10$vywHgfkgjZ17aqwQWcLkP.CKL3HAfdzyCqFvzYLPxQ4UW5B.NFJQW', 'Princess', 'Liu', '0000-00-00', 0, 0, 0, 0),
(4, 'rose1', 'rose1@gmail.com', '$2y$10$64vLb2.JwyNvp0vtDW9bK.S74MTtC0QW9S8lcME6YNWnWWwuFqwdK', 'Rose', 'Tiu', '0000-00-00', 0, 0, 13, 56),
(6, 'Rose23', 'rose23@gmail.com', '$2y$10$XKsIR1wHzwwHkO.0zey81ONHozfLNGaJ/QE0MtXa0W34vaGShiuiW', 'Rosalyn', 'Kira', '0000-00-00', 0, 0, 0, 0),
(7, 'student1', 's1@gmail.com', '$2y$10$f9Ds7a79L/l1vzE0T0jD5.oXtuliBUbJSjlB6jjPaTjwZKhX.4MFe', 'Student1', 'Student1', '2025-01-17', 0, 8, 34, 148),
(8, 'student', 'wdadwafawfWQd@gmail.com', '$2y$10$fEKVJGfM7i5CbZnJe0ipMuvAYZw/2DkgU5KFYGWhB/eU4UCNtut0W', 'student', 'student', '2025-01-21', 0, 14, 40, 121),
(9, 'cccc', 'ccc@gmail.com', '$2y$10$CHnOEZ8sMjTl6Idm5OcrZ.sFZiFLN8E4paEvP7M9JdyaXEC6kM5kS', 'cccc', 'cccc', '0000-00-00', 0, 0, 0, 0),
(10, 'aaa', 'allencarlo32@gmail.com', '$2y$10$llMpxGNNzopHVeHyszFDx.20E9JDYIHQAtVC6ghKugQ0Rz6qbM3iG', 'aaa', 'aaa', '0000-00-00', 0, 0, 6, 27),
(11, 'studentstudent', 'studentstudent@gmail.com', '$2y$10$ZdBALYnSx6DnBdjnIwKxF.y9aqE8/dR/ziwQlx6rnsFwTX0BMI/Pu', 'studentstudent', 'studentstudent', '0000-00-00', 0, 0, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `teacher_tbl`
--

CREATE TABLE `teacher_tbl` (
  `teacher_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `teacher_email` varchar(100) DEFAULT NULL,
  `teacher_password` varchar(255) DEFAULT NULL,
  `teacher_first_name` varchar(100) NOT NULL,
  `teacher_last_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_tbl`
--

INSERT INTO `teacher_tbl` (`teacher_id`, `username`, `teacher_email`, `teacher_password`, `teacher_first_name`, `teacher_last_name`) VALUES
(1, 'Ms. Max', 'max11@gmail.com', '$2y$10$apvidvIovp0Of8coFfAr3.i2uFNcT3Omkd48RPL2QGtrUHL8.ytA6', 'Maxine', 'Lopez'),
(2, 'Ms.Joanne', 'joanneg13@gmail.com', '$2y$10$dU5quQFjjt9k4gFwbVibwesgzss/.rCPfNIF2JVNgKlm.lfXQg8Sq', 'Joanne', 'Galang'),
(3, 'teacher1', 't1@gmail.com', '$2y$10$yaaBsbYAkLklkcHVWzAB6eKhoIAQrE3NVTuF6oibMR0Aq.JhzOkQ.', 'Teacher1', 'Teacher1');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `achievement_tbl`
--
ALTER TABLE `achievement_tbl`
  ADD PRIMARY KEY (`achievement_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `answers_esy_tbl`
--
ALTER TABLE `answers_esy_tbl`
  ADD PRIMARY KEY (`essay_id`),
  ADD KEY `fk_questions_esy_to_answers_esy` (`question_id`),
  ADD KEY `fk_assessment_to_answers_esy` (`assessment_id`);

--
-- Indexes for table `answers_mcq_collab_tbl`
--
ALTER TABLE `answers_mcq_collab_tbl`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_assessment_id` (`assessment_id`),
  ADD KEY `fk_submitted_by` (`submitted_by`);

--
-- Indexes for table `answers_mcq_tbl`
--
ALTER TABLE `answers_mcq_tbl`
  ADD PRIMARY KEY (`answer_id`),
  ADD KEY `assessment_id` (`assessment_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `answers_tf_tbl`
--
ALTER TABLE `answers_tf_tbl`
  ADD PRIMARY KEY (`true_false_id`),
  ADD KEY `fk_questions_tf_to_answers_tf` (`question_id`),
  ADD KEY `fk_assessment_to_answers_tf` (`assessment_id`);

--
-- Indexes for table `assessment_tbl`
--
ALTER TABLE `assessment_tbl`
  ADD PRIMARY KEY (`assessment_id`),
  ADD KEY `class_id` (`class_id`);

--
-- Indexes for table `class_tbl`
--
ALTER TABLE `class_tbl`
  ADD PRIMARY KEY (`class_id`),
  ADD UNIQUE KEY `class_code` (`class_code`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `leaderboard_tbl`
--
ALTER TABLE `leaderboard_tbl`
  ADD PRIMARY KEY (`leaderboard_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `modules_tbl`
--
ALTER TABLE `modules_tbl`
  ADD PRIMARY KEY (`module_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `password_reset_tbl`
--
ALTER TABLE `password_reset_tbl`
  ADD PRIMARY KEY (`id`),
  ADD KEY `token` (`token`);

--
-- Indexes for table `questions_esy_tbl`
--
ALTER TABLE `questions_esy_tbl`
  ADD PRIMARY KEY (`question_id`),
  ADD KEY `fk_assessment_to_questions_esy` (`assessment_id`);

--
-- Indexes for table `questions_mcq_collab_tbl`
--
ALTER TABLE `questions_mcq_collab_tbl`
  ADD PRIMARY KEY (`question_id`);

--
-- Indexes for table `questions_mcq_tbl`
--
ALTER TABLE `questions_mcq_tbl`
  ADD PRIMARY KEY (`question_id`),
  ADD KEY `assessment_id` (`assessment_id`);

--
-- Indexes for table `questions_reci_tbl`
--
ALTER TABLE `questions_reci_tbl`
  ADD PRIMARY KEY (`question_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `assessment_id` (`assessment_id`);

--
-- Indexes for table `questions_tf_tbl`
--
ALTER TABLE `questions_tf_tbl`
  ADD PRIMARY KEY (`question_id`),
  ADD KEY `fk_assessment_to_questions_tf` (`assessment_id`);

--
-- Indexes for table `room_ready_tbl`
--
ALTER TABLE `room_ready_tbl`
  ADD PRIMARY KEY (`collab_id`);

--
-- Indexes for table `student_classes`
--
ALTER TABLE `student_classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `class_id` (`class_id`);

--
-- Indexes for table `student_tbl`
--
ALTER TABLE `student_tbl`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `unique_student_username` (`username`),
  ADD UNIQUE KEY `student_email` (`student_email`),
  ADD UNIQUE KEY `unique_student_email` (`student_email`);

--
-- Indexes for table `teacher_tbl`
--
ALTER TABLE `teacher_tbl`
  ADD PRIMARY KEY (`teacher_id`),
  ADD UNIQUE KEY `unique_teacher_username` (`username`),
  ADD UNIQUE KEY `teacher_email` (`teacher_email`),
  ADD UNIQUE KEY `unique_teacher_email` (`teacher_email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `achievement_tbl`
--
ALTER TABLE `achievement_tbl`
  MODIFY `achievement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `answers_esy_tbl`
--
ALTER TABLE `answers_esy_tbl`
  MODIFY `essay_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `answers_mcq_collab_tbl`
--
ALTER TABLE `answers_mcq_collab_tbl`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=448;

--
-- AUTO_INCREMENT for table `answers_mcq_tbl`
--
ALTER TABLE `answers_mcq_tbl`
  MODIFY `answer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `answers_tf_tbl`
--
ALTER TABLE `answers_tf_tbl`
  MODIFY `true_false_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `assessment_tbl`
--
ALTER TABLE `assessment_tbl`
  MODIFY `assessment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=311;

--
-- AUTO_INCREMENT for table `class_tbl`
--
ALTER TABLE `class_tbl`
  MODIFY `class_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `modules_tbl`
--
ALTER TABLE `modules_tbl`
  MODIFY `module_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `password_reset_tbl`
--
ALTER TABLE `password_reset_tbl`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `questions_esy_tbl`
--
ALTER TABLE `questions_esy_tbl`
  MODIFY `question_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=170;

--
-- AUTO_INCREMENT for table `questions_mcq_collab_tbl`
--
ALTER TABLE `questions_mcq_collab_tbl`
  MODIFY `question_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `questions_mcq_tbl`
--
ALTER TABLE `questions_mcq_tbl`
  MODIFY `question_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=114;

--
-- AUTO_INCREMENT for table `questions_reci_tbl`
--
ALTER TABLE `questions_reci_tbl`
  MODIFY `question_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `questions_tf_tbl`
--
ALTER TABLE `questions_tf_tbl`
  MODIFY `question_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `room_ready_tbl`
--
ALTER TABLE `room_ready_tbl`
  MODIFY `collab_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=130;

--
-- AUTO_INCREMENT for table `student_classes`
--
ALTER TABLE `student_classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `student_tbl`
--
ALTER TABLE `student_tbl`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `teacher_tbl`
--
ALTER TABLE `teacher_tbl`
  MODIFY `teacher_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `achievement_tbl`
--
ALTER TABLE `achievement_tbl`
  ADD CONSTRAINT `achievement_tbl_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student_tbl` (`student_id`);

--
-- Constraints for table `answers_esy_tbl`
--
ALTER TABLE `answers_esy_tbl`
  ADD CONSTRAINT `fk_assessment_to_answers_esy` FOREIGN KEY (`assessment_id`) REFERENCES `assessment_tbl` (`assessment_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_questions_esy_to_answers_esy` FOREIGN KEY (`question_id`) REFERENCES `questions_esy_tbl` (`question_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `answers_mcq_collab_tbl`
--
ALTER TABLE `answers_mcq_collab_tbl`
  ADD CONSTRAINT `fk_assessment_id` FOREIGN KEY (`assessment_id`) REFERENCES `assessment_tbl` (`assessment_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `student_tbl` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `answers_mcq_tbl`
--
ALTER TABLE `answers_mcq_tbl`
  ADD CONSTRAINT `answers_mcq_tbl_ibfk_1` FOREIGN KEY (`assessment_id`) REFERENCES `assessment_tbl` (`assessment_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `answers_mcq_tbl_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions_mcq_tbl` (`question_id`) ON DELETE CASCADE;

--
-- Constraints for table `answers_tf_tbl`
--
ALTER TABLE `answers_tf_tbl`
  ADD CONSTRAINT `fk_assessment_to_answers_tf` FOREIGN KEY (`assessment_id`) REFERENCES `assessment_tbl` (`assessment_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_questions_tf_to_answers_tf` FOREIGN KEY (`question_id`) REFERENCES `questions_tf_tbl` (`question_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `assessment_tbl`
--
ALTER TABLE `assessment_tbl`
  ADD CONSTRAINT `assessment_tbl_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `class_tbl` (`class_id`);

--
-- Constraints for table `class_tbl`
--
ALTER TABLE `class_tbl`
  ADD CONSTRAINT `class_tbl_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teacher_tbl` (`teacher_id`),
  ADD CONSTRAINT `class_tbl_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `student_tbl` (`student_id`);

--
-- Constraints for table `leaderboard_tbl`
--
ALTER TABLE `leaderboard_tbl`
  ADD CONSTRAINT `leaderboard_tbl_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `class_tbl` (`class_id`),
  ADD CONSTRAINT `leaderboard_tbl_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `student_tbl` (`student_id`);

--
-- Constraints for table `modules_tbl`
--
ALTER TABLE `modules_tbl`
  ADD CONSTRAINT `modules_tbl_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `class_tbl` (`class_id`),
  ADD CONSTRAINT `modules_tbl_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `teacher_tbl` (`teacher_id`);

--
-- Constraints for table `questions_esy_tbl`
--
ALTER TABLE `questions_esy_tbl`
  ADD CONSTRAINT `fk_assessment_to_questions_esy` FOREIGN KEY (`assessment_id`) REFERENCES `assessment_tbl` (`assessment_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `questions_mcq_tbl`
--
ALTER TABLE `questions_mcq_tbl`
  ADD CONSTRAINT `questions_mcq_tbl_ibfk_1` FOREIGN KEY (`assessment_id`) REFERENCES `assessment_tbl` (`assessment_id`) ON DELETE CASCADE;

--
-- Constraints for table `questions_reci_tbl`
--
ALTER TABLE `questions_reci_tbl`
  ADD CONSTRAINT `questions_reci_tbl_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `class_tbl` (`class_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `questions_reci_tbl_ibfk_2` FOREIGN KEY (`assessment_id`) REFERENCES `assessment_tbl` (`assessment_id`) ON DELETE CASCADE;

--
-- Constraints for table `questions_tf_tbl`
--
ALTER TABLE `questions_tf_tbl`
  ADD CONSTRAINT `fk_assessment_to_questions_tf` FOREIGN KEY (`assessment_id`) REFERENCES `assessment_tbl` (`assessment_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `student_classes`
--
ALTER TABLE `student_classes`
  ADD CONSTRAINT `student_classes_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student_tbl` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_classes_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `class_tbl` (`class_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
