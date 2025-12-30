<?php
// API Authentication Endpoint
require_once '../includes/init.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$response = ['success' => false, 'message' => '', 'data' => null];

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        switch ($action) {
            case 'login':
                // API Login
                $data = json_decode(file_get_contents('php://input'), true);
                
                if (!$data) {
                    $data = $_POST;
                }
                
                if (isset($data['email']) && isset($data['password'])) {
                    $email = sanitize_input($data['email']);
                    $password = $data['password'];
                    
                    // Check user exists
                    $sql = "SELECT * FROM users WHERE email = '$email' AND is_active = TRUE";
                    $result = mysqli_query($conn, $sql);
                    
                    if (mysqli_num_rows($result) == 1) {
                        $user = mysqli_fetch_assoc($result);
                        
                        if (password_verify($password, $user['password'])) {
                            // Generate API token
                            $token = bin2hex(random_bytes(32));
                            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                            
                            // Store token in session
                            $_SESSION['api_token'] = $token;
                            $_SESSION['api_token_expires'] = $expires;
                            $_SESSION['user_id'] = $user['user_id'];
                            $_SESSION['email'] = $user['email'];
                            $_SESSION['role'] = $user['role'];
                            $_SESSION['name'] = $user['name'];
                            
                            // Update last login
                            mysqli_query($conn, "UPDATE users SET last_login = NOW() WHERE user_id = '{$user['user_id']}'");
                            
                            $response['success'] = true;
                            $response['message'] = 'Login successful';
                            $response['data'] = [
                                'token' => $token,
                                'expires' => $expires,
                                'user' => [
                                    'id' => $user['user_id'],
                                    'name' => $user['name'],
                                    'email' => $user['email'],
                                    'role' => $user['role']
                                ]
                            ];
                        } else {
                            $response['message'] = 'Invalid credentials';
                        }
                    } else {
                        $response['message'] = 'User not found or inactive';
                    }
                } else {
                    $response['message'] = 'Email and password required';
                }
                break;
                
            case 'register':
                // API Registration
                $data = json_decode(file_get_contents('php://input'), true);
                
                if (!$data) {
                    $data = $_POST;
                }
                
                $required = ['name', 'email', 'phone', 'id_number', 'password'];
                $valid = true;
                
                foreach ($required as $field) {
                    if (!isset($data[$field]) || empty($data[$field])) {
                        $response['message'] = "Field '$field' is required";
                        $valid = false;
                        break;
                    }
                }
                
                if ($valid) {
                    $name = sanitize_input($data['name']);
                    $email = sanitize_input($data['email']);
                    $phone = sanitize_input($data['phone']);
                    $id_number = sanitize_input($data['id_number']);
                    $password = $data['password'];
                    
                    if (!validate_email($email)) {
                        $response['message'] = 'Invalid email address';
                    } elseif (!validate_phone($phone)) {
                        $response['message'] = 'Invalid phone number';
                    } elseif (strlen($password) < 8) {
                        $response['message'] = 'Password must be at least 8 characters';
                    } else {
                        // Check if email exists
                        $check_email = "SELECT user_id FROM users WHERE email = '$email'";
                        $result_email = mysqli_query($conn, $check_email);
                        
                        if (mysqli_num_rows($result_email) > 0) {
                            $response['message'] = 'Email already registered';
                        } else {
                            // Check if ID number exists
                            $check_id = "SELECT user_id FROM users WHERE id_number = '$id_number'";
                            $result_id = mysqli_query($conn, $check_id);
                            
                            if (mysqli_num_rows($result_id) > 0) {
                                $response['message'] = 'ID number already registered';
                            } else {
                                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                                
                                $sql = "INSERT INTO users (name, email, phone, id_number, password) 
                                        VALUES ('$name', '$email', '$phone', '$id_number', '$hashed_password')";
                                
                                if (mysqli_query($conn, $sql)) {
                                    $user_id = mysqli_insert_id($conn);
                                    
                                    $response['success'] = true;
                                    $response['message'] = 'Registration successful';
                                    $response['data'] = [
                                        'user_id' => $user_id,
                                        'name' => $name,
                                        'email' => $email
                                    ];
                                } else {
                                    $response['message'] = 'Registration failed: ' . mysqli_error($conn);
                                }
                            }
                        }
                    }
                }
                break;
                
            case 'logout':
                // API Logout
                unset($_SESSION['api_token']);
                unset($_SESSION['api_token_expires']);
                
                $response['success'] = true;
                $response['message'] = 'Logged out successfully';
                break;
                
            case 'verify_token':
                // Verify API token
                $headers = getallheaders();
                $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : '';
                
                if (!empty($token) && isset($_SESSION['api_token']) && $token == $_SESSION['api_token']) {
                    if (isset($_SESSION['api_token_expires']) && strtotime($_SESSION['api_token_expires']) > time()) {
                        $response['success'] = true;
                        $response['message'] = 'Token is valid';
                    } else {
                        $response['message'] = 'Token has expired';
                    }
                } else {
                    $response['message'] = 'Invalid token';
                }
                break;
                
            default:
                $response['message'] = 'Invalid action';
        }
        break;
        
    case 'GET':
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        switch ($action) {
            case 'profile':
                // Get user profile
                $headers = getallheaders();
                $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : '';
                
                if (verifyToken($token)) {
                    $user_id = $_SESSION['user_id'] ?? 0;
                    
                    if ($user_id > 0) {
                        $sql = "SELECT user_id, name, email, phone, id_number, role, created_at, last_login 
                                FROM users 
                                WHERE user_id = '$user_id'";
                        $result = mysqli_query($conn, $sql);
                        
                        if ($row = mysqli_fetch_assoc($result)) {
                            $response['success'] = true;
                            $response['data'] = $row;
                        } else {
                            $response['message'] = 'User not found';
                        }
                    } else {
                        $response['message'] = 'User not authenticated';
                    }
                } else {
                    $response['message'] = 'Invalid or expired token';
                }
                break;
                
            default:
                $response['message'] = 'Invalid action';
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
    
    return isset($_SESSION['api_token']) && 
           $_SESSION['api_token'] == $token && 
           isset($_SESSION['api_token_expires']) && 
           strtotime($_SESSION['api_token_expires']) > time();
}

echo json_encode($response);
?>
[file content end]