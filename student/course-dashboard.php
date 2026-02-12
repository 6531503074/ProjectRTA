<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION["user"]) || $_SESSION["user"]["role"] !== "student") {
    header("Location: ../auth/login.php");
    exit();
}

$user = $_SESSION["user"];
$student_id = $user["id"];
$course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($course_id <= 0) {
    header("Location: dashboard.php");
    exit();
}

// Mark announcements as read
$conn->query("
    INSERT IGNORE INTO announcement_reads (announcement_id, student_id)
    SELECT id, $student_id
    FROM announcements
    WHERE course_id = $course_id
");

// Get course details
$course_query = "SELECT c.*, u.name as teacher_name 
                 FROM courses c 
                 LEFT JOIN users u ON c.teacher_id = u.id 
                 WHERE c.id = ?";
$course_stmt = $conn->prepare($course_query);
$course_stmt->bind_param("i", $course_id);
$course_stmt->execute();
$course = $course_stmt->get_result()->fetch_assoc();

if (!$course) {
    header("Location: dashboard.php");
    exit();
}

// Check if student is enrolled
$enrollment_check = "SELECT id FROM course_students WHERE course_id = ? AND student_id = ?";
$enrollment_stmt = $conn->prepare($enrollment_check);
$enrollment_stmt->bind_param("ii", $course_id, $student_id);
$enrollment_stmt->execute();
if ($enrollment_stmt->get_result()->num_rows == 0) {
    header("Location: dashboard.php");
    exit();
}

// Get course materials
$materials_query = "SELECT * FROM course_materials WHERE course_id = ? ORDER BY uploaded_at DESC";
$materials_stmt = $conn->prepare($materials_query);
$materials_stmt->bind_param("i", $course_id);
$materials_stmt->execute();
$materials = $materials_stmt->get_result();

// Get assignments with submission status
$assignments_query = "SELECT a.*, 
                      s.id as submission_id,
                      s.submission_text,
                      s.file_path,
                      s.submitted_at,
                      s.grade,
                      s.feedback,
                      (SELECT COUNT(*) FROM assignment_chat WHERE assignment_id = a.id) as chat_count
                      FROM assignments a
                      LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = ?
                      WHERE a.course_id = ?
                      ORDER BY a.due_date ASC";
$assignments_stmt = $conn->prepare($assignments_query);
$assignments_stmt->bind_param("ii", $student_id, $course_id);
$assignments_stmt->execute();
$assignments = $assignments_stmt->get_result();

// Get Active Tests
$tests_query = "SELECT * FROM course_tests WHERE course_id = ? AND is_active = 1 ORDER BY test_type DESC, id ASC"; // Pre then Post? No, 'pre' < 'post'. DESC means Post then Pre. Let's sorting later or just handle loop.
$tests_stmt = $conn->prepare($tests_query);
$tests_stmt->bind_param("i", $course_id);
$tests_stmt->execute();
$active_tests = $tests_stmt->get_result();

// Get announcements
$announcements_query = "SELECT * FROM announcements WHERE course_id = ? ORDER BY created_at DESC LIMIT 5";
$announcements_stmt = $conn->prepare($announcements_query);
$announcements_stmt->bind_param("i", $course_id);
$announcements_stmt->execute();
$announcements = $announcements_stmt->get_result();
// Thai Date Helper
function th_dt($datetime)
{
    if (!$datetime)
        return '-';
    $timestamp = strtotime($datetime);
    $months_th = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $day = date('j', $timestamp);
    $month = $months_th[(int) date('n', $timestamp)];
    $year = (int) date('Y', $timestamp) + 543;
    $time = date('H:i', $timestamp);
    return "$day $month $year $time น.";
}

function th_date($date)
{
    if (!$date)
        return '-';
    $timestamp = strtotime($date);
    $months_th = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $day = date('j', $timestamp);
    $month = $months_th[(int) date('n', $timestamp)];
    $year = (int) date('Y', $timestamp) + 543;
    return "$day $month $year";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($course['title']) ?> - CyberLearn</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }

        .main-content {
            margin-left: 280px;
            padding: 0;
            min-height: 100vh;
        }

        /* Course Header */
        .course-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .course-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .course-header .teacher {
            font-size: 16px;
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .course-header .description {
            margin-top: 15px;
            font-size: 14px;
            opacity: 0.9;
            line-height: 1.6;
        }

        /* Content Container */
        .content-container {
            padding: 30px 40px;
            max-width: 1400px;
        }

        /* Materials Section */
        .materials-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }

        .section-header h2 {
            font-size: 20px;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #2d3748;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
        }

        .btn-success {
            background: #48bb78;
            color: white;
        }

        .btn-success:hover {
            background: #38a169;
        }

        .btn-warning {
            background: #ed8936;
            color: white;
        }

        .btn-warning:hover {
            background: #dd6b20;
        }

        /* Assignments Section */
        .assignment-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }

        .assignment-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .assignment-card.overdue {
            border-left-color: #f56565;
            background: #fffafa;
        }

        .assignment-card.submitted {
            border-left-color: #48bb78;
            background: #f0fff4;
        }

        .assignment-card.graded {
            border-left-color: #4299e1;
            background: #ebf8ff;
        }

        .assignment-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .assignment-title {
            font-size: 18px;
            color: #2d3748;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .assignment-meta {
            display: flex;
            gap: 15px;
            font-size: 13px;
            color: #718096;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .assignment-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .status-badge {
            padding: 6px 14px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-pending {
            background: #fff5f0;
            color: #ed8936;
            border: 1px solid #fbd38d;
        }

        .status-submitted {
            background: #f0fff4;
            color: #38a169;
            border: 1px solid #9ae6b4;
        }

        .status-graded {
            background: #ebf8ff;
            color: #3182ce;
            border: 1px solid #90cdf4;
        }

        .status-overdue {
            background: #fff5f5;
            color: #e53e3e;
            border: 1px solid #fc8181;
        }

        .assignment-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .assignment-chat {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px dashed #e2e8f0;
        }

        /* Submission Details Section */
        .submission-details {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin: 15px 0;
        }

        .submission-details-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #edf2f7;
        }

        .submission-details-header h4 {
            color: #2d3748;
            font-size: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .submission-toggle {
            cursor: pointer;
            color: #667eea;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .submission-toggle:hover {
            color: #5568d3;
        }

        .submission-content {
            background: #f7fafc;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 12px;
        }

        .submission-label {
            font-size: 12px;
            font-weight: 600;
            color: #718096;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .submission-text {
            color: #2d3748;
            font-size: 14px;
            line-height: 1.7;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .submission-file {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: #edf2f7;
            border-radius: 6px;
            margin-top: 10px;
            transition: all 0.3s ease;
        }

        .submission-file:hover {
            background: #e2e8f0;
        }

        .submission-file .file-icon {
            font-size: 24px;
        }

        .submission-file .file-info {
            flex: 1;
        }

        .submission-file .file-name {
            color: #2d3748;
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 3px;
        }

        .submission-file .file-size {
            color: #718096;
            font-size: 11px;
        }

        .submission-file a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            padding: 6px 12px;
            border-radius: 5px;
            background: white;
            transition: all 0.3s ease;
        }

        .submission-file a:hover {
            background: #667eea;
            color: white;
        }

        .submission-meta {
            font-size: 12px;
            color: #718096;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #edf2f7;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .submission-collapsed {
            display: none;
        }

        .submission-expanded {
            display: block;
        }

        /* Feedback Section */
        .feedback-section {
            background: linear-gradient(135deg, #f0fff4 0%, #e6fffa 100%);
            border: 2px solid #9ae6b4;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }

        .feedback-section.neutral {
            background: linear-gradient(135deg, #ebf8ff 0%, #e6fffa 100%);
            border-color: #90cdf4;
        }

        .feedback-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }

        .feedback-header strong {
            color: #2f855a;
            font-size: 14px;
        }

        .feedback-content {
            color: #2d3748;
            font-size: 13px;
            line-height: 1.6;
        }

        .grade-display {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 13px;
        }

        .chat-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .chat-toggle:hover {
            text-decoration: underline;
        }

        .chat-messages {
            display: none;
            margin-top: 15px;
            max-height: 340px;
            overflow-y: none;
            background: #f7fafc;
            border-radius: 8px;
            padding: 15px;
        }

        .chat-messages.show {
            display: block;
        }

        .messages-list {
            max-height: 250px;
            overflow-y: auto;
            margin-bottom: 15px;
        }

        .chat-message {
            background: white;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            width: 100%;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .chat-message .sender {
            font-weight: 600;
            color: #2d3748;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .chat-message .message {
            color: #4a5568;
            font-size: 13px;
            line-height: 1.5;
            word-break: break-word;
            white-space: pre-wrap;
        }

        .chat-message .time {
            font-size: 11px;
            color: #a0aec0;
            margin-top: 5px;
        }

        .chat-input-container {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            align-items: flex-end;
        }

        .chat-input-container textarea {
            flex: 1;
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            resize: none;
            overflow: hidden;
            min-height: 44px;
            max-height: 150px;
        }

        .chat-input-container textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        /* Announcements */
        .announcement-card {
            background: #fff8e1;
            border-left: 4px solid #ffa726;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .announcement-card .content {
            color: #2d3748;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 8px;
        }

        .announcement-card:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .announcement-card .time {
            font-size: 12px;
            color: #718096;
        }

        /* Floating Chat Button */
        .floating-chat-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
            z-index: 999;
        }

        .floating-chat-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.4);
        }

        .chat-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #e74c3c;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
        }

        /* Floating Chat Window */
        .floating-chat-window {
            position: fixed;
            bottom: 100px;
            right: 30px;
            width: 380px;
            height: 500px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            display: none;
            flex-direction: column;
            z-index: 1000;
        }

        .floating-chat-window.show {
            display: flex;
        }

        .chat-window-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chat-window-header h3 {
            font-size: 16px;
        }

        .chat-window-close {
            cursor: pointer;
            font-size: 20px;
            opacity: 0.8;
        }

        .chat-window-close:hover {
            opacity: 1;
        }

        .chat-window-tabs {
            display: flex;
            background: #f7fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .chat-tab {
            flex: 1;
            padding: 12px;
            text-align: center;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #718096;
            transition: all 0.3s ease;
        }

        .chat-tab.active {
            background: white;
            color: #667eea;
            border-bottom: 2px solid #667eea;
        }

        .chat-window-content {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
        }

        .group-chat-item {
            background: #f7fafc;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .group-chat-item:hover {
            background: #edf2f7;
            transform: translateX(5px);
        }

        .group-chat-item .name {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .group-chat-item .members {
            font-size: 12px;
            color: #718096;
        }

        .create-group-btn {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }

        .modal.show {
            display: flex;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 600px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }

        .modal-header h3 {
            font-size: 20px;
            color: #2d3748;
        }

        .modal-close {
            font-size: 28px;
            cursor: pointer;
            color: #a0aec0;
            line-height: 1;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            color: #2d3748;
            transform: rotate(90deg);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3748;
            font-size: 14px;
        }

        .form-group textarea,
        .form-group input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-group textarea:focus,
        .form-group input[type="file"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .current-file-display {
            margin-top: 10px;
            padding: 10px;
            background: #f7fafc;
            border-radius: 6px;
            font-size: 13px;
        }

        .current-file-display a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .current-file-display a:hover {
            text-decoration: underline;
        }


        .material-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .material-item:hover {
            background: #edf2f7;
            transform: translateX(5px);
        }

        .material-item .info {
            flex: 1;
        }

        .material-item .name {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .material-item .size {
            font-size: 12px;
            color: #718096;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #a0aec0;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .empty-state p {
            font-size: 16px;
        }

        /* Loading Spinner */
        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Confirmation Dialog */
        .confirm-dialog {
            background: white;
            padding: 25px;
            border-radius: 12px;
            max-width: 400px;
            text-align: center;
        }

        .confirm-dialog-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .confirm-dialog-message {
            font-size: 16px;
            color: #2d3748;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .confirm-dialog-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }

            .content-container {
                padding: 20px;
            }

            .course-header {
                padding: 25px 20px;
            }

            .course-header h1 {
                font-size: 24px;
            }

            .floating-chat-window {
                width: calc(100% - 40px);
                right: 20px;
                bottom: 90px;
            }

            .assignment-actions {
                flex-direction: column;
            }

            .assignment-actions button {
                width: 100%;
            }

            .assignment-meta {
                flex-direction: column;
                gap: 8px;
            }

            .modal-content {
                padding: 20px;
            }
        }
    </style>
</head>

<body>
    <?php include "../components/student-sidebar.php"; ?>

    <div class="main-content">
        <!-- Course Header -->
        <div class="course-header">
            <h1><?= htmlspecialchars($course['title']) ?></h1>
            <div class="teacher">
                <span>👨‍🏫</span>
                <span>ผู้สอน: <?= htmlspecialchars($course['teacher_name'] ?? 'ไม่ได้ระบุ') ?></span>
            </div>
            <?php if ($course['description']): ?>
                <div class="description"><?= htmlspecialchars($course['description']) ?></div>
            <?php endif; ?>
        </div>

        <div class="content-container">
            <!-- Course Materials Section -->
            <div class="materials-section">
                <div class="section-header">
                    <h2>📚 เอกสารประกอบการเรียน</h2>
                    <button class="btn-primary" onclick="openMaterialsModal()">
                        📥 ดาวน์โหลดเอกสาร
                    </button>
                </div>
                <p style="color: #718096; font-size: 14px;">
                    เข้าถึงเอกสารประกอบการเรียน บันทึกการบรรยาย และแหล่งข้อมูลทั้งหมดที่นี่
                </p>
            </div>

            <!-- Announcements Section -->
            <?php if ($announcements->num_rows > 0): ?>
                <div class="materials-section">
                    <div class="section-header">
                        <h2>📢 ประกาศ</h2>
                    </div>
                    <?php while ($announcement = $announcements->fetch_assoc()): ?>
                        <div class="announcement-card">
                            <div class="content"><?php 
                                $content = htmlspecialchars($announcement['content']);
                                $pattern = '/(https?:\/\/[^\s]+)/';
                                echo nl2br(preg_replace($pattern, '<a href="$1" target="_blank">$1</a>', $content));
                            ?></div>
                            <div class="time"><?= th_dt($announcement['created_at']) ?></div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>

            <!-- Tests Section -->
            <?php if ($active_tests->num_rows > 0): ?>
                <div class="materials-section" style="border-left: 4px solid #3182ce;">
                    <div class="section-header">
                        <h2>📝 แบบทดสอบ (Tests)</h2>
                    </div>
                    <?php
                    $test_counters = ['pre' => 0, 'post' => 0];
                    while ($test = $active_tests->fetch_assoc()):
                        $type = $test['test_type'];
                        $test_counters[$type]++;
                        $typeLabel = !empty($test['title']) ? htmlspecialchars($test['title']) : (($type === 'pre' ? 'แบบทดสอบก่อนเรียน (Pre-test)' : 'แบบทดสอบหลังเรียน (Post-test)') . " ชุดที่ " . $test_counters[$type]);

                        // Check if submitted
                        $chk = $conn->prepare("SELECT score, total_points FROM student_test_attempts WHERE test_id = ? AND student_id = ?");
                        $chk->bind_param("ii", $test['id'], $student_id);
                        $chk->execute();
                        $res = $chk->get_result();
                        $attempt = $res->fetch_assoc();
                        $is_done = ($attempt !== null);
                        ?>
                        <div class="material-item">
                            <div class="info">
                                <div class="name"><?= $typeLabel ?></div>
                                <div class="size">
                                    <?php if ($is_done): ?>
                                        <span style="color: green;">✅ ทำแล้ว</span>
                                    <?php else: ?>
                                        <span style="color: #e53e3e;">⏳ ยังไม่ได้ทำ</span>
                                        • เวลา:
                                        <?= $test['time_limit_minutes'] > 0 ? $test['time_limit_minutes'] . ' นาที' : 'ไม่จำกัด' ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!$is_done): ?>
                                <button class="btn-secondary btn-primary"
                                    onclick="openTestModal(<?= $test['id'] ?>, '<?= $test['test_type'] ?>')">
                                    เริ่มทำแบบทดสอบ
                                </button>
                            <?php else: ?>
                                <button class="btn-secondary" disabled>✅ ส่งแล้ว</button>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>

            <!-- Assignments Section -->
            <div class="section-header" style="margin-top: 20px;">
                <h2>📝 งานที่ได้รับมอบหมาย</h2>
            </div>

            <?php if ($assignments->num_rows > 0): ?>
                <?php while ($assignment = $assignments->fetch_assoc()):
                    $due_date = new DateTime($assignment['due_date']);
                    $today = new DateTime();
                    $is_overdue = $today > $due_date && !$assignment['submission_id'];
                    $is_submitted = $assignment['submission_id'] != null;
                    $is_graded = $is_submitted && $assignment['grade'] != null;
                    ?>
                    <div
                        class="assignment-card <?= $is_graded ? 'graded' : ($is_overdue ? 'overdue' : ($is_submitted ? 'submitted' : '')) ?>">
                        <div class="assignment-header">
                            <div style="flex: 1;">
                                <h3 class="assignment-title"><?= htmlspecialchars($assignment['title']) ?></h3>
                                <div class="assignment-meta">
                                    <span>📅 กำหนดส่ง: <?= th_dt($assignment['due_date']) ?></span>
                                    <?php if ($is_submitted): ?>
                                        <span>✅ ส่งเมื่อ: <?= th_date($assignment['submitted_at']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($is_graded): ?>
                                        <!-- <span class="grade-display">📊 Grade: <?= htmlspecialchars($assignment['grade']) ?></span> -->
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span
                                class="status-badge <?= $is_graded ? 'status-graded' : ($is_submitted ? 'status-submitted' : ($is_overdue ? 'status-overdue' : 'status-pending')) ?>">
                                <?= $is_graded ? '✓ ให้คะแนนแล้ว' : ($is_submitted ? '✓ ส่งแล้ว' : ($is_overdue ? '⚠ เลยกำหนดส่ง' : '⏳ รอส่ง')) ?>
                            </span>
                        </div>

                        <?php if ($assignment['description']): ?>
                            <div style="color: #4a5568; font-size: 14px; margin-bottom: 15px; line-height: 1.6;">
                                <?php
                                $desc = htmlspecialchars($assignment['description']);
                                // Auto-link URLs
                                $desc = preg_replace('/(https?:\/\/[^\s]+)/', '<a href="$1" target="_blank" style="color: #667eea; text-decoration: underline;">$1</a>', $desc);
                                echo nl2br($desc);
                                ?>
                            </div>
                        <?php endif; ?>

                        <!-- Show Submission Details -->
                        <?php if ($is_submitted): ?>
                            <div class="submission-details">
                                <div class="submission-details-header">
                                    <h4>📋 งานที่ส่ง</h4>
                                    <span class="submission-toggle"
                                        onclick="toggleSubmissionDetails(<?= $assignment['submission_id'] ?>)">
                                        <span id="toggle-text-<?= $assignment['submission_id'] ?>">ซ่อน</span>
                                        <span id="toggle-icon-<?= $assignment['submission_id'] ?>">▲</span>
                                    </span>
                                </div>

                                <div id="submission-details-<?= $assignment['submission_id'] ?>" class="submission-expanded">
                                    <?php if ($assignment['submission_text']): ?>
                                        <div class="submission-content">
                                            <div class="submission-label">ข้อความที่ส่ง:</div>
                                            <div class="submission-text"><?= nl2br(htmlspecialchars($assignment['submission_text'])) ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($assignment['file_path']): ?>
                                        <div class="submission-file">
                                            <span class="file-icon">📎</span>
                                            <div class="file-info">
                                                <div class="file-name"><?= basename($assignment['file_path']) ?></div>
                                                <div class="file-size">
                                                    <?php
                                                    $file_full_path = "../" . $assignment['file_path'];
                                                    if (file_exists($file_full_path)) {
                                                        $file_size = filesize($file_full_path);
                                                        echo number_format($file_size / 1024, 2) . " KB";
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                            <a href="../<?= htmlspecialchars($assignment['file_path']) ?>" target="_blank" download>
                                                📥 ดาวน์โหลด
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <div class="submission-meta">
                                        <span>🕒</span>
                                        <span>ส่งเมื่อ <?= th_dt($assignment['submitted_at']) ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($is_graded && $assignment['feedback']): ?>
                            <div class="feedback-section">
                                <div class="feedback-header">
                                    <span>💬</span>
                                    <strong>ข้อเสนอแนะจากผู้สอน</strong>
                                </div>
                                <div class="feedback-content">
                                    <?= nl2br(htmlspecialchars($assignment['feedback'])) ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="assignment-actions">
                            <?php if (!$is_submitted): ?>
                                <button class="btn-primary"
                                    onclick="openSubmissionModal(<?= $assignment['id'] ?>, '<?= htmlspecialchars(addslashes($assignment['title'])) ?>')">
                                    📤 ส่งงาน
                                </button>
                            <?php else: ?>
                                <?php if (!$is_graded): ?>
                                    <!-- Edit Submission Button (only if not graded) -->
                                    <button class="btn-secondary btn-warning"
                                        onclick="editSubmission(<?= $assignment['id'] ?>, <?= $assignment['submission_id'] ?>, '<?= htmlspecialchars(addslashes($assignment['title'])) ?>')">
                                        ✏️ แก้ไขการส่ง
                                    </button>

                                    <!-- Cancel Submission Button (only if not graded) -->
                                    <button class="btn-secondary btn-danger"
                                        onclick="confirmCancelSubmission(<?= $assignment['submission_id'] ?>, <?= $assignment['id'] ?>)">
                                        ❌ ยกเลิกการส่ง
                                    </button>
                                <?php else: ?>
                                    <button class="btn-secondary btn-success" disabled style="opacity: 0.7;">
                                        ✓ ให้คะแนนแล้ว
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>

                        </div>
                        <!-- Assignment Chat -->
                        <div class="assignment-chat">
                            <div class="chat-toggle" onclick="toggleAssignmentChat(<?= $assignment['id'] ?>)">
                                💬 สนทนาเกี่ยวกับงาน (<?= $assignment['chat_count'] ?> ข้อความ)
                            </div>
                            <div class="chat-messages" id="chat-<?= $assignment['id'] ?>">
                                <div class="empty-state" style="padding: 20px;">
                                    <p>เริ่มการสนทนาเกี่ยวกับงานนี้</p>
                                </div>
                                <div class="chat-input-container">
                                    <textarea placeholder="พิมพ์ข้อความ..." id="chat-input-<?= $assignment['id'] ?>"
                                        rows="1"></textarea>
                                    <button class="btn-primary"
                                        onclick="sendAssignmentMessage(<?= $assignment['id'] ?>)">ส่ง</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📝</div>
                    <p>ยังไม่มีงานที่ได้รับมอบหมาย</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Floating Group Chat Button -->
    <div class="floating-chat-btn" onclick="toggleFloatingChat()">
        💬
        <span class="chat-badge" id="chatBadge" style="display: none;">0</span>
    </div>

    <!-- Floating Chat Window -->
    <div class="floating-chat-window" id="floatingChat">
        <div class="chat-window-header">
            <h3>แชทกลุ่ม</h3>
            <span class="chat-window-close" onclick="toggleFloatingChat()">×</span>
        </div>

        <div class="chat-window-tabs">
            <div class="chat-tab active" onclick="switchChatTab('groups')">กลุ่มของฉัน</div>
            <div class="chat-tab" onclick="switchChatTab('all')">กลุ่มทั้งหมด</div>
        </div>

        <div class="chat-window-content" id="chatContent">
            <div class="empty-state" style="padding: 60px 20px;">
                <div class="empty-state-icon">💬</div>
                <p>ยังไม่มีแชทกลุ่ม</p>
            </div>
            <button class="create-group-btn" onclick="chatManager.openCreateGroupModal()">
                ➕ สร้างกลุ่มใหม่
            </button>
        </div>
    </div>

    <!-- Materials Modal -->
    <div class="modal" id="materialsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>เอกสารประกอบการเรียน</h3>
                <span class="modal-close" onclick="closeMaterialsModal()">×</span>
            </div>
            <div id="materialsContent">
                <?php if ($materials->num_rows > 0): ?>
                    <?php while ($material = $materials->fetch_assoc()): ?>
                        <div class="material-item">
                            <div class="info">
                                <div class="name">📄 <?= htmlspecialchars($material['title']) ?></div>
                                <?php if (isset($material['file_size'])): ?>
                                    <div class="size"><?= round($material['file_size'] / 1024, 2) ?> KB</div>
                                <?php endif; ?>
                            </div>
                            <a href="../<?= htmlspecialchars($material['file_path']) ?>" class="btn-secondary btn-success"
                                download target="_blank"
                                style="text-decoration: none; display: flex; align-items: center; gap: 5px;">
                                📥 ดาวน์โหลด
                            </a>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📚</div>
                        <p>ยังไม่มีเอกสารในขณะนี้</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Submission Modal -->
    <div class="modal" id="submissionModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="submissionModalTitle">ส่งงาน</h3>
                <span class="modal-close" onclick="closeSubmissionModal()">×</span>
            </div>
            <form id="submissionForm" onsubmit="submitAssignment(event)">
                <input type="hidden" id="assignmentId" name="assignment_id">
                <input type="hidden" id="submissionId" name="submission_id">
                <input type="hidden" id="isEdit" name="is_edit" value="0">

                <div class="form-group">
                    <label for="submissionText">รายละเอียดการส่ง</label>
                    <textarea id="submissionText" name="submission_text" rows="8"
                        placeholder="พิมพ์รายละเอียดการส่งงาน แปะลิงก์ หรืออธิบายงานของคุณ..."></textarea>
                </div>

                <div class="form-group">
                    <label for="submissionFile">แนบไฟล์ (ไม่บังคับ)</label>
                    <input type="file" id="submissionFile" name="submission_file"
                        accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.gif,.zip,.rar,.ppt,.pptx,.xls,.xlsx">
                    <div id="currentFileDisplay" class="current-file-display" style="display: none;">
                        <span style="color: #718096;">📎 ไฟล์ปัจจุบัน: </span>
                        <a id="currentFileLink" href="#" target="_blank">
                            <span id="currentFileName"></span>
                        </a>
                        <div style="font-size: 11px; color: #a0aec0; margin-top: 5px;">
                            💡 อัปโหลดไฟล์ใหม่เพื่อแทนที่ หรือเว้นว่างไว้เพื่อใช้ไฟล์เดิม
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%;" id="submitButton">
                    <span id="submitButtonText">ส่งงาน</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal" id="confirmModal">
        <div class="confirm-dialog">
            <div class="confirm-dialog-icon">⚠️</div>
            <div class="confirm-dialog-message" id="confirmMessage">
                คุณแน่ใจหรือไม่ที่จะยกเลิกการส่งงานนี้? การดำเนินการนี้ไม่สามารถย้อนกลับได้
            </div>
            <div class="confirm-dialog-actions">
                <button class="btn-secondary" onclick="closeConfirmModal()">
                    ยกเลิก
                </button>
                <button class="btn-danger" id="confirmButton">
                    ใช่, ลบ
                </button>
            </div>
        </div>
    </div>


    <!-- Test Modal -->
    <div class="modal" id="testModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="testModalTitle">แบบทดสอบ</h3>
                <span class="modal-close" onclick="closeTestModal()">×</span>
            </div>
            <div id="testContent">
                <p style="color: #718096; margin-bottom: 20px;">แบบทดสอบนี้ประกอบด้วยคำถามที่เกี่ยวข้องกับงาน</p>
                <button class="btn-primary" style="width: 100%;" onclick="startTest()">
                    เริ่มทำแบบทดสอบ
                </button>
            </div>
        </div>
    </div>

    <!-- Create Group Modal -->
    <div class="modal" id="createGroupModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>สร้างแชทกลุ่ม</h3>
                <span class="modal-close" onclick="closeCreateGroupModal()">×</span>
            </div>
            <form id="createGroupForm" onsubmit="createGroup(event)">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">ชื่อกลุ่ม</label>
                    <input type="text" name="group_name" required
                        style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px;"
                        placeholder="ระบุชื่อกลุ่ม...">
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">รายละเอียด</label>
                    <textarea name="group_description" rows="3"
                        style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px;"
                        placeholder="รายละเอียดกลุ่ม..."></textarea>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%;">
                    สร้างกลุ่ม
                </button>
            </form>
        </div>
    </div>

    <!-- Include Chat Manager -->
    <script src="../api/chat.js"></script>
    <script src="../api/assignment_chat.js"></script>

    <script>
        // Initialize Chat Manager
        chatManager = new ChatManager(<?= $course_id ?>, <?= $student_id ?>);

        // Materials Modal
        function openMaterialsModal() {
            document.getElementById('materialsModal').classList.add('show');
        }

        function closeMaterialsModal() {
            document.getElementById('materialsModal').classList.remove('show');
        }



        // Toggle submission details
        function toggleSubmissionDetails(submissionId) {
            const details = document.getElementById(`submission-details-${submissionId}`);
            const toggleText = document.getElementById(`toggle-text-${submissionId}`);
            const toggleIcon = document.getElementById(`toggle-icon-${submissionId}`);

            if (details.classList.contains('submission-expanded')) {
                details.classList.remove('submission-expanded');
                details.classList.add('submission-collapsed');
                toggleText.textContent = 'แสดง';
                toggleIcon.textContent = '▼';
            } else {
                details.classList.remove('submission-collapsed');
                details.classList.add('submission-expanded');
                toggleText.textContent = 'ซ่อน';
                toggleIcon.textContent = '▲';
            }
        }

        // Submission Modal
        function openSubmissionModal(assignmentId, title) {
            document.getElementById('assignmentId').value = assignmentId;
            document.getElementById('submissionId').value = '';
            document.getElementById('isEdit').value = '0';
            document.getElementById('submissionModalTitle').textContent = `📤 ส่งงาน: ${title}`;
            document.getElementById('submitButtonText').textContent = 'ส่งงาน';
            document.getElementById('submissionText').value = '';
            document.getElementById('submissionFile').value = '';
            document.getElementById('currentFileDisplay').style.display = 'none';
            document.getElementById('submissionModal').classList.add('show');
        }

        function closeSubmissionModal() {
            document.getElementById('submissionModal').classList.remove('show');
            document.getElementById('submissionForm').reset();
        }


        // Edit Submission
        async function editSubmission(assignmentId, submissionId, title) {
            try {
                const response = await fetch(`../api/get_submission.php?id=${submissionId}`);
                const data = await response.json();

                if (data.success) {
                    const sub = data.submission;

                    // Populate form
                    document.getElementById('assignmentId').value = assignmentId;
                    document.getElementById('submissionId').value = submissionId;
                    document.getElementById('isEdit').value = '1';
                    document.getElementById('submissionModalTitle').textContent = `✏️ แก้ไข: ${title}`;
                    document.getElementById('submitButtonText').textContent = 'อัปเดตการส่งงาน';
                    document.getElementById('submissionText').value = sub.submission_text || '';

                    // Show current file if exists
                    if (sub.file_path) {
                        document.getElementById('currentFileDisplay').style.display = 'block';
                        document.getElementById('currentFileName').textContent = sub.file_path.split('/').pop();
                        document.getElementById('currentFileLink').href = '../' + sub.file_path;
                    } else {
                        document.getElementById('currentFileDisplay').style.display = 'none';
                    }

                    document.getElementById('submissionModal').classList.add('show');
                } else {
                    alert('❌ ' + (data.message || 'ไม่สามารถโหลดข้อมูลการส่งงานได้'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('❌ เกิดข้อผิดพลาดขณะโหลดข้อมูลการส่งงาน');
            }
        }

        // Submit or Update Assignment
        async function submitAssignment(e) {
            e.preventDefault();

            const submitButton = document.getElementById('submitButton');
            const originalText = document.getElementById('submitButtonText').textContent;

            // Show loading state
            submitButton.disabled = true;
            document.getElementById('submitButtonText').innerHTML = '<span class="spinner"></span> กำลังประมวลผล...';

            const formData = new FormData(e.target);

            try {
                const response = await fetch('../api/submit_assignment.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    alert('✅ ' + data.message);
                    closeSubmissionModal();
                    location.reload();
                } else {
                    alert('❌ ' + (data.message || 'การดำเนินการล้มเหลว'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('❌ เกิดข้อผิดพลาด โปรดลองใหม่อีกครั้ง');
            } finally {
                submitButton.disabled = false;
                document.getElementById('submitButtonText').textContent = originalText;
            }
        }

        // Confirm Cancel Submission
        function confirmCancelSubmission(submissionId, assignmentId) {
            document.getElementById('confirmMessage').textContent =
                'คุณแน่ใจหรือไม่ที่จะยกเลิกการส่งงานนี้? การดำเนินการนี้ไม่สามารถย้อนกลับได้ และงานของคุณจะถูกลบทั้งหมด';
            document.getElementById('confirmButton').onclick = () => cancelSubmission(submissionId);
            document.getElementById('confirmModal').classList.add('show');
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').classList.remove('show');
        }

        // Cancel Submission
        async function cancelSubmission(submissionId) {
            closeConfirmModal();

            const formData = new FormData();
            formData.append('submission_id', submissionId);

            try {
                const response = await fetch('../api/cancel_submission.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    alert('✅ ' + data.message);
                    location.reload();
                } else {
                    alert('❌ ' + (data.message || 'ยกเลิกการส่งงานล้มเหลว'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('❌ เกิดข้อผิดพลาด โปรดลองใหม่อีกครั้ง');
            }
        }

        // Test Modal
        let currentTestId = 0;
        function openTestModal(testId, testType) {
            currentTestId = testId;
            const title = testType === 'pre' ? 'แบบทดสอบก่อนเรียน' : 'แบบทดสอบหลังเรียน';
            document.getElementById('testModalTitle').textContent = title;
            document.getElementById('testModal').classList.add('show');
        }

        function closeTestModal() {
            document.getElementById('testModal').classList.remove('show');
            currentTestId = 0;
        }

        function startTest() {
            if (currentTestId > 0) {
                window.location.href = `take_test.php?test_id=${currentTestId}`;
            }
        }

        // Floating Chat
        function toggleFloatingChat() {
            const floatingChat = document.getElementById('floatingChat');
            floatingChat.classList.toggle('show');

            if (floatingChat.classList.contains('show')) {
                chatManager.loadGroups('my');
            }
        }

        function switchChatTab(tab) {
            document.querySelectorAll('.chat-tab').forEach(t => t.classList.remove('active'));
            event.target.classList.add('active');

            chatManager.loadGroups(tab === 'groups' ? 'my' : 'all');
        }

        function closeCreateGroupModal() {
            document.getElementById('createGroupModal').classList.remove('show');
            document.getElementById('createGroupForm').reset();
        }

        // Create group
        async function createGroup(e) {
            e.preventDefault();
            const formData = new FormData(e.target);

            const data = {
                course_id: <?= $course_id ?>,
                name: formData.get('group_name'),
                description: formData.get('group_description')
            };

            try {
                const response = await fetch('../api/chat_api.php?action=create_group', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    alert('Group created successfully!');
                    closeCreateGroupModal();
                    chatManager.loadGroups('my');
                } else {
                    alert(result.message || 'Failed to create group');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred');
            }
        }

        // Close modals when clicking outside
        window.onclick = function (event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }
        // Keyboard shortcuts
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeSubmissionModal();
                closeTestModal();
                closeMaterialsModal();
                closeConfirmModal();
            }
        });
    </script>
</body>

</html>