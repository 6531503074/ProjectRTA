<?php
// session_start();
include "../config/db.php";

if (!isset($_SESSION["user"]) || $_SESSION["user"]["role"] !== "teacher") {
    header("Location: ../auth/login.php");
    exit();
}

$teacher_id = $_SESSION["user"]["id"];
$assignment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($assignment_id <= 0) {
    header("Location: dashboard.php");
    exit();
}

// Get assignment details and verify ownership
$assignment_query = "SELECT a.*, c.title as course_title 
                     FROM assignments a 
                     INNER JOIN courses c ON a.course_id = c.id 
                     WHERE a.id = ? AND c.teacher_id = ?";
$assignment_stmt = $conn->prepare($assignment_query);
$assignment_stmt->bind_param("ii", $assignment_id, $teacher_id);
$assignment_stmt->execute();
$assignment = $assignment_stmt->get_result()->fetch_assoc();

if (!$assignment) {
    header("Location: dashboard.php");
    exit();
}

// Get all submissions
$submissions_query = "SELECT s.*, u.name as student_name, u.email as student_email
                      FROM assignment_submissions s
                      INNER JOIN users u ON s.student_id = u.id
                      WHERE s.assignment_id = ?
                      ORDER BY s.submitted_at DESC";
$submissions_stmt = $conn->prepare($submissions_query);
$submissions_stmt->bind_param("i", $assignment_id);
$submissions_stmt->execute();
$submissions = $submissions_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Submissions - <?= htmlspecialchars($assignment['title']) ?></title>
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
            padding: 30px;
        }

        .page-header {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .page-header h1 {
            color: #2d3748;
            margin-bottom: 10px;
        }

        .page-header .meta {
            color: #718096;
            font-size: 14px;
        }

        .submission-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .submission-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }

        .student-info h3 {
            color: #2d3748;
            margin-bottom: 5px;
        }

        .student-info .email {
            color: #718096;
            font-size: 14px;
        }

        .submission-time {
            color: #718096;
            font-size: 13px;
        }

        .submission-content {
            margin-bottom: 20px;
        }

        .submission-content h4 {
            color: #2d3748;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .submission-text {
            background: #f7fafc;
            padding: 15px;
            border-radius: 8px;
            color: #4a5568;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .file-attachment {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #ebf8ff;
            color: #2c5282;
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
        }

        .file-attachment:hover {
            background: #bee3f8;
        }

        .grading-section {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .grading-section h4 {
            color: #2d3748;
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            color: #2d3748;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
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
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .graded-badge {
            background: #c6f6d5;
            color: #22543d;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
        }

        .current-grade {
            background: #e6fffa;
            border-left: 4px solid #38b2ac;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .current-grade strong {
            color: #234e52;
            font-size: 16px;
        }

        .current-feedback {
            background: #fef5e7;
            border-left: 4px solid #f39c12;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .empty-state {
            text-align: center;
            padding: 60px;
            color: #a0aec0;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include "components/sidebar.php"; ?>

    <div class="main-content">
        <div class="page-header">
            <h1><?= htmlspecialchars($assignment['title']) ?></h1>
            <div class="meta">
                Course: <?= htmlspecialchars($assignment['course_title']) ?> | 
                Due: <?= date('M d, Y', strtotime($assignment['due_date'])) ?> |
                Total Submissions: <?= $submissions->num_rows ?>
            </div>
        </div>

        <?php if ($submissions->num_rows > 0): ?>
            <?php while ($submission = $submissions->fetch_assoc()): ?>
                <div class="submission-card">
                    <div class="submission-header">
                        <div class="student-info">
                            <h3><?= htmlspecialchars($submission['student_name']) ?></h3>
                            <div class="email"><?= htmlspecialchars($submission['student_email']) ?></div>
                        </div>
                        <div>
                            <div class="submission-time">
                                Submitted: <?= date('M d, Y - g:i A', strtotime($submission['submitted_at'])) ?>
                            </div>
                            <?php if ($submission['grade']): ?>
                                <div class="graded-badge" style="margin-top: 10px;">✓ Graded</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="submission-content">
                        <?php if ($submission['submission_text']): ?>
                            <h4>Submission:</h4>
                            <div class="submission-text">
                                <?= nl2br(htmlspecialchars($submission['submission_text'])) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($submission['file_path']): ?>
                            <h4>Attachment:</h4>
                            <a href="<?= htmlspecialchars($submission['file_path']) ?>" class="file-attachment" target="_blank">
                                📎 Download Attachment
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="grading-section">
                        <?php if ($submission['grade']): ?>
                            <div class="current-grade">
                                <strong>Grade: <?= htmlspecialchars($submission['grade']) ?></strong>
                            </div>
                            <?php if ($submission['feedback']): ?>
                                <div class="current-feedback">
                                    <strong>Feedback:</strong>
                                    <p style="margin-top: 8px;"><?= nl2br(htmlspecialchars($submission['feedback'])) ?></p>
                                </div>
                            <?php endif; ?>
                            <h4>Update Grade</h4>
                        <?php else: ?>
                            <h4>Grade Submission</h4>
                        <?php endif; ?>

                        <form onsubmit="submitGrade(event, <?= $submission['id'] ?>)">
                            <div class="form-group">
                                <label>Grade</label>
                                <input type="text" name="grade" placeholder="e.g., A+, 95/100, Pass" 
                                       value="<?= htmlspecialchars($submission['grade'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Feedback</label>
                                <textarea name="feedback" rows="4" 
                                          placeholder="Provide feedback to the student..."><?= htmlspecialchars($submission['feedback'] ?? '') ?></textarea>
                            </div>
                            <button type="submit" class="btn-primary">
                                <?= $submission['grade'] ? 'Update Grade' : 'Submit Grade' ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <div style="font-size: 48px; margin-bottom: 10px;">📝</div>
                <p>No submissions yet</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function submitGrade(e, submissionId) {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('submission_id', submissionId);

            fetch('api/grade_assignment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Grade submitted successfully!');
                    location.reload();
                } else {
                    alert(data.message || 'Failed to submit grade');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
            });
        }
    </script>
</body>
</html>