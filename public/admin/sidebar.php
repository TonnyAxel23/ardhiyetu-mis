<aside class="sidebar">
    <div class="sidebar-header">
        <a href="<?php echo BASE_URL; ?>/index.php" class="logo">
            <i class="fas fa-landmark"></i> ArdhiYetu
        </a>
        <p class="user-info">Admin: <?php echo htmlspecialchars($_SESSION['name']); ?></p>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-section">
            <h3>Main</h3>
            <a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="users.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Users
            </a>
            <a href="lands.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'lands.php' ? 'active' : ''; ?>">
                <i class="fas fa-landmark"></i> Land Records
            </a>
            <a href="transfers.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'transfers.php' ? 'active' : ''; ?>">
                <i class="fas fa-exchange-alt"></i> Transfers
            </a>
        </div>
        
        <div class="nav-section">
            <h3>Reports</h3>
            <a href="reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Analytics
            </a>
            <a href="audit-logs.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'audit-logs.php' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i> Audit Logs
            </a>
            <a href="system-logs.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'system-logs.php' ? 'active' : ''; ?>">
                <i class="fas fa-server"></i> System Logs
            </a>
        </div>
        
        <div class="nav-section">
            <h3>System</h3>
            <a href="settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i> Settings
            </a>
            <a href="backup.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'backup.php' ? 'active' : ''; ?>">
                <i class="fas fa-database"></i> Backup
            </a>
        </div>
    </nav>
    
    <div class="sidebar-footer">
        <a href="<?php echo BASE_URL; ?>/dashboard.php" class="btn">
            <i class="fas fa-user"></i> User View
        </a>
        <a href="../logout.php" class="btn logout">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</aside>