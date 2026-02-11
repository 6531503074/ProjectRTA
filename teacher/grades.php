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

function th_dt($datetime)
{
    if (!$datetime) return '-';
    $date = DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
    if (!$date) return h($datetime);
    $months_th = ['', 'มค.', 'กพ.', 'มีค.', 'เมย.', 'พค.', 'มิย.', 'กค.', 'สค.', 'กันย.', 'ตค.', 'พย.', 'ธค.'];
    $day = $date->format('d');
    $month = $months_th[(int)$date->format('m')];
    $year = (int)$date->format('Y') + 543;
    $time = $date->format('H:i');
    return "$day $month $year $time น.";
}

/**
 * Filters
 */
$q = trim($_GET['q'] ?? '');
$course_id = $_GET['course_id'] ?? '';
$assignment_id = $_GET['assignment_id'] ?? '';
$status = $_GET['status'] ?? 'pending'; // pending | graded | all
$sort = $_GET['sort'] ?? 'newest'; // newest | oldest

$course_id_int = ($course_id !== '' && ctype_digit($course_id)) ? (int)$course_id : null;
$assignment_id_int = ($assignment_id !== '' && ctype_digit($assignment_id)) ? (int)$assignment_id : null;

/**
 * Load teacher courses for dropdowns
 */
$courses_stmt = $conn->prepare("SELECT id, title FROM courses ORDER BY title ASC");
$courses_stmt->execute();
$courses_rs = $courses_stmt->get_result();

/**
 * Load assignments for dropdown (dependent on selected course)
 */
if ($course_id_int !== null) {
    $a_stmt = $conn->prepare("SELECT a.id, a.title
                              FROM assignments a
                              INNER JOIN courses c ON a.course_id = c.id
                              WHERE a.course_id = ?
                              ORDER BY a.id DESC");
    $a_stmt->bind_param("i", $course_id_int);
} else {
    $a_stmt = $conn->prepare("SELECT a.id, a.title
                              FROM assignments a
                              INNER JOIN courses c ON a.course_id = c.id
                              ORDER BY a.id DESC
                              LIMIT 200");
}
$a_stmt->execute();
$assignments_rs = $a_stmt->get_result();

/**
 * Stats
 */
$stats_sql = "
SELECT
 (SELECT COUNT(*)
    FROM assignment_submissions s
    INNER JOIN assignments a ON s.assignment_id = a.id
    INNER JOIN courses c ON a.course_id = c.id
    WHERE s.grade IS NULL) AS pending_total,
 (SELECT COUNT(*)
    FROM assignment_submissions s
    INNER JOIN assignments a ON s.assignment_id = a.id
    INNER JOIN courses c ON a.course_id = c.id
    WHERE s.grade IS NOT NULL) AS graded_total,
 (SELECT COUNT(*)
    FROM assignments a
    INNER JOIN courses c ON a.course_id = c.id) AS assignments_total
";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc() ?: [
    'pending_total' => 0,
    'graded_total' => 0,
    'assignments_total' => 0,
];

/**
 * Submissions list
 * Assumed schema:
 * - assignment_submissions: id, assignment_id, student_id, file_path (optional), content (optional), submitted_at, grade, feedback (optional)
 * - assignments: id, course_id, title, due_date
 * - courses: id, teacher_id, title
 * - users: id, name, email, rank
 */
$where = "WHERE 1=1";
$params = [];
$types = "";

if ($course_id_int !== null) {
    $where .= " AND c.id = ?";
    $params[] = $course_id_int;
    $types .= "i";
}
if ($assignment_id_int !== null) {
    $where .= " AND a.id = ?";
    $params[] = $assignment_id_int;
    $types .= "i";
}

if ($status === 'pending') {
    $where .= " AND s.grade IS NULL";
} elseif ($status === 'graded') {
    $where .= " AND s.grade IS NOT NULL";
}

if ($q !== '') {
    $where .= " AND (u.name LIKE ? OR u.email LIKE ? OR a.title LIKE ? OR c.title LIKE ?)";
    $like = "%{$q}%";
    $params = array_merge($params, [$like, $like, $like, $like]);
    $types .= "ssss";
}

$orderBy = ($sort === 'oldest') ? "ORDER BY s.submitted_at ASC" : "ORDER BY s.submitted_at DESC";

$sql = "
SELECT
  s.id AS submission_id,
  s.submitted_at,
  s.grade,
  s.feedback,
  s.file_path,
  s.content,
  a.id AS assignment_id,
  a.title AS assignment_title,
  a.due_date,
  c.id AS course_id,
  c.title AS course_title,
  u.id AS student_id,
  u.name AS student_name,
  u.email,
  u.rank
FROM assignment_submissions s
INNER JOIN assignments a ON s.assignment_id = a.id
INNER JOIN courses c ON a.course_id = c.id
INNER JOIN users u ON s.student_id = u.id
{$where}
{$orderBy}
LIMIT 400
";

$stmt = $conn->prepare($sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$submissions = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Grades - CyberLearn</title>
    <link href="teacher.css" rel="stylesheet">
</head>

<body>
    <?php include "../components/teacher-sidebar.php"; ?>

    <div class="main-content">
        <div class="page-header">
            <div>
                <h1>คะแนนงาน</h1>
            </div>
            <div class="actions-row">
                <button class="btn btn-secondary" onclick="location.reload()">รีเฟรช</button>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📌</div>
                <div>
                    <div class="stat-num"><?= (int)$stats['pending_total'] ?></div>
                    <div class="stat-label">รอให้คะแนน</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div>
                    <div class="stat-num"><?= (int)$stats['graded_total'] ?></div>
                    <div class="stat-label">ให้คะแนนแล้ว</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📝</div>
                <div>
                    <div class="stat-num"><?= (int)$stats['assignments_total'] ?></div>
                    <div class="stat-label">จำนวนงานทั้งหมด</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card">
            <form method="GET" class="filter-grid">
                <div>
                    <label>ค้นหา</label>
                    <input type="text" name="q" value="<?= h($q) ?>" placeholder="ชื่อนักเรียน / อีเมล / ชื่องาน / ชื่อคอร์ส">
                </div>

                <div>
                    <label>หลักสูตร</label>
                    <select name="course_id" onchange="this.form.submit()">
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
                    <label>งาน</label>
                    <select name="assignment_id">
                        <option value="">ทั้งหมด</option>
                        <?php while ($a = $assignments_rs->fetch_assoc()): ?>
                            <option value="<?= (int)$a['id'] ?>" <?= ($assignment_id_int === (int)$a['id']) ? 'selected' : '' ?>>
                                <?= h($a['title']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label>สถานะ</label>
                    <select name="status">
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>รอให้คะแนน</option>
                        <option value="graded" <?= $status === 'graded' ? 'selected' : '' ?>>ให้คะแนนแล้ว</option>
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>ทั้งหมด</option>
                    </select>
                </div>

                <div>
                    <label>เรียงตาม</label>
                    <select name="sort">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>ส่งล่าสุด</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>ส่งเก่าสุด</option>
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
                            <th>นักเรียน</th>
                            <th>งาน</th>
                            <th>หลักสูตร</th>
                            <th>เวลาส่ง</th>
                            <th>สถานะ</th>
                            <th>ให้คะแนน</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($submissions->num_rows > 0): ?>
                            <?php while ($s = $submissions->fetch_assoc()): ?>
                                <?php
                                $isGraded = ($s['grade'] !== null && $s['grade'] !== '');
                                $statusTag = $isGraded ? 'graded' : 'pending';
                                ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:900;">
                                            <?= h(($s['rank'] ?? '') . ' ' . ($s['student_name'] ?? '')) ?>
                                        </div>
                                        <div class="muted"><?= h($s['email'] ?? '-') ?></div>
                                        <div class="muted">ID: <?= (int)$s['student_id'] ?></div>
                                    </td>

                                    <td>
                                        <div style="font-weight:900;"><?= h($s['assignment_title'] ?? '-') ?></div>
                                        <div class="muted">กำหนดส่ง: <?= h($s['due_date'] ?? '-') ?></div>
                                        <?php if (!empty($s['file_path'])): ?>
                                            <div style="margin-top:8px;">
                                                <a class="btn btn-sm btn-view" href="../<?= h($s['file_path']) ?>" target="_blank" rel="noopener">
                                                    ดูไฟล์ที่ส่ง
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <td><?= h($s['course_title'] ?? '-') ?></td>

                                    <td><?= th_dt($s['submitted_at'] ?? '') ?></td>

                                    <td>
                                        <span class="tag <?= h($statusTag) ?>">
                                            <?= $isGraded ? 'ให้คะแนนแล้ว' : 'รอให้คะแนน' ?>
                                        </span>
                                        <?php if ($isGraded): ?>
                                            <div class="muted" style="margin-top:6px;">คะแนน: <?= h($s['grade']) ?></div>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div class="inline-grade">
                                            <div>
                                                <label style="margin-bottom:6px;">คะแนน</label>
                                                <input type="number" step="0.01" min="0" name="grade_<?= (int)$s['submission_id'] ?>"
                                                    value="<?= h($s['grade'] ?? '') ?>"
                                                    placeholder="เช่น 10 หรือ 9.5">
                                            </div>

                                            <div>
                                                <label style="margin-bottom:6px;">Feedback</label>
                                                <textarea name="feedback_<?= (int)$s['submission_id'] ?>" placeholder="คอมเมนต์ให้นักเรียน"><?= h($s['feedback'] ?? '') ?></textarea>
                                                <?php if (!empty($s['content'])): ?>
                                                    <div class="muted" style="margin-top:6px;">
                                                        ข้อความที่ส่ง: <?= h(mb_strimwidth($s['content'], 0, 140, '…', 'UTF-8')) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <div>
                                                <label style="margin-bottom:6px;">บันทึก</label>
                                                <button class="btn btn-primary" onclick="saveGrade(<?= (int)$s['submission_id'] ?>)">
                                                    บันทึก
                                                </button>
                                                <button class="btn btn-ghost" onclick="goGradePage(<?= (int)$s['submission_id'] ?>)">
                                                    หน้าเต็ม
                                                </button>
                                                <div id="msg_<?= (int)$s['submission_id'] ?>" class="muted" style="margin-top:6px;"></div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">📭</div>
                                        <div>ยังไม่มีรายการส่งงานที่ตรงกับเงื่อนไข</div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function goGradePage(submissionId) {
            // ถ้าเธอมีหน้า grade_submission.php อยู่แล้วจาก dashboard ก็ใช้ต่อได้เลย
            window.location.href = `grade_submission.php?id=${submissionId}`;
        }

        function saveGrade(submissionId) {
            const gradeEl = document.querySelector(`[name="grade_${submissionId}"]`);
            const feedbackEl = document.querySelector(`[name="feedback_${submissionId}"]`);
            const msg = document.getElementById(`msg_${submissionId}`);

            const grade = gradeEl ? gradeEl.value.trim() : '';
            const feedback = feedbackEl ? feedbackEl.value.trim() : '';

            msg.textContent = 'กำลังบันทึก...';

            fetch('../api/teacher_api.php?action=update_grade', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'submission_id=' + encodeURIComponent(submissionId) +
                        '&grade=' + encodeURIComponent(grade) +
                        '&feedback=' + encodeURIComponent(feedback)
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        msg.textContent = 'บันทึกแล้ว ✅';
                        setTimeout(() => {
                            msg.textContent = '';
                        }, 1500);
                    } else {
                        msg.textContent = data.message || 'บันทึกไม่สำเร็จ';
                    }
                })
                .catch(err => {
                    console.error(err);
                    msg.textContent = 'เกิดข้อผิดพลาด';
                });
        }
    </script>
</body>

</html>