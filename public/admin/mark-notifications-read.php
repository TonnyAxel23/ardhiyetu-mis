<?php
require_once '../../includes/init.php';
require_admin();

$sql = "UPDATE notifications SET is_read = 1, read_at = NOW() 
        WHERE user_id = ? AND is_read = 0";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true, 'message' => 'Notifications marked as read']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to mark notifications as read']);
}