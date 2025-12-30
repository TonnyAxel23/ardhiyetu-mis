<?php
require_once '../../includes/init.php';
require_admin();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Generate CSRF token if needed
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle actions
if (isset($_GET['action'])) {
    $transfer_id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    // Verify transfer exists
    $check_sql = "SELECT transfer_id, status FROM ownership_transfers WHERE transfer_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "i", $transfer_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) === 0) {
        flash_message('error', 'Transfer request not found.');
        header('Location: transfers.php');
        exit();
    }
    
    $transfer_data = mysqli_fetch_assoc($check_result);
    $current_status = $transfer_data['status'];
    
    switch ($action) {
        case 'view':
            // Display transfer details (handled by modal)
            break;
            
        case 'review':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Verify CSRF token
                if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
                    flash_message('error', 'Invalid security token.');
                    header('Location: transfers.php');
                    exit();
                }
                
                $status = mysqli_real_escape_string($conn, $_POST['status']);
                $review_notes = mysqli_real_escape_string($conn, $_POST['review_notes']);
                
                // Validate status transition
                $valid_transitions = [
                    'submitted' => ['under_review', 'approved', 'rejected'],
                    'under_review' => ['approved', 'rejected']
                ];
                
                if (!isset($valid_transitions[$current_status]) || !in_array($status, $valid_transitions[$current_status])) {
                    flash_message('error', 'Invalid status transition.');
                    header('Location: transfers.php');
                    exit();
                }
                
                // Require notes for rejections
                if ($status === 'rejected' && empty(trim($review_notes))) {
                    flash_message('error', 'Review notes are required when rejecting a transfer.');
                    header('Location: transfers.php?action=review&id=' . $transfer_id);
                    exit();
                }
                
                if ($status === 'approved') {
                    // Check if land record exists
                    $land_sql = "SELECT l.record_id 
                                FROM ownership_transfers t
                                JOIN land_records l ON t.record_id = l.record_id
                                WHERE t.transfer_id = ?";
                    $land_stmt = mysqli_prepare($conn, $land_sql);
                    mysqli_stmt_bind_param($land_stmt, "i", $transfer_id);
                    mysqli_stmt_execute($land_stmt);
                    
                    if (mysqli_stmt_num_rows($land_stmt) === 0) {
                        flash_message('error', 'Associated land record no longer exists.');
                        header('Location: transfers.php');
                        exit();
                    }
                    
                    // Approve the transfer
                    $result = completeLandTransfer($transfer_id, $_SESSION['user_id'], $review_notes);
                    if ($result['success']) {
                        flash_message('success', $result['message']);
                    } else {
                        flash_message('error', $result['message']);
                    }
                } elseif ($status === 'rejected') {
                    // Reject the transfer
                    $result = rejectTransferRequest($transfer_id, $_SESSION['user_id'], $review_notes);
                    if ($result['success']) {
                        flash_message('success', $result['message']);
                    } else {
                        flash_message('error', $result['message']);
                    }
                } else {
                    // Mark as under review
                    $sql = "UPDATE ownership_transfers 
                           SET status = ?, review_notes = ?, reviewed_at = NOW(), reviewed_by = ? 
                           WHERE transfer_id = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "ssii", $status, $review_notes, $_SESSION['user_id'], $transfer_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        flash_message('success', "Transfer marked as " . str_replace('_', ' ', $status) . ".");
                    } else {
                        flash_message('error', 'Failed to update transfer status.');
                    }
                }
                
                header('Location: transfers.php');
                exit();
            }
            break;
            
        case 'delete':
            // Only allow deletion of pending transfers
            if ($current_status === 'approved' || $current_status === 'rejected') {
                flash_message('error', 'Cannot delete processed transfer requests.');
                header('Location: transfers.php');
                exit();
            }
            
            // Delete the transfer
            $sql = "DELETE FROM ownership_transfers WHERE transfer_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $transfer_id);
            
            if (mysqli_stmt_execute($stmt)) {
                log_activity($_SESSION['user_id'], 'transfer_delete', "Transfer ID $transfer_id deleted");
                flash_message('success', 'Transfer request deleted successfully.');
            } else {
                flash_message('error', 'Failed to delete transfer request.');
            }
            
            header('Location: transfers.php');
            exit();
            
        case 'bulk_action':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transfer_ids']) && isset($_POST['bulk_action'])) {
                // Verify CSRF token
                if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
                    flash_message('error', 'Invalid security token.');
                    header('Location: transfers.php');
                    exit();
                }
                
                $transfer_ids = array_map('intval', $_POST['transfer_ids']);
                $bulk_action = $_POST['bulk_action'];
                
                if (empty($transfer_ids)) {
                    flash_message('error', 'No transfers selected.');
                    header('Location: transfers.php');
                    exit();
                }
                
                $ids_placeholder = implode(',', array_fill(0, count($transfer_ids), '?'));
                $types = str_repeat('i', count($transfer_ids));
                
                switch ($bulk_action) {
                    case 'mark_review':
                        $sql = "UPDATE ownership_transfers SET status = 'under_review', reviewed_at = NOW(), reviewed_by = ? WHERE transfer_id IN ($ids_placeholder) AND status = 'submitted'";
                        $params = array_merge([$_SESSION['user_id']], $transfer_ids);
                        $types = 'i' . $types;
                        $success_message = "Selected transfers marked as under review.";
                        break;
                        
                    case 'delete':
                        // Only allow deletion of pending transfers
                        $sql = "DELETE FROM ownership_transfers WHERE transfer_id IN ($ids_placeholder) AND status IN ('submitted', 'under_review')";
                        $params = $transfer_ids;
                        $success_message = "Selected transfers deleted successfully.";
                        break;
                        
                    default:
                        flash_message('error', 'Invalid bulk action.');
                        header('Location: transfers.php');
                        exit();
                }
                
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, $types, ...$params);
                
                if (mysqli_stmt_execute($stmt)) {
                    $affected = mysqli_stmt_affected_rows($stmt);
                    log_activity($_SESSION['user_id'], 'bulk_transfer_action', "$bulk_action performed on $affected transfers");
                    flash_message('success', $success_message);
                } else {
                    flash_message('error', 'Failed to perform bulk action.');
                }
                
                header('Location: transfers.php');
                exit();
            }
            break;
    }
}

// Build query with review priority
$where = [];
$params = [];
$types = '';

if (!empty($status_filter)) {
    $where[] = "t.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count total transfers
$count_sql = "SELECT COUNT(*) as total FROM ownership_transfers t $where_clause";
$count_stmt = mysqli_prepare($conn, $count_sql);
if ($params) {
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_transfers = mysqli_fetch_assoc($count_result)['total'];

// Pagination
$items_per_page = 20; // Default value
$pagination = paginate($page, $items_per_page, $total_transfers);

// Get transfers with details including review priority
$sql = "SELECT t.*, 
               u1.name as from_name, u1.email as from_email,
               u2.name as to_name, u2.email as to_email,
               l.parcel_no, l.location,
               a.name as reviewer_name,
               DATEDIFF(NOW(), t.submitted_at) as days_pending,
               CASE 
                   WHEN DATEDIFF(NOW(), t.submitted_at) > 14 THEN 'overdue'
                   WHEN DATEDIFF(NOW(), t.submitted_at) > 7 THEN 'warning'
                   ELSE 'normal'
               END as review_priority
        FROM ownership_transfers t
        JOIN users u1 ON t.from_user_id = u1.user_id
        JOIN users u2 ON t.to_user_id = u2.user_id
        JOIN land_records l ON t.record_id = l.record_id
        LEFT JOIN users a ON t.reviewed_by = a.user_id
        $where_clause 
        ORDER BY 
            CASE WHEN t.status IN ('submitted', 'under_review') THEN 0 ELSE 1 END,
            DATEDIFF(NOW(), t.submitted_at) DESC,
            t.submitted_at DESC 
        LIMIT ?, ?";

$types .= 'ii';
$params[] = $pagination['offset'];
$params[] = $pagination['limit'];

$stmt = mysqli_prepare($conn, $sql);
if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$transfers = mysqli_stmt_get_result($stmt);

// Store transfer data for JavaScript
$transfer_data_array = [];
while ($transfer = mysqli_fetch_assoc($transfers)) {
    $transfer_data_array[$transfer['transfer_id']] = $transfer;
}
// Reset pointer for later use
mysqli_data_seek($transfers, 0);

// Get transfer statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status IN ('submitted', 'under_review') THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN DATEDIFF(NOW(), submitted_at) > 14 AND status IN ('submitted', 'under_review') THEN 1 ELSE 0 END) as overdue
    FROM ownership_transfers
";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Management - ArdhiYetu</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .tab-nav {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            flex-wrap: wrap;
        }
        
        .tab-nav a {
            padding: 10px 20px;
            text-decoration: none;
            color: var(--dark);
            border-radius: 5px 5px 0 0;
            border-bottom: 2px solid transparent;
            transition: var(--transition);
            white-space: nowrap;
        }
        
        .tab-nav a.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            background: rgba(46, 134, 171, 0.1);
        }
        
        .transfer-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .info-card {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }
        
        .info-card h4 {
            margin: 0 0 10px 0;
            color: var(--dark);
            font-size: 16px;
        }
        
        .info-card p {
            margin: 0;
            color: var(--gray);
        }
        
        .documents-list {
            margin: 20px 0;
        }
        
        .document-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        
        .document-item i {
            color: var(--primary);
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-header h2 {
            margin: 0;
            color: var(--dark);
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--gray);
        }
        
        .btn-secondary {
            background: var(--gray);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .priority-overdue {
            border-left-color: #e74c3c !important;
            background: rgba(231, 76, 60, 0.1) !important;
        }
        
        .priority-warning {
            border-left-color: #f1c40f !important;
            background: rgba(241, 196, 15, 0.1) !important;
        }
        
        .bulk-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            align-items: center;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        
        .bulk-actions select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
        }
        
        .bulk-actions button {
            padding: 8px 16px;
            background: #2e86ab;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .bulk-actions button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .transfer-row.selected {
            background: rgba(46, 134, 171, 0.1);
        }
        
        .days-indicator {
            font-size: 12px;
            padding: 2px 6px;
            border-radius: 3px;
            margin-left: 5px;
        }
        
        .days-indicator.overdue {
            background: #e74c3c;
            color: white;
        }
        
        .days-indicator.warning {
            background: #f1c40f;
            color: #333;
        }
        
        .days-indicator.normal {
            background: #2ecc71;
            color: white;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-submitted { background: #3498db; color: white; }
        .status-under_review { background: #f39c12; color: white; }
        .status-approved { background: #2ecc71; color: white; }
        .status-rejected { background: #e74c3c; color: white; }
        
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .text-danger {
            color: #e74c3c;
        }
        
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .status-indicator.active {
            background: #2ecc71;
        }
        
        .status-indicator.danger {
            background: #e74c3c;
        }
        
        .view-loading {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
        
        .view-loading i {
            font-size: 48px;
            margin-bottom: 20px;
            color: #2e86ab;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="admin-main">
            <header class="admin-header">
                <div class="header-left">
                    <h1>Transfer Requests</h1>
                    <p>Review and manage land ownership transfers</p>
                </div>
                <div class="header-right">
                    <div class="system-status">
                        <?php if ($stats['overdue'] > 0): ?>
                            <span class="status-indicator danger" title="<?php echo $stats['overdue']; ?> overdue reviews"></span>
                        <?php else: ?>
                            <span class="status-indicator active"></span>
                        <?php endif; ?>
                        <span><?php echo $stats['pending']; ?> Pending Reviews</span>
                        <?php if ($stats['overdue'] > 0): ?>
                            <span class="text-danger">(<?php echo $stats['overdue']; ?> overdue)</span>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <div class="admin-content">
                <?php if (isset($_SESSION['flash'])): ?>
                    <?php foreach ($_SESSION['flash'] as $type => $message): ?>
                        <div class="alert alert-<?php echo $type; ?>">
                            <?php echo $message; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php unset($_SESSION['flash']); ?>
                <?php endif; ?>

                <div class="stats-cards">
                    <div class="stat-mini">
                        <h4><?php echo $stats['total']; ?></h4>
                        <p>Total Transfers</p>
                    </div>
                    <div class="stat-mini">
                        <h4><?php echo $stats['submitted']; ?></h4>
                        <p>Submitted</p>
                    </div>
                    <div class="stat-mini">
                        <h4><?php echo $stats['under_review']; ?></h4>
                        <p>Under Review</p>
                    </div>
                    <div class="stat-mini">
                        <h4><?php echo $stats['approved']; ?></h4>
                        <p>Approved</p>
                    </div>
                    <div class="stat-mini">
                        <h4><?php echo $stats['rejected']; ?></h4>
                        <p>Rejected</p>
                    </div>
                    <?php if ($stats['overdue'] > 0): ?>
                        <div class="stat-mini" style="background: #f8d7da; border-left: 4px solid #e74c3c;">
                            <h4><?php echo $stats['overdue']; ?></h4>
                            <p>Overdue</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="tab-nav">
                    <a href="transfers.php" class="<?php echo !$status_filter ? 'active' : ''; ?>">All</a>
                    <a href="transfers.php?status=submitted" class="<?php echo $status_filter === 'submitted' ? 'active' : ''; ?>">
                        Submitted (<?php echo $stats['submitted']; ?>)
                    </a>
                    <a href="transfers.php?status=under_review" class="<?php echo $status_filter === 'under_review' ? 'active' : ''; ?>">
                        Under Review (<?php echo $stats['under_review']; ?>)
                    </a>
                    <a href="transfers.php?status=approved" class="<?php echo $status_filter === 'approved' ? 'active' : ''; ?>">
                        Approved (<?php echo $stats['approved']; ?>)
                    </a>
                    <a href="transfers.php?status=rejected" class="<?php echo $status_filter === 'rejected' ? 'active' : ''; ?>">
                        Rejected (<?php echo $stats['rejected']; ?>)
                    </a>
                </div>

                <!-- Bulk Actions -->
                <form id="bulkActionForm" method="POST" action="transfers.php?action=bulk_action">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <div class="bulk-actions">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                        <label for="selectAll" style="margin-right: 15px;">Select All</label>
                        
                        <select name="bulk_action" id="bulkActionSelect">
                            <option value="">Bulk Actions</option>
                            <option value="mark_review">Mark as Under Review</option>
                            <option value="delete">Delete Selected</option>
                        </select>
                        
                        <button type="submit" id="bulkActionButton" disabled onclick="return confirmBulkAction()">
                            Apply
                        </button>
                        
                        <span id="selectedCount" style="margin-left: auto; color: #7f8c8d;">
                            0 selected
                        </span>
                    </div>

                    <div class="table-card">
                        <div class="table-content">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th width="30"><input type="checkbox" id="toggleAll" onchange="toggleAllCheckboxes(this)"></th>
                                        <th>Reference No</th>
                                        <th>Parcel</th>
                                        <th>From</th>
                                        <th>To</th>
                                        <th>Submitted</th>
                                        <th>Status</th>
                                        <th>Reviewer</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($transfers) > 0): ?>
                                        <?php while ($transfer = mysqli_fetch_assoc($transfers)): ?>
                                            <tr id="transfer-<?php echo $transfer['transfer_id']; ?>" 
                                                class="<?php echo $transfer['review_priority'] !== 'normal' ? 'priority-' . $transfer['review_priority'] : ''; ?>">
                                                <td>
                                                    <input type="checkbox" name="transfer_ids[]" 
                                                           value="<?php echo $transfer['transfer_id']; ?>" 
                                                           class="transfer-checkbox"
                                                           onchange="updateSelection()">
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($transfer['reference_no']); ?>
                                                    <?php if ($transfer['review_priority'] !== 'normal' && in_array($transfer['status'], ['submitted', 'under_review'])): ?>
                                                        <span class="days-indicator <?php echo $transfer['review_priority']; ?>">
                                                            <?php echo $transfer['days_pending']; ?>d
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($transfer['parcel_no']); ?>
                                                    <br><small><?php echo htmlspecialchars($transfer['location']); ?></small>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($transfer['from_name']); ?>
                                                    <br><small><?php echo htmlspecialchars($transfer['from_email']); ?></small>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($transfer['to_name']); ?>
                                                    <br><small><?php echo htmlspecialchars($transfer['to_email']); ?></small>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($transfer['submitted_at'])); ?></td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $transfer['status']; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $transfer['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo $transfer['reviewer_name'] ? htmlspecialchars($transfer['reviewer_name']) : 'Not reviewed'; ?>
                                                    <?php if ($transfer['reviewed_at']): ?>
                                                        <br><small><?php echo date('M d, Y', strtotime($transfer['reviewed_at'])); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="action-dropdown">
                                                        <button class="btn-small">
                                                            <i class="fas fa-ellipsis-v"></i>
                                                        </button>
                                                        <div class="dropdown-content">
                                                            <a href="#" onclick="openViewModal(<?php echo $transfer['transfer_id']; ?>); return false;">
                                                                <i class="fas fa-eye"></i> View Details
                                                            </a>
                                                            <?php if ($transfer['status'] === 'submitted' || $transfer['status'] === 'under_review'): ?>
                                                                <a href="#" onclick="openReviewModal(<?php echo $transfer['transfer_id']; ?>); return false;">
                                                                    <i class="fas fa-check"></i> Review
                                                                </a>
                                                            <?php endif; ?>
                                                            <?php if ($transfer['status'] === 'submitted' || $transfer['status'] === 'under_review'): ?>
                                                                <a href="transfers.php?action=delete&id=<?php echo $transfer['transfer_id']; ?>" 
                                                                   onclick="return confirmDelete(<?php echo $transfer['transfer_id']; ?>)"
                                                                   style="color: #e74c3c;">
                                                                    <i class="fas fa-trash"></i> Delete
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center">
                                                <div class="empty-state">
                                                    <i class="fas fa-exchange-alt"></i>
                                                    <p>No transfer requests found</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>

                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?><?php echo $status_filter ? '&status=' . $status_filter : ''; ?>" 
                               class="page-link">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                            <a href="?page=<?php echo $i; ?><?php echo $status_filter ? '&status=' . $status_filter : ''; ?>" 
                               class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $pagination['total_pages']): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo $status_filter ? '&status=' . $status_filter : ''; ?>" 
                               class="page-link">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- View Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Transfer Request Details</h2>
                <button class="close-modal" onclick="closeViewModal()">&times;</button>
            </div>
            <div id="transferDetails">
                <div class="view-loading">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading transfer details...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Review Modal -->
    <div id="reviewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Review Transfer Request</h2>
                <button class="close-modal" onclick="closeReviewModal()">&times;</button>
            </div>
            <form id="reviewForm" method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="transfer_id" id="review_transfer_id">
                
                <div id="reviewTransferDetails">
                    <div class="view-loading">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Loading transfer details...</p>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="status">Decision *</label>
                    <select id="status" name="status" class="form-control" required onchange="toggleNotesRequirement()">
                        <option value="">Select Decision</option>
                        <option value="under_review">Mark as Under Review</option>
                        <option value="approved">Approve Transfer</option>
                        <option value="rejected">Reject Transfer</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="review_notes">Review Notes <span id="notesRequired" style="color: #e74c3c; display: none;">*</span></label>
                    <textarea id="review_notes" name="review_notes" class="form-control" 
                              rows="4" placeholder="Add notes about your review decision..."></textarea>
                    <small id="rejectionNote" style="color: #e74c3c; display: none;">
                        Notes are required when rejecting a transfer request.
                    </small>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeReviewModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" onclick="return confirmReviewAction()">Submit Review</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Store transfer data from PHP to JavaScript
        const transferData = <?php echo json_encode($transfer_data_array); ?>;
        
        // Debug log
        console.log('Transfer data loaded:', transferData);
        
        // Bulk selection functions
        function toggleAllCheckboxes(source) {
            const checkboxes = document.querySelectorAll('.transfer-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = source.checked;
            });
            updateSelection();
        }
        
        function toggleSelectAll(source) {
            const checkboxes = document.querySelectorAll('.transfer-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = source.checked;
            });
            updateSelection();
        }
        
        function updateSelection() {
            const checkboxes = document.querySelectorAll('.transfer-checkbox');
            const selected = Array.from(checkboxes).filter(cb => cb.checked);
            const selectedCount = selected.length;
            
            document.getElementById('selectedCount').textContent = selectedCount + ' selected';
            document.getElementById('bulkActionButton').disabled = selectedCount === 0;
            
            // Update row styling
            checkboxes.forEach(checkbox => {
                const row = checkbox.closest('tr');
                if (checkbox.checked) {
                    row.classList.add('selected');
                } else {
                    row.classList.remove('selected');
                }
            });
        }
        
        function confirmBulkAction() {
            const action = document.getElementById('bulkActionSelect').value;
            const selectedCount = document.querySelectorAll('.transfer-checkbox:checked').length;
            
            if (!action) {
                alert('Please select a bulk action.');
                return false;
            }
            
            if (selectedCount === 0) {
                alert('No transfers selected.');
                return false;
            }
            
            let message = '';
            switch (action) {
                case 'mark_review':
                    message = `Mark ${selectedCount} transfer(s) as Under Review?`;
                    break;
                case 'delete':
                    message = `DELETE ${selectedCount} transfer(s)?\n\nThis action cannot be undone!`;
                    break;
            }
            
            return confirm(message);
        }
        
        // Modal functions
        function openViewModal(transferId) {
            console.log('Opening view modal for transfer ID:', transferId);
            
            const transfer = transferData[transferId];
            
            if (!transfer) {
                alert('Transfer request not found. Transfer ID: ' + transferId);
                return false;
            }
            
            const statusText = transfer.status.charAt(0).toUpperCase() + transfer.status.slice(1).replace('_', ' ');
            const statusClass = `status-${transfer.status}`;
            
            let html = `
                <div class="transfer-info">
                    <div class="info-card">
                        <h4>Reference Number</h4>
                        <p>${escapeHtml(transfer.reference_no)}</p>
                    </div>
                    <div class="info-card">
                        <h4>Status</h4>
                        <p><span class="status-badge ${statusClass}">${statusText}</span></p>
                    </div>
                    <div class="info-card">
                        <h4>Submitted</h4>
                        <p>${formatDate(transfer.submitted_at)}</p>
                    </div>
                </div>
                
                <div class="transfer-info">
                    <div class="info-card">
                        <h4>Parcel Details</h4>
                        <p><strong>${escapeHtml(transfer.parcel_no)}</strong><br>
                        ${escapeHtml(transfer.location)}</p>
                    </div>
                    <div class="info-card">
                        <h4>Current Owner</h4>
                        <p>${escapeHtml(transfer.from_name)}<br>
                        <small>${escapeHtml(transfer.from_email)}</small></p>
                    </div>
                    <div class="info-card">
                        <h4>New Owner</h4>
                        <p>${escapeHtml(transfer.to_name)}<br>
                        <small>${escapeHtml(transfer.to_email)}</small></p>
                    </div>
                </div>
                
                <div class="info-card">
                    <h4>Transfer Information</h4>
                    <p><strong>Transfer ID:</strong> ${transfer.transfer_id}<br>
                    <strong>Record ID:</strong> ${transfer.record_id}<br>
                    <strong>Days Pending:</strong> ${transfer.days_pending} days</p>
                </div>
            `;
            
            if (transfer.reason && transfer.reason.trim() !== '') {
                html += `
                    <div class="info-card">
                        <h4>Transfer Reason</h4>
                        <p>${escapeHtml(transfer.reason)}</p>
                    </div>
                `;
            }
            
            if (transfer.review_notes && transfer.review_notes.trim() !== '') {
                html += `
                    <div class="info-card">
                        <h4>Review Notes</h4>
                        <p>${escapeHtml(transfer.review_notes)}</p>
                        <small>Reviewed by: ${escapeHtml(transfer.reviewer_name || 'System')} 
                        on ${transfer.reviewed_at ? formatDate(transfer.reviewed_at) : 'N/A'}</small>
                    </div>
                `;
            }
            
            document.getElementById('transferDetails').innerHTML = html;
            document.getElementById('viewModal').style.display = 'block';
            return false;
        }
        
        function openReviewModal(transferId) {
            console.log('Opening review modal for transfer ID:', transferId);
            
            const transfer = transferData[transferId];
            
            if (!transfer) {
                alert('Transfer request not found. Transfer ID: ' + transferId);
                return false;
            }
            
            document.getElementById('review_transfer_id').value = transferId;
            document.getElementById('reviewForm').action = `transfers.php?action=review&id=${transferId}`;
            
            let html = `
                <div class="info-card">
                    <h4>Transfer Details</h4>
                    <p><strong>${escapeHtml(transfer.reference_no)}</strong><br>
                    Parcel: ${escapeHtml(transfer.parcel_no)}<br>
                    Location: ${escapeHtml(transfer.location)}<br>
                    From: ${escapeHtml(transfer.from_name)} → To: ${escapeHtml(transfer.to_name)}</p>
                </div>
            `;
            
            if (transfer.reason && transfer.reason.trim() !== '') {
                html += `
                    <div class="info-card">
                        <h4>Reason for Transfer</h4>
                        <p>${escapeHtml(transfer.reason)}</p>
                    </div>
                `;
            }
            
            if (transfer.days_pending > 7) {
                const priorityClass = transfer.review_priority === 'overdue' ? 'priority-overdue' : 'priority-warning';
                html += `
                    <div class="info-card ${priorityClass}">
                        <h4><i class="fas fa-exclamation-triangle"></i> Review Priority</h4>
                        <p>This transfer has been pending for <strong>${transfer.days_pending} days</strong>.<br>
                        Priority: <strong>${transfer.review_priority.toUpperCase()}</strong></p>
                    </div>
                `;
            }
            
            document.getElementById('reviewTransferDetails').innerHTML = html;
            document.getElementById('reviewModal').style.display = 'block';
            
            // Reset form
            document.getElementById('status').value = '';
            document.getElementById('review_notes').value = '';
            toggleNotesRequirement();
            return false;
        }
        
        function toggleNotesRequirement() {
            const status = document.getElementById('status').value;
            const notesRequired = document.getElementById('notesRequired');
            const rejectionNote = document.getElementById('rejectionNote');
            
            if (status === 'rejected') {
                notesRequired.style.display = 'inline';
                rejectionNote.style.display = 'block';
            } else {
                notesRequired.style.display = 'none';
                rejectionNote.style.display = 'none';
            }
        }
        
        function confirmReviewAction() {
            const status = document.getElementById('status').value;
            const notes = document.getElementById('review_notes').value.trim();
            const transferId = document.getElementById('review_transfer_id').value;
            const transfer = transferData[transferId];
            
            if (!transfer) {
                alert('Transfer request not found.');
                return false;
            }
            
            if (!status) {
                alert('Please select a decision.');
                return false;
            }
            
            if (status === 'rejected' && !notes) {
                alert('Review notes are required when rejecting a transfer.');
                return false;
            }
            
            let message = '';
            switch (status) {
                case 'approved':
                    message = `APPROVE transfer request #${transferId} (${transfer.reference_no})?\n\nThis will transfer land ownership from ${transfer.from_name} to ${transfer.to_name} and cannot be easily undone.`;
                    break;
                case 'rejected':
                    message = `REJECT transfer request #${transferId} (${transfer.reference_no})?\n\nThe transfer will be cancelled and both parties will be notified.`;
                    break;
                case 'under_review':
                    message = `Mark transfer request #${transferId} (${transfer.reference_no}) as Under Review?`;
                    break;
            }
            
            return confirm(message);
        }
        
        function confirmDelete(transferId) {
            const transfer = transferData[transferId];
            if (!transfer) {
                alert('Transfer not found.');
                return false;
            }
            return confirm(`DELETE transfer request #${transferId} (${transfer.reference_no})?\n\nThis action cannot be undone!`);
        }
        
        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
        }
        
        function closeReviewModal() {
            document.getElementById('reviewModal').style.display = 'none';
        }
        
        // Utility function to escape HTML
        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Utility function to format date
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                closeViewModal();
                closeReviewModal();
            }
        };
        
        // Initialize selection count
        updateSelection();
        
        // Debug: Override any old function calls that might still exist
        console.log('Transfer management system initialized');
        
        // Prevent any default form submissions that might be causing issues
        document.addEventListener('submit', function(e) {
            const form = e.target;
            if (form.id === 'reviewForm') {
                // Allow review form to submit normally
                return true;
            }
            if (form.id === 'bulkActionForm') {
                // Allow bulk action form to submit normally
                return true;
            }
            // Prevent any other unexpected form submissions
            e.preventDefault();
            return false;
        });
    </script>
</body>
</html>