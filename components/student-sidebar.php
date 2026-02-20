<?php
if (!isset($_SESSION["user"]) || $_SESSION["user"]["role"] !== "student") {
    header("Location: ../auth/login.php");
    exit();
}

$user = $_SESSION["user"];
$student_id = $user["id"];

// Get student courses with pin status and unread count
$stmt = $conn->prepare("
    SELECT 
        c.id,
        c.title,
        c.description,
        COALESCE(
            (SELECT 1 FROM pinned_courses pc 
             WHERE pc.course_id = c.id AND pc.student_id = ? LIMIT 1), 
            0
        ) AS pinned,
        COALESCE(
            (SELECT COUNT(*) FROM announcements a 
             WHERE a.course_id = c.id
             AND NOT EXISTS (
                 SELECT 1 FROM announcement_reads ar 
                 WHERE ar.announcement_id = a.id AND ar.student_id = ?
             )), 
            0
        ) AS unread_count
    FROM courses c
    INNER JOIN course_students cs ON c.id = cs.course_id
    WHERE cs.student_id = ?
    ORDER BY pinned DESC, c.title ASC
");

if ($stmt) {
    $stmt->bind_param("iii", $student_id, $student_id, $student_id);
    $stmt->execute();
    $courses = $stmt->get_result();
} else {
    die("Query error: " . $conn->error);
}
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
        --unread-badge: #e74c3c;
        --border-color: rgba(255, 255, 255, 0.1);
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
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

    .course-search {
        margin: 15px 20px;
        padding: 12px 15px;
        width: calc(100% - 40px);
        border-radius: 8px;
        border: 2px solid var(--border-color);
        background: rgba(255, 255, 255, 0.05);
        color: var(--text-primary);
        font-size: 14px;
        outline: none;
        transition: all 0.3s ease;
    }

    .course-search::placeholder {
        color: var(--text-secondary);
    }

    .course-search:focus {
        background: rgba(255, 255, 255, 0.1);
        border-color: var(--primary-color);
    }

    .sidebar-nav {
        flex: 1;
        overflow-y: auto;
        padding-bottom: 10px;
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

    .course-link {
        display: block;
        padding: 12px 20px;
        color: var(--text-secondary);
        text-decoration: none;
        transition: all 0.3s ease;
        position: relative;
        border-left: 3px solid transparent;
    }

    .course-link:hover {
        background: var(--sidebar-hover);
        color: var(--text-primary);
        border-left-color: var(--primary-color);
    }

    .course-link.active {
        background: rgba(102, 126, 234, 0.1);
        color: var(--text-primary);
        border-left-color: var(--primary-color);
    }

    .nav-link {
        display: block;
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
        border-left-color: var(--primary-color);
    }
    
    .nav-link.active {
        background: rgba(102, 126, 234, 0.1);
        color: var(--text-primary);
        border-left-color: var(--primary-color);
    }

    .course-item {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
        font-weight: 500;
    }

    .course-title {
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .unread-badge {
        background: var(--unread-badge);
        color: white;
        font-size: 10px;
        font-weight: 600;
        padding: 3px 7px;
        border-radius: 10px;
        min-width: 18px;
        text-align: center;
        flex-shrink: 0;
    }

    .pin-btn {
        cursor: pointer;
        opacity: 0.5;
        font-size: 16px;
        transition: all 0.3s ease;
        flex-shrink: 0;
    }

    .pin-btn:hover {
        opacity: 1;
        transform: scale(1.2);
    }

    .course-link[data-pinned="1"] .pin-btn {
        opacity: 1;
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

    .loading {
        display: inline-block;
        width: 14px;
        height: 14px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-top-color: white;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    .empty-courses {
        text-align: center;
        padding: 40px 20px;
        color: var(--text-secondary);
    }

    .no-results {
        display: none;
        text-align: center;
        padding: 30px 20px;
        color: var(--text-secondary);
    }

    .no-results.show {
        display: block;
    }

    .sidebar-toggle {
        display: none;
        position: fixed;
        top: 20px;
        left: 20px;
        z-index: 1001;
        background: var(--primary-color);
        color: white;
        border: none;
        padding: 10px 15px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 20px;
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

    <input
        type="text"
        class="course-search"
        id="courseSearch"
        placeholder="🔍 ค้นหาหลักสูตร..."
        autocomplete="off">

    <div class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title">เมนู</div>
            <a href="../student/dashboard.php" class="nav-link">แดชบอร์ด</a>
            <a href="../student/profile.php" class="nav-link">โปรไฟล์</a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">หลักสูตร</div>

            <?php if ($courses && $courses->num_rows > 0): ?>
                <?php while ($c = $courses->fetch_assoc()): ?>
                    <a href="course-dashboard.php?id=<?= $c["id"] ?>"
                        class="course-link"
                        data-title="<?= strtolower($c["title"]) ?>"
                        data-pinned="<?= $c["pinned"] ?>">
                        <div class="course-item">
                            <span class="course-title"><?= htmlspecialchars($c["title"]) ?></span>

                            <?php if ($c["unread_count"] > 0): ?>
                                <span class="unread-badge"><?= $c["unread_count"] ?></span>
                            <?php endif; ?>

                            <span class="pin-btn"
                                onclick="togglePin(event, <?= $c['id'] ?>)"
                                title="<?= $c['pinned'] ? 'Unpin' : 'Pin' ?>">
                                <?= $c["pinned"] ? "📌" : "📍" ?>
                            </span>
                        </div>
                    </a>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-courses">
                    <p>📚<br>No courses enrolled</p>
                </div>
            <?php endif; ?>

            <div class="no-results" id="noResults">
                <p>No courses found</p>
            </div>
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

    document.getElementById("courseSearch").addEventListener("input", function() {
        const value = this.value.toLowerCase().trim();
        const courseLinks = document.querySelectorAll(".course-link");
        const noResults = document.getElementById("noResults");
        let visibleCount = 0;

        courseLinks.forEach(link => {
            if (link.dataset.title.includes(value)) {
                link.style.display = "block";
                visibleCount++;
            } else {
                link.style.display = "none";
            }
        });

        noResults.classList.toggle("show", visibleCount === 0 && value !== "");
    });

    function togglePin(e, courseId) {
        e.preventDefault();
        e.stopPropagation();

        const pinBtn = e.target;
        const originalEmoji = pinBtn.textContent;
        pinBtn.innerHTML = '<span class="loading"></span>';
        pinBtn.style.pointerEvents = 'none';

        fetch("../api/toggle_pin.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    course_id: parseInt(courseId)
                })
            })
            .then(response => {
                const contentType = response.headers.get("content-type");
                if (!contentType || !contentType.includes("application/json")) {
                    return response.text().then(text => {
                        console.error('API returned HTML:', text.substring(0, 200));
                        throw new Error('Server returned HTML instead of JSON');
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('Response:', data);
                if (data.success) {
                    setTimeout(() => location.reload(), 300);
                } else {
                    pinBtn.textContent = originalEmoji;
                    alert(data.message || 'Failed to toggle pin');
                    console.error('Error:', data);
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                pinBtn.textContent = originalEmoji;
                alert('Connection error: ' + error.message);
            })
            .finally(() => {
                pinBtn.style.pointerEvents = 'auto';
            });
    }

    // Highlight active page
    const urlParams = new URLSearchParams(window.location.search);
    const courseId = urlParams.get('id');
    if (courseId) {
        const activeLink = document.querySelector(`a[href*="id=${courseId}"]`);
        if (activeLink) activeLink.classList.add('active');
    }
</script>