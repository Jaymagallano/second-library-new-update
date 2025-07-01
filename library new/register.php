<?php
// Start session
session_start();

// Check if user is already logged in
if(isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: index.php");
    exit();
}

// Include database connection
require_once "config.php";

// Define variables and initialize with empty values
$username = $email = $password = $confirm_password = $full_name = "";
$username_err = $email_err = $password_err = $confirm_password_err = $full_name_err = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate full name
    if(empty(trim($_POST["full_name"]))) {
        $full_name_err = "Please enter your full name.";
    } else {
        $full_name = trim($_POST["full_name"]);
    }
    
    // Validate username
    if(empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } else {
        // Prepare a select statement
        $sql = "SELECT id FROM users WHERE username = ?";
        
        if($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("s", $param_username);
            
            // Set parameters
            $param_username = trim($_POST["username"]);
            
            // Attempt to execute the prepared statement
            if($stmt->execute()) {
                // Store result
                $stmt->store_result();
                
                if($stmt->num_rows == 1) {
                    $username_err = "This username is already taken.";
                } else {
                    $username = trim($_POST["username"]);
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            $stmt->close();
        }
    }
    
    // Validate email
    if(empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email.";
    } else {
        // Check email format
        $email_input = trim($_POST["email"]);
        if(!filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
            $email_err = "Please enter a valid email address.";
        } else {
            // Prepare a select statement
            $sql = "SELECT id FROM users WHERE email = ?";
            
            if($stmt = $conn->prepare($sql)) {
                // Bind variables to the prepared statement as parameters
                $stmt->bind_param("s", $param_email);
                
                // Set parameters
                $param_email = $email_input;
                
                // Attempt to execute the prepared statement
                if($stmt->execute()) {
                    // Store result
                    $stmt->store_result();
                    
                    if($stmt->num_rows == 1) {
                        $email_err = "This email is already registered.";
                    } else {
                        $email = $email_input;
                    }
                } else {
                    echo "Oops! Something went wrong. Please try again later.";
                }

                // Close statement
                $stmt->close();
            }
        }
    }
    
    // Validate password
    if(empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";     
    } elseif(strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate confirm password
    if(empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";     
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if(empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Password did not match.";
        }
    }
    
    // Check input errors before inserting in database
    if(empty($username_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err) && empty($full_name_err)) {
        
        // Prepare an insert statement
        $sql = "INSERT INTO users (username, password, email, full_name, role_id) VALUES (?, ?, ?, ?, ?)";
         
        if($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("ssssi", $param_username, $param_password, $param_email, $param_full_name, $param_role_id);
            
            // Set parameters
            $param_username = $username;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // Creates a password hash
            $param_email = $email;
            $param_full_name = $full_name;
            $param_role_id = 3; // Default role for patrons/members
            
            // Attempt to execute the prepared statement
            if($stmt->execute()) {
                // Redirect to login page
                header("location: login.php?registered=success");
                exit();
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
    <title>Register - Library Management System</title>
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
            background: linear-gradient(135deg, #0f3460 0%, #16213e 50%, #1a1a2e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }

        /* Elegant book pattern overlay */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image:
                radial-gradient(circle at 20% 80%, rgba(139, 69, 19, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 215, 0, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(160, 82, 45, 0.05) 0%, transparent 50%);
            z-index: 1;
        }

        .register-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow:
                0 15px 30px rgba(0, 0, 0, 0.3),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 380px;
            position: relative;
            z-index: 10;
            backdrop-filter: blur(10px);
        }

        .register-header {
            background: linear-gradient(135deg, #CD853F 0%, #A0522D 50%, #8B4513 100%);
            padding: 25px 20px;
            text-align: center;
            position: relative;
        }

        .register-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="library" x="0" y="0" width="25" height="25" patternUnits="userSpaceOnUse"><circle cx="5" cy="5" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="15" cy="15" r="1.5" fill="rgba(255,255,255,0.05)"/><circle cx="20" cy="8" r="0.8" fill="rgba(255,255,255,0.08)"/></pattern></defs><rect width="100" height="100" fill="url(%23library)"/></svg>');
            opacity: 0.3;
        }

        .register-title {
            font-size: 20px;
            font-weight: 700;
            color: white;
            margin-bottom: 5px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 2;
        }

        .register-subtitle {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.9);
            font-style: italic;
            position: relative;
            z-index: 2;
        }

        .register-form {
            padding: 25px 20px;
            background: white;
        }

        .form-row {
            display: flex;
            gap: 12px;
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 15px;
            position: relative;
            flex: 1;
        }

        .form-label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-input {
            width: 100%;
            padding: 8px 12px;
            font-size: 13px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            background: #f8f9fa;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-input:focus {
            outline: none;
            border-color: #8B4513;
            background: white;
            box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
            transform: translateY(-1px);
        }

        .password-wrapper {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            font-size: 14px;
            transition: color 0.3s;
        }

        .password-toggle:hover {
            color: #8B4513;
        }

        .register-btn {
            width: 100%;
            padding: 10px;
            background: linear-gradient(135deg, #CD853F 0%, #A0522D 50%, #8B4513 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(139, 69, 19, 0.3);
            margin-top: 10px;
        }

        .register-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(139, 69, 19, 0.4);
        }

        .register-btn:active {
            transform: translateY(-1px);
        }

        .form-footer {
            text-align: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }

        .form-footer p {
            color: #666;
            font-size: 11px;
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
            padding: 8px 12px;
            border-radius: 6px;
            margin-bottom: 12px;
            font-size: 11px;
            border-left: 3px solid;
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border-left-color: #dc3545;
        }

        .invalid-feedback {
            color: #dc3545;
            font-size: 10px;
            margin-top: 2px;
            font-style: italic;
        }

        /* Responsive design */
        @media (max-width: 480px) {
            .register-container {
                margin: 10px;
                border-radius: 12px;
                max-width: 320px;
            }

            .register-header {
                padding: 20px 15px;
            }

            .register-form {
                padding: 20px 15px;
            }

            .register-title {
                font-size: 18px;
            }

            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1 class="register-title">📖 Join Our Library</h1>
            <p class="register-subtitle">Create your account to access thousands of books</p>
        </div>

        <div class="register-form">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" autocomplete="off">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-input" value="<?php echo $full_name; ?>" placeholder="Enter your full name">
                    <?php if(!empty($full_name_err)): ?>
                        <div class="invalid-feedback"><?php echo $full_name_err; ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-input" value="<?php echo $username; ?>" placeholder="Choose username">
                    <?php if(!empty($username_err)): ?>
                        <div class="invalid-feedback"><?php echo $username_err; ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" value="<?php echo $email; ?>" placeholder="Enter your email">
                    <?php if(!empty($email_err)): ?>
                        <div class="invalid-feedback"><?php echo $email_err; ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" class="form-input" placeholder="Create password">
                        <span class="password-toggle" onclick="togglePassword('password', 'toggleIcon1')">
                            <i class="fas fa-eye" id="toggleIcon1"></i>
                        </span>
                    </div>
                    <?php if(!empty($password_err)): ?>
                        <div class="invalid-feedback"><?php echo $password_err; ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="confirm_password" id="confirm_password" class="form-input" placeholder="Confirm password">
                        <span class="password-toggle" onclick="togglePassword('confirm_password', 'toggleIcon2')">
                            <i class="fas fa-eye" id="toggleIcon2"></i>
                        </span>
                    </div>
                    <?php if(!empty($confirm_password_err)): ?>
                        <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="register-btn">
                    📝 Create Account
                </button>
            </form>

            <div class="form-footer">
                <p>Already have an account? <a href="login.php">Sign in here</a></p>
            </div>
        </div>
    </div>
    
    <script>
        // Function to toggle password visibility
        function togglePassword(fieldId, iconId) {
            const passwordField = document.getElementById(fieldId);
            const toggleIcon = document.getElementById(iconId);
            
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
    <script src="assets/js/loader.js" defer></script>
    <script src="assets/js/email-validator.js" defer></script>
    <script src="assets/js/security-enhancements.js" defer></script>
</body>
</html>