<?php
// session_start();
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

// Get announcements
$announcements_query = "SELECT * FROM announcements WHERE course_id = ? ORDER BY created_at DESC LIMIT 5";
$announcements_stmt = $conn->prepare($announcements_query);
$announcements_stmt->bind_param("i", $course_id);
$announcements_stmt->execute();
$announcements = $announcements_stmt->get_result();
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
        }

        .assignment-card.overdue {
            border-left-color: #e74c3c;
        }

        .assignment-card.submitted {
            border-left-color: #48bb78;
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
        }

        .assignment-meta {
            display: flex;
            gap: 15px;
            font-size: 13px;
            color: #718096;
            margin-bottom: 15px;
        }

        .assignment-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending {
            background: #fff5f0;
            color: #ed8936;
        }

        .status-submitted {
            background: #f0fff4;
            color: #48bb78;
        }

        .status-graded {
            background: #ebf8ff;
            color: #4299e1;
        }

        .status-overdue {
            background: #fff5f5;
            color: #e74c3c;
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
            max-height: 300px;
            overflow-y: auto;
            background: #f7fafc;
            border-radius: 8px;
            padding: 15px;
        }

        .chat-messages.show {
            display: block;
        }

        .chat-message {
            background: white;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
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
        }

        .chat-input-container input {
            flex: 1;
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
        }

        .chat-input-container input:focus {
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
        }

        .announcement-card .content {
            color: #2d3748;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 8px;
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
            background: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            font-size: 20px;
            color: #2d3748;
        }

        .modal-close {
            font-size: 24px;
            cursor: pointer;
            color: #a0aec0;
        }

        .modal-close:hover {
            color: #2d3748;
        }

        .material-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
            margin-bottom: 10px;
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
                <span>Instructor: <?= htmlspecialchars($course['teacher_name'] ?? 'Not assigned') ?></span>
            </div>
            <?php if ($course['description']): ?>
                <div class="description"><?= htmlspecialchars($course['description']) ?></div>
            <?php endif; ?>
        </div>

        <div class="content-container">
            <!-- Course Materials Section -->
            <div class="materials-section">
                <div class="section-header">
                    <h2>📚 Course Materials</h2>
                    <button class="btn-primary" onclick="openMaterialsModal()">
                        📥 Download Materials
                    </button>
                </div>
                <p style="color: #718096; font-size: 14px;">
                    Access all course materials, lecture notes, and resources here.
                </p>
            </div>

            <!-- Announcements Section -->
            <?php if ($announcements->num_rows > 0): ?>
                <div class="materials-section">
                    <div class="section-header">
                        <h2>📢 Announcements</h2>
                    </div>
                    <?php while ($announcement = $announcements->fetch_assoc()): ?>
                        <div class="announcement-card">
                            <div class="content"><?= nl2br(htmlspecialchars($announcement['content'])) ?></div>
                            <div class="time"><?= date('M d, Y - g:i A', strtotime($announcement['created_at'])) ?></div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>

            <!-- Assignments Section -->
            <div class="section-header" style="margin-top: 20px;">
                <h2>📝 Assignments</h2>
            </div>

            <?php if ($assignments->num_rows > 0): ?>
                <?php while ($assignment = $assignments->fetch_assoc()):
                    $due_date = new DateTime($assignment['due_date']);
                    $today = new DateTime();
                    $is_overdue = $today > $due_date && !$assignment['submission_id'];
                    $is_submitted = $assignment['submission_id'] != null;
                    $is_graded = $is_submitted && $assignment['grade'] != null;
                ?>
                    <div class="assignment-card <?= $is_overdue ? 'overdue' : ($is_submitted ? 'submitted' : '') ?>">
                        <div class="assignment-header">
                            <div>
                                <h3 class="assignment-title"><?= htmlspecialchars($assignment['title']) ?></h3>
                                <div class="assignment-meta">
                                    <span>📅 Due: <?= date('M d, Y', strtotime($assignment['due_date'])) ?></span>
                                    <?php if ($is_graded): ?>
                                        <span>📊 Grade: <?= htmlspecialchars($assignment['grade']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="status-badge <?= $is_graded ? 'status-graded' : ($is_submitted ? 'status-submitted' : ($is_overdue ? 'status-overdue' : 'status-pending')) ?>">
                                <?= $is_graded ? 'Graded' : ($is_submitted ? 'Submitted' : ($is_overdue ? 'Overdue' : 'Pending')) ?>
                            </span>
                        </div>

                        <?php if ($assignment['description']): ?>
                            <div style="color: #4a5568; font-size: 14px; margin-bottom: 15px;">
                                <?= nl2br(htmlspecialchars($assignment['description'])) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($is_graded && $assignment['feedback']): ?>
                            <div style="background: #f0fff4; padding: 12px; border-radius: 6px; margin-bottom: 15px;">
                                <strong style="color: #38a169;">Feedback:</strong>
                                <p style="color: #2d3748; margin-top: 5px; font-size: 13px;">
                                    <?= nl2br(htmlspecialchars($assignment['feedback'])) ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <div class="assignment-actions">
                            <?php if (!$is_submitted): ?>
                                <button class="btn-primary" onclick="openSubmissionModal(<?= $assignment['id'] ?>, '<?= htmlspecialchars($assignment['title']) ?>')">
                                    📤 Submit Work
                                </button>
                            <?php endif; ?>

                            <button class="btn-secondary btn-warning" onclick="openTestModal(<?= $assignment['id'] ?>, 'pre')">
                                📋 Pre-Test
                            </button>

                            <button class="btn-secondary btn-warning" onclick="openTestModal(<?= $assignment['id'] ?>, 'post')">
                                📋 Post-Test
                            </button>
                        </div>

                        <!-- Assignment Chat -->
                        <div class="assignment-chat">
                            <div class="chat-toggle" onclick="toggleAssignmentChat(<?= $assignment['id'] ?>)">
                                💬 Assignment Discussion (<?= $assignment['chat_count'] ?> messages)
                            </div>
                            <div class="chat-messages" id="chat-<?= $assignment['id'] ?>">
                                <div class="empty-state" style="padding: 20px;">
                                    <p>Start a discussion about this assignment</p>
                                </div>
                                <div class="chat-input-container">
                                    <input type="text" placeholder="Type your message..." id="chat-input-<?= $assignment['id'] ?>">
                                    <button class="btn-primary" onclick="sendAssignmentMessage(<?= $assignment['id'] ?>)">Send</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📝</div>
                    <p>No assignments yet</p>
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
            <h3>Group Chats</h3>
            <span class="chat-window-close" onclick="toggleFloatingChat()">×</span>
        </div>

        <div class="chat-window-tabs">
            <div class="chat-tab active" onclick="switchChatTab('groups')">My Groups</div>
            <div class="chat-tab" onclick="switchChatTab('all')">All Groups</div>
        </div>

        <div class="chat-window-content" id="chatContent">
            <div class="empty-state" style="padding: 60px 20px;">
                <div class="empty-state-icon">💬</div>
                <p>No group chats yet</p>
            </div>
            <button class="create-group-btn" onclick="openCreateGroupModal()">
                ➕ Create New Group
            </button>
        </div>
    </div>

    <!-- Materials Modal -->
    <div class="modal" id="materialsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Course Materials</h3>
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
                            <button class="btn-secondary btn-success" onclick="downloadMaterial(<?= $material['id'] ?>)">
                                📥 Download
                            </button>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📚</div>
                        <p>No materials available yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Submission Modal -->
    <div class="modal" id="submissionModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Submit Assignment</h3>
                <span class="modal-close" onclick="closeSubmissionModal()">×</span>
            </div>
            <form id="submissionForm" onsubmit="submitAssignment(event)">
                <input type="hidden" id="assignmentId" name="assignment_id">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Submission Text</label>
                    <textarea
                        name="submission_text"
                        rows="6"
                        style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px;"
                        placeholder="Enter your submission or paste a link..."></textarea>
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Attach File (Optional)</label>
                    <input
                        type="file"
                        name="submission_file"
                        style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px;">
                </div>
                <button type="submit" class="btn-primary" style="width: 100%;">
                    Submit Assignment
                </button>
            </form>
        </div>
    </div>

    <!-- Test Modal -->
    <div class="modal" id="testModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="testModalTitle">Test</h3>
                <span class="modal-close" onclick="closeTestModal()">×</span>
            </div>
            <div id="testContent">
                <p style="color: #718096; margin-bottom: 20px;">This test contains questions related to the assignment.</p>
                <button class="btn-primary" style="width: 100%;" onclick="startTest()">
                    Start Test
                </button>
            </div>
        </div>
    </div>

    <!-- Create Group Modal -->
    <div class="modal" id="createGroupModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create Group Chat</h3>
                <span class="modal-close" onclick="closeCreateGroupModal()">×</span>
            </div>
            <form id="createGroupForm" onsubmit="createGroup(event)">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Group Name</label>
                    <input
                        type="text"
                        name="group_name"
                        required
                        style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px;"
                        placeholder="Enter group name...">
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Description</label>
                    <textarea
                        name="group_description"
                        rows="3"
                        style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px;"
                        placeholder="Group description..."></textarea>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%;">
                    Create Group
                </button>
            </form>
        </div>
    </div>
    <script>
        // Materials Modal
        function openMaterialsModal() {
            document.getElementById('materialsModal').classList.add('show');
        }

        function closeMaterialsModal() {
            document.getElementById('materialsModal').classList.remove('show');
        }

        function downloadMaterial(materialId) {
            window.location.href = `download_material.php?id=${materialId}`;
        }

        // Submission Modal
        function openSubmissionModal(assignmentId, title) {
            document.getElementById('assignmentId').value = assignmentId;
            document.querySelector('#submissionModal h3').textContent = `Submit: ${title}`;
            document.getElementById('submissionModal').classList.add('show');
        }

        function closeSubmissionModal() {
            document.getElementById('submissionModal').classList.remove('show');
            document.getElementById('submissionForm').reset();
        }

        function submitAssignment(e) {
            e.preventDefault();
            const formData = new FormData(e.target);

            fetch('api/submit_assignment.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Assignment submitted successfully!');
                        closeSubmissionModal();
                        location.reload();
                    } else {
                        alert(data.message || 'Submission failed');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred');
                });
        }

        // Test Modal
        function openTestModal(assignmentId, testType) {
            const title = testType === 'pre' ? 'Pre-Test' : 'Post-Test';
            document.getElementById('testModalTitle').textContent = title;
            document.getElementById('testModal').classList.add('show');
        }

        function closeTestModal() {
            document.getElementById('testModal').classList.remove('show');
        }

        function startTest() {
            alert('Test functionality will be implemented');
            closeTestModal();
        }

        // Assignment Chat
        function toggleAssignmentChat(assignmentId) {
            const chatDiv = document.getElementById(`chat-${assignmentId}`);
            chatDiv.classList.toggle('show');

            if (chatDiv.classList.contains('show')) {
                loadAssignmentChat(assignmentId);
            }
        }

        function loadAssignmentChat(assignmentId) {
            // Load chat messages via API
            fetch(`api/get_assignment_chat.php?assignment_id=${assignmentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayChatMessages(assignmentId, data.messages);
                    }
                });
        }

        function displayChatMessages(assignmentId, messages) {
            const chatDiv = document.getElementById(`chat-${assignmentId}`);
            const emptyState = chatDiv.querySelector('.empty-state');

            if (messages.length > 0) {
                emptyState.style.display = 'none';
                // Add messages to chat
                messages.forEach(msg => {
                    // Render message
                });
            }
        }

        function sendAssignmentMessage(assignmentId) {
            const input = document.getElementById(`chat-input-${assignmentId}`);
            const message = input.value.trim();

            if (!message) return;

            fetch('api/send_assignment_message.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        assignment_id: assignmentId,
                        message: message
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        input.value = '';
                        loadAssignmentChat(assignmentId);
                    }
                });
        }

        // Floating Chat
        function toggleFloatingChat() {
            document.getElementById('floatingChat').classList.toggle('show');
        }

        function switchChatTab(tab) {
            document.querySelectorAll('.chat-tab').forEach(t => t.classList.remove('active'));
            event.target.classList.add('active');
            // Load respective content
        }

        function openCreateGroupModal() {
            document.getElementById('createGroupModal').classList.add('show');
        }

        function closeCreateGroupModal() {
            document.getElementById('createGroupModal').classList.remove('show');
        }

        function createGroup(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('course_id', <?= $course_id ?>);

            fetch('api/create_group_chat.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Group created successfully!');
                        closeCreateGroupModal();
                        // Reload groups
                    } else {
                        alert(data.message || 'Failed to create group');
                    }
                });
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }
    </script>
</body>

</html>