<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION["user"]) || $_SESSION["user"]["role"] !== "teacher") {
    header("Location: ../auth/login.php");
    exit();
}

$teacher_id = (int)$_SESSION["user"]["id"];
$assignment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($assignment_id === 0) {
    header("Location: assignments.php");
    exit();
}

function h($str)
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// 1. Get Assignment Details
$sql = "SELECT a.*, c.title as course_title, c.id as course_id
        FROM assignments a
        JOIN courses c ON a.course_id = c.id
        WHERE a.id = ? AND c.teacher_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $assignment_id, $teacher_id);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();

if (!$assignment) {
    header("Location: assignments.php");
    exit();
}

// 2. Get Submissions List (All students in course)
// We want to see ALL students, and their submission status
$sql_students = "
    SELECT 
        u.id as student_id, u.name, u.avatar, u.email,
        s.id as submission_id, s.submitted_at, s.file_path, s.grade, s.feedback
    FROM course_students cs
    JOIN users u ON cs.student_id = u.id
    LEFT JOIN assignment_submissions s ON s.student_id = u.id AND s.assignment_id = ?
    WHERE cs.course_id = ?
    ORDER BY u.name ASC
";
$stmt_stud = $conn->prepare($sql_students);
$stmt_stud->bind_param("ii", $assignment_id, $assignment['course_id']);
$stmt_stud->execute();
$students_result = $stmt_stud->get_result();

// Stats calculation
$total_students = $students_result->num_rows;
$submitted_count = 0;
$graded_count = 0;
$students_data = [];

while ($row = $students_result->fetch_assoc()) {
    if ($row['submission_id']) {
        $submitted_count++;
        if ($row['grade'] !== null) {
            $graded_count++;
        }
    }
    $students_data[] = $row;
}

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($assignment['title']) ?> - CyberLearn</title>
    <link href="teacher.css" rel="stylesheet">
</head>

<body>
    <?php include "../components/teacher-sidebar.php"; ?>

    <div class="main-content">
        <!-- Header -->
        <div class="page-header">
            <div>
                <a href="assignments.php" class="btn btn-ghost" style="margin-bottom: 10px; padding: 6px 12px;">
                    ← กลับไปหน้ารวมงาน
                </a>
                <h1><?= h($assignment['title']) ?></h1>
                <p>
                    วิชา: <a href="course_detail.php?id=<?= $assignment['course_id'] ?>" style="color:var(--primary); text-decoration:none; font-weight:600;">
                        <?= h($assignment['course_title']) ?>
                    </a>
                </p>
            </div>
            <div class="actions-row">
                <button onclick="openEditModal()" class="btn btn-secondary">
                    ✏️ แก้ไขงาน
                </button>
                <button onclick="deleteAssignment(<?= $assignment_id ?>)" class="btn btn-danger">
                    🗑️ ลบงาน
                </button>
            </div>
        </div>

        <!-- Info & Stats -->
        <div class="content-grid" style="grid-template-columns: 2fr 1fr;">
            <!-- Descriptions -->
            <div class="card">
                <div class="card-header">
                    <h2>รายละเอียด</h2>
                </div>
                <div style="color:var(--dark); white-space: pre-wrap; line-height:1.6;"><?= h($assignment['description'] ?: 'ไม่มีรายละเอียด') ?></div>
                
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border);">
                    <strong>กำหนดส่ง:</strong> 
                    <?php 
                        $due = strtotime($assignment['due_date']);
                        $is_overdue = time() > $due;
                        echo date('d/m/Y', $due);
                        if ($is_overdue) echo ' <span style="color:var(--danger); font-size:12px;">(หมดเขตแล้ว)</span>';
                    ?>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-grid" style="grid-template-columns: 1fr; margin-bottom: 0; gap: 15px;">
                <div class="stat-card">
                    <div class="stat-icon blue">👥</div>
                    <div class="stat-details">
                        <h3><?= $total_students ?></h3>
                        <p>นักเรียนทั้งหมด</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange">📥</div>
                    <div class="stat-details">
                        <h3><?= $submitted_count ?></h3>
                        <p>ส่งงานแล้ว</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">✅</div>
                    <div class="stat-details">
                        <h3><?= $graded_count ?></h3>
                        <p>ตรวจแล้ว</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submissions List -->
        <div class="card">
            <div class="card-header">
                <h2>การส่งงานของนักเรียน</h2>
            </div>
            
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>นักเรียน</th>
                            <th>สถานะ</th>
                            <th>วันที่ส่ง</th>
                            <th>ไฟล์แนบ</th>
                            <th>เกรด</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students_data as $std): ?>
                            <tr>
                                <td>
                                    <div class="student-cell">
                                        <div class="avatar">
                                            <?php if (!empty($std['avatar'])): ?>
                                                <img src="../<?= h($std['avatar']) ?>" alt="avatar">
                                            <?php else: ?>
                                                <?= mb_substr($std['name'], 0, 1, 'UTF-8') ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div style="font-weight:600;"><?= h($std['name']) ?></div>
                                            <div style="font-size:12px; color:var(--gray);"><?= h($std['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($std['submission_id']): ?>
                                        <span class="tag open">ส่งแล้ว</span>
                                    <?php else: ?>
                                        <span class="tag closed">ยังไม่ส่ง</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($std['submitted_at']): ?>
                                        <?= date('d/m/Y H:i', strtotime($std['submitted_at'])) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($std['file_path']): ?>
                                        <a href="../uploads/assignments/<?= h($std['file_path']) ?>" target="_blank" class="btn-ghost" style="padding: 4px 8px; font-size: 12px;">
                                            📂 ดูไฟล์
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($std['grade'] !== null): ?>
                                        <span style="font-weight:bold; color:var(--success);"><?= $std['grade'] ?>/100</span>
                                    <?php else: ?>
                                        <span style="color:var(--gray);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($std['submission_id']): ?>
                                        <button class="btn btn-sm btn-primary" onclick="window.location.href='grade_submission.php?id=<?= $std['submission_id'] ?>'">
                                            📝 ตรวจงาน
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled style="opacity:0.5; cursor:not-allowed;">
                                            รอส่ง
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($students_data)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding: 30px; color:var(--gray);">
                                    ยังไม่มีนักเรียนในวิชานี้
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Edit Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>แก้ไขงาน</h3>
                <span class="modal-close" onclick="closeEditModal()">×</span>
            </div>
            <form id="editForm" onsubmit="updateAssignment(event)">
                <input type="hidden" name="id" value="<?= $assignment_id ?>">
                
                <div class="form-group">
                    <label class="form-label">ชื่องาน <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="title" class="form-control" required value="<?= h($assignment['title']) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">กำหนดส่ง <span style="color:var(--danger)">*</span></label>
                    <input type="datetime-local" name="due_date" class="form-control" required value="<?= date('Y-m-d\TH:i', strtotime($assignment['due_date'])) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">รายละเอียด</label>
                    <textarea name="description" class="form-control" rows="5"><?= h($assignment['description']) ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;">บันทึกการแก้ไข</button>
            </form>
        </div>
    </div>

    <script>
        function openEditModal() {
            document.getElementById('editModal').classList.add('show');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }

        function updateAssignment(e) {
            e.preventDefault();
            const formData = new FormData(e.target);

            // ใช้ API เดียวกับการสร้าง/แก้ไข
            fetch('../api/teacher_api.php?action=update_assignment', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('บันทึกสำเร็จ');
                    location.reload();
                } else {
                    alert(data.message || 'บันทึกไม่สำเร็จ');
                }
            })
            .catch(err => {
                console.error(err);
                alert('เกิดข้อผิดพลาด');
            });
        }

        function deleteAssignment(id) {
            if (!confirm('ยืนยันลบงานนี้? หากลบแล้วข้อมูลการส่งงานของนักเรียนจะหายไปทั้งหมด')) return;

            fetch('../api/teacher_api.php?action=delete_assignment', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + encodeURIComponent(id)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('ลบงานสำเร็จ');
                    window.location.href = 'assignments.php?course_id=<?= $assignment['course_id'] ?>';
                } else {
                    alert(data.message || 'ลบไม่สำเร็จ');
                }
            })
            .catch(err => {
                console.error(err);
                alert('เกิดข้อผิดพลาด');
            });
        }

        // Close modal on outside click
        window.addEventListener('click', (event) => {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        });
    </script>
</body>
</html>
