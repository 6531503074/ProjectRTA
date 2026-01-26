<?php
session_start();
include "../config/db.php";
date_default_timezone_set('Asia/Bangkok');

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
function th_date($datetime)
{
    if (!$datetime) return '-';
    $timestamp = strtotime($datetime);
    if (!$timestamp) return h($datetime);
    
    $months_th = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $day = date('j', $timestamp);
    $month = $months_th[(int)date('n', $timestamp)];
    $year = (int)date('Y', $timestamp) + 543;
    $time = date('H:i', $timestamp);
    
    // If time is 00:00:00 (legacy dates), maybe hide it? 
    // But now we use datetime-local so time is relevant.
    return "$day $month $year $time";
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

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
if (!in_array($limit, [5, 10, 20, 50])) $limit = 5;
$offset = ($page - 1) * $limit;

// Count total for pagination
$count_sql = "SELECT COUNT(*) as total FROM assignments a INNER JOIN courses c ON a.course_id = c.id {$where}";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

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
LIMIT ? OFFSET ?
";

// Add limit/offset to params
$params[] = $limit;
$params[] = $offset;
$types .= "ii";


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
    <link href="teacher.css" rel="stylesheet">
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
                    <div class="stat-label">รอให้คะแนน</div>
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
            <div class="card-header" style="justify-content: space-between; align-items: center; display: flex;">
                <h2 style="font-size: 1.25rem;">รายการงาน</h2>
                <form method="GET" style="margin:0;">
                    <!-- Preserve other filters -->
                    <input type="hidden" name="q" value="<?= h($q) ?>">
                    <input type="hidden" name="course_id" value="<?= h($course_id) ?>">
                    <input type="hidden" name="status" value="<?= h($status) ?>">
                    <input type="hidden" name="sort" value="<?= h($sort) ?>">
                    <input type="hidden" name="page" value="1"> <!-- Reset page on limit change -->
                    
                    <select name="limit" onchange="this.form.submit()" style="padding: 4px 8px; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 14px;">
                        <option value="5" <?= $limit == 5 ? 'selected' : '' ?>>แสดง 5 แถว</option>
                        <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>แสดง 10 แถว</option>
                        <option value="20" <?= $limit == 20 ? 'selected' : '' ?>>แสดง 20 แถว</option>
                        <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>แสดง 50 แถว</option>
                    </select>
                </form>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>งาน</th>
                            <th>หลักสูตร</th>
                            <th>กำหนดส่ง</th>
                            <th>ความคืบหน้า</th>
                            <th>รอให้คะแนน</th>
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
                                    $due_dt = new DateTime($due); // Parse full datetime
                                    $now = new DateTime();
                                    
                                    if ($due_dt < $now) {
                                        $dueTag = 'overdue';
                                        $dueText = 'หมดเขตแล้ว';
                                    } else {
                                        // ใกล้ถึง: ภายใน 3 วัน
                                        $diffDays = (int)$now->diff($due_dt)->format('%a');
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
                                            <button id="chat-btn-<?= (int)$a['id'] ?>" class="btn btn-sm btn-ghost" onclick="openChatModal(<?= (int)$a['id'] ?>, '<?= h($a['title']) ?>')">💬 แชท</button>
                                            
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">🗂️</div>
                                        <div>ยังไม่เจองานที่ตรงกับเงื่อนไข</div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div style="padding: 20px; display: flex; justify-content: flex-end; gap: 8px; border-top: 1px solid #edf2f7;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&limit=<?= $limit ?>&q=<?= h($q) ?>&course_id=<?= h($course_id) ?>&status=<?= h($status) ?>&sort=<?= h($sort) ?>" 
                           class="btn btn-sm btn-ghost" style="color:var(--gray);">
                            &lt;
                        </a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?>&limit=<?= $limit ?>&q=<?= h($q) ?>&course_id=<?= h($course_id) ?>&status=<?= h($status) ?>&sort=<?= h($sort) ?>"
                           class="btn btn-sm <?= $i == $page ? 'btn-primary' : 'btn-ghost' ?>" 
                           style="<?= $i != $page ? 'color:var(--dark);' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&limit=<?= $limit ?>&q=<?= h($q) ?>&course_id=<?= h($course_id) ?>&status=<?= h($status) ?>&sort=<?= h($sort) ?>" 
                           class="btn btn-sm btn-ghost" style="color:var(--gray);">
                            &gt;
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
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
                        <input type="datetime-local" name="due_date" class="form-control" required>
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

    <!-- Chat Modal -->
    <div class="modal" id="chatModal">
        <div class="modal-content" style="width: 500px; height: 600px; display: flex; flex-direction: column;">
            <div class="modal-header">
                <h3 id="chatTitle">Chat</h3>
                <span class="modal-close" onclick="closeChatModal()">×</span>
            </div>
            
            <div class="chat-container">
                <div class="messages-list" id="chatMessages">
                    <!-- Messages will load here -->
                    <div style="text-align:center; padding: 20px; color:#a0aec0;">Loading...</div>
                </div>
                
                <div class="chat-input-area">
                    <input type="text" id="chatInput" placeholder="พิมพ์ข้อความ..." autocomplete="off">
                    <button class="btn btn-primary" onclick="sendMessage()">ส่ง</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ... (existing assignment functions) ...
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
            if (!confirm('ลบงานนี้แน่ใจนะ?')) return;

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

        // --- Chat Logic ---
        let currentChatId = null;
        let chatPollInterval = null;
        const currentUserId = <?= (int)$teacher_id ?>; // Teacher's ID

        function openChatModal(id, title) {
            // Optimistic update: Remove badge immediately
            const btn = document.getElementById(`chat-btn-${id}`);
            if (btn) {
                const badge = btn.querySelector('.chat-badge');
                if (badge) badge.remove();
            }

            currentChatId = id;
            document.getElementById('chatTitle').textContent = '💬 ' + title;
            document.getElementById('chatModal').classList.add('show');
            document.getElementById('chatMessages').innerHTML = '<div style="text-align:center; padding: 20px; color:#a0aec0;">กำลังโหลด...</div>';
            
            loadMessages(true);
            startPolling();
        }
        
        function closeChatModal() {
            document.getElementById('chatModal').classList.remove('show');
            stopPolling();
            currentChatId = null;
            loadUnreadCounts(); // Refresh badges
        }
        
        function startPolling() {
            if (chatPollInterval) clearInterval(chatPollInterval);
            chatPollInterval = setInterval(() => loadMessages(false), 3000);
        }
        
        function stopPolling() {
            if (chatPollInterval) clearInterval(chatPollInterval);
        }
        
        let lastMessageId = 0;
        
        function loadMessages(isFullLoad) {
            if (!currentChatId) return;
            
            const lastId = isFullLoad ? 0 : lastMessageId;
            
            fetch(`../api/assignment_chat_api.php?action=get_messages&assignment_id=${currentChatId}&last_id=${lastId}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const list = document.getElementById('chatMessages');
                    
                    if (isFullLoad) {
                        list.innerHTML = '';
                        lastMessageId = 0;
                    }
                    
                    if (data.messages.length > 0) {
                        if (data.messages.length > 0) {
                           lastMessageId = data.messages[data.messages.length - 1].id;
                        }
                        
                        data.messages.forEach(msg => {
                            const isMe = (parseInt(msg.user_id) === currentUserId);
                            const div = document.createElement('div');
                            div.className = `chat-message ${isMe ? 'me' : 'other'}`;
                            
                            // Format timestamp
                            const date = new Date(msg.created_at);
                            const timeStr = date.toLocaleTimeString('th-TH', {hour:'2-digit', minute:'2-digit'});
                            
                            div.innerHTML = `
                                <div class="msg-header">${isMe ? 'คุณ' : msg.name}</div>
                                <div>${escapeHtml(msg.message)}</div>
                                <div class="msg-time">${timeStr}</div>
                            `;
                            list.appendChild(div);
                        });
                        
                        // Scroll to bottom
                        if (isFullLoad || data.messages.length > 0) {
                            list.scrollTop = list.scrollHeight;
                        }
                        } else if (isFullLoad) {
                        list.innerHTML = '<div style="text-align:center; padding: 20px; color:#a0aec0;">ยังไม่มีข้อความ</div>';
                    }
                    
                    // Refresh badges immediately to clear unread count for this item
                    loadUnreadCounts();
                }
            })
            .catch(err => console.error(err));
        }
        
        function sendMessage() {
            const input = document.getElementById('chatInput');
            const msg = input.value.trim();
            if (!msg || !currentChatId) return;
            
            fetch('../api/assignment_chat_api.php?action=send_message', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    assignment_id: currentChatId,
                    message: msg
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    input.value = '';
                    loadMessages(false); // Load immediately
                } else {
                    alert('ส่งข้อความไม่สำเร็จ: ' + data.message);
                }
            });
        }
        
        document.getElementById('chatInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') sendMessage();
        });

        function escapeHtml(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        // close modal when click outside
        window.addEventListener('click', (event) => {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
                if (event.target.id === 'chatModal') {
                    stopPolling();
                    loadUnreadCounts();
                }
            }
        });

        function loadUnreadCounts() {
            fetch('../api/assignment_chat_api.php?action=get_unread_counts')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    for (const [id, count] of Object.entries(data.counts)) {
                        const btn = document.getElementById(`chat-btn-${id}`);
                        if (btn) {
                            let badge = btn.querySelector('.chat-badge');
                            if (count > 0) {
                                if (!badge) {
                                    badge = document.createElement('span');
                                    badge.className = 'chat-badge';
                                    btn.appendChild(badge);
                                }
                                badge.textContent = count;
                            } else {
                                if (badge) badge.remove();
                            }
                        }
                    }
                }
            })
            .catch(e => console.error(e));
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadUnreadCounts();
            setInterval(loadUnreadCounts, 10000); // Check every 10s
        });
    </script>
</body>

</html>