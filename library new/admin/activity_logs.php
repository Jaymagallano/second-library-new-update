<?php
/**
 * Enhanced User Activity Logs Management System
 *
 * This comprehensive system provides detailed monitoring and analysis
 * of all user activities within the library management system.
 *
 * Features:
 * - Real-time activity monitoring
 * - Advanced filtering and search
 * - Security threat detection
 * - User behavior analytics
 * - Comprehensive audit trails
 * - Export capabilities
 * - Interactive dashboards
 */

// Include necessary files
require_once "../config.php";
require_once "../admin_auth.php";
require_once "../includes/user_logger.php";

// Verify admin session
if (!verify_admin_session()) {
    header("Location: ../admin_login.php");
    exit;
}

// Log this page access with enhanced details
log_admin_activity($_SESSION["user_id"], 'activity_logs_page_access', $conn);

// Enhanced initialization with security monitoring
$logs = [];
$total_logs = 0;
$search = trim($_GET['search'] ?? "");
$action_filter = $_GET['action'] ?? "all";
$module_filter = $_GET['module'] ?? "all";
$status_filter = $_GET['status'] ?? "all";
$user_filter = $_GET['user'] ?? "all";
$risk_level = $_GET['risk'] ?? "all";
$date_from = $_GET['date_from'] ?? "";
$date_to = $_GET['date_to'] ?? "";
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = min(100, max(10, (int)($_GET['per_page'] ?? 25)));
$offset = ($page - 1) * $per_page;

// Security monitoring flags
$show_security_alerts = true;
$show_analytics = true;

// Handle search and filters
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    if (isset($_GET['search'])) {
        $search = trim($_GET['search']);
    }
    if (isset($_GET['action'])) {
        $action_filter = trim($_GET['action']);
    }
    if (isset($_GET['module'])) {
        $module_filter = trim($_GET['module']);
    }
    if (isset($_GET['status'])) {
        $status_filter = trim($_GET['status']);
    }
    if (isset($_GET['date_from'])) {
        $date_from = trim($_GET['date_from']);
    }
    if (isset($_GET['date_to'])) {
        $date_to = trim($_GET['date_to']);
    }
}

// Enhanced Security Analytics Functions
function calculateRiskLevel($log) {
    $risk_score = 0;

    // Failed login attempts
    if ($log['action'] == 'login_failed') $risk_score += 3;

    // Multiple rapid actions
    if ($log['status'] == 'failure') $risk_score += 2;

    // Off-hours access (10 PM - 6 AM)
    $hour = (int)date('H', strtotime($log['timestamp']));
    if ($hour >= 22 || $hour <= 6) $risk_score += 1;

    // Admin actions
    if (in_array($log['action'], ['delete', 'user_deleted', 'book_deleted'])) $risk_score += 2;

    // Suspicious patterns
    if (strpos($log['action_details'], 'multiple') !== false) $risk_score += 1;

    // Determine risk level
    if ($risk_score >= 5) return 'high';
    if ($risk_score >= 3) return 'medium';
    if ($risk_score >= 1) return 'low';
    return 'normal';
}

// Get unique values for enhanced filters
function getFilterOptions($conn) {
    $options = [
        'actions' => [],
        'modules' => [],
        'users' => []
    ];

    // Get unique actions
    $result = $conn->query("SELECT DISTINCT action FROM user_activity_log ORDER BY action");
    while ($row = $result->fetch_assoc()) {
        $options['actions'][] = $row['action'];
    }

    // Get unique modules
    $result = $conn->query("SELECT DISTINCT module FROM user_activity_log WHERE module IS NOT NULL ORDER BY module");
    while ($row = $result->fetch_assoc()) {
        $options['modules'][] = $row['module'];
    }

    // Get unique users
    $result = $conn->query("SELECT DISTINCT username FROM user_activity_log WHERE username IS NOT NULL ORDER BY username");
    while ($row = $result->fetch_assoc()) {
        $options['users'][] = $row['username'];
    }

    return $options;
}

// Get security statistics
function getSecurityStats($conn) {
    $stats = [];

    // Failed login attempts in last 24 hours
    $result = $conn->query("SELECT COUNT(*) as count FROM user_activity_log
                           WHERE action = 'login_failed' AND timestamp >= NOW() - INTERVAL 24 HOUR");
    $stats['failed_logins_24h'] = $result->fetch_assoc()['count'];

    // Unique users active today
    $result = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM user_activity_log
                           WHERE DATE(timestamp) = CURDATE() AND user_id IS NOT NULL");
    $stats['active_users_today'] = $result->fetch_assoc()['count'];

    // Total activities today
    $result = $conn->query("SELECT COUNT(*) as count FROM user_activity_log
                           WHERE DATE(timestamp) = CURDATE()");
    $stats['activities_today'] = $result->fetch_assoc()['count'];

    // High risk activities in last 7 days
    $result = $conn->query("SELECT COUNT(*) as count FROM user_activity_log
                           WHERE timestamp >= NOW() - INTERVAL 7 DAY
                           AND (action = 'login_failed' OR status = 'failure')");
    $stats['high_risk_7d'] = $result->fetch_assoc()['count'];

    return $stats;
}

$filter_options = getFilterOptions($conn);
$security_stats = getSecurityStats($conn);

// Handle export action
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="user_activity_logs_' . date('Y-m-d') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add CSV header
    fputcsv($output, ['ID', 'User ID', 'Username', 'Action', 'Details', 'Module', 'IP Address', 'Device', 'Timestamp', 'Status']);
    
    // Build query based on filters (without pagination)
    $query = "SELECT * FROM user_activity_log WHERE 1=1";
    $params = [];
    $types = "";
    
    if (!empty($search)) {
        $search_param = "%$search%";
        $query .= " AND (username LIKE ? OR action LIKE ? OR action_details LIKE ? OR ip_address LIKE ?)";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "ssss";
    }
    
    if ($action_filter != "all") {
        $query .= " AND action = ?";
        $params[] = $action_filter;
        $types .= "s";
    }
    
    if ($module_filter != "all") {
        $query .= " AND module = ?";
        $params[] = $module_filter;
        $types .= "s";
    }
    
    if ($status_filter != "all") {
        $query .= " AND status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }
    
    if (!empty($date_from)) {
        $query .= " AND timestamp >= ?";
        $params[] = $date_from . " 00:00:00";
        $types .= "s";
    }
    
    if (!empty($date_to)) {
        $query .= " AND timestamp <= ?";
        $params[] = $date_to . " 23:59:59";
        $types .= "s";
    }
    
    $query .= " ORDER BY timestamp DESC";
    
    // Execute query
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Output each row as CSV
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['user_id'],
            $row['username'],
            $row['action'],
            $row['action_details'],
            $row['module'],
            $row['ip_address'],
            $row['device_info'],
            $row['timestamp'],
            $row['status']
        ]);
    }
    
    // Close statement and exit
    $stmt->close();
    exit;
}

// Build query based on filters
$query = "SELECT * FROM user_activity_log WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM user_activity_log WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $search_param = "%$search%";
    $query .= " AND (username LIKE ? OR action LIKE ? OR action_details LIKE ? OR ip_address LIKE ?)";
    $count_query .= " AND (username LIKE ? OR action LIKE ? OR action_details LIKE ? OR ip_address LIKE ?)";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

if ($action_filter != "all") {
    $query .= " AND action = ?";
    $count_query .= " AND action = ?";
    $params[] = $action_filter;
    $types .= "s";
}

if ($module_filter != "all") {
    $query .= " AND module = ?";
    $count_query .= " AND module = ?";
    $params[] = $module_filter;
    $types .= "s";
}

if ($status_filter != "all") {
    $query .= " AND status = ?";
    $count_query .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($date_from)) {
    $query .= " AND timestamp >= ?";
    $count_query .= " AND timestamp >= ?";
    $params[] = $date_from . " 00:00:00";
    $types .= "s";
}

if (!empty($date_to)) {
    $query .= " AND timestamp <= ?";
    $count_query .= " AND timestamp <= ?";
    $params[] = $date_to . " 23:59:59";
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
$total_logs = $row['total'];
$stmt->close();

// Add pagination to query
$query .= " ORDER BY timestamp DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

// Get logs
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}
$stmt->close();

// Calculate pagination
$total_pages = ceil($total_logs / $per_page);

// Get unique actions for filter dropdown
$actions = [];
$stmt = $conn->prepare("SELECT DISTINCT action FROM user_activity_log ORDER BY action");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $actions[] = $row['action'];
}
$stmt->close();

// Get unique modules for filter dropdown
$modules = [];
$stmt = $conn->prepare("SELECT DISTINCT module FROM user_activity_log ORDER BY module");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $modules[] = $row['module'];
}
$stmt->close();

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Include header
$page_title = "User Activity Logs";
include "../admin/includes/header.php";
?>

<div class="main-content responsive-container main-content-wide">
    <div class="header">
        <h1><i class="fas fa-list"></i> User Activity Logs</h1>
        <div class="header-actions">
            <button class="btn-minimal" onclick="toggleInfoPanel()" title="Information">
                <i class="fas fa-info-circle"></i> Info
            </button>
            <button class="btn-minimal" onclick="location.href='activity_logs.php?export=csv<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $action_filter != 'all' ? '&action=' . urlencode($action_filter) : ''; ?><?php echo $module_filter != 'all' ? '&module=' . urlencode($module_filter) : ''; ?><?php echo $status_filter != 'all' ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>'">
                <i class="fas fa-download"></i> Export
            </button>
        </div>
    </div>


    <!-- Information Panel -->
    <div id="infoPanel" class="info-panel" style="display: none;">
        <div class="info-content">
            <div class="info-grid">
                <div class="info-section">
                    <h3><i class="fas fa-shield-alt"></i> Bakit Kailangan ng Admin ang User Activity Logs?</h3>
                    <div class="info-cards">
                        <div class="info-card monitoring">
                            <div class="info-icon">
                                <i class="fas fa-eye"></i>
                            </div>
                            <div class="info-details">
                                <h4>Monitoring</h4>
                                <p>Para mabantayan kung tama ang paggamit ng system ng mga user at masiguro na sumusunod sila sa mga patakaran.</p>
                            </div>
                        </div>

                        <div class="info-card security">
                            <div class="info-icon">
                                <i class="fas fa-lock"></i>
                            </div>
                            <div class="info-details">
                                <h4>Security</h4>
                                <p>Para malaman kung may suspicious activity tulad ng repeated login attempts, unauthorized access, o unusual behavior patterns.</p>
                            </div>
                        </div>

                        <div class="info-card support">
                            <div class="info-icon">
                                <i class="fas fa-headset"></i>
                            </div>
                            <div class="info-details">
                                <h4>Support & Troubleshooting</h4>
                                <p>Kapag may reklamo ang user, makikita ng admin ang complete history ng actions para masolusyunan ang problema.</p>
                            </div>
                        </div>

                        <div class="info-card audit">
                            <div class="info-icon">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <div class="info-details">
                                <h4>Audit Trail</h4>
                                <p>Para may official record kung anong ginawa ng users sa system, kailan, at para sa compliance requirements.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="info-section">
                    <h3><i class="fas fa-list-ul"></i> Ano ang Nakikita ng Admin sa Activity Logs?</h3>
                    <div class="monitoring-items">
                        <div class="monitoring-item">
                            <i class="fas fa-user"></i>
                            <div class="monitoring-details">
                                <strong>User Information</strong>
                                <span>Username, User ID, at role ng user na gumawa ng action</span>
                            </div>
                        </div>

                        <div class="monitoring-item">
                            <i class="fas fa-clock"></i>
                            <div class="monitoring-details">
                                <strong>Date & Time</strong>
                                <span>Exact timestamp kung kailan nangyari ang action</span>
                            </div>
                        </div>

                        <div class="monitoring-item">
                            <i class="fas fa-cogs"></i>
                            <div class="monitoring-details">
                                <strong>Action Details</strong>
                                <span>Specific na action (login, logout, create, update, delete, view)</span>
                            </div>
                        </div>

                        <div class="monitoring-item">
                            <i class="fas fa-cube"></i>
                            <div class="monitoring-details">
                                <strong>Module/Section</strong>
                                <span>Saang part ng system nangyari ang action (Books, Users, Admin, etc.)</span>
                            </div>
                        </div>

                        <div class="monitoring-item">
                            <i class="fas fa-globe"></i>
                            <div class="monitoring-details">
                                <strong>IP Address</strong>
                                <span>Location o network kung saan galing ang user</span>
                            </div>
                        </div>

                        <div class="monitoring-item">
                            <i class="fas fa-mobile-alt"></i>
                            <div class="monitoring-details">
                                <strong>Device Information</strong>
                                <span>Browser, operating system, at device type na ginamit</span>
                            </div>
                        </div>

                        <div class="monitoring-item">
                            <i class="fas fa-check-circle"></i>
                            <div class="monitoring-details">
                                <strong>Status</strong>
                                <span>Success o failure ng action para sa error tracking</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="info-footer">
                <div class="info-tips">
                    <h4><i class="fas fa-lightbulb"></i> Pro Tips para sa Admins:</h4>
                    <ul>
                        <li><strong>Regular Monitoring:</strong> I-check ang logs araw-araw para sa unusual patterns</li>
                        <li><strong>Filter Usage:</strong> Gamitin ang date range at filters para sa specific investigations</li>
                        <li><strong>Export Function:</strong> I-download ang CSV para sa detailed analysis o reporting</li>
                        <li><strong>Security Alerts:</strong> Bantayan ang multiple failed login attempts o off-hours access</li>
                        <li><strong>User Support:</strong> Gamitin ang search function para makita ang specific user's activity</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card card-responsive card-full-width">
        <div class="card-header">
            <h2>Activity Logs</h2>
            <div class="card-tools">
                <form method="GET" action="" class="search-form">
                    <div class="form-responsive">
                        <div class="form-group">
                            <label for="search">Search</label>
                            <input type="text" id="search" name="search" placeholder="Search users, actions, details..." value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="action">Action</label>
                            <select name="action" id="action" class="form-control">
                                <option value="all" <?php echo ($action_filter == 'all') ? 'selected' : ''; ?>>All Actions</option>
                                <?php foreach ($filter_options['actions'] as $action): ?>
                                <option value="<?php echo htmlspecialchars($action); ?>" <?php echo ($action_filter == $action) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $action))); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="module">Module</label>
                            <select name="module" id="module" class="form-control">
                                <option value="all" <?php echo ($module_filter == 'all') ? 'selected' : ''; ?>>All Modules</option>
                                <?php foreach ($filter_options['modules'] as $module): ?>
                                <option value="<?php echo htmlspecialchars($module); ?>" <?php echo ($module_filter == $module) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucfirst($module)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="user">User</label>
                            <select name="user" id="user" class="form-control">
                                <option value="all" <?php echo ($user_filter == 'all') ? 'selected' : ''; ?>>All Users</option>
                                <?php foreach ($filter_options['users'] as $user): ?>
                                <option value="<?php echo htmlspecialchars($user); ?>" <?php echo ($user_filter == $user) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-responsive">
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select name="status" id="status" class="form-control">
                                <option value="all" <?php echo ($status_filter == 'all') ? 'selected' : ''; ?>>All Status</option>
                                <option value="success" <?php echo ($status_filter == 'success') ? 'selected' : ''; ?>>Success</option>
                                <option value="failure" <?php echo ($status_filter == 'failure') ? 'selected' : ''; ?>>Failure</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="date_from">From Date</label>
                            <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="date_to">To Date</label>
                            <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="form-control">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-minimal">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <a href="activity_logs.php" class="btn-minimal">
                            <i class="fas fa-sync"></i> Reset
                        </a>
                        <button type="button" class="btn-minimal" onclick="applyQuickFilter('today')">
                            <i class="fas fa-calendar-day"></i> Today
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($logs) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Risk</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>Module</th>
                                <th>IP Address</th>
                                <th>Device</th>
                                <th>Timestamp</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log):
                                $risk_level = calculateRiskLevel($log);
                                $risk_class = 'risk-' . $risk_level;
                            ?>
                                <tr class="log-row <?php echo $risk_class; ?>" data-log-id="<?php echo $log['id']; ?>" data-risk="<?php echo $risk_level; ?>">
                                    <td class="log-id"><?php echo htmlspecialchars($log['id']); ?></td>
                                    <td class="log-risk">
                                        <?php
                                        $risk_labels = [
                                            'high' => 'High',
                                            'medium' => 'Medium',
                                            'low' => 'Low',
                                            'normal' => 'Normal'
                                        ];
                                        ?>
                                        <span class="risk-badge risk-<?php echo $risk_level; ?>">
                                            <?php echo $risk_labels[$risk_level]; ?>
                                        </span>
                                    </td>
                                    <td class="log-user">
                                        <?php if ($log['user_id']): ?>
                                            <div class="user-info">
                                                <a href="users.php?search=<?php echo urlencode($log['username']); ?>" class="user-link">
                                                    <i class="fas fa-user"></i>
                                                    <?php echo htmlspecialchars($log['username'] ?? 'User #' . $log['user_id']); ?>
                                                </a>
                                                <small class="user-id">ID: <?php echo $log['user_id']; ?></small>
                                            </div>
                                        <?php else: ?>
                                            <div class="user-info anonymous">
                                                <i class="fas fa-user-secret"></i>
                                                <?php echo htmlspecialchars($log['username'] ?? 'Anonymous'); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="log-action">
                                        <?php
                                        $action_display = ucfirst(str_replace('_', ' ', $log['action']));
                                        $action_icon = '';
                                        switch ($log['action']) {
                                            case 'login':
                                                $action_icon = 'fas fa-sign-in-alt';
                                                break;
                                            case 'logout':
                                                $action_icon = 'fas fa-sign-out-alt';
                                                break;
                                            case 'create':
                                            case 'add':
                                                $action_icon = 'fas fa-plus';
                                                break;
                                            case 'update':
                                            case 'edit':
                                                $action_icon = 'fas fa-edit';
                                                break;
                                            case 'delete':
                                                $action_icon = 'fas fa-trash';
                                                break;
                                            case 'view':
                                            case 'access':
                                                $action_icon = 'fas fa-eye';
                                                break;
                                            default:
                                                $action_icon = 'fas fa-cog';
                                        }
                                        ?>
                                        <div class="action-info">
                                            <i class="<?php echo $action_icon; ?>"></i>
                                            <?php echo htmlspecialchars($action_display); ?>
                                        </div>
                                    </td>
                                    <td class="log-details">
                                        <div class="details-text" title="<?php echo htmlspecialchars($log['action_details'] ?? 'No details'); ?>">
                                            <?php echo htmlspecialchars($log['action_details'] ?? 'No details'); ?>
                                        </div>
                                    </td>
                                    <td class="log-module">
                                        <span class="module-badge module-<?php echo strtolower($log['module'] ?? 'system'); ?>">
                                            <?php echo htmlspecialchars(ucfirst($log['module'] ?? 'System')); ?>
                                        </span>
                                    </td>
                                    <td class="log-ip">
                                        <div class="ip-info">
                                            <i class="fas fa-globe"></i>
                                            <?php echo htmlspecialchars($log['ip_address'] ?? 'Unknown'); ?>
                                        </div>
                                    </td>
                                    <td class="log-device">
                                        <div class="device-info" title="<?php echo htmlspecialchars($log['device_info'] ?? 'Unknown device'); ?>">
                                            <?php
                                            $device = $log['device_info'] ?? 'Unknown';
                                            $device_icon = 'fas fa-desktop';
                                            if (stripos($device, 'mobile') !== false || stripos($device, 'android') !== false || stripos($device, 'iphone') !== false) {
                                                $device_icon = 'fas fa-mobile-alt';
                                            } elseif (stripos($device, 'tablet') !== false || stripos($device, 'ipad') !== false) {
                                                $device_icon = 'fas fa-tablet-alt';
                                            }
                                            ?>
                                            <i class="<?php echo $device_icon; ?>"></i>
                                            <span class="device-text"><?php echo htmlspecialchars(substr($device, 0, 20) . (strlen($device) > 20 ? '...' : '')); ?></span>
                                        </div>
                                    </td>
                                    <td class="log-timestamp">
                                        <div class="timestamp-info">
                                            <div class="date"><?php echo date('M d, Y', strtotime($log['timestamp'])); ?></div>
                                            <div class="time"><?php echo date('h:i:s A', strtotime($log['timestamp'])); ?></div>
                                        </div>
                                    </td>
                                    <td class="log-status">
                                        <?php
                                        $status_icon = $log['status'] == 'success' ? 'fas fa-check-circle' : 'fas fa-times-circle';
                                        $status_class = $log['status'] == 'success' ? 'badge-success' : 'badge-danger';
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>">
                                            <i class="<?php echo $status_icon; ?>"></i>
                                            <?php echo htmlspecialchars(ucfirst($log['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-history"></i>
                    <p>No activity logs found matching your criteria.</p>
                    <small>Try adjusting your search filters or date range.</small>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="card-footer">
                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        Showing <?php echo (($page - 1) * $per_page) + 1; ?> to
                        <?php echo min($page * $per_page, $total_logs); ?> of
                        <?php echo $total_logs; ?> activity logs
                    </div>
                    <nav aria-label="Activity logs pagination">
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $action_filter != 'all' ? '&action=' . urlencode($action_filter) : ''; ?><?php echo $module_filter != 'all' ? '&module=' . urlencode($module_filter) : ''; ?><?php echo $status_filter != 'all' ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $action_filter != 'all' ? '&action=' . urlencode($action_filter) : ''; ?><?php echo $module_filter != 'all' ? '&module=' . urlencode($module_filter) : ''; ?><?php echo $status_filter != 'all' ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>">
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
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $action_filter != 'all' ? '&action=' . urlencode($action_filter) : ''; ?><?php echo $module_filter != 'all' ? '&module=' . urlencode($module_filter) : ''; ?><?php echo $status_filter != 'all' ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $action_filter != 'all' ? '&action=' . urlencode($action_filter) : ''; ?><?php echo $module_filter != 'all' ? '&module=' . urlencode($module_filter) : ''; ?><?php echo $status_filter != 'all' ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>">
                                        <i class="fas fa-angle-right"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $action_filter != 'all' ? '&action=' . urlencode($action_filter) : ''; ?><?php echo $module_filter != 'all' ? '&module=' . urlencode($module_filter) : ''; ?><?php echo $status_filter != 'all' ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>">
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

</div>

<style>
/* Activity Logs Specific Styles - Wide Layout */

/* Full-width layout modifications */
.main-content-wide {
    padding: 15px 5px !important;
    margin-left: 250px !important;
    width: calc(100% - 250px) !important;
    max-width: none !important;
}

.responsive-container {
    width: 100%;
    max-width: 100%;
    padding: 0;
    margin: 0;
}

.card-full-width {
    width: 100%;
    max-width: 100%;
    margin: 0 0 20px 0;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.card-body-full-width {
    padding: 15px 10px;
}

/* Information Panel Styles - Minimal */
.info-panel {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    overflow: hidden;
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideUp {
    from {
        opacity: 1;
        transform: translateY(0);
    }
    to {
        opacity: 0;
        transform: translateY(-20px);
    }
}

.info-content {
    padding: 20px;
    color: #495057;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.info-section h3 {
    color: #495057;
    font-size: 16px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
}

.info-section h3 i {
    color: #6c757d;
}

.info-cards {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.info-card {
    background: #ffffff;
    border-radius: 6px;
    padding: 15px;
    border: 1px solid #e9ecef;
    transition: all 0.2s ease;
}

.info-card:hover {
    border-color: #dee2e6;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.info-card .info-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 10px;
    font-size: 18px;
}

.info-card.monitoring .info-icon {
    background: rgba(52, 152, 219, 0.3);
    color: #3498db;
}

.info-card.security .info-icon {
    background: rgba(231, 76, 60, 0.3);
    color: #e74c3c;
}

.info-card.support .info-icon {
    background: rgba(46, 204, 113, 0.3);
    color: #2ecc71;
}

.info-card.audit .info-icon {
    background: rgba(241, 196, 15, 0.3);
    color: #f1c40f;
}

.info-card h4 {
    color: #495057;
    font-size: 14px;
    margin-bottom: 8px;
    font-weight: 600;
}

.info-card p {
    color: #6c757d;
    font-size: 12px;
    line-height: 1.4;
    margin: 0;
}

.monitoring-items {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.monitoring-item {
    display: flex;
    align-items: center;
    gap: 15px;
    background: rgba(255,255,255,0.1);
    padding: 12px;
    border-radius: 8px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
    transition: all 0.3s ease;
}

.monitoring-item:hover {
    background: rgba(255,255,255,0.2);
    transform: translateX(5px);
}

.monitoring-item i {
    color: #ffd700;
    font-size: 16px;
    width: 20px;
    text-align: center;
}

.monitoring-details {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.monitoring-details strong {
    color: white;
    font-size: 13px;
    font-weight: 600;
}

.monitoring-details span {
    color: rgba(255,255,255,0.8);
    font-size: 11px;
    line-height: 1.3;
}

.info-footer {
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid rgba(255,255,255,0.2);
}

.info-tips h4 {
    color: white;
    font-size: 16px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-tips h4 i {
    color: #ffd700;
}

.info-tips ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.info-tips li {
    color: rgba(255,255,255,0.9);
    font-size: 12px;
    line-height: 1.4;
    margin-bottom: 8px;
    padding-left: 20px;
    position: relative;
}

.info-tips li:before {
    content: "✓";
    position: absolute;
    left: 0;
    color: #2ecc71;
    font-weight: bold;
}

.info-tips strong {
    color: white;
}

.btn-minimal {
    background-color: #f8f9fa;
    color: #495057;
    border: 1px solid #dee2e6;
    padding: 8px 15px;
    border-radius: 4px;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
}

.btn-minimal:hover {
    background-color: #e9ecef;
    border-color: #adb5bd;
    color: #495057;
    text-decoration: none;
}

.btn-warning {
    background-color: #ffc107;
    color: #212529;
    border: none;
    padding: 8px 15px;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-warning:hover {
    background-color: #e0a800;
    transform: translateY(-2px);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.btn-success {
    background-color: #28a745;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-success:hover {
    background-color: #1e7e34;
    transform: translateY(-2px);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}



/* Search Form - Minimal */
.search-form {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 20px;
}

.search-form label {
    color: #495057;
    font-weight: 500;
    font-size: 12px;
    margin-bottom: 5px;
    display: block;
}

.form-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: flex-start;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #dee2e6;
}

/* Risk Level Styling - Minimal */
.risk-badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 500;
}

.risk-high {
    background: #f8d7da;
    color: #721c24;
}

.risk-medium {
    background: #fff3cd;
    color: #856404;
}

.risk-low {
    background: #d1ecf1;
    color: #0c5460;
}

.risk-normal {
    background: #e2e3e5;
    color: #383d41;
}

.log-row.risk-high {
    border-left: 2px solid #dc3545;
}

.log-row.risk-medium {
    border-left: 2px solid #ffc107;
}

.log-row.risk-low {
    border-left: 2px solid #17a2b8;
}

.risk-text {
    display: none;
}

@media (min-width: 768px) {
    .risk-text {
        display: inline;
    }
}

/* Badge styles */
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
}

.badge i {
    margin-right: 4px;
    font-size: 10px;
}

.badge-success { background-color: #28a745; }
.badge-danger { background-color: #dc3545; }

/* Table styles - Wide Layout */
.table {
    width: 100%;
    margin-bottom: 1rem;
    color: #212529;
    border-collapse: collapse;
    font-size: 13px;
}

.table th,
.table td {
    padding: 8px 6px;
    vertical-align: middle;
    border-top: 1px solid #dee2e6;
}

.table thead th {
    vertical-align: bottom;
    border-bottom: 2px solid #dee2e6;
    background-color: #f8f9fa;
    font-weight: 600;
    color: #495057;
    font-size: 12px;
    padding: 10px 6px;
}

.table-striped tbody tr:nth-of-type(odd) {
    background-color: rgba(0, 0, 0, 0.02);
}

.table-responsive {
    display: block;
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    margin: 0;
    padding: 0;
}

/* Log-specific styling */
.log-row {
    transition: background-color 0.15s ease-in-out;
}

.log-row:hover {
    background-color: rgba(0, 123, 255, 0.05);
}

.log-id {
    font-weight: 600;
    color: #6c757d;
}

.user-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.user-link {
    color: #007bff;
    text-decoration: none;
    font-weight: 500;
}

.user-link:hover {
    color: #0056b3;
    text-decoration: underline;
}

.user-link i {
    margin-right: 4px;
}

.user-id {
    color: #6c757d;
    font-size: 10px;
}

.user-info.anonymous {
    color: #6c757d;
    font-style: italic;
}

.action-info {
    display: flex;
    align-items: center;
    gap: 6px;
}

.action-info i {
    color: #495057;
    width: 14px;
}

.details-text {
    max-width: 250px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Optimized column widths for wide layout */
.table th:nth-child(1), .table td:nth-child(1) { width: 60px; } /* ID */
.table th:nth-child(2), .table td:nth-child(2) { width: 120px; } /* User */
.table th:nth-child(3), .table td:nth-child(3) { width: 130px; } /* Action */
.table th:nth-child(4), .table td:nth-child(4) { width: 250px; } /* Details */
.table th:nth-child(5), .table td:nth-child(5) { width: 100px; } /* Module */
.table th:nth-child(6), .table td:nth-child(6) { width: 120px; } /* IP Address */
.table th:nth-child(7), .table td:nth-child(7) { width: 150px; } /* Device */
.table th:nth-child(8), .table td:nth-child(8) { width: 130px; } /* Timestamp */
.table th:nth-child(9), .table td:nth-child(9) { width: 80px; } /* Status */

.module-badge {
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 500;
    color: #fff;
}

.module-system { background-color: #6c757d; }
.module-auth { background-color: #007bff; }
.module-user { background-color: #28a745; }
.module-book { background-color: #ffc107; color: #212529; }
.module-admin { background-color: #dc3545; }
.module-library { background-color: #17a2b8; }

.ip-info {
    display: flex;
    align-items: center;
    gap: 4px;
    font-family: monospace;
    font-size: 12px;
}

.ip-info i {
    color: #6c757d;
}

.device-info {
    display: flex;
    align-items: center;
    gap: 4px;
    max-width: 150px;
}

.device-info i {
    color: #6c757d;
}

.device-text {
    font-size: 12px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.timestamp-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.timestamp-info .date {
    font-weight: 500;
    font-size: 12px;
}

.timestamp-info .time {
    color: #6c757d;
    font-size: 11px;
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

.no-data small {
    display: block;
    margin-top: 8px;
    font-size: 12px;
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
    background-color: #007bff;
    border-color: #007bff;
}

.pagination .page-link:hover {
    color: #0056b3;
    background-color: #e9ecef;
    border-color: #dee2e6;
}









/* Wide Layout Responsive Design */
@media (min-width: 1200px) {
    .main-content-wide {
        padding: 20px 5px !important;
    }

    .card-full-width {
        margin: 0 0 25px 0;
    }



    .table th,
    .table td {
        padding: 10px 8px;
    }
}

@media (max-width: 992px) {
    .main-content-wide {
        padding: 15px 5px !important;
    }



    .info-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }

    .info-cards {
        grid-template-columns: 1fr;
        gap: 12px;
    }
}

@media (max-width: 768px) {
    .main-content-wide {
        margin-left: 70px !important;
        width: calc(100% - 70px) !important;
        padding: 10px 5px !important;
    }

    .pagination-wrapper {
        flex-direction: column;
        text-align: center;
    }

    .table-responsive {
        font-size: 12px;
    }

    .table th,
    .table td {
        padding: 6px 4px;
    }

    .details-text {
        max-width: 80px;
    }

    .device-info {
        max-width: 80px;
    }



    .card-body-full-width {
        padding: 10px 5px;
    }

    .summary-box-wide {
        padding: 10px;
    }

    /* Hide some columns on mobile for better space utilization */
    .table th:nth-child(6), .table td:nth-child(6), /* IP Address */
    .table th:nth-child(7), .table td:nth-child(7) { /* Device */
        display: none;
    }

    /* Info panel mobile adjustments */
    .info-content {
        padding: 15px;
    }

    .info-cards {
        grid-template-columns: 1fr;
    }

    .info-card {
        padding: 12px;
    }

    .monitoring-item {
        padding: 10px;
        gap: 10px;
    }
}

@media (max-width: 576px) {
    .main-content-wide {
        margin-left: 0 !important;
        width: 100% !important;
        padding: 8px 3px !important;
    }

    .form-responsive {
        flex-direction: column;
    }

    .form-group {
        width: 100%;
    }



    .table {
        font-size: 11px;
    }

    .table th,
    .table td {
        padding: 4px 2px;
    }

    .card-body-full-width {
        padding: 8px 3px;
    }

    /* Hide more columns on very small screens */
    .table th:nth-child(1), .table td:nth-child(1), /* ID */
    .table th:nth-child(5), .table td:nth-child(5) { /* Module */
        display: none;
    }

    /* Info panel small mobile adjustments */
    .info-content {
        padding: 12px;
    }

    .info-section h3 {
        font-size: 16px;
    }

    .info-card {
        padding: 10px;
    }

    .info-card h4 {
        font-size: 13px;
    }

    .info-card p {
        font-size: 11px;
    }

    .monitoring-item {
        padding: 8px;
        gap: 8px;
    }

    .monitoring-details strong {
        font-size: 12px;
    }

    .monitoring-details span {
        font-size: 10px;
    }

    .info-tips li {
        font-size: 11px;
    }
}
</style>

<script>
// Enhanced Panel Toggle Functions
function toggleInfoPanel() {
    const panel = document.getElementById('infoPanel');
    const isVisible = panel.style.display !== 'none';

    if (isVisible) {
        panel.style.animation = 'slideUp 0.3s ease-out';
        setTimeout(() => {
            panel.style.display = 'none';
        }, 300);
    } else {
        panel.style.display = 'block';
        panel.style.animation = 'slideDown 0.3s ease-out';
    }

    // Store preference in localStorage
    localStorage.setItem('activityLogsInfoPanelVisible', !isVisible);
}



// Quick Filter Functions
function applyQuickFilter(type) {
    const form = document.querySelector('.search-form');
    const today = new Date().toISOString().split('T')[0];

    switch(type) {
        case 'today':
            document.getElementById('date_from').value = today;
            document.getElementById('date_to').value = today;
            break;
        case 'failed':
            document.getElementById('status').value = 'failure';
            break;
    }

    form.submit();
}

document.addEventListener('DOMContentLoaded', function() {
    // Check if info panel should be visible on load
    const shouldShowInfo = localStorage.getItem('activityLogsInfoPanelVisible');
    if (shouldShowInfo === 'true') {
        document.getElementById('infoPanel').style.display = 'block';
    }

    // Simple table interactions
    const logRows = document.querySelectorAll('.log-row');

    logRows.forEach(row => {
        row.addEventListener('click', function() {
            // Toggle row selection
            this.classList.toggle('selected');
        });

        // Add tooltip for truncated content
        const detailsCell = row.querySelector('.details-text');
        if (detailsCell && detailsCell.scrollWidth > detailsCell.clientWidth) {
            detailsCell.style.cursor = 'help';
        }
    });

    // Auto-refresh functionality
    let autoRefresh = false;
    const refreshInterval = 30000; // 30 seconds
    let refreshTimer;

    function toggleAutoRefresh() {
        autoRefresh = !autoRefresh;
        if (autoRefresh) {
            refreshTimer = setInterval(() => {
                // Only refresh if no filters are applied to avoid disrupting user's work
                const hasFilters = new URLSearchParams(window.location.search).toString();
                if (!hasFilters || hasFilters === 'page=1') {
                    window.location.reload();
                }
            }, refreshInterval);
            console.log('Auto-refresh enabled');
        } else {
            clearInterval(refreshTimer);
            console.log('Auto-refresh disabled');
        }
    }

    // Add keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+R or F5 for manual refresh
        if ((e.ctrlKey && e.key === 'r') || e.key === 'F5') {
            e.preventDefault();
            window.location.reload();
        }

        // Ctrl+E for export
        if (e.ctrlKey && e.key === 'e') {
            e.preventDefault();
            const exportUrl = document.querySelector('.btn-secondary[onclick*="export=csv"]');
            if (exportUrl) {
                window.location.href = exportUrl.getAttribute('onclick').match(/location\.href='([^']+)'/)[1];
            }
        }
    });



    // Add loading states for export
    const exportButton = document.querySelector('.btn-secondary[onclick*="export=csv"]');
    if (exportButton) {
        exportButton.addEventListener('click', function() {
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
            this.disabled = true;

            // Re-enable after 3 seconds
            setTimeout(() => {
                this.innerHTML = originalText;
                this.disabled = false;
            }, 3000);
        });
    }

    // Enhanced date picker functionality
    const dateFromInput = document.getElementById('date_from');
    const dateToInput = document.getElementById('date_to');

    if (dateFromInput && dateToInput) {
        dateFromInput.addEventListener('change', function() {
            if (this.value && dateToInput.value && this.value > dateToInput.value) {
                dateToInput.value = this.value;
            }
        });

        dateToInput.addEventListener('change', function() {
            if (this.value && dateFromInput.value && this.value < dateFromInput.value) {
                dateFromInput.value = this.value;
            }
        });
    }


});

// Simple utility functions
function clearAllFilters() {
    const form = document.querySelector('.search-form');
    const inputs = form.querySelectorAll('input, select');

    inputs.forEach(input => {
        if (input.type === 'text' || input.type === 'date') {
            input.value = '';
        } else if (input.tagName === 'SELECT') {
            input.value = 'all';
        }
    });

    form.submit();
}
</script>

<?php include "../admin/includes/footer.php"; ?>