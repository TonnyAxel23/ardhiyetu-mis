<?php
require_once '../../includes/init.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

// Get user statistics
$user_id = $_SESSION['user_id'];

// Get user's land records
$user_lands_sql = "SELECT COUNT(*) as total, SUM(size) as total_size FROM land_records WHERE owner_id = ?";
$stmt = mysqli_prepare($conn, $user_lands_sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_lands_result = mysqli_stmt_get_result($stmt);
$user_lands = mysqli_fetch_assoc($user_lands_result);

// Get user's transfer requests
$user_transfers_sql = "SELECT COUNT(*) as total FROM ownership_transfers WHERE from_user_id = ? OR to_user_id = ?";
$stmt = mysqli_prepare($conn, $user_transfers_sql);
mysqli_stmt_bind_param($stmt, "ii", $user_id, $user_id);
mysqli_stmt_execute($stmt);
$user_transfers_result = mysqli_stmt_get_result($stmt);
$user_transfers = mysqli_fetch_assoc($user_transfers_result);

// Get pending transfers
$pending_transfers_sql = "SELECT COUNT(*) as total FROM ownership_transfers WHERE (from_user_id = ? OR to_user_id = ?) AND status IN ('submitted', 'under_review')";
$stmt = mysqli_prepare($conn, $pending_transfers_sql);
mysqli_stmt_bind_param($stmt, "ii", $user_id, $user_id);
mysqli_stmt_execute($stmt);
$pending_transfers_result = mysqli_stmt_get_result($stmt);
$pending_transfers = mysqli_fetch_assoc($pending_transfers_result);

// Get recent land records
$recent_lands_sql = "SELECT * FROM land_records WHERE owner_id = ? ORDER BY registered_at DESC LIMIT 5";
$stmt = mysqli_prepare($conn, $recent_lands_sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$recent_lands = mysqli_stmt_get_result($stmt);

// Get recent transfer requests
$recent_transfers_sql = "
    SELECT t.*, 
           u1.name as from_name, 
           u2.name as to_name,
           l.parcel_no,
           l.location
    FROM ownership_transfers t
    JOIN users u1 ON t.from_user_id = u1.user_id
    JOIN users u2 ON t.to_user_id = u2.user_id
    JOIN land_records l ON t.record_id = l.record_id
    WHERE (t.from_user_id = ? OR t.to_user_id = ?)
    ORDER BY t.submitted_at DESC 
    LIMIT 5
";
$stmt = mysqli_prepare($conn, $recent_transfers_sql);
mysqli_stmt_bind_param($stmt, "ii", $user_id, $user_id);
mysqli_stmt_execute($stmt);
$recent_transfers = mysqli_stmt_get_result($stmt);

// Get recent activities
$recent_activities_sql = "
    SELECT * FROM user_activities 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
";
$stmt = mysqli_prepare($conn, $recent_activities_sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$recent_activities = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ArdhiYetu</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.css">
    <style>
        .user-dashboard {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .user-header {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 25px 30px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-avatar {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .user-details h2 {
            margin: 0 0 5px 0;
            color: #333;
            font-size: 24px;
        }
        
        .user-details p {
            margin: 0;
            color: #666;
        }
        
        .user-role {
            display: inline-block;
            padding: 5px 15px;
            background: #667eea;
            color: white;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 5px;
        }
        
        .user-actions {
            display: flex;
            gap: 15px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 20px;
        }
        
        .stat-content h3 {
            font-size: 32px;
            margin: 0 0 5px 0;
            color: #333;
        }
        
        .stat-content p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }
        
        .stat-trend {
            color: #4CAF50;
            font-weight: 600;
            font-size: 12px;
            margin-top: 10px;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .content-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h3 {
            margin: 0;
            color: #333;
            font-size: 18px;
        }
        
        .card-header a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        
        .card-header a:hover {
            text-decoration: underline;
        }
        
        .card-content {
            padding: 25px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            transition: background-color 0.3s ease;
        }
        
        .activity-item:hover {
            background: #f0f0f0;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-content p {
            margin: 0 0 5px 0;
            color: #333;
        }
        
        .activity-content small {
            color: #999;
            font-size: 12px;
        }
        
        .land-item, .transfer-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.3s ease;
        }
        
        .land-item:last-child, .transfer-item:last-child {
            border-bottom: none;
        }
        
        .land-item:hover, .transfer-item:hover {
            background: #f8f9fa;
        }
        
        .item-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: #f0f0f0;
            color: #667eea;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        
        .item-content {
            flex: 1;
        }
        
        .item-content h4 {
            margin: 0 0 5px 0;
            color: #333;
        }
        
        .item-content p {
            margin: 0;
            color: #666;
            font-size: 13px;
        }
        
        .item-meta {
            text-align: right;
            font-size: 12px;
            color: #999;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 25px;
        }
        
        .action-button {
            background: white;
            border: 2px solid #667eea;
            color: #667eea;
            padding: 15px;
            border-radius: 10px;
            text-decoration: none;
            text-align: center;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .action-button:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }
        
        .system-status {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-top: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .status-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-dot {
            width: 10px;
            height: 10px;
            background: #4CAF50;
            border-radius: 50%;
        }
        
        .status-dot.active {
            background: #4CAF50;
        }
        
        .status-dot.inactive {
            background: #f44336;
        }
        
        @media (max-width: 768px) {
            .user-header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .user-info {
                flex-direction: column;
                text-align: center;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="user-dashboard">
        <div class="dashboard-container">
            <header class="user-header">
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-details">
                        <h2><?php echo htmlspecialchars($_SESSION['name']); ?></h2>
                        <p><?php echo htmlspecialchars($_SESSION['email']); ?></p>
                        <span class="user-role"><?php echo ucfirst($_SESSION['role']); ?></span>
                    </div>
                </div>
                
                <div class="user-actions">
                    <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'officer'): ?>
                        <a href="index.php" class="btn btn-primary" style="background: #667eea; border: none;">
                            <i class="fas fa-shield-alt"></i> Admin Panel
                        </a>
                    <?php endif; ?>
                    <a href="../logout.php" class="btn" style="background: #f8f9fa; color: #333;">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </header>

            <div class="stats-grid">
                <a href="lands.php?view=my" class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-landmark"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $user_lands['total'] ?? 0; ?></h3>
                        <p>My Land Records</p>
                        <?php if ($user_lands['total_size'] > 0): ?>
                            <div class="stat-trend">
                                Total Size: <?php echo number_format($user_lands['total_size'], 2); ?> ha
                            </div>
                        <?php endif; ?>
                    </div>
                </a>
                
                <a href="transfers.php?view=my" class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $user_transfers['total'] ?? 0; ?></h3>
                        <p>Transfer Requests</p>
                        <?php if ($pending_transfers['total'] > 0): ?>
                            <div class="stat-trend" style="color: #ff9800;">
                                <i class="fas fa-clock"></i> <?php echo $pending_transfers['total']; ?> Pending
                            </div>
                        <?php endif; ?>
                    </div>
                </a>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo format_date($_SESSION['last_login'] ?? date('Y-m-d H:i:s'), 'M j, Y'); ?></h3>
                        <p>Last Login</p>
                        <div class="stat-trend">
                            <i class="fas fa-circle"></i> Account Active
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo date('Y'); ?></h3>
                        <p>System Year</p>
                        <div class="stat-trend">
                            <i class="fas fa-calendar"></i> <?php echo date('F j, Y'); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-grid">
                <div class="content-card">
                    <div class="card-header">
                        <h3>Recent Land Records</h3>
                        <a href="lands.php?view=my">View All</a>
                    </div>
                    <div class="card-content">
                        <?php if (mysqli_num_rows($recent_lands) > 0): ?>
                            <?php while ($land = mysqli_fetch_assoc($recent_lands)): ?>
                                <div class="land-item">
                                    <div class="item-icon">
                                        <i class="fas fa-landmark"></i>
                                    </div>
                                    <div class="item-content">
                                        <h4><?php echo htmlspecialchars($land['parcel_no']); ?></h4>
                                        <p><?php echo htmlspecialchars($land['location']); ?> • <?php echo number_format($land['size'], 2); ?> ha</p>
                                    </div>
                                    <div class="item-meta">
                                        <?php echo format_date($land['registered_at'], 'M j'); ?><br>
                                        <span class="status-badge status-<?php echo $land['status']; ?>">
                                            <?php echo ucfirst($land['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-landmark"></i>
                                <p>No land records found</p>
                                <a href="lands.php?action=add" class="btn" style="background: #667eea; color: white; margin-top: 10px;">
                                    <i class="fas fa-plus"></i> Add Land Record
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="content-card">
                    <div class="card-header">
                        <h3>Recent Transfer Requests</h3>
                        <a href="transfers.php?view=my">View All</a>
                    </div>
                    <div class="card-content">
                        <?php if (mysqli_num_rows($recent_transfers) > 0): ?>
                            <?php while ($transfer = mysqli_fetch_assoc($recent_transfers)): ?>
                                <div class="transfer-item">
                                    <div class="item-icon">
                                        <i class="fas fa-exchange-alt"></i>
                                    </div>
                                    <div class="item-content">
                                        <h4><?php echo htmlspecialchars($transfer['parcel_no']); ?></h4>
                                        <p><?php echo htmlspecialchars($transfer['from_name']); ?> → <?php echo htmlspecialchars($transfer['to_name']); ?></p>
                                    </div>
                                    <div class="item-meta">
                                        <?php echo format_date($transfer['submitted_at'], 'M j'); ?><br>
                                        <span class="status-badge status-<?php echo $transfer['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $transfer['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-exchange-alt"></i>
                                <p>No transfer requests found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="content-grid">
                <div class="content-card">
                    <div class="card-header">
                        <h3>Recent Activities</h3>
                        <a href="audit-logs.php?view=my">View All</a>
                    </div>
                    <div class="card-content">
                        <?php if (mysqli_num_rows($recent_activities) > 0): ?>
                            <div class="activity-list">
                                <?php while ($activity = mysqli_fetch_assoc($recent_activities)): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <i class="fas fa-history"></i>
                                        </div>
                                        <div class="activity-content">
                                            <p><?php echo htmlspecialchars($activity['description']); ?></p>
                                            <small><?php echo format_date($activity['created_at']); ?> • IP: <?php echo htmlspecialchars($activity['ip_address']); ?></small>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-history"></i>
                                <p>No recent activities</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="content-card">
                    <div class="card-header">
                        <h3>Quick Actions</h3>
                    </div>
                    <div class="card-content">
                        <div class="quick-actions">
                            <a href="lands.php?action=add" class="action-button">
                                <i class="fas fa-plus"></i> Add Land Record
                            </a>
                            <a href="transfers.php?action=new" class="action-button">
                                <i class="fas fa-exchange-alt"></i> New Transfer
                            </a>
                            <a href="profile.php" class="action-button">
                                <i class="fas fa-user-edit"></i> Edit Profile
                            </a>
                            <a href="settings.php" class="action-button">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                        </div>
                        
                        <div class="system-status">
                            <div class="status-info">
                                <span class="status-dot active"></span>
                                <span>System Status: <strong>Online</strong></span>
                            </div>
                            <div class="status-time">
                                <small>Last updated: <?php echo date('h:i A'); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line"></i> Statistics Overview</h3>
                    <a href="reports.php">Detailed Reports</a>
                </div>
                <div class="card-content">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                        <div style="text-align: center;">
                            <div style="font-size: 24px; font-weight: bold; color: #667eea;"><?php echo $user_lands['total'] ?? 0; ?></div>
                            <div style="color: #666; font-size: 14px;">Land Records</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 24px; font-weight: bold; color: #4CAF50;"><?php echo number_format($user_lands['total_size'] ?? 0, 2); ?> ha</div>
                            <div style="color: #666; font-size: 14px;">Total Land Size</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 24px; font-weight: bold; color: #ff9800;"><?php echo $user_transfers['total'] ?? 0; ?></div>
                            <div style="color: #666; font-size: 14px;">Total Transfers</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 24px; font-weight: bold; color: #f44336;"><?php echo $pending_transfers['total'] ?? 0; ?></div>
                            <div style="color: #666; font-size: 14px;">Pending Reviews</div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
                        <h4 style="margin-top: 0; color: #333;">Quick Tips</h4>
                        <ul style="margin: 10px 0; padding-left: 20px; color: #666;">
                            <li>Keep your land records updated regularly</li>
                            <li>Review pending transfer requests promptly</li>
                            <li>Contact support for any land disputes</li>
                            <li>Backup important documents regularly</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Update time every minute
            function updateTime() {
                const now = new Date();
                const timeString = now.toLocaleTimeString('en-US', { 
                    hour: '2-digit', 
                    minute: '2-digit',
                    hour12: true 
                });
                document.querySelector('.status-time small').textContent = `Last updated: ${timeString}`;
            }
            
            updateTime();
            setInterval(updateTime, 60000);
            
            // Add hover effects to stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>