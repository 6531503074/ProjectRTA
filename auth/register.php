<?php
// session_start();
include "../config/db.php";

// Initialize variables
$error_message = "";
$success_message = "";

// Redirect if already logged in
if (isset($_SESSION["user"])) {
    $role = $_SESSION["user"]["role"];
    header("Location: ../dashboard/{$role}.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Sanitize and validate inputs
    $name = trim($_POST["name"]);
    $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];
    $role = "student"; // Fixed role as student only
    $terms = isset($_POST["terms"]) ? true : false;
    
    // Optional fields
    $rank = isset($_POST["rank"]) ? trim($_POST["rank"]) : NULL;
    $position = isset($_POST["position"]) ? trim($_POST["position"]) : NULL;
    $affiliation = isset($_POST["affiliation"]) ? trim($_POST["affiliation"]) : NULL;
    $phone = isset($_POST["phone"]) ? trim($_POST["phone"]) : NULL;
    $courseLevel = isset($_POST["courseLevel"]) ? trim($_POST["courseLevel"]) : NULL;
    
    // Avatar upload handling
    $avatar = NULL;
    $upload_error = "";

    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        $error_message = "กรุณากรอกข้อมูลให้ครบทุกช่อง";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "รูปแบบอีเมลไม่ถูกต้อง";
    } elseif (strlen($password) < 8) {
        $error_message = "รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร";
    } elseif ($password !== $confirm_password) {
        $error_message = "รหัสผ่านไม่ตรงกัน";
    } elseif (!$terms) {
        $error_message = "คุณต้องยอมรับข้อกำหนดและเงื่อนไข";
    } else {
        // Handle avatar upload
        if (isset($_FILES["avatar"]) && $_FILES["avatar"]["error"] === UPLOAD_ERR_OK) {
            $allowed_types = ["image/jpeg", "image/png", "image/jpg", "image/gif"];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            $file_type = $_FILES["avatar"]["type"];
            $file_size = $_FILES["avatar"]["size"];
            $file_tmp = $_FILES["avatar"]["tmp_name"];
            $file_name = $_FILES["avatar"]["name"];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Validate file
            if (!in_array($file_type, $allowed_types)) {
                $upload_error = "อนุญาตเฉพาะไฟล์ JPG, JPEG, PNG และ GIF เท่านั้น";
            } elseif ($file_size > $max_size) {
                $upload_error = "ขนาดไฟล์ต้องไม่เกิน 2MB";
            } else {
                // Create uploads directory if it doesn't exist
                $upload_dir = "../uploads/avatars/";
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate unique filename
                $new_filename = uniqid() . "_" . time() . "." . $file_ext;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    $avatar = "uploads/avatars/" . $new_filename;
                } else {
                    $upload_error = "อัปโหลดรูปโปรไฟล์ไม่สำเร็จ";
                }
            }
        }
        
        if ($upload_error) {
            $error_message = $upload_error;
        } else {
            // Check if email already exists
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error_message = "อีเมลนี้ถูกลงทะเบียนแล้ว";
                // Delete uploaded avatar if email exists
                if ($avatar && file_exists("../" . $avatar)) {
                    unlink("../" . $avatar);
                }
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new user
                $stmt = $conn->prepare(
                    "INSERT INTO users (name, email, password, role, rank, position, affiliation, phone, courseLevel, avatar) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param(
                    "ssssssssss", 
                    $name, 
                    $email, 
                    $hashed_password, 
                    $role, 
                    $rank, 
                    $position, 
                    $affiliation, 
                    $phone, 
                    $courseLevel,
                    $avatar
                );
                
                if ($stmt->execute()) {
                    $success_message = "ลงทะเบียนสำเร็จ! คุณสามารถเข้าสู่ระบบได้แล้ว";
                } else {
                    $error_message = "การลงทะเบียนล้มเหลว กรุณาลองใหม่อีกครั้ง";
                    // Delete uploaded avatar if registration fails
                    if ($avatar && file_exists("../" . $avatar)) {
                        unlink("../" . $avatar);
                    }
                }
                
                $stmt->close();
            }
            
            $check_stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงทะเบียน - Cyber Security Learning Platform</title>
    <!-- Import Thai font -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Sarabun', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .register-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 800px;
        }
        
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 10px;
        }
        
        .subtitle {
            text-align: center;
            color: #888;
            font-size: 14px;
            margin-bottom: 30px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        
        label .required {
            color: #e74c3c;
        }
        
        input, select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
            font-family: 'Sarabun', sans-serif;
            transition: border-color 0.3s;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        select {
            cursor: pointer;
        }
        
        /* Avatar Upload Styles */
        .avatar-upload {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 15px;
            border: 2px dashed #e0e0e0;
            border-radius: 5px;
            background: #f9f9f9;
        }
        
        .avatar-preview {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .avatar-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .avatar-preview-icon {
            font-size: 40px;
            color: #999;
        }
        
        .avatar-upload-btn {
            flex: 1;
        }
        
        .avatar-upload-btn input[type="file"] {
            display: none;
        }
        
        .avatar-upload-btn label {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
            font-size: 13px;
            margin-bottom: 5px;
        }
        
        .avatar-upload-btn label:hover {
            background: #5568d3;
        }
        
        .avatar-info {
            font-size: 12px;
            color: #888;
        }
        
        /* Terms Checkbox */
        .terms-group {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
        }
        
        .terms-group input[type="checkbox"] {
            width: auto;
            margin-top: 3px;
            cursor: pointer;
        }
        
        .terms-group label {
            margin: 0;
            font-weight: normal;
            font-size: 13px;
            cursor: pointer;
        }
        
        .terms-group label a {
            color: #667eea;
            text-decoration: none;
        }
        
        .terms-group label a:hover {
            text-decoration: underline;
        }
        
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-family: 'Sarabun', sans-serif;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
            margin-top: 10px;
        }
        
        button:hover {
            transform: translateY(-2px);
        }
        
        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .error-message {
            background: #fee;
            color: #c33;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .success-message {
            background: #efe;
            color: #2d5;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .links {
            text-align: center;
            margin-top: 20px;
        }
        
        .links a {
            color: #667eea;
            text-decoration: none;
        }
        
        .links a:hover {
            text-decoration: underline;
        }
        
        .password-strength {
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s;
        }
        
        .strength-weak { background: #e74c3c; width: 33%; }
        .strength-medium { background: #f39c12; width: 66%; }
        .strength-strong { background: #27ae60; width: 100%; }
        
        .optional-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px dashed #e0e0e0;
        }
        
        .optional-header {
            color: #888;
            font-size: 14px;
            margin-bottom: 15px;
            text-align: center;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-header h3 {
            color: #333;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #000;
        }
        
        .modal-body {
            color: #555;
            line-height: 1.6;
        }
        
        .modal-body h4 {
            color: #667eea;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        
        .modal-body p {
            margin-bottom: 10px;
        }

        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .register-container {
                padding: 30px 20px;
            }
            
            .avatar-upload {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h2>🎓 สร้างบัญชีนักเรียน</h2>
        <p class="subtitle">เข้าร่วมแพลตฟอร์มการเรียนรู้ความปลอดภัยทางไซเบอร์ของเรา</p>
        
        <?php if ($error_message): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($success_message); ?>
                <br><a href="login.php"><strong>เข้าสู่ระบบที่นี่</strong></a>
                <p id="countdown" style="margin-top: 10px; font-size: 12px; color: #666;">กำลังเปลี่ยนหน้าใน <span id="timer">5</span> วินาที...</p>
                <script>
                    let seconds = 5;
                    const timer = document.getElementById('timer');
                    const countdown = setInterval(() => {
                        seconds--;
                        timer.textContent = seconds;
                        if (seconds <= 0) {
                            clearInterval(countdown);
                            window.location.href = 'login.php';
                        }
                    }, 1000);
                </script>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="registerForm" enctype="multipart/form-data">
            <!-- Avatar Upload -->
            <div class="form-group">
                <label>รูปโปรไฟล์</label>
                <div class="avatar-upload">
                    <div class="avatar-preview" id="avatarPreview">
                        <span class="avatar-preview-icon">👤</span>
                    </div>
                    <div class="avatar-upload-btn">
                        <label for="avatar">เลือกรูปภาพ</label>
                        <input 
                            type="file" 
                            id="avatar" 
                            name="avatar" 
                            accept="image/jpeg,image/jpg,image/png,image/gif"
                        >
                        <div class="avatar-info">JPG, PNG, GIF (สูงสุด 2MB)</div>
                    </div>
                </div>
            </div>
            
            <!-- Required Fields -->
            <div class="form-group">
                <label for="name">ชื่อ-นามสกุล <span class="required">*</span></label>
                <input 
                    type="text" 
                    id="name" 
                    name="name" 
                    placeholder="กรอกชื่อ-นามสกุลของคุณ" 
                    required
                    value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                >
            </div>
            
            <div class="form-group">
                <label for="email">อีเมล <span class="required">*</span></label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    placeholder="กรอกอีเมลของคุณ" 
                    required
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                >
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password">รหัสผ่าน <span class="required">*</span></label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="อย่างน้อย 8 ตัวอักษร" 
                        required
                        minlength="8"
                    >
                    <div class="password-strength">
                        <div class="password-strength-bar" id="strengthBar"></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">ยืนยันรหัสผ่าน <span class="required">*</span></label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        placeholder="กรอกรหัสผ่านอีกครั้ง" 
                        required
                        minlength="8"
                    >
                </div>
            </div>
            
            <!-- Optional Fields -->
            <div class="optional-section">
                <div class="optional-header">📋 ข้อมูลเพิ่มเติม (ไม่บังคับ)</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="rank">ยศ</label>
                        <input 
                            type="text" 
                            id="rank" 
                            name="rank" 
                            placeholder="เช่น สิบเอก, ร้อยโท"
                            value="<?php echo isset($_POST['rank']) ? htmlspecialchars($_POST['rank']) : ''; ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="courseLevel">ระดับหลักสูตร</label>
                        <select id="courseLevel" name="courseLevel">
                            <option value="">-- เลือกระดับ --</option>
                            <option value="1" <?php echo (isset($_POST['courseLevel']) && $_POST['courseLevel'] === '1') ? 'selected' : ''; ?>>ขั้นเริ่มต้น</option>
                            <option value="2" <?php echo (isset($_POST['courseLevel']) && $_POST['courseLevel'] === '2') ? 'selected' : ''; ?>>ขั้นกลาง</option>
                            <option value="3" <?php echo (isset($_POST['courseLevel']) && $_POST['courseLevel'] === '3') ? 'selected' : ''; ?>>ขั้นสูง</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="affiliation">สังกัด</label>
                    <input 
                        type="text" 
                        id="affiliation" 
                        name="affiliation" 
                        placeholder="ระบุสังกัดของคุณ"
                        value="<?php echo isset($_POST['affiliation']) ? htmlspecialchars($_POST['affiliation']) : ''; ?>"
                    >
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="position">ตำแหน่ง</label>
                        <input 
                            type="text" 
                            id="position" 
                            name="position" 
                            placeholder="เช่น นักเรียน, นักวิจัย"
                            value="<?php echo isset($_POST['position']) ? htmlspecialchars($_POST['position']) : ''; ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">เบอร์โทรศัพท์</label>
                        <input 
                            type="tel" 
                            id="phone" 
                            name="phone" 
                            placeholder="+66 XX XXX XXXX"
                            value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                        >
                    </div>
                </div>
            </div>
            
            <!-- Terms and Conditions -->
            <div class="terms-group">
                <input 
                    type="checkbox" 
                    id="terms" 
                    name="terms" 
                    required
                >
                <label for="terms">
                    ฉันยอมรับ <a href="#" id="termsLink">ข้อกำหนดและเงื่อนไข</a> และ <a href="#" id="privacyLink">นโยบายความเป็นส่วนตัว</a> <span class="required">*</span>
                </label>
            </div>
            
            <button type="submit" id="submitBtn">สร้างบัญชี</button>
        </form>
        
        <div class="links">
            มีบัญชีอยู่แล้ว? <a href="login.php">เข้าสู่ระบบที่นี่</a>
        </div>
    </div>
    
    <!-- Terms and Conditions Modal -->
    <div id="termsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>ข้อกำหนดและเงื่อนไข</h3>
                <span class="close" id="closeTerms">&times;</span>
            </div>
            <div class="modal-body">
                <p><strong>อัปเดตล่าสุด: มกราคม 2026</strong></p>
                
                <h4>1. การยอมรับข้อกำหนด</h4>
                <p>ในการเข้าถึงและใช้งานแพลตฟอร์มการเรียนรู้ความปลอดภัยทางไซเบอร์นี้ คุณยอมรับและตกลงที่จะผูกพันตามข้อกำหนดและเงื่อนไขของข้อตกลงนี้</p>
                
                <h4>2. การใช้บริการ</h4>
                <p>คุณตกลงที่จะใช้แพลตฟอร์มนี้เพื่อวัตถุประสงค์ที่ถูกต้องตามกฎหมายเท่านั้น คุณต้องไม่ใช้แพลตฟอร์มนี้เพื่อ:</p>
                <ul>
                    <li>มีส่วนร่วมในกิจกรรมที่ผิดกฎหมายใดๆ</li>
                    <li>ส่งรหัสที่เป็นอันตรายหรือมัลแวร์</li>
                    <li>ละเมิดกฎหมายหรือระเบียบข้อบังคับที่เกี่ยวข้อง</li>
                    <li>คุกคาม ข่มเหง หรือทำร้ายผู้ใช้อื่น</li>
                </ul>
                
                <h4>3. ความรับผิดชอบต่อบัญชี</h4>
                <p>คุณมีหน้าที่รับผิดชอบในการรักษาความลับของข้อมูลบัญชีของคุณและกิจกรรมทั้งหมดที่เกิดขึ้นภายใต้บัญชีของคุณ</p>
                
                <h4>4. การใช้เนื้อหา</h4>
                <p>เนื้อหาการศึกษาทั้งหมดที่ให้บริการบนแพลตฟอร์มนี้มีไว้เพื่อการเรียนรู้เท่านั้น ห้ามทำซ้ำหรือเผยแพร่โดยไม่ได้รับอนุญาต</p>
                
                <h4>5. การแฮ็กอย่างมีจริยธรรม (Ethical Hacking)</h4>
                <p>ความรู้ที่ได้รับจากแพลตฟอร์มนี้ควรใช้เพื่อวัตถุประสงค์ทางจริยธรรมเท่านั้น การเข้าถึงระบบคอมพิวเตอร์โดยไม่ได้รับอนุญาตถือเป็นสิ่งผิดกฎหมาย</p>
                
                <h4>6. การยกเลิกการใช้งาน</h4>
                <p>เราขอสงวนสิทธิ์ในการยกเลิกบัญชีที่ละเมิดข้อกำหนดเหล่านี้โดยไม่ต้องแจ้งให้ทราบล่วงหน้า</p>
                
                <h4>7. การเปลี่ยนแปลงข้อกำหนด</h4>
                <p>เราขอสงวนสิทธิ์ในการแก้ไขข้อกำหนดเหล่านี้ได้ทุกเมื่อ การใช้งานแพลตฟอร์มต่อไปถือเป็นการยอมรับข้อกำหนดที่แก้ไขแล้ว</p>
            </div>
        </div>
    </div>
    
    <!-- Privacy Policy Modal -->
    <div id="privacyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>นโยบายความเป็นส่วนตัว</h3>
                <span class="close" id="closePrivacy">&times;</span>
            </div>
            <div class="modal-body">
                <p><strong>อัปเดตล่าสุด: มกราคม 2026</strong></p>
                
                <h4>1. ข้อมูลที่เราเก็บรวบรวม</h4>
                <p>เราเก็บรวบรวมข้อมูลที่คุณให้โดยตรง รวมถึง:</p>
                <ul>
                    <li>ชื่อและที่อยู่อีเมล</li>
                    <li>รูปโปรไฟล์ (ไม่บังคับ)</li>
                    <li>ข้อมูลพื้นฐานทางการศึกษา</li>
                    <li>ความคืบหน้าในการเรียนรู้และความสำเร็จ</li>
                </ul>
                
                <h4>2. วิธีที่เราใช้ข้อมูลของคุณ</h4>
                <p>เราใช้ข้อมูลที่เราเก็บรวบรวมเพื่อ:</p>
                <ul>
                    <li>ให้บริการและปรับปรุงบริการการศึกษาของเรา</li>
                    <li>ปรับแต่งประสบการณ์การเรียนรู้ของคุณ</li>
                    <li>ติดต่อสื่อสารกับคุณเกี่ยวกับหลักสูตรและการอัปเดต</li>
                    <li>รักษาความปลอดภัยของแพลตฟอร์ม</li>
                </ul>
                
                <h4>3. การแบ่งปันข้อมูล</h4>
                <p>เราไม่ขายหรือแบ่งปันข้อมูลส่วนบุคคลของคุณกับบุคคลที่สาม ยกเว้น:</p>
                <ul>
                    <li>เมื่อได้รับความยินยอมจากคุณอย่างชัดแจ้ง</li>
                    <li>เพื่อปฏิบัติตามข้อผูกพันทางกฎหมาย</li>
                    <li>เพื่อปกป้องสิทธิ์และความปลอดภัยของเรา</li>
                </ul>
                
                <h4>4. ความปลอดภัยของข้อมูล</h4>
                <p>เราใช้มาตรการรักษาความปลอดภัยที่เหมาะสมเพื่อปกป้องข้อมูลส่วนบุคคลของคุณจากการเข้าถึง การเปลี่ยนแปลง หรือการทำลายโดยไม่ได้รับอนุญาต</p>
                
                <h4>5. สิทธิ์ของคุณ</h4>
                <p>คุณมีสิทธิ์ที่จะ:</p>
                <ul>
                    <li>เข้าถึงข้อมูลส่วนบุคคลของคุณ</li>
                    <li>ขอแก้ไขข้อมูลที่ไม่ถูกต้อง</li>
                    <li>ขอลบข้อมูลบัญชีของคุณ</li>
                    <li>เลือกที่จะไม่รับการสื่อสารทางการตลาด</li>
                </ul>
                
                <h4>6. คุกกี้ (Cookies)</h4>
                <p>เราใช้คุกกี้เพื่อเพิ่มประสบการณ์ของคุณและรักษาเซสชันการใช้งาน คุณสามารถควบคุมการตั้งค่าคุกกี้ในเบราว์เซอร์ของคุณ</p>
                
                <h4>7. ติดต่อเรา</h4>
                <p>หากคุณมีคำถามเกี่ยวกับนโยบายความเป็นส่วนตัวนี้ โปรดติดต่อเราที่ privacy@cybersecuritylearning.com</p>
            </div>
        </div>
    </div>
    
    <script>
        // Avatar preview
        const avatarInput = document.getElementById('avatar');
        const avatarPreview = document.getElementById('avatarPreview');
        
        avatarInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validate file size
                if (file.size > 2 * 1024 * 1024) {
                    alert('ขนาดไฟล์ต้องไม่เกิน 2MB');
                    this.value = '';
                    return;
                }
                
                // Validate file type
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!validTypes.includes(file.type)) {
                    alert('อนุญาตเฉพาะไฟล์ JPG, PNG และ GIF เท่านั้น');
                    this.value = '';
                    return;
                }
                
                // Preview image
                const reader = new FileReader();
                reader.onload = function(e) {
                    avatarPreview.innerHTML = `<img src="${e.target.result}" alt="Avatar Preview">`;
                };
                reader.readAsDataURL(file);
            }
        });
        
        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('strengthBar');
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            strengthBar.className = 'password-strength-bar';
            if (strength === 1 || strength === 2) {
                strengthBar.classList.add('strength-weak');
            } else if (strength === 3) {
                strengthBar.classList.add('strength-medium');
            } else if (strength === 4) {
                strengthBar.classList.add('strength-strong');
            }
        });
        
        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const terms = document.getElementById('terms').checked;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('รหัสผ่านไม่ตรงกัน!');
                return false;
            }
            
            if (!terms) {
                e.preventDefault();
                alert('คุณต้องยอมรับข้อกำหนดและเงื่อนไข');
                return false;
            }
        });
        
        // Modal functionality
        const termsModal = document.getElementById('termsModal');
        const privacyModal = document.getElementById('privacyModal');
        const termsLink = document.getElementById('termsLink');
        const privacyLink = document.getElementById('privacyLink');
        const closeTerms = document.getElementById('closeTerms');
        const closePrivacy = document.getElementById('closePrivacy');
        
        termsLink.addEventListener('click', function(e) {
            e.preventDefault();
            termsModal.style.display = 'block';
        });
        
        privacyLink.addEventListener('click', function(e) {
            e.preventDefault();
            privacyModal.style.display = 'block';
        });
        
        closeTerms.addEventListener('click', function() {
            termsModal.style.display = 'none';
        });
        
        closePrivacy.addEventListener('click', function() {
            privacyModal.style.display = 'none';
        });
        
        window.addEventListener('click', function(e) {
            if (e.target === termsModal) {
                termsModal.style.display = 'none';
            }
            if (e.target === privacyModal) {
                privacyModal.style.display = 'none';
            }
        });
    </script>
</body>
</html>