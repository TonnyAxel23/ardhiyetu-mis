<?php
require_once '../includes/init.php';

// Check if user is already logged in
if (is_logged_in()) {
    // Redirect based on role
    if ($_SESSION['role'] === 'admin') {
        header('Location: ' . BASE_URL . '/admin/index.php');
        exit();
    } else {
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = sanitize_input($_POST['email']);
        $password = $_POST['password'];
        $remember_me = isset($_POST['remember']) ? true : false;
        
        // Validate inputs
        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields';
        } elseif (!validate_email($email)) {
            $error = 'Please enter a valid email address';
        } else {
            // Check user exists using prepared statement
            $sql = "SELECT * FROM users WHERE email = ? AND is_active = TRUE";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) == 1) {
                $user = mysqli_fetch_assoc($result);
                
                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Check for account lockout
                    if (isset($user['failed_attempts']) && $user['failed_attempts'] >= 5) {
                        $error = 'Account locked. Please reset your password.';
                    } else {
                        // Login successful
                        login_user($user['user_id'], $user['email'], $user['role'], $user['name']);
                        
                        // Set remember me cookie if checked
                        if ($remember_me) {
                            $token = bin2hex(random_bytes(32));
                            setcookie('ardhiyetu_remember', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
                            
                            // Store token in database
                            $sql = "UPDATE users SET remember_token = ? WHERE user_id = ?";
                            $stmt = mysqli_prepare($conn, $sql);
                            mysqli_stmt_bind_param($stmt, "ss", $token, $user['user_id']);
                            mysqli_stmt_execute($stmt);
                        }
                        
                        // Reset failed attempts and update last login
                        $update_sql = "UPDATE users SET last_login = NOW(), failed_attempts = 0 WHERE user_id = ?";
                        $stmt = mysqli_prepare($conn, $update_sql);
                        mysqli_stmt_bind_param($stmt, "s", $user['user_id']);
                        mysqli_stmt_execute($stmt);
                        
                        // Log login activity
                        log_activity($user['user_id'], 'login', 'User logged in');
                        
                        // Redirect to intended page or based on role
                        if (isset($_SESSION['redirect_url'])) {
                            $redirect_url = $_SESSION['redirect_url'];
                            unset($_SESSION['redirect_url']);
                            header('Location: ' . $redirect_url);
                            exit();
                        } else {
                            // Role-based redirection
                            if ($user['role'] === 'admin') {
                                header('Location: ' . BASE_URL . '/admin/index.php');
                            } else {
                                header('Location: ' . BASE_URL . '/dashboard.php');
                            }
                            exit();
                        }
                    }
                } else {
                    // Increment failed attempts
                    $sql = "UPDATE users SET failed_attempts = COALESCE(failed_attempts, 0) + 1 WHERE user_id = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "s", $user['user_id']);
                    mysqli_stmt_execute($stmt);
                    
                    $error = 'Invalid email or password';
                    log_activity($user['user_id'] ?? 'unknown', 'failed_login', 'Failed login attempt');
                }
            } else {
                $error = 'No active account found with this email';
            }
        }
    }
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ArdhiYetu</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2E86AB;
            --primary-dark: #1C5D7F;
            --secondary: #F4A261;
            --accent: #E76F51;
            --light: #F8F9FA;
            --dark: #2C3E50;
            --success: #27AE60;
            --warning: #F39C12;
            --danger: #E74C3C;
            --gray: #95A5A6;
            --shadow: 0 10px 30px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }
        
        /* Back Home Button */
        .back-home {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 1000;
        }
        
        .back-home-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            background: white;
            color: var(--dark);
            text-decoration: none;
            border-radius: 50px;
            font-weight: 500;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 2px solid transparent;
        }
        
        .back-home-btn:hover {
            transform: translateX(-5px);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .login-container {
            display: flex;
            max-width: 1200px;
            width: 100%;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
            animation: fadeIn 0.8s ease;
            position: relative;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .login-left {
            flex: 1;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-left::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path d="M0,0 L100,0 L100,100 Z" fill="rgba(255,255,255,0.05)"/></svg>');
            background-size: cover;
            transform: rotate(30deg);
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 24px;
            font-weight: 700;
            color: white;
            text-decoration: none;
            margin-bottom: 40px;
            transition: var(--transition);
        }
        
        .logo:hover {
            transform: scale(1.05);
        }
        
        .logo i {
            font-size: 28px;
        }
        
        .welcome-text h1 {
            font-size: 36px;
            margin-bottom: 15px;
            line-height: 1.2;
        }
        
        .welcome-text p {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 30px;
        }
        
        .features-list {
            list-style: none;
            margin: 30px 0;
        }
        
        .features-list li {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            font-size: 15px;
            transition: var(--transition);
        }
        
        .features-list li:hover {
            transform: translateX(10px);
        }
        
        .features-list i {
            background: rgba(255,255,255,0.2);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .testimonial {
            background: rgba(255,255,255,0.1);
            border-radius: 15px;
            padding: 25px;
            margin-top: 40px;
            position: relative;
            transition: var(--transition);
        }
        
        .testimonial:hover {
            transform: translateY(-5px);
            background: rgba(255,255,255,0.15);
        }
        
        .testimonial::before {
            content: '"';
            position: absolute;
            top: -20px;
            left: 25px;
            font-size: 60px;
            color: rgba(255,255,255,0.3);
            font-family: serif;
        }
        
        .testimonial p {
            font-style: italic;
            margin-bottom: 15px;
        }
        
        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .testimonial-author img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .login-right {
            flex: 1;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .form-header h2 {
            color: var(--dark);
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .form-header p {
            color: var(--gray);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.5s ease;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .alert.error {
            background: #FFEBEE;
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }
        
        .alert.success {
            background: #E8F5E9;
            color: var(--success);
            border-left: 4px solid var(--success);
        }
        
        .alert.warning {
            background: #FFF8E1;
            color: var(--warning);
            border-left: 4px solid var(--warning);
        }
        
        .login-form {
            width: 100%;
        }
        
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark);
            font-weight: 500;
        }
        
        .input-group {
            position: relative;
        }
        
        .input-group input {
            width: 100%;
            padding: 15px 20px 15px 50px;
            border: 2px solid #E8ECEF;
            border-radius: 10px;
            font-size: 16px;
            transition: var(--transition);
            outline: none;
        }
        
        .input-group input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(46, 134, 171, 0.1);
        }
        
        .input-group input.error {
            border-color: var(--danger);
        }
        
        .input-group input.success {
            border-color: var(--success);
        }
        
        .input-icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            font-size: 18px;
        }
        
        .toggle-password {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray);
            cursor: pointer;
            font-size: 18px;
            transition: var(--transition);
        }
        
        .toggle-password:hover {
            color: var(--primary);
        }
        
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary);
        }
        
        .forgot-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .forgot-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        .login-button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }
        
        .login-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: 0.5s;
        }
        
        .login-button:hover::before {
            left: 100%;
        }
        
        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 134, 171, 0.4);
        }
        
        .login-button:active {
            transform: translateY(0);
        }
        
        .login-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 30px 0;
            color: var(--gray);
        }
        
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #E8ECEF;
        }
        
        .divider span {
            padding: 0 15px;
            font-size: 14px;
        }
        
        .social-login {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .social-button {
            padding: 14px;
            border: 2px solid #E8ECEF;
            border-radius: 10px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .social-button:hover {
            transform: translateY(-2px);
            border-color: var(--primary);
            background: #F8F9FA;
        }
        
        .social-button.google {
            color: #DB4437;
        }
        
        .social-button.google:hover {
            background: #FFEBEE;
            border-color: #DB4437;
        }
        
        .social-button.facebook {
            color: #4267B2;
        }
        
        .social-button.facebook:hover {
            background: #E8F0FE;
            border-color: #4267B2;
        }
        
        .register-link {
            text-align: center;
            color: var(--gray);
            margin-top: 20px;
        }
        
        .register-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            margin-left: 5px;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        .footer-links {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #E8ECEF;
        }
        
        .footer-links a {
            color: var(--gray);
            text-decoration: none;
            font-size: 14px;
            transition: var(--transition);
        }
        
        .footer-links a:hover {
            color: var(--primary);
            transform: translateY(-2px);
        }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            .login-container {
                flex-direction: column;
                max-width: 500px;
            }
            
            .login-left {
                padding: 40px 30px;
            }
            
            .login-right {
                padding: 40px 30px;
            }
            
            .back-home {
                position: relative;
                top: 0;
                left: 0;
                margin-bottom: 20px;
                display: flex;
                justify-content: center;
            }
            
            .back-home-btn {
                display: inline-flex;
            }
        }
        
        @media (max-width: 576px) {
            .social-login {
                grid-template-columns: 1fr;
            }
            
            .form-options {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .welcome-text h1 {
                font-size: 28px;
            }
            
            .form-header h2 {
                font-size: 26px;
            }
            
            .footer-links {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
        
        /* Animation for form elements */
        .form-group {
            animation: slideUp 0.5s ease forwards;
            opacity: 0;
            transform: translateY(20px);
        }
        
        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-options { animation-delay: 0.3s; }
        .login-button { animation-delay: 0.4s; }
        .divider { animation-delay: 0.5s; }
        .social-login { animation-delay: 0.6s; }
        .register-link { animation-delay: 0.7s; }
        
        @keyframes slideUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Loading animation */
        .loading {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid #fff;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .login-button.loading .button-text {
            display: none;
        }
        
        .login-button.loading .loading {
            display: block;
        }
        
        /* Password strength indicator */
        .password-strength {
            height: 4px;
            background: #E8ECEF;
            border-radius: 2px;
            margin-top: 5px;
            overflow: hidden;
            display: none;
        }
        
        .strength-bar {
            height: 100%;
            width: 0;
            transition: width 0.3s ease;
        }
        
        .strength-bar.weak { background: var(--danger); }
        .strength-bar.medium { background: var(--warning); }
        .strength-bar.strong { background: var(--success); }
        
        /* Language selector */
        .language-selector {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 1001;
        }
        
        .language-selector select {
            padding: 8px 15px;
            border: 1px solid #E8ECEF;
            border-radius: 8px;
            background: white;
            color: var(--dark);
            font-size: 14px;
            outline: none;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .language-selector select:hover {
            border-color: var(--primary);
        }
        
        /* Input validation icons */
        .validation-icon {
            position: absolute;
            right: 50px;
            top: 50%;
            transform: translateY(-50%);
            display: none;
        }
        
        .input-group.valid .validation-icon.valid {
            display: block;
            color: var(--success);
        }
        
        .input-group.invalid .validation-icon.invalid {
            display: block;
            color: var(--danger);
        }
        
        /* Toast notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        
        /* Demo credentials banner */
        .demo-banner {
            background: linear-gradient(135deg, var(--secondary) 0%, var(--accent) 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
            animation: slideIn 0.5s ease;
        }
        
        .demo-banner h4 {
            font-size: 14px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .demo-banner p {
            font-size: 13px;
            opacity: 0.9;
        }
        
        .demo-credentials {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 8px;
            flex-wrap: wrap;
        }
        
        .demo-item {
            font-size: 12px;
        }
        
        .demo-item code {
            background: rgba(255,255,255,0.2);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .demo-item code:hover {
            background: rgba(255,255,255,0.3);
        }
    </style>
</head>
<body>
    <!-- Back Home Button - UPDATED TO POINT TO index.php in public folder -->
    <div class="back-home">
        <a href="index.php" class="back-home-btn">
            <i class="fas fa-arrow-left"></i>
            Back to Home
        </a>
    </div>
    
    <!-- Language Selector -->
    <div class="language-selector">
        <select id="language">
            <option value="en">English</option>
            <option value="sw">Swahili</option>
        </select>
    </div>
    
    <div class="login-container">
        <!-- Left Panel -->
        <div class="login-left">
            <a href="index.php" class="logo">
                <i class="fas fa-landmark"></i>
                <span>ArdhiYetu</span>
            </a>
            
            <div class="welcome-text">
                <h1>Karibu Tena</h1>
                <p>Login to access your land records and manage your properties digitally.</p>
            </div>
            
            <ul class="features-list">
                <li>
                    <i class="fas fa-shield-alt"></i>
                    <div>
                        <h4>Secure & Encrypted</h4>
                        <p>Bank-level security for your data</p>
                    </div>
                </li>
                <li>
                    <i class="fas fa-bolt"></i>
                    <div>
                        <h4>Fast Processing</h4>
                        <p>Instant access to all services</p>
                    </div>
                </li>
                <li>
                    <i class="fas fa-mobile-alt"></i>
                    <div>
                        <h4>Mobile Friendly</h4>
                        <p>Access from any device</p>
                    </div>
                </li>
                <li>
                    <i class="fas fa-headset"></i>
                    <div>
                        <h4>24/7 Support</h4>
                        <p>Always here to help you</p>
                    </div>
                </li>
            </ul>
            
            <div class="testimonial">
                <p>"ArdhiYetu has revolutionized how we manage land records. Everything is now at our fingertips!"</p>
                <div class="testimonial-author">
                    <img src="https://ui-avatars.com/api/?name=TonnyAxels&background=2E86AB&color=fff" alt="User">
                    <div>
                        <h5>TonnyAxels</h5>
                        <p>Landowner, Nairobi</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Panel -->
        <div class="login-right">
            <div class="form-header">
                <h2>Login to Your Account</h2>
                <p>Enter your credentials to continue</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <!-- Demo Credentials Banner (Optional - Remove in production) -->
            <div class="demo-banner">
                <h4><i class="fas fa-vial"></i> Demo Credentials</h4>
                <div class="demo-credentials">
                    <div class="demo-item">Email: <code onclick="fillDemo('demo@ardhiyetu.com')">demo@ardhiyetu.com</code></div>
                    <div class="demo-item">Password: <code onclick="fillDemo('demopass123')">demopass123</code></div>
                </div>
            </div>
            
            <form method="POST" action="" class="login-form" id="loginForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <div class="input-group">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               required 
                               placeholder="Enter your email address"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                               autocomplete="email"
                               data-validate="email">
                        <i class="fas fa-check-circle validation-icon valid"></i>
                        <i class="fas fa-exclamation-circle validation-icon invalid"></i>
                    </div>
                    <small class="error-message" style="color: var(--danger); display: none;"></small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               required 
                               placeholder="Enter your password"
                               autocomplete="current-password"
                               minlength="8"
                               data-validate="password">
                        <button type="button" class="toggle-password" id="togglePassword" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                        <i class="fas fa-check-circle validation-icon valid"></i>
                        <i class="fas fa-exclamation-circle validation-icon invalid"></i>
                    </div>
                    <div class="password-strength" id="passwordStrength">
                        <div class="strength-bar"></div>
                    </div>
                    <small class="error-message" style="color: var(--danger); display: none;"></small>
                </div>
                
                <div class="form-options">
                    <label class="checkbox-group">
                        <input type="checkbox" name="remember" id="remember">
                        <span>Remember me for 30 days</span>
                    </label>
                    <a href="forgot-password.php" class="forgot-link">Forgot Password?</a>
                </div>
                
                <button type="submit" class="login-button" id="loginButton">
                    <span class="button-text">Login to Account</span>
                    <i class="fas fa-arrow-right"></i>
                    <div class="loading"></div>
                </button>
                
                <div class="divider">
                    <span>Or continue with</span>
                </div>
                
                <div class="social-login">
                    <button type="button" class="social-button google" onclick="loginWithGoogle()" aria-label="Login with Google">
                        <i class="fab fa-google"></i>
                        <span>Google</span>
                    </button>
                    <button type="button" class="social-button facebook" onclick="loginWithFacebook()" aria-label="Login with Facebook">
                        <i class="fab fa-facebook-f"></i>
                        <span>Facebook</span>
                    </button>
                </div>
                
                <p class="register-link">
                    Don't have an account?
                    <a href="register.php">Create Account</a>
                </p>
            </form>
            
            <div class="footer-links">
                <a href="../contact.php"><i class="fas fa-question-circle"></i> Need Help?</a>
                <a href="../privacy.php"><i class="fas fa-shield-alt"></i> Privacy Policy</a>
                <a href="../terms.php"><i class="fas fa-file-contract"></i> Terms of Service</a>
                <a href="../about.php"><i class="fas fa-info-circle"></i> About Us</a>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle password visibility
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const eyeIcon = togglePassword.querySelector('i');
            
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                eyeIcon.classList.toggle('fa-eye');
                eyeIcon.classList.toggle('fa-eye-slash');
                this.setAttribute('aria-label', type === 'password' ? 'Show password' : 'Hide password');
            });
            
            // Form validation and submission
            const loginForm = document.getElementById('loginForm');
            const loginButton = document.getElementById('loginButton');
            
            // Real-time validation
            const emailInput = document.getElementById('email');
            const passwordInputField = document.getElementById('password');
            
            emailInput.addEventListener('blur', validateEmail);
            passwordInputField.addEventListener('input', validatePassword);
            
            function validateEmail() {
                const email = emailInput.value.trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                const inputGroup = emailInput.parentElement;
                const errorMessage = inputGroup.parentElement.querySelector('.error-message');
                
                if (!email) {
                    setInvalid(inputGroup, errorMessage, 'Email is required');
                    return false;
                }
                
                if (!emailRegex.test(email)) {
                    setInvalid(inputGroup, errorMessage, 'Please enter a valid email address');
                    return false;
                }
                
                setValid(inputGroup, errorMessage);
                return true;
            }
            
            function validatePassword() {
                const password = passwordInputField.value;
                const inputGroup = passwordInputField.parentElement;
                const errorMessage = inputGroup.parentElement.querySelector('.error-message');
                
                if (!password) {
                    setInvalid(inputGroup, errorMessage, 'Password is required');
                    return false;
                }
                
                if (password.length < 8) {
                    setInvalid(inputGroup, errorMessage, 'Password must be at least 8 characters');
                    return false;
                }
                
                setValid(inputGroup, errorMessage);
                return true;
            }
            
            function setValid(inputGroup, errorMessage) {
                inputGroup.classList.remove('invalid');
                inputGroup.classList.add('valid');
                if (errorMessage) errorMessage.style.display = 'none';
            }
            
            function setInvalid(inputGroup, errorMessage, message) {
                inputGroup.classList.remove('valid');
                inputGroup.classList.add('invalid');
                if (errorMessage) {
                    errorMessage.textContent = message;
                    errorMessage.style.display = 'block';
                }
            }
            
            loginForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const isEmailValid = validateEmail();
                const isPasswordValid = validatePassword();
                
                if (!isEmailValid || !isPasswordValid) {
                    showToast('Please fix the errors before submitting', 'error');
                    return false;
                }
                
                // Show loading state
                loginButton.classList.add('loading');
                loginButton.disabled = true;
                loginButton.innerHTML = '<div class="loading"></div>';
                
                // Add slight delay to show loading state
                setTimeout(() => {
                    loginForm.submit();
                }, 1000);
            });
            
            // Password strength indicator
            const passwordStrength = document.getElementById('passwordStrength');
            
            passwordInputField.addEventListener('input', function() {
                const password = this.value;
                if (password.length > 0) {
                    passwordStrength.style.display = 'block';
                    const strength = calculatePasswordStrength(password);
                    const strengthBar = passwordStrength.querySelector('.strength-bar');
                    
                    strengthBar.className = 'strength-bar';
                    strengthBar.style.width = (strength * 25) + '%';
                    
                    if (strength < 2) {
                        strengthBar.classList.add('weak');
                    } else if (strength < 4) {
                        strengthBar.classList.add('medium');
                    } else {
                        strengthBar.classList.add('strong');
                    }
                } else {
                    passwordStrength.style.display = 'none';
                }
            });
            
            // Auto focus email field
            emailInput.focus();
            
            // Check for saved email from remember me
            const savedEmail = localStorage.getItem('ardhiyetu_email');
            if (savedEmail) {
                emailInput.value = savedEmail;
                document.getElementById('remember').checked = true;
            }
            
            // Save email if remember is checked
            document.getElementById('remember').addEventListener('change', function() {
                const email = emailInput.value.trim();
                if (this.checked && email) {
                    localStorage.setItem('ardhiyetu_email', email);
                } else {
                    localStorage.removeItem('ardhiyetu_email');
                }
            });
            
            // Language selector
            const languageSelect = document.getElementById('language');
            const savedLanguage = localStorage.getItem('ardhiyetu_language') || 'en';
            languageSelect.value = savedLanguage;
            
            languageSelect.addEventListener('change', function() {
                const language = this.value;
                localStorage.setItem('ardhiyetu_language', language);
                showToast(`Language changed to ${this.options[this.selectedIndex].text}`, 'success');
                // In production, you would reload the page with new language
                // window.location.href = `?lang=${language}`;
            });
            
            // Demo credentials auto-fill
            window.fillDemo = function(value) {
                if (value === 'demo@ardhiyetu.com') {
                    emailInput.value = value;
                    validateEmail();
                    passwordInputField.focus();
                } else if (value === 'demopass123') {
                    passwordInputField.value = value;
                    validatePassword();
                    showToast('Demo credentials filled. Click Login to continue.', 'info');
                }
            };
        });
        
        function calculatePasswordStrength(password) {
            let strength = 0;
            
            // Length check
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            
            // Character variety checks
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            return Math.min(strength, 4);
        }
        
        function showToast(message, type = 'info') {
            // Remove existing toasts
            document.querySelectorAll('.toast').forEach(toast => toast.remove());
            
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            
            const icons = {
                success: 'check-circle',
                error: 'exclamation-circle',
                warning: 'exclamation-triangle',
                info: 'info-circle'
            };
            
            toast.innerHTML = `
                <i class="fas fa-${icons[type] || 'info-circle'}"></i>
                <span>${message}</span>
                <button class="toast-close" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            // Add styles
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: white;
                padding: 15px 20px;
                border-radius: 10px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.15);
                display: flex;
                align-items: center;
                gap: 12px;
                z-index: 10000;
                animation: slideInRight 0.3s ease;
                border-left: 4px solid ${type === 'success' ? '#27AE60' : type === 'error' ? '#E74C3C' : type === 'warning' ? '#F39C12' : '#3498DB'};
                max-width: 400px;
            `;
            
            // Close button styles
            const closeBtn = toast.querySelector('.toast-close');
            closeBtn.style.cssText = `
                background: none;
                border: none;
                color: #95A5A6;
                cursor: pointer;
                padding: 0;
                margin-left: auto;
                font-size: 14px;
                transition: all 0.3s ease;
            `;
            
            closeBtn.addEventListener('mouseenter', () => {
                closeBtn.style.color = '#E74C3C';
            });
            
            closeBtn.addEventListener('mouseleave', () => {
                closeBtn.style.color = '#95A5A6';
            });
            
            closeBtn.addEventListener('click', () => {
                toast.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            });
            
            document.body.appendChild(toast);
            
            // Remove after 5 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.style.animation = 'slideOutRight 0.3s ease';
                    setTimeout(() => toast.remove(), 300);
                }
            }, 5000);
            
            // Add keyframes if not already added
            if (!document.getElementById('toast-animations')) {
                const style = document.createElement('style');
                style.id = 'toast-animations';
                style.textContent = `
                    @keyframes slideInRight {
                        from { transform: translateX(100%); opacity: 0; }
                        to { transform: translateX(0); opacity: 1; }
                    }
                    @keyframes slideOutRight {
                        from { transform: translateX(0); opacity: 1; }
                        to { transform: translateX(100%); opacity: 0; }
                    }
                `;
                document.head.appendChild(style);
            }
        }
        
        // Social login functions
        function loginWithGoogle() {
            showToast('Redirecting to Google authentication...', 'info');
            // In production: window.location.href = 'auth/google.php';
        }
        
        function loginWithFacebook() {
            showToast('Redirecting to Facebook authentication...', 'info');
            // In production: window.location.href = 'auth/facebook.php';
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+Enter to submit form
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                const loginForm = document.getElementById('loginForm');
                if (loginForm) loginForm.submit();
            }
            
            // Esc to clear form
            if (e.key === 'Escape') {
                document.getElementById('loginForm').reset();
                showToast('Form cleared', 'info');
            }
            
            // F1 for help
            if (e.key === 'F1') {
                e.preventDefault();
                window.open('../help.php', '_blank');
            }
        });
        
        // Add accessibility features
        document.addEventListener('keyup', function(e) {
            // Tab navigation highlighting
            if (e.key === 'Tab') {
                document.body.classList.add('user-is-tabbing');
            }
        });
        
        document.addEventListener('mousedown', function() {
            document.body.classList.remove('user-is-tabbing');
        });
    </script>
</body>
</html>