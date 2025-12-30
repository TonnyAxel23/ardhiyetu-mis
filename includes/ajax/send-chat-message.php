<?php
require_once '../../includes/init.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$conversation_id = intval($_POST['conversation_id'] ?? 0);
$message = trim($_POST['message'] ?? '');

// Check if chat tables exist
$check_table = mysqli_query($conn, "SHOW TABLES LIKE 'chat_conversations'");
if (mysqli_num_rows($check_table) == 0) {
    echo json_encode(['success' => false, 'message' => 'Chat system not available']);
    exit();
}

// Validate input
if ($conversation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid conversation']);
    exit();
}

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
    exit();
}

// Check if user has access to this conversation
$check_stmt = mysqli_prepare($conn, 
    "SELECT conversation_id FROM chat_conversations WHERE conversation_id = ? AND user_id = ?");
mysqli_stmt_bind_param($check_stmt, 'ii', $conversation_id, $user_id);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($check_result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Handle file upload
$file_url = null;
$file_name = null;
$file_size = null;

if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (in_array($_FILES['file']['type'], $allowed_types) && $_FILES['file']['size'] <= $max_size) {
        $upload_dir = '../../uploads/chat/' . date('Y/m/d') . '/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = basename($_FILES['file']['name']);
        $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
        $new_file_name = uniqid() . '.' . $file_ext;
        $file_path = $upload_dir . $new_file_name;
        
        if (move_uploaded_file($_FILES['file']['tmp_name'], $file_path)) {
            $file_url = str_replace('../../', BASE_URL . '/', $file_path);
            $file_size = $_FILES['file']['size'];
        }
    }
}

// Insert message
$stmt = mysqli_prepare($conn, 
    "INSERT INTO chat_messages (conversation_id, sender_id, message_type, message, file_url, file_name, file_size) 
     VALUES (?, ?, 'text', ?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, 'iisssi', $conversation_id, $user_id, $message, $file_url, $file_name, $file_size);
mysqli_stmt_execute($stmt);

// Update conversation last message time
$update_stmt = mysqli_prepare($conn, 
    "UPDATE chat_conversations SET last_message_at = NOW() WHERE conversation_id = ?");
mysqli_stmt_bind_param($update_stmt, 'i', $conversation_id);
mysqli_stmt_execute($update_stmt);

echo json_encode(['success' => true, 'message_id' => mysqli_insert_id($conn)]);
?>