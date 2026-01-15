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
 (SELECT COUNT(*) FROM courses WHERE teacher_id = ?) AS total_courses,
 (SELECT COUNT(DISTINCT cs.student_id)
    FROM course_students cs
    INNER JOIN courses c ON cs.course_id = c.id
    WHERE c.teacher_id = ?) AS total_students,
 (SELECT COUNT(*)
    FROM assignments a
    INNER JOIN courses c ON a.course_id = c.id
    WHERE c.teacher_id = ?) AS total_assignments,
 (SELECT COUNT(*)
    FROM assignment_submissions s
    INNER JOIN assignments a ON s.assignment_id = a.id
    INNER JOIN courses c ON a.course_id = c.id
    WHERE c.teacher_id = ? AND s.grade IS NULL) AS pending_grades
";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("iiii", $teacher_id, $teacher_id, $teacher_id, $teacher_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc() ?: [
    'total_courses' => 0,
    'total_students' => 0,
    'total_assignments' => 0,
    'pending_grades' => 0,
];

/**
 * Courses list
 * Assumed schema:
 * - courses: id, teacher_id, title, description (optional)
 * - course_students: course_id, student_id
 * - assignments: course_id
 * - assignment_submissions + assignments for pending grades
 */
$where = "WHERE c.teacher_id = ?";
$params = [$teacher_id];
$types = "i";

if ($q !== '') {
    $where .= " AND (c.title LIKE ? OR c.description LIKE ?)";
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
$stmt->bind_param($types, ...$params);
$stmt->execute();
$courses = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Courses - CyberLearn</title>
    <style>
        *{
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            margin: 0;
        }

        .main-content {
            margin-left: 280px;
            padding: 30px;
            min-height: 100vh;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 20px;
        }

        .page-header h1 {
            margin: 0;
            font-size: 28px;
            color: #2d3748;
        }

        .page-header p {
            margin: 6px 0 0;
            color: #718096;
            font-size: 14px;
        }

        .actions-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 14px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 900;
            font-size: 13px;
            transition: 0.2s;
        }

        .btn-primary {
            background: #f39c12;
            color: #fff;
        }

        .btn-primary:hover {
            background: #e67e22;
        }

        .btn-ghost {
            background: #fff;
            color: #2d3748;
            border: 2px solid #e2e8f0;
        }

        .btn-ghost:hover {
            border-color: #f39c12;
        }

        .btn-danger {
            background: #e74c3c;
            color: #fff;
        }

        .btn-danger:hover {
            filter: brightness(.95);
        }

        .btn-secondary {
            background: #667eea;
            color: #fff;
        }

        .btn-secondary:hover {
            filter: brightness(.95);
        }

        .btn-sm {
            padding: 7px 10px;
            border-radius: 10px;
            font-size: 12px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin: 18px 0 22px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

            .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            background: rgba(243, 156, 18, .15);
        }

        .stat-num {
            font-size: 26px;
            font-weight: 900;
            color: #2d3748;
            line-height: 1;
        }

        .stat-label {
            font-size: 13px;
            color: #718096;
            margin-top: 4px;
        }

        .card {
            background: #fff;
            border-radius: 12px;
            padding: 18px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, .08);
            margin-bottom: 18px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: 1.3fr 1fr auto;
            gap: 12px;
            align-items: end;
        }

        .filter-grid div input {
            width: 90%;
            /* min-width: 200px; */
        }

        label {
            display: block;
            font-size: 12px;
            color: #718096;
            margin-bottom: 6px;
            font-weight: 900;
        }

        input[type="text"],
        select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 13px;
            outline: none;
            background: #fff;
        }

        input[type="text"]:focus,
        select:focus {
            border-color: #f39c12;
        }

        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 14px;
        }

        .course-card {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            border-radius: 14px;
            padding: 18px;
            color: #fff;
            box-shadow: 0 10px 20px rgba(0, 0, 0, .08);
            position: relative;
            overflow: hidden;
        }

        .course-card .title {
            font-size: 16px;
            font-weight: 900;
            margin: 0 0 8px;
        }

        .course-card .desc {
            font-size: 12px;
            opacity: .92;
            margin: 0 0 12px;
            line-height: 1.45;
        }

        .meta-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            font-size: 12px;
            opacity: .95;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .18);
            font-weight: 800;
        }

        .card-actions {
            display: flex;
            gap: 8px;
            margin-top: 14px;
            flex-wrap: wrap;
        }

        .card-actions a,
        .card-actions button {
            border: none;
            cursor: pointer;
            text-decoration: none;
            padding: 8px 10px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 900;
            background: rgba(255, 255, 255, .18);
            color: #fff;
            transition: .2s;
        }

        .card-actions a:hover,
        .card-actions button:hover {
            background: rgba(255, 255, 255, .26);
        }

        .danger {
            background: rgba(231, 76, 60, .25) !important;
        }

        .danger:hover {
            background: rgba(231, 76, 60, .35) !important;
        }

        .empty {
            text-align: center;
            padding: 50px 18px;
            color: #a0aec0;
        }

        .empty .icon {
            font-size: 46px;
            margin-bottom: 8px;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            font-size: 20px;
            color: #2d3748;
        }

        .modal-close {
            font-size: 24px;
            cursor: pointer;
            color: #a0aec0;
        }

        .modal-close:hover {
            color: #2d3748;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3748;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #f39c12;
        }
        

        .btn-submit {
            width: 100%;
            padding: 12px;
            background: #f39c12;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-submit:hover {
            background: #e67e22;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                flex-direction: column;
            }
        }
        @media(max-width:1024px) {
            .filter-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

    </style>
</head>

<body>
    <?php include "../components/teacher-sidebar.php"; ?>

    <div class="main-content">
        <div class="page-header">
            <div>
                <h1>หลักสูตรทั้งหมด</h1>
            </div>
            <div class="actions-row">
                <button class="btn btn-primary" onclick="openCreateCourseModal()">➕ สร้างหลักสูตร</button>
                <button class="btn btn-ghost" onclick="window.location.href='dashboard.php'">กลับแดชบอร์ด</button>
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
                    <div class="stat-label">รอให้เกรด</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card">
            <form method="GET" class="filter-grid">
                <div>
                    <label>ค้นหา</label>
                    <input type="text" name="q" value="<?= h($q) ?>" placeholder="ชื่อหลักสูตร / คำอธิบาย">
                </div>

                <div>
                    <label>เรียงตาม</label>
                    <select name="sort">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>สร้างล่าสุด</option>
                        <option value="title_asc" <?= $sort === 'title_asc' ? 'selected' : '' ?>>ชื่อ A-Z</option>
                        <option value="students_desc" <?= $sort === 'students_desc' ? 'selected' : '' ?>>นักเรียนเยอะสุด</option>
                        <option value="pending_desc" <?= $sort === 'pending_desc' ? 'selected' : '' ?>>งานรอให้เกรดเยอะสุด</option>
                    </select>
                </div>

                <div>
                    <button class="btn btn-primary" type="submit">ค้นหา</button>
                </div>
            </form>
        </div>

        <!-- Courses Grid -->
        <div class="card">
            <?php if ($courses->num_rows > 0): ?>
                <div class="courses-grid">
                    <?php while ($c = $courses->fetch_assoc()): ?>
                        <div class="course-card">
                            <div class="title"><?= h($c['title']) ?></div>
                            <div class="desc">
                                <?= $c['description'] ? h(mb_strimwidth($c['description'], 0, 120, '…', 'UTF-8')) : '— ไม่มีรายละเอียด —' ?>
                            </div>

                            <div class="meta-row">
                                <span class="pill">👥 <?= (int)$c['student_count'] ?> นักเรียน</span>
                                <span class="pill">📝 <?= (int)$c['assignment_count'] ?> งาน</span>
                                <span class="pill">📌 <?= (int)$c['pending_grade_count'] ?> รอให้เกรด</span>
                            </div>

                            <div class="card-actions">
                                <a href="course_detail.php?id=<?= (int)$c['id'] ?>">จัดการคอร์ส</a>
                                <a href="students.php?course_id=<?= (int)$c['id'] ?>">ดูนักเรียน</a>
                                <a href="assignments.php?course_id=<?= (int)$c['id'] ?>">ดูงาน</a>
                                <button onclick="openEditCourseModal(<?= (int)$c['id'] ?>, '<?= h($c['title']) ?>', `<?= h($c['description'] ?? '') ?>`)">แก้ไข</button>
                                <button class="danger" onclick="deleteCourse(<?= (int)$c['id'] ?>)">ลบ</button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty">
                    <div class="icon">📚</div>
                    <div>ยังไม่มีหลักสูตร หรือไม่พบผลลัพธ์จากการค้นหา</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Course Modal -->
    <div class="modal" id="createCourseModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>สร้างหลักสูตรใหม่</h3>
                <span class="modal-close" onclick="closeCreateCourseModal()">×</span>
            </div>
            <form id="createCourseForm" onsubmit="createCourse(event)">
                <div class="form-group">
                    <label>ชื่อหลักสูตร *</label>
                    <input type="text" name="title" required placeholder="ใส่ชื่อหลักสูตร">
                </div>
                <div class="form-group">
                    <label>รายละเอียด</label>
                    <textarea name="description" rows="4" placeholder="ใส่รายละเอียดหลักสูตร"></textarea>
                </div>
                <button type="submit" class="btn-submit">สร้างหลักสูตร</button>
            </form>
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
                    <label>ชื่อหลักสูตร *</label>
                    <input type="text" name="title" id="edit_course_title" required>
                </div>

                <div class="form-group">
                    <label>รายละเอียด</label>
                    <textarea name="description" id="edit_course_desc"></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;">บันทึกการแก้ไข</button>
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

        function createCourse(e) {
            e.preventDefault();
            const formData = new FormData(e.target);

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