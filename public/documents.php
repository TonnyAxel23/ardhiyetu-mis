<?php
// File: public/documents.php
// Adjust the path based on where this file is located
require_once __DIR__ . '/../includes/init.php';

// Require login
require_login();

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$document_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$land_id = isset($_GET['land_id']) ? intval($_GET['land_id']) : 0;

// Get user's active lands for certificate generation
$lands_sql = "SELECT record_id, parcel_no, location, size FROM land_records 
              WHERE owner_id = ? AND status = 'active' 
              ORDER BY parcel_no";
$lands_stmt = mysqli_prepare($conn, $lands_sql);
mysqli_stmt_bind_param($lands_stmt, "i", $user_id);
mysqli_stmt_execute($lands_stmt);
$lands_result = mysqli_stmt_get_result($lands_stmt);
$active_lands = [];
while ($land = mysqli_fetch_assoc($lands_result)) {
    $active_lands[] = $land;
}
mysqli_stmt_close($lands_stmt);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        flash_message('error', 'Invalid form submission. Please try again.');
        redirect('documents.php');
    }
    
    $action = $_POST['action'];
    
    if ($action == 'generate_certificate') {
        $land_id = intval($_POST['land_id']);
        $certificate_type = sanitize_input($_POST['certificate_type']);
        $purpose = sanitize_input($_POST['purpose']);
        
        // Validate
        if (empty($land_id) || empty($certificate_type)) {
            flash_message('error', 'Please fill all required fields.');
        } else {
            // Check if user owns this land
            $check_sql = "SELECT l.*, u.name as owner_name, u.id_number 
                         FROM land_records l 
                         JOIN users u ON l.owner_id = u.user_id 
                         WHERE l.record_id = ? AND l.owner_id = ? AND l.status = 'active'";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "ii", $land_id, $user_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if (mysqli_num_rows($check_result) === 0) {
                flash_message('error', 'Land record not found or not eligible for certificate generation.');
                mysqli_stmt_close($check_stmt);
            } else {
                $land_data = mysqli_fetch_assoc($check_result); // FIXED: Changed from $check_stmt to $check_result
                mysqli_stmt_close($check_stmt);
                
                // Generate unique document number
                $doc_number = generate_unique_document_number($conn, $land_id);
                
                // Generate certificate data
                $certificate_data = [
                    'document_number' => $doc_number,
                    'certificate_type' => $certificate_type,
                    'purpose' => $purpose,
                    'generated_date' => date('Y-m-d H:i:s'),
                    'valid_until' => date('Y-m-d', strtotime('+30 days')),
                    'land_data' => $land_data,
                    'user_data' => [
                        'name' => $user_name,
                        'id_number' => $land_data['id_number']
                    ]
                ];
                
                // First check if documents table exists
                $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'documents'");
                if (mysqli_num_rows($table_check) === 0) {
                    // Create documents table if it doesn't exist
                    $create_table_sql = "CREATE TABLE IF NOT EXISTS documents (
                        document_id INT PRIMARY KEY AUTO_INCREMENT,
                        user_id INT NOT NULL,
                        land_id INT NULL,
                        document_type VARCHAR(100) NOT NULL,
                        document_number VARCHAR(100) UNIQUE NOT NULL,
                        purpose TEXT NULL,
                        generated_data LONGTEXT NOT NULL,
                        status ENUM('pending', 'generated', 'verified', 'expired', 'revoked') DEFAULT 'generated',
                        format VARCHAR(50) DEFAULT 'pdf',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_user_id (user_id),
                        INDEX idx_document_number (document_number),
                        INDEX idx_created_at (created_at)
                    )";
                    
                    if (!mysqli_query($conn, $create_table_sql)) {
                        flash_message('error', 'Failed to create documents table: ' . mysqli_error($conn));
                        redirect('documents.php');
                    }
                }
                
                // Save document record
                $insert_sql = "INSERT INTO documents (user_id, land_id, document_type, document_number, 
                              purpose, generated_data, status) 
                              VALUES (?, ?, ?, ?, ?, ?, 'generated')";
                $insert_stmt = mysqli_prepare($conn, $insert_sql);
                
                if ($insert_stmt) {
                    $json_data = json_encode($certificate_data);
                    mysqli_stmt_bind_param($insert_stmt, "iissss", 
                        $user_id, $land_id, $certificate_type, $doc_number, $purpose, $json_data);
                    
                    if (mysqli_stmt_execute($insert_stmt)) {
                        $document_id = mysqli_insert_id($conn);
                        
                        // Log activity if function exists
                        if (function_exists('log_activity')) {
                            log_activity($user_id, 'certificate_generated', 
                                "Generated $certificate_type for parcel: " . $land_data['parcel_no']);
                        }
                        
                        flash_message('success', 'Certificate generated successfully.');
                        redirect("documents.php?action=view&id=$document_id");
                    } else {
                        // Handle duplicate document number error
                        if (mysqli_errno($conn) == 1062) { // Duplicate entry error code
                            flash_message('error', 'Failed to generate certificate: Document number already exists. Please try again.');
                        } else {
                            flash_message('error', 'Failed to generate certificate: ' . mysqli_error($conn));
                        }
                    }
                    mysqli_stmt_close($insert_stmt);
                } else {
                    flash_message('error', 'Database error: ' . mysqli_error($conn));
                }
            }
        }
    } elseif ($action == 'generate_report') {
        $report_type = sanitize_input($_POST['report_type']);
        $start_date = sanitize_input($_POST['start_date']);
        $end_date = sanitize_input($_POST['end_date']);
        $format = sanitize_input($_POST['format']);
        
        // Validate
        if (empty($report_type) || empty($start_date) || empty($end_date)) {
            flash_message('error', 'Please fill all required fields.');
        } else {
            // Generate report data based on type
            $report_data = generate_report_data($user_id, $report_type, $start_date, $end_date);
            
            if ($report_data) {
                // Generate unique report number
                $report_number = generate_unique_report_number($conn);
                
                // Check if documents table exists
                $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'documents'");
                if (mysqli_num_rows($table_check) === 0) {
                    // Create documents table if it doesn't exist
                    $create_table_sql = "CREATE TABLE IF NOT EXISTS documents (
                        document_id INT PRIMARY KEY AUTO_INCREMENT,
                        user_id INT NOT NULL,
                        land_id INT NULL,
                        document_type VARCHAR(100) NOT NULL,
                        document_number VARCHAR(100) UNIQUE NOT NULL,
                        purpose TEXT NULL,
                        generated_data LONGTEXT NOT NULL,
                        status ENUM('pending', 'generated', 'verified', 'expired', 'revoked') DEFAULT 'generated',
                        format VARCHAR(50) DEFAULT 'pdf',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_user_id (user_id),
                        INDEX idx_document_number (document_number),
                        INDEX idx_created_at (created_at)
                    )";
                    
                    if (!mysqli_query($conn, $create_table_sql)) {
                        flash_message('error', 'Failed to create documents table: ' . mysqli_error($conn));
                        redirect('documents.php');
                    }
                }
                
                // Save report record
                $insert_sql = "INSERT INTO documents (user_id, document_type, document_number, 
                              purpose, generated_data, status, format) 
                              VALUES (?, ?, ?, ?, ?, 'generated', ?)";
                $insert_stmt = mysqli_prepare($conn, $insert_sql);
                
                if ($insert_stmt) {
                    $purpose = "$report_type report from $start_date to $end_date";
                    $json_data = json_encode($report_data);
                    mysqli_stmt_bind_param($insert_stmt, "isssss", 
                        $user_id, $report_type, $report_number, $purpose, $json_data, $format);
                    
                    if (mysqli_stmt_execute($insert_stmt)) {
                        $document_id = mysqli_insert_id($conn);
                        
                        // Log activity if function exists
                        if (function_exists('log_activity')) {
                            log_activity($user_id, 'report_generated', 
                                "Generated $report_type report");
                        }
                        
                        flash_message('success', 'Report generated successfully.');
                        redirect("documents.php?action=view&id=$document_id");
                    } else {
                        // Handle duplicate document number error
                        if (mysqli_errno($conn) == 1062) { // Duplicate entry error code
                            flash_message('error', 'Failed to generate report: Document number already exists. Please try again.');
                        } else {
                            flash_message('error', 'Failed to generate report: ' . mysqli_error($conn));
                        }
                    }
                    mysqli_stmt_close($insert_stmt);
                } else {
                    flash_message('error', 'Database error: ' . mysqli_error($conn));
                }
            } else {
                flash_message('error', 'No data found for the selected criteria.');
            }
        }
    }
}

// Function to generate unique document number for certificates
function generate_unique_document_number($conn, $land_id) {
    $base_number = 'ARDHI/' . date('Y/m') . '/' . str_pad($land_id, 6, '0', STR_PAD_LEFT);
    $counter = 1;
    $final_number = $base_number;
    
    // Check if documents table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'documents'");
    if (mysqli_num_rows($table_check) > 0) {
        // Check if base number exists
        $check_sql = "SELECT COUNT(*) as count FROM documents WHERE document_number = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        
        while (true) {
            mysqli_stmt_bind_param($check_stmt, "s", $final_number);
            mysqli_stmt_execute($check_stmt);
            $result = mysqli_stmt_get_result($check_stmt);
            $row = mysqli_fetch_assoc($result);
            mysqli_free_result($result);
            
            if ($row['count'] == 0) {
                // Number is unique
                break;
            } else {
                // Number exists, append counter
                $final_number = $base_number . '-' . $counter;
                $counter++;
            }
        }
        mysqli_stmt_close($check_stmt);
    }
    
    mysqli_free_result($table_check);
    return $final_number;
}

// Function to generate unique report number
function generate_unique_report_number($conn) {
    $base_number = 'REPORT/' . date('Y/m') . '/' . uniqid();
    $counter = 1;
    $final_number = $base_number;
    
    // Check if documents table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'documents'");
    if (mysqli_num_rows($table_check) > 0) {
        // Check if base number exists
        $check_sql = "SELECT COUNT(*) as count FROM documents WHERE document_number = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        
        while (true) {
            mysqli_stmt_bind_param($check_stmt, "s", $final_number);
            mysqli_stmt_execute($check_stmt);
            $result = mysqli_stmt_get_result($check_stmt);
            $row = mysqli_fetch_assoc($result);
            mysqli_free_result($result);
            
            if ($row['count'] == 0) {
                // Number is unique
                break;
            } else {
                // Number exists, append counter
                $final_number = 'REPORT/' . date('Y/m') . '/' . uniqid() . '-' . $counter;
                $counter++;
            }
        }
        mysqli_stmt_close($check_stmt);
    }
    
    mysqli_free_result($table_check);
    return $final_number;
}

// Function to generate report data
function generate_report_data($user_id, $report_type, $start_date, $end_date) {
    global $conn;
    
    $report_data = [
        'report_type' => $report_type,
        'period' => "$start_date to $end_date",
        'generated_date' => date('Y-m-d H:i:s'),
        'data' => []
    ];
    
    switch ($report_type) {
        case 'land_summary':
            $sql = "SELECT parcel_no, location, size, status, registered_at 
                   FROM land_records 
                   WHERE owner_id = ? 
                   AND DATE(registered_at) BETWEEN ? AND ?
                   ORDER BY registered_at DESC";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "iss", $user_id, $start_date, $end_date);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            while ($row = mysqli_fetch_assoc($result)) {
                $report_data['data'][] = $row;
            }
            mysqli_stmt_close($stmt);
            mysqli_free_result($result);
            break;
            
        case 'transfer_history':
            // Check if land_transfers table exists
            $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'land_transfers'");
            if (mysqli_num_rows($check_table) > 0) {
                $sql = "SELECT t.*, l.parcel_no, l.location 
                       FROM land_transfers t
                       JOIN land_records l ON t.land_id = l.record_id
                       WHERE (t.from_user_id = ? OR t.to_user_id = ?)
                       AND DATE(t.transfer_date) BETWEEN ? AND ?
                       ORDER BY t.transfer_date DESC";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "iiss", $user_id, $user_id, $start_date, $end_date);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                while ($row = mysqli_fetch_assoc($result)) {
                    $report_data['data'][] = $row;
                }
                mysqli_stmt_close($stmt);
                mysqli_free_result($result);
            }
            mysqli_free_result($check_table);
            break;
            
        case 'activity_log':
            // Check if activity_logs table exists
            $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'activity_logs'");
            if (mysqli_num_rows($check_table) > 0) {
                $sql = "SELECT * FROM activity_logs 
                       WHERE user_id = ? 
                       AND DATE(created_at) BETWEEN ? AND ?
                       ORDER BY created_at DESC";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "iss", $user_id, $start_date, $end_date);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                while ($row = mysqli_fetch_assoc($result)) {
                    $report_data['data'][] = $row;
                }
                mysqli_stmt_close($stmt);
                mysqli_free_result($result);
            }
            mysqli_free_result($check_table);
            break;
    }
    
    return !empty($report_data['data']) ? $report_data : false;
}

// Get document details for view
$document = null;
if ($document_id > 0) {
    // Check if documents table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'documents'");
    if (mysqli_num_rows($table_check) > 0) {
        $doc_sql = "SELECT d.*, l.parcel_no, l.location 
                   FROM documents d 
                   LEFT JOIN land_records l ON d.land_id = l.record_id 
                   WHERE d.document_id = ? AND d.user_id = ?";
        $doc_stmt = mysqli_prepare($conn, $doc_sql);
        mysqli_stmt_bind_param($doc_stmt, "ii", $document_id, $user_id);
        mysqli_stmt_execute($doc_stmt);
        $doc_result = mysqli_stmt_get_result($doc_stmt);
        $document = mysqli_fetch_assoc($doc_result);
        mysqli_stmt_close($doc_stmt);
        
        if ($document) {
            $document['generated_data'] = json_decode($document['generated_data'], true);
        }
        
        mysqli_free_result($table_check);
        if ($doc_result) {
            mysqli_free_result($doc_result);
        }
    }
}

// Get user's documents for listing
$documents_result = false;
$documents_stmt = null;

// Check if documents table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'documents'");
if (mysqli_num_rows($table_check) > 0) {
    $documents_sql = "SELECT d.*, l.parcel_no, l.location 
                     FROM documents d 
                     LEFT JOIN land_records l ON d.land_id = l.record_id 
                     WHERE d.user_id = ? 
                     ORDER BY d.created_at DESC 
                     LIMIT 20";
    $documents_stmt = mysqli_prepare($conn, $documents_sql);
    mysqli_stmt_bind_param($documents_stmt, "i", $user_id);
    mysqli_stmt_execute($documents_stmt);
    $documents_result = mysqli_stmt_get_result($documents_stmt);
}
mysqli_free_result($table_check);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documents & Certificates - ArdhiYetu</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .documents-container {
            padding: 20px 0;
            min-height: 1000px;
        }
        
        .documents-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .documents-header h1 {
            margin: 0 0 10px 0;
            font-size: 2.5rem;
        }
        
        .documents-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .doc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .doc-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            border: 1px solid #e9ecef;
        }
        
        .doc-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .doc-icon {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .doc-card h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.5rem;
        }
        
        .doc-card p {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .doc-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .document-details {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .details-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .doc-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .info-item label {
            display: block;
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .info-item span {
            color: #333;
            font-size: 1.1rem;
        }
        
        .document-preview {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 30px;
            margin: 30px 0;
            text-align: center;
        }
        
        .preview-placeholder {
            padding: 50px;
            background: white;
            border-radius: 5px;
            border: 2px dashed #ddd;
        }
        
        .documents-list {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .documents-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .documents-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #dee2e6;
        }
        
        .documents-table td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        
        .documents-table tr:hover {
            background: #f8f9fa;
        }
        
        .doc-type-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-certificate {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-report {
            background: #cce5ff;
            color: #004085;
        }
        
        .badge-other {
            background: #fff3cd;
            color: #856404;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-icon {
            padding: 8px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-view {
            background: #4CAF50;
            color: white;
        }
        
        .btn-download {
            background: #2196F3;
            color: white;
        }
        
        .btn-print {
            background: #ff9800;
            color: white;
        }
        
        .btn-icon:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .form-container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            margin: 0 0 10px 0;
            color: #555;
        }
        
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            color: #555;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 20px 0;
            color: #667eea;
        }
        
        @media (max-width: 768px) {
            .doc-grid {
                grid-template-columns: 1fr;
            }
            
            .documents-header {
                padding: 20px;
            }
            
            .documents-header h1 {
                font-size: 2rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .details-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .action-buttons {
                flex-wrap: wrap;
            }
            
            .quick-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="../index.php" class="logo">
                <i class="fas fa-landmark"></i> ArdhiYetu
            </a>
            <div class="nav-links">
                <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="my-lands.php"><i class="fas fa-landmark"></i> My Lands</a>
                <a href="land-map.php"><i class="fas fa-map"></i> Land Map</a>
                <a href="transfer-land.php"><i class="fas fa-exchange-alt"></i> Transfer</a>
                <a href="documents.php" class="active"><i class="fas fa-file-alt"></i> Documents</a>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <?php if (is_admin()): ?>
                    <a href="../admin/index.php" class="btn"><i class="fas fa-user-shield"></i> Admin</a>
                <?php endif; ?>
                <a href="logout.php" class="btn logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
            <button class="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <main class="documents-container">
        <div class="container">
            <div class="documents-header">
                <h1><i class="fas fa-file-certificate"></i> Documents & Certificates</h1>
                <p>Generate and manage your land-related documents, certificates, and reports</p>
            </div>

            <?php 
            // Display flash messages if function exists
            if (function_exists('display_flash_message')) {
                display_flash_message();
            }
            ?>
            
            <?php if ($action == 'generate_certificate'): ?>
                <!-- Generate Certificate Form -->
                <div class="form-container">
                    <div class="form-header">
                        <h2><i class="fas fa-certificate"></i> Generate Certificate</h2>
                        <a href="documents.php" class="btn secondary">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                    
                    <form method="POST" action="" class="certificate-form">
                        <input type="hidden" name="action" value="generate_certificate">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="land_id">Select Land Parcel *</label>
                                <select id="land_id" name="land_id" class="form-control" required>
                                    <option value="">Select a land parcel</option>
                                    <?php foreach ($active_lands as $land): ?>
                                        <option value="<?php echo $land['record_id']; ?>" 
                                            <?php echo $land_id == $land['record_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($land['parcel_no']); ?> - 
                                            <?php echo htmlspecialchars($land['location']); ?> 
                                            (<?php echo number_format($land['size'], 2); ?> acres)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small>Only active land parcels are eligible for certificates</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="certificate_type">Certificate Type *</label>
                                <select id="certificate_type" name="certificate_type" class="form-control" required>
                                    <option value="">Select certificate type</option>
                                    <option value="ownership_certificate">Ownership Certificate</option>
                                    <option value="no_objection_certificate">No Objection Certificate</option>
                                    <option value="search_certificate">Search Certificate</option>
                                    <option value="title_deed">Title Deed</option>
                                    <option value="land_clearance">Land Clearance Certificate</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="purpose">Purpose of Certificate</label>
                                <textarea id="purpose" name="purpose" class="form-control" rows="3" 
                                          placeholder="e.g., For bank loan application, property transfer, legal proceedings, etc."></textarea>
                            </div>
                        </div>
                        
                        <div class="form-info">
                            <h3><i class="fas fa-info-circle"></i> Certificate Information</h3>
                            <ul>
                                <li>Certificates are generated with official ArdhiYetu letterhead</li>
                                <li>All certificates include a unique document number for verification</li>
                                <li>Generated certificates are valid for 30 days from date of issue</li>
                                <li>Certificates can be downloaded as PDF for official use</li>
                                <li>Certificate authenticity can be verified online using the document number</li>
                            </ul>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn">
                                <i class="fas fa-file-certificate"></i> Generate Certificate
                            </button>
                            <a href="documents.php" class="btn secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
                
            <?php elseif ($action == 'generate_report'): ?>
                <!-- Generate Report Form -->
                <div class="form-container">
                    <div class="form-header">
                        <h2><i class="fas fa-chart-bar"></i> Generate Report</h2>
                        <a href="documents.php" class="btn secondary">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                    
                    <form method="POST" action="" class="report-form">
                        <input type="hidden" name="action" value="generate_report">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="report_type">Report Type *</label>
                                <select id="report_type" name="report_type" class="form-control" required>
                                    <option value="">Select report type</option>
                                    <option value="land_summary">Land Summary Report</option>
                                    <option value="transfer_history">Transfer History Report</option>
                                    <option value="activity_log">Activity Log Report</option>
                                    <option value="ownership_history">Ownership History</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="start_date">Start Date *</label>
                                <input type="date" id="start_date" name="start_date" class="form-control" required 
                                       value="<?php echo date('Y-m-01'); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="end_date">End Date *</label>
                                <input type="date" id="end_date" name="end_date" class="form-control" required 
                                       value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="format">Output Format</label>
                                <select id="format" name="format" class="form-control">
                                    <option value="pdf">PDF Document</option>
                                    <option value="excel">Excel Spreadsheet</option>
                                    <option value="csv">CSV File</option>
                                    <option value="html">Web Page</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-info">
                            <h3><i class="fas fa-info-circle"></i> Report Information</h3>
                            <ul>
                                <li>Reports include comprehensive data analysis for the selected period</li>
                                <li>All reports are generated with timestamp and unique reference number</li>
                                <li>You can download reports in multiple formats for different uses</li>
                                <li>Reports can be used for tax purposes, audits, and legal documentation</li>
                                <li>Historical reports can be accessed anytime from your documents list</li>
                            </ul>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn">
                                <i class="fas fa-file-export"></i> Generate Report
                            </button>
                            <a href="documents.php" class="btn secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
                
            <?php elseif ($action == 'view' && $document): ?>
                <!-- View Document Details -->
                <div class="document-details">
                    <div class="details-header">
                        <div>
                            <h2>
                                <?php if (strpos($document['document_type'], 'certificate') !== false): ?>
                                    <i class="fas fa-certificate"></i>
                                <?php elseif (strpos($document['document_type'], 'report') !== false): ?>
                                    <i class="fas fa-chart-bar"></i>
                                <?php else: ?>
                                    <i class="fas fa-file-alt"></i>
                                <?php endif; ?>
                                <?php echo ucwords(str_replace('_', ' ', $document['document_type'])); ?>
                            </h2>
                            <p class="text-muted">Document Number: <?php echo $document['document_number']; ?></p>
                        </div>
                        <div class="action-buttons">
                            <button onclick="printDocument()" class="btn">
                                <i class="fas fa-print"></i> Print
                            </button>
                            <button onclick="downloadDocument()" class="btn secondary">
                                <i class="fas fa-download"></i> Download
                            </button>
                            <a href="documents.php" class="btn secondary">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>
                    
                    <div class="doc-info-grid">
                        <div class="info-item">
                            <label>Document Type</label>
                            <span><?php echo ucwords(str_replace('_', ' ', $document['document_type'])); ?></span>
                        </div>
                        
                        <div class="info-item">
                            <label>Document Number</label>
                            <span><?php echo $document['document_number']; ?></span>
                        </div>
                        
                        <div class="info-item">
                            <label>Status</label>
                            <span class="status-badge status-<?php echo $document['status']; ?>">
                                <?php echo ucfirst($document['status']); ?>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <label>Generated Date</label>
                            <span><?php echo format_date($document['created_at']); ?></span>
                        </div>
                        
                        <?php if ($document['land_id']): ?>
                        <div class="info-item">
                            <label>Land Parcel</label>
                            <span>
                                <?php echo htmlspecialchars($document['parcel_no']); ?> - 
                                <?php echo htmlspecialchars($document['location']); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($document['purpose']): ?>
                        <div class="info-item">
                            <label>Purpose</label>
                            <span><?php echo htmlspecialchars($document['purpose']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="document-preview">
                        <h3>Document Preview</h3>
                        <div class="preview-placeholder" id="document-preview">
                            <!-- Document content will be rendered here -->
                            <p>Loading document preview...</p>
                        </div>
                    </div>
                    
                    <div class="document-actions">
                        <h3>Document Actions</h3>
                        <div class="action-buttons">
                            <button onclick="printDocument()" class="btn">
                                <i class="fas fa-print"></i> Print Document
                            </button>
                            <button onclick="downloadDocument()" class="btn secondary">
                                <i class="fas fa-file-pdf"></i> Download as PDF
                            </button>
                            <button onclick="shareDocument()" class="btn secondary">
                                <i class="fas fa-share-alt"></i> Share Document
                            </button>
                            <button onclick="verifyDocument()" class="btn">
                                <i class="fas fa-check-circle"></i> Verify Authenticity
                            </button>
                        </div>
                    </div>
                </div>
                
                <script>
                    // Render document preview
                    document.addEventListener('DOMContentLoaded', function() {
                        const documentData = <?php echo json_encode($document['generated_data']); ?>;
                        renderDocumentPreview(documentData);
                    });
                    
                    function renderDocumentPreview(data) {
                        const preview = document.getElementById('document-preview');
                        let html = '';
                        
                        html += `
                            <div style="text-align: left; font-family: Arial, sans-serif;">
                                <div style="border-bottom: 3px solid #667eea; padding-bottom: 20px; margin-bottom: 30px;">
                                    <h1 style="color: #667eea; margin: 0;">ArdhiYetu</h1>
                                    <p style="color: #666; margin: 5px 0;">Digital Land Administration System</p>
                                    <p style="color: #999; font-size: 0.9rem; margin: 0;">Official Document</p>
                                </div>
                                
                                <div style="margin-bottom: 30px;">
                                    <h2 style="color: #333; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                                        ${data.certificate_type ? data.certificate_type.replace(/_/g, ' ').toUpperCase() : 'DOCUMENT'}
                                    </h2>
                                    <p style="font-size: 1.1rem; color: #555;">
                                        Document Number: <strong>${data.document_number}</strong>
                                    </p>
                                </div>
                        `;
                        
                        if (data.land_data) {
                            html += `
                                <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 30px;">
                                    <h3 style="color: #555; margin-top: 0;">Land Information</h3>
                                    <table style="width: 100%; border-collapse: collapse;">
                                        <tr>
                                            <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Parcel Number:</strong></td>
                                            <td style="padding: 8px 0; border-bottom: 1px solid #eee;">${data.land_data.parcel_no}</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Location:</strong></td>
                                            <td style="padding: 8px 0; border-bottom: 1px solid #eee;">${data.land_data.location}</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Size:</strong></td>
                                            <td style="padding: 8px 0; border-bottom: 1px solid #eee;">${parseFloat(data.land_data.size).toFixed(2)} acres</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 8px 0;"><strong>Owner:</strong></td>
                                            <td style="padding: 8px 0;">${data.user_data.name}</td>
                                        </tr>
                                    </table>
                                </div>
                            `;
                        }
                        
                        if (data.data && Array.isArray(data.data)) {
                            html += `
                                <div style="margin-bottom: 30px;">
                                    <h3 style="color: #555;">Report Data</h3>
                                    <p>Period: ${data.period}</p>
                                    <p>Total Records: ${data.data.length}</p>
                                </div>
                            `;
                        }
                        
                        html += `
                                <div style="margin-top: 50px; padding-top: 20px; border-top: 1px solid #eee;">
                                    <div style="float: right; text-align: right;">
                                        <p><strong>Generated Date:</strong> ${data.generated_date}</p>
                                        ${data.valid_until ? `<p><strong>Valid Until:</strong> ${data.valid_until}</p>` : ''}
                                    </div>
                                    <div style="clear: both;"></div>
                                    <p style="font-size: 0.9rem; color: #999; margin-top: 30px;">
                                        This is an electronically generated document. It can be verified online at the ArdhiYetu portal.
                                        Document ID: ${data.document_number}
                                    </p>
                                </div>
                            </div>
                        `;
                        
                        preview.innerHTML = html;
                    }
                    
                    function printDocument() {
                        window.print();
                    }
                    
                    function downloadDocument() {
                        // In a real implementation, this would generate and download a PDF
                        alert('PDF download functionality would be implemented here');
                        // For now, we'll create a simple PDF download
                        const docNumber = '<?php echo $document['document_number']; ?>';
                        const link = document.createElement('a');
                        link.href = 'data:application/pdf;base64,' + btoa('PDF content for ' + docNumber);
                        link.download = docNumber + '.pdf';
                        link.click();
                    }
                    
                    function shareDocument() {
                        if (navigator.share) {
                            navigator.share({
                                title: 'ArdhiYetu Document',
                                text: 'Check out this document from ArdhiYetu',
                                url: window.location.href
                            });
                        } else {
                            alert('Share this URL: ' + window.location.href);
                        }
                    }
                    
                    function verifyDocument() {
                        const docNumber = '<?php echo $document['document_number']; ?>';
                        alert('Document verification would check: ' + docNumber + '\n\nIn a real system, this would redirect to a verification page.');
                    }
                </script>
                
            <?php else: ?>
                <!-- Main Documents Dashboard -->
                <div class="doc-grid">
                    <div class="doc-card">
                        <div class="doc-icon">
                            <i class="fas fa-certificate"></i>
                        </div>
                        <h3>Generate Certificate</h3>
                        <p>Create official certificates for your land parcels including ownership certificates, title deeds, and clearance certificates.</p>
                        <div class="doc-actions">
                            <a href="?action=generate_certificate" class="btn">
                                <i class="fas fa-plus"></i> Generate
                            </a>
                            <a href="#certificates-list" class="btn secondary">
                                <i class="fas fa-list"></i> View All
                            </a>
                        </div>
                    </div>
                    
                    <div class="doc-card">
                        <div class="doc-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <h3>Generate Report</h3>
                        <p>Create detailed reports about your land activities, transfer history, ownership records, and activity logs.</p>
                        <div class="doc-actions">
                            <a href="?action=generate_report" class="btn">
                                <i class="fas fa-plus"></i> Generate
                            </a>
                            <a href="#reports-list" class="btn secondary">
                                <i class="fas fa-list"></i> View All
                            </a>
                        </div>
                    </div>
                    
                    <div class="doc-card">
                        <div class="doc-icon">
                            <i class="fas fa-file-contract"></i>
                        </div>
                        <h3>Legal Documents</h3>
                        <p>Access standard legal documents, templates, and forms for land transactions and agreements.</p>
                        <div class="doc-actions">
                            <a href="legal-documents.php" class="btn">
                                <i class="fas fa-eye"></i> Browse
                            </a>
                            <a href="templates.php" class="btn secondary">
                                <i class="fas fa-download"></i> Templates
                            </a>
                        </div>
                    </div>
                    
                    <div class="doc-card">
                        <div class="doc-icon">
                            <i class="fas fa-archive"></i>
                        </div>
                        <h3>Document Archive</h3>
                        <p>Access your complete document history, archived certificates, and historical reports.</p>
                        <div class="doc-actions">
                            <a href="archive.php" class="btn">
                                <i class="fas fa-archive"></i> Open Archive
                            </a>
                            <a href="search-documents.php" class="btn secondary">
                                <i class="fas fa-search"></i> Search
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Documents List -->
                <div class="documents-list" id="recent-documents">
                    <div class="list-header">
                        <h2><i class="fas fa-history"></i> Recent Documents</h2>
                        <div class="doc-actions">
                            <a href="?action=generate_certificate" class="btn small">
                                <i class="fas fa-certificate"></i> New Certificate
                            </a>
                            <a href="?action=generate_report" class="btn small secondary">
                                <i class="fas fa-chart-bar"></i> New Report
                            </a>
                        </div>
                    </div>
                    
                    <?php if ($documents_result && mysqli_num_rows($documents_result) > 0): ?>
                        <div class="table-responsive">
                            <table class="documents-table">
                                <thead>
                                    <tr>
                                        <th>Document Number</th>
                                        <th>Type</th>
                                        <th>Land Parcel</th>
                                        <th>Purpose</th>
                                        <th>Generated</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($doc = mysqli_fetch_assoc($documents_result)): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo $doc['document_number']; ?></strong>
                                            </td>
                                            <td>
                                                <?php 
                                                $doc_type_class = 'badge-other';
                                                if (strpos($doc['document_type'], 'certificate') !== false) {
                                                    $doc_type_class = 'badge-certificate';
                                                } elseif (strpos($doc['document_type'], 'report') !== false) {
                                                    $doc_type_class = 'badge-report';
                                                }
                                                ?>
                                                <span class="doc-type-badge <?php echo $doc_type_class; ?>">
                                                    <?php echo ucfirst($doc['document_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($doc['parcel_no']): ?>
                                                    <?php echo htmlspecialchars($doc['parcel_no']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars(substr($doc['purpose'], 0, 50)); ?>
                                                <?php if (strlen($doc['purpose']) > 50): ?>...<?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo date('Y-m-d', strtotime($doc['created_at'])); ?>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo $doc['status']; ?>">
                                                    <?php echo ucfirst($doc['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="?action=view&id=<?php echo $doc['document_id']; ?>" 
                                                       class="btn-icon btn-view" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <button onclick="downloadDoc(<?php echo $doc['document_id']; ?>)" 
                                                            class="btn-icon btn-download" title="Download">
                                                        <i class="fas fa-download"></i>
                                                    </button>
                                                    <button onclick="printDoc(<?php echo $doc['document_id']; ?>)" 
                                                            class="btn-icon btn-print" title="Print">
                                                        <i class="fas fa-print"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="text-center" style="margin-top: 20px;">
                            <a href="all-documents.php" class="btn secondary">
                                <i class="fas fa-list"></i> View All Documents
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <h3>No Documents Found</h3>
                            <p>You haven't generated any documents yet.</p>
                            <div class="doc-actions" style="margin-top: 20px;">
                                <a href="?action=generate_certificate" class="btn">
                                    <i class="fas fa-certificate"></i> Create Your First Certificate
                                </a>
                                <a href="?action=generate_report" class="btn secondary">
                                    <i class="fas fa-chart-bar"></i> Generate a Report
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php 
                    if ($documents_stmt) {
                        mysqli_stmt_close($documents_stmt);
                    }
                    if ($documents_result) {
                        mysqli_free_result($documents_result);
                    }
                    ?>
                </div>
                
                <!-- Quick Stats -->
                <div class="quick-stats">
                    <div class="stat-card">
                        <h3><i class="fas fa-certificate"></i> Certificates Generated</h3>
                        <div class="stat-number">
                            <?php 
                            $cert_count = 0;
                            $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'documents'");
                            if (mysqli_num_rows($table_check) > 0) {
                                $cert_count_sql = "SELECT COUNT(*) as count FROM documents 
                                                  WHERE user_id = ? AND document_type LIKE '%certificate%'";
                                $cert_stmt = mysqli_prepare($conn, $cert_count_sql);
                                mysqli_stmt_bind_param($cert_stmt, "i", $user_id);
                                mysqli_stmt_execute($cert_stmt);
                                $cert_result = mysqli_stmt_get_result($cert_stmt);
                                $cert_count = mysqli_fetch_assoc($cert_result)['count'] ?? 0;
                                if ($cert_stmt) mysqli_stmt_close($cert_stmt);
                                if ($cert_result) mysqli_free_result($cert_result);
                            }
                            mysqli_free_result($table_check);
                            echo $cert_count;
                            ?>
                        </div>
                        <p>Official certificates created</p>
                    </div>
                    
                    <div class="stat-card">
                        <h3><i class="fas fa-chart-bar"></i> Reports Generated</h3>
                        <div class="stat-number">
                            <?php 
                            $report_count = 0;
                            $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'documents'");
                            if (mysqli_num_rows($table_check) > 0) {
                                $report_count_sql = "SELECT COUNT(*) as count FROM documents 
                                                    WHERE user_id = ? AND document_type LIKE '%report%'";
                                $report_stmt = mysqli_prepare($conn, $report_count_sql);
                                mysqli_stmt_bind_param($report_stmt, "i", $user_id);
                                mysqli_stmt_execute($report_stmt);
                                $report_result = mysqli_stmt_get_result($report_stmt);
                                $report_count = mysqli_fetch_assoc($report_result)['count'] ?? 0;
                                if ($report_stmt) mysqli_stmt_close($report_stmt);
                                if ($report_result) mysqli_free_result($report_result);
                            }
                            mysqli_free_result($table_check);
                            echo $report_count;
                            ?>
                        </div>
                        <p>Analytical reports created</p>
                    </div>
                    
                    <div class="stat-card">
                        <h3><i class="fas fa-file-pdf"></i> Documents This Month</h3>
                        <div class="stat-number">
                            <?php 
                            $month_count = 0;
                            $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'documents'");
                            if (mysqli_num_rows($table_check) > 0) {
                                $month_count_sql = "SELECT COUNT(*) as count FROM documents 
                                                   WHERE user_id = ? AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
                                                   AND YEAR(created_at) = YEAR(CURRENT_DATE())";
                                $month_stmt = mysqli_prepare($conn, $month_count_sql);
                                mysqli_stmt_bind_param($month_stmt, "i", $user_id);
                                mysqli_stmt_execute($month_stmt);
                                $month_result = mysqli_stmt_get_result($month_stmt);
                                $month_count = mysqli_fetch_assoc($month_result)['count'] ?? 0;
                                if ($month_stmt) mysqli_stmt_close($month_stmt);
                                if ($month_result) mysqli_free_result($month_result);
                            }
                            mysqli_free_result($table_check);
                            echo $month_count;
                            ?>
                        </div>
                        <p>Documents generated this month</p>
                    </div>
                </div>
            <?php endif; ?>
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
                    <h3>Document Support</h3>
                    <p><i class="fas fa-envelope"></i> documents@ardhiyetu.go.ke</p>
                    <p><i class="fas fa-phone"></i> 0700 000 001</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> ArdhiYetu Land Management System. All documents are electronically generated and verifiable.</p>
            </div>
        </div>
    </footer>

    <script src="../assets/js/script.js"></script>
    <script>
        // Set end date minimum to start date
        document.addEventListener('DOMContentLoaded', function() {
            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');
            
            if (startDate && endDate) {
                startDate.addEventListener('change', function() {
                    endDate.min = this.value;
                    if (endDate.value < this.value) {
                        endDate.value = this.value;
                    }
                });
                
                // Set initial min value
                endDate.min = startDate.value;
            }
            
            // Auto-format document numbers in table
            document.querySelectorAll('.documents-table td:first-child').forEach(td => {
                const text = td.textContent.trim();
                if (text.includes('/')) {
                    td.innerHTML = `<code style="background: #f0f0f0; padding: 2px 5px; border-radius: 3px;">${text}</code>`;
                }
            });
        });
        
        // Quick action functions
        function quickGenerate(type) {
            if (type === 'certificate') {
                window.location.href = '?action=generate_certificate';
            } else if (type === 'report') {
                window.location.href = '?action=generate_report';
            }
        }
        
        // Document actions
        function downloadDoc(docId) {
            alert('Downloading document ID: ' + docId);
            // In real implementation, this would trigger a download
            // window.location.href = 'download-document.php?id=' + docId;
        }
        
        function printDoc(docId) {
            alert('Printing document ID: ' + docId);
            // In real implementation, this would open print dialog
            // window.open('print-document.php?id=' + docId, '_blank');
        }
    </script>
</body>
</html>