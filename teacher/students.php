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
$courses_stmt = $conn->prepare("SELECT id, title FROM courses ORDER BY title ASC");
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
    INNER JOIN courses c ON cs.course_id = c.id) AS total_students,
 (SELECT COUNT(*)
    FROM course_students cs
    INNER JOIN courses c ON cs.course_id = c.id) AS total_enrollments,
 (SELECT COUNT(*) FROM courses) AS total_courses
";
$stats_stmt = $conn->prepare($stats_sql);
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
$where = "WHERE u.role = 'student'";
$params = [];
$types = "";

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
LIMIT 2000
";
$stmt = $conn->prepare($sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
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
      <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0; border-bottom:none; padding-bottom:10px;">
        <div>
          <h2>รายชื่อนักเรียน</h2>
          <div class="muted">ทั้งหมด <span id="totalStudentsCount"><?= $rows->num_rows ?></span> คน</div>
        </div>
        <div style="display:flex; align-items:center;">
          <label style="font-size:14px; margin-right:8px; font-weight:600;">แสดง</label>
          <select id="pageSize" class="form-control" style="width:80px; padding:6px 10px;" onchange="changePageSize()">
            <option value="5">5</option>
            <option value="10">10</option>
            <option value="20">20</option>
            <option value="50">50</option>
          </select>
          <label style="font-size:14px; margin-left:8px; font-weight:600;">แถว</label>
        </div>
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
                <tr class="student-data-row">
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
      
      <!-- Pagination Controls -->
      <div id="paginationControls" style="display:flex; justify-content:space-between; align-items:center; padding-top:15px; border-top: 1px solid #edf2f7; margin-top: 15px;">
        <div id="paginationSummary" style="font-size:14px; color:var(--gray); font-weight:500;"></div>
        <div id="paginationButtons" style="display:flex; gap:5px;"></div>
      </div>
      
    </div>
  </div>

  <!-- Add Student Modal -->
  <div class="modal" id="studentModal">
    <div class="modal-content" style="max-width: 800px; width: 95%;">
      <div class="modal-header">
        <h3>เพิ่มนักเรียนเข้าคอร์ส</h3>
        <span class="modal-close" onclick="closeStudentModal()">×</span>
      </div>

      <div style="margin-bottom: 20px;">
        <label style="display:block; margin-bottom:8px; font-weight:600;">1. เลือกหลักสูตรที่ต้องการเพิ่มนักเรียน *</label>
        <select id="bulk_enroll_course_id" class="form-control" style="width:100%; padding:10px; border-radius:8px; border:1px solid #ddd;">
          <option value="" disabled selected>-- เลือกหลักสูตร --</option>
          <?php
          mysqli_data_seek($courses_rs, 0);
          while ($c = $courses_rs->fetch_assoc()):
          ?>
            <option value="<?= (int)$c['id'] ?>"><?= h($c['title']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <div id="enrollment_controls" style="display:none;">
        <div style="margin-bottom: 15px;">
            <label style="display:block; margin-bottom:8px; font-weight:600;">2. ค้นหาและเลือกนักเรียน</label>
            <div style="position:relative;">
                <input type="text" id="candidate_search" placeholder="ค้นหาด้วยชื่อ, รหัส, หรืออีเมล..." 
                       style="width:100%; padding:10px 40px 10px 12px; border-radius:8px; border:1px solid #ddd;"
                       onkeyup="searchCandidates(this.value)">
                <span style="position:absolute; right:12px; top:50%; transform:translateY(-50%); color:#aaa;">🔍</span>
            </div>
        </div>

        <div class="table-wrap" style="max-height: 400px; overflow-y: auto; border: 1px solid #eee; border-radius: 8px;">
          <table style="margin-bottom:0;">
            <thead style="position: sticky; top: 0; background: #fff; z-index: 10; box-shadow: 0 1px 0 #eee;">
              <tr>
                <th style="width: 40px; text-align: center;">
                    <input type="checkbox" id="selectAllStudents" onclick="toggleAllStudents(this)">
                </th>
                <th>ชื่อ-นามสกุล</th>
                <th>อีเมล</th>
              </tr>
            </thead>
            <tbody id="candidate_list">
                <!-- Students will be rendered here -->
            </tbody>
          </table>
        </div>

        <div id="no_students_msg" style="display:none; text-align:center; padding:30px; color:#999;">
            <div style="font-size:40px; margin-bottom:10px;">🧑‍🎓</div>
            <div>ไม่มีรายชื่อนักเรียนที่สามารถเพิ่มได้</div>
        </div>

        <div style="margin-top: 25px; display:flex; gap:12px; justify-content: flex-end;">
            <button class="btn btn-secondary" onclick="closeStudentModal()" style="min-width:120px;">ยกเลิก</button>
            <button class="btn btn-primary" onclick="addSelectedStudents()" id="submitEnrollBtn" style="min-width:160px;">เพิ่มนักเรียนที่เลือก</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    function openStudentModal() {
      const modal = document.getElementById('studentModal');
      const courseSelect = document.getElementById('bulk_enroll_course_id');
      const controls = document.getElementById('enrollment_controls');
      
      // Reset
      courseSelect.value = '';
      controls.style.display = 'none';
      document.getElementById('candidate_list').innerHTML = '';
      document.getElementById('candidate_search').value = '';
      document.getElementById('selectAllStudents').checked = false;
      
      // Handle course change
      courseSelect.onchange = function() {
          if (this.value) {
              controls.style.display = 'block';
              loadAvailableStudents(this.value);
          } else {
              controls.style.display = 'none';
          }
      };

      modal.classList.add('show');
    }

    function closeStudentModal() {
      document.getElementById('studentModal').classList.remove('show');
    }

    let searchTimeout;
    function searchCandidates(q) {
        clearTimeout(searchTimeout);
        const courseId = document.getElementById('bulk_enroll_course_id').value;
        
        searchTimeout = setTimeout(() => {
            loadAvailableStudents(courseId, q);
        }, 300);
    }

    function loadAvailableStudents(courseId, q = '') {
        const listDiv = document.getElementById('candidate_list');
        const noStudentsMsg = document.getElementById('no_students_msg');
        const tableWrap = listDiv.closest('.table-wrap');
        const submitBtn = document.getElementById('submitEnrollBtn');
        
        listDiv.innerHTML = '<tr><td colspan="3" style="text-align:center; padding:20px;">กำลังโหลด...</td></tr>';
        noStudentsMsg.style.display = 'none';
        tableWrap.style.display = 'block';
        submitBtn.disabled = true;

        fetch(`../api/teacher_api.php?action=search_candidates&course_id=${courseId}&q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    renderCandidates(data.students);
                } else {
                    listDiv.innerHTML = `<tr><td colspan="4" style="text-align:center; padding:20px; color:red;">${data.message}</td></tr>`;
                }
            })
            .catch(err => {
                listDiv.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px; color:red;">เกิดข้อผิดพลาดในการโหลดข้อมูล</td></tr>';
            });
    }

    function renderCandidates(students) {
        const listDiv = document.getElementById('candidate_list');
        const noStudentsMsg = document.getElementById('no_students_msg');
        const tableWrap = listDiv.closest('.table-wrap');
        const submitBtn = document.getElementById('submitEnrollBtn');
        
        if (students.length === 0) {
            listDiv.innerHTML = '';
            tableWrap.style.display = 'none';
            noStudentsMsg.style.display = 'block';
            submitBtn.disabled = true;
            return;
        }

        tableWrap.style.display = 'block';
        noStudentsMsg.style.display = 'none';
        submitBtn.disabled = false;

        let html = '';
        students.forEach(s => {
            html += `
                <tr onclick="toggleCheckbox(this)" style="cursor:pointer;">
                    <td style="text-align:center;">
                        <input type="checkbox" class="student-select-cb" value="${s.id}" onclick="event.stopPropagation()">
                    </td>
                    <td>
                        <div style="font-weight:600;">${s.rank || ''} ${s.name}</div>
                    </td>
                    <td class="muted">${s.email}</td>
                </tr>
            `;
        });
        listDiv.innerHTML = html;
        document.getElementById('selectAllStudents').checked = false;
    }

    function toggleCheckbox(row) {
        const cb = row.querySelector('.student-select-cb');
        cb.checked = !cb.checked;
    }

    function toggleAllStudents(master) {
        const checkboxes = document.querySelectorAll('.student-select-cb');
        checkboxes.forEach(cb => cb.checked = master.checked);
    }

    function addSelectedStudents() {
        const courseId = document.getElementById('bulk_enroll_course_id').value;
        const checkboxes = document.querySelectorAll('.student-select-cb:checked');
        const ids = Array.from(checkboxes).map(cb => cb.value);

        if (ids.length === 0) {
            alert('กรุณาเลือกนักเรียนอย่างน้อย 1 คน');
            return;
        }

        const btn = document.getElementById('submitEnrollBtn');
        const originalText = btn.innerText;
        btn.disabled = true;
        btn.innerText = 'กำลังบันทึก...';

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
                btn.disabled = false;
                btn.innerText = originalText;
            }
        })
        .catch(err => {
            alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
            btn.disabled = false;
            btn.innerText = originalText;
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

    window.addEventListener('click', (event) => {
      if (event.target.classList.contains('modal')) {
        event.target.classList.remove('show');
      }
    });

    // --- Client-Side Pagination Logic ---
    let currentPage = 1;
    let pageSize = 5;
    let allStudentRows = [];

    document.addEventListener('DOMContentLoaded', () => {
      allStudentRows = Array.from(document.querySelectorAll('.student-data-row'));
      const total = allStudentRows.length;
      if (total > 0) {
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

    function renderTable() {
      const total = allStudentRows.length;
      const totalPages = Math.ceil(total / pageSize) || 1;
      
      if (currentPage > totalPages) currentPage = totalPages;
      if (currentPage < 1) currentPage = 1;

      const startIdx = (currentPage - 1) * pageSize;
      const endIdx = Math.min(startIdx + pageSize, total);

      allStudentRows.forEach((row, index) => {
        if (index >= startIdx && index < endIdx) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });

      // Update summary
      document.getElementById('paginationSummary').innerText = `แสดง ${startIdx + 1}-${endIdx} จากทั้งหมด ${total} คน`;

      // Update buttons
      let btnHtml = '';
      btnHtml += `<button class="btn btn-sm btn-secondary" onclick="goToPage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>ก่อนหน้า</button>`;
      
      for (let i = 1; i <= totalPages; i++) {
        // Show boundary pages and current page +/- 1
        if (totalPages <= 7 || i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
          btnHtml += `<button class="btn btn-sm ${i === currentPage ? 'btn-primary' : 'btn-secondary'}" onclick="goToPage(${i})">${i}</button>`;
        } else if (i === currentPage - 2 || i === currentPage + 2) {
          btnHtml += `<span style="padding: 5px; color: var(--gray);">...</span>`;
        }
      }

      btnHtml += `<button class="btn btn-sm btn-secondary" onclick="goToPage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>ถัดไป</button>`;
      
      // Cleanup multiple ellipsis
      btnHtml = btnHtml.replace(/(<span[^>]*>\.\.\.<\/span>){2,}/g, '<span style="padding: 5px; color: var(--gray);">...</span>');

      document.getElementById('paginationButtons').innerHTML = btnHtml;
    }
  </script>
</body>

</html>