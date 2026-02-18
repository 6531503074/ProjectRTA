<?php
include "../config/db.php";

$message = "";
$error = "";
$reset_link = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "รูปแบบอีเมลไม่ถูกต้อง";
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Generate token
            $token = bin2hex(random_bytes(32));

            // Insert into password_resets
            $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
            $stmt->bind_param("ss", $email, $token);
            
            if ($stmt->execute()) {
                $message = "คลิ๊กลิงก์นี้เพื่อเปลี่ยนรหัสผ่าน";
                // For development, we show the link
                $reset_link = "reset-password.php?token=" . $token;
            } else {
                $error = "เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง";
            }
        } else {
            // To prevent user enumeration, we could show the same success message, 
            // but for a learning platform, a clear error might be more helpful.
            $error = "ไม่พบอีเมลนี้ในระบบ";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลืมรหัสผ่าน - Cyber Security Learning Platform</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
        }
        h2 { text-align: center; margin-bottom: 20px; color: #333; }
        p { text-align: center; margin-bottom: 30px; color: #666; font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; color: #555; }
        input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-family: 'Sarabun', sans-serif;
        }
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Sarabun', sans-serif;
        }
        .message { background: #e7f3ff; color: #004085; padding: 10px; border-radius: 5px; margin-bottom: 20px; text-align: center; }
        .error { background: #fee; color: #c33; padding: 10px; border-radius: 5px; margin-bottom: 20px; text-align: center; }
        .links { text-align: center; margin-top: 20px; }
        .links a { color: #667eea; text-decoration: none; font-size: 14px; }
        .reset-debug {
            margin-top: 20px;
            padding: 10px;
            background: #f8f9fa;
            border: 1px dashed #ccc;
            word-break: break-all;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔑 ลืมรหัสผ่าน</h2>
        <p>ระบุอีเมลของคุณเพื่อรับลิงก์สำหรับตั้งรหัสผ่านใหม่</p>

        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php if ($reset_link): ?>
                <div class="reset-debug">
                    <strong>ลิงก์</strong><br>
                    <a href="<?php echo $reset_link; ?>"><?php echo $reset_link; ?></a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="email">อีเมล</label>
                <input type="email" id="email" name="email" placeholder="example@email.com" required>
            </div>
            <button type="submit">ยืนยัน</button>
        </form>

        <div class="links">
            <a href="login.php"><i class="fas fa-arrow-left"></i> กลับไปหน้าเข้าสู่ระบบ</a>
        </div>
    </div>
</body>
</html>
