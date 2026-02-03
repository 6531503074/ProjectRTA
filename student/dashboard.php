<?php
// session_start();
include "../config/db.php";

if (!isset($_SESSION["user"]) || $_SESSION["user"]["role"] !== "student") {
    header("Location: ../auth/login.php");
    exit();
}

$student_id = $_SESSION["user"]["id"];
$student_name = $_SESSION["user"]["name"] ?? "Student";

// Get enrolled courses count
$courses_query = "SELECT COUNT(*) as total FROM course_students WHERE student_id = ?";
$courses_stmt = $conn->prepare($courses_query);
$courses_stmt->bind_param("i", $student_id);
$courses_stmt->execute();
$courses_count = $courses_stmt->get_result()->fetch_assoc()['total'];

// Get pending assignments count
$assignments_query = "SELECT COUNT(*) as total FROM assignments a 
                     INNER JOIN course_students cs ON a.course_id = cs.course_id 
                     LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = cs.student_id
                     WHERE cs.student_id = ? AND a.due_date >= CURDATE() AND s.id IS NULL";
$assignments_stmt = $conn->prepare($assignments_query);
$assignments_stmt->bind_param("i", $student_id);
$assignments_stmt->execute();
$assignments_count = $assignments_stmt->get_result()->fetch_assoc()['total'];

// Get recent announcements count
$announcements_query = "SELECT COUNT(*) as total FROM announcements an
                       INNER JOIN course_students cs ON an.course_id = cs.course_id 
                       WHERE cs.student_id = ? AND an.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
$announcements_stmt = $conn->prepare($announcements_query);
$announcements_stmt->bind_param("i", $student_id);
$announcements_stmt->execute();
$announcements_count = $announcements_stmt->get_result()->fetch_assoc()['total'];

// Get enrolled courses with details
$enrolled_courses_query = "SELECT c.id, c.title, c.description, u.name as teacher_name 
                          FROM courses c 
                          INNER JOIN course_students cs ON c.id = cs.course_id 
                          LEFT JOIN users u ON c.teacher_id = u.id 
                          WHERE cs.student_id = ? 
                          ORDER BY c.id DESC 
                          LIMIT 6";
$enrolled_stmt = $conn->prepare($enrolled_courses_query);
$enrolled_stmt->bind_param("i", $student_id);
$enrolled_stmt->execute();
$enrolled_courses = $enrolled_stmt->get_result();

// Get upcoming assignments
$upcoming_assignments_query = "SELECT a.id, a.title, a.due_date, c.title as course_title 
                              FROM assignments a 
                              INNER JOIN courses c ON a.course_id = c.id 
                              INNER JOIN course_students cs ON c.id = cs.course_id 
                              WHERE cs.student_id = ? AND a.due_date >= CURDATE() 
                              ORDER BY a.due_date ASC 
                              LIMIT 5";
$upcoming_stmt = $conn->prepare($upcoming_assignments_query);
$upcoming_stmt->bind_param("i", $student_id);
$upcoming_stmt->execute();
$upcoming_assignments = $upcoming_stmt->get_result();

// Get recent announcements
$recent_announcements_query = "SELECT an.id, an.content, an.created_at, c.title as course_title 
                              FROM announcements an 
                              INNER JOIN courses c ON an.course_id = c.id 
                              INNER JOIN course_students cs ON c.id = cs.course_id 
                              WHERE cs.student_id = ? 
                              ORDER BY an.created_at DESC 
                              LIMIT 5";
$recent_announcements_stmt = $conn->prepare($recent_announcements_query);
$recent_announcements_stmt->bind_param("i", $student_id);
$recent_announcements_stmt->execute();
$recent_announcements = $recent_announcements_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - CyberLearn</title>
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
            margin-left: 260px;
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

        .stat-icon.blue {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-icon.green {
            background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
        }

        .stat-icon.orange {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
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
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
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
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .card-header a:hover {
            text-decoration: underline;
        }

        /* Courses Grid */
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .course-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        .course-card p {
            font-size: 13px;
            opacity: 0.9;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .course-card .teacher {
            font-size: 12px;
            opacity: 0.8;
        }

        .course-card .view-btn {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 5px;
            color: white;
            text-decoration: none;
            font-size: 13px;
            transition: background 0.3s ease;
        }

        .course-card .view-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Assignments List */
        .assignment-item {
            padding: 15px;
            border-left: 4px solid #667eea;
            background: #f7fafc;
            border-radius: 5px;
            margin-bottom: 12px;
        }

        .assignment-item h4 {
            font-size: 14px;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .assignment-item .course-name {
            font-size: 12px;
            color: #667eea;
            margin-bottom: 5px;
        }

        .assignment-item .due-date {
            font-size: 12px;
            color: #e74c3c;
            font-weight: 500;
        }

        .assignment-item.urgent {
            border-left-color: #e74c3c;
            background: #fff5f5;
        }

        /* Announcements */
        .announcement-item {
            padding: 15px;
            background: #f7fafc;
            border-radius: 5px;
            margin-bottom: 12px;
        }

        .announcement-item .course-name {
            font-size: 12px;
            color: #667eea;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .announcement-item p {
            font-size: 13px;
            color: #4a5568;
            margin-bottom: 5px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .announcement-item .time {
            font-size: 11px;
            color: #a0aec0;
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

            .courses-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include "../components/student-sidebar.php"; ?>

    <div class="main-content">
        <div class="dashboard-header">
            <h1>ยินดีต้อนรับ <?php echo htmlspecialchars($student_name); ?>! 👋</h1>
            <p>นี่คือสิ่งที่กำลังเกิดขึ้นกับหลักสูตรของคุณในวันนี้</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">📚</div>
                <div class="stat-details">
                    <h3><?php echo $courses_count; ?></h3>
                    <p>หลักสูตรที่ลงทะเบียน</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">📝</div>
                <div class="stat-details">
                    <h3><?php echo $assignments_count; ?></h3>
                    <p>งานที่ยังไม่ได้ส่ง</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">📢</div>
                <div class="stat-details">
                    <h3><?php echo $announcements_count; ?></h3>
                    <p>ประกาศใหม่</p>
                </div>
            </div>
        </div>

        <!-- My Courses -->
        <div class="card">
            <div class="card-header">
                <h2>หลักสูตรของฉัน</h2>
            </div>

            <?php if ($enrolled_courses->num_rows > 0): ?>
                <div class="courses-grid">
                    <?php while ($course = $enrolled_courses->fetch_assoc()): ?>
                        <div class="course-card">
                            <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                            <p><?php echo htmlspecialchars($course['description']); ?></p>
                            <div class="teacher">👨‍🏫 <?php echo htmlspecialchars($course['teacher_name'] ?? 'No teacher assigned'); ?></div>
                            <a href="course-dashboard.php?id=<?php echo $course['id']; ?>" class="view-btn">ดูหลักสูตร</a>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📚</div>
                    <p>คุณยังไม่ได้ลงทะเบียนในหลักสูตรใดๆ</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Upcoming Assignments -->
            <div class="card">
                <div class="card-header">
                    <h2>งานที่กำหนดส่งกำลังจะมาถึง</h2>
                   
                </div>

                <?php if ($upcoming_assignments->num_rows > 0): ?>
                    <?php while ($assignment = $upcoming_assignments->fetch_assoc()):
                        $due_date = new DateTime($assignment['due_date']);
                        $today = new DateTime();
                        $diff = $today->diff($due_date)->days;
                        $is_urgent = $diff <= 3;
                    ?>
                        <div class="assignment-item <?php echo $is_urgent ? 'urgent' : ''; ?>">
                            <h4><?php echo htmlspecialchars($assignment['title']); ?></h4>
                            <div class="course-name"><?php echo htmlspecialchars($assignment['course_title']); ?></div>
                            <div class="due-date">วันที่: <?php 
                                $date = new DateTime($assignment['due_date']);
                                $months_th = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
                                $day = $date->format('j');
                                $month = $months_th[(int)$date->format('n')];
                                $year = (int)$date->format('Y') + 543;
                                echo "$day $month $year";
                            ?></div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">✅</div>
                        <p>ไม่มีงานที่กำหนดส่งกำลังจะมาถึง</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Announcements -->
            <div class="card">  
                <div class="card-header">
                    <h2>ประกาศล่าสุด</h2>
                  
                </div>

                <?php if ($recent_announcements->num_rows > 0): ?>
                    <?php while ($announcement = $recent_announcements->fetch_assoc()): ?>
                        <div class="announcement-item">
                            <div class="course-name"><?php echo htmlspecialchars($announcement['course_title']); ?></div>
                            <p><?php 
                                $content = htmlspecialchars($announcement['content']);
                                // Pattern to find URLs
                                $pattern = '/(https?:\/\/[^\s]+)/';
                                // Replace with <a> tag
                                echo preg_replace($pattern, '<a href="$1" target="_blank">$1</a>', $content); 
                            ?></p>
                            <div class="time"><?php 
                                $date = new DateTime($announcement['created_at']);
                                $months_th = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
                                $day = $date->format('j');
                                $month = $months_th[(int)$date->format('n')];
                                $year = (int)$date->format('Y') + 543;
                                echo "$day $month $year";
                            ?></div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📢</div>
                        <p>ไม่มีประกาศล่าสุด</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>