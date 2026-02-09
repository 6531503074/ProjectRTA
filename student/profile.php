<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION["user"]) || $_SESSION["user"]["role"] !== "student") {
    header("Location: ../auth/login.php");
    exit();
}

$user = $_SESSION["user"];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile - CyberLearn</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }

        .main-content {
            margin-left: 260px;
            padding: 30px;
            min-height: 100vh;
        }

        .profile-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 30px;
            max-width: 800px;
            margin: 0 auto;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid #eee;
            margin-bottom: 30px;
        }

        .profile-avatar-wrapper {
            position: relative;
            width: 120px;
            height: 120px;
        }

        .profile-avatar {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .avatar-upload-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 36px;
            height: 36px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 2px solid white;
            transition: all 0.2s;
        }

        .avatar-upload-btn:hover {
            background: #5a6fd1;
            transform: scale(1.1);
        }

        .profile-title h2 {
            margin: 0;
            color: #2d3748;
            font-size: 24px;
        }

        .profile-title p {
            margin: 5px 0 0;
            color: #718096;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #4a5568;
            font-weight: 500;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-control:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-control[readonly] {
            background-color: #f7fafc;
            cursor: not-allowed;
        }

        .section-title {
            grid-column: 1 / -1;
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
            margin: 20px 0 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f7fafc;
        }

        .btn-save {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 20px;
        }

        .btn-save:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include "../components/student-sidebar.php"; ?>

    <div class="main-content">
        <div class="profile-container">
            <form id="profileForm" onsubmit="updateProfile(event)">
                <div class="profile-header">
                    <div class="profile-avatar-wrapper">
                        <?php 
                            $avatarPath = !empty($user['avatar']) ? "../" . htmlspecialchars($user['avatar']) : "https://ui-avatars.com/api/?name=" . urlencode($user['name']);
                        ?>
                        <img src="<?= $avatarPath ?>" alt="Profile" class="profile-avatar" id="avatarPreview">
                        <label for="avatarInput" class="avatar-upload-btn">
                            <i class="fas fa-camera"></i>
                        </label>
                        <input type="file" id="avatarInput" name="avatar" accept="image/*" style="display: none;" onchange="previewImage(this)">
                    </div>
                    <div class="profile-title">
                        <h2><?= htmlspecialchars($user['name']) ?></h2>
                        <p>นักเรียน</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="section-title">ข้อมูลส่วนตัว</div>

                    <div class="form-group">
                        <label class="form-label">ชื่อ-นามสกุล</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">อีเมล</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">ยศ</label>
                        <input type="text" name="rank" class="form-control" value="<?= htmlspecialchars($user['rank'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">ตำแหน่ง</label>
                        <input type="text" name="position" class="form-control" value="<?= htmlspecialchars($user['position'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">สังกัด</label>
                        <input type="text" name="affiliation" class="form-control" value="<?= htmlspecialchars($user['affiliation'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">เบอร์โทรศัพท์</label>
                        <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">ระดับชั้นเรียน</label>
                        <?php 
                            $levels = ['1' => 'ขั้นเริ่มต้น', '2' => 'ขั้นกลาง', '3' => 'ขั้นสูง'];
                            $levelText = $levels[$user['courseLevel'] ?? ''] ?? 'ไม่ระบุ';
                        ?>
                        <input type="text" class="form-control" value="<?= $levelText ?>" readonly>
                    </div>

                    <div class="section-title">เปลี่ยนรหัสผ่าน (เว้นว่างหากไม่ต้องการเปลี่ยน)</div>

                    <div class="form-group">
                        <label class="form-label">รหัสผ่านใหม่</label>
                        <input type="password" name="new_password" class="form-control" minlength="8">
                    </div>

                    <div class="form-group">
                        <label class="form-label">ยืนยันรหัสผ่านใหม่</label>
                        <input type="password" name="confirm_password" class="form-control" minlength="8">
                    </div>

                    <div class="form-group full-width" style="text-align: right;">
                        <button type="submit" class="btn-save">
                            <i class="fas fa-save"></i> บันทึกการเปลี่ยนแปลง
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('avatarPreview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function updateProfile(e) {
            e.preventDefault();
            
            const form = e.target;
            const formData = new FormData(form);
            const password = formData.get('new_password');
            const confirm = formData.get('confirm_password');

            if (password && password !== confirm) {
                alert('รหัสผ่านใหม่ไม่ตรงกัน');
                return;
            }

            fetch('../api/student_api.php?action=update_profile', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    alert('บันทึกข้อมูลเรียบร้อยแล้ว');
                    location.reload();
                } else {
                    alert(data.message || 'เกิดข้อผิดพลาดในการบันทึกข้อมูล');
                }
            })
            .catch(err => {
                console.error(err);
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
            });
        }
    </script>
</body>
</html>
