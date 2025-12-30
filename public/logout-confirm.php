<?php
// public/logout-confirm.php
require_once '../includes/init.php';

if (!is_logged_in()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Logout - ArdhiYetu</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .logout-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        .logout-icon {
            font-size: 48px;
            color: #e74c3c;
            margin-bottom: 20px;
        }
        h2 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        p {
            color: #7f8c8d;
            margin-bottom: 30px;
        }
        .button-group {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-cancel {
            background: #95a5a6;
            color: white;
        }
        .btn-logout {
            background: #e74c3c;
            color: white;
        }
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        <h2>Confirm Logout</h2>
        <p>Are you sure you want to logout from your account?</p>
        <div class="button-group">
            <a href="<?php echo BASE_URL . '/dashboard.php'; ?>" class="btn btn-cancel">
                <i class="fas fa-times"></i> Cancel
            </a>
            <a href="<?php echo BASE_URL . '/logout.php'; ?>" class="btn btn-logout">
                <i class="fas fa-sign-out-alt"></i> Yes, Logout
            </a>
        </div>
    </div>
</body>
</html>