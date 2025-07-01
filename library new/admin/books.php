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
log_admin_activity($_SESSION["user_id"], 'books_page_access', $conn);

// Initialize variables
$books = [];
$total_books = 0;
$search = "";
$category_filter = "";
$status_filter = "all";
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Handle search and filters
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    if (isset($_GET['search'])) {
        $search = trim($_GET['search']);
    }
    if (isset($_GET['category'])) {
        $category_filter = trim($_GET['category']);
    }
    if (isset($_GET['status'])) {
        $status_filter = trim($_GET['status']);
    }
}

// Handle book actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security error: Invalid token");
    }
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete':
                if (isset($_POST['book_id'])) {
                    // Check if book has active borrowings
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM borrowings WHERE book_id = ? AND status = 'active'");
                    $stmt->bind_param("i", $_POST['book_id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    $stmt->close();
                    
                    if ($row['count'] > 0) {
                        $_SESSION['error_message'] = "Cannot delete book with active borrowings";
                    } else {
                        $stmt = $conn->prepare("DELETE FROM books WHERE id = ?");
                        $stmt->bind_param("i", $_POST['book_id']);
                        $stmt->execute();
                        if ($stmt->affected_rows > 0) {
                            log_admin_activity($_SESSION["user_id"], 'book_deleted', $conn);
                            $_SESSION['success_message'] = "Book deleted successfully";
                        } else {
                            $_SESSION['error_message'] = "Failed to delete book";
                        }
                        $stmt->close();
                    }
                }
                break;
                
            case 'update_status':
                if (isset($_POST['book_id']) && isset($_POST['status'])) {
                    $stmt = $conn->prepare("UPDATE books SET status = ? WHERE id = ?");
                    $stmt->bind_param("si", $_POST['status'], $_POST['book_id']);
                    $stmt->execute();
                    if ($stmt->affected_rows > 0) {
                        log_admin_activity($_SESSION["user_id"], 'book_status_updated', $conn);
                        $_SESSION['success_message'] = "Book status updated successfully";
                    } else {
                        $_SESSION['error_message'] = "Failed to update book status";
                    }
                    $stmt->close();
                }
                break;
        }
        
        // Redirect to prevent form resubmission
        header("Location: books.php");
        exit;
    }
}

// Build query based on filters
$query = "SELECT * FROM books WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM books WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $search_param = "%$search%";
    $query .= " AND (title LIKE ? OR author LIKE ? OR isbn LIKE ?)";
    $count_query .= " AND (title LIKE ? OR author LIKE ? OR isbn LIKE ?)";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($category_filter)) {
    $query .= " AND category = ?";
    $count_query .= " AND category = ?";
    $params[] = $category_filter;
    $types .= "s";
}

if ($status_filter != "all") {
    $query .= " AND status = ?";
    $count_query .= " AND status = ?";
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
$total_books = $row['total'];
$stmt->close();

// Add pagination to query
$query .= " ORDER BY id DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

// Get books
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $books[] = $row;
}
$stmt->close();

// Get categories for filter
$categories = [];
$stmt = $conn->prepare("SELECT DISTINCT category FROM books WHERE category IS NOT NULL ORDER BY category");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $categories[] = $row['category'];
}
$stmt->close();

// Calculate pagination
$total_pages = ceil($total_books / $per_page);

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Include header
$page_title = "Book Management";
include "../admin/includes/header.php";
?>

<div class="main-content responsive-container">
    <div class="header">
        <h1><i class="fas fa-book"></i> Book Management</h1>
        <div class="header-actions">
            <button class="btn-minimal" onclick="location.href='add_book.php'">
                <i class="fas fa-plus"></i> Add Book
            </button>
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
            <h2>Books List</h2>
            <div class="card-tools">
                <form method="GET" action="" class="search-form">
                    <div class="form-responsive">
                        <div class="form-group">
                            <input type="text" name="search" placeholder="Search books..." value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <select name="category" class="form-control">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>" <?php echo ($category_filter == $category) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <select name="status" class="form-control">
                                <option value="all" <?php echo ($status_filter == 'all') ? 'selected' : ''; ?>>All Status</option>
                                <option value="available" <?php echo ($status_filter == 'available') ? 'selected' : ''; ?>>Available</option>
                                <option value="borrowed" <?php echo ($status_filter == 'borrowed') ? 'selected' : ''; ?>>Borrowed</option>
                                <option value="reserved" <?php echo ($status_filter == 'reserved') ? 'selected' : ''; ?>>Reserved</option>
                                <option value="maintenance" <?php echo ($status_filter == 'maintenance') ? 'selected' : ''; ?>>Maintenance</option>
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
            <?php if (empty($books)): ?>
                <div class="no-data">
                    <i class="fas fa-book"></i>
                    <p>No books found matching your criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Author</th>
                                <th>ISBN</th>
                                <th>Category</th>
                                <th>Copies</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($books as $book): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($book['id']); ?></td>
                                    <td>
                                        <div class="book-title" title="<?php echo htmlspecialchars($book['title']); ?>">
                                            <?php echo htmlspecialchars($book['title']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="book-author" title="<?php echo htmlspecialchars($book['author']); ?>">
                                            <?php echo htmlspecialchars($book['author']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($book['isbn'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="category-badge">
                                            <?php echo htmlspecialchars($book['category'] ?? 'Uncategorized'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="copies-info">
                                            <span class="available"><?php echo $book['copies_available']; ?></span>
                                            <span class="separator">/</span>
                                            <span class="total"><?php echo $book['copies_total']; ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        $status_icon = '';
                                        switch ($book['status']) {
                                            case 'available':
                                                $status_class = 'badge-success';
                                                $status_icon = 'fas fa-check-circle';
                                                break;
                                            case 'borrowed':
                                                $status_class = 'badge-warning';
                                                $status_icon = 'fas fa-hand-holding';
                                                break;
                                            case 'reserved':
                                                $status_class = 'badge-info';
                                                $status_icon = 'fas fa-bookmark';
                                                break;
                                            case 'maintenance':
                                                $status_class = 'badge-danger';
                                                $status_icon = 'fas fa-tools';
                                                break;
                                            default:
                                                $status_class = 'badge-secondary';
                                                $status_icon = 'fas fa-question-circle';
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>">
                                            <i class="<?php echo $status_icon; ?>"></i>
                                            <?php echo ucfirst($book['status']); ?>
                                        </span>
                                    </td>
                                    <td class="actions">
                                        <!-- Edit button -->
                                        <a href="edit_book.php?id=<?php echo $book['id']; ?>" class="btn-sm btn-primary" title="Edit Book">
                                            <i class="fas fa-edit"></i>
                                        </a>

                                        <!-- Change Status button -->
                                        <button type="button" class="btn-sm btn-secondary" title="Change Status" onclick="showStatusModal(<?php echo $book['id']; ?>, '<?php echo htmlspecialchars($book['status']); ?>')">
                                            <i class="fas fa-exchange-alt"></i>
                                        </button>

                                        <!-- Delete button -->
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                            <button type="submit" class="btn-sm btn-danger" title="Delete Book" onclick="return confirm('⚠️ WARNING: This will permanently delete the book.\n\nThis action cannot be undone. Are you absolutely sure?');">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
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
                        <?php echo min($page * $per_page, $total_books); ?> of
                        <?php echo $total_books; ?> books
                    </div>
                    <nav aria-label="Book pagination">
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($category_filter) ? '&category=' . urlencode($category_filter) : ''; ?><?php echo $status_filter != 'all' ? '&status=' . urlencode($status_filter) : ''; ?>">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($category_filter) ? '&category=' . urlencode($category_filter) : ''; ?><?php echo $status_filter != 'all' ? '&status=' . urlencode($status_filter) : ''; ?>">
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
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($category_filter) ? '&category=' . urlencode($category_filter) : ''; ?><?php echo $status_filter != 'all' ? '&status=' . urlencode($status_filter) : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($category_filter) ? '&category=' . urlencode($category_filter) : ''; ?><?php echo $status_filter != 'all' ? '&status=' . urlencode($status_filter) : ''; ?>">
                                        <i class="fas fa-angle-right"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($category_filter) ? '&category=' . urlencode($category_filter) : ''; ?><?php echo $status_filter != 'all' ? '&status=' . urlencode($status_filter) : ''; ?>">
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

<!-- Status Change Modal -->
<div id="statusModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2>Change Book Status</h2>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="book_id" id="modal_book_id">
            
            <div class="form-group">
                <label for="book_status">Status</label>
                <select name="status" id="book_status" class="form-control">
                    <option value="available">Available</option>
                    <option value="borrowed">Borrowed</option>
                    <option value="reserved">Reserved</option>
                    <option value="maintenance">Maintenance</option>
                </select>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn-primary">Update Status</button>
                <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Book Management - Clean Professional Styles */

/* Badge styles - Clean and professional */
.badge {
    display: inline-block;
    padding: 4px 8px;
    font-size: 12px;
    font-weight: 600;
    line-height: 1;
    color: #fff;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 4px;
    border: 1px solid transparent;
}

.badge i {
    margin-right: 4px;
    font-size: 10px;
}

.badge-success { background-color: #e8f5e8; color: #2d5a2d; border-color: #c3e6c3; }
.badge-warning { background-color: #fffbeb; color: #d97706; border-color: #fed7aa; }
.badge-info { background-color: #eff6ff; color: #1e40af; border-color: #bfdbfe; }
.badge-danger { background-color: #fef2f2; color: #b91c1c; border-color: #fecaca; }
.badge-secondary { background-color: #f0f2f5; color: #4a5568; border-color: #d1d5db; }

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

/* Book-specific styling */
.book-title,
.book-author {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.category-badge {
    background-color: #f0f9ff;
    color: #0369a1;
    border: 1px solid #bae6fd;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 500;
}

.copies-info {
    display: flex;
    align-items: center;
    gap: 4px;
}

.copies-info .available {
    font-weight: 600;
    color: #155724;
}

.copies-info .separator {
    color: #6c757d;
}

.copies-info .total {
    color: #495057;
}

/* Action buttons */
.actions {
    white-space: nowrap;
}

.actions .btn-sm {
    margin-right: 5px;
}

.actions form {
    display: inline-block;
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

.btn-secondary {
    background-color: #6b7280;
    color: #ffffff;
    border-color: #6b7280;
}

.btn-secondary:hover {
    background-color: #4b5563;
    border-color: #4b5563;
    box-shadow: 0 2px 4px rgba(107, 114, 128, 0.3);
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

/* No data state */
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

/* Pagination styles */
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

/* Modal Styles */
.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background-color: #fff;
    padding: 30px;
    border-radius: 10px;
    width: 400px;
    max-width: 90%;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    position: relative;
}

.modal-content h2 {
    margin-bottom: 20px;
    color: #495057;
    font-size: 20px;
}

.close {
    position: absolute;
    top: 15px;
    right: 20px;
    font-size: 24px;
    cursor: pointer;
    color: #6c757d;
    transition: color 0.15s ease-in-out;
}

.close:hover {
    color: #495057;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #495057;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.form-control:focus {
    border-color: #80bdff;
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

/* Responsive design */
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

    .book-title,
    .book-author {
        max-width: 120px;
    }

    .modal-content {
        width: 95%;
        padding: 20px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Enhanced confirmation dialogs
    const deleteButtons = document.querySelectorAll('.btn-danger[title="Delete Book"]');

    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const form = this.closest('form');
            if (confirm('⚠️ WARNING: This will permanently delete the book and all associated data.\n\nThis action cannot be undone. Are you absolutely sure?')) {
                form.submit();
            }
        });
    });
});

function showStatusModal(bookId, currentStatus) {
    document.getElementById('modal_book_id').value = bookId;
    document.getElementById('book_status').value = currentStatus;
    document.getElementById('statusModal').style.display = 'flex';

    // Focus on the select element for better UX
    setTimeout(() => {
        document.getElementById('book_status').focus();
    }, 100);
}

function closeModal() {
    document.getElementById('statusModal').style.display = 'none';
}

// Close modal when clicking outside or pressing Escape
window.addEventListener('click', function(event) {
    const modal = document.getElementById('statusModal');
    if (event.target === modal) {
        closeModal();
    }
});

window.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal();
    }
});

// Form validation for status modal
document.querySelector('#statusModal form').addEventListener('submit', function(e) {
    const status = document.getElementById('book_status').value;
    if (!status) {
        e.preventDefault();
        alert('Please select a status.');
        return false;
    }

    // Show confirmation for status change
    const confirmMessage = `Are you sure you want to change the book status to "${status.charAt(0).toUpperCase() + status.slice(1)}"?`;
    if (!confirm(confirmMessage)) {
        e.preventDefault();
        return false;
    }
});
</script>

<?php
// Include footer
include "../admin/includes/footer.php";
?>