<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION["user"]) || $_SESSION["user"]["role"] !== "student") {
    header("Location: ../auth/login.php");
    exit();
}

$user = $_SESSION["user"];
$student_id = $user["id"];
$test_id = isset($_GET['test_id']) ? intval($_GET['test_id']) : 0;

if ($test_id <= 0) {
    header("Location: dashboard.php");
    exit();
}

// Check if already taken
$chk = $conn->prepare("SELECT id FROM student_test_attempts WHERE test_id = ? AND student_id = ?");
$chk->bind_param("ii", $test_id, $student_id);
$chk->execute();
if ($chk->get_result()->num_rows > 0) {
    // Already taken, redirect to dashboard or result
    // Since result page is simpler, maybe just redirect to dashboard with alert?
    // Or a simple "You completed this test" page.
    // Let's make a simple result page or just show message here.
    echo "<script>alert('คุณทำแบบทดสอบนี้ไปแล้ว'); window.location.href='course-dashboard.php';</script>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ทำแบบทดสอบ - CyberLearn</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #edf2f7;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        .timer {
            font-size: 20px;
            font-weight: bold;
            color: #e53e3e;
        }

        .question-card {
            margin-bottom: 24px;
            padding: 20px;
            border: 1px solid #edf2f7;
            border-radius: 8px;
        }

        .question-text {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #2d3748;
            white-space: pre-wrap;
        }

        .options {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .option-label {
            display: flex;
            align-items: start;
            gap: 10px;
            cursor: pointer;
            padding: 8px;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .option-label:hover {
            background: #f7fafc;
        }

        .option-label input {
            margin-top: 4px;
        }

        .btn-submit {
            background: #48bb78;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            margin-top: 20px;
        }

        .btn-submit:hover {
            background: #38a169;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 100;
        }
    </style>
</head>

<body>

    <div id="loading" class="loading-overlay">
        <h2>กำลังโหลดข้อสอบ...</h2>
    </div>

    <!-- Alert Warning -->
    <div style="background: #fff5f5; border-left: 4px solid #e53e3e; padding: 15px; margin-bottom: 20px; color: #c53030; max-width: 800px; margin: 20px auto 0;">
        <strong>คำเตือน:</strong> ห้ามออกจากหน้านี้ หรือสลับ Tab มิเช่นนั้นระบบจะส่งคำตอบทันที!
    </div>

    <div class="container" id="quizContainer" style="display:none;">
        <div class="header">
            <h2 id="testTitle">แบบทดสอบ</h2>
            <div id="timer" class="timer"></div>
        </div>

        <form id="quizForm" onsubmit="submitQuiz(event)">
            <input type="hidden" name="test_id" value="<?= $test_id ?>">
            <div id="questionsList"></div>
            <button type="submit" class="btn-submit">ส่งคำตอบ</button>
        </form>
    </div>

    <script>
        const testId = <?= $test_id ?>;
        let timeLimit = 0; // minutes
        let timerInterval;
        let isSubmitted = false;

        // Auto Submit on Visibility Change (Tab Switch / Minimize)
        document.addEventListener('visibilitychange', function() {
            if (document.hidden && !isSubmitted) {
                alert('ตรวจพบการออกจากหน้าจอ! ระบบจะส่งคำตอบอัตโนมัติ');
                submitQuiz(null);
            }
        });

        // Optional: Block context menu
        document.addEventListener('contextmenu', event => event.preventDefault());

        // Fetch Test Data
        fetch(`../api/student_test_api.php?action=start_test&test_id=${testId}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    renderQuiz(data.test);
                } else {
                    alert(data.message);
                    window.location.href = 'dashboard.php';
                }
            })
            .catch(err => {
                console.error(err);
                alert('เกิดข้อผิดพลาดในการโหลดข้อสอบ');
            });

        function renderQuiz(test) {
            document.getElementById('loading').style.display = 'none';
            document.getElementById('quizContainer').style.display = 'block';

            // Set Timer
            timeLimit = test.time_limit;
            if (timeLimit > 0) {
                startTimer(timeLimit * 60);
            } else {
                document.getElementById('timer').style.display = 'none';
            }

            // Render Questions
            const list = document.getElementById('questionsList');
            let html = '';

            test.questions.forEach((q, idx) => {
                html += `
                <div class="question-card">
                    <div class="question-text">${idx + 1}. ${q.question_text}</div>
                    <div class="options">
                `;
                q.answers.forEach(a => {
                    html += `
                        <label class="option-label">
                            <input type="radio" name="q_${q.id}" value="${a.id}" required>
                            <span>${a.answer_text}</span>
                        </label>
                    `;
                });
                html += `</div></div>`;
            });
            list.innerHTML = html;
        }

        function startTimer(seconds) {
            const timerEl = document.getElementById('timer');

            function update() {
                const m = Math.floor(seconds / 60);
                const s = seconds % 60;
                timerEl.textContent = `เหลือเวลา: ${m}:${s < 10 ? '0' + s : s}`;

                if (seconds <= 0) {
                    clearInterval(timerInterval);
                    alert('หมดเวลา! ระบบจะส่งคำตอบอัตโนมัติ');
                    submitQuiz(null); // Auto submit
                }
                seconds--;
            }

            update();
            timerInterval = setInterval(update, 1000);
        }

        function submitQuiz(e) {
            if (e) e.preventDefault();
            if (isSubmitted) return;
            isSubmitted = true;

            // Clear timer
            clearInterval(timerInterval);

            // Gather answers
            const formData = new FormData(document.getElementById('quizForm'));
            const answers = [];

            // Iterate over form data
            // Since we used name="q_{id}", we can parse them
            for (let [key, val] of formData.entries()) {
                if (key.startsWith('q_')) {
                    const qId = key.split('_')[1];
                    answers.push({ question_id: qId, answer_id: val });
                }
            }

            const payload = new FormData();
            payload.append('test_id', testId);
            payload.append('answers', JSON.stringify(answers));

            // Show loading
            document.getElementById('loading').style.display = 'flex';
            document.getElementById('loading').innerHTML = '<h2>กำลังส่งคำตอบ...</h2>';

            fetch('../api/student_test_api.php?action=submit_test', {
                method: 'POST',
                body: payload
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = 'test_result.php';
                    } else {
                        alert('ส่งคำตอบไม่สำเร็จ: ' + data.message);
                        document.getElementById('loading').style.display = 'none';
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('เกิดข้อผิดพลาด');
                    document.getElementById('loading').style.display = 'none';
                });
        }
    </script>
</body>

</html>