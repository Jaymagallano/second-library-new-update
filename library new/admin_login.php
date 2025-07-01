<?php
// Include database connection and admin authentication
require_once "config.php";
require_once "admin_auth.php";
require_once "includes/user_logger.php";

// Ensure admin log table exists
ensure_admin_log_table($conn);

// Define variables and initialize with empty values
$email = $password = "";
$email_err = $password_err = $login_err = "";

// Check for brute force attempts
function check_brute_force($email, $ip, $conn) {
    // Count failed attempts in the last 30 minutes
    $stmt = $conn->prepare("SELECT COUNT(*) as attempts FROM admin_login_attempts 
                           WHERE (email = ? OR ip_address = ?) 
                           AND success = 0 
                           AND attempt_time > DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
    $stmt->bind_param("ss", $email, $ip);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    // If more than 5 failed attempts, block access
    return ($row['attempts'] >= 5);
}

// Log login attempt
function log_login_attempt($email, $ip, $success, $conn) {
    $stmt = $conn->prepare("INSERT INTO admin_login_attempts (email, ip_address, success) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $email, $ip, $success);
    $stmt->execute();
    $stmt->close();
}

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get client IP address
    $ip = $_SERVER['REMOTE_ADDR'];
    
    // Check if email is empty
    if(empty(trim($_POST["email"]))) {
        $email_err = "Please enter your email.";
    } else {
        $email = trim($_POST["email"]);
    }
    
    // Check if password is empty
    if(empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate credentials
    if(empty($email_err) && empty($password_err)) {
        // Check for brute force attempts
        if(check_brute_force($email, $ip, $conn)) {
            $login_err = "Too many failed login attempts. Please try again later.";
        } else {
            // Attempt to log in
            $result = secure_admin_login($email, $password, $conn);
            
            if($result['success']) {
                // Log successful attempt
                log_login_attempt($email, $ip, 1, $conn);
                
                // Also log to user_activity_log for dashboard stats - direct query
                $username_esc = $conn->real_escape_string($_SESSION["username"]);
                $ip_esc = $conn->real_escape_string($ip);
                $user_agent = $conn->real_escape_string($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
                $conn->query("INSERT INTO user_activity_log 
                            (user_id, username, action, action_details, module, ip_address, user_agent, status) 
                            VALUES 
                            (NULL, '$username_esc', 'login', 'Admin logged in successfully', 'authentication', '$ip_esc', '$user_agent', 'success')");
                
                // Redirect to admin dashboard
                header("location: admin_dashboard.php");
                exit;
            } else {
                // Log failed attempt
                log_login_attempt($email, $ip, 0, $conn);
                
                // Also log to user_activity_log for dashboard stats - direct query
                $email_esc = $conn->real_escape_string($email);
                $ip_esc = $conn->real_escape_string($ip);
                $user_agent = $conn->real_escape_string($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
                $conn->query("INSERT INTO user_activity_log 
                            (user_id, username, action, action_details, module, ip_address, user_agent, status) 
                            VALUES 
                            (NULL, '$email_esc', 'login_failed', 'Admin login failed', 'authentication', '$ip_esc', '$user_agent', 'failure')");
                
                $login_err = $result['message'];
            }
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
    <title>Admin Portal - Library Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/validation.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 25%, #2c3e50 50%, #1a252f 75%, #2c3e50 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Professional background pattern */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background:
                radial-gradient(circle at 25% 25%, rgba(52, 73, 94, 0.4) 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, rgba(44, 62, 80, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 50% 50%, rgba(26, 37, 47, 0.2) 0%, transparent 50%);
            z-index: 1;
        }

        /* Subtle geometric pattern overlay */
        body::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image:
                linear-gradient(45deg, rgba(255, 255, 255, 0.02) 25%, transparent 25%),
                linear-gradient(-45deg, rgba(255, 255, 255, 0.02) 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, rgba(255, 255, 255, 0.02) 75%),
                linear-gradient(-45deg, transparent 75%, rgba(255, 255, 255, 0.02) 75%);
            background-size: 60px 60px;
            background-position: 0 0, 0 30px, 30px -30px, -30px 0px;
            z-index: 1;
            opacity: 0.5;
        }

        .admin-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            box-shadow:
                0 20px 40px rgba(0, 0, 0, 0.2),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 340px;
            position: relative;
            z-index: 10;
            backdrop-filter: blur(20px);
        }

        .admin-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 50%, #2c3e50 100%);
            padding: 25px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .admin-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="admin-pattern" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="12" cy="12" r="1.5" fill="rgba(255,255,255,0.05)"/><circle cx="18" cy="6" r="0.8" fill="rgba(255,255,255,0.08)"/></pattern></defs><rect width="100" height="100" fill="url(%23admin-pattern)"/></svg>');
            opacity: 0.3;
        }

        .admin-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(231, 76, 60, 0.9);
            color: white;
            padding: 6px 12px;
            border-radius: 16px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 15px;
            position: relative;
            z-index: 2;
        }

        .admin-title {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            font-weight: 700;
            color: white;
            margin-bottom: 5px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 2;
        }

        .admin-subtitle {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 400;
            position: relative;
            z-index: 2;
        }

        .admin-form {
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
            color: #2c3e50;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            font-size: 14px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            background: #f8f9fa;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-input:focus {
            outline: none;
            border-color: #34495e;
            background: white;
            box-shadow: 0 0 0 4px rgba(52, 73, 94, 0.15);
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
            color: #6c757d;
            font-size: 16px;
            transition: color 0.3s;
        }

        .password-toggle:hover {
            color: #34495e;
        }

        .admin-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #34495e 0%, #2c3e50 50%, #1a252f 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 6px 15px rgba(44, 62, 80, 0.4);
            margin-top: 8px;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .admin-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .admin-btn:hover::before {
            left: 100%;
        }

        .admin-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(44, 62, 80, 0.5);
            background: linear-gradient(135deg, #3d566e 0%, #34495e 50%, #2c3e50 100%);
        }

        .admin-btn:active {
            transform: translateY(-1px);
        }

        .security-features {
            margin-top: 20px;
            padding: 15px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }

        .security-title {
            font-size: 12px;
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .security-list {
            list-style: none;
            font-size: 10px;
            color: #6c757d;
        }

        .security-list li {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 4px;
        }

        .security-list i {
            color: #28a745;
            font-size: 8px;
        }

        .form-footer {
            text-align: center;
            margin-top: 18px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
        }

        .form-footer p {
            color: #6c757d;
            font-size: 12px;
        }

        .form-footer a {
            color: #34495e;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }

        .form-footer a:hover {
            color: #2c3e50;
            text-decoration: underline;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 12px;
            border-left: 3px solid;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border-left-color: #dc3545;
        }

        .invalid-feedback {
            color: #dc3545;
            font-size: 11px;
            margin-top: 3px;
            font-style: italic;
        }

        /* Responsive design */
        @media (max-width: 480px) {
            .admin-container {
                margin: 10px;
                border-radius: 12px;
                max-width: 300px;
            }

            .admin-header {
                padding: 20px 15px;
            }

            .admin-form {
                padding: 20px 15px;
            }

            .admin-title {
                font-size: 20px;
            }

            .admin-subtitle {
                font-size: 12px;
            }
        }

        /* Loading animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <div class="admin-badge">
                <i class="fas fa-shield-halved"></i>
                Admin Portal
            </div>
            <h1 class="admin-title">🛡️ Secure Access</h1>
            <p class="admin-subtitle">Administrative Control Panel</p>
        </div>

        <div class="admin-form">
            <?php
            if(!empty($login_err)){
                echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i>' . $login_err . '</div>';
            }
            ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" autocomplete="off">
                <div class="form-group">
                    <label class="form-label">Administrator Email</label>
                    <input type="email" name="email" class="form-input <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>" placeholder="Enter your admin email">
                    <?php if(!empty($email_err)): ?>
                        <div class="invalid-feedback"><?php echo $email_err; ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label">Secure Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" class="form-input <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" placeholder="Enter your secure password">
                        <span class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </span>
                    </div>
                    <?php if(!empty($password_err)): ?>
                        <div class="invalid-feedback"><?php echo $password_err; ?></div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="admin-btn">
                    <i class="fas fa-key"></i> Authenticate Access
                </button>
            </form>

            <div class="security-features">
                <div class="security-title">
                    <i class="fas fa-lock"></i>
                    Security Features Active
                </div>
                <ul class="security-list">
                    <li><i class="fas fa-check"></i> Brute force protection</li>
                    <li><i class="fas fa-check"></i> IP address monitoring</li>
                    <li><i class="fas fa-check"></i> Session encryption</li>
                    <li><i class="fas fa-check"></i> Activity logging</li>
                </ul>
            </div>

            <div class="form-footer">
                <p>Need user access? <a href="login.php">Return to User Login</a></p>
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

        // Enhanced security features
        document.addEventListener('DOMContentLoaded', function() {
            // Enhanced form security
            const form = document.querySelector('form');
            const submitBtn = document.querySelector('.admin-btn');
            const startTime = Date.now();

            // Add loading state to button
            form.addEventListener('submit', function(e) {
                const elapsedTime = Date.now() - startTime;

                // Bot detection - form submitted too quickly
                if (elapsedTime < 3000) {
                    e.preventDefault();
                    showAlert('Please wait a moment before submitting.', 'warning');
                    return;
                }

                // Add loading state
                submitBtn.innerHTML = '<div class="loading"></div> Authenticating...';
                submitBtn.disabled = true;

                // Add timing data for server-side analysis
                const timingField = document.createElement('input');
                timingField.type = 'hidden';
                timingField.name = 'form_timing';
                timingField.value = elapsedTime;
                form.appendChild(timingField);
            });

            // Enhanced input validation
            const emailInput = document.querySelector('input[type="email"]');
            const passwordInput = document.querySelector('input[type="password"]');

            emailInput.addEventListener('input', function() {
                validateEmail(this);
            });

            passwordInput.addEventListener('input', function() {
                validatePassword(this);
            });

            // Security monitoring
            let suspiciousActivity = 0;

            // Monitor for suspicious keyboard activity
            document.addEventListener('keydown', function(e) {
                // Block developer tools
                if (e.key === 'F12' ||
                    (e.ctrlKey && e.shiftKey && e.key === 'I') ||
                    (e.ctrlKey && e.shiftKey && e.key === 'J') ||
                    (e.ctrlKey && e.key === 'U')) {
                    e.preventDefault();
                    suspiciousActivity++;
                    if (suspiciousActivity > 3) {
                        showAlert('Suspicious activity detected. Session will be logged.', 'danger');
                    }
                }
            });

            // Block right-click context menu
            document.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                suspiciousActivity++;
            });

            // Monitor for automated tools
            let mouseMovements = 0;
            document.addEventListener('mousemove', function() {
                mouseMovements++;
            });

            // Check for human-like behavior after 5 seconds
            setTimeout(function() {
                if (mouseMovements < 5) {
                    console.warn('Potential automated access detected');
                }
            }, 5000);
        });

        // Validation functions
        function validateEmail(input) {
            const email = input.value.trim();
            const isValid = email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/);

            if (email && !isValid) {
                input.style.borderColor = '#dc3545';
                input.style.backgroundColor = '#fff5f5';
            } else if (email && isValid) {
                input.style.borderColor = '#28a745';
                input.style.backgroundColor = '#f8fff9';
            } else {
                input.style.borderColor = '#e1e8ed';
                input.style.backgroundColor = '#f8f9fa';
            }
        }

        function validatePassword(input) {
            const password = input.value;
            const strength = calculatePasswordStrength(password);

            if (password.length > 0) {
                if (strength < 3) {
                    input.style.borderColor = '#dc3545';
                    input.style.backgroundColor = '#fff5f5';
                } else if (strength < 4) {
                    input.style.borderColor = '#ffc107';
                    input.style.backgroundColor = '#fffbf0';
                } else {
                    input.style.borderColor = '#28a745';
                    input.style.backgroundColor = '#f8fff9';
                }
            } else {
                input.style.borderColor = '#e1e8ed';
                input.style.backgroundColor = '#f8f9fa';
            }
        }

        function calculatePasswordStrength(password) {
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            return strength;
        }

        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${message}`;

            const form = document.querySelector('.admin-form');
            form.insertBefore(alertDiv, form.firstChild);

            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }
    </script>
    <script src="assets/js/email-validator.js" defer></script>
</body>
</html>