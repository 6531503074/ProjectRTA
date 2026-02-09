<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION["user"]) || $_SESSION["user"]["role"] !== "teacher") {
    header("Location: ../auth/login.php");
    exit();
}

$user = $_SESSION["user"];

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// Fetch all users
$query = "SELECT id, name, email, role, avatar, courseLevel FROM users ORDER BY name ASC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Permissions - CyberLearn</title>
    <link href="teacher.css" rel="stylesheet">
    <style>
        .permission-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            padding: 20px;
        }
        .filter-section {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            align-items: center;
        }
        .filter-select {
            padding: 8px 12px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
            font-size: 14px;
            color: #4a5568;
        }
        .user-table {
            width: 100%;
            border-collapse: collapse;
        }
        .user-table th, .user-table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        .user-table th {
            background: #f8fafc;
            color: #4a5568;
            font-weight: 600;
        }
        .user-avatar-small {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        .user-info-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .role-select {
            padding: 8px 12px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
            background: white;
            color: #2d3748;
            cursor: pointer;
        }
        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
        }
        .role-student { background: #e6fffa; color: #2c7a7b; }
        .role-teacher { background: #ebf8ff; color: #2b6cb0; }
        .btn-save {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-save:hover { background: #5a67d8; }
        
        .level-badge {
            background: #edf2f7;
            color: #4a5568;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <?php include "../components/teacher-sidebar.php"; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>การจัดการสิทธิ์ผู้ใช้</h1>
            <p>จัดการบทบาทและสิทธิ์การเข้าถึง</p>
        </div>

        <div class="permission-card">
            <div class="filter-section">
                <label>📌 กรองตามบทบาท:</label>
                <select id="roleFilter" class="filter-select" onchange="filterTable()">
                    <option value="all">ทั้งหมด</option>
                    <option value="student">นักเรียน (Students)</option>
                    <option value="teacher">ครู (Teachers)</option>
                </select>
            </div>

            <table class="user-table" id="userTable">
                <thead>
                    <tr>
                        <th>ผู้ใช้</th>
                        <th>อีเมล</th>
                        <th>ระดับ</th>
                        <th>บทบาทปัจจุบัน</th>
                        <th>เปลี่ยนบทบาท</th>
                        <th>การดำเนินการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr class="user-row" data-role="<?= strtolower($row['role']) ?>">
                                <td>
                                    <div class="user-info-cell">
                                        <div class="user-avatar-small">
                                            <?php if (!empty($row['avatar'])): ?>
                                                <img src="../<?= h($row['avatar']) ?>" alt="A" style="width:100%; height:100%; border-radius:50%;">
                                            <?php else: ?>
                                                👤
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600;"><?= h($row['name']) ?></div>
                                            <div style="font-size: 12px; color: #718096;">ID: <?= $row['id'] ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= h($row['email']) ?></td>
                                <td>
                                    <?php if ($row['role'] === 'student'): ?>
                                        <?php 
                                            $levels = ['1' => 'ขั้นเริ่มต้น', '2' => 'ขั้นกลาง', '3' => 'ขั้นสูง'];
                                            $level = $row['courseLevel'] ?? '-';
                                            echo '<span class="level-badge">' . ($levels[$level] ?? 'ไม่ระบุ') . '</span>';
                                        ?>
                                    <?php else: ?>
                                        <span class="level-badge" style="background:#e9d8fd; color:#553c9a">Admin</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="role-badge role-<?= strtolower($row['role']) ?>">
                                        <?= ucfirst(h($row['role'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <select id="role_<?= $row['id'] ?>" class="role-select">
                                        <option value="student" <?= $row['role'] === 'student' ? 'selected' : '' ?>>นักเรียน</option>
                                        <option value="teacher" <?= $row['role'] === 'teacher' ? 'selected' : '' ?>>ครู</option>
                                    </select>
                                </td>
                                <td>
                                    <button class="btn-save" onclick="updateRole(<?= $row['id'] ?>)">
                                        อัปเดต
                                    </button>
                                    <button class="btn-save btn-delete" onclick="deleteUser(<?= $row['id'] ?>)">
                                        ลบ
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center;">No users found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <style>
        .btn-delete {
            background: #e53e3e;
            margin-left: 5px;
        }
        .btn-delete:hover {
            background: #c53030;
        }
    </style>

    <script>
        function updateRole(userId) {
            const roleSelect = document.getElementById(`role_${userId}`);
            const newRole = roleSelect.value;
            const originalRole = roleSelect.querySelector('option[selected]') ? roleSelect.querySelector('option[selected]').value : '';

            if (newRole === originalRole) {
                // ไม่เปลี่ยน
                return;
            }

            if (!confirm(`คุณต้องการเปลี่ยนบทบาทของผู้ใช้เป็น ${newRole}?`)) {
                return;
            }

            const formData = new FormData();
            formData.append('user_id', userId);
            formData.append('new_role', newRole);

            fetch('../api/teacher_api.php?action=update_user_role', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('อัปเดตบทบาทเรียบร้อย!');
                    location.reload();
                } else {
                    alert(data.message || 'ไม่สามารถอัปเดตบทบาทได้');
                }
            })
            .catch(err => {
                console.error(err);
                alert('เกิดข้อผิดพลาด');
            });
        }

        function deleteUser(userId) {
            if (!confirm('คุณแน่ใจว่าต้องการลบผู้ใช้นี้? การกระทำนี้ไม่สามารถเรียกคืนได้!')) {
                return;
            }

            const formData = new FormData();
            formData.append('user_id', userId);

            fetch('../api/teacher_api.php?action=delete_user', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('ลบผู้ใช้เรียบร้อยแล้ว');
                    location.reload();
                } else {
                    alert(data.message || 'ไม่สามารถลบผู้ใช้ได้');
                }
            })
            .catch(err => {
                console.error(err);
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
            });
        }
        function filterTable() {
            const filterValue = document.getElementById('roleFilter').value;
            const rows = document.querySelectorAll('.user-row');

            rows.forEach(row => {
                const role = row.getAttribute('data-role');
                if (filterValue === 'all' || role === filterValue) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
