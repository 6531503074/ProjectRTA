<?php
// session_start();
include "../../config/db.php";

header('Content-Type: application/json');

if (!isset($_SESSION["user"]) || $_SESSION["user"]["role"] !== "student") {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$student_id = $_SESSION["user"]["id"];

// Get pending assignments count
$assignments_query = "SELECT COUNT(*) as total FROM assignments a 
                     INNER JOIN course_students cs ON a.course_id = cs.course_id 
                     WHERE cs.student_id = ? AND a.due_date >= CURDATE()";
$assignments_stmt = $conn->prepare($assignments_query);
$assignments_stmt->bind_param("i", $student_id);
$assignments_stmt->execute();
$assignments_count = $assignments_stmt->get_result()->fetch_assoc()['total'];

// Get new announcements count (last 7 days)
$announcements_query = "SELECT COUNT(*) as total FROM announcements an
                       INNER JOIN course_students cs ON an.course_id = cs.course_id 
                       WHERE cs.student_id = ? AND an.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
$announcements_stmt = $conn->prepare($announcements_query);
$announcements_stmt->bind_param("i", $student_id);
$announcements_stmt->execute();
$announcements_count = $announcements_stmt->get_result()->fetch_assoc()['total'];

echo json_encode([
    'success' => true,
    'assignments' => $assignments_count,
    'announcements' => $announcements_count
]);
?>