<?php
require_once '../../includes/init.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$conversation_id = intval($_GET['conversation_id'] ?? 0);

if ($conversation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid conversation']);
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

// Get messages
$stmt = mysqli_prepare($conn, 
    "SELECT cm.*, u.name as sender_name
     FROM chat_messages cm
     JOIN users u ON cm.sender_id = u.user_id
     WHERE cm.conversation_id = ?
     ORDER BY cm.created_at ASC
     LIMIT 100");
mysqli_stmt_bind_param($stmt, 'i', $conversation_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$messages = [];
while ($row = mysqli_fetch_assoc($result)) {
    $messages[] = $row;
}

echo json_encode(['success' => true, 'messages' => $messages]);
?>