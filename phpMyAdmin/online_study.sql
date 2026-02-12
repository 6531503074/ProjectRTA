-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 06, 2026 at 02:07 AM
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
-- Database: `online_study`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `course_id`, `content`, `created_at`) VALUES
(1, 1, 'Welcome to Web Programming class 🎉', '2026-01-06 05:37:31'),
(2, 1, 'Please read Chapter 1 before next class', '2026-01-06 05:37:31'),
(3, 2, 'Database project guidelines uploaded', '2026-01-06 05:37:31'),
(4, 3, 'First lecture slides are available now', '2026-01-06 05:37:31');

-- --------------------------------------------------------

--
-- Table structure for table `announcement_reads`
--

CREATE TABLE `announcement_reads` (
  `id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcement_reads`
--

INSERT INTO `announcement_reads` (`id`, `announcement_id`, `student_id`, `read_at`) VALUES
(10, 3, 1, '2026-01-07 06:30:51'),
(27, 3, 3, '2026-01-07 07:08:23'),
(47, 4, 1, '2026-01-07 07:26:16'),
(56, 1, 1, '2026-01-07 08:00:24'),
(57, 2, 1, '2026-01-07 08:00:24');

-- --------------------------------------------------------

--
-- Table structure for table `assignments`
--

CREATE TABLE `assignments` (
  `id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `title` varchar(100) DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assignments`
--

INSERT INTO `assignments` (`id`, `course_id`, `title`, `due_date`, `description`) VALUES
(1, 1, 'Build a simple PHP Login System', '2026-01-20 00:00:00', NULL),
(2, 1, 'HTML & CSS Layout Assignment', '2026-01-15 00:00:00', NULL),
(3, 2, 'Design ER Diagram for Library System', '2026-01-25 00:00:00', '1. Go to Draw.io'),
(4, 3, 'Write SDLC Report (5 pages)', '2026-01-30 00:00:00', NULL),
(23, 6, '1', '2026-01-16 15:10:00', ''),
(24, 6, 'lab1', '2026-01-17 11:59:00', '');

-- --------------------------------------------------------

--
-- Table structure for table `assignment_chat`
--

CREATE TABLE `assignment_chat` (
  `id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assignment_chat`
--

INSERT INTO `assignment_chat` (`id`, `assignment_id`, `user_id`, `message`, `created_at`) VALUES
(1, 3, 1, 'hi', '2026-01-07 06:58:39'),
(2, 3, 1, 'สวัสดี', '2026-01-07 06:58:57'),
(3, 3, 3, 'สวัสจากเปตอง', '2026-01-07 07:08:36'),
(4, 3, 3, 'สวัสดีจาดอาตี้', '2026-01-07 07:11:15'),
(5, 3, 3, 'ggg', '2026-01-07 07:23:23'),
(6, 3, 3, 'อาตี้ทำไร', '2026-01-08 05:03:07'),
(7, 3, 1, '.', '2026-01-08 05:03:13'),
(8, 3, 3, '.', '2026-01-08 05:09:21'),
(9, 3, 3, '1', '2026-01-08 05:09:59'),
(10, 3, 1, '2', '2026-01-08 05:10:02'),
(11, 3, 1, 'f', '2026-01-08 06:59:35'),
(12, 3, 1, 's', '2026-01-08 06:59:47'),
(13, 3, 3, '6', '2026-01-08 07:13:25'),
(41, 23, 6, 'hi', '2026-01-20 03:44:00'),
(42, 23, 5, 'ho', '2026-01-20 03:44:47');

-- --------------------------------------------------------

--
-- Table structure for table `assignment_chat_reads`
--

CREATE TABLE `assignment_chat_reads` (
  `id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `last_read_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assignment_chat_reads`
--

INSERT INTO `assignment_chat_reads` (`id`, `assignment_id`, `user_id`, `last_read_at`) VALUES
(244, 23, 6, '2026-01-22 08:37:55'),
(269, 23, 5, '2026-01-26 01:33:49'),
(583, 24, 5, '2026-01-26 01:33:51');

-- --------------------------------------------------------

--
-- Table structure for table `assignment_submissions`
--

CREATE TABLE `assignment_submissions` (
  `id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `submission_text` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `grade` varchar(10) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `content` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assignment_submissions`
--

INSERT INTO `assignment_submissions` (`id`, `assignment_id`, `student_id`, `submission_text`, `file_path`, `submitted_at`, `grade`, `feedback`, `content`) VALUES
(10, 23, 6, '', 'uploads/submissions/submission_6_23_1768543890.png', '2026-01-16 06:11:30', '100', '', NULL),
(11, 24, 6, '', 'uploads/submissions/submission_6_24_1768546370.pdf', '2026-01-16 06:52:50', '100', '', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `title` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `course_level` enum('ขั้นเริ่มต้น','ขั้นกลาง','ขั้นสูง') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `title`, `description`, `teacher_id`, `course_level`) VALUES
(1, 'Web Programming', 'Learn HTML, CSS, PHP, and MySQL', 2, 'ขั้นเริ่มต้น'),
(2, 'Database Systems', 'MySQL, ER Diagram, SQL Queries', 2, 'ขั้นเริ่มต้น'),
(3, 'Software Engineering', 'SDLC, Agile, UML Diagrams', 2, 'ขั้นเริ่มต้น'),
(5, 'IT', '', 5, 'ขั้นเริ่มต้น'),
(6, 'AI', '', 5, 'ขั้นเริ่มต้น'),
(7, 'Cyber', '', 5, 'ขั้นเริ่มต้น');

-- --------------------------------------------------------

--
-- Table structure for table `course_materials`
--

CREATE TABLE `course_materials` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_materials`
--

INSERT INTO `course_materials` (`id`, `course_id`, `title`, `file_path`, `file_size`, `uploaded_at`) VALUES
(2, 6, 'l', 'uploads/materials/696ee68fc477a_1768875663.pdf', 56866, '2026-01-20 02:21:03'),
(3, 6, '2', 'uploads/materials/696ee6b646dce_1768875702.pdf', 56866, '2026-01-20 02:21:42');

-- --------------------------------------------------------

--
-- Table structure for table `course_students`
--

CREATE TABLE `course_students` (
  `id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `student_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_students`
--

INSERT INTO `course_students` (`id`, `course_id`, `student_id`) VALUES
(1, 1, 1),
(2, 2, 1),
(3, 3, 1),
(4, 2, 3),
(5, 1, 4),
(6, 2, 4),
(7, 3, 4),
(26, 6, 1),
(27, 6, 3),
(28, 6, 4),
(29, 6, 6),
(30, 4, 1),
(31, 4, 3),
(32, 4, 4),
(33, 4, 6),
(34, 5, 1),
(35, 5, 3),
(36, 5, 4),
(37, 5, 6),
(38, 7, 1),
(39, 7, 3),
(40, 7, 6);

-- --------------------------------------------------------

--
-- Table structure for table `course_tests`
--

CREATE TABLE `course_tests` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `test_type` enum('pre','post') NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `time_limit_minutes` int(11) DEFAULT 0,
  `shuffle_questions` tinyint(1) DEFAULT 0,
  `shuffle_answers` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_tests`
--

INSERT INTO `course_tests` (`id`, `course_id`, `test_type`, `title`, `is_active`, `time_limit_minutes`, `shuffle_questions`, `shuffle_answers`, `created_at`) VALUES
(1, 6, 'pre', NULL, 1, 0, 0, 0, '2026-02-06 01:00:37');

-- --------------------------------------------------------

--
-- Table structure for table `group_chats`
--

CREATE TABLE `group_chats` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `group_chats`
--

INSERT INTO `group_chats` (`id`, `course_id`, `name`, `description`, `created_by`, `created_at`) VALUES
(5, 2, 'Group 1', 'lab 1', 1, '2026-01-08 01:23:16'),
(6, 2, 'Group 3', 'lab 2', 1, '2026-01-08 01:31:39'),
(7, 2, 'Group 3', 'lab 3', 3, '2026-01-08 02:17:57'),
(8, 2, 'Test leave', 'test', 1, '2026-01-08 07:13:53'),
(9, 6, 'กลุ่ม 1', '', 6, '2026-01-20 06:57:18');

-- --------------------------------------------------------

--
-- Table structure for table `group_chat_members`
--

CREATE TABLE `group_chat_members` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_read_message_id` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `group_chat_members`
--

INSERT INTO `group_chat_members` (`id`, `group_id`, `user_id`, `joined_at`, `last_read_message_id`) VALUES
(6, 5, 1, '2026-01-08 01:23:16', 0),
(7, 5, 3, '2026-01-08 01:27:21', 0),
(10, 7, 3, '2026-01-08 02:17:57', 0),
(11, 7, 1, '2026-01-08 02:18:30', 0),
(15, 9, 6, '2026-01-20 06:57:18', 0),
(16, 9, 5, '2026-01-20 06:57:38', 32);

-- --------------------------------------------------------

--
-- Table structure for table `group_chat_messages`
--

CREATE TABLE `group_chat_messages` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `group_chat_messages`
--

INSERT INTO `group_chat_messages` (`id`, `group_id`, `user_id`, `message`, `created_at`) VALUES
(7, 5, 1, 'hi', '2026-01-08 01:25:38'),
(8, 5, 3, 'hi', '2026-01-08 01:27:27'),
(9, 6, 3, 'hi arty', '2026-01-08 01:32:22'),
(10, 6, 1, 'hi petong', '2026-01-08 01:32:36'),
(11, 7, 3, 'hi', '2026-01-08 02:18:18'),
(12, 7, 1, 'hi', '2026-01-08 02:18:36'),
(13, 7, 1, 'l', '2026-01-08 05:09:36'),
(32, 9, 6, '33', '2026-01-20 08:09:26');

-- --------------------------------------------------------

--
-- Table structure for table `pinned_courses`
--

CREATE TABLE `pinned_courses` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_test_answers`
--

CREATE TABLE `student_test_answers` (
  `id` int(11) NOT NULL,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_answer_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_test_answers`
--

INSERT INTO `student_test_answers` (`id`, `attempt_id`, `question_id`, `selected_answer_id`) VALUES
(1, 1, 1, 2),
(2, 1, 2, 7),
(3, 1, 3, 11),
(4, 1, 4, 14),
(5, 1, 5, 17);

-- --------------------------------------------------------

--
-- Table structure for table `student_test_attempts`
--

CREATE TABLE `student_test_attempts` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `test_id` int(11) NOT NULL,
  `start_time` datetime DEFAULT NULL,
  `submit_time` datetime DEFAULT NULL,
  `score` int(11) DEFAULT 0,
  `total_points` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_test_attempts`
--

INSERT INTO `student_test_attempts` (`id`, `student_id`, `test_id`, `start_time`, `submit_time`, `score`, `total_points`) VALUES
(1, 6, 1, '2026-02-06 08:01:24', '2026-02-06 08:01:24', 5, 5);

-- --------------------------------------------------------

--
-- Table structure for table `test_answers`
--

CREATE TABLE `test_answers` (
  `id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer_text` text NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `test_answers`
--

INSERT INTO `test_answers` (`id`, `question_id`, `answer_text`, `is_correct`) VALUES
(1, 1, 'สาม', 0),
(2, 1, 'สี่', 1),
(3, 1, '5', 0),
(4, 1, '6', 0),
(5, 2, 'Earth', 0),
(6, 2, 'Venus', 0),
(7, 2, 'Mars', 1),
(8, 2, 'Jupiter', 0),
(9, 3, 'Seoul', 0),
(10, 3, 'Beijing', 0),
(11, 3, 'Tokyo', 1),
(12, 3, 'Bangkok', 0),
(13, 4, 'HTML', 0),
(14, 4, 'CSS', 1),
(15, 4, 'JavaScript', 0),
(16, 4, 'Python', 0),
(17, 5, '0', 1),
(18, 5, '32', 0),
(19, 5, '100', 0),
(20, 5, '-10', 0);

-- --------------------------------------------------------

--
-- Table structure for table `test_questions`
--

CREATE TABLE `test_questions` (
  `id` int(11) NOT NULL,
  `test_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `points` int(11) DEFAULT 1,
  `order_index` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `test_questions`
--

INSERT INTO `test_questions` (`id`, `test_id`, `question_text`, `points`, `order_index`) VALUES
(1, 1, '2 + 2 เท่ากับเท่าไหร่?', 1, 0),
(2, 1, 'Which planet is known as the Red Planet?Which planet is known as the Red Planet?Which planet is known as the Red Planet?Which planet is known as the Red Planet?Which planet is known as the Red Planet?Which planet is known as the Red Planet?Which planet is known as the Red Planet?', 1, 0),
(3, 1, 'What is the capital of Japan?', 1, 0),
(4, 1, 'Which language is primarily used for web page styling?', 1, 0),
(5, 1, 'Water freezes at what temperature (Celsius)?', 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `rank` varchar(100) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `affiliation` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('student','teacher','admin') NOT NULL,
  `courseLevel` varchar(100) DEFAULT NULL,
  `status` enum('inactive','active','','') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `rank`, `name`, `position`, `affiliation`, `phone`, `email`, `avatar`, `password`, `role`, `courseLevel`, `status`) VALUES
(1, 'นศท.', 'วิรศิลป์ ลิ้มธนเดชอนันต์', 'ฝึกงาน', 'กรซ.', '0994793969', '6531503074@lamduan.mfu.ac.th', 'uploads/avatars/695c804b3d0aa_1767669835.jfif', '$2y$10$eIt7X/krz1BAHBkB0oXQ6O4os4EzYCTUt0HJiafQ5pRoMV/dgKftu', 'student', '1', 'active'),
(2, 'admin', 'Admin', 'admin', 'admin', '099999999', 'admin@gmail.com', 'uploads/avatars/695c8136c1345_1767670070.jfif', '$2y$10$Jfl6OJmbUjG.qILC5ngSwuSJVAMzYMm6UpKZymsEiHZnLBeLVr.La', 'teacher', 'admin', 'active'),
(3, 'นศท.', 'อธิชา คำดี', 'PM', 'กรซ', '099789546', 'user2@gmail.com', 'uploads/avatars/695e05d611957_1767769558.jfif', '$2y$10$JV1Ex1eQTW65YzjhouBS6.F3pRLCI.WPs2vs1aPPrjbaXlNR7LOfC', 'student', '1', 'active'),
(5, 'พ.อ', 'แอนตัน ลี', 'IT', '', '', 'aton@gmail.com', 'uploads/avatars/69843f5d9e325_1770274653.jpg', '$2y$10$6DmMoCx8KXJS2gZntU1n4OSBns4iIvi1H8uSztoK1.pBV84I3xfgW', 'teacher', '3', 'active'),
(6, 'ร.อ', 'วอนบิน พัค', 'IT', '', '', 'wonbin@gmail.com', 'uploads/avatars/696857090aed1_1768445705.jpg', '$2y$10$RsQ.jebvgb8awobLP1UHoeFxZB2/FI4MNSl23QgJvlsr6wgfTr9t6', 'student', '1', 'active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `announcement_reads`
--
ALTER TABLE `announcement_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_read` (`announcement_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `assignment_chat`
--
ALTER TABLE `assignment_chat`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assignment_id` (`assignment_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `assignment_chat_reads`
--
ALTER TABLE `assignment_chat_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_read` (`assignment_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `assignment_submissions`
--
ALTER TABLE `assignment_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assignment_id` (`assignment_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `course_materials`
--
ALTER TABLE `course_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `course_students`
--
ALTER TABLE `course_students`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `course_tests`
--
ALTER TABLE `course_tests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_course_test` (`course_id`,`test_type`);

--
-- Indexes for table `group_chats`
--
ALTER TABLE `group_chats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `group_chat_members`
--
ALTER TABLE `group_chat_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_member` (`group_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `group_chat_messages`
--
ALTER TABLE `group_chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `pinned_courses`
--
ALTER TABLE `pinned_courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_pin` (`course_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `student_test_answers`
--
ALTER TABLE `student_test_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attempt_id` (`attempt_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `student_test_attempts`
--
ALTER TABLE `student_test_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `test_id` (`test_id`);

--
-- Indexes for table `test_answers`
--
ALTER TABLE `test_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `test_questions`
--
ALTER TABLE `test_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `test_id` (`test_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `announcement_reads`
--
ALTER TABLE `announcement_reads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=219;

--
-- AUTO_INCREMENT for table `assignments`
--
ALTER TABLE `assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `assignment_chat`
--
ALTER TABLE `assignment_chat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `assignment_chat_reads`
--
ALTER TABLE `assignment_chat_reads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=584;

--
-- AUTO_INCREMENT for table `assignment_submissions`
--
ALTER TABLE `assignment_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `course_materials`
--
ALTER TABLE `course_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `course_students`
--
ALTER TABLE `course_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `course_tests`
--
ALTER TABLE `course_tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `group_chats`
--
ALTER TABLE `group_chats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `group_chat_members`
--
ALTER TABLE `group_chat_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `group_chat_messages`
--
ALTER TABLE `group_chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `pinned_courses`
--
ALTER TABLE `pinned_courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `student_test_answers`
--
ALTER TABLE `student_test_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `student_test_attempts`
--
ALTER TABLE `student_test_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `test_answers`
--
ALTER TABLE `test_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `test_questions`
--
ALTER TABLE `test_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcement_reads`
--
ALTER TABLE `announcement_reads`
  ADD CONSTRAINT `announcement_reads_ibfk_1` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcement_reads_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assignment_chat`
--
ALTER TABLE `assignment_chat`
  ADD CONSTRAINT `assignment_chat_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assignment_chat_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assignment_chat_reads`
--
ALTER TABLE `assignment_chat_reads`
  ADD CONSTRAINT `assignment_chat_reads_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assignment_chat_reads_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assignment_submissions`
--
ALTER TABLE `assignment_submissions`
  ADD CONSTRAINT `assignment_submissions_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assignment_submissions_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `course_materials`
--
ALTER TABLE `course_materials`
  ADD CONSTRAINT `course_materials_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `course_tests`
--
ALTER TABLE `course_tests`
  ADD CONSTRAINT `course_tests_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `group_chats`
--
ALTER TABLE `group_chats`
  ADD CONSTRAINT `group_chats_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_chats_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `group_chat_members`
--
ALTER TABLE `group_chat_members`
  ADD CONSTRAINT `group_chat_members_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `group_chats` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_chat_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `group_chat_messages`
--
ALTER TABLE `group_chat_messages`
  ADD CONSTRAINT `group_chat_messages_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `group_chats` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_chat_messages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pinned_courses`
--
ALTER TABLE `pinned_courses`
  ADD CONSTRAINT `pinned_courses_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pinned_courses_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_test_answers`
--
ALTER TABLE `student_test_answers`
  ADD CONSTRAINT `student_test_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `student_test_attempts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_test_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `test_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_test_attempts`
--
ALTER TABLE `student_test_attempts`
  ADD CONSTRAINT `student_test_attempts_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_test_attempts_ibfk_2` FOREIGN KEY (`test_id`) REFERENCES `course_tests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `test_answers`
--
ALTER TABLE `test_answers`
  ADD CONSTRAINT `test_answers_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `test_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `test_questions`
--
ALTER TABLE `test_questions`
  ADD CONSTRAINT `test_questions_ibfk_1` FOREIGN KEY (`test_id`) REFERENCES `course_tests` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
