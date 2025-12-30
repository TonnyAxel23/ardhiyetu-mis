<?php
// ardhiyetu/public/admin/transfers.php
require_once '../../includes/init.php';
require_admin();

// Include the EmailSender class
require_once __DIR__ . '/../../includes/EmailSender.php';

// Initialize EmailSender
$emailSender = new EmailSender([
    'smtp_username' => 'your-email@gmail.com',  // CHANGE THIS
    'smtp_password' => 'your-app-password',     // CHANGE THIS
    'debug' => true  // Set to false in production
]);

// Check if email function exists
$has_email_function = true; // Now we have EmailSender

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Generate CSRF token if needed
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Helper function to send emails using EmailSender
function send_transfer_email($to_email, $to_name, $subject, $template, $data = []) {
    global $emailSender;
    
    // Check if EmailSender is available
    if (!isset($emailSender) || !$emailSender) {
        error_log("EmailSender not available. Would send to: $to_email, Subject: $subject");
        return false;
    }
    
    try {
        // Add necessary data for templates
        $data['subject'] = $subject;
        $data['admin_email'] = ADMIN_EMAIL ?? 'admin@ardhiyetu.com';
        $data['site_name'] = SITE_NAME ?? 'ArdhiYetu Land Management System';
        
        // Map template names to EmailSender methods
        $method_map = [
            'transfer_approved_previous' => 'sendTransferApprovedPrevious',
            'transfer_approved_new' => 'sendTransferApprovedNew',
            'transfer_submission' => 'sendTransferSubmission',
            'transfer_under_review' => 'sendTransferUnderReview',
            'transfer_rejected' => 'sendTransferRejected',
            'transfer_submission_admin' => 'sendTransferSubmissionAdmin'
        ];
        
        if (isset($method_map[$template])) {
            $method = $method_map[$template];
            
            // Prepare data for EmailSender
            $emailData = array_merge($data, [
                'user_email' => $to_email,
                'user_name' => $to_name
            ]);
            
            return $emailSender->$method($emailData);
        } else {
            // Use generic sendEmail method
            return $emailSender->sendEmail($to_email, $to_name, $template, $data);
        }
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

// Helper function to send transfer submission notification
function send_transfer_submission_email($transfer_id, $from_user_id, $to_user_id) {
    global $conn, $emailSender;
    
    // Get transfer details
    $sql = "SELECT t.*, 
                   u1.name as from_name, u1.email as from_email,
                   u2.name as to_name, u2.email as to_email,
                   l.parcel_no, l.location
            FROM ownership_transfers t
            JOIN users u1 ON t.from_user_id = u1.user_id
            JOIN users u2 ON t.to_user_id = u2.user_id
            JOIN land_records l ON t.record_id = l.record_id
            WHERE t.transfer_id = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $transfer_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $transfer = mysqli_fetch_assoc($result);
    
    if (!$transfer) return false;
    
    $site_name = "ArdhiYetu Land Management System";
    $admin_email = "admin@ardhiyetu.com";
    
    // Email to current owner (transfer initiator)
    $data = [
        'site_name' => $site_name,
        'user_name' => $transfer['from_name'],
        'transfer_id' => $transfer['transfer_id'],
        'reference_no' => $transfer['reference_no'],
        'parcel_no' => $transfer['parcel_no'],
        'location' => $transfer['location'],
        'to_user_name' => $transfer['to_name'],
        'submission_date' => date('F j, Y', strtotime($transfer['submitted_at'])),
        'status' => 'Submitted',
        'admin_email' => $admin_email,
        'message' => "Your land transfer request has been submitted successfully and is awaiting review."
    ];
    
    send_transfer_email($transfer['from_email'], $transfer['from_name'], 
                       "Land Transfer Request Submitted - #" . $transfer['reference_no'], 
                       'transfer_submission', $data);
    
    // Email to new owner (recipient)
    $data['user_name'] = $transfer['to_name'];
    $data['message'] = "You have been nominated as the new owner of a land parcel. The transfer request is awaiting administrative review.";
    
    send_transfer_email($transfer['to_email'], $transfer['to_name'], 
                       "Land Transfer Request Received - #" . $transfer['reference_no'], 
                       'transfer_submission', $data);
    
    // Email to admin (if different from system admin)
    $admin_sql = "SELECT email, name FROM users WHERE role = 'admin' AND user_id != ? LIMIT 1";
    $admin_stmt = mysqli_prepare($conn, $admin_sql);
    mysqli_stmt_bind_param($admin_stmt, 'i', $_SESSION['user_id']);
    mysqli_stmt_execute($admin_stmt);
    $admin_result = mysqli_stmt_get_result($admin_stmt);
    
    if ($admin = mysqli_fetch_assoc($admin_result)) {
        $data['user_name'] = $admin['name'];
        $data['message'] = "A new land transfer request has been submitted and requires your review.";
        $data['from_user_name'] = $transfer['from_name'];
        
        send_transfer_email($admin['email'], $admin['name'], 
                           "New Land Transfer Request - #" . $transfer['reference_no'], 
                           'transfer_submission_admin', $data);
    }
    
    return true;
}

// Helper function to send transfer decision notification
function send_transfer_decision_email($transfer_id, $status, $review_notes = '') {
    global $conn, $emailSender;
    
    // Get transfer details
    $sql = "SELECT t.*, 
                   u1.name as from_name, u1.email as from_email,
                   u2.name as to_name, u2.email as to_email,
                   l.parcel_no, l.location,
                   a.name as reviewer_name
            FROM ownership_transfers t
            JOIN users u1 ON t.from_user_id = u1.user_id
            JOIN users u2 ON t.to_user_id = u2.user_id
            JOIN land_records l ON t.record_id = l.record_id
            LEFT JOIN users a ON t.reviewed_by = a.user_id
            WHERE t.transfer_id = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $transfer_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $transfer = mysqli_fetch_assoc($result);
    
    if (!$transfer) return false;
    
    $site_name = "ArdhiYetu Land Management System";
    $admin_email = "admin@ardhiyetu.com";
    
    $status_text = ucfirst(str_replace('_', ' ', $status));
    $decision_date = date('F j, Y');
    $reviewer_name = $transfer['reviewer_name'] ?: 'Administrator';
    
    // Common data for all emails
    $common_data = [
        'site_name' => $site_name,
        'transfer_id' => $transfer['transfer_id'],
        'reference_no' => $transfer['reference_no'],
        'parcel_no' => $transfer['parcel_no'],
        'location' => $transfer['location'],
        'status' => $status_text,
        'review_notes' => $review_notes,
        'reviewer_name' => $reviewer_name,
        'decision_date' => $decision_date,
        'admin_email' => $admin_email
    ];
    
    try {
        // Email to current owner
        if ($status === 'approved') {
            $data = array_merge($common_data, [
                'user_name' => $transfer['from_name'],
                'user_email' => $transfer['from_email'],
                'to_user_name' => $transfer['to_name'],
                'message' => "Your land transfer request has been approved. The land parcel ownership has been transferred to " . $transfer['to_name'] . "."
            ]);
            
            $emailSender->sendTransferApprovedPrevious($data);
            
        } elseif ($status === 'rejected') {
            $data = array_merge($common_data, [
                'user_name' => $transfer['from_name'],
                'user_email' => $transfer['from_email'],
                'message' => "Your land transfer request has been rejected."
            ]);
            
            $emailSender->sendTransferRejected($data);
            
        } else { // under_review
            $data = array_merge($common_data, [
                'user_name' => $transfer['from_name'],
                'user_email' => $transfer['from_email'],
                'message' => "Your land transfer request is now under administrative review."
            ]);
            
            $emailSender->sendTransferUnderReview($data);
        }
        
        // Email to new owner (only for approved transfers)
        if ($status === 'approved') {
            $data = array_merge($common_data, [
                'user_name' => $transfer['to_name'],
                'user_email' => $transfer['to_email'],
                'message' => "You are now the registered owner of the land parcel. The transfer has been completed successfully."
            ]);
            
            $emailSender->sendTransferApprovedNew($data);
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Failed to send email notifications: " . $e->getMessage());
        return false;
    }
}

// Handle actions
if (isset($_GET['action'])) {
    // Verify CSRF token for POST actions
    if (isset($_POST['csrf_token']) && $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['flash']['error'] = "Security token invalid. Please try again.";
        header("Location: transfers.php");
        exit();
    }
    
    $action = $_GET['action'];
    
    switch ($action) {
        case 'review':
            if (isset($_GET['id']) && isset($_POST['status'])) {
                $transfer_id = (int)$_GET['id'];
                $status = $_POST['status'];
                $review_notes = isset($_POST['review_notes']) ? trim($_POST['review_notes']) : '';
                
                // Debug: Check what's being received
                error_log("Review Action: transfer_id=$transfer_id, status=$status, user_id={$_SESSION['user_id']}");
                
                // Validate required notes for rejection
                if ($status === 'rejected' && empty($review_notes)) {
                    $_SESSION['flash']['error'] = "Review notes are required when rejecting a transfer.";
                    header("Location: transfers.php");
                    exit();
                }
                
                // Update transfer status
                $update_sql = "UPDATE ownership_transfers SET 
                              status = ?, 
                              review_notes = ?, 
                              reviewed_by = ?, 
                              reviewed_at = NOW() 
                              WHERE transfer_id = ?";
                
                $stmt = mysqli_prepare($conn, $update_sql);
                if (!$stmt) {
                    error_log("Prepare failed: " . mysqli_error($conn));
                    $_SESSION['flash']['error'] = "Database error. Please try again.";
                    header("Location: transfers.php");
                    exit();
                }
                
                mysqli_stmt_bind_param($stmt, 'ssii', $status, $review_notes, $_SESSION['user_id'], $transfer_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    error_log("Transfer updated successfully: ID=$transfer_id, Status=$status");
                    
                    // Send email notification for the decision
                    $email_sent = send_transfer_decision_email($transfer_id, $status, $review_notes);
                    
                    // If approved, update land record ownership
                    if ($status === 'approved') {
                        // Get transfer details
                        $transfer_sql = "SELECT record_id, to_user_id FROM ownership_transfers WHERE transfer_id = ?";
                        $transfer_stmt = mysqli_prepare($conn, $transfer_sql);
                        mysqli_stmt_bind_param($transfer_stmt, 'i', $transfer_id);
                        mysqli_stmt_execute($transfer_stmt);
                        $transfer_result = mysqli_stmt_get_result($transfer_stmt);
                        $transfer_data = mysqli_fetch_assoc($transfer_result);
                        
                        if ($transfer_data) {
                            // Update land record ownership using owner_id column
                            $update_land_sql = "UPDATE land_records SET owner_id = ? WHERE record_id = ?";
                            $land_stmt = mysqli_prepare($conn, $update_land_sql);
                            
                            if ($land_stmt) {
                                mysqli_stmt_bind_param($land_stmt, 'ii', $transfer_data['to_user_id'], $transfer_data['record_id']);
                                
                                if (mysqli_stmt_execute($land_stmt)) {
                                    error_log("Land record updated: record_id={$transfer_data['record_id']}, new_owner_id={$transfer_data['to_user_id']}");
                                    $affected_rows = mysqli_stmt_affected_rows($land_stmt);
                                    error_log("Rows affected: $affected_rows");
                                    
                                    // Also update the ownership_transfers record with the new owner info
                                    $update_transfer_sql = "UPDATE ownership_transfers SET completed_at = NOW() WHERE transfer_id = ?";
                                    $complete_stmt = mysqli_prepare($conn, $update_transfer_sql);
                                    mysqli_stmt_bind_param($complete_stmt, 'i', $transfer_id);
                                    mysqli_stmt_execute($complete_stmt);
                                    
                                    // Create notification for new owner
                                    $notification_sql = "INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, 'Transfer Approved', 'Your land ownership transfer has been approved. You are now the owner of the land parcel.', 'success', NOW())";
                                    $notif_stmt = mysqli_prepare($conn, $notification_sql);
                                    mysqli_stmt_bind_param($notif_stmt, 'i', $transfer_data['to_user_id']);
                                    mysqli_stmt_execute($notif_stmt);
                                    
                                    // Get from_user_id for notification
                                    $full_sql = "SELECT from_user_id FROM ownership_transfers WHERE transfer_id = ?";
                                    $full_stmt = mysqli_prepare($conn, $full_sql);
                                    mysqli_stmt_bind_param($full_stmt, 'i', $transfer_id);
                                    mysqli_stmt_execute($full_stmt);
                                    $full_result = mysqli_stmt_get_result($full_stmt);
                                    $full_data = mysqli_fetch_assoc($full_result);
                                    
                                    if ($full_data) {
                                        // Create notification for previous owner
                                        $notification_sql2 = "INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, 'Transfer Completed', 'Your land ownership transfer has been completed. You are no longer the owner of the land parcel.', 'info', NOW())";
                                        $notif_stmt2 = mysqli_prepare($conn, $notification_sql2);
                                        mysqli_stmt_bind_param($notif_stmt2, 'i', $full_data['from_user_id']);
                                        mysqli_stmt_execute($notif_stmt2);
                                    }
                                } else {
                                    error_log("Failed to update land record: " . mysqli_error($conn));
                                    $_SESSION['flash']['error'] = "Failed to update land ownership. Error: " . mysqli_error($conn);
                                }
                            } else {
                                error_log("Failed to prepare land update statement: " . mysqli_error($conn));
                                $_SESSION['flash']['error'] = "Database error when updating land ownership.";
                            }
                        }
                    }
                    
                    $_SESSION['flash']['success'] = "Transfer request has been " . 
                        ($status === 'approved' ? 'approved and ownership transferred!' : 
                         ($status === 'rejected' ? 'rejected.' : 'marked as under review.')) . 
                        ($email_sent ? " Email notifications have been sent." : " Email notifications failed.");
                } else {
                    error_log("Failed to update transfer: " . mysqli_error($conn));
                    $_SESSION['flash']['error'] = "Failed to update transfer request. Please try again.";
                }
                
                header("Location: transfers.php");
                exit();
            } else {
                error_log("Missing parameters for review action");
                $_SESSION['flash']['error'] = "Missing required parameters.";
                header("Location: transfers.php");
                exit();
            }
            break;
            
        case 'delete':
            if (isset($_GET['id'])) {
                $transfer_id = (int)$_GET['id'];
                
                // Check if transfer can be deleted (only submitted/under_review)
                $check_sql = "SELECT status FROM ownership_transfers WHERE transfer_id = ?";
                $check_stmt = mysqli_prepare($conn, $check_sql);
                mysqli_stmt_bind_param($check_stmt, 'i', $transfer_id);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);
                $check_data = mysqli_fetch_assoc($check_result);
                
                if ($check_data && in_array($check_data['status'], ['submitted', 'under_review'])) {
                    $delete_sql = "DELETE FROM ownership_transfers WHERE transfer_id = ?";
                    $delete_stmt = mysqli_prepare($conn, $delete_sql);
                    mysqli_stmt_bind_param($delete_stmt, 'i', $transfer_id);
                    
                    if (mysqli_stmt_execute($delete_stmt)) {
                        $_SESSION['flash']['success'] = "Transfer request deleted successfully.";
                    } else {
                        $_SESSION['flash']['error'] = "Failed to delete transfer request.";
                    }
                } else {
                    $_SESSION['flash']['error'] = "Cannot delete this transfer request. Only submitted or under review transfers can be deleted.";
                }
                
                header("Location: transfers.php");
                exit();
            }
            break;
            
        case 'bulk_action':
            if (isset($_POST['bulk_action']) && isset($_POST['transfer_ids'])) {
                $bulk_action = $_POST['bulk_action'];
                $transfer_ids = array_map('intval', $_POST['transfer_ids']);
                
                if (count($transfer_ids) > 0) {
                    $placeholders = implode(',', array_fill(0, count($transfer_ids), '?'));
                    $types = str_repeat('i', count($transfer_ids));
                    
                    switch ($bulk_action) {
                        case 'mark_review':
                            $update_sql = "UPDATE ownership_transfers SET status = 'under_review', reviewed_by = ?, reviewed_at = NOW() WHERE transfer_id IN ($placeholders) AND status = 'submitted'";
                            $stmt = mysqli_prepare($conn, $update_sql);
                            
                            // Prepare parameters array
                            $params = $transfer_ids;
                            array_unshift($params, $_SESSION['user_id']);
                            $bind_types = 'i' . $types;
                            
                            mysqli_stmt_bind_param($stmt, $bind_types, ...$params);
                            
                            if (mysqli_stmt_execute($stmt)) {
                                $affected = mysqli_affected_rows($conn);
                                $_SESSION['flash']['success'] = "Marked $affected transfer(s) as Under Review.";
                                
                                // Send email notifications for each transfer marked as under review
                                foreach ($transfer_ids as $tid) {
                                    send_transfer_decision_email($tid, 'under_review');
                                }
                            } else {
                                $_SESSION['flash']['error'] = "Failed to update transfers.";
                            }
                            break;
                            
                        case 'delete':
                            $delete_sql = "DELETE FROM ownership_transfers WHERE transfer_id IN ($placeholders) AND status IN ('submitted', 'under_review')";
                            $stmt = mysqli_prepare($conn, $delete_sql);
                            mysqli_stmt_bind_param($stmt, $types, ...$transfer_ids);
                            
                            if (mysqli_stmt_execute($stmt)) {
                                $affected = mysqli_affected_rows($conn);
                                $_SESSION['flash']['success'] = "Deleted $affected transfer(s).";
                            } else {
                                $_SESSION['flash']['error'] = "Failed to delete transfers.";
                            }
                            break;
                            
                        default:
                            $_SESSION['flash']['error'] = "Invalid bulk action selected.";
                    }
                } else {
                    $_SESSION['flash']['error'] = "No transfers selected.";
                }
                
                header("Location: transfers.php");
                exit();
            }
            break;
    }
}

// Rest of the code remains the same...
// Build query
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
$items_per_page = 20;
$pagination = paginate($page, $items_per_page, $total_transfers);

// Get ALL transfer data for this page
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
$transfers_result = mysqli_stmt_get_result($stmt);

// Store ALL transfer data in array for JavaScript
$all_transfers_data = [];
$transfers_for_table = [];

while ($transfer = mysqli_fetch_assoc($transfers_result)) {
    $transfers_for_table[] = $transfer;
    
    // Prepare data for JavaScript
    $transfer_data = $transfer;
    
    // Handle supporting documents
    if (isset($transfer_data['supporting_docs']) && $transfer_data['supporting_docs']) {
        if (is_string($transfer_data['supporting_docs'])) {
            $transfer_data['supporting_docs'] = json_decode($transfer_data['supporting_docs'], true);
        }
    } else {
        $transfer_data['supporting_docs'] = [];
    }
    
    $all_transfers_data[$transfer['transfer_id']] = $transfer_data;
}

// Get statistics
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
        /* Keep all your CSS styles */
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
            max-width: 800px;
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
        
        .selected {
            background-color: rgba(46, 134, 171, 0.1) !important;
        }
        
        .documents-list {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .document-item {
            display: flex;
            align-items: center;
            padding: 10px;
            margin: 5px 0;
            background: white;
            border-radius: 5px;
            border: 1px solid #eee;
        }
        
        .document-item i {
            margin-right: 10px;
            color: var(--primary);
        }
        
        .email-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            margin-left: 5px;
        }
        
        .email-sent {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .email-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
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
                                    <?php if (count($transfers_for_table) > 0): ?>
                                        <?php foreach ($transfers_for_table as $transfer): ?>
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
                                                    <?php if ($has_email_function && in_array($transfer['status'], ['approved', 'rejected', 'under_review'])): ?>
                                                        <span class="email-status email-sent" title="Email notification sent">
                                                            <i class="fas fa-envelope"></i>
                                                        </span>
                                                    <?php endif; ?>
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
                                                                <a href="transfers.php?action=delete&id=<?php echo $transfer['transfer_id']; ?>&csrf_token=<?php echo $csrf_token; ?>" 
                                                                   onclick="return confirmDelete(<?php echo $transfer['transfer_id']; ?>)" 
                                                                   style="color: #e74c3c;">
                                                                    <i class="fas fa-trash"></i> Delete
                                                                </a>
                                                            <?php endif; ?>
                                                            <?php if ($has_email_function && in_array($transfer['status'], ['approved', 'rejected', 'under_review'])): ?>
                                                                <a href="#" onclick="resendNotification(<?php echo $transfer['transfer_id']; ?>); return false;" 
                                                                   style="color: #3498db;">
                                                                    <i class="fas fa-envelope"></i> Resend Email
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
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
                <!-- Content loaded by JavaScript -->
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
            <form id="reviewForm" method="POST" action="transfers.php?action=review">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="transfer_id" id="review_transfer_id">
                
                <div id="reviewTransferDetails">
                    <!-- Content loaded by JavaScript -->
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
        // ALL TRANSFER DATA IS ALREADY LOADED IN PHP - NO API NEEDED!
        const transferData = <?php echo json_encode($all_transfers_data); ?>;
        const hasEmailFunction = <?php echo $has_email_function ? 'true' : 'false'; ?>;
        
        console.log('Transfer data loaded:', transferData);
        console.log('Total transfers available:', Object.keys(transferData).length);
        console.log('Email function available:', hasEmailFunction);
        
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
                    message = `Mark ${selectedCount} transfer(s) as Under Review?\n\nEmail notifications will be sent to all parties.`;
                    break;
                case 'delete':
                    message = `DELETE ${selectedCount} transfer(s)?\n\nThis action cannot be undone!`;
                    break;
            }
            
            return confirm(message);
        }
        
        // Modal functions - NO API CALLS!
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
            
            // Handle supporting documents
            if (transfer.supporting_docs && Array.isArray(transfer.supporting_docs) && transfer.supporting_docs.length > 0) {
                html += `
                    <div class="documents-list">
                        <h4>Supporting Documents (${transfer.supporting_docs.length})</h4>
                        ${transfer.supporting_docs.map(doc => `
                            <div class="document-item">
                                <i class="fas fa-file-alt"></i>
                                <span>${escapeHtml(doc.name || doc.filename || 'Document')}</span>
                                <a href="${escapeHtml(doc.path || doc.url || '#')}" target="_blank" class="btn-small" style="margin-left: auto;">
                                    <i class="fas fa-download"></i> View
                                </a>
                            </div>
                        `).join('')}
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
            
            // Set up the form with the transfer ID
            const form = document.getElementById('reviewForm');
            form.action = `transfers.php?action=review&id=${transferId}`;
            document.getElementById('review_transfer_id').value = transferId;
            
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
            
            if (transfer.days_pending > 7 && (transfer.status === 'submitted' || transfer.status === 'under_review')) {
                const priorityClass = transfer.review_priority === 'overdue' ? 'priority-overdue' : 'priority-warning';
                const priorityText = transfer.review_priority === 'overdue' ? 'OVERDUE' : 'WARNING';
                html += `
                    <div class="info-card ${priorityClass}">
                        <h4><i class="fas fa-exclamation-triangle"></i> Review Priority</h4>
                        <p>This transfer has been pending for <strong>${transfer.days_pending} days</strong>.<br>
                        Priority: <strong>${priorityText}</strong></p>
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
            const form = document.getElementById('reviewForm');
            const transferId = form.action.split('id=')[1];
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
                    message = `APPROVE transfer request #${transferId} (${transfer.reference_no})?\n\nThis will transfer land ownership from ${transfer.from_name} to ${transfer.to_name} and cannot be easily undone.\n\nEmail notifications will be sent to both parties.`;
                    break;
                case 'rejected':
                    message = `REJECT transfer request #${transferId} (${transfer.reference_no})?\n\nThe transfer will be cancelled and both parties will be notified via email.`;
                    break;
                case 'under_review':
                    message = `Mark transfer request #${transferId} (${transfer.reference_no}) as Under Review?\n\nEmail notifications will be sent to inform all parties.`;
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
        
        function resendNotification(transferId) {
            const transfer = transferData[transferId];
            if (!transfer) {
                alert('Transfer not found.');
                return false;
            }
            
            if (!hasEmailFunction) {
                alert('Email functionality is not available in this system.');
                return false;
            }
            
            if (!confirm(`Resend email notification for transfer #${transferId} (${transfer.reference_no})?\n\nThis will send email notifications to ${transfer.from_name} and ${transfer.to_name}.`)) {
                return false;
            }
            
            // Show loading
            const originalText = event.target.innerHTML;
            event.target.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            event.target.disabled = true;
            
            // Send AJAX request to resend notification
            fetch(`transfers.php?action=resend_notification&id=${transferId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `csrf_token=${encodeURIComponent('<?php echo $csrf_token; ?>')}`
            })
            .then(response => response.text())
            .then(data => {
                alert('Email notification resent successfully!');
                // Reload the page to show updated status
                location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to resend email notification. Please try again.');
                event.target.innerHTML = originalText;
                event.target.disabled = false;
            });
            
            return false;
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
        
        console.log('✅ Transfer management system initialized successfully!');
    </script>
</body>
</html>