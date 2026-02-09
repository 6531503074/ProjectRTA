<?php
// Start session FIRST before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection

$conn = new mysqli("localhost", "csdacc_online_study", "aEJBLdWtGvv5nNNvRWPQ", "csdacc_online_study");

// Check connection
if ($conn->connect_error) {
    // For API calls, return JSON instead of die
    if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit();
    } else {
        die("Database Connection Failed: " . $conn->connect_error);
    }
}
// ตั้ง charset เป็น utf8mb4 (แนะนำ)
$conn->set_charset("utf8mb4");

// ถ้าต้องการกำหนด collation ระดับ session ให้ชัดเจนด้วย (เลือกอย่างใดอย่างหนึ่งที่เหมาะ)
$conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"); 
// หรือถ้าต้องการ 'general_ci' จริง ๆ:
// $conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");

// ตั้ง collation สำหรับการเปรียบเทียบอักขระใน session เพิ่มเติม (ไม่จำเป็นเสมอไป)
// $conn->query("SET collation_connection = 'utf8mb4_unicode_ci'");

echo "Connected and charset set.";

?>  