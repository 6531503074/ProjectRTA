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
$course_id = $_GET['course_id'] ?? '';
$sort = $_GET['sort'] ?? 'name_asc'; // name_asc | name_desc | newest

$course_id_int = ($course_id !== '' && ctype_digit($course_id)) ? (int)$course_id : null;

/**
 * Teacher courses (dropdown + sidebar already uses it, but we need for filter + modal)
 */
$courses_stmt = $conn->prepare("SELECT id, title FROM courses WHERE teacher_id = ? ORDER BY title ASC");
$courses_stmt->bind_param("i", $teacher_id);
$courses_stmt->execute();
$courses_rs = $courses_stmt->get_result();

/**
 * Stats
 * - total students (distinct across all teacher courses)
 * - total enrollments (course_students rows for teacher courses)
 * - courses count
 */
$stats_sql = "
SELECT
 (SELECT COUNT(DISTINCT cs.student_id)
    FROM course_students cs
    INNER JOIN courses c ON cs.course_id = c.id
    WHERE c.teacher_id = ?) AS total_students,
 (SELECT COUNT(*)
    FROM course_students cs
    INNER JOIN courses c ON cs.course_id = c.id
    WHERE c.teacher_id = ?) AS total_enrollments,
 (SELECT COUNT(*) FROM courses WHERE teacher_id = ?) AS total_courses
";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("iii", $teacher_id, $teacher_id, $teacher_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc() ?: [
  'total_students' => 0,
  'total_enrollments' => 0,
  'total_courses' => 0,
];

/**
 * Students list
 * Assumed schema:
 * - users: id, name, email, role, rank, position, affiliation, phone, avatar
 * - course_students: course_id, student_id, created_at (optional)
 * - courses: id, title, teacher_id
 */
$where = "WHERE c.teacher_id = ? AND u.role = 'student'";
$params = [$teacher_id];
$types = "i";

if ($course_id_int !== null) {
  $where .= " AND c.id = ?";
  $params[] = $course_id_int;
  $types .= "i";
}

if ($q !== '') {
  $where .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.rank LIKE ? OR u.position LIKE ? OR u.affiliation LIKE ? OR c.title LIKE ?)";
  $like = "%{$q}%";
  $params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
  $types .= "ssssss";
}

$orderBy = "ORDER BY u.name ASC";
if ($sort === 'name_desc') $orderBy = "ORDER BY u.name DESC";
if ($sort === 'newest') $orderBy = "ORDER BY cs.student_id DESC"; // fallback ถ้าไม่มี created_at

$sql = "
SELECT
  u.id AS student_id,
  u.name AS student_name,
  u.email,
  u.rank,
  u.position,
  u.affiliation,
  u.phone,
  u.avatar,
  c.id AS course_id,
  c.title AS course_title
FROM course_students cs
INNER JOIN courses c ON cs.course_id = c.id
INNER JOIN users u ON cs.student_id = u.id
{$where}
{$orderBy}
LIMIT 300
";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result();

/**
 * If no course selected, show a "summary grouped view" is heavy.
 * We'll keep simple table; teacher can filter by course.
 */
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Students - CyberLearn</title>
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
      font-weight: 800;
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
      grid-template-columns: 1.2fr 1fr 1fr auto;
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
      font-weight: 800;
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

    .muted {
      color: #718096;
      font-size: 12px;
    }

    .row-actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .student-cell {
      display: flex;
      gap: 10px;
      align-items: center;
    }

    .avatar {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: #edf2f7;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      flex-shrink: 0;
      font-weight: 900;
      color: #2d3748;
    }

    .avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .tag {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 900;
      background: #edf2f7;
      color: #2d3748;
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

    }
  </style>
</head>

<body>
  <?php include "../components/teacher-sidebar.php"; ?>

  <div class="main-content">
    <div class="page-header">
      <div>
        <h1>นักเรียน</h1>
        <p>ดูรายชื่อเด็กในคอร์ส, ค้นหาไว, และเพิ่ม/เอาออกจากคอร์สได้ในหน้าเดียว</p>
      </div>
      <div class="actions-row">
        <button class="btn btn-primary" onclick="openStudentModal()">➕ เพิ่มนักเรียนเข้าคอร์ส</button>
        <button class="btn btn-ghost" onclick="window.location.href='courses.php'">ไปหน้าหลักสูตร</button>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div>
          <div class="stat-num"><?= (int)$stats['total_students'] ?></div>
          <div class="stat-label">นักเรียนทั้งหมด (ไม่ซ้ำ)</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📚</div>
        <div>
          <div class="stat-num"><?= (int)$stats['total_courses'] ?></div>
          <div class="stat-label">หลักสูตรของคุณ</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">🧾</div>
        <div>
          <div class="stat-num"><?= (int)$stats['total_enrollments'] ?></div>
          <div class="stat-label">การลงทะเบียนรวม</div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card">
      <form method="GET" class="filter-grid">
        <div>
          <label>ค้นหา</label>
          <input type="text" name="q" value="<?= h($q) ?>" placeholder="ชื่อ / อีเมล / ยศ. / ตำแหน่ง / สังกัด / ชื่อคอร์ส">
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
          <label>เรียงตาม</label>
          <select name="sort">
            <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>ชื่อ A-Z</option>
            <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>ชื่อ Z-A</option>
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>เพิ่มล่าสุด</option>
          </select>
        </div>

        <div>
          <button class="btn btn-primary" type="submit">ค้นหา</button>
        </div>
      </form>

      <div class="help">
        ทิป: ถ้าอยากดูรายชื่อตามคอร์สแบบชัด ๆ ให้เลือก “หลักสูตร” ก่อน จะไม่ตาลาย
      </div>
    </div>

    <!-- List -->
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>นักเรียน</th>
              <th>ติดต่อ</th>
              <th>สังกัด/ตำแหน่ง</th>
              <th>หลักสูตร</th>
              <th>จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows->num_rows > 0): ?>
              <?php while ($r = $rows->fetch_assoc()): ?>
                <?php
                $avatar = $r['avatar'] ?? '';
                $initial = mb_substr((string)$r['student_name'], 0, 1, 'UTF-8');
                ?>
                <tr>
                  <td>
                    <div class="student-cell">
                      <div class="avatar">
                        <?php if (!empty($avatar)): ?>
                          <img src="../<?= h($avatar) ?>" alt="avatar">
                        <?php else: ?>
                          <?= h($initial ?: '👤') ?>
                        <?php endif; ?>
                      </div>
                      <div>
                        <div style="font-weight:900;">
                          <?= h(($r['rank'] ?? '') . ' ' . ($r['student_name'] ?? '')) ?>
                        </div>
                        <div class="muted">ID: <?= (int)$r['student_id'] ?></div>
                      </div>
                    </div>
                  </td>

                  <td>
                    <div><?= h($r['email'] ?: '-') ?></div>
                    <div class="muted"><?= h($r['phone'] ?: '-') ?></div>
                  </td>

                  <td>
                    <div><?= h($r['position'] ?: '-') ?></div>
                    <div class="muted"><?= h($r['affiliation'] ?: '-') ?></div>
                  </td>

                  <td>
                    <span class="tag"><?= h($r['course_title'] ?: '-') ?></span>
                  </td>

                  <td>
                    <div class="row-actions">
                      <button class="btn btn-sm btn-secondary" onclick="viewStudent(<?= (int)$r['student_id'] ?>)">ดูโปรไฟล์</button>
                      <button class="btn btn-sm btn-danger"
                        onclick="removeFromCourse(<?= (int)$r['course_id'] ?>, <?= (int)$r['student_id'] ?>)">
                        เอาออกจากคอร์ส
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="5">
                  <div class="empty">
                    <div class="icon">🧑‍🎓</div>
                    <div>ยังไม่พบรายชื่อที่ตรงกับเงื่อนไข</div>
                  </div>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Add Student Modal -->
  <div class="modal" id="studentModal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>เพิ่มนักเรียนเข้าคอร์ส</h3>
        <span class="modal-close" onclick="closeStudentModal()">×</span>
      </div>

      <form id="addStudentForm" onsubmit="addStudent(event)">
        <div style="margin-bottom:12px;">
          <label>เลือกหลักสูตร *</label>
          <select name="course_id" required>
            <option value="" disabled selected>เลือกหลักสูตร</option>
            <?php
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

        <div style="margin-bottom:12px;">
          <label>Student ID หรือ Email *</label>
          <input type="text" name="student_key" required placeholder="เช่น 123 หรือ student@email.com">
          <div class="help">ระบบจะหา user role=student แล้วจับลงคอร์สให้</div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;">เพิ่มนักเรียน</button>
      </form>

      <div class="help" style="margin-top:10px;">
        ถ้าอยาก “อัปโหลดรายชื่อเป็นไฟล์” เดี๋ยวพี่ทำโหมด CSV ให้ได้เหมือนกัน (จะโหดขึ้น แต่คุ้ม)
      </div>
    </div>
  </div>

  <script>
    function openStudentModal() {
      document.getElementById('studentModal').classList.add('show');
    }

    function closeStudentModal() {
      document.getElementById('studentModal').classList.remove('show');
      document.getElementById('addStudentForm').reset();
    }

    function addStudent(e) {
      e.preventDefault();
      const formData = new FormData(e.target);

      fetch('../api/teacher_api.php?action=add_student_to_course', {
          method: 'POST',
          body: formData
        })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            alert('เพิ่มนักเรียนสำเร็จ!');
            closeStudentModal();
            location.reload();
          } else {
            alert(data.message || 'เพิ่มนักเรียนไม่สำเร็จ');
          }
        })
        .catch(err => {
          console.error(err);
          alert('เกิดข้อผิดพลาด');
        });
    }

    function removeFromCourse(courseId, studentId) {
      if (!confirm('เอานักเรียนออกจากคอร์สนี้แน่ใจนะ?')) return;

      fetch('../api/teacher_api.php?action=remove_student_from_course', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: 'course_id=' + encodeURIComponent(courseId) + '&student_id=' + encodeURIComponent(studentId)
        })
        .then(r => r.json())
        .then(data => {
          if (data.success) location.reload();
          else alert(data.message || 'เอาออกไม่สำเร็จ');
        })
        .catch(err => {
          console.error(err);
          alert('เกิดข้อผิดพลาด');
        });
    }

    function viewStudent(studentId) {
      // ถ้ามีหน้าโปรไฟล์นักเรียนฝั่งครู
      window.location.href = `student_detail.php?id=${studentId}`;
    }

    window.addEventListener('click', (event) => {
      if (event.target.classList.contains('modal')) {
        event.target.classList.remove('show');
      }
    });
  </script>
</body>

</html>