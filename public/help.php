<?php
require_once '../includes/init.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle contact form submission
$form_success = false;
$form_error = '';
$form_data = [
    'name' => '',
    'email' => '',
    'subject' => '',
    'category' => 'general',
    'message' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    // Sanitize input
    $form_data['name'] = trim($_POST['name'] ?? '');
    $form_data['email'] = trim($_POST['email'] ?? '');
    $form_data['subject'] = trim($_POST['subject'] ?? '');
    $form_data['category'] = trim($_POST['category'] ?? 'general');
    $form_data['message'] = trim($_POST['message'] ?? '');
    
    // Validate input
    $errors = [];
    
    if (empty($form_data['name'])) {
        $errors[] = 'Name is required';
    }
    
    if (empty($form_data['email'])) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    if (empty($form_data['subject'])) {
        $errors[] = 'Subject is required';
    }
    
    if (empty($form_data['message'])) {
        $errors[] = 'Message is required';
    } elseif (strlen($form_data['message']) < 10) {
        $errors[] = 'Message must be at least 10 characters';
    }
    
    if (empty($errors)) {
        // Get user data if logged in user is submitting
        if (is_logged_in()) {
            $user_stmt = mysqli_prepare($conn, "SELECT name, email FROM users WHERE user_id = ?");
            mysqli_stmt_bind_param($user_stmt, 'i', $user_id);
            mysqli_stmt_execute($user_stmt);
            $user_result = mysqli_stmt_get_result($user_stmt);
            $user = mysqli_fetch_assoc($user_result);
            
            // Use logged in user data if form fields are empty
            if (empty($form_data['name']) && $user) {
                $form_data['name'] = $user['name'];
            }
            if (empty($form_data['email']) && $user) {
                $form_data['email'] = $user['email'];
            }
        }
        
        // Insert into support_tickets table if it exists
        $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'support_tickets'");
        if (mysqli_num_rows($check_table) > 0) {
            $stmt = mysqli_prepare($conn, 
                "INSERT INTO support_tickets (user_id, name, email, subject, category, message, status, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, 'open', NOW())");
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'isssss', 
                    $user_id, 
                    $form_data['name'], 
                    $form_data['email'], 
                    $form_data['subject'], 
                    $form_data['category'], 
                    $form_data['message']
                );
                
                if (mysqli_stmt_execute($stmt)) {
                    $ticket_id = mysqli_insert_id($conn);
                    $form_success = true;
                    
                    // Send email notification to admin
                    $to = 'support@ardhiyetu.go.ke';
                    $subject = 'New Support Ticket: ' . $form_data['subject'];
                    $message = "A new support ticket has been submitted:\n\n";
                    $message .= "Ticket ID: #" . str_pad($ticket_id, 6, '0', STR_PAD_LEFT) . "\n";
                    $message .= "Name: " . $form_data['name'] . "\n";
                    $message .= "Email: " . $form_data['email'] . "\n";
                    $message .= "Category: " . $form_data['category'] . "\n";
                    $message .= "Message:\n" . $form_data['message'] . "\n\n";
                    $message .= "Submitted at: " . date('Y-m-d H:i:s');
                    
                    $headers = "From: " . $form_data['email'] . "\r\n";
                    $headers .= "Reply-To: " . $form_data['email'] . "\r\n";
                    
                    // Uncomment to send email
                    // mail($to, $subject, $message, $headers);
                    
                    // Clear form data
                    $form_data = [
                        'name' => '',
                        'email' => '',
                        'subject' => '',
                        'category' => 'general',
                        'message' => ''
                    ];
                } else {
                    $form_error = 'Failed to submit ticket. Please try again.';
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            // If support_tickets table doesn't exist, just show success message
            $form_success = true;
            
            // Send email directly
            $to = 'support@ardhiyetu.go.ke';
            $subject = 'Contact Form: ' . $form_data['subject'];
            $message = "New contact form submission:\n\n";
            $message .= "Name: " . $form_data['name'] . "\n";
            $message .= "Email: " . $form_data['email'] . "\n";
            $message .= "Category: " . $form_data['category'] . "\n";
            $message .= "Message:\n" . $form_data['message'];
            
            $headers = "From: " . $form_data['email'] . "\r\n";
            $headers .= "Reply-To: " . $form_data['email'] . "\r\n";
            
            // Uncomment to send email
            // mail($to, $subject, $message, $headers);
            
            // Clear form data
            $form_data = [
                'name' => '',
                'email' => '',
                'subject' => '',
                'category' => 'general',
                'message' => ''
            ];
        }
    } else {
        $form_error = implode('<br>', $errors);
    }
}

// Get user data for pre-filling form
$user_data = [];
if (is_logged_in()) {
    $stmt = mysqli_prepare($conn, "SELECT name, email FROM users WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user_data = mysqli_fetch_assoc($result);
}

// Get unread chat message count for badge
$unread_chat_count = 0;
if (is_logged_in()) {
    $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'chat_conversations'");
    if (mysqli_num_rows($check_table) > 0) {
        $unread_stmt = mysqli_prepare($conn, 
            "SELECT COUNT(*) as unread 
             FROM chat_messages cm
             JOIN chat_conversations cc ON cm.conversation_id = cc.conversation_id
             WHERE cc.user_id = ? AND cm.sender_id != ? AND cm.is_read = FALSE");
        mysqli_stmt_bind_param($unread_stmt, 'ii', $user_id, $user_id);
        mysqli_stmt_execute($unread_stmt);
        $unread_result = mysqli_stmt_get_result($unread_stmt);
        $unread_data = mysqli_fetch_assoc($unread_result);
        $unread_chat_count = $unread_data['unread'] ?? 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Center - ArdhiYetu</title>
    
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .help-center {
            padding: 2rem 0;
            background: #f8f9fa;
            min-height: calc(100vh - 200px);
        }
        
        .help-header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        
        .help-header h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .help-search {
            max-width: 600px;
            margin: 0 auto;
            position: relative;
        }
        
        .help-search input {
            width: 100%;
            padding: 1rem 1.5rem;
            border-radius: 50px;
            border: none;
            font-size: 1rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .help-search button {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: #667eea;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            cursor: pointer;
        }
        
        .help-categories {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .category-card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .category-icon {
            width: 70px;
            height: 70px;
            background: #667eea;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 2rem;
        }
        
        .faq-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 3rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        .faq-item {
            margin-bottom: 1rem;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .faq-question {
            padding: 1.5rem;
            background: #f8f9fa;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
        }
        
        .faq-question:hover {
            background: #e9ecef;
        }
        
        .faq-answer {
            padding: 1.5rem;
            display: none;
            border-top: 1px solid #e9ecef;
        }
        
        .faq-answer.show {
            display: block;
        }
        
        .contact-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        .contact-form .form-group {
            margin-bottom: 1.5rem;
        }
        
        .contact-form label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .contact-form input,
        .contact-form select,
        .contact-form textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .contact-form textarea {
            min-height: 150px;
            resize: vertical;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .tutorial-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .tutorial-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .tutorial-card:hover {
            transform: translateY(-5px);
        }
        
        .tutorial-thumbnail {
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 4rem;
        }
        
        .tutorial-content {
            padding: 1.5rem;
        }
        
        .resources-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .resource-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .resource-icon {
            width: 50px;
            height: 50px;
            background: #667eea;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        
        .support-channels {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .support-channel {
            text-align: center;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        .channel-icon {
            width: 60px;
            height: 60px;
            background: #667eea;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 1.5rem;
        }
        
        /* Live Chat Widget Styles */
        .chat-widget {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 350px;
            height: 500px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 30px rgba(0,0,0,0.2);
            z-index: 1000;
            display: none;
            flex-direction: column;
            overflow: hidden;
        }
        
        .chat-widget.chat-open {
            display: flex;
        }
        
        .chat-widget.chat-minimized {
            height: 60px;
        }
        
        .chat-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            user-select: none;
        }
        
        .chat-header-info {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }
        
        .chat-header-info i {
            font-size: 1.2rem;
        }
        
        .chat-badge {
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }
        
        .chat-header-controls {
            display: flex;
            gap: 10px;
        }
        
        .chat-header-controls i {
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.2s;
        }
        
        .chat-header-controls i:hover {
            opacity: 1;
        }
        
        .chat-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .chat-minimized .chat-body {
            display: none;
        }
        
        .chat-auth-required {
            padding: 40px 20px;
            text-align: center;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 15px;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #f8f9fa;
        }
        
        .chat-message {
            margin-bottom: 15px;
            max-width: 80%;
        }
        
        .chat-message.sent {
            margin-left: auto;
        }
        
        .chat-message.received {
            margin-right: auto;
        }
        
        .message-content {
            padding: 12px 15px;
            border-radius: 15px;
            position: relative;
        }
        
        .chat-message.sent .message-content {
            background: #667eea;
            color: white;
            border-bottom-right-radius: 5px;
        }
        
        .chat-message.received .message-content {
            background: white;
            color: #333;
            border-bottom-left-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .message-text {
            word-wrap: break-word;
            line-height: 1.4;
        }
        
        .message-file {
            margin-top: 8px;
            padding: 8px;
            background: rgba(255,255,255,0.1);
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .chat-message.received .message-file {
            background: #f1f3f4;
        }
        
        .message-file a {
            color: inherit;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .message-file a:hover {
            text-decoration: underline;
        }
        
        .message-time {
            font-size: 0.75rem;
            opacity: 0.8;
            margin-top: 5px;
            text-align: right;
        }
        
        .chat-input-container {
            border-top: 1px solid #e9ecef;
            padding: 15px;
            background: white;
            position: relative;
        }
        
        .chat-input-tools {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .chat-tool-btn {
            background: none;
            border: none;
            color: #667eea;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 5px;
            border-radius: 5px;
            transition: background 0.2s;
        }
        
        .chat-tool-btn:hover {
            background: #f8f9fa;
        }
        
        .chat-input {
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 12px 50px 12px 15px;
            font-size: 0.95rem;
            resize: none;
            outline: none;
            transition: border 0.2s;
        }
        
        .chat-input:focus {
            border-color: #667eea;
        }
        
        .chat-send-btn {
            position: absolute;
            right: 20px;
            bottom: 20px;
            background: #667eea;
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        
        .chat-send-btn:hover {
            background: #5a67d8;
        }
        
        .file-preview {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            margin-bottom: 10px;
            font-size: 0.9rem;
        }
        
        .file-preview button {
            background: none;
            border: none;
            color: #f44336;
            cursor: pointer;
            font-size: 1.2rem;
            margin-left: auto;
        }
        
        .emoji-picker {
            background: white;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 10px;
            max-height: 200px;
            overflow-y: auto;
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 5px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .emoji-picker span {
            cursor: pointer;
            padding: 5px;
            text-align: center;
            border-radius: 5px;
            transition: background 0.2s;
            font-size: 1.2rem;
        }
        
        .emoji-picker span:hover {
            background: #f8f9fa;
        }
        
        /* Start Chat Button */
        .start-chat-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .start-chat-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        /* Agent Status */
        .agent-status {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #ccc;
        }
        
        .status-dot.online {
            background: #4CAF50;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        /* Chat Loader */
        .chat-loader {
            text-align: center;
            padding: 20px;
            color: #667eea;
        }
        
        .chat-loader i {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .help-header h1 {
                font-size: 2rem;
            }
            
            .help-categories,
            .tutorial-grid,
            .resources-grid,
            .support-channels {
                grid-template-columns: 1fr;
            }
            
            .chat-widget {
                width: calc(100% - 40px);
                height: 70vh;
                bottom: 10px;
                right: 10px;
                left: 10px;
            }
            
            .emoji-picker {
                grid-template-columns: repeat(6, 1fr);
                max-width: 90vw;
            }
        }
    </style>
</head>
<body>
    <!-- Live Chat Widget -->
    <div class="chat-widget" id="chatWidget">
        <!-- Chat Header -->
        <div class="chat-header" onclick="toggleChat()">
            <div class="chat-header-info">
                <i class="fas fa-comments"></i>
                <span>Live Chat</span>
                <?php if ($unread_chat_count > 0): ?>
                    <span class="chat-badge"><?php echo $unread_chat_count; ?></span>
                <?php endif; ?>
            </div>
            <div class="chat-header-controls">
                <i class="fas fa-minus minimize-chat"></i>
                <i class="fas fa-times close-chat" onclick="closeChat()"></i>
            </div>
        </div>
        
        <!-- Chat Body -->
        <div class="chat-body" id="chatBody">
            <?php if (!is_logged_in()): ?>
                <div class="chat-auth-required">
                    <p>Please log in to use live chat</p>
                    <a href="<?php echo BASE_URL; ?>/auth/login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                </div>
            <?php else: ?>
                <!-- Chat Messages Container -->
                <div class="chat-messages" id="chatMessages">
                    <!-- Messages will be loaded here via AJAX -->
                </div>
                
                <!-- Chat Input -->
                <div class="chat-input-container">
                    <div class="chat-input-tools">
                        <button class="chat-tool-btn" title="Attach file" onclick="document.getElementById('chatFileInput').click()">
                            <i class="fas fa-paperclip"></i>
                        </button>
                        <button class="chat-tool-btn" title="Insert emoji" onclick="toggleEmojiPicker()">
                            <i class="fas fa-smile"></i>
                        </button>
                    </div>
                    <textarea 
                        id="chatInput" 
                        class="chat-input"
                        placeholder="Type your message here..." 
                        rows="3"
                        onkeypress="handleChatKeyPress(event)"
                    ></textarea>
                    <input type="file" id="chatFileInput" style="display: none;" onchange="uploadChatFile(this)">
                    <button id="sendChatBtn" class="chat-send-btn" onclick="sendChatMessage()">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <nav class="navbar" aria-label="Main Navigation">
        <div class="container">
            <a href="index.php" class="logo">
                <i class="fas fa-landmark" aria-hidden="true"></i> 
                <span>ArdhiYetu</span>
            </a>
            
            <button class="mobile-menu-btn" aria-label="Toggle menu" aria-expanded="false">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="nav-links">
                <a href="dashboard.php">
                    <i class="fas fa-tachometer-alt" aria-hidden="true"></i> Dashboard
                </a>
                <a href="user/my-lands.php">
                    <i class="fas fa-landmark" aria-hidden="true"></i> My Lands
                </a>
                <a href="user/transfer-land.php">
                    <i class="fas fa-exchange-alt" aria-hidden="true"></i> Transfer
                </a>
                <a href="help.php" class="active" aria-current="page">
                    <i class="fas fa-question-circle" aria-hidden="true"></i> Help
                </a>
                <a href="user/profile.php">
                    <i class="fas fa-user" aria-hidden="true"></i> Profile
                </a>
                <a href="logout.php" class="btn logout">
                    <i class="fas fa-sign-out-alt" aria-hidden="true"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <main class="help-center">
        <div class="container">
            <!-- Help Header -->
            <div class="help-header">
                <h1>How can we help you today?</h1>
                <p>Find answers to common questions or get in touch with our support team</p>
                
                <div class="help-search">
                    <input type="text" placeholder="Search for help articles, tutorials, or FAQs..." id="searchInput">
                    <button onclick="searchHelp()">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </div>

            <!-- Categories -->
            <div class="help-categories">
                <a href="#faq" class="category-card">
                    <div class="category-icon">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <h3>FAQs</h3>
                    <p>Find answers to frequently asked questions</p>
                </a>
                
                <a href="#tutorials" class="category-card">
                    <div class="category-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h3>Tutorials</h3>
                    <p>Step-by-step guides and tutorials</p>
                </a>
                
                <a href="#contact" class="category-card">
                    <div class="category-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3>Contact Support</h3>
                    <p>Get help from our support team</p>
                </a>
                
                <a href="#resources" class="category-card">
                    <div class="category-icon">
                        <i class="fas fa-file-download"></i>
                    </div>
                    <h3>Resources</h3>
                    <p>Download guides and documents</p>
                </a>
            </div>

            <!-- FAQ Section -->
            <section id="faq" class="faq-section">
                <h2><i class="fas fa-question-circle"></i> Frequently Asked Questions</h2>
                <p class="text-muted">Browse through our most common questions and answers</p>
                
                <div class="faq-list">
                    <!-- Registration & Account FAQs -->
                    <h3 class="mt-4">Registration & Account</h3>
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <span>How do I register for ArdhiYetu?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>To register for ArdhiYetu:</p>
                            <ol>
                                <li>Visit the registration page</li>
                                <li>Fill in your personal details (name, email, phone number)</li>
                                <li>Create a secure password</li>
                                <li>Verify your email address</li>
                                <li>Complete your profile with identification details</li>
                            </ol>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <span>I forgot my password. How can I reset it?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>To reset your password:</p>
                            <ol>
                                <li>Click on "Forgot Password" on the login page</li>
                                <li>Enter your registered email address</li>
                                <li>Check your email for a password reset link</li>
                                <li>Click the link and create a new password</li>
                                <li>Log in with your new password</li>
                            </ol>
                        </div>
                    </div>
                    
                    <!-- Land Registration FAQs -->
                    <h3 class="mt-4">Land Registration</h3>
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <span>How do I register a new land parcel?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>To register a new land parcel:</p>
                            <ol>
                                <li>Go to "My Lands" in your dashboard</li>
                                <li>Click "Register New Land"</li>
                                <li>Fill in the land details (parcel number, location, size, etc.)</li>
                                <li>Upload supporting documents (title deed, maps, etc.)</li>
                                <li>Submit for verification</li>
                                <li>Wait for confirmation from the land office</li>
                            </ol>
                            <p><strong>Note:</strong> You'll need to provide original documents for verification at the physical land office.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <span>What documents do I need to register land?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>The following documents are typically required:</p>
                            <ul>
                                <li>Original title deed or allotment letter</li>
                                <li>National ID or passport</li>
                                <li>PIN certificate</li>
                                <li>Recent passport photo</li>
                                <li>Survey maps (if available)</li>
                                <li>Land rates clearance certificate</li>
                                <li>Consent from family/co-owners (if applicable)</li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Transfer & Transactions FAQs -->
                    <h3 class="mt-4">Transfers & Transactions</h3>
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <span>How do I transfer land ownership?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>To transfer land ownership:</p>
                            <ol>
                                <li>Go to "Transfer Land" in your dashboard</li>
                                <li>Select the land parcel you want to transfer</li>
                                <li>Enter the recipient's details (ID number or email)</li>
                                <li>Set transfer terms and conditions</li>
                                <li>Upload required documents (sale agreement, consent forms)</li>
                                <li>Submit for review</li>
                                <li>Both parties will need to verify the transfer</li>
                                <li>Wait for approval from land office</li>
                            </ol>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <span>How long does a land transfer take?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>The transfer process typically takes:</p>
                            <ul>
                                <li><strong>Standard transfer:</strong> 14-30 working days</li>
                                <li><strong>Urgent transfer:</strong> 7-14 working days (additional fees apply)</li>
                                <li><strong>Inheritance transfer:</strong> 30-60 working days</li>
                            </ul>
                            <p>Processing time depends on document verification, approval stages, and whether all required documents are submitted correctly.</p>
                        </div>
                    </div>
                    
                    <!-- Technical FAQs -->
                    <h3 class="mt-4">Technical Support</h3>
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <span>I can't upload documents. What should I do?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>If you're having trouble uploading documents:</p>
                            <ol>
                                <li><strong>Check file format:</strong> We accept PDF, JPG, PNG files (max 10MB each)</li>
                                <li><strong>Check file size:</strong> Ensure files are under 10MB</li>
                                <li><strong>Clear browser cache:</strong> Clear your browser cache and try again</li>
                                <li><strong>Try different browser:</strong> Try using Chrome, Firefox, or Safari</li>
                                <li><strong>Check internet connection:</strong> Ensure you have stable internet</li>
                                <li><strong>Scan for viruses:</strong> Ensure files are not corrupted or infected</li>
                            </ol>
                            <p>If problems persist, contact our technical support team.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <span>How do I download my land certificate?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>To download your land certificate:</p>
                            <ol>
                                <li>Go to "My Lands" in your dashboard</li>
                                <li>Click on the land parcel you want</li>
                                <li>Click "Documents" tab</li>
                                <li>Find the certificate and click "Download"</li>
                                <li>Save the PDF file to your device</li>
                            </ol>
                            <p><strong>Note:</strong> Only verified and active lands will have downloadable certificates. If you don't see the download option, your land might still be under verification.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Tutorials Section -->
            <section id="tutorials" class="faq-section">
                <h2><i class="fas fa-graduation-cap"></i> Video Tutorials</h2>
                <p class="text-muted">Watch step-by-step guides on using ArdhiYetu</p>
                
                <div class="tutorial-grid">
                    <div class="tutorial-card">
                        <div class="tutorial-thumbnail">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="tutorial-content">
                            <h3>Getting Started</h3>
                            <p>Learn how to create your account and set up your profile</p>
                            <button class="btn btn-primary mt-2" onclick="playTutorial('getting-started')">
                                <i class="fas fa-play"></i> Watch Tutorial
                            </button>
                        </div>
                    </div>
                    
                    <div class="tutorial-card">
                        <div class="tutorial-thumbnail">
                            <i class="fas fa-landmark"></i>
                        </div>
                        <div class="tutorial-content">
                            <h3>Registering Land</h3>
                            <p>Step-by-step guide to registering your land parcels</p>
                            <button class="btn btn-primary mt-2" onclick="playTutorial('registering-land')">
                                <i class="fas fa-play"></i> Watch Tutorial
                            </button>
                        </div>
                    </div>
                    
                    <div class="tutorial-card">
                        <div class="tutorial-thumbnail">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div class="tutorial-content">
                            <h3>Transferring Ownership</h3>
                            <p>How to initiate and complete land transfers</p>
                            <button class="btn btn-primary mt-2" onclick="playTutorial('transferring-ownership')">
                                <i class="fas fa-play"></i> Watch Tutorial
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Resources Section -->
            <section id="resources" class="faq-section">
                <h2><i class="fas fa-file-download"></i> Helpful Resources</h2>
                <p class="text-muted">Download guides, forms, and documentation</p>
                
                <div class="resources-grid">
                    <div class="resource-card">
                        <div class="resource-icon">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <div>
                            <h4>User Manual</h4>
                            <p>Complete guide to using ArdhiYetu</p>
                            <a href="#" class="btn btn-sm btn-outline-primary">Download PDF</a>
                        </div>
                    </div>
                    
                    <div class="resource-card">
                        <div class="resource-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div>
                            <h4>Forms & Templates</h4>
                            <p>Official forms for land transactions</p>
                            <a href="#" class="btn btn-sm btn-outline-primary">Download</a>
                        </div>
                    </div>
                    
                    <div class="resource-card">
                        <div class="resource-icon">
                            <i class="fas fa-balance-scale"></i>
                        </div>
                        <div>
                            <h4>Legal Guidelines</h4>
                            <p>Understanding land laws and regulations</p>
                            <a href="#" class="btn btn-sm btn-outline-primary">View</a>
                        </div>
                    </div>
                    
                    <div class="resource-card">
                        <div class="resource-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div>
                            <h4>Security Tips</h4>
                            <p>Protecting your account and land records</p>
                            <a href="#" class="btn btn-sm btn-outline-primary">Read</a>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Contact Section -->
            <section id="contact" class="contact-section">
                <h2><i class="fas fa-headset"></i> Contact Support</h2>
                <p class="text-muted">Can't find what you're looking for? Contact our support team</p>
                
                <?php if ($form_success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> Thank you! Your message has been sent successfully. We'll get back to you within 24 hours.
                    </div>
                <?php endif; ?>
                
                <?php if ($form_error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $form_error; ?>
                    </div>
                <?php endif; ?>
                
                <div class="contact-form">
                    <form method="POST" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Full Name *</label>
                                <input type="text" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($form_data['name'] ?: ($user_data['name'] ?? '')); ?>" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($form_data['email'] ?: ($user_data['email'] ?? '')); ?>" 
                                       required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="subject">Subject *</label>
                                <input type="text" id="subject" name="subject" 
                                       value="<?php echo htmlspecialchars($form_data['subject']); ?>" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="category">Issue Category *</label>
                                <select id="category" name="category" required>
                                    <option value="general" <?php echo $form_data['category'] == 'general' ? 'selected' : ''; ?>>General Inquiry</option>
                                    <option value="technical" <?php echo $form_data['category'] == 'technical' ? 'selected' : ''; ?>>Technical Support</option>
                                    <option value="registration" <?php echo $form_data['category'] == 'registration' ? 'selected' : ''; ?>>Land Registration</option>
                                    <option value="transfer" <?php echo $form_data['category'] == 'transfer' ? 'selected' : ''; ?>>Ownership Transfer</option>
                                    <option value="document" <?php echo $form_data['category'] == 'document' ? 'selected' : ''; ?>>Document Issues</option>
                                    <option value="billing" <?php echo $form_data['category'] == 'billing' ? 'selected' : ''; ?>>Billing & Payments</option>
                                    <option value="security" <?php echo $form_data['category'] == 'security' ? 'selected' : ''; ?>>Security Concern</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Message *</label>
                            <textarea id="message" name="message" required><?php echo htmlspecialchars($form_data['message']); ?></textarea>
                            <small class="text-muted">Please provide as much detail as possible about your issue</small>
                        </div>
                        
                        <button type="submit" name="contact_submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                    </form>
                </div>
                
                <!-- Support Channels -->
                <div class="support-channels">
                    <div class="support-channel">
                        <div class="channel-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <h3>Phone Support</h3>
                        <p><strong>0700 000 000</strong></p>
                        <p>Mon-Fri: 8:00 AM - 5:00 PM</p>
                        <p>Sat: 9:00 AM - 1:00 PM</p>
                    </div>
                    
                    <div class="support-channel">
                        <div class="channel-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h3>Email Support</h3>
                        <p><strong>support@ardhiyetu.go.ke</strong></p>
                        <p>Response time: 24 hours</p>
                        <p>For non-urgent inquiries</p>
                    </div>
                    
                    <div class="support-channel">
                        <div class="channel-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <h3>Visit Office</h3>
                        <p><strong>Ardhi House, 1st Ngong Ave</strong></p>
                        <p>Nairobi, Kenya</p>
                        <p>Office Hours: 8:00 AM - 5:00 PM</p>
                    </div>
                    
                    <div class="support-channel">
                        <div class="channel-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <h3>Live Chat</h3>
                        <p><strong>Available Online</strong></p>
                        <p>Chat with our support agents</p>
                        <button class="start-chat-btn" onclick="openChatWidget()">
                            <i class="fas fa-comments"></i> Start Live Chat
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><i class="fas fa-landmark" aria-hidden="true"></i> ArdhiYetu</h3>
                    <p>Digital Land Administration System</p>
                </div>
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="user/my-lands.php">My Lands</a></li>
                        <li><a href="help.php">Help Center</a></li>
                        <li><a href="privacy.php">Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Contact Support</h3>
                    <p><i class="fas fa-envelope" aria-hidden="true"></i> support@ardhiyetu.go.ke</p>
                    <p><i class="fas fa-phone" aria-hidden="true"></i> 0700 000 000</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> ArdhiYetu Land Management System</p>
            </div>
        </div>
    </footer>

    <script>
        // Chat functionality
        let chatInterval;
        let currentConversationId = null;
        let isChatMinimized = false;
        let isChatOpen = false;
        let currentFile = null;

        function toggleChat() {
            const chatWidget = document.getElementById('chatWidget');
            const chatBody = document.getElementById('chatBody');
            
            if (!chatWidget.classList.contains('chat-open')) {
                // Open chat
                chatWidget.classList.add('chat-open');
                chatWidget.classList.remove('chat-minimized');
                isChatMinimized = false;
                isChatOpen = true;
                
                // Load chat messages if logged in
                if (<?php echo is_logged_in() ? 'true' : 'false'; ?>) {
                    loadChatConversations();
                    startChatPolling();
                }
            } else if (chatWidget.classList.contains('chat-minimized')) {
                // Restore from minimized
                chatWidget.classList.remove('chat-minimized');
                isChatMinimized = false;
            } else {
                // Minimize
                chatWidget.classList.add('chat-minimized');
                isChatMinimized = true;
            }
        }

        function openChatWidget() {
            const chatWidget = document.getElementById('chatWidget');
            
            if (!chatWidget.classList.contains('chat-open')) {
                chatWidget.classList.add('chat-open');
                chatWidget.classList.remove('chat-minimized');
                isChatMinimized = false;
                isChatOpen = true;
                
                // Load chat if logged in
                if (<?php echo is_logged_in() ? 'true' : 'false'; ?>) {
                    loadChatConversations();
                    startChatPolling();
                }
            }
        }

        function closeChat() {
            const chatWidget = document.getElementById('chatWidget');
            chatWidget.classList.remove('chat-open', 'chat-minimized');
            isChatOpen = false;
            stopChatPolling();
        }

        function startChatPolling() {
            // Clear any existing interval
            if (chatInterval) {
                clearInterval(chatInterval);
            }
            
            // Poll for new messages every 3 seconds
            chatInterval = setInterval(() => {
                if (currentConversationId) {
                    loadChatMessages(currentConversationId);
                } else {
                    loadChatConversations();
                }
            }, 3000);
        }

        function stopChatPolling() {
            if (chatInterval) {
                clearInterval(chatInterval);
                chatInterval = null;
            }
        }

        async function loadChatConversations() {
            if (!<?php echo is_logged_in() ? 'true' : 'false'; ?>) return;
            
            try {
                const response = await fetch('ajax/get-chat-conversations.php');
                const data = await response.json();
                if (data.success && data.conversations && data.conversations.length > 0) {
                    // For simplicity, just load the first conversation
                    if (!currentConversationId) {
                        currentConversationId = data.conversations[0].conversation_id;
                        loadChatMessages(currentConversationId);
                    }
                } else {
                    // Show empty state
                    document.getElementById('chatMessages').innerHTML = `
                        <div class="chat-loader">
                            <p>No active conversations</p>
                            <p>Start a new chat by typing a message below</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading conversations:', error);
            }
        }

        async function loadChatMessages(conversationId) {
            try {
                const response = await fetch(`ajax/get-chat-messages.php?conversation_id=${conversationId}`);
                const data = await response.json();
                if (data.success) {
                    displayChatMessages(data.messages);
                    currentConversationId = conversationId;
                    markMessagesAsRead(conversationId);
                }
            } catch (error) {
                console.error('Error loading messages:', error);
            }
        }

        function displayChatMessages(messages) {
            const container = document.getElementById('chatMessages');
            if (!container) return;
            
            container.innerHTML = '';
            
            if (messages.length === 0) {
                container.innerHTML = `
                    <div class="chat-loader">
                        <p>Start the conversation by sending a message</p>
                    </div>
                `;
                return;
            }
            
            messages.forEach(message => {
                const messageDiv = document.createElement('div');
                messageDiv.className = `chat-message ${message.sender_id == <?php echo $user_id ?? 0; ?> ? 'sent' : 'received'}`;
                
                const time = new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                
                messageDiv.innerHTML = `
                    <div class="message-content">
                        <div class="message-text">${escapeHtml(message.message)}</div>
                        ${message.file_url ? `
                            <div class="message-file">
                                <i class="fas fa-file"></i>
                                <a href="${message.file_url}" target="_blank" download="${message.file_name}">
                                    ${message.file_name} (${formatFileSize(message.file_size)})
                                </a>
                            </div>
                        ` : ''}
                        <div class="message-time">${time}</div>
                    </div>
                `;
                
                container.appendChild(messageDiv);
            });
            
            // Scroll to bottom
            container.scrollTop = container.scrollHeight;
        }

        async function sendChatMessage() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            
            if (!message && !currentFile) return;
            
            if (!currentConversationId) {
                // Start new conversation
                try {
                    const response = await fetch('ajax/start-chat-conversation.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ subject: 'New Chat' })
                    });
                    const data = await response.json();
                    if (data.success) {
                        currentConversationId = data.conversation_id;
                        await sendMessageToConversation(message);
                    }
                } catch (error) {
                    console.error('Error starting conversation:', error);
                }
            } else {
                await sendMessageToConversation(message);
            }
            
            input.value = '';
        }

        async function sendMessageToConversation(message) {
            const formData = new FormData();
            formData.append('conversation_id', currentConversationId);
            formData.append('message', message);
            
            if (currentFile) {
                formData.append('file', currentFile);
            }
            
            try {
                const response = await fetch('ajax/send-chat-message.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    // Clear current file
                    currentFile = null;
                    document.getElementById('chatFileInput').value = '';
                    const preview = document.querySelector('.file-preview');
                    if (preview) preview.remove();
                    // Reload messages
                    loadChatMessages(currentConversationId);
                }
            } catch (error) {
                console.error('Error sending message:', error);
            }
        }

        function handleChatKeyPress(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendChatMessage();
            }
        }

        function uploadChatFile(input) {
            const file = input.files[0];
            if (!file) return;
            
            // Check file size (max 10MB)
            if (file.size > 10 * 1024 * 1024) {
                alert('File size must be less than 10MB');
                return;
            }
            
            // Check file type
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            if (!allowedTypes.includes(file.type)) {
                alert('Please upload only images, PDFs, or Word documents');
                return;
            }
            
            currentFile = file;
            
            // Show file preview
            const inputContainer = document.getElementById('chatInput').parentNode;
            const preview = document.createElement('div');
            preview.className = 'file-preview';
            preview.innerHTML = `
                <i class="fas fa-file"></i>
                <span>${file.name}</span>
                <button onclick="removeFilePreview(this)">×</button>
            `;
            
            const existingPreview = inputContainer.querySelector('.file-preview');
            if (existingPreview) {
                existingPreview.remove();
            }
            
            inputContainer.insertBefore(preview, document.getElementById('chatInput'));
        }

        function removeFilePreview(button) {
            button.parentNode.remove();
            currentFile = null;
            document.getElementById('chatFileInput').value = '';
        }

        function toggleEmojiPicker() {
            // Simple emoji picker
            const emojis = ['😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇', '🙂', '🙃', '😉', '😌', '😍', '🥰', '😘', '😗', '😙', '😚', '😋', '😛', '😝', '😜', '🤪', '🤨', '🧐', '🤓', '😎', '🤩', '🥳', '😏', '😒', '😞', '😔', '😟', '😕', '🙁', '☹️', '😣', '😖', '😫', '😩', '🥺', '😢', '😭', '😤', '😠', '😡', '🤬', '🤯', '😳', '🥵', '🥶', '😱', '😨', '😰', '😥', '😓', '🤗', '🤔', '🤭', '🤫', '🤥', '😶', '😐', '😑', '😬', '🙄', '😯', '😦', '😧', '😮', '😲', '🥱', '😴', '🤤', '😪', '😵', '🤐', '🥴', '🤢', '🤮', '🤧', '😷', '🤒', '🤕', '🤑', '🤠', '😈', '👿', '👹', '👺', '🤡', '💩', '👻', '💀', '☠️', '👽', '👾', '🤖', '🎃', '😺', '😸', '😹', '😻', '😼', '😽', '🙀', '😿', '😾'];
            
            const picker = document.createElement('div');
            picker.className = 'emoji-picker';
            picker.innerHTML = emojis.map(emoji => 
                `<span onclick="insertEmoji('${emoji}')">${emoji}</span>`
            ).join('');
            
            const existingPicker = document.querySelector('.emoji-picker');
            if (existingPicker) {
                existingPicker.remove();
            } else {
                document.body.appendChild(picker);
                
                // Position picker
                const input = document.getElementById('chatInput');
                const rect = input.getBoundingClientRect();
                picker.style.position = 'fixed';
                picker.style.top = `${rect.top - 200}px`;
                picker.style.left = `${rect.left}px`;
                picker.style.zIndex = '1000';
                
                // Close picker when clicking outside
                setTimeout(() => {
                    document.addEventListener('click', function closePicker(e) {
                        if (!picker.contains(e.target) && e.target.id !== 'chatInput') {
                            picker.remove();
                            document.removeEventListener('click', closePicker);
                        }
                    });
                }, 0);
            }
        }

        function insertEmoji(emoji) {
            const input = document.getElementById('chatInput');
            input.value += emoji;
            input.focus();
            
            // Close picker
            document.querySelector('.emoji-picker')?.remove();
        }

        async function markMessagesAsRead(conversationId) {
            try {
                await fetch(`ajax/mark-messages-read.php?conversation_id=${conversationId}`, {
                    method: 'POST'
                });
            } catch (error) {
                console.error('Error marking messages as read:', error);
            }
        }

        // FAQ Toggle Functionality
        function toggleFAQ(element) {
            const answer = element.nextElementSibling;
            const icon = element.querySelector('i');
            
            answer.classList.toggle('show');
            icon.classList.toggle('fa-chevron-down');
            icon.classList.toggle('fa-chevron-up');
        }
        
        // Search Functionality
        function searchHelp() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            if (!searchTerm.trim()) return;
            
            // Search in FAQ questions
            const faqQuestions = document.querySelectorAll('.faq-question span');
            let found = false;
            
            faqQuestions.forEach(question => {
                const questionText = question.textContent.toLowerCase();
                const faqItem = question.closest('.faq-item');
                
                if (questionText.includes(searchTerm)) {
                    faqItem.style.display = 'block';
                    // Open the FAQ answer
                    const answer = faqItem.querySelector('.faq-answer');
                    const icon = faqItem.querySelector('.faq-question i');
                    answer.classList.add('show');
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-up');
                    found = true;
                } else {
                    faqItem.style.display = 'none';
                }
            });
            
            // Show message if no results found
            if (!found) {
                alert('No results found for "' + searchTerm + '". Try different keywords or contact support.');
            }
        }
        
        // Enter key support for search
        document.getElementById('searchInput')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchHelp();
            }
        });
        
        // Tutorial Play Functions
        function playTutorial(tutorialId) {
            alert('Tutorial video would play here. In a real implementation, this would open a video player.');
            // window.open('tutorials/' + tutorialId + '.mp4', '_blank');
        }
        
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            });
        });
        
        // Mobile menu toggle
        document.querySelector('.mobile-menu-btn')?.addEventListener('click', function() {
            const navLinks = document.querySelector('.nav-links');
            navLinks.style.display = navLinks.style.display === 'flex' ? 'none' : 'flex';
            this.setAttribute('aria-expanded', navLinks.style.display === 'flex');
        });
        
        // Form character counter for message
        const messageTextarea = document.getElementById('message');
        if (messageTextarea) {
            const counter = document.createElement('div');
            counter.className = 'text-muted text-right mt-1';
            counter.innerHTML = '<small><span id="charCount">0</span>/2000 characters</small>';
            messageTextarea.parentNode.appendChild(counter);
            
            messageTextarea.addEventListener('input', function() {
                const charCount = this.value.length;
                document.getElementById('charCount').textContent = charCount;
                
                if (charCount > 2000) {
                    this.value = this.value.substring(0, 2000);
                    document.getElementById('charCount').textContent = 2000;
                }
            });
        }

        // Utility functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatFileSize(bytes) {
            if (!bytes) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    </script>
</body>
</html>