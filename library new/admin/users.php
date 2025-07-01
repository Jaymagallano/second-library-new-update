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
log_admin_activity($_SESSION["user_id"], 'users_page_access', $conn);

// Initialize variables
$users = [];
$total_users = 0;
$search = "";
$role_filter = "";
$status_filter = "all";
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Handle search and filters
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    if (isset($_GET['search'])) {
        $search = trim($_GET['search']);
    }
    if (isset($_GET['role'])) {
        $role_filter = trim($_GET['role']);
    }
    if (isset($_GET['status'])) {
        $status_filter = trim($_GET['status']);
    }
}

// Handle user actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security error: Invalid token");
    }
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'activate':
                if (isset($_POST['user_id'])) {
                    $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                    $stmt->bind_param("i", $_POST['user_id']);
                    if ($stmt->execute()) {
                        log_admin_activity($_SESSION["user_id"], 'user_activated', $conn);
                    }
                    $stmt->close();
                }
                break;
                
            case 'deactivate':
                if (isset($_POST['user_id'])) {
                    $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
                    $stmt->bind_param("i", $_POST['user_id']);
                    if ($stmt->execute()) {
                        log_admin_activity($_SESSION["user_id"], 'user_deactivated', $conn);
                    }
                    $stmt->close();
                }
                break;
                
            case 'delete':
                if (isset($_POST['user_id'])) {
                    // Start transaction
                    $conn->begin_transaction();
                    
                    try {
                        $user_id = $_POST['user_id'];
                        
                        // Check if user has borrowings
                        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM borrowings WHERE user_id = ?");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $row = $result->fetch_assoc();
                        $stmt->close();
                        
                        if ($row['count'] > 0) {
                            throw new Exception("Cannot delete user with active borrowings");
                        }
                        
                        // Check if user is admin
                        $stmt = $conn->prepare("SELECT role_id FROM users WHERE id = ?");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $user = $result->fetch_assoc();
                        $stmt->close();
                        
                        if (!$user || $user['role_id'] == 1) {
                            throw new Exception("Cannot delete admin users");
                        }
                        
                        // Delete related notifications first
                        $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $stmt->close();
                        
                        // Delete related reservations
                        $stmt = $conn->prepare("DELETE FROM reservations WHERE user_id = ?");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $stmt->close();
                        
                        // Delete related activity logs
                        $stmt = $conn->prepare("DELETE FROM user_activity_log WHERE user_id = ?");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $stmt->close();
                        
                        // Finally delete the user
                        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        
                        if ($stmt->affected_rows > 0) {
                            // Commit transaction
                            $conn->commit();
                            log_admin_activity($_SESSION["user_id"], 'user_deleted', $conn);
                            $_SESSION['success_message'] = "User deleted successfully";
                        } else {
                            throw new Exception("Failed to delete user");
                        }
                        $stmt->close();
                        
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        $conn->rollback();
                        $_SESSION['error_message'] = $e->getMessage();
                    }
                }
                break;
        }
        
        // Redirect to prevent form resubmission
        header("Location: users.php");
        exit;
    }
}

// Build query based on filters
$query = "SELECT u.*, r.name as role_name FROM users u 
          JOIN roles r ON u.role_id = r.id 
          WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM users u 
                JOIN roles r ON u.role_id = r.id 
                WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $search_param = "%$search%";
    $query .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.full_name LIKE ?)";
    $count_query .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.full_name LIKE ?)";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($role_filter)) {
    $query .= " AND u.role_id = ?";
    $count_query .= " AND u.role_id = ?";
    $params[] = $role_filter;
    $types .= "i";
}

if ($status_filter != "all") {
    $query .= " AND u.status = ?";
    $count_query .= " AND u.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Get total count for pagination
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$total_users = $row['total'];
$stmt->close();

// Add pagination to query
$query .= " ORDER BY u.id DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

// Get users
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();

// Get roles for filter
$roles = [];
$stmt = $conn->prepare("SELECT id, name FROM roles ORDER BY id");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $roles[] = $row;
}
$stmt->close();

// Calculate pagination
$total_pages = ceil($total_users / $per_page);

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Include header
$page_title = "User Management";
include "../admin/includes/header.php";
?>

<div class="main-content responsive-container">
    <div class="header">
        <h1><i class="fas fa-users"></i> User Management</h1>
        <div class="header-actions">
            <a href="add_user.php" class="btn-minimal">
                <i class="fas fa-plus"></i> Add User
            </a>
        </div>
    </div>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?php 
                echo $_SESSION['success_message']; 
                unset($_SESSION['success_message']);
            ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <?php 
                echo $_SESSION['error_message']; 
                unset($_SESSION['error_message']);
            ?>
        </div>
    <?php endif; ?>
    
    <div class="card card-responsive">
        <div class="card-header">
            <h2>Users List</h2>
            <div class="card-tools">
                <form method="GET" action="" class="search-form">
                    <div class="form-responsive">
                        <div class="form-group">
                            <input type="text" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <select name="role" class="form-control">
                                <option value="">All Roles</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>" <?php echo ($role_filter == $role['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($role['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <select name="status" class="form-control">
                                <option value="all" <?php echo ($status_filter == 'all') ? 'selected' : ''; ?>>All Status</option>
                                <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($status_filter == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn-minimal">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($users)): ?>
                <div class="no-data">
                    <i class="fas fa-users"></i>
                    <p>No users found matching your criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge badge-role-<?php echo $user['role_id']; ?>">
                                            <?php echo htmlspecialchars($user['role_name']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $user['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                    <td class="actions">
                                        <!-- Edit button -->
                                        <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn-sm btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($user['status'] == 'active'): ?>
                                            <!-- Deactivate button -->
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="action" value="deactivate">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn-sm btn-warning" title="Deactivate" onclick="return confirm('Are you sure you want to deactivate this user? They will not be able to log in until reactivated.');">
                                                    <i class="fas fa-user-slash"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <!-- Activate button -->
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="action" value="activate">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn-sm btn-success" title="Activate" onclick="return confirm('Are you sure you want to activate this user? They will be able to log in and access the system.');">
                                                    <i class="fas fa-user-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($user['role_id'] != 1): // Don't allow deleting admin users ?>
                                            <!-- Delete button -->
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn-sm btn-danger" title="Delete" onclick="return confirm('⚠️ WARNING: This will permanently delete the user and all associated data. This action cannot be undone. Are you absolutely sure?');">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($total_pages > 1): ?>
            <div class="card-footer">
                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        Showing <?php echo (($page - 1) * $per_page) + 1; ?> to
                        <?php echo min($page * $per_page, $total_users); ?> of
                        <?php echo $total_users; ?> users
                    </div>
                    <nav aria-label="User pagination">
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($role_filter) ? '&role=' . urlencode($role_filter) : ''; ?><?php echo $status_filter != 'all' ? '&status=' . urlencode($status_filter) : ''; ?>">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($role_filter) ? '&role=' . urlencode($role_filter) : ''; ?><?php echo $status_filter != 'all' ? '&status=' . urlencode($status_filter) : ''; ?>">
                                        <i class="fas fa-angle-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($role_filter) ? '&role=' . urlencode($role_filter) : ''; ?><?php echo $status_filter != 'all' ? '&status=' . urlencode($status_filter) : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($role_filter) ? '&role=' . urlencode($role_filter) : ''; ?><?php echo $status_filter != 'all' ? '&status=' . urlencode($status_filter) : ''; ?>">
                                        <i class="fas fa-angle-right"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($role_filter) ? '&role=' . urlencode($role_filter) : ''; ?><?php echo $status_filter != 'all' ? '&status=' . urlencode($status_filter) : ''; ?>">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* User Management - Clean Professional Styles */
/* Badge styles - Clean and professional */
.badge {
    display: inline-block;
    padding: 4px 8px;
    font-size: 11px;
    font-weight: 500;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 4px;
    border: 1px solid transparent;
}
.badge-success {
    background-color: #e8f5e8;
    color: #2d5a2d;
    border-color: #c3e6c3;
}
.badge-secondary {
    background-color: #f0f2f5;
    color: #4a5568;
    border-color: #d1d5db;
}
/* Role-specific badge colors - Professional palette */
.badge-role-1 { background-color: #fef2f2; color: #b91c1c; border-color: #fecaca; } /* Administrator */
.badge-role-2 { background-color: #eff6ff; color: #1e40af; border-color: #bfdbfe; } /* Librarian */
.badge-role-3 { background-color: #f0fdf4; color: #166534; border-color: #bbf7d0; } /* Patron */
.badge-role-4 { background-color: #fffbeb; color: #d97706; border-color: #fed7aa; } /* Cataloger */
.badge-role-5 { background-color: #f0f9ff; color: #0369a1; border-color: #bae6fd; } /* Acquisitions Manager */
.badge-role-6 { background-color: #f8fafc; color: #475569; border-color: #cbd5e1; } /* Systems Administrator */
.badge-role-7 { background-color: #faf5ff; color: #7c3aed; border-color: #ddd6fe; } /* Researcher/Analyst */
.badge-role-8 { background-color: #ecfdf5; color: #059669; border-color: #a7f3d0; } /* Book Keeper */
.badge-role-9 { background-color: #fef7ff; color: #a21caf; border-color: #f3e8ff; } /* Event Planner */
.badge-role-10 { background-color: #f1f5f9; color: #334155; border-color: #e2e8f0; } /* Help Desk Staff */
/* Table styles */
.table {
    width: 100%;
    margin-bottom: 1rem;
    color: #212529;
    border-collapse: collapse;
}
.table th,
.table td {
    padding: 12px;
    vertical-align: middle;
    border-top: 1px solid #dee2e6;
}
.table thead th {
    vertical-align: bottom;
    border-bottom: 2px solid #e2e8f0;
    background-color: #f8fafc;
    font-weight: 600;
    color: #374151;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.table-striped tbody tr:nth-of-type(odd) {
    background-color: #f9fafb;
}
.table tbody tr:hover {
    background-color: #f3f4f6;
    transition: background-color 0.2s ease;
}
.table-responsive {
    display: block;
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
/* Button styles - Clean Professional */
.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
    line-height: 1.4;
    border-radius: 4px;
    border: 1px solid transparent;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 500;
    transition: all 0.2s ease;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}
.btn-minimal {
    background-color: #ffffff;
    color: #374151;
    border: 1px solid #d1d5db;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    font-weight: 500;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}
.btn-minimal:hover {
    background-color: #f9fafb;
    border-color: #9ca3af;
    color: #374151;
    text-decoration: none;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
.btn-primary {
    background-color: #3b82f6;
    color: #ffffff;
    border-color: #3b82f6;
}
.btn-primary:hover {
    background-color: #2563eb;
    border-color: #2563eb;
    box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
}
.btn-warning {
    background-color: #f59e0b;
    color: #ffffff;
    border-color: #f59e0b;
}
.btn-warning:hover {
    background-color: #d97706;
    border-color: #d97706;
    box-shadow: 0 2px 4px rgba(245, 158, 11, 0.3);
}
.btn-success {
    background-color: #10b981;
    color: #ffffff;
    border-color: #10b981;
}
.btn-success:hover {
    background-color: #059669;
    border-color: #059669;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
}
.btn-danger {
    background-color: #ef4444;
    color: #ffffff;
    border-color: #ef4444;
}
.btn-danger:hover {
    background-color: #dc2626;
    border-color: #dc2626;
    box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
}
.d-inline {
    display: inline !important;
}
.actions {
    white-space: nowrap;
}
.actions .btn-sm {
    margin-right: 5px;
}
.actions form {
    display: inline-block;
}
.no-data {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
}
.no-data i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}
.pagination-wrapper {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}
.pagination-info {
    color: #6c757d;
    font-size: 14px;
}
.pagination {
    margin: 0;
}
.pagination .page-link {
    color: #495057;
    border-color: #dee2e6;
}
.pagination .page-item.active .page-link {
    background-color: #3b82f6;
    border-color: #3b82f6;
    color: #ffffff;
}
.pagination .page-link:hover {
    color: #2563eb;
    background-color: #f3f4f6;
    border-color: #d1d5db;
}
@media (max-width: 768px) {
    .pagination-wrapper {
        flex-direction: column;
        text-align: center;
    }
    .actions .btn-sm {
        margin: 2px;
        padding: 4px 8px;
    }
    .table-responsive {
        font-size: 14px;
    }
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Enhanced confirmation dialogs
    const deleteButtons = document.querySelectorAll('.btn-danger[title="Delete"]');
    const deactivateButtons = document.querySelectorAll('.btn-warning[title="Deactivate"]');
    const activateButtons = document.querySelectorAll('.btn-success[title="Activate"]');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const form = this.closest('form');
            if (confirm('⚠️ WARNING: This will permanently delete the user and all associated data.\n\nThis action cannot be undone. Are you absolutely sure?')) {
                form.submit();
            }
        });
    });
    
    deactivateButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const form = this.closest('form');
            if (confirm('Are you sure you want to deactivate this user?\n\nThey will not be able to log in until reactivated.')) {
                form.submit();
            }
        });
    });
    
    activateButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const form = this.closest('form');
            if (confirm('Are you sure you want to activate this user?\n\nThey will be able to log in and access the system.')) {
                form.submit();
            }
        });
    });
});
</script>
<?php include "../admin/includes/footer.php"; ?>