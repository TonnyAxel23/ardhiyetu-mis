<?php
require_once '../init.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit();
}

$user_id = $_SESSION['user_id'];
$record_id = intval($_POST['record_id']);
$latitude = floatval($_POST['latitude']);
$longitude = floatval($_POST['longitude']);

// Verify user owns this land
$check_sql = "SELECT record_id FROM land_records 
              WHERE record_id = '$record_id' 
              AND owner_id = '$user_id'";
$check_result = mysqli_query($conn, $check_sql);

if (mysqli_num_rows($check_result) === 1) {
    $update_sql = "UPDATE land_records 
                   SET latitude = '$latitude', 
                       longitude = '$longitude',
                       updated_at = NOW()
                   WHERE record_id = '$record_id'";
    
    if (mysqli_query($conn, $update_sql)) {
        log_activity($user_id, 'update_coordinates', "Updated coordinates for land ID: $record_id");
        
        echo json_encode([
            'success' => true,
            'message' => 'Coordinates updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . mysqli_error($conn)
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Land record not found or access denied'
    ]);
}
?>