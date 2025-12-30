<?php
// File: public/my-legal-documents.php
require_once __DIR__ . '/../includes/init.php';

// Require login
require_login();

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$document_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';

// Check if legal_documents table exists, create if not
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'legal_documents'");
if (mysqli_num_rows($table_check) === 0) {
    $create_table_sql = "CREATE TABLE IF NOT EXISTS legal_documents (
        legal_doc_id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        land_id INT NULL,
        template_id INT NOT NULL,
        document_title VARCHAR(255) NOT NULL,
        document_content LONGTEXT NOT NULL,
        status ENUM('draft', 'finalized', 'signed', 'archived') DEFAULT 'draft',
        signed_date DATE NULL,
        signed_by VARCHAR(255) NULL,
        witnesses TEXT NULL,
        file_path VARCHAR(500) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_template_id (template_id),
        INDEX idx_status (status),
        INDEX idx_created_at (created_at)
    )";
    
    if (!mysqli_query($conn, $create_table_sql)) {
        die("Failed to create legal_documents table: " . mysqli_error($conn));
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        flash_message('error', 'Invalid form submission. Please try again.');
        redirect('my-legal-documents.php');
    }
    
    $action = $_POST['action'];
    
    if ($action == 'update_document') {
        $doc_id = intval($_POST['document_id']);
        $document_title = sanitize_input($_POST['document_title']);
        $document_content = $_POST['document_content'];
        $status = sanitize_input($_POST['status']);
        $signed_date = !empty($_POST['signed_date']) ? sanitize_input($_POST['signed_date']) : null;
        $signed_by = !empty($_POST['signed_by']) ? sanitize_input($_POST['signed_by']) : null;
        $witnesses = !empty($_POST['witnesses']) ? sanitize_input($_POST['witnesses']) : null;
        
        // Validate
        if (empty($document_title) || empty($document_content)) {
            flash_message('error', 'Document title and content are required.');
        } else {
            // Check if user owns this document
            $check_sql = "SELECT * FROM legal_documents WHERE legal_doc_id = ? AND user_id = ?";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "ii", $doc_id, $user_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if (mysqli_num_rows($check_result) === 0) {
                flash_message('error', 'Document not found or access denied.');
                mysqli_stmt_close($check_stmt);
                redirect('my-legal-documents.php');
            }
            
            mysqli_stmt_close($check_stmt);
            
            // Update document
            $update_sql = "UPDATE legal_documents SET 
                          document_title = ?, 
                          document_content = ?, 
                          status = ?, 
                          signed_date = ?, 
                          signed_by = ?, 
                          witnesses = ?, 
                          updated_at = NOW() 
                          WHERE legal_doc_id = ? AND user_id = ?";
            
            $update_stmt = mysqli_prepare($conn, $update_sql);
            
            if ($update_stmt) {
                mysqli_stmt_bind_param($update_stmt, "ssssssii", 
                    $document_title, $document_content, $status, $signed_date, $signed_by, $witnesses, $doc_id, $user_id);
                
                if (mysqli_stmt_execute($update_stmt)) {
                    // Log activity if function exists
                    if (function_exists('log_activity')) {
                        log_activity($user_id, 'legal_document_updated', 
                            "Updated legal document: " . $document_title);
                    }
                    
                    // If status is signed and file doesn't exist, generate PDF
                    if ($status == 'signed' && $signed_date && $signed_by) {
                        generate_document_pdf($doc_id, $user_id);
                    }
                    
                    flash_message('success', 'Document updated successfully.');
                    redirect("my-legal-documents.php?action=view&id=$doc_id");
                } else {
                    flash_message('error', 'Failed to update document: ' . mysqli_error($conn));
                }
                mysqli_stmt_close($update_stmt);
            } else {
                flash_message('error', 'Database error: ' . mysqli_error($conn));
            }
        }
    } elseif ($action == 'delete_document') {
        $doc_id = intval($_POST['document_id']);
        
        // Check if user owns this document
        $check_sql = "SELECT document_title FROM legal_documents WHERE legal_doc_id = ? AND user_id = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "ii", $doc_id, $user_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) === 0) {
            flash_message('error', 'Document not found or access denied.');
            mysqli_stmt_close($check_stmt);
            redirect('my-legal-documents.php');
        }
        
        $doc_data = mysqli_fetch_assoc($check_stmt);
        mysqli_stmt_close($check_stmt);
        
        // Soft delete by changing status to archived
        $update_sql = "UPDATE legal_documents SET status = 'archived' WHERE legal_doc_id = ? AND user_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        
        if ($update_stmt) {
            mysqli_stmt_bind_param($update_stmt, "ii", $doc_id, $user_id);
            
            if (mysqli_stmt_execute($update_stmt)) {
                // Log activity if function exists
                if (function_exists('log_activity')) {
                    log_activity($user_id, 'legal_document_archived', 
                        "Archived legal document: " . $doc_data['document_title']);
                }
                
                flash_message('success', 'Document archived successfully.');
                redirect('my-legal-documents.php');
            } else {
                flash_message('error', 'Failed to archive document: ' . mysqli_error($conn));
            }
            mysqli_stmt_close($update_stmt);
        } else {
            flash_message('error', 'Database error: ' . mysqli_error($conn));
        }
    } elseif ($action == 'download_pdf') {
        $doc_id = intval($_POST['document_id']);
        
        // Check if user owns this document
        $check_sql = "SELECT * FROM legal_documents WHERE legal_doc_id = ? AND user_id = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "ii", $doc_id, $user_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) === 0) {
            flash_message('error', 'Document not found or access denied.');
            mysqli_stmt_close($check_stmt);
            redirect('my-legal-documents.php');
        }
        
        $doc_data = mysqli_fetch_assoc($check_stmt);
        mysqli_stmt_close($check_stmt);
        
        // Generate or get PDF file path
        $pdf_path = generate_document_pdf($doc_id, $user_id);
        
        if ($pdf_path) {
            // Redirect to download
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($pdf_path) . '"');
            readfile($pdf_path);
            exit;
        } else {
            flash_message('error', 'Failed to generate PDF.');
            redirect("my-legal-documents.php?action=view&id=$doc_id");
        }
    }
}

// Function to generate document PDF
function generate_document_pdf($doc_id, $user_id) {
    global $conn;
    
    // Get document data
    $sql = "SELECT * FROM legal_documents WHERE legal_doc_id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $doc_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $document = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$document) {
        return false;
    }
    
    // Create uploads directory if it doesn't exist
    $upload_dir = __DIR__ . '/../uploads/legal-documents/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate PDF filename
    $filename = 'legal_doc_' . $doc_id . '_' . time() . '.pdf';
    $file_path = $upload_dir . $filename;
    
    // In a real system, you would use a PDF library like TCPDF, Dompdf, or mPDF
    // For now, we'll create a simple text file as placeholder
    
    $pdf_content = "ARDHIYETU LEGAL DOCUMENT\n";
    $pdf_content .= "=======================\n\n";
    $pdf_content .= "Document ID: " . $document['legal_doc_id'] . "\n";
    $pdf_content .= "Title: " . $document['document_title'] . "\n";
    $pdf_content .= "Status: " . ucfirst($document['status']) . "\n";
    $pdf_content .= "Created: " . $document['created_at'] . "\n";
    $pdf_content .= "Last Updated: " . $document['updated_at'] . "\n\n";
    
    if ($document['signed_date']) {
        $pdf_content .= "Signed Date: " . $document['signed_date'] . "\n";
        $pdf_content .= "Signed By: " . $document['signed_by'] . "\n";
        if ($document['witnesses']) {
            $pdf_content .= "Witnesses: " . $document['witnesses'] . "\n";
        }
        $pdf_content .= "\n";
    }
    
    $pdf_content .= "DOCUMENT CONTENT:\n";
    $pdf_content .= "=================\n\n";
    $pdf_content .= $document['document_content'];
    
    // Save as text file (in real system, save as PDF)
    file_put_contents($file_path, $pdf_content);
    
    // Update document with file path
    $update_sql = "UPDATE legal_documents SET file_path = ? WHERE legal_doc_id = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "si", $file_path, $doc_id);
    mysqli_stmt_execute($update_stmt);
    mysqli_stmt_close($update_stmt);
    
    return $file_path;
}

// Build query for documents list - FIXED: Specify table alias for status
$where = "ld.user_id = ? AND ld.status != 'archived'";
$params = [$user_id];
$param_types = "i";

if ($search) {
    $where .= " AND (ld.document_title LIKE ? OR ld.document_content LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $param_types .= "ss";
}

if ($status) {
    $where .= " AND ld.status = ?";
    $params[] = $status;
    $param_types .= "s";
}

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Get total count - FIXED: Specify table alias
$count_sql = "SELECT COUNT(*) as total FROM legal_documents ld WHERE $where";
$count_stmt = mysqli_prepare($conn, $count_sql);
if ($count_stmt) {
    mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
    $total_row = mysqli_fetch_assoc($count_result);
    $total_documents = $total_row ? $total_row['total'] : 0;
    $total_pages = ceil($total_documents / $limit);
    mysqli_stmt_close($count_stmt);
} else {
    $total_documents = 0;
    $total_pages = 0;
}

// Get documents - FIXED: Specify table alias for all columns that might be ambiguous
$sql = "SELECT ld.*, lr.parcel_no, lr.location, lr.status as land_status 
        FROM legal_documents ld 
        LEFT JOIN land_records lr ON ld.land_id = lr.record_id 
        WHERE $where 
        ORDER BY ld.updated_at DESC 
        LIMIT ? OFFSET ?";
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    $limit_param = $limit;
    $offset_param = $offset;
    $full_param_types = $param_types . "ii";
    $full_params = array_merge($params, [$limit_param, $offset_param]);
    
    mysqli_stmt_bind_param($stmt, $full_param_types, ...$full_params);
    mysqli_stmt_execute($stmt);
    $documents_result = mysqli_stmt_get_result($stmt);
} else {
    $documents_result = false;
}

// Get document for view/edit
$document = null;
if ($document_id > 0) {
    $doc_sql = "SELECT ld.*, lr.parcel_no, lr.location, lr.size 
               FROM legal_documents ld 
               LEFT JOIN land_records lr ON ld.land_id = lr.record_id 
               WHERE ld.legal_doc_id = ? AND ld.user_id = ?";
    $doc_stmt = mysqli_prepare($conn, $doc_sql);
    mysqli_stmt_bind_param($doc_stmt, "ii", $document_id, $user_id);
    mysqli_stmt_execute($doc_stmt);
    $doc_result = mysqli_stmt_get_result($doc_stmt);
    $document = mysqli_fetch_assoc($doc_result);
    mysqli_stmt_close($doc_stmt);
}

// Get document statistics
$stats_sql = "SELECT 
              COUNT(*) as total,
              SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as drafts,
              SUM(CASE WHEN status = 'finalized' THEN 1 ELSE 0 END) as finalized,
              SUM(CASE WHEN status = 'signed' THEN 1 ELSE 0 END) as signed,
              SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived
              FROM legal_documents 
              WHERE user_id = ?";
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
    <title>My Legal Documents - ArdhiYetu</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .my-legal-docs-container {
            padding: 20px 0;
            min-height: 1000px;
        }
        
        .docs-header {
            background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
            color: white;
            padding: 40px 30px;
            border-radius: 10px;
            margin-bottom: 40px;
            text-align: center;
        }
        
        .docs-header h1 {
            margin: 0 0 15px 0;
            font-size: 2.8rem;
        }
        
        .docs-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 1.2rem;
            max-width: 800px;
            margin: 0 auto;
            line-height: 1.6;
        }
        
        .header-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 25px;
            flex-wrap: wrap;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            border-top: 4px solid #3498db;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.draft { border-top-color: #f39c12; }
        .stat-card.finalized { border-top-color: #27ae60; }
        .stat-card.signed { border-top-color: #9b59b6; }
        .stat-card.archived { border-top-color: #95a5a6; }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 10px 0;
            color: #2c3e50;
        }
        
        .stat-label {
            color: #666;
            font-size: 1.1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .filter-bar {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .filter-form {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .search-box {
            flex: 1;
            min-width: 300px;
            position: relative;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .search-box button {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 1.2rem;
        }
        
        .status-filter {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .status-btn {
            padding: 10px 20px;
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            color: #555;
            transition: all 0.3s;
        }
        
        .status-btn.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .status-btn:hover:not(.active) {
            background: #e9ecef;
            border-color: #3498db;
        }
        
        .documents-list {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 40px;
        }
        
        .list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
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
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-draft {
            background: #fef9e7;
            color: #b7950b;
        }
        
        .badge-finalized {
            background: #e8f6f3;
            color: #1d8348;
        }
        
        .badge-signed {
            background: #f4ecf7;
            color: #6c3483;
        }
        
        .badge-archived {
            background: #f2f4f4;
            color: #5d6d7e;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-icon {
            padding: 8px 12px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-view {
            background: #3498db;
            color: white;
        }
        
        .btn-edit {
            background: #f39c12;
            color: white;
        }
        
        .btn-download {
            background: #27ae60;
            color: white;
        }
        
        .btn-delete {
            background: #e74c3c;
            color: white;
        }
        
        .btn-icon:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .document-detail-view {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            margin-bottom: 40px;
        }
        
        .detail-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }
        
        .detail-title h2 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            font-size: 2rem;
        }
        
        .detail-meta {
            display: flex;
            gap: 20px;
            color: #666;
            font-size: 0.95rem;
            flex-wrap: wrap;
        }
        
        .detail-actions {
            display: flex;
            gap: 15px;
        }
        
        .detail-content {
            margin: 40px 0;
        }
        
        .document-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .info-item {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }
        
        .info-item label {
            display: block;
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-item span {
            color: #333;
            font-size: 1.1rem;
            display: block;
            word-break: break-word;
        }
        
        .document-content-view {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            margin: 30px 0;
            font-family: 'Courier New', monospace;
            line-height: 1.8;
            white-space: pre-wrap;
            max-height: 600px;
            overflow-y: auto;
        }
        
        .edit-form-container {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            margin-bottom: 40px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.1rem;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        textarea.form-control {
            min-height: 300px;
            resize: vertical;
            font-family: monospace;
            line-height: 1.6;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .form-actions {
            display: flex;
            gap: 20px;
            justify-content: flex-end;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #eee;
        }
        
        .signature-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin: 30px 0;
            border-left: 4px solid #27ae60;
        }
        
        .signature-section h4 {
            margin: 0 0 20px 0;
            color: #2c3e50;
        }
        
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 5rem;
            color: #ddd;
            margin-bottom: 25px;
        }
        
        .empty-state h3 {
            margin: 0 0 15px 0;
            color: #555;
            font-size: 1.8rem;
        }
        
        .empty-state p {
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto 30px;
            line-height: 1.6;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .page-link {
            padding: 10px 18px;
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            color: #555;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .page-link:hover {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .page-link.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        @media (max-width: 768px) {
            .docs-header h1 {
                font-size: 2.2rem;
            }
            
            .docs-header p {
                font-size: 1rem;
            }
            
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                min-width: 100%;
            }
            
            .status-filter {
                justify-content: center;
            }
            
            .detail-header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .detail-meta {
                justify-content: center;
            }
            
            .detail-actions {
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .action-buttons {
                flex-wrap: wrap;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .header-actions {
                flex-direction: column;
                align-items: center;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .status-filter {
                flex-direction: column;
            }
            
            .action-buttons {
                flex-direction: column;
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
                <a href="documents.php"><i class="fas fa-file-alt"></i> Documents</a>
                <a href="legal-documents.php"><i class="fas fa-gavel"></i> Legal Docs</a>
                <a href="my-legal-documents.php" class="active"><i class="fas fa-folder"></i> My Legal Docs</a>
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

    <main class="my-legal-docs-container">
        <div class="container">
            <div class="docs-header">
                <h1><i class="fas fa-folder-open"></i> My Legal Documents</h1>
                <p>Manage and access all your customized legal documents, agreements, and contracts. Edit, download, or sign your documents here.</p>
                
                <div class="header-actions">
                    <a href="legal-documents.php" class="btn">
                        <i class="fas fa-plus"></i> Create New Document
                    </a>
                    <a href="?status=signed" class="btn secondary">
                        <i class="fas fa-signature"></i> View Signed Documents
                    </a>
                    <a href="?status=archived" class="btn secondary">
                        <i class="fas fa-archive"></i> View Archived
                    </a>
                </div>
            </div>

            <?php 
            // Display flash messages if function exists
            if (function_exists('display_flash_message')) {
                display_flash_message();
            }
            ?>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                    <div class="stat-label">Total Documents</div>
                </div>
                
                <div class="stat-card draft">
                    <div class="stat-icon">
                        <i class="fas fa-edit"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['drafts'] ?? 0; ?></div>
                    <div class="stat-label">Drafts</div>
                </div>
                
                <div class="stat-card finalized">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['finalized'] ?? 0; ?></div>
                    <div class="stat-label">Finalized</div>
                </div>
                
                <div class="stat-card signed">
                    <div class="stat-icon">
                        <i class="fas fa-signature"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['signed'] ?? 0; ?></div>
                    <div class="stat-label">Signed</div>
                </div>
                
                <div class="stat-card archived">
                    <div class="stat-icon">
                        <i class="fas fa-archive"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['archived'] ?? 0; ?></div>
                    <div class="stat-label">Archived</div>
                </div>
            </div>
            
            <?php if ($action == 'view' && $document): ?>
                <!-- Document Detail View -->
                <div class="document-detail-view">
                    <div class="detail-header">
                        <div class="detail-title">
                            <h2><?php echo htmlspecialchars($document['document_title']); ?></h2>
                            <div class="detail-meta">
                                <span><i class="fas fa-calendar"></i> Created: <?php echo date('M d, Y', strtotime($document['created_at'])); ?></span>
                                <span><i class="fas fa-sync-alt"></i> Updated: <?php                                echo date('M d, Y', strtotime($document['updated_at'])); ?></span>
                                <span class="status-badge <?php echo 'badge-' . $document['status']; ?>">
                                    <i class="fas fa-circle"></i> <?php echo ucfirst($document['status']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="detail-actions">
                            <a href="?action=edit&id=<?php echo $document_id; ?>" class="btn-icon btn-edit">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to download this document?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="download_pdf">
                                <input type="hidden" name="document_id" value="<?php echo $document_id; ?>">
                                <button type="submit" class="btn-icon btn-download">
                                    <i class="fas fa-download"></i> Download PDF
                                </button>
                            </form>
                            <a href="my-legal-documents.php" class="btn-icon" style="background: #6c757d; color: white;">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>
                    
                    <!-- Document Information Grid -->
                    <div class="document-info-grid">
                        <?php if ($document['land_id']): ?>
                        <div class="info-item">
                            <label><i class="fas fa-landmark"></i> Associated Land</label>
                            <span>
                                <?php if ($document['parcel_no']): ?>
                                Parcel No: <?php echo htmlspecialchars($document['parcel_no']); ?> - 
                                <?php echo htmlspecialchars($document['location']); ?>
                                <?php else: ?>
                                Land ID: <?php echo $document['land_id']; ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($document['signed_date']): ?>
                        <div class="info-item">
                            <label><i class="fas fa-calendar-check"></i> Signed Date</label>
                            <span><?php echo date('M d, Y', strtotime($document['signed_date'])); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($document['signed_by']): ?>
                        <div class="info-item">
                            <label><i class="fas fa-signature"></i> Signed By</label>
                            <span><?php echo htmlspecialchars($document['signed_by']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($document['file_path']): ?>
                        <div class="info-item">
                            <label><i class="fas fa-file-pdf"></i> PDF File</label>
                            <span><?php echo basename($document['file_path']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($document['witnesses']): ?>
                    <div class="signature-section">
                        <h4><i class="fas fa-users"></i> Witnesses</h4>
                        <p><?php echo nl2br(htmlspecialchars($document['witnesses'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Document Content -->
                    <div class="document-content-view">
                        <?php echo nl2br(htmlspecialchars($document['document_content'])); ?>
                    </div>
                    
                    <!-- Document Actions -->
                    <div class="form-actions">
                        <?php if ($document['status'] != 'signed'): ?>
                        <form method="post" style="display: inline;" onsubmit="return confirm('Mark this document as signed? This action cannot be undone.');">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="update_document">
                            <input type="hidden" name="document_id" value="<?php echo $document_id; ?>">
                            <input type="hidden" name="document_title" value="<?php echo htmlspecialchars($document['document_title']); ?>">
                            <input type="hidden" name="document_content" value="<?php echo htmlspecialchars($document['document_content']); ?>">
                            <input type="hidden" name="status" value="signed">
                            <input type="hidden" name="signed_date" value="<?php echo date('Y-m-d'); ?>">
                            <input type="hidden" name="signed_by" value="<?php echo htmlspecialchars($user_name); ?>">
                            <button type="submit" class="btn" style="background: #27ae60;">
                                <i class="fas fa-signature"></i> Mark as Signed
                            </button>
                        </form>
                        <?php endif; ?>
                        
                        <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to archive this document? You can restore it from the archived view.');">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="delete_document">
                            <input type="hidden" name="document_id" value="<?php echo $document_id; ?>">
                            <button type="submit" class="btn secondary" style="background: #95a5a6;">
                                <i class="fas fa-archive"></i> Archive Document
                            </button>
                        </form>
                    </div>
                </div>
                
            <?php elseif ($action == 'edit' && $document): ?>
                <!-- Edit Document Form -->
                <div class="edit-form-container">
                    <h2 style="margin-bottom: 30px; color: #2c3e50;">
                        <i class="fas fa-edit"></i> Edit Document: <?php echo htmlspecialchars($document['document_title']); ?>
                    </h2>
                    
                    <form method="post" id="editDocumentForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="update_document">
                        <input type="hidden" name="document_id" value="<?php echo $document_id; ?>">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="document_title"><i class="fas fa-heading"></i> Document Title *</label>
                                <input type="text" id="document_title" name="document_title" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($document['document_title']); ?>"
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="status"><i class="fas fa-tag"></i> Status *</label>
                                <select id="status" name="status" class="form-control" required>
                                    <option value="draft" <?php echo $document['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="finalized" <?php echo $document['status'] == 'finalized' ? 'selected' : ''; ?>>Finalized</option>
                                    <option value="signed" <?php echo $document['status'] == 'signed' ? 'selected' : ''; ?>>Signed</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="document_content"><i class="fas fa-file-alt"></i> Document Content *</label>
                            <textarea id="document_content" name="document_content" 
                                      class="form-control" 
                                      rows="15" 
                                      required><?php echo htmlspecialchars($document['document_content']); ?></textarea>
                        </div>
                        
                        <div class="signature-section">
                            <h4><i class="fas fa-signature"></i> Signature Information</h4>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="signed_date"><i class="fas fa-calendar-alt"></i> Signed Date</label>
                                    <input type="date" id="signed_date" name="signed_date" 
                                           class="form-control" 
                                           value="<?php echo $document['signed_date'] ?? ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="signed_by"><i class="fas fa-user-check"></i> Signed By</label>
                                    <input type="text" id="signed_by" name="signed_by" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($document['signed_by'] ?? ''); ?>"
                                           placeholder="Full name of signatory">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="witnesses"><i class="fas fa-users"></i> Witnesses (one per line)</label>
                                <textarea id="witnesses" name="witnesses" 
                                          class="form-control" 
                                          rows="3"
                                          placeholder="Enter witness names, one per line"><?php echo htmlspecialchars($document['witnesses'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn" style="background: #27ae60;">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <a href="?action=view&id=<?php echo $document_id; ?>" class="btn secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <a href="my-legal-documents.php" class="btn secondary" style="background: #6c757d;">
                                <i class="fas fa-arrow-left"></i> Back to List
                            </a>
                        </div>
                    </form>
                </div>
                
            <?php else: ?>
                <!-- Documents List View -->
                
                <!-- Filter Bar -->
                <div class="filter-bar">
                    <form method="get" class="filter-form">
                        <input type="hidden" name="action" value="list">
                        
                        <div class="search-box">
                            <input type="text" name="search" 
                                   placeholder="Search documents by title or content..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit"><i class="fas fa-search"></i></button>
                        </div>
                        
                        <div class="status-filter">
                            <a href="?status=" class="status-btn <?php echo empty($status) ? 'active' : ''; ?>">
                                All Documents
                            </a>
                            <a href="?status=draft" class="status-btn <?php echo $status == 'draft' ? 'active' : ''; ?>">
                                Drafts
                            </a>
                            <a href="?status=finalized" class="status-btn <?php echo $status == 'finalized' ? 'active' : ''; ?>">
                                Finalized
                            </a>
                            <a href="?status=signed" class="status-btn <?php echo $status == 'signed' ? 'active' : ''; ?>">
                                Signed
                            </a>
                        </div>
                        
                        <?php if ($search || $status): ?>
                        <a href="my-legal-documents.php" class="btn secondary" style="padding: 10px 20px;">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <!-- Documents Table -->
                <div class="documents-list">
                    <div class="list-header">
                        <h3 style="margin: 0; color: #2c3e50;">
                            <i class="fas fa-folder"></i> My Legal Documents
                            <?php if ($total_documents > 0): ?>
                            <span style="font-size: 0.9rem; color: #666; font-weight: normal;">
                                (<?php echo $total_documents; ?> document<?php echo $total_documents != 1 ? 's' : ''; ?>)
                            </span>
                            <?php endif; ?>
                        </h3>
                        
                        <a href="legal-documents.php" class="btn" style="padding: 12px 24px;">
                            <i class="fas fa-plus"></i> Create New Document
                        </a>
                    </div>
                    
                    <?php if ($documents_result && mysqli_num_rows($documents_result) > 0): ?>
                    <div class="table-responsive">
                        <table class="documents-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Land Info</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($doc = mysqli_fetch_assoc($documents_result)): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($doc['document_title']); ?></strong>
                                        <?php if (strlen($doc['document_title']) > 50): ?>
                                        <br><small style="color: #666;"><?php echo htmlspecialchars(substr($doc['document_title'], 0, 50)); ?>...</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($doc['parcel_no']): ?>
                                        <small>Parcel: <?php echo htmlspecialchars($doc['parcel_no']); ?></small><br>
                                        <small style="color: #666;"><?php echo htmlspecialchars($doc['location']); ?></small>
                                        <?php else: ?>
                                        <span style="color: #999;">Not linked to land</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge badge-<?php echo $doc['status']; ?>">
                                            <?php echo ucfirst($doc['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($doc['created_at'])); ?><br>
                                        <small style="color: #666;"><?php echo date('H:i', strtotime($doc['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($doc['updated_at'])); ?><br>
                                        <small style="color: #666;"><?php echo date('H:i', strtotime($doc['updated_at'])); ?></small>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?action=view&id=<?php echo $doc['legal_doc_id']; ?>" 
                                               class="btn-icon btn-view" 
                                               title="View Document">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="?action=edit&id=<?php echo $doc['legal_doc_id']; ?>" 
                                               class="btn-icon btn-edit" 
                                               title="Edit Document">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="post" style="display: inline;" 
                                                  onsubmit="return confirm('Download this document as PDF?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="action" value="download_pdf">
                                                <input type="hidden" name="document_id" value="<?php echo $doc['legal_doc_id']; ?>">
                                                <button type="submit" class="btn-icon btn-download" title="Download PDF">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                            </form>
                                            <form method="post" style="display: inline;" 
                                                  onsubmit="return confirm('Archive this document? You can restore it from archived view.');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="action" value="delete_document">
                                                <input type="hidden" name="document_id" value="<?php echo $doc['legal_doc_id']; ?>">
                                                <button type="submit" class="btn-icon btn-delete" title="Archive Document">
                                                    <i class="fas fa-archive"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status ? '&status=' . urlencode($status) : ''; ?>" 
                               class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <h3>No Legal Documents Found</h3>
                        <p>
                            <?php if ($search || $status): ?>
                            No documents match your current filters. Try adjusting your search criteria.
                            <?php else: ?>
                            You haven't created any legal documents yet. Create your first document to get started.
                            <?php endif; ?>
                        </p>
                        <a href="legal-documents.php" class="btn" style="padding: 15px 30px; font-size: 1.1rem;">
                            <i class="fas fa-plus"></i> Create Your First Document
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php 
                // Free result if it exists
                if ($documents_result) {
                    mysqli_free_result($documents_result);
                }
                ?>
                
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 ArdhiYetu Land Management System. All rights reserved.</p>
            <div class="footer-links">
                <a href="../about.php">About</a>
                <a href="../privacy.php">Privacy Policy</a>
                <a href="../terms.php">Terms of Service</a>
                <a href="../contact.php">Contact</a>
            </div>
        </div>
    </footer>

    <script>
        // Mobile menu toggle
        document.querySelector('.mobile-menu-btn').addEventListener('click', function() {
            document.querySelector('.nav-links').classList.toggle('show');
        });
        
        // Auto-resize textarea
        const textarea = document.getElementById('document_content');
        if (textarea) {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
            // Trigger once on load
            textarea.dispatchEvent(new Event('input'));
        }
        
        // Status change confirmation
        const statusSelect = document.getElementById('status');
        if (statusSelect) {
            statusSelect.addEventListener('change', function() {
                if (this.value === 'signed') {
                    if (!confirm('Marking as signed will require signature details. Continue?')) {
                        this.value = '<?php echo $document["status"] ?? "draft"; ?>';
                    }
                }
            });
        }
        
        // Form validation
        const form = document.getElementById('editDocumentForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const signedDate = document.getElementById('signed_date').value;
                const signedBy = document.getElementById('signed_by').value;
                const status = document.getElementById('status').value;
                
                if (status === 'signed') {
                    if (!signedDate) {
                        e.preventDefault();
                        alert('Please enter a signed date for signed documents.');
                        document.getElementById('signed_date').focus();
                        return false;
                    }
                    if (!signedBy.trim()) {
                        e.preventDefault();
                        alert('Please enter the signatory name for signed documents.');
                        document.getElementById('signed_by').focus();
                        return false;
                    }
                }
            });
        }
    </script>
</body>
</html>