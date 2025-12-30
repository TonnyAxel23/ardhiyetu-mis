<?php
// File: public/archive.php
require_once __DIR__ . '/../includes/init.php';

// Require login
require_login();

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$document_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        flash_message('error', 'Invalid form submission. Please try again.');
        redirect('archive.php');
    }
    
    $action = $_POST['action'];
    
    if ($action == 'restore_document') {
        $doc_id = intval($_POST['document_id']);
        
        // Check if user owns this document
        $check_sql = "SELECT document_title FROM legal_documents WHERE legal_doc_id = ? AND user_id = ? AND status = 'archived'";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "ii", $doc_id, $user_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) === 0) {
            flash_message('error', 'Document not found or not archived.');
            mysqli_stmt_close($check_stmt);
            redirect('archive.php');
        }
        
        $doc_data = mysqli_fetch_assoc($check_stmt);
        mysqli_stmt_close($check_stmt);
        
        // Restore document by changing status to draft
        $update_sql = "UPDATE legal_documents SET status = 'draft' WHERE legal_doc_id = ? AND user_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        
        if ($update_stmt) {
            mysqli_stmt_bind_param($update_stmt, "ii", $doc_id, $user_id);
            
            if (mysqli_stmt_execute($update_stmt)) {
                // Log activity if function exists
                if (function_exists('log_activity')) {
                    log_activity($user_id, 'legal_document_restored', 
                        "Restored legal document: " . $doc_data['document_title']);
                }
                
                flash_message('success', 'Document restored successfully. It is now back in your drafts.');
                redirect('archive.php');
            } else {
                flash_message('error', 'Failed to restore document: ' . mysqli_error($conn));
            }
            mysqli_stmt_close($update_stmt);
        } else {
            flash_message('error', 'Database error: ' . mysqli_error($conn));
        }
    } elseif ($action == 'delete_permanently') {
        $doc_id = intval($_POST['document_id']);
        
        // Check if user owns this document
        $check_sql = "SELECT document_title, file_path FROM legal_documents WHERE legal_doc_id = ? AND user_id = ? AND status = 'archived'";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "ii", $doc_id, $user_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) === 0) {
            flash_message('error', 'Document not found or not archived.');
            mysqli_stmt_close($check_stmt);
            redirect('archive.php');
        }
        
        $doc_data = mysqli_fetch_assoc($check_stmt);
        mysqli_stmt_close($check_stmt);
        
        // Delete associated PDF file if exists
        if ($doc_data['file_path'] && file_exists($doc_data['file_path'])) {
            unlink($doc_data['file_path']);
        }
        
        // Permanently delete the document
        $delete_sql = "DELETE FROM legal_documents WHERE legal_doc_id = ? AND user_id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_sql);
        
        if ($delete_stmt) {
            mysqli_stmt_bind_param($delete_stmt, "ii", $doc_id, $user_id);
            
            if (mysqli_stmt_execute($delete_stmt)) {
                // Log activity if function exists
                if (function_exists('log_activity')) {
                    log_activity($user_id, 'legal_document_deleted', 
                        "Permanently deleted legal document: " . $doc_data['document_title']);
                }
                
                flash_message('success', 'Document permanently deleted.');
                redirect('archive.php');
            } else {
                flash_message('error', 'Failed to delete document: ' . mysqli_error($conn));
            }
            mysqli_stmt_close($delete_stmt);
        } else {
            flash_message('error', 'Database error: ' . mysqli_error($conn));
        }
    } elseif ($action == 'bulk_restore') {
        $document_ids = isset($_POST['document_ids']) ? $_POST['document_ids'] : [];
        $success_count = 0;
        $error_count = 0;
        
        foreach ($document_ids as $doc_id) {
            $doc_id = intval($doc_id);
            
            // Check if user owns this document
            $check_sql = "SELECT document_title FROM legal_documents WHERE legal_doc_id = ? AND user_id = ? AND status = 'archived'";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "ii", $doc_id, $user_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if (mysqli_num_rows($check_result) === 0) {
                $error_count++;
                continue;
            }
            
            $doc_data = mysqli_fetch_assoc($check_result);
            mysqli_stmt_close($check_stmt);
            
            // Restore document
            $update_sql = "UPDATE legal_documents SET status = 'draft' WHERE legal_doc_id = ? AND user_id = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            
            if ($update_stmt) {
                mysqli_stmt_bind_param($update_stmt, "ii", $doc_id, $user_id);
                
                if (mysqli_stmt_execute($update_stmt)) {
                    $success_count++;
                    // Log activity if function exists
                    if (function_exists('log_activity')) {
                        log_activity($user_id, 'legal_document_restored', 
                            "Restored legal document: " . $doc_data['document_title']);
                    }
                } else {
                    $error_count++;
                }
                mysqli_stmt_close($update_stmt);
            } else {
                $error_count++;
            }
        }
        
        if ($success_count > 0) {
            flash_message('success', "Successfully restored $success_count document(s).");
        }
        if ($error_count > 0) {
            flash_message('error', "Failed to restore $error_count document(s).");
        }
        
        redirect('archive.php');
    } elseif ($action == 'bulk_delete') {
        $document_ids = isset($_POST['document_ids']) ? $_POST['document_ids'] : [];
        $success_count = 0;
        $error_count = 0;
        
        foreach ($document_ids as $doc_id) {
            $doc_id = intval($doc_id);
            
            // Check if user owns this document
            $check_sql = "SELECT document_title, file_path FROM legal_documents WHERE legal_doc_id = ? AND user_id = ? AND status = 'archived'";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "ii", $doc_id, $user_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if (mysqli_num_rows($check_result) === 0) {
                $error_count++;
                continue;
            }
            
            $doc_data = mysqli_fetch_assoc($check_result);
            mysqli_stmt_close($check_stmt);
            
            // Delete associated PDF file if exists
            if ($doc_data['file_path'] && file_exists($doc_data['file_path'])) {
                unlink($doc_data['file_path']);
            }
            
            // Permanently delete the document
            $delete_sql = "DELETE FROM legal_documents WHERE legal_doc_id = ? AND user_id = ?";
            $delete_stmt = mysqli_prepare($conn, $delete_sql);
            
            if ($delete_stmt) {
                mysqli_stmt_bind_param($delete_stmt, "ii", $doc_id, $user_id);
                
                if (mysqli_stmt_execute($delete_stmt)) {
                    $success_count++;
                    // Log activity if function exists
                    if (function_exists('log_activity')) {
                        log_activity($user_id, 'legal_document_deleted', 
                            "Permanently deleted legal document: " . $doc_data['document_title']);
                    }
                } else {
                    $error_count++;
                }
                mysqli_stmt_close($delete_stmt);
            } else {
                $error_count++;
            }
        }
        
        if ($success_count > 0) {
            flash_message('success', "Successfully deleted $success_count document(s).");
        }
        if ($error_count > 0) {
            flash_message('error', "Failed to delete $error_count document(s).");
        }
        
        redirect('archive.php');
    } elseif ($action == 'empty_archive') {
        // Confirm action
        if (!isset($_POST['confirm']) || $_POST['confirm'] !== 'yes') {
            flash_message('error', 'Please confirm you want to empty the archive.');
            redirect('archive.php');
        }
        
        // Get all archived documents for this user
        $sql = "SELECT legal_doc_id, file_path FROM legal_documents WHERE user_id = ? AND status = 'archived'";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $deleted_count = 0;
        $error_count = 0;
        
        while ($doc = mysqli_fetch_assoc($result)) {
            // Delete associated PDF file if exists
            if ($doc['file_path'] && file_exists($doc['file_path'])) {
                unlink($doc['file_path']);
            }
            
            // Delete document
            $delete_sql = "DELETE FROM legal_documents WHERE legal_doc_id = ?";
            $delete_stmt = mysqli_prepare($conn, $delete_sql);
            
            if ($delete_stmt) {
                mysqli_stmt_bind_param($delete_stmt, "i", $doc['legal_doc_id']);
                
                if (mysqli_stmt_execute($delete_stmt)) {
                    $deleted_count++;
                } else {
                    $error_count++;
                }
                mysqli_stmt_close($delete_stmt);
            } else {
                $error_count++;
            }
        }
        
        mysqli_stmt_close($stmt);
        
        if ($deleted_count > 0) {
            // Log activity if function exists
            if (function_exists('log_activity')) {
                log_activity($user_id, 'archive_emptied', 
                    "Emptied archive - deleted $deleted_count document(s)");
            }
            
            flash_message('success', "Successfully emptied archive. $deleted_count document(s) permanently deleted.");
        }
        if ($error_count > 0) {
            flash_message('error', "Failed to delete $error_count document(s).");
        }
        
        redirect('archive.php');
    }
}

// Build query for archived documents
$where = "ld.user_id = ? AND ld.status = 'archived'";
$params = [$user_id];
$param_types = "i";

if ($search) {
    $where .= " AND (ld.document_title LIKE ? OR ld.document_content LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $param_types .= "ss";
}

// Pagination
$limit = 15;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Get total count
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

// Get archived documents
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

// Get document for view
$document = null;
if ($document_id > 0 && $action == 'view') {
    $doc_sql = "SELECT ld.*, lr.parcel_no, lr.location, lr.size 
               FROM legal_documents ld 
               LEFT JOIN land_records lr ON ld.land_id = lr.record_id 
               WHERE ld.legal_doc_id = ? AND ld.user_id = ? AND ld.status = 'archived'";
    $doc_stmt = mysqli_prepare($conn, $doc_sql);
    mysqli_stmt_bind_param($doc_stmt, "ii", $document_id, $user_id);
    mysqli_stmt_execute($doc_stmt);
    $doc_result = mysqli_stmt_get_result($doc_stmt);
    $document = mysqli_fetch_assoc($doc_result);
    mysqli_stmt_close($doc_stmt);
}

// Get archive statistics
$stats_sql = "SELECT 
              COUNT(*) as total,
              MIN(created_at) as oldest,
              MAX(created_at) as newest,
              SUM(CASE WHEN file_path IS NOT NULL THEN 1 ELSE 0 END) as has_files
              FROM legal_documents 
              WHERE user_id = ? AND status = 'archived'";
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
    <title>Archived Documents - ArdhiYetu</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .archive-container {
            padding: 20px 0;
            min-height: 1000px;
        }
        
        .archive-header {
            background: linear-gradient(135deg, #5d6d7e 0%, #34495e 100%);
            color: white;
            padding: 40px 30px;
            border-radius: 10px;
            margin-bottom: 40px;
            text-align: center;
        }
        
        .archive-header h1 {
            margin: 0 0 15px 0;
            font-size: 2.8rem;
        }
        
        .archive-header p {
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
            border-top: 4px solid #95a5a6;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: #5d6d7e;
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
        
        .stat-detail {
            font-size: 0.9rem;
            color: #888;
            margin-top: 10px;
        }
        
        .warning-banner {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            color: #856404;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        
        .warning-banner i {
            font-size: 1.5rem;
            margin-top: 2px;
        }
        
        .filter-bar {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
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
            border-color: #95a5a6;
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
        
        .bulk-actions {
            display: flex;
            gap: 10px;
            align-items: center;
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
            flex-wrap: wrap;
            gap: 20px;
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
        
        .select-cell {
            width: 40px;
            text-align: center;
        }
        
        .select-all {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .select-doc {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-archived {
            background: #f2f4f4;
            color: #5d6d7e;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
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
        
        .btn-restore {
            background: #27ae60;
            color: white;
        }
        
        .btn-download {
            background: #9b59b6;
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
        
        .empty-archive-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .empty-archive-btn:hover {
            background: #c0392b;
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
            background: #95a5a6;
            color: white;
            border-color: #95a5a6;
        }
        
        .page-link.active {
            background: #95a5a6;
            color: white;
            border-color: #95a5a6;
        }
        
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-body {
            margin-bottom: 25px;
        }
        
        .modal-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
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
            border-left: 4px solid #95a5a6;
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
        
        .form-actions {
            display: flex;
            gap: 20px;
            justify-content: flex-end;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #eee;
        }
        
        @media (max-width: 768px) {
            .archive-header h1 {
                font-size: 2.2rem;
            }
            
            .archive-header p {
                font-size: 1rem;
            }
            
            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                min-width: 100%;
            }
            
            .bulk-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .empty-archive-btn {
                justify-content: center;
            }
            
            .list-header {
                flex-direction: column;
                align-items: stretch;
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
            
            .action-buttons {
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .modal-content {
                padding: 20px;
                width: 95%;
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
                <a href="my-legal-documents.php"><i class="fas fa-folder"></i> My Legal Docs</a>
                <a href="archive.php" class="active"><i class="fas fa-archive"></i> Archive</a>
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

    <main class="archive-container">
        <div class="container">
            <div class="archive-header">
                <h1><i class="fas fa-archive"></i> Document Archive</h1>
                <p>Manage your archived legal documents. Restore documents to active status or permanently delete them. Archived documents are kept for 90 days before automatic deletion.</p>
                
                <div class="header-actions">
                    <a href="my-legal-documents.php" class="btn">
                        <i class="fas fa-arrow-left"></i> Back to Active Documents
                    </a>
                    <a href="legal-documents.php" class="btn secondary">
                        <i class="fas fa-plus"></i> Create New Document
                    </a>
                </div>
            </div>

            <?php 
            // Display flash messages if function exists
            if (function_exists('display_flash_message')) {
                display_flash_message();
            }
            ?>
            
            <!-- Warning Banner -->
            <div class="warning-banner">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Warning:</strong> Documents in the archive are marked for deletion. You can restore them or permanently delete them. Documents will be automatically deleted after 90 days in the archive.
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-file-archive"></i>
                    <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                    <div class="stat-label">Archived Documents</div>
                    <?php if ($stats['total'] > 0): ?>
                    <div class="stat-detail">
                        <?php echo $stats['has_files'] ?? 0; ?> with PDF files
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($stats['oldest']): ?>
                <div class="stat-card">
                    <i class="fas fa-calendar-minus"></i>
                    <div class="stat-number">
                        <?php 
                        $oldest = new DateTime($stats['oldest']);
                        $now = new DateTime();
                        $days = $now->diff($oldest)->days;
                        echo $days; 
                        ?>
                    </div>
                    <div class="stat-label">Days in Archive</div>
                    <div class="stat-detail">
                        Oldest: <?php echo date('M d, Y', strtotime($stats['oldest'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($stats['newest']): ?>
                <div class="stat-card">
                    <i class="fas fa-calendar-plus"></i>
                    <div class="stat-number">
                        <?php 
                        $newest = new DateTime($stats['newest']);
                        $now = new DateTime();
                        $days = $now->diff($newest)->days;
                        echo $days; 
                        ?>
                    </div>
                    <div class="stat-label">Days Since Last</div>
                    <div class="stat-detail">
                        Newest: <?php echo date('M d, Y', strtotime($stats['newest'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="stat-card">
                    <i class="fas fa-trash-alt"></i>
                    <div class="stat-number">90</div>
                    <div class="stat-label">Auto-delete Days</div>
                    <div class="stat-detail">
                        Documents auto-delete after 90 days
                    </div>
                </div>
            </div>
            
            <?php if ($action == 'view' && $document): ?>
                <!-- Document Detail View -->
                <div class="document-detail-view">
                    <div class="detail-header">
                        <div class="detail-title">
                            <h2><?php echo htmlspecialchars($document['document_title']); ?></h2>
                            <div class="detail-meta">
                                <span><i class="fas fa-archive"></i> Archived Document</span>
                                <span><i class="fas fa-calendar"></i> Created: <?php echo date('M d, Y', strtotime($document['created_at'])); ?></span>
                                <span><i class="fas fa-sync-alt"></i> Updated: <?php echo date('M d, Y', strtotime($document['updated_at'])); ?></span>
                            </div>
                        </div>
                        <div class="detail-actions">
                            <form method="post" style="display: inline;" onsubmit="return confirm('Restore this document to active status?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="restore_document">
                                <input type="hidden" name="document_id" value="<?php echo $document_id; ?>">
                                <button type="submit" class="btn-icon btn-restore">
                                    <i class="fas fa-undo"></i> Restore
                                </button>
                            </form>
                            <form method="post" style="display: inline;" onsubmit="return confirm('Download this archived document?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="download_pdf">
                                <input type="hidden" name="document_id" value="<?php echo $document_id; ?>">
                                <button type="submit" class="btn-icon btn-download">
                                    <i class="fas fa-download"></i> Download
                                </button>
                            </form>
                            <form method="post" style="display: inline;" onsubmit="return confirm('Permanently delete this document? This action cannot be undone!');">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="delete_permanently">
                                <input type="hidden" name="document_id" value="<?php echo $document_id; ?>">
                                <button type="submit" class="btn-icon btn-delete">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </button>
                            </form>
                            <a href="archive.php" class="btn-icon" style="background: #6c757d; color: white;">
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
                    
                    <!-- Document Content -->
                    <div class="document-content-view">
                        <?php echo nl2br(htmlspecialchars($document['document_content'])); ?>
                    </div>
                    
                    <!-- Document Actions -->
                    <div class="form-actions">
                        <form method="post" onsubmit="return confirm('Restore this document to active status?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="restore_document">
                            <input type="hidden" name="document_id" value="<?php echo $document_id; ?>">
                            <button type="submit" class="btn" style="background: #27ae60;">
                                <i class="fas fa-undo"></i> Restore to Active
                            </button>
                        </form>
                        <form method="post" onsubmit="return confirm('Permanently delete this document? This action cannot be undone!');">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="delete_permanently">
                            <input type="hidden" name="document_id" value="<?php echo $document_id; ?>">
                            <button type="submit" class="btn" style="background: #e74c3c;">
                                <i class="fas fa-trash-alt"></i> Delete Permanently
                            </button>
                        </form>
                        <a href="archive.php" class="btn secondary" style="background: #6c757d;">
                            <i class="fas fa-arrow-left"></i> Back to Archive
                        </a>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Archive List View -->
                
                <!-- Filter Bar -->
                <div class="filter-bar">
                    <form method="get" class="filter-form" id="searchForm">
                        <div class="search-box">
                            <input type="text" name="search" 
                                   placeholder="Search archived documents..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit"><i class="fas fa-search"></i></button>
                        </div>
                        <input type="hidden" name="action" value="list">
                    </form>
                    
                    <div class="bulk-actions">
                        <form method="post" id="bulkForm" style="display: none;">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" id="bulkAction" value="">
                            <input type="hidden" name="document_ids[]" id="bulkDocumentIds" value="">
                            
                            <button type="button" class="btn secondary" onclick="performBulkAction('restore')" style="background: #27ae60;">
                                <i class="fas fa-undo"></i> Restore Selected
                            </button>
                            <button type="button" class="btn secondary" onclick="performBulkAction('delete')" style="background: #e74c3c;">
                                <i class="fas fa-trash-alt"></i> Delete Selected
                            </button>
                            <button type="button" class="btn secondary" onclick="clearSelection()" style="background: #6c757d;">
                                <i class="fas fa-times"></i> Clear
                            </button>
                        </form>
                        
                        <button type="button" class="empty-archive-btn" onclick="showEmptyArchiveModal()">
                            <i class="fas fa-broom"></i> Empty Archive
                        </button>
                    </div>
                </div>
                
                <!-- Documents Table -->
                <div class="documents-list">
                    <div class="list-header">
                        <h3 style="margin: 0; color: #2c3e50;">
                            <i class="fas fa-archive"></i> Archived Documents
                            <?php if ($total_documents > 0): ?>
                            <span style="font-size: 0.9rem; color: #666; font-weight: normal;">
                                (<?php echo $total_documents; ?> document<?php echo $total_documents != 1 ? 's' : ''; ?>)
                            </span>
                            <?php endif; ?>
                        </h3>
                        
                        <?php if ($search): ?>
                        <a href="archive.php" class="btn secondary" style="padding: 10px 20px;">
                            <i class="fas fa-times"></i> Clear Search
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($documents_result && mysqli_num_rows($documents_result) > 0): ?>
                    <div class="table-responsive">
                        <table class="documents-table">
                            <thead>
                                <tr>
                                    <th class="select-cell">
                                        <input type="checkbox" class="select-all" onclick="toggleSelectAll(this)">
                                    </th>
                                    <th>Title</th>
                                    <th>Archived On</th>
                                    <th>Size</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($doc = mysqli_fetch_assoc($documents_result)): 
                                    $file_size = $doc['file_path'] && file_exists($doc['file_path']) ? 
                                        format_file_size(filesize($doc['file_path'])) : 'N/A';
                                ?>
                                <tr>
                                    <td class="select-cell">
                                        <input type="checkbox" class="select-doc" 
                                               value="<?php echo $doc['legal_doc_id']; ?>"
                                               onchange="updateBulkActions()">
                                    </td>
                                    <td>
                                        <strong>
                                            <a href="?action=view&id=<?php echo $doc['legal_doc_id']; ?>" 
                                               style="color: #2c3e50; text-decoration: none;">
                                                <?php echo htmlspecialchars($doc['document_title']); ?>
                                            </a>
                                        </strong>
                                        <?php if ($doc['parcel_no']): ?>
                                        <br><small style="color: #666;">Land: <?php echo htmlspecialchars($doc['parcel_no']); ?></small>
                                        <?php endif; ?>
                                        <br><small style="color: #999;">Created: <?php echo date('M d, Y', strtotime($doc['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($doc['updated_at'])); ?><br>
                                        <small style="color: #666;">
                                            <?php 
                                            $archived_date = new DateTime($doc['updated_at']);
                                            $now = new DateTime();
                                            $days = $now->diff($archived_date)->days;
                                            echo "$days days ago";
                                            ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php echo $file_size; ?>
                                        <?php if ($doc['file_path']): ?>
                                        <br><small style="color: #666;"><i class="fas fa-file-pdf"></i> PDF available</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?action=view&id=<?php echo $doc['legal_doc_id']; ?>" 
                                               class="btn-icon btn-view" 
                                               title="View Document">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <form method="post" style="display: inline;" 
                                                  onsubmit="return confirm('Restore this document to active status?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="action" value="restore_document">
                                                <input type="hidden" name="document_id" value="<?php echo $doc['legal_doc_id']; ?>">
                                                <button type="submit" class="btn-icon btn-restore" title="Restore Document">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            </form>
                                            <?php if ($doc['file_path']): ?>
                                            <form method="post" style="display: inline;" 
                                                  onsubmit="return confirm('Download this archived document?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="action" value="download_pdf">
                                                <input type="hidden" name="document_id" value="<?php echo $doc['legal_doc_id']; ?>">
                                                <button type="submit" class="btn-icon btn-download" title="Download PDF">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            <form method="post" style="display: inline;" 
                                                  onsubmit="return confirm('Permanently delete this document? This action cannot be undone!');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="action" value="delete_permanently">
                                                <input type="hidden" name="document_id" value="<?php echo $doc['legal_doc_id']; ?>">
                                                <button type="submit" class="btn-icon btn-delete" title="Delete Permanently">
                                                    <i class="fas fa-trash-alt"></i>
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
                            <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                               class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-archive"></i>
                        <h3>Archive is Empty</h3>
                        <p>
                            <?php if ($search): ?>
                            No archived documents match your search. Try a different search term.
                            <?php else: ?>
                            You don't have any archived documents. Documents you archive will appear here.
                            <?php endif; ?>
                        </p>
                        <a href="my-legal-documents.php" class="btn" style="padding: 15px 30px; font-size: 1.1rem;">
                            <i class="fas fa-arrow-left"></i> Back to Active Documents
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

    <!-- Empty Archive Modal -->
    <div class="modal-overlay" id="emptyArchiveModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle" style="color: #e74c3c;"></i> Empty Archive</h3>
            </div>
            <div class="modal-body">
                <p><strong>Warning:</strong> This action will permanently delete ALL documents in your archive. This action cannot be undone!</p>
                <p>You have <?php echo $total_documents; ?> document(s) in your archive.</p>
                <p>To confirm, type "DELETE ALL" in the box below:</p>
                <input type="text" id="confirmText" class="form-control" placeholder="Type DELETE ALL here" style="margin-top: 15px;">
            </div>
            <div class="modal-actions">
                <form method="post" id="emptyArchiveForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="empty_archive">
                    <input type="hidden" name="confirm" value="">
                    <button type="button" class="btn secondary" onclick="hideEmptyArchiveModal()" style="background: #6c757d;">
                        Cancel
                    </button>
                    <button type="button" class="btn" onclick="confirmEmptyArchive()" style="background: #e74c3c;">
                        <i class="fas fa-broom"></i> Empty Archive
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        document.querySelector('.mobile-menu-btn').addEventListener('click', function() {
            document.querySelector('.nav-links').classList.toggle('show');
        });
        
        // Bulk actions functionality
        let selectedDocs = [];
        
        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.select-doc');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
                updateDocSelection(cb);
            });
            updateBulkActions();
        }
        
        function updateDocSelection(checkbox) {
            const docId = parseInt(checkbox.value);
            if (checkbox.checked) {
                if (!selectedDocs.includes(docId)) {
                    selectedDocs.push(docId);
                }
            } else {
                const index = selectedDocs.indexOf(docId);
                if (index > -1) {
                    selectedDocs.splice(index, 1);
                }
            }
        }
        
        function updateBulkActions() {
            const bulkForm = document.getElementById('bulkForm');
            const checkboxes = document.querySelectorAll('.select-doc');
            const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
            
            if (anyChecked) {
                bulkForm.style.display = 'block';
                // Update hidden input with selected IDs
                document.getElementById('bulkDocumentIds').value = JSON.stringify(selectedDocs);
            } else {
                bulkForm.style.display = 'none';
            }
            
            // Update select-all checkbox
            const selectAll = document.querySelector('.select-all');
            if (selectAll) {
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                const someChecked = Array.from(checkboxes).some(cb => cb.checked);
                selectAll.checked = allChecked;
                selectAll.indeterminate = someChecked && !allChecked;
            }
        }
        
        // Initialize event listeners for checkboxes
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.select-doc');
            checkboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    updateDocSelection(this);
                    updateBulkActions();
                });
                
                // Initialize selection array
                if (cb.checked) {
                    selectedDocs.push(parseInt(cb.value));
                }
            });
            updateBulkActions();
        });
        
        function performBulkAction(action) {
            if (selectedDocs.length === 0) {
                alert('Please select at least one document.');
                return;
            }
            
            let message, actionValue;
            if (action === 'restore') {
                message = `Restore ${selectedDocs.length} document(s) to active status?`;
                actionValue = 'bulk_restore';
            } else if (action === 'delete') {
                message = `Permanently delete ${selectedDocs.length} document(s)? This action cannot be undone!`;
                actionValue = 'bulk_delete';
            }
            
            if (confirm(message)) {
                document.getElementById('bulkAction').value = actionValue;
                document.getElementById('bulkForm').submit();
            }
        }
        
        function clearSelection() {
            selectedDocs = [];
            const checkboxes = document.querySelectorAll('.select-doc');
            checkboxes.forEach(cb => cb.checked = false);
            updateBulkActions();
        }
        
        // Empty archive modal functions
        function showEmptyArchiveModal() {
            if (<?php echo $total_documents; ?> === 0) {
                alert('Archive is already empty.');
                return;
            }
            
            document.getElementById('emptyArchiveModal').style.display = 'flex';
            document.getElementById('confirmText').value = '';
        }
        
        function hideEmptyArchiveModal() {
            document.getElementById('emptyArchiveModal').style.display = 'none';
        }
        
        function confirmEmptyArchive() {
            const confirmText = document.getElementById('confirmText').value;
            if (confirmText !== 'DELETE ALL') {
                alert('Please type "DELETE ALL" exactly as shown to confirm.');
                return;
            }
            
            document.getElementById('emptyArchiveForm').querySelector('input[name="confirm"]').value = 'yes';
            document.getElementById('emptyArchiveForm').submit();
        }
        
        // Close modal when clicking outside
        document.getElementById('emptyArchiveModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideEmptyArchiveModal();
            }
        });
        
        // Auto-submit search on Enter
        document.getElementById('searchForm')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.submit();
            }
        });
        
        // Format file size helper function
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    </script>
</body>
</html>
<?php
// Helper function to format file sizes
if (!function_exists('format_file_size')) {
    function format_file_size($bytes) {
        if ($bytes === 0) return '0 Bytes';
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
}
?>