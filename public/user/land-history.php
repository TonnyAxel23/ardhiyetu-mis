<?php
require_once __DIR__ . '/../../includes/init.php';
require_login();

$user_id = $_SESSION['user_id'];
$record_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Check if user owns this land or has permission to view
$land_sql = "SELECT l.*, u.name as owner_name 
            FROM land_records l 
            JOIN users u ON l.owner_id = u.user_id 
            WHERE l.record_id = ? 
            AND (l.owner_id = ? OR ? IN (SELECT user_id FROM users WHERE role = 'admin'))";
$land_stmt = mysqli_prepare($conn, $land_sql);
mysqli_stmt_bind_param($land_stmt, "iii", $record_id, $user_id, $user_id);
mysqli_stmt_execute($land_stmt);
$land_result = mysqli_stmt_get_result($land_stmt);
$land = mysqli_fetch_assoc($land_result);

if (!$land) {
    flash_message('error', 'Land record not found or access denied.');
    redirect('my-lands.php');
}

// Get complete land history
$history_sql = "CALL GetLandHistory(?)";
$history_stmt = mysqli_prepare($conn, $history_sql);
mysqli_stmt_bind_param($history_stmt, "i", $record_id);
mysqli_stmt_execute($history_stmt);
$history_result = mysqli_stmt_get_result($history_stmt);
$history = [];
while ($row = mysqli_fetch_assoc($history_result)) {
    $history[] = $row;
}
mysqli_stmt_close($history_stmt);

// Get ownership chain
$chain_sql = "CALL GetOwnershipChain(?)";
$chain_stmt = mysqli_prepare($conn, $chain_sql);
mysqli_stmt_bind_param($chain_stmt, "i", $record_id);
mysqli_stmt_execute($chain_stmt);
$chain_result = mysqli_stmt_get_result($chain_stmt);
$ownership_chain = [];
while ($row = mysqli_fetch_assoc($chain_result)) {
    $ownership_chain[] = $row;
}
mysqli_stmt_close($chain_stmt);

// Get all splits from this land
$splits_sql = "SELECT l.*, u.name as owner_name, 
                      (SELECT COUNT(*) FROM land_records WHERE parent_record_id = l.record_id) as has_splits
               FROM land_records l 
               JOIN users u ON l.owner_id = u.user_id 
               WHERE l.parent_record_id = ?
               ORDER BY l.registered_at DESC";
$splits_stmt = mysqli_prepare($conn, $splits_sql);
mysqli_stmt_bind_param($splits_stmt, "i", $record_id);
mysqli_stmt_execute($splits_stmt);
$splits_result = mysqli_stmt_get_result($splits_stmt);
$splits = [];
while ($row = mysqli_fetch_assoc($splits_result)) {
    $splits[] = $row;
}
mysqli_stmt_close($splits_stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Land History - <?php echo htmlspecialchars($land['parcel_no']); ?> - ArdhiYetu</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .history-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .land-header-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            border-left: 5px solid #007bff;
        }
        
        .history-timeline {
            position: relative;
            margin: 40px 0;
            padding-left: 30px;
        }
        
        .history-timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #007bff;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -25px;
            top: 25px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #007bff;
            border: 3px solid white;
            box-shadow: 0 0 0 3px #007bff;
        }
        
        .timeline-item.ownership {
            border-left-color: #28a745;
        }
        
        .timeline-item.ownership::before {
            background: #28a745;
            box-shadow: 0 0 0 3px #28a745;
        }
        
        .timeline-item.land_change {
            border-left-color: #ffc107;
        }
        
        .timeline-item.land_change::before {
            background: #ffc107;
            box-shadow: 0 0 0 3px #ffc107;
        }
        
        .timeline-date {
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .timeline-type {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            margin-left: 10px;
        }
        
        .timeline-type.ownership {
            background: #d4edda;
            color: #155724;
        }
        
        .timeline-type.land_change {
            background: #fff3cd;
            color: #856404;
        }
        
        .timeline-content {
            margin-top: 10px;
        }
        
        .timeline-details {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            font-size: 14px;
        }
        
        .chain-visualization {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin: 30px 0;
        }
        
        .chain-item {
            display: flex;
            align-items: center;
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        
        .chain-arrow {
            margin: 0 15px;
            color: #007bff;
        }
        
        .chain-depth {
            font-size: 12px;
            color: #666;
            margin-left: 10px;
        }
        
        .splits-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin: 30px 0;
        }
        
        .split-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 3px solid #28a745;
        }
        
        .empty-history {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .history-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
            display: block;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .history-timeline {
                padding-left: 20px;
            }
            
            .history-timeline::before {
                left: 10px;
            }
            
            .timeline-item::before {
                left: -17px;
            }
            
            .chain-item {
                flex-direction: column;
                text-align: center;
            }
            
            .chain-arrow {
                transform: rotate(90deg);
                margin: 10px 0;
            }
        }
        
        .document-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #007bff;
            text-decoration: none;
            margin-top: 5px;
        }
        
        .document-link:hover {
            text-decoration: underline;
        }
        
        .ownership-timeline {
            display: flex;
            overflow-x: auto;
            padding: 20px 0;
            margin: 20px 0;
            gap: 20px;
        }
        
        .ownership-node {
            min-width: 200px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
            position: relative;
        }
        
        .ownership-node::after {
            content: '→';
            position: absolute;
            right: -15px;
            top: 50%;
            transform: translateY(-50%);
            color: #007bff;
            font-size: 20px;
        }
        
        .ownership-node:last-child::after {
            display: none;
        }
        
        .node-date {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
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

    <main class="history-container">
        <div class="container">
            <div class="page-header">
                <h1><i class="fas fa-history"></i> Land History & Ownership Chain</h1>
                <p>Complete history of land <?php echo htmlspecialchars($land['parcel_no']); ?></p>
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <a href="my-lands.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to My Lands
                    </a>
                    <a href="?id=<?php echo $record_id; ?>&print=true" class="btn" target="_blank">
                        <i class="fas fa-print"></i> Print History
                    </a>
                    <a href="land-chain-pdf.php?id=<?php echo $record_id; ?>" class="btn">
                        <i class="fas fa-file-pdf"></i> Export PDF
                    </a>
                </div>
            </div>

            <!-- Land Header Card -->
            <div class="land-header-card">
                <h2><?php echo htmlspecialchars($land['parcel_no']); ?></h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                    <div>
                        <strong>Location:</strong> <?php echo htmlspecialchars($land['location']); ?>
                    </div>
                    <div>
                        <strong>Size:</strong> <?php echo number_format($land['size'], 2); ?> acres
                    </div>
                    <div>
                        <strong>Current Owner:</strong> <?php echo htmlspecialchars($land['owner_name']); ?>
                    </div>
                    <div>
                        <strong>Status:</strong> <span class="status-badge status-<?php echo $land['status']; ?>"><?php echo ucfirst($land['status']); ?></span>
                    </div>
                </div>
                <?php if ($land['original_parcel_no']): ?>
                <div style="margin-top: 10px; padding: 10px; background: #e3f2fd; border-radius: 5px;">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Original Parcel:</strong> <?php echo htmlspecialchars($land['original_parcel_no']); ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- History Statistics -->
            <div class="history-stats">
                <?php
                // Calculate statistics
                $ownership_count = 0;
                $change_count = 0;
                $total_transfers = 0;
                $first_owner = '';
                $first_date = '';
                
                foreach ($history as $item) {
                    if ($item['history_type'] == 'ownership') {
                        $ownership_count++;
                        $total_transfers++;
                        if (empty($first_date) || $item['effective_date'] < $first_date) {
                            $first_date = $item['effective_date'];
                            $first_owner = $item['change_description'];
                        }
                    } else {
                        $change_count++;
                    }
                }
                ?>
                <div class="stat-card">
                    <span class="stat-number"><?php echo count($history); ?></span>
                    <span class="stat-label">Total History Events</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo $ownership_count; ?></span>
                    <span class="stat-label">Ownership Transfers</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo $change_count; ?></span>
                    <span class="stat-label">Land Changes</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo count($splits); ?></span>
                    <span class="stat-label">Land Splits</span>
                </div>
            </div>

            <!-- Ownership Chain Visualization -->
            <div class="chain-visualization">
                <h3><i class="fas fa-link"></i> Ownership Chain</h3>
                <?php if (count($ownership_chain) > 1): ?>
                    <div class="ownership-timeline">
                        <?php foreach ($ownership_chain as $index => $chain_item): ?>
                            <div class="ownership-node">
                                <div style="font-weight: bold;"><?php echo htmlspecialchars($chain_item['parcel_no']); ?></div>
                                <div style="margin: 5px 0;"><?php echo htmlspecialchars($chain_item['owner_name']); ?></div>
                                <div style="font-size: 14px; color: #666;">
                                    <?php echo number_format($chain_item['size'], 2); ?> acres
                                </div>
                                <div class="node-date">
                                    <?php if ($index == 0): ?>
                                        Current
                                    <?php else: ?>
                                        Generation <?php echo $chain_item['depth']; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div style="margin-top: 20px;">
                        <strong>Chain Path:</strong> 
                        <?php echo end($ownership_chain)['chain_path'] ?? 'Not available'; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-history">
                        <i class="fas fa-info-circle fa-3x" style="color: #ddd; margin-bottom: 20px;"></i>
                        <p>No ownership chain found. This appears to be the original land parcel.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- History Timeline -->
            <div class="chain-visualization">
                <h3><i class="fas fa-stream"></i> Complete History Timeline</h3>
                
                <?php if (!empty($history)): ?>
                    <div class="history-timeline">
                        <?php foreach ($history as $item): ?>
                            <div class="timeline-item <?php echo $item['history_type']; ?>">
                                <div class="timeline-date">
                                    <i class="far fa-calendar-alt"></i>
                                    <?php echo date('F j, Y', strtotime($item['effective_date'])); ?>
                                    <span class="timeline-type <?php echo $item['history_type']; ?>">
                                        <?php echo $item['history_type'] == 'ownership' ? 'Ownership' : 'Change'; ?>
                                    </span>
                                </div>
                                
                                <div class="timeline-content">
                                    <strong><?php echo htmlspecialchars($item['change_description']); ?></strong>
                                    
                                    <?php if ($item['history_type'] == 'ownership'): ?>
                                        <div class="timeline-details">
                                            <div><strong>Transfer Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $item['transfer_type'])); ?></div>
                                            <?php if ($item['previous_size'] || $item['new_size']): ?>
                                                <div><strong>Size Change:</strong> 
                                                    <?php if ($item['previous_size']): ?>
                                                        <?php echo number_format($item['previous_size'], 2); ?> → 
                                                    <?php endif; ?>
                                                    <?php echo number_format($item['new_size'], 2); ?> acres
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($item['notes']): ?>
                                                <div><strong>Notes:</strong> <?php echo htmlspecialchars($item['notes']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($item['document_path']): ?>
                                                <a href="../../<?php echo htmlspecialchars($item['document_path']); ?>" 
                                                   class="document-link" target="_blank">
                                                    <i class="fas fa-file-download"></i> View Document
                                                </a>
                                            <?php endif; ?>
                                            <div style="margin-top: 5px; font-size: 12px; color: #666;">
                                                Recorded by: <?php echo htmlspecialchars($item['recorded_by_name']); ?> 
                                                on <?php echo date('M j, Y H:i', strtotime($item['recorded_at'])); ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="timeline-details">
                                            <div><strong>Change Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $item['transfer_type'])); ?></div>
                                            <div style="margin-top: 5px; font-size: 12px; color: #666;">
                                                Changed by: <?php echo htmlspecialchars($item['recorded_by_name']); ?> 
                                                on <?php echo date('M j, Y H:i', strtotime($item['recorded_at'])); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-history">
                        <i class="fas fa-history fa-3x" style="color: #ddd; margin-bottom: 20px;"></i>
                        <h3>No History Found</h3>
                        <p>No historical records found for this land parcel.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Split History -->
            <?php if (!empty($splits)): ?>
            <div class="splits-section">
                <h3><i class="fas fa-code-branch"></i> Land Splits</h3>
                <p>This land has been split into the following parcels:</p>
                
                <?php foreach ($splits as $split): ?>
                    <div class="split-card">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?php echo htmlspecialchars($split['parcel_no']); ?></strong>
                                <span style="margin-left: 10px; font-size: 14px; color: #666;">
                                    <?php echo number_format($split['size'], 2); ?> acres
                                </span>
                            </div>
                            <div>
                                <span class="status-badge status-<?php echo $split['status']; ?>" style="margin-right: 10px;">
                                    <?php echo ucfirst($split['status']); ?>
                                </span>
                                <span>Owner: <?php echo htmlspecialchars($split['owner_name']); ?></span>
                            </div>
                        </div>
                        <div style="margin-top: 10px; display: flex; gap: 10px;">
                            <a href="land-history.php?id=<?php echo $split['record_id']; ?>" class="btn-small">
                                <i class="fas fa-history"></i> View History
                            </a>
                            <?php if ($split['has_splits'] > 0): ?>
                                <span class="split-badge">
                                    <i class="fas fa-sitemap"></i> Has <?php echo $split['has_splits']; ?> split(s)
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div style="margin-top: 30px; text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <h3>Quick Actions</h3>
                <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-top: 15px;">
                    <a href="my-lands.php?action=view&id=<?php echo $record_id; ?>" class="btn">
                        <i class="fas fa-eye"></i> View Land Details
                    </a>
                    <a href="transfer-land.php?land_id=<?php echo $record_id; ?>" class="btn">
                        <i class="fas fa-exchange-alt"></i> Transfer Land
                    </a>
                    <?php if (is_admin()): ?>
                        <a href="../admin/add-land-history.php?land_id=<?php echo $record_id; ?>" class="btn">
                            <i class="fas fa-plus"></i> Add Historical Record
                        </a>
                        <a href="../admin/lands.php?action=edit&id=<?php echo $record_id; ?>" class="btn">
                            <i class="fas fa-edit"></i> Edit Land Record
                        </a>
                    <?php endif; ?>
                </div>
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
                <p style="font-size: 12px; color: #666; margin-top: 5px;">
                    Land History ID: <?php echo $record_id; ?> | Last Updated: <?php echo date('Y-m-d H:i:s'); ?>
                </p>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Print functionality
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('print') === 'true') {
                window.print();
            }
            
            // Timeline animation
            const timelineItems = document.querySelectorAll('.timeline-item');
            timelineItems.forEach((item, index) => {
                item.style.animationDelay = `${index * 0.1}s`;
                item.style.animation = 'fadeInUp 0.5s ease forwards';
            });
            
            // Add CSS animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes fadeInUp {
                    from {
                        opacity: 0;
                        transform: translateY(20px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
                
                .timeline-item {
                    opacity: 0;
                }
            `;
            document.head.appendChild(style);
            
            // Smooth scroll for timeline
            const timeline = document.querySelector('.history-timeline');
            if (timeline) {
                timeline.addEventListener('wheel', (e) => {
                    if (e.deltaY !== 0) {
                        e.preventDefault();
                        timeline.scrollLeft += e.deltaY;
                    }
                });
            }
            
            // Export functionality
            document.querySelectorAll('.export-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const format = this.dataset.format;
                    const landId = <?php echo $record_id; ?>;
                    
                    if (format === 'pdf') {
                        window.location.href = `land-chain-pdf.php?id=${landId}`;
                    } else if (format === 'csv') {
                        window.location.href = `land-history-export.php?id=${landId}&format=csv`;
                    }
                });
            });
        });
    </script>
</body>
</html>