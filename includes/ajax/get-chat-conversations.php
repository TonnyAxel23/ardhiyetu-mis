<?php
require_once '../../includes/init.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if chat tables exist
$check_table = mysqli_query($conn, "SHOW TABLES LIKE 'chat_conversations'");
if (mysqli_num_rows($check_table) == 0) {
    echo json_encode(['success' => true, 'conversations' => []]);
    exit();
}

// Get user's conversations
$stmt = mysqli_prepare($conn, 
    "SELECT cc.*, 
            (SELECT message FROM chat_messages WHERE conversation_id = cc.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message,
            (SELECT COUNT(*) FROM chat_messages cm2 WHERE cm2.conversation_id = cc.conversation_id AND cm2.sender_id != ? AND cm2.is_read = FALSE) as unread_count
     FROM chat_conversations cc
     WHERE cc.user_id = ?
     ORDER BY cc.last_message_at DESC
     LIMIT 10");
mysqli_stmt_bind_param($stmt, 'ii', $user_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$conversations = [];
while ($row = mysqli_fetch_assoc($result)) {
    $conversations[] = $row;
}

echo json_encode(['success' => true, 'conversations' => $conversations]);
?>