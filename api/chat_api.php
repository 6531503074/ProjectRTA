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
    echo json_encode(['success' => false, 'message' => 'คุณไม่ได้รับอนุญาต']);
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
            
        case 'delete_group':
            deleteGroup($conn, $user_id);
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

        case 'get_unread_count':
            getGlobalUnreadCount($conn, $user_id);
            break;

        case 'mark_read':
            markRead($conn, $user_id);
            break;
        
        default:
            echo json_encode(['success' => false, 'message' => 'ไม่พบการทำงาน']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function sendMessage($conn, $user_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    $group_id = isset($input['group_id']) ? intval($input['group_id']) : 0;
    $message = isset($input['message']) ? trim($input['message']) : '';
    
    if ($group_id <= 0 || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง']);
        return;
    }
    
    // Check if user is member of group
    $check_query = "SELECT id FROM group_chat_members WHERE group_id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $group_id, $user_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'ไม่ได้เป็นสมาชิกกลุ่ม']);
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
        echo json_encode(['success' => false, 'message' => 'ส่งข้อความไม่สำเร็จ']);
    }
}

function getMessages($conn, $user_id) {
    $group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;
    $last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;
    
    if ($group_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'รหัสกลุ่มไม่ถูกต้อง']);
        return;
    }
    
    // Check if user is member
    $check_query = "SELECT id FROM group_chat_members WHERE group_id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $group_id, $user_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'ไม่ได้เป็นสมาชิกกลุ่ม']);
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

// Helper to check if user is teacher of the course
// Modified to give all teachers equal rights
function isTeacherOfCourse($conn, $user_id, $course_id) {
    if (isset($_SESSION["user"]) && $_SESSION["user"]["role"] === "teacher") {
        return true;
    }
    return false;
}

function createGroup($conn, $user_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    $course_id = isset($input['course_id']) ? intval($input['course_id']) : 0;
    $name = isset($input['name']) ? trim($input['name']) : '';
    $description = isset($input['description']) ? trim($input['description']) : '';
    
    if ($course_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'รหัสรายวิชาไม่ถูกต้อง']);
        return;
    }
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'ชื่อกลุ่มต้องไม่ว่าง']);
        return;
    }
    
    // Check if user is enrolled in course OR is teacher
    $is_student = false;
    $check_query = "SELECT id FROM course_students WHERE course_id = ? AND student_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $course_id, $user_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) $is_student = true;

    $is_teacher = isTeacherOfCourse($conn, $user_id, $course_id);
    
    if (!$is_student && !$is_teacher) {
        echo json_encode(['success' => false, 'message' => 'คุณยังไม่ได้ลงทะเบียนในรายวิชานี้']);
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
    
    // Allow teachers to see all groups if course_id is 0
    // Check if user is teacher (simple check via session usually, but here we query DB or assume caller handles logic)
    // We'll check if they own ANY courses if course_id is 0
    
    if ($course_id == 0) {
        // Global fetch for teacher
        // Get all groups where the course is owned by this user
        // OR groups where user is a member (for 'my' filter)
        
        if ($filter === 'my') {
             $query = "SELECT g.*, u.name as creator_name, c.title as course_title,
                  (SELECT COUNT(*) FROM group_chat_members WHERE group_id = g.id) as member_count,
                  (SELECT COUNT(*) FROM group_chat_messages WHERE group_id = g.id) as message_count,
                  (SELECT COUNT(*) FROM group_chat_messages WHERE group_id = g.id AND id > m.last_read_message_id) as unread_count
                  FROM group_chats g
                  INNER JOIN users u ON g.created_by = u.id
                  INNER JOIN courses c ON g.course_id = c.id
                  INNER JOIN group_chat_members m ON g.id = m.group_id
                  WHERE m.user_id = ?
                  ORDER BY g.created_at DESC";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $user_id);
        } else {
            // All groups in courses taught by this user
            // Teachers might not have an entry in group_chat_members for all groups, so unread count is complex if they haven't joined.
            // For now, if they haven't joined, unread count is just total messages? Or 0? Let's say 0 until they join.
             $query = "SELECT g.*, u.name as creator_name, c.title as course_title,
                  (SELECT COUNT(*) FROM group_chat_members WHERE group_id = g.id) as member_count,
                  (SELECT COUNT(*) FROM group_chat_messages WHERE group_id = g.id) as message_count,
                  COALESCE((SELECT COUNT(*) FROM group_chat_messages gm 
                            WHERE gm.group_id = g.id AND gm.id > 
                            (SELECT last_read_message_id FROM group_chat_members WHERE group_id = g.id AND user_id = ?)), 0) as unread_count,
                  (SELECT COUNT(*) FROM group_chat_members WHERE group_id = g.id AND user_id = ?) as is_member
                  FROM group_chats g
                  INNER JOIN users u ON g.created_by = u.id
                  INNER JOIN courses c ON g.course_id = c.id
                  ORDER BY g.created_at DESC";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $user_id, $user_id);
        }
    } else {
        // Specific course access
        if ($filter === 'my') {
            // Get groups user is member of
            $query = "SELECT g.*, u.name as creator_name,
                      (SELECT COUNT(*) FROM group_chat_members WHERE group_id = g.id) as member_count,
                      (SELECT COUNT(*) FROM group_chat_messages WHERE group_id = g.id) as message_count,
                      (SELECT COUNT(*) FROM group_chat_messages WHERE group_id = g.id AND id > m.last_read_message_id) as unread_count
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
                      COALESCE((SELECT COUNT(*) FROM group_chat_messages gm 
                                WHERE gm.group_id = g.id AND gm.id > 
                                (SELECT last_read_message_id FROM group_chat_members WHERE group_id = g.id AND user_id = ?)), 0) as unread_count,
                      (SELECT COUNT(*) FROM group_chat_members WHERE group_id = g.id AND user_id = ?) as is_member
                      FROM group_chats g
                      INNER JOIN users u ON g.created_by = u.id
                      WHERE g.course_id = ?
                      ORDER BY g.created_at DESC";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iii", $user_id, $user_id, $course_id);
        }
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $groups = [];
    while ($row = $result->fetch_assoc()) {
        $groups[] = $row;
    }
    
    $is_teacher = isset($_SESSION["user"]) && $_SESSION["user"]["role"] === 'teacher';
    echo json_encode(['success' => true, 'groups' => $groups, 'is_teacher' => $is_teacher]);
}

function joinGroup($conn, $user_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    $group_id = isset($input['group_id']) ? intval($input['group_id']) : 0;
    
    if ($group_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid group ID']);
        return;
    }
    
    // Check if group exists and user is enrolled in course OR is teacher
    $check_query = "SELECT g.id, g.course_id FROM group_chats g
                    WHERE g.id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $group_id);
    $check_stmt->execute();
    $res = $check_stmt->get_result();
    
    if ($res->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Group not found']);
        return;
    }
    
    $group = $res->fetch_assoc();
    $course_id = $group['course_id'];
    
    $is_student = false;
    $enroll_check = $conn->prepare("SELECT id FROM course_students WHERE course_id = ? AND student_id = ?");
    $enroll_check->bind_param("ii", $course_id, $user_id);
    $enroll_check->execute();
    if ($enroll_check->get_result()->num_rows > 0) $is_student = true;
    
    $is_teacher = isTeacherOfCourse($conn, $user_id, $course_id);
    
    if (!$is_student && !$is_teacher) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
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
            echo json_encode(['success' => false, 'message' => 'ในฐานะผู้สร้างกลุ่ม คุณไม่สามารถออกจากกลุ่มได้ในขณะที่ยังมีสมาชิกคนอื่นอยู่ กรุณาโอนสิทธิ์ความเป็นเจ้าของให้ผู้อื่นก่อน หรือรอให้สมาชิกทั้งหมดออกจากกลุ่มก่อน']);
            return;
        }
    }
    
    // Remove user from group
    $delete_query = "DELETE FROM group_chat_members WHERE group_id = ? AND user_id = ?";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param("ii", $group_id, $user_id);
    
    if ($delete_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'ออกจากกลุ่มเรียบร้อยแล้ว']);
    } else {
        echo json_encode(['success' => false, 'message' => 'ออกจากกลุ่มไม่สำเร็จ']);
    }
}

function getGroupMembers($conn, $user_id) {
    $group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;
    
    if ($group_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'รหัสกลุ่มไม่ถูกต้อง']);
        return;
    }
    
    // Check if user is a member
    $check_query = "SELECT id FROM group_chat_members WHERE group_id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $group_id, $user_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'ไม่ได้เป็นสมาชิกกลุ่ม']);
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


function getGlobalUnreadCount($conn, $user_id) {
    // Get total unread messages from all groups user is a member of
    $query = "SELECT COUNT(*) as total_unread
              FROM group_chat_messages m
              INNER JOIN group_chat_members mem ON m.group_id = mem.group_id
              WHERE mem.user_id = ? AND m.id > mem.last_read_message_id";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    echo json_encode(['success' => true, 'count' => (int)$result['total_unread']]);
}

function markRead($conn, $user_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    $group_id = isset($input['group_id']) ? intval($input['group_id']) : 0;
    
    if ($group_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'รหัสกลุ่มไม่ถูกต้อง']);
        return;
    }
    
    // Get latest message ID in group
    $latest_query = "SELECT MAX(id) as max_id FROM group_chat_messages WHERE group_id = ?";
    $latest_stmt = $conn->prepare($latest_query);
    $latest_stmt->bind_param("i", $group_id);
    $latest_stmt->execute();
    $latest_id = $latest_stmt->get_result()->fetch_assoc()['max_id'] ?? 0;
    
    // Update last_read_message_id
    $update_query = "UPDATE group_chat_members SET last_read_message_id = ? WHERE group_id = ? AND user_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("iii", $latest_id, $group_id, $user_id);
    
    if ($update_stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'ทำเครื่องหมายว่าอ่านแล้วไม่สำเร็จ']);
    }
}

function deleteGroup($conn, $user_id) {
    if (!isset($_SESSION["user"]) || $_SESSION["user"]["role"] !== "teacher") {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $group_id = isset($input['group_id']) ? intval($input['group_id']) : 0;
    
    if ($group_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'รหัสกลุ่มไม่ถูกต้อง']);
        return;
    }
    
    $conn->begin_transaction();
    try {
        $stmt_msg = $conn->prepare("DELETE FROM group_chat_messages WHERE group_id = ?");
        $stmt_msg->bind_param("i", $group_id);
        $stmt_msg->execute();
        
        $stmt_mem = $conn->prepare("DELETE FROM group_chat_members WHERE group_id = ?");
        $stmt_mem->bind_param("i", $group_id);
        $stmt_mem->execute();
        
        $stmt_grp = $conn->prepare("DELETE FROM group_chats WHERE id = ?");
        $stmt_grp->bind_param("i", $group_id);
        $stmt_grp->execute();
        
        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'ลบกลุ่มไม่สำเร็จ']);
    }
}

$conn->close();
?>