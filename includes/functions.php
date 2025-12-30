<?php
// Common functions for ArdhiYetu

// Check if session is active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define BASE_URL if not already defined
if (!defined('BASE_URL')) {
    define('BASE_URL', SITE_URL); // Use SITE_URL from config
}

function sanitize_input($data) {
    global $conn;
    if (!isset($conn)) {
        require_once 'config.php';
    }
    
    if (is_string($data)) {
        return mysqli_real_escape_string($conn, trim($data));
    }
    return $data;
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

function is_officer() {
    return isset($_SESSION['role']) && ($_SESSION['role'] == 'officer' || $_SESSION['role'] == 'admin');
}

function redirect($url) {
    if (!headers_sent()) {
        // Ensure URL is absolute
        if (strpos($url, 'http') !== 0) {
            $url = BASE_URL . ltrim($url, '/');
        }
        header("Location: $url");
        exit();
    } else {
        $full_url = strpos($url, 'http') !== 0 ? BASE_URL . ltrim($url, '/') : $url;
        echo '<script>window.location.href="' . $full_url . '";</script>';
        exit();
    }
}

function flash_message($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

function display_flash_message() {
    if (isset($_SESSION['flash'])) {
        foreach ($_SESSION['flash'] as $type => $message) {
            echo "<div class='alert alert-$type'>$message</div>";
        }
        unset($_SESSION['flash']);
    }
}

function format_date($date) {
    return date('d/m/Y H:i', strtotime($date));
}

function generate_reference_number($prefix = 'ARD') {
    return $prefix . '-' . date('Ymd') . '-' . rand(1000, 9999);
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_phone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return (strlen($phone) >= 10 && strlen($phone) <= 15);
}

function get_user_by_id($user_id) {
    global $conn;
    $user_id = mysqli_real_escape_string($conn, $user_id);
    $sql = "SELECT * FROM users WHERE user_id = '$user_id'";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_assoc($result);
}

function get_user_by_email($email) {
    global $conn;
    $email = mysqli_real_escape_string($conn, $email);
    $sql = "SELECT * FROM users WHERE email = '$email'";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_assoc($result);
}

function get_land_record($record_id) {
    global $conn;
    $record_id = mysqli_real_escape_string($conn, $record_id);
    $sql = "SELECT l.*, u.name as owner_name 
            FROM land_records l 
            JOIN users u ON l.owner_id = u.user_id 
            WHERE l.record_id = '$record_id'";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_assoc($result);
}

function count_pending_transfers() {
    global $conn;
    $sql = "SELECT COUNT(*) as count FROM ownership_transfers WHERE status = 'submitted'";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['count'];
}

function validate_coordinates($lat, $lng) {
    $min_lat = -4.8;
    $max_lat = 5.0;
    $min_lng = 33.8;
    $max_lng = 42.0;
    
    return ($lat >= $min_lat && $lat <= $max_lat && 
            $lng >= $min_lng && $lng <= $max_lng);
}

function get_address_from_coordinates($lat, $lng) {
    return "Location at $lat, $lng";
}

function get_flash_message($type) {
    if (isset($_SESSION['flash'][$type])) {
        $message = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $message;
    }
    return null;
}

// Log activity function
function log_activity($user_id, $action_type, $description) {
    global $conn;
    
    if (!$conn) {
        require_once 'config.php';
    }
    
    $user_id = intval($user_id);
    $action_type = mysqli_real_escape_string($conn, trim($action_type));
    $description = mysqli_real_escape_string($conn, trim($description));
    
    $sql = "INSERT INTO user_activities (user_id, action_type, description) 
            VALUES ('$user_id', '$action_type', '$description')";
    return mysqli_query($conn, $sql);
}

// Function to check if admin exists
function admin_exists() {
    global $conn;
    
    if (!$conn) {
        require_once 'config.php';
    }
    
    $sql = "SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND is_active = TRUE";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    
    return $row['count'] > 0;
}

function get_setting($key, $default = null) {
    global $conn;
    
    if (!isset($conn)) {
        require_once 'config.php';
    }
    
    $key = mysqli_real_escape_string($conn, $key);
    
    $sql = "SELECT setting_value FROM system_settings WHERE setting_key = '$key'";
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['setting_value'];
    }
    
    return $default;
}

function paginate($page, $items_per_page, $total_items) {
    $total_pages = ceil($total_items / $items_per_page);
    $page = max(1, min($page, $total_pages));
    $offset = ($page - 1) * $items_per_page;
    
    return [
        'page' => $page,
        'total_pages' => $total_pages,
        'offset' => $offset,
        'limit' => $items_per_page
    ];
}

// Helper function to ensure default settings exist
function ensure_default_settings() {
    global $conn;
    
    if (!$conn) {
        require_once 'config.php';
    }
    
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'system_settings'");
    if (mysqli_num_rows($table_check) == 0) {
        $create_sql = "CREATE TABLE IF NOT EXISTS system_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT,
            description TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        mysqli_query($conn, $create_sql);
    }
    
    $default_settings = [
        ['items_per_page', '20', 'Number of items to display per page'],
        ['site_name', 'ArdhiYetu', 'Website name'],
        ['site_email', 'admin@ardhiyetu.com', 'Default admin email']
    ];
    
    foreach ($default_settings as $setting) {
        $key = mysqli_real_escape_string($conn, $setting[0]);
        $value = mysqli_real_escape_string($conn, $setting[1]);
        $desc = mysqli_real_escape_string($conn, $setting[2]);
        
        $check_sql = "SELECT COUNT(*) as count FROM system_settings WHERE setting_key = '$key'";
        $result = mysqli_query($conn, $check_sql);
        $row = mysqli_fetch_assoc($result);
        
        if ($row['count'] == 0) {
            $insert_sql = "INSERT INTO system_settings (setting_key, setting_value, description) 
                           VALUES ('$key', '$value', '$desc')";
            mysqli_query($conn, $insert_sql);
        }
    }
}

/**
 * Send WebSocket notification
 */
function sendWebSocketNotification($data) {
    return createDatabaseNotification($data);
}

/**
 * Create database notification
 */
function createDatabaseNotification($data) {
    global $conn;
    
    if (!isset($conn)) {
        require_once 'config.php';
    }
    
    if (!isset($data['type'])) return false;
    
    $type = $data['type'];
    $title = '';
    $message = '';
    $userId = 0;
    $relatedId = 0;
    $relatedType = '';
    
    switch ($type) {
        case 'new_land':
            $title = 'New Land Registration';
            $message = isset($data['message']) ? $data['message'] : "New land registration received";
            $relatedId = isset($data['land_id']) ? $data['land_id'] : 0;
            $relatedType = 'land';
            break;
        case 'new_transfer':
            $title = 'New Transfer Request';
            $message = isset($data['message']) ? $data['message'] : "New transfer request received";
            $relatedId = isset($data['transfer_id']) ? $data['transfer_id'] : 0;
            $relatedType = 'transfer';
            break;
        case 'transfer_approved':
            $title = 'Transfer Approved';
            $message = isset($data['message']) ? $data['message'] : "Transfer has been approved";
            $userId = isset($data['to_user_id']) ? $data['to_user_id'] : 0;
            $relatedId = isset($data['transfer_id']) ? $data['transfer_id'] : 0;
            $relatedType = 'transfer';
            break;
        case 'land_approved':
            $title = 'Land Registration Approved';
            $message = isset($data['message']) ? $data['message'] : "Land registration has been approved";
            $userId = isset($data['user_id']) ? $data['user_id'] : 0;
            $relatedId = isset($data['land_id']) ? $data['land_id'] : 0;
            $relatedType = 'land';
            break;
        case 'transfer_rejected':
            $title = 'Transfer Rejected';
            $message = isset($data['message']) ? $data['message'] : "Transfer has been rejected";
            $userId = isset($data['from_user_id']) ? $data['from_user_id'] : 0;
            $relatedId = isset($data['transfer_id']) ? $data['transfer_id'] : 0;
            $relatedType = 'transfer';
            break;
        case 'land_rejected':
            $title = 'Land Registration Rejected';
            $message = isset($data['message']) ? $data['message'] : "Land registration has been rejected";
            $userId = isset($data['user_id']) ? $data['user_id'] : 0;
            $relatedId = isset($data['land_id']) ? $data['land_id'] : 0;
            $relatedType = 'land';
            break;
        default:
            return false;
    }
    
    if ($type === 'new_land' || $type === 'new_transfer') {
        $sql = "SELECT user_id FROM users WHERE role = 'admin' AND is_active = 1";
        $result = mysqli_query($conn, $sql);
        if ($result) {
            while ($admin = mysqli_fetch_assoc($result)) {
                $notification_sql = "INSERT INTO notifications (user_id, title, message, type, related_entity_type, related_entity_id, created_at) 
                                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
                $stmt = mysqli_prepare($conn, $notification_sql);
                mysqli_stmt_bind_param($stmt, "issssi", $admin['user_id'], $title, $message, $type, $relatedType, $relatedId);
                mysqli_stmt_execute($stmt);
            }
            return true;
        }
    } elseif ($userId > 0) {
        $notification_sql = "INSERT INTO notifications (user_id, title, message, type, related_entity_type, related_entity_id, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = mysqli_prepare($conn, $notification_sql);
        mysqli_stmt_bind_param($stmt, "issssi", $userId, $title, $message, $type, $relatedType, $relatedId);
        return mysqli_stmt_execute($stmt);
    }
    
    return false;
}

/**
 * Complete land transfer
 */
function completeLandTransfer($transfer_id, $admin_id, $review_notes) {
    global $conn;
    
    if (!isset($conn)) {
        require_once 'config.php';
    }
    
    mysqli_begin_transaction($conn);
    
    try {
        $sql = "SELECT t.*, l.record_id, l.parcel_no, l.owner_id as current_owner, 
                       u1.name as from_name, u2.name as to_name,
                       u1.user_id as from_id, u2.user_id as to_id
                FROM ownership_transfers t
                JOIN land_records l ON t.record_id = l.record_id
                JOIN users u1 ON t.from_user_id = u1.user_id
                JOIN users u2 ON t.to_user_id = u2.user_id
                WHERE t.transfer_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $transfer_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $transfer = mysqli_fetch_assoc($result);
        
        if (!$transfer) {
            throw new Exception("Transfer not found");
        }
        
        $update_sql = "UPDATE land_records 
                      SET owner_id = ?, 
                          previous_owner_id = ?,
                          status = 'active',
                          updated_at = NOW()
                      WHERE record_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "iii", $transfer['to_user_id'], $transfer['from_user_id'], $transfer['record_id']);
        mysqli_stmt_execute($update_stmt);
        
        $update_transfer_sql = "UPDATE ownership_transfers 
                               SET status = 'approved',
                                   review_notes = ?,
                                   reviewed_by = ?,
                                   reviewed_at = NOW(),
                                   transfer_completed = 1,
                                   completion_date = NOW()
                               WHERE transfer_id = ?";
        $update_transfer_stmt = mysqli_prepare($conn, $update_transfer_sql);
        mysqli_stmt_bind_param($update_transfer_stmt, "sii", $review_notes, $admin_id, $transfer_id);
        mysqli_stmt_execute($update_transfer_stmt);
        
        if (function_exists('log_activity')) {
            log_activity($admin_id, 'transfer_approved', "Approved transfer: {$transfer['reference_no']}", $transfer_id);
            log_activity($transfer['from_id'], 'land_transferred', "Transferred land: {$transfer['parcel_no']}", $transfer['record_id']);
            log_activity($transfer['to_id'], 'land_received', "Received land: {$transfer['parcel_no']}", $transfer['record_id']);
        }
        
        createDatabaseNotification([
            'type' => 'transfer_approved',
            'transfer_id' => $transfer_id,
            'parcel_no' => $transfer['parcel_no'],
            'from_user_id' => $transfer['from_id'],
            'to_user_id' => $transfer['to_id'],
            'to_user' => $transfer['to_name'],
            'from_user' => $transfer['from_name'],
            'message' => "Transfer of {$transfer['parcel_no']} has been approved. Ownership transferred to {$transfer['to_name']}."
        ]);
        
        mysqli_commit($conn);
        return [
            'success' => true,
            'message' => 'Transfer completed successfully',
            'transfer' => $transfer
        ];
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Transfer completion failed: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Transfer failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Approve land registration
 */
function approveLandRegistration($land_id, $admin_id, $review_notes) {
    global $conn;
    
    if (!isset($conn)) {
        require_once 'config.php';
    }
    
    mysqli_begin_transaction($conn);
    
    try {
        $sql = "SELECT l.*, u.name as owner_name, u.user_id as owner_id 
                FROM land_records l 
                JOIN users u ON l.owner_id = u.user_id 
                WHERE l.record_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $land_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $land = mysqli_fetch_assoc($result);
        
        if (!$land) {
            throw new Exception("Land not found");
        }
        
        $update_sql = "UPDATE land_records 
                      SET status = 'active',
                          reviewed_by = ?,
                          review_notes = ?,
                          reviewed_at = NOW(),
                          updated_at = NOW()
                      WHERE record_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "isi", $admin_id, $review_notes, $land_id);
        mysqli_stmt_execute($update_stmt);
        
        if (function_exists('log_activity')) {
            log_activity($admin_id, 'land_approved', "Approved land registration: {$land['parcel_no']}", $land_id);
            log_activity($land['owner_id'], 'land_approved', "Your land registration approved: {$land['parcel_no']}", $land_id);
        }
        
        createDatabaseNotification([
            'type' => 'land_approved',
            'land_id' => $land_id,
            'parcel_no' => $land['parcel_no'],
            'user_id' => $land['owner_id'],
            'user_name' => $land['owner_name'],
            'message' => "Your land registration for {$land['parcel_no']} has been approved."
        ]);
        
        mysqli_commit($conn);
        return [
            'success' => true,
            'message' => 'Land registration approved successfully',
            'land' => $land
        ];
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Land approval failed: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Land approval failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Reject land registration
 */
function rejectLandRegistration($land_id, $admin_id, $review_notes) {
    global $conn;
    
    if (!isset($conn)) {
        require_once 'config.php';
    }
    
    $sql = "UPDATE land_records 
           SET status = 'rejected',
               reviewed_by = ?,
               review_notes = ?,
               reviewed_at = NOW(),
               updated_at = NOW()
           WHERE record_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "isi", $admin_id, $review_notes, $land_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $land_sql = "SELECT l.*, u.name as owner_name, u.user_id as owner_id 
                    FROM land_records l 
                    JOIN users u ON l.owner_id = u.user_id 
                    WHERE l.record_id = ?";
        $land_stmt = mysqli_prepare($conn, $land_sql);
        mysqli_stmt_bind_param($land_stmt, "i", $land_id);
        mysqli_stmt_execute($land_stmt);
        $land_result = mysqli_stmt_get_result($land_stmt);
        $land = mysqli_fetch_assoc($land_result);
        
        createDatabaseNotification([
            'type' => 'land_rejected',
            'land_id' => $land_id,
            'parcel_no' => $land['parcel_no'],
            'user_id' => $land['owner_id'],
            'reason' => $review_notes,
            'message' => "Your land registration for {$land['parcel_no']} has been rejected. Reason: {$review_notes}"
        ]);
        
        return [
            'success' => true,
            'message' => 'Land registration rejected'
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Failed to reject land registration'
    ];
}

/**
 * Reject transfer request
 */
function rejectTransferRequest($transfer_id, $admin_id, $review_notes) {
    global $conn;
    
    if (!isset($conn)) {
        require_once 'config.php';
    }
    
    $sql = "UPDATE ownership_transfers 
           SET status = 'rejected',
               review_notes = ?,
               reviewed_by = ?,
               reviewed_at = NOW()
           WHERE transfer_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sii", $review_notes, $admin_id, $transfer_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $transfer_sql = "SELECT t.*, l.parcel_no, u1.user_id as from_id, u2.user_id as to_id
                        FROM ownership_transfers t
                        JOIN land_records l ON t.record_id = l.record_id
                        JOIN users u1 ON t.from_user_id = u1.user_id
                        JOIN users u2 ON t.to_user_id = u2.user_id
                        WHERE t.transfer_id = ?";
        $transfer_stmt = mysqli_prepare($conn, $transfer_sql);
        mysqli_stmt_bind_param($transfer_stmt, "i", $transfer_id);
        mysqli_stmt_execute($transfer_stmt);
        $transfer_result = mysqli_stmt_get_result($transfer_stmt);
        $transfer = mysqli_fetch_assoc($transfer_result);
        
        if ($transfer) {
            $land_sql = "UPDATE land_records SET status = 'active' WHERE record_id = ?";
            $land_stmt = mysqli_prepare($conn, $land_sql);
            mysqli_stmt_bind_param($land_stmt, "i", $transfer['record_id']);
            mysqli_stmt_execute($land_stmt);
            
            createDatabaseNotification([
                'type' => 'transfer_rejected',
                'transfer_id' => $transfer_id,
                'parcel_no' => $transfer['parcel_no'],
                'from_user_id' => $transfer['from_id'],
                'to_user_id' => $transfer['to_id'],
                'reason' => $review_notes,
                'message' => "Transfer of {$transfer['parcel_no']} has been rejected. Reason: {$review_notes}"
            ]);
        }
        
        return [
            'success' => true,
            'message' => 'Transfer request rejected'
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Failed to reject transfer request'
    ];
}

/**
 * Get split history of a land parcel
 */
function get_land_split_history($parcel_no) {
    global $conn;
    
    if (!isset($conn)) {
        require_once 'config.php';
    }
    
    $sql = "SELECT l.*, u.name as owner_name
            FROM land_records l
            JOIN users u ON l.owner_id = u.user_id
            WHERE l.original_parcel_no = ? OR l.parcel_no = ?
            ORDER BY l.registered_at";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $parcel_no, $parcel_no);
    mysqli_stmt_execute($stmt);
    
    return mysqli_stmt_get_result($stmt);
}

/**
 * Check if land can be split further
 */
function can_split_land($record_id, $min_size = 0.01) {
    global $conn;
    
    if (!isset($conn)) {
        require_once 'config.php';
    }
    
    $sql = "SELECT size, status FROM land_records WHERE record_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $record_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $land = mysqli_fetch_assoc($result);
    
    if (!$land || $land['status'] != 'active') {
        return false;
    }
    
    return $land['size'] >= ($min_size * 2);
}

/**
 * Get all splits from a parent land
 */
function get_land_splits($parent_record_id) {
    global $conn;
    
    if (!isset($conn)) {
        require_once 'config.php';
    }
    
    $sql = "SELECT * FROM land_records WHERE parent_record_id = ? ORDER BY registered_at";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $parent_record_id);
    mysqli_stmt_execute($stmt);
    
    return mysqli_stmt_get_result($stmt);
}
?>