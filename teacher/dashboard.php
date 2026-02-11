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
$courses_query = "SELECT COUNT(*) as total FROM courses";
$courses_stmt = $conn->prepare($courses_query);
$courses_stmt->execute();
$total_courses = $courses_stmt->get_result()->fetch_assoc()['total'];

// Total students
$students_query = "SELECT COUNT(DISTINCT cs.student_id) as total 
                  FROM course_students cs 
                  INNER JOIN courses c ON cs.course_id = c.id";
$students_stmt = $conn->prepare($students_query);
$students_stmt->execute();
$total_students = $students_stmt->get_result()->fetch_assoc()['total'];

// Pending submissions
$pending_query = "SELECT COUNT(*) as total 
                 FROM assignment_submissions asub
                 INNER JOIN assignments a ON asub.assignment_id = a.id
                 INNER JOIN courses c ON a.course_id = c.id
                 WHERE asub.grade IS NULL";
$pending_stmt = $conn->prepare($pending_query);
$pending_stmt->execute();
$pending_submissions = $pending_stmt->get_result()->fetch_assoc()['total'];

// Get recent courses
$recent_courses_query = "SELECT c.*, 
                        (SELECT COUNT(*) FROM course_students WHERE course_id = c.id) as student_count,
                        (SELECT COUNT(*) FROM assignments WHERE course_id = c.id) as assignment_count
                        FROM courses c 
                        ORDER BY c.id DESC 
                        LIMIT 4";
$recent_courses_stmt = $conn->prepare($recent_courses_query);
$recent_courses_stmt->execute();
$recent_courses = $recent_courses_stmt->get_result();

// Get recent submissions
$recent_submissions_query = "SELECT asub.*, a.title as assignment_title, 
                             c.title as course_title, u.name as student_name
                             FROM assignment_submissions asub
                             INNER JOIN assignments a ON asub.assignment_id = a.id
                             INNER JOIN courses c ON a.course_id = c.id
                             INNER JOIN users u ON asub.student_id = u.id
                             ORDER BY asub.submitted_at DESC
                              LIMIT 3";
$recent_submissions_stmt = $conn->prepare($recent_submissions_query);
$recent_submissions_stmt->execute();
$recent_submissions = $recent_submissions_stmt->get_result();

// Get upcoming assignments
$upcoming_assignments_query = "SELECT a.*, c.title as course_title,
                               (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) as submission_count,
                               (SELECT COUNT(*) FROM course_students WHERE course_id = c.id) as student_count
                               FROM assignments a
                               INNER JOIN courses c ON a.course_id = c.id
                               WHERE a.due_date >= CURDATE()
                               ORDER BY a.due_date ASC
                               LIMIT 3";
$upcoming_assignments_stmt = $conn->prepare($upcoming_assignments_query);
$upcoming_assignments_stmt->execute();
$upcoming_assignments = $upcoming_assignments_stmt->get_result();
$upcoming_assignments_stmt->execute();
$upcoming_assignments = $upcoming_assignments_stmt->get_result();

// Get all courses for announcement dropdown
$all_courses_query = "SELECT id, title FROM courses ORDER BY title ASC";
$all_courses_stmt = $conn->prepare($all_courses_query);
$all_courses_stmt->execute();
$all_courses = $all_courses_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - CyberLearn</title>
    <link href="teacher.css" rel="stylesheet">
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
            <div class="quick-action-btn" onclick="openAnnouncementModal()">
                <div class="icon">📢</div>
                <div class="label">ประกาศ</div>
            </div>
            <div class="quick-action-btn" onclick="window.location.href='students.php'">
                <div class="icon">👥</div>
                <div class="label">จัดการนักเรียน</div>
            </div>
            <div class="quick-action-btn" onclick="window.location.href='grades.php'">
                <div class="icon">💯</div>
                <div class="label">จัดการคะแนนงาน</div>
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
                            <div class="course-card-body">
                                <h3 class="course-title" style="color: black;"><?= htmlspecialchars($course['title']) ?></h3>
                                <div class="course-desc">
                                    <?= htmlspecialchars(mb_strimwidth($course['description'] ?? '', 0, 100, '...', 'UTF-8')) ?>
                                </div>
                                <div class="course-meta">
                                    <span class="meta-pill">👥 <?= $course['student_count'] ?> นักเรียน</span>
                                    <span class="meta-pill">📝 <?= $course['assignment_count'] ?> งาน</span>
                                </div>
                            </div>
                            <div class="course-actions">
                                <a href="course_detail.php?id=<?= $course['id'] ?>" class="action-btn btn-view">จัดการหลักสูตร</a>
                            </div>
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
                                        ให้คะแนน
                                    </button>
                                </div>
                            <?php else: ?>
                                <div style="margin-top: 8px; color: #2ecc71; font-size: 12px; font-weight: 600;">
                                    ✓ ได้รับคะแนนแล้ว: <?= htmlspecialchars($submission['grade']) ?>
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
                                            $datetime = null;
                                            if (!empty($assignment['due_date'])) {
                                                try {
                                                    $datetime = new DateTime($assignment['due_date']);
                                                } catch (Exception $e) {
                                                    $datetime = null;
                                                }
                                            }
                                            
                                            if ($datetime) {
                                                $months_th = ['', 'มค.', 'กพ.', 'มีค.', 'เมย.', 'พค.', 'มิย.', 'กค.', 'สค.', 'กันย.', 'ตค.', 'พย.', 'ธค.'];
                                                $day = $datetime->format('d');
                                                $month = $months_th[(int)$datetime->format('m')];
                                                $year = (int)$datetime->format('Y') + 543;
                                                $time = $datetime->format('H:i'); // Will be 00:00 for DATE columns
                                                echo "$day $month $year"; // Removed time as it's likely irrelevant for DATE type
                                            } else {
                                                echo "ไม่ระบุวันที่";
                                            }
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



    <!-- Announcement Modal -->
    <div id="announcementModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>📢 สร้างประกาศ</h2>
                <span class="modal-close" onclick="closeAnnouncementModal()">×</span>
            </div>
            <form id="announcementForm" onsubmit="createAnnouncement(event)">
                <div class="form-group">
                    <label class="form-label">เลือกวิชา <span style="color:red">*</span></label>
                    <select name="course_id" class="form-control" required>
                        <option value="">-- เลือกวิชา --</option>
                        <?php 
                        $all_courses->data_seek(0); // Reset pointer
                        while($c = $all_courses->fetch_assoc()): 
                        ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">ข้อความประกาศ <span style="color:red">*</span></label>
                    <textarea name="content" class="form-control" rows="5" required placeholder="สิ่งที่ต้องการแจ้งให้นักเรียนทราบ (ใส่ลิงก์ได้)"></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">โพสต์ประกาศ</button>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openAnnouncementModal() {
            document.getElementById('announcementModal').classList.add('show');
        }

        function closeAnnouncementModal() {
            document.getElementById('announcementModal').classList.remove('show');
        }
        
        // Close when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }

        function createAnnouncement(e) {
            e.preventDefault();
            const form = document.getElementById('announcementForm');
            const formData = new FormData(form);

            fetch('../api/teacher_api.php?action=create_announcement', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    alert('โพสต์ประกาศสำเร็จ');
                    closeAnnouncementModal();
                    form.reset();
                    // Optional: reload page to see visual confirmation? 
                    // Or just let it be. Dashboard doesn't show own announcements usually, 
                    // but student dashboard does.
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => {
                console.error(err);
                alert('Connection Error');
            });
        }

        function gradeSubmission(submissionId) {
            window.location.href = `grades.php?id=${submissionId}`;
        }
    </script>
</body>

</html>