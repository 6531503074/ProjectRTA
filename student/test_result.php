<?php
session_start();
if (!isset($_SESSION["user"])) {
    header("Location: ../auth/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ส่งแบบทดสอบสำเร็จ</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f5f7fa;
            padding: 50px 20px;
            text-align: center;
        }

        .card {
            background: white;
            max-width: 500px;
            margin: 0 auto;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        h1 {
            margin-bottom: 10px;
            color: #2d3748;
        }

        p {
            color: #718096;
            margin-bottom: 30px;
        }

        .btn {
            display: inline-block;
            background: #3182ce;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            transition: background 0.2s;
        }

        .btn:hover {
            background: #2b6cb0;
        }
    </style>
</head>

<body>
    <div class="card">
        <div class="icon">🎉</div>
        <h1>ส่งแบบทดสอบเรียบร้อย</h1>
        <p>บันทึกคำตอบของคุณแล้ว คุณสามารถกลับไปที่หน้าหลักสูตรได้</p>
        <a href="dashboard.php" class="btn">กลับไปหน้า Dashboard</a>
    </div>
</body>

</html>