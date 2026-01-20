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
            
        case 'get_unread_counts':
            getUnreadCounts($conn, $user_id);
            break;
        
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function checkAccess($conn, $assignment_id, $user_id) {
    // Check if user is student enrolled in course
    $student_query = "SELECT a.id FROM assignments a
                      INNER JOIN course_students cs ON a.course_id = cs.course_id
                      WHERE a.id = ? AND cs.student_id = ?";
    $stmt = $conn->prepare($student_query);
    $stmt->bind_param("ii", $assignment_id, $user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) return true;

    // Check if user is teacher owning the course
    $teacher_query = "SELECT a.id FROM assignments a
                      INNER JOIN courses c ON a.course_id = c.id
                      WHERE a.id = ? AND c.teacher_id = ?";
    $stmt2 = $conn->prepare($teacher_query);
    $stmt2->bind_param("ii", $assignment_id, $user_id);
    $stmt2->execute();
    if ($stmt2->get_result()->num_rows > 0) return true;

    return false;
}

function getAssignmentMessages($conn, $user_id) {
    $assignment_id = isset($_GET['assignment_id']) ? intval($_GET['assignment_id']) : 0;
    $last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;
    
    if ($assignment_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid assignment ID']);
        return;
    }
    
    if (!checkAccess($conn, $assignment_id, $user_id)) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    
    // Mark as read
    $read_query = "INSERT INTO assignment_chat_reads (assignment_id, user_id, last_read_at) 
                   VALUES (?, ?, NOW()) 
                   ON DUPLICATE KEY UPDATE last_read_at = NOW()";
    $read_stmt = $conn->prepare($read_query);
    $read_stmt->bind_param("ii", $assignment_id, $user_id);
    $read_stmt->execute();
    
    // Get messages
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
    
    if (!checkAccess($conn, $assignment_id, $user_id)) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    
    // Insert message
    $insert_query = "INSERT INTO assignment_chat (assignment_id, user_id, message) VALUES (?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("iis", $assignment_id, $user_id, $message);
    
    if ($insert_stmt->execute()) {
        // Mark as read for sender (optional, but good for consistency)
        $read_query = "INSERT INTO assignment_chat_reads (assignment_id, user_id, last_read_at) 
                       VALUES (?, ?, NOW()) 
                       ON DUPLICATE KEY UPDATE last_read_at = NOW()";
        $read_stmt = $conn->prepare($read_query);
        $read_stmt->bind_param("ii", $assignment_id, $user_id);
        $read_stmt->execute();

        // Get inserted message
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
    
    if (!checkAccess($conn, $assignment_id, $user_id)) {
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

function getUnreadCounts($conn, $user_id) {
    // Get unread counts for all assignments accessible to user
    // We only care about courses/assignments related to the user
    // But for simplicity, we can query all assignments linked to user (if student) or courses owned (if teacher)
    
    // Query finds assignments and counts messages created AFTER last_read_at
    // IF no last_read_at, ALL messages are unread.
    // Optimization: filtering by user role might be complex here, 
    // but assuming calling this means we want unread for relevant stuff.
    // Let's just do it for IDs passed in? No, we want to show badges on list.
    
    // Let's try to get ALL unread counts for this user across all assignments they have access to.
    
    // This query joins assignment_chat with assignment_chat_reads
    $sql = "
        SELECT 
            ac.assignment_id, 
            COUNT(ac.id) as unread_count
        FROM assignment_chat ac
        LEFT JOIN assignment_chat_reads acr ON ac.assignment_id = acr.assignment_id AND acr.user_id = ?
        WHERE 
            ac.created_at > COALESCE(acr.last_read_at, '0000-00-00 00:00:00')
            /* AND ac.user_id != ? (optional: don't count my own messages? usually yes) */
            AND ac.user_id != ?
        GROUP BY ac.assignment_id
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $counts = [];
    while ($row = $result->fetch_assoc()) {
        $counts[$row['assignment_id']] = (int)$row['unread_count'];
    }
    
    echo json_encode(['success' => true, 'counts' => $counts]);
}

$conn->close();
?>