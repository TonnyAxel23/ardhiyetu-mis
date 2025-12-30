<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../debug.log');

require_once __DIR__ . '/../../includes/init.php';

// Require login
require_login();

// Generate CSRF token if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = $_SESSION['user_id'];
error_log("=== DEBUG: Page loaded for user ID: " . $user_id . " ===");

// Handle actions with improved validation
$action = $_GET['action'] ?? 'list';
$action = htmlspecialchars($action, ENT_QUOTES, 'UTF-8');
$record_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? 0;

// Search and filter with improved sanitization
$search = $_GET['search'] ?? '';
$search = htmlspecialchars($search, ENT_QUOTES, 'UTF-8');
$status = $_GET['status'] ?? '';
$status = htmlspecialchars($status, ENT_QUOTES, 'UTF-8');

// Build query with prepared statements
$where_conditions = ["owner_id = ?"];
$params = [$user_id];
$param_types = "i";

if (!empty($search)) {
    $where_conditions[] = "(parcel_no LIKE ? OR location LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $param_types .= "ss";
}
if (!empty($status)) {
    $where_conditions[] = "status = ?";
    $params[] = $status;
    $param_types .= "s";
}

$where = implode(" AND ", $where_conditions);

// Pagination with validation
$limit = 10;
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]) ?? 1;
$offset = ($page - 1) * $limit;

// Get total count
try {
    $count_sql = "SELECT COUNT(*) as total FROM land_records WHERE $where";
    $count_stmt = mysqli_prepare($conn, $count_sql);
    if ($count_stmt) {
        mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
        mysqli_stmt_execute($count_stmt);
        $count_result = mysqli_stmt_get_result($count_stmt);
        $total_row = mysqli_fetch_assoc($count_result);
        $total_lands = $total_row ? (int)$total_row['total'] : 0;
        $total_pages = $total_lands > 0 ? ceil($total_lands / $limit) : 0;
        mysqli_stmt_close($count_stmt);
    } else {
        throw new Exception("Failed to prepare count statement");
    }
} catch (Exception $e) {
    error_log("Count query error: " . $e->getMessage());
    $total_lands = 0;
    $total_pages = 0;
}

// Get lands with split information
$lands = [];
try {
    $sql = "SELECT l.*, 
                   (SELECT COUNT(*) FROM land_records WHERE parent_record_id = l.record_id) as split_count,
                   (SELECT parcel_no FROM land_records WHERE record_id = l.parent_record_id) as parent_parcel
            FROM land_records l 
            WHERE $where 
            ORDER BY registered_at DESC 
            LIMIT ? OFFSET ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        $limit_param = $limit;
        $offset_param = $offset;
        $full_param_types = $param_types . "ii";
        $full_params = array_merge($params, [$limit_param, $offset_param]);
        
        mysqli_stmt_bind_param($stmt, $full_param_types, ...$full_params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $lands[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
} catch (Exception $e) {
    error_log("Land query error: " . $e->getMessage());
    flash_message('error', 'Unable to load land records. Please try again.');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // DEBUG: Log POST request
    error_log("=== DEBUG: POST REQUEST RECEIVED ===");
    error_log("POST Data: " . print_r($_POST, true));
    error_log("FILES Data: " . print_r($_FILES, true));
    error_log("Session User ID: " . $_SESSION['user_id']);
    
    // Validate CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        error_log("CSRF token validation failed");
        flash_message('error', 'Security validation failed. Please try again.');
        if ($_POST['action'] == 'add_land') {
            redirect('my-lands.php?action=add');
        } else {
            redirect('my-lands.php');
        }
        exit;
    }
    
    $form_action = $_POST['action'] ?? '';
    $form_action = htmlspecialchars($form_action, ENT_QUOTES, 'UTF-8');
    error_log("Form Action: " . $form_action);
    
    if ($form_action == 'add_land') {
        error_log("=== DEBUG: Processing add_land ===");
        
        // Validate and sanitize inputs
        $parcel_no = trim($_POST['parcel_no'] ?? '');
        $parcel_no = htmlspecialchars($parcel_no, ENT_QUOTES, 'UTF-8');
        $location = trim($_POST['location'] ?? '');
        $location = htmlspecialchars($location, ENT_QUOTES, 'UTF-8');
        $size = filter_input(INPUT_POST, 'size', FILTER_VALIDATE_FLOAT);
        $latitude = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT) ?: null;
        $longitude = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT) ?: null;
        $description = trim($_POST['description'] ?? '');
        $description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
        
        error_log("Parcel No: " . $parcel_no);
        error_log("Location: " . $location);
        error_log("Size: " . $size);
        error_log("Latitude: " . $latitude);
        error_log("Longitude: " . $longitude);
        error_log("Description: " . $description);
        
        // Enhanced validation
        $errors = [];
        if (empty($parcel_no)) $errors[] = 'Parcel number is required';
        if (empty($location)) $errors[] = 'Location is required';
        if (!$size || $size <= 0) $errors[] = 'Valid size is required (greater than 0)';
        
        if (!empty($errors)) {
            error_log("Validation Errors: " . implode(', ', $errors));
            flash_message('error', implode('<br>', $errors));
        } else {
            // Check if parcel number exists
            error_log("Checking if parcel number exists...");
            $check_sql = "SELECT record_id FROM land_records WHERE parcel_no = ?";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            if (!$check_stmt) {
                error_log("Check statement failed: " . mysqli_error($conn));
                flash_message('error', 'Database error. Please try again.');
            } else {
                mysqli_stmt_bind_param($check_stmt, "s", $parcel_no);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);
                
                if (mysqli_num_rows($check_result) > 0) {
                    error_log("Parcel number already exists: " . $parcel_no);
                    flash_message('error', 'Parcel number already exists.');
                } else {
                    mysqli_stmt_close($check_stmt);
                    
                    // Handle file upload
                    $document_path = '';
                    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
                        error_log("Processing file upload...");
                        $file_info = $_FILES['document'];
                        $allowed_types = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 
                                        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                        $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
                        
                        $file_ext = strtolower(pathinfo($file_info['name'], PATHINFO_EXTENSION));
                        $file_mime = mime_content_type($file_info['tmp_name']);
                        
                        error_log("File extension: " . $file_ext);
                        error_log("File MIME type: " . $file_mime);
                        error_log("File size: " . $file_info['size']);
                        
                        if (!in_array($file_ext, $allowed_extensions) || !in_array($file_mime, $allowed_types)) {
                            error_log("Invalid file type");
                            flash_message('error', 'Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX.');
                        } elseif ($file_info['size'] > 5 * 1024 * 1024) {
                            error_log("File too large");
                            flash_message('error', 'File too large. Maximum size is 5MB.');
                        } else {
                            $upload_dir = __DIR__ . '/../../uploads/lands/';
                            error_log("Upload directory: " . $upload_dir);
                            
                            if (!is_dir($upload_dir)) {
                                error_log("Creating upload directory...");
                                if (mkdir($upload_dir, 0755, true)) {
                                    error_log("Directory created successfully");
                                } else {
                                    error_log("Failed to create directory");
                                    flash_message('error', 'Failed to create upload directory.');
                                }
                            }
                            
                            if (is_dir($upload_dir) && is_writable($upload_dir)) {
                                $safe_filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file_info['name']);
                                $filename = uniqid('land_', true) . '_' . $safe_filename;
                                $target_file = $upload_dir . $filename;
                                
                                error_log("Target file: " . $target_file);
                                
                                if (move_uploaded_file($file_info['tmp_name'], $target_file)) {
                                    $document_path = 'uploads/lands/' . $filename;
                                    error_log("File uploaded successfully: " . $document_path);
                                } else {
                                    error_log("File upload failed. Error: " . $file_info['error']);
                                    $upload_errors = [
                                        0 => 'There is no error, the file uploaded with success',
                                        1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
                                        2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
                                        3 => 'The uploaded file was only partially uploaded',
                                        4 => 'No file was uploaded',
                                        6 => 'Missing a temporary folder',
                                        7 => 'Failed to write file to disk.',
                                        8 => 'A PHP extension stopped the file upload.',
                                    ];
                                    error_log("Upload error meaning: " . ($upload_errors[$file_info['error']] ?? 'Unknown error'));
                                    flash_message('error', 'Failed to upload file. Please try again.');
                                }
                            } else {
                                error_log("Upload directory not writable: " . $upload_dir);
                                flash_message('error', 'Upload directory is not writable. Please contact administrator.');
                            }
                        }
                    } else {
                        error_log("No file uploaded or upload error: " . ($_FILES['document']['error'] ?? 'No file'));
                    }
                    
                    // Insert land record
                    error_log("Starting database transaction...");
                    mysqli_begin_transaction($conn);
                    
                    try {
                        // Get available columns
                        error_log("Checking database columns...");
                        $columns_result = mysqli_query($conn, "SHOW COLUMNS FROM land_records");
                        $columns = [];
                        while ($row = mysqli_fetch_assoc($columns_result)) {
                            $columns[$row['Field']] = true;
                        }
                        error_log("Available columns: " . implode(', ', array_keys($columns)));
                        
                        // Build dynamic insert
                        $insert_fields = ["owner_id", "parcel_no", "location", "size", "status"];
                        $placeholders = ["?", "?", "?", "?", "'pending'"];
                        $insert_params = [$user_id, $parcel_no, $location, $size];
                        $param_types = "issd";
                        
                        error_log("Insert fields: " . implode(', ', $insert_fields));
                        error_log("Insert params: " . print_r($insert_params, true));
                        error_log("Param types: " . $param_types);
                        
                        // Add optional fields
                        $optional_fields = [
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                            'description' => $description,
                            'document_path' => $document_path
                        ];
                        
                        foreach ($optional_fields as $field => $value) {
                            if (isset($columns[$field]) && !empty($value)) {
                                $insert_fields[] = $field;
                                $placeholders[] = "?";
                                $insert_params[] = $value;
                                $param_types .= is_float($value) ? "d" : "s";
                                error_log("Adding optional field: " . $field . " = " . $value);
                            }
                        }
                        
                        $insert_sql = "INSERT INTO land_records (" . implode(", ", $insert_fields) . ") 
                                      VALUES (" . implode(", ", $placeholders) . ")";
                        error_log("SQL Query: " . $insert_sql);
                        error_log("Parameters: " . print_r($insert_params, true));
                        error_log("Parameter types: " . $param_types);
                        
                        $insert_stmt = mysqli_prepare($conn, $insert_sql);
                        
                        if (!$insert_stmt) {
                            $error_msg = 'Failed to prepare statement: ' . mysqli_error($conn);
                            error_log("ERROR: " . $error_msg);
                            throw new Exception($error_msg);
                        }
                        
                        error_log("Statement prepared successfully");
                        
                        if (!mysqli_stmt_bind_param($insert_stmt, $param_types, ...$insert_params)) {
                            $error_msg = 'Failed to bind parameters: ' . mysqli_error($conn);
                            error_log("ERROR: " . $error_msg);
                            throw new Exception($error_msg);
                        }
                        
                        error_log("Parameters bound successfully");
                        
                        if (!mysqli_stmt_execute($insert_stmt)) {
                            $error_msg = 'Failed to execute insert: ' . mysqli_stmt_error($insert_stmt);
                            error_log("ERROR: " . $error_msg);
                            throw new Exception($error_msg);
                        }
                        
                        $new_record_id = mysqli_insert_id($conn);
                        error_log("SUCCESS: Land inserted with ID: " . $new_record_id);
                        
                        // Send notification to admins (using database only)
                        if (function_exists('createDatabaseNotification')) {
                            error_log("Creating notification...");
                            createDatabaseNotification([
                                'type' => 'new_land',
                                'land_id' => $new_record_id,
                                'parcel_no' => $parcel_no,
                                'user_name' => $_SESSION['name'],
                                'user_id' => $user_id,
                                'message' => "New land registration: {$parcel_no} by {$_SESSION['name']}"
                            ]);
                        }
                        
                        // Log activity
                        if (function_exists('log_activity')) {
                            error_log("Logging activity...");
                            log_activity($user_id, 'land_registration', "Registered new land: $parcel_no", $new_record_id);
                        }
                        
                        mysqli_commit($conn);
                        error_log("Transaction committed");
                        flash_message('success', 'Land registered successfully. Awaiting verification.');
                        redirect('my-lands.php');
                        exit;
                        
                    } catch (Exception $e) {
                        mysqli_rollback($conn);
                        error_log("ROLLBACK: " . $e->getMessage());
                        flash_message('error', 'Registration failed: ' . $e->getMessage());
                    }
                }
            }
        }
        
    } elseif ($form_action == 'update_land') {
        // Validate CSRF token for update as well
        if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
            error_log("CSRF token validation failed for update");
            flash_message('error', 'Security validation failed. Please try again.');
            redirect("my-lands.php?action=edit&id=" . ($_POST['record_id'] ?? ''));
            exit;
        }
        
        $record_id = filter_input(INPUT_POST, 'record_id', FILTER_VALIDATE_INT);
        $location = trim($_POST['location'] ?? '');
        $location = htmlspecialchars($location, ENT_QUOTES, 'UTF-8');
        $size = filter_input(INPUT_POST, 'size', FILTER_VALIDATE_FLOAT);
        $latitude = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT) ?: null;
        $longitude = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT) ?: null;
        $description = trim($_POST['description'] ?? '');
        $description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
        
        // Validate
        if (empty($location) || !$size || $size <= 0) {
            flash_message('error', 'Please fill all required fields correctly.');
        } else {
            // Check if user owns this land
            $check_sql = "SELECT status, parcel_no FROM land_records WHERE record_id = ? AND owner_id = ?";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "ii", $record_id, $user_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if (mysqli_num_rows($check_result) === 0) {
                flash_message('error', 'Land record not found or access denied.');
                mysqli_stmt_close($check_stmt);
                redirect('my-lands.php');
            }
            
            $land_data = mysqli_fetch_assoc($check_result);
            mysqli_stmt_close($check_stmt);
            
            // Handle file upload if new file is provided
            $document_update = "";
            $document_path = "";
            
            if (isset($_FILES['document']) && $_FILES['document']['error'] == UPLOAD_ERR_OK) {
                $allowed_types = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 
                                'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
                
                $file_info = $_FILES['document'];
                $file_ext = strtolower(pathinfo($file_info['name'], PATHINFO_EXTENSION));
                $file_mime = mime_content_type($file_info['tmp_name']);
                
                if (!in_array($file_ext, $allowed_extensions) || !in_array($file_mime, $allowed_types)) {
                    flash_message('error', 'Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX.');
                } elseif ($file_info['size'] > 5 * 1024 * 1024) {
                    flash_message('error', 'File too large. Maximum size is 5MB.');
                } else {
                    $upload_dir = __DIR__ . '/../../uploads/lands/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $safe_filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file_info['name']);
                    $filename = uniqid('land_', true) . '_' . $safe_filename;
                    $target_file = $upload_dir . $filename;
                    
                    if (move_uploaded_file($file_info['tmp_name'], $target_file)) {
                        $document_path = 'uploads/lands/' . $filename;
                        $document_update = true;
                    } else {
                        flash_message('error', 'Failed to upload file.');
                    }
                }
            }
            
            // Check which columns exist in the database
            $check_columns_sql = "SHOW COLUMNS FROM land_records";
            $columns_result = mysqli_query($conn, $check_columns_sql);
            $columns = [];
            while ($row = mysqli_fetch_assoc($columns_result)) {
                $columns[$row['Field']] = true;
            }
            
            // Build update query based on available columns
            $update_fields = ["location = ?", "size = ?", "updated_at = NOW()"];
            $update_params = [$location, $size];
            $param_types = "sd";
            
            // Add optional columns if they exist
            if (isset($columns['latitude']) && isset($columns['longitude'])) {
                $update_fields[] = "latitude = ?";
                $update_fields[] = "longitude = ?";
                $param_types .= "dd";
                $update_params[] = $latitude;
                $update_params[] = $longitude;
            }
            
            if (isset($columns['description'])) {
                $update_fields[] = "description = ?";
                $param_types .= "s";
                $update_params[] = $description;
            }
            
            if ($document_update && isset($columns['document_path'])) {
                $update_fields[] = "document_path = ?";
                $param_types .= "s";
                $update_params[] = $document_path;
            }
            
            // Add where clause params
            $update_params[] = $record_id;
            $update_params[] = $user_id;
            $param_types .= "ii";
            
            // Update land record
            $update_sql = "UPDATE land_records SET " . implode(", ", $update_fields) . 
                          " WHERE record_id = ? AND owner_id = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            
            if ($update_stmt) {
                mysqli_stmt_bind_param($update_stmt, $param_types, ...$update_params);
                
                if (mysqli_stmt_execute($update_stmt)) {
                    flash_message('success', 'Land record updated successfully.');
                    redirect("my-lands.php?action=view&id=$record_id");
                } else {
                    flash_message('error', 'Failed to update land: ' . mysqli_error($conn));
                }
                mysqli_stmt_close($update_stmt);
            } else {
                flash_message('error', 'Failed to update land: ' . mysqli_error($conn));
            }
        }
    }
}

// Get land details for view/edit
$land = null;
if ($record_id > 0) {
    $land_sql = "SELECT l.*, 
                        (SELECT COUNT(*) FROM land_records WHERE parent_record_id = l.record_id) as split_count,
                        (SELECT parcel_no FROM land_records WHERE record_id = l.parent_record_id) as parent_parcel
                 FROM land_records l
                 WHERE record_id = ? AND owner_id = ?";
    $land_stmt = mysqli_prepare($conn, $land_sql);
    mysqli_stmt_bind_param($land_stmt, "ii", $record_id, $user_id);
    mysqli_stmt_execute($land_stmt);
    $land_result = mysqli_stmt_get_result($land_stmt);
    $land = mysqli_fetch_assoc($land_result);
    mysqli_stmt_close($land_stmt);
}

// Get land statistics including splits
$stats_sql = "SELECT 
    COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
    COUNT(CASE WHEN status = 'transferred' THEN 1 END) as transferred,
    COUNT(CASE WHEN status = 'disputed' THEN 1 END) as disputed,
    COUNT(CASE WHEN parent_record_id IS NOT NULL THEN 1 END) as split_lands,
    COUNT(CASE WHEN original_parcel_no IS NOT NULL THEN 1 END) as original_lands
    FROM land_records WHERE owner_id = ?";
$stats_stmt = mysqli_prepare($conn, $stats_sql);
mysqli_stmt_bind_param($stats_stmt, "i", $user_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);
mysqli_stmt_close($stats_stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Land Records - ArdhiYetu</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Only enhance the content area, keep original navbar */
        .lands-container {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            min-height: calc(100vh - 140px);
            padding: 2rem 0;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        /* Modern Header */
        .lands-header {
            margin-bottom: 2rem;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .lands-header h1 {
            font-size: 2rem;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .lands-header p {
            color: #7f8c8d;
            font-size: 1.1rem;
        }
        
        /* Modern Statistics Bar */
        .statistics-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
            padding: 1rem 0;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-color), #3a9cc8);
        }
        
        .stat-card.special::before {
            background: linear-gradient(135deg, #A23B72, #c44569);
        }
        
        .stat-number {
            font-size: 2.2rem;
            font-weight: bold;
            color: #2c3e50;
            display: block;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Modern Cards */
        .lands-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .land-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
            animation: fadeIn 0.5s ease-out;
        }
        
        .land-card:hover {
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transform: translateY(-5px);
        }
        
        .land-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 1px solid #e9ecef;
        }
        
        .land-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-active {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
            animation: pulse 2s infinite;
        }
        
        .status-transferred {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }
        
        .status-disputed {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .status-pending_transfer {
            background: rgba(0, 123, 255, 0.1);
            color: #007bff;
        }
        
        .land-actions {
            position: absolute;
            top: 1rem;
            right: 1rem;
            display: flex;
            gap: 0.5rem;
        }
        
        .land-body {
            padding: 1.5rem;
            position: relative;
        }
        
        .land-body h3 {
            font-size: 1.2rem;
            color: #2c3e50;
            margin-bottom: 0.75rem;
            font-weight: 600;
        }
        
        .location {
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.95rem;
        }
        
        .land-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .info-item {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
        }
        
        .info-item .label {
            font-size: 0.8rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 0.25rem;
        }
        
        .info-item .value {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .land-footer {
            padding: 1.5rem;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
        }
        
        .action-buttons-small {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        
        /* Modern Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #3a9cc8);
            color: white;
            border: none;
            box-shadow: 0 4px 12px rgba(46, 134, 171, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(46, 134, 171, 0.4);
        }
        
        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        /* Modern Form */
        .form-container {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f3f4;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 3px rgba(46, 134, 171, 0.1);
        }
        
        .form-hint {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.5rem;
            display: block;
        }
        
        /* Modern Filter Bar */
        .filter-bar {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            margin-bottom: 2rem;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .input-with-icon {
            position: relative;
        }
        
        .input-with-icon i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .input-with-icon input {
            padding-left: 2.5rem;
        }
        
        /* Modern Pagination */
        .pagination {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            margin-top: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .pagination-info {
            color: #6c757d;
            font-size: 0.95rem;
        }
        
        .pagination-controls {
            display: flex;
            gap: 0.5rem;
        }
        
        .page-link {
            padding: 0.75rem 1rem;
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            color: #6c757d;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
        }
        
        .page-link:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            transform: translateY(-1px);
        }
        
        .page-link.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        /* Modern Badges */
        .split-badge {
            background: linear-gradient(135deg, rgba(0, 123, 255, 0.1), rgba(0, 123, 255, 0.05));
            color: #007bff;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .original-badge {
            background: linear-gradient(135deg, rgba(162, 59, 114, 0.1), rgba(162, 59, 114, 0.05));
            color: var(--secondary-color);
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .split-count-badge {
            background: linear-gradient(135deg, rgba(12, 84, 96, 0.1), rgba(12, 84, 96, 0.05));
            color: #0c5460;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        /* Modern Details View */
        .land-details {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
        }
        
        .details-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f3f4;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .details-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .details-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .info-item {
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .info-item:last-child {
            margin-bottom: 0;
        }
        
        .info-item label {
            font-weight: 600;
            color: #6c757d;
        }
        
        .info-item span {
            color: #2c3e50;
        }
        
        /* Modern Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #e9ecef;
            margin-bottom: 1.5rem;
        }
        
        .empty-state h3 {
            font-size: 1.5rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }
        
        .empty-state-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { 
                opacity: 0; 
                transform: translateY(20px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.6; }
            100% { opacity: 1; }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        
        /* Modern Flash Messages */
        .flash-message {
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 12px;
            font-weight: 500;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            animation: fadeIn 0.5s ease-out;
            border-left: 4px solid transparent;
        }
        
        .flash-success {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(40, 167, 69, 0.05));
            color: #28a745;
            border-left-color: #28a745;
        }
        
        .flash-error {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
            color: #dc3545;
            border-left-color: #dc3545;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            
            .header-top {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .lands-grid {
                grid-template-columns: 1fr;
            }
            
            .statistics-bar {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .pagination {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .details-grid {
                grid-template-columns: 1fr;
            }
            
            .empty-state-actions {
                flex-direction: column;
            }
        }
        
        @media (max-width: 480px) {
            .statistics-bar {
                grid-template-columns: 1fr;
            }
            
            .land-info {
                grid-template-columns: 1fr;
            }
            
            .action-buttons-small {
                flex-direction: column;
            }
            
            .btn-small {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Original Navigation Bar (completely unchanged) -->
    <nav class="navbar">
        <div class="container">
            <a href="../../index.php" class="logo">
                <i class="fas fa-landmark"></i> ArdhiYetu
            </a>
            <div class="nav-links">
                <a href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="my-lands.php" class="active"><i class="fas fa-landmark"></i> My Lands</a>
                <a href="land-map.php"><i class="fas fa-map"></i> Land Map</a>
                <a href="transfer-land.php"><i class="fas fa-exchange-alt"></i> Transfer</a>
                <a href="../documents.php"><i class="fas fa-file-alt"></i> Documents</a>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <?php if (is_admin()): ?>
                    <a href="../admin/index.php" class="btn btn-admin"><i class="fas fa-user-shield"></i> Admin</a>
                <?php endif; ?>
                <a href="../logout.php" class="btn logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
            <button class="mobile-menu-btn" aria-label="Toggle menu">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <!-- Original User Notifications Area -->
    <div id="userNotifications" class="user-notifications-area"></div>

    <!-- Enhanced Main Content Area -->
    <main class="lands-container">
        <div class="container">
            <div class="lands-header">
                <div class="header-top">
                    <h1><i class="fas fa-landmark"></i> My Land Records</h1>
                    <a href="?action=add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Register New Land
                    </a>
                </div>
                <p>Manage your registered land parcels and monitor their status</p>
                
                <!-- Modern Statistics Bar -->
                <?php if ($action == 'list'): ?>
                <div class="statistics-bar">
                    <div class="stat-card">
                        <span class="stat-number"><?php echo $total_lands; ?></span>
                        <span class="stat-label">Total Lands</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?php echo $stats['active'] ?? 0; ?></span>
                        <span class="stat-label">Active</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?php echo $stats['pending'] ?? 0; ?></span>
                        <span class="stat-label">Pending</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?php echo $stats['disputed'] ?? 0; ?></span>
                        <span class="stat-label">Needs Attention</span>
                    </div>
                    <div class="stat-card special">
                        <span class="stat-number"><?php echo $stats['split_lands'] ?? 0; ?></span>
                        <span class="stat-label">Split Lands</span>
                    </div>
                    <div class="stat-card special">
                        <span class="stat-number"><?php echo $stats['original_lands'] ?? 0; ?></span>
                        <span class="stat-label">Original Lands</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php 
            // Display flash messages with modern styling
            if (isset($_SESSION['flash'])) {
                foreach ($_SESSION['flash'] as $type => $message) {
                    echo "<div class='flash-message flash-$type'>$message</div>";
                }
                unset($_SESSION['flash']);
            }
            ?>
            
            <!-- Keep original debug info -->
            <?php if (isset($_POST) && !empty($_POST)): ?>
            <div class="debug-info">
                <strong>DEBUG:</strong> Form submitted with action: <?php echo $_POST['action'] ?? 'none'; ?>
            </div>
            <?php endif; ?>
            
            <!-- Content Sections with Modern Styling -->
            <?php if ($action == 'view' && $land): ?>
                <!-- View Land Details -->
                <div class="land-details fade-in">
                    <div class="details-header">
                        <h2>Land Record Details</h2>
                        <div class="action-buttons">
                            <?php if ($land['status'] == 'pending' || $land['status'] == 'active'): ?>
                                <a href="?action=edit&id=<?php echo $record_id; ?>" class="btn">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                            <?php endif; ?>
                            <a href="my-lands.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to List
                            </a>
                        </div>
                    </div>
                    
                    <div class="details-grid">
                        <div class="details-card">
                            <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
                            <div class="info-item">
                                <label>Parcel Number:</label>
                                <span><?php echo htmlspecialchars($land['parcel_no']); ?>
                                    <?php if ($land['parent_record_id']): ?>
                                        <span class="split-badge" title="Split from parent parcel">
                                            <i class="fas fa-code-branch"></i> Split
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($land['original_parcel_no']): ?>
                                        <span class="original-badge" title="Original parcel: <?php echo htmlspecialchars($land['original_parcel_no']); ?>">
                                            <i class="fas fa-history"></i> Original
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($land['split_count'] > 0): ?>
                                        <span class="split-count-badge" title="<?php echo $land['split_count']; ?> split(s) from this land">
                                            <i class="fas fa-sitemap"></i> <?php echo $land['split_count']; ?>
                                        </span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <label>Location:</label>
                                <span><?php echo htmlspecialchars($land['location']); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Size:</label>
                                <span><?php echo number_format($land['size'], 2); ?> acres</span>
                            </div>
                            <div class="info-item">
                                <label>Status:</label>
                                <span class="status-badge-modern status-<?php echo $land['status']; ?>">
                                    <i class="fas fa-circle"></i> <?php echo ucfirst($land['status']); ?>
                                </span>
                            </div>
                            <?php if ($land['parent_record_id'] && $land['parent_parcel']): ?>
                            <div class="info-item">
                                <label>Split From:</label>
                                <span><?php echo htmlspecialchars($land['parent_parcel']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="details-card">
                            <h3><i class="fas fa-history"></i> Registration Details</h3>
                            <div class="info-item">
                                <label>Registered:</label>
                                <span><?php echo format_date($land['registered_at']); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Last Updated:</label>
                                <span><?php echo format_date($land['updated_at']); ?></span>
                            </div>
                            <?php if (isset($land['latitude']) && isset($land['longitude']) && $land['latitude'] && $land['longitude']): ?>
                            <div class="info-item">
                                <label>Coordinates:</label>
                                <span class="coordinate-display">
                                    <i class="fas fa-location-dot"></i>
                                    <?php echo $land['latitude'] . ', ' . $land['longitude']; ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <label>Map:</label>
                                <a href="land-map.php?focus=<?php echo $land['record_id']; ?>" class="btn-small">
                                    <i class="fas fa-map-marked-alt"></i> View on Map
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php if (isset($land['document_path']) && $land['document_path']): ?>
                            <div class="info-item">
                                <label>Document:</label>
                                <a href="../../<?php echo htmlspecialchars($land['document_path']); ?>" 
                                   target="_blank" 
                                   class="btn-small" download>
                                    <i class="fas fa-file-download"></i> Download
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($land['split_count'] > 0): ?>
                    <div class="details-card">
                        <h3><i class="fas fa-sitemap"></i> Split Information</h3>
                        <div class="land-split-info">
                            <p><i class="fas fa-info-circle"></i> This land has been split <?php echo $land['split_count']; ?> time(s).</p>
                            <div class="split-actions">
                                <a href="view-splits.php?land_id=<?php echo $land['record_id']; ?>" class="btn btn-small">
                                    <i class="fas fa-eye"></i> View Splits
                                </a>
                                <?php if ($land['status'] == 'active'): ?>
                                    <a href="transfer-land.php?land_id=<?php echo $land['record_id']; ?>" class="btn btn-small">
                                        <i class="fas fa-code-branch"></i> Split Again
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($land['description']) && $land['description']): ?>
                    <div class="details-card">
                        <h3><i class="fas fa-align-left"></i> Description</h3>
                        <p><?php echo nl2br(htmlspecialchars($land['description'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($land['status'] == 'active'): ?>
                    <div class="action-card">
                        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                        <div class="land-actions-horizontal">
                            <a href="transfer-land.php?land_id=<?php echo $record_id; ?>" class="btn">
                                <i class="fas fa-exchange-alt"></i> Transfer Ownership
                            </a>
                            <a href="../documents.php?action=certificate&land_id=<?php echo $record_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-certificate"></i> Generate Certificate
                            </a>
                            <a href="land-history.php?id=<?php echo $record_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-history"></i> View History
                            </a>
                            <a href="print-record.php?id=<?php echo $record_id; ?>" class="btn btn-secondary" target="_blank">
                                <i class="fas fa-print"></i> Print Record
                            </a>
                            <a href="?action=edit&id=<?php echo $record_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-edit"></i> Update Details
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
            <?php elseif ($action == 'edit' && $land && ($land['status'] == 'pending' || $land['status'] == 'active')): ?>
                <!-- Edit Land Form -->
                <div class="form-container fade-in">
                    <div class="form-header">
                        <h2><i class="fas fa-edit"></i> Edit Land Record</h2>
                        <a href="?action=view&id=<?php echo $record_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                    
                    <form method="POST" action="" class="land-form" enctype="multipart/form-data">
                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="update_land">
                        <input type="hidden" name="record_id" value="<?php echo $record_id; ?>">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="parcel_no">Parcel Number <span class="required-asterisk">*</span></label>
                                <input type="text" 
                                       id="parcel_no" 
                                       name="parcel_no" 
                                       value="<?php echo htmlspecialchars($land['parcel_no']); ?>" 
                                       required
                                       <?php echo $land['status'] == 'active' ? 'readonly' : ''; ?>
                                       aria-describedby="parcelNoHelp">
                                <span class="form-hint" id="parcelNoHelp">Unique identifier for your land parcel</span>
                            </div>
                            
                            <div class="form-group">
                                <label for="location">Location <span class="required-asterisk">*</span></label>
                                <input type="text" 
                                       id="location" 
                                       name="location" 
                                       value="<?php echo htmlspecialchars($land['location']); ?>" 
                                       required
                                       aria-describedby="locationHelp">
                                <span class="form-hint" id="locationHelp">Physical location or address</span>
                            </div>
                            
                            <div class="form-group">
                                <label for="size">Size (acres) <span class="required-asterisk">*</span></label>
                                <input type="number" 
                                       id="size" 
                                       name="size" 
                                       value="<?php echo $land['size']; ?>" 
                                       required 
                                       step="0.01" 
                                       min="0.01"
                                       aria-describedby="sizeHelp">
                                <span class="form-hint" id="sizeHelp">Land area in acres (minimum 0.01)</span>
                            </div>
                            
                            <div class="form-group">
                                <label for="latitude">Latitude</label>
                                <input type="number" 
                                       id="latitude" 
                                       name="latitude" 
                                       value="<?php echo isset($land['latitude']) ? $land['latitude'] : ''; ?>" 
                                       step="any"
                                       placeholder="e.g., -1.286389"
                                       aria-describedby="coordHelp">
                            </div>
                            
                            <div class="form-group">
                                <label for="longitude">Longitude</label>
                                <input type="number" 
                                       id="longitude" 
                                       name="longitude" 
                                       value="<?php echo isset($land['longitude']) ? $land['longitude'] : ''; ?>" 
                                       step="any"
                                       placeholder="e.g., 36.817223"
                                       aria-describedby="coordHelp">
                                <span class="coordinate-help" id="coordHelp">Optional: For map display</span>
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="description">Description</label>
                                <textarea id="description" 
                                          name="description" 
                                          rows="4"
                                          aria-describedby="descHelp"><?php echo isset($land['description']) ? htmlspecialchars($land['description']) : ''; ?></textarea>
                                <span class="form-hint" id="descHelp">Optional: Boundaries, landmarks, features</span>
                            </div>
                            
                            <div class="form-group">
                                <label for="document">Update Document</label>
                                <input type="file" 
                                       id="document" 
                                       name="document" 
                                       accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                       aria-describedby="fileHelp">
                                <span class="form-hint" id="fileHelp">Max size: 5MB. Allowed: PDF, JPG, PNG, DOC, DOCX</span>
                                <?php if (isset($land['document_path']) && $land['document_path']): ?>
                                <div class="current-document">
                                    <i class="fas fa-paperclip"></i>
                                    <span>Current: <?php echo basename($land['document_path']); ?></span>
                                    <a href="../../<?php echo htmlspecialchars($land['document_path']); ?>" 
                                       target="_blank" class="btn-small">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" id="updateSubmitBtn">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <a href="?action=view&id=<?php echo $record_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
                
            <?php elseif ($action == 'add'): ?>
                <!-- Add Land Form -->
                <div class="form-container fade-in">
                    <div class="form-header">
                        <h2><i class="fas fa-plus-circle"></i> Register New Land</h2>
                        <a href="my-lands.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                    
                    <form method="POST" action="" class="land-form" enctype="multipart/form-data" id="addLandForm">
                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="add_land">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="parcel_no">Parcel Number <span class="required-asterisk">*</span></label>
                                <input type="text" 
                                       id="parcel_no" 
                                       name="parcel_no" 
                                       required 
                                       placeholder="e.g., LR001/2025"
                                       aria-describedby="parcelNoHelp">
                                <span class="form-hint" id="parcelNoHelp">Unique identifier for your land parcel</span>
                            </div>
                            
                            <div class="form-group">
                                <label for="location">Location <span class="required-asterisk">*</span></label>
                                <input type="text" 
                                       id="location" 
                                       name="location" 
                                       required 
                                       placeholder="e.g., Nairobi, Westlands"
                                       aria-describedby="locationHelp">
                                <span class="form-hint" id="locationHelp">Physical location of the land</span>
                            </div>
                            
                            <div class="form-group">
                                <label for="size">Size (acres) <span class="required-asterisk">*</span></label>
                                <input type="number" 
                                       id="size" 
                                       name="size" 
                                       required 
                                       step="0.01" 
                                       min="0.01" 
                                       placeholder="e.g., 0.25"
                                       aria-describedby="sizeHelp">
                                <span class="form-hint" id="sizeHelp">Land area in acres (minimum 0.01)</span>
                            </div>
                            
                            <div class="form-group">
                                <label for="latitude">Latitude</label>
                                <input type="number" 
                                       id="latitude" 
                                       name="latitude" 
                                       step="any"
                                       placeholder="e.g., -1.286389"
                                       aria-describedby="coordHelp">
                            </div>
                            
                            <div class="form-group">
                                <label for="longitude">Longitude</label>
                                <input type="number" 
                                       id="longitude" 
                                       name="longitude" 
                                       step="any"
                                       placeholder="e.g., 36.817223"
                                       aria-describedby="coordHelp">
                                <span class="coordinate-help" id="coordHelp">Optional: For map display</span>
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="description">Description</label>
                                <textarea id="description" 
                                          name="description" 
                                          rows="4" 
                                          placeholder="Describe your land (boundaries, features, landmarks, etc.)"
                                          aria-describedby="descHelp"></textarea>
                                <span class="form-hint" id="descHelp">Optional: Helpful for verification</span>
                            </div>
                            
                            <div class="form-group">
                                <label for="document">Supporting Document</label>
                                <input type="file" 
                                       id="document" 
                                       name="document" 
                                       accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                       aria-describedby="fileHelp">
                                <span class="form-hint" id="fileHelp">Max size: 5MB. Allowed: PDF, JPG, PNG, DOC, DOCX</span>
                            </div>
                        </div>
                        
                        <div class="form-help">
                            <h3><i class="fas fa-info-circle"></i> Important Information</h3>
                            <ul>
                                <li><strong>Verification Required:</strong> All registrations require administrative verification</li>
                                <li><strong>Accuracy:</strong> Ensure all information matches official documents</li>
                                <li><strong>Documents:</strong> Supporting documents speed up verification</li>
                                <li><strong>Notifications:</strong> You'll be notified via email when processed</li>
                                <li><strong>Coordinates:</strong> Optional but recommended for map features</li>
                                <li><strong>Partial Transfers:</strong> You can split your land for partial transfers later</li>
                            </ul>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="fas fa-check"></i> Submit Registration
                            </button>
                            <a href="my-lands.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
                
            <?php else: ?>
                <!-- Land List -->
                <div class="filter-bar">
                    <form method="GET" action="" class="filter-form" id="filterForm">
                        <div class="filter-grid">
                            <div class="filter-group">
                                <div class="input-with-icon">
                                    <i class="fas fa-search"></i>
                                    <input type="text" 
                                           name="search" 
                                           placeholder="Search by parcel no or location..." 
                                           value="<?php echo htmlspecialchars($search); ?>"
                                           aria-label="Search lands">
                                </div>
                            </div>
                            
                            <div class="filter-group">
                                <select name="status" aria-label="Filter by status">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="transferred" <?php echo $status == 'transferred' ? 'selected' : ''; ?>>Transferred</option>
                                    <option value="disputed" <?php echo $status == 'disputed' ? 'selected' : ''; ?>>Disputed</option>
                                    <option value="pending_transfer" <?php echo $status == 'pending_transfer' ? 'selected' : ''; ?>>Pending Transfer</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <button type="submit" class="btn">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <a href="my-lands.php" class="btn btn-secondary">
                                    <i class="fas fa-redo"></i> Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <?php if (!empty($lands)): ?>
                    <div class="lands-grid">
                        <?php foreach ($lands as $row): ?>
                            <div class="land-card card-hover">
                                <div class="land-header">
                                    <div class="land-status status-<?php echo $row['status']; ?>">
                                        <i class="fas fa-circle"></i> <?php echo ucfirst($row['status']); ?>
                                    </div>
                                    <?php if ($row['parent_record_id']): ?>
                                        <span class="split-badge" title="Split from parent parcel">
                                            <i class="fas fa-code-branch"></i> Split
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($row['original_parcel_no']): ?>
                                        <span class="original-badge" title="Original parcel: <?php echo htmlspecialchars($row['original_parcel_no']); ?>">
                                            <i class="fas fa-history"></i> Original
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($row['split_count'] > 0): ?>
                                        <span class="split-count-badge" title="<?php echo $row['split_count']; ?> split(s) from this land">
                                            <i class="fas fa-sitemap"></i> <?php echo $row['split_count']; ?>
                                        </span>
                                    <?php endif; ?>
                                    <div class="land-actions">
                                        <a href="?action=view&id=<?php echo $row['record_id']; ?>" 
                                           class="btn-small" 
                                           title="View details" aria-label="View land details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="land-history.php?id=<?php echo $row['record_id']; ?>" 
                                           class="btn-small" 
                                           title="View history" aria-label="View land history">
                                            <i class="fas fa-history"></i>
                                        </a>
                                        <?php if ($row['status'] == 'pending' || $row['status'] == 'active'): ?>
                                            <a href="?action=edit&id=<?php echo $row['record_id']; ?>" 
                                               class="btn-small" 
                                               title="Edit" aria-label="Edit land">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (isset($row['latitude']) && isset($row['longitude']) && $row['latitude'] && $row['longitude']): ?>
                                            <a href="land-map.php?focus=<?php echo $row['record_id']; ?>" 
                                               class="btn-small" 
                                               title="View on map" aria-label="View on map">
                                                <i class="fas fa-map-marker-alt"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="land-body">
                                    <h3><?php echo htmlspecialchars($row['parcel_no']); ?></h3>
                                    <p class="location">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($row['location']); ?>
                                    </p>
                                    <div class="land-info">
                                        <div class="info-item">
                                            <span class="label"><i class="fas fa-ruler-combined"></i> Size:</span>
                                            <span class="value"><?php echo number_format($row['size'], 2); ?> acres</span>
                                        </div>
                                        <div class="info-item">
                                            <span class="label"><i class="fas fa-calendar-alt"></i> Registered:</span>
                                            <span class="value"><?php echo format_date($row['registered_at']); ?></span>
                                        </div>
                                        <?php if ($row['parent_record_id'] && $row['parent_parcel']): ?>
                                        <div class="info-item">
                                            <span class="label"><i class="fas fa-code-branch"></i> Split From:</span>
                                            <span class="value"><?php echo htmlspecialchars($row['parent_parcel']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (isset($row['latitude']) && isset($row['longitude']) && $row['latitude'] && $row['longitude']): ?>
                                        <div class="info-item">
                                            <span class="label"><i class="fas fa-location-dot"></i> Coordinates:</span>
                                            <span class="value"><?php echo $row['latitude'] . ', ' . $row['longitude']; ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="land-footer">
                                    <?php if ($row['status'] == 'active'): ?>
                                        <div class="action-buttons-small">
                                            <a href="transfer-land.php?land_id=<?php echo $row['record_id']; ?>" 
                                               class="btn btn-small">
                                                <i class="fas fa-exchange-alt"></i> Transfer
                                            </a>
                                            <a href="../documents.php?action=certificate&land_id=<?php echo $row['record_id']; ?>" 
                                               class="btn btn-secondary btn-small">
                                                <i class="fas fa-certificate"></i> Certificate
                                            </a>
                                            <?php if ($row['split_count'] > 0): ?>
                                            <a href="view-splits.php?land_id=<?php echo $row['record_id']; ?>" 
                                               class="btn btn-secondary btn-small">
                                                <i class="fas fa-sitemap"></i> Splits
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif ($row['status'] == 'pending'): ?>
                                        <span class="pending-note">
                                            <i class="fas fa-clock"></i> Awaiting verification
                                        </span>
                                    <?php elseif ($row['status'] == 'pending_transfer'): ?>
                                        <div class="partial-transfer-notice">
                                            <i class="fas fa-exchange-alt"></i> Pending partial transfer
                                            <a href="transfer-status.php?land_id=<?php echo $row['record_id']; ?>" 
                                               class="btn-small" style="margin-left: 10px;">
                                                View Status
                                            </a>
                                        </div>
                                    <?php elseif ($row['status'] == 'disputed'): ?>
                                        <span class="disputed-note">
                                            <i class="fas fa-exclamation-triangle"></i> Requires attention
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <div class="pagination-info">
                                Showing <?php echo (($page - 1) * $limit) + 1; ?> - 
                                <?php echo min($page * $limit, $total_lands); ?> of <?php echo $total_lands; ?> lands
                            </div>
                            <div class="pagination-controls">
                                <?php if ($page > 1): ?>
                                    <a href="?page=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status ? '&status=' . urlencode($status) : ''; ?>" 
                                       class="page-link" aria-label="First page">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                    <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status ? '&status=' . urlencode($status) : ''; ?>" 
                                       class="page-link" aria-label="Previous page">
                                        <i class="fas fa-angle-left"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php 
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1) {
                                    echo '<span class="page-dots">...</span>';
                                }
                                
                                for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status ? '&status=' . urlencode($status) : ''; ?>" 
                                       class="page-link <?php echo $i == $page ? 'active' : ''; ?>" 
                                       aria-label="Page <?php echo $i; ?>" 
                                       <?php echo $i == $page ? 'aria-current="page"' : ''; ?>>
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; 
                                
                                if ($end_page < $total_pages) {
                                    echo '<span class="page-dots">...</span>';
                                }
                                ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status ? '&status=' . urlencode($status) : ''; ?>" 
                                       class="page-link" aria-label="Next page">
                                        <i class="fas fa-angle-right"></i>
                                    </a>
                                    <a href="?page=<?php echo $total_pages; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status ? '&status=' . urlencode($status) : ''; ?>" 
                                       class="page-link" aria-label="Last page">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-landmark"></i>
                        <h3>No Land Records Found</h3>
                        <p><?php echo !empty($search) || !empty($status) ? 'Try adjusting your search criteria.' : 'You haven\'t registered any land parcels yet.'; ?></p>
                        <div class="empty-state-actions">
                            <a href="?action=add" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Register Your First Land
                            </a>
                            <?php if (!empty($search) || !empty($status)): ?>
                                <a href="my-lands.php" class="btn btn-secondary">
                                    <i class="fas fa-redo"></i> Clear Filters
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Original Footer -->
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
                    <p><i class="fas fa-clock"></i> Mon-Fri: 8AM-5PM</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> ArdhiYetu Land Management System. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Keep Original JavaScript with small enhancements -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add fade-in animations to new elements
            const cards = document.querySelectorAll('.land-card, .stat-card, .details-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
                card.classList.add('fade-in');
            });
            
            // Enhanced form validation
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const sizeInput = this.querySelector('input[name="size"]');
                    if (sizeInput && parseFloat(sizeInput.value) <= 0) {
                        e.preventDefault();
                        showToast('Size must be greater than 0 acres', 'error');
                        sizeInput.focus();
                        return false;
                    }
                });
            });
            
            // Toast notification function
            function showToast(message, type = 'info') {
                const toast = document.createElement('div');
                toast.className = `toast toast-${type}`;
                toast.style.cssText = `
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    background: ${type === 'error' ? '#dc3545' : type === 'success' ? '#28a745' : '#2E86AB'};
                    color: white;
                    padding: 1rem 1.5rem;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    z-index: 9999;
                    animation: slideIn 0.3s ease;
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                    max-width: 400px;
                `;
                
                toast.innerHTML = `
                    <i class="fas fa-${type === 'error' ? 'exclamation-circle' : type === 'success' ? 'check-circle' : 'info-circle'}"></i>
                    <span>${message}</span>
                `;
                
                document.body.appendChild(toast);
                
                setTimeout(() => {
                    toast.style.animation = 'slideOut 0.3s ease';
                    setTimeout(() => toast.remove(), 300);
                }, 5000);
                
                // Add CSS for animations
                if (!document.querySelector('#toast-animations')) {
                    const style = document.createElement('style');
                    style.id = 'toast-animations';
                    style.textContent = `
                        @keyframes slideIn {
                            from { transform: translateX(100%); opacity: 0; }
                            to { transform: translateX(0); opacity: 1; }
                        }
                        @keyframes slideOut {
                            from { transform: translateX(0); opacity: 1; }
                            to { transform: translateX(100%); opacity: 0; }
                        }
                    `;
                    document.head.appendChild(style);
                }
            }
        });
    </script>
</body>
</html>