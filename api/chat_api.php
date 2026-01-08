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
        case 'send_message':
            sendMessage($conn, $user_id);
            break;
        
        case 'get_messages':
            getMessages($conn, $user_id);
            break;
        
        case 'create_group':
            createGroup($conn, $user_id);
            break;
        
        case 'get_groups':
            getGroups($conn, $user_id);
            break;
        
        case 'join_group':
            joinGroup($conn, $user_id);
            break;
        
        case 'leave_group':
            leaveGroup($conn, $user_id);
            break;
        
        case 'get_group_members':
            getGroupMembers($conn, $user_id);
            break;
        
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function sendMessage($conn, $user_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    $group_id = isset($input['group_id']) ? intval($input['group_id']) : 0;
    $message = isset($input['message']) ? trim($input['message']) : '';
    
    if ($group_id <= 0 || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        return;
    }
    
    // Check if user is member of group
    $check_query = "SELECT id FROM group_chat_members WHERE group_id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $group_id, $user_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Not a member of this group']);
        return;
    }
    
    // Insert message
    $insert_query = "INSERT INTO group_chat_messages (group_id, user_id, message) VALUES (?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("iis", $group_id, $user_id, $message);
    
    if ($insert_stmt->execute()) {
        $message_id = $conn->insert_id;
        
        // Get message with user info
        $msg_query = "SELECT m.*, u.name, u.avatar FROM group_chat_messages m
                      INNER JOIN users u ON m.user_id = u.id
                      WHERE m.id = ?";
        $msg_stmt = $conn->prepare($msg_query);
        $msg_stmt->bind_param("i", $message_id);
        $msg_stmt->execute();
        $msg = $msg_stmt->get_result()->fetch_assoc();
        
        echo json_encode(['success' => true, 'message' => $msg]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send message']);
    }
}

function getMessages($conn, $user_id) {
    $group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;
    $last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;
    
    if ($group_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid group ID']);
        return;
    }
    
    // Check if user is member
    $check_query = "SELECT id FROM group_chat_members WHERE group_id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $group_id, $user_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Not a member']);
        return;
    }
    
    // Get messages
    $query = "SELECT m.*, u.name, u.avatar FROM group_chat_messages m
              INNER JOIN users u ON m.user_id = u.id
              WHERE m.group_id = ? AND m.id > ?
              ORDER BY m.created_at ASC
              LIMIT 50";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $group_id, $last_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    
    echo json_encode(['success' => true, 'messages' => $messages]);
}

function createGroup($conn, $user_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    $course_id = isset($input['course_id']) ? intval($input['course_id']) : 0;
    $name = isset($input['name']) ? trim($input['name']) : '';
    $description = isset($input['description']) ? trim($input['description']) : '';
    
    if ($course_id <= 0 || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        return;
    }
    
    // Check if user is enrolled in course
    $check_query = "SELECT id FROM course_students WHERE course_id = ? AND student_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $course_id, $user_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Not enrolled in course']);
        return;
    }
    
    // Create group
    $insert_query = "INSERT INTO group_chats (course_id, name, description, created_by) VALUES (?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("issi", $course_id, $name, $description, $user_id);
    
    if ($insert_stmt->execute()) {
        $group_id = $conn->insert_id;
        
        // Add creator as member
        $member_query = "INSERT INTO group_chat_members (group_id, user_id) VALUES (?, ?)";
        $member_stmt = $conn->prepare($member_query);
        $member_stmt->bind_param("ii", $group_id, $user_id);
        $member_stmt->execute();
        
        echo json_encode(['success' => true, 'group_id' => $group_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create group']);
    }
}

function getGroups($conn, $user_id) {
    $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    
    if ($course_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid course ID']);
        return;
    }
    
    if ($filter === 'my') {
        // Get groups user is member of
        $query = "SELECT g.*, u.name as creator_name,
                  (SELECT COUNT(*) FROM group_chat_members WHERE group_id = g.id) as member_count,
                  (SELECT COUNT(*) FROM group_chat_messages WHERE group_id = g.id) as message_count
                  FROM group_chats g
                  INNER JOIN users u ON g.created_by = u.id
                  INNER JOIN group_chat_members m ON g.id = m.group_id
                  WHERE g.course_id = ? AND m.user_id = ?
                  ORDER BY g.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $course_id, $user_id);
    } else {
        // Get all groups for course
        $query = "SELECT g.*, u.name as creator_name,
                  (SELECT COUNT(*) FROM group_chat_members WHERE group_id = g.id) as member_count,
                  (SELECT COUNT(*) FROM group_chat_messages WHERE group_id = g.id) as message_count,
                  (SELECT COUNT(*) FROM group_chat_members WHERE group_id = g.id AND user_id = ?) as is_member
                  FROM group_chats g
                  INNER JOIN users u ON g.created_by = u.id
                  WHERE g.course_id = ?
                  ORDER BY g.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $user_id, $course_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $groups = [];
    while ($row = $result->fetch_assoc()) {
        $groups[] = $row;
    }
    
    echo json_encode(['success' => true, 'groups' => $groups]);
}

function joinGroup($conn, $user_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    $group_id = isset($input['group_id']) ? intval($input['group_id']) : 0;
    
    if ($group_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid group ID']);
        return;
    }
    
    // Check if group exists and user is enrolled in course
    $check_query = "SELECT g.id FROM group_chats g
                    INNER JOIN course_students cs ON g.course_id = cs.course_id
                    WHERE g.id = ? AND cs.student_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $group_id, $user_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Group not found or access denied']);
        return;
    }
    
    // Add member
    $insert_query = "INSERT IGNORE INTO group_chat_members (group_id, user_id) VALUES (?, ?)";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("ii", $group_id, $user_id);
    
    if ($insert_stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to join group']);
    }
}

function leaveGroup($conn, $user_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    $group_id = isset($input['group_id']) ? intval($input['group_id']) : 0;
    
    if ($group_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid group ID']);
        return;
    }
    
    // Check if user is the creator
    $creator_query = "SELECT created_by FROM group_chats WHERE id = ?";
    $creator_stmt = $conn->prepare($creator_query);
    $creator_stmt->bind_param("i", $group_id);
    $creator_stmt->execute();
    $creator_result = $creator_stmt->get_result();
    
    if ($creator_result->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Group not found']);
        return;
    }
    
    $group_data = $creator_result->fetch_assoc();
    
    // Prevent creator from leaving (optional - you can remove this if you want creators to leave)
    if ($group_data['created_by'] == $user_id) {
        // Check if there are other members
        $member_count_query = "SELECT COUNT(*) as count FROM group_chat_members WHERE group_id = ? AND user_id != ?";
        $member_count_stmt = $conn->prepare($member_count_query);
        $member_count_stmt->bind_param("ii", $group_id, $user_id);
        $member_count_stmt->execute();
        $member_count = $member_count_stmt->get_result()->fetch_assoc()['count'];
        
        if ($member_count > 0) {
            echo json_encode(['success' => false, 'message' => 'As the creator, you cannot leave while other members are still in the group. Please transfer ownership or wait for all members to leave.']);
            return;
        }
    }
    
    // Remove user from group
    $delete_query = "DELETE FROM group_chat_members WHERE group_id = ? AND user_id = ?";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param("ii", $group_id, $user_id);
    
    if ($delete_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Successfully left the group']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to leave group']);
    }
}

function getGroupMembers($conn, $user_id) {
    $group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;
    
    if ($group_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid group ID']);
        return;
    }
    
    // Check if user is a member
    $check_query = "SELECT id FROM group_chat_members WHERE group_id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $group_id, $user_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Not a member']);
        return;
    }
    
    // Get group creator
    $creator_query = "SELECT created_by FROM group_chats WHERE id = ?";
    $creator_stmt = $conn->prepare($creator_query);
    $creator_stmt->bind_param("i", $group_id);
    $creator_stmt->execute();
    $creator_id = $creator_stmt->get_result()->fetch_assoc()['created_by'];
    
    // Get members with role
    $query = "SELECT u.id, u.name, u.email, u.avatar, m.joined_at,
              CASE WHEN u.id = ? THEN 'creator' ELSE 'member' END as role
              FROM group_chat_members m
              INNER JOIN users u ON m.user_id = u.id
              WHERE m.group_id = ?
              ORDER BY CASE WHEN u.id = ? THEN 0 ELSE 1 END, m.joined_at ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $creator_id, $group_id, $creator_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $members = [];
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
    
    echo json_encode(['success' => true, 'members' => $members]);
}

$conn->close();
?>