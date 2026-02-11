<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION["user"]) || $_SESSION["user"]["role"] !== "teacher") {
    header("Location: ../auth/login.php");
    exit();
}

$teacher_id = (int) $_SESSION["user"]["id"];
$course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
$test_type = $_GET['type'] ?? 'pre';

if ($course_id === 0 || !in_array($test_type, ['pre', 'post'])) {
    header("Location: courses.php");
    exit();
}

// Get Course Info
$course_stmt = $conn->prepare("SELECT title FROM courses WHERE id = ?");
$course_stmt->bind_param("i", $course_id);
$course_stmt->execute();
$course = $course_stmt->get_result()->fetch_assoc();

if (!$course) {
    header("Location: courses.php");
    exit();
}

// Get Test Info
$test_stmt = $conn->prepare("SELECT * FROM course_tests WHERE course_id = ? AND test_type = ?");
$test_stmt->bind_param("is", $course_id, $test_type);
$test_stmt->execute();
$test = $test_stmt->get_result()->fetch_assoc();

// If test doesn't exist logically yet, use defaults
if (!$test) {
    $test = [
        'id' => 0,
        'is_active' => 0,
        'time_limit_minutes' => 0,
        'shuffle_questions' => 0,
        'shuffle_answers' => 0
    ];
}

$test_label = ($test_type === 'pre') ? "แบบทดสอบก่อนเรียน (Pre-test)" : "แบบทดสอบหลังเรียน (Post-test)";
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการ
        <?= $test_label ?> -
        <?= htmlspecialchars($course['title']) ?>
    </title>
    <link href="teacher.css" rel="stylesheet">
    <style>
        .settings-card {
            background: #fff;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 24px;
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 16px;
        }

        .form-group-half {
            flex: 1;
        }

        .toggle-switch {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            cursor: pointer;
        }

        .toggle-switch input {
            transform: scale(1.5);
        }

        textarea.aiken-input {
            width: 100%;
            height: 300px;
            font-family: monospace;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            resize: vertical;
        }

        .tab-nav {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .tab-btn {
            background: none;
            border: none;
            padding: 8px 16px;
            cursor: pointer;
            font-weight: 600;
            color: #718096;
            border-bottom: 2px solid transparent;
        }

        .tab-btn.active {
            color: #3182ce;
            border-bottom-color: #3182ce;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }
    </style>
</head>

<body>
    <?php include "../components/teacher-sidebar.php"; ?>

    <div class="main-content">
        <div class="page-header">
            <div>
                <a href="course_detail.php?id=<?= $course_id ?>" class="btn btn-ghost">← กลับไปหน้าหลักสูตร</a>
                <h1>
                    <?= $test_label ?>
                </h1>
                <p>วิชา:
                    <?= htmlspecialchars($course['title']) ?>
                </p>
            </div>
            <div>
                <button onclick="saveSettings()" class="btn btn-primary">💾 บันทึกการตั้งค่า</button>
            </div>
        </div>

        <div class="tab-nav">
            <button class="tab-btn active" onclick="switchTab('settings')">⚙️ ตั้งค่า</button>
            <button class="tab-btn" onclick="switchTab('questions')">❓ คำถาม (Import)</button>
            <button class="tab-btn" onclick="switchTab('results')">📊 ผลคะแนน</button>
        </div>

        <!-- Tab 1: Settings -->
        <div id="tab-settings" class="tab-content active">
            <div class="settings-card">
                <h3>สถานะและการตั้งค่า</h3>
                <form id="settingsForm">
                    <input type="hidden" name="course_id" value="<?= $course_id ?>">
                    <input type="hidden" name="type" value="<?= $test_type ?>">

                    <div class="form-group">
                        <label class="toggle-switch">
                            <input type="checkbox" name="is_active" id="is_active" <?= $test['is_active'] ? 'checked' : '' ?>>
                            <span>เปิดใช้งานแบบทดสอบ (ให้นักเรียนเห็นและทำได้)</span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label class="form-label">เวลาที่ให้ทำ (นาที)</label>
                        <input type="number" name="time_limit" class="form-control"
                            value="<?= $test['time_limit_minutes'] ?>" placeholder="0 = ไม่จำกัดเวลา">
                        <small style="color: grey;">ใส่ 0 หากไม่ต้องการจับเวลา</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group-half">
                            <label class="toggle-switch">
                                <input type="checkbox" name="shuffle_questions" <?= $test['shuffle_questions'] ? 'checked' : '' ?>>
                                <span>สลับลำดับคำถาม</span>
                            </label>
                        </div>
                        <div class="form-group-half">
                            <label class="toggle-switch">
                                <input type="checkbox" name="shuffle_answers" <?= $test['shuffle_answers'] ? 'checked' : '' ?>>
                                <span>สลับลำดับตัวเลือก</span>
                            </label>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tab 2: Questions (Import) -->
        <div id="tab-questions" class="tab-content">
            <div class="settings-card">
                <h3>นำเข้าคำถาม (Aiken Format)</h3>
                <p class="text-sm text-gray-600 mb-4">
                    รูปแบบ: เขียนคำถามบรรทัดแรก, ตามด้วยตัวเลือก A. B. C. D., และปิดท้ายด้วย ANSWER: X<br>
                    สามารถพิมพ์ลงในช่องข้างล่าง หรืออัปโหลดไฟล์ .txt
                </p>

                <div style="margin-bottom: 15px;">
                    <label style="display:block; margin-bottom:5px; font-weight:600;">อัปโหลดไฟล์ (.txt):</label>
                    <input type="file" id="aikenFile" accept=".txt" class="form-control">
                </div>

                <div style="margin-bottom: 15px; text-align: center; color: #718096;">— หรือ —</div>

                <textarea id="aikenInput" class="aiken-input"
                    placeholder="วางเนื้อหา Aiken Format ที่นี่..."></textarea>

                <div style="margin-top: 16px;">
                    <button onclick="importAiken()" class="btn btn-primary">📥 นำเข้าคำถาม</button>
                    <span id="importStatus" style="margin-left: 10px;"></span>
                </div>
            </div>

            <div class="settings-card">
                <h3>คำถามที่มีอยู่ในระบบ</h3>
                <div style="margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                        เลือกทั้งหมด
                    </label>
                    <button id="deleteSelectedBtn" onclick="deleteSelected()" class="btn btn-danger" style="display: none;">
                        ลบที่เลือก (<span id="selectedCount">0</span>)
                    </button>
                </div>
                <div id="questionsList">
                    กำลังโหลด...
                </div>
            </div>
        </div>

        <!-- Tab 3: Results -->
        <div id="tab-results" class="tab-content">
            <div class="settings-card">
                <h3>ผลการสอบของนักเรียน</h3>
                <table style="width:100%; text-align:left; border-collapse:collapse;">
                    <thead>
                        <tr style="border-bottom:2px solid #eee;">
                            <th style="padding:10px;">นักเรียน</th>
                            <th style="padding:10px;">รหัสนักศึกษา</th>
                            <th style="padding:10px;">คะแนน</th>
                            <th style="padding:10px;">เวลาที่ส่ง</th>
                            <th style="padding:10px;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="resultsBody">
                        <!-- Loaded via JS -->
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script>
        let currentTestId = <?= $test['id'] ?>;
        const courseId = <?= $course_id ?>;
        const testType = '<?= $test_type ?>';

        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

            event.target.classList.add('active');
            document.getElementById('tab-' + tab).classList.add('active');

            if (tab === 'questions' && currentTestId > 0) {
                loadQuestions();
            }
            if (tab === 'results' && currentTestId > 0) {
                loadResults();
            }
        }

        function saveSettings() {
            const form = document.getElementById('settingsForm');
            const formData = new FormData(form);
            // Handle checkboxes manually if unchecked (default HTML behavior sends nothing)
            if (!document.getElementById('is_active').checked) formData.set('is_active', 0);
            else formData.set('is_active', 1);

            // Same for shuffles if needed, but logic usually checks isset or == 'on'. 
            // Better to be explicit:
            formData.set('shuffle_questions', form.querySelector('[name=shuffle_questions]').checked ? 1 : 0);
            formData.set('shuffle_answers', form.querySelector('[name=shuffle_answers]').checked ? 1 : 0);

            fetch('../api/teacher_api.php?action=save_test_settings', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        currentTestId = data.test_id;
                        alert('บันทึกการตั้งค่าแล้ว');
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
        }

        function importAiken() {
            if (currentTestId === 0) {
                alert('โปรดบันทึกการตั้งค่า (กด "บันทึกการตั้งค่า") เพื่อสร้างแบบทดสอบก่อนนำเข้าคำถาม');
                return;
            }

            const fileInput = document.getElementById('aikenFile');
            const textInput = document.getElementById('aikenInput');

            const file = fileInput.files[0];
            const text = textInput.value;

            if (!file && !text.trim()) {
                alert('กรุณาเลือกไฟล์ หรือป้อนข้อความ');
                return;
            }

            const fd = new FormData();
            fd.append('test_id', currentTestId);
            if (file) {
                fd.append('aiken_file', file);
            } else {
                fd.append('aiken_text', text);
            }

            document.getElementById('importStatus').innerText = 'กำลังนำเข้า...';

            fetch('../api/teacher_api.php?action=import_aiken', {
                method: 'POST',
                body: fd
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert(`นำเข้าสำเร็จ ${data.imported_count} ข้อ!`);
                        textInput.value = '';
                        fileInput.value = '';
                        document.getElementById('importStatus').innerText = '';
                        loadQuestions();
                    } else {
                        alert('Error: ' + data.message);
                        document.getElementById('importStatus').innerText = 'เกิดข้อผิดพลาด';
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Network Error');
                });
        }

        function loadQuestions() {
            fetch(`../api/teacher_api.php?action=get_test_questions&test_id=${currentTestId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const list = document.getElementById('questionsList');
                        if (data.questions.length === 0) {
                            list.innerHTML = '<p style="color:#aaa;">ยังไม่มีคำถาม</p>';
                            document.getElementById('selectAll').checked = false;
                            checkSelection();
                            return;
                        }

                        // Reset header checkbox
                        document.getElementById('selectAll').checked = false;
                        checkSelection();

                        let html = '';
                        data.questions.forEach((q, idx) => {
                            html += `
                        <div style="border:1px solid #eee; padding:12px; margin-bottom:8px; border-radius:4px; position:relative;">
                             <div style="position:absolute; top:10px; right:10px; display:flex; gap:10px;">
                                <input type="checkbox" class="q-checkbox" value="${q.id}" onchange="checkSelection()" style="transform: scale(1.2);">
                                <button onclick="deleteQuestion(${q.id})" style="color:red; border:none; background:none; cursor:pointer; font-size:16px;" title="Delete">🗑️</button>
                             </div>
                            <strong>${idx + 1}. ${q.question_text.replace(/\n/g, '<br>')}</strong>
                            <ul style="margin-top:8px; padding-left:20px;">
                        `;
                            q.answers.forEach((a, aIdx) => {
                                const letter = String.fromCharCode(65 + aIdx);
                                const style = a.is_correct == 1 ? 'color:green; font-weight:bold;' : '';
                                html += `<li style="${style}">${letter}. ${a.answer_text}</li>`;
                            });
                            html += `</ul></div>`;
                        });
                        list.innerHTML = html;
                    }
                });
        }

        function deleteQuestion(id) {
            if (!confirm('ต้องการลบคำถามข้อนี้?')) return;

            const fd = new FormData();
            fd.append('question_id', id);

            fetch('../api/teacher_api.php?action=delete_test_question', {
                method: 'POST',
                body: fd
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        loadQuestions();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Connection Error');
                });
        }

        function toggleSelectAll() {
            const isChecked = document.getElementById('selectAll').checked;
            document.querySelectorAll('.q-checkbox').forEach(cb => cb.checked = isChecked);
            checkSelection();
        }

        function checkSelection() {
            const count = document.querySelectorAll('.q-checkbox:checked').length;
            const btn = document.getElementById('deleteSelectedBtn');
            const countSpan = document.getElementById('selectedCount');
            
            if (count > 0) {
                btn.style.display = 'inline-block';
                countSpan.innerText = count;
            } else {
                btn.style.display = 'none';
            }
        }

        function deleteSelected() {
            const checkboxes = document.querySelectorAll('.q-checkbox:checked');
            if (checkboxes.length === 0) return;

            if (!confirm(`ต้องการลบคำถามที่เลือก ${checkboxes.length} ข้อ?`)) return;

            const ids = Array.from(checkboxes).map(cb => cb.value);
            
            const fd = new FormData();
            ids.forEach(id => fd.append('question_ids[]', id)); // Send as array

            fetch('../api/teacher_api.php?action=delete_bulk_test_questions', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Reset select all
                    document.getElementById('selectAll').checked = false;
                    document.getElementById('deleteSelectedBtn').style.display = 'none';
                    loadQuestions();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => {
                console.error(err);
                alert('Connection Error');
            });
        }

        function loadResults() {
            console.log('Loading results for test:', currentTestId);
            fetch(`../api/teacher_api.php?action=get_test_results&test_id=${currentTestId}`)
                .then(r => r.json())
                .then(data => {
                    console.log('Results response:', data);
                    if (data.success) {
                        const tbody = document.getElementById('resultsBody');
                        if (data.results.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px;">ยังไม่มีผู้ส่งคำตอบ (No results found)</td></tr>';
                            return;
                        }

                        let html = '';
                        data.results.forEach(r => {
                            html += `
                        <tr style="border-bottom:1px solid #eee;">
                            <td style="padding:10px;">${r.student_name}</td>
                            <td style="padding:10px;">${r.student_code || '-'}</td>
                            <td style="padding:10px; font-weight:bold;">${r.score} / ${r.total_points}</td>
                            <td style="padding:10px;">${new Date(r.submit_time).toLocaleString('th-TH')}</td>
                            <td style="padding:10px;">
                                <button onclick="deleteAttempt(${r.id})" class="btn btn-secondary btn-sm" style="color:red; border-color:red; padding:4px 8px; font-size:12px;">Reset</button>
                            </td>
                        </tr>
                        `;
                        });
                        tbody.innerHTML = html;
                    }
                });
        }

        function deleteAttempt(id) {
            if (!confirm('ต้องการล้างผลการสอบของนักเรียนคนนี้? นักเรียนจะต้องทำแบบทดสอบใหม่')) return;

            const fd = new FormData();
            fd.append('attempt_id', id);

            fetch('../api/teacher_api.php?action=delete_student_attempt', {
                method: 'POST',
                body: fd
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('รีเซ็ตเรียบร้อย');
                        loadResults();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
        }

        // Init load if existing test
        if (currentTestId > 0) {
            // Pre-load logic if we want, but better waiting for user tab switch or just lazy
        }
    </script>
</body>

</html>