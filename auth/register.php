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
        $error_message = "All required fields must be filled";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format";
    } elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match";
    } elseif (!$terms) {
        $error_message = "You must agree to the Terms and Conditions";
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
                $upload_error = "Only JPG, JPEG, PNG & GIF files are allowed";
            } elseif ($file_size > $max_size) {
                $upload_error = "File size must be less than 2MB";
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
                    $upload_error = "Failed to upload avatar";
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
                $error_message = "Email already registered";
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
                    $success_message = "Registration successful! You can now login.";
                } else {
                    $error_message = "Registration failed. Please try again.";
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
    <title>Register - Cyber Security Learning Platform</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
        <h2>🎓 Create Student Account</h2>
        <p class="subtitle">Join our Cyber Security Learning Platform</p>
        
        <?php if ($error_message): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($success_message); ?>
                <br><a href="login.php"><strong>Click here to login</strong></a>
                <p id="countdown" style="margin-top: 10px; font-size: 12px; color: #666;">Redirecting in <span id="timer">5</span> seconds...</p>
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
                <label>Profile Picture</label>
                <div class="avatar-upload">
                    <div class="avatar-preview" id="avatarPreview">
                        <span class="avatar-preview-icon">👤</span>
                    </div>
                    <div class="avatar-upload-btn">
                        <label for="avatar">Choose Image</label>
                        <input 
                            type="file" 
                            id="avatar" 
                            name="avatar" 
                            accept="image/jpeg,image/jpg,image/png,image/gif"
                        >
                        <div class="avatar-info">JPG, PNG, GIF (Max 2MB)</div>
                    </div>
                </div>
            </div>
            
            <!-- Required Fields -->
            <div class="form-group">
                <label for="name">Full Name <span class="required">*</span></label>
                <input 
                    type="text" 
                    id="name" 
                    name="name" 
                    placeholder="Enter your full name" 
                    required
                    value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                >
            </div>
            
            <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    placeholder="Enter your email" 
                    required
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                >
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Min. 8 characters" 
                        required
                        minlength="8"
                    >
                    <div class="password-strength">
                        <div class="password-strength-bar" id="strengthBar"></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        placeholder="Repeat password" 
                        required
                        minlength="8"
                    >
                </div>
            </div>
            
            <!-- Optional Fields -->
            <div class="optional-section">
                <div class="optional-header">📋 Optional Information</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="rank">Rank</label>
                        <input 
                            type="text" 
                            id="rank" 
                            name="rank" 
                            placeholder="e.g., Beginner, Advanced"
                            value="<?php echo isset($_POST['rank']) ? htmlspecialchars($_POST['rank']) : ''; ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="courseLevel">Course Level</label>
                        <select id="courseLevel" name="courseLevel">
                            <option value="">-- Select Level --</option>
                            <option value="1" <?php echo (isset($_POST['courseLevel']) && $_POST['courseLevel'] === '1') ? 'selected' : ''; ?>>ขั้นเริ่มต้น</option>
                            <option value="2" <?php echo (isset($_POST['courseLevel']) && $_POST['courseLevel'] === '2') ? 'selected' : ''; ?>>ขั้นกลาง</option>
                            <option value="3" <?php echo (isset($_POST['courseLevel']) && $_POST['courseLevel'] === '3') ? 'selected' : ''; ?>>ขั้นสูง</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="affiliation">School/University</label>
                    <input 
                        type="text" 
                        id="affiliation" 
                        name="affiliation" 
                        placeholder="Your educational institution"
                        value="<?php echo isset($_POST['affiliation']) ? htmlspecialchars($_POST['affiliation']) : ''; ?>"
                    >
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="position">Position</label>
                        <input 
                            type="text" 
                            id="position" 
                            name="position" 
                            placeholder="e.g., Student, Researcher"
                            value="<?php echo isset($_POST['position']) ? htmlspecialchars($_POST['position']) : ''; ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
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
                    I agree to the <a href="#" id="termsLink">Terms and Conditions</a> and <a href="#" id="privacyLink">Privacy Policy</a> <span class="required">*</span>
                </label>
            </div>
            
            <button type="submit" id="submitBtn">Create Account</button>
        </form>
        
        <div class="links">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
    
    <!-- Terms and Conditions Modal -->
    <div id="termsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Terms and Conditions</h3>
                <span class="close" id="closeTerms">&times;</span>
            </div>
            <div class="modal-body">
                <p><strong>Last Updated: January 2026</strong></p>
                
                <h4>1. Acceptance of Terms</h4>
                <p>By accessing and using this Cyber Security Learning Platform, you accept and agree to be bound by the terms and provision of this agreement.</p>
                
                <h4>2. Use of Service</h4>
                <p>You agree to use this platform for lawful purposes only. You must not use this platform:</p>
                <ul>
                    <li>To engage in any illegal activities</li>
                    <li>To transmit any harmful code or malware</li>
                    <li>To violate any applicable laws or regulations</li>
                    <li>To harass, abuse, or harm other users</li>
                </ul>
                
                <h4>3. Account Responsibilities</h4>
                <p>You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account.</p>
                
                <h4>4. Content Usage</h4>
                <p>All educational content provided on this platform is for learning purposes only. Unauthorized reproduction or distribution is prohibited.</p>
                
                <h4>5. Ethical Hacking</h4>
                <p>Any knowledge gained from this platform should only be used for ethical purposes. Unauthorized access to computer systems is illegal.</p>
                
                <h4>6. Termination</h4>
                <p>We reserve the right to terminate accounts that violate these terms without prior notice.</p>
                
                <h4>7. Changes to Terms</h4>
                <p>We reserve the right to modify these terms at any time. Continued use of the platform constitutes acceptance of modified terms.</p>
            </div>
        </div>
    </div>
    
    <!-- Privacy Policy Modal -->
    <div id="privacyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Privacy Policy</h3>
                <span class="close" id="closePrivacy">&times;</span>
            </div>
            <div class="modal-body">
                <p><strong>Last Updated: January 2026</strong></p>
                
                <h4>1. Information We Collect</h4>
                <p>We collect information that you provide directly to us, including:</p>
                <ul>
                    <li>Name and email address</li>
                    <li>Profile picture (optional)</li>
                    <li>Educational background information</li>
                    <li>Learning progress and achievements</li>
                </ul>
                
                <h4>2. How We Use Your Information</h4>
                <p>We use the information we collect to:</p>
                <ul>
                    <li>Provide and improve our educational services</li>
                    <li>Personalize your learning experience</li>
                    <li>Communicate with you about courses and updates</li>
                    <li>Ensure platform security</li>
                </ul>
                
                <h4>3. Information Sharing</h4>
                <p>We do not sell or share your personal information with third parties except:</p>
                <ul>
                    <li>With your explicit consent</li>
                    <li>To comply with legal obligations</li>
                    <li>To protect our rights and safety</li>
                </ul>
                
                <h4>4. Data Security</h4>
                <p>We implement appropriate security measures to protect your personal information from unauthorized access, alteration, or destruction.</p>
                
                <h4>5. Your Rights</h4>
                <p>You have the right to:</p>
                <ul>
                    <li>Access your personal data</li>
                    <li>Request correction of inaccurate data</li>
                    <li>Request deletion of your account</li>
                    <li>Opt-out of marketing communications</li>
                </ul>
                
                <h4>6. Cookies</h4>
                <p>We use cookies to enhance your experience and maintain your session. You can control cookie settings in your browser.</p>
                
                <h4>7. Contact Us</h4>
                <p>If you have questions about this Privacy Policy, please contact us at privacy@cybersecuritylearning.com</p>
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
                    alert('File size must be less than 2MB');
                    this.value = '';
                    return;
                }
                
                // Validate file type
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!validTypes.includes(file.type)) {
                    alert('Only JPG, PNG, and GIF files are allowed');
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
                alert('Passwords do not match!');
                return false;
            }
            
            if (!terms) {
                e.preventDefault();
                alert('You must agree to the Terms and Conditions');
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