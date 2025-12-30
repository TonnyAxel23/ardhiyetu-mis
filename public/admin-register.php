<?php
session_start();
require_once '../includes/config.php';

// Simple admin check function
function admin_exists() {
    global $conn;
    $sql = "SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND is_active = TRUE";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['count'] > 0;
}

// Redirect if admin exists
if (admin_exists()) {
    header('Location: index.php');
    exit();
}

// Initialize variables
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $name = mysqli_real_escape_string($conn, $_POST['name'] ?? '');
    $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'All fields are required';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } else {
        // Check if email exists
        $check_sql = "SELECT user_id FROM users WHERE email = '$email'";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            $error = 'Email already exists';
        } else {
            // Hash password and insert
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (name, email, password, role, is_active, created_at) 
                    VALUES ('$name', '$email', '$hashed_password', 'admin', TRUE, NOW())";
            
            if (mysqli_query($conn, $sql)) {
                $success = 'Admin account created successfully! Redirecting to login...';
                echo '<meta http-equiv="refresh" content="3;url=login.php">';
            } else {
                $error = 'Error: ' . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Admin - <?php echo SITE_NAME; ?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
            text-align: center;
        }
        .alert {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }
        .success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
        }
        input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        input:focus {
            outline: none;
            border-color: #4a90e2;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #4a90e2;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }
        button:hover {
            background: #3a80d2;
        }
        .note {
            font-size: 12px;
            color: #777;
            margin-top: 5px;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Create Admin Account</h1>
        <p style="text-align: center; color: #666; margin-bottom: 30px;">
            This page is only accessible when no admin exists.
        </p>
        
        <?php if ($error): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="name" required placeholder="Enter your full name">
            </div>
            
            <div class="form-group">
                <label>Email Address *</label>
                <input type="email" name="email" required placeholder="admin@example.com">
            </div>
            
            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="password" required placeholder="Minimum 8 characters">
                <div class="note">Must be at least 8 characters long</div>
            </div>
            
            <div class="form-group">
                <label>Confirm Password *</label>
                <input type="password" name="confirm_password" required placeholder="Confirm your password">
            </div>
            
            <button type="submit">Create Admin Account</button>
            
            <div class="login-link">
                <a href="login.php">Go to Login</a>
            </div>
        </form>
    </div>
</body>
</html>