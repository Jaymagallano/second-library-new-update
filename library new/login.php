<?php
// Start session
session_start();

// Check if user is already logged in
if(isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: index.php");
    exit();
}

// Include database connection and user logger
require_once "config.php";
require_once "includes/user_logger.php";

// Define variables and initialize with empty values
$email = $password = "";
$email_err = $password_err = $login_err = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Check if email is empty
    if(empty(trim($_POST["email"]))) {
        $email_err = "Please enter your email.";
    } else {
        $email_input = trim($_POST["email"]);
        // Basic email validation
        if(!filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
            $email_err = "Please enter a valid email address.";
        } else {
            $email = $email_input;
        }
    }
    
    // Check if password is empty
    if(empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate credentials
    if(empty($email_err) && empty($password_err)) {
        // Prepare a select statement
        $sql = "SELECT id, username, email, password, role_id FROM users WHERE email = ?";
        
        if($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("s", $param_email);
            
            // Set parameters
            $param_email = $email;
            
            // Attempt to execute the prepared statement
            if($stmt->execute()) {
                // Store result
                $stmt->store_result();
                
                // Check if email exists, if yes then verify password
                if($stmt->num_rows == 1) {                    
                    // Bind result variables
                    $stmt->bind_result($id, $username, $email, $hashed_password, $role_id);
                    if($stmt->fetch()) {
                        if(password_verify($password, $hashed_password)) {
                            // Password is correct, no need to start a new session as it's already started
                            
                            // Store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"] = $id;
                            $_SESSION["username"] = $username;
                            $_SESSION["email"] = $email;
                            $_SESSION["role_id"] = $role_id;
                            
                            // Log successful login with direct query - escape values to prevent SQL injection
                            $ip = $conn->real_escape_string($_SERVER['REMOTE_ADDR']);
                            $username_esc = $conn->real_escape_string($username);
                            $user_agent = $conn->real_escape_string($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
                            $conn->query("INSERT INTO user_activity_log 
                                        (user_id, username, action, action_details, module, ip_address, user_agent, status) 
                                        VALUES 
                                        (NULL, '$username_esc', 'login', 'User logged in successfully', 'authentication', '$ip', '$user_agent', 'success')");
                            
                            // Redirect user to welcome page
                            header("location: index.php");
                            exit();
                        } else {
                            // Password is not valid
                            $login_err = "Invalid email or password.";
                            // Log failed login attempt with direct query
                            $ip = $conn->real_escape_string($_SERVER['REMOTE_ADDR']);
                            $email_esc = $conn->real_escape_string($email);
                            $user_agent = $conn->real_escape_string($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
                            $conn->query("INSERT INTO user_activity_log 
                                        (user_id, username, action, action_details, module, ip_address, user_agent, status) 
                                        VALUES 
                                        (NULL, '$email_esc', 'login_failed', 'Failed login attempt - invalid password', 'authentication', '$ip', '$user_agent', 'failure')");
                        }
                    }
                } else {
                    // Email doesn't exist
                    $login_err = "Invalid email or password.";
                    // Log failed login attempt with direct query
                    $ip = $conn->real_escape_string($_SERVER['REMOTE_ADDR']);
                    $email_esc = $conn->real_escape_string($email);
                    $user_agent = $conn->real_escape_string($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
                    $conn->query("INSERT INTO user_activity_log 
                                (user_id, username, action, action_details, module, ip_address, user_agent, status) 
                                VALUES 
                                (NULL, '$email_esc', 'login_failed', 'Failed login attempt - email not found', 'authentication', '$ip', '$user_agent', 'failure')");
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            $stmt->close();
        }
    }
    
    // Close connection
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Library Management System</title>
    <link rel="stylesheet" href="assets/css/validation.css">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Georgia', 'Times New Roman', serif;
        }

        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }

        /* Subtle book pattern overlay */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image:
                radial-gradient(circle at 25% 25%, rgba(255, 215, 0, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, rgba(139, 69, 19, 0.1) 0%, transparent 50%);
            z-index: 1;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow:
                0 15px 30px rgba(0, 0, 0, 0.3),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 320px;
            position: relative;
            z-index: 10;
            backdrop-filter: blur(10px);
        }

        .login-header {
            background: linear-gradient(135deg, #8B4513 0%, #A0522D 50%, #CD853F 100%);
            padding: 25px 20px;
            text-align: center;
            position: relative;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="books" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse"><rect width="2" height="20" fill="rgba(255,255,255,0.1)"/><rect x="5" width="3" height="20" fill="rgba(255,255,255,0.05)"/><rect x="12" width="2" height="20" fill="rgba(255,255,255,0.08)"/></pattern></defs><rect width="100" height="100" fill="url(%23books)"/></svg>');
            opacity: 0.3;
        }

        .login-title {
            font-size: 22px;
            font-weight: 700;
            color: white;
            margin-bottom: 5px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 2;
        }

        .login-subtitle {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.9);
            font-style: italic;
            position: relative;
            z-index: 2;
        }

        .login-form {
            padding: 25px 20px;
            background: white;
        }

        .form-group {
            margin-bottom: 18px;
            position: relative;
        }

        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-input {
            width: 100%;
            padding: 10px 15px;
            font-size: 14px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            background: #f8f9fa;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-input:focus {
            outline: none;
            border-color: #8B4513;
            background: white;
            box-shadow: 0 0 0 4px rgba(139, 69, 19, 0.1);
            transform: translateY(-2px);
        }

        .password-wrapper {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            font-size: 16px;
            transition: color 0.3s;
        }

        .password-toggle:hover {
            color: #8B4513;
        }

        .login-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #8B4513 0%, #A0522D 50%, #CD853F 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(139, 69, 19, 0.3);
            margin-top: 8px;
        }

        .login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(139, 69, 19, 0.4);
        }

        .login-btn:active {
            transform: translateY(-1px);
        }

        .form-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }

        .form-footer p {
            color: #666;
            font-size: 12px;
        }

        .form-footer a {
            color: #8B4513;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }

        .form-footer a:hover {
            color: #A0522D;
            text-decoration: underline;
        }

        .alert {
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 12px;
            border-left: 3px solid;
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border-left-color: #dc3545;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
            border-left-color: #28a745;
        }

        .invalid-feedback {
            color: #dc3545;
            font-size: 11px;
            margin-top: 3px;
            font-style: italic;
        }

        /* Responsive design */
        @media (max-width: 480px) {
            .login-container {
                margin: 10px;
                border-radius: 12px;
                max-width: 300px;
            }

            .login-header {
                padding: 20px 15px;
            }

            .login-form {
                padding: 20px 15px;
            }

            .login-title {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1 class="login-title">📚 Library Portal</h1>
            <p class="login-subtitle">Welcome back to your digital library</p>
        </div>

        <div class="login-form">
            <?php
            if(!empty($login_err)){
                echo '<div class="alert alert-danger">' . $login_err . '</div>';
            }
            if(isset($_GET['registered']) && $_GET['registered'] == 'success'){
                echo '<div class="alert alert-success">Registration successful! Please sign in to continue.</div>';
            }
            ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" autocomplete="off">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-input" value="<?php echo $email; ?>" placeholder="Enter your email">
                    <?php if(!empty($email_err)): ?>
                        <div class="invalid-feedback"><?php echo $email_err; ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" class="form-input" placeholder="Enter your password">
                        <span class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </span>
                    </div>
                    <?php if(!empty($password_err)): ?>
                        <div class="invalid-feedback"><?php echo $password_err; ?></div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="login-btn">
                    🔐 Sign In
                </button>
            </form>

            <div class="form-footer">
                <p>Don't have an account? <a href="register.php">Create one here</a></p>
            </div>
        </div>
    </div>
    
    <script>
        // Function to toggle password visibility
        function togglePassword() {
            const passwordField = document.getElementById("password");
            const toggleIcon = document.getElementById("toggleIcon");

            if (passwordField.type === "password") {
                passwordField.type = "text";
                toggleIcon.classList.remove("fa-eye");
                toggleIcon.classList.add("fa-eye-slash");
            } else {
                passwordField.type = "password";
                toggleIcon.classList.remove("fa-eye-slash");
                toggleIcon.classList.add("fa-eye");
            }
        }
    </script>
    <script src="assets/js/scripts.min.js" defer></script>
    <script src="assets/js/email-validator.js" defer></script>
    <script src="assets/js/security-enhancements.js" defer></script>
</body>
</html>