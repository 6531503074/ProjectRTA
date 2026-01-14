<?php
if (!isset($_SESSION["user"]) || $_SESSION["user"]["role"] !== "teacher") {
    header("Location: ../auth/login.php");
    exit();
}

$user = $_SESSION["user"];
$teacher_id = $user["id"];

// Get teacher's courses
$courses_query = "SELECT c.id, c.title, 
                  (SELECT COUNT(*) FROM course_students WHERE course_id = c.id) as student_count,
                  (SELECT COUNT(*) FROM announcements WHERE course_id = c.id 
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as recent_announcements
                  FROM courses c 
                  WHERE c.teacher_id = ? 
                  ORDER BY c.title ASC";
$courses_stmt = $conn->prepare($courses_query);
$courses_stmt->bind_param("i", $teacher_id);
$courses_stmt->execute();
$teacher_courses = $courses_stmt->get_result();
?>

<style>
    :root {
        --primary-color: #667eea;
        --primary-dark: #5568d3;
        --sidebar-bg: #1a202c;
        --sidebar-hover: #2d3748;
        --text-primary: #ffffff;
        --text-secondary: #a0aec0;
        --active-bg: #667eea;
        --border-color: rgba(255, 255, 255, 0.1);
        --teacher-accent: #f39c12;
    }

    .sidebar {
        width: 280px;
        height: 100vh;
        background: var(--sidebar-bg);
        position: fixed;
        left: 0;
        top: 0;
        display: flex;
        flex-direction: column;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        z-index: 1000;
    }

    .sidebar-header {
        padding: 25px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
    }

    .sidebar-header h2 {
        margin: 0;
        font-size: 22px;
        color: var(--text-primary);
        font-weight: 600;
    }

    .sidebar-header .user-info {
        margin-top: 15px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .sidebar-header .user-avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        overflow: hidden;
    }

    .sidebar-header .user-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .sidebar-header .user-details {
        flex: 1;
    }

    .sidebar-header .user-main-detail {
        color: var(--text-primary);
        font-size: 14px;
        font-weight: 600;
        margin: 0;
    }

    .sidebar-header .user-sub-detail {
        color: rgba(255, 255, 255, 0.7);
        font-size: 12px;
        margin: 2px 0 0 0;
    }

    .sidebar-nav {
        flex: 1;
        overflow-y: auto;
        padding: 15px 0;
    }

    .sidebar-nav::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar-nav::-webkit-scrollbar-track {
        background: transparent;
    }

    .sidebar-nav::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.2);
        border-radius: 3px;
    }

    .nav-section {
        margin-bottom: 25px;
    }

    .nav-section-title {
        padding: 12px 20px 8px 20px;
        color: var(--text-secondary);
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .nav-link {
        display: flex;
        align-items: center;
        padding: 12px 20px;
        color: var(--text-secondary);
        text-decoration: none;
        transition: all 0.3s ease;
        position: relative;
        border-left: 3px solid transparent;
    }

    .nav-link:hover {
        background: var(--sidebar-hover);
        color: var(--text-primary);
        border-left-color: var(--teacher-accent);
    }

    .nav-link.active {
        background: rgba(243, 156, 18, 0.1);
        color: var(--text-primary);
        border-left-color: var(--teacher-accent);
    }

    .nav-link .icon {
        margin-right: 12px;
        font-size: 18px;
        width: 20px;
        text-align: center;
    }

    .nav-link .badge {
        margin-left: auto;
        background: #e74c3c;
        color: white;
        font-size: 11px;
        padding: 2px 8px;
        border-radius: 10px;
        font-weight: 600;
    }

    .course-item {
        display: flex;
        align-items: center;
        padding: 10px 20px;
        color: var(--text-secondary);
        text-decoration: none;
        transition: all 0.3s ease;
        border-left: 3px solid transparent;
        cursor: pointer;
    }

    .course-item:hover {
        background: var(--sidebar-hover);
        color: var(--text-primary);
        border-left-color: var(--teacher-accent);
    }

    .course-item.active {
        background: rgba(243, 156, 18, 0.1);
        color: var(--text-primary);
        border-left-color: var(--teacher-accent);
    }

    .course-info {
        flex: 1;
    }

    .course-title {
        font-size: 14px;
        font-weight: 500;
        margin-bottom: 3px;
    }

    .course-meta {
        font-size: 11px;
        opacity: 0.8;
    }

    .sidebar-footer {
        padding: 20px;
        border-top: 1px solid var(--border-color);
        flex-shrink: 0;
    }

    .sidebar-footer a {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 15px;
        color: #e74c3c;
        text-decoration: none;
        font-weight: 500;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .sidebar-footer a:hover {
        background: rgba(231, 76, 60, 0.1);
    }

    .sidebar-toggle {
        display: none;
        position: fixed;
        top: 20px;
        left: 20px;
        z-index: 1001;
        background: var(--teacher-accent);
        color: white;
        border: none;
        padding: 10px 15px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    .create-course-btn {
        margin: 0 20px 15px 20px;
        padding: 10px 15px;
        width: calc(100% - 40px);
        background: var(--teacher-accent);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .create-course-btn:hover {
        background: #e67e22;
        transform: translateY(-2px);
    }

    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }

        .sidebar.active {
            transform: translateX(0);
        }

        .sidebar-toggle {
            display: block;
        }
    }
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h2>LMS | Cyber</h2>
        <div class="user-info">
            <div class="user-avatar"> <?php if (!empty($user['avatar'])): ?> <img src="../<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar"> <?php else: ?> 👤 <?php endif; ?> </div>
            <div class="user-details">
                <p class="user-main-detail"><?php echo htmlspecialchars($user['rank'] ?? 'N/A'); ?> <?php echo htmlspecialchars($user['name'] ?? 'N/A'); ?></p>
                <p class="user-sub-detail">ตำแหน่ง: <?php echo htmlspecialchars($user['position'] ?? 'N/A'); ?> สังกัด: <?php echo htmlspecialchars($user['affiliation'] ?? 'N/A'); ?></p>
            </div>
        </div>
    </div>


    <div class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title">เมนู</div>
            <a href="dashboard.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                แดช์บอร์ด
            </a>
            <a href="profile.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : '' ?>">
                โปรไฟล์
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">จัดการหลักสูตร</div>
            <a href="courses.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'courses.php' ? 'active' : '' ?>">
                หลักสูตรทั้งหมด
            </a>
            <a href="students.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'students.php' ? 'active' : '' ?>">
                นักเรียน
            </a>
            <a href="assignments.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'assignments.php' ? 'active' : '' ?>">
                งาน
            </a>
            <a href="grades.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'grades.php' ? 'active' : '' ?>">
                เกรด
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">หลักสูตรของฉัน</div>
            <button class="create-course-btn" onclick="openCreateCourseModal()">
                ➕ สร้างหลักสูตรใหม่
            </button>

            <?php if ($teacher_courses->num_rows > 0): ?>
                <?php while ($course = $teacher_courses->fetch_assoc()): ?>
                    <a href="course_detail.php?id=<?= $course['id'] ?>"
                        class="course-item <?= (isset($_GET['id']) && $_GET['id'] == $course['id']) ? 'active' : '' ?>">
                        <div class="course-info">
                            <div class="course-title"><?= htmlspecialchars($course['title']) ?></div>
                            <div class="course-meta">
                                👥 <?= $course['student_count'] ?> students
                                <?php if ($course['recent_announcements'] > 0): ?>
                                    | 📢 <?= $course['recent_announcements'] ?> new
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="padding: 20px; text-align: center; color: var(--text-secondary); font-size: 13px;">
                    คุณยังไม่มีหลักสูตร
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="sidebar-footer">
        <a href="../auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ</a>
    </div>
</div>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('active');
    }

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const toggle = document.querySelector('.sidebar-toggle');

        if (window.innerWidth <= 768) {
            if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        }
    });
</script>