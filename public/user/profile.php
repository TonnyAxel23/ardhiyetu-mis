<?php
require_once __DIR__ . '/../../includes/init.php';

// Require login
require_login();

$user_id = $_SESSION['user_id'];

// Get user data
$sql = "SELECT * FROM users WHERE user_id = '$user_id'";
$result = mysqli_query($conn, $sql);
$user = mysqli_fetch_assoc($result);

$error = '';
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    
    if ($action == 'update_profile') {
        $name = sanitize_input($_POST['name']);
        $phone = sanitize_input($_POST['phone']);
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate inputs
        if (empty($name) || empty($phone)) {
            $error = 'Name and phone are required';
        } elseif (!validate_phone($phone)) {
            $error = 'Please enter a valid phone number';
        } else {
            // Check if password change is requested
            if (!empty($current_password)) {
                if (!password_verify($current_password, $user['password'])) {
                    $error = 'Current password is incorrect';
                } elseif (empty($new_password)) {
                    $error = 'New password is required';
                } elseif (strlen($new_password) < 8) {
                    $error = 'New password must be at least 8 characters';
                } elseif ($new_password !== $confirm_password) {
                    $error = 'New passwords do not match';
                } else {
                    // Update with new password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_sql = "UPDATE users 
                                  SET name = '$name', 
                                      phone = '$phone', 
                                      password = '$hashed_password',
                                      updated_at = NOW()
                                  WHERE user_id = '$user_id'";
                }
            } else {
                // Update without password
                $update_sql = "UPDATE users 
                              SET name = '$name', 
                                  phone = '$phone',
                                  updated_at = NOW()
                              WHERE user_id = '$user_id'";
            }
            
            if (!$error && mysqli_query($conn, $update_sql)) {
                // Update session
                $_SESSION['name'] = $name;
                
                // Log activity
                log_activity($user_id, 'profile_update', 'Updated profile information');
                
                $success = 'Profile updated successfully';
                
                // Refresh user data
                $result = mysqli_query($conn, $sql);
                $user = mysqli_fetch_assoc($result);
            } elseif (!$error) {
                $error = 'Failed to update profile: ' . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - ArdhiYetu</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
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
                <a href="land-map.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'land-map.php' ? 'active' : ''; ?>"><i class="fas fa-map"></i> Land Map</a>
                <a href="transfer-land.php"><i class="fas fa-exchange-alt"></i> Transfer</a>
                <a href="documents.php"><i class="fas fa-file-alt"></i> Documents</a>
                <a href="profile.php" class="active"><i class="fas fa-user"></i> Profile</a>
                <?php if (is_admin()): ?>
                    <a href="../admin/index.php" class="btn"><i class="fas fa-user-shield"></i> Admin</a>
                <?php endif; ?>
                <a href="../logout.php" class="btn logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
            <button class="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <main class="profile-container">
        <div class="container">
            <div class="profile-header">
                <h1>My Profile</h1>
                <p>Manage your account information and settings</p>
            </div>

            <?php if ($error): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <div class="profile-content">
                <div class="profile-sidebar">
                    <div class="profile-card">
                        <div class="profile-avatar">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="profile-info">
                            <h2><?php echo htmlspecialchars($user['name']); ?></h2>
                            <p class="role-badge role-<?php echo $user['role']; ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </p>
                            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                            <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone']); ?></p>
                            <p><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($user['id_number']); ?></p>
                        </div>
                        <div class="profile-stats">
                            <?php
                            $lands_count = mysqli_query($conn, "SELECT COUNT(*) as count FROM land_records WHERE owner_id = '$user_id'");
                            $active_lands = mysqli_query($conn, "SELECT COUNT(*) as count FROM land_records WHERE owner_id = '$user_id' AND status = 'active'");
                            $transfers_count = mysqli_query($conn, "SELECT COUNT(*) as count FROM ownership_transfers WHERE from_user_id = '$user_id' OR to_user_id = '$user_id'");
                            
                            $lands_count = mysqli_fetch_assoc($lands_count)['count'];
                            $active_lands = mysqli_fetch_assoc($active_lands)['count'];
                            $transfers_count = mysqli_fetch_assoc($transfers_count)['count'];
                            ?>
                            <div class="stat-item">
                                <h3><?php echo $lands_count; ?></h3>
                                <p>Land Records</p>
                            </div>
                            <div class="stat-item">
                                <h3><?php echo $active_lands; ?></h3>
                                <p>Active Lands</p>
                            </div>
                            <div class="stat-item">
                                <h3><?php echo $transfers_count; ?></h3>
                                <p>Transfers</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="profile-links">
                        <a href="profile.php" class="active">
                            <i class="fas fa-user-edit"></i> Edit Profile
                        </a>
                        <a href="security.php">
                            <i class="fas fa-shield-alt"></i> Security
                        </a>
                        <a href="notifications.php">
                            <i class="fas fa-bell"></i> Notifications
                        </a>
                        <a href="activity-log.php">
                            <i class="fas fa-history"></i> Activity Log
                        </a>
                        <a href="documents.php">
                            <i class="fas fa-file-alt"></i> My Documents
                        </a>
                    </div>
                </div>

                <div class="profile-main">
                    <div class="tab-content">
                        <div class="tab-pane active">
                            <h2>Edit Profile</h2>
                            
                            <form method="POST" action="" class="profile-form">
                                <input type="hidden" name="action" value="update_profile">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                
                                <div class="form-section">
                                    <h3>Personal Information</h3>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label for="name">Full Name *</label>
                                            <input type="text" 
                                                   id="name" 
                                                   name="name" 
                                                   value="<?php echo htmlspecialchars($user['name']); ?>" 
                                                   required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="email">Email Address</label>
                                            <input type="email" 
                                                   id="email" 
                                                   value="<?php echo htmlspecialchars($user['email']); ?>" 
                                                   disabled>
                                            <small>Email cannot be changed</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="phone">Phone Number *</label>
                                            <input type="tel" 
                                                   id="phone" 
                                                   name="phone" 
                                                   value="<?php echo htmlspecialchars($user['phone']); ?>" 
                                                   required
                                                   pattern="[0-9]{10,15}">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="id_number">National ID</label>
                                            <input type="text" 
                                                   id="id_number" 
                                                   value="<?php echo htmlspecialchars($user['id_number']); ?>" 
                                                   disabled>
                                            <small>ID number cannot be changed</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-section">
                                    <h3>Change Password</h3>
                                    <p class="form-description">Leave blank if you don't want to change password</p>
                                    
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label for="current_password">Current Password</label>
                                            <div class="password-wrapper">
                                                <input type="password" 
                                                       id="current_password" 
                                                       name="current_password">
                                                <button type="button" class="password-toggle">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="new_password">New Password</label>
                                            <div class="password-wrapper">
                                                <input type="password" 
                                                       id="new_password" 
                                                       name="new_password"
                                                       minlength="8">
                                                <button type="button" class="password-toggle">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                            <small>Minimum 8 characters</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="confirm_password">Confirm New Password</label>
                                            <div class="password-wrapper">
                                                <input type="password" 
                                                       id="confirm_password" 
                                                       name="confirm_password">
                                                <button type="button" class="password-toggle">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-section">
                                    <h3>Account Information</h3>
                                    <div class="info-grid">
                                        <div class="info-item">
                                            <label>Account Created:</label>
                                            <span><?php echo format_date($user['created_at']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <label>Last Updated:</label>
                                            <span><?php echo format_date($user['updated_at']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <label>Last Login:</label>
                                            <span><?php echo $user['last_login'] ? format_date($user['last_login']) : 'Never'; ?></span>
                                        </div>
                                        <div class="info-item">
                                            <label>Account Status:</label>
                                            <span class="status-badge <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                    <a href="../dashboard.php" class="btn secondary">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
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
                    <h3>Need Help?</h3>
                    <p><i class="fas fa-envelope"></i> support@ardhiyetu.go.ke</p>
                    <p><i class="fas fa-phone"></i> 0700 000 000</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> ArdhiYetu Land Management System</p>
            </div>
        </div>
    </footer>

    <script src="../../assets/js/script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password toggle
            const passwordToggles = document.querySelectorAll('.password-toggle');
            passwordToggles.forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const input = this.previousElementSibling;
                    const icon = this.querySelector('i');
                    
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        input.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            });
            
            // Password strength indicator
            const newPassword = document.getElementById('new_password');
            if (newPassword) {
                newPassword.addEventListener('input', function() {
                    const strengthBar = document.querySelector('.strength-bar');
                    const strengthText = document.querySelector('.strength-text');
                    
                    if (strengthBar && strengthText) {
                        const password = this.value;
                        let strength = 0;
                        
                        // Check length
                        if (password.length >= 8) strength++;
                        if (password.length >= 12) strength++;
                        
                        // Check complexity
                        if (/[a-z]/.test(password)) strength++;
                        if (/[A-Z]/.test(password)) strength++;
                        if (/[0-9]/.test(password)) strength++;
                        if (/[^A-Za-z0-9]/.test(password)) strength++;
                        
                        // Update UI
                        const percent = (strength / 6) * 100;
                        strengthBar.style.width = percent + '%';
                        
                        if (percent < 40) {
                            strengthBar.className = 'strength-bar weak';
                            strengthText.textContent = 'Password strength: Weak';
                        } else if (percent < 70) {
                            strengthBar.className = 'strength-bar medium';
                            strengthText.textContent = 'Password strength: Medium';
                        } else {
                            strengthBar.className = 'strength-bar strong';
                            strengthText.textContent = 'Password strength: Strong';
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>