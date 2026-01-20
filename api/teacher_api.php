<?php
session_start();
include "../config/db.php";

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user = $_SESSION['user'];
$teacher_id = (int)$user['id'];
$action = $_GET['action'] ?? '';

// Helper to return error
function error($msg) {
    echo json_encode(['success' => false, 'message' => $msg]);
    exit();
}

// Helper to return success
function success($data = []) {
    echo json_encode(array_merge(['success' => true], $data));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- CREATE COURSE ---
    if ($action === 'create_course') {
        $title = trim($_POST['title'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $course_level = $_POST['course_level'] ?? '1';

        if ($title === '') error('Title is required');

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
        $id = (int)($_POST['id'] ?? 0);
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $course_level = $_POST['course_level'] ?? '1';

        if ($id <= 0) error('Invalid course ID');
        if ($title === '') error('Title is required');

        // Check ownership
        $check = $conn->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
        $check->bind_param("ii", $id, $teacher_id);
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

    // --- DELETE COURSE ---
    elseif ($action === 'delete_course') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) error('Invalid course ID');

        // Check ownership
        $check = $conn->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
        $check->bind_param("ii", $id, $teacher_id);
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
        $submission_id = (int)($_POST['submission_id'] ?? 0);
        $grade = trim($_POST['grade'] ?? '');
        $feedback = trim($_POST['feedback'] ?? '');

        if ($submission_id <= 0) error('Invalid submission ID');

        // Verify that this submission belongs to a course owned by this teacher
        $check = $conn->prepare("
            SELECT s.id 
            FROM assignment_submissions s
            INNER JOIN assignments a ON s.assignment_id = a.id
            INNER JOIN courses c ON a.course_id = c.id
            WHERE s.id = ? AND c.teacher_id = ?
        ");
        $check->bind_param("ii", $submission_id, $teacher_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            error('Submission not found or permission denied');
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
        $course_id = (int)($_POST['course_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $due_date = trim($_POST['due_date'] ?? '');

        if ($course_id <= 0) error('Invalid course ID');
        if ($title === '') error('Title is required');
        if ($due_date === '') error('Due date is required');

        // Check ownership
        $check = $conn->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
        $check->bind_param("ii", $course_id, $teacher_id);
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
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $due_date = trim($_POST['due_date'] ?? '');

        if ($id <= 0) error('Invalid assignment ID');
        if ($title === '') error('Title is required');

        // Check ownership
        $check = $conn->prepare("
            SELECT a.id 
            FROM assignments a 
            INNER JOIN courses c ON a.course_id = c.id 
            WHERE a.id = ? AND c.teacher_id = ?
        ");
        $check->bind_param("ii", $id, $teacher_id);
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
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) error('Invalid assignment ID');

        // Check ownership
        $check = $conn->prepare("
            SELECT a.id 
            FROM assignments a 
            INNER JOIN courses c ON a.course_id = c.id 
            WHERE a.id = ? AND c.teacher_id = ?
        ");
        $check->bind_param("ii", $id, $teacher_id);
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
        $course_id = (int)($_POST['course_id'] ?? 0);
        $student_key = trim($_POST['student_key'] ?? '');

        if ($course_id <= 0) error('Invalid course ID');
        if ($student_key === '') error('Student key (ID or Email) is required');

        // Check ownership
        $check = $conn->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
        $check->bind_param("ii", $course_id, $teacher_id);
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
        $course_id = (int)($_POST['course_id'] ?? 0);
        $level = trim($_POST['level'] ?? '');

        if ($course_id <= 0) error('Invalid course ID');
        if ($level === '') error('Level is required');

        // Check ownership
        $check = $conn->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
        $check->bind_param("ii", $course_id, $teacher_id);
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
            $current_sid = (int)$row['id'];
            
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
        $course_id = (int)($_GET['course_id'] ?? 0);
        $q = trim($_GET['q'] ?? '');

        if ($course_id <= 0) error('Invalid course ID');

        // Check ownership
        $check = $conn->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
        $check->bind_param("ii", $course_id, $teacher_id);
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
        $course_id = (int)($_POST['course_id'] ?? 0);
        $student_ids = $_POST['student_ids'] ?? [];

        if ($course_id <= 0) error('Invalid course ID');
        if (!is_array($student_ids) || empty($student_ids)) error('No students selected');

        // Check ownership
        $check = $conn->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
        $check->bind_param("ii", $course_id, $teacher_id);
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
            $current_sid = (int)$sid;
            if ($current_sid <= 0) continue;

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
        $course_id = (int)($_POST['course_id'] ?? 0);
        $student_id = (int)($_POST['student_id'] ?? 0);

        if ($course_id <= 0 || $student_id <= 0) error('Invalid IDs');

        // Check ownership
        $check = $conn->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
        $check->bind_param("ii", $course_id, $teacher_id);
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
        $course_id = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';

        if ($course_id <= 0 || empty($title)) {
            error('Invalid input');
        }

        // Check ownership
        $check = $conn->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
        $check->bind_param("ii", $course_id, $teacher_id);
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
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if ($id <= 0) error('Invalid ID');

        // Check ownership via course
        $query = "SELECT m.id, m.file_path FROM course_materials m 
                  INNER JOIN courses c ON m.course_id = c.id 
                  WHERE m.id = ? AND c.teacher_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $id, $teacher_id);
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

    else {
        error('Invalid action');
    }

} else {
    error('Method not allowed');
}
