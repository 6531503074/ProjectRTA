<?php
session_start();
include "../config/db.php";

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION["user"]) || $_SESSION["user"]["role"] !== "student") {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$student_id = $_SESSION["user"]["id"];
$assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;
$submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;
$is_edit = isset($_POST['is_edit']) && $_POST['is_edit'] == '1';
$submission_text = isset($_POST['submission_text']) ? trim($_POST['submission_text']) : '';

// Validate assignment ID
if ($assignment_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid assignment ID']);
    exit();
}

// Verify student has access to this assignment
$check_query = "SELECT a.id, a.course_id, a.title 
                FROM assignments a 
                INNER JOIN course_students cs ON a.course_id = cs.course_id 
                WHERE a.id = ? AND cs.student_id = ?";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("ii", $assignment_id, $student_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Assignment not found or access denied']);
    exit();
}

$assignment = $result->fetch_assoc();

// Handle file upload function
function handleFileUpload($student_id, $assignment_id, $old_file_path = null) {
    if (!isset($_FILES['submission_file']) || $_FILES['submission_file']['error'] !== 0) {
        return ['success' => true, 'file_path' => $old_file_path];
    }
    
    $upload_dir = "../uploads/submissions/";
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            return ['success' => false, 'message' => 'Failed to create upload directory'];
        }
    }
    
    // Validate file size (max 10MB)
    $max_size = 10 * 1024 * 1024;
    if ($_FILES['submission_file']['size'] > $max_size) {
        return ['success' => false, 'message' => 'File too large. Maximum size is 10MB'];
    }
    
    // Validate file extension
    $file_extension = strtolower(pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar', 'ppt', 'pptx', 'xls', 'xlsx'];
    
    if (!in_array($file_extension, $allowed_extensions)) {
        return ['success' => false, 'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowed_extensions)];
    }
    
    // Delete old file if exists and we're replacing it
    if ($old_file_path && file_exists("../" . $old_file_path)) {
        unlink("../" . $old_file_path);
    }
    
    // Generate unique filename
    $file_name = "submission_" . $student_id . "_" . $assignment_id . "_" . time() . "." . $file_extension;
    $target_file = $upload_dir . $file_name;
    
    // Move uploaded file
    if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $target_file)) {
        return ['success' => true, 'file_path' => "uploads/submissions/" . $file_name];
    } else {
        return ['success' => false, 'message' => 'Failed to upload file'];
    }
}

// ==================== EDIT MODE ====================
if ($is_edit && $submission_id > 0) {
    
    // Verify submission exists and belongs to student
    $verify_query = "SELECT s.id, s.file_path, s.grade, s.feedback 
                     FROM assignment_submissions s
                     WHERE s.id = ? AND s.student_id = ? AND s.assignment_id = ?";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->bind_param("iii", $submission_id, $student_id, $assignment_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Submission not found or access denied']);
        exit();
    }
    
    $existing_submission = $verify_result->fetch_assoc();
    
    // Prevent editing graded submissions
    if ($existing_submission['grade'] !== null) {
        echo json_encode(['success' => false, 'message' => 'Cannot edit graded submission. Your submission has already been graded.']);
        exit();
    }
    
    // Handle file upload (keep existing file if no new file uploaded)
    $file_result = handleFileUpload($student_id, $assignment_id, $existing_submission['file_path']);
    
    if (!$file_result['success']) {
        echo json_encode(['success' => false, 'message' => $file_result['message']]);
        exit();
    }
    
    $file_path = $file_result['file_path'];
    
    // Validate that at least one submission method is provided
    if (empty($submission_text) && empty($file_path)) {
        echo json_encode(['success' => false, 'message' => 'Please provide submission text or upload a file']);
        exit();
    }
    
    // Update submission
    $update_query = "UPDATE assignment_submissions 
                     SET submission_text = ?, 
                         file_path = ?, 
                         submitted_at = NOW() 
                     WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("ssi", $submission_text, $file_path, $submission_id);
    
    if ($update_stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Submission updated successfully! Your changes have been saved.',
            'submission_id' => $submission_id,
            'is_edit' => true
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed: ' . $conn->error]);
    }
    
// ==================== NEW SUBMISSION MODE ====================
} else {
    
    // Check if already submitted
    $existing_query = "SELECT id, grade FROM assignment_submissions 
                       WHERE assignment_id = ? AND student_id = ?";
    $existing_stmt = $conn->prepare($existing_query);
    $existing_stmt->bind_param("ii", $assignment_id, $student_id);
    $existing_stmt->execute();
    $existing_result = $existing_stmt->get_result();
    
    if ($existing_result->num_rows > 0) {
        $existing = $existing_result->fetch_assoc();
        if ($existing['grade'] !== null) {
            echo json_encode(['success' => false, 'message' => 'This assignment has already been graded. You cannot submit again.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'You have already submitted this assignment. Please use the Edit button to modify your submission.']);
        }
        exit();
    }
    
    // Handle file upload for new submission
    $file_result = handleFileUpload($student_id, $assignment_id);
    
    if (!$file_result['success']) {
        echo json_encode(['success' => false, 'message' => $file_result['message']]);
        exit();
    }
    
    $file_path = $file_result['file_path'];
    
    // Validate that at least one submission method is provided
    if (empty($submission_text) && empty($file_path)) {
        echo json_encode(['success' => false, 'message' => 'Please provide submission text or upload a file']);
        exit();
    }
    
    // Insert new submission
    $insert_query = "INSERT INTO assignment_submissions 
                     (assignment_id, student_id, submission_text, file_path, submitted_at) 
                     VALUES (?, ?, ?, ?, NOW())";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("iiss", $assignment_id, $student_id, $submission_text, $file_path);
    
    if ($insert_stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Assignment submitted successfully! Your submission has been recorded.',
            'submission_id' => $conn->insert_id,
            'is_edit' => false
        ]);
    } else {
        // If insert failed, delete uploaded file
        if ($file_path && file_exists("../" . $file_path)) {
            unlink("../" . $file_path);
        }
        echo json_encode(['success' => false, 'message' => 'Submission failed: ' . $conn->error]);
    }
}

$conn->close();
?>