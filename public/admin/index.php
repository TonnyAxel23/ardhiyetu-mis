<?php
// This file is located at: public/admin/index.php
require_once '../../includes/init.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

// Check if user has admin role
if ($_SESSION['role'] !== 'admin') {
    flash_message('error', 'You do not have permission to access the admin area.');
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit();
}

// Get statistics
$total_users = mysqli_query($conn, "SELECT COUNT(*) as count FROM users");
$total_lands = mysqli_query($conn, "SELECT COUNT(*) as count FROM land_records");
$total_transfers = mysqli_query($conn, "SELECT COUNT(*) as count FROM ownership_transfers");
$pending_transfers = mysqli_query($conn, "SELECT COUNT(*) as count FROM ownership_transfers WHERE status IN ('submitted', 'under_review')");
$active_users = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$recent_activities = mysqli_query($conn, "SELECT COUNT(*) as count FROM user_activities WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");

$total_users = mysqli_fetch_assoc($total_users)['count'];
$total_lands = mysqli_fetch_assoc($total_lands)['count'];
$total_transfers = mysqli_fetch_assoc($total_transfers)['count'];
$pending_transfers = mysqli_fetch_assoc($pending_transfers)['count'];
$active_users = mysqli_fetch_assoc($active_users)['count'];
$recent_activities = mysqli_fetch_assoc($recent_activities)['count'];

// Get recent transfers
$recent_transfers_sql = "SELECT t.*, u1.name as from_name, u2.name as to_name, l.parcel_no 
                         FROM ownership_transfers t
                         JOIN users u1 ON t.from_user_id = u1.user_id
                         JOIN users u2 ON t.to_user_id = u2.user_id
                         JOIN land_records l ON t.record_id = l.record_id
                         ORDER BY t.submitted_at DESC LIMIT 10";
$recent_transfers = mysqli_query($conn, $recent_transfers_sql);

// Get recent registrations
$recent_users_sql = "SELECT * FROM users ORDER BY created_at DESC LIMIT 10";
$recent_users = mysqli_query($conn, $recent_users_sql);

// Get system status
$disk_usage = round(disk_free_space(".") / disk_total_space(".") * 100, 2);
$memory_usage = round(memory_get_usage(true) / 1024 / 1024, 2); // MB
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ArdhiYetu</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.css">
    <style>
        /* Admin-specific styles */
        :root {
            --primary: #2E86AB;
            --primary-dark: #1C5D7F;
            --secondary: #F4A261;
            --accent: #E76F51;
            --light: #F8F9FA;
            --dark: #2C3E50;
            --success: #27AE60;
            --warning: #F39C12;
            --danger: #E74C3C;
            --gray: #95A5A6;
            --shadow: 0 10px 30px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: #f5f7fa;
            min-height: 100vh;
        }
        
        .admin-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background: var(--dark);
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
            font-weight: 700;
            color: white;
            text-decoration: none;
            margin-bottom: 15px;
        }
        
        .logo i {
            font-size: 24px;
        }
        
        .user-info {
            font-size: 14px;
            color: var(--gray);
        }
        
        .sidebar-nav {
            padding: 0 20px;
        }
        
        .nav-section {
            margin-bottom: 25px;
        }
        
        .nav-section h3 {
            font-size: 12px;
            text-transform: uppercase;
            color: var(--gray);
            margin-bottom: 10px;
            letter-spacing: 1px;
        }
        
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 5px;
            transition: var(--transition);
        }
        
        .sidebar-nav a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .sidebar-nav a.active {
            background: var(--primary);
            color: white;
        }
        
        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: auto;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 15px;
            background: rgba(255,255,255,0.1);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            width: 100%;
            justify-content: center;
            margin-bottom: 10px;
        }
        
        .btn:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .btn.logout {
            background: var(--danger);
        }
        
        .btn.logout:hover {
            background: #c0392b;
        }
        
        /* Main Content Styles */
        .admin-main {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: var(--shadow);
        }
        
        .header-left h1 {
            font-size: 24px;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .header-left p {
            color: var(--gray);
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .system-status {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--success);
        }
        
        .status-indicator {
            width: 10px;
            height: 10px;
            background: var(--success);
            border-radius: 50%;
        }
        
        .status-indicator.active {
            background: var(--success);
        }
        
        .status-indicator.inactive {
            background: var(--danger);
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
        }
        
        .header-actions .btn {
            width: auto;
            padding: 10px 20px;
        }
        
        .header-actions .secondary {
            background: var(--light);
            color: var(--dark);
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
            padding: 25px;
            border-radius: 10px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .stat-info h3 {
            font-size: 32px;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .stat-info p {
            color: var(--gray);
        }
        
        .stat-trend {
            margin-left: auto;
            color: var(--success);
            font-weight: 600;
        }
        
        .stat-link {
            margin-left: auto;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-card {
            background: white;
            border-radius: 10px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .chart-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .chart-header h3 {
            color: var(--dark);
        }
        
        .chart-header a {
            color: var(--primary);
            text-decoration: none;
            font-size: 14px;
        }
        
        .chart-content {
            padding: 20px;
        }
        
        .system-metrics {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .metric {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .metric-label {
            width: 100px;
            color: var(--dark);
            font-size: 14px;
        }
        
        .progress-bar {
            flex: 1;
            height: 8px;
            background: #eee;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--primary);
            border-radius: 4px;
        }
        
        .metric-value {
            width: 80px;
            text-align: right;
            color: var(--dark);
            font-weight: 500;
        }
        
        .activities-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .activity-item {
            display: flex;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .activity-content p {
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .activity-content small {
            color: var(--gray);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        /* Tables Grid */
        .tables-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
        }
        
        .table-card {
            background: white;
            border-radius: 10px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .table-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-header h3 {
            color: var(--dark);
        }
        
        .table-content {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 1px solid #eee;
        }
        
        .data-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-submitted {
            background: #FFF3CD;
            color: #856404;
        }
        
        .status-under_review {
            background: #D1ECF1;
            color: #0C5460;
        }
        
        .status-approved {
            background: #D4EDDA;
            color: #155724;
        }
        
        .status-rejected {
            background: #F8D7DA;
            color: #721C24;
        }
        
        .status-active {
            background: #D4EDDA;
            color: #155724;
        }
        
        .status-inactive {
            background: #F8D7DA;
            color: #721C24;
        }
        
        .role-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        
        .role-admin {
            background: #D4EDDA;
            color: #155724;
        }
        
        .role-officer {
            background: #D1ECF1;
            color: #0C5460;
        }
        
        .role-user {
            background: #E2E3E5;
            color: #383D41;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-small {
            padding: 5px 10px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            color: var(--dark);
            text-decoration: none;
            font-size: 12px;
            transition: var(--transition);
        }
        
        .btn-small:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .text-center {
            text-align: center;
        }
        
        /* Alert Styles */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-error {
            background: #F8D7DA;
            color: #721C24;
            border-left: 4px solid #F5C6CB;
        }
        
        .alert-success {
            background: #D4EDDA;
            color: #155724;
            border-left: 4px solid #C3E6CB;
        }
        
        .alert-warning {
            background: #FFF3CD;
            color: #856404;
            border-left: 4px solid #FFEEBA;
        }
        
        .alert-info {
            background: #D1ECF1;
            color: #0C5460;
            border-left: 4px solid #BEE5EB;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .sidebar {
                width: 220px;
            }
            
            .admin-main {
                margin-left: 220px;
            }
            
            .charts-grid,
            .tables-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -250px;
                z-index: 1000;
                transition: var(--transition);
            }
            
            .sidebar.active {
                left: 0;
            }
            
            .admin-main {
                margin-left: 0;
            }
            
            .admin-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="<?php echo BASE_URL; ?>/index.php" class="logo">
                    <i class="fas fa-landmark"></i> ArdhiYetu
                </a>
                <p class="user-info">Admin: <?php echo htmlspecialchars($_SESSION['name']); ?></p>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <h3>Main</h3>
                    <a href="index.php" class="active">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="users.php">
                        <i class="fas fa-users"></i> Users
                    </a>
                    <a href="lands.php">
                        <i class="fas fa-landmark"></i> Land Records
                    </a>
                    <a href="transfers.php">
                        <i class="fas fa-exchange-alt"></i> Transfers
                    </a>
                </div>
                
                <div class="nav-section">
                    <h3>Reports</h3>
                    <a href="reports.php">
                        <i class="fas fa-chart-bar"></i> Analytics
                    </a>
                    <a href="audit-logs.php">
                        <i class="fas fa-history"></i> Audit Logs
                    </a>
                    <a href="system-logs.php">
                        <i class="fas fa-server"></i> System Logs
                    </a>
                </div>
                
                <div class="nav-section">
                    <h3>System</h3>
                    <a href="settings.php">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                    <a href="backup.php">
                        <i class="fas fa-database"></i> Backup
                    </a>
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <a href="<?php echo BASE_URL; ?>/dashboard.php" class="btn">
                    <i class="fas fa-user"></i> User View
                </a>
               <a href="../logout.php" class="btn logout">
                <i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </aside>

        <main class="admin-main">
            <header class="admin-header">
                <div class="header-left">
                    <h1>Admin Dashboard</h1>
                    <p>System overview and management</p>
                </div>
                <div class="header-right">
                    <div class="system-status">
                        <span class="status-indicator active"></span>
                        <span>System Online</span>
                    </div>
                    <div class="header-actions">
                        <button class="btn" id="refresh-btn">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                        <button class="btn secondary" id="help-btn">
                            <i class="fas fa-question-circle"></i> Help
                        </button>
                    </div>
                </div>
            </header>

            <div class="admin-content">
                <?php 
                // Display flash messages
                if (isset($_SESSION['flash'])) {
                    foreach ($_SESSION['flash'] as $type => $message) {
                        echo "<div class='alert alert-$type'>$message</div>";
                    }
                    unset($_SESSION['flash']);
                }
                ?>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $total_users; ?></h3>
                            <p>Total Users</p>
                        </div>
                        <div class="stat-trend">
                            <i class="fas fa-arrow-up"></i> 12%
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-landmark"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $total_lands; ?></h3>
                            <p>Land Records</p>
                        </div>
                        <div class="stat-trend">
                            <i class="fas fa-arrow-up"></i> 8%
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $total_transfers; ?></h3>
                            <p>Total Transfers</p>
                        </div>
                        <div class="stat-trend">
                            <i class="fas fa-arrow-up"></i> 15%
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $pending_transfers; ?></h3>
                            <p>Pending Reviews</p>
                        </div>
                        <a href="transfers.php?status=submitted" class="stat-link">Review Now</a>
                    </div>
                </div>

                <div class="charts-grid">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3>System Status</h3>
                        </div>
                        <div class="chart-content">
                            <div class="system-metrics">
                                <div class="metric">
                                    <span class="metric-label">Disk Usage:</span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $disk_usage; ?>%"></div>
                                    </div>
                                    <span class="metric-value"><?php echo $disk_usage; ?>%</span>
                                </div>
                                <div class="metric">
                                    <span class="metric-label">Memory Usage:</span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: 65%"></div>
                                    </div>
                                    <span class="metric-value"><?php echo $memory_usage; ?> MB</span>
                                </div>
                                <div class="metric">
                                    <span class="metric-label">Active Users:</span>
                                    <span class="metric-value"><?php echo $active_users; ?> users</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="chart-card">
                        <div class="chart-header">
                            <h3>Recent Activities</h3>
                            <a href="audit-logs.php">View All</a>
                        </div>
                        <div class="chart-content">
                            <div class="activities-list">
                                <?php
                                $activities_sql = "SELECT a.*, u.name 
                                                   FROM user_activities a
                                                   JOIN users u ON a.user_id = u.user_id
                                                   ORDER BY a.created_at DESC LIMIT 5";
                                $activities = mysqli_query($conn, $activities_sql);
                                
                                if (mysqli_num_rows($activities) > 0):
                                    while ($activity = mysqli_fetch_assoc($activities)):
                                ?>
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div class="activity-content">
                                            <p><strong><?php echo htmlspecialchars($activity['name']); ?></strong> 
                                            <?php echo htmlspecialchars($activity['description']); ?></p>
                                            <small><?php echo format_date($activity['created_at']); ?></small>
                                        </div>
                                    </div>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                    <div class="empty-state">
                                        <i class="fas fa-history"></i>
                                        <p>No recent activities</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tables-grid">
                    <div class="table-card">
                        <div class="table-header">
                            <h3>Recent Transfer Requests</h3>
                            <a href="transfers.php">View All</a>
                        </div>
                        <div class="table-content">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Reference No</th>
                                        <th>Parcel</th>
                                        <th>From</th>
                                        <th>To</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($recent_transfers) > 0): ?>
                                        <?php while ($transfer = mysqli_fetch_assoc($recent_transfers)): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($transfer['reference_no']); ?></td>
                                                <td><?php echo htmlspecialchars($transfer['parcel_no']); ?></td>
                                                <td><?php echo htmlspecialchars($transfer['from_name']); ?></td>
                                                <td><?php echo htmlspecialchars($transfer['to_name']); ?></td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $transfer['status']; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $transfer['status'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo format_date($transfer['submitted_at']); ?></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="transfers.php?action=view&id=<?php echo $transfer['transfer_id']; ?>" 
                                                           class="btn-small" 
                                                           title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($transfer['status'] == 'submitted'): ?>
                                                            <a href="transfers.php?action=review&id=<?php echo $transfer['transfer_id']; ?>" 
                                                               class="btn-small" 
                                                               title="Review">
                                                                <i class="fas fa-check"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">
                                                <div class="empty-state">
                                                    <i class="fas fa-exchange-alt"></i>
                                                    <p>No transfer requests</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="table-card">
                        <div class="table-header">
                            <h3>Recent User Registrations</h3>
                            <a href="users.php">View All</a>
                        </div>
                        <div class="table-content">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Role</th>
                                        <th>Joined</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($recent_users) > 0): ?>
                                        <?php while ($user = mysqli_fetch_assoc($recent_users)): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                                <td>
                                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo format_date($user['created_at']); ?></td>
                                                <td>
                                                    <span class="status-badge <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">
                                                <div class="empty-state">
                                                    <i class="fas fa-users"></i>
                                                    <p>No users found</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Refresh button
            document.getElementById('refresh-btn').addEventListener('click', function() {
                location.reload();
            });

            // Help button
            document.getElementById('help-btn').addEventListener('click', function() {
                alert('Help documentation coming soon!');
            });
        });
    </script>
</body>
</html>
<?php