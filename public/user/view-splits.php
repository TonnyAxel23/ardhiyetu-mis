<?php
require_once __DIR__ . '/../../includes/init.php';
require_login();

$user_id = $_SESSION['user_id'];
$land_id = isset($_GET['land_id']) ? intval($_GET['land_id']) : 0;

// Check if user owns this land
$land_sql = "SELECT * FROM land_records WHERE record_id = ? AND owner_id = ?";
$land_stmt = mysqli_prepare($conn, $land_sql);
mysqli_stmt_bind_param($land_stmt, "ii", $land_id, $user_id);
mysqli_stmt_execute($land_stmt);
$land = mysqli_fetch_assoc(mysqli_stmt_get_result($land_stmt));

if (!$land) {
    flash_message('error', 'Land record not found or access denied.');
    redirect('my-lands.php');
}

// Get all splits from this land
$splits_sql = "SELECT l.*, u.name as owner_name, u.email as owner_email
               FROM land_records l
               JOIN users u ON l.owner_id = u.user_id
               WHERE l.parent_record_id = ?
               ORDER BY l.registered_at DESC";
$splits_stmt = mysqli_prepare($conn, $splits_sql);
mysqli_stmt_bind_param($splits_stmt, "i", $land_id);
mysqli_stmt_execute($splits_stmt);
$splits = mysqli_stmt_get_result($splits_stmt);

// Calculate total split size
$total_split_size = 0;
while ($split = mysqli_fetch_assoc($splits)) {
    $total_split_size += $split['size'];
}
mysqli_data_seek($splits, 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Land Splits - ArdhiYetu</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .split-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .original-land-info {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            border-left: 5px solid #007bff;
        }
        
        .split-visual {
            display: flex;
            height: 80px;
            border-radius: 8px;
            overflow: hidden;
            margin: 20px 0;
            border: 2px solid #dee2e6;
        }
        
        .remaining-portion {
            background: #e3f2fd;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1976d2;
            font-weight: bold;
            flex-direction: column;
        }
        
        .split-portion {
            background: #c8e6c9;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #2e7d32;
            font-weight: bold;
            border-left: 3px solid white;
            flex-direction: column;
        }
        
        .splits-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .split-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-top: 4px solid #28a745;
        }
        
        .split-card h3 {
            margin-top: 0;
            color: #2e7d32;
        }
        
        .split-info {
            margin: 10px 0;
        }
        
        .split-info-item {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
            padding: 5px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        
        .split-label {
            font-weight: 600;
            color: #555;
        }
        
        .split-value {
            color: #333;
        }
        
        .split-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .no-splits {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .size-summary {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .size-item {
            text-align: center;
        }
        
        .size-number {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        
        .size-label {
            font-size: 14px;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .splits-list {
                grid-template-columns: 1fr;
            }
            
            .size-summary {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="../../index.php" class="logo">
                <i class="fas fa-landmark"></i> ArdhiYetu
            </a>
            <div class="nav-links">
                <a href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="my-lands.php"><i class="fas fa-landmark"></i> My Lands</a>
                <a href="land-map.php"><i class="fas fa-map"></i> Land Map</a>
                <a href="transfer-land.php"><i class="fas fa-exchange-alt"></i> Transfer</a>
                <a href="../documents.php"><i class="fas fa-file-alt"></i> Documents</a>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="../logout.php" class="btn logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
            <button class="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <main class="split-container">
        <div class="container">
            <div class="page-header">
                <h1><i class="fas fa-sitemap"></i> Land Split History</h1>
                <p>View splits from your land parcel</p>
                <a href="my-lands.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to My Lands
                </a>
            </div>

            <div class="original-land-info">
                <h2>Original Land: <?php echo htmlspecialchars($land['parcel_no']); ?></h2>
                <div class="size-summary">
                    <div class="size-item">
                        <div class="size-number"><?php echo number_format($land['size'], 2); ?></div>
                        <div class="size-label">Original Size (acres)</div>
                    </div>
                    <div class="size-item">
                        <div class="size-number"><?php echo number_format($total_split_size, 2); ?></div>
                        <div class="size-label">Total Split Size (acres)</div>
                    </div>
                    <div class="size-item">
                        <div class="size-number"><?php echo number_format($land['size'] - $total_split_size, 2); ?></div>
                        <div class="size-label">Remaining (acres)</div>
                    </div>
                </div>
                
                <div class="split-visual">
                    <?php
                    $remaining_size = $land['size'] - $total_split_size;
                    $remaining_percentage = ($remaining_size / $land['size']) * 100;
                    $split_percentage = ($total_split_size / $land['size']) * 100;
                    ?>
                    <?php if ($remaining_size > 0): ?>
                    <div class="remaining-portion" style="width: <?php echo $remaining_percentage; ?>%">
                        <span><?php echo number_format($remaining_size, 2); ?> acres</span>
                        <small>Remaining</small>
                    </div>
                    <?php endif; ?>
                    <?php if ($total_split_size > 0): ?>
                    <div class="split-portion" style="width: <?php echo $split_percentage; ?>%">
                        <span><?php echo number_format($total_split_size, 2); ?> acres</span>
                        <small>Split Off</small>
                    </div>
                    <?php endif; ?>
                </div>
                
                <p><strong>Location:</strong> <?php echo htmlspecialchars($land['location']); ?></p>
                <p><strong>Status:</strong> <span class="status-badge status-<?php echo $land['status']; ?>"><?php echo ucfirst($land['status']); ?></span></p>
            </div>

            <h2>Split Land Parcels</h2>
            
            <?php if (mysqli_num_rows($splits) > 0): ?>
                <div class="splits-list">
                    <?php while ($split = mysqli_fetch_assoc($splits)): ?>
                        <div class="split-card">
                            <h3><?php echo htmlspecialchars($split['parcel_no']); ?></h3>
                            <div class="split-info">
                                <div class="split-info-item">
                                    <span class="split-label">Size:</span>
                                    <span class="split-value"><?php echo number_format($split['size'], 2); ?> acres</span>
                                </div>
                                <div class="split-info-item">
                                    <span class="split-label">Owner:</span>
                                    <span class="split-value"><?php echo htmlspecialchars($split['owner_name']); ?></span>
                                </div>
                                <div class="split-info-item">
                                    <span class="split-label">Status:</span>
                                    <span class="split-value">
                                        <span class="status-badge status-<?php echo $split['status']; ?>">
                                            <?php echo ucfirst($split['status']); ?>
                                        </span>
                                    </span>
                                </div>
                                <div class="split-info-item">
                                    <span class="split-label">Created:</span>
                                    <span class="split-value"><?php echo format_date($split['registered_at']); ?></span>
                                </div>
                            </div>
                            <div class="split-actions">
                                <?php if ($split['owner_id'] == $user_id): ?>
                                    <a href="?action=view&id=<?php echo $split['record_id']; ?>" class="btn-small">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                <?php endif; ?>
                                <?php if ($split['status'] == 'active' && $split['owner_id'] == $user_id): ?>
                                    <a href="transfer-land.php?land_id=<?php echo $split['record_id']; ?>" class="btn-small">
                                        <i class="fas fa-exchange-alt"></i> Transfer
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="no-splits">
                    <i class="fas fa-sitemap fa-3x" style="color: #ddd; margin-bottom: 20px;"></i>
                    <h3>No Splits Found</h3>
                    <p>This land has not been split yet.</p>
                    <?php if ($land['status'] == 'active'): ?>
                        <a href="transfer-land.php?land_id=<?php echo $land_id; ?>" class="btn btn-primary" style="margin-top: 15px;">
                            <i class="fas fa-code-branch"></i> Create First Split
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 30px; text-align: center;">
                <a href="my-lands.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to My Lands
                </a>
                <?php if ($land['status'] == 'active' && ($land['size'] - $total_split_size) >= 0.02): ?>
                    <a href="transfer-land.php?land_id=<?php echo $land_id; ?>" class="btn btn-primary">
                        <i class="fas fa-code-branch"></i> Create Another Split
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><i class="fas fa-landmark"></i> ArdhiYetu</h3>
                    <p>Digital Land Administration System</p>
                </div>
                <div class="footer-section">
                    <h3>Need Help?</h3>
                    <p><i class="fas fa-envelope"></i> support@ardhiyetu.go.ke</p>
                    <p><i class="fas fa-phone"></i> 0700 000 000</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> ArdhiYetu Land Management System</p>
            </div>
        </div>
    </footer>
</body>
</html>