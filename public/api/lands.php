<?php
require_once '../includes/init.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$response = ['success' => false, 'message' => '', 'data' => null];

// Verify API token
$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : '';

if (!verifyToken($token)) {
    $response['message'] = 'Invalid or expired token';
    http_response_code(401);
    echo json_encode($response);
    exit();
}

// Get authenticated user
$user_id = $_SESSION['user_id'] ?? 0;

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get land records
        $record_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $owner_id = isset($_GET['owner_id']) ? intval($_GET['owner_id']) : 0;
        $status = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
        $search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
        
        // Pagination
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
        $offset = ($page - 1) * $limit;
        
        if ($record_id > 0) {
            // Get single land record
            $sql = "SELECT l.*, u.name as owner_name 
                    FROM land_records l
                    JOIN users u ON l.owner_id = u.user_id
                    WHERE l.record_id = '$record_id'";
            
            // Check permissions
            if (!is_admin() && !is_officer()) {
                $sql .= " AND l.owner_id = '$user_id'";
            }
            
            $result = mysqli_query($conn, $sql);
            
            if (mysqli_num_rows($result) == 1) {
                $land = mysqli_fetch_assoc($result);
                $response['success'] = true;
                $response['data'] = $land;
            } else {
                $response['message'] = 'Land record not found or access denied';
                http_response_code(404);
            }
        } else {
            // Get multiple land records
            $where = [];
            
            // Filter by owner (admins/officers can view all)
            if ($owner_id > 0) {
                if (is_admin() || is_officer()) {
                    $where[] = "l.owner_id = '$owner_id'";
                } elseif ($owner_id == $user_id) {
                    $where[] = "l.owner_id = '$user_id'";
                } else {
                    $response['message'] = 'Access denied';
                    http_response_code(403);
                    echo json_encode($response);
                    exit();
                }
            } elseif (!is_admin() && !is_officer()) {
                // Regular users can only see their own lands
                $where[] = "l.owner_id = '$user_id'";
            }
            
            if ($status) {
                $where[] = "l.status = '$status'";
            }
            
            if ($search) {
                $where[] = "(l.parcel_no LIKE '%$search%' OR l.location LIKE '%$search%' OR u.name LIKE '%$search%')";
            }
            
            $where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            
            // Get total count
            $count_sql = "SELECT COUNT(*) as total 
                         FROM land_records l
                         LEFT JOIN users u ON l.owner_id = u.user_id
                         $where_clause";
            $count_result = mysqli_query($conn, $count_sql);
            $total = mysqli_fetch_assoc($count_result)['total'];
            $total_pages = ceil($total / $limit);
            
            // Get data
            $sql = "SELECT l.*, u.name as owner_name 
                    FROM land_records l
                    LEFT JOIN users u ON l.owner_id = u.user_id
                    $where_clause
                    ORDER BY l.registered_at DESC 
                    LIMIT $limit OFFSET $offset";
            $result = mysqli_query($conn, $sql);
            
            $lands = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $lands[] = $row;
            }
            
            $response['success'] = true;
            $response['data'] = [
                'lands' => $lands,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => $total_pages
                ]
            ];
        }
        break;
        
    case 'POST':
        // Create new land record
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            $data = $_POST;
        }
        
        $required = ['parcel_no', 'location', 'size'];
        $valid = true;
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $response['message'] = "Field '$field' is required";
                $valid = false;
                break;
            }
        }
        
        if ($valid) {
            $parcel_no = sanitize_input($data['parcel_no']);
            $location = sanitize_input($data['location']);
            $size = floatval($data['size']);
            $coordinates = isset($data['coordinates']) ? sanitize_input($data['coordinates']) : '';
            $description = isset($data['description']) ? sanitize_input($data['description']) : '';
            
            // Determine owner
            $owner_id = $user_id;
            if (isset($data['owner_id']) && (is_admin() || is_officer())) {
                $owner_id = intval($data['owner_id']);
            }
            
            // Check if parcel number exists
            $check_sql = "SELECT record_id FROM land_records WHERE parcel_no = '$parcel_no'";
            $check_result = mysqli_query($conn, $check_sql);
            
            if (mysqli_num_rows($check_result) > 0) {
                $response['message'] = 'Parcel number already exists';
            } elseif ($size <= 0) {
                $response['message'] = 'Size must be greater than 0';
            } else {
                $insert_sql = "INSERT INTO land_records (owner_id, parcel_no, location, size, coordinates, description, status) 
                              VALUES ('$owner_id', '$parcel_no', '$location', '$size', '$coordinates', '$description', 'pending')";
                
                if (mysqli_query($conn, $insert_sql)) {
                    $record_id = mysqli_insert_id($conn);
                    
                    // Log activity
                    log_activity($user_id, 'api_land_create', "Created land record via API: $parcel_no");
                    
                    $response['success'] = true;
                    $response['message'] = 'Land record created successfully';
                    $response['data'] = ['record_id' => $record_id];
                } else {
                    $response['message'] = 'Failed to create land record: ' . mysqli_error($conn);
                }
            }
        }
        break;
        
    case 'PUT':
        // Update land record
        $record_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($record_id <= 0) {
            $response['message'] = 'Land record ID required';
            break;
        }
        
        // Check ownership/permissions
        $check_sql = "SELECT owner_id FROM land_records WHERE record_id = '$record_id'";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) != 1) {
            $response['message'] = 'Land record not found';
            break;
        }
        
        $land = mysqli_fetch_assoc($check_result);
        
        if ($land['owner_id'] != $user_id && !is_admin() && !is_officer()) {
            $response['message'] = 'Access denied';
            http_response_code(403);
            break;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            parse_str(file_get_contents('php://input'), $data);
        }
        
        $updates = [];
        
        if (isset($data['location'])) {
            $location = sanitize_input($data['location']);
            $updates[] = "location = '$location'";
        }
        
        if (isset($data['size']) && floatval($data['size']) > 0) {
            $size = floatval($data['size']);
            $updates[] = "size = '$size'";
        }
        
        if (isset($data['coordinates'])) {
            $coordinates = sanitize_input($data['coordinates']);
            $updates[] = "coordinates = '$coordinates'";
        }
        
        if (isset($data['description'])) {
            $description = sanitize_input($data['description']);
            $updates[] = "description = '$description'";
        }
        
        if (isset($data['status']) && (is_admin() || is_officer())) {
            $status = sanitize_input($data['status']);
            $updates[] = "status = '$status'";
        }
        
        if (!empty($updates)) {
            $updates[] = "updated_at = NOW()";
            $update_sql = "UPDATE land_records SET " . implode(', ', $updates) . " WHERE record_id = '$record_id'";
            
            if (mysqli_query($conn, $update_sql)) {
                log_activity($user_id, 'api_land_update', "Updated land record via API: $record_id");
                
                $response['success'] = true;
                $response['message'] = 'Land record updated successfully';
            } else {
                $response['message'] = 'Failed to update land record: ' . mysqli_error($conn);
            }
        } else {
            $response['message'] = 'No fields to update';
        }
        break;
        
    case 'DELETE':
        // Delete land record
        $record_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($record_id <= 0) {
            $response['message'] = 'Land record ID required';
            break;
        }
        
        // Check ownership/permissions
        $check_sql = "SELECT owner_id, status FROM land_records WHERE record_id = '$record_id'";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) != 1) {
            $response['message'] = 'Land record not found';
            break;
        }
        
        $land = mysqli_fetch_assoc($check_result);
        
        if ($land['owner_id'] != $user_id && !is_admin()) {
            $response['message'] = 'Access denied';
            http_response_code(403);
            break;
        }
        
        // Check for pending transfers
        $pending_sql = "SELECT transfer_id FROM ownership_transfers WHERE record_id = '$record_id' AND status IN ('submitted', 'under_review')";
        $pending_result = mysqli_query($conn, $pending_sql);
        
        if (mysqli_num_rows($pending_result) > 0) {
            $response['message'] = 'Cannot delete land with pending transfers';
        } else {
            $delete_sql = "DELETE FROM land_records WHERE record_id = '$record_id'";
            
            if (mysqli_query($conn, $delete_sql)) {
                log_activity($user_id, 'api_land_delete', "Deleted land record via API: $record_id");
                
                $response['success'] = true;
                $response['message'] = 'Land record deleted successfully';
            } else {
                $response['message'] = 'Failed to delete land record: ' . mysqli_error($conn);
            }
        }
        break;
        
    default:
        $response['message'] = 'Method not allowed';
        http_response_code(405);
}

// Helper function to verify token
function verifyToken($token) {
    if (empty($token)) {
        return false;
    }
    
    // Start session if not started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['api_token']) && 
           $_SESSION['api_token'] == $token && 
           isset($_SESSION['api_token_expires']) && 
           strtotime($_SESSION['api_token_expires']) > time();
}

echo json_encode($response);
?>