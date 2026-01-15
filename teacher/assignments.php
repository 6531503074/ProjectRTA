<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION["user"]) || $_SESSION["user"]["role"] !== "teacher") {
    header("Location: ../auth/login.php");
    exit();
}

$user = $_SESSION["user"];
$teacher_id = (int)$user["id"];

/**
 * Helpers
 */
function h($str)
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}
function th_date($ymd)
{
    if (!$ymd) return '-';
    $date = DateTime::createFromFormat('Y-m-d', $ymd);
    if (!$date) return h($ymd);
    $months_th = ['', 'มค.', 'กพ.', 'มีค.', 'เมย.', 'พค.', 'มิย.', 'กค.', 'สค.', 'กันย.', 'ตค.', 'พย.', 'ธค.'];
    $day = $date->format('d');
    $month = $months_th[(int)$date->format('m')];
    $year = (int)$date->format('Y') + 543;
    return "$day $month $year";
}

/**
 * Filters (GET)
 */
$q = trim($_GET['q'] ?? '');
$course_id = $_GET['course_id'] ?? '';
$status = $_GET['status'] ?? 'all'; // all | open | overdue | closed
$sort = $_GET['sort'] ?? 'due_asc';  // due_asc | due_desc | newest

$course_id_int = ($course_id !== '' && ctype_digit($course_id)) ? (int)$course_id : null;

/**
 * Load teacher courses for filter dropdown
 */
$courses_stmt = $conn->prepare("SELECT id, title FROM courses WHERE teacher_id = ? ORDER BY title ASC");
$courses_stmt->bind_param("i", $teacher_id);
$courses_stmt->execute();
$courses_rs = $courses_stmt->get_result();

/**
 * Build query for assignments list
 * Assumed schema:
 * - assignments: id, course_id, title, description, due_date, created_at
 * - courses: id, title, teacher_id
 * - assignment_submissions: id, assignment_id, student_id, submitted_at, grade
 */
$where = "WHERE c.teacher_id = ?";
$params = [$teacher_id];
$types = "i";

if ($q !== '') {
    $where .= " AND (a.title LIKE ? OR a.description LIKE ? OR c.title LIKE ?)";
    $like = "%{$q}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

if ($course_id_int !== null) {
    $where .= " AND a.course_id = ?";
    $params[] = $course_id_int;
    $types .= "i";
}

// status filter
// open: due_date >= today
// overdue: due_date < today
// closed: (optional) if you have a.status; otherwise treat as "overdue AND all graded"? -> we'll keep simple by date.
if ($status === 'open') {
    $where .= " AND a.due_date >= CURDATE()";
} elseif ($status === 'overdue') {
    $where .= " AND a.due_date < CURDATE()";
} elseif ($status === 'closed') {
    // ถ้าใน DB มีคอลัมน์ a.is_closed หรือ a.status ให้แก้ตรงนี้
    // ตอนนี้ให้ใช้ heuristic: due_date < today และ (ส่งครบทุกคน) — อาจไม่ชัวร์ ถ้าไม่มีข้อมูลครบ
    // จะโชว์เป็น "ปิด/หมดเขต" ตาม due_date ไปก่อน
    $where .= " AND a.due_date < CURDATE()";
}

$orderBy = "ORDER BY a.due_date ASC";
if ($sort === 'due_desc') $orderBy = "ORDER BY a.due_date DESC";
if ($sort === 'newest')   $orderBy = "ORDER BY a.id DESC";

$sql = "
SELECT
  a.*,
  c.title AS course_title,
  (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.id) AS submission_count,
  (SELECT COUNT(*) FROM course_students cs WHERE cs.course_id = a.course_id) AS student_count,
  (SELECT COUNT(*) FROM assignment_submissions s2 WHERE s2.assignment_id = a.id AND s2.grade IS NULL) AS pending_grade_count
FROM assignments a
INNER JOIN courses c ON a.course_id = c.id
{$where}
{$orderBy}
LIMIT 200
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$assignments = $stmt->get_result();

/**
 * Stats quick summary
 */
$stats_sql = "
SELECT
  (SELECT COUNT(*)
     FROM assignments a
     INNER JOIN courses c ON a.course_id = c.id
     WHERE c.teacher_id = ?) AS total_assignments,
  (SELECT COUNT(*)
     FROM assignments a
     INNER JOIN courses c ON a.course_id = c.id
     WHERE c.teacher_id = ? AND a.due_date >= CURDATE()) AS open_assignments,
  (SELECT COUNT(*)
     FROM assignments a
     INNER JOIN courses c ON a.course_id = c.id
     WHERE c.teacher_id = ? AND a.due_date < CURDATE()) AS overdue_assignments,
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
    'total_assignments' => 0,
    'open_assignments' => 0,
    'overdue_assignments' => 0,
    'pending_grades' => 0,
];

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Assignments - CyberLearn</title>

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
            font-weight: 700;
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

        /* stats */
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
            font-weight: 800;
            color: #2d3748;
            line-height: 1;
        }

        .stat-label {
            font-size: 13px;
            color: #718096;
            margin-top: 4px;
        }

        /* filter card */
        .card {
            background: #fff;
            border-radius: 12px;
            padding: 18px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, .08);
            margin-bottom: 18px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr 1fr 1fr auto;
            gap: 12px;
            align-items: end;
        }

        .filter-grid div input{
            width: 90%;
            /* min-width: 200px; */
        }

        label {
            display: block;
            font-size: 12px;
            color: #718096;
            margin-bottom: 6px;
            font-weight: 700;
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

        /* table */
        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        thead th {
            text-align: left;
            font-size: 12px;
            color: #718096;
            padding: 12px;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }

        tbody td {
            padding: 12px;
            border-bottom: 1px solid #edf2f7;
            font-size: 13px;
            color: #2d3748;
            vertical-align: top;
        }

        .tag {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            background: #edf2f7;
            color: #2d3748;
        }

        .tag.open {
            background: rgba(46, 204, 113, .15);
            color: #27ae60;
        }

        .tag.overdue {
            background: rgba(231, 76, 60, .12);
            color: #e74c3c;
        }

        .tag.soon {
            background: rgba(243, 156, 18, .15);
            color: #e67e22;
        }

        .muted {
            color: #718096;
            font-size: 12px;
        }

        .row-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 7px 10px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 800;
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

        .empty {
            text-align: center;
            padding: 40px;
            color: #a0aec0;
        }

        .empty .icon {
            font-size: 46px;
            margin-bottom: 8px;
        }

        /* modal */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .5);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: #fff;
            border-radius: 14px;
            width: min(560px, 92vw);
            max-height: 82vh;
            overflow: auto;
            padding: 22px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .modal-header h3 {
            margin: 0;
            color: #2d3748;
        }

        .modal-close {
            font-size: 26px;
            cursor: pointer;
            color: #a0aec0;
        }

        .modal-close:hover {
            color: #2d3748;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        textarea {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 13px;
            outline: none;
        }

        textarea:focus {
            border-color: #f39c12;
        }

        .help {
            font-size: 12px;
            color: #718096;
            margin-top: 6px;
        }

        @media(max-width:1024px) {
            .filter-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media(max-width:768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include "../components/teacher-sidebar.php"; ?>

    <div class="main-content">
        <div class="page-header">
            <div>
                <h1>งาน</h1>
            </div>
            <div class="actions-row">
                <button class="btn btn-primary" onclick="openAssignmentModal()">➕ สร้างงานใหม่</button>
                <button class="btn btn-ghost" onclick="window.location.href='grades.php'">ไปหน้าให้เกรด</button>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">

                <div class="stat-icon">📝</div>
                <div>
                    <div class="stat-num"><?= (int)$stats['total_assignments'] ?></div>
                    <div class="stat-label">งานทั้งหมด</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div>
                    <div class="stat-num"><?= (int)$stats['open_assignments'] ?></div>
                    <div class="stat-label">ยังไม่หมดเขต</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏰</div>
                <div>
                    <div class="stat-num"><?= (int)$stats['overdue_assignments'] ?></div>
                    <div class="stat-label">หมดเขตแล้ว</div>
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
                    <input type="text" name="q" value="<?= h($q) ?>" placeholder="เช่น ชื่อชิ้นงาน / คำอธิบาย / ชื่อคอร์ส">
                </div>

                <div>
                    <label>หลักสูตร</label>
                    <select name="course_id">
                        <option value="">ทั้งหมด</option>
                        <?php mysqli_data_seek($courses_rs, 0); ?>
                        <?php while ($c = $courses_rs->fetch_assoc()): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= ($course_id_int === (int)$c['id']) ? 'selected' : '' ?>>
                                <?= h($c['title']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label>สถานะ</label>
                    <select name="status">
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>ทั้งหมด</option>
                        <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>ยังไม่หมดเขต</option>
                        <option value="overdue" <?= $status === 'overdue' ? 'selected' : '' ?>>หมดเขตแล้ว</option>
                        <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>ปิด/หมดเขต</option>
                    </select>
                </div>

                <div>
                    <label>เรียงตาม</label>
                    <select name="sort">
                        <option value="due_asc" <?= $sort === 'due_asc' ? 'selected' : '' ?>>กำหนดส่งใกล้สุด</option>
                        <option value="due_desc" <?= $sort === 'due_desc' ? 'selected' : '' ?>>กำหนดส่งไกลสุด</option>
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>สร้างล่าสุด</option>
                    </select>
                </div>

                <div>
                    <button class="btn btn-primary" type="submit">ค้นหา</button>
                </div>
            </form>
        </div>

        <!-- List -->
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>งาน</th>
                            <th>หลักสูตร</th>
                            <th>กำหนดส่ง</th>
                            <th>ความคืบหน้า</th>
                            <th>รอให้เกรด</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($assignments->num_rows > 0): ?>
                            <?php while ($a = $assignments->fetch_assoc()): ?>
                                <?php
                                $due = $a['due_date'] ?? null;
                                $dueTag = 'open';
                                $dueText = 'ยังไม่หมดเขต';
                                $soon = false;

                                if ($due) {
                                    $due_dt = DateTime::createFromFormat('Y-m-d', $due);
                                    $today = new DateTime('today');
                                    if ($due_dt < $today) {
                                        $dueTag = 'overdue';
                                        $dueText = 'หมดเขตแล้ว';
                                    } else {
                                        // ใกล้ถึง: ภายใน 3 วัน
                                        $diffDays = (int)$today->diff($due_dt)->format('%a');
                                        if ($diffDays <= 3) {
                                            $dueTag = 'soon';
                                            $dueText = 'ใกล้ถึง';
                                            $soon = true;
                                        }
                                    }
                                } else {
                                    $dueTag = '';
                                    $dueText = 'ไม่ระบุ';
                                }

                                $studentCount = (int)($a['student_count'] ?? 0);
                                $subCount = (int)($a['submission_count'] ?? 0);
                                $pendingGrade = (int)($a['pending_grade_count'] ?? 0);

                                $progress = $studentCount > 0 ? round(($subCount / $studentCount) * 100) : 0;
                                ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:800;"><?= h($a['title']) ?></div>
                                        <div class="muted"><?= $a['description'] ? h(mb_strimwidth($a['description'], 0, 90, '…', 'UTF-8')) : '—' ?></div>
                                    </td>
                                    <td><?= h($a['course_title']) ?></td>
                                    <td>
                                        <div><?= th_date($due) ?></div>
                                        <div style="margin-top:6px;"><span class="tag <?= h($dueTag) ?>"><?= h($dueText) ?></span></div>
                                    </td>
                                    <td>
                                        <div style="font-weight:800;"><?= $subCount ?>/<?= $studentCount ?> ส่งแล้ว</div>
                                        <div class="muted"><?= $progress ?>%</div>
                                    </td>
                                    <td>
                                        <span class="tag"><?= $pendingGrade ?></span>
                                    </td>
                                    <td>
                                        <div class="row-actions">
                                            <button class="btn btn-sm btn-secondary" onclick="viewAssignment(<?= (int)$a['id'] ?>)">ดู/จัดการ</button>
                                            <button class="btn btn-sm btn-ghost" onclick="editAssignment(<?= (int)$a['id'] ?>)">แก้ไข</button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteAssignment(<?= (int)$a['id'] ?>)">ลบ</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty">
                                        <div class="icon">🗂️</div>
                                        <div>ยังไม่เจองานที่ตรงกับเงื่อนไข</div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Create Assignment Modal -->
    <div class="modal" id="assignmentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>สร้างงานใหม่</h3>
                <span class="modal-close" onclick="closeAssignmentModal()">×</span>
            </div>

            <form id="createAssignmentForm" onsubmit="createAssignment(event)">
                <div class="form-row">
                    <div>
                        <label>หลักสูตร *</label>
                        <select name="course_id" required>
                            <option value="" disabled selected>เลือกหลักสูตร</option>
                            <?php
                            // โหลด courses ใหม่สำหรับ modal (กัน cursor ชี้ท้าย)
                            $courses_stmt2 = $conn->prepare("SELECT id, title FROM courses WHERE teacher_id = ? ORDER BY title ASC");
                            $courses_stmt2->bind_param("i", $teacher_id);
                            $courses_stmt2->execute();
                            $courses_rs2 = $courses_stmt2->get_result();
                            while ($c2 = $courses_rs2->fetch_assoc()):
                            ?>
                                <option value="<?= (int)$c2['id'] ?>"><?= h($c2['title']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div>
                        <label>กำหนดส่ง *</label>
                        <input type="date" name="due_date" required>
                        <div class="help">ตั้งวันส่งให้ชัด จะได้ตามงานได้แบบไม่ต้องสวดมนต์</div>
                    </div>
                </div>

                <div style="margin-top:12px;">
                    <label>ชื่องาน *</label>
                    <input type="text" name="title" required placeholder="เช่น Assignment 1: SQL Basics">
                </div>

                <div style="margin-top:12px;">
                    <label>คำอธิบาย</label>
                    <textarea name="description" rows="5" placeholder="รายละเอียดงาน, เงื่อนไข, วิธีส่งงาน ฯลฯ"></textarea>
                </div>

                <div style="margin-top:14px;">
                    <button type="submit" class="btn btn-primary" style="width:100%;">สร้างงาน</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAssignmentModal() {
            document.getElementById('assignmentModal').classList.add('show');
        }

        function closeAssignmentModal() {
            document.getElementById('assignmentModal').classList.remove('show');
            document.getElementById('createAssignmentForm').reset();
        }

        // create assignment -> API (ทำให้ pattern เหมือน create_course)
        function createAssignment(e) {
            e.preventDefault();
            const formData = new FormData(e.target);

            fetch('../api/teacher_api.php?action=create_assignment', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('สร้างงานสำเร็จ!');
                        closeAssignmentModal();
                        location.reload();
                    } else {
                        alert(data.message || 'สร้างงานไม่สำเร็จ');
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('เกิดข้อผิดพลาด');
                });
        }

        function viewAssignment(id) {
            // ถ้ามีหน้ารายละเอียดงาน: assignment_detail.php
            window.location.href = `assignment_detail.php?id=${id}`;
        }

        function editAssignment(id) {
            // ถ้ามีหน้าแก้: edit_assignment.php
            window.location.href = `edit_assignment.php?id=${id}`;
        }

        function deleteAssignment(id) {
            if (!confirm('ลบงานนี้แน่ใจนะ? (ลบแล้วไม่ใช่ลบแค่จากใจ 😭)')) return;

            fetch('../api/teacher_api.php?action=delete_assignment', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'id=' + encodeURIComponent(id)
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