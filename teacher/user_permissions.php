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
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                <div class="filter-section" style="margin-bottom: 0;">
                    <label>📌 กรองตามบทบาท:</label>
                    <select id="roleFilter" class="filter-select" onchange="filterTable()">
                        <option value="all">ทั้งหมด</option>
                        <option value="student">นักเรียน (Students)</option>
                        <option value="teacher">ครู (Teachers)</option>
                    </select>
                </div>
                <div style="display:flex; align-items:center;">
                    <label style="font-size:14px; margin-right:8px; font-weight:600;">แสดง</label>
                    <select id="pageSize" class="filter-select" style="width:80px; padding:6px 10px;" onchange="changePageSize()">
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                    </select>
                    <label style="font-size:14px; margin-left:8px; font-weight:600;">แถว</label>
                </div>
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
                                        <select id="level_<?= $row['id'] ?>" class="role-select" onchange="updateLevel(<?= $row['id'] ?>)">
                                            <option value="1" <?= ($row['courseLevel'] == '1') ? 'selected' : '' ?>>ชั้นต้น</option>
                                            <option value="2" <?= ($row['courseLevel'] == '2') ? 'selected' : '' ?>>ชั้นสูง</option>
                                            <option value="3" <?= ($row['courseLevel'] == '3') ? 'selected' : '' ?>>ชั้นสูงพิเศษ</option>
                                        </select>
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
            
            <!-- Pagination Controls -->
            <div id="paginationControls" style="display:flex; justify-content:space-between; align-items:center; padding-top:15px; border-top: 1px solid #edf2f7; margin-top: 15px;">
                <div id="paginationSummary" style="font-size:14px; color:#718096; font-weight:500;"></div>
                <div id="paginationButtons" style="display:flex; gap:5px; align-items:center;"></div>
            </div>
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
        function updateLevel(userId) {
            const levelSelect = document.getElementById(`level_${userId}`);
            const newLevel = levelSelect.value;
            
            if (!confirm(`คุณต้องการเปลี่ยนระดับของผู้ใช้นี้เป็นระดับ ${newLevel}?`)) {
                location.reload(); // Reset selection if cancelled
                return;
            }

            const formData = new FormData();
            formData.append('user_id', userId);
            formData.append('new_level', newLevel);

            fetch('../api/teacher_api.php?action=update_user_level', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('อัปเดตระดับเรียบร้อย!');
                    // No need to reload, value is selected
                } else {
                    alert(data.message || 'ไม่สามารถอัปเดตระดับได้');
                    location.reload();
                }
            })
            .catch(err => {
                console.error(err);
                alert('เกิดข้อผิดพลาด');
            });
        }

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
        // --- Client-Side Pagination & Filtering Logic ---
        let currentPage = 1;
        let pageSize = 5;
        let allUserRows = [];

        document.addEventListener('DOMContentLoaded', () => {
            allUserRows = Array.from(document.querySelectorAll('.user-row'));
            allUserRows.forEach(row => row.dataset.filtered = 'true');
            if (allUserRows.length > 0) {
                renderTable();
            } else {
                document.getElementById('pageSize').disabled = true;
                document.getElementById('paginationControls').style.display = 'none';
            }
        });

        function changePageSize() {
            pageSize = parseInt(document.getElementById('pageSize').value);
            currentPage = 1;
            renderTable();
        }

        function goToPage(page) {
            currentPage = page;
            renderTable();
        }

        function filterTable() {
            const filterValue = document.getElementById('roleFilter').value;

            allUserRows.forEach(row => {
                const role = row.getAttribute('data-role');
                if (filterValue === 'all' || role === filterValue) {
                    row.dataset.filtered = 'true';
                } else {
                    row.dataset.filtered = 'false';
                }
            });
            
            currentPage = 1;
            renderTable();
        }

        function renderTable() {
            const filteredRows = allUserRows.filter(row => row.dataset.filtered === 'true');
            const total = filteredRows.length;
            const totalPages = Math.ceil(total / pageSize) || 1;
            
            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            const startIdx = (currentPage - 1) * pageSize;
            const endIdx = Math.min(startIdx + pageSize, total);

            // Hide all rows initially
            allUserRows.forEach(row => row.style.display = 'none');

            // Show only the visible slice for the current page
            filteredRows.forEach((row, index) => {
                if (index >= startIdx && index < endIdx) {
                    row.style.display = '';
                }
            });

            // Update summary text
            if (total === 0) {
                document.getElementById('paginationSummary').innerText = `แสดง 0-0 จากทั้งหมด 0 คน`;
            } else {
                document.getElementById('paginationSummary').innerText = `แสดง ${startIdx + 1}-${endIdx} จากทั้งหมด ${total} คน`;
            }

            // Generate pagination buttons
            let btnHtml = '';
            
            btnHtml += `<button style="padding:4px 8px; border-radius:4px; border:1px solid #e2e8f0; background:#f8fafc; cursor:pointer; color:#4a5568;" onclick="goToPage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>ก่อนหน้า</button>`;
            
            for (let i = 1; i <= totalPages; i++) {
                if (totalPages <= 7 || i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                    let activeStyle = i === currentPage ? 'background:#667eea; color:white; border-color:#667eea;' : 'background:#f8fafc; color:#4a5568; border-color:#e2e8f0;';
                    btnHtml += `<button style="padding:4px 10px; border-radius:4px; border:1px solid; cursor:pointer; margin:0 2px; ${activeStyle}" onclick="goToPage(${i})">${i}</button>`;
                } else if (i === currentPage - 2 || i === currentPage + 2) {
                    btnHtml += `<span style="padding: 5px; color: #a0aec0;">...</span>`;
                }
            }

            btnHtml += `<button style="padding:4px 8px; border-radius:4px; border:1px solid #e2e8f0; background:#f8fafc; cursor:pointer; color:#4a5568;" onclick="goToPage(${currentPage + 1})" ${currentPage === totalPages || total === 0 ? 'disabled' : ''}>ถัดไป</button>`;
            
            // Clean up back-to-back ellipses
            btnHtml = btnHtml.replace(/(<span[^>]*>\.\.\.<\/span>){2,}/g, '<span style="padding: 5px; color: #a0aec0;">...</span>');

            document.getElementById('paginationButtons').innerHTML = btnHtml;
        }
    </script>
</body>
</html>
