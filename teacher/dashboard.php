<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION["user"]) || $_SESSION["user"]["role"] !== "teacher") {
    header("Location: ../auth/login.php");
    exit();
}

$user = $_SESSION["user"];
$teacher_id = $user["id"];
$teacher_name = $user["name"];

// Get statistics
// Total courses
$courses_query = "SELECT COUNT(*) as total FROM courses WHERE teacher_id = ?";
$courses_stmt = $conn->prepare($courses_query);
$courses_stmt->bind_param("i", $teacher_id);
$courses_stmt->execute();
$total_courses = $courses_stmt->get_result()->fetch_assoc()['total'];

// Total students
$students_query = "SELECT COUNT(DISTINCT cs.student_id) as total 
                  FROM course_students cs 
                  INNER JOIN courses c ON cs.course_id = c.id 
                  WHERE c.teacher_id = ?";
$students_stmt = $conn->prepare($students_query);
$students_stmt->bind_param("i", $teacher_id);
$students_stmt->execute();
$total_students = $students_stmt->get_result()->fetch_assoc()['total'];

// Pending submissions
$pending_query = "SELECT COUNT(*) as total 
                 FROM assignment_submissions asub
                 INNER JOIN assignments a ON asub.assignment_id = a.id
                 INNER JOIN courses c ON a.course_id = c.id
                 WHERE c.teacher_id = ? AND asub.grade IS NULL";
$pending_stmt = $conn->prepare($pending_query);
$pending_stmt->bind_param("i", $teacher_id);
$pending_stmt->execute();
$pending_submissions = $pending_stmt->get_result()->fetch_assoc()['total'];

// Get recent courses
$recent_courses_query = "SELECT c.*, 
                        (SELECT COUNT(*) FROM course_students WHERE course_id = c.id) as student_count,
                        (SELECT COUNT(*) FROM assignments WHERE course_id = c.id) as assignment_count
                        FROM courses c 
                        WHERE c.teacher_id = ? 
                        ORDER BY c.id DESC 
                        LIMIT 4";
$recent_courses_stmt = $conn->prepare($recent_courses_query);
$recent_courses_stmt->bind_param("i", $teacher_id);
$recent_courses_stmt->execute();
$recent_courses = $recent_courses_stmt->get_result();

// Get recent submissions
$recent_submissions_query = "SELECT asub.*, a.title as assignment_title, 
                             c.title as course_title, u.name as student_name
                             FROM assignment_submissions asub
                             INNER JOIN assignments a ON asub.assignment_id = a.id
                             INNER JOIN courses c ON a.course_id = c.id
                             INNER JOIN users u ON asub.student_id = u.id
                             WHERE c.teacher_id = ?
                             ORDER BY asub.submitted_at DESC
                             LIMIT 10";
$recent_submissions_stmt = $conn->prepare($recent_submissions_query);
$recent_submissions_stmt->bind_param("i", $teacher_id);
$recent_submissions_stmt->execute();
$recent_submissions = $recent_submissions_stmt->get_result();

// Get upcoming assignments
$upcoming_assignments_query = "SELECT a.*, c.title as course_title,
                               (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) as submission_count,
                               (SELECT COUNT(*) FROM course_students WHERE course_id = c.id) as student_count
                               FROM assignments a
                               INNER JOIN courses c ON a.course_id = c.id
                               WHERE c.teacher_id = ? AND a.due_date >= CURDATE()
                               ORDER BY a.due_date ASC
                               LIMIT 5";
$upcoming_assignments_stmt = $conn->prepare($upcoming_assignments_query);
$upcoming_assignments_stmt->bind_param("i", $teacher_id);
$upcoming_assignments_stmt->execute();
$upcoming_assignments = $upcoming_assignments_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - CyberLearn</title>
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
            min-height: 100vh;
        }

        .dashboard-header {
            margin-bottom: 30px;
        }

        .dashboard-header h1 {
            font-size: 28px;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .dashboard-header p {
            color: #718096;
            font-size: 14px;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }

        .stat-icon.orange {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
        }

        .stat-icon.blue {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        }

        .stat-icon.green {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
        }

        .stat-details h3 {
            font-size: 32px;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .stat-details p {
            color: #718096;
            font-size: 14px;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }

        .card-header h2 {
            font-size: 18px;
            color: #2d3748;
        }

        .card-header a {
            color: #f39c12;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .card-header a:hover {
            text-decoration: underline;
        }

        /* Course Cards */
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 15px;
        }

        .course-card {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            border-radius: 10px;
            padding: 20px;
            color: white;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .course-card:hover {
            transform: translateY(-5px);
        }

        .course-card h3 {
            font-size: 16px;
            margin-bottom: 10px;
        }

        .course-card .course-meta {
            font-size: 13px;
            opacity: 0.9;
            margin-bottom: 10px;
        }

        .course-card .view-btn {
            display: inline-block;
            margin-top: 10px;
            padding: 6px 12px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 5px;
            color: white;
            text-decoration: none;
            font-size: 12px;
            transition: background 0.3s ease;
        }

        .course-card .view-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Submission List */
        .submission-item {
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
            margin-bottom: 12px;
            border-left: 4px solid #f39c12;
        }

        .submission-item.graded {
            border-left-color: #2ecc71;
            opacity: 0.7;
        }

        .submission-item h4 {
            font-size: 14px;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .submission-item .student-name {
            font-size: 13px;
            color: #667eea;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .submission-item .course-name {
            font-size: 12px;
            color: #718096;
            margin-bottom: 5px;
        }

        .submission-item .time {
            font-size: 11px;
            color: #a0aec0;
        }

        .submission-item .actions {
            margin-top: 10px;
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #f39c12;
            color: white;
        }

        .btn-primary:hover {
            background: #e67e22;
        }

        .btn-success {
            background: #2ecc71;
            color: white;
        }

        /* Assignment List */
        .assignment-item {
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
            margin-bottom: 12px;
        }

        .assignment-item h4 {
            font-size: 14px;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .assignment-item .meta {
            font-size: 12px;
            color: #718096;
            margin-bottom: 8px;
        }

        .progress-bar {
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #f39c12, #e67e22);
            transition: width 0.3s ease;
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

        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }

        .quick-action-btn {
            flex: 1;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .quick-action-btn:hover {
            border-color: #f39c12;
            transform: translateY(-3px);
        }

        .quick-action-btn .icon {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .quick-action-btn .label {
            font-size: 14px;
            font-weight: 600;
            color: #2d3748;
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

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3748;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #f39c12;
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background: #f39c12;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-submit:hover {
            background: #e67e22;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <?php include "../components/teacher-sidebar.php"; ?>

    <div class="main-content">
        <div class="dashboard-header">
            <h1>ยินดีต้อนรับ <?= htmlspecialchars($teacher_name) ?>! 👋</h1>
            <p>นี่คือสิ่งที่กำลังเกิดขึ้นกับหลักสูตรของคุณในวันนี้</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon orange">📚</div>
                <div class="stat-details">
                    <h3><?= $total_courses ?></h3>
                    <p>หลักสูตรทั้งหมด</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon blue">👥</div>
                <div class="stat-details">
                    <h3><?= $total_students ?></h3>
                    <p>นักเรียนทั้งหมด</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">📝</div>
                <div class="stat-details">
                    <h3><?= $pending_submissions ?></h3>
                    <p>การส่งงานที่รอดำเนินการ</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="quick-action-btn" onclick="openCreateCourseModal()">
                <div class="icon">➕</div>
                <div class="label">สร้างหลักสูตร</div>
            </div>
            <div class="quick-action-btn" onclick="window.location.href='assignments.php'">
                <div class="icon">📝</div>
                <div class="label">จัดการการส่งงาน</div>
            </div>
            <div class="quick-action-btn" onclick="window.location.href='students.php'">
                <div class="icon">👥</div>
                <div class="label">จัดการนักเรียน</div>
            </div>
            <div class="quick-action-btn" onclick="window.location.href='grades.php'">
                <div class="icon">📊</div>
                <div class="label">จัดการเกรด</div>
            </div>
        </div>

        <!-- My Courses -->
        <div class="card">
            <div class="card-header">
                <h2>หลักสูตรของฉัน</h2>
                <a href="courses.php">ดูทั้งหมด →</a>
            </div>
            <?php if ($recent_courses->num_rows > 0): ?>
                <div class="courses-grid">
                    <?php while ($course = $recent_courses->fetch_assoc()): ?>
                        <div class="course-card" onclick="window.location.href='course_detail.php?id=<?= $course['id'] ?>'">
                            <h3><?= htmlspecialchars($course['title']) ?></h3>
                            <div class="course-meta">
                                👥 <?= $course['student_count'] ?> นักเรียน<br>
                                📝 <?= $course['assignment_count'] ?> งาน
                            </div>
                            <a href="course_detail.php?id=<?= $course['id'] ?>" class="view-btn">จัดการหลักสูตร</a>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📚</div>
                    <p>ยังไม่มีหลักสูตร สร้างหลักสูตรแรกของคุณ!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Recent Submissions -->
            <div class="card">
                <div class="card-header">
                    <h2>การส่งงานล่าสุด</h2>
                    <a href="assignments.php">ดูทั้งหมด →</a>
                </div>

                <?php if ($recent_submissions->num_rows > 0): ?>
                    <?php while ($submission = $recent_submissions->fetch_assoc()): ?>
                        <div class="submission-item <?= $submission['grade'] ? 'graded' : '' ?>">
                            <h4><?= htmlspecialchars($submission['assignment_title']) ?></h4>
                            <div class="student-name">👤 <?= htmlspecialchars($submission['student_name']) ?></div>
                            <div class="course-name">📚 <?= htmlspecialchars($submission['course_title']) ?></div>
                            <div class="time">📅
                                <?php
                                $date = DateTime::createFromFormat('Y-m-d H:i:s', $submission['submitted_at']);
                                $months_th = ['', 'มค.', 'กพ.', 'มีค.', 'เมย.', 'พค.', 'มิย.', 'กค.', 'สค.', 'กันย.', 'ตค.', 'พย.', 'ธค.'];
                                $day = $date->format('d');
                                $month = $months_th[(int)$date->format('m')];
                                $year = (int)$date->format('Y') + 543;
                                $time = $date->format('H:i');
                                echo "$day $month $year เวลา $time น.";
                                ?>
                            </div>

                            <?php if (!$submission['grade']): ?>
                                <div class="actions">
                                    <button class="btn btn-primary" onclick="gradeSubmission(<?= $submission['id'] ?>)">
                                        ให้เกรด
                                    </button>
                                </div>
                            <?php else: ?>
                                <div style="margin-top: 8px; color: #2ecc71; font-size: 12px; font-weight: 600;">
                                    ✓ ได้รับเกรดแล้ว: <?= htmlspecialchars($submission['grade']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📝</div>
                        <p>ยังไม่มีการส่งงาน</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Upcoming Assignments -->
            <div class="card">
                <div class="card-header">
                    <h2>กำหนดส่งงานที่ใกล้ถึง</h2>
                    <a href="assignments.php">ดูทั้งหมด →</a>
                </div>

                <?php if ($upcoming_assignments->num_rows > 0): ?>
                    <?php while ($assignment = $upcoming_assignments->fetch_assoc()):
                        $progress = $assignment['student_count'] > 0
                            ? ($assignment['submission_count'] / $assignment['student_count']) * 100
                            : 0;
                    ?>
                        <div class="assignment-item">
                            <h4><?= htmlspecialchars($assignment['title']) ?></h4>
                            <div class="meta">
                                📚 <?= htmlspecialchars($assignment['course_title']) ?><br>
                                📅 วันที่: <?php
                                            $date = DateTime::createFromFormat('Y-m-d', $assignment['due_date']);
                                            $months_th = ['', 'มค.', 'กพ.', 'มีค.', 'เมย.', 'พค.', 'มิย.', 'กค.', 'สค.', 'กันย.', 'ตค.', 'พย.', 'ธค.'];
                                            $day = $date->format('d');
                                            $month = $months_th[(int)$date->format('m')];
                                            $year = (int)$date->format('Y') + 543;
                                            echo "$day $month $year";
                                            ?><br>
                                📊 <?= $assignment['submission_count'] ?>/<?= $assignment['student_count'] ?> ส่งแล้ว
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= $progress ?>%"></div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📅</div>
                        <p>ไม่มีงานที่กำหนดส่ง</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Create Course Modal -->
    <div class="modal" id="createCourseModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>สร้างหลักสูตรใหม่</h3>
                <span class="modal-close" onclick="closeCreateCourseModal()">×</span>
            </div>
            <form id="createCourseForm" onsubmit="createCourse(event)">
                <div class="form-group">
                    <label>ชื่อหลักสูตร *</label>
                    <input type="text" name="title" required placeholder="ใส่ชื่อหลักสูตร">
                </div>
                <div class="form-group">
                    <label>รายละเอียด</label>
                    <textarea name="description" rows="4" placeholder="ใส่รายละเอียดหลักสูตร"></textarea>
                </div>
                <button type="submit" class="btn-submit">สร้างหลักสูตร</button>
            </form>
        </div>
    </div>

    <script>
        function openCreateCourseModal() {
            document.getElementById('createCourseModal').classList.add('show');
        }

        function closeCreateCourseModal() {
            document.getElementById('createCourseModal').classList.remove('show');
            document.getElementById('createCourseForm').reset();
        }

        function createCourse(e) {
            e.preventDefault();
            const formData = new FormData(e.target);

            fetch('../api/teacher_api.php?action=create_course', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Course created successfully!');
                        closeCreateCourseModal();
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to create course');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred');
                });
        }

        function gradeSubmission(submissionId) {
            window.location.href = `grade_submission.php?id=${submissionId}`;
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