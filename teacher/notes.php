<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION["user"]) || $_SESSION["user"]["role"] !== "teacher") {
    header("Location: ../auth/login.php");
    exit();
}

$user = $_SESSION["user"];
$teacher_id = $user["id"];

// Fetch announcements
$query = "SELECT a.id, a.content, a.created_at, c.title as course_title 
          FROM announcements a 
          INNER JOIN courses c ON a.course_id = c.id 
          ORDER BY a.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$announcements = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Announcements - CyberLearn</title>
    <link href="teacher.css" rel="stylesheet">
    <style>
        .container {
            padding: 20px;
        }

        .announcement-item {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            position: relative;
        }

        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .course-badge {
            background: #e2e8f0;
            color: #4a5568;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .announcement-time {
            font-size: 12px;
            color: #a0aec0;
        }

        .announcement-content {
            color: #2d3748;
            line-height: 1.6;
            margin-bottom: 15px;
            white-space: pre-wrap;
            overflow-wrap: break-word;
            word-break: break-word;
        }

        .actions {
            display: flex;
            gap: 10px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }

        .btn-edit {
            background: #4299e1;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-delete {
            background: #f56565;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 25px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px;
            color: #a0aec0;
        }
    </style>
</head>

<body>
    <?php include "../components/teacher-sidebar.php"; ?>

    <div class="main-content">
        <div class="header">
            <h1>📢 จัดการประกาศ</h1>
            <p>แก้ไขหรือลบประกาศที่คุณได้สร้างไว้</p>
        </div>

        <div class="container">
            <?php if ($announcements->num_rows > 0): ?>
                <?php while ($row = $announcements->fetch_assoc()): ?>
                    <div class="announcement-item" id="ann-<?= $row['id'] ?>">
                        <div class="announcement-header">
                            <span class="course-badge"><?= htmlspecialchars($row['course_title']) ?></span>
                            <span class="announcement-time">
                                <?php 
                                $date = new DateTime($row['created_at']);
                                echo $date->format('d/m/Y H:i');
                                ?>
                            </span>
                        </div>
                        <div class="announcement-content"><?= htmlspecialchars($row['content']) ?></div>
                        <div class="actions">
                            <button class="btn-edit btn-sm" onclick="openEditModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['content'])) ?>')">✏️ แก้ไข</button>
                            <button class="btn-delete btn-sm" onclick="deleteAnnouncement(<?= $row['id'] ?>)">🗑️ ลบ</button>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <h3>ไม่มีประกาศ</h3>
                    <p>คุณยังไม่ได้สร้างประกาศใดๆ</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3>✏️ แก้ไขประกาศ</h3>
            <form id="editForm" onsubmit="updateAnnouncement(event)">
                <input type="hidden" id="editId" name="id">
                <div class="form-group">
                    <textarea id="editContent" name="content" class="form-control" rows="5" required style="width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px; margin: 15px 0;"></textarea>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()" style="padding: 8px 16px; border: 1px solid #ccc; background: white; border-radius: 4px; cursor: pointer;">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary" style="padding: 8px 16px; background: #4299e1; color: white; border: none; border-radius: 4px; cursor: pointer;">บันทึก</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(id, content) {
            document.getElementById('editId').value = id;
            document.getElementById('editContent').value = content;
            document.getElementById('editModal').classList.add('show');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }

        function updateAnnouncement(e) {
            e.preventDefault();
            const formData = new FormData(document.getElementById('editForm'));
            
            fetch('../api/teacher_api.php?action=update_announcement', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('แก้ไขประกาศสำเร็จ');
                    location.reload();
                } else {
                    alert('เกิดข้อผิดพลาด: ' + data.message);
                }
            })
            .catch(err => {
                console.error(err);
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
            });
        }

        function deleteAnnouncement(id) {
            if (!confirm('คุณแน่ใจหรือไม่ที่จะลบประกาศนี้?')) return;

            const formData = new FormData();
            formData.append('id', id);

            fetch('../api/teacher_api.php?action=delete_announcement', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('ลบประกาศสำเร็จ');
                    document.getElementById('ann-' + id).remove();
                    if (document.querySelectorAll('.announcement-item').length === 0) {
                        location.reload(); // To show empty state
                    }
                } else {
                    alert('เกิดข้อผิดพลาด: ' + data.message);
                }
            })
            .catch(err => {
                console.error(err);
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }
    </script>
</body>
</html>
