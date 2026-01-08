<?php
// NO SPACES BEFORE THIS LINE!
// Set headers FIRST before any output
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

// Include database (which starts session)
include "../config/db.php";

try {
    // Check authentication
    if (!isset($_SESSION["user"]) || $_SESSION["user"]["role"] !== "student") {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit();
    }

    // Get POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit();
    }

    $course_id = isset($data['course_id']) ? intval($data['course_id']) : 0;
    $student_id = intval($_SESSION["user"]["id"]);

    if ($course_id <= 0 || $student_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid IDs']);
        exit();
    }

    // Check if already pinned
    $check_stmt = $conn->prepare("SELECT id FROM pinned_courses WHERE course_id = ? AND student_id = ?");
    
    if (!$check_stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $check_stmt->bind_param("ii", $course_id, $student_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        // UNPIN - Delete the record
        $delete_stmt = $conn->prepare("DELETE FROM pinned_courses WHERE course_id = ? AND student_id = ?");
        
        if (!$delete_stmt) {
            throw new Exception("Delete prepare error: " . $conn->error);
        }
        
        $delete_stmt->bind_param("ii", $course_id, $student_id);
        
        if ($delete_stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'pinned' => false,
                'message' => 'Course unpinned'
            ]);
        } else {
            throw new Exception("Delete execution error: " . $delete_stmt->error);
        }
        
        $delete_stmt->close();
    } else {
        // PIN - Insert new record
        $insert_stmt = $conn->prepare("INSERT INTO pinned_courses (course_id, student_id) VALUES (?, ?)");
        
        if (!$insert_stmt) {
            throw new Exception("Insert prepare error: " . $conn->error);
        }
        
        $insert_stmt->bind_param("ii", $course_id, $student_id);
        
        if ($insert_stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'pinned' => true,
                'message' => 'Course pinned'
            ]);
        } else {
            throw new Exception("Insert execution error: " . $insert_stmt->error);
        }
        
        $insert_stmt->close();
    }
    
    $check_stmt->close();
    
} catch (Exception $e) {
    error_log("Toggle Pin Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred',
        'error' => $e->getMessage() // Remove in production
    ]);
}

$conn->close();
exit();
?>