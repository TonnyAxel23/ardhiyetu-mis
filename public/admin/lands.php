<?php
require_once '../../includes/init.php';
require_admin();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Handle actions
if (isset($_GET['action'])) {
    $land_id = (int)$_GET['id'];
    
    switch ($_GET['action']) {
        case 'view':
            // Display land details
            break;
            
        case 'edit':
            // Handle edit form
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $parcel_no = mysqli_real_escape_string($conn, $_POST['parcel_no']);
                $location = mysqli_real_escape_string($conn, $_POST['location']);
                $size = (float)$_POST['size'];
                $owner_id = (int)$_POST['owner_id'];
                $status = mysqli_real_escape_string($conn, $_POST['status']);
                $original_parcel_no = isset($_POST['original_parcel_no']) ? mysqli_real_escape_string($conn, $_POST['original_parcel_no']) : null;
                $parent_record_id = isset($_POST['parent_record_id']) ? (int)$_POST['parent_record_id'] : null;
                
                $sql = "UPDATE land_records SET parcel_no = ?, location = ?, size = ?, owner_id = ?, status = ?, 
                        original_parcel_no = ?, parent_record_id = ?, updated_at = NOW()
                        WHERE record_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "ssdisssii", $parcel_no, $location, $size, $owner_id, $status, 
                                      $original_parcel_no, $parent_record_id, $land_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    log_activity($_SESSION['user_id'], 'land_update', "Land record $parcel_no updated");
                    flash_message('success', 'Land record updated successfully.');
                } else {
                    flash_message('error', 'Failed to update land record.');
                }
                header('Location: lands.php');
                exit();
            }
            break;
            
        case 'approve':
            $sql = "UPDATE land_records SET status = 'active' WHERE record_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $land_id);
            
            if (mysqli_stmt_execute($stmt)) {
                log_activity($_SESSION['user_id'], 'land_approve', "Land record ID $land_id approved");
                flash_message('success', 'Land record approved successfully.');
            } else {
                flash_message('error', 'Failed to approve land record.');
            }
            header('Location: lands.php');
            exit();
            break;
            
        case 'reject':
            $reason = isset($_GET['reason']) ? mysqli_real_escape_string($conn, $_GET['reason']) : 'Rejected by administrator';
            
            $sql = "UPDATE land_records SET status = 'inactive', rejection_reason = ? WHERE record_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "si", $reason, $land_id);
            
            if (mysqli_stmt_execute($stmt)) {
                log_activity($_SESSION['user_id'], 'land_reject', "Land record ID $land_id rejected");
                flash_message('success', 'Land record rejected.');
            } else {
                flash_message('error', 'Failed to reject land record.');
            }
            header('Location: lands.php');
            exit();
            break;
            
        case 'delete':
            // Check for pending transfers
            $check_sql = "SELECT COUNT(*) as count FROM ownership_transfers 
                         WHERE record_id = ? AND status IN ('submitted', 'under_review')";
            $stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($stmt, "i", $land_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            
            if ($row['count'] > 0) {
                flash_message('error', 'Cannot delete land record with pending transfers.');
            } else {
                // Check if this land has splits
                $check_splits_sql = "SELECT COUNT(*) as split_count FROM land_records WHERE parent_record_id = ?";
                $stmt = mysqli_prepare($conn, $check_splits_sql);
                mysqli_stmt_bind_param($stmt, "i", $land_id);
                mysqli_stmt_execute($stmt);
                $split_result = mysqli_stmt_get_result($stmt);
                $split_row = mysqli_fetch_assoc($split_result);
                
                if ($split_row['split_count'] > 0) {
                    flash_message('error', 'Cannot delete land record that has been split. Delete splits first.');
                } else {
                    $sql = "DELETE FROM land_records WHERE record_id = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "i", $land_id);
                    if (mysqli_stmt_execute($stmt)) {
                        log_activity($_SESSION['user_id'], 'land_delete', "Land record ID $land_id deleted");
                        flash_message('success', 'Land record deleted successfully.');
                    } else {
                        flash_message('error', 'Failed to delete land record.');
                    }
                }
            }
            header('Location: lands.php');
            exit();
            
        case 'view_splits':
            // Redirect to view splits page
            header("Location: view-splits.php?land_id=$land_id");
            exit();
            break;
    }
}

// Build query
$where = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where[] = "(l.parcel_no LIKE ? OR l.location LIKE ? OR u.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($status_filter)) {
    $where[] = "l.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count total lands
$count_sql = "SELECT COUNT(*) as total FROM land_records l 
              LEFT JOIN users u ON l.owner_id = u.user_id 
              $where_clause";
$count_stmt = mysqli_prepare($conn, $count_sql);
if ($params) {
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_lands = mysqli_fetch_assoc($count_result)['total'];

// Pagination
$items_per_page = get_setting('items_per_page', 20);
$pagination = paginate($page, $items_per_page, $total_lands);

// Get lands with owner info, split information, and document info
$sql = "SELECT l.*, u.name as owner_name, u.email as owner_email,
               (SELECT COUNT(*) FROM land_records WHERE parent_record_id = l.record_id) as split_count,
               (SELECT parcel_no FROM land_records WHERE record_id = l.parent_record_id) as parent_parcel,
               d.document_path, d.document_type, d.uploaded_at as document_uploaded
        FROM land_records l 
        LEFT JOIN users u ON l.owner_id = u.user_id 
        LEFT JOIN land_documents d ON l.record_id = d.land_id AND d.is_primary = 1
        $where_clause 
        ORDER BY l.registered_at DESC 
        LIMIT ?, ?";

$types .= 'ii';
$params[] = $pagination['offset'];
$params[] = $pagination['limit'];

$stmt = mysqli_prepare($conn, $sql);
if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$lands = mysqli_stmt_get_result($stmt);

// Get land statistics including split statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
        SUM(CASE WHEN status = 'disputed' THEN 1 ELSE 0 END) as disputed,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'pending_transfer' THEN 1 ELSE 0 END) as pending_transfer,
        SUM(size) as total_size,
        SUM(CASE WHEN parent_record_id IS NOT NULL THEN 1 ELSE 0 END) as split_lands,
        SUM(CASE WHEN original_parcel_no IS NOT NULL THEN 1 ELSE 0 END) as original_lands
    FROM land_records
";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Get all users for owner selection
$users_sql = "SELECT user_id, name, email FROM users ORDER BY name";
$users_result = mysqli_query($conn, $users_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Land Records Management - ArdhiYetu</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        /* Modern Color Palette */
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --warning-gradient: linear-gradient(135deg, #f7971e 0%, #ffd200 100%);
            --danger-gradient: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
            --info-gradient: linear-gradient(135deg, #00c6ff 0%, #0072ff 100%);
            --card-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            --hover-shadow: 0 15px 50px rgba(0, 0, 0, 0.12);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes dropdownSlide {
            0% {
                opacity: 0;
                transform: translateY(-10px) scale(0.95);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 16px;
            width: 90%;
            max-width: 700px;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: var(--card-shadow);
            animation: slideUp 0.4s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f2f5;
            position: relative;
        }
        
        .modal-header::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 80px;
            height: 3px;
            background: var(--primary-gradient);
            border-radius: 3px;
        }
        
        .modal-header h2 {
            margin: 0;
            color: #2d3748;
            font-size: 1.8rem;
            font-weight: 700;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #a0aec0;
            transition: var(--transition);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .close-modal:hover {
            background: #f7fafc;
            color: #4a5568;
            transform: rotate(90deg);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #4a5568;
            font-weight: 600;
            font-size: 14px;
            transition: var(--transition);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: var(--transition);
            background: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-1px);
        }
        
        .form-control::placeholder {
            color: #a0aec0;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #f0f2f5;
        }
        
        .btn {
            padding: 12px 28px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #f7fafc;
            color: #4a5568;
            border: 2px solid #e2e8f0;
        }
        
        .btn-secondary:hover {
            background: #edf2f7;
            border-color: #cbd5e0;
            transform: translateY(-2px);
        }
        
        .land-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .detail-item {
            padding: 20px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 12px;
            border-left: 4px solid #667eea;
            transition: var(--transition);
        }
        
        .detail-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .detail-item label {
            display: block;
            color: #64748b;
            font-size: 12px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        .detail-item span {
            display: block;
            color: #1e293b;
            font-size: 16px;
            font-weight: 600;
        }
        
        .detail-item small {
            display: block;
            color: #64748b;
            font-size: 13px;
            margin-top: 4px;
        }
        
        /* Status Badges - Enhanced */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: var(--transition);
        }
        
        .status-badge i {
            margin-right: 6px;
            font-size: 10px;
        }
        
        .status-active {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            box-shadow: 0 2px 8px rgba(21, 87, 36, 0.1);
        }
        
        .status-inactive {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            box-shadow: 0 2px 8px rgba(114, 28, 36, 0.1);
        }
        
        .status-disputed {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
            box-shadow: 0 2px 8px rgba(133, 100, 4, 0.1);
        }
        
        .status-pending {
            background: linear-gradient(135deg, #cce5ff 0%, #b3d7ff 100%);
            color: #004085;
            box-shadow: 0 2px 8px rgba(0, 64, 133, 0.1);
        }
        
        .status-pending_transfer {
            background: linear-gradient(135deg, #e2e3e5 0%, #d6d8db 100%);
            color: #383d41;
            box-shadow: 0 2px 8px rgba(56, 61, 65, 0.1);
        }
        
        /* Split Badges - Enhanced */
        .split-badge {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            color: #1976d2;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 4px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }
        
        .original-badge {
            background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);
            color: #7b1fa2;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 4px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }
        
        .split-count-badge {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            color: #0c5460;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
            cursor: help;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        /* Document Button Styles */
        .document-cell {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .document-btn {
            padding: 8px 12px;
            background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: var(--transition);
            min-width: 80px;
            justify-content: center;
        }
        
        .document-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(66, 153, 225, 0.3);
        }
        
        .document-btn i {
            font-size: 14px;
        }
        
        .document-type {
            font-size: 11px;
            letter-spacing: 0.5px;
        }
        
        /* Document Preview Modal */
        .document-preview {
            width: 100%;
            height: 600px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        
        .document-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: center;
        }
        
        .document-info {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #4299e1;
        }
        
        .document-info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .document-info-label {
            color: #4a5568;
            font-weight: 600;
        }
        
        .document-info-value {
            color: #2d3748;
        }
        
        /* Table Card - Enhanced */
        .table-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            transition: var(--transition);
            margin-top: 20px;
            border: 1px solid #e2e8f0;
        }

        .table-card:hover {
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.12);
        }

        .table-header {
            padding: 20px 25px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h3 {
            margin: 0;
            color: #2d3748;
            font-size: 16px;
            font-weight: 600;
        }

        .table-header h3 i {
            color: #667eea;
            margin-right: 10px;
        }

        .table-actions {
            display: flex;
            gap: 10px;
        }

        .table-content {
            overflow-x: auto;
            position: relative;
            padding: 5px;
        }

        /* Enhanced Data Table */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            min-width: 1000px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .data-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: sticky;
            top: 0;
            z-index: 20;
        }

        .data-table th {
            padding: 18px 20px;
            text-align: left;
            color: white;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
            white-space: nowrap;
        }

        .data-table th:first-child {
            border-top-left-radius: 12px;
            padding-left: 25px;
        }

        .data-table th:last-child {
            border-top-right-radius: 12px;
            padding-right: 25px;
        }

        .data-table tbody tr {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border-bottom: 1px solid #f1f5f9;
            position: relative;
            background: white;
        }

        .data-table tbody tr:nth-child(even) {
            background: #fafbfd;
        }

        .data-table tbody tr:hover {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.08);
        }

        .data-table td {
            padding: 18px 20px;
            color: #4a5568;
            font-size: 14px;
            border: none;
            vertical-align: middle;
            line-height: 1.5;
        }

        .data-table td:first-child {
            padding-left: 25px;
            font-weight: 600;
            color: #2d3748;
            position: relative;
        }

        /* Add subtle indicator for each row */
        .data-table td:first-child::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: 0 2px 2px 0;
        }

        .data-table tr:hover td:first-child::before {
            opacity: 1;
        }

        .data-table td:last-child {
            padding-right: 25px;
            position: relative;
            overflow: visible;
            z-index: 10;
        }

        /* Improved cell content styling */
        .data-table td strong {
            color: #2d3748;
            font-weight: 600;
        }

        .data-table td small {
            display: block;
            color: #718096;
            font-size: 12px;
            margin-top: 4px;
            line-height: 1.4;
        }

        /* Status badges in table */
        .data-table .status-badge {
            font-size: 11px;
            padding: 5px 12px;
            letter-spacing: 0.3px;
        }

        /* Owner information styling */
        .data-table .owner-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .data-table .owner-name {
            color: #2d3748;
            font-weight: 600;
            font-size: 14px;
        }

        .data-table .owner-email {
            color: #718096;
            font-size: 12px;
        }

        /* Parcel number styling */
        .data-table .parcel-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .data-table .parcel-icon {
            color: #667eea;
            font-size: 16px;
            flex-shrink: 0;
        }

        .data-table .parcel-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .data-table .parcel-number {
            color: #2d3748;
            font-weight: 700;
            font-size: 14px;
            letter-spacing: 0.3px;
        }

        .data-table .parcel-badges {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        /* Size cell styling */
        .data-table .size-cell {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .data-table .size-value {
            color: #2d3748;
            font-weight: 600;
            font-size: 14px;
        }

        /* Date cell styling */
        .data-table .date-cell {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .data-table .date-icon {
            color: #718096;
            font-size: 14px;
        }

        .data-table .date-value {
            color: #4a5568;
            font-size: 13px;
        }

        /* Table footer for summary */
        .table-footer {
            padding: 15px 25px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: #718096;
        }

        .table-footer-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-footer-actions {
            display: flex;
            gap: 10px;
        }

        /* Scrollbar styling for table */
        .table-content::-webkit-scrollbar {
            height: 8px;
            width: 8px;
        }

        .table-content::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }

        .table-content::-webkit-scrollbar-thumb {
            background: #cbd5e0;
            border-radius: 4px;
        }

        .table-content::-webkit-scrollbar-thumb:hover {
            background: #a0aec0;
        }

        /* Empty state in table */
        .data-table .empty-state {
            padding: 60px 20px;
            text-align: center;
            grid-column: 1 / -1;
        }

        .data-table .empty-state i {
            font-size: 48px;
            color: #cbd5e0;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .data-table .empty-state h4 {
            color: #4a5568;
            margin: 0 0 10px 0;
            font-size: 16px;
            font-weight: 600;
        }

        .data-table .empty-state p {
            color: #718096;
            margin: 0;
            font-size: 14px;
        }

        /* Action Dropdown - ENHANCED PROFESSIONAL VERSION */
        .action-dropdown {
            position: relative;
            display: inline-block;
        }

        .btn-small {
            padding: 8px 16px;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            cursor: pointer;
            color: #4a5568;
            font-size: 14px;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            position: relative;
            z-index: 30;
        }

        .btn-small:hover {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-color: #667eea;
            color: #2d3748;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
        }

        .btn-small:active {
            transform: translateY(0);
        }

        /* When dropdown is open, highlight the button */
        .action-dropdown.active .btn-small {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-color: #667eea;
            color: #667eea;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.15);
        }

        /* Dropdown content - POSITIONED PROPERLY */
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: calc(100% + 5px);
            background: white;
            min-width: 240px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            border-radius: 12px;
            z-index: 1000;
            border: 1px solid #e2e8f0;
            animation: dropdownSlide 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.98);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.1),
                0 1px 3px rgba(0, 0, 0, 0.05);
        }

        /* Arrow pointing to button */
        .dropdown-content::before {
            content: '';
            position: absolute;
            top: -6px;
            right: 20px;
            width: 12px;
            height: 12px;
            background: white;
            transform: rotate(45deg);
            border-left: 1px solid rgba(226, 232, 240, 0.8);
            border-top: 1px solid rgba(226, 232, 240, 0.8);
            z-index: 1001;
            box-shadow: -2px -2px 4px rgba(0, 0, 0, 0.05);
        }

        /* Dropdown items */
        .dropdown-content a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            text-decoration: none;
            color: #4a5568;
            border-bottom: 1px solid rgba(241, 245, 249, 0.8);
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 14px;
            font-weight: 500;
            position: relative;
            overflow: hidden;
            background: transparent;
        }

        .dropdown-content a:last-child {
            border-bottom: none;
        }

        .dropdown-content a:hover {
            background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
            padding-left: 25px;
            color: #2d3748;
            transform: translateX(2px);
        }

        /* Add subtle hover effect */
        .dropdown-content a::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: var(--primary-gradient);
            transform: translateX(-4px);
            transition: transform 0.2s ease;
        }

        .dropdown-content a:hover::before {
            transform: translateX(0);
        }

        .dropdown-content i {
            width: 20px;
            text-align: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        /* Action type colors */
        .action-success {
            color: #059669 !important;
        }

        .action-success i {
            color: #10b981;
        }

        .action-danger {
            color: #dc2626 !important;
        }

        .action-danger i {
            color: #ef4444;
        }

        .action-warning {
            color: #d97706 !important;
        }

        .action-warning i {
            color: #f59e0b;
        }

        .action-info {
            color: #2563eb !important;
        }

        .action-info i {
            color: #3b82f6;
        }

        /* Dropdown header for grouped actions */
        .dropdown-header {
            padding: 12px 20px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: 600;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        /* Divider between sections */
        .dropdown-divider {
            height: 1px;
            background: linear-gradient(90deg, 
                transparent 0%, 
                rgba(203, 213, 224, 0.5) 50%, 
                transparent 100%);
            margin: 8px 20px;
        }
        
        /* Statistics Cards - Enhanced */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-mini {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            text-align: center;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .stat-mini::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }
        
        .stat-mini:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }
        
        .stat-mini.special::before {
            background: var(--warning-gradient);
        }
        
        .stat-mini h4 {
            font-size: 32px;
            margin: 0 0 10px 0;
            color: #2d3748;
            font-weight: 700;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-mini.special h4 {
            background: var(--warning-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-mini p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Filters Section - Enhanced */
        .filters {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            margin-bottom: 25px;
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            align-items: end;
        }
        
        /* Pagination - Enhanced */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
            padding: 20px;
        }
        
        .page-link {
            padding: 12px 18px;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            color: #4a5568;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 45px;
        }
        
        .page-link:hover {
            background: #f7fafc;
            border-color: #cbd5e0;
            transform: translateY(-2px);
        }
        
        .page-link.active {
            background: var(--primary-gradient);
            color: white;
            border-color: transparent;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        /* Search Highlight */
        .highlight {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            padding: 2px 4px;
            border-radius: 4px;
            color: #856404;
            font-weight: 600;
        }
        
        /* Floating Action Button */
        .fab {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary-gradient);
            color: white;
            border: none;
            cursor: pointer;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
            font-size: 24px;
            transition: var(--transition);
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .fab:hover {
            transform: scale(1.1) rotate(90deg);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.6);
        }
        
        /* Quick Actions Bar */
        .quick-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .quick-action-btn {
            padding: 10px 20px;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            color: #4a5568;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .quick-action-btn:hover {
            background: #f7fafc;
            border-color: #cbd5e0;
            transform: translateY(-2px);
        }
        
        .quick-action-btn.active {
            background: var(--primary-gradient);
            color: white;
            border-color: transparent;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        /* Tooltip */
        [data-tooltip] {
            position: relative;
        }
        
        [data-tooltip]::before {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            padding: 8px 12px;
            background: #2d3748;
            color: white;
            font-size: 12px;
            border-radius: 6px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
            z-index: 1000;
            pointer-events: none;
        }
        
        [data-tooltip]::after {
            content: '';
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-top-color: #2d3748;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
            z-index: 1000;
            pointer-events: none;
        }
        
        [data-tooltip]:hover::before,
        [data-tooltip]:hover::after {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(-8px);
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .modal-content {
                margin: 10% auto;
                width: 95%;
                padding: 20px;
            }
            
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .data-table {
                min-width: 800px;
            }
            
            .data-table th,
            .data-table td {
                padding: 12px 15px;
                font-size: 13px;
            }
            
            .table-header {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .table-header h3 {
                text-align: center;
            }
            
            .table-actions {
                justify-content: center;
            }
            
            /* Make action button more compact on mobile */
            .btn-small span {
                display: none;
            }
            
            .btn-small {
                padding: 8px 12px;
                min-width: 40px;
                justify-content: center;
            }
            
            .btn-small i {
                margin: 0;
            }
            
            /* Adjust dropdown position on mobile */
            .dropdown-content {
                position: fixed;
                right: 10px;
                left: 10px;
                top: auto;
                bottom: 0;
                min-width: auto;
                width: calc(100% - 20px);
                border-radius: 16px 16px 0 0;
                animation: slideUp 0.3s ease;
                max-height: 70vh;
                overflow-y: auto;
            }
            
            .dropdown-content::before {
                display: none;
            }
            
            /* Make dropdown items more touch-friendly */
            .dropdown-content a {
                padding: 18px 20px;
                font-size: 15px;
            }
            
            .dropdown-content i {
                font-size: 18px;
            }
            
            .table-footer {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .document-preview {
                height: 400px;
            }
        }
        
        @media (max-width: 480px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .quick-actions {
                flex-direction: column;
            }
            
            .quick-action-btn {
                width: 100%;
                justify-content: center;
            }
            
            .document-preview {
                height: 300px;
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
                    <h1><i class="fas fa-landmark"></i> Land Records</h1>
                    <p>Manage land parcels and ownership information</p>
                </div>
                <div class="header-right">
                    <button class="btn btn-primary" onclick="openAddModal()" data-tooltip="Add new land record">
                        <i class="fas fa-plus"></i> Add Land
                    </button>
                </div>
            </header>

            <div class="admin-content">
                <!-- Flash Messages -->
                <?php if (isset($_SESSION['flash'])): ?>
                    <?php foreach ($_SESSION['flash'] as $type => $message): ?>
                        <div class="alert alert-<?php echo $type; ?> animate__animated animate__fadeIn">
                            <i class="fas fa-<?php echo $type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                            <?php echo $message; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php unset($_SESSION['flash']); ?>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="stats-cards">
                    <div class="stat-mini">
                        <h4><?php echo $stats['total']; ?></h4>
                        <p>Total Lands</p>
                        <i class="fas fa-layer-group" style="position: absolute; right: 20px; top: 25px; font-size: 24px; opacity: 0.1;"></i>
                    </div>
                    <div class="stat-mini">
                        <h4><?php echo $stats['active']; ?></h4>
                        <p>Active</p>
                        <i class="fas fa-check-circle" style="position: absolute; right: 20px; top: 25px; font-size: 24px; opacity: 0.1; color: #10b981;"></i>
                    </div>
                    <div class="stat-mini">
                        <h4><?php echo $stats['inactive']; ?></h4>
                        <p>Inactive</p>
                        <i class="fas fa-times-circle" style="position: absolute; right: 20px; top: 25px; font-size: 24px; opacity: 0.1; color: #ef4444;"></i>
                    </div>
                    <div class="stat-mini">
                        <h4><?php echo $stats['disputed']; ?></h4>
                        <p>Disputed</p>
                        <i class="fas fa-exclamation-triangle" style="position: absolute; right: 20px; top: 25px; font-size: 24px; opacity: 0.1; color: #f59e0b;"></i>
                    </div>
                    <div class="stat-mini">
                        <h4><?php echo $stats['pending']; ?></h4>
                        <p>Pending</p>
                        <i class="fas fa-clock" style="position: absolute; right: 20px; top: 25px; font-size: 24px; opacity: 0.1; color: #3b82f6;"></i>
                    </div>
                    <div class="stat-mini">
                        <h4><?php echo number_format($stats['total_size'], 2); ?></h4>
                        <p>Total Size (acres)</p>
                        <i class="fas fa-expand-arrows-alt" style="position: absolute; right: 20px; top: 25px; font-size: 24px; opacity: 0.1;"></i>
                    </div>
                    <div class="stat-mini special">
                        <h4><?php echo $stats['split_lands']; ?></h4>
                        <p>Split Lands</p>
                        <i class="fas fa-code-branch" style="position: absolute; right: 20px; top: 25px; font-size: 24px; opacity: 0.1;"></i>
                    </div>
                    <div class="stat-mini special">
                        <h4><?php echo $stats['original_lands']; ?></h4>
                        <p>Original Lands</p>
                        <i class="fas fa-seedling" style="position: absolute; right: 20px; top: 25px; font-size: 24px; opacity: 0.1;"></i>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <button class="quick-action-btn" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Add New
                    </button>
                    <button class="quick-action-btn" onclick="exportToCSV()">
                        <i class="fas fa-file-export"></i> Export CSV
                    </button>
                    <button class="quick-action-btn" onclick="printTable()">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <button class="quick-action-btn" onclick="refreshPage()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>

                <!-- Filters -->
                <div class="filters animate__animated animate__fadeIn">
                    <form method="GET" class="filter-form" id="filterForm">
                        <div class="form-group">
                            <label for="search"><i class="fas fa-search"></i> Search</label>
                            <input type="text" id="search" name="search" class="form-control" 
                                   placeholder="Search by parcel no, location or owner..." 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   oninput="debouncedSearch()">
                        </div>
                        
                        <div class="form-group">
                            <label for="status"><i class="fas fa-filter"></i> Status</label>
                            <select id="status" name="status" class="form-control" onchange="document.getElementById('filterForm').submit()">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="disputed" <?php echo $status_filter === 'disputed' ? 'selected' : ''; ?>>Disputed</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="pending_transfer" <?php echo $status_filter === 'pending_transfer' ? 'selected' : ''; ?>>Pending Transfer</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <div style="display: flex; gap: 10px;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Apply Filters
                                </button>
                                <a href="lands.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Results Summary -->
                <div style="margin: 15px 0; color: #64748b; font-size: 14px;">
                    <i class="fas fa-info-circle"></i>
                    Showing <?php echo min($pagination['offset'] + 1, $total_lands); ?>-<?php echo min($pagination['offset'] + $pagination['limit'], $total_lands); ?> 
                    of <?php echo $total_lands; ?> land records
                    <?php if ($search): ?>
                        <span class="highlight">for "<?php echo htmlspecialchars($search); ?>"</span>
                    <?php endif; ?>
                    <?php if ($status_filter): ?>
                        <span class="status-badge status-<?php echo $status_filter; ?>" style="margin-left: 10px;">
                            <?php echo ucfirst(str_replace('_', ' ', $status_filter)); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Lands Table - PROFESSIONAL VERSION -->
                <div class="table-card animate__animated animate__fadeInUp">
                    <div class="table-header">
                        <h3><i class="fas fa-table"></i> Land Records List</h3>
                        <div class="table-actions">
                            <button class="btn-small" onclick="exportToCSV()">
                                <i class="fas fa-file-export"></i> Export
                            </button>
                            <button class="btn-small" onclick="printTable()">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-content">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Parcel No</th>
                                    <th>Location</th>
                                    <th>Size (acres)</th>
                                    <th>Owner</th>
                                    <th>Registered</th>
                                    <th>Status</th>
                                    <th>Documents</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($lands) > 0): ?>
                                    <?php mysqli_data_seek($lands, 0); // Reset pointer ?>
                                    <?php while ($land = mysqli_fetch_assoc($lands)): ?>
                                        <?php 
                                        // Get transfer info if pending_transfer
                                        $transfer_info = null;
                                        if ($land['status'] == 'pending_transfer') {
                                            $transfer_sql = "SELECT transfer_id FROM ownership_transfers 
                                                           WHERE record_id = ? AND status = 'submitted' 
                                                           ORDER BY submitted_at DESC LIMIT 1";
                                            $stmt = mysqli_prepare($conn, $transfer_sql);
                                            mysqli_stmt_bind_param($stmt, "i", $land['record_id']);
                                            mysqli_stmt_execute($stmt);
                                            $transfer_result = mysqli_stmt_get_result($stmt);
                                            $transfer_info = mysqli_fetch_assoc($transfer_result);
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="parcel-cell">
                                                    <i class="fas fa-map-marker-alt parcel-icon"></i>
                                                    <div class="parcel-info">
                                                        <span class="parcel-number"><?php echo htmlspecialchars($land['parcel_no']); ?></span>
                                                        <div class="parcel-badges">
                                                            <?php if ($land['parent_parcel']): ?>
                                                                <span class="split-badge" data-tooltip="Split from <?php echo $land['parent_parcel']; ?>">
                                                                    <i class="fas fa-code-branch"></i> Split
                                                                </span>
                                                            <?php endif; ?>
                                                            <?php if ($land['original_parcel_no']): ?>
                                                                <span class="original-badge" data-tooltip="Original parcel: <?php echo $land['original_parcel_no']; ?>">
                                                                    <i class="fas fa-seedling"></i> Original
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($land['location']); ?></strong>
                                            </td>
                                            <td>
                                                <div class="size-cell">
                                                    <span class="size-value"><?php echo number_format($land['size'], 2); ?></span>
                                                    <?php if ($land['split_count'] > 0): ?>
                                                        <span class="split-count-badge" data-tooltip="<?php echo $land['split_count']; ?> split(s) exist">
                                                            <i class="fas fa-sitemap"></i> <?php echo $land['split_count']; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="owner-info">
                                                    <span class="owner-name"><?php echo htmlspecialchars($land['owner_name']); ?></span>
                                                    <span class="owner-email"><?php echo htmlspecialchars($land['owner_email']); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="date-cell">
                                                    <i class="fas fa-calendar-alt date-icon"></i>
                                                    <span class="date-value"><?php echo date('M d, Y', strtotime($land['registered_at'])); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <?php 
                                                $status_icons = [
                                                    'active' => 'check-circle',
                                                    'inactive' => 'times-circle',
                                                    'disputed' => 'exclamation-triangle',
                                                    'pending' => 'clock',
                                                    'pending_transfer' => 'exchange-alt'
                                                ];
                                                $status_text = ucfirst(str_replace('_', ' ', $land['status']));
                                                ?>
                                                <span class="status-badge status-<?php echo $land['status']; ?>">
                                                    <i class="fas fa-<?php echo $status_icons[$land['status']] ?? 'circle'; ?>"></i>
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($land['document_path'])): ?>
                                                    <div class="document-cell">
                                                        <button class="document-btn" onclick="openDocumentModal(<?php echo $land['record_id']; ?>)" 
                                                                data-tooltip="View <?php echo htmlspecialchars($land['document_type'] ?? 'document'); ?>">
                                                            <i class="fas fa-file-alt"></i>
                                                            <span class="document-type">
                                                                <?php echo strtoupper(pathinfo($land['document_path'], PATHINFO_EXTENSION)); ?>
                                                            </span>
                                                        </button>
                                                        <?php if ($land['document_type']): ?>
                                                            <small style="display: block; margin-top: 4px; color: #64748b;">
                                                                <?php echo htmlspecialchars($land['document_type']); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: #a0aec0; font-size: 12px;">No documents</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="action-dropdown">
                                                    <button class="btn-small" data-tooltip="Actions">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                        <span>Actions</span>
                                                    </button>
                                                    <div class="dropdown-content">
                                                        <a href="#" onclick="openViewModal(<?php echo $land['record_id']; ?>)" class="action-info">
                                                            <i class="fas fa-eye"></i> View Details
                                                        </a>
                                                        <a href="../user/land-history.php?id=<?php echo $land['record_id']; ?>" target="_blank" class="action-info">
                                                            <i class="fas fa-history"></i> View History
                                                        </a>
                                                        <a href="add-land-history.php?land_id=<?php echo $land['record_id']; ?>" class="action-info">
                                                            <i class="fas fa-plus-circle"></i> Add History
                                                        </a>
                                                        <?php if (!empty($land['document_path'])): ?>
                                                            <a href="#" onclick="openDocumentModal(<?php echo $land['record_id']; ?>)" class="action-info">
                                                                <i class="fas fa-file-pdf"></i> View Documents
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if ($land['split_count'] > 0): ?>
                                                            <a href="#" onclick="openSplitInfoModal(<?php echo $land['record_id']; ?>)" class="action-info">
                                                                <i class="fas fa-sitemap"></i> View Splits
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if ($land['status'] === 'pending'): ?>
                                                            <a href="lands.php?action=approve&id=<?php echo $land['record_id']; ?>" 
                                                               onclick="return confirm('Approve this land registration?')"
                                                               class="action-success">
                                                                <i class="fas fa-check-circle"></i> Approve
                                                            </a>
                                                            <a href="#" onclick="openRejectModal(<?php echo $land['record_id']; ?>)" 
                                                               class="action-danger">
                                                                <i class="fas fa-times-circle"></i> Reject
                                                            </a>
                                                        <?php elseif ($land['status'] === 'pending_transfer' && $transfer_info): ?>
                                                            <a href="review-partial-transfer.php?id=<?php echo $transfer_info['transfer_id']; ?>" 
                                                               class="action-warning">
                                                                <i class="fas fa-exchange-alt"></i> Review Transfer
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="#" onclick="openEditModal(<?php echo $land['record_id']; ?>)" class="action-info">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </a>
                                                            <a href="lands.php?action=delete&id=<?php echo $land['record_id']; ?>" 
                                                               onclick="return confirmDelete(<?php echo $land['record_id']; ?>, '<?php echo addslashes($land['parcel_no']); ?>')"
                                                               class="action-danger">
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
                                        <td colspan="8">
                                            <div class="empty-state">
                                                <i class="fas fa-landmark"></i>
                                                <h4>No Land Records Found</h4>
                                                <p><?php echo $search || $status_filter ? 'Try adjusting your search or filter criteria' : 'Add your first land record to get started'; ?></p>
                                                <?php if (!$search && !$status_filter): ?>
                                                    <button class="btn btn-primary" onclick="openAddModal()" style="margin-top: 15px;">
                                                        <i class="fas fa-plus"></i> Add First Land
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (mysqli_num_rows($lands) > 0): ?>
                    <div class="table-footer">
                        <div class="table-footer-info">
                            <i class="fas fa-info-circle"></i>
                            <span>Showing <?php echo min($pagination['offset'] + 1, $total_lands); ?>-<?php echo min($pagination['offset'] + $pagination['limit'], $total_lands); ?> of <?php echo $total_lands; ?> records</span>
                        </div>
                        <div class="table-footer-actions">
                            <button class="btn-small" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">
                                <i class="fas fa-arrow-up"></i> Top
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="pagination animate__animated animate__fadeIn">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status_filter ? '&status=' . $status_filter : ''; ?>" 
                               class="page-link" data-tooltip="Previous Page">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($pagination['total_pages'], $page + 2);
                        
                        if ($start_page > 1): ?>
                            <a href="?page=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status_filter ? '&status=' . $status_filter : ''; ?>" 
                               class="page-link">
                                1
                            </a>
                            <?php if ($start_page > 2): ?>
                                <span class="page-link" style="background: transparent; border: none; cursor: default;">...</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status_filter ? '&status=' . $status_filter : ''; ?>" 
                               class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($end_page < $pagination['total_pages']): ?>
                            <?php if ($end_page < $pagination['total_pages'] - 1): ?>
                                <span class="page-link" style="background: transparent; border: none; cursor: default;">...</span>
                            <?php endif; ?>
                            <a href="?page=<?php echo $pagination['total_pages']; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status_filter ? '&status=' . $status_filter : ''; ?>" 
                               class="page-link">
                                <?php echo $pagination['total_pages']; ?>
                            </a>
                        <?php endif; ?>

                        <?php if ($page < $pagination['total_pages']): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status_filter ? '&status=' . $status_filter : ''; ?>" 
                               class="page-link" data-tooltip="Next Page">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Floating Action Button -->
    <button class="fab" onclick="openAddModal()" data-tooltip="Add New Land Record">
        <i class="fas fa-plus"></i>
    </button>

    <!-- Add/Edit Modal -->
    <div id="landModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add Land Record</h2>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <form id="landForm" method="POST" action="">
                <input type="hidden" name="record_id" id="record_id">
                <input type="hidden" name="action" value="edit">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="parcel_no"><i class="fas fa-hashtag"></i> Parcel Number *</label>
                        <input type="text" id="parcel_no" name="parcel_no" class="form-control" required 
                               placeholder="Enter parcel number">
                    </div>
                    <div class="form-group">
                        <label for="size"><i class="fas fa-expand-arrows-alt"></i> Size (acres) *</label>
                        <input type="number" id="size" name="size" class="form-control" step="0.01" min="0.01" required 
                               placeholder="0.00">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="location"><i class="fas fa-map-marker-alt"></i> Location *</label>
                        <input type="text" id="location" name="location" class="form-control" required 
                               placeholder="Enter location">
                    </div>
                    <div class="form-group">
                        <label for="owner_id"><i class="fas fa-user"></i> Owner *</label>
                        <select id="owner_id" name="owner_id" class="form-control" required>
                            <option value="">Select Owner</option>
                            <?php 
                            // Reset pointer for users_result
                            mysqli_data_seek($users_result, 0);
                            while ($user = mysqli_fetch_assoc($users_result)): 
                            ?>
                                <option value="<?php echo $user['user_id']; ?>">
                                    <?php echo htmlspecialchars($user['name'] . ' (' . $user['email'] . ')'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="status"><i class="fas fa-tag"></i> Status *</label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="pending">Pending</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="disputed">Disputed</option>
                            <option value="pending_transfer">Pending Transfer</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="original_parcel_no"><i class="fas fa-seedling"></i> Original Parcel (if split)</label>
                        <input type="text" id="original_parcel_no" name="original_parcel_no" class="form-control" 
                               placeholder="Original parcel number">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="parent_record_id"><i class="fas fa-code-branch"></i> Parent Record ID (if split)</label>
                        <input type="number" id="parent_record_id" name="parent_record_id" class="form-control" 
                               placeholder="Parent land record ID">
                    </div>
                    <div class="form-group">
                        <label for="description"><i class="fas fa-align-left"></i> Description (Optional)</label>
                        <textarea id="description" name="description" class="form-control" rows="3" 
                                  placeholder="Enter additional details"></textarea>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> Save Land Record
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Land Record Details</h2>
                <button class="close-modal" onclick="closeViewModal()">&times;</button>
            </div>
            <div id="landDetails"></div>
        </div>
    </div>

    <!-- Split Info Modal -->
    <div id="splitInfoModal" class="modal">
        <div class="modal-content split-info-modal">
            <div class="modal-header">
                <h2>Land Split Information</h2>
                <button class="close-modal" onclick="closeSplitInfoModal()">&times;</button>
            </div>
            <div id="splitInfoContent"></div>
        </div>
    </div>

    <!-- Document Preview Modal -->
    <div id="documentModal" class="modal">
        <div class="modal-content" style="max-width: 900px;">
            <div class="modal-header">
                <h2>Land Documents</h2>
                <button class="close-modal" onclick="closeDocumentModal()">&times;</button>
            </div>
            <div class="document-info">
                <div class="document-info-item">
                    <span class="document-info-label">Parcel Number:</span>
                    <span class="document-info-value" id="docParcelNo"></span>
                </div>
                <div class="document-info-item">
                    <span class="document-info-label">Location:</span>
                    <span class="document-info-value" id="docLocation"></span>
                </div>
                <div class="document-info-item">
                    <span class="document-info-label">Owner:</span>
                    <span class="document-info-value" id="docOwner"></span>
                </div>
            </div>
            <div id="documentContent">
                <!-- Document preview will be loaded here -->
            </div>
            <div id="allDocuments" style="display: none;">
                <h3 style="margin: 20px 0 10px 0; color: #2d3748;">All Documents</h3>
                <div id="documentList" class="document-list"></div>
            </div>
            <div class="form-actions">
                <button class="btn-secondary" onclick="closeDocumentModal()">
                    <i class="fas fa-times"></i> Close
                </button>
                <button class="btn btn-primary" onclick="downloadDocument()">
                    <i class="fas fa-download"></i> Download
                </button>
                <button class="btn btn-primary" onclick="viewAllDocuments()" id="viewAllBtn">
                    <i class="fas fa-folder-open"></i> View All Documents
                </button>
            </div>
        </div>
    </div>

    <script>
        // Debounce search function
        let searchTimeout;
        function debouncedSearch() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                document.getElementById('filterForm').submit();
            }, 500);
        }

        // Enhanced modal functions
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Land Record';
            document.getElementById('landForm').reset();
            document.getElementById('record_id').value = '';
            document.getElementById('status').value = 'pending';
            document.getElementById('landForm').action = 'lands.php?action=add';
            document.getElementById('landModal').style.display = 'block';
            document.getElementById('parcel_no').focus();
        }
        
        function openEditModal(recordId) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = '<span class="loading"></span> Loading...';
            submitBtn.disabled = true;
            
            fetch(`api/get_land.php?id=${recordId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('modalTitle').textContent = 'Edit Land Record';
                    document.getElementById('record_id').value = data.record_id;
                    document.getElementById('parcel_no').value = data.parcel_no;
                    document.getElementById('location').value = data.location;
                    document.getElementById('size').value = data.size;
                    document.getElementById('owner_id').value = data.owner_id;
                    document.getElementById('status').value = data.status;
                    document.getElementById('original_parcel_no').value = data.original_parcel_no || '';
                    document.getElementById('parent_record_id').value = data.parent_record_id || '';
                    document.getElementById('description').value = data.description || '';
                    document.getElementById('landForm').action = 'lands.php?action=edit&id=' + recordId;
                    document.getElementById('landModal').style.display = 'block';
                    
                    submitBtn.innerHTML = '<i class="fas fa-save"></i> Update Land Record';
                    submitBtn.disabled = false;
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error loading land data. Please try again.', 'error');
                    submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Land Record';
                    submitBtn.disabled = false;
                });
        }
        
        function openViewModal(recordId) {
            fetch(`api/get_land.php?id=${recordId}`)
                .then(response => response.json())
                .then(data => {
                    const statusIcons = {
                        'active': 'check-circle text-green-500',
                        'inactive': 'times-circle text-red-500',
                        'disputed': 'exclamation-triangle text-yellow-500',
                        'pending': 'clock text-blue-500',
                        'pending_transfer': 'exchange-alt text-gray-500'
                    };
                    
                    let html = `
                        <div class="land-details">
                            <div class="detail-item">
                                <label>Parcel Number</label>
                                <span><i class="fas fa-hashtag"></i> ${data.parcel_no}</span>
                            </div>
                            <div class="detail-item">
                                <label>Location</label>
                                <span><i class="fas fa-map-marker-alt"></i> ${data.location}</span>
                            </div>
                            <div class="detail-item">
                                <label>Size</label>
                                <span><i class="fas fa-expand-arrows-alt"></i> ${data.size} acres</span>
                            </div>
                            <div class="detail-item">
                                <label>Owner</label>
                                <span><i class="fas fa-user"></i> ${data.owner_name}</span>
                                <small><i class="fas fa-envelope"></i> ${data.owner_email}</small>
                            </div>
                            <div class="detail-item">
                                <label>Status</label>
                                <span class="status-badge status-${data.status}">
                                    <i class="fas fa-${statusIcons[data.status].split(' ')[0]}"></i>
                                    ${data.status.charAt(0).toUpperCase() + data.status.slice(1)}
                                </span>
                            </div>
                            <div class="detail-item">
                                <label>Registered</label>
                                <span><i class="fas fa-calendar-alt"></i> ${new Date(data.registered_at).toLocaleDateString()}</span>
                            </div>
                            ${data.original_parcel_no ? `
                            <div class="detail-item">
                                <label>Original Parcel</label>
                                <span><i class="fas fa-seedling"></i> ${data.original_parcel_no}</span>
                            </div>
                            ` : ''}
                            ${data.parent_record_id ? `
                            <div class="detail-item">
                                <label>Parent Record ID</label>
                                <span><i class="fas fa-code-branch"></i> ${data.parent_record_id}</span>
                            </div>
                            ` : ''}
                        </div>
                        ${data.description ? `
                        <div class="detail-item">
                            <label>Description</label>
                            <p style="margin-top: 10px; color: #4a5568; line-height: 1.6;">${data.description}</p>
                        </div>
                        ` : ''}
                        ${data.rejection_reason ? `
                        <div class="detail-item" style="border-left-color: #ef4444;">
                            <label>Rejection Reason</label>
                            <span style="color: #7f1d1d;"><i class="fas fa-exclamation-circle"></i> ${data.rejection_reason}</span>
                        </div>
                        ` : ''}
                        <div class="form-actions" style="justify-content: space-between; margin-top: 30px;">
                            <div>
                                <a href="#" onclick="openEditModal(${data.record_id}); closeViewModal();" class="btn btn-primary">
                                    <i class="fas fa-edit"></i> Edit Record
                                </a>
                            </div>
                            <div>
                                <button class="btn-secondary" onclick="closeViewModal()">
                                    <i class="fas fa-times"></i> Close
                                </button>
                            </div>
                        </div>
                    `;
                    document.getElementById('landDetails').innerHTML = html;
                    document.getElementById('viewModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error loading land details. Please try again.', 'error');
                });
        }
        
        function openSplitInfoModal(recordId) {
            fetch(`api/get_land_splits.php?id=${recordId}`)
                .then(response => response.json())
                .then(data => {
                    let html = `
                        <div class="land-details">
                            <div class="detail-item">
                                <label>Original Parcel</label>
                                <span><i class="fas fa-hashtag"></i> ${data.original.parcel_no}</span>
                            </div>
                            <div class="detail-item">
                                <label>Original Size</label>
                                <span><i class="fas fa-expand-arrows-alt"></i> ${data.original.size} acres</span>
                            </div>
                            <div class="detail-item">
                                <label>Location</label>
                                <span><i class="fas fa-map-marker-alt"></i> ${data.original.location}</span>
                            </div>
                            <div class="detail-item">
                                <label>Owner</label>
                                <span><i class="fas fa-user"></i> ${data.original.owner_name}</span>
                            </div>
                        </div>
                        
                        <h3 style="margin: 25px 0 15px 0; color: #2d3748;">Split Visualization</h3>
                        <div class="split-visual" style="height: 80px; border-radius: 12px; overflow: hidden; border: 2px solid #e2e8f0; background: #f8fafc; display: flex;">
                    `;
                    
                    let totalSplitSize = 0;
                    data.splits.forEach(split => {
                        totalSplitSize += parseFloat(split.size);
                    });
                    
                    const remainingSize = data.original.size - totalSplitSize;
                    
                    if (remainingSize > 0) {
                        const remainingPercent = (remainingSize / data.original.size * 100);
                        html += `
                            <div class="original-part" style="width: ${remainingPercent}%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-size: 12px; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                <strong>${remainingSize.toFixed(2)} acres</strong>
                                <small>Remaining</small>
                            </div>
                        `;
                    }
                    
                    const colors = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
                    data.splits.forEach((split, index) => {
                        const percentage = (split.size / data.original.size * 100);
                        const color = colors[index % colors.length];
                        html += `
                            <div class="split-part" style="width: ${percentage}%; background: ${color}; color: white; font-size: 12px; display: flex; flex-direction: column; justify-content: center; align-items: center; border-left: 2px solid white;">
                                <strong>${split.size} acres</strong>
                                <small>${split.parcel_no}</small>
                            </div>
                        `;
                    });
                    
                    html += `</div>`;
                    
                    if (data.splits.length > 0) {
                        html += `
                            <div class="split-history">
                                <h3 style="margin: 30px 0 15px 0; color: #2d3748; border-bottom: 2px solid #f0f2f5; padding-bottom: 10px;">
                                    <i class="fas fa-sitemap"></i> Split History (${data.splits.length})
                                </h3>
                                <div style="overflow-x: auto;">
                                    <table style="width: 100%; border-collapse: collapse;">
                                        <thead>
                                            <tr style="background: #f8fafc;">
                                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Parcel No</th>
                                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Size (acres)</th>
                                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Owner</th>
                                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Status</th>
                                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Created</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                        `;
                        
                        data.splits.forEach(split => {
                            html += `
                                <tr>
                                    <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;"><strong>${split.parcel_no}</strong></td>
                                    <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">${split.size}</td>
                                    <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">${split.owner_name}</td>
                                    <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;"><span class="status-badge status-${split.status}">${split.status}</span></td>
                                    <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">${new Date(split.registered_at).toLocaleDateString()}</td>
                                </tr>
                            `;
                        });
                        
                        html += `
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        `;
                    } else {
                        html += `
                            <div class="empty-state" style="margin: 40px 0; text-align: center;">
                                <i class="fas fa-info-circle fa-2x" style="color: #cbd5e0;"></i>
                                <p style="color: #718096; margin-top: 10px;">No splits found for this land parcel</p>
                            </div>
                        `;
                    }
                    
                    html += `
                        <div class="form-actions" style="margin-top: 30px;">
                            <button class="btn-secondary" onclick="closeSplitInfoModal()">
                                <i class="fas fa-times"></i> Close
                            </button>
                        </div>
                    `;
                    
                    document.getElementById('splitInfoContent').innerHTML = html;
                    document.getElementById('splitInfoModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error loading split information. Please try again.', 'error');
                });
        }
        
        // Document viewing functions
        function openDocumentModal(recordId) {
            // First, get land details
            fetch(`api/get_land.php?id=${recordId}`)
                .then(response => response.json())
                .then(data => {
                    // Update modal info
                    document.getElementById('docParcelNo').textContent = data.parcel_no;
                    document.getElementById('docLocation').textContent = data.location;
                    document.getElementById('docOwner').textContent = data.owner_name;
                    
                    // Store current land ID for later use
                    document.getElementById('documentModal').dataset.landId = recordId;
                    
                    // Load the primary document
                    loadPrimaryDocument(recordId);
                    
                    // Show modal
                    document.getElementById('documentModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error loading land information.', 'error');
                });
        }

        function loadPrimaryDocument(recordId) {
            // Fetch document information
            fetch(`api/get_land_documents.php?id=${recordId}`)
                .then(response => response.json())
                .then(documents => {
                    const contentDiv = document.getElementById('documentContent');
                    
                    if (documents && documents.length > 0) {
                        const primaryDoc = documents.find(doc => doc.is_primary) || documents[0];
                        
                        // Determine file type and display accordingly
                        const ext = primaryDoc.document_path.split('.').pop().toLowerCase();
                        let html = '';
                        
                        if (['jpg', 'jpeg', 'png', 'gif', 'bmp'].includes(ext)) {
                            html = `
                                <div class="document-preview">
                                    <img src="../../uploads/${primaryDoc.document_path}" 
                                         alt="${primaryDoc.document_type || 'Document'}" 
                                         style="width: 100%; height: 100%; object-fit: contain; padding: 20px;">
                                </div>
                            `;
                        } else if (ext === 'pdf') {
                            html = `
                                <div class="document-preview">
                                    <iframe src="../../uploads/${primaryDoc.document_path}#toolbar=0" 
                                            style="width: 100%; height: 100%; border: none;"></iframe>
                                </div>
                            `;
                        } else {
                            html = `
                                <div style="text-align: center; padding: 40px;">
                                    <i class="fas fa-file fa-4x" style="color: #4299e1;"></i>
                                    <h3 style="margin-top: 20px;">${primaryDoc.document_type || 'Document'}</h3>
                                    <p style="color: #718096;">File type: ${ext.toUpperCase()}</p>
                                </div>
                            `;
                        }
                        
                        contentDiv.innerHTML = html;
                        
                        // Store current document path for download
                        document.getElementById('documentModal').dataset.currentDoc = primaryDoc.document_path;
                        
                        // Load all documents list for "View All" button
                        loadAllDocumentsList(documents);
                        
                    } else {
                        contentDiv.innerHTML = `
                            <div style="text-align: center; padding: 60px;">
                                <i class="fas fa-file-circle-xmark fa-3x" style="color: #cbd5e0;"></i>
                                <h4 style="margin-top: 20px; color: #4a5568;">No Documents Found</h4>
                                <p style="color: #718096;">No documents have been uploaded for this land record.</p>
                            </div>
                        `;
                        document.getElementById('viewAllBtn').style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    contentDiv.innerHTML = `
                        <div style="text-align: center; padding: 60px;">
                            <i class="fas fa-exclamation-triangle fa-3x" style="color: #f59e0b;"></i>
                            <h4 style="margin-top: 20px; color: #4a5568;">Error Loading Documents</h4>
                            <p style="color: #718096;">Unable to load document information. Please try again.</p>
                        </div>
                    `;
                    showNotification('Error loading documents.', 'error');
                });
        }

        function loadAllDocumentsList(documents) {
            const listDiv = document.getElementById('documentList');
            if (documents && documents.length > 1) {
                listDiv.innerHTML = '';
                documents.forEach((doc, index) => {
                    const ext = doc.document_path.split('.').pop().toLowerCase();
                    const icon = ext === 'pdf' ? 'file-pdf' : 
                                ['jpg', 'jpeg', 'png', 'gif'].includes(ext) ? 'file-image' : 'file';
                    
                    listDiv.innerHTML += `
                        <div class="document-item" style="padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 10px; cursor: pointer; transition: var(--transition);"
                             onclick="switchDocument('${doc.document_path}', '${doc.document_type || ''}')">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <i class="fas fa-${icon}" style="color: #4299e1;"></i>
                                <div style="flex: 1;">
                                    <strong style="color: #2d3748;">${doc.document_type || 'Document'}</strong>
                                    <div style="display: flex; justify-content: space-between; font-size: 12px;">
                                        <span style="color: #718096;">${doc.document_path}</span>
                                        <span style="color: #4a5568;">${new Date(doc.uploaded_at).toLocaleDateString()}</span>
                                    </div>
                                </div>
                                ${doc.is_primary ? '<span class="status-badge status-active" style="font-size: 10px;">Primary</span>' : ''}
                            </div>
                        </div>
                    `;
                });
            } else {
                document.getElementById('viewAllBtn').style.display = 'none';
            }
        }

        function switchDocument(docPath, docType) {
            const ext = docPath.split('.').pop().toLowerCase();
            const contentDiv = document.getElementById('documentContent');
            let html = '';
            
            if (['jpg', 'jpeg', 'png', 'gif', 'bmp'].includes(ext)) {
                html = `
                    <div class="document-preview">
                        <img src="../../uploads/${docPath}" 
                             alt="${docType || 'Document'}" 
                             style="width: 100%; height: 100%; object-fit: contain; padding: 20px;">
                    </div>
                `;
            } else if (ext === 'pdf') {
                html = `
                    <div class="document-preview">
                        <iframe src="../../uploads/${docPath}#toolbar=0" 
                                style="width: 100%; height: 100%; border: none;"></iframe>
                    </div>
                `;
            } else {
                html = `
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-file fa-4x" style="color: #4299e1;"></i>
                        <h3 style="margin-top: 20px;">${docType || 'Document'}</h3>
                        <p style="color: #718096;">File type: ${ext.toUpperCase()}</p>
                    </div>
                `;
            }
            
            contentDiv.innerHTML = html;
            document.getElementById('documentModal').dataset.currentDoc = docPath;
        }

        function viewAllDocuments() {
            document.getElementById('documentContent').style.display = 'none';
            document.getElementById('allDocuments').style.display = 'block';
            document.getElementById('viewAllBtn').innerHTML = '<i class="fas fa-file"></i> View Primary Document';
            document.getElementById('viewAllBtn').onclick = viewPrimaryDocument;
        }

        function viewPrimaryDocument() {
            document.getElementById('documentContent').style.display = 'block';
            document.getElementById('allDocuments').style.display = 'none';
            document.getElementById('viewAllBtn').innerHTML = '<i class="fas fa-folder-open"></i> View All Documents';
            document.getElementById('viewAllBtn').onclick = viewAllDocuments;
        }

        function downloadDocument() {
            const docPath = document.getElementById('documentModal').dataset.currentDoc;
            if (docPath) {
                window.open(`../../uploads/${docPath}`, '_blank');
            }
        }

        function closeDocumentModal() {
            document.getElementById('documentModal').style.display = 'none';
            document.getElementById('documentContent').style.display = 'block';
            document.getElementById('allDocuments').style.display = 'none';
            document.getElementById('viewAllBtn').innerHTML = '<i class="fas fa-folder-open"></i> View All Documents';
            document.getElementById('viewAllBtn').onclick = viewAllDocuments;
            document.getElementById('viewAllBtn').style.display = 'inline-flex';
        }
        
        function openRejectModal(recordId) {
            const reason = prompt('Enter reason for rejection:', '');
            if (reason !== null && reason.trim() !== '') {
                if (confirm(`Are you sure you want to reject this land record?\n\nReason: ${reason}`)) {
                    window.location.href = `lands.php?action=reject&id=${recordId}&reason=${encodeURIComponent(reason)}`;
                }
            }
        }
        
        function confirmDelete(recordId, parcelNo) {
            return confirm(`Are you sure you want to delete land record?\n\nParcel No: ${parcelNo}\n\nThis action cannot be undone!`);
        }
        
        function closeModal() {
            document.getElementById('landModal').style.display = 'none';
        }
        
        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
        }
        
        function closeSplitInfoModal() {
            document.getElementById('splitInfoModal').style.display = 'none';
        }
        
        // ENHANCED DROPDOWN FUNCTIONALITY
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize dropdowns
            const dropdownButtons = document.querySelectorAll('.action-dropdown .btn-small');
            
            dropdownButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    e.preventDefault();
                    
                    const parentDropdown = this.closest('.action-dropdown');
                    const dropdown = this.nextElementSibling;
                    
                    // Close all other dropdowns
                    document.querySelectorAll('.action-dropdown').forEach(drop => {
                        if (drop !== parentDropdown) {
                            drop.classList.remove('active');
                            const otherDropdown = drop.querySelector('.dropdown-content');
                            if (otherDropdown) {
                                otherDropdown.style.display = 'none';
                            }
                        }
                    });
                    
                    // Toggle this dropdown
                    if (dropdown.style.display === 'block') {
                        dropdown.style.display = 'none';
                        parentDropdown.classList.remove('active');
                    } else {
                        dropdown.style.display = 'block';
                        parentDropdown.classList.add('active');
                        
                        // Position check to ensure it stays in viewport
                        setTimeout(() => {
                            const rect = dropdown.getBoundingClientRect();
                            if (rect.right > window.innerWidth) {
                                dropdown.style.right = 'auto';
                                dropdown.style.left = '0';
                                dropdown.style.transform = 'translateX(-100%)';
                                dropdown.style.top = 'calc(100% + 5px)';
                            }
                        }, 0);
                    }
                });
            });
            
            // Close dropdowns when clicking anywhere else
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.action-dropdown')) {
                    document.querySelectorAll('.action-dropdown').forEach(dropdown => {
                        dropdown.classList.remove('active');
                        const content = dropdown.querySelector('.dropdown-content');
                        if (content) {
                            content.style.display = 'none';
                        }
                    });
                }
            });
            
            // Close dropdowns when clicking on a dropdown item
            document.querySelectorAll('.dropdown-content a').forEach(link => {
                link.addEventListener('click', function(e) {
                    if (this.getAttribute('href') === '#') {
                        e.preventDefault();
                    }
                    const parentDropdown = this.closest('.action-dropdown');
                    if (parentDropdown) {
                        parentDropdown.classList.remove('active');
                        const dropdownContent = this.closest('.dropdown-content');
                        if (dropdownContent) {
                            dropdownContent.style.display = 'none';
                        }
                    }
                });
            });
            
            // Add keyboard support
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    document.querySelectorAll('.action-dropdown').forEach(dropdown => {
                        dropdown.classList.remove('active');
                        const content = dropdown.querySelector('.dropdown-content');
                        if (content) {
                            content.style.display = 'none';
                        }
                    });
                }
            });
            
            // Highlight search terms in table
            const searchTerm = "<?php echo addslashes($search); ?>";
            if (searchTerm) {
                const tableCells = document.querySelectorAll('.data-table td');
                tableCells.forEach(cell => {
                    const html = cell.innerHTML;
                    const regex = new RegExp(searchTerm, 'gi');
                    const highlighted = html.replace(regex, match => 
                        `<span class="highlight">${match}</span>`
                    );
                    cell.innerHTML = highlighted;
                });
            }
        });
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = ['landModal', 'viewModal', 'splitInfoModal', 'documentModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    if (modalId === 'landModal') closeModal();
                    if (modalId === 'viewModal') closeViewModal();
                    if (modalId === 'splitInfoModal') closeSplitInfoModal();
                    if (modalId === 'documentModal') closeDocumentModal();
                }
            });
        };
        
        // Form validation
        document.getElementById('landForm').addEventListener('submit', function(e) {
            const parcelNo = document.getElementById('parcel_no').value.trim();
            const location = document.getElementById('location').value.trim();
            const size = document.getElementById('size').value;
            const ownerId = document.getElementById('owner_id').value;
            
            if (!parcelNo || !location || !size || !ownerId) {
                e.preventDefault();
                showNotification('Please fill in all required fields.', 'error');
                return false;
            }
            
            if (parseFloat(size) <= 0) {
                e.preventDefault();
                showNotification('Size must be greater than 0.', 'error');
                return false;
            }
            
            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = '<span class="loading"></span> Saving...';
            submitBtn.disabled = true;
        });
        
        // Utility functions
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} animate__animated animate__fadeIn`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                ${message}
            `;
            document.querySelector('.admin-content').prepend(notification);
            
            setTimeout(() => {
                notification.classList.remove('animate__fadeIn');
                notification.classList.add('animate__fadeOut');
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        }
        
        function exportToCSV() {
            showNotification('Export feature coming soon!', 'info');
        }
        
        function printTable() {
            window.print();
        }
        
        function refreshPage() {
            window.location.reload();
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                openAddModal();
            }
            if (e.key === 'Escape') {
                closeModal();
                closeViewModal();
                closeSplitInfoModal();
                closeDocumentModal();
                document.querySelectorAll('.dropdown-content').forEach(dropdown => {
                    dropdown.style.display = 'none';
                });
            }
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.getElementById('search').focus();
            }
        });
    </script>
</body>
</html>