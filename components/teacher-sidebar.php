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

    .course-item:visited {
        color: var(--text-secondary);
    }

    .course-item:hover, 
    .course-item:focus, 
    .course-item:active {
        background: var(--sidebar-hover);
        color: var(--text-primary);
        border-left-color: var(--teacher-accent);
        outline: none;
    }

    .course-item.active,
    .course-item.active:visited,
    .course-item.active:hover,
    .course-item.active:focus {
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
        color: var(--text-primary);
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
        <h2>RTA | Cyber</h2>
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
                แดชบอร์ด
            </a>

            <a href="profile.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : '' ?>">
                โปรไฟล์
            </a>


            <a href="user_permissions.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'user_permissions.php' ? 'active' : '' ?>">
                กำหนดสิทธิ์ผู้ใช้
            </a>

         <a href="notes.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'notes.php' ? 'active' : '' ?>">
                ประกาศ
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
                คะแนนงาน
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">หลักสูตรของฉัน</div>
            <button class="create-course-btn" onclick="openCreateCourseModal()">
                ➕ สร้างหลักสูตรใหม่
            </button>

            <?php if ($teacher_courses->num_rows > 0): ?>
                <?php while ($sidebar_course = $teacher_courses->fetch_assoc()): ?>
                    <a href="course_detail.php?id=<?= $sidebar_course['id'] ?>"
                        class="course-item <?= (isset($_GET['id']) && $_GET['id'] == $sidebar_course['id']) ? 'active' : '' ?>">
                        <div class="course-info">
                            <div class="course-title"><?= htmlspecialchars($sidebar_course['title']) ?></div>
                            <div class="course-meta">
                                👥 <?= $sidebar_course['student_count'] ?> students
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

<!-- Create Course Modal (Global) -->
<div class="modal" id="createCourseModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>สร้างหลักสูตรใหม่</h3>
            <span class="modal-close" onclick="closeCreateCourseModal()">×</span>
        </div>
        <form id="createCourseForm" onsubmit="createCourseGlobal(event)">
            <div class="form-group">
                <label>ชื่อหลักสูตร *</label>
                <input type="text" name="title" required placeholder="ใส่ชื่อหลักสูตร" class="form-control">
            </div>
            <div class="form-group">
                <label>ระดับชั้น <span style="color:red">*</span></label>
                <select name="course_level" class="form-control" required>
                    <option value="1">🌱 ชั้นต้น</option>
                    <option value="2">🔧 ชั้นสูง</option>
                    <option value="3">🚀 ชั้นนสูงพิเศษ </option>
                </select>
            </div>
            <div class="form-group">
                <label>รายละเอียด</label>
                <textarea name="description" rows="4" placeholder="ใส่รายละเอียดหลักสูตร" class="form-control"></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:16px;">สร้างหลักสูตร</button>
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

    function createCourseGlobal(e) {
        e.preventDefault();
        const formData = new FormData(e.target);

        // We assume we are in a subfolder like /teacher/, so API is at ../api/
        fetch('../api/teacher_api.php?action=create_course', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('สร้างหลักสูตรสำเร็จ!');
                    closeCreateCourseModal();
                    location.reload();
                } else {
                    alert(data.message || 'สร้างหลักสูตรไม่สำเร็จ');
                }
            })
            .catch(err => {
                console.error(err);
                alert('เกิดข้อผิดพลาด');
            });
    }
    
    // Close global modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.classList.remove('show');
        }
    });
</script>

<!-- Chat Widget -->
<style>
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
        margin: 0;
    }

    .chat-window-close {
        cursor: pointer;
        font-size: 20px;
        opacity: 0.8;
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
    
    .empty-state {
        text-align: center;
        color: #a0aec0;
    }
    
    .empty-state-icon {
        font-size: 48px;
        margin-bottom: 10px;
        opacity: 0.5;
    }
</style>

<div class="floating-chat-btn" onclick="toggleFloatingChat()">
    💬
</div>

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
        <!-- Groups will be loaded here -->
    </div>
</div>

<!-- Group Creation Modal -->
<div id="createGroupModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>สร้างกลุ่มแชทใหม่</h3>
            <span class="modal-close" onclick="closeCreateGroupModal()">×</span>
        </div>
        <form id="createGroupForm" onsubmit="createGroup(event)">
            <?php if (isset($course_id) && $course_id > 0): ?>
                <input type="hidden" name="course_id" value="<?= $course_id ?>">
            <?php else: ?>
                <div class="form-group">
                    <label class="form-label">เลือกวิชา <span style="color:red">*</span></label>
                    <select name="course_id" class="form-control" required id="group_course_select">
                        <option value="">-- เลือกวิชา --</option>
                        <?php 
                        // Modified to allow any teacher to create a group for any course
                        $c_stmt = $conn->prepare("SELECT id, title FROM courses ORDER BY title ASC");
                        $c_stmt->execute();
                        $c_res = $c_stmt->get_result();
                        while ($c = $c_res->fetch_assoc()): 
                        ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label">ชื่อกลุ่ม <span style="color:red">*</span></label>
                <input type="text" name="group_name" class="form-control" required placeholder="เช่น กลุ่มโปรเจกต์ 1">
            </div>
            

            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:10px;">สร้างกลุ่ม</button>
        </form>
    </div>
</div>

<script src="../api/chat.js?v=<?= time() ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize with courseId from PHP context if available
        if (typeof ChatManager !== 'undefined') {
            const contextCourseId = <?= isset($course_id) ? (int)$course_id : 0 ?>;
            chatManager = new ChatManager(contextCourseId, <?= (int)$_SESSION['user']['id'] ?>);
        }
    });

    // Make toggleFloatingChat global if needed, though chat.js defines it too.
    // However, chat.js implementation relies on `chatManager` being global.
    // Re-implementing simplified toggle if chat.js one has issues or to ensure it works.
    // Actually chat.js defines it. We should use that.
    
    // But we need to ensure chat.js is loaded.
</script>