<?php
session_start();
include "../config/db.php";

header('Content-Type: application/json');
error_reporting(0);

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user = $_SESSION['user'];
$student_id = (int) $user['id'];
$action = $_GET['action'] ?? '';

function error($msg) {
    echo json_encode(['success' => false, 'message' => $msg]);
    exit();
}

function success($data = []) {
    echo json_encode(array_merge(['success' => true], $data));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- UPDATE PROFILE ---
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $phone = trim($_POST['phone'] ?? '');
        $rank = trim($_POST['rank'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $affiliation = trim($_POST['affiliation'] ?? '');
        $password = $_POST['new_password'] ?? '';
        
        if (empty($name) || empty($email)) {
            error('กรุณากรอกชื่อและอีเมล');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error('รูปแบบอีเมลไม่ถูกต้อง');
        }

        // Check if email already exists for other user
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_stmt->bind_param("si", $email, $student_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            error('อีเมลนี้ถูกใช้งานแล้ว');
        }

        // Handle Avatar Upload
        $avatar_sql = "";
        $params = [];
        $types = "";
        
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ["image/jpeg", "image/png", "image/jpg", "image/gif"];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            $file_type = $_FILES['avatar']['type'];
            $file_size = $_FILES['avatar']['size'];
            
            if (!in_array($file_type, $allowed_types)) {
                error('อนุญาตเฉพาะไฟล์รูปภาพ (JPG, PNG, GIF)');
            }
            if ($file_size > $max_size) {
                error('ขนาดไฟล์ต้องไม่เกิน 2MB');
            }

            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            $new_filename = uniqid() . "_" . time() . "." . $ext;
            $upload_dir = "../uploads/avatars/";
            
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_dir . $new_filename)) {
                $avatar_path = "uploads/avatars/" . $new_filename;
                
                // Delete old avatar if exists and strictly local
                if (!empty($user['avatar']) && file_exists("../" . $user['avatar'])) {
                    unlink("../" . $user['avatar']);
                }
                
                $avatar_sql = ", avatar = ?";
                $params[] = $avatar_path;
                $types .= "s";
                
                // Update session immediately for avatar
                $_SESSION['user']['avatar'] = $avatar_path;
            }
        }

        // Build Query
        $sql = "UPDATE users SET name = ?, email = ?, phone = ?, rank = ?, position = ?, affiliation = ?" . $avatar_sql;
        $base_params = [$name, $email, $phone, $rank, $position, $affiliation];
        $base_types = "ssssss";
        
        // Add Password if provided
        if (!empty($password)) {
            if (strlen($password) < 8) {
                error('รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร');
            }
            $sql .= ", password = ?";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
            $types .= "s";
        }

        $sql .= " WHERE id = ?";
        
        // Combine all params
        $final_params = array_merge($base_params, $params, [$student_id]);
        $final_types = $base_types . $types . "i";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($final_types, ...$final_params);
        
        if ($stmt->execute()) {
            // Update Session Data
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['phone'] = $phone;
            $_SESSION['user']['rank'] = $rank;
            $_SESSION['user']['position'] = $position;
            $_SESSION['user']['affiliation'] = $affiliation;
            
            success();
        } else {
            error('Database error: ' . $stmt->error);
        }
    } else {
        error('Invalid action');
    }
} else {
    error('Invalid method');
}
