<?php
require_once __DIR__ . '/../../includes/init.php';
// Require login
require_login();

// Include the EmailSender class
require_once __DIR__ . '/../../includes/EmailSender.php';

// Initialize EmailSender for transfer submission notifications
$emailSender = new EmailSender([
    'smtp_username' => 'your-email@gmail.com',  // CHANGE THIS
    'smtp_password' => 'your-app-password',     // CHANGE THIS
    'debug' => true  // Set to false in production
]);

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$transfer_id = null;

// Get user's active lands for transfer
$lands_sql = "SELECT * FROM land_records WHERE owner_id = '$user_id' AND status = 'active'";
$lands_result = mysqli_query($conn, $lands_sql);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    
    if ($action == 'initiate_transfer') {
        $record_id = intval($_POST['record_id']);
        $to_email = sanitize_input($_POST['to_email']);
        $transfer_type = sanitize_input($_POST['transfer_type']);
        $transfer_type_full = $_POST['transfer_type_full'] ?? 'full'; // 'full' or 'partial'
        $price = isset($_POST['price']) ? floatval($_POST['price']) : null;
        $remarks = sanitize_input($_POST['remarks']);
        
        // For partial transfer
        $is_partial = ($transfer_type_full === 'partial');
        $transfer_size = $is_partial ? floatval($_POST['transfer_size']) : null;
        $new_parcel_no = $is_partial ? sanitize_input($_POST['new_parcel_no']) : null;
        
        // Validate
        if (empty($record_id) || empty($to_email)) {
            $error = 'Please fill all required fields';
        } elseif (!validate_email($to_email)) {
            $error = 'Please enter a valid email address';
        } elseif ($to_email == $_SESSION['email']) {
            $error = 'You cannot transfer land to yourself';
        } elseif ($is_partial && (!$transfer_size || $transfer_size <= 0)) {
            $error = 'Please specify a valid transfer size for partial transfer';
        } else {
            // Check if land belongs to user
            $land_sql = "SELECT * FROM land_records WHERE record_id = '$record_id' AND owner_id = '$user_id' AND status = 'active'";
            $land_result = mysqli_query($conn, $land_sql);
            
            if (mysqli_num_rows($land_result) != 1) {
                $error = 'Invalid land record or land is not active';
            } else {
                $land = mysqli_fetch_assoc($land_result);
                
                // Additional validation for partial transfer
                if ($is_partial) {
                    if ($transfer_size >= $land['size']) {
                        $error = 'Transfer size must be less than total land size';
                    } elseif ($transfer_size > ($land['size'] - 0.01)) {
                        $error = 'Must leave at least 0.01 acres in original parcel';
                    }
                    
                    // Generate new parcel number if not provided
                    if (empty($new_parcel_no)) {
                        $new_parcel_no = $land['parcel_no'] . '-SPLIT-' . date('Ymd') . '-' . substr(md5(mt_rand()), 0, 6);
                    }
                    
                    // Check if new parcel number exists
                    $check_parcel_sql = "SELECT record_id FROM land_records WHERE parcel_no = '$new_parcel_no'";
                    $check_parcel_result = mysqli_query($conn, $check_parcel_sql);
                    if (mysqli_num_rows($check_parcel_result) > 0) {
                        $error = 'The generated parcel number already exists. Please specify a different one.';
                    }
                }
                
                // Check if recipient exists
                $recipient_sql = "SELECT user_id, name, email FROM users WHERE email = '$to_email'";
                $recipient_result = mysqli_query($conn, $recipient_sql);
                
                if (mysqli_num_rows($recipient_result) != 1) {
                    $error = 'Recipient not found. Please ensure they have an ArdhiYetu account.';
                } else {
                    $recipient = mysqli_fetch_assoc($recipient_result);
                    $to_user_id = $recipient['user_id'];
                    $to_user_name = $recipient['name'];
                    
                    // Check for pending transfers on this land
                    $pending_sql = "SELECT transfer_id FROM ownership_transfers 
                                   WHERE record_id = '$record_id' AND status IN ('submitted', 'under_review')";
                    $pending_result = mysqli_query($conn, $pending_sql);
                    
                    if (mysqli_num_rows($pending_result) > 0) {
                        $error = 'This land already has a pending transfer request';
                    } else {
                        // Handle document upload
                        $document_path = '';
                        if (isset($_FILES['document']) && $_FILES['document']['error'] == 0) {
                            $allowed_types = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
                            $file_ext = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
                            
                            if (in_array($file_ext, $allowed_types)) {
                                if ($_FILES['document']['size'] <= 5 * 1024 * 1024) {
                                    $upload_dir = '../../uploads/transfers/';
                                    if (!is_dir($upload_dir)) {
                                        mkdir($upload_dir, 0777, true);
                                    }
                                    
                                    $filename = uniqid() . '_' . basename($_FILES['document']['name']);
                                    $target_file = $upload_dir . $filename;
                                    
                                    if (move_uploaded_file($_FILES['document']['tmp_name'], $target_file)) {
                                        $document_path = 'uploads/transfers/' . $filename;
                                    }
                                } else {
                                    $error = 'File too large. Maximum size is 5MB.';
                                }
                            } else {
                                $error = 'Invalid file type. Allowed: PDF, JPG, PNG, DOC.';
                            }
                        }
                        
                        if (!$error) {
                            // Start transaction for partial transfer
                            if ($is_partial) {
                                mysqli_begin_transaction($conn);
                                
                                try {
                                    $remaining_size = $land['size'] - $transfer_size;
                                    $original_parcel_no = $land['parcel_no'];
                                    
                                    // 1. Update original land record size and mark as split
                                    $update_sql = "UPDATE land_records 
                                                   SET size = '$remaining_size', 
                                                       updated_at = NOW() 
                                                   WHERE record_id = '$record_id'";
                                    
                                    if (!mysqli_query($conn, $update_sql)) {
                                        throw new Exception('Failed to update original land: ' . mysqli_error($conn));
                                    }
                                    
                                    // 2. Create new land record for transferred portion
                                    $new_land_sql = "INSERT INTO land_records 
                                                    (parcel_no, location, size, owner_id, status, 
                                                     original_parcel_no, parent_record_id, latitude, 
                                                     longitude, description, document_path, registered_at) 
                                                    SELECT 
                                                    '$new_parcel_no', 
                                                    location, 
                                                    '$transfer_size', 
                                                    '$to_user_id',
                                                    'pending_transfer', 
                                                    '$original_parcel_no', 
                                                    '$record_id', 
                                                    latitude, 
                                                    longitude, 
                                                    description, 
                                                    document_path, 
                                                    NOW() 
                                                    FROM land_records 
                                                    WHERE record_id = '$record_id'";
                                    
                                    if (!mysqli_query($conn, $new_land_sql)) {
                                        throw new Exception('Failed to create new land record: ' . mysqli_error($conn));
                                    }
                                    
                                    $new_record_id = mysqli_insert_id($conn);
                                    
                                    // Update record_id to point to NEW land record for transfer
                                    $record_id_for_transfer = $new_record_id;
                                    
                                    mysqli_commit($conn);
                                    
                                } catch (Exception $e) {
                                    mysqli_rollback($conn);
                                    $error = 'Partial transfer failed: ' . $e->getMessage();
                                    error_log($error);
                                }
                            }
                            
                            if (!$error) {
                                // Let's debug the table structure first
                                $debug_sql = "DESCRIBE ownership_transfers";
                                $debug_result = mysqli_query($conn, $debug_sql);
                                $table_columns = [];
                                while ($col = mysqli_fetch_assoc($debug_result)) {
                                    $table_columns[] = $col['Field'];
                                }
                                
                                // Check what columns we have
                                $available_columns = [];
                                $available_values = [];
                                
                                // Always include required columns
                                $available_columns[] = 'record_id';
                                $available_values[] = "'" . ($is_partial ? $new_record_id : $record_id) . "'";
                                
                                $available_columns[] = 'from_user_id';
                                $available_values[] = "'$user_id'";
                                
                                $available_columns[] = 'to_user_id';
                                $available_values[] = "'$to_user_id'";
                                
                                // Handle reference_no column if it exists
                                if (in_array('reference_no', $table_columns)) {
                                    // Generate a unique reference number
                                    $reference_no = 'TRF-' . date('Ymd') . '-' . str_pad($record_id, 6, '0', STR_PAD_LEFT) . '-' . mt_rand(1000, 9999);
                                    
                                    // Check if this reference number already exists
                                    $check_ref_sql = "SELECT transfer_id FROM ownership_transfers WHERE reference_no = '$reference_no'";
                                    $check_ref_result = mysqli_query($conn, $check_ref_sql);
                                    
                                    // If exists, generate a new one
                                    if (mysqli_num_rows($check_ref_result) > 0) {
                                        $reference_no = 'TRF-' . date('YmdHis') . '-' . mt_rand(10000, 99999);
                                    }
                                    
                                    $available_columns[] = 'reference_no';
                                    $available_values[] = "'$reference_no'";
                                }
                                
                                // Include optional columns only if they exist
                                if (in_array('transfer_type', $table_columns)) {
                                    $available_columns[] = 'transfer_type';
                                    $available_values[] = "'$transfer_type'";
                                }
                                
                                if (in_array('price', $table_columns) && $price !== null) {
                                    $available_columns[] = 'price';
                                    $available_values[] = "'$price'";
                                }
                                
                                if (in_array('document_path', $table_columns) && !empty($document_path)) {
                                    $available_columns[] = 'document_path';
                                    $available_values[] = "'$document_path'";
                                }
                                
                                if (in_array('remarks', $table_columns) && !empty($remarks)) {
                                    $available_columns[] = 'remarks';
                                    $available_values[] = "'$remarks'";
                                }
                                
                                // Add partial transfer columns if they exist
                                if (in_array('is_partial_transfer', $table_columns)) {
                                    $available_columns[] = 'is_partial_transfer';
                                    $available_values[] = $is_partial ? "'1'" : "'0'";
                                }
                                
                                if ($is_partial && in_array('transferred_size', $table_columns)) {
                                    $available_columns[] = 'transferred_size';
                                    $available_values[] = "'$transfer_size'";
                                }
                                
                                if ($is_partial && in_array('remaining_size', $table_columns)) {
                                    $available_columns[] = 'remaining_size';
                                    $available_values[] = "'$remaining_size'";
                                }
                                
                                if ($is_partial && in_array('new_parcel_no', $table_columns)) {
                                    $available_columns[] = 'new_parcel_no';
                                    $available_values[] = "'$new_parcel_no'";
                                }
                                
                                if ($is_partial && in_array('original_parcel_no', $table_columns)) {
                                    $available_columns[] = 'original_parcel_no';
                                    $available_values[] = "'$original_parcel_no'";
                                }
                                
                                // Add timestamp columns if they exist
                                if (in_array('submitted_at', $table_columns)) {
                                    $available_columns[] = 'submitted_at';
                                    $available_values[] = "NOW()";
                                }
                                
                                if (in_array('status', $table_columns)) {
                                    $available_columns[] = 'status';
                                    $available_values[] = "'submitted'";
                                }
                                
                                // Build the INSERT query with only existing columns
                                $insert_sql = "INSERT INTO ownership_transfers (" . implode(', ', $available_columns) . ") 
                                              VALUES (" . implode(', ', $available_values) . ")";
                                
                                // Debug: Log the SQL query
                                error_log("Transfer INSERT SQL: " . $insert_sql);
                                
                                if (mysqli_query($conn, $insert_sql)) {
                                    $transfer_id = mysqli_insert_id($conn);
                                    
                                    // Send email notifications using EmailSender
                                    try {
                                        $site_name = "ArdhiYetu Land Management System";
                                        $admin_email = "admin@ardhiyetu.com";
                                        $submission_date = date('F j, Y');
                                        
                                        // Email to current owner (transfer initiator)
                                        $data = [
                                            'site_name' => $site_name,
                                            'user_name' => $_SESSION['name'],
                                            'transfer_id' => $transfer_id,
                                            'reference_no' => $reference_no ?? "TRF-$transfer_id",
                                            'parcel_no' => $land['parcel_no'],
                                            'location' => $land['location'],
                                            'to_user_name' => $to_user_name,
                                            'submission_date' => $submission_date,
                                            'status' => 'Submitted',
                                            'admin_email' => $admin_email,
                                            'is_partial' => $is_partial,
                                            'transfer_size' => $transfer_size,
                                            'remaining_size' => $remaining_size ?? null,
                                            'new_parcel_no' => $new_parcel_no ?? null,
                                            'message' => $is_partial ? 
                                                "Your partial land transfer request has been submitted. Transferring $transfer_size acres from {$land['parcel_no']} to new parcel: $new_parcel_no. Your original parcel now has $remaining_size acres." :
                                                "Your land transfer request has been submitted successfully and is awaiting review."
                                        ];
                                        
                                        $emailSender->sendTransferSubmission($data);
                                        
                                        // Email to new owner (recipient)
                                        $data['user_name'] = $to_user_name;
                                        $data['message'] = $is_partial ?
                                            "You have been nominated as the new owner of a portion of land ($transfer_size acres from {$land['parcel_no']}). The transfer request is awaiting administrative review." :
                                            "You have been nominated as the new owner of a land parcel. The transfer request is awaiting administrative review.";
                                        
                                        $emailSender->sendEmail(
                                            $to_email, 
                                            $to_user_name, 
                                            'transfer_submission', 
                                            $data
                                        );
                                        
                                        // Email to admin
                                        $data['user_name'] = 'Administrator';
                                        $data['message'] = $is_partial ?
                                            "A new partial land transfer request has been submitted and requires your review." :
                                            "A new land transfer request has been submitted and requires your review.";
                                        $data['from_user_name'] = $_SESSION['name'];
                                        
                                        $emailSender->sendTransferSubmissionAdmin($data);
                                        
                                    } catch (Exception $e) {
                                        error_log("Failed to send email notifications: " . $e->getMessage());
                                        // Continue with other operations even if email fails
                                    }
                                    
                                    // Send WebSocket notification to admins
                                    if (function_exists('sendWebSocketNotification')) {
                                        sendWebSocketNotification([
                                            'type' => 'new_transfer',
                                            'transfer_id' => $transfer_id,
                                            'parcel_no' => $land['parcel_no'],
                                            'from_user' => $_SESSION['name'],
                                            'to_user' => $to_user_name,
                                            'from_user_id' => $user_id,
                                            'to_user_id' => $to_user_id,
                                            'is_partial' => $is_partial,
                                            'transfer_size' => $transfer_size,
                                            'message' => $is_partial ?
                                                "New partial transfer: {$transfer_size} acres from {$land['parcel_no']} ({$_SESSION['name']} to {$to_user_name})" :
                                                "New transfer request: {$land['parcel_no']} from {$_SESSION['name']} to {$to_user_name}"
                                        ]);
                                    }
                                    
                                    // Update land status if not partial (partial already handled)
                                    if (!$is_partial) {
                                        mysqli_query($conn, "UPDATE land_records SET status = 'pending' WHERE record_id = '$record_id'");
                                    }
                                    
                                    // Log activity
                                    if (function_exists('log_activity')) {
                                        $log_message = $is_partial ?
                                            "Initiated partial transfer: ID $transfer_id, $transfer_size acres from {$land['parcel_no']}" :
                                            "Initiated transfer: ID $transfer_id";
                                        log_activity($user_id, 'transfer_initiate', $log_message);
                                    }
                                    
                                    // Send notifications
                                    // To transferor
                                    $notification_message = $is_partial ?
                                        "You have initiated partial transfer of $transfer_size acres from {$land['parcel_no']} to {$to_user_name}. New parcel: $new_parcel_no. Transfer ID: $transfer_id" :
                                        "You have initiated transfer of {$land['parcel_no']} to {$to_user_name}. Transfer ID: $transfer_id";
                                    
                                    $notification_sql = "INSERT INTO notifications (user_id, title, message, type, related_entity_type, related_entity_id) 
                                                        VALUES ('$user_id', 'Transfer Initiated', 
                                                                '$notification_message', 
                                                                'info', 'transfer', '$transfer_id')";
                                    mysqli_query($conn, $notification_sql);
                                    
                                    // To transferee
                                    $notification_message = $is_partial ?
                                        "{$_SESSION['name']} has initiated partial transfer of $transfer_size acres from {$land['parcel_no']} to you. New parcel: $new_parcel_no. Transfer ID: $transfer_id" :
                                        "{$_SESSION['name']} has initiated transfer of {$land['parcel_no']} to you. Transfer ID: $transfer_id";
                                    
                                    $notification_sql = "INSERT INTO notifications (user_id, title, message, type, related_entity_type, related_entity_id) 
                                                        VALUES ('$to_user_id', 'Transfer Request', 
                                                                '$notification_message', 
                                                                'info', 'transfer', '$transfer_id')";
                                    mysqli_query($conn, $notification_sql);
                                    
                                    // To admin
                                    $admin_sql = "SELECT user_id FROM users WHERE role = 'admin' LIMIT 1";
                                    $admin_result = mysqli_query($conn, $admin_sql);
                                    if ($admin_row = mysqli_fetch_assoc($admin_result)) {
                                        $notification_message = $is_partial ?
                                            "{$_SESSION['name']} has initiated partial transfer of $transfer_size acres from {$land['parcel_no']} to {$to_user_name}. New parcel: $new_parcel_no. Transfer ID: $transfer_id" :
                                            "{$_SESSION['name']} has initiated transfer of {$land['parcel_no']} to {$to_user_name}. Transfer ID: $transfer_id";
                                        
                                        $notification_sql = "INSERT INTO notifications (user_id, title, message, type, related_entity_type, related_entity_id) 
                                                            VALUES ('{$admin_row['user_id']}', 'New Transfer Request', 
                                                                    '$notification_message', 
                                                                    'info', 'transfer', '$transfer_id')";
                                        mysqli_query($conn, $notification_sql);
                                    }
                                    
                                    // After successful transfer - Blockchain Integration
                                    if (defined('BLOCKCHAIN_ENABLED') && BLOCKCHAIN_ENABLED) {
                                        // Include BlockchainManager class if not already included
                                        if (!class_exists('BlockchainManager')) {
                                            require_once __DIR__ . '/../../includes/BlockchainManager.php';
                                        }
                                        
                                        $blockchain = new BlockchainManager($conn);
                                        $blockchainResult = $blockchain->transferLandOnBlockchain([
                                            'transfer_id' => $transfer_id,
                                            'parcel_no' => $land['parcel_no'],
                                            'from_user_id' => $user_id,
                                            'to_user_id' => $to_user_id,
                                            'price' => $price
                                        ]);
                                        
                                        if ($blockchainResult['success']) {
                                            $success .= " Transaction recorded on blockchain: " . $blockchainResult['transaction_hash'];
                                            
                                            // Log blockchain transaction
                                            if (function_exists('log_activity')) {
                                                log_activity($user_id, 'blockchain_record', 
                                                    "Transfer $transfer_id recorded on blockchain. Hash: " . $blockchainResult['transaction_hash']);
                                            }
                                            
                                            // Update transfer record with blockchain info if columns exist
                                            $update_transfer_sql = "UPDATE ownership_transfers SET 
                                                                    blockchain_hash = '{$blockchainResult['transaction_hash']}',
                                                                    blockchain_timestamp = NOW()
                                                                    WHERE transfer_id = '$transfer_id'";
                                            mysqli_query($conn, $update_transfer_sql);
                                        } else {
                                            // Log blockchain failure but don't fail the transfer
                                            error_log("Blockchain recording failed for transfer $transfer_id: " . 
                                                     $blockchainResult['error']);
                                            
                                            // Still show success message, just without blockchain part
                                            $success = $is_partial ?
                                                "Partial transfer request submitted successfully. Transferring $transfer_size acres from {$land['parcel_no']} to new parcel: $new_parcel_no. Your original parcel now has $remaining_size acres. Transfer ID: $transfer_id. Email notifications have been sent." :
                                                "Transfer request submitted successfully. Transfer ID: $transfer_id. Email notifications have been sent.";
                                        }
                                    } else {
                                        $success = $is_partial ?
                                            "Partial transfer request submitted successfully. Transferring $transfer_size acres from {$land['parcel_no']} to new parcel: $new_parcel_no. Your original parcel now has $remaining_size acres. Transfer ID: $transfer_id. Email notifications have been sent." :
                                            "Transfer request submitted successfully. Transfer ID: $transfer_id. Email notifications have been sent.";
                                    }
                                } else {
                                    $error = 'Failed to submit transfer request: ' . mysqli_error($conn);
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

// Get land ID from URL if specified
$land_id = isset($_GET['land_id']) ? intval($_GET['land_id']) : 0;

// Reset lands_result pointer for form display
mysqli_data_seek($lands_result, 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Land - ArdhiYetu</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Enhanced Main Content Styles Only */
        :root {
            --primary-gradient: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            --secondary-gradient: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            --success-gradient: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            --warning-gradient: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-border: rgba(255, 255, 255, 0.2);
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 20px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 8px 30px rgba(0, 0, 0, 0.12);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Modern Layout */
        .transfer-container {
            padding: 2rem 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: calc(100vh - 180px);
        }

        .transfer-header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 2rem;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--glass-border);
        }

        .transfer-header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            font-weight: 700;
        }

        .transfer-header p {
            color: #6c757d;
            font-size: 1.1rem;
        }

        .transfer-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        @media (max-width: 992px) {
            .transfer-content {
                grid-template-columns: 1fr;
            }
        }

        /* Enhanced Cards */
        .transfer-form-container, .transfer-info {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--glass-border);
            padding: 2rem;
            transition: var(--transition);
        }

        .transfer-form-container:hover, .transfer-info:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        /* Form Enhancements */
        .form-section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: rgba(248, 249, 250, 0.5);
            border-radius: var(--radius-md);
            border-left: 4px solid #007bff;
        }

        .form-section h3 {
            font-size: 1.3rem;
            color: #495057;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .form-section h3 i {
            color: #007bff;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #495057;
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: var(--radius-sm);
            font-size: 1rem;
            transition: var(--transition);
            background: white;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        /* Transfer Type Selector */
        .transfer-type-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .transfer-type-card {
            position: relative;
            cursor: pointer;
            border: 2px solid #e9ecef;
            border-radius: var(--radius-md);
            padding: 1.5rem;
            text-align: center;
            transition: var(--transition);
            background: white;
        }

        .transfer-type-card:hover {
            border-color: #007bff;
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        .transfer-type-card.selected {
            border-color: #007bff;
            background: linear-gradient(135deg, rgba(0, 123, 255, 0.05) 0%, rgba(0, 123, 255, 0.1) 100%);
        }

        .transfer-type-card i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #6c757d;
            transition: var(--transition);
        }

        .transfer-type-card.selected i {
            color: #007bff;
        }

        .transfer-type-card input {
            position: absolute;
            opacity: 0;
        }

        .transfer-type-card span {
            font-weight: 600;
            color: #495057;
        }

        /* Partial Transfer Section */
        .partial-transfer-section {
            background: rgba(23, 162, 184, 0.05);
            border: 2px solid rgba(23, 162, 184, 0.2);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            margin: 1.5rem 0;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .size-input-container {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 1rem;
            align-items: center;
        }

        .size-preview {
            background: white;
            padding: 1rem;
            border-radius: var(--radius-sm);
            border: 2px solid #e9ecef;
            min-width: 200px;
        }

        .size-bar {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin: 0.5rem 0;
        }

        .size-bar-fill {
            height: 100%;
            background: var(--primary-gradient);
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        /* Transfer List */
        .transfers-list {
            display: grid;
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .transfer-item {
            background: white;
            border-radius: var(--radius-md);
            padding: 1.25rem;
            border-left: 4px solid;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .transfer-item:hover {
            transform: translateX(5px);
            box-shadow: var(--shadow-md);
        }

        .transfer-item.status-submitted {
            border-left-color: #ffc107;
        }

        .transfer-item.status-under_review {
            border-left-color: #17a2b8;
        }

        .transfer-item.status-approved {
            border-left-color: #28a745;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-submitted .status-badge {
            background: rgba(255, 193, 7, 0.1);
            color: #e0a800;
        }

        .status-under_review .status-badge {
            background: rgba(23, 162, 184, 0.1);
            color: #17a2b8;
        }

        /* Progress Steps */
        .transfer-progress {
            margin: 2rem 0;
            padding: 1.5rem;
            background: rgba(0, 123, 255, 0.05);
            border-radius: var(--radius-md);
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin: 2rem 0;
        }

        .progress-steps::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: #e9ecef;
            transform: translateY(-50%);
            z-index: 1;
        }

        .progress-step {
            position: relative;
            z-index: 2;
            text-align: center;
            width: 80px;
        }

        .step-circle {
            width: 40px;
            height: 40px;
            background: white;
            border: 3px solid #e9ecef;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            transition: var(--transition);
        }

        .progress-step.active .step-circle {
            border-color: #007bff;
            background: #007bff;
            color: white;
            transform: scale(1.1);
        }

        .step-label {
            font-size: 0.85rem;
            color: #6c757d;
        }

        /* Enhanced Buttons */
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e9ecef;
        }

        .btn-enhanced {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: var(--radius-sm);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 1rem;
        }

        .btn-enhanced:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary-enhanced {
            background: var(--secondary-gradient);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: var(--radius-sm);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 1rem;
        }

        .btn-secondary-enhanced:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Enhanced Alerts */
        .alert-enhanced {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-enhanced.error {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }

        .alert-enhanced.success {
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.2);
            color: #28a745;
        }

        /* Info Cards */
        .info-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .info-card h3 {
            color: #495057;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-card ul {
            list-style: none;
            padding: 0;
        }

        .info-card li {
            padding: 0.5rem 0;
            color: #6c757d;
            display: flex;
            align-items: start;
            gap: 0.5rem;
        }

        .info-card li:before {
            content: '✓';
            color: #28a745;
            font-weight: bold;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .transfer-header {
                padding: 1rem;
            }

            .transfer-header h1 {
                font-size: 2rem;
            }

            .form-actions {
                flex-direction: column;
            }

            .size-input-container {
                grid-template-columns: 1fr;
            }

            .transfer-type-options {
                grid-template-columns: 1fr;
            }
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- EXACT ORIGINAL MENU BAR - NO CHANGES -->
    <nav class="navbar">
        <div class="container">
            <a href="../index.php" class="logo">
                <i class="fas fa-landmark"></i> ArdhiYetu
            </a>
            <div class="nav-links">
                <a href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="my-lands.php"><i class="fas fa-landmark"></i> My Lands</a>
                <a href="land-map.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'land-map.php' ? 'active' : ''; ?>"><i class="fas fa-map"></i> Land Map</a>
                <a href="transfer-land.php" class="active"><i class="fas fa-exchange-alt"></i> Transfer</a>
                <a href="documents.php"><i class="fas fa-file-alt"></i> Documents</a>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <?php if (is_admin()): ?>
                    <a href="../admin/index.php" class="btn"><i class="fas fa-user-shield"></i> Admin</a>
                <?php endif; ?>
                <a href="../logout.php" class="btn logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
            <button class="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <main class="transfer-container">
        <div class="container">
            <div class="transfer-header">
                <h1><i class="fas fa-exchange-alt"></i> Transfer Land Ownership</h1>
                <p>Securely transfer land ownership with our streamlined digital process</p>
            </div>

            <?php if ($error): ?>
                <div class="alert-enhanced error">
                    <i class="fas fa-exclamation-circle fa-2x"></i>
                    <div>
                        <strong>Error</strong>
                        <p><?php echo $error; ?></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert-enhanced success">
                    <i class="fas fa-check-circle fa-2x"></i>
                    <div>
                        <strong>Success!</strong>
                        <p><?php echo $success; ?></p>
                        <div style="margin-top: 1rem;">
                            <a href="transfer-status.php?id=<?php echo urlencode($transfer_id ?? ''); ?>" class="btn-enhanced">
                                <i class="fas fa-eye"></i> View Transfer Status
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="transfer-progress">
                <h3><i class="fas fa-list-ol"></i> Transfer Process</h3>
                <div class="progress-steps">
                    <div class="progress-step active">
                        <div class="step-circle">1</div>
                        <div class="step-label">Initiate</div>
                    </div>
                    <div class="progress-step">
                        <div class="step-circle">2</div>
                        <div class="step-label">Review</div>
                    </div>
                    <div class="progress-step">
                        <div class="step-circle">3</div>
                        <div class="step-label">Approve</div>
                    </div>
                    <div class="progress-step">
                        <div class="step-circle">4</div>
                        <div class="step-label">Complete</div>
                    </div>
                </div>
            </div>

            <div class="transfer-content">
                <div class="transfer-form-container">
                    <h2><i class="fas fa-pencil-alt"></i> Initiate New Transfer</h2>
                    
                    <form method="POST" action="" class="transfer-form" enctype="multipart/form-data" id="transferForm">
                        <input type="hidden" name="action" value="initiate_transfer">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <!-- Land Selection -->
                        <div class="form-section">
                            <h3><i class="fas fa-landmark"></i> Select Land Parcel</h3>
                            <div class="form-group">
                                <label for="record_id">Available Land Parcels *</label>
                                <select id="record_id" name="record_id" required class="modern-select">
                                    <option value="">Choose a land parcel...</option>
                                    <?php while ($land = mysqli_fetch_assoc($lands_result)): ?>
                                        <?php 
                                        $check_split_sql = "SELECT COUNT(*) as split_count FROM land_records WHERE parent_record_id = '{$land['record_id']}'";
                                        $check_split_result = mysqli_query($conn, $check_split_sql);
                                        $split_count = mysqli_fetch_assoc($check_split_result)['split_count'];
                                        ?>
                                        <option value="<?php echo $land['record_id']; ?>" 
                                                data-size="<?php echo $land['size']; ?>"
                                                data-parcel="<?php echo $land['parcel_no']; ?>"
                                                data-location="<?php echo $land['location']; ?>"
                                                <?php echo $land_id == $land['record_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($land['parcel_no']); ?> • 
                                            <?php echo htmlspecialchars($land['location']); ?> • 
                                            <?php echo $land['size']; ?> acres
                                            <?php if ($split_count > 0): ?>
                                                <span style="color: #6c757d;">(Split <?php echo $split_count; ?> time<?php echo $split_count > 1 ? 's' : ''; ?>)</span>
                                            <?php endif; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <?php if (mysqli_num_rows($lands_result) == 0): ?>
                                    <div class="alert-enhanced warning" style="margin-top: 1rem; background: rgba(255, 193, 7, 0.1); border-color: rgba(255, 193, 7, 0.2);">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <div>
                                            <strong>No Lands Available</strong>
                                            <p>You don't have any active land records.</p>
                                            <a href="my-lands.php?action=add" class="btn-enhanced" style="margin-top: 0.5rem; padding: 0.5rem 1rem; display: inline-block;">
                                                <i class="fas fa-plus"></i> Register Land
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Transfer Type -->
                        <div class="form-section">
                            <h3><i class="fas fa-exchange-alt"></i> Transfer Type</h3>
                            <p style="color: #6c757d; margin-bottom: 1rem;">Choose how you want to transfer this land</p>
                            
                            <div class="transfer-type-options">
                                <label class="transfer-type-card" for="type-full">
                                    <input type="radio" name="transfer_type_full" value="full" id="type-full" checked>
                                    <i class="fas fa-arrows-alt-h"></i>
                                    <div>
                                        <span>Full Transfer</span>
                                        <small style="display: block; color: #6c757d; margin-top: 0.5rem;">
                                            Transfer entire land parcel
                                        </small>
                                    </div>
                                </label>
                                
                                <label class="transfer-type-card" for="type-partial">
                                    <input type="radio" name="transfer_type_full" value="partial" id="type-partial">
                                    <i class="fas fa-code-branch"></i>
                                    <div>
                                        <span>Partial Transfer</span>
                                        <small style="display: block; color: #6c757d; margin-top: 0.5rem;">
                                            Transfer portion only
                                        </small>
                                    </div>
                                </label>
                            </div>
                            
                            <div id="partial-transfer-section" class="partial-transfer-section">
                                <h4><i class="fas fa-chart-pie"></i> Specify Transfer Portion</h4>
                                
                                <div class="form-group">
                                    <label for="transfer_size">Size to Transfer (acres) *</label>
                                    <div class="size-input-container">
                                        <input type="number" 
                                               id="transfer_size" 
                                               name="transfer_size" 
                                               step="0.01" 
                                               min="0.01" 
                                               placeholder="Enter portion size">
                                        <div class="size-preview">
                                            <small>Total: <span id="total-size">0</span> acres</small>
                                            <div class="size-bar">
                                                <div class="size-bar-fill" id="size-bar-fill" style="width: 0%"></div>
                                            </div>
                                            <small>Max: <span id="max-transfer">0</span> acres</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_parcel_no">New Parcel Number</label>
                                    <input type="text" 
                                           id="new_parcel_no" 
                                           name="new_parcel_no" 
                                           placeholder="Will be auto-generated">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recipient Information -->
                        <div class="form-section">
                            <h3><i class="fas fa-user-friends"></i> Recipient Details</h3>
                            <div class="form-group">
                                <label for="to_email">Recipient's Email Address *</label>
                                <div style="position: relative;">
                                    <input type="email" 
                                           id="to_email" 
                                           name="to_email" 
                                           required 
                                           placeholder="recipient@example.com">
                                    <i class="fas fa-envelope" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #6c757d;"></i>
                                </div>
                                <small style="color: #6c757d; display: block; margin-top: 0.5rem;">Recipient must have an ArdhiYetu account</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="transfer_type">Transfer Purpose</label>
                                <select id="transfer_type" name="transfer_type" class="modern-select">
                                    <option value="">Select purpose...</option>
                                    <option value="sale">Sale</option>
                                    <option value="gift">Gift</option>
                                    <option value="inheritance">Inheritance</option>
                                    <option value="lease">Lease</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group" id="price-field" style="display: none;">
                                <label for="price">Sale Price (Ksh)</label>
                                <div style="position: relative;">
                                    <span style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #6c757d; font-weight: 600;">Ksh</span>
                                    <input type="number" 
                                           id="price" 
                                           name="price" 
                                           min="0" 
                                           step="0.01" 
                                           placeholder="0.00"
                                           style="padding-left: 50px;">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Supporting Documents -->
                        <div class="form-section">
                            <h3><i class="fas fa-file-upload"></i> Supporting Documents</h3>
                            <div class="form-group">
                                <label for="document">Upload Document (Optional)</label>
                                <div class="file-upload-area">
                                    <input type="file" 
                                           id="document" 
                                           name="document" 
                                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                           style="display: none;">
                                    <div id="file-drop-area" style="border: 2px dashed #dee2e6; border-radius: var(--radius-sm); padding: 2rem; text-align: center; cursor: pointer; transition: var(--transition);">
                                        <i class="fas fa-cloud-upload-alt fa-2x" style="color: #6c757d; margin-bottom: 1rem;"></i>
                                        <p style="margin-bottom: 0.5rem; color: #495057;">Drag & drop files here or click to browse</p>
                                        <small style="color: #6c757d;">Max file size: 5MB • PDF, JPG, PNG, DOC</small>
                                        <div id="file-preview" style="margin-top: 1rem;"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="remarks">Additional Remarks</label>
                                <textarea id="remarks" 
                                          name="remarks" 
                                          rows="4" 
                                          placeholder="Any additional information about this transfer..."
                                          style="resize: vertical;"></textarea>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-enhanced" id="submitBtn">
                                <i class="fas fa-paper-plane"></i> Submit Transfer Request
                            </button>
                            <button type="button" class="btn-secondary-enhanced" id="resetBtn">
                                <i class="fas fa-redo"></i> Reset Form
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="transfer-info">
                    <h2><i class="fas fa-history"></i> Active Transfer Requests</h2>
                    
                    <?php
                    $active_transfers_sql = "SELECT t.*, l.parcel_no, u.name as recipient_name, 
                                            t.is_partial_transfer, t.transferred_size, t.new_parcel_no
                                            FROM ownership_transfers t
                                            JOIN land_records l ON t.record_id = l.record_id
                                            JOIN users u ON t.to_user_id = u.user_id
                                            WHERE t.from_user_id = '$user_id' 
                                            AND t.status IN ('submitted', 'under_review')
                                            ORDER BY t.submitted_at DESC";
                    $active_transfers = mysqli_query($conn, $active_transfers_sql);
                    
                    if (mysqli_num_rows($active_transfers) > 0):
                    ?>
                        <div class="transfers-list">
                            <?php while ($transfer = mysqli_fetch_assoc($active_transfers)): ?>
                                <div class="transfer-item status-<?php echo $transfer['status']; ?>">
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                                        <div>
                                            <h4 style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                                                <i class="fas fa-exchange-alt"></i>
                                                Transfer #<?php echo htmlspecialchars($transfer['transfer_id']); ?>
                                            </h4>
                                            <small style="color: #6c757d;">Parcel: <?php echo htmlspecialchars($transfer['parcel_no']); ?></small>
                                        </div>
                                        <span class="status-badge">
                                            <?php echo str_replace('_', ' ', $transfer['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin: 1rem 0;">
                                        <div>
                                            <small style="color: #6c757d;">Recipient</small>
                                            <p style="margin: 0.25rem 0; font-weight: 600; color: #495057;">
                                                <?php echo htmlspecialchars($transfer['recipient_name']); ?>
                                            </p>
                                        </div>
                                        <div>
                                            <small style="color: #6c757d;">Initiated</small>
                                            <p style="margin: 0.25rem 0; font-weight: 600; color: #495057;">
                                                <?php echo format_date($transfer['submitted_at']); ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <?php if ($transfer['is_partial_transfer']): ?>
                                        <div style="background: rgba(23, 162, 184, 0.1); padding: 0.75rem; border-radius: var(--radius-sm); margin: 0.75rem 0;">
                                            <div style="display: flex; align-items: center; gap: 0.5rem; color: #17a2b8;">
                                                <i class="fas fa-code-branch"></i>
                                                <small><strong>Partial Transfer:</strong> <?php echo $transfer['transferred_size']; ?> acres</small>
                                            </div>
                                            <small style="color: #6c757d;">New parcel: <?php echo htmlspecialchars($transfer['new_parcel_no']); ?></small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                                        <a href="transfer-status.php?id=<?php echo urlencode($transfer['transfer_id']); ?>" 
                                           class="btn-enhanced" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                        <?php if ($transfer['is_partial_transfer']): ?>
                                            <a href="review-partial-transfer.php?id=<?php echo urlencode($transfer['transfer_id']); ?>" 
                                               class="btn-secondary-enhanced" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                                                <i class="fas fa-code-branch"></i> Review Split
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-exchange-alt"></i>
                            <h3>No Active Transfers</h3>
                            <p>You don't have any active transfer requests.</p>
                            <p style="color: #6c757d;">Initiate a new transfer using the form.</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="info-card">
                        <h3><i class="fas fa-lightbulb"></i> Tips for Successful Transfers</h3>
                        <ul>
                            <li>Ensure the recipient has verified their ArdhiYetu account</li>
                            <li>Double-check parcel numbers and sizes before submission</li>
                            <li>Upload supporting documents for faster processing</li>
                            <li>Communicate with the recipient about the pending transfer</li>
                            <li>Check your email for transfer status updates</li>
                            <li>Partial transfers create new parcel numbers automatically</li>
                            <li>Keep your contact information up to date</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- EXACT ORIGINAL FOOTER - NO CHANGES -->
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

    <script src="../../assets/js/script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize variables
            let selectedLandSize = 0;
            const minKeepSize = 0.01;
            
            // DOM Elements
            const landSelect = document.getElementById('record_id');
            const partialSection = document.getElementById('partial-transfer-section');
            const transferSizeInput = document.getElementById('transfer_size');
            const typeFull = document.getElementById('type-full');
            const typePartial = document.getElementById('type-partial');
            const priceField = document.getElementById('price-field');
            const transferTypeSelect = document.getElementById('transfer_type');
            const fileDropArea = document.getElementById('file-drop-area');
            const fileInput = document.getElementById('document');
            const filePreview = document.getElementById('file-preview');
            
            // Land selection handler
            if (landSelect) {
                landSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    if (selectedOption.value) {
                        selectedLandSize = parseFloat(selectedOption.getAttribute('data-size') || 0);
                        updateSizeDisplay();
                        
                        // Update parcel info in UI
                        const parcelNo = selectedOption.getAttribute('data-parcel');
                        const location = selectedOption.getAttribute('data-location');
                        if (parcelNo) {
                            console.log(`Selected: ${parcelNo} - ${location}`);
                        }
                    }
                });
                
                // Initialize
                if (landSelect.value) {
                    const selectedOption = landSelect.options[landSelect.selectedIndex];
                    selectedLandSize = parseFloat(selectedOption.getAttribute('data-size') || 0);
                    updateSizeDisplay();
                }
            }
            
            // Update size display with visual bar
            function updateSizeDisplay() {
                const totalSizeEl = document.getElementById('total-size');
                const maxTransferEl = document.getElementById('max-transfer');
                const sizeBarFill = document.getElementById('size-bar-fill');
                
                if (totalSizeEl) totalSizeEl.textContent = selectedLandSize.toFixed(2);
                
                const maxTransfer = Math.max(0, selectedLandSize - minKeepSize);
                if (maxTransferEl) maxTransferEl.textContent = maxTransfer.toFixed(2);
                
                if (transferSizeInput) {
                    transferSizeInput.max = maxTransfer.toFixed(2);
                    transferSizeInput.setAttribute('max', maxTransfer.toFixed(2));
                    transferSizeInput.setAttribute('placeholder', `Max ${maxTransfer.toFixed(2)} acres`);
                }
                
                // Update visual bar
                if (sizeBarFill) {
                    const percentage = (maxTransfer / selectedLandSize) * 100;
                    sizeBarFill.style.width = `${percentage}%`;
                }
            }
            
            // Transfer type toggle with visual feedback
            function togglePartialTransfer(isPartial) {
                const typeCards = document.querySelectorAll('.transfer-type-card');
                typeCards.forEach(card => card.classList.remove('selected'));
                
                if (isPartial) {
                    partialSection.style.display = 'block';
                    document.getElementById('type-partial').parentElement.classList.add('selected');
                    if (transferSizeInput) {
                        transferSizeInput.required = true;
                        transferSizeInput.focus();
                    }
                } else {
                    partialSection.style.display = 'none';
                    document.getElementById('type-full').parentElement.classList.add('selected');
                    if (transferSizeInput) {
                        transferSizeInput.required = false;
                        transferSizeInput.value = '';
                    }
                }
            }
            
            // Transfer type selection
            if (typeFull && typePartial) {
                typeFull.addEventListener('change', () => togglePartialTransfer(false));
                typePartial.addEventListener('change', () => togglePartialTransfer(true));
                // Initialize
                togglePartialTransfer(false);
            }
            
            // Real-time transfer size validation with visual feedback
            if (transferSizeInput) {
                transferSizeInput.addEventListener('input', function() {
                    const value = parseFloat(this.value) || 0;
                    const maxTransfer = selectedLandSize - minKeepSize;
                    
                    // Update visual bar
                    const sizeBarFill = document.getElementById('size-bar-fill');
                    if (sizeBarFill) {
                        const percentage = (value / selectedLandSize) * 100;
                        sizeBarFill.style.width = `${Math.min(percentage, 100)}%`;
                    }
                    
                    // Validation
                    if (value > maxTransfer) {
                        this.setCustomValidity(`Maximum: ${maxTransfer.toFixed(2)} acres`);
                        this.style.borderColor = '#dc3545';
                    } else if (value < 0.01 && value > 0) {
                        this.setCustomValidity('Minimum: 0.01 acres');
                        this.style.borderColor = '#dc3545';
                    } else {
                        this.setCustomValidity('');
                        this.style.borderColor = value > 0 ? '#28a745' : '#e9ecef';
                    }
                });
            }
            
            // Show/hide price field
            if (transferTypeSelect && priceField) {
                transferTypeSelect.addEventListener('change', function() {
                    priceField.style.display = this.value === 'sale' ? 'block' : 'none';
                    if (this.value !== 'sale') {
                        document.getElementById('price').value = '';
                    }
                });
            }
            
            // File upload with preview
            if (fileDropArea && fileInput) {
                // Click to browse
                fileDropArea.addEventListener('click', () => fileInput.click());
                
                // Drag and drop
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    fileDropArea.addEventListener(eventName, preventDefaults, false);
                });
                
                function preventDefaults(e) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                
                ['dragenter', 'dragover'].forEach(eventName => {
                    fileDropArea.addEventListener(eventName, highlight, false);
                });
                
                ['dragleave', 'drop'].forEach(eventName => {
                    fileDropArea.addEventListener(eventName, unhighlight, false);
                });
                
                function highlight() {
                    fileDropArea.style.borderColor = '#007bff';
                    fileDropArea.style.backgroundColor = 'rgba(0, 123, 255, 0.05)';
                }
                
                function unhighlight() {
                    fileDropArea.style.borderColor = '#dee2e6';
                    fileDropArea.style.backgroundColor = '';
                }
                
                // Handle file selection
                fileInput.addEventListener('change', handleFileSelect);
                fileDropArea.addEventListener('drop', handleDrop);
                
                function handleFileSelect(e) {
                    const files = e.target.files;
                    handleFiles(files);
                }
                
                function handleDrop(e) {
                    const dt = e.dataTransfer;
                    const files = dt.files;
                    handleFiles(files);
                }
                
                function handleFiles(files) {
                    if (files.length > 0) {
                        const file = files[0];
                        
                        // Size validation
                        if (file.size > 5 * 1024 * 1024) {
                            showFileError('File size must be less than 5MB');
                            return;
                        }
                        
                        // Type validation
                        const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 
                                            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                        if (!allowedTypes.includes(file.type)) {
                            showFileError('Invalid file type. Allowed: PDF, JPG, PNG, DOC');
                            return;
                        }
                        
                        // Show preview
                        showFilePreview(file);
                    }
                }
                
                function showFilePreview(file) {
                    filePreview.innerHTML = `
                        <div style="display: flex; align-items: center; gap: 1rem; background: white; padding: 1rem; border-radius: var(--radius-sm); border: 1px solid #dee2e6;">
                            <i class="fas fa-file-alt fa-2x" style="color: #007bff;"></i>
                            <div style="flex: 1;">
                                <div style="font-weight: 600;">${file.name}</div>
                                <small style="color: #6c757d;">${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                            </div>
                            <button type="button" onclick="removeFile()" style="background: none; border: none; color: #dc3545; cursor: pointer;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    `;
                }
                
                function showFileError(message) {
                    filePreview.innerHTML = `
                        <div style="color: #dc3545; background: rgba(220, 53, 69, 0.1); padding: 0.75rem; border-radius: var(--radius-sm);">
                            <i class="fas fa-exclamation-circle"></i> ${message}
                        </div>
                    `;
                    fileInput.value = '';
                }
                
                window.removeFile = function() {
                    filePreview.innerHTML = '';
                    fileInput.value = '';
                };
            }
            
            // Form validation and submission
            const form = document.getElementById('transferForm');
            const submitBtn = document.getElementById('submitBtn');
            
            if (form && submitBtn) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Basic validation
                    if (!landSelect.value) {
                        showValidationError('Please select a land parcel to transfer', landSelect);
                        return false;
                    }
                    
                    const email = document.getElementById('to_email');
                    if (!email.value || !validateEmail(email.value)) {
                        showValidationError('Please enter a valid recipient email', email);
                        return false;
                    }
                    
                    const isPartial = typePartial.checked;
                    if (isPartial) {
                        const transferSize = parseFloat(transferSizeInput.value);
                        if (!transferSize || transferSize < 0.01) {
                            showValidationError('Minimum transfer size is 0.01 acres', transferSizeInput);
                            return false;
                        }
                        
                        if (transferSize >= selectedLandSize) {
                            showValidationError(`Transfer size must be less than total land size (${selectedLandSize.toFixed(2)} acres)`, transferSizeInput);
                            return false;
                        }
                    }
                    
                    // Show loading state
                    submitBtn.innerHTML = '<span class="loading"></span> Processing Transfer...';
                    submitBtn.disabled = true;
                    
                    // Submit form
                    this.submit();
                });
            }
            
            // Reset form
            const resetBtn = document.getElementById('resetBtn');
            if (resetBtn) {
                resetBtn.addEventListener('click', function() {
                    if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
                        form.reset();
                        togglePartialTransfer(false);
                        if (filePreview) filePreview.innerHTML = '';
                        if (submitBtn) {
                            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Transfer Request';
                            submitBtn.disabled = false;
                        }
                    }
                });
            }
            
            // Helper functions
            function validateEmail(email) {
                const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(email);
            }
            
            function showValidationError(message, element) {
                // Create error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert-enhanced error';
                errorDiv.innerHTML = `
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <strong>Validation Error</strong>
                        <p>${message}</p>
                    </div>
                `;
                
                // Insert before form
                form.parentNode.insertBefore(errorDiv, form);
                
                // Focus element
                if (element) {
                    element.focus();
                    element.style.borderColor = '#dc3545';
                    
                    // Remove error after 5 seconds
                    setTimeout(() => {
                        if (errorDiv.parentNode) {
                            errorDiv.parentNode.removeChild(errorDiv);
                        }
                        element.style.borderColor = '#e9ecef';
                    }, 5000);
                }
            }
            
            // Initialize transfer type cards
            const transferCards = document.querySelectorAll('.transfer-type-card');
            transferCards.forEach(card => {
                card.addEventListener('click', function() {
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change'));
                });
            });
        });
    </script>
</body>
</html>