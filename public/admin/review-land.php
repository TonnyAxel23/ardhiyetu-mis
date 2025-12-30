<?php
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

$land_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $review_notes = mysqli_real_escape_string($conn, $_POST['review_notes']);
    
    if ($action === 'approve') {
        $result = approveLandRegistration($land_id, $_SESSION['user_id'], $review_notes);
        if ($result['success']) {
            // Send approval email to land owner
            try {
                // Get land and owner details for email
                $sql = "SELECT l.*, u.name as owner_name, u.email as owner_email 
                        FROM land_records l 
                        JOIN users u ON l.owner_id = u.user_id 
                        WHERE l.record_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "i", $land_id);
                mysqli_stmt_execute($stmt);
                $land_result = mysqli_stmt_get_result($stmt);
                $land = mysqli_fetch_assoc($land_result);
                
                if ($land) {
                    $emailData = [
                        'user_email' => $land['owner_email'],
                        'user_name' => $land['owner_name'],
                        'parcel_no' => $land['parcel_no'],
                        'location' => $land['location'],
                        'size' => $land['size'],
                        'decision_date' => date('F j, Y'),
                        'reviewer_name' => $_SESSION['name'],
                        'review_notes' => $review_notes,
                        'admin_email' => ADMIN_EMAIL ?? 'admin@ardhiyetu.com',
                        'site_name' => SITE_NAME ?? 'ArdhiYetu Land Management System'
                    ];
                    
                    // Send land registration approval email
                    // Note: You need to create a land_approval.php template
                    $emailSender->sendEmail(
                        $land['owner_email'], 
                        $land['owner_name'], 
                        'land_approved', 
                        $emailData
                    );
                    
                    flash_message('success', $result['message'] . ' Email notification sent to the land owner.');
                }
            } catch (Exception $e) {
                error_log("Failed to send approval email: " . $e->getMessage());
                flash_message('success', $result['message'] . ' (Email notification failed)');
            }
        } else {
            flash_message('error', $result['message']);
        }
    } elseif ($action === 'reject') {
        $result = rejectLandRegistration($land_id, $_SESSION['user_id'], $review_notes);
        if ($result['success']) {
            // Send rejection email to land owner
            try {
                // Get land and owner details for email
                $sql = "SELECT l.*, u.name as owner_name, u.email as owner_email 
                        FROM land_records l 
                        JOIN users u ON l.owner_id = u.user_id 
                        WHERE l.record_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "i", $land_id);
                mysqli_stmt_execute($stmt);
                $land_result = mysqli_stmt_get_result($stmt);
                $land = mysqli_fetch_assoc($land_result);
                
                if ($land) {
                    $emailData = [
                        'user_email' => $land['owner_email'],
                        'user_name' => $land['owner_name'],
                        'parcel_no' => $land['parcel_no'],
                        'location' => $land['location'],
                        'decision_date' => date('F j, Y'),
                        'reviewer_name' => $_SESSION['name'],
                        'review_notes' => $review_notes,
                        'admin_email' => ADMIN_EMAIL ?? 'admin@ardhiyetu.com',
                        'site_name' => SITE_NAME ?? 'ArdhiYetu Land Management System'
                    ];
                    
                    // Send land registration rejection email
                    // Note: You need to create a land_rejected.php template
                    $emailSender->sendEmail(
                        $land['owner_email'], 
                        $land['owner_name'], 
                        'land_rejected', 
                        $emailData
                    );
                    
                    flash_message('success', $result['message'] . ' Email notification sent to the land owner.');
                }
            } catch (Exception $e) {
                error_log("Failed to send rejection email: " . $e->getMessage());
                flash_message('success', $result['message'] . ' (Email notification failed)');
            }
        } else {
            flash_message('error', $result['message']);
        }
    }
    
    redirect('lands.php');
}

$sql = "SELECT l.*, u.name as owner_name, u.email as owner_email 
        FROM land_records l 
        JOIN users u ON l.owner_id = u.user_id 
        WHERE l.record_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $land_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$land = mysqli_fetch_assoc($result);

if (!$land) {
    flash_message('error', 'Land record not found.');
    redirect('lands.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Land Registration - ArdhiYetu</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .review-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .review-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .detail-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }
        
        .detail-item label {
            display: block;
            color: #666;
            font-size: 12px;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        
        .detail-item span {
            display: block;
            color: var(--dark);
            font-size: 16px;
            font-weight: 500;
        }
        
        .document-preview, .description {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .decision-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        @media (max-width: 768px) {
            .review-container {
                grid-template-columns: 1fr;
            }
        }
        
        .email-notice {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            font-size: 14px;
        }
        
        .email-notice i {
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="admin-main">
            <header class="admin-header">
                <div class="header-left">
                    <h1>Review Land Registration</h1>
                    <p>Approve or reject land registration request</p>
                </div>
                <div class="header-right">
                    <a href="lands.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Lands
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

                <div class="review-container">
                    <div class="review-section">
                        <h3><i class="fas fa-landmark"></i> Land Details</h3>
                        
                        <div class="details-grid">
                            <div class="detail-item">
                                <label>Parcel Number</label>
                                <span><?php echo htmlspecialchars($land['parcel_no']); ?></span>
                            </div>
                            <div class="detail-item">
                                <label>Owner</label>
                                <span><?php echo htmlspecialchars($land['owner_name']); ?></span>
                                <small><?php echo htmlspecialchars($land['owner_email']); ?></small>
                            </div>
                            <div class="detail-item">
                                <label>Location</label>
                                <span><?php echo htmlspecialchars($land['location']); ?></span>
                            </div>
                            <div class="detail-item">
                                <label>Size</label>
                                <span><?php echo number_format($land['size'], 2); ?> acres</span>
                            </div>
                            <div class="detail-item">
                                <label>Status</label>
                                <span class="status-badge status-<?php echo $land['status']; ?>">
                                    <?php echo ucfirst($land['status']); ?>
                                </span>
                            </div>
                            <div class="detail-item">
                                <label>Submitted</label>
                                <span><?php echo format_date($land['registered_at']); ?></span>
                            </div>
                        </div>
                        
                        <?php if ($land['document_path']): ?>
                        <div class="document-preview">
                            <h4><i class="fas fa-file-alt"></i> Supporting Document</h4>
                            <a href="../../<?php echo htmlspecialchars($land['document_path']); ?>" 
                               target="_blank" class="btn">
                                <i class="fas fa-file-download"></i> View Document
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($land['description']): ?>
                        <div class="description">
                            <h4><i class="fas fa-align-left"></i> Description</h4>
                            <p><?php echo nl2br(htmlspecialchars($land['description'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="email-notice">
                            <i class="fas fa-envelope"></i>
                            An email notification will be sent to the owner upon decision.
                        </div>
                    </div>
                    
                    <div class="review-section">
                        <h3><i class="fas fa-clipboard-check"></i> Review Decision</h3>
                        
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="review_notes">Review Notes *</label>
                                <textarea id="review_notes" name="review_notes" rows="6" 
                                          class="form-control" 
                                          placeholder="Enter your review comments here..." 
                                          required></textarea>
                                <small>Provide detailed notes for your decision. These will be visible to the user and included in the email notification.</small>
                            </div>
                            
                            <div class="decision-buttons">
                                <button type="submit" name="action" value="approve" 
                                        class="btn btn-success" onclick="return confirmApprove()">
                                    <i class="fas fa-check-circle"></i> Approve Registration
                                </button>
                                
                                <button type="submit" name="action" value="reject" 
                                        class="btn btn-danger" onclick="return confirmReject()">
                                    <i class="fas fa-times-circle"></i> Reject Registration
                                </button>
                                
                                <a href="lands.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
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
            return confirm('Are you sure you want to approve this land registration? An email notification will be sent to the owner.');
        }
        
        function confirmReject() {
            const notes = document.getElementById('review_notes').value.trim();
            if (!notes) {
                alert('Please provide review notes before rejecting.');
                return false;
            }
            return confirm('Are you sure you want to reject this land registration? The user will be notified via email.');
        }
    </script>
</body>
</html>