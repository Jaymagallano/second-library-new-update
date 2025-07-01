<?php
// Include necessary files
require_once "../config.php";
require_once "../admin_auth.php";

// Verify admin session
if (!verify_admin_session()) {
    header("Location: ../admin_login.php");
    exit;
}

// Log this page access
log_admin_activity($_SESSION["user_id"], 'add_user_page_access', $conn);

$username = $email = $full_name = "";
$username_err = $email_err = $full_name_err = $role_err = $password_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', trim($_POST["username"]))) {
        $username_err = "Username can only contain letters, numbers, and underscores.";
    } else {
        // Prepare a select statement
        $sql = "SELECT id FROM users WHERE username = ?";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $param_username);
            $param_username = trim($_POST["username"]);
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    $username_err = "This username is already taken.";
                } else {
                    $username = trim($_POST["username"]);
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address.";
    } else {
        // Check if email already exists
        $sql = "SELECT id FROM users WHERE email = ?";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $param_email);
            $param_email = trim($_POST["email"]);
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    $email_err = "This email is already registered.";
                } else {
                    $email = trim($_POST["email"]);
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // Validate full name
    if (empty(trim($_POST["full_name"]))) {
        $full_name_err = "Please enter the full name.";
    } else {
        $full_name = trim($_POST["full_name"]);
    }
    
    // Validate role
    if (empty($_POST["role_id"])) {
        $role_err = "Please select a role.";
    } else {
        $role_id = $_POST["role_id"];
    }
    
    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Check input errors before inserting in database
    if (empty($username_err) && empty($email_err) && empty($full_name_err) && empty($role_err) && empty($password_err)) {

        // Prepare an insert statement
        $sql = "INSERT INTO users (username, email, password, full_name, role_id, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "ssssi", $param_username, $param_email, $param_password, $param_full_name, $param_role_id);

            $param_username = $username;
            $param_email = $email;
            $param_password = password_hash($password, PASSWORD_DEFAULT);
            $param_full_name = $full_name;
            $param_role_id = $role_id;
            
            if (mysqli_stmt_execute($stmt)) {
                // Log the user creation
                log_admin_activity($_SESSION["user_id"], 'user_created', $conn, "Created user: $username");
                
                $_SESSION['success_message'] = "User created successfully!";
                header("location: users.php");
                exit();
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Get available roles from roles table
$roles = [];
$sql = "SELECT id, name FROM roles ORDER BY id";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $roles[] = $row;
    }
}

// Include header
$page_title = "Add New User";
include "../admin/includes/header.php";
?>

<div class="main-content responsive-container">
    <div class="header">
        <h1><i class="fas fa-user-plus"></i> Add New User</h1>
        <div class="header-actions">
            <button class="btn-minimal" onclick="location.href='users.php'">
                <i class="fas fa-arrow-left"></i> Back to Users
            </button>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username; ?>">
                            <div class="invalid-feedback"><?php echo $username_err; ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>">
                            <div class="invalid-feedback"><?php echo $email_err; ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" class="form-control <?php echo (!empty($full_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $full_name; ?>">
                    <div class="invalid-feedback"><?php echo $full_name_err; ?></div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Role <span class="text-danger">*</span></label>
                            <select name="role_id" class="form-control <?php echo (!empty($role_err)) ? 'is-invalid' : ''; ?>">
                                <option value="">Select Role</option>
                                <?php foreach ($roles as $role_option): ?>
                                    <option value="<?php echo $role_option['id']; ?>" <?php echo (isset($role_id) && $role_id == $role_option['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($role_option['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback"><?php echo $role_err; ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                            <div class="invalid-feedback"><?php echo $password_err; ?></div>
                            <small class="form-text text-muted">Password must be at least 6 characters long.</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Create User
                    </button>
                    <button type="button" class="btn-minimal" onclick="location.href='users.php'">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Add User Form Styles */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 6px;
    display: block;
}

.form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.form-control:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-control.is-invalid {
    border-color: #ef4444;
}

.invalid-feedback {
    color: #ef4444;
    font-size: 12px;
    margin-top: 4px;
    display: block;
}

.text-danger {
    color: #ef4444;
}

.form-text {
    font-size: 12px;
    color: #6b7280;
    margin-top: 4px;
}

.form-actions {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    gap: 12px;
}

.row {
    display: flex;
    margin: 0 -10px;
}

.col-md-6 {
    flex: 0 0 50%;
    padding: 0 10px;
}

@media (max-width: 768px) {
    .row {
        flex-direction: column;
        margin: 0;
    }
    
    .col-md-6 {
        flex: none;
        padding: 0;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions button {
        width: 100%;
    }
}
</style>

<?php include "../admin/includes/footer.php"; ?>
