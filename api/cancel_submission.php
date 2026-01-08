<?php
session_start();
include "../config/db.php";

header('Content-Type: application/json');

if (!isset($_SESSION["user"]) || $_SESSION["user"]["role"] !== "student") {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$student_id = $_SESSION["user"]["id"];
$submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;

if ($submission_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid submission ID']);
    exit();
}

// Get submission details and verify ownership
$query = "SELECT s.*, a.course_id 
          FROM assignment_submissions s
          INNER JOIN assignments a ON s.assignment_id = a.id
          INNER JOIN course_students cs ON a.course_id = cs.course_id
          WHERE s.id = ? AND s.student_id = ? AND cs.student_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $submission_id, $student_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Submission not found or access denied']);
    exit();
}

$submission = $result->fetch_assoc();

// Prevent canceling graded submissions
if ($submission['grade'] !== null) {
    echo json_encode(['success' => false, 'message' => 'Cannot cancel graded submission']);
    exit();
}

// Delete file if exists
if ($submission['file_path'] && file_exists("../" . $submission['file_path'])) {
    unlink("../" . $submission['file_path']);
}

// Delete submission from database
$delete_query = "DELETE FROM assignment_submissions WHERE id = ?";
$delete_stmt = $conn->prepare($delete_query);
$delete_stmt->bind_param("i", $submission_id);

if ($delete_stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Submission cancelled successfully'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to cancel submission: ' . $conn->error]);
}

$conn->close();
?>