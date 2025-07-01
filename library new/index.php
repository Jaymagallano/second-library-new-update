<?php
// Start session
session_start();

// Check if the user is logged in, if not redirect to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit();
}

// Include database connection
require_once "config.php";

// Get user information
$user_id = $_SESSION["user_id"];
$username = $_SESSION["username"];
$user_role = $_SESSION["role_id"];

// Get dashboard statistics
$stats = [];

// Total books
$result = $conn->query("SELECT COUNT(*) as total FROM books");
$stats['total_books'] = $result->fetch_assoc()['total'];

// Available books
$result = $conn->query("SELECT COUNT(*) as available FROM books WHERE copies_available > 0");
$stats['available_books'] = $result->fetch_assoc()['available'];

// User's active borrowings
$stmt = $conn->prepare("SELECT COUNT(*) as active FROM borrowings WHERE user_id = ? AND status = 'active'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats['my_borrowings'] = $stmt->get_result()->fetch_assoc()['active'];

// User's active reservations
$stmt = $conn->prepare("SELECT COUNT(*) as active FROM reservations WHERE user_id = ? AND status = 'active'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats['my_reservations'] = $stmt->get_result()->fetch_assoc()['active'];

// Recent books (last 10)
$recent_books = $conn->query("SELECT * FROM books ORDER BY created_at DESC LIMIT 6");

// User's recent borrowings
$stmt = $conn->prepare("
    SELECT b.title, b.author, br.borrow_date, br.due_date, br.status
    FROM borrowings br
    JOIN books b ON br.book_id = b.id
    WHERE br.user_id = ?
    ORDER BY br.borrow_date DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_borrowings = $stmt->get_result();

// Popular categories
$popular_categories = $conn->query("
    SELECT category, COUNT(*) as count
    FROM books
    WHERE category IS NOT NULL
    GROUP BY category
    ORDER BY count DESC
    LIMIT 5
");

// Notifications for user
$stmt = $conn->prepare("
    SELECT * FROM notifications
    WHERE user_id = ? AND is_read = FALSE
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Dashboard - Digital Library Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Georgia', 'Times New Roman', serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            color: #333;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #8B4513 0%, #A0522D 50%, #CD853F 100%);
            padding: 20px 0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            color: white;
        }

        .logo i {
            font-size: 32px;
            margin-right: 15px;
        }

        .logo h1 {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .header-nav {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .nav-links {
            display: flex;
            gap: 25px;
        }

        .nav-links a {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            padding: 8px 16px;
            border-radius: 6px;
        }

        .nav-links a:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
            color: white;
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            font-size: 16px;
        }

        .user-role {
            font-size: 12px;
            opacity: 0.8;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        /* Main Container */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        /* Welcome Section */
        .welcome-section {
            background: white;
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #8B4513, #A0522D, #CD853F);
        }

        .welcome-title {
            font-size: 32px;
            color: #8B4513;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .welcome-subtitle {
            font-size: 18px;
            color: #666;
            margin-bottom: 20px;
        }

        .current-time {
            font-size: 14px;
            color: #999;
            font-style: italic;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--card-color);
        }

        .stat-card.books { --card-color: #3498db; }
        .stat-card.available { --card-color: #2ecc71; }
        .stat-card.borrowed { --card-color: #e74c3c; }
        .stat-card.reserved { --card-color: #f39c12; }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-icon {
            font-size: 24px;
            color: var(--card-color);
            background: rgba(var(--card-color-rgb), 0.1);
            padding: 12px;
            border-radius: 10px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        /* Section Cards */
        .section-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #8B4513;
        }

        .view-all-btn {
            color: #8B4513;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: color 0.3s;
        }

        .view-all-btn:hover {
            color: #A0522D;
        }

        /* Book Grid */
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .book-card {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 15px;
            transition: all 0.3s;
            cursor: pointer;
        }

        .book-card:hover {
            border-color: #8B4513;
            box-shadow: 0 5px 15px rgba(139, 69, 19, 0.1);
        }

        .book-title {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            line-height: 1.3;
        }

        .book-author {
            font-size: 12px;
            color: #666;
            margin-bottom: 8px;
        }

        .book-category {
            font-size: 11px;
            background: #f0f0f0;
            color: #666;
            padding: 3px 8px;
            border-radius: 12px;
            display: inline-block;
        }

        /* Activity List */
        .activity-list {
            list-style: none;
        }

        .activity-item {
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-info h4 {
            font-size: 14px;
            color: #333;
            margin-bottom: 3px;
        }

        .activity-info p {
            font-size: 12px;
            color: #666;
        }

        .activity-date {
            font-size: 11px;
            color: #999;
        }

        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }

        .status-active { background: #e8f5e8; color: #2ecc71; }
        .status-overdue { background: #ffeaea; color: #e74c3c; }
        .status-returned { background: #e8f4fd; color: #3498db; }

        /* Notifications */
        .notification-item {
            padding: 15px;
            border-left: 4px solid #8B4513;
            background: #f9f9f9;
            margin-bottom: 10px;
            border-radius: 0 8px 8px 0;
        }

        .notification-item:last-child {
            margin-bottom: 0;
        }

        .notification-message {
            font-size: 14px;
            color: #333;
            margin-bottom: 5px;
        }

        .notification-time {
            font-size: 11px;
            color: #999;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .action-btn {
            background: linear-gradient(135deg, #8B4513, #A0522D);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(139, 69, 19, 0.2);
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(139, 69, 19, 0.3);
        }

        .action-btn i {
            font-size: 24px;
            margin-bottom: 10px;
            display: block;
        }

        .action-btn span {
            font-size: 14px;
            font-weight: 600;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header-nav {
                flex-direction: column;
                gap: 15px;
            }

            .nav-links {
                display: none;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }

            .books-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #8B4513;
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
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <i class="fas fa-book-open"></i>
                <h1>Digital Library</h1>
            </div>

            <nav class="header-nav">
                <div class="nav-links">
                    <a href="#books"><i class="fas fa-book"></i> Browse Books</a>
                    <a href="#borrowings"><i class="fas fa-bookmark"></i> My Books</a>
                    <a href="#profile"><i class="fas fa-user"></i> Profile</a>
                </div>

                <div class="user-menu">
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($username); ?></div>
                        <div class="user-role">
                            <?php
                            $role_names = [1 => 'Administrator', 2 => 'Librarian', 3 => 'Patron'];
                            echo $role_names[$user_role] ?? 'User';
                            ?>
                        </div>
                    </div>
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Sign Out
                    </a>
                </div>
            </nav>
        </div>
    </header>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Welcome Section -->
        <section class="welcome-section">
            <h1 class="welcome-title">📚 Welcome to Your Digital Library</h1>
            <p class="welcome-subtitle">Discover, borrow, and explore thousands of books at your fingertips</p>
            <div class="current-time">
                <i class="fas fa-clock"></i>
                <span id="current-time"></span>
            </div>
        </section>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card books" style="--card-color-rgb: 52, 152, 219;">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-books"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo number_format($stats['total_books']); ?></div>
                <div class="stat-label">Total Books</div>
            </div>

            <div class="stat-card available" style="--card-color-rgb: 46, 204, 113;">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo number_format($stats['available_books']); ?></div>
                <div class="stat-label">Available Books</div>
            </div>

            <div class="stat-card borrowed" style="--card-color-rgb: 231, 76, 60;">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-book-reader"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo number_format($stats['my_borrowings']); ?></div>
                <div class="stat-label">My Borrowed Books</div>
            </div>

            <div class="stat-card reserved" style="--card-color-rgb: 243, 156, 18;">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-bookmark"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo number_format($stats['my_reservations']); ?></div>
                <div class="stat-label">My Reservations</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="#search-books" class="action-btn">
                <i class="fas fa-search"></i>
                <span>Search Books</span>
            </a>
            <a href="#borrow-book" class="action-btn">
                <i class="fas fa-plus-circle"></i>
                <span>Borrow Book</span>
            </a>
            <a href="#my-account" class="action-btn">
                <i class="fas fa-user-circle"></i>
                <span>My Account</span>
            </a>
            <a href="#help" class="action-btn">
                <i class="fas fa-question-circle"></i>
                <span>Help & Support</span>
            </a>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Recent Books Section -->
            <div class="section-card">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-star"></i>
                        Recently Added Books
                    </h2>
                    <a href="#all-books" class="view-all-btn">View All Books →</a>
                </div>

                <div class="books-grid">
                    <?php while($book = $recent_books->fetch_assoc()): ?>
                    <div class="book-card">
                        <div class="book-title"><?php echo htmlspecialchars($book['title']); ?></div>
                        <div class="book-author">by <?php echo htmlspecialchars($book['author']); ?></div>
                        <div class="book-category"><?php echo htmlspecialchars($book['category'] ?? 'General'); ?></div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div>
                <!-- My Recent Activity -->
                <div class="section-card" style="margin-bottom: 20px;">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-history"></i>
                            My Recent Activity
                        </h2>
                    </div>

                    <ul class="activity-list">
                        <?php if($recent_borrowings->num_rows > 0): ?>
                            <?php while($borrowing = $recent_borrowings->fetch_assoc()): ?>
                            <li class="activity-item">
                                <div class="activity-info">
                                    <h4><?php echo htmlspecialchars($borrowing['title']); ?></h4>
                                    <p>by <?php echo htmlspecialchars($borrowing['author']); ?></p>
                                    <div class="activity-date">
                                        Borrowed: <?php echo date('M j, Y', strtotime($borrowing['borrow_date'])); ?>
                                    </div>
                                </div>
                                <span class="status-badge status-<?php echo $borrowing['status']; ?>">
                                    <?php echo ucfirst($borrowing['status']); ?>
                                </span>
                            </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <li class="activity-item">
                                <div class="activity-info">
                                    <p>No recent borrowing activity</p>
                                </div>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Notifications -->
                <div class="section-card">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-bell"></i>
                            Notifications
                        </h2>
                    </div>

                    <?php if($notifications->num_rows > 0): ?>
                        <?php while($notification = $notifications->fetch_assoc()): ?>
                        <div class="notification-item">
                            <div class="notification-message">
                                <?php echo htmlspecialchars($notification['message']); ?>
                            </div>
                            <div class="notification-time">
                                <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="notification-item">
                            <div class="notification-message">No new notifications</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Popular Categories -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-tags"></i>
                    Popular Categories
                </h2>
            </div>

            <div class="books-grid">
                <?php while($category = $popular_categories->fetch_assoc()): ?>
                <div class="book-card">
                    <div class="book-title"><?php echo htmlspecialchars($category['category']); ?></div>
                    <div class="book-author"><?php echo $category['count']; ?> books available</div>
                    <div class="book-category">Category</div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <script>
        // Update current time
        function updateTime() {
            const now = new Date();
            const options = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            document.getElementById('current-time').textContent = now.toLocaleDateString('en-US', options);
        }

        updateTime();
        setInterval(updateTime, 60000); // Update every minute

        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Add loading states for action buttons
        document.querySelectorAll('.action-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (this.getAttribute('href').startsWith('#')) {
                    e.preventDefault();
                    // Add your functionality here
                    console.log('Action clicked:', this.querySelector('span').textContent);
                }
            });
        });
    </script>
</body>
</html>