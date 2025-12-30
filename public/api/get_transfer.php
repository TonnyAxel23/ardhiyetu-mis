<?php
// ardhiyetu/public/api/get_transfer.php

// IMPORTANT: Check the path to init.php based on your structure
// From: /public/api/get_transfer.php
// To:   /includes/init.php (go up one level, then into includes)
require_once '../includes/init.php';  // Adjust if needed
require_admin();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode([
        'error' => 'Invalid transfer ID', 
        'received_id' => $_GET['id'] ?? 'null',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

$transfer_id = (int)$_GET['id'];

// Query to get transfer details
$sql = "SELECT t.*, 
        u1.name as from_name, u1.email as from_email,
        u2.name as to_name, u2.email as to_email,
        l.parcel_no, l.location, l.record_id,
        a.name as reviewer_name
        FROM ownership_transfers t
        JOIN users u1 ON t.from_user_id = u1.user_id
        JOIN users u2 ON t.to_user_id = u2.user_id
        JOIN land_records l ON t.record_id = l.record_id
        LEFT JOIN users a ON t.reviewed_by = a.user_id
        WHERE t.transfer_id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $transfer_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    echo json_encode([
        'error' => 'Transfer request not found', 
        'transfer_id' => $transfer_id,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

$transfer = mysqli_fetch_assoc($result);

// Decode JSON fields if they exist
if (isset($transfer['supporting_docs']) && $transfer['supporting_docs']) {
    $transfer['supporting_docs'] = json_decode($transfer['supporting_docs'], true);
} else {
    $transfer['supporting_docs'] = [];
}

// Add debug info
$transfer['api_debug'] = [
    'called_at' => date('Y-m-d H:i:s'),
    'transfer_id' => $transfer_id,
    'api_file' => basename(__FILE__),
    'api_path' => __DIR__
];

// Return as JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');  // For localhost testing
echo json_encode($transfer);
?>