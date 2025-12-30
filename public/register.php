<?php
require_once '../includes/init.php';

// Check if user is already logged in
if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$max_steps = 4;

// Handle multi-step form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Security token invalid. Please try again.';
    } else {
        $current_step = (int)$_POST['current_step'];
        
        // Store form data in session
        foreach ($_POST as $key => $value) {
            if ($key !== 'csrf_token' && $key !== 'current_step' && $key !== 'submit') {
                $_SESSION['registration_data'][$key] = sanitize_input($value);
            }
        }
        
        // Validate based on current step
        switch ($current_step) {
            case 1: // Personal Details
                $name = sanitize_input($_POST['name'] ?? '');
                $email = sanitize_input($_POST['email'] ?? '');
                $date_of_birth = sanitize_input($_POST['date_of_birth'] ?? '');
                $gender = sanitize_input($_POST['gender'] ?? '');
                
                if (empty($name) || empty($email) || empty($date_of_birth)) {
                    $error = 'Please fill in all required personal details';
                } elseif (!validate_email($email)) {
                    $error = 'Please enter a valid email address';
                } else {
                    // Check if email already exists
                    $check_email = "SELECT user_id FROM users WHERE email = ?";
                    $stmt = mysqli_prepare($conn, $check_email);
                    mysqli_stmt_bind_param($stmt, "s", $email);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    
                    if (mysqli_num_rows($result) > 0) {
                        $error = 'Email already registered. Please use a different email or <a href="login.php">login</a>.';
                    } else {
                        $step = 2;
                        $success = 'Personal details saved successfully';
                    }
                }
                break;
                
            case 2: // Contact Information
                $phone = sanitize_input($_POST['phone'] ?? '');
                $id_number = sanitize_input($_POST['id_number'] ?? '');
                $address = sanitize_input($_POST['address'] ?? '');
                $county = sanitize_input($_POST['county'] ?? '');
                
                if (empty($phone) || empty($id_number) || empty($county)) {
                    $error = 'Please fill in all required contact information';
                } elseif (!validate_phone($phone)) {
                    $error = 'Please enter a valid phone number (10-15 digits)';
                } elseif (!preg_match('/^[0-9]{8}$/', $id_number)) {
                    $error = 'Please enter a valid 8-digit national ID number';
                } else {
                    // Check if ID number already exists
                    $check_id = "SELECT user_id FROM users WHERE id_number = ?";
                    $stmt = mysqli_prepare($conn, $check_id);
                    mysqli_stmt_bind_param($stmt, "s", $id_number);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    
                    if (mysqli_num_rows($result) > 0) {
                        $error = 'ID number already registered. Please contact support if this is an error.';
                    } else {
                        $step = 3;
                        $success = 'Contact information saved successfully';
                    }
                }
                break;
                
            case 3: // Security Setup
                $password = $_POST['password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                $security_question = sanitize_input($_POST['security_question'] ?? '');
                $security_answer = sanitize_input($_POST['security_answer'] ?? '');
                
                if (empty($password) || empty($confirm_password) || empty($security_question) || empty($security_answer)) {
                    $error = 'Please fill in all security details';
                } elseif (strlen($password) < 8) {
                    $error = 'Password must be at least 8 characters long';
                } elseif ($password !== $confirm_password) {
                    $error = 'Passwords do not match';
                } else {
                    $step = 4;
                    $success = 'Security setup completed';
                }
                break;
                
            case 4: // Final Review & Terms
                $terms = isset($_POST['terms']) ? true : false;
                $newsletter = isset($_POST['newsletter']) ? true : false;
                $marketing = isset($_POST['marketing']) ? true : false;
                
                if (!$terms) {
                    $error = 'You must agree to the Terms of Service';
                } else {
                    // Get all data from session
                    $registration_data = $_SESSION['registration_data'] ?? [];
                    
                    if (empty($registration_data)) {
                        $error = 'Registration data not found. Please start over.';
                    } else {
                        // Hash password
                        $hashed_password = password_hash($registration_data['password'], PASSWORD_DEFAULT);
                        
                        // Generate verification token
                        $verification_token = bin2hex(random_bytes(32));
                        
                        // Insert user with prepared statement
                        $sql = "INSERT INTO users (name, email, phone, id_number, date_of_birth, gender, address, county, password, security_question, security_answer, verification_token, is_active) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, FALSE)";
                        
                        $stmt = mysqli_prepare($conn, $sql);
                        mysqli_stmt_bind_param($stmt, "ssssssssssss", 
                            $registration_data['name'],
                            $registration_data['email'],
                            $registration_data['phone'],
                            $registration_data['id_number'],
                            $registration_data['date_of_birth'],
                            $registration_data['gender'],
                            $registration_data['address'],
                            $registration_data['county'],
                            $hashed_password,
                            $registration_data['security_question'],
                            $registration_data['security_answer'],
                            $verification_token
                        );
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $user_id = mysqli_insert_id($conn);
                            
                            // Log activity
                            log_activity($user_id, 'registration', 'New user registered');
                            
                            // In development: Auto-verify the user
$verify_sql = "UPDATE users SET is_active = TRUE WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $verify_sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

// Redirect to login with success message
$_SESSION['flash']['success'] = 'Registration successful! You can now login to your account.';
redirect('login.php');
                            // Send welcome notification
                            $notification_sql = "INSERT INTO notifications (user_id, title, message, type) 
                                                 VALUES (?, 'Welcome to ArdhiYetu!', 
                                                         'Thank you for registering. Please verify your email to get started.', 
                                                         'success')";
                            $stmt = mysqli_prepare($conn, $notification_sql);
                            mysqli_stmt_bind_param($stmt, "s", $user_id);
                            mysqli_stmt_execute($stmt);
                            
                            // Add to newsletter if opted in
                            if ($newsletter) {
                                $newsletter_sql = "INSERT INTO newsletter_subscribers (email, name, user_id, subscribed_at) 
                                                   VALUES (?, ?, ?, NOW())";
                                $stmt = mysqli_prepare($conn, $newsletter_sql);
                                mysqli_stmt_bind_param($stmt, "sss", 
                                    $registration_data['email'],
                                    $registration_data['name'],
                                    $user_id
                                );
                                mysqli_stmt_execute($stmt);
                            }
                            
                            // Clear session data
                            unset($_SESSION['registration_data']);
                            
                            // Redirect to verification page
                            $_SESSION['flash']['success'] = 'Registration successful! Please check your email to verify your account.';
                            redirect('verify-email.php?email=' . urlencode($registration_data['email']));
                        } else {
                            $error = 'Registration failed. Please try again. Error: ' . mysqli_error($conn);
                        }
                    }
                }
                break;
        }
    }
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Pre-fill form from session data if exists
$form_data = $_SESSION['registration_data'] ?? [];

// Kenyan counties
$kenyan_counties = [
    'Baringo', 'Bomet', 'Bungoma', 'Busia', 'Elgeyo-Marakwet',
    'Embu', 'Garissa', 'Homa Bay', 'Isiolo', 'Kajiado',
    'Kakamega', 'Kericho', 'Kiambu', 'Kilifi', 'Kirinyaga',
    'Kisii', 'Kisumu', 'Kitui', 'Kwale', 'Laikipia',
    'Lamu', 'Machakos', 'Makueni', 'Mandera', 'Marsabit',
    'Meru', 'Migori', 'Mombasa', 'Muranga', 'Nairobi',
    'Nakuru', 'Nandi', 'Narok', 'Nyamira', 'Nyandarua',
    'Nyeri', 'Samburu', 'Siaya', 'Taita Taveta', 'Tana River',
    'Tharaka Nithi', 'Trans Nzoia', 'Turkana', 'Uasin Gishu',
    'Vihiga', 'Wajir', 'West Pokot'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - ArdhiYetu</title>
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
        
        .register-container {
            display: flex;
            max-width: 1400px;
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
        
        .register-left {
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
        
        .register-left::before {
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
        
        .progress-steps {
            margin: 40px 0;
        }
        
        .step {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            opacity: 0.7;
            transition: var(--transition);
        }
        
        .step.active {
            opacity: 1;
            transform: translateX(10px);
        }
        
        .step.completed {
            opacity: 1;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 18px;
            transition: var(--transition);
        }
        
        .step.active .step-number {
            background: var(--secondary);
            color: var(--dark);
            transform: scale(1.1);
        }
        
        .step.completed .step-number {
            background: var(--success);
            color: white;
        }
        
        .step-info h4 {
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .step-info p {
            font-size: 14px;
            opacity: 0.8;
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
        
        .register-right {
            flex: 1.5;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow-y: auto;
            max-height: 90vh;
        }
        
        .form-header {
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
        
        .register-form {
            width: 100%;
        }
        
        .form-section {
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 1px solid #E8ECEF;
            animation: fadeInUp 0.5s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .form-section:last-child {
            border-bottom: none;
        }
        
        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 25px;
        }
        
        .section-header i {
            color: var(--primary);
            font-size: 20px;
        }
        
        .section-header h3 {
            color: var(--dark);
            font-size: 20px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .form-group {
            position: relative;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark);
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-label .required {
            color: var(--danger);
        }
        
        .input-group {
            position: relative;
        }
        
        .input-group input,
        .input-group select,
        .input-group textarea {
            width: 100%;
            padding: 15px 20px 15px 50px;
            border: 2px solid #E8ECEF;
            border-radius: 10px;
            font-size: 16px;
            transition: var(--transition);
            outline: none;
            background: white;
        }
        
        .input-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .input-group input:focus,
        .input-group select:focus,
        .input-group textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(46, 134, 171, 0.1);
        }
        
        .input-group input.error,
        .input-group select.error,
        .input-group textarea.error {
            border-color: var(--danger);
        }
        
        .input-group input.success,
        .input-group select.success,
        .input-group textarea.success {
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
        
        .textarea-icon {
            position: absolute;
            left: 20px;
            top: 20px;
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
        
        .form-hint {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: var(--gray);
        }
        
        .form-hint.error {
            color: var(--danger);
        }
        
        .form-hint.success {
            color: var(--success);
        }
        
        .password-strength {
            margin-top: 10px;
        }
        
        .strength-meter {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 5px;
        }
        
        .strength-bars {
            display: flex;
            gap: 3px;
            flex: 1;
        }
        
        .strength-bar {
            height: 4px;
            flex: 1;
            background: #E8ECEF;
            border-radius: 2px;
            transition: var(--transition);
        }
        
        .strength-bar.active.weak { background: var(--danger); }
        .strength-bar.active.medium { background: var(--warning); }
        .strength-bar.active.strong { background: var(--success); }
        
        .strength-text {
            font-size: 12px;
            font-weight: 500;
        }
        
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 15px;
            cursor: pointer;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            flex-shrink: 0;
            margin-top: 3px;
            accent-color: var(--primary);
        }
        
        .checkbox-label {
            color: var(--dark);
            font-size: 14px;
            line-height: 1.5;
        }
        
        .checkbox-label a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .checkbox-label a:hover {
            text-decoration: underline;
        }
        
        .form-navigation {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .nav-button {
            flex: 1;
            padding: 16px;
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
        }
        
        .nav-button.prev {
            background: #E8ECEF;
            color: var(--dark);
        }
        
        .nav-button.prev:hover {
            background: #DDE1E6;
        }
        
        .nav-button.next,
        .nav-button.submit {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
        }
        
        .nav-button.next:hover,
        .nav-button.submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 134, 171, 0.3);
        }
        
        .nav-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }
        
        .login-link {
            text-align: center;
            color: var(--gray);
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #E8ECEF;
        }
        
        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            margin-left: 5px;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .form-progress {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .progress-bar {
            flex: 1;
            height: 4px;
            background: #E8ECEF;
            border-radius: 2px;
            overflow: hidden;
            margin: 0 10px;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--primary);
            width: <?php echo ($step / $max_steps) * 100; ?>%;
            transition: width 0.5s ease;
        }
        
        .progress-step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #E8ECEF;
            color: var(--gray);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            transition: var(--transition);
        }
        
        .progress-step.active {
            background: var(--primary);
            color: white;
            transform: scale(1.1);
        }
        
        .progress-step.completed {
            background: var(--success);
            color: white;
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .register-container {
                flex-direction: column;
                max-width: 700px;
            }
            
            .register-left {
                padding: 40px 30px;
            }
            
            .register-right {
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
            .form-header h2 {
                font-size: 26px;
            }
            
            .welcome-text h1 {
                font-size: 28px;
            }
            
            .step {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            
            .form-navigation {
                flex-direction: column;
            }
        }
        
        /* Animations */
        .form-group {
            animation: slideUp 0.5s ease forwards;
            opacity: 0;
            transform: translateY(20px);
        }
        
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
        
        .nav-button.loading .button-text {
            display: none;
        }
        
        .nav-button.loading .loading {
            display: block;
        }
        
        /* Language selector */
        .language-selector {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 10;
        }
        
        .language-selector select {
            padding: 8px 15px;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 8px;
            background: rgba(255,255,255,0.1);
            color: white;
            font-size: 14px;
            outline: none;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .language-selector select:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .language-selector select option {
            background: var(--primary-dark);
            color: white;
        }
        
        /* Tooltip */
        .tooltip {
            position: absolute;
            right: -10px;
            top: -35px;
            background: var(--dark);
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
            z-index: 100;
            white-space: nowrap;
        }
        
        .tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 5px solid transparent;
            border-top-color: var(--dark);
        }
        
        .input-group:hover .tooltip {
            opacity: 1;
            visibility: visible;
        }
        
        /* Validation icons */
        .validation-icon {
            position: absolute;
            right: 45px;
            top: 50%;
            transform: translateY(-50%);
            display: none;
            font-size: 16px;
        }
        
        .validation-icon.valid {
            color: var(--success);
        }
        
        .validation-icon.invalid {
            color: var(--danger);
        }
        
        .input-group.valid .validation-icon.valid {
            display: block;
        }
        
        .input-group.invalid .validation-icon.invalid {
            display: block;
        }
        
        /* Review section */
        .review-section {
            background: #F8F9FA;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .review-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #E8ECEF;
        }
        
        .review-item:last-child {
            border-bottom: none;
        }
        
        .review-label {
            font-weight: 500;
            color: var(--dark);
        }
        
        .review-value {
            color: var(--gray);
        }
        
        .review-value a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .review-value a:hover {
            text-decoration: underline;
        }
        
        /* Step indicator */
        .step-indicator {
            display: inline-block;
            background: var(--primary);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        /* Demo banner */
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
        
        /* Auto-save indicator */
        .auto-save-indicator {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--success);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            opacity: 0;
            transform: translateY(10px);
            transition: var(--transition);
            z-index: 1000;
        }
        
        .auto-save-indicator.show {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <!-- Back Home Button -->
    <div class="back-home">
        <a href="../public/index.php" class="back-home-btn">
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
    
    <div class="register-container">
        <!-- Left Panel -->
        <div class="register-left">
            <a href="../index.php" class="logo">
                <i class="fas fa-landmark"></i>
                <span>ArdhiYetu</span>
            </a>
            
            <div class="welcome-text">
                <h1>Join ArdhiYetu Today</h1>
                <p>Create your account and start managing your land records digitally with ease and security.</p>
            </div>
            
            <div class="progress-steps">
                <div class="step <?php echo $step >= 1 ? 'completed' : ($step == 1 ? 'active' : ''); ?>">
                    <div class="step-number">1</div>
                    <div class="step-info">
                        <h4>Personal Details</h4>
                        <p>Your name, email, and basic info</p>
                    </div>
                </div>
                <div class="step <?php echo $step >= 2 ? 'completed' : ($step == 2 ? 'active' : ''); ?>">
                    <div class="step-number">2</div>
                    <div class="step-info">
                        <h4>Contact Information</h4>
                        <p>Phone, ID, and location</p>
                    </div>
                </div>
                <div class="step <?php echo $step >= 3 ? 'completed' : ($step == 3 ? 'active' : ''); ?>">
                    <div class="step-number">3</div>
                    <div class="step-info">
                        <h4>Security Setup</h4>
                        <p>Password and security questions</p>
                    </div>
                </div>
                <div class="step <?php echo $step >= 4 ? 'completed' : ($step == 4 ? 'active' : ''); ?>">
                    <div class="step-number">4</div>
                    <div class="step-info">
                        <h4>Review & Complete</h4>
                        <p>Final review and terms</p>
                    </div>
                </div>
            </div>
            
            <div class="testimonial">
                <p>"Registering with ArdhiYetu was the best decision I made. Now I can manage all my land records from my phone!"</p>
                <div class="testimonial-author">
                    <img src="https://ui-avatars.com/api/?name=Sarah+Mwangi&background=F4A261&color=fff" alt="User">
                    <div>
                        <h5>Sarah Mwangi</h5>
                        <p>Registered 3 months ago</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Panel -->
        <div class="register-right">
            <div class="form-header">
                <span class="step-indicator">Step <?php echo $step; ?> of <?php echo $max_steps; ?></span>
                <h2>
                    <?php 
                    switch($step) {
                        case 1: echo 'Personal Information'; break;
                        case 2: echo 'Contact Information'; break;
                        case 3: echo 'Security Setup'; break;
                        case 4: echo 'Review & Terms'; break;
                    }
                    ?>
                </h2>
                <p>
                    <?php 
                    switch($step) {
                        case 1: echo 'Tell us about yourself'; break;
                        case 2: echo 'How can we contact you?'; break;
                        case 3: echo 'Secure your account'; break;
                        case 4: echo 'Review and complete registration'; break;
                    }
                    ?>
                </p>
            </div>
            
            <div class="form-progress">
                <?php for($i = 1; $i <= $max_steps; $i++): ?>
                    <div class="progress-step <?php echo $i < $step ? 'completed' : ($i == $step ? 'active' : ''); ?>">
                        <?php echo $i; ?>
                    </div>
                    <?php if($i < $max_steps): ?>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $i < $step ? '100%' : ($i == $step ? '50%' : '0%'); ?>"></div>
                        </div>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
            
            <?php if ($error): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $success; ?></span>
                </div>
            <?php endif; ?>
            
            <!-- Demo Banner (Optional - Remove in production) -->
            <?php if($step == 1): ?>
            <div class="demo-banner">
                <h4><i class="fas fa-lightbulb"></i> Quick Tip</h4>
                <p>Use your legal name as it appears on your ID document for verification</p>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="register-form" id="registerForm" data-step="<?php echo $step; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="current_step" value="<?php echo $step; ?>">
                
                <?php if($step == 1): ?>
                <!-- Step 1: Personal Details -->
                <div class="form-section">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Full Name <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-user input-icon"></i>
                                <input type="text" 
                                       id="name" 
                                       name="name" 
                                       required 
                                       placeholder="Enter your full name"
                                       value="<?php echo htmlspecialchars($form_data['name'] ?? ''); ?>"
                                       autocomplete="name"
                                       minlength="5"
                                       maxlength="100">
                                <div class="tooltip">Enter your legal name as on ID</div>
                                <div class="validation-icon valid">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="validation-icon invalid">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                            </div>
                            <span class="form-hint" id="nameHint">First and last name (minimum 5 characters)</span>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email Address <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-envelope input-icon"></i>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       required 
                                       placeholder="your.email@example.com"
                                       value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
                                       autocomplete="email">
                                <div class="tooltip">We'll send verification to this email</div>
                                <div class="validation-icon valid">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="validation-icon invalid">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                            </div>
                            <span class="form-hint" id="emailHint">Enter a valid email you can access</span>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Date of Birth <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-birthday-cake input-icon"></i>
                                <input type="date" 
                                       id="date_of_birth" 
                                       name="date_of_birth" 
                                       required 
                                       value="<?php echo htmlspecialchars($form_data['date_of_birth'] ?? ''); ?>"
                                       max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>">
                                <div class="tooltip">Must be 18+ years old</div>
                            </div>
                            <span class="form-hint" id="dobHint">You must be at least 18 years old</span>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Gender</label>
                            <div class="input-group">
                                <i class="fas fa-venus-mars input-icon"></i>
                                <select id="gender" name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="male" <?php echo isset($form_data['gender']) && $form_data['gender'] == 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo isset($form_data['gender']) && $form_data['gender'] == 'female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo isset($form_data['gender']) && $form_data['gender'] == 'other' ? 'selected' : ''; ?>>Other</option>
                                    <option value="prefer_not_to_say" <?php echo isset($form_data['gender']) && $form_data['gender'] == 'prefer_not_to_say' ? 'selected' : ''; ?>>Prefer not to say</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if($step == 2): ?>
                <!-- Step 2: Contact Information -->
                <div class="form-section">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Phone Number <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-phone input-icon"></i>
                                <input type="tel" 
                                       id="phone" 
                                       name="phone" 
                                       required 
                                       placeholder="0712345678"
                                       value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>"
                                       autocomplete="tel">
                                <div class="tooltip">10-15 digits, start with 07</div>
                                <div class="validation-icon valid">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="validation-icon invalid">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                            </div>
                            <span class="form-hint" id="phoneHint">Enter your mobile phone number</span>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">National ID Number <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-id-card input-icon"></i>
                                <input type="text" 
                                       id="id_number" 
                                       name="id_number" 
                                       required 
                                       placeholder="12345678"
                                       value="<?php echo htmlspecialchars($form_data['id_number'] ?? ''); ?>"
                                       pattern="[0-9]{8}"
                                       maxlength="8">
                                <div class="tooltip">8-digit Kenyan national ID</div>
                                <div class="validation-icon valid">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="validation-icon invalid">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                            </div>
                            <span class="form-hint" id="idHint">8-digit ID number</span>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Address</label>
                            <div class="input-group">
                                <i class="fas fa-home textarea-icon"></i>
                                <textarea id="address" 
                                          name="address" 
                                          placeholder="Enter your residential address"
                                          maxlength="200"><?php echo htmlspecialchars($form_data['address'] ?? ''); ?></textarea>
                            </div>
                            <span class="form-hint">Optional - Your physical address</span>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">County <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-map-marker-alt input-icon"></i>
                                <select id="county" name="county" required>
                                    <option value="">Select County</option>
                                    <?php foreach($kenyan_counties as $county): ?>
                                        <option value="<?php echo $county; ?>" 
                                            <?php echo isset($form_data['county']) && $form_data['county'] == $county ? 'selected' : ''; ?>>
                                            <?php echo $county; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="tooltip">Select your county of residence</div>
                            </div>
                            <span class="form-hint">Select your county from the list</span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if($step == 3): ?>
                <!-- Step 3: Security Setup -->
                <div class="form-section">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Password <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" 
                                       id="password" 
                                       name="password" 
                                       required 
                                       placeholder="Create a strong password"
                                       minlength="8"
                                       autocomplete="new-password">
                                <button type="button" class="toggle-password" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <div class="tooltip">Minimum 8 characters</div>
                                <div class="validation-icon valid">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="validation-icon invalid">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                            </div>
                            <div class="password-strength">
                                <div class="strength-meter">
                                    <div class="strength-bars" id="strengthBars">
                                        <div class="strength-bar"></div>
                                        <div class="strength-bar"></div>
                                        <div class="strength-bar"></div>
                                        <div class="strength-bar"></div>
                                    </div>
                                    <span class="strength-text" id="strengthText">Weak</span>
                                </div>
                            </div>
                            <span class="form-hint" id="passwordHint">Use 8+ characters with mix of letters, numbers, and symbols</span>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Confirm Password <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       required 
                                       placeholder="Confirm your password"
                                       autocomplete="new-password">
                                <button type="button" class="toggle-password" id="toggleConfirmPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <div class="validation-icon valid">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="validation-icon invalid">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                            </div>
                            <span class="form-hint" id="confirmHint">Must match the password above</span>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Security Question <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-question-circle input-icon"></i>
                                <select id="security_question" name="security_question" required>
                                    <option value="">Select a security question</option>
                                    <option value="mother_maiden" <?php echo isset($form_data['security_question']) && $form_data['security_question'] == 'mother_maiden' ? 'selected' : ''; ?>>What is your mother's maiden name?</option>
                                    <option value="first_pet" <?php echo isset($form_data['security_question']) && $form_data['security_question'] == 'first_pet' ? 'selected' : ''; ?>>What was your first pet's name?</option>
                                    <option value="birth_city" <?php echo isset($form_data['security_question']) && $form_data['security_question'] == 'birth_city' ? 'selected' : ''; ?>>In what city were you born?</option>
                                    <option value="high_school" <?php echo isset($form_data['security_question']) && $form_data['security_question'] == 'high_school' ? 'selected' : ''; ?>>What high school did you attend?</option>
                                    <option value="first_car" <?php echo isset($form_data['security_question']) && $form_data['security_question'] == 'first_car' ? 'selected' : ''; ?>>What was your first car?</option>
                                </select>
                                <div class="tooltip">Select a question for account recovery</div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Security Answer <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-key input-icon"></i>
                                <input type="text" 
                                       id="security_answer" 
                                       name="security_answer" 
                                       required 
                                       placeholder="Your answer"
                                       value="<?php echo htmlspecialchars($form_data['security_answer'] ?? ''); ?>"
                                       minlength="2">
                                <div class="tooltip">Remember this for account recovery</div>
                            </div>
                            <span class="form-hint">Answer to your security question</span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if($step == 4): ?>
                <!-- Step 4: Review & Terms -->
                <div class="form-section">
                    <div class="review-section">
                        <h4 style="margin-bottom: 20px; color: var(--dark);">Review Your Information</h4>
                        
                        <div class="review-item">
                            <span class="review-label">Full Name:</span>
                            <span class="review-value"><?php echo htmlspecialchars($form_data['name'] ?? 'Not provided'); ?></span>
                        </div>
                        
                        <div class="review-item">
                            <span class="review-label">Email:</span>
                            <span class="review-value"><?php echo htmlspecialchars($form_data['email'] ?? 'Not provided'); ?></span>
                        </div>
                        
                        <div class="review-item">
                            <span class="review-label">Phone:</span>
                            <span class="review-value"><?php echo htmlspecialchars($form_data['phone'] ?? 'Not provided'); ?></span>
                        </div>
                        
                        <div class="review-item">
                            <span class="review-label">ID Number:</span>
                            <span class="review-value"><?php echo htmlspecialchars($form_data['id_number'] ?? 'Not provided'); ?></span>
                        </div>
                        
                        <div class="review-item">
                            <span class="review-label">Date of Birth:</span>
                            <span class="review-value"><?php echo htmlspecialchars($form_data['date_of_birth'] ?? 'Not provided'); ?></span>
                        </div>
                        
                        <div class="review-item">
                            <span class="review-label">County:</span>
                            <span class="review-value"><?php echo htmlspecialchars($form_data['county'] ?? 'Not provided'); ?></span>
                        </div>
                        
                        <?php if(isset($form_data['gender']) && $form_data['gender']): ?>
                        <div class="review-item">
                            <span class="review-label">Gender:</span>
                            <span class="review-value"><?php echo ucfirst(htmlspecialchars($form_data['gender'])); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if(isset($form_data['address']) && $form_data['address']): ?>
                        <div class="review-item">
                            <span class="review-label">Address:</span>
                            <span class="review-value"><?php echo htmlspecialchars($form_data['address']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div style="margin-top: 30px;">
                        <label class="checkbox-group">
                            <input type="checkbox" name="terms" id="terms" required>
                            <span class="checkbox-label">
                                I agree to the <a href="../terms.php" target="_blank">Terms of Service</a> and 
                                <a href="../privacy.php" target="_blank">Privacy Policy</a> <span class="required">*</span>
                            </span>
                        </label>
                        
                        <label class="checkbox-group">
                            <input type="checkbox" name="newsletter" id="newsletter" <?php echo isset($form_data['newsletter']) ? 'checked' : 'checked'; ?>>
                            <span class="checkbox-label">
                                Subscribe to newsletters and important updates about land administration
                            </span>
                        </label>
                        
                        <label class="checkbox-group">
                            <input type="checkbox" name="marketing" id="marketing" <?php echo isset($form_data['marketing']) ? 'checked' : ''; ?>>
                            <span class="checkbox-label">
                                I agree to receive marketing communications about ArdhiYetu services
                            </span>
                        </label>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="form-navigation">
                    <?php if($step > 1): ?>
                    <button type="button" class="nav-button prev" id="prevButton">
                        <i class="fas fa-arrow-left"></i>
                        <span class="button-text">Previous</span>
                    </button>
                    <?php endif; ?>
                    
                    <?php if($step < $max_steps): ?>
                    <button type="submit" class="nav-button next" id="nextButton">
                        <span class="button-text">Save & Continue</span>
                        <i class="fas fa-arrow-right"></i>
                        <div class="loading"></div>
                    </button>
                    <?php else: ?>
                    <button type="submit" class="nav-button submit" id="submitButton">
                        <span class="button-text">Create Account</span>
                        <i class="fas fa-check"></i>
                        <div class="loading"></div>
                    </button>
                    <?php endif; ?>
                </div>
                
                <p class="login-link">
                    Already have an account?
                    <a href="login.php">Login here</a>
                </p>
            </form>
        </div>
    </div>
    
    <!-- Auto-save Indicator -->
    <div class="auto-save-indicator" id="autoSaveIndicator">
        <i class="fas fa-save"></i>
        <span>Changes saved</span>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize the registration form
            initRegistrationForm();
            
            // Setup navigation
            setupNavigation();
            
            // Setup validation
            setupValidation();
            
            // Setup auto-save
            setupAutoSave();
            
            // Setup language selector
            setupLanguageSelector();
            
            // Setup keyboard shortcuts
            setupKeyboardShortcuts();
        });
        
        function initRegistrationForm() {
            const currentStep = <?php echo $step; ?>;
            
            // Focus first input field on each step
            switch(currentStep) {
                case 1:
                    document.getElementById('name')?.focus();
                    break;
                case 2:
                    document.getElementById('phone')?.focus();
                    break;
                case 3:
                    document.getElementById('password')?.focus();
                    break;
                case 4:
                    document.getElementById('terms')?.focus();
                    break;
            }
            
            // Restore auto-saved data
            restoreAutoSavedData();
        }
        
        function setupNavigation() {
            const prevButton = document.getElementById('prevButton');
            const nextButton = document.getElementById('nextButton');
            const submitButton = document.getElementById('submitButton');
            const currentStep = <?php echo $step; ?>;
            
            // Previous button
            if (prevButton) {
                prevButton.addEventListener('click', function() {
                    const newStep = currentStep - 1;
                    window.location.href = `?step=${newStep}`;
                });
            }
            
            // Next button validation
            if (nextButton) {
                nextButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    if (validateCurrentStep(currentStep)) {
                        // Save form data to localStorage before proceeding
                        saveFormData();
                        
                        // Show loading state
                        this.classList.add('loading');
                        this.disabled = true;
                        
                        // Submit form
                        setTimeout(() => {
                            document.getElementById('registerForm').submit();
                        }, 500);
                    }
                });
            }
            
            // Submit button
            if (submitButton) {
                submitButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    if (validateCurrentStep(currentStep)) {
                        // Check terms agreement
                        const terms = document.getElementById('terms');
                        if (!terms || !terms.checked) {
                            showToast('You must agree to the Terms of Service', 'error');
                            return;
                        }
                        
                        // Show loading state
                        this.classList.add('loading');
                        this.disabled = true;
                        
                        // Submit form
                        setTimeout(() => {
                            document.getElementById('registerForm').submit();
                        }, 500);
                    }
                });
            }
        }
        
        function validateCurrentStep(step) {
            let isValid = true;
            
            switch(step) {
                case 1:
                    isValid = validateStep1();
                    break;
                case 2:
                    isValid = validateStep2();
                    break;
                case 3:
                    isValid = validateStep3();
                    break;
                case 4:
                    isValid = validateStep4();
                    break;
            }
            
            if (!isValid) {
                showToast('Please fix the errors before continuing', 'error');
                return false;
            }
            
            return true;
        }
        
        function validateStep1() {
            let isValid = true;
            
            // Name validation
            const name = document.getElementById('name');
            const nameHint = document.getElementById('nameHint');
            if (!validateName(name.value)) {
                markInvalid(name, nameHint, 'Enter first and last name (minimum 5 characters)');
                isValid = false;
            } else {
                markValid(name, nameHint, '✓ Valid full name');
            }
            
            // Email validation
            const email = document.getElementById('email');
            const emailHint = document.getElementById('emailHint');
            if (!validateEmail(email.value)) {
                markInvalid(email, emailHint, 'Please enter a valid email address');
                isValid = false;
            } else {
                markValid(email, emailHint, '✓ Valid email');
            }
            
            // Date of birth validation
            const dob = document.getElementById('date_of_birth');
            const dobHint = document.getElementById('dobHint');
            if (!dob.value) {
                markInvalid(dob, dobHint, 'Date of birth is required');
                isValid = false;
            } else {
                const birthDate = new Date(dob.value);
                const today = new Date();
                const age = today.getFullYear() - birthDate.getFullYear();
                if (age < 18) {
                    markInvalid(dob, dobHint, 'You must be at least 18 years old');
                    isValid = false;
                } else {
                    markValid(dob, dobHint, '✓ Valid date of birth');
                }
            }
            
            return isValid;
        }
        
        function validateStep2() {
            let isValid = true;
            
            // Phone validation
            const phone = document.getElementById('phone');
            const phoneHint = document.getElementById('phoneHint');
            const phoneRegex = /^[0-9]{10,15}$/;
            if (!phoneRegex.test(phone.value)) {
                markInvalid(phone, phoneHint, 'Phone must be 10-15 digits');
                isValid = false;
            } else {
                markValid(phone, phoneHint, '✓ Valid phone number');
            }
            
            // ID validation
            const id = document.getElementById('id_number');
            const idHint = document.getElementById('idHint');
            const idRegex = /^[0-9]{8}$/;
            if (!idRegex.test(id.value)) {
                markInvalid(id, idHint, 'ID must be 8 digits');
                isValid = false;
            } else {
                markValid(id, idHint, '✓ Valid ID number');
            }
            
            // County validation
            const county = document.getElementById('county');
            if (!county.value) {
                county.classList.add('error');
                isValid = false;
            } else {
                county.classList.remove('error');
                county.classList.add('success');
            }
            
            return isValid;
        }
        
        function validateStep3() {
            let isValid = true;
            
            // Password validation
            const password = document.getElementById('password');
            const passwordHint = document.getElementById('passwordHint');
            if (password.value.length < 8) {
                markInvalid(password, passwordHint, 'Password must be at least 8 characters');
                isValid = false;
            } else {
                markValid(password, passwordHint, '✓ Strong password');
            }
            
            // Confirm password validation
            const confirm = document.getElementById('confirm_password');
            const confirmHint = document.getElementById('confirmHint');
            if (confirm.value !== password.value) {
                markInvalid(confirm, confirmHint, 'Passwords do not match');
                isValid = false;
            } else {
                markValid(confirm, confirmHint, '✓ Passwords match');
            }
            
            // Security question validation
            const question = document.getElementById('security_question');
            const answer = document.getElementById('security_answer');
            if (!question.value || !answer.value || answer.value.length < 2) {
                if (!question.value) question.classList.add('error');
                if (!answer.value || answer.value.length < 2) answer.classList.add('error');
                isValid = false;
            } else {
                question.classList.remove('error');
                answer.classList.remove('error');
                question.classList.add('success');
                answer.classList.add('success');
            }
            
            return isValid;
        }
        
        function validateStep4() {
            // Terms agreement is checked in submit handler
            return true;
        }
        
        function validateName(name) {
            if (!name || name.trim().length < 5) return false;
            const nameParts = name.trim().split(/\s+/);
            return nameParts.length >= 2;
        }
        
        function validateEmail(email) {
            const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            return emailRegex.test(email);
        }
        
        function markInvalid(element, hintElement, message) {
            element.classList.remove('success');
            element.classList.add('error');
            element.parentElement.classList.remove('valid');
            element.parentElement.classList.add('invalid');
            
            if (hintElement) {
                hintElement.textContent = message;
                hintElement.classList.remove('success');
                hintElement.classList.add('error');
            }
        }
        
        function markValid(element, hintElement, message) {
            element.classList.remove('error');
            element.classList.add('success');
            element.parentElement.classList.remove('invalid');
            element.parentElement.classList.add('valid');
            
            if (hintElement) {
                hintElement.textContent = message;
                hintElement.classList.remove('error');
                hintElement.classList.add('success');
            }
        }
        
        function setupValidation() {
            // Real-time validation for step 1
            if (document.getElementById('name')) {
                document.getElementById('name').addEventListener('input', function() {
                    const nameHint = document.getElementById('nameHint');
                    if (validateName(this.value)) {
                        markValid(this, nameHint, '✓ Valid full name');
                    } else {
                        markInvalid(this, nameHint, 'Enter first and last name (minimum 5 characters)');
                    }
                });
                
                document.getElementById('email').addEventListener('input', function() {
                    const emailHint = document.getElementById('emailHint');
                    if (validateEmail(this.value)) {
                        markValid(this, emailHint, '✓ Valid email');
                    } else {
                        markInvalid(this, emailHint, 'Please enter a valid email address');
                    }
                });
            }
            
            // Real-time validation for step 2
            if (document.getElementById('phone')) {
                document.getElementById('phone').addEventListener('input', function() {
                    const phoneHint = document.getElementById('phoneHint');
                    const phoneRegex = /^[0-9]{10,15}$/;
                    if (phoneRegex.test(this.value)) {
                        markValid(this, phoneHint, '✓ Valid phone number');
                    } else {
                        markInvalid(this, phoneHint, 'Phone must be 10-15 digits');
                    }
                });
                
                document.getElementById('id_number').addEventListener('input', function() {
                    const idHint = document.getElementById('idHint');
                    const idRegex = /^[0-9]{8}$/;
                    if (idRegex.test(this.value)) {
                        markValid(this, idHint, '✓ Valid ID number');
                    } else {
                        markInvalid(this, idHint, 'ID must be 8 digits');
                    }
                });
            }
            
            // Password strength indicator
            const passwordInput = document.getElementById('password');
            if (passwordInput) {
                passwordInput.addEventListener('input', function() {
                    const strength = calculatePasswordStrength(this.value);
                    updateStrengthIndicator(strength);
                    
                    const passwordHint = document.getElementById('passwordHint');
                    if (this.value.length >= 8) {
                        markValid(this, passwordHint, '✓ Strong password');
                    } else {
                        markInvalid(this, passwordHint, 'Password must be at least 8 characters');
                    }
                    
                    // Update confirm password validation
                    const confirm = document.getElementById('confirm_password');
                    const confirmHint = document.getElementById('confirmHint');
                    if (confirm && confirm.value) {
                        if (confirm.value === this.value) {
                            markValid(confirm, confirmHint, '✓ Passwords match');
                        } else {
                            markInvalid(confirm, confirmHint, 'Passwords do not match');
                        }
                    }
                });
                
                // Confirm password validation
                const confirmInput = document.getElementById('confirm_password');
                if (confirmInput) {
                    confirmInput.addEventListener('input', function() {
                        const confirmHint = document.getElementById('confirmHint');
                        const password = document.getElementById('password').value;
                        
                        if (this.value === password) {
                            markValid(this, confirmHint, '✓ Passwords match');
                        } else {
                            markInvalid(this, confirmHint, 'Passwords do not match');
                        }
                    });
                }
                
                // Password toggle
                const togglePassword = document.getElementById('togglePassword');
                const toggleConfirm = document.getElementById('toggleConfirmPassword');
                
                if (togglePassword) {
                    togglePassword.addEventListener('click', function() {
                        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                        passwordInput.setAttribute('type', type);
                        this.querySelector('i').classList.toggle('fa-eye');
                        this.querySelector('i').classList.toggle('fa-eye-slash');
                    });
                }
                
                if (toggleConfirm && confirmInput) {
                    toggleConfirm.addEventListener('click', function() {
                        const type = confirmInput.getAttribute('type') === 'password' ? 'text' : 'password';
                        confirmInput.setAttribute('type', type);
                        this.querySelector('i').classList.toggle('fa-eye');
                        this.querySelector('i').classList.toggle('fa-eye-slash');
                    });
                }
            }
        }
        
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
        
        function updateStrengthIndicator(strength) {
            const bars = document.getElementById('strengthBars').children;
            const text = document.getElementById('strengthText');
            
            // Reset all bars
            for (let i = 0; i < bars.length; i++) {
                bars[i].className = 'strength-bar';
                if (i < strength) {
                    bars[i].classList.add('active');
                    if (strength < 2) {
                        bars[i].classList.add('weak');
                    } else if (strength < 4) {
                        bars[i].classList.add('medium');
                    } else {
                        bars[i].classList.add('strong');
                    }
                }
            }
            
            // Update text
            const texts = ['Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
            text.textContent = texts[strength];
        }
        
        function setupAutoSave() {
            const form = document.getElementById('registerForm');
            const autoSaveIndicator = document.getElementById('autoSaveIndicator');
            let autoSaveTimer;
            
            if (!form) return;
            
            // Save form data on input
            form.addEventListener('input', function() {
                clearTimeout(autoSaveTimer);
                autoSaveTimer = setTimeout(() => {
                    saveFormData();
                    showAutoSaveIndicator(autoSaveIndicator);
                }, 1000);
            });
            
            // Save on page unload
            window.addEventListener('beforeunload', function() {
                saveFormData();
            });
        }
        
        function saveFormData() {
            const form = document.getElementById('registerForm');
            if (!form) return;
            
            const formData = new FormData(form);
            const data = {};
            
            for (let [key, value] of formData.entries()) {
                if (key !== 'csrf_token' && key !== 'current_step' && key !== 'submit') {
                    data[key] = value;
                }
            }
            
            // Save to localStorage
            localStorage.setItem('ardhiyetu_registration_data', JSON.stringify(data));
            localStorage.setItem('ardhiyetu_registration_step', <?php echo $step; ?>);
        }
        
        function restoreAutoSavedData() {
            const savedData = localStorage.getItem('ardhiyetu_registration_data');
            const savedStep = localStorage.getItem('ardhiyetu_registration_step');
            
            if (savedData && savedStep && parseInt(savedStep) === <?php echo $step; ?>) {
                const data = JSON.parse(savedData);
                
                // Restore form values
                Object.keys(data).forEach(key => {
                    const element = document.getElementById(key);
                    if (element) {
                        if (element.type === 'checkbox') {
                            element.checked = data[key] === 'on';
                        } else if (element.type === 'select-one') {
                            element.value = data[key];
                        } else {
                            element.value = data[key];
                        }
                        
                        // Trigger validation
                        element.dispatchEvent(new Event('input'));
                    }
                });
                
                showToast('Previous data restored', 'success');
            }
        }
        
        function showAutoSaveIndicator(indicator) {
            if (!indicator) return;
            
            indicator.classList.add('show');
            
            setTimeout(() => {
                indicator.classList.remove('show');
            }, 2000);
        }
        
        function setupLanguageSelector() {
            const languageSelect = document.getElementById('language');
            if (languageSelect) {
                languageSelect.addEventListener('change', function() {
                    showToast('Language changed to ' + this.options[this.selectedIndex].text, 'success');
                    // In production: window.location.href = `?lang=${this.value}`;
                });
            }
        }
        
        function setupKeyboardShortcuts() {
            document.addEventListener('keydown', function(e) {
                // Ctrl+Enter to submit current step
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    const currentStep = <?php echo $step; ?>;
                    if (currentStep < 4) {
                        document.getElementById('nextButton')?.click();
                    } else {
                        document.getElementById('submitButton')?.click();
                    }
                }
                
                // Ctrl+S to save
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    saveFormData();
                    showToast('Data saved locally', 'success');
                }
                
                // Escape to clear auto-saved data
                if (e.key === 'Escape') {
                    if (confirm('Clear all saved form data?')) {
                        localStorage.removeItem('ardhiyetu_registration_data');
                        localStorage.removeItem('ardhiyetu_registration_step');
                        showToast('Saved data cleared', 'success');
                    }
                }
            });
        }
        
        function showToast(message, type = 'info') {
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
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
    </script>
</body>
</html>