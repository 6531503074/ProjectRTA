<?php
include "../config/db.php";

$message = "";
$error = "";
$token = $_GET["token"] ?? $_POST["token"] ?? "";

if (empty($token)) {
    header("Location: login.php");
    exit();
}

// Verify token
$stmt = $conn->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$reset_request = $result->fetch_assoc();

if (!$reset_request) {
    $error = "ลิงก์รีเซ็ตรหัสผ่านไม่ถูกต้องหรือหมดอายุแล้ว";
} else {
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $password = $_POST["password"];
        $confirm_password = $_POST["confirm_password"];

        if (strlen($password) < 6) {
            $error = "รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
        } elseif ($password !== $confirm_password) {
            $error = "รหัสผ่านไม่ตรงกัน";
        } else {
            // Update password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $email = $reset_request["email"];

            $conn->begin_transaction();
            try {
                // Update user password
                $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
                $update_stmt->bind_param("ss", $hashed_password, $email);
                $update_stmt->execute();

                // Delete reset token
                $delete_stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
                $delete_stmt->bind_param("s", $email);
                $delete_stmt->execute();

                $conn->commit();
                $message = "รีเซ็ตรหัสผ่านสำเร็จแล้ว คุณสามารถเข้าสู่ระบบด้วยรหัสผ่านใหม่ได้ทันที";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งรหัสผ่านใหม่ - Cyber Security Learning Platform</title>
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
        .password-wrapper { position: relative; }
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔒 ตั้งรหัสผ่านใหม่</h2>

        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <div class="links">
                <a href="login.php">ไปที่หน้าเข้าสู่ระบบ</a>
            </div>
        <?php elseif ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <div class="links">
                <a href="forgot-password.php">ขอลิงก์ใหม่อีกครั้ง</a>
            </div>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <div class="form-group">
                    <label for="password">รหัสผ่านใหม่</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" required minlength="6">
                        <i class="fas fa-eye toggle-password" onclick="togglePass('password')"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label for="confirm_password">ยืนยันรหัสผ่านใหม่</label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                        <i class="fas fa-eye toggle-password" onclick="togglePass('confirm_password')"></i>
                    </div>
                </div>
                <button type="submit">บันทึกรหัสผ่านใหม่</button>
            </form>
        <?php endif; ?>
    </div>

    <script>
        function togglePass(id) {
            const input = document.getElementById(id);
            const icon = input.nextElementSibling;
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }
    </script>
</body>
</html>
