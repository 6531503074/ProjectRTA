<?php if (!isset($_SESSION["user"]) || $_SESSION["user"]["role"] !== "student") {
    header("Location: ../auth/login.php");
    exit();
}
$current_page = basename($_SERVER["PHP_SELF"]);
$user = $_SESSION["user"]; ?> <style>
    :root {
        --primary-color: #667eea;
        --primary-hover: #5568d3;
        --sidebar-bg: #1a202c;
        --sidebar-hover: #2d3748;
        --text-primary: #ffffff;
        --text-secondary: #a0aec0;
        --active-bg: #667eea;
    }

    .sidebar {
        width: 260px;
        height: 100vh;
        background: var(--sidebar-bg);
        position: fixed;
        left: 0;
        top: 0;
        padding: 0;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        overflow-y: auto;
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

    .sidebar-header .user-name {
        color: var(--text-primary);
        font-size: 14px;
        font-weight: 600;
        margin: 0;
    }

    .sidebar-header .user-role {
        color: rgba(255, 255, 255, 0.7);
        font-size: 12px;
        margin: 2px 0 0 0;
    }

    .sidebar-nav {
        padding: 20px 0;
    }

    .nav-section {
        margin-bottom: 25px;
    }

    .nav-section-title {
        padding: 0 20px;
        color: var(--text-secondary);
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 8px;
    }

    .sidebar-nav a {
        display: flex;
        align-items: center;
        padding: 12px 20px;
        color: var(--text-secondary);
        text-decoration: none;
        font-weight: 500;
        font-size: 14px;
        transition: all 0.3s ease;
        position: relative;
    }

    .sidebar-nav a:hover {
        background: var(--sidebar-hover);
        color: var(--text-primary);
    }

    .sidebar-nav a.active {
        background: var(--active-bg);
        color: var(--text-primary);
    }

    .sidebar-nav a.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: #ffffff;
    }

    .sidebar-nav a .icon {
        margin-right: 12px;
        font-size: 18px;
        width: 20px;
        text-align: center;
    }

    .sidebar-nav a .badge {
        margin-left: auto;
        background: #e74c3c;
        color: white;
        font-size: 11px;
        padding: 2px 8px;
        border-radius: 10px;
        font-weight: 600;
    }

    .sidebar-footer {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 20px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        background: var(--sidebar-bg);
    }

    .sidebar-footer a {
        display: flex;
        align-items: center;
        padding: 12px;
        color: #e74c3c;
        text-decoration: none;
        font-weight: 500;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .sidebar-footer a:hover {
        background: rgba(231, 76, 60, 0.1);
    }

    .sidebar-footer a .icon {
        margin-right: 12px;
        font-size: 18px;
    }

    /* Mobile Toggle */
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
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h2>🎓 CyberLearn</h2>
        <div class="user-info">
            <div class="user-avatar"> <?php if (!empty($user['avatar'])): ?> <img src="../<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar"> <?php else: ?> 👤 <?php endif; ?> </div>
            <div class="user-details">
                <p class="user-name"><?php echo htmlspecialchars($user['name'] ?? 'Student'); ?></p>
                <p class="user-role">Student Account</p>
            </div>
        </div>
    </div>
    <div class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title">Courses History</div> <!-- show list of courses for each student enrolled -->
        </div>
    </div>
    <div class="sidebar-footer"> <a href="../auth/logout.php"> <span class="icon">🚪</span> Logout </a> </div>
</div> <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('active');
    } // Close sidebar when clicking outside on mobile document.addEventListener('click', function(event) { const sidebar = document.getElementById('sidebar'); const toggle = document.querySelector('.sidebar-toggle'); if (window.innerWidth <= 768) { if (!sidebar.contains(event.target) && !toggle.contains(event.target)) { sidebar.classList.remove('active'); } } }); // Load notification counts async function loadNotificationCounts() { try { const response = await fetch('api/get_notification_counts.php'); const data = await response.json(); if (data.success) { const assignmentsBadge = document.getElementById('assignmentsBadge'); const announcementsBadge = document.getElementById('announcementsBadge'); if (data.assignments > 0) { assignmentsBadge.textContent = data.assignments; assignmentsBadge.style.display = 'inline-block'; } else { assignmentsBadge.style.display = 'none'; } if (data.announcements > 0) { announcementsBadge.textContent = data.announcements; announcementsBadge.style.display = 'inline-block'; } else { announcementsBadge.style.display = 'none'; } } } catch (error) { console.error('Error loading notification counts:', error); } } // Load counts on page load loadNotificationCounts(); // Refresh every 2 minutes setInterval(loadNotificationCounts, 120000); 
</script>