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
        WHERE a.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $assignment_id);
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
                <button id="chat-btn-<?= $assignment_id ?>" onclick="openChatModal(<?= $assignment_id ?>, '<?= h($assignment['title']) ?>')" class="btn btn-secondary">
                    💬 แชท
                </button>
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
                            <th>คะแนน</th>

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
                                        <a href="../<?= h($std['file_path']) ?>" target="_blank" class="btn-ghost" style="padding: 4px 8px; font-size: 12px;">
                                            📂 ดูไฟล์
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($std['grade'] !== null): ?>
                                        <span style="font-weight:bold; color:var(--success);"><?= $std['grade'] ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--gray);">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($students_data)): ?>
                            <tr>
                                <td colspan="5" style="text-align:center; padding: 30px; color:var(--gray);">
                                    ยังไม่มีนักเรียนในวิชานี้
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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
                if (event.target.id === 'chatModal') {
                    stopPolling();
                    loadUnreadCounts();
                }
            }
        });

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
                        lastMessageId = data.messages[data.messages.length - 1].id;
                        
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
                        list.scrollTop = list.scrollHeight;
                    } else if (isFullLoad) {
                        list.innerHTML = '<div style="text-align:center; padding: 20px; color:#a0aec0;">ยังไม่มีข้อความ</div>';
                    }
                    
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
                    loadMessages(false);
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

        function loadUnreadCounts() {
            fetch('../api/assignment_chat_api.php?action=get_unread_counts')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const count = data.counts[currentChatId] || 0;
                    const btn = document.getElementById(`chat-btn-${currentChatId}`);
                    if (btn) {
                        let badge = btn.querySelector('.chat-badge');
                        if (count > 0) {
                            if (!badge) {
                                badge = document.createElement('span');
                                badge.className = 'chat-badge';
                                btn.appendChild(badge);
                            }
                            badge.textContent = count;
                        } else if (badge) {
                            badge.remove();
                        }
                    }
                }
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadUnreadCounts();
            setInterval(loadUnreadCounts, 10000);
        });
    </script>
</body>
</html>
