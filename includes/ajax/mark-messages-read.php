<?php
require_once '../../includes/init.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$conversation_id = intval($_GET['conversation_id'] ?? 0);

// Check if chat tables exist
$check_table = mysqli_query($conn, "SHOW TABLES LIKE 'chat_messages'");
if (mysqli_num_rows($check_table) == 0) {
    echo json_encode(['success' => true]);
    exit();
}

$stmt = mysqli_prepare($conn, 
    "UPDATE chat_messages cm
     JOIN chat_conversations cc ON cm.conversation_id = cc.conversation_id
     SET cm.is_read = TRUE, cm.read_at = NOW()
     WHERE cc.conversation_id = ? 
     AND cc.user_id = ?
     AND cm.sender_id != ?
     AND cm.is_read = FALSE");
mysqli_stmt_bind_param($stmt, 'iii', $conversation_id, $user_id, $user_id);
mysqli_stmt_execute($stmt);

echo json_encode(['success' => true]);
?>