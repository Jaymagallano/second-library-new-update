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
log_admin_activity($_SESSION["user_id"], 'add_book_page_access', $conn);

$title = $author = $isbn = $category = $publisher = $publication_year = $location = "";
$title_err = $author_err = $isbn_err = $category_err = $publisher_err = $publication_year_err = $copies_err = $location_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate title
    if (empty(trim($_POST["title"]))) {
        $title_err = "Please enter a book title.";
    } else {
        $title = trim($_POST["title"]);
    }
    
    // Validate author
    if (empty(trim($_POST["author"]))) {
        $author_err = "Please enter the author name.";
    } else {
        $author = trim($_POST["author"]);
    }
    
    // Validate ISBN
    if (empty(trim($_POST["isbn"]))) {
        $isbn_err = "Please enter the ISBN.";
    } else {
        // Check if ISBN already exists
        $sql = "SELECT id FROM books WHERE isbn = ?";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $param_isbn);
            $param_isbn = trim($_POST["isbn"]);
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    $isbn_err = "This ISBN already exists in the system.";
                } else {
                    $isbn = trim($_POST["isbn"]);
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // Validate category
    if (empty(trim($_POST["category"]))) {
        $category_err = "Please enter a category.";
    } else {
        $category = trim($_POST["category"]);
    }
    
    // Validate publisher
    if (empty(trim($_POST["publisher"]))) {
        $publisher_err = "Please enter the publisher.";
    } else {
        $publisher = trim($_POST["publisher"]);
    }
    
    // Validate publication year
    if (empty(trim($_POST["publication_year"]))) {
        $publication_year_err = "Please enter the publication year.";
    } elseif (!is_numeric(trim($_POST["publication_year"])) || trim($_POST["publication_year"]) < 1000 || trim($_POST["publication_year"]) > date('Y')) {
        $publication_year_err = "Please enter a valid publication year.";
    } else {
        $publication_year = trim($_POST["publication_year"]);
    }
    
    // Validate copies
    if (empty(trim($_POST["copies_total"]))) {
        $copies_err = "Please enter the number of copies.";
    } elseif (!is_numeric(trim($_POST["copies_total"])) || trim($_POST["copies_total"]) < 1) {
        $copies_err = "Please enter a valid number of copies (minimum 1).";
    } else {
        $copies_total = trim($_POST["copies_total"]);
    }

    // Validate location
    if (empty(trim($_POST["location"]))) {
        $location_err = "Please enter the location.";
    } else {
        $location = trim($_POST["location"]);
    }
    
    // Check input errors before inserting in database
    if (empty($title_err) && empty($author_err) && empty($isbn_err) && empty($category_err) && empty($publisher_err) && empty($publication_year_err) && empty($copies_err) && empty($location_err)) {

        // Prepare an insert statement
        $sql = "INSERT INTO books (title, author, isbn, category, publisher, publication_year, copies_total, copies_available, location, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'available', NOW())";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "sssssiiis", $param_title, $param_author, $param_isbn, $param_category, $param_publisher, $param_publication_year, $param_copies_total, $param_copies_available, $param_location);

            $param_title = $title;
            $param_author = $author;
            $param_isbn = $isbn;
            $param_category = $category;
            $param_publisher = $publisher;
            $param_publication_year = $publication_year;
            $param_copies_total = $copies_total;
            $param_copies_available = $copies_total; // Initially all copies are available
            $param_location = $location;
            
            if (mysqli_stmt_execute($stmt)) {
                // Log the book creation
                log_admin_activity($_SESSION["user_id"], 'book_created', $conn, "Created book: $title");
                
                $_SESSION['success_message'] = "Book added successfully!";
                header("location: books.php");
                exit();
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Get existing categories for dropdown
$categories = [];
$sql = "SELECT DISTINCT category FROM books WHERE category IS NOT NULL AND category != '' ORDER BY category";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row['category'];
    }
}

// Add default categories if not present
$default_categories = ['Fiction', 'Non-Fiction', 'Science', 'Technology', 'History', 'Biography', 'Reference', 'Children', 'Young Adult', 'Academic'];
foreach ($default_categories as $default_category) {
    if (!in_array($default_category, $categories)) {
        $categories[] = $default_category;
    }
}
sort($categories);

// Include header
$page_title = "Add New Book";
include "../admin/includes/header.php";
?>

<div class="main-content responsive-container">
    <div class="header">
        <h1><i class="fas fa-book-plus"></i> Add New Book</h1>
        <div class="header-actions">
            <button class="btn-minimal" onclick="location.href='books.php'">
                <i class="fas fa-arrow-left"></i> Back to Books
            </button>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control <?php echo (!empty($title_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $title; ?>">
                            <div class="invalid-feedback"><?php echo $title_err; ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Author <span class="text-danger">*</span></label>
                            <input type="text" name="author" class="form-control <?php echo (!empty($author_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $author; ?>">
                            <div class="invalid-feedback"><?php echo $author_err; ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>ISBN <span class="text-danger">*</span></label>
                            <input type="text" name="isbn" class="form-control <?php echo (!empty($isbn_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $isbn; ?>">
                            <div class="invalid-feedback"><?php echo $isbn_err; ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Category <span class="text-danger">*</span></label>
                            <select name="category" class="form-control <?php echo (!empty($category_err)) ? 'is-invalid' : ''; ?>">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($category == $cat) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback"><?php echo $category_err; ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Publisher <span class="text-danger">*</span></label>
                            <input type="text" name="publisher" class="form-control <?php echo (!empty($publisher_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $publisher; ?>">
                            <div class="invalid-feedback"><?php echo $publisher_err; ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Publication Year <span class="text-danger">*</span></label>
                            <input type="number" name="publication_year" class="form-control <?php echo (!empty($publication_year_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $publication_year; ?>" min="1000" max="<?php echo date('Y'); ?>">
                            <div class="invalid-feedback"><?php echo $publication_year_err; ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Number of Copies <span class="text-danger">*</span></label>
                            <input type="number" name="copies_total" class="form-control <?php echo (!empty($copies_err)) ? 'is-invalid' : ''; ?>" value="<?php echo isset($copies_total) ? $copies_total : ''; ?>" min="1">
                            <div class="invalid-feedback"><?php echo $copies_err; ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Location <span class="text-danger">*</span></label>
                            <input type="text" name="location" class="form-control <?php echo (!empty($location_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $location; ?>" placeholder="e.g., Section A, Shelf 3">
                            <div class="invalid-feedback"><?php echo $location_err; ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Add Book
                    </button>
                    <button type="button" class="btn-minimal" onclick="location.href='books.php'">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Add Book Form Styles */
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
