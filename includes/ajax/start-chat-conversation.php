<?php
require_once '../../includes/init.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$subject = $data['subject'] ?? 'New Chat';

// Check if chat tables exist
$check_table = mysqli_query($conn, "SHOW TABLES LIKE 'chat_conversations'");
if (mysqli_num_rows($check_table) == 0) {
    echo json_encode(['success' => false, 'message' => 'Chat system not available']);
    exit();
}

// Create conversation
$stmt = mysqli_prepare($conn, 
    "INSERT INTO chat_conversations (user_id, subject, status) 
     VALUES (?, ?, 'pending')");
mysqli_stmt_bind_param($stmt, 'is', $user_id, $subject);
mysqli_stmt_execute($stmt);

$conversation_id = mysqli_insert_id($conn);

// Add welcome message
$welcome_stmt = mysqli_prepare($conn, 
    "INSERT INTO chat_messages (conversation_id, sender_id, message_type, message) 
     VALUES (?, 0, 'system', 'Chat started. You are now connected with support. An agent will be with you shortly.')");
mysqli_stmt_bind_param($welcome_stmt, 'i', $conversation_id);
mysqli_stmt_execute($welcome_stmt);

echo json_encode(['success' => true, 'conversation_id' => $conversation_id]);
?>