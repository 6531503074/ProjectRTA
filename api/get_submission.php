<?php
session_start();
include "../config/db.php";

header('Content-Type: application/json');

if (!isset($_SESSION["user"]) || $_SESSION["user"]["role"] !== "student") {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$student_id = $_SESSION["user"]["id"];
$submission_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($submission_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid submission ID']);
    exit();
}

// Get submission details with verification
$query = "SELECT s.*, a.title as assignment_title 
          FROM assignment_submissions s
          INNER JOIN assignments a ON s.assignment_id = a.id
          WHERE s.id = ? AND s.student_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $submission_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Submission not found']);
    exit();
}

$submission = $result->fetch_assoc();

echo json_encode([
    'success' => true,
    'submission' => $submission
]);

$conn->close();
?>