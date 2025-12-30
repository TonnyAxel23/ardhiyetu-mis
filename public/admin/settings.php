<?php
require_once '../../includes/init.php';
require_admin();

// Check if system_settings table exists, create if not
$check_table = mysqli_query($conn, "SHOW TABLES LIKE 'system_settings'");
if (mysqli_num_rows($check_table) == 0) {
    // Create the system_settings table
    $create_table_sql = "
        CREATE TABLE system_settings (
            setting_id INT PRIMARY KEY AUTO_INCREMENT,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            setting_type ENUM('text', 'number', 'boolean', 'json', 'array') DEFAULT 'text',
            category VARCHAR(50) NOT NULL DEFAULT 'general',
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_category (category),
            INDEX idx_key (setting_key)
        )
    ";
    
    if (mysqli_query($conn, $create_table_sql)) {
        // Insert default settings
        $default_settings_sql = "
            INSERT INTO system_settings (setting_key, setting_value, setting_type, category, description) VALUES
            ('site_name', 'ArdhiYetu MIS', 'text', 'general', 'The name displayed throughout the application'),
            ('site_description', 'Land Information Management System', 'text', 'general', 'Brief description of your application'),
            ('site_logo', '', 'text', 'general', 'Path to the site logo'),
            ('items_per_page', '50', 'number', 'general', 'Number of items to display per page in tables'),
            ('allow_registration', 'true', 'boolean', 'security', 'Allow new users to register accounts'),
            ('password_min_length', '8', 'number', 'security', 'Minimum characters required for passwords'),
            ('max_login_attempts', '5', 'number', 'security', 'Maximum failed login attempts before lockout'),
            ('session_timeout', '30', 'number', 'security', 'Time before inactive sessions expire (minutes)'),
            ('system_email', 'admin@ardhiyetu.com', 'text', 'email', 'Email address used for system notifications'),
            ('contact_email', 'contact@ardhiyetu.com', 'text', 'email', 'Email address for user inquiries'),
            ('smtp_host', '', 'text', 'email', 'SMTP server address'),
            ('smtp_port', '587', 'number', 'email', 'SMTP server port'),
            ('smtp_username', '', 'text', 'email', 'SMTP authentication username'),
            ('smtp_password', '', 'text', 'email', 'SMTP authentication password'),
            ('maintenance_mode', 'false', 'boolean', 'system', 'Enable maintenance mode to restrict access'),
            ('debug_mode', 'false', 'boolean', 'system', 'Enable debug mode for development (displays errors)'),
            ('timezone', 'Africa/Dar_es_Salaam', 'text', 'system', 'System timezone for all date/time displays'),
            ('date_format', 'Y-m-d', 'text', 'system', 'Format for displaying dates'),
            ('backup_schedule', 'daily', 'text', 'backup', 'How often to automatically backup the database'),
            ('backup_retention', '30', 'number', 'backup', 'How long to keep backup files before deleting (days)'),
            ('last_backup', 'Never', 'text', 'backup', 'When the last backup was created')
        ";
        
        mysqli_query($conn, $default_settings_sql);
        flash_message('success', 'System settings table created successfully with default values.');
    } else {
        flash_message('error', 'Failed to create system settings table: ' . mysqli_error($conn));
    }
}

$category = isset($_GET['category']) ? $_GET['category'] : 'general';
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'setting_') === 0) {
            $setting_key = substr($key, 8);
            $setting_value = trim($value);
            
            $sql = "UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ss", $setting_value, $setting_key);
            
            if (mysqli_stmt_execute($stmt)) {
                log_activity($_SESSION['user_id'], 'setting_update', "Updated setting: $setting_key");
            }
        }
    }
    
    // Handle file uploads
    if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../assets/uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_name = 'logo_' . time() . '_' . basename($_FILES['site_logo']['name']);
        $target_file = $upload_dir . $file_name;
        
        // Validate image
        $image_info = getimagesize($_FILES['site_logo']['tmp_name']);
        if ($image_info !== false) {
            if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $target_file)) {
                $sql = "UPDATE system_settings SET setting_value = ? WHERE setting_key = 'site_logo'";
                $stmt = mysqli_prepare($conn, $sql);
                $logo_path = 'assets/uploads/' . $file_name;
                mysqli_stmt_bind_param($stmt, "s", $logo_path);
                mysqli_stmt_execute($stmt);
            }
        }
    }
    
    flash_message('success', 'Settings updated successfully.');
    header('Location: settings.php?category=' . $category);
    exit();
}

// Get settings by category
$sql = "SELECT * FROM system_settings WHERE category = ? ORDER BY setting_key";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $category);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$settings = [];
while ($row = mysqli_fetch_assoc($result)) {
    $settings[$row['setting_key']] = $row;
}

// Get all categories
$categories_sql = "SELECT DISTINCT category FROM system_settings ORDER BY category";
$categories_result = mysqli_query($conn, $categories_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - ArdhiYetu</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .settings-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 20px;
        }
        
        .settings-sidebar {
            background: white;
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 20px;
        }
        
        .settings-sidebar h3 {
            color: var(--dark);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .settings-nav {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .settings-nav a {
            padding: 12px 15px;
            text-decoration: none;
            color: var(--dark);
            border-radius: 8px;
            transition: var(--transition);
        }
        
        .settings-nav a:hover {
            background: #f8f9fa;
        }
        
        .settings-nav a.active {
            background: var(--primary);
            color: white;
        }
        
        .settings-content {
            background: white;
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 30px;
        }
        
        .settings-group {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .settings-group:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .settings-group h3 {
            color: var(--dark);
            margin-bottom: 20px;
            font-size: 18px;
        }
        
        .setting-row {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 20px;
            margin-bottom: 20px;
            align-items: start;
        }
        
        .setting-label {
            padding-top: 10px;
        }
        
        .setting-label label {
            font-weight: 500;
            color: var(--dark);
        }
        
        .setting-label small {
            display: block;
            color: var(--gray);
            margin-top: 5px;
            font-size: 12px;
        }
        
        .setting-control {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .setting-control input[type="text"],
        .setting-control input[type="number"],
        .setting-control input[type="email"],
        .setting-control input[type="url"],
        .setting-control input[type="password"],
        .setting-control textarea,
        .setting-control select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            width: 100%;
            max-width: 400px;
        }
        
        .setting-control textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .setting-control input:focus,
        .setting-control textarea:focus,
        .setting-control select:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        
        .file-upload {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .file-preview {
            width: 100px;
            height: 100px;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .file-preview img {
            max-width: 100%;
            max-height: 100%;
        }
        
        .file-input {
            flex: 1;
        }
        
        .file-input input[type="file"] {
            padding: 10px;
            border: 1px dashed #ddd;
            border-radius: 8px;
            width: 100%;
        }
        
        .settings-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .json-editor {
            font-family: monospace;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .code-block {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #ddd;
            font-family: monospace;
            font-size: 13px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="admin-main">
            <header class="admin-header">
                <div class="header-left">
                    <h1>System Settings</h1>
                    <p>Configure application settings and preferences</p>
                </div>
                <div class="header-right">
                    <button type="submit" form="settingsForm" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
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

                <div class="settings-container">
                    <div class="settings-sidebar">
                        <h3>Settings Categories</h3>
                        <nav class="settings-nav">
                            <?php while ($cat = mysqli_fetch_assoc($categories_result)): ?>
                                <a href="settings.php?category=<?php echo $cat['category']; ?>" 
                                   class="<?php echo $category === $cat['category'] ? 'active' : ''; ?>">
                                    <i class="fas fa-folder"></i>
                                    <?php echo ucfirst($cat['category']); ?>
                                </a>
                            <?php endwhile; ?>
                        </nav>
                    </div>

                    <div class="settings-content">
                        <form id="settingsForm" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="category" value="<?php echo $category; ?>">
                            
                            <?php if ($category === 'general'): ?>
                                <div class="settings-group">
                                    <h3>General Settings</h3>
                                    
                                    <div class="setting-row">
                                        <div class="setting-label">
                                            <label for="setting_site_name">Site Name</label>
                                            <small>The name displayed throughout the application</small>
                                        </div>
                                        <div class="setting-control">
                                            <input type="text" id="setting_site_name" name="setting_site_name" 
                                                   value="<?php echo htmlspecialchars($settings['site_name']['setting_value'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="setting-row">
                                        <div class="setting-label">
                                            <label for="setting_site_description">Site Description</label>
                                            <small>Brief description of your application</small>
                                        </div>
                                        <div class="setting-control">
                                            <textarea id="setting_site_description" name="setting_site_description"><?php echo htmlspecialchars($settings['site_description']['setting_value'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                    
                                    <div class="setting-row">
                                        <div class="setting-label">
                                            <label for="setting_site_logo">Site Logo</label>
                                            <small>Recommended size: 200x50 pixels</small>
                                        </div>
                                        <div class="setting-control">
                                            <div class="file-upload">
                                                <?php if (!empty($settings['site_logo']['setting_value'])): ?>
                                                    <div class="file-preview">
                                                        <img src="../../<?php echo htmlspecialchars($settings['site_logo']['setting_value']); ?>" 
                                                             alt="Current Logo">
                                                    </div>
                                                <?php else: ?>
                                                    <div class="file-preview">
                                                        <i class="fas fa-image fa-2x" style="color: #ddd;"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="file-input">
                                                    <input type="file" name="site_logo" accept="image/*">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="setting-row">
                                        <div class="setting-label">
                                            <label for="setting_items_per_page">Items Per Page</label>
                                            <small>Number of items to display per page in tables</small>
                                        </div>
                                        <div class="setting-control">
                                            <input type="number" id="setting_items_per_page" name="setting_items_per_page" 
                                                   value="<?php echo htmlspecialchars($settings['items_per_page']['setting_value'] ?? '20'); ?>"
                                                   min="5" max="100">
                                        </div>
                                    </div>
                                </div>
                                
                            <?php elseif ($category === 'security'): ?>
                                <div class="settings-group">
                                    <h3>Security Settings</h3>
                                    
                                    <div class="setting-row">
                                        <div class="setting-label">
                                            <label for="setting_allow_registration">Allow User Registration</label>
                                            <small>Allow new users to register accounts</small>
                                        </div>
                                        <div class="setting-control">
                                            <div class="checkbox-group">
                                                <input type="checkbox" id="setting_allow_registration" name="setting_allow_registration" 
                                                       value="true" <?php echo ($settings['allow_registration']['setting_value'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                                <label for="setting_allow_registration">Enable user registration</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="setting-row">
                                        <div class="setting-label">
                                            <label for="setting_password_min_length">Minimum Password Length</label>
                                            <small>Minimum characters required for passwords</small>
                                        </div>
                                        <div class="setting-control">
                                            <input type="number" id="setting_password_min_length" name="setting_password_min_length" 
                                                   value="<?php echo htmlspecialchars($settings['password_min_length']['setting_value'] ?? '8'); ?>"
                                                   min="6" max="32">
                                        </div>
                                    </div>
                                    
                                    <div class="setting-row">
                                        <div class="setting-label">
                                            <label for="setting_max_login_attempts">Max Login Attempts</label>
                                            <small>Maximum failed login attempts before lockout</small>
                                        </div>
                                        <div class="setting-control">
                                            <input type="number" id="setting_max_login_attempts" name="setting_max_login_attempts" 
                                                   value="<?php echo htmlspecialchars($settings['max_login_attempts']['setting_value'] ?? '5'); ?>"
                                                   min="1" max="10">
                                        </div>
                                    </div>
                                    
                                    <div class="setting-row">
                                        <div class="setting-label">
                                            <label for="setting_session_timeout">Session Timeout (minutes)</label>
                                            <small>Time before inactive sessions expire</small>
                                        </div>
                                        <div class="setting-control">
                                            <input type="number" id="setting_session_timeout" name="setting_session_timeout" 
                                                   value="<?php echo htmlspecialchars($settings['session_timeout']['setting_value'] ?? '30'); ?>"
                                                   min="5" max="1440">
                                        </div>
                                    </div>
                                </div>
                                
                            <?php elseif ($category === 'email'): ?>
                                <div class="settings-group">
                                    <h3>Email Settings</h3>
                                    
                                    <div class="setting-row">
                                        <div class="setting-label">
                                            <label for="setting_system_email">System Email</label>
                                            <small>Email address used for system notifications</small>
                                        </div>
                                        <div class="setting-control">
                                            <input type="email" id="setting_system_email" name="setting_system_email" 
                                                   value="<?php echo htmlspecialchars($settings['system_email']['setting_value'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="setting-row">
                                        <div class="setting-label">
                                            <label for="setting_contact_email">Contact Email</label>
                                            <small>Email address for user inquiries</small>
                                        </div>
                                        <div class="setting-control">
                                            <input type="email" id="setting_contact_email" name="setting_contact_email" 
                                                   value="<?php echo htmlspecialchars($settings['contact_email']['setting_value'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="setting-row">
                                        <div class="setting-label">
                                            <label for="setting_smtp_host">SMTP Host</label>
                                            <small>SMTP server address</small>
                                        </div>
                                        <div class="setting-control">
                                            <input type="text" id="setting_smtp_host" name="setting_smtp_host" 
                                                   value="<?php echo htmlspecialchars($settings['smtp_host']['setting_value'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="setting-row">
                                        <div class="setting-label">
                                            <label for="setting_smtp_port">SMTP Port</label>
                                            <small>SMTP server port (usually 465 for SSL, 587 for TLS)</small>
                                        </div>
                                        <div class="setting-control">
                                            <input type="number" id="setting_smtp_port" name="setting_smtp_port" 
                                                   value="<?php echo htmlspecialchars($settings['smtp_port']['setting_value'] ?? '587'); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="setting-row">
                                        <div class="setting-label">
                                            <label for="setting_smtp_username">SMTP Username</label>
                                            <small>SMTP authentication username</small>
                                        </div>
                                        <div class="setting-control">
                                            <input type="text" id="setting_smtp_username" name="setting_smtp_username" 
                                                   value="<?php echo htmlspecialchars($settings['smtp_username']['setting_value'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="setting-row">
                                        <div class="setting-label">
                                            <label for="setting_smtp_password">SMTP Password</label>
                                            <small>SMTP authentication password</small>
                                        </div>
                                        <div class="setting-control">
                                            <input type="password" id="setting_smtp_password" name="setting_smtp_password" 
                                                   value="<?php echo htmlspecialchars($settings['smtp_password']['setting_value'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                            <?php elseif ($category === 'system'): ?>
                                <div class="settings-group">
                                    <h3>System Settings</h3>
                                    
                                    <div class="setting-row">
                                        <div class="setting-label">
                                            <label for="setting_maintenance_mode">Maintenance Mode</label>
                                            <small>Enable maintenance mode to restrict access</small>
                                        </div>
                                        <div class="setting-control">
                                            <div class="checkbox-group">
                                                <input type="checkbox" id="setting_maintenance_mode" name="setting_maintenance_mode" 
                                                       value="true" <?php echo ($settings['maintenance_mode']['setting_value'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                                <label for="setting_maintenance_mode">Enable maintenance mode</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="setting-row">
                                        <div class="setting-label">
                                            <label for="setting_debug_mode">Debug Mode</label>
                                            <small>Enable debug mode for development (displays errors)</small>
                                        </div>
                                        <div class="setting-control">
                                            <div class="checkbox-group">
                                                <input type="checkbox" id="setting_debug_mode" name="setting_debug_mode" 
                                                       value="true" <?php echo ($settings['debug_mode']['setting_value'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                                <label for="setting_debug_mode">Enable debug mode</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="setting-row">
                                        <div class="setting-label">
                                            <label for="setting_timezone">Timezone</label>
                                            <small>System timezone for all date/time displays</small>
                                        </div>
                                        <div class="setting-control">
                                            <select id="setting_timezone" name="setting_timezone">
                                                <?php
                                                $timezones = DateTimeZone::listIdentifiers();
                                                foreach ($timezones as $tz) {
                                                    $selected = ($settings['timezone']['setting_value'] ?? 'Africa/Dar_es_Salaam') === $tz ? 'selected' : '';
                                                    echo "<option value=\"$tz\" $selected>$tz</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="setting-row">
                                        <div class="setting-label">
                                            <label for="setting_date_format">Date Format</label>
                                            <small>Format for displaying dates</small>
                                        </div>
                                        <div class="setting-control">
                                            <select id="setting_date_format" name="setting_date_format">
                                                <option value="Y-m-d" <?php echo ($settings['date_format']['setting_value'] ?? 'Y-m-d') === 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                                <option value="d/m/Y" <?php echo ($settings['date_format']['setting_value'] ?? 'Y-m-d') === 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                                <option value="m/d/Y" <?php echo ($settings['date_format']['setting_value'] ?? 'Y-m-d') === 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                                <option value="d M, Y" <?php echo ($settings['date_format']['setting_value'] ?? 'Y-m-d') === 'd M, Y' ? 'selected' : ''; ?>>DD Mon, YYYY</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                            <?php elseif ($category === 'backup'): ?>
                                <div class="settings-group">
                                    <h3>Backup Settings</h3>
                                    
                                    <div class="setting-row">
                                        <div class="setting-label">
                                            <label for="setting_backup_schedule">Backup Schedule</label>
                                            <small>How often to automatically backup the database</small>
                                        </div>
                                        <div class="setting-control">
                                            <select id="setting_backup_schedule" name="setting_backup_schedule">
                                                <option value="daily" <?php echo ($settings['backup_schedule']['setting_value'] ?? 'daily') === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                                <option value="weekly" <?php echo ($settings['backup_schedule']['setting_value'] ?? 'daily') === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                                <option value="monthly" <?php echo ($settings['backup_schedule']['setting_value'] ?? 'daily') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                                <option value="never" <?php echo ($settings['backup_schedule']['setting_value'] ?? 'daily') === 'never' ? 'selected' : ''; ?>>Never (Manual only)</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="setting-row">
                                        <div class="setting-label">
                                            <label for="setting_backup_retention">Backup Retention (days)</label>
                                            <small>How long to keep backup files before deleting</small>
                                        </div>
                                        <div class="setting-control">
                                            <input type="number" id="setting_backup_retention" name="setting_backup_retention" 
                                                   value="<?php echo htmlspecialchars($settings['backup_retention']['setting_value'] ?? '30'); ?>"
                                                   min="1" max="365">
                                        </div>
                                    </div>
                                    
                                    <div class="setting-row">
                                        <div class="setting-label">
                                            <label>Last Backup</label>
                                            <small>When the last backup was created</small>
                                        </div>
                                        <div class="setting-control">
                                            <div class="code-block">
                                                <?php
                                                $last_backup = $settings['last_backup']['setting_value'] ?? 'Never';
                                                if ($last_backup === 'Never') {
                                                    echo 'No backups have been created yet.';
                                                } else {
                                                    echo "Last backup: $last_backup";
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="settings-group">
                                    <h3>Manual Backup</h3>
                                    <p>Create a manual backup of the database immediately.</p>
                                    <a href="backup.php?action=create" class="btn btn-primary">
                                        <i class="fas fa-database"></i> Create Backup Now
                                    </a>
                                </div>
                                
                            <?php else: ?>
                                <!-- Dynamic settings for other categories -->
                                <div class="settings-group">
                                    <h3><?php echo ucfirst($category); ?> Settings</h3>
                                    
                                    <?php foreach ($settings as $key => $setting): ?>
                                        <?php if ($setting['category'] === $category): ?>
                                            <div class="setting-row">
                                                <div class="setting-label">
                                                    <label for="setting_<?php echo $key; ?>">
                                                        <?php echo ucwords(str_replace('_', ' ', $key)); ?>
                                                    </label>
                                                </div>
                                                <div class="setting-control">
                                                    <?php if ($setting['setting_type'] === 'boolean'): ?>
                                                        <div class="checkbox-group">
                                                            <input type="checkbox" id="setting_<?php echo $key; ?>" 
                                                                   name="setting_<?php echo $key; ?>" 
                                                                   value="true" <?php echo $setting['setting_value'] === 'true' ? 'checked' : ''; ?>>
                                                            <label for="setting_<?php echo $key; ?>">Enabled</label>
                                                        </div>
                                                    <?php elseif ($setting['setting_type'] === 'number'): ?>
                                                        <input type="number" id="setting_<?php echo $key; ?>" 
                                                               name="setting_<?php echo $key; ?>" 
                                                               value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                                    <?php elseif ($setting['setting_type'] === 'json'): ?>
                                                        <textarea id="setting_<?php echo $key; ?>" 
                                                                  name="setting_<?php echo $key; ?>" 
                                                                  class="json-editor"><?php echo htmlspecialchars($setting['setting_value']); ?></textarea>
                                                    <?php else: ?>
                                                        <input type="text" id="setting_<?php echo $key; ?>" 
                                                               name="setting_<?php echo $key; ?>" 
                                                               value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="settings-actions">
                                <button type="reset" class="btn-secondary">
                                    <i class="fas fa-undo"></i> Reset Changes
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>