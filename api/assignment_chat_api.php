<?php
session_start();
include "../config/db.php";

// Set headers before any output
header('Content-Type: application/json');

// Error handling - catch all errors and return as JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo json_encode(['success' => false, 'message' => "Error: $errstr"]);
    exit();
});

// Check authentication
if (!isset($_SESSION["user"])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION["user"]["id"];
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($action) {
        case 'get_messages':
            getAssignmentMessages($conn, $user_id);
            break;
        
        case 'send_message':
            sendAssignmentMessage($conn, $user_id);
            break;
        
        case 'get_message_count':
            getMessageCount($conn, $user_id);
            break;
        
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getAssignmentMessages($conn, $user_id) {
    $assignment_id = isset($_GET['assignment_id']) ? intval($_GET['assignment_id']) : 0;
    $last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;
    
    if ($assignment_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid assignment ID']);
        return;
    }
    
    // Check if user has access to this assignment
    $check_query = "SELECT a.id FROM assignments a
                    INNER JOIN course_students cs ON a.course_id = cs.course_id
                    WHERE a.id = ? AND cs.student_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $assignment_id, $user_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    
    // Get messages (only new ones if last_id is provided)
    $query = "SELECT c.*, u.name, u.avatar, u.role 
              FROM assignment_chat c
              INNER JOIN users u ON c.user_id = u.id
              WHERE c.assignment_id = ? AND c.id > ?
              ORDER BY c.created_at ASC
              LIMIT 50";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $assignment_id, $last_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    
    echo json_encode(['success' => true, 'messages' => $messages]);
}

function sendAssignmentMessage($conn, $user_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    $assignment_id = isset($input['assignment_id']) ? intval($input['assignment_id']) : 0;
    $message = isset($input['message']) ? trim($input['message']) : '';
    
    if ($assignment_id <= 0 || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        return;
    }
    
    // Check access
    $check_query = "SELECT a.id FROM assignments a
                    INNER JOIN course_students cs ON a.course_id = cs.course_id
                    WHERE a.id = ? AND cs.student_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $assignment_id, $user_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    
    // Insert message
    $insert_query = "INSERT INTO assignment_chat (assignment_id, user_id, message) VALUES (?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("iis", $assignment_id, $user_id, $message);
    
    if ($insert_stmt->execute()) {
        // Get the inserted message with user info
        $msg_id = $conn->insert_id;
        $msg_query = "SELECT c.*, u.name, u.avatar, u.role 
                      FROM assignment_chat c
                      INNER JOIN users u ON c.user_id = u.id
                      WHERE c.id = ?";
        $msg_stmt = $conn->prepare($msg_query);
        $msg_stmt->bind_param("i", $msg_id);
        $msg_stmt->execute();
        $msg = $msg_stmt->get_result()->fetch_assoc();
        
        echo json_encode(['success' => true, 'message' => $msg]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send message']);
    }
}

function getMessageCount($conn, $user_id) {
    $assignment_id = isset($_GET['assignment_id']) ? intval($_GET['assignment_id']) : 0;
    
    if ($assignment_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid assignment ID']);
        return;
    }
    
    // Check access
    $check_query = "SELECT a.id FROM assignments a
                    INNER JOIN course_students cs ON a.course_id = cs.course_id
                    WHERE a.id = ? AND cs.student_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $assignment_id, $user_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    
    // Get message count
    $count_query = "SELECT COUNT(*) as count FROM assignment_chat WHERE assignment_id = ?";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param("i", $assignment_id);
    $count_stmt->execute();
    $count = $count_stmt->get_result()->fetch_assoc()['count'];
    
    echo json_encode(['success' => true, 'count' => $count]);
}

$conn->close();
?>