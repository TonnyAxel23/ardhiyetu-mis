<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../ai/LandValuationAI.php';

use ArdhiYetu\AI\LandValuationAI;

// CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Authentication
$apiKey = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!$apiKey || $apiKey !== AI_API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$ai = new LandValuationAI($conn);

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'No data provided']);
            exit();
        }
        
        // Validate required fields
        $required = ['location', 'size'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Missing required field: $field"]);
                exit();
            }
        }
        
        try {
            $result = $ai->estimateValue($data);
            
            // Log the prediction
            if (isset($_SESSION['user_id'])) {
                $predictionData = [
                    'user_id' => $_SESSION['user_id'],
                    'prediction_type' => 'valuation',
                    'input_data' => json_encode($data),
                    'output_data' => json_encode($result),
                    'model_version' => '1.0',
                    'confidence_score' => $result['confidence']
                ];
                
                $stmt = $conn->prepare("
                    INSERT INTO ai_predictions 
                    (user_id, prediction_type, input_data, output_data, model_version, confidence_score)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    'issssd',
                    $predictionData['user_id'],
                    $predictionData['prediction_type'],
                    $predictionData['input_data'],
                    $predictionData['output_data'],
                    $predictionData['model_version'],
                    $predictionData['confidence_score']
                );
                $stmt->execute();
            }
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $result,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Valuation failed',
                'message' => $e->getMessage()
            ]);
        }
        break;
        
    case 'GET':
        if (isset($_GET['location'])) {
            $location = $_GET['location'];
            $history = $ai->getValuationHistory($location, 10);
            
            echo json_encode([
                'success' => true,
                'location' => $location,
                'history' => $history
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Location parameter required']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}
?>