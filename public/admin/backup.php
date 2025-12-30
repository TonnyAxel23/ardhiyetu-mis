<?php
require_once '../../includes/init.php';
require_admin();

$message = '';

// Handle backup creation
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'create':
            $backup_file = create_database_backup();
            if ($backup_file) {
                flash_message('success', "Backup created successfully: $backup_file");
                log_activity($_SESSION['user_id'], 'backup_create', 'Database backup created');
            } else {
                flash_message('error', 'Failed to create backup.');
            }
            header('Location: backup.php');
            exit();
            
        case 'download':
            $backup_id = (int)$_GET['id'];
            $sql = "SELECT * FROM backups WHERE backup_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $backup_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $backup = mysqli_fetch_assoc($result);
            
            if ($backup && file_exists($backup['file_path'])) {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($backup['file_path']) . '"');
                header('Content-Length: ' . filesize($backup['file_path']));
                readfile($backup['file_path']);
                exit();
            } else {
                flash_message('error', 'Backup file not found.');
                header('Location: backup.php');
                exit();
            }
            
        case 'delete':
            $backup_id = (int)$_GET['id'];
            $sql = "SELECT * FROM backups WHERE backup_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $backup_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $backup = mysqli_fetch_assoc($result);
            
            if ($backup) {
                if (file_exists($backup['file_path'])) {
                    unlink($backup['file_path']);
                }
                
                $sql = "DELETE FROM backups WHERE backup_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "i", $backup_id);
                if (mysqli_stmt_execute($stmt)) {
                    flash_message('success', 'Backup deleted successfully.');
                    log_activity($_SESSION['user_id'], 'backup_delete', "Backup ID $backup_id deleted");
                } else {
                    flash_message('error', 'Failed to delete backup record.');
                }
            }
            header('Location: backup.php');
            exit();
            
        case 'restore':
            $backup_id = (int)$_GET['id'];
            if (restore_database_backup($backup_id)) {
                flash_message('success', 'Database restored successfully.');
                log_activity($_SESSION['user_id'], 'backup_restore', "Database restored from backup ID $backup_id");
            } else {
                flash_message('error', 'Failed to restore database.');
            }
            header('Location: backup.php');
            exit();
    }
}

// Create backups table if not exists
$create_table_sql = "
    CREATE TABLE IF NOT EXISTS backups (
        backup_id INT PRIMARY KEY AUTO_INCREMENT,
        filename VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_size BIGINT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INT NOT NULL,
        FOREIGN KEY (created_by) REFERENCES users(user_id)
    )
";
mysqli_query($conn, $create_table_sql);

// Get all backups
$sql = "SELECT b.*, u.name as creator_name 
        FROM backups b 
        LEFT JOIN users u ON b.created_by = u.user_id 
        ORDER BY b.created_at DESC";
$backups = mysqli_query($conn, $sql);

// Get backup statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total_backups,
        SUM(file_size) as total_size,
        MAX(created_at) as latest_backup,
        MIN(created_at) as oldest_backup
    FROM backups
";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

function create_database_backup() {
    global $conn;
    
    $backup_dir = '../../backups/';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "backup_{$timestamp}.sql";
    $filepath = $backup_dir . $filename;
    
    // Get all tables
    $tables = [];
    $result = mysqli_query($conn, 'SHOW TABLES');
    while ($row = mysqli_fetch_row($result)) {
        $tables[] = $row[0];
    }
    
    $output = "-- Database Backup\n";
    $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $output .= "-- Database: " . DB_NAME . "\n\n";
    
    foreach ($tables as $table) {
        // Drop table if exists
        $output .= "DROP TABLE IF EXISTS `$table`;\n\n";
        
        // Create table structure
        $result = mysqli_query($conn, "SHOW CREATE TABLE `$table`");
        $row = mysqli_fetch_row($result);
        $output .= $row[1] . ";\n\n";
        
        // Get table data
        $result = mysqli_query($conn, "SELECT * FROM `$table`");
        $num_fields = mysqli_num_fields($result);
        
        while ($row = mysqli_fetch_row($result)) {
            $output .= "INSERT INTO `$table` VALUES(";
            for ($i = 0; $i < $num_fields; $i++) {
                $row[$i] = addslashes($row[$i]);
                $row[$i] = str_replace("\n", "\\n", $row[$i]);
                if (isset($row[$i])) {
                    $output .= '"' . $row[$i] . '"';
                } else {
                    $output .= '""';
                }
                if ($i < ($num_fields - 1)) {
                    $output .= ',';
                }
            }
            $output .= ");\n";
        }
        $output .= "\n";
    }
    
    // Save to file
    if (file_put_contents($filepath, $output)) {
        $file_size = filesize($filepath);
        
        // Save backup record
        $sql = "INSERT INTO backups (filename, file_path, file_size, created_by) 
                VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssii", $filename, $filepath, $file_size, $_SESSION['user_id']);
        mysqli_stmt_execute($stmt);
        
        // Update last backup setting
        $sql = "UPDATE settings SET setting_value = ?, updated_at = NOW() 
                WHERE setting_key = 'last_backup'";
        $stmt = mysqli_prepare($conn, $sql);
        $timestamp = date('Y-m-d H:i:s');
        mysqli_stmt_bind_param($stmt, "s", $timestamp);
        mysqli_stmt_execute($stmt);
        
        return $filename;
    }
    
    return false;
}

function restore_database_backup($backup_id) {
    global $conn;
    
    $sql = "SELECT * FROM backups WHERE backup_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $backup_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $backup = mysqli_fetch_assoc($result);
    
    if (!$backup || !file_exists($backup['file_path'])) {
        return false;
    }
    
    // Read backup file
    $sql_content = file_get_contents($backup['file_path']);
    $queries = explode(';', $sql_content);
    
    // Execute each query
    foreach ($queries as $query) {
        if (trim($query) != '') {
            mysqli_query($conn, $query);
        }
    }
    
    return true;
}

function format_file_size($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup - ArdhiYetu</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .backup-actions {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .backup-action-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: var(--shadow);
            flex: 1;
            text-align: center;
        }
        
        .backup-action-card h3 {
            margin-bottom: 10px;
            color: var(--dark);
        }
        
        .backup-action-card p {
            color: var(--gray);
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .backup-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .backup-stat {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: var(--shadow);
            text-align: center;
        }
        
        .backup-stat h4 {
            font-size: 24px;
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .backup-stat p {
            color: var(--gray);
            font-size: 14px;
        }
        
        .backup-list {
            background: white;
            border-radius: 10px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .backup-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }
        
        .backup-item:last-child {
            border-bottom: none;
        }
        
        .backup-icon {
            width: 40px;
            height: 40px;
            background: var(--light);
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .backup-info {
            flex: 1;
        }
        
        .backup-info h4 {
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .backup-meta {
            display: flex;
            gap: 15px;
            color: var(--gray);
            font-size: 12px;
        }
        
        .backup-actions-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #219653;
        }
        
        .btn-warning {
            background: var(--warning);
            color: white;
        }
        
        .btn-warning:hover {
            background: #e67e22;
        }
        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
        }
        
        .modal-content h3 {
            margin-bottom: 20px;
            color: var(--dark);
        }
        
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .empty-backups {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
        }
        
        .empty-backups i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .btn-secondary {
            background: var(--gray);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="admin-main">
            <header class="admin-header">
                <div class="header-left">
                    <h1>Database Backup</h1>
                    <p>Backup and restore your database</p>
                </div>
                <div class="header-right">
                    <button class="btn btn-primary" onclick="showCreateBackupModal()">
                        <i class="fas fa-database"></i> Create Backup
                    </button>
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

                <div class="backup-stats">
                    <div class="backup-stat">
                        <h4><?php echo $stats['total_backups'] ?? 0; ?></h4>
                        <p>Total Backups</p>
                    </div>
                    <div class="backup-stat">
                        <h4><?php echo format_file_size($stats['total_size'] ?? 0); ?></h4>
                        <p>Total Size</p>
                    </div>
                    <div class="backup-stat">
                        <h4>
                            <?php if ($stats['latest_backup']): ?>
                                <?php echo format_date($stats['latest_backup'], 'M j, Y'); ?>
                            <?php else: ?>
                                Never
                            <?php endif; ?>
                        </h4>
                        <p>Last Backup</p>
                    </div>
                    <div class="backup-stat">
                        <h4>
                            <?php if ($stats['oldest_backup']): ?>
                                <?php echo format_date($stats['oldest_backup'], 'M j, Y'); ?>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </h4>
                        <p>Oldest Backup</p>
                    </div>
                </div>

                <div class="backup-actions">
                    <div class="backup-action-card">
                        <h3><i class="fas fa-database"></i> Create Backup</h3>
                        <p>Create a new backup of the entire database</p>
                        <button class="btn btn-primary" onclick="showCreateBackupModal()">
                            <i class="fas fa-plus"></i> Create Now
                        </button>
                    </div>
                    
                    <div class="backup-action-card">
                        <h3><i class="fas fa-cloud-upload-alt"></i> Upload Backup</h3>
                        <p>Upload a backup file from your computer</p>
                        <button class="btn" onclick="showUploadBackupModal()">
                            <i class="fas fa-upload"></i> Upload Backup
                        </button>
                    </div>
                    
                    <div class="backup-action-card">
                        <h3><i class="fas fa-cog"></i> Auto Backup</h3>
                        <p>Configure automatic backup schedule</p>
                        <a href="settings.php?category=backup" class="btn">
                            <i class="fas fa-sliders-h"></i> Configure
                        </a>
                    </div>
                </div>

                <div class="table-card">
                    <div class="table-header">
                        <h3>Recent Backups</h3>
                        <span><?php echo mysqli_num_rows($backups); ?> backups found</span>
                    </div>
                    
                    <div class="table-content">
                        <?php if (mysqli_num_rows($backups) > 0): ?>
                            <div class="backup-list">
                                <?php while ($backup = mysqli_fetch_assoc($backups)): ?>
                                    <div class="backup-item">
                                        <div class="backup-icon">
                                            <i class="fas fa-database"></i>
                                        </div>
                                        
                                        <div class="backup-info">
                                            <h4><?php echo htmlspecialchars($backup['filename']); ?></h4>
                                            <div class="backup-meta">
                                                <span><i class="fas fa-calendar"></i> <?php echo format_date($backup['created_at']); ?></span>
                                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($backup['creator_name']); ?></span>
                                                <span><i class="fas fa-hdd"></i> <?php echo format_file_size($backup['file_size']); ?></span>
                                                <span><i class="fas fa-map-marker-alt"></i> <?php echo dirname($backup['file_path']); ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="backup-actions-buttons">
                                            <a href="backup.php?action=download&id=<?php echo $backup['backup_id']; ?>" 
                                               class="btn-small" title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <button class="btn-small btn-success" 
                                                    onclick="showRestoreModal(<?php echo $backup['backup_id']; ?>, '<?php echo htmlspecialchars($backup['filename']); ?>')"
                                                    title="Restore">
                                                <i class="fas fa-history"></i>
                                            </button>
                                            <button class="btn-small btn-warning" 
                                                    onclick="showDeleteModal(<?php echo $backup['backup_id']; ?>, '<?php echo htmlspecialchars($backup['filename']); ?>')"
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-backups">
                                <i class="fas fa-database"></i>
                                <h3>No Backups Found</h3>
                                <p>You haven't created any backups yet. Create your first backup now.</p>
                                <button class="btn btn-primary" onclick="showCreateBackupModal()">
                                    <i class="fas fa-plus"></i> Create First Backup
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Backup Modal -->
    <div class="modal-overlay" id="createBackupModal">
        <div class="modal-content">
            <h3>Create Database Backup</h3>
            <p>This will create a complete backup of your database. The process may take a few moments.</p>
            <p><strong>Note:</strong> During backup creation, the system may become temporarily slower.</p>
            
            <div class="modal-actions">
                <button class="btn-secondary" onclick="hideCreateBackupModal()">Cancel</button>
                <a href="backup.php?action=create" class="btn btn-primary">
                    <i class="fas fa-database"></i> Create Backup
                </a>
            </div>
        </div>
    </div>

    <!-- Restore Backup Modal -->
    <div class="modal-overlay" id="restoreBackupModal">
        <div class="modal-content">
            <h3>Restore Database</h3>
            <p id="restoreMessage">Are you sure you want to restore from this backup?</p>
            <p><strong>Warning:</strong> This will overwrite your current database. This action cannot be undone.</p>
            
            <div class="modal-actions">
                <button class="btn-secondary" onclick="hideRestoreModal()">Cancel</button>
                <a href="#" id="restoreLink" class="btn btn-success">
                    <i class="fas fa-history"></i> Restore Now
                </a>
            </div>
        </div>
    </div>

    <!-- Delete Backup Modal -->
    <div class="modal-overlay" id="deleteBackupModal">
        <div class="modal-content">
            <h3>Delete Backup</h3>
            <p id="deleteMessage">Are you sure you want to delete this backup?</p>
            <p><strong>Note:</strong> This action cannot be undone.</p>
            
            <div class="modal-actions">
                <button class="btn-secondary" onclick="hideDeleteModal()">Cancel</button>
                <a href="#" id="deleteLink" class="btn btn-warning">
                    <i class="fas fa-trash"></i> Delete Backup
                </a>
            </div>
        </div>
    </div>

    <!-- Upload Backup Modal -->
    <div class="modal-overlay" id="uploadBackupModal">
        <div class="modal-content">
            <h3>Upload Backup File</h3>
            <form method="POST" action="upload_backup.php" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="backup_file">Select Backup File</label>
                    <input type="file" id="backup_file" name="backup_file" class="form-control" accept=".sql,.gz,.zip">
                    <small>Supported formats: .sql, .gz, .zip (max: 100MB)</small>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="hideUploadBackupModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Upload Backup
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showCreateBackupModal() {
            document.getElementById('createBackupModal').style.display = 'flex';
        }
        
        function hideCreateBackupModal() {
            document.getElementById('createBackupModal').style.display = 'none';
        }
        
        function showRestoreModal(backupId, filename) {
            document.getElementById('restoreMessage').innerHTML = 
                `Are you sure you want to restore from backup: <strong>${filename}</strong>?`;
            document.getElementById('restoreLink').href = `backup.php?action=restore&id=${backupId}`;
            document.getElementById('restoreBackupModal').style.display = 'flex';
        }
        
        function hideRestoreModal() {
            document.getElementById('restoreBackupModal').style.display = 'none';
        }
        
        function showDeleteModal(backupId, filename) {
            document.getElementById('deleteMessage').innerHTML = 
                `Are you sure you want to delete backup: <strong>${filename}</strong>?`;
            document.getElementById('deleteLink').href = `backup.php?action=delete&id=${backupId}`;
            document.getElementById('deleteBackupModal').style.display = 'flex';
        }
        
        function hideDeleteModal() {
            document.getElementById('deleteBackupModal').style.display = 'none';
        }
        
        function showUploadBackupModal() {
            document.getElementById('uploadBackupModal').style.display = 'flex';
        }
        
        function hideUploadBackupModal() {
            document.getElementById('uploadBackupModal').style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                hideCreateBackupModal();
                hideRestoreModal();
                hideDeleteModal();
                hideUploadBackupModal();
            }
        };
    </script>
</body>
</html>