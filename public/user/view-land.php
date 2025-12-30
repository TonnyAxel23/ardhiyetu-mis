<?php
require_once __DIR__ . '/../../includes/init.php';
require_login();

$user_id = $_SESSION['user_id'];
$record_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($record_id <= 0) {
    flash_message('error', 'Invalid land record ID.');
    redirect('my-lands.php');
}

// Get land details with prepared statement for security
$sql = "SELECT l.*, u.name as owner_name, u.email as owner_email, u.phone as owner_phone
        FROM land_records l
        JOIN users u ON l.owner_id = u.user_id
        WHERE l.record_id = ?
        AND (l.owner_id = ? OR ? IN (SELECT user_id FROM users WHERE role IN ('admin', 'officer')))";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'iii', $record_id, $user_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) != 1) {
    flash_message('error', 'Land record not found or access denied.');
    redirect('my-lands.php');
}

$land = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Get transfer history
$transfers_sql = "SELECT t.*, u1.name as from_name, u1.email as from_email, 
                         u2.name as to_name, u2.email as to_email
                  FROM ownership_transfers t
                  JOIN users u1 ON t.from_user_id = u1.user_id
                  JOIN users u2 ON t.to_user_id = u2.user_id
                  WHERE t.record_id = ?
                  ORDER BY t.submitted_at DESC";
$transfers_stmt = mysqli_prepare($conn, $transfers_sql);
mysqli_stmt_bind_param($transfers_stmt, 'i', $record_id);
mysqli_stmt_execute($transfers_stmt);
$transfers_result = mysqli_stmt_get_result($transfers_stmt);
$transfer_count = mysqli_num_rows($transfers_result);

// Check if main document exists
$has_main_document = !empty($land['document_path']) && file_exists($land['document_path']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($land['parcel_no']); ?> - Land Details - ArdhiYetu</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/view-land.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="../index.php" class="logo">
                <i class="fas fa-landmark"></i> ArdhiYetu
            </a>
            <div class="nav-links">
                <a href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="my-lands.php"><i class="fas fa-landmark"></i> My Lands</a>
                <a href="land-map.php"><i class="fas fa-map"></i> Land Map</a>
                <a href="transfer-land.php"><i class="fas fa-exchange-alt"></i> Transfer</a>
                <a href="public/documents.php"><i class="fas fa-file-alt"></i> Documents</a>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <?php if (is_admin()): ?>
                    <a href="../admin/index.php" class="btn admin-btn"><i class="fas fa-user-shield"></i> Admin</a>
                <?php endif; ?>
                <a href="../logout.php" class="btn logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
            <button class="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <main class="land-view-container">
        <div class="container">
            <!-- Breadcrumb Navigation -->
            <nav class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <a href="my-lands.php">My Lands</a>
                <i class="fas fa-chevron-right"></i>
                <span><?php echo htmlspecialchars($land['parcel_no']); ?></span>
            </nav>

            <!-- Header Section -->
            <div class="view-header">
                <div class="header-content">
                    <div class="title-section">
                        <h1><i class="fas fa-landmark"></i> <?php echo htmlspecialchars($land['parcel_no']); ?></h1>
                        <p class="subtitle"><?php echo htmlspecialchars($land['location']); ?></p>
                        <div class="meta-info">
                            <span class="status-badge status-<?php echo $land['status']; ?>">
                                <i class="fas fa-circle"></i> <?php echo ucfirst($land['status']); ?>
                            </span>
                            <span class="meta-item">
                                <i class="fas fa-ruler-combined"></i> <?php echo $land['size']; ?> acres
                            </span>
                            <span class="meta-item">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($land['owner_name']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <a href="my-lands.php" class="btn secondary">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                        <?php if ($land['owner_id'] == $user_id): ?>
                            <a href="edit-land.php?id=<?php echo $record_id; ?>" class="btn">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="print-record.php?id=<?php echo $record_id; ?>" class="btn secondary" target="_blank">
                                <i class="fas fa-print"></i> Print
                            </a>
                            <div class="dropdown">
                                <button class="btn more-actions">
                                    <i class="fas fa-ellipsis-v"></i> More
                                </button>
                                <div class="dropdown-content">
                                    <a href="generate-certificate.php?id=<?php echo $record_id; ?>">
                                        <i class="fas fa-certificate"></i> Generate Certificate
                                    </a>
                                    <a href="share-record.php?id=<?php echo $record_id; ?>">
                                        <i class="fas fa-share-alt"></i> Share Record
                                    </a>
                                    <a href="verify-record.php?id=<?php echo $record_id; ?>">
                                        <i class="fas fa-check-circle"></i> Verify Record
                                    </a>
                                    <?php if ($land['status'] == 'active'): ?>
                                        <a href="transfer-land.php?land_id=<?php echo $record_id; ?>">
                                            <i class="fas fa-exchange-alt"></i> Transfer Ownership
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="land-view-content">
                <!-- Left Column: Details -->
                <div class="left-column">
                    <!-- Basic Information Card -->
                    <div class="details-card card">
                        <div class="card-header">
                            <h2><i class="fas fa-info-circle"></i> Basic Information</h2>
                        </div>
                        <div class="card-body">
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">Owner</span>
                                    <span class="info-value"><?php echo htmlspecialchars($land['owner_name']); ?></span>
                                </div>
                                <?php if (!empty($land['owner_email'])): ?>
                                <div class="info-item">
                                    <span class="info-label">Email</span>
                                    <span class="info-value"><?php echo htmlspecialchars($land['owner_email']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($land['owner_phone'])): ?>
                                <div class="info-item">
                                    <span class="info-label">Phone</span>
                                    <span class="info-value"><?php echo htmlspecialchars($land['owner_phone']); ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="info-item">
                                    <span class="info-label">Location</span>
                                    <span class="info-value"><?php echo htmlspecialchars($land['location']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Size</span>
                                    <span class="info-value"><?php echo $land['size']; ?> acres</span>
                                </div>
                                <?php if (!empty($land['coordinates'])): ?>
                                <div class="info-item">
                                    <span class="info-label">Coordinates</span>
                                    <span class="info-value code"><?php echo htmlspecialchars($land['coordinates']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($land['land_use'])): ?>
                                <div class="info-item">
                                    <span class="info-label">Land Use</span>
                                    <span class="info-value"><?php echo htmlspecialchars($land['land_use']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($land['value'])): ?>
                                <div class="info-item">
                                    <span class="info-label">Estimated Value</span>
                                    <span class="info-value">Ksh <?php echo number_format($land['value'], 2); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Registration Details Card -->
                    <div class="details-card card">
                        <div class="card-header">
                            <h2><i class="fas fa-calendar-alt"></i> Registration Details</h2>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <div class="timeline-item">
                                    <div class="timeline-marker success">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <span class="timeline-date"><?php echo format_date($land['registered_at']); ?></span>
                                        <h4>Initial Registration</h4>
                                        <p>Land parcel was registered in the system</p>
                                    </div>
                                </div>
                                <div class="timeline-item">
                                    <div class="timeline-marker info">
                                        <i class="fas fa-sync-alt"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <span class="timeline-date"><?php echo format_date($land['updated_at']); ?></span>
                                        <h4>Last Updated</h4>
                                        <p>Record was last modified</p>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($has_main_document): ?>
                            <div class="document-preview">
                                <a href="<?php echo htmlspecialchars($land['document_path']); ?>" 
                                   target="_blank" 
                                   class="document-link">
                                    <i class="fas fa-file-pdf"></i>
                                    <span>View Title Deed</span>
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            </div>
                            <?php elseif (!empty($land['document_path'])): ?>
                            <div class="document-preview">
                                <div class="document-link unavailable">
                                    <i class="fas fa-file-exclamation"></i>
                                    <span>Document unavailable (File not found)</span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Description Card -->
                    <?php if (!empty($land['description'])): ?>
                    <div class="details-card card">
                        <div class="card-header">
                            <h2><i class="fas fa-file-alt"></i> Description</h2>
                        </div>
                        <div class="card-body">
                            <div class="description-content">
                                <?php echo nl2br(htmlspecialchars($land['description'])); ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Right Column: History & Actions -->
                <div class="right-column">
                    <!-- Transfer History Card -->
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-history"></i> Transfer History</h2>
                            <span class="badge"><?php echo $transfer_count; ?></span>
                        </div>
                        <div class="card-body">
                            <?php if ($transfer_count > 0): ?>
                                <div class="transfer-list">
                                    <?php while ($transfer = mysqli_fetch_assoc($transfers_result)): ?>
                                    <div class="transfer-item status-<?php echo $transfer['status']; ?>">
                                        <div class="transfer-icon">
                                            <?php if ($transfer['status'] == 'completed'): ?>
                                                <i class="fas fa-check-circle success"></i>
                                            <?php elseif ($transfer['status'] == 'pending'): ?>
                                                <i class="fas fa-clock warning"></i>
                                            <?php else: ?>
                                                <i class="fas fa-times-circle danger"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="transfer-content">
                                            <div class="transfer-header">
                                                <h4><?php echo htmlspecialchars($transfer['reference_no']); ?></h4>
                                                <span class="transfer-status"><?php echo ucfirst($transfer['status']); ?></span>
                                            </div>
                                            <div class="transfer-details">
                                                <div class="transfer-from-to">
                                                    <span class="from">
                                                        <i class="fas fa-user"></i>
                                                        <?php echo htmlspecialchars($transfer['from_name']); ?>
                                                    </span>
                                                    <i class="fas fa-arrow-right"></i>
                                                    <span class="to">
                                                        <i class="fas fa-user"></i>
                                                        <?php echo htmlspecialchars($transfer['to_name']); ?>
                                                    </span>
                                                </div>
                                                <div class="transfer-meta">
                                                    <span><i class="fas fa-calendar"></i> <?php echo format_date($transfer['submitted_at']); ?></span>
                                                    <?php if (!empty($transfer['price'])): ?>
                                                    <span><i class="fas fa-money-bill"></i> Ksh <?php echo number_format($transfer['price'], 2); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-history"></i>
                                    <h3>No Transfer History</h3>
                                    <p>This land has not been transferred before</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Actions Card -->
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                        </div>
                        <div class="card-body">
                            <div class="actions-grid">
                                <?php if ($land['owner_id'] == $user_id): ?>
                                    <?php if ($land['status'] == 'active'): ?>
                                    <a href="transfer-land.php?land_id=<?php echo $record_id; ?>" class="action-btn primary">
                                        <i class="fas fa-exchange-alt"></i>
                                        <span>Transfer Ownership</span>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <a href="generate-certificate.php?id=<?php echo $record_id; ?>" class="action-btn">
                                        <i class="fas fa-certificate"></i>
                                        <span>Generate Certificate</span>
                                    </a>
                                    
                                    <a href="share-record.php?id=<?php echo $record_id; ?>" class="action-btn">
                                        <i class="fas fa-share-alt"></i>
                                        <span>Share Record</span>
                                    </a>
                                    
                                    <a href="verify-record.php?id=<?php echo $record_id; ?>" class="action-btn">
                                        <i class="fas fa-check-circle"></i>
                                        <span>Verify Record</span>
                                    </a>
                                    
                                    <a href="#" class="action-btn" onclick="copyToClipboard('<?php echo $record_id; ?>')">
                                        <i class="fas fa-copy"></i>
                                        <span>Copy ID</span>
                                    </a>
                                    
                                    <?php if (is_admin()): ?>
                                    <button class="action-btn danger" onclick="confirmDelete()">
                                        <i class="fas fa-trash"></i>
                                        <span>Delete Record</span>
                                    </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="view-only-notice">
                                        <i class="fas fa-eye"></i>
                                        <div>
                                            <h3>View Only Mode</h3>
                                            <p>You are viewing this record with read-only permissions</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Documents Card -->
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-folder"></i> Documents</h2>
                        </div>
                        <div class="card-body">
                            <div class="documents-list">
                                <?php if ($has_main_document): ?>
                                <div class="document-item">
                                    <i class="fas fa-file-pdf file-icon"></i>
                                    <div class="document-info">
                                        <h4>Title Deed</h4>
                                        <p>Official ownership document</p>
                                    </div>
                                    <a href="<?php echo htmlspecialchars($land['document_path']); ?>" 
                                       target="_blank" 
                                       class="btn-small download-btn" 
                                       title="Download">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                                <?php elseif (!empty($land['document_path'])): ?>
                                <div class="document-item unavailable">
                                    <i class="fas fa-file-exclamation file-icon"></i>
                                    <div class="document-info">
                                        <h4>Title Deed</h4>
                                        <p>Document file not found at specified path</p>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="document-item">
                                    <i class="fas fa-file-upload file-icon"></i>
                                    <div class="document-info">
                                        <h4>No Documents Uploaded</h4>
                                        <p>Upload title deed in Edit mode</p>
                                    </div>
                                    <?php if ($land['owner_id'] == $user_id): ?>
                                    <a href="edit-land.php?id=<?php echo $record_id; ?>" 
                                       class="btn-small download-btn" 
                                       title="Upload Document">
                                        <i class="fas fa-upload"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Floating Action Button for Mobile -->
    <button class="fab" onclick="toggleActions()">
        <i class="fas fa-ellipsis-v"></i>
    </button>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><i class="fas fa-landmark"></i> ArdhiYetu</h3>
                    <p>Digital Land Administration System</p>
                </div>
                <div class="footer-section">
                    <h3>Record Information</h3>
                    <div class="footer-info">
                        <p><strong>Parcel No:</strong> <?php echo htmlspecialchars($land['parcel_no']); ?></p>
                        <p><strong>Record ID:</strong> <?php echo $record_id; ?></p>
                        <p><strong>Last Updated:</strong> <?php echo format_date($land['updated_at']); ?></p>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> ArdhiYetu Land Management System</p>
                <p class="page-load-time" id="loadTime"></p>
            </div>
        </div>
    </footer>

    <!-- Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Confirm Deletion</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete land record <strong><?php echo htmlspecialchars($land['parcel_no']); ?></strong>?</p>
                <p class="warning"><i class="fas fa-exclamation-circle"></i> This action cannot be undone and will permanently remove all data associated with this record.</p>
            </div>
            <div class="modal-footer">
                <button class="btn secondary" onclick="closeModal()">Cancel</button>
                <a href="delete-land.php?id=<?php echo $record_id; ?>" class="btn danger">Delete Record</a>
            </div>
        </div>
    </div>

    <script src="../../assets/js/script.js"></script>
    <script>
        // Page load time
        window.addEventListener('load', function() {
            const loadTime = performance.now();
            document.getElementById('loadTime').textContent = `Page loaded in ${Math.round(loadTime)}ms`;
        });

        // Copy to clipboard function
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                showToast('Record ID copied to clipboard!');
            }).catch(err => {
                console.error('Failed to copy: ', err);
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                showToast('Record ID copied to clipboard!');
            });
        }

        // Modal functions
        function confirmDelete() {
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Toast notification
        function showToast(message) {
            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.classList.add('show');
            }, 100);
            
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => document.body.removeChild(toast), 300);
            }, 3000);
        }

        // Mobile actions toggle
        function toggleActions() {
            const actions = document.querySelector('.action-buttons');
            actions.classList.toggle('show');
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-content').forEach(dropdown => {
                    dropdown.style.display = 'none';
                });
            }
        });

        // Initialize dropdowns
        document.querySelectorAll('.more-actions').forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation();
                const dropdown = this.nextElementSibling;
                dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
            });
        });

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.fab') && !e.target.closest('.action-buttons')) {
                document.querySelector('.action-buttons').classList.remove('show');
            }
        });
    </script>
</body>
</html>

<?php
// Free result sets
mysqli_free_result($result);
mysqli_free_result($transfers_result);
?>