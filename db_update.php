<?php
include "config/db.php";

echo "Starting Database Update...\n";

// 1. Ensure course_tests table has correct columns and remove UNIQUE key if it exists
echo "Checking course_tests table...\n";

// Add title column if not exists
$conn->query("ALTER TABLE `course_tests` ADD COLUMN IF NOT EXISTS `title` varchar(255) DEFAULT NULL AFTER `test_type` ");

// Check for old unique constraint and drop it
$idx_check = $conn->query("SHOW INDEX FROM `course_tests` WHERE Key_name = 'unique_course_test'");
if ($idx_check && $idx_check->num_rows > 0) {
    if ($conn->query("ALTER TABLE `course_tests` DROP INDEX `unique_course_test`")) {
        echo "Successfully dropped old unique constraint `unique_course_test`.\n";
    } else {
        echo "Error dropping constraint: " . $conn->error . "\n";
    }
}

$queries = [
    // 1. Table for Course Tests (Pre/Post) - Re-affirm table structure without UNIQUE KEY
    "CREATE TABLE IF NOT EXISTS `course_tests` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `course_id` int(11) NOT NULL,
        `test_type` enum('pre','post') NOT NULL,
        `title` varchar(255) DEFAULT NULL,
        `is_active` tinyint(1) DEFAULT 0,
        `time_limit_minutes` int(11) DEFAULT 0,
        `shuffle_questions` tinyint(1) DEFAULT 0,
        `shuffle_answers` tinyint(1) DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `course_id` (`course_id`),
        FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

    // 2. Table for Questions
    "CREATE TABLE IF NOT EXISTS `test_questions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `test_id` int(11) NOT NULL,
        `question_text` text NOT NULL,
        `points` int(11) DEFAULT 1,
        `order_index` int(11) DEFAULT 0,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`test_id`) REFERENCES `course_tests` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

    // 3. Table for Answers (Choices)
    "CREATE TABLE IF NOT EXISTS `test_answers` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `question_id` int(11) NOT NULL,
        `answer_text` text NOT NULL,
        `is_correct` tinyint(1) DEFAULT 0,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`question_id`) REFERENCES `test_questions` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

    // 4. Table for Student Attempts
    "CREATE TABLE IF NOT EXISTS `student_test_attempts` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` int(11) NOT NULL,
        `test_id` int(11) NOT NULL,
        `start_time` datetime DEFAULT NULL,
        `submit_time` datetime DEFAULT NULL,
        `score` int(11) DEFAULT 0,
        `total_points` int(11) DEFAULT 0,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        FOREIGN KEY (`test_id`) REFERENCES `course_tests` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

    // 5. Table for Student Answers (What they selected)
    "CREATE TABLE IF NOT EXISTS `student_test_answers` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `attempt_id` int(11) NOT NULL,
        `question_id` int(11) NOT NULL,
        `selected_answer_id` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`attempt_id`) REFERENCES `student_test_attempts` (`id`) ON DELETE CASCADE,
        FOREIGN KEY (`question_id`) REFERENCES `test_questions` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;"
];

foreach ($queries as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "Query executed successfully: " . substr($sql, 0, 50) . "...\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
}

echo "Database Update Completed.\n";
?>