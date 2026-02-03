<?php
// Start session FIRST before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "online_study");

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

// Set charset
$conn->set_charset("utf8mb4");
?>