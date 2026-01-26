<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION["user"]) || $_SESSION["user"]["role"] !== "teacher") {
    header("Location: ../auth/login.php");
    exit();
}

$teacher_id = (int) $_SESSION["user"]["id"];
$course_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($course_id === 0) {
    header("Location: courses.php");
    exit();
}

function h($str)
{
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
}

// 1. Get Course Details
$course_stmt = $conn->prepare("SELECT * FROM courses WHERE id = ? AND teacher_id = ?");
$course_stmt->bind_param("ii", $course_id, $teacher_id);
$course_stmt->execute();
$course = $course_stmt->get_result()->fetch_assoc();

if (!$course) {
    // Course not found or doesn't belong to this teacher
    header("Location: courses.php");
    exit();
}

// 2. Get Stats for this course
$stats_sql = "
SELECT
    (SELECT COUNT(*) FROM course_students WHERE course_id = ?) as total_students,
    (SELECT COUNT(*) FROM assignments WHERE course_id = ?) as total_assignments,
    (SELECT COUNT(*) 
     FROM assignment_submissions s 
     INNER JOIN assignments a ON s.assignment_id = a.id 
     WHERE a.course_id = ? AND s.grade IS NULL) as pending_grades
";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("iii", $course_id, $course_id, $course_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// 3. Get Recent Assignments (Limit 5)
$assign_sql = "SELECT * FROM assignments WHERE course_id = ? ORDER BY id DESC LIMIT 5";
$assign_stmt = $conn->prepare($assign_sql);
$assign_stmt->bind_param("i", $course_id);
$assign_stmt->execute();
$assignments = $assign_stmt->get_result();

// 4. Get Recent Students (Limit 5)
$stud_sql = "
    SELECT u.id, u.name, u.email, u.avatar 
    FROM course_students cs 
    INNER JOIN users u ON cs.student_id = u.id 
    WHERE cs.course_id = ? 
    ORDER BY u.id DESC LIMIT 5
";
$stud_stmt = $conn->prepare($stud_sql);
$stud_stmt->bind_param("i", $course_id);
$stud_stmt->execute();
$students = $stud_stmt->get_result();

// 5. Get Course Materials
$mat_sql = "SELECT * FROM course_materials WHERE course_id = ? ORDER BY uploaded_at DESC";
$mat_stmt = $conn->prepare($mat_sql);
$mat_stmt->bind_param("i", $course_id);
$mat_stmt->execute();
$materials = $mat_stmt->get_result();

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($course['title']) ?> - CyberLearn</title>
    <link href="teacher.css" rel="stylesheet">
</head>

<body>
    <?php include "../components/teacher-sidebar.php"; ?>

    <div class="main-content">
        <!-- Navigation / Header -->
        <div class="page-header">
            <div>
                <a href="courses.php" class="btn btn-ghost" style="margin-bottom: 10px; padding: 6px 12px;">
                    ← กลับไปหน้าหลักสูตร
                </a>
                <h1><?= h($course['title']) ?></h1>
                <p><?= h($course['description'] ?: 'ไม่มีรายละเอียด') ?></p>
            </div>
            <div class="actions-row">
                <button
                    onclick="openEditCourseModal(<?= $course_id ?>, '<?= h($course['title']) ?>', `<?= h($course['description']) ?>`)"
                    class="btn btn-secondary">
                    ✏️ แก้ไขหลักสูตร
                </button>
                <button onclick="deleteCourse(<?= $course_id ?>)" class="btn btn-danger">
                    🗑️ ลบหลักสูตร
                </button>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">👥</div>
                <div class="stat-details">
                    <h3><?= $stats['total_students'] ?></h3>
                    <p>นักเรียนทั้งหมด</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange">📝</div>
                <div class="stat-details">
                    <h3><?= $stats['total_assignments'] ?></h3>
                    <p>งานทั้งหมด</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">⚡</div>
                <div class="stat-details">
                    <h3><?= $stats['pending_grades'] ?></h3>
                    <p>รอการตรวจ</p>
                </div>
            </div>
        </div>

        <!-- Content Grid (Assignments & Students) -->
        <div class="content-grid">

            <!-- Assignments Column -->
            <div class="card">
                <div class="card-header">
                    <h2>งานล่าสุด</h2>
                    <a href="assignments.php?course_id=<?= $course_id ?>">ดูทั้งหมด →</a>
                </div>

                <?php if ($assignments->num_rows > 0): ?>
                    <?php while ($assign = $assignments->fetch_assoc()): ?>
                        <div class="assignment-item">
                            <h4><?= h($assign['title']) ?></h4>
                            <div class="timestamp">
                                📅 กำหนดส่ง: <?= date('d/m/Y', strtotime($assign['due_date'])) ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state" style="padding: 20px;">
                        <p>ยังไม่มีงานในหลักสูตรนี้</p>
                        <a href="assignments.php?course_id=<?= $course_id ?>" class="btn btn-sm btn-primary"
                            style="margin-top:10px;">จัดการงาน</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Students Column -->
            <div class="card">
                <div class="card-header">
                    <h2>นักเรียนล่าสุด</h2>
                    <a href="students.php?course_id=<?= $course_id ?>">ดูทั้งหมด →</a>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ชื่อ</th>
                                <th>อีเมล</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($students->num_rows > 0): ?>
                                <?php while ($std = $students->fetch_assoc()):
                                    $avatar = $std['avatar'] ?? '';
                                    $initial = mb_substr($std['name'], 0, 1, 'UTF-8');
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="student-cell">
                                                <div class="avatar">
                                                    <?php if (!empty($avatar)): ?>
                                                        <img src="../<?= h($avatar) ?>" alt="avatar">
                                                    <?php else: ?>
                                                        <?= h($initial) ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div><?= h($std['name']) ?></div>
                                            </div>
                                        </td>
                                        <td><?= h($std['email']) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" style="text-align:center; padding: 20px; color:#718096;">
                                        ยังไม่มีนักเรียนในหลักสูตรนี้
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tests Column -->
            <div class="card">
                <div class="card-header">
                    <h2>แบบทดสอบ (Tests)</h2>
                </div>
                <div class="test-list" style="padding: 10px;">
                    <div class="test-item"
                        style="display:flex; justify-content:space-between; align-items:center; padding:12px; border-bottom:1px solid #eee; margin-bottom:8px;">
                        <div>
                            <div style="font-weight:600;">แบบทดสอบก่อนเรียน (Pre-test)</div>
                            <div style="font-size:12px; color:#718096;">สำหรับวัดความรู้พื้นฐาน</div>
                        </div>
                        <a href="manage_test.php?course_id=<?= $course_id ?>&type=pre"
                            class="btn btn-sm btn-outline-primary">จัดการ</a>
                    </div>
                    <div class="test-item"
                        style="display:flex; justify-content:space-between; align-items:center; padding:12px;">
                        <div>
                            <div style="font-weight:600;">แบบทดสอบหลังเรียน (Post-test)</div>
                            <div style="font-size:12px; color:#718096;">สำหรับวัดผลสัมฤทธิ์</div>
                        </div>
                        <a href="manage_test.php?course_id=<?= $course_id ?>&type=post"
                            class="btn btn-sm btn-outline-primary">จัดการ</a>
                    </div>
                </div>
            </div>

            <!-- Course Materials Column -->
            <div class="card">
                <div class="card-header">
                    <h2>เอกสารประกอบการเรียน</h2>
                    <button onclick="openUploadModal()" class="btn btn-sm btn-primary">+ อัปโหลด</button>
                </div>

                <?php if ($materials->num_rows > 0): ?>
                    <div class="material-list">
                        <?php while ($mat = $materials->fetch_assoc()): ?>
                            <div class="material-item"
                                style="display:flex; justify-content:space-between; align-items:center; padding:10px; border-bottom:1px solid #eee;">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <span style="font-size:20px;">📄</span>
                                    <div>
                                        <div style="font-weight:600;"><?= h($mat['title']) ?></div>
                                        <div style="font-size:12px; color:#718096;">
                                            <?= round($mat['file_size'] / 1024, 2) ?> KB •
                                            <?= date('d/m/Y', strtotime($mat['uploaded_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                                <div style="display:flex; gap:10px;">
                                    <a href="../<?= h($mat['file_path']) ?>" target="_blank" class="btn btn-sm btn-ghost"
                                        title="ดาวน์โหลด">⬇️</a>
                                    <button onclick="deleteMaterial(<?= $mat['id'] ?>)" class="btn btn-sm btn-danger"
                                        style="padding:4px 8px;" title="ลบ">🗑️</button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state" style="padding: 20px;">
                        <p>ยังไม่มีเอกสารในหลักสูตรนี้</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>

    </div>

    <!-- Edit Course Modal -->
    <div class="modal" id="editCourseModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>แก้ไขหลักสูตร</h3>
                <span class="modal-close" onclick="closeEditCourseModal()">×</span>
            </div>

            <form id="editCourseForm" onsubmit="updateCourse(event)">
                <input type="hidden" name="id" id="edit_course_id">
                <div class="form-group">
                    <label class="form-label">ชื่อหลักสูตร <span style="color:red">*</span></label>
                    <input type="text" name="title" id="edit_course_title" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">รายละเอียด</label>
                    <textarea name="description" id="edit_course_desc" class="form-control" rows="4"></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;">บันทึกการแก้ไข</button>
            </form>
        </div>
    </div>

    <!-- Upload Material Modal -->
    <div class="modal" id="uploadModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>อัปโหลดเอกสารประกอบการเรียน</h3>
                <span class="modal-close" onclick="closeUploadModal()">×</span>
            </div>
            <form id="uploadForm" onsubmit="uploadMaterial(event)">
                <input type="hidden" name="course_id" value="<?= $course_id ?>">
                <div class="form-group">
                    <label class="form-label">ชื่อเอกสาร <span style="color:red">*</span></label>
                    <input type="text" name="title" class="form-control" required placeholder="เช่น Lecture 1 Slide">
                </div>
                <div class="form-group">
                    <label class="form-label">ไฟล์ (PDF, Doc, Image) <span style="color:red">*</span></label>
                    <input type="file" name="file" class="form-control" required
                        accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip,.jpg,.png,.jpeg">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">อัปโหลด</button>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        function openEditCourseModal(id, title, desc) {
            document.getElementById('edit_course_id').value = id;
            document.getElementById('edit_course_title').value = title;
            document.getElementById('edit_course_desc').value = (desc || '').replace(/`/g, '');
            document.getElementById('editCourseModal').classList.add('show');
        }

        function closeEditCourseModal() {
            document.getElementById('editCourseModal').classList.remove('show');
            document.getElementById('editCourseForm').reset();
        }

        function updateCourse(e) {
            e.preventDefault();
            const formData = new FormData(e.target);

            fetch('../api/teacher_api.php?action=update_course', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('อัปเดตหลักสูตรสำเร็จ!');
                        closeEditCourseModal();
                        location.reload();
                    } else {
                        alert(data.message || 'อัปเดตไม่สำเร็จ');
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('เกิดข้อผิดพลาด');
                });
        }

        function deleteCourse(courseId) {
            if (!confirm('ยืนยันที่จะลบหลักสูตรนี้? การกระทำนี้ไม่สามารถย้อนกลับได้')) return;

            fetch('../api/teacher_api.php?action=delete_course', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'id=' + encodeURIComponent(courseId)
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('ลบหลักสูตรสำเร็จ');
                        window.location.href = 'courses.php';
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
        // Upload Material Functions
        function openUploadModal() {
            document.getElementById('uploadModal').classList.add('show');
        }

        function closeUploadModal() {
            document.getElementById('uploadModal').classList.remove('show');
            document.getElementById('uploadForm').reset();
        }

        function uploadMaterial(e) {
            e.preventDefault();
            const formData = new FormData(e.target);

            // Add action
            // Fetch API doesn't support appending action to FormData if we use URL param for action usually, 
            // but here we can append it to URL or FormData. 
            // My API checks $_GET['action'] usually? Let's check.
            // Yes, $action = $_GET['action'] ?? '';

            fetch('../api/teacher_api.php?action=add_material', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('อัปโหลดสำเร็จ');
                        location.reload();
                    } else {
                        alert(data.message || 'อัปโหลดไม่สำเร็จ');
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('เกิดข้อผิดพลาด');
                });
        }

        function deleteMaterial(id) {
            if (!confirm('ยืนยันลบเอกสารนี้?')) return;

            const formData = new FormData();
            formData.append('id', id);

            fetch('../api/teacher_api.php?action=delete_material', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('ลบสำเร็จ');
                        location.reload();
                    } else {
                        alert(data.message || 'ลบไม่สำเร็จ');
                    }
                })
                .catch(err => console.error(err));
        }

    </script>
</body>

</html>