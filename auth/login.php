<?php
// session_start(); // Always start session at the beginning
include "../config/db.php";

// Initialize error message
$error_message = "";

// Redirect if already logged in
if (isset($_SESSION["user"])) {
    $role = $_SESSION["user"]["role"];
    header("Location: ../{$role}/dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Sanitize and validate inputs
    $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
    $password = $_POST["password"];

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format";
    } else {
        // Use prepared statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT id, email, password, role, status, name, avatar, rank, position, affiliation FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($password, $user["password"])) {
            // Check if account is active (optional)
            if (isset($user["status"]) && $user["status"] === "inactive") {
                $error_message = "Your account has been deactivated";
            } else {
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);

                // Store minimal user data in session
                $_SESSION["user"] = [
                    "id" => $user["id"],
                    "name" => $user["name"],
                    "avatar" => $user["avatar"],
                    "email" => $user["email"],
                    "rank" => $user["rank"],
                    "position" => $user["position"],
                    "affiliation" => $user["affiliation"],
                    "role" => $user["role"]
                ];

                // Set session timeout (optional)
                $_SESSION["last_activity"] = time();

                // Redirect based on role
                $role = $user["role"];
                header("Location: ../{$role}/dashboard.php");
                exit();
            }
        } else {
            // Generic error message to prevent user enumeration
            $error_message = "Invalid email or password";

            // Optional: Log failed login attempts
            // error_log("Failed login attempt for email: " . $email);
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Cyber Security Learning Platform</title>
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
        }

        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
        }

        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
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
        }

        button:hover {
            transform: translateY(-2px);
        }

        .error-message {
            background: #fee;
            color: #c33;
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

        .password-wrapper {
            position: relative;
            width: 100%;
        }

        .password-wrapper input {
            width: 100%;
            padding: 10px 40px 10px 10px;
            font-size: 16px;
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            cursor: pointer;
            color: #555;
            transition: transform 0.2s ease, color 0.2s ease;
        }

        .toggle-password.active {
            transform: translateY(-50%) scale(1.2);
            color: #007bff;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <h2>🔒 Secure Login</h2>

        <?php if ($error_message): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email"
                    required
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>

            <div class="form-group password-group">
                <label for="password">Password</label>

                <div class="password-wrapper">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        required>

                    <i class="fa-solid fa-eye toggle-password" id="togglePassword"></i>
                </div>
            </div>

            <script>
                const togglePassword = document.getElementById("togglePassword");
                const password = document.getElementById("password");

                togglePassword.addEventListener("click", function() {
                    const isPassword = password.type === "password";

                    password.type = isPassword ? "text" : "password";

                    this.classList.toggle("fa-eye");
                    this.classList.toggle("fa-eye-slash");
                    this.classList.toggle("active");
                });
            </script>


            <button type="submit">Login</button>
        </form>

        <div class="links">
            <a href="../auth/register.php">Don't have an account? Register</a><br>
            <!-- <a href="../forgot-password.php">Forgot Password?</a> -->
        </div>
    </div>
</body>

</html>