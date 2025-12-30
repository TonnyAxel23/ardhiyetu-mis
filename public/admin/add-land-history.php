<?php
require_once '../../includes/init.php';
require_admin();

$record_id = isset($_GET['land_id']) ? intval($_GET['land_id']) : 0;

// Get land details
$land_sql = "SELECT l.*, u.name as owner_name 
            FROM land_records l 
            JOIN users u ON l.owner_id = u.user_id 
            WHERE l.record_id = ?";
$land_stmt = mysqli_prepare($conn, $land_sql);
mysqli_stmt_bind_param($land_stmt, "i", $record_id);
mysqli_stmt_execute($land_stmt);
$land_result = mysqli_stmt_get_result($land_stmt);
$land = mysqli_fetch_assoc($land_result);

if (!$land) {
    flash_message('error', 'Land record not found.');
    redirect('lands.php');
}

// Get all users for selection
$users_sql = "SELECT user_id, name, email FROM users ORDER BY name";
$users_result = mysqli_query($conn, $users_sql);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'add_ownership_history') {
        $from_user_id = !empty($_POST['from_user_id']) ? intval($_POST['from_user_id']) : null;
        $to_user_id = intval($_POST['to_user_id']);
        $transfer_type = mysqli_real_escape_string($conn, $_POST['transfer_type']);
        $effective_date = mysqli_real_escape_string($conn, $_POST['effective_date']);
        $notes = mysqli_real_escape_string($conn, $_POST['notes']);
        $document_path = '';
        
        // Handle document upload
        if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
            $file_info = $_FILES['document'];
            $file_ext = strtolower(pathinfo($file_info['name'], PATHINFO_EXTENSION));
            $file_mime = mime_content_type($file_info['tmp_name']);
            
            if (in_array($file_ext, ['pdf', 'jpg', 'jpeg', 'png']) && in_array($file_mime, $allowed_types)) {
                if ($file_info['size'] <= 5 * 1024 * 1024) {
                    $upload_dir = '../../uploads/history/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $safe_filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file_info['name']);
                    $filename = uniqid('history_', true) . '_' . $safe_filename;
                    $target_file = $upload_dir . $filename;
                    
                    if (move_uploaded_file($file_info['tmp_name'], $target_file)) {
                        $document_path = 'uploads/history/' . $filename;
                    }
                }
            }
        }
        
        // Insert ownership history
        $sql = "INSERT INTO ownership_history (
                record_id, from_user_id, to_user_id, transfer_type, 
                effective_date, notes, document_path, recorded_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iiissssi", 
            $record_id, $from_user_id, $to_user_id, $transfer_type,
            $effective_date, $notes, $document_path, $_SESSION['user_id']
        );
        
        if (mysqli_stmt_execute($stmt)) {
            flash_message('success', 'Ownership history added successfully.');
            redirect("land-history.php?id=$record_id");
        } else {
            flash_message('error', 'Failed to add ownership history.');
        }
        
    } elseif ($action === 'add_land_history') {
        $change_type = mysqli_real_escape_string($conn, $_POST['change_type']);
        $old_value = mysqli_real_escape_string($conn, $_POST['old_value']);
        $new_value = mysqli_real_escape_string($conn, $_POST['new_value']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        
        $sql = "INSERT INTO land_history (
                record_id, change_type, old_value, new_value, 
                description, changed_by
            ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "issssi", 
            $record_id, $change_type, $old_value, $new_value,
            $description, $_SESSION['user_id']
        );
        
        if (mysqli_stmt_execute($stmt)) {
            flash_message('success', 'Land history added successfully.');
            redirect("land-history.php?id=$record_id");
        } else {
            flash_message('error', 'Failed to add land history.');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Land History - ArdhiYetu</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .history-form-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #dee2e6;
        }
        
        .tab-button {
            padding: 10px 20px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-weight: 500;
            color: #666;
        }
        
        .tab-button.active {
            color: #007bff;
            border-bottom-color: #007bff;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .land-info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="admin-main">
            <header class="admin-header">
                <div class="header-left">
                    <h1>Add Land History</h1>
                    <p>Add historical records for land parcel</p>
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

                <div class="land-info-card">
                    <h3><?php echo htmlspecialchars($land['parcel_no']); ?></h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-top: 10px;">
                        <div><strong>Location:</strong> <?php echo htmlspecialchars($land['location']); ?></div>
                        <div><strong>Size:</strong> <?php echo number_format($land['size'], 2); ?> acres</div>
                        <div><strong>Current Owner:</strong> <?php echo htmlspecialchars($land['owner_name']); ?></div>
                        <div><strong>Status:</strong> <?php echo ucfirst($land['status']); ?></div>
                    </div>
                </div>

                <div class="history-form-container">
                    <div class="form-tabs">
                        <button class="tab-button active" onclick="switchTab('ownership')">
                            <i class="fas fa-exchange-alt"></i> Ownership History
                        </button>
                        <button class="tab-button" onclick="switchTab('land')">
                            <i class="fas fa-landmark"></i> Land Change History
                        </button>
                    </div>

                    <!-- Ownership History Form -->
                    <div id="ownership-tab" class="tab-content active">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="add_ownership_history">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="from_user_id">From Owner (Optional)</label>
                                    <select id="from_user_id" name="from_user_id" class="form-control">
                                        <option value="">Select Previous Owner</option>
                                        <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                                            <option value="<?php echo $user['user_id']; ?>">
                                                <?php echo htmlspecialchars($user['name'] . ' (' . $user['email'] . ')'); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <small>Leave blank for initial registration</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="to_user_id">To Owner *</label>
                                    <select id="to_user_id" name="to_user_id" class="form-control" required>
                                        <option value="">Select New Owner</option>
                                        <?php mysqli_data_seek($users_result, 0); ?>
                                        <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                                            <option value="<?php echo $user['user_id']; ?>">
                                                <?php echo htmlspecialchars($user['name'] . ' (' . $user['email'] . ')'); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="transfer_type">Transfer Type *</label>
                                    <select id="transfer_type" name="transfer_type" class="form-control" required>
                                        <option value="registration">Initial Registration</option>
                                        <option value="transfer">Transfer</option>
                                        <option value="partial_transfer">Partial Transfer</option>
                                        <option value="inheritance">Inheritance</option>
                                        <option value="court_order">Court Order</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="effective_date">Effective Date *</label>
                                    <input type="date" id="effective_date" name="effective_date" 
                                           class="form-control" required 
                                           max="<?php echo date('Y-m-d'); ?>">
                                </div>
                                
                                <div class="form-group full-width">
                                    <label for="notes">Notes (Optional)</label>
                                    <textarea id="notes" name="notes" rows="3" 
                                              class="form-control" 
                                              placeholder="Additional notes about this transfer"></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="document">Supporting Document (Optional)</label>
                                    <input type="file" id="document" name="document" 
                                           accept=".pdf,.jpg,.jpeg,.png"
                                           class="form-control">
                                    <small>Max 5MB. Allowed: PDF, JPG, PNG</small>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Add Ownership Record
                                </button>
                                <a href="land-history.php?id=<?php echo $record_id; ?>" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Land Change History Form -->
                    <div id="land-tab" class="tab-content">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add_land_history">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="change_type">Change Type *</label>
                                    <select id="change_type" name="change_type" class="form-control" required>
                                        <option value="size_change">Size Change</option>
                                        <option value="status_change">Status Change</option>
                                        <option value="location_update">Location Update</option>
                                        <option value="document_update">Document Update</option>
                                        <option value="split">Land Split</option>
                                        <option value="merge">Land Merge</option>
                                        <option value="other">Other Change</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="old_value">Old Value (Optional)</label>
                                    <input type="text" id="old_value" name="old_value" 
                                           class="form-control" 
                                           placeholder="Previous value before change">
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_value">New Value (Optional)</label>
                                    <input type="text" id="new_value" name="new_value" 
                                           class="form-control" 
                                           placeholder="New value after change">
                                </div>
                                
                                <div class="form-group full-width">
                                    <label for="description">Description *</label>
                                    <textarea id="description" name="description" rows="4" 
                                              class="form-control" required
                                              placeholder="Describe what changed and why"></textarea>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Add Change Record
                                </button>
                                <a href="land-history.php?id=<?php echo $record_id; ?>" class="btn btn-secondary">
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
        function switchTab(tabName) {
            // Update tab buttons
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
                if (btn.textContent.includes(tabName === 'ownership' ? 'Ownership' : 'Land Change')) {
                    btn.classList.add('active');
                }
            });
            
            // Update tab content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            document.getElementById(tabName + '-tab').classList.add('active');
        }
        
        // Set default date to today
        document.getElementById('effective_date').value = new Date().toISOString().split('T')[0];
        
        // Auto-fill description based on change type
        document.getElementById('change_type').addEventListener('change', function() {
            const description = document.getElementById('description');
            const changeType = this.value;
            
            const descriptions = {
                'size_change': 'Land size was adjusted.',
                'status_change': 'Land status was updated.',
                'location_update': 'Land location information was updated.',
                'document_update': 'Supporting documents were added or updated.',
                'split': 'Land was split into multiple parcels.',
                'merge': 'Land was merged with another parcel.',
                'other': 'Other changes were made to the land record.'
            };
            
            if (descriptions[changeType] && !description.value) {
                description.value = descriptions[changeType];
            }
        });
    </script>
</body>
</html>