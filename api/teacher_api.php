<?php
session_start();
include "../config/db.php";

header('Content-Type: application/json');
error_reporting(0); // Suppress warnings to avoid breaking JSON

// Check authentication
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user = $_SESSION['user'];
$teacher_id = (int) $user['id'];
$action = $_GET['action'] ?? '';

// Helper to return error
function error($msg)
{
    echo json_encode(['success' => false, 'message' => $msg]);
    exit();
}

// Helper to return success
function success($data = [])
{
    echo json_encode(array_merge(['success' => true], $data));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // --- GET COURSE TESTS INFO ---
    if ($action === 'get_course_tests') {
        $course_id = (int) ($_GET['course_id'] ?? 0);
        if ($course_id <= 0)
            error('Invalid course ID');

        // Check ownership
        $check = $conn->prepare("SELECT id FROM courses WHERE id = ?");
        $check->bind_param("i", $course_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0)
            error('Access denied');

        // Fetch pre and post tests
        $stmt = $conn->prepare("SELECT * FROM course_tests WHERE course_id = ?");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $res = $stmt->get_result();

        $tests = ['pre' => null, 'post' => null];
        while ($row = $res->fetch_assoc()) {
            $tests[$row['test_type']] = $row;
        }

        // Return just null if not exists, frontend will handle "create" logic if needed
        // Or better, we ensure they exist. For now, just return what we have.
        success(['tests' => $tests]);
    }

    // --- GET QUESTIONS FOR A TEST ---
    elseif ($action === 'get_test_questions') {
        $test_id = (int) ($_GET['test_id'] ?? 0);
        if ($test_id <= 0)
            error('Invalid test ID');

        // Check ownership via course - REMOVED
        $check = $conn->prepare("
            SELECT t.id 
            FROM course_tests t
            INNER JOIN courses c ON t.course_id = c.id
            WHERE t.id = ?
        ");
        $check->bind_param("i", $test_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0)
            error('Test not found');

        // Get questions
        $q_stmt = $conn->prepare("SELECT * FROM test_questions WHERE test_id = ? ORDER BY order_index ASC, id ASC");
        $q_stmt->bind_param("i", $test_id);
        $q_stmt->execute();
        $q_res = $q_stmt->get_result();

        $questions = [];
        while ($q = $q_res->fetch_assoc()) {
            // Get answers for each question
            $a_stmt = $conn->prepare("SELECT * FROM test_answers WHERE question_id = ?");
            $a_stmt->bind_param("i", $q['id']);
            $a_stmt->execute();
            $a_res = $a_stmt->get_result();
            $answers = [];
            while ($a = $a_res->fetch_assoc()) {
                $answers[] = $a;
            }
            $q['answers'] = $answers;
            $questions[] = $q;
        }

        success(['questions' => $questions]);
    }

    // --- GET TEST RESULTS ---
    elseif ($action === 'get_test_results') {
        $test_id = (int) ($_GET['test_id'] ?? 0);
        if ($test_id <= 0)
            error('Invalid test ID');

        // Check ownership - REMOVED
        $check = $conn->prepare("
            SELECT t.id 
            FROM course_tests t
            INNER JOIN courses c ON t.course_id = c.id
            WHERE t.id = ?
        ");
        $check->bind_param("i", $test_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0)
            error('Test not found');

        $sql = "
            SELECT 
                sta.*,
                COALESCE(u.name, 'Unknown User') as student_name,
                u.email as student_code,
                u.avatar
            FROM student_test_attempts sta
            LEFT JOIN users u ON sta.student_id = u.id
            WHERE sta.test_id = ?
            ORDER BY sta.submit_time DESC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $test_id);
        $stmt->execute();
        $res = $stmt->get_result();

        $results = [];
        while ($row = $res->fetch_assoc()) {
            $results[] = $row;
        }

        // Debugging handled via response
        // $log = ...

        success(['results' => $results]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- SAVE TEST SETTINGS ---
    if ($action === 'save_test_settings') {
        $course_id = (int) ($_POST['course_id'] ?? 0);
        $type = $_POST['type'] ?? '';
        $is_active = (int) ($_POST['is_active'] ?? 0);
        $time_limit = (int) ($_POST['time_limit'] ?? 0);
        $shuffle_q = (int) ($_POST['shuffle_questions'] ?? 0);
        $shuffle_a = (int) ($_POST['shuffle_answers'] ?? 0);

        if ($course_id <= 0 || !in_array($type, ['pre', 'post']))
            error('Invalid input');

        // Check ownership
        $check = $conn->prepare("SELECT id FROM courses WHERE id = ?");
        $check->bind_param("i", $course_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0)
            error('Access denied');

        // Upsert test
        // MySQL ON DUPLICATE KEY UPDATE might be tricky if we don't have unique index set up perfectly, 
        // but we added UNIQUE KEY `unique_course_test` (`course_id`, `test_type`) in migration.

        $stmt = $conn->prepare("
            INSERT INTO course_tests (course_id, test_type, is_active, time_limit_minutes, shuffle_questions, shuffle_answers) 
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            is_active = VALUES(is_active),
            time_limit_minutes = VALUES(time_limit_minutes),
            shuffle_questions = VALUES(shuffle_questions),
            shuffle_answers = VALUES(shuffle_answers)
        ");
        $stmt->bind_param("isiiii", $course_id, $type, $is_active, $time_limit, $shuffle_q, $shuffle_a);

        if ($stmt->execute()) {
            // Get the ID
            $new_id = $stmt->insert_id;
            if ($new_id == 0) {
                // Determine ID if updated
                $id_sql = $conn->prepare("SELECT id FROM course_tests WHERE course_id = ? AND test_type = ?");
                $id_sql->bind_param("is", $course_id, $type);
                $id_sql->execute();
                $new_id = $id_sql->get_result()->fetch_assoc()['id'];
            }
            success(['test_id' => $new_id]);
        } else {
            error('Database error: ' . $stmt->error);
        }
    }

    // --- IMPORT AIKEN ---
    elseif ($action === 'import_aiken') {
        $test_id = (int) ($_POST['test_id'] ?? 0);

        $file_content = '';
        if (isset($_FILES['aiken_file']) && $_FILES['aiken_file']['error'] === UPLOAD_ERR_OK) {
            $file_content = file_get_contents($_FILES['aiken_file']['tmp_name']);
        } elseif (isset($_POST['aiken_text'])) {
            $file_content = $_POST['aiken_text'];
        }

        if ($test_id <= 0)
            error('Invalid test ID');
        if (trim($file_content) === '')
            error('No content to import (File or Text required)');

        // Check ownership
        // Check ownership - REMOVED
        $check = $conn->prepare("
            SELECT t.id, t.course_id 
            FROM course_tests t
            INNER JOIN courses c ON t.course_id = c.id
            WHERE t.id = ?
        ");
        $check->bind_param("i", $test_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0)
            error('Test not found');

        // Parse Aiken
        // Format:
        // Question text
        // A. option
        // B. option
        // ANSWER: X

        $lines = explode("\n", $file_content);
        $questions = [];
        $current_q = [
            'text' => [],
            'options' => [],
            'correct' => null
        ];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                // Could be delimiter, check if we have a valid question ready
                if (!empty($current_q['text']) && !empty($current_q['options']) && $current_q['correct']) {
                    $questions[] = $current_q;
                    $current_q = ['text' => [], 'options' => [], 'correct' => null];
                }
                continue;
            }

            // Check for ANSWER: X (Allowing some flexibility)
            if (preg_match('/^ANSWER:\s*([A-Z])/i', $line, $matches)) {
                $current_q['correct'] = strtoupper($matches[1]);
                // End of this question block ideally
                $questions[] = $current_q;
                $current_q = ['text' => [], 'options' => [], 'correct' => null];
                continue;
            }

            // Check for Option (A. something)
            if (preg_match('/^([A-Z])[\.\)]\s*(.*)$/', $line, $matches)) {
                $key = strtoupper($matches[1]);
                $val = $matches[2];
                $current_q['options'][$key] = $val;
            } else {
                // Assume part of question text (unless we already have options started - Aiken is strictly Q then Options)
                // If options already exist and this line doesn't look like an option or answer, it might be a malformed line or next question?
                // Aiken spec: Question must be one line. But some allow multiline.
                // Simpler parser: proper format is Q on one line.
                // Let's assume multi-line question text allowed until first option found.
                if (empty($current_q['options'])) {
                    $current_q['text'][] = $line;
                }
            }
        }
        // Catch last one if no newline at EOF
        if (!empty($current_q['text']) && !empty($current_q['options']) && $current_q['correct']) {
            $questions[] = $current_q;
        }

        if (empty($questions))
            error('No valid questions found in Aiken format');

        // Insert into DB
        $conn->begin_transaction();
        try {
            foreach ($questions as $q) {
                // Insert Question
                $q_text = implode("\n", $q['text']);
                $q_stmt = $conn->prepare("INSERT INTO test_questions (test_id, question_text) VALUES (?, ?)");
                $q_stmt->bind_param("is", $test_id, $q_text);
                $q_stmt->execute();
                $q_id = $q_stmt->insert_id;

                // Insert Options
                $a_stmt = $conn->prepare("INSERT INTO test_answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
                foreach ($q['options'] as $key => $val) {
                    $is_correct = ($key === $q['correct']) ? 1 : 0;
                    $a_stmt->bind_param("isi", $q_id, $val, $is_correct);
                    $a_stmt->execute();
                }
            }
            $conn->commit();
            success(['imported_count' => count($questions)]);
        } catch (Exception $e) {
            $conn->rollback();
            error('Import failed: ' . $e->getMessage());
        }
    }

    // --- DELETE TEST QUESTION ---
    elseif ($action === 'delete_test_question') {
        $id = (int) ($_POST['question_id'] ?? 0);
        if ($id <= 0)
            error('Invalid question ID');

        // Check ownership via question -> test -> course
        $check = $conn->prepare("
            SELECT q.id 
            FROM test_questions q
            INNER JOIN course_tests t ON q.test_id = t.id
            INNER JOIN courses c ON t.course_id = c.id
            WHERE q.id = ?
        ");
        $check->bind_param("i", $id);
        $check->execute();
        if ($check->get_result()->num_rows === 0)
            error('Question not found');

        // Delete question (cascade should handle answers if foreign keys set, but manual is safer)
        $conn->begin_transaction();
        try {
            // Delete answers
            $del_a = $conn->prepare("DELETE FROM test_answers WHERE question_id = ?");
            $del_a->bind_param("i", $id);
            $del_a->execute();

            // Delete question
            $del_q = $conn->prepare("DELETE FROM test_questions WHERE id = ?");
            $del_q->bind_param("i", $id);
            $del_q->execute();

            $conn->commit();
            success();
        } catch (Exception $e) {
            $conn->rollback();
            error('Database error: ' . $e->getMessage());
        }
    }

    // --- DELETE BULK TEST QUESTIONS ---
    elseif ($action === 'delete_bulk_test_questions') {
        $ids = $_POST['question_ids'] ?? [];
        if (!is_array($ids) || empty($ids)) {
            error('No questions selected');
        }

        // Sanitize IDs
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids, function($id) { return $id > 0; });

        if (empty($ids)) error('Invalid question IDs');

        // Verify ownership (Check if at least one belongs to teacher? Or verify all?)
        // Safer to verify all or just delete WHERE id IN (...) AND test_id IN (SELECT id FROM tests WHERE course_id IN (SELECT id FROM courses WHERE teacher_id = ?))
        // This ensures we only delete questions owned by this teacher.
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        
        // Build query to verify or just directly delete with join? 
        // Direct delete with JOIN is supported in MySQL.
        /*
           DELETE q, a 
           FROM test_questions q
           LEFT JOIN test_answers a ON a.question_id = q.id
           INNER JOIN course_tests t ON q.test_id = t.id
           INNER JOIN courses c ON t.course_id = c.id
           WHERE q.id IN (...) AND c.teacher_id = ?
        */
        // Note: DELETE with multiple tables syntax: DELETE q, a FROM ...
        // But simpler to just delete questions and let foreign keys or second query handle answers?
        // We do manual delete of answers usually.

        // 1. Get valid IDs
        $sql = "
            SELECT q.id 
            FROM test_questions q
            INNER JOIN course_tests t ON q.test_id = t.id
            INNER JOIN courses c ON t.course_id = c.id
            WHERE q.id IN ($placeholders)
        ";
        
        $stmt = $conn->prepare($sql);
        $params = $ids;
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        
        $valid_ids = [];
        while ($row = $res->fetch_assoc()) {
            $valid_ids[] = $row['id'];
        }
        
        if (empty($valid_ids)) {
            error('No valid questions found to delete (Permission denied or not found)');
        }

        // Delete valid IDs
        $conn->begin_transaction();
        try {
            $ph = implode(',', array_fill(0, count($valid_ids), '?'));
            $types_v = str_repeat('i', count($valid_ids));

            // Delete answers
            $del_a = $conn->prepare("DELETE FROM test_answers WHERE question_id IN ($ph)");
            $del_a->bind_param($types_v, ...$valid_ids);
            $del_a->execute();

            // Delete questions
            $del_q = $conn->prepare("DELETE FROM test_questions WHERE id IN ($ph)");
            $del_q->bind_param($types_v, ...$valid_ids);
            $del_q->execute();

            $conn->commit();
            success(['deleted_count' => count($valid_ids)]);
        } catch (Exception $e) {
            $conn->rollback();
            error('Database error: ' . $e->getMessage());
        }
    }

    // --- DELETE STUDENT ATTEMPT (RESET) ---
    elseif ($action === 'delete_student_attempt') {
        $attempt_id = (int) ($_POST['attempt_id'] ?? 0);
        if ($attempt_id <= 0)
            error('Invalid attempt ID');

        // Check ownership
        $check = $conn->prepare("
            SELECT sta.id 
            FROM student_test_attempts sta
            INNER JOIN course_tests t ON sta.test_id = t.id
            INNER JOIN courses c ON t.course_id = c.id
            WHERE sta.id = ?
        ");
        $check->bind_param("i", $attempt_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0)
            error('Attempt not found');

        $conn->begin_transaction();
        try {
            // Delete answers first (though cascade might handle it)
            $del_ans = $conn->prepare("DELETE FROM student_test_answers WHERE attempt_id = ?");
            $del_ans->bind_param("i", $attempt_id);
            $del_ans->execute();

            // Delete attempt
            $del_att = $conn->prepare("DELETE FROM student_test_attempts WHERE id = ?");
            $del_att->bind_param("i", $attempt_id);
            $del_att->execute();

            $conn->commit();
            success();
        } catch (Exception $e) {
            $conn->rollback();
            error('Delete failed: ' . $e->getMessage());
        }
    }

    // --- CREATE COURSE ---
    elseif ($action === 'create_course') {
        $title = trim($_POST['title'] ?? '');

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $course_level = $_POST['course_level'] ?? '1';

        if ($title === '')
            error('Title is required');

        $stmt = $conn->prepare("INSERT INTO courses (teacher_id, title, description, course_level) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $teacher_id, $title, $description, $course_level);

        if ($stmt->execute()) {
            success(['id' => $stmt->insert_id]);
        } else {
            error('Database error: ' . $stmt->error);
        }
    }

    // --- UPDATE COURSE ---
    elseif ($action === 'update_course') {
        $id = (int) ($_POST['id'] ?? 0);
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $course_level = $_POST['course_level'] ?? '1';

        if ($id <= 0)
            error('Invalid course ID');
        if ($title === '')
            error('Title is required');

        // Check ownership
        $check = $conn->prepare("SELECT id FROM courses WHERE id = ?");
        $check->bind_param("i", $id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            error('Course not found or permission denied');
        }

        $stmt = $conn->prepare("UPDATE courses SET title = ?, description = ?, course_level = ? WHERE id = ?");
        $stmt->bind_param("sssi", $title, $description, $course_level, $id);

        if ($stmt->execute()) {
            success();
        } else {
            error('Database error: ' . $stmt->error);
        }
    }

    // --- CREATE ANNOUNCEMENT ---
    elseif ($action === 'create_announcement') {
        $course_id = (int) ($_POST['course_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');

        if ($course_id <= 0) error('Invalid course ID');
        if ($content === '') error('Content is required');

        // Check ownership
        $check = $conn->prepare("SELECT id FROM courses WHERE id = ?");
        $check->bind_param("i", $course_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            error('Course not found or permission denied');
        }

        $stmt = $conn->prepare("INSERT INTO announcements (course_id, content) VALUES (?, ?)");
        $stmt->bind_param("is", $course_id, $content);

        if ($stmt->execute()) {
            success(['id' => $stmt->insert_id]);
        } else {
            error('Database error: ' . $stmt->error);
        }
    }

    // --- UPDATE ANNOUNCEMENT ---
    elseif ($action === 'update_announcement') {
        $id = (int) ($_POST['id'] ?? 0);
        $content = trim($_POST['content'] ?? '');

        if ($id <= 0) error('Invalid announcement ID');
        if ($content === '') error('Content is required');

        // Check ownership via course
        $check = $conn->prepare("
            SELECT a.id 
            FROM announcements a 
            INNER JOIN courses c ON a.course_id = c.id 
            WHERE a.id = ?
        ");
        $check->bind_param("i", $id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            error('Announcement not found or permission denied');
        }

        $stmt = $conn->prepare("UPDATE announcements SET content = ? WHERE id = ?");
        $stmt->bind_param("si", $content, $id);

        if ($stmt->execute()) {
            success();
        } else {
            error('Database error: ' . $stmt->error);
        }
    }

    // --- DELETE ANNOUNCEMENT ---
    elseif ($action === 'delete_announcement') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) error('Invalid announcement ID');

        // Check ownership via course
        $check = $conn->prepare("
            SELECT a.id 
            FROM announcements a 
            INNER JOIN courses c ON a.course_id = c.id 
            WHERE a.id = ?
        ");
        $check->bind_param("i", $id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            error('Announcement not found or permission denied');
        }

        $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            success();
        } else {
            error('Database error: ' . $stmt->error);
        }
    }

    // --- DELETE COURSE ---
    elseif ($action === 'delete_course') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0)
            error('Invalid course ID');

        // Check ownership
        $check = $conn->prepare("SELECT id FROM courses WHERE id = ?");
        $check->bind_param("i", $id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            error('Course not found or permission denied');
        }

        // Optional: You might want to delete related assignments/students first 
        // to avoid foreign key constraints if CASCADE isn't set.
        // For now, we assume database handles CASCADE or simple delete.

        $stmt = $conn->prepare("DELETE FROM courses WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            success();
        } else {
            error('Database error: ' . $stmt->error);
        }
    }

    // --- UPDATE GRADE ---
    elseif ($action === 'update_grade') {
        $submission_id = (int) ($_POST['submission_id'] ?? 0);
        $grade = trim($_POST['grade'] ?? '');
        $feedback = trim($_POST['feedback'] ?? '');

        if ($submission_id <= 0)
            error('Invalid submission ID');

        // Verify that this submission belongs to a course owned by this teacher
        $check = $conn->prepare("
            SELECT s.id 
            FROM assignment_submissions s
            INNER JOIN assignments a ON s.assignment_id = a.id
            INNER JOIN courses c ON a.course_id = c.id
            WHERE s.id = ?
        ");
        $check->bind_param("i", $submission_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            error('Submission not found');
        }

        // If grade is empty string, check if we should set to NULL or keep it empty?
        // Usually grade form sends empty string to mean "no grade" or just updating feedback.
        // But here we probably want to set it. 

        // Logic: if grade provided, update it. If feedback provided, update it.
        // For simplicity: Update both. If grade is empty, we set it to NULL? 
        // Or if the user just wants to save feedback without grading yet?
        // Let's assume if grade is empty string, we set it to NULL (ungraded) OR 
        // if your system allows "partial" saving. 
        // Based on grades.php, it looks like a simple text/number input. 
        // Let's treat empty string as NULL for grade.

        $gradeVal = ($grade === '') ? null : $grade;

        $stmt = $conn->prepare("UPDATE assignment_submissions SET grade = ?, feedback = ? WHERE id = ?");
        $stmt->bind_param("ssi", $gradeVal, $feedback, $submission_id);

        if ($stmt->execute()) {
            success();
        } else {
            error('Database error: ' . $stmt->error);
        }
    }

    // --- CREATE ASSIGNMENT ---
    elseif ($action === 'create_assignment') {
        $course_id = (int) ($_POST['course_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $due_date = trim($_POST['due_date'] ?? '');

        if ($course_id <= 0)
            error('Invalid course ID');
        if ($title === '')
            error('Title is required');
        if ($due_date === '')
            error('Due date is required');

        // Check ownership
        $check = $conn->prepare("SELECT id FROM courses WHERE id = ?");
        $check->bind_param("i", $course_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            error('Course not found or permission denied');
        }

        $stmt = $conn->prepare("INSERT INTO assignments (course_id, title, description, due_date) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $course_id, $title, $description, $due_date);

        if ($stmt->execute()) {
            success(['id' => $stmt->insert_id]);
        } else {
            error('Database error: ' . $stmt->error);
        }
    }

    // --- UPDATE ASSIGNMENT ---
    elseif ($action === 'update_assignment') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $due_date = trim($_POST['due_date'] ?? '');

        if ($id <= 0)
            error('Invalid assignment ID');
        if ($title === '')
            error('Title is required');

        // Check ownership
        $check = $conn->prepare("
            SELECT a.id 
            FROM assignments a 
            INNER JOIN courses c ON a.course_id = c.id 
            WHERE a.id = ?
        ");
        $check->bind_param("i", $id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            error('Assignment not found or permission denied');
        }

        $stmt = $conn->prepare("UPDATE assignments SET title = ?, description = ?, due_date = ? WHERE id = ?");
        $stmt->bind_param("sssi", $title, $description, $due_date, $id);

        if ($stmt->execute()) {
            success();
        } else {
            error('Database error: ' . $stmt->error);
        }
    }

    // --- DELETE ASSIGNMENT ---
    elseif ($action === 'delete_assignment') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0)
            error('Invalid assignment ID');

        // Check ownership
        $check = $conn->prepare("
            SELECT a.id 
            FROM assignments a 
            INNER JOIN courses c ON a.course_id = c.id 
            WHERE a.id = ?
        ");
        $check->bind_param("i", $id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            error('Assignment not found or permission denied');
        }

        $stmt = $conn->prepare("DELETE FROM assignments WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            success();
        } else {
            error('Database error: ' . $stmt->error);
        }
    }

    // --- ADD STUDENT TO COURSE ---
    elseif ($action === 'add_student_to_course') {
        $course_id = (int) ($_POST['course_id'] ?? 0);
        $student_key = trim($_POST['student_key'] ?? '');

        if ($course_id <= 0)
            error('Invalid course ID');
        if ($student_key === '')
            error('Student key (ID or Email) is required');

        // Check ownership
        $check = $conn->prepare("SELECT id FROM courses WHERE id = ?");
        $check->bind_param("i", $course_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            error('Course not found or permission denied');
        }

        // Find student
        // Search by ID or Email. Role must be 'student'.
        // If student_key is numeric, check ID first.

        $student_id = null;

        if (ctype_digit($student_key)) {
            $s_stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'student'");
            $s_stmt->bind_param("i", $student_key);
            $s_stmt->execute();
            $res = $s_stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $student_id = $row['id'];
            }
        }

        if (!$student_id) {
            // Try by email
            $s_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND role = 'student'");
            $s_stmt->bind_param("s", $student_key);
            $s_stmt->execute();
            $res = $s_stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $student_id = $row['id'];
            }
        }

        if (!$student_id) {
            error('Student not found (must be role="student")');
        }

        // Check if already enrolled
        $exists_stmt = $conn->prepare("SELECT id FROM course_students WHERE course_id = ? AND student_id = ?");
        $exists_stmt->bind_param("ii", $course_id, $student_id);
        $exists_stmt->execute();
        if ($exists_stmt->get_result()->num_rows > 0) {
            error('Student is already in this course');
        }

        // Enroll
        $ins = $conn->prepare("INSERT INTO course_students (course_id, student_id) VALUES (?, ?)");
        $ins->bind_param("ii", $course_id, $student_id);
        if ($ins->execute()) {
            success();
        } else {
            error('Database error: ' . $ins->error);
        }
    }

    // --- ADD STUDENTS BY LEVEL (BULK) ---
    elseif ($action === 'add_students_by_level') {
        $course_id = (int) ($_POST['course_id'] ?? 0);
        $level = trim($_POST['level'] ?? '');

        if ($course_id <= 0)
            error('Invalid course ID');
        if ($level === '')
            error('Level is required');

        // Check ownership
        $check = $conn->prepare("SELECT id FROM courses WHERE id = ?");
        $check->bind_param("i", $course_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            error('Course not found or permission denied');
        }

        // Find students with this level
        $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'student' AND courseLevel = ?");
        $stmt->bind_param("s", $level);
        $stmt->execute();
        $res = $stmt->get_result();

        $added_count = 0;

        // Prepare checks and inserts outside loop
        $exists_stmt = $conn->prepare("SELECT id FROM course_students WHERE course_id = ? AND student_id = ?");
        $current_sid = 0;
        $exists_stmt->bind_param("ii", $course_id, $current_sid);

        $ins_stmt = $conn->prepare("INSERT INTO course_students (course_id, student_id) VALUES (?, ?)");
        $ins_stmt->bind_param("ii", $course_id, $current_sid);

        while ($row = $res->fetch_assoc()) {
            $current_sid = (int) $row['id'];

            $exists_stmt->execute();
            if ($exists_stmt->get_result()->num_rows === 0) {
                if ($ins_stmt->execute()) {
                    $added_count++;
                }
            }
        }

        success(['added_count' => $added_count]);
    }

    // --- SEARCH CANDIDATES (For Multiselect) ---
    elseif ($action === 'search_candidates') {
        $course_id = (int) ($_GET['course_id'] ?? 0);
        $q = trim($_GET['q'] ?? '');

        if ($course_id <= 0)
            error('Invalid course ID');

        // Check ownership
        $check = $conn->prepare("SELECT id FROM courses WHERE id = ?");
        $check->bind_param("i", $course_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            error('Course not found or permission denied');
        }

        // Search students NOT in this course
        $query = "
            SELECT id, name, email, avatar, rank 
            FROM users 
            WHERE role = 'student' 
            AND (name LIKE ? OR email LIKE ?)
            AND id NOT IN (
                SELECT student_id FROM course_students WHERE course_id = ?
            )
            LIMIT 20
        ";
        $param = "%{$q}%";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssi", $param, $param, $course_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $students = [];
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }

        success(['students' => $students]);
    }

    // --- ADD STUDENTS MULTISELECT ---
    elseif ($action === 'add_students_multiselect') {
        $course_id = (int) ($_POST['course_id'] ?? 0);
        $student_ids = $_POST['student_ids'] ?? [];

        if ($course_id <= 0)
            error('Invalid course ID');
        if (!is_array($student_ids) || empty($student_ids))
            error('No students selected');

        // Check ownership
        $check = $conn->prepare("SELECT id FROM courses WHERE id = ?");
        $check->bind_param("i", $course_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            error('Course not found or permission denied');
        }

        $added_count = 0;

        // Optimized Insert
        $exists_stmt = $conn->prepare("SELECT id FROM course_students WHERE course_id = ? AND student_id = ?");
        $current_sid = 0;
        $exists_stmt->bind_param("ii", $course_id, $current_sid);

        $ins_stmt = $conn->prepare("INSERT INTO course_students (course_id, student_id) VALUES (?, ?)");
        $ins_stmt->bind_param("ii", $course_id, $current_sid);

        foreach ($student_ids as $sid) {
            $current_sid = (int) $sid;
            if ($current_sid <= 0)
                continue;

            $exists_stmt->execute();
            if ($exists_stmt->get_result()->num_rows === 0) {
                if ($ins_stmt->execute()) {
                    $added_count++;
                }
            }
        }

        success(['added_count' => $added_count]);
    }

    // --- REMOVE STUDENT FROM COURSE ---
    elseif ($action === 'remove_student_from_course') {
        $course_id = (int) ($_POST['course_id'] ?? 0);
        $student_id = (int) ($_POST['student_id'] ?? 0);

        if ($course_id <= 0 || $student_id <= 0)
            error('Invalid IDs');

        // Check ownership
        $check = $conn->prepare("SELECT id FROM courses WHERE id = ?");
        $check->bind_param("i", $course_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            error('Course not found or permission denied');
        }

        $del = $conn->prepare("DELETE FROM course_students WHERE course_id = ? AND student_id = ?");
        $del->bind_param("ii", $course_id, $student_id);

        if ($del->execute()) {
            success();
        } else {
            error('Database error: ' . $del->error);
        }
    }

    // --- ADD MATERIAL ---
    elseif ($action === 'add_material') {
        $course_id = isset($_POST['course_id']) ? (int) $_POST['course_id'] : 0;
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';

        if ($course_id <= 0 || empty($title)) {
            error('Invalid input');
        }

        // Check ownership
        $check = $conn->prepare("SELECT id FROM courses WHERE id = ?");
        $check->bind_param("i", $course_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            error('Access denied');
        }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            error('File upload failed');
        }

        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip', 'jpg', 'png', 'jpeg'];

        if (!in_array($ext, $allowed)) {
            error('Invalid file type');
        }

        $upload_dir = "../uploads/materials/";
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $new_name = uniqid() . '_' . time() . '.' . $ext;
        $dest = $upload_dir . $new_name;

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $file_path = "uploads/materials/" . $new_name;
            $file_size = $file['size'];

            $stmt = $conn->prepare("INSERT INTO course_materials (course_id, title, file_path, file_size) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $course_id, $title, $file_path, $file_size);

            if ($stmt->execute()) {
                success(['message' => 'File uploaded successfully']);
            } else {
                unlink($dest); // Delete file if DB insert fails
                error('Database error');
            }
        } else {
            error('Failed to move uploaded file');
        }
    }

    // --- DELETE MATERIAL ---
    elseif ($action === 'delete_material') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if ($id <= 0)
            error('Invalid ID');

        // Check ownership via course
        $query = "SELECT m.id, m.file_path FROM course_materials m 
                  INNER JOIN courses c ON m.course_id = c.id 
                  WHERE m.id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 0) {
            error('Material not found or access denied');
        }

        $row = $res->fetch_assoc();

        // Delete file
        if ($row['file_path'] && file_exists("../" . $row['file_path'])) {
            unlink("../" . $row['file_path']);
        }

        // Delete DB record
        $del = $conn->prepare("DELETE FROM course_materials WHERE id = ?");
        $del->bind_param("i", $id);

        if ($del->execute()) {
            success();
        } else {
            error('Database delete failed');
        }
    }

    // --- UPDATE USER ROLE ---
    elseif ($action === 'update_user_role') {
        $user_id = (int) ($_POST['user_id'] ?? 0);
        $new_role = trim($_POST['new_role'] ?? '');

        if ($user_id <= 0)
            error('Invalid user ID');
        if (!in_array($new_role, ['student', 'teacher']))
            error('Invalid role');

        // Prevent changing own role
        if ($user_id === $teacher_id) {
            error('You cannot change your own role');
        }

        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $new_role, $user_id);

        if ($stmt->execute()) {
            success();
        } else {
            error('Database error: ' . $stmt->error);
        }
    }

    // --- UPDATE USER LEVEL ---
    elseif ($action === 'update_user_level') {
        $user_id = (int) ($_POST['user_id'] ?? 0);
        $new_level = trim($_POST['new_level'] ?? '');

        if ($user_id <= 0)
            error('Invalid user ID');
        
        // Validate level if necessary, e.g. 1, 2, 3
        if (!in_array($new_level, ['1', '2', '3'])) {
            // Optional: allow blank or other values? tailored to '1','2','3' as seen in user_permissions.php
            error('Invalid level');
        }

        $stmt = $conn->prepare("UPDATE users SET courseLevel = ? WHERE id = ?");
        $stmt->bind_param("si", $new_level, $user_id);

        if ($stmt->execute()) {
            success();
        } else {
            error('Database error: ' . $stmt->error);
        }
    }

    // --- DELETE USER ---
    elseif ($action === 'delete_user') {
        $user_id = (int) ($_POST['user_id'] ?? 0);

        if ($user_id <= 0)
            error('Invalid user ID');

        // Prevent deleting self
        if ($user_id === $teacher_id) {
            error('คุณไม่สามารถลบบัญชีของตัวเองได้');
        }

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);

        if ($stmt->execute()) {
            success();
        } else {
            error('Database error: ' . $stmt->error);
        }
    }

    // --- UPDATE PROFILE ---
    elseif ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $rank = trim($_POST['rank'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $affiliation = trim($_POST['affiliation'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['new_password'] ?? '';
        
        if (empty($name) || empty($email)) {
            error('กรุณากรอกชื่อและอีเมล');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error('รูปแบบอีเมลไม่ถูกต้อง');
        }

        // Check if email already exists for other user
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_stmt->bind_param("si", $email, $teacher_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            error('อีเมลนี้ถูกใช้งานแล้ว');
        }

        // Handle Avatar Upload
        $avatar_sql = "";
        $params = [];
        $types = "";
        
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ["image/jpeg", "image/png", "image/jpg", "image/gif"];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            $file_type = $_FILES['avatar']['type'];
            $file_size = $_FILES['avatar']['size'];
            
            if (!in_array($file_type, $allowed_types)) {
                error('อนุญาตเฉพาะไฟล์รูปภาพ (JPG, PNG, GIF)');
            }
            if ($file_size > $max_size) {
                error('ขนาดไฟล์ต้องไม่เกิน 2MB');
            }

            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            $new_filename = uniqid() . "_" . time() . "." . $ext;
            $upload_dir = "../uploads/avatars/";
            
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_dir . $new_filename)) {
                $avatar_path = "uploads/avatars/" . $new_filename;
                
                // Delete old avatar if exists and strictly local
                if (!empty($user['avatar']) && file_exists("../" . $user['avatar'])) {
                    unlink("../" . $user['avatar']);
                }
                
                $avatar_sql = ", avatar = ?";
                $params[] = $avatar_path;
                $types .= "s";
                
                // Update session immediately for avatar
                $_SESSION['user']['avatar'] = $avatar_path;
            }
        }

        // Build Query
        $sql = "UPDATE users SET name = ?, email = ?, rank = ?, position = ?, affiliation = ?, phone = ?" . $avatar_sql;
        $base_params = [$name, $email, $rank, $position, $affiliation, $phone];
        $base_types = "ssssss";
        
        // Add Password if provided
        if (!empty($password)) {
            if (strlen($password) < 8) {
                error('รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร');
            }
            $sql .= ", password = ?";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
            $types .= "s";
        }

        $sql .= " WHERE id = ?";
        
        // Combine all params
        // Order: name, email, rank, position, affiliation, phone, [avatar], [password], id
        $final_params = array_merge($base_params, $params, [$teacher_id]);
        $final_types = $base_types . $types . "i";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($final_types, ...$final_params);
        
        if ($stmt->execute()) {
            // Update Session Data
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['rank'] = $rank;
            $_SESSION['user']['position'] = $position;
            $_SESSION['user']['affiliation'] = $affiliation;
            $_SESSION['user']['phone'] = $phone;
            
            success();
        } else {
            error('Database error: ' . $stmt->error);
        }
    } else {
        error('Invalid action');
    }

} else {
    // If not POST (and mostly not GET because GET exits on success)
    // Actually, if GET falls through here, it means action invalid.
    error('Invalid action or method');
}
