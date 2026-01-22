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
    <link href="teacher.css" rel="stylesheet">
</head>

<body>
  <?php include "../components/teacher-sidebar.php"; ?>

  <div class="main-content">
    <div class="page-header">
      <div>
        <h1>นักเรียน</h1>
      </div>
      <div class="actions-row">
        <button class="btn btn-primary" onclick="openStudentModal()">➕ เพิ่มนักเรียนเข้าคอร์ส</button>
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
    </div>

    <!-- List -->
    <div class="card">
      <div class="card-header" style="margin-bottom:0; border-bottom:none; padding-bottom:10px;">
        <h2>รายชื่อนักเรียน</h2>
        <div class="muted">ทั้งหมด <?= $rows->num_rows ?> คน</div>
      </div>
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
                  <div class="empty-state">
                    <div class="empty-state-icon">🧑‍🎓</div>
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

      <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">

      <div style="margin-bottom:12px;">
          <h4 style="margin-bottom:10px;">หรือ เพิ่มยกชั้นปี (Bulk Add)</h4>
          <form id="addStudentLevelForm" onsubmit="addStudentByLevel(event)">
            <input type="hidden" name="course_id_level" id="course_id_level_input">
            
            <div style="display:flex; gap:8px;">
                <select name="student_level" class="form-control" required style="flex:1;">
                    <option value="" disabled selected>เลือกระดับชั้นนักเรียน</option>
                    <option value="1">🌱 ขั้นเริ่มต้น</option>
                    <option value="2">🔧 ขั้นกลาง</option>
                    <option value="3">🚀 ขั้นสูง</option>
                </select>
                <button type="submit" class="btn btn-secondary" style="white-space:nowrap;">เพิ่มทั้งระดับ</button>
            </div>
          </form>
      </div>


    </div>
  </div>

  <script>
    function openStudentModal() {
      // Sync course selection for bulk form
      const courseSelect = document.querySelector('#addStudentForm select[name="course_id"]');
      const levelCourseInput = document.getElementById('course_id_level_input');
      
      // Update hidden input when main select changes
      courseSelect.addEventListener('change', function() {
          levelCourseInput.value = this.value;
      });
      // Init
      levelCourseInput.value = courseSelect.value;
      
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

    function addStudentByLevel(e) {
      e.preventDefault();
      const courseId = document.getElementById('course_id_level_input').value;
      const level = e.target.student_level.value;

      if(!courseId) {
          alert('กรุณาเลือกหลักสูตรด้านบนก่อน');
          return;
      }

      if(!confirm(`ยืนยันการเพิ่มนักเรียนระดับ "${level}" ทุกคนเข้าสู่คอร์สนี้?`)) return;

      const formData = new FormData();
      formData.append('course_id', courseId);
      formData.append('level', level);

      fetch('../api/teacher_api.php?action=add_students_by_level', {
          method: 'POST',
          body: formData
        })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            alert(`เพิ่มนักเรียนจำนวน ${data.added_count} คนเรียบร้อยแล้ว!`);
            closeStudentModal();
            location.reload();
          } else {
            alert(data.message || 'เพิ่มไม่สำเร็จ');
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

    // Multi-Select Logic
    let searchTimeout;
    function searchCandidates(q) {
        clearTimeout(searchTimeout);
        const courseId = document.getElementById('course_id_level_input').value; // Borrow input from bulk add
        const listDiv = document.getElementById('candidate_list');
        
        if (!courseId) {
            listDiv.innerHTML = '<div style="text-align:center; padding:10px; color:red;">กรุณาเลือกหลักสูตรด้านบนก่อน</div>';
            return;
        }

        if (q.length < 2) {
            listDiv.innerHTML = '<div style="text-align:center; padding:10px; color:#aaa;">พิมพ์รายชื่ออย่างน้อย 2 ตัวอักษร</div>';
            return;
        }

        searchTimeout = setTimeout(() => {
            fetch(`../api/teacher_api.php?action=search_candidates&course_id=${courseId}&q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        renderCandidates(data.students);
                    }
                });
        }, 300);
    }

    function renderCandidates(students) {
        const listDiv = document.getElementById('candidate_list');
        if (students.length === 0) {
            listDiv.innerHTML = '<div style="text-align:center; padding:10px; color:#aaa;">ไม่พบนักเรียน (หรืออยู่ในคอร์สแล้ว)</div>';
            return;
        }

        let html = '';
        students.forEach(s => {
            html += `
                <label style="display:flex; align-items:center; padding:5px; border-bottom:1px solid #f0f0f0; cursor:pointer;">
                    <input type="checkbox" class="student-select-cb" value="${s.id}" style="margin-right:10px;">
                    <div style="flex:1;">
                        <div style="font-weight:600; font-size:13px;">${s.name} (${s.rank || ''})</div>
                        <div style="font-size:11px; color:#888;">${s.email}</div>
                    </div>
                </label>
            `;
        });
        listDiv.innerHTML = html;
    }

    function addSelectedStudents() {
        const courseId = document.getElementById('course_id_level_input').value;
        const checkboxes = document.querySelectorAll('.student-select-cb:checked');
        const ids = Array.from(checkboxes).map(cb => cb.value);

        if (ids.length === 0) {
            alert('กรุณาเลือกนักเรียนอย่างน้อย 1 คน');
            return;
        }

        if(!confirm(`ยืนยันการเพิ่มนักเรียน ${ids.length} คน?`)) return;

        const formData = new FormData();
        formData.append('course_id', courseId);
        ids.forEach(id => formData.append('student_ids[]', id));

        fetch('../api/teacher_api.php?action=add_students_multiselect', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(`เพิ่มนักเรียนสำเร็จ ${data.added_count} คน`);
                closeStudentModal();
                location.reload();
            } else {
                alert(data.message || 'เพิ่มไม่สำเร็จ');
            }
        });
    }

    window.addEventListener('click', (event) => {
      if (event.target.classList.contains('modal')) {
        event.target.classList.remove('show');
      }
    });
  </script>
</body>

</html>