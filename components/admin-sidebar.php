<?php
$current = basename($_SERVER["PHP_SELF"]);
?>

<style>
    .sidebar {
        width: 230px;
        height: 100vh;
        background: #ffffff;
        border-right: 1px solid #ddd;
        position: fixed;
        left: 0;
        top: 0;
        padding: 20px;
    }

    .sidebar h2 {
        margin-bottom: 30px;
        font-size: 20px;
        color: #1a73e8;
    }

    .sidebar a {
        display: block;
        padding: 12px;
        margin-bottom: 8px;
        border-radius: 8px;
        color: #333;
        text-decoration: none;
        font-weight: 500;
    }

    .sidebar a.active,
    .sidebar a:hover {
        background: #e8f0fe;
        color: #1a73e8;
    }
</style>

<div class="sidebar">
    <h2>Admin</h2>

    <a href="/online-study/admin/dashboard.php">Dashboard</a>
    <a href="/online-study/admin/users.php">Users</a>
    <a href="/online-study/admin/courses.php">Courses</a>
    <a href="/online-study/admin/settings.php">Settings</a>

    <hr>
    <a href="../auth/logout.php">🚪 Logout</a>
</div>