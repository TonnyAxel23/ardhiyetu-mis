<?php
require_once '../includes/init.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// SECURITY: Use prepared statements for all database queries
function get_user_data($conn, $user_id) {
    // Remove profile_picture from the query since it doesn't exist
    $stmt = mysqli_prepare($conn, "SELECT user_id, name, email, phone, created_at FROM users WHERE user_id = ?");
    if (!$stmt) {
        error_log("Database error: " . mysqli_error($conn));
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    if (!mysqli_stmt_execute($stmt)) {
        error_log("Statement execution error: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }
    
    $result = mysqli_stmt_get_result($stmt);
    $user_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    return $user_data;
}

function get_user_statistics($conn, $user_id) {
    $stats = [
        'lands_count' => 0,
        'active_lands' => 0,
        'pending_transfers' => 0,
        'completed_transfers' => 0,
        'total_value' => 0,
        'total_area' => 0
    ];
    
    // Check if estimated_value column exists
    $check_column = mysqli_query($conn, "SHOW COLUMNS FROM land_records LIKE 'estimated_value'");
    $has_estimated_value = mysqli_num_rows($check_column) > 0;
    
    // All queries with prepared statements
    $queries = [
        'lands_count' => "SELECT COUNT(*) as count FROM land_records WHERE owner_id = ?",
        'active_lands' => "SELECT COUNT(*) as count FROM land_records WHERE owner_id = ? AND status = 'active'",
        'pending_transfers' => "SELECT COUNT(*) as count FROM ownership_transfers WHERE from_user_id = ? AND status IN ('submitted', 'under_review')",
        'completed_transfers' => "SELECT COUNT(*) as count FROM ownership_transfers WHERE from_user_id = ? AND status = 'completed'",
        'total_area' => "SELECT COALESCE(SUM(size), 0) as total FROM land_records WHERE owner_id = ? AND status = 'active'"
    ];
    
    // Add total_value query only if column exists
    if ($has_estimated_value) {
        $queries['total_value'] = "SELECT COALESCE(SUM(estimated_value), 0) as total FROM land_records WHERE owner_id = ? AND status = 'active'";
    }
    
    foreach ($queries as $key => $query) {
        $stmt = mysqli_prepare($conn, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $user_id);
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($result);
                $stats[$key] = $row ? ($row['count'] ?? $row['total']) : 0;
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    return $stats;
}

function get_recent_lands($conn, $user_id) {
    // Check if estimated_value column exists
    $check_column = mysqli_query($conn, "SHOW COLUMNS FROM land_records LIKE 'estimated_value'");
    $has_estimated_value = mysqli_num_rows($check_column) > 0;
    
    $sql = "SELECT record_id, parcel_no, location, size, status, registered_at";
    if ($has_estimated_value) {
        $sql .= ", estimated_value";
    }
    $sql .= " FROM land_records WHERE owner_id = ? ORDER BY registered_at DESC LIMIT 5";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return false;
    
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

function get_recent_transfers($conn, $user_id) {
    // Check if recipient_photo exists in users table
    $check_column = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'profile_picture'");
    $has_profile_picture = mysqli_num_rows($check_column) > 0;
    
    $sql = "SELECT t.*, l.parcel_no, u.name as recipient_name";
    if ($has_profile_picture) {
        $sql .= ", u.profile_picture as recipient_photo";
    }
    $sql .= " FROM ownership_transfers t
             JOIN land_records l ON t.record_id = l.record_id
             JOIN users u ON t.to_user_id = u.user_id
             WHERE t.from_user_id = ? 
             ORDER BY t.submitted_at DESC 
             LIMIT 5";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return false;
    
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

function get_unread_notifications($conn, $user_id) {
    $stmt = mysqli_prepare($conn, 
        "SELECT notification_id, title, message, type, sent_at, is_read
         FROM notifications 
         WHERE user_id = ? AND is_read = FALSE 
         ORDER BY sent_at DESC 
         LIMIT 10");
    if (!$stmt) return false;
    
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

// Get history statistics for user
function get_history_statistics($conn, $user_id) {
    // Check if ownership_history table exists
    $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'ownership_history'");
    if (mysqli_num_rows($check_table) == 0) {
        return [
            'lands_with_history' => 0,
            'total_transfers' => 0,
            'first_transfer' => null,
            'last_transfer' => null
        ];
    }
    
    $history_stats_sql = "
        SELECT 
            COUNT(DISTINCT oh.record_id) as lands_with_history,
            COUNT(oh.history_id) as total_transfers,
            MIN(oh.effective_date) as first_transfer,
            MAX(oh.effective_date) as last_transfer
        FROM ownership_history oh
        JOIN land_records l ON oh.record_id = l.record_id
        WHERE l.owner_id = ?
        AND oh.to_user_id = ?
    ";
    $history_stats_stmt = mysqli_prepare($conn, $history_stats_sql);
    if (!$history_stats_stmt) {
        return [
            'lands_with_history' => 0,
            'total_transfers' => 0,
            'first_transfer' => null,
            'last_transfer' => null
        ];
    }
    
    mysqli_stmt_bind_param($history_stats_stmt, "ii", $user_id, $user_id);
    mysqli_stmt_execute($history_stats_stmt);
    $history_stats_result = mysqli_stmt_get_result($history_stats_stmt);
    $data = mysqli_fetch_assoc($history_stats_result);
    mysqli_stmt_close($history_stats_stmt);
    
    return $data ?: [
        'lands_with_history' => 0,
        'total_transfers' => 0,
        'first_transfer' => null,
        'last_transfer' => null
    ];
}

// Get upcoming deadlines
function get_upcoming_deadlines($conn, $user_id) {
    // Check if deadlines table exists
    $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'deadlines'");
    if (mysqli_num_rows($check_table) == 0) {
        return false;
    }
    
    $stmt = mysqli_prepare($conn, 
        "SELECT d.*, l.parcel_no, l.location
         FROM deadlines d
         JOIN land_records l ON d.record_id = l.record_id
         WHERE l.owner_id = ? 
         AND d.deadline_date >= CURDATE()
         AND d.status = 'pending'
         ORDER BY d.deadline_date ASC 
         LIMIT 5");
    if (!$stmt) return false;
    
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

// Get monthly statistics for chart
function get_monthly_statistics($conn, $user_id) {
    $check_column = mysqli_query($conn, "SHOW COLUMNS FROM land_records LIKE 'size'");
    $has_size = mysqli_num_rows($check_column) > 0;
    
    $sql = "SELECT 
                DATE_FORMAT(registered_at, '%Y-%m') as month,
                COUNT(*) as count";
    if ($has_size) {
        $sql .= ", SUM(size) as total_area";
    } else {
        $sql .= ", 0 as total_area";
    }
    $sql .= " FROM land_records 
             WHERE owner_id = ? 
             AND registered_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
             GROUP BY DATE_FORMAT(registered_at, '%Y-%m')
             ORDER BY month ASC";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return [];
    
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    mysqli_stmt_close($stmt);
    
    return $data;
}

// Fetch all data
$user = get_user_data($conn, $user_id);
if (!$user) {
    // User not found, log out and redirect
    session_destroy();
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit();
}

$stats = get_user_statistics($conn, $user_id);
$history_stats = get_history_statistics($conn, $user_id);
$lands_result = get_recent_lands($conn, $user_id);
$transfers_result = get_recent_transfers($conn, $user_id);
$notifications_result = get_unread_notifications($conn, $user_id);
$deadlines_result = get_upcoming_deadlines($conn, $user_id);
$monthly_stats = get_monthly_statistics($conn, $user_id);

// Prepare chart data
$chart_labels = [];
$chart_counts = [];
$chart_areas = [];
foreach ($monthly_stats as $stat) {
    $chart_labels[] = date('M Y', strtotime($stat['month'] . '-01'));
    $chart_counts[] = $stat['count'];
    $chart_areas[] = $stat['total_area'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ArdhiYetu</title>
    
    <!-- Performance: Preload critical assets -->
    <link rel="preload" href="../assets/css/style.css" as="style">
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style">
    
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Accessibility: Add ARIA attributes and meta tags -->
    <meta name="description" content="ArdhiYetu Dashboard - Manage your land records, transfers, and notifications">
    
    <style>
        .dashboard-welcome {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        
        .dashboard-welcome::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 20px 20px;
            opacity: 0.1;
        }
        
        .welcome-content {
            position: relative;
            z-index: 1;
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid white;
            overflow: hidden;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #667eea;
            font-weight: bold;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            border-left: 4px solid #667eea;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.total-value {
            border-left-color: #4CAF50;
        }
        
        .stat-card.total-area {
            border-left-color: #2196F3;
        }
        
        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            opacity: 0.8;
        }
        
        .stat-card h3 {
            font-size: 1.8rem;
            margin: 0.5rem 0;
        }
        
        .stat-card .trend {
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .trend.positive {
            color: #4CAF50;
        }
        
        .trend.negative {
            color: #f44336;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }
        
        .dashboard-section {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        .section-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-content {
            padding: 1.5rem;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .notification-item {
            padding: 1rem;
            border-left: 4px solid #ddd;
            margin-bottom: 0.5rem;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .notification-item.unread {
            background: #f8f9fa;
            border-left-color: #667eea;
        }
        
        .notification-item:hover {
            background: #e9ecef;
        }
        
        .land-item, .transfer-item, .deadline-item {
            padding: 1rem;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .land-item:hover, .transfer-item:hover {
            border-color: #667eea;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.1);
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-active { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-completed { background: #d1ecf1; color: #0c5460; }
        
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
        }
        
        .quick-action {
            padding: 1rem;
            text-align: center;
            border: 2px dashed #ddd;
            border-radius: 10px;
            transition: all 0.3s ease;
            color: inherit;
            text-decoration: none;
        }
        
        .quick-action:hover {
            border-color: #667eea;
            background: #f8f9fa;
            transform: scale(1.05);
        }
        
        .action-icon {
            width: 50px;
            height: 50px;
            background: #667eea;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 1.5rem;
        }
        
        .deadline-item.urgent {
            border-left: 4px solid #f44336;
            background: #ffebee;
        }
        
        .deadline-item.warning {
            border-left: 4px solid #ff9800;
            background: #fff3e0;
        }
        
        .recipient-avatar-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .text-primary { color: #667eea; }
        .text-success { color: #4CAF50; }
        .text-warning { color: #ff9800; }
        .text-info { color: #2196F3; }
        .text-secondary { color: #6c757d; }
        .text-muted { color: #6c757d; }
        
        .mt-2 { margin-top: 0.5rem; }
        .mt-4 { margin-top: 1.5rem; }
        .mb-3 { margin-bottom: 1rem; }
        
        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .btn-outline-primary {
            border: 1px solid #667eea;
            color: #667eea;
            background: transparent;
        }
        
        .btn-outline-primary:hover {
            background: #667eea;
            color: white;
        }
        
        .btn-outline-success {
            border: 1px solid #4CAF50;
            color: #4CAF50;
            background: transparent;
        }
        
        .btn-outline-success:hover {
            background: #4CAF50;
            color: white;
        }
        
        @media (max-width: 768px) {
            .dashboard-welcome {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Accessibility: Skip to main content link -->
    <a href="#main-content" class="skip-to-content">Skip to main content</a>
    
    <nav class="navbar" aria-label="Main Navigation">
        <div class="container">
            <a href="index.php" class="logo">
                <i class="fas fa-landmark" aria-hidden="true"></i> 
                <span>ArdhiYetu</span>
            </a>
            
            <button class="mobile-menu-btn" aria-label="Toggle menu" aria-expanded="false">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="nav-links">
                <a href="dashboard.php" class="active" aria-current="page">
                    <i class="fas fa-tachometer-alt" aria-hidden="true"></i> Dashboard
                </a>
                <a href="user/my-lands.php">
                    <i class="fas fa-landmark" aria-hidden="true"></i> My Lands
                </a>
                <a href="land-map.php"><i class="fas fa-map"></i> Land Map</a>
                <a href="user/transfer-land.php">
                    <i class="fas fa-exchange-alt" aria-hidden="true"></i> Transfer
                </a>
                <a href="documents.php"><i class="fas fa-file-alt"></i> Documents</a>
                <a href="user/profile.php" class="user-profile-link">
                    <i class="fas fa-user" aria-hidden="true"></i> Profile
                </a>
                <?php if (is_admin()): ?>
                    <a href="admin/index.php" class="btn admin-btn">
                        <i class="fas fa-user-shield" aria-hidden="true"></i> Admin
                    </a>
                <?php endif; ?>
                <a href="logout.php" class="btn logout">
                    <i class="fas fa-sign-out-alt" aria-hidden="true"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <main id="main-content" class="dashboard">
        <div class="container">
            <!-- Enhanced Welcome Section -->
            <div class="dashboard-welcome">
                <div class="welcome-content">
                    <h1>Welcome back, <?php echo htmlspecialchars($user['name']); ?>! 👋</h1>
                    <p>Here's your land management overview for today</p>
                    <?php if (isset($user['created_at'])): ?>
                        <p class="member-since">
                            <i class="fas fa-calendar-alt"></i> 
                            Member since: <?php echo date('F Y', strtotime($user['created_at'])); ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
            </div>

            <?php if (function_exists('display_flash_message')): ?>
                <?php display_flash_message(); ?>
            <?php endif; ?>

            <!-- Statistics Grid -->
            <div class="stats-grid" role="region" aria-label="Dashboard statistics">
                <div class="stat-card">
                    <i class="fas fa-landmark text-primary"></i>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['lands_count']); ?></h3>
                        <p>Total Land Records</p>
                    </div>
                </div>

                <div class="stat-card">
                    <i class="fas fa-check-circle text-success"></i>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['active_lands']); ?></h3>
                        <p>Active Lands</p>
                    </div>
                </div>

                <div class="stat-card">
                    <i class="fas fa-clock text-warning"></i>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['pending_transfers']); ?></h3>
                        <p>Pending Transfers</p>
                    </div>
                </div>

                <?php if ($stats['total_value'] > 0): ?>
                <div class="stat-card total-value">
                    <i class="fas fa-coins text-success"></i>
                    <div class="stat-info">
                        <h3>Ksh <?php echo number_format($stats['total_value'], 2); ?></h3>
                        <p>Total Land Value</p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($stats['total_area'] > 0): ?>
                <div class="stat-card total-area">
                    <i class="fas fa-ruler-combined text-info"></i>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['total_area'], 2); ?></h3>
                        <p>Total Area (acres)</p>
                    </div>
                </div>
                <?php endif; ?>

                <div class="stat-card">
                    <i class="fas fa-history text-secondary"></i>
                    <div class="stat-info">
                        <h3><?php echo number_format($history_stats['total_transfers']); ?></h3>
                        <p>Historical Transfers</p>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content Grid -->
            <div class="dashboard-grid">
                <!-- Notifications Section -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2><i class="fas fa-bell" aria-hidden="true"></i> Notifications</h2>
                        <a href="notifications.php" class="btn btn-sm">
                            View All
                        </a>
                    </div>
                    <div class="section-content">
                        <?php if ($notifications_result && mysqli_num_rows($notifications_result) > 0): ?>
                            <div class="notifications-list">
                                <?php while ($notification = mysqli_fetch_assoc($notifications_result)): ?>
                                    <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                                        <div class="notification-header">
                                            <span class="notification-type badge badge-<?php echo $notification['type']; ?>">
                                                <?php echo ucfirst($notification['type']); ?>
                                            </span>
                                            <small class="text-muted">
                                                <?php 
                                                if (function_exists('format_date')) {
                                                    echo format_date($notification['sent_at']);
                                                } else {
                                                    echo date('M d, Y', strtotime($notification['sent_at']));
                                                }
                                                ?>
                                            </small>
                                        </div>
                                        <h4><?php echo htmlspecialchars($notification['title']); ?></h4>
                                        <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <?php if (!$notification['is_read']): ?>
                                            <div class="notification-actions">
                                                <a href="mark-notification-read.php?id=<?php echo $notification['notification_id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    Mark as Read
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                                <p>No new notifications</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Lands Section -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2><i class="fas fa-landmark" aria-hidden="true"></i> Recent Lands</h2>
                        <a href="user/my-lands.php" class="btn btn-sm">
                            View All
                        </a>
                    </div>
                    <div class="section-content">
                        <?php if ($lands_result && mysqli_num_rows($lands_result) > 0): ?>
                            <div class="lands-list">
                                <?php while ($land = mysqli_fetch_assoc($lands_result)): ?>
                                    <div class="land-item">
                                        <div class="land-header">
                                            <h4><?php echo htmlspecialchars($land['parcel_no']); ?></h4>
                                            <span class="status-badge status-<?php echo $land['status']; ?>">
                                                <?php echo ucfirst($land['status']); ?>
                                            </span>
                                        </div>
                                        <p class="text-muted"><?php echo htmlspecialchars($land['location']); ?></p>
                                        <div class="land-details">
                                            <span><i class="fas fa-ruler-combined"></i> <?php echo $land['size']; ?> acres</span>
                                            <?php if (isset($land['estimated_value']) && $land['estimated_value']): ?>
                                                <span><i class="fas fa-coins"></i> Ksh <?php echo number_format($land['estimated_value'], 2); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="land-actions mt-2">
                                            <a href="user/view-land.php?id=<?php echo $land['record_id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <?php if ($land['status'] == 'active'): ?>
                                                <a href="user/transfer-land.php?land_id=<?php echo $land['record_id']; ?>" 
                                                   class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-exchange-alt"></i> Transfer
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-landmark fa-3x text-muted mb-3"></i>
                                <p>No land records yet</p>
                                <a href="user/my-lands.php?action=add" class="btn btn-primary">
                                    Register Your First Land
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Transfers Section -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2><i class="fas fa-exchange-alt" aria-hidden="true"></i> Recent Transfers</h2>
                        <a href="transfer-history.php" class="btn btn-sm">
                            View All
                        </a>
                    </div>
                    <div class="section-content">
                        <?php if ($transfers_result && mysqli_num_rows($transfers_result) > 0): ?>
                            <div class="transfers-list">
                                <?php while ($transfer = mysqli_fetch_assoc($transfers_result)): ?>
                                    <div class="transfer-item">
                                        <div class="transfer-header">
                                            <div class="recipient-info">
                                                <div class="recipient-avatar-placeholder">
                                                    <?php echo strtoupper(substr($transfer['recipient_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($transfer['recipient_name']); ?></strong>
                                                    <small class="text-muted d-block"><?php echo htmlspecialchars($transfer['parcel_no']); ?></small>
                                                </div>
                                            </div>
                                            <span class="status-badge status-<?php echo $transfer['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $transfer['status'])); ?>
                                            </span>
                                        </div>
                                        <div class="transfer-details mt-2">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar-alt"></i> 
                                                <?php 
                                                if (function_exists('format_date')) {
                                                    echo format_date($transfer['submitted_at']);
                                                } else {
                                                    echo date('M d, Y', strtotime($transfer['submitted_at']));
                                                }
                                                ?>
                                            </small>
                                            <?php if (isset($transfer['price']) && $transfer['price']): ?>
                                                <span class="transfer-price">
                                                    Ksh <?php echo number_format($transfer['price'], 2); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                                <p>No transfer history</p>
                                <a href="user/transfer-land.php" class="btn btn-primary">
                                    Initiate Transfer
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Upcoming Deadlines Section -->
                <?php if ($deadlines_result): ?>
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2><i class="fas fa-calendar-exclamation" aria-hidden="true"></i> Upcoming Deadlines</h2>
                        <a href="deadlines.php" class="btn btn-sm">
                            View All
                        </a>
                    </div>
                    <div class="section-content">
                        <?php if (mysqli_num_rows($deadlines_result) > 0): ?>
                            <div class="deadlines-list">
                                <?php while ($deadline = mysqli_fetch_assoc($deadlines_result)): 
                                    $days_remaining = floor((strtotime($deadline['deadline_date']) - time()) / (60 * 60 * 24));
                                    $urgency_class = $days_remaining <= 3 ? 'urgent' : ($days_remaining <= 7 ? 'warning' : '');
                                ?>
                                    <div class="deadline-item <?php echo $urgency_class; ?>">
                                        <div class="deadline-header">
                                            <h4><?php echo htmlspecialchars($deadline['title']); ?></h4>
                                            <span class="badge badge-<?php echo $urgency_class ? 'danger' : 'warning'; ?>">
                                                <?php echo $days_remaining; ?> days
                                            </span>
                                        </div>
                                        <p class="text-muted mb-1"><?php echo htmlspecialchars($deadline['parcel_no']); ?></p>
                                        <small>
                                            <i class="fas fa-calendar-day"></i> 
                                            Due: <?php echo date('M d, Y', strtotime($deadline['deadline_date'])); ?>
                                        </small>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                                <p>No upcoming deadlines</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Charts Section (Conditional) -->
            <?php if (!empty($chart_labels)): ?>
            <div class="chart-container mt-4">
                <h2><i class="fas fa-chart-line"></i> Land Registration Trends</h2>
                <canvas id="landChart" height="100"></canvas>
            </div>
            <?php endif; ?>

            <!-- Quick Actions Section -->
            <div class="dashboard-section mt-4">
                <div class="section-header">
                    <h2><i class="fas fa-bolt" aria-hidden="true"></i> Quick Actions</h2>
                </div>
                <div class="section-content">
                    <div class="quick-actions-grid">
                        <a href="user/my-lands.php?action=add" class="quick-action">
                            <div class="action-icon">
                                <i class="fas fa-plus"></i>
                            </div>
                            <h4>Register Land</h4>
                        </a>
                        
                        <a href="user/transfer-land.php" class="quick-action">
                            <div class="action-icon">
                                <i class="fas fa-exchange-alt"></i>
                            </div>
                            <h4>Transfer Land</h4>
                        </a>
                        
                        <a href="documents.php" class="quick-action">
                            <div class="action-icon">
                                <i class="fas fa-file-download"></i>
                            </div>
                            <h4>Documents</h4>
                        </a>
                        
                        <a href="land-map.php" class="quick-action">
                            <div class="action-icon">
                                <i class="fas fa-map"></i>
                            </div>
                            <h4>View Map</h4>
                        </a>
                        
                        <a href="user/profile.php" class="quick-action">
                            <div class="action-icon">
                                <i class="fas fa-user-edit"></i>
                            </div>
                            <h4>Update Profile</h4>
                        </a>
                        
                        <a href="help.php" class="quick-action">
                            <div class="action-icon">
                                <i class="fas fa-question-circle"></i>
                            </div>
                            <h4>Help Center</h4>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><i class="fas fa-landmark" aria-hidden="true"></i> ArdhiYetu</h3>
                    <p>Digital Land Administration System</p>
                </div>
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="user/my-lands.php">My Lands</a></li>
                        <li><a href="user/profile.php">Profile</a></li>
                        <li><a href="help.php">Help Center</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Contact Support</h3>
                    <p><i class="fas fa-envelope" aria-hidden="true"></i> support@ardhiyetu.go.ke</p>
                    <p><i class="fas fa-phone" aria-hidden="true"></i> 0700 000 000</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> ArdhiYetu Land Management System</p>
            </div>
        </div>
    </footer>

    <?php if (!empty($chart_labels)): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Chart.js Implementation
        const ctx = document.getElementById('landChart');
        if (ctx) {
            const landChart = new Chart(ctx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chart_labels); ?>,
                    datasets: [{
                        label: 'Number of Lands',
                        data: <?php echo json_encode($chart_counts); ?>,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'Total Area (acres)',
                        data: <?php echo json_encode($chart_areas); ?>,
                        borderColor: '#4CAF50',
                        backgroundColor: 'rgba(76, 175, 80, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        // Mobile menu toggle
        document.querySelector('.mobile-menu-btn')?.addEventListener('click', function() {
            const navLinks = document.querySelector('.nav-links');
            if (navLinks) {
                navLinks.style.display = navLinks.style.display === 'flex' ? 'none' : 'flex';
                this.setAttribute('aria-expanded', navLinks.style.display === 'flex');
            }
        });

        // Mark notification as read with AJAX
        document.querySelectorAll('.notification-item .btn-outline-primary').forEach(button => {
            button.addEventListener('click', async function(e) {
                e.preventDefault();
                const notificationItem = this.closest('.notification-item');
                
                try {
                    const response = await fetch(this.href);
                    if (response.ok) {
                        notificationItem.classList.remove('unread');
                        notificationItem.classList.add('read');
                        this.remove();
                    }
                } catch (error) {
                    console.error('Error marking notification as read:', error);
                }
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>