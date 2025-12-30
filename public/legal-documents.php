<?php
// File: public/legal-documents.php
require_once __DIR__ . '/../includes/init.php';

// Require login
require_login();

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : 'browse';
$document_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$category = isset($_GET['category']) ? sanitize_input($_GET['category']) : 'all';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Get user's active lands for document generation
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

// Legal documents database (in a real system, this would come from a database)
$legal_documents = [
    [
        'id' => 1,
        'title' => 'Land Sale Agreement',
        'description' => 'Standard agreement for the sale and purchase of land parcels.',
        'category' => 'sale_purchase',
        'file_size' => '45 KB',
        'file_format' => 'PDF/DOC',
        'version' => '3.2',
        'last_updated' => '2024-01-15',
        'downloads' => 1245,
        'preview_content' => 'THIS AGREEMENT is made on [DATE] BETWEEN [SELLER NAME] (hereinafter referred to as "the Seller") AND [BUYER NAME] (hereinafter referred to as "the Buyer")...'
    ],
    [
        'id' => 2,
        'title' => 'Lease Agreement for Agricultural Land',
        'description' => 'Comprehensive lease agreement for agricultural land use.',
        'category' => 'lease_rental',
        'file_size' => '52 KB',
        'file_format' => 'PDF/DOC',
        'version' => '2.1',
        'last_updated' => '2024-02-10',
        'downloads' => 987,
        'preview_content' => 'THIS LEASE AGREEMENT is made on [DATE] BETWEEN [LANDOWNER NAME] (hereinafter referred to as "the Lessor") AND [TENANT NAME] (hereinafter referred to as "the Lessee")...'
    ],
    [
        'id' => 3,
        'title' => 'Affidavit of Ownership',
        'description' => 'Sworn statement affirming ownership of land parcel.',
        'category' => 'legal_declarations',
        'file_size' => '28 KB',
        'file_format' => 'PDF/DOC',
        'version' => '1.5',
        'last_updated' => '2024-01-25',
        'downloads' => 2156,
        'preview_content' => 'I, [FULL NAME], of [ADDRESS], do hereby solemnly declare and affirm as follows: 1. That I am the lawful owner of the land parcel known as [PARCEL NUMBER]...'
    ],
    [
        'id' => 4,
        'title' => 'Power of Attorney for Land Transactions',
        'description' => 'Legal document authorizing someone to act on your behalf for land matters.',
        'category' => 'legal_declarations',
        'file_size' => '38 KB',
        'file_format' => 'PDF/DOC',
        'version' => '2.0',
        'last_updated' => '2024-03-05',
        'downloads' => 1789,
        'preview_content' => 'KNOW ALL MEN BY THESE PRESENTS that I, [PRINCIPAL NAME], of [ADDRESS], do hereby appoint [AGENT NAME], of [ADDRESS], as my true and lawful attorney...'
    ],
    [
        'id' => 5,
        'title' => 'Land Subdivision Agreement',
        'description' => 'Agreement for subdividing a larger land parcel into smaller plots.',
        'category' => 'development',
        'file_size' => '61 KB',
        'file_format' => 'PDF/DOC',
        'version' => '1.8',
        'last_updated' => '2024-02-28',
        'downloads' => 743,
        'preview_content' => 'THIS SUBDIVISION AGREEMENT is made on [DATE] BETWEEN [LANDOWNER NAME] (hereinafter referred to as "the Owner") AND [DEVELOPER NAME] (hereinafter referred to as "the Developer")...'
    ],
    [
        'id' => 6,
        'title' => 'Mortgage Agreement',
        'description' => 'Standard mortgage agreement for using land as collateral.',
        'category' => 'financial',
        'file_size' => '55 KB',
        'file_format' => 'PDF/DOC',
        'version' => '3.0',
        'last_updated' => '2024-01-20',
        'downloads' => 1567,
        'preview_content' => 'THIS MORTGAGE AGREEMENT is made on [DATE] BETWEEN [BORROWER NAME] (hereinafter referred to as "the Mortgagor") AND [LENDER NAME] (hereinafter referred to as "the Mortgagee")...'
    ],
    [
        'id' => 7,
        'title' => 'Joint Ownership Agreement',
        'description' => 'Agreement for multiple parties owning land together.',
        'category' => 'ownership',
        'file_size' => '49 KB',
        'file_format' => 'PDF/DOC',
        'version' => '2.3',
        'last_updated' => '2024-03-12',
        'downloads' => 892,
        'preview_content' => 'THIS JOINT OWNERSHIP AGREEMENT is made on [DATE] BETWEEN the following parties who shall collectively be referred to as "the Co-Owners": 1. [OWNER 1 NAME]...'
    ],
    [
        'id' => 8,
        'title' => 'Boundary Agreement',
        'description' => 'Agreement between neighbors to establish and maintain property boundaries.',
        'category' => 'boundary',
        'file_size' => '42 KB',
        'file_format' => 'PDF/DOC',
        'version' => '1.6',
        'last_updated' => '2024-02-15',
        'downloads' => 634,
        'preview_content' => 'THIS BOUNDARY AGREEMENT is made on [DATE] BETWEEN [OWNER 1 NAME] (hereinafter referred to as "Party A") AND [OWNER 2 NAME] (hereinafter referred to as "Party B")...'
    ],
    [
        'id' => 9,
        'title' => 'Gift Deed for Land Transfer',
        'description' => 'Legal document for gifting land to family members or others.',
        'category' => 'transfer',
        'file_size' => '37 KB',
        'file_format' => 'PDF/DOC',
        'version' => '1.9',
        'last_updated' => '2024-03-01',
        'downloads' => 1023,
        'preview_content' => 'THIS GIFT DEED is made on [DATE] BETWEEN [DONOR NAME] (hereinafter referred to as "the Donor") AND [DONEE NAME] (hereinafter referred to as "the Donee")...'
    ],
    [
        'id' => 10,
        'title' => 'Easement Agreement',
        'description' => 'Agreement granting right of way or access through a property.',
        'category' => 'rights',
        'file_size' => '44 KB',
        'file_format' => 'PDF/DOC',
        'version' => '2.2',
        'last_updated' => '2024-02-20',
        'downloads' => 567,
        'preview_content' => 'THIS EASEMENT AGREEMENT is made on [DATE] BETWEEN [PROPERTY OWNER NAME] (hereinafter referred to as "the Grantor") AND [BENEFICIARY NAME] (hereinafter referred to as "the Grantee")...'
    ]
];

// Categories
$categories = [
    'all' => 'All Documents',
    'sale_purchase' => 'Sale & Purchase',
    'lease_rental' => 'Lease & Rental',
    'legal_declarations' => 'Legal Declarations',
    'development' => 'Development',
    'financial' => 'Financial',
    'ownership' => 'Ownership',
    'boundary' => 'Boundary',
    'transfer' => 'Transfer',
    'rights' => 'Rights & Easements'
];

// Filter documents based on category and search
$filtered_documents = $legal_documents;

if ($category !== 'all') {
    $filtered_documents = array_filter($filtered_documents, function($doc) use ($category) {
        return $doc['category'] === $category;
    });
}

if ($search) {
    $search_lower = strtolower($search);
    $filtered_documents = array_filter($filtered_documents, function($doc) use ($search_lower) {
        return strpos(strtolower($doc['title']), $search_lower) !== false || 
               strpos(strtolower($doc['description']), $search_lower) !== false;
    });
}

// Get specific document for view
$current_document = null;
if ($document_id > 0) {
    foreach ($legal_documents as $doc) {
        if ($doc['id'] == $document_id) {
            $current_document = $doc;
            break;
        }
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        flash_message('error', 'Invalid form submission. Please try again.');
        redirect('legal-documents.php');
    }
    
    $action = $_POST['action'];
    
    if ($action == 'customize_document') {
        $template_id = intval($_POST['template_id']);
        $land_id = intval($_POST['land_id']);
        $party1_name = sanitize_input($_POST['party1_name']);
        $party2_name = sanitize_input($_POST['party2_name']);
        $effective_date = sanitize_input($_POST['effective_date']);
        $additional_terms = sanitize_input($_POST['additional_terms']);
        
        // Validate
        if (empty($template_id) || empty($land_id) || empty($party1_name) || empty($party2_name) || empty($effective_date)) {
            flash_message('error', 'Please fill all required fields.');
        } else {
            // Check if user owns this land
            $check_sql = "SELECT parcel_no, location, size FROM land_records 
                         WHERE record_id = ? AND owner_id = ?";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "ii", $land_id, $user_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if (mysqli_num_rows($check_result) === 0) {
                flash_message('error', 'Land record not found or access denied.');
                mysqli_stmt_close($check_stmt);
            } else {
                $land_data = mysqli_fetch_assoc($check_stmt);
                mysqli_stmt_close($check_stmt);
                
                // Find the template
                $template = null;
                foreach ($legal_documents as $doc) {
                    if ($doc['id'] == $template_id) {
                        $template = $doc;
                        break;
                    }
                }
                
                if ($template) {
                    // Generate customized document
                    $customized_doc = generate_customized_document($template, $land_data, [
                        'party1_name' => $party1_name,
                        'party2_name' => $party2_name,
                        'effective_date' => $effective_date,
                        'additional_terms' => $additional_terms,
                        'user_name' => $user_name,
                        'generation_date' => date('Y-m-d H:i:s')
                    ]);
                    
                    // Save to user's documents
                    save_customized_document($user_id, $land_id, $template, $customized_doc);
                    
                    flash_message('success', 'Document customized successfully. You can now download it.');
                    redirect("legal-documents.php?action=view&id=$template_id&customized=1");
                } else {
                    flash_message('error', 'Template not found.');
                }
            }
        }
    }
}

// Function to generate customized document
function generate_customized_document($template, $land_data, $custom_data) {
    // In a real system, this would use a template engine
    // For now, we'll create a simple placeholder
    $document_content = "CUSTOMIZED DOCUMENT\n";
    $document_content .= "====================\n\n";
    $document_content .= "Template: " . $template['title'] . "\n";
    $document_content .= "Generated for: " . $custom_data['user_name'] . "\n";
    $document_content .= "Land Parcel: " . $land_data['parcel_no'] . "\n";
    $document_content .= "Location: " . $land_data['location'] . "\n";
    $document_content .= "Size: " . $land_data['size'] . " acres\n\n";
    $document_content .= "Parties:\n";
    $document_content .= "- Party 1: " . $custom_data['party1_name'] . "\n";
    $document_content .= "- Party 2: " . $custom_data['party2_name'] . "\n\n";
    $document_content .= "Effective Date: " . $custom_data['effective_date'] . "\n";
    $document_content .= "Generation Date: " . $custom_data['generation_date'] . "\n\n";
    
    if (!empty($custom_data['additional_terms'])) {
        $document_content .= "Additional Terms:\n";
        $document_content .= $custom_data['additional_terms'] . "\n\n";
    }
    
    $document_content .= "Document Content:\n";
    $document_content .= str_replace(
        ['[DATE]', '[SELLER NAME]', '[BUYER NAME]', '[LANDOWNER NAME]', '[TENANT NAME]', '[FULL NAME]', '[PARCEL NUMBER]'],
        [$custom_data['effective_date'], $custom_data['party1_name'], $custom_data['party2_name'], $custom_data['party1_name'], $custom_data['party2_name'], $custom_data['user_name'], $land_data['parcel_no']],
        $template['preview_content']
    );
    
    return $document_content;
}

// Function to save customized document
function save_customized_document($user_id, $land_id, $template, $content) {
    global $conn;
    
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
            status ENUM('draft', 'finalized', 'signed') DEFAULT 'draft',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_template_id (template_id)
        )";
        
        mysqli_query($conn, $create_table_sql);
    }
    
    // Save the document
    $insert_sql = "INSERT INTO legal_documents (user_id, land_id, template_id, document_title, document_content) 
                   VALUES (?, ?, ?, ?, ?)";
    $insert_stmt = mysqli_prepare($conn, $insert_sql);
    
    if ($insert_stmt) {
        mysqli_stmt_bind_param($insert_stmt, "iiiss", 
            $user_id, $land_id, $template['id'], $template['title'], $content);
        mysqli_stmt_execute($insert_stmt);
        mysqli_stmt_close($insert_stmt);
        
        // Log activity if function exists
        if (function_exists('log_activity')) {
            log_activity($user_id, 'legal_document_created', 
                "Created customized document: " . $template['title']);
        }
        
        return mysqli_insert_id($conn);
    }
    
    return false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Legal Documents & Templates - ArdhiYetu</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .legal-documents-container {
            padding: 20px 0;
            min-height: 1000px;
        }
        
        .legal-header {
            background: linear-gradient(135deg, #2c3e50 0%, #4a6491 100%);
            color: white;
            padding: 40px 30px;
            border-radius: 10px;
            margin-bottom: 40px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .legal-header h1 {
            margin: 0 0 15px 0;
            font-size: 2.8rem;
            font-weight: 700;
        }
        
        .legal-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 1.2rem;
            max-width: 800px;
            margin: 0 auto;
            line-height: 1.6;
            font-weight: 300;
        }
        
        .header-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
            min-height: 44px;
            justify-content: center;
        }
        
        .action-btn.primary {
            background: #27ae60;
            color: white;
            border: 2px solid #27ae60;
        }
        
        .action-btn.secondary {
            background: transparent;
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .action-btn.primary:hover {
            background: #219653;
            border-color: #219653;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }
        
        .action-btn.secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
        }
        
        .filter-bar {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            border: 1px solid #e9ecef;
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
            padding: 14px 50px 14px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #2c3e50;
            background: white;
            box-shadow: 0 0 0 3px rgba(44, 62, 80, 0.1);
        }
        
        .search-box button {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 5px;
            transition: color 0.3s;
        }
        
        .search-box button:hover {
            color: #2c3e50;
        }
        
        .category-filter {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .category-btn {
            padding: 10px 18px;
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            color: #555;
            transition: all 0.3s;
            text-decoration: none;
            font-size: 0.9rem;
            white-space: nowrap;
        }
        
        .category-btn.active {
            background: #2c3e50;
            color: white;
            border-color: #2c3e50;
            box-shadow: 0 3px 10px rgba(44, 62, 80, 0.2);
        }
        
        .category-btn:hover:not(.active) {
            background: #e9ecef;
            border-color: #2c3e50;
            transform: translateY(-2px);
        }
        
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }
        
        .document-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .document-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.12);
            border-color: #2c3e50;
        }
        
        .document-header {
            background: linear-gradient(135deg, #3498db, #2c3e50);
            color: white;
            padding: 25px;
            position: relative;
            flex-shrink: 0;
        }
        
        .document-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            opacity: 0.9;
        }
        
        .document-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255,255,255,0.2);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            backdrop-filter: blur(5px);
            letter-spacing: 0.5px;
        }
        
        .document-card h3 {
            margin: 0 0 12px 0;
            font-size: 1.4rem;
            line-height: 1.4;
            font-weight: 600;
        }
        
        .document-card .description {
            color: rgba(255,255,255,0.85);
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 0;
            font-weight: 300;
        }
        
        .document-body {
            padding: 25px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .document-meta {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .meta-item {
            text-align: center;
        }
        
        .meta-item .label {
            display: block;
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        .meta-item .value {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.95rem;
        }
        
        .document-preview {
            background: #f8f9fa;
            padding: 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            max-height: 150px;
            overflow: hidden;
            position: relative;
            flex-grow: 1;
            border: 1px solid #e9ecef;
        }
        
        .document-preview:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 40px;
            background: linear-gradient(to bottom, transparent, #f8f9fa);
        }
        
        .document-preview p {
            margin: 0;
            color: #555;
            font-size: 0.9rem;
            line-height: 1.6;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .document-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: auto;
        }
        
        .doc-btn {
            padding: 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.9rem;
            text-decoration: none;
            min-height: 44px;
        }
        
        .doc-btn.download {
            background: #27ae60;
            color: white;
        }
        
        .doc-btn.preview {
            background: #3498db;
            color: white;
        }
        
        .doc-btn.customize {
            background: #e67e22;
            color: white;
        }
        
        .doc-btn.download:hover { 
            background: #219653;
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(39, 174, 96, 0.2);
        }
        
        .doc-btn.preview:hover { 
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(52, 152, 219, 0.2);
        }
        
        .doc-btn.customize:hover { 
            background: #d35400;
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(230, 126, 34, 0.2);
        }
        
        .document-detail-view {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            margin-bottom: 40px;
            border: 1px solid #e9ecef;
        }
        
        .detail-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .detail-title h2 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            font-size: 2rem;
            font-weight: 700;
        }
        
        .detail-meta {
            display: flex;
            gap: 20px;
            color: #666;
            font-size: 0.95rem;
            flex-wrap: wrap;
        }
        
        .detail-meta span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f8f9fa;
            padding: 8px 14px;
            border-radius: 6px;
            font-weight: 500;
        }
        
        .detail-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .detail-btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s;
            min-height: 44px;
            border: 2px solid transparent;
        }
        
        .detail-btn.primary {
            background: #27ae60;
            color: white;
        }
        
        .detail-btn.secondary {
            background: #3498db;
            color: white;
        }
        
        .detail-btn.outline {
            background: transparent;
            color: #666;
            border-color: #ddd;
        }
        
        .detail-btn.primary:hover {
            background: #219653;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.2);
        }
        
        .detail-btn.secondary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.2);
        }
        
        .detail-btn.outline:hover {
            background: #f8f9fa;
            border-color: #2c3e50;
            transform: translateY(-2px);
        }
        
        .detail-content {
            margin: 40px 0;
        }
        
        .detail-content h3 {
            color: #2c3e50;
            margin: 30px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            font-weight: 600;
        }
        
        .document-preview-full {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            margin: 30px 0;
            font-family: 'Courier New', monospace;
            line-height: 1.8;
            white-space: pre-wrap;
            max-height: 500px;
            overflow-y: auto;
            border: 1px solid #e9ecef;
            font-size: 0.95rem;
        }
        
        .customize-form-container {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            margin-bottom: 40px;
            border: 1px solid #e9ecef;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
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
            font-size: 1rem;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #2c3e50;
            background: white;
            box-shadow: 0 0 0 3px rgba(44, 62, 80, 0.1);
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #eee;
        }
        
        .form-btn {
            padding: 14px 28px;
            border-radius: 8px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s;
            border: 2px solid transparent;
            min-height: 48px;
            cursor: pointer;
        }
        
        .form-btn.primary {
            background: #27ae60;
            color: white;
        }
        
        .form-btn.secondary {
            background: #95a5a6;
            color: white;
        }
        
        .form-btn.primary:hover {
            background: #219653;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.2);
        }
        
        .form-btn.secondary:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }
        
        .info-box {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 25px;
            border-radius: 10px;
            margin: 30px 0;
            border-left: 5px solid #2c3e50;
        }
        
        .info-box h4 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-box ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .info-box li {
            margin-bottom: 10px;
            line-height: 1.5;
            color: #555;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-top: 40px;
        }
        
        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-top: 5px solid #2c3e50;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .stat-number {
            font-size: 2.8rem;
            font-weight: 700;
            color: #2c3e50;
            margin: 15px 0;
            line-height: 1;
        }
        
        .stat-label {
            color: #666;
            font-size: 1rem;
            margin-top: 10px;
        }
        
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #666;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
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
            font-weight: 600;
        }
        
        .empty-state p {
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto 30px;
            line-height: 1.6;
            color: #777;
        }
        
        .empty-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .legal-header h1 {
                font-size: 2.2rem;
            }
            
            .legal-header p {
                font-size: 1rem;
            }
            
            .header-actions, .empty-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .action-btn, .empty-actions a {
                width: 100%;
                justify-content: center;
            }
            
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                min-width: 100%;
            }
            
            .category-filter {
                justify-content: stretch;
                overflow-x: auto;
                padding-bottom: 10px;
            }
            
            .category-btn {
                white-space: nowrap;
            }
            
            .documents-grid {
                grid-template-columns: 1fr;
            }
            
            .document-actions {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .detail-header {
                flex-direction: column;
                text-align: center;
            }
            
            .detail-meta {
                justify-content: center;
            }
            
            .detail-actions {
                justify-content: center;
                width: 100%;
            }
            
            .detail-btn {
                flex: 1;
                justify-content: center;
                min-width: 140px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .form-btn {
                width: 100%;
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .document-meta {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .document-meta {
                grid-template-columns: 1fr;
            }
            
            .detail-meta span {
                width: 100%;
                justify-content: center;
            }
            
            .document-card {
                margin: 0 -15px;
                border-radius: 0;
                border-left: none;
                border-right: none;
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
                <a href="legal-documents.php" class="active"><i class="fas fa-gavel"></i> Legal Docs</a>
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

    <main class="legal-documents-container">
        <div class="container">
            <div class="legal-header">
                <h1><i class="fas fa-gavel"></i> Legal Documents & Templates</h1>
                <p>Access professionally drafted legal documents, agreements, and templates for all your land-related transactions. Customize and download ready-to-use legal forms.</p>
                
                <div class="header-actions">
                    <a href="#browse" class="action-btn primary">
                        <i class="fas fa-search"></i> Browse Templates
                    </a>
                    <a href="my-legal-documents.php" class="action-btn secondary">
                        <i class="fas fa-folder"></i> My Documents
                    </a>
                    <a href="?action=customize&id=1" class="action-btn secondary">
                        <i class="fas fa-edit"></i> Customize Document
                    </a>
                </div>
            </div>

            <?php 
            // Display flash messages if function exists
            if (function_exists('display_flash_message')) {
                display_flash_message();
            }
            ?>
            
            <?php if ($action == 'view' && $current_document): ?>
                <!-- Document Detail View -->
                <div class="document-detail-view">
                    <div class="detail-header">
                        <div class="detail-title">
                            <h2><?php echo htmlspecialchars($current_document['title']); ?></h2>
                            <div class="detail-meta">
                                <span><i class="fas fa-tag"></i> <?php echo $categories[$current_document['category']]; ?></span>
                                <span><i class="fas fa-file-alt"></i> <?php echo $current_document['file_format']; ?></span>
                                <span><i class="fas fa-hdd"></i> <?php echo $current_document['file_size']; ?></span>
                                <span><i class="fas fa-download"></i> <?php echo number_format($current_document['downloads']); ?> downloads</span>
                                <span><i class="fas fa-calendar-alt"></i> Updated: <?php echo $current_document['last_updated']; ?></span>
                            </div>
                        </div>
                        <div class="detail-actions">
                            <a href="javascript:void(0)" onclick="downloadDocument(<?php echo $current_document['id']; ?>)" class="detail-btn primary">
                                <i class="fas fa-download"></i> Download
                            </a>
                            <a href="?action=customize&id=<?php echo $current_document['id']; ?>" class="detail-btn secondary">
                                <i class="fas fa-edit"></i> Customize
                            </a>
                            <a href="legal-documents.php" class="detail-btn outline">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>
                    
                    <div class="detail-content">
                        <h3>Description</h3>
                        <p><?php echo htmlspecialchars($current_document['description']); ?></p>
                        
                        <h3>Document Preview</h3>
                        <div class="document-preview-full">
                            <?php echo htmlspecialchars($current_document['preview_content']); ?>
                        </div>
                        
                        <div class="info-box">
                            <h4><i class="fas fa-info-circle"></i> Important Information</h4>
                            <ul>
                                <li>This is a standard legal template. Consult with a qualified lawyer before use.</li>
                                <li>All placeholders (like [NAME], [DATE]) need to be replaced with actual information.</li>
                                <li>Documents should be signed in the presence of witnesses or a notary public.</li>
                                <li>Keep copies of all signed documents for your records.</li>
                                <li>Some documents may require registration with relevant authorities.</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($action == 'customize' && $current_document): ?>
                <!-- Customize Document Form -->
                <div class="customize-form-container">
                    <div class="detail-header">
                        <div class="detail-title">
                            <h2>Customize: <?php echo htmlspecialchars($current_document['title']); ?></h2>
                            <p>Fill in the details below to generate a customized version of this document.</p>
                        </div>
                        <div class="detail-actions">
                            <a href="?action=view&id=<?php echo $current_document['id']; ?>" class="detail-btn outline">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>
                    
                    <form method="POST" action="" class="customize-form">
                        <input type="hidden" name="action" value="customize_document">
                        <input type="hidden" name="template_id" value="<?php echo $current_document['id']; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="land_id">Select Land Parcel *</label>
                                <select id="land_id" name="land_id" class="form-control" required>
                                    <option value="">Select a land parcel</option>
                                    <?php foreach ($active_lands as $land): ?>
                                        <option value="<?php echo $land['record_id']; ?>">
                                            <?php echo htmlspecialchars($land['parcel_no']); ?> - 
                                            <?php echo htmlspecialchars($land['location']); ?> 
                                            (<?php echo number_format($land['size'], 2); ?> acres)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small>The land parcel this document relates to</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="party1_name">Party 1 Name *</label>
                                <input type="text" id="party1_name" name="party1_name" class="form-control" required
                                       placeholder="e.g., John Doe, ABC Company Ltd.">
                                <small>First party in the agreement (Seller/Lessor/Owner)</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="party2_name">Party 2 Name *</label>
                                <input type="text" id="party2_name" name="party2_name" class="form-control" required
                                       placeholder="e.g., Jane Smith, XYZ Corporation">
                                <small>Second party in the agreement (Buyer/Lessee/Recipient)</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="effective_date">Effective Date *</label>
                                <input type="date" id="effective_date" name="effective_date" class="form-control" required
                                       value="<?php echo date('Y-m-d'); ?>">
                                <small>Date when the agreement takes effect</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="additional_terms">Additional Terms (Optional)</label>
                            <textarea id="additional_terms" name="additional_terms" class="form-control" rows="5"
                                      placeholder="Enter any additional clauses, terms, or conditions specific to your agreement..."></textarea>
                            <small>These will be appended to the standard document</small>
                        </div>
                        
                        <div class="info-box">
                            <h4><i class="fas fa-lightbulb"></i> Customization Tips</h4>
                            <ul>
                                <li>Use full legal names of all parties involved</li>
                                <li>Be specific about amounts, dates, and property details</li>
                                <li>Consider including dispute resolution clauses</li>
                                <li>Specify governing law (usually laws of Kenya)</li>
                                <li>Include details about payment schedules if applicable</li>
                            </ul>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="form-btn primary">
                                <i class="fas fa-magic"></i> Generate Customized Document
                            </button>
                            <a href="?action=view&id=<?php echo $current_document['id']; ?>" class="form-btn secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
                
            <?php else: ?>
                <!-- Main Browse View -->
                <div class="filter-bar">
                    <form method="GET" action="" class="filter-form">
                        <div class="search-box">
                            <input type="text" name="search" placeholder="Search legal documents..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        
                        <div class="category-filter">
                            <?php foreach ($categories as $cat_id => $cat_name): ?>
                                <a href="?category=<?php echo $cat_id; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
                                   class="category-btn <?php echo $category == $cat_id ? 'active' : ''; ?>">
                                    <?php echo $cat_name; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </form>
                </div>
                
                <?php if (count($filtered_documents) > 0): ?>
                    <div class="documents-grid" id="browse">
                        <?php foreach ($filtered_documents as $doc): ?>
                            <div class="document-card">
                                <div class="document-header">
                                    <div class="document-icon">
                                        <i class="fas fa-file-contract"></i>
                                    </div>
                                    <span class="document-badge">
                                        <?php echo $categories[$doc['category']]; ?>
                                    </span>
                                    <h3><?php echo htmlspecialchars($doc['title']); ?></h3>
                                    <p class="description"><?php echo htmlspecialchars($doc['description']); ?></p>
                                </div>
                                
                                <div class="document-body">
                                    <div class="document-meta">
                                        <div class="meta-item">
                                            <span class="label">Format</span>
                                            <span class="value"><?php echo $doc['file_format']; ?></span>
                                        </div>
                                        <div class="meta-item">
                                            <span class="label">Size</span>
                                            <span class="value"><?php echo $doc['file_size']; ?></span>
                                        </div>
                                        <div class="meta-item">
                                            <span class="label">Version</span>
                                            <span class="value"><?php echo $doc['version']; ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="document-preview">
                                        <p><?php echo htmlspecialchars(substr($doc['preview_content'], 0, 200)); ?>...</p>
                                    </div>
                                    
                                    <div class="document-actions">
                                        <a href="javascript:void(0)" onclick="downloadDocument(<?php echo $doc['id']; ?>)" 
                                           class="doc-btn download">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                        <a href="?action=view&id=<?php echo $doc['id']; ?>" 
                                           class="doc-btn preview">
                                            <i class="fas fa-eye"></i> Preview
                                        </a>
                                        <a href="?action=customize&id=<?php echo $doc['id']; ?>" 
                                           class="doc-btn customize">
                                            <i class="fas fa-edit"></i> Customize
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Quick Stats -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h3><i class="fas fa-file-contract"></i> Total Templates</h3>
                            <div class="stat-number"><?php echo count($legal_documents); ?></div>
                            <p class="stat-label">Professionally drafted documents</p>
                        </div>
                        
                        <div class="stat-card">
                            <h3><i class="fas fa-download"></i> Total Downloads</h3>
                            <div class="stat-number">
                                <?php 
                                $total_downloads = array_sum(array_column($legal_documents, 'downloads'));
                                echo number_format($total_downloads);
                                ?>
                            </div>
                            <p class="stat-label">Documents downloaded by users</p>
                        </div>
                        
                        <div class="stat-card">
                            <h3><i class="fas fa-tags"></i> Categories</h3>
                            <div class="stat-number"><?php echo count($categories) - 1; ?></div>
                            <p class="stat-label">Different document types available</p>
                        </div>
                        
                        <div class="stat-card">
                            <h3><i class="fas fa-calendar-check"></i> Updated</h3>
                            <div class="stat-number">2024</div>
                            <p class="stat-label">All documents updated this year</p>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-search"></i>
                        <h3>No Documents Found</h3>
                        <p>We couldn't find any legal documents matching your search criteria.</p>
                        <div class="empty-actions">
                            <a href="legal-documents.php" class="action-btn primary">
                                <i class="fas fa-redo"></i> Clear Search
                            </a>
                            <a href="?category=all" class="action-btn secondary">
                                <i class="fas fa-list"></i> View All Documents
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Information Section -->
                <div class="info-box" style="margin-top: 50px;">
                    <h4><i class="fas fa-balance-scale"></i> Legal Disclaimer</h4>
                    <p>The legal documents provided on this platform are templates for informational purposes only. They are not legal advice and should not be considered as such. While we strive to provide accurate and up-to-date templates, laws and regulations may change. It is strongly recommended that you:</p>
                    <ul>
                        <li>Consult with a qualified legal professional before using any document</li>
                        <li>Ensure the document meets your specific needs and circumstances</li>
                        <li>Verify that the document complies with current laws and regulations in your jurisdiction</li>
                        <li>Keep signed copies of all documents in a safe place</li>
                        <li>Register documents with relevant authorities when required by law</li>
                    </ul>
                    <p>ArdhiYetu and its affiliates are not responsible for any losses, damages, or legal issues arising from the use of these templates.</p>
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
                    <h3>Legal Support</h3>
                    <p><i class="fas fa-envelope"></i> legal@ardhiyetu.go.ke</p>
                    <p><i class="fas fa-phone"></i> 0700 000 002</p>
                    <p><i class="fas fa-clock"></i> Mon-Fri: 8:00 AM - 5:00 PM</p>
                </div>
                <div class="footer-section">
                    <h3>Important Links</h3>
                    <p><a href="legal-guidelines.php"><i class="fas fa-book"></i> Legal Guidelines</a></p>
                    <p><a href="faq.php"><i class="fas fa-question-circle"></i> FAQ</a></p>
                    <p><a href="terms.php"><i class="fas fa-file-contract"></i> Terms of Use</a></p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> ArdhiYetu Land Management System. Legal documents are provided as templates only. Consult a lawyer for legal advice.</p>
            </div>
        </div>
    </footer>

    <script src="../assets/js/script.js"></script>
    <script>
        // Document download function
        function downloadDocument(docId) {
            const doc = <?php echo json_encode($legal_documents); ?>.find(d => d.id === docId);
            if (doc) {
                alert(`Downloading: ${doc.title}\n\nIn a real system, this would download the document file.`);
                
                // Simulate download
                const content = `ArdhiYetu Legal Document\n=======================\n\nTitle: ${doc.title}\nDescription: ${doc.description}\nCategory: ${doc.category}\n\n${doc.preview_content}\n\n---\nGenerated from ArdhiYetu Legal Documents Library`;
                const blob = new Blob([content], { type: 'text/plain' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = doc.title.replace(/\s+/g, '_') + '.txt';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                
                // Log download (in real system, this would be a server-side call)
                console.log(`Downloaded document ID: ${docId}`);
            }
        }
        
        // Search functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('input[name="search"]');
            const categoryButtons = document.querySelectorAll('.category-btn');
            
            // Auto-submit search on Enter
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        this.form.submit();
                    }
                });
            }
            
            // Add active styling to current category
            categoryButtons.forEach(btn => {
                if (btn.href.includes('category=<?php echo $category; ?>')) {
                    btn.classList.add('active');
                }
            });
            
            // Smooth scroll for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    const href = this.getAttribute('href');
                    if (href === '#browse') {
                        e.preventDefault();
                        const target = document.querySelector(href);
                        if (target) {
                            target.scrollIntoView({ behavior: 'smooth' });
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>