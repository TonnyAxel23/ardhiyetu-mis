<?php
require_once '../../includes/init.php';
require_admin();

$transfer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Include EmailSender for notifications
require_once __DIR__ . '/../../includes/EmailSender.php';
$emailSender = new EmailSender([
    'smtp_username' => 'your-email@gmail.com',
    'smtp_password' => 'your-app-password',
    'debug' => true
]);

// Get partial transfer details
$sql = "SELECT t.*, 
               l_original.parcel_no as original_parcel,
               l_original.size as original_size,
               l_original.location as original_location,
               l_new.parcel_no as new_parcel,
               l_new.size as new_size,
               l_new.status as new_land_status,
               u_from.name as from_name,
               u_from.email as from_email,
               u_to.name as to_name,
               u_to.email as to_email,
               admin.name as reviewer_name
        FROM ownership_transfers t
        LEFT JOIN land_records l_original ON t.original_parcel_no = l_original.parcel_no
        LEFT JOIN land_records l_new ON t.new_parcel_no = l_new.parcel_no
        JOIN users u_from ON t.from_user_id = u_from.user_id
        JOIN users u_to ON t.to_user_id = u_to.user_id
        LEFT JOIN users admin ON t.reviewed_by = admin.user_id
        WHERE t.transfer_id = ? AND t.is_partial_transfer = 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $transfer_id);
mysqli_stmt_execute($stmt);
$transfer = mysqli_fetch_assoc($stmt);

if (!$transfer) {
    flash_message('error', 'Partial transfer not found.');
    redirect('transfers.php');
}

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $review_notes = mysqli_real_escape_string($conn, $_POST['review_notes']);
    $admin_id = $_SESSION['user_id'];
    
    mysqli_begin_transaction($conn);
    
    try {
        if ($action === 'approve') {
            // Update transfer status
            $update_sql = "UPDATE ownership_transfers 
                          SET status = 'approved', 
                              reviewed_by = ?, 
                              reviewed_at = NOW(),
                              review_notes = ?
                          WHERE transfer_id = ?";
            $stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($stmt, "isi", $admin_id, $review_notes, $transfer_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to update transfer status');
            }
            
            // Update new land status to active
            $update_land_sql = "UPDATE land_records 
                               SET status = 'active',
                                   updated_at = NOW()
                               WHERE parcel_no = ?";
            $stmt = mysqli_prepare($conn, $update_land_sql);
            mysqli_stmt_bind_param($stmt, "s", $transfer['new_parcel']);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to activate new land record');
            }
            
            // Send email notifications
            try {
                $emailData = [
                    'site_name' => 'ArdhiYetu Land Management System',
                    'user_name' => $transfer['from_name'],
                    'transfer_id' => $transfer_id,
                    'parcel_no' => $transfer['original_parcel'],
                    'new_parcel_no' => $transfer['new_parcel'],
                    'transfer_size' => $transfer['transferred_size'],
                    'remaining_size' => $transfer['remaining_size'],
                    'location' => $transfer['original_location'],
                    'reviewer_name' => $_SESSION['name'],
                    'review_notes' => $review_notes,
                    'decision_date' => date('F j, Y'),
                    'admin_email' => 'admin@ardhiyetu.com'
                ];
                
                // Email to original owner
                $emailSender->sendEmail(
                    $transfer['from_email'],
                    $transfer['from_name'],
                    'partial_transfer_approved_owner',
                    $emailData
                );
                
                // Email to new owner
                $emailData['user_name'] = $transfer['to_name'];
                $emailSender->sendEmail(
                    $transfer['to_email'],
                    $transfer['to_name'],
                    'partial_transfer_approved_recipient',
                    $emailData
                );
                
            } catch (Exception $e) {
                error_log("Email sending failed: " . $e->getMessage());
                // Continue even if email fails
            }
            
            // Create notifications
            $notification_message = "Your partial transfer of {$transfer['transferred_size']} acres from {$transfer['original_parcel']} has been approved. New parcel: {$transfer['new_parcel']}";
            $notification_sql = "INSERT INTO notifications (user_id, title, message, type, related_entity_type, related_entity_id) 
                                VALUES ('{$transfer['from_user_id']}', 'Partial Transfer Approved', 
                                        '$notification_message', 
                                        'success', 'transfer', '$transfer_id')";
            mysqli_query($conn, $notification_sql);
            
            $notification_message = "Partial transfer of {$transfer['transferred_size']} acres from {$transfer['original_parcel']} to you has been approved. Your new parcel: {$transfer['new_parcel']}";
            $notification_sql = "INSERT INTO notifications (user_id, title, message, type, related_entity_type, related_entity_id) 
                                VALUES ('{$transfer['to_user_id']}', 'Partial Transfer Approved', 
                                        '$notification_message', 
                                        'success', 'transfer', '$transfer_id')";
            mysqli_query($conn, $notification_sql);
            
            // WebSocket notification
            if (function_exists('sendWebSocketNotification')) {
                sendWebSocketNotification([
                    'type' => 'partial_transfer_approved',
                    'transfer_id' => $transfer_id,
                    'from_user_id' => $transfer['from_user_id'],
                    'to_user_id' => $transfer['to_user_id'],
                    'original_parcel' => $transfer['original_parcel'],
                    'new_parcel' => $transfer['new_parcel'],
                    'transfer_size' => $transfer['transferred_size'],
                    'message' => "Partial transfer approved: {$transfer['transferred_size']} acres from {$transfer['original_parcel']}"
                ]);
            }
            
            mysqli_commit($conn);
            flash_message('success', 'Partial transfer approved successfully. Email notifications sent to both parties.');
            
        } elseif ($action === 'reject') {
            // Update transfer status
            $update_sql = "UPDATE ownership_transfers 
                          SET status = 'rejected', 
                              reviewed_by = ?, 
                              reviewed_at = NOW(),
                              review_notes = ?
                          WHERE transfer_id = ?";
            $stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($stmt, "isi", $admin_id, $review_notes, $transfer_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to update transfer status');
            }
            
            // Restore original land size and delete new land record
            if ($transfer['original_parcel']) {
                // Get current size of original land
                $get_size_sql = "SELECT size FROM land_records WHERE parcel_no = ?";
                $stmt = mysqli_prepare($conn, $get_size_sql);
                mysqli_stmt_bind_param($stmt, "s", $transfer['original_parcel']);
                mysqli_stmt_execute($stmt);
                $current_size = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['size'];
                
                // Restore original size
                $restored_size = $current_size + $transfer['transferred_size'];
                $restore_sql = "UPDATE land_records 
                               SET size = ?, 
                                   updated_at = NOW()
                               WHERE parcel_no = ?";
                $stmt = mysqli_prepare($conn, $restore_sql);
                mysqli_stmt_bind_param($stmt, "ds", $restored_size, $transfer['original_parcel']);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception('Failed to restore original land size');
                }
                
                // Delete new land record
                $delete_sql = "DELETE FROM land_records WHERE parcel_no = ?";
                $stmt = mysqli_prepare($conn, $delete_sql);
                mysqli_stmt_bind_param($stmt, "s", $transfer['new_parcel']);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception('Failed to delete new land record');
                }
            }
            
            // Send rejection emails
            try {
                $emailData = [
                    'site_name' => 'ArdhiYetu Land Management System',
                    'user_name' => $transfer['from_name'],
                    'transfer_id' => $transfer_id,
                    'parcel_no' => $transfer['original_parcel'],
                    'transfer_size' => $transfer['transferred_size'],
                    'reviewer_name' => $_SESSION['name'],
                    'review_notes' => $review_notes,
                    'decision_date' => date('F j, Y'),
                    'admin_email' => 'admin@ardhiyetu.com'
                ];
                
                // Email to original owner
                $emailSender->sendEmail(
                    $transfer['from_email'],
                    $transfer['from_name'],
                    'partial_transfer_rejected',
                    $emailData
                );
                
                // Email to recipient
                $emailData['user_name'] = $transfer['to_name'];
                $emailSender->sendEmail(
                    $transfer['to_email'],
                    $transfer['to_name'],
                    'partial_transfer_rejected',
                    $emailData
                );
                
            } catch (Exception $e) {
                error_log("Email sending failed: " . $e->getMessage());
            }
            
            // Create notifications
            $notification_message = "Your partial transfer of {$transfer['transferred_size']} acres from {$transfer['original_parcel']} has been rejected. Reason: $review_notes";
            $notification_sql = "INSERT INTO notifications (user_id, title, message, type, related_entity_type, related_entity_id) 
                                VALUES ('{$transfer['from_user_id']}', 'Partial Transfer Rejected', 
                                        '$notification_message', 
                                        'error', 'transfer', '$transfer_id')";
            mysqli_query($conn, $notification_sql);
            
            $notification_message = "Partial transfer of {$transfer['transferred_size']} acres from {$transfer['original_parcel']} to you has been rejected.";
            $notification_sql = "INSERT INTO notifications (user_id, title, message, type, related_entity_type, related_entity_id) 
                                VALUES ('{$transfer['to_user_id']}', 'Partial Transfer Rejected', 
                                        '$notification_message', 
                                        'error', 'transfer', '$transfer_id')";
            mysqli_query($conn, $notification_sql);
            
            mysqli_commit($conn);
            flash_message('success', 'Partial transfer rejected. Original land restored and notifications sent.');
            
        } elseif ($action === 'request_changes') {
            // Request changes/amendments
            $update_sql = "UPDATE ownership_transfers 
                          SET status = 'changes_requested', 
                              reviewed_by = ?, 
                              reviewed_at = NOW(),
                              review_notes = ?
                          WHERE transfer_id = ?";
            $stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($stmt, "isi", $admin_id, $review_notes, $transfer_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to update transfer status');
            }
            
            // Send email requesting changes
            try {
                $emailData = [
                    'site_name' => 'ArdhiYetu Land Management System',
                    'user_name' => $transfer['from_name'],
                    'transfer_id' => $transfer_id,
                    'parcel_no' => $transfer['original_parcel'],
                    'reviewer_name' => $_SESSION['name'],
                    'review_notes' => $review_notes,
                    'admin_email' => 'admin@ardhiyetu.com'
                ];
                
                $emailSender->sendEmail(
                    $transfer['from_email'],
                    $transfer['from_name'],
                    'partial_transfer_changes',
                    $emailData
                );
                
            } catch (Exception $e) {
                error_log("Email sending failed: " . $e->getMessage());
            }
            
            mysqli_commit($conn);
            flash_message('info', 'Changes requested. The transfer initiator has been notified.');
        }
        
        redirect('transfers.php');
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        flash_message('error', 'Error processing transfer: ' . $e->getMessage());
        redirect("review-partial-transfer.php?id=$transfer_id");
    }
}

// Get split history
$split_history_sql = "SELECT l.parcel_no, l.size, l.registered_at, u.name as owner_name
                     FROM land_records l
                     JOIN users u ON l.owner_id = u.user_id
                     WHERE l.parent_record_id = (SELECT record_id FROM land_records WHERE parcel_no = ?)
                     OR l.parcel_no = ?
                     ORDER BY l.registered_at DESC";
$stmt = mysqli_prepare($conn, $split_history_sql);
mysqli_stmt_bind_param($stmt, "ss", $transfer['original_parcel'], $transfer['original_parcel']);
mysqli_stmt_execute($stmt);
$split_history = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Partial Transfer - ArdhiYetu</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .partial-transfer-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .transfer-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .transfer-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .transfer-card h3 {
            color: var(--primary);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        
        .detail-label {
            font-weight: 600;
            color: #555;
        }
        
        .detail-value {
            color: #333;
        }
        
        .size-comparison {
            display: flex;
            justify-content: space-around;
            align-items: center;
            margin: 30px 0;
            padding: 30px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            position: relative;
        }
        
        .size-arrow {
            font-size: 24px;
            color: #007bff;
        }
        
        .size-item {
            text-align: center;
            flex: 1;
        }
        
        .size-value {
            font-size: 32px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }
        
        .size-label {
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .split-history {
            margin-top: 40px;
        }
        
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .history-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }
        
        .history-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .history-table tr:hover {
            background: #f8f9fa;
        }
        
        .decision-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .status-submitted {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-changes_requested {
            background: #fff3cd;
            color: #856404;
        }
        
        .visual-split {
            display: flex;
            height: 100px;
            margin: 20px 0;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .original-portion {
            background: #e3f2fd;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1976d2;
            font-weight: bold;
        }
        
        .transferred-portion {
            background: #c8e6c9;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #2e7d32;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .transfer-overview {
                grid-template-columns: 1fr;
            }
            
            .size-comparison {
                flex-direction: column;
                gap: 20px;
            }
            
            .decision-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="admin-main">
            <header class="admin-header">
                <div class="header-left">
                    <h1>Review Partial Land Transfer</h1>
                    <p>Approve, reject, or request changes for partial land transfer</p>
                </div>
                <div class="header-right">
                    <a href="transfers.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Transfers
                    </a>
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

                <div class="partial-transfer-container">
                    <div class="transfer-overview">
                        <div class="transfer-card">
                            <h3><i class="fas fa-info-circle"></i> Transfer Information</h3>
                            <div class="detail-row">
                                <span class="detail-label">Transfer ID:</span>
                                <span class="detail-value"><?php echo $transfer['transfer_id']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Status:</span>
                                <span class="status-badge status-<?php echo $transfer['status']; ?>">
                                    <?php echo str_replace('_', ' ', $transfer['status']); ?>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Submitted:</span>
                                <span class="detail-value"><?php echo format_date($transfer['submitted_at']); ?></span>
                            </div>
                            <?php if ($transfer['reviewed_at']): ?>
                            <div class="detail-row">
                                <span class="detail-label">Reviewed:</span>
                                <span class="detail-value"><?php echo format_date($transfer['reviewed_at']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Reviewed By:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($transfer['reviewer_name']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="transfer-card">
                            <h3><i class="fas fa-user"></i> Parties Involved</h3>
                            <div class="detail-row">
                                <span class="detail-label">From (Current Owner):</span>
                                <span class="detail-value"><?php echo htmlspecialchars($transfer['from_name']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Email:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($transfer['from_email']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">To (New Owner):</span>
                                <span class="detail-value"><?php echo htmlspecialchars($transfer['to_name']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Email:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($transfer['to_email']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="transfer-card">
                        <h3><i class="fas fa-landmark"></i> Land Split Details</h3>
                        
                        <div class="size-comparison">
                            <div class="size-item">
                                <div class="size-value"><?php echo number_format($transfer['original_size'], 2); ?></div>
                                <div class="size-label">Original Size</div>
                                <small><?php echo htmlspecialchars($transfer['original_parcel']); ?></small>
                            </div>
                            
                            <div class="size-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                            
                            <div class="size-item">
                                <div class="size-value"><?php echo number_format($transfer['remaining_size'], 2); ?></div>
                                <div class="size-label">Remaining</div>
                                <small>To be kept by <?php echo htmlspecialchars($transfer['from_name']); ?></small>
                            </div>
                            
                            <div class="size-arrow">
                                <i class="fas fa-plus"></i>
                            </div>
                            
                            <div class="size-item">
                                <div class="size-value"><?php echo number_format($transfer['transferred_size'], 2); ?></div>
                                <div class="size-label">Transferred</div>
                                <small>New parcel: <?php echo htmlspecialchars($transfer['new_parcel']); ?></small>
                            </div>
                        </div>
                        
                        <div class="visual-split">
                            <div class="original-portion" style="width: <?php echo ($transfer['remaining_size'] / $transfer['original_size'] * 100); ?>%">
                                <?php echo number_format($transfer['remaining_size'], 2); ?> acres<br>
                                <small>Remaining</small>
                            </div>
                            <div class="transferred-portion" style="width: <?php echo ($transfer['transferred_size'] / $transfer['original_size'] * 100); ?>%">
                                <?php echo number_format($transfer['transferred_size'], 2); ?> acres<br>
                                <small>Transferred</small>
                            </div>
                        </div>
                        
                        <div style="text-align: center; margin-top: 15px;">
                            <strong>Location:</strong> <?php echo htmlspecialchars($transfer['original_location']); ?>
                        </div>
                    </div>
                    
                    <?php if (mysqli_num_rows($split_history) > 0): ?>
                    <div class="transfer-card split-history">
                        <h3><i class="fas fa-history"></i> Split History</h3>
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Parcel No</th>
                                    <th>Size (acres)</th>
                                    <th>Owner</th>
                                    <th>Registered</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($history = mysqli_fetch_assoc($split_history)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($history['parcel_no']); ?></td>
                                    <td><?php echo number_format($history['size'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($history['owner_name']); ?></td>
                                    <td><?php echo format_date($history['registered_at']); ?></td>
                                    <td>
                                        <?php if ($history['parcel_no'] == $transfer['new_parcel']): ?>
                                            <span class="status-badge status-<?php echo $transfer['new_land_status']; ?>">
                                                <?php echo $transfer['new_land_status']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-active">Active</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($transfer['status'] == 'submitted' || $transfer['status'] == 'changes_requested'): ?>
                    <div class="transfer-card">
                        <h3><i class="fas fa-clipboard-check"></i> Review Decision</h3>
                        
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="review_notes">Review Notes *</label>
                                <textarea id="review_notes" name="review_notes" rows="6" 
                                          class="form-control" 
                                          placeholder="Enter your review comments here..." 
                                          required><?php echo htmlspecialchars($transfer['review_notes'] ?? ''); ?></textarea>
                                <small>These notes will be included in email notifications to both parties.</small>
                            </div>
                            
                            <div class="decision-actions">
                                <button type="submit" name="action" value="approve" 
                                        class="btn btn-success" onclick="return confirmApprove()">
                                    <i class="fas fa-check-circle"></i> Approve Partial Transfer
                                </button>
                                
                                <button type="submit" name="action" value="request_changes" 
                                        class="btn btn-warning" onclick="return confirmRequestChanges()">
                                    <i class="fas fa-edit"></i> Request Changes
                                </button>
                                
                                <button type="submit" name="action" value="reject" 
                                        class="btn btn-danger" onclick="return confirmReject()">
                                    <i class="fas fa-times-circle"></i> Reject Transfer
                                </button>
                                
                                <a href="transfers.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                            
                            <div class="email-notice" style="margin-top: 20px; padding: 10px; background: #d4edda; border-radius: 5px;">
                                <i class="fas fa-envelope"></i>
                                <strong>Email Notifications:</strong> Both parties will receive email notifications with your decision and review notes.
                            </div>
                        </form>
                    </div>
                    <?php elseif ($transfer['review_notes']): ?>
                    <div class="transfer-card">
                        <h3><i class="fas fa-file-alt"></i> Review Notes</h3>
                        <div style="padding: 15px; background: #f8f9fa; border-radius: 5px;">
                            <?php echo nl2br(htmlspecialchars($transfer['review_notes'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        function confirmApprove() {
            const notes = document.getElementById('review_notes').value.trim();
            if (!notes) {
                alert('Please provide review notes before approving.');
                return false;
            }
            return confirm('Are you sure you want to approve this partial land transfer?\n\n• A new land parcel will be created\n• Ownership will be transferred\n• Both parties will be notified via email');
        }
        
        function confirmRequestChanges() {
            const notes = document.getElementById('review_notes').value.trim();
            if (!notes) {
                alert('Please specify what changes are required.');
                return false;
            }
            return confirm('Request changes for this partial transfer? The transfer initiator will be notified to make amendments.');
        }
        
        function confirmReject() {
            const notes = document.getElementById('review_notes').value.trim();
            if (!notes) {
                alert('Please provide reasons for rejection.');
                return false;
            }
            return confirm('Are you sure you want to reject this partial transfer?\n\n• The original land will be restored to its full size\n• The new land record will be deleted\n• Both parties will be notified via email');
        }
    </script>
</body>
</html>