<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION["user"]) || $_SESSION["user"]["role"] !== "teacher") {
    header("Location: ../auth/login.php");
    exit();
}

$user = $_SESSION["user"];
$teacher_id = (int)$user["id"];

function h($str)
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/**
 * Filters
 */
$q = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'newest'; // newest | title_asc | students_desc | pending_desc

/**
 * Stats
 */
$stats_sql = "
SELECT
 (SELECT COUNT(*) FROM courses) AS total_courses,
 (SELECT COUNT(DISTINCT cs.student_id)
    FROM course_students cs
    INNER JOIN courses c ON cs.course_id = c.id) AS total_students,
 (SELECT COUNT(*)
    FROM assignments a
    INNER JOIN courses c ON a.course_id = c.id) AS total_assignments,
 (SELECT COUNT(*)
    FROM assignment_submissions s
    INNER JOIN assignments a ON s.assignment_id = a.id
    INNER JOIN courses c ON a.course_id = c.id
    WHERE s.grade IS NULL) AS pending_grades
";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc() ?: [
    'total_courses' => 0,
    'total_students' => 0,
    'total_assignments' => 0,
    'pending_grades' => 0,
];

/**
 * Courses list
 */
$where = "";
$params = [];
$types = "";

if ($q !== '') {
    $where = "WHERE (c.title LIKE ? OR c.description LIKE ?)";
    $like = "%{$q}%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

$orderBy = "ORDER BY c.id DESC";
if ($sort === 'title_asc') $orderBy = "ORDER BY c.title ASC";
if ($sort === 'students_desc') $orderBy = "ORDER BY student_count DESC, c.title ASC";
if ($sort === 'pending_desc') $orderBy = "ORDER BY pending_grade_count DESC, c.title ASC";

$sql = "
SELECT
  c.*,
  (SELECT COUNT(*) FROM course_students cs WHERE cs.course_id = c.id) AS student_count,
  (SELECT COUNT(*) FROM assignments a WHERE a.course_id = c.id) AS assignment_count,
  (SELECT COUNT(*)
     FROM assignment_submissions s
     INNER JOIN assignments a2 ON s.assignment_id = a2.id
     WHERE a2.course_id = c.id AND s.grade IS NULL) AS pending_grade_count
FROM courses c
{$where}
{$orderBy}
LIMIT 200
";

$stmt = $conn->prepare($sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$courses = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Courses - CyberLearn</title>
    <link href="teacher.css" rel="stylesheet">
</head>

<body>
    <?php include "../components/teacher-sidebar.php"; ?>

    <div class="main-content">
        <div class="page-header">
            <div>
                <h1>หลักสูตรทั้งหมด</h1>
            </div>
            <div class="actions-row">
                <button class="btn btn-primary" onclick="openCreateCourseModal()">
                    <span>+</span> สร้างหลักสูตรใหม่
                </button>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div>
                    <div class="stat-num"><?= (int)$stats['total_courses'] ?></div>
                    <div class="stat-label">หลักสูตรทั้งหมด</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div>
                    <div class="stat-num"><?= (int)$stats['total_students'] ?></div>
                    <div class="stat-label">นักเรียนทั้งหมด (ไม่ซ้ำ)</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📝</div>
                <div>
                    <div class="stat-num"><?= (int)$stats['total_assignments'] ?></div>
                    <div class="stat-label">งานทั้งหมด</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📌</div>
                <div>
                    <div class="stat-num"><?= (int)$stats['pending_grades'] ?></div>
                    <div class="stat-label">รอให้คะแนน</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <form method="GET" class="filter-grid">
                <div>
                    <label class="form-label">ค้นหา</label>
                    <input type="text" name="q" class="form-control" value="<?= h($q) ?>" placeholder="ค้นหาชื่อหลักสูตร หรือคำอธิบาย...">
                </div>

                <div>
                    <label class="form-label">เรียงตาม</label>
                    <select name="sort" class="form-select">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>✨ สร้างล่าสุด</option>
                        <option value="title_asc" <?= $sort === 'title_asc' ? 'selected' : '' ?>>📝 ชื่อ A-Z</option>
                        <option value="students_desc" <?= $sort === 'students_desc' ? 'selected' : '' ?>>👥 นักเรียนเยอะสุด</option>
                        <option value="pending_desc" <?= $sort === 'pending_desc' ? 'selected' : '' ?>>📌 งานรอตรวจเยอะสุด</option>
                    </select>
                </div>

                <div>
                    <button class="btn btn-primary" style="width: 100%; height: 47px;" type="submit">ค้นหา</button>
                </div>
            </form>
        </div>

        <!-- Courses Grid -->
        <?php if ($courses->num_rows > 0): ?>
            <div class="courses-grid">
                <?php while ($c = $courses->fetch_assoc()): ?>
                    <div class="course-card">
                        <div class="course-card-body">
                            <div class="course-title" style="color: black;"><?= h($c['title']) ?></div>
                            <div class="course-desc">
                                <?= $c['description'] ? h($c['description']) : '— ไม่มีรายละเอียด —' ?>
                            </div>

                            <div class="course-meta">
                                <span class="meta-pill">👥 <?= (int)$c['student_count'] ?> นักเรียน</span>
                                <span class="meta-pill">📝 <?= (int)$c['assignment_count'] ?> งาน</span>
                                <?php if($c['pending_grade_count'] > 0): ?>
                                    <span class="meta-pill" style="background: #fff5f5; color: #e53e3e;">📌 <?= (int)$c['pending_grade_count'] ?> รอตรวจ</span>
                                <?php else: ?>
                                    <span class="meta-pill">📌 ครบถ้วน</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="course-actions">
                            <a href="course_detail.php?id=<?= (int)$c['id'] ?>" class="action-btn btn-manage">
                                ⚙️ จัดการ
                            </a>
                            <a href="students.php?course_id=<?= (int)$c['id'] ?>" class="action-btn btn-view">
                                👥 นักเรียน
                            </a>
                            <button onclick="openEditCourseModal(<?= (int)$c['id'] ?>, '<?= h($c['title']) ?>', `<?= h($c['description'] ?? '') ?>`, '<?= h($c['course_level'] ?? '1') ?>')" class="action-btn btn-edit">
                                ✏️ แก้ไข
                            </button>
                            <button onclick="deleteCourse(<?= (int)$c['id'] ?>)" class="action-btn btn-delete">
                                🗑️ ลบ
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div style="font-size: 48px; margin-bottom: 16px;">�</div>
                <h3>ไม่พบรายวิชา</h3>
                <p>ลองเปลี่ยนคำค้นหา หรือสร้างรายวิชาใหม่</p>
            </div>
        <?php endif; ?>
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
                    <input type="text" id="edit_title" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">ระดับชั้น <span style="color:red">*</span></label>
                    <select id="edit_course_level" name="course_level" class="form-control" required>
                        <option value="1">🌱 พื้นฐาน (Basic)</option>
                        <option value="2">🔧 ปานกลาง (Intermediate)</option>
                        <option value="3">🚀 ขั้นสูง (Advanced)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">รายละเอียด</label>
                    <textarea id="edit_description" name="description" class="form-control" rows="4"></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;">บันทึกการแก้ไข</button>
            </form>
        </div>
    </div>

    <script>

        function openEditCourseModal(id, title, description, course_level) {
            document.getElementById('edit_course_id').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_course_level').value = course_level || '1';
            
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
            if (!confirm('ลบหลักสูตรนี้แน่ใจนะ? (งาน/นักเรียนที่ผูกอยู่ อาจโดนผลกระทบ)')) return;

            fetch('../api/teacher_api.php?action=delete_course', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'id=' + encodeURIComponent(courseId)
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) location.reload();
                    else alert(data.message || 'ลบไม่สำเร็จ');
                })
                .catch(err => {
                    console.error(err);
                    alert('เกิดข้อผิดพลาด');
                });
        }

        // close modal when click outside
        window.addEventListener('click', (event) => {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        });
    </script>
</body>

</html>